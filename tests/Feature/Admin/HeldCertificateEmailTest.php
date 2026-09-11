<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A "held" certificate is one an administrator deliberately withheld because
 * the address on file is not the participant's. The hold must be authoritative:
 * an already-queued job must neither deliver it nor quietly clear the hold.
 */
class HeldCertificateEmailTest extends TestCase
{
    use RefreshDatabase;

    private function heldCertificate(): Certificate
    {
        return Certificate::create([
            'certificate_code' => 'TEST-HELD-001',
            'participant_name' => 'Held Participant',
            'email' => 'coordinator@example.com',
            'training_title' => 'Test Training',
            'training_date' => '2026-03-01',
            'issuing_office' => 'DOST Caraga - Test Office',
            'public_token' => (string) \Illuminate\Support\Str::uuid(),
            'stamped_pdf_path' => 'certificates/test.pdf',
            'email_delivery_status' => Certificate::EMAIL_STATUS_HELD,
        ]);
    }

    public function test_a_held_certificate_is_not_emailed_by_an_in_flight_job(): void
    {
        Mail::fake();
        $certificate = $this->heldCertificate();

        (new SendCertificateEmailJob($certificate->id))->handle();

        Mail::assertNothingSent();

        $certificate->refresh();
        $this->assertSame(Certificate::EMAIL_STATUS_HELD, $certificate->email_delivery_status);
        $this->assertNull($certificate->email_sent_at);
        // The hold must not even be recorded as an attempt.
        $this->assertNull($certificate->email_last_attempt_at);
    }

    public function test_a_failing_job_does_not_clear_an_administrators_hold(): void
    {
        $certificate = $this->heldCertificate();

        (new SendCertificateEmailJob($certificate->id))->failed(new \RuntimeException('smtp down'));

        $certificate->refresh();
        $this->assertSame(
            Certificate::EMAIL_STATUS_HELD,
            $certificate->email_delivery_status,
            'A failing in-flight job must not downgrade a held certificate to failed.'
        );
        $this->assertNull($certificate->email_failed_at);
    }

    public function test_the_resend_command_skips_held_certificates(): void
    {
        $held = $this->heldCertificate();

        $this->artisan('certificates:resend-emails', ['--dry-run' => true, '--limit' => 50])
            ->assertSuccessful();

        $held->refresh();
        $this->assertSame(Certificate::EMAIL_STATUS_HELD, $held->email_delivery_status);
        $this->assertNull($held->email_queued_at);
    }
}
