<?php

namespace App\Console\Commands;

use App\Jobs\SendCertificateEmailJob;
use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Transport;

/**
 * Requeues certificate emails that never reached their recipient.
 *
 * Gmail caps a free account at roughly 500 messages per day, so this command
 * queues a bounded batch (default 400) and is meant to be run once per day
 * until the backlog clears. It refuses to run while SMTP authentication is
 * failing, so a broken mail password does not burn the retry attempts of the
 * whole backlog.
 */
class ResendFailedCertificateEmails extends Command
{
    protected $signature = 'certificates:resend-emails
        {--limit=400 : Maximum emails to queue in this batch}
        {--dry-run : List what would be queued without queuing anything}
        {--skip-smtp-check : Queue without verifying SMTP credentials first}
        {--include-never-queued : Also include certificates that were never queued at all}';

    protected $description = 'Requeue certificate emails that were never delivered, in batches that respect the daily sending limit';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('skip-smtp-check') && ! $this->smtpCredentialsWork()) {
            return self::FAILURE;
        }

        $query = Certificate::query()
            ->whereNull('email_sent_at')
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->whereNotNull('stamped_pdf_path')
            ->where('stamped_pdf_path', '<>', '');

        if (! $this->option('include-never-queued')) {
            $query->whereNotNull('email_delivery_status');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Nothing to resend: no undelivered certificates with a usable email and PDF.');

            return self::SUCCESS;
        }

        $this->line("Undelivered certificates eligible for resend: {$total}");
        $this->line('Queuing up to ' . $limit . ' in this batch.' . ($dryRun ? ' (DRY RUN)' : ''));
        $this->newLine();

        $queued = 0;
        $skippedInvalid = 0;
        $skippedMissingPdf = 0;

        $query->orderBy('created_at')->limit($limit)->each(
            function (Certificate $certificate) use (&$queued, &$skippedInvalid, &$skippedMissingPdf, $dryRun) {
                $email = trim((string) $certificate->email);

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skippedInvalid++;
                    if (! $dryRun) {
                        $certificate->forceFill([
                            'email_delivery_status' => Certificate::EMAIL_STATUS_SKIPPED_INVALID_EMAIL,
                            'email_failure_message' => 'Certificate recipient email address is invalid.',
                        ])->save();
                    }
                    $this->warn("  skip (invalid email)  {$certificate->certificate_code}  {$email}");

                    return;
                }

                if (! $this->stampedPdfExists((string) $certificate->stamped_pdf_path)) {
                    $skippedMissingPdf++;
                    $this->warn("  skip (PDF missing)    {$certificate->certificate_code}  {$certificate->stamped_pdf_path}");

                    return;
                }

                if (! $dryRun) {
                    $certificate->forceFill([
                        'email_delivery_status' => Certificate::EMAIL_STATUS_QUEUED,
                        'email_queued_at' => now(),
                        'email_last_attempt_at' => null,
                        'email_failed_at' => null,
                        'email_failure_message' => null,
                    ])->save();

                    SendCertificateEmailJob::dispatch($certificate->id);
                }

                $queued++;
                $this->line("  queued                {$certificate->certificate_code}  {$email}");
            }
        );

        $this->newLine();
        $this->info(($dryRun ? 'Would queue' : 'Queued') . ": {$queued}");
        if ($skippedInvalid > 0) {
            $this->warn("Skipped (invalid email): {$skippedInvalid}");
        }
        if ($skippedMissingPdf > 0) {
            $this->warn("Skipped (PDF missing): {$skippedMissingPdf}");
        }

        $remaining = max(0, $total - $queued - $skippedInvalid - $skippedMissingPdf);
        if ($remaining > 0) {
            $this->line("Remaining after this batch: {$remaining} — run again tomorrow to continue.");
        }

        if (! $dryRun && $queued > 0) {
            $this->newLine();
            $this->line('Ensure a queue worker is running so the batch is actually sent:');
            $this->line('  php artisan queue:work');
        }

        return self::SUCCESS;
    }

    /**
     * Verify the configured SMTP credentials before queuing a batch, so a bad
     * mail password does not consume every job's retry attempts.
     */
    private function smtpCredentialsWork(): bool
    {
        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');
        $username = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');

        if ($host === '' || $username === '') {
            $this->error('SMTP is not configured (missing host or username). Check MAIL_* in .env.');

            return false;
        }

        $dsn = sprintf(
            'smtp://%s:%s@%s:%d',
            rawurlencode($username),
            rawurlencode($password),
            $host,
            $port ?: 587
        );

        try {
            $transport = Transport::fromDsn($dsn);
            $transport->start();
        } catch (\Throwable $e) {
            $this->error('SMTP authentication failed — not queuing anything.');
            $this->line('  ' . mb_substr($e->getMessage(), 0, 200));
            $this->newLine();
            $this->line('Fix MAIL_PASSWORD in .env (Gmail requires an App Password:');
            $this->line('https://myaccount.google.com/apppasswords), then run this command again.');
            $this->line('Use --skip-smtp-check to override this guard.');

            return false;
        }

        $this->info("SMTP authentication OK ({$username}).");

        return true;
    }

    private function stampedPdfExists(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return Storage::disk('local')->exists($path)
            || Storage::disk('public')->exists($path);
    }
}
