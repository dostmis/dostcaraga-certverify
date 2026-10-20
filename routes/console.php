<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly analytics rebuild (also runs after each successful certificate
// generation via RefreshAnalyticsJob). withoutOverlapping guards the slow
// REFRESH from stacking if a run runs long.
Schedule::command('analytics:refresh')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->onOneServer();

// Retention for raw verification rows. Runs after the refresh above, so the
// verification materialized views have already absorbed the history being
// pruned (see AnalyticsPruneVerifications).
Schedule::command('analytics:prune-verifications')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();
