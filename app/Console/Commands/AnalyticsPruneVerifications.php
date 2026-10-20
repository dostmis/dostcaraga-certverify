<?php

namespace App\Console\Commands;

use App\Models\CertificateVerification;
use Illuminate\Console\Command;

/**
 * Enforces the 24-month retention window on raw verification rows.
 *
 * The aggregates these rows feed live in analytics.mv_verification_facts and
 * mv_verification_daily, which are refreshed before this runs — so pruning
 * costs no history in the dashboard, only the ability to recompute
 * unique-verifier counts for windows older than the retention period.
 */
class AnalyticsPruneVerifications extends Command
{
    protected $signature = 'analytics:prune-verifications
        {--months=24 : Retention window in months}
        {--chunk=5000 : Rows deleted per statement}';

    protected $description = 'Delete raw certificate verification rows older than the retention window.';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $chunk = max(100, (int) $this->option('chunk'));
        $cutoff = now()->subMonths($months);

        $deleted = 0;
        // Chunked so a long-neglected table cannot lock up in one statement.
        // Postgres has no DELETE ... LIMIT, so each batch collects ids first
        // and deletes by primary key.
        do {
            $ids = CertificateVerification::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            $batch = $ids->isEmpty()
                ? 0
                : CertificateVerification::query()->whereIn('id', $ids)->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Pruned {$deleted} verification row(s) older than {$cutoff->toDateString()} ({$months} months).");

        return self::SUCCESS;
    }
}
