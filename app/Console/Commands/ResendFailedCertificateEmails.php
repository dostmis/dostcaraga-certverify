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
        {--include-never-queued : Also include certificates that were never queued at all}
        {--redeliver-sent-since= : Also re-send certificates already marked sent on/after this date (YYYY-MM-DD). Use to recover from a period when the mail provider accepted messages but then blocked them.}
        {--only-email= : Restrict the batch to a single recipient address (useful for targeted recovery)}
        {--interval=0 : Seconds between queued sends. Spreads the batch out so Gmail does not throttle logins (454).}';

    protected $description = 'Requeue certificate emails that were never delivered, in batches that respect the daily sending limit';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $interval = max(0, (int) $this->option('interval'));

        if (! $dryRun && ! $this->option('skip-smtp-check') && ! $this->smtpCredentialsWork()) {
            return self::FAILURE;
        }

        $redeliverSince = $this->option('redeliver-sent-since');
        $onlyEmail = trim((string) $this->option('only-email'));

        $query = Certificate::query()
            ->where(function ($q) use ($redeliverSince) {
                $q->whereNull('email_sent_at');

                // Recovery path: a provider can accept a message and then block
                // it, leaving the certificate marked sent although it never
                // arrived. Allow those to be deliberately re-sent.
                if ($redeliverSince) {
                    $q->orWhere('email_sent_at', '>=', $redeliverSince);
                }
            })
            ->when($onlyEmail !== '', fn ($q) => $q->where('email', 'ILIKE', $onlyEmail))
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->whereNotNull('stamped_pdf_path')
            ->where('stamped_pdf_path', '<>', '')
            // Never resend certificates an administrator has deliberately held
            // back (e.g. the address on file is not the participant's).
            ->where(function ($q) {
                $q->whereNull('email_delivery_status')
                    ->orWhere('email_delivery_status', '<>', Certificate::EMAIL_STATUS_HELD);
            });

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
        $startAt = now();

        $query->orderBy('created_at')->limit($limit)->each(
            function (Certificate $certificate) use (&$queued, &$skippedInvalid, &$skippedMissingPdf, $dryRun, $interval, $startAt) {
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

                    // Stagger sends: each job becomes available one interval
                    // after the previous, so the worker logs in to SMTP at a
                    // steady pace instead of in a burst.
                    SendCertificateEmailJob::dispatch($certificate->id)
                        ->delay($startAt->copy()->addSeconds($queued * $interval));
                }

                $sendAt = $interval > 0 ? '  at ' . $startAt->copy()->addSeconds($queued * $interval)->format('Y-m-d H:i:s') : '';
                $queued++;
                $this->line("  queued                {$certificate->certificate_code}  {$email}{$sendAt}");
            }
        );

        $this->newLine();
        $this->info(($dryRun ? 'Would queue' : 'Queued') . ": {$queued}");
        if ($interval > 0 && $queued > 0) {
            $this->line(sprintf(
                'Spaced %ds apart; last send at %s.',
                $interval,
                $startAt->copy()->addSeconds(($queued - 1) * $interval)->format('Y-m-d H:i:s')
            ));
        }
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
            $message = $e->getMessage();

            // Gmail returns 454 when too many logins happen in a short window.
            // That is a temporary throttle, NOT a credentials problem, and
            // retrying makes it worse — so give very different advice.
            if (str_contains($message, '454')) {
                $this->error('SMTP is temporarily rate-limited (454) — not queuing anything.');
                $this->newLine();
                $this->line('Your mail password is fine. Gmail is throttling logins because too many');
                $this->line('were attempted in a short window. Do NOT keep retrying: each attempt can');
                $this->line('extend the block. Wait (typically 1-24 hours) and run this again.');
                $this->newLine();
                $this->line('To avoid recurrence, send smaller batches (--limit) or move to a');
                $this->line('transactional mail provider instead of Gmail SMTP.');

                return false;
            }

            if (str_contains($message, '535')) {
                $this->error('SMTP credentials were rejected (535) — not queuing anything.');
                $this->newLine();
                $this->line('Fix MAIL_PASSWORD in .env (Gmail requires an App Password:');
                $this->line('https://myaccount.google.com/apppasswords), run `php artisan config:clear`,');
                $this->line('then run this command again.');

                return false;
            }

            $this->error('SMTP connection failed — not queuing anything.');
            $this->line('  ' . mb_substr($message, 0, 200));
            $this->newLine();
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
