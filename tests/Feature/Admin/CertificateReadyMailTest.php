<?php

namespace Tests\Feature\Admin;

use App\Mail\CertificateReadyMail;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The certificate email must link to the PDF rather than attach it. Bulk
 * identical attachments are a strong spam signal and were causing Gmail to
 * block delivery outright.
 */
class CertificateReadyMailTest extends TestCase
{
    use RefreshDatabase;

    private function certificate(): Certificate
    {
        return Certificate::create([
            'certificate_code' => 'TEST-MAIL-001',
            'participant_name' => 'Mail Participant',
            'email' => 'participant@example.com',
            'training_title' => 'Test Training',
            'training_date' => '2026-03-01',
            'issuing_office' => 'DOST Caraga - Test Office',
            'public_token' => (string) Str::uuid(),
            'stamped_pdf_path' => 'certificates/test.pdf',
        ]);
    }

    public function test_the_certificate_pdf_is_not_attached(): void
    {
        $mail = (new CertificateReadyMail($this->certificate()))->build();

        $this->assertCount(
            0,
            $mail->attachments,
            'The certificate PDF must be linked, not attached - attachments get the mail spam-blocked.'
        );
    }

    public function test_the_email_contains_a_download_and_verify_link(): void
    {
        $certificate = $this->certificate();
        $body = (new CertificateReadyMail($certificate))->build()->render();

        $this->assertStringContainsString($certificate->public_token, $body);
        $this->assertStringContainsString('/download?t=', $body);
        $this->assertStringContainsString('/verify?t=', $body);
    }

    public function test_the_email_does_not_claim_a_pdf_is_attached(): void
    {
        $body = (new CertificateReadyMail($this->certificate()))->build()->render();

        $this->assertStringNotContainsStringIgnoringCase('attached to this email', $body);
    }
}
