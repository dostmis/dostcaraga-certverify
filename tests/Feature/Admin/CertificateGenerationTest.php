<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\CertificateAdminController;
use App\Jobs\SendCertificateEmailJob;
use App\Mail\CertificateReadyMail;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class CertificateGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_stamps_downloadable_pdfs_and_queues_emails_without_blockchain_jobs(): void
    {
        Storage::fake('local');
        Bus::fake();
        Mail::fake();
        Http::preventStrayRequests();
        config(['services.hedera.enabled' => true]);

        $template = new \FPDF('L');
        $template->AddPage();
        $template->SetFont('Helvetica', '', 18);
        $template->Text(20, 30, 'Certificate of Participation');
        Storage::disk('local')->put('templates/test.pdf', $template->Output('S'));

        $controller = app(CertificateAdminController::class);
        $generate = new ReflectionMethod($controller, 'generateCertificatesFromPayload');
        $certificates = $generate->invoke($controller, [
            'training_title' => 'Certificate Generation Regression Test',
            'training_date_from' => '2026-06-01',
            'training_date_to' => '2026-06-02',
            'issuing_office' => 'DOST Caraga - Innovation Unit',
            'caption_text' => 'For participating in the training.',
        ], [
            ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com'],
            ['name' => 'Maria Santos'],
        ], 'templates/test.pdf');

        $this->assertCount(2, $certificates);
        $this->assertDatabaseCount('certificates', 2);
        $this->assertNotSame($certificates[0]->public_token, $certificates[1]->public_token);
        $this->assertNotSame($certificates[0]->certificate_code, $certificates[1]->certificate_code);
        Bus::assertNothingDispatched();

        foreach ($certificates as $certificate) {
            $this->assertTrue($certificate->isValid());
            Storage::disk('local')->assertExists($certificate->source_pdf_path);
            Storage::disk('local')->assertExists($certificate->stamped_pdf_path);
            $pdf = Storage::disk('local')->get($certificate->stamped_pdf_path);
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertStringContainsString('/Subtype /Image', $pdf);
            $parser = new Fpdi;
            $this->assertSame(1, $parser->setSourceFile(Storage::disk('local')->path($certificate->stamped_pdf_path)));

            $this->get(route('cert.verify', ['t' => $certificate->public_token]))
                ->assertOk()->assertSee($certificate->participant_name);
            $this->get(route('cert.preview', ['t' => $certificate->public_token]))
                ->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->get(route('cert.download', ['t' => $certificate->public_token]))
                ->assertOk()->assertDownload($certificate->certificate_code.'.pdf');
        }

        $queueEmails = new ReflectionMethod($controller, 'queueGeneratedCertificateEmails');
        $this->assertSame([1, 1], $queueEmails->invoke($controller, $certificates));
        Bus::assertDispatchedTimes(SendCertificateEmailJob::class, 1);
        Bus::assertDispatched(SendCertificateEmailJob::class,
            fn ($job) => $job->certificateId === $certificates[0]->id);
        $this->assertSame(Certificate::EMAIL_STATUS_QUEUED, $certificates[0]->fresh()->email_delivery_status);
        $this->assertSame(Certificate::EMAIL_STATUS_SKIPPED_NO_EMAIL, $certificates[1]->fresh()->email_delivery_status);

        (new SendCertificateEmailJob($certificates[0]->id))->handle();
        Mail::assertSent(CertificateReadyMail::class, fn ($mail) => $mail->hasTo('juan@example.com'));
        $this->assertSame(Certificate::EMAIL_STATUS_SENT, $certificates[0]->fresh()->email_delivery_status);
        Http::assertNothingSent();
    }
}
