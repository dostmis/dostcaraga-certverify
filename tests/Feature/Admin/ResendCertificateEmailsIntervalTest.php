<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sending a whole backlog at once makes Gmail throttle SMTP logins (454), so
 * the resend command can stagger jobs with --interval.
 */
class ResendCertificateEmailsIntervalTest extends TestCase
{
    use RefreshDatabase;

    private function failedCertificate(int $n): Certificate
    {
        return Certificate::create([
            'certificate_code' => "TEST-FAILED-00{$n}",
            'participant_name' => "Participant {$n}",
            'email' => "participant{$n}@example.com",
            'training_title' => 'Test Training',
            'training_date' => '2026-03-01',
            'issuing_office' => 'DOST Caraga - Test Office',
            'public_token' => (string) Str::uuid(),
            'stamped_pdf_path' => 'certificates/test.pdf',
            'email_delivery_status' => Certificate::EMAIL_STATUS_FAILED,
            'created_at' => now()->addSeconds($n),
        ]);
    }

    public function test_interval_staggers_each_queued_job(): void
    {
        Queue::fake();
        Storage::fake('local');
        Storage::disk('local')->put('certificates/test.pdf', 'pdf');
        $this->freezeTime();

        foreach ([1, 2, 3] as $n) {
            $this->failedCertificate($n);
        }

        $this->artisan('certificates:resend-emails', ['--skip-smtp-check' => true, '--interval' => 60])
            ->assertSuccessful();

        $delays = [];
        Queue::assertPushed(SendCertificateEmailJob::class, function (SendCertificateEmailJob $job) use (&$delays) {
            $delays[] = (int) now()->diffInSeconds($job->delay);

            return true;
        });
        sort($delays);

        $this->assertSame([0, 60, 120], $delays);
    }

    public function test_without_interval_jobs_are_available_immediately(): void
    {
        Queue::fake();
        Storage::fake('local');
        Storage::disk('local')->put('certificates/test.pdf', 'pdf');
        $this->freezeTime();

        $this->failedCertificate(1);
        $this->failedCertificate(2);

        $this->artisan('certificates:resend-emails', ['--skip-smtp-check' => true])
            ->assertSuccessful();

        Queue::assertPushed(SendCertificateEmailJob::class, 2);
        Queue::assertPushed(
            SendCertificateEmailJob::class,
            fn (SendCertificateEmailJob $job) => (int) now()->diffInSeconds($job->delay) === 0
        );
    }
}
