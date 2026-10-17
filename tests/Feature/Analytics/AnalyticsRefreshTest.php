<?php

namespace Tests\Feature\Analytics;

use App\Console\Commands\AnalyticsRefresh;
use App\Jobs\RefreshAnalyticsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_command_is_a_safe_noop_off_postgres(): void
    {
        // SQLite has no materialized views; the command must succeed as a no-op.
        $this->assertSame('sqlite', DB::getDriverName());
        $this->artisan('analytics:refresh')->assertSuccessful();
    }

    public function test_refresh_job_never_throws_off_postgres(): void
    {
        // The job runs on the generation success path; it must never raise.
        (new RefreshAnalyticsJob)->handle();
        $this->assertTrue(true);
    }

    public function test_refresh_bumps_the_version_on_postgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Materialized-view refresh requires PostgreSQL.');
        }

        $before = (int) Cache::get(AnalyticsRefresh::VERSION_KEY, 0);
        $this->artisan('analytics:refresh')->assertSuccessful();
        $this->assertGreaterThan($before, (int) Cache::get(AnalyticsRefresh::VERSION_KEY, 0));
    }
}
