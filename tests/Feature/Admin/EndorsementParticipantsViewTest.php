<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Endorsers can open any package they endorsed and see who is in it: the
 * issued certificates (with their PDFs) once approved, or the names from the
 * uploaded participant list before that. Email addresses are never shown.
 */
class EndorsementParticipantsViewTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put(
            'endorsements/participants/list.csv',
            "name,email\nJuan Dela Cruz,juan@example.com\nMaria Santos,maria@example.com\n"
        );
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_admin' => $role === User::ROLE_REGIONAL_DIRECTOR,
        ]);
    }

    private function endorsement(User $submitter, string $status, array $overrides = []): CertificateEndorsement
    {
        return CertificateEndorsement::create(array_merge([
            'status' => $status,
            'submitted_by' => $submitter->id,
            'participants_count' => 2,
            'generated_count' => $status === CertificateEndorsement::STATUS_RD_APPROVED ? 2 : 0,
            'rd_approved_at' => $status === CertificateEndorsement::STATUS_RD_APPROVED ? now() : null,
            'rd_rejected_at' => $status === CertificateEndorsement::STATUS_RD_REJECTED ? now() : null,
            'participants_file_path' => 'endorsements/participants/list.csv',
            'template_pdf_path' => 'endorsements/templates/test.pdf',
            'payload' => [
                'training_title' => 'Participants View Training',
                'issuing_office' => 'DOST Caraga - Innovation Unit',
                'training_date_from' => '2026-09-01',
            ],
        ], $overrides));
    }

    private function issuedCertificate(CertificateEndorsement $endorsement, string $name): Certificate
    {
        $this->sequence++;
        $pdfPath = "certificates/test-{$this->sequence}.pdf";
        Storage::disk('public')->put($pdfPath, '%PDF-1.4 test certificate');

        return Certificate::create([
            'certificate_code' => sprintf('TEST-VIEW-%03d', $this->sequence),
            'participant_name' => $name,
            'email' => "participant{$this->sequence}@example.com",
            'training_title' => 'Participants View Training',
            'training_date' => '2026-09-01',
            'issuing_office' => 'DOST Caraga - Innovation Unit',
            'stamped_pdf_path' => $pdfPath,
            'certificate_endorsement_id' => $endorsement->id,
            'email_delivery_status' => Certificate::EMAIL_STATUS_SENT,
            'email_sent_at' => now(),
        ]);
    }

    public function test_pending_package_lists_names_from_the_uploaded_file_without_emails(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_ENDORSED);

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.show', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertSee('Pending Approval')
            ->assertSee('waiting for the Regional Director')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Maria Santos')
            ->assertDontSee('juan@example.com')
            ->assertDontSee('maria@example.com');
    }

    public function test_rejected_package_shows_names_and_the_rejection_reason(): void
    {
        $supervisor = $this->user(User::ROLE_UNIT_SUPERVISOR);
        $endorsement = $this->endorsement($supervisor, CertificateEndorsement::STATUS_RD_REJECTED, [
            'rejection_reason' => 'Please fix the spelling of the training title.',
        ]);

        $this->actingAs($supervisor)
            ->get(route('admin.certs.endorsements.show', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertSee('Rejected')
            ->assertSee('Please fix the spelling of the training title.')
            ->assertSee('Juan Dela Cruz');
    }

    public function test_approved_package_without_linked_certificates_falls_back_to_the_file(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_RD_APPROVED);

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.show', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertSee('cannot be opened from here yet')
            ->assertSee('Maria Santos');
    }

    public function test_approved_package_lists_certificates_with_pdf_links_and_no_emails(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_RD_APPROVED);
        $certificate = $this->issuedCertificate($endorsement, 'Pedro Penduko');

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.show', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertSee('Pedro Penduko')
            ->assertSee($certificate->certificate_code)
            ->assertSee(route('admin.certs.endorsements.certificate', [
                'id' => $endorsement->id,
                'certificateId' => $certificate->id,
            ]), false)
            ->assertDontSee($certificate->email);
    }

    public function test_search_filters_the_names(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_ENDORSED);

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.show', ['id' => $endorsement->id, 'q' => 'maria']))
            ->assertOk()
            ->assertSee('Maria Santos')
            ->assertDontSee('Juan Dela Cruz');

        $approved = $this->endorsement($organizer, CertificateEndorsement::STATUS_RD_APPROVED);
        $this->issuedCertificate($approved, 'Pedro Penduko');
        $this->issuedCertificate($approved, 'Liza Soberano');

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.show', ['id' => $approved->id, 'q' => 'liza']))
            ->assertOk()
            ->assertSee('Liza Soberano')
            ->assertDontSee('Pedro Penduko');
    }

    public function test_missing_participant_file_shows_a_friendly_message(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_ENDORSED, [
            'participants_file_path' => 'endorsements/participants/missing.csv',
        ]);

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.show', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertSee('could not be read');
    }

    public function test_endorser_can_open_a_certificate_pdf_from_their_package(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_RD_APPROVED);
        $certificate = $this->issuedCertificate($endorsement, 'Pedro Penduko');

        $response = $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.certificate', [
                'id' => $endorsement->id,
                'certificateId' => $certificate->id,
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_unit_supervisor_can_open_pdfs_from_their_own_package(): void
    {
        $supervisor = $this->user(User::ROLE_UNIT_SUPERVISOR);
        $endorsement = $this->endorsement($supervisor, CertificateEndorsement::STATUS_RD_APPROVED);
        $certificate = $this->issuedCertificate($endorsement, 'Pedro Penduko');

        $this->actingAs($supervisor)
            ->get(route('admin.certs.endorsements.certificate', [
                'id' => $endorsement->id,
                'certificateId' => $certificate->id,
            ]))
            ->assertOk();
    }

    public function test_a_certificate_from_another_package_cannot_be_opened_through_your_package(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $own = $this->endorsement($organizer, CertificateEndorsement::STATUS_RD_APPROVED);
        $other = $this->endorsement($this->user(User::ROLE_ORGANIZER), CertificateEndorsement::STATUS_RD_APPROVED);
        $otherCertificate = $this->issuedCertificate($other, 'Someone Else');

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.certificate', [
                'id' => $own->id,
                'certificateId' => $otherCertificate->id,
            ]))
            ->assertNotFound();
    }

    public function test_endorsers_cannot_view_another_endorsers_package_or_pdfs(): void
    {
        $endorsement = $this->endorsement($this->user(User::ROLE_ORGANIZER), CertificateEndorsement::STATUS_RD_APPROVED);
        $certificate = $this->issuedCertificate($endorsement, 'Pedro Penduko');
        $intruder = $this->user(User::ROLE_UNIT_SUPERVISOR);

        $this->actingAs($intruder)
            ->get(route('admin.certs.endorsements.show', ['id' => $endorsement->id]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get(route('admin.certs.endorsements.certificate', [
                'id' => $endorsement->id,
                'certificateId' => $certificate->id,
            ]))
            ->assertForbidden();
    }

    public function test_regional_director_can_view_any_package_and_pdf(): void
    {
        $endorsement = $this->endorsement($this->user(User::ROLE_ORGANIZER), CertificateEndorsement::STATUS_RD_APPROVED);
        $certificate = $this->issuedCertificate($endorsement, 'Pedro Penduko');
        $director = $this->user(User::ROLE_REGIONAL_DIRECTOR);

        $this->actingAs($director)
            ->get(route('admin.certs.endorsements.show', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertSee('Pedro Penduko');

        $this->actingAs($director)
            ->get(route('admin.certs.endorsements.certificate', [
                'id' => $endorsement->id,
                'certificateId' => $certificate->id,
            ]))
            ->assertOk();
    }

    public function test_endorsements_tab_links_every_package_to_its_participants(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $pending = $this->endorsement($organizer, CertificateEndorsement::STATUS_ENDORSED);
        $rejected = $this->endorsement($organizer, CertificateEndorsement::STATUS_RD_REJECTED);

        $this->actingAs($organizer)
            ->get(route('admin.certs.index', ['group' => 'endorsements']))
            ->assertOk()
            ->assertSee('View participants')
            ->assertSee(route('admin.certs.endorsements.show', ['id' => $pending->id]), false)
            ->assertSee(route('admin.certs.endorsements.show', ['id' => $rejected->id]), false);
    }

    public function test_activity_title_links_to_the_package_page(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_ENDORSED);
        $url = route('admin.certs.endorsements.show', ['id' => $endorsement->id]);

        $this->actingAs($organizer)
            ->get(route('admin.certs.index', ['group' => 'endorsements']))
            ->assertOk()
            ->assertSee('<a class="cert-title-link" href="'.$url.'"', false);
    }

    public function test_preview_button_opens_an_issued_certificate_when_there_is_one(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_RD_APPROVED);
        $certificate = $this->issuedCertificate($endorsement, 'Pedro Penduko');

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.show', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertSee('Preview certificate')
            ->assertSee(route('admin.certs.endorsements.certificate', [
                'id' => $endorsement->id,
                'certificateId' => $certificate->id,
            ]), false)
            ->assertDontSee(route('admin.certs.endorsements.sample', ['id' => $endorsement->id]), false);
    }

    public function test_preview_button_opens_a_sample_before_certificates_are_issued(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_ENDORSED);

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.show', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertSee('Preview certificate')
            ->assertSee(route('admin.certs.endorsements.sample', ['id' => $endorsement->id]), false);
    }

    public function test_endorser_sample_is_marked_as_a_preview(): void
    {
        $this->storeTemplate();
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_ENDORSED);

        $response = $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.sample', ['id' => $endorsement->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $text = $this->pdfText($response->getContent());
        $this->assertStringContainsString('PREVIEW ONLY - NOT AN ISSUED CERTIFICATE', $text);
        $this->assertStringContainsString('Juan Dela Cruz', $text);
    }

    public function test_regional_director_preview_has_no_endorser_banner(): void
    {
        $this->storeTemplate();
        $endorsement = $this->endorsement($this->user(User::ROLE_ORGANIZER), CertificateEndorsement::STATUS_ENDORSED);

        $response = $this->actingAs($this->user(User::ROLE_REGIONAL_DIRECTOR))
            ->get(route('admin.certs.endorsements.preview', ['id' => $endorsement->id]))
            ->assertOk();

        $this->assertStringNotContainsString('PREVIEW ONLY', $this->pdfText($response->getContent()));
    }

    public function test_endorsers_cannot_preview_another_endorsers_package(): void
    {
        $this->storeTemplate();
        $endorsement = $this->endorsement($this->user(User::ROLE_ORGANIZER), CertificateEndorsement::STATUS_ENDORSED);

        $this->actingAs($this->user(User::ROLE_ORGANIZER))
            ->get(route('admin.certs.endorsements.sample', ['id' => $endorsement->id]))
            ->assertForbidden();
    }

    private function storeTemplate(): void
    {
        $template = new \FPDF('L');
        $template->AddPage();
        $template->SetFont('Helvetica', '', 18);
        $template->Text(20, 30, 'Certificate of Participation');
        Storage::disk('local')->put('endorsements/templates/test.pdf', $template->Output('S'));
    }

    private function pdfText(string $pdf): string
    {
        exec('command -v pdftotext', $found, $status);
        if ($status !== 0) {
            $this->markTestSkipped('pdftotext is not installed.');
        }

        $path = tempnam(sys_get_temp_dir(), 'cert-preview-');
        file_put_contents($path, $pdf);
        $text = (string) shell_exec('pdftotext '.escapeshellarg($path).' -');
        @unlink($path);

        return $text;
    }

    public function test_old_delivery_links_redirect_to_the_package_page(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $endorsement = $this->endorsement($organizer, CertificateEndorsement::STATUS_RD_APPROVED);

        $this->actingAs($organizer)
            ->get(route('admin.certs.endorsements.delivery', ['id' => $endorsement->id, 'problems' => 1]))
            ->assertRedirect(route('admin.certs.endorsements.show', ['id' => $endorsement->id, 'problems' => 1]));
    }
}
