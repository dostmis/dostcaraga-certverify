<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsRefresh extends Command
{
    protected $signature = 'analytics:refresh {--skip-crosswalk : Refresh the views without re-syncing the PSGC crosswalk}';

    protected $description = 'Sync the PSGC crosswalk and refresh the analytics materialized views, then bump the cache version.';

    /**
     * Materialized views in dependency order: public_reach and
     * verification_facts are both built on certificate_facts, so they must
     * come after it.
     */
    private const VIEWS = [
        'analytics.mv_certificate_facts',
        'analytics.mv_endorsement_facts',
        'analytics.mv_intake_facts',
        'analytics.mv_public_reach',
        'analytics.mv_verification_facts',
        'analytics.mv_verification_daily',
    ];

    public const VERSION_KEY = 'analytics:version';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('analytics:refresh is a no-op on non-pgsql connections ('.DB::getDriverName().').');

            return self::SUCCESS;
        }

        $lock = Cache::lock('analytics:refresh', 600);
        if (! $lock->get()) {
            $this->warn('Another analytics:refresh is already running; skipping.');

            return self::SUCCESS;
        }

        try {
            if (! $this->option('skip-crosswalk')) {
                $this->syncCrosswalk();
            }

            foreach (self::VIEWS as $view) {
                $this->refreshView($view);
            }

            $version = $this->bumpVersion();
            $this->info("Analytics refreshed. Version now {$version}.");
        } finally {
            optional($lock)->release();
        }

        return self::SUCCESS;
    }

    /**
     * Idempotently load the PSGC crosswalk from the bundled Caraga GeoJSON.
     * name_normalized is a generated column, so only raw codes/names are written.
     */
    private function syncCrosswalk(): void
    {
        $rows = array_merge(
            $this->crosswalkRows(public_path('geo/caraga-provinces.0.001.json'), 'province', 'adm2_psgc', 'adm2_en', 'adm1_psgc'),
            $this->crosswalkRows(public_path('geo/caraga-municipalities.0.001.json'), 'municipality', 'adm3_psgc', 'adm3_en', 'adm2_psgc'),
        );

        if ($rows === []) {
            $this->warn('No PSGC crosswalk rows found in public/geo; leaving crosswalk unchanged.');

            return;
        }

        DB::table('analytics.psgc_crosswalk')->upsert(
            $rows,
            ['level', 'psgc_code'],
            ['parent_code', 'name_canonical'],
        );

        $this->line('PSGC crosswalk synced ('.count($rows).' rows).');
    }

    /**
     * @return array<int, array{level:string, psgc_code:int, parent_code:int|null, name_canonical:string}>
     */
    private function crosswalkRows(string $path, string $level, string $codeKey, string $nameKey, string $parentKey): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        $features = is_array($data['features'] ?? null) ? $data['features'] : [];

        $rows = [];
        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];
            if (! isset($props[$codeKey], $props[$nameKey])) {
                continue;
            }
            $rows[] = [
                'level' => $level,
                'psgc_code' => (int) $props[$codeKey],
                'parent_code' => isset($props[$parentKey]) ? (int) $props[$parentKey] : null,
                'name_canonical' => (string) $props[$nameKey],
            ];
        }

        return $rows;
    }

    private function refreshView(string $view): void
    {
        // A view added by a later migration than the one the database has run
        // is skipped rather than fatal, so one un-migrated view cannot take
        // down the whole nightly refresh.
        if (DB::selectOne('SELECT to_regclass(?) AS t', [$view])?->t === null) {
            $this->warn("Skipped {$view} (not present; run migrations).");

            return;
        }

        // CONCURRENTLY needs a prior populated state + unique index (both true
        // after the migration created the views WITH DATA). Fall back to a
        // plain refresh if a view was somehow never populated.
        try {
            DB::statement("REFRESH MATERIALIZED VIEW CONCURRENTLY {$view}");
        } catch (\Throwable $e) {
            DB::statement("REFRESH MATERIALIZED VIEW {$view}");
        }

        $this->line("Refreshed {$view}.");
    }

    private function bumpVersion(): int
    {
        $version = (int) Cache::get(self::VERSION_KEY, 0) + 1;
        Cache::forever(self::VERSION_KEY, $version);

        return $version;
    }
}
