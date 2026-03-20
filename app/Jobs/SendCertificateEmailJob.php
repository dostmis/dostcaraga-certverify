<?php

namespace App\Jobs;

use App\Mail\CertificateReadyMail;
use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCertificateEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $certificateId)
    {
    }

    public function handle(): void
    {
        $certificate = Certificate::find($this->certificateId);
        if (! $certificate) {
            return;
        }

        $certificate->forceFill([
            'email_last_attempt_at' => now(),
        ])->save();

        $email = trim((string) ($certificate->email ?? ''));
        if ($email === '') {
            $certificate->forceFill([
                'email_delivery_status' => Certificate::EMAIL_STATUS_SKIPPED_NO_EMAIL,
                'email_failure_message' => 'Certificate recipient does not have an email address.',
            ])->save();

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $certificate->forceFill([
                'email_delivery_status' => Certificate::EMAIL_STATUS_SKIPPED_INVALID_EMAIL,
                'email_failure_message' => 'Certificate recipient email address is invalid.',
            ])->save();

            return;
        }

        if (empty($certificate->stamped_pdf_path)) {
            throw new \RuntimeException('Stamped certificate PDF is missing.');
        }

        Mail::to($email)->send(new CertificateReadyMail($certificate));

        $certificate->forceFill([
            'email_delivery_status' => Certificate::EMAIL_STATUS_SENT,
            'email_sent_at' => now(),
            'email_failed_at' => null,
            'email_failure_message' => null,
        ])->save();
    }

    public function failed(\Throwable $exception): void
    {
        $certificate = Certificate::find($this->certificateId);
        if (! $certificate) {
            return;
        }

        $certificate->forceFill([
            'email_delivery_status' => Certificate::EMAIL_STATUS_FAILED,
            'email_failed_at' => now(),
            'email_failure_message' => mb_substr($exception->getMessage(), 0, 1000),
        ])->save();
    }
}
