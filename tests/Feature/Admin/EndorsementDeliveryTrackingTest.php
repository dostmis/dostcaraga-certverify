<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\CertificateAdminController;
use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Endorsers can follow whether the certificates from their approved package
 * reached participants, with plain-language reasons for anything that did not.
 */
class EndorsementDeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => $role === User::ROLE_REGIONAL_DIRECTOR,
        ]);
    }

    private function approvedEndorsement(User $submitter, array $overrides = []): CertificateEndorsement
    {
        return CertificateEndorsement::create(array_merge([
            'status' => CertificateEndorsement::STATUS_RD_APPROVED,
            'submitted_by' => $submitter->id,
            'participants_count' => 4,
            'generated_count' => 4,
            'rd_approved_at' => now(),
            'participants_file_path' => 'endorsements/participants/test.json',
            'template_pdf_path' => 'endorsements/templates/test.pdf',
            'payload' => [
                'training_title' => 'Delivery Tracking Training',
                'issuing_office' => 'DOST Caraga - Innovation Unit',
                'training_date_from' => '2026-09-01',
            ],
        ], $overrides));
    }

    private function certificate(array $attributes = []): Certificate
    {
        $this->sequence++;

        return Certificate::create(array_merge([
            'certificate_code' => sprintf('TEST-TRACK-%03d', $this->sequence),
            'participant_name' => "Participant {$this->sequence}",
            'email' => "participant{$this->sequence}@example.com",
            'training_title' => 'Delivery Tracking Training',
            'training_date' => '2026-09-01',
            'issuing_office' => 'DOST Caraga - Innovation Unit',
            'stamped_pdf_path' => 'certificates/test.pdf',
        ], $attributes));
    }

    private function sentCertificate(CertificateEndorsement $endorsement, array $attributes = []): Certificate
    {
        return $this->certificate(array_merge([
            'certificate_endorsement_id' => $endorsement->id,
            'email_delivery_status' => Certificate::EMAIL_STATUS_SENT,
            'email_sent_at' => now(),
        ], $attributes));
    }

    public function test_generated_certificates_are_linked_to_their_endorsement(): void
    {
        Storage::fake('local');
        Bus::fake();
        $endorsement = $this->approvedEndorsement($this->user(User::ROLE_ORGANIZER));

        $template = new \FPDF('L');
        $template->AddPage();
        $template->SetFont('Helvetica', '', 18);
        $template->Text(20, 30, 'Certificate of Participation');
        Storage::disk('local')->put('templates/test.pdf', $template->Output('S'));

        $controller = app(CertificateAdminController::class);
        $generate = new ReflectionMethod($controller, 'generateCertificatesFromPayload');
        $certificates = $generate->invoke($controller, $endorsement->payload, [
            ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com'],
            ['name' => 'Maria Santos'],
        ], 'templates/test.pdf', false, $endorsement->id);

        $this->assertCount(2, $certificates);
        foreach ($certificates as $certificate) {
            $this->assertSame($endorsement->id, (int) $certificate->certificate_endorsement_id);
        }
        $this->assertSame(2, $endorsement->certificates()->count());
    }

    public function test_endorsements_tab_shows_delivery_counts_to_the_submitter(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->approvedEndorsement($organizer);
        $this->sentCertificate($endorsement);
        $this->sentCertificate($endorsement);
        $this->certificate([
            'certificate_endorsement_id' => $endorsement->id,
            'email_delivery_status' => Certificate::EMAIL_STATUS_FAILED,
            'email_failed_at' => now(),
            'email_failure_message' => 'Expected response code "235" but got code "454"',
        ]);
        $this->certificate([
            'certificate_endorsement_id' => $endorsement->id,
            'email' => null,
            'email_delivery_status' => Certificate::EMAIL_STATUS_SKIPPED_NO_EMAIL,
        ]);

        $this->actingAs($organizer)
            ->get(route('admin.certs.index', ['group' => 'endorsements']))
            ->assertOk()
            ->assertSee('2 sent')
            ->assertSee('1 failed')
            ->assertSee('1 no email')
            ->assertSee(route('admin.certs.endorsements.delivery', ['id' => $endorsement->id]), false);
    }

    public function test_approved_packages_without_linked_certificates_say_not_tracked(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $this->approvedEndorsement($organizer);

        $this->actingAs($organizer)
            ->get(route('admin.certs.index', ['group' => 'endorsements']))
            ->assertOk()
            ->assertSee('Not tracked yet');
    }

    public function test_delivery_page_explains_problems_in_plain_language(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->approvedEndorsement($organizer);
        $this->certificate([
            'participant_name' => 'Busy Server Participant',
            'certificate_endorsement_id' => $endorsement->id,
            'email_delivery_status' => Certificate::EMAIL_STATUS_FAILED,
            'email_failed_at' => now(),
            'email_failure_message' => 'Failed to authenticate on SMTP server. Expected response code "235" but got code "454", with message "454-4.7.0 Too many login attempts"',
        ]);
        $this->certificate([
            'participant_name' => 'Typo Address Participant',
            'certificate_endorsement_id' => $endorsement->id,
            'email' => 'someone-at-example.com',
            'email_delivery_status' => Certificate::EMAIL_STATUS_SKIPPED_INVALID_EMAIL,
        ]);
        $this->sentCertificate($endorsement, ['participant_name' => 'Delivered Participant']);

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.delivery', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertSee('Delivery Tracking Training')
            ->assertSee('Busy Server Participant')
            ->assertSee('The mail server was temporarily busy')
            ->assertDontSee('Expected response code')
            ->assertSee('Typo Address Participant')
            ->assertSee('is not a valid email address')
            ->assertSee('Delivered Participant');
    }

    public function test_delivery_page_can_show_only_problems(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->approvedEndorsement($organizer);
        $this->sentCertificate($endorsement, ['participant_name' => 'Delivered Participant']);
        $this->certificate([
            'participant_name' => 'Missing Email Participant',
            'certificate_endorsement_id' => $endorsement->id,
            'email' => null,
            'email_delivery_status' => Certificate::EMAIL_STATUS_SKIPPED_NO_EMAIL,
        ]);

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.delivery', ['id' => $endorsement->id, 'problems' => 1]))
            ->assertOk()
            ->assertSee('Missing Email Participant')
            ->assertDontSee('Delivered Participant');
    }

    public function test_endorsers_cannot_view_another_endorsers_delivery_page(): void
    {
        $endorsement = $this->approvedEndorsement($this->user(User::ROLE_ORGANIZER));

        $this->actingAs($this->user(User::ROLE_UNIT_SUPERVISOR))
            ->get(route('admin.certs.endorsements.delivery', ['id' => $endorsement->id]))
            ->assertForbidden();
    }

    public function test_regional_director_can_view_any_delivery_page(): void
    {
        $endorsement = $this->approvedEndorsement($this->user(User::ROLE_ORGANIZER));
        $this->sentCertificate($endorsement);

        $this->actingAs($this->user(User::ROLE_REGIONAL_DIRECTOR))
            ->get(route('admin.certs.endorsements.delivery', ['id' => $endorsement->id]))
            ->assertOk();
    }

    public function test_link_command_is_a_dry_run_until_applied(): void
    {
        $approvedAt = Carbon::parse('2026-09-02 10:00:00');
        $endorsement = $this->approvedEndorsement($this->user(User::ROLE_ORGANIZER), [
            'generated_count' => 2,
            'rd_approved_at' => $approvedAt,
        ]);

        // Same training, but from an earlier batch an hour before approval.
        $older = $this->certificate();
        $older->forceFill(['created_at' => $approvedAt->copy()->subHour()])->save();

        $batch = [$this->certificate(), $this->certificate()];
        foreach ($batch as $certificate) {
            $certificate->forceFill(['created_at' => $approvedAt->copy()->subSeconds(2)])->save();
        }

        $this->artisan('certificates:link-endorsements')->assertSuccessful();
        $this->assertSame(0, Certificate::whereNotNull('certificate_endorsement_id')->count());

        $this->artisan('certificates:link-endorsements', ['--apply' => true])->assertSuccessful();
        foreach ($batch as $certificate) {
            $this->assertSame($endorsement->id, (int) $certificate->fresh()->certificate_endorsement_id);
        }
        $this->assertNull($older->fresh()->certificate_endorsement_id);
    }

    public function test_link_command_leaves_ambiguous_packages_for_review(): void
    {
        $approvedAt = Carbon::parse('2026-09-02 10:00:00');
        $this->approvedEndorsement($this->user(User::ROLE_ORGANIZER), [
            'generated_count' => 1,
            'rd_approved_at' => $approvedAt,
        ]);

        foreach ([1, 3] as $secondsBefore) {
            $this->certificate()->forceFill(['created_at' => $approvedAt->copy()->subSeconds($secondsBefore)])->save();
        }

        $this->artisan('certificates:link-endorsements', ['--apply' => true])
            ->expectsOutputToContain('Needs review')
            ->assertSuccessful();

        $this->assertSame(0, Certificate::whereNotNull('certificate_endorsement_id')->count());
    }
}
