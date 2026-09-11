<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-glance answer to "are any certificates still not delivered?".
 *
 * Reports what is outstanding and, for each bucket, what it needs - so an
 * operator can tell the difference between "just run the resend command" and
 * "someone has to collect email addresses".
 */
class CertificateDeliveryStatus extends Command
{
    protected $signature = 'certificates:delivery-status
        {--details : List the outstanding certificates individually}';

    protected $description = 'Show how many certificates have not reached their recipient, and what each group needs';

    public function handle(): int
    {
        $total = Certificate::count();
        $delivered = Certificate::whereNotNull('email_sent_at')->count();
        $undelivered = $total - $delivered;

        $this->newLine();
        $this->line('  <options=bold>CERTIFICATE DELIVERY STATUS</>  ' . now()->format('Y-m-d H:i'));
        $this->newLine();
        $this->line(sprintf('  Total certificates : %d', $total));
        $this->line(sprintf('  Delivered          : %d (%s%%)', $delivered, $total > 0 ? number_format($delivered / $total * 100, 1) : '0'));

        if ($undelivered === 0) {
            $this->newLine();
            $this->info('  All certificates have been delivered.');

            return self::SUCCESS;
        }

        $this->line(sprintf('  <fg=yellow>Not delivered      : %d</>', $undelivered));

        // Buckets, in the order an operator should act on them.
        $neverQueued = $this->bucket()->whereNull('email_delivery_status')->count();

        $sendable = $this->bucket()
            ->whereNotNull('email_delivery_status')
            ->where('email_delivery_status', '<>', Certificate::EMAIL_STATUS_HELD)
            ->whereNotNull('email')->where('email', '<>', '')
            ->where('email', 'LIKE', '%@%')
            ->where('email', 'NOT ILIKE', '%.con')
            ->whereNotNull('stamped_pdf_path')->where('stamped_pdf_path', '<>', '')
            ->count();

        $held = $this->bucket()->where('email_delivery_status', Certificate::EMAIL_STATUS_HELD)->count();

        $badAddress = $this->bucket()
            ->whereNotNull('email')->where('email', '<>', '')
            ->where(fn ($q) => $q->where('email', 'NOT LIKE', '%@%')->orWhere('email', 'ILIKE', '%.con'))
            ->count();

        $noEmail = $this->bucket()
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
            ->count();

        $noPdf = $this->bucket()
            ->whereNotNull('email')->where('email', '<>', '')
            ->where(fn ($q) => $q->whereNull('stamped_pdf_path')->orWhere('stamped_pdf_path', ''))
            ->count();

        $this->newLine();
        $this->line('  <options=bold>What each group needs</>');
        $this->table(
            ['Group', 'Count', 'Action'],
            array_values(array_filter([
                $neverQueued > 0 ? ['Never attempted', $neverQueued, 'certificates:resend-emails --include-never-queued'] : null,
                $sendable > 0 ? ['Ready to resend', $sendable, 'certificates:resend-emails'] : null,
                $held > 0 ? ['Held by admin', $held, 'needs the participant\'s real email, then clear the hold'] : null,
                $badAddress > 0 ? ['Invalid address', $badAddress, 'correct the address on the certificate'] : null,
                $noEmail > 0 ? ['No email on file', $noEmail, 'collect addresses, or distribute by hand'] : null,
                $noPdf > 0 ? ['PDF missing', $noPdf, 'regenerate the certificate PDF'] : null,
            ]))
        );

        $pending = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $this->line("  Queue: {$pending} waiting, {$failedJobs} in failed_jobs");

        $lastSent = Certificate::max('email_sent_at');
        $this->line('  Last successful delivery: ' . ($lastSent ?: 'never'));

        $this->newLine();
        $this->line('  <fg=yellow>Note:</> "delivered" means the mail provider accepted the message.');
        $this->line('  A message can still be blocked or bounced afterwards. Check the sending');
        $this->line('  mailbox for bounce notices, then use --redeliver-sent-since to re-send.');

        if ($this->option('details')) {
            $this->newLine();
            $this->line('  <options=bold>Outstanding certificates</>');
            foreach ($this->bucket()->orderBy('created_at')->get(['certificate_code', 'participant_name', 'email', 'email_delivery_status']) as $c) {
                $this->line(sprintf(
                    '  %-22s %-28s %-34s %s',
                    $c->certificate_code,
                    mb_substr((string) $c->participant_name, 0, 26),
                    $c->email ?: '(no email)',
                    $c->email_delivery_status ?? '(never queued)'
                ));
            }
        }

        return self::SUCCESS;
    }

    private function bucket(): \Illuminate\Database\Eloquent\Builder
    {
        return Certificate::query()->whereNull('email_sent_at');
    }
}
