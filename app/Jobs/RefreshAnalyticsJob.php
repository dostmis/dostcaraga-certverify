<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes the analytics materialized views off the request path.
 *
 * Dispatched from the certificate-generation success callback. It runs entirely
 * outside any transaction and never touches generation state, so it cannot
 * affect the generation rollback path. ShouldBeUnique coalesces bursts (e.g.
 * several packages approved together) into effectively one refresh.
 */
class RefreshAnalyticsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return 'analytics-refresh';
    }

    public function handle(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            Artisan::call('analytics:refresh');
        } catch (\Throwable $e) {
            // Never let an analytics refresh failure surface into generation flow.
            Log::warning('RefreshAnalyticsJob failed.', ['error' => $e->getMessage()]);
        }
    }
}
