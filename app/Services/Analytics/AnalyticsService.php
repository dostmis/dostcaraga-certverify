<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reads the analytics materialized views and assembles every dashboard panel.
 *
 * All geography/program/demographic filtering is parameterised. The service
 * degrades gracefully: when the views are absent (non-pgsql test driver, or an
 * un-migrated database) it returns a well-formed empty payload with
 * available=false instead of throwing, so access-control and the shell still work.
 */
class AnalyticsService
{
    private const CF = 'analytics.mv_certificate_facts';

    private const EF = 'analytics.mv_endorsement_facts';

    private const IF = 'analytics.mv_intake_facts';

    private const PR = 'analytics.mv_public_reach';

    private const VF = 'analytics.mv_verification_facts';

    private const VD = 'analytics.mv_verification_daily';

    /** Raw verification log — a plain table, so it reads on any driver. */
    private const VLOG = 'certificate_verifications';

    private const TARGETS = 'analytics_targets';

    private ?bool $available = null;

    /** @var array<string, bool> */
    private array $relationExists = [];

    public function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        return $this->available = $this->hasRelation(self::CF);
    }

    /**
     * The verification views arrived in a later migration than the rest of the
     * read-model, so they are probed separately: a database that has the
     * original four views but not these must still render every other chapter.
     */
    public function verificationAvailable(): bool
    {
        return $this->hasRelation(self::VF) && $this->hasRelation(self::VD);
    }

    private function hasRelation(string $relation): bool
    {
        if (array_key_exists($relation, $this->relationExists)) {
            return $this->relationExists[$relation];
        }

        if (DB::getDriverName() !== 'pgsql') {
            return $this->relationExists[$relation] = false;
        }

        try {
            $row = DB::selectOne('SELECT to_regclass(?) AS t', [$relation]);

            return $this->relationExists[$relation] = ($row->t ?? null) !== null;
        } catch (\Throwable) {
            return $this->relationExists[$relation] = false;
        }
    }

    /**
     * Full payload for the admin dashboard: executive aggregates (global) plus
     * operations (scoped to the user's own packages/events unless they are the RD).
     *
     * @return array<string, mixed>
     */
    public function data(User $user, AnalyticsFilters $filters): array
    {
        $isRd = $user->isRegionalDirector();

        if (! $this->available()) {
            return [
                'available' => false,
                'filters' => $filters->toArray(),
                'executive' => null,
                'operations' => null,
                // Targets live in a plain table, so the Pace chapter can still
                // show what was planned even with no read-model to compare it
                // against. Integrity reads the raw log, also a plain table.
                'targets' => $this->targets($filters),
                'integrity' => $isRd ? $this->integrity($filters) : null,
            ];
        }

        $scopeUserId = $isRd ? null : (int) $user->id;

        return [
            'available' => true,
            'filters' => $filters->toArray(),
            'executive' => $this->executive($filters),
            'operations' => $this->operations($filters, $scopeUserId),
            'targets' => $this->targets($filters),
            'integrity' => $isRd ? $this->integrity($filters) : null,
        ];
    }

    // ---- executive (global aggregates, no PII) --------------------------------

    /** @return array<string, mixed> */
    public function executive(AnalyticsFilters $filters): array
    {
        return [
            'kpis' => $this->kpis($filters),
            'geo' => $this->geo($filters),
            'age_pyramid' => $this->agePyramid($filters),
            'program_mix' => $this->programMix($filters),
            'monthly_trend' => $this->monthlyTrend($filters),
            'top_trainings' => $this->topTrainings($filters),
            'verification' => $this->verification($filters),
        ];
    }

    /** @return array<string, mixed> */
    private function kpis(AnalyticsFilters $filters): array
    {
        $current = $this->kpiSnapshot($filters);
        $previous = ($prev = $filters->previousPeriod()) ? $this->kpiSnapshot($prev) : null;

        $withDelta = [];
        foreach ($current as $key => $value) {
            $withDelta[$key] = [
                'value' => $value,
                'previous' => $previous[$key] ?? null,
                'delta' => $previous !== null ? round($value - $previous[$key], 2) : null,
            ];
        }

        return $withDelta;
    }

    /** @return array<string, float|int> */
    private function kpiSnapshot(AnalyticsFilters $filters): array
    {
        $cf = $this->cf($filters);

        $total = (int) (clone $cf)->count();
        $people = (int) (clone $cf)->distinct()->count('person_key');
        $repeatPeople = (int) (clone $cf)->where('is_repeat_participant', true)->distinct()->count('person_key');
        $munis = (int) (clone $cf)->whereNotNull('municipality_code')->distinct()->count('municipality_code');
        $claimed = (int) (clone $cf)->where('is_claimed', true)->count();

        $avgTurnaround = (float) ($this->ef($filters, null)->avg('turnaround_hours') ?? 0);

        return [
            'certificates' => $total,
            'people_reached' => $people,
            'repeat_rate' => $people > 0 ? round(100 * $repeatPeople / $people, 1) : 0.0,
            'municipalities' => $munis,
            'avg_turnaround_hours' => round($avgTurnaround, 1),
            'claim_rate' => $total > 0 ? round(100 * $claimed / $total, 1) : 0.0,
        ];
    }

    /** @return array<string, mixed> */
    private function geo(AnalyticsFilters $filters): array
    {
        $province = (clone $this->cf($filters))
            ->whereNotNull('province_code')
            ->selectRaw('province_code AS code, province_name AS name, COUNT(*) AS total')
            ->groupBy('province_code', 'province_name')
            ->orderByDesc('total')
            ->get();

        $municipality = (clone $this->cf($filters))
            ->whereNotNull('municipality_code')
            ->selectRaw('municipality_code AS code, municipality_name AS name, province_code, COUNT(*) AS total')
            ->groupBy('municipality_code', 'municipality_name', 'province_code')
            ->orderByDesc('total')
            ->get();

        $total = (int) (clone $this->cf($filters))->count();
        $unknownProvince = (int) (clone $this->cf($filters))->whereNull('province_code')->count();
        $unknownMunicipality = (int) (clone $this->cf($filters))->whereNull('municipality_code')->count();

        return [
            'province' => $province,
            'municipality' => $municipality,
            'unknown_province_share' => $total > 0 ? round(100 * $unknownProvince / $total, 1) : 0.0,
            'unknown_municipality_share' => $total > 0 ? round(100 * $unknownMunicipality / $total, 1) : 0.0,
        ];
    }

    private function agePyramid(AnalyticsFilters $filters): \Illuminate\Support\Collection
    {
        return (clone $this->cf($filters))
            ->selectRaw('age_band, gender, COUNT(*) AS total')
            ->groupBy('age_band', 'gender')
            ->get();
    }

    private function programMix(AnalyticsFilters $filters): \Illuminate\Support\Collection
    {
        return (clone $this->cf($filters))
            ->selectRaw('program_code, COUNT(*) AS total')
            ->groupBy('program_code')
            ->orderByDesc('total')
            ->get();
    }

    private function monthlyTrend(AnalyticsFilters $filters): \Illuminate\Support\Collection
    {
        return (clone $this->cf($filters))
            ->selectRaw('training_year, training_month, COUNT(*) AS total')
            ->whereNotNull('training_month')
            ->groupBy('training_year', 'training_month')
            ->orderBy('training_year')
            ->orderBy('training_month')
            ->get();
    }

    private function topTrainings(AnalyticsFilters $filters): \Illuminate\Support\Collection
    {
        // Group by title + program so the owning program is exact (a title run
        // under several programs shows as a row per program, not a guess).
        return (clone $this->cf($filters))
            ->selectRaw('MAX(training_title) AS training_title, program_code, training_title_norm, COUNT(*) AS total')
            ->groupBy('training_title_norm', 'program_code')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }

    // ---- operations (scoped for non-RD) ---------------------------------------

    /** @return array<string, mixed> */
    public function operations(AnalyticsFilters $filters, ?int $scopeUserId): array
    {
        return [
            'scoped' => $scopeUserId !== null,
            'pipeline' => $this->pipeline($filters, $scopeUserId),
            'turnaround' => $this->turnaround($filters, $scopeUserId),
            'error_trend' => $this->errorTrend($filters, $scopeUserId),
            'email_delivery' => $this->emailDelivery($filters),
            'claim_funnel' => $this->claimFunnel($filters),
            'intake_conversion' => $this->intakeConversion($scopeUserId),
        ];
    }

    private function pipeline(AnalyticsFilters $filters, ?int $scopeUserId): \Illuminate\Support\Collection
    {
        return $this->ef($filters, $scopeUserId)
            ->selectRaw('outcome, COUNT(*) AS total')
            ->groupBy('outcome')
            ->get();
    }

    /** @return array<string, float|int|null> */
    private function turnaround(AnalyticsFilters $filters, ?int $scopeUserId): array
    {
        $row = $this->ef($filters, $scopeUserId)
            ->whereNotNull('turnaround_hours')
            ->selectRaw('
                ROUND(AVG(turnaround_hours)::numeric, 1) AS avg_h,
                ROUND((percentile_cont(0.5) WITHIN GROUP (ORDER BY turnaround_hours))::numeric, 1) AS p50_h,
                ROUND((percentile_cont(0.9) WITHIN GROUP (ORDER BY turnaround_hours))::numeric, 1) AS p90_h,
                ROUND(MAX(turnaround_hours)::numeric, 1) AS max_h,
                COUNT(*) AS decided
            ')
            ->first();

        return [
            'avg_hours' => $row->avg_h ?? null,
            'p50_hours' => $row->p50_h ?? null,
            'p90_hours' => $row->p90_h ?? null,
            'max_hours' => $row->max_h ?? null,
            'decided' => (int) ($row->decided ?? 0),
        ];
    }

    private function errorTrend(AnalyticsFilters $filters, ?int $scopeUserId): \Illuminate\Support\Collection
    {
        return $this->ef($filters, $scopeUserId)
            ->whereNotNull('submitted_at')
            ->selectRaw("to_char(submitted_at, 'YYYY-MM') AS month,
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE outcome = 'rejected') AS rejected,
                COUNT(*) FILTER (WHERE had_generation_error) AS generation_errors")
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    private function emailDelivery(AnalyticsFilters $filters): \Illuminate\Support\Collection
    {
        return (clone $this->cf($filters))
            ->selectRaw('email_delivery_status AS status, COUNT(*) AS total')
            ->groupBy('email_delivery_status')
            ->orderByDesc('total')
            ->get();
    }

    /** @return array<string, int> */
    private function claimFunnel(AnalyticsFilters $filters): array
    {
        $people = (int) (clone $this->cf($filters))->distinct()->count('person_key');
        $claimed = (int) (clone $this->cf($filters))->where('is_claimed', true)->distinct()->count('person_key');

        return [
            'people' => $people,
            'claimed' => $claimed,
            'dormant' => max($people - $claimed, 0),
        ];
    }

    private function intakeConversion(?int $scopeUserId): \Illuminate\Support\Collection
    {
        $query = DB::table(self::IF);
        if ($scopeUserId !== null) {
            $query->where('owner_user_id', $scopeUserId);
        }

        return $query
            ->whereNotNull('event_id')
            ->selectRaw('event_id,
                COUNT(*) AS submissions,
                COUNT(*) FILTER (WHERE resulted_in_certificate) AS converted')
            ->groupBy('event_id')
            ->orderByDesc('submissions')
            ->limit(20)
            ->get();
    }

    // ---- verification (the Proof chapter) -------------------------------------

    /**
     * Is anyone actually verifying these certificates? Reads the verification
     * views, which carry the certificate dimensions, so the same geography /
     * program / demographic filters apply here as everywhere else.
     *
     * @return array<string, mixed>
     */
    public function verification(AnalyticsFilters $filters): array
    {
        if (! $this->verificationAvailable()) {
            return [
                'available' => false,
                'kpis' => null,
                'trend' => [],
                'top_verified' => [],
                'ua_mix' => [],
            ];
        }

        return [
            'available' => true,
            'kpis' => $this->verificationKpis($filters),
            'trend' => $this->verificationTrend($filters),
            'top_verified' => $this->topVerifiedTrainings($filters),
            'ua_mix' => $this->verificationUaMix($filters),
        ];
    }

    /** @return array<string, float|int|null> */
    private function verificationKpis(AnalyticsFilters $filters): array
    {
        $row = $this->vf($filters)
            ->selectRaw('
                COUNT(*) AS certificates,
                COUNT(*) FILTER (WHERE is_verified) AS verified_certificates,
                COALESCE(SUM(verification_count), 0) AS verifications,
                COALESCE(SUM(unique_verifier_count), 0) AS unique_verifiers,
                ROUND((percentile_cont(0.5) WITHIN GROUP (ORDER BY days_to_first_verification))::numeric, 1) AS median_days
            ')
            ->first();

        $certificates = (int) ($row->certificates ?? 0);
        $verified = (int) ($row->verified_certificates ?? 0);

        return [
            'certificates' => $certificates,
            'verified_certificates' => $verified,
            // Share of issued certificates looked up at least once. Computed
            // over all certificates, not just verified ones — hence the
            // LEFT JOIN in mv_verification_facts.
            'verification_rate' => $certificates > 0 ? round(100 * $verified / $certificates, 1) : 0.0,
            'verifications' => (int) ($row->verifications ?? 0),
            'unique_verifiers' => (int) ($row->unique_verifiers ?? 0),
            'median_days_to_first' => $row->median_days !== null ? (float) $row->median_days : null,
        ];
    }

    /**
     * Daily successful-verification volume. The daily view carries no
     * certificate dimensions (it is a log rollup, not a fact table), so only
     * the date window applies here — the UI labels it accordingly.
     */
    private function verificationTrend(AnalyticsFilters $filters): \Illuminate\Support\Collection
    {
        $query = DB::table(self::VD)->whereIn('outcome', ['valid', 'revoked', 'invalid']);
        $filters->applyDateRange($query, 'verified_date');

        return $query
            ->selectRaw('verified_date::text AS date, SUM(total) AS total, SUM(unique_verifiers) AS unique_verifiers')
            ->groupBy('verified_date')
            ->orderBy('verified_date')
            ->get();
    }

    private function topVerifiedTrainings(AnalyticsFilters $filters): \Illuminate\Support\Collection
    {
        return $this->vf($filters)
            ->selectRaw('MAX(training_title) AS training_title, program_code, training_title_norm,
                COUNT(*) AS certificates,
                COALESCE(SUM(verification_count), 0) AS verifications,
                ROUND(100.0 * COUNT(*) FILTER (WHERE is_verified) / NULLIF(COUNT(*), 0), 1) AS verified_share')
            ->groupBy('training_title_norm', 'program_code')
            ->orderByDesc('verifications')
            ->limit(10)
            ->get();
    }

    /**
     * Device mix of verifications, which stands in for how certificates are
     * being checked: a mobile share dominated by QR scans off a printed
     * certificate reads very differently from desktop HR lookups.
     */
    private function verificationUaMix(AnalyticsFilters $filters): \Illuminate\Support\Collection
    {
        $query = DB::table(self::VLOG)->whereIn('outcome', ['valid', 'revoked', 'invalid']);
        $filters->applyDateRange($query, 'created_at');

        return $query
            ->selectRaw('ua_class, COUNT(*) AS total')
            ->groupBy('ua_class')
            ->orderByDesc('total')
            ->get();
    }

    // ---- targets (the Pace chapter) -------------------------------------------

    /**
     * Target-vs-actual for one year, per program, with a pacing verdict.
     *
     * Targets are annual and per-program, so this block deliberately honors
     * only the year and the program filter. Comparing a gender-filtered or
     * province-filtered actual against a whole-region annual target would
     * produce a number that looks authoritative and is simply wrong; the
     * ignored filters are reported back so the UI can say so out loud.
     *
     * @return array<string, mixed>
     */
    public function targets(AnalyticsFilters $filters): array
    {
        $year = $this->targetYear($filters);
        $elapsed = $this->yearElapsedFraction($year);

        $rows = DB::table(self::TARGETS)
            ->where('period_year', $year)
            ->where('province_code', 0)
            ->when($filters->program !== null, fn ($q) => $q->where('program_code', $filters->program))
            ->orderBy('program_code')
            ->get();

        $actuals = $this->actualsByProgram($year, $filters->program);

        $programs = $rows->map(function ($row) use ($actuals, $elapsed) {
            $actual = $actuals[$row->program_code] ?? ['certificates' => 0, 'people' => 0];

            return [
                'program_code' => $row->program_code,
                'target_certificates' => $row->target_certificates !== null ? (int) $row->target_certificates : null,
                'target_people' => $row->target_people !== null ? (int) $row->target_people : null,
                'actual_certificates' => (int) $actual['certificates'],
                'actual_people' => (int) $actual['people'],
                'notes' => $row->notes,
                'certificates' => $this->pacing((int) $actual['certificates'], $row->target_certificates, $elapsed),
                'people' => $this->pacing((int) $actual['people'], $row->target_people, $elapsed),
            ];
        })->values();

        $totalTarget = (int) $rows->sum('target_certificates');
        $totalActual = (int) $programs->sum('actual_certificates');

        return [
            'year' => $year,
            'elapsed_fraction' => round($elapsed, 4),
            'has_targets' => $rows->isNotEmpty(),
            'actuals_available' => $this->available(),
            // Filters this block cannot honor without lying about the target.
            'ignored_filters' => array_values(array_filter([
                $filters->gender !== null ? 'gender' : null,
                $filters->ageBand !== null ? 'age_band' : null,
                $filters->province !== null ? 'province' : null,
                $filters->municipality !== null ? 'municipality' : null,
            ])),
            'programs' => $programs,
            'total' => [
                'target_certificates' => $totalTarget,
                'actual_certificates' => $totalActual,
                'certificates' => $this->pacing($totalActual, $totalTarget ?: null, $elapsed),
            ],
        ];
    }

    /**
     * Year-to-date actuals per program. Honors the program filter only, to
     * match the targets they are compared against.
     *
     * @return array<string, array{certificates:int, people:int}>
     */
    private function actualsByProgram(int $year, ?string $program): array
    {
        if (! $this->available()) {
            return [];
        }

        $rows = DB::table(self::CF)
            ->where('training_year', $year)
            ->when($program !== null, fn ($q) => $q->where('program_code', $program))
            ->selectRaw('program_code, COUNT(*) AS certificates, COUNT(DISTINCT person_key) AS people')
            ->groupBy('program_code')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->program_code] = [
                'certificates' => (int) $row->certificates,
                'people' => (int) $row->people,
            ];
        }

        return $out;
    }

    /**
     * Pace of an actual against a target, pro-rated by how much of the year has
     * elapsed, plus a straight-line year-end projection.
     *
     * @return array<string, mixed>
     */
    private function pacing(int $actual, int|string|null $target, float $elapsed): array
    {
        $target = $target !== null ? (int) $target : null;

        if ($target === null || $target <= 0) {
            return [
                'target' => $target,
                'actual' => $actual,
                'attainment' => null,
                'expected_to_date' => null,
                'projection' => null,
                'status' => 'no_target',
            ];
        }

        $attainment = round(100 * $actual / $target, 1);
        $expected = $target * $elapsed;
        // Below ~8% of the year there is not enough signal for a verdict, and
        // the projection divides by a number small enough to be meaningless.
        $tooEarly = $elapsed < 0.08;
        $projection = $elapsed > 0 ? (int) round($actual / $elapsed) : null;

        $status = match (true) {
            $tooEarly => 'too_early',
            $actual >= $target => 'met',
            $expected > 0 && $actual >= $expected => 'on_track',
            $expected > 0 && $actual >= $expected * 0.85 => 'at_risk',
            default => 'behind',
        };

        return [
            'target' => $target,
            'actual' => $actual,
            'attainment' => $attainment,
            'expected_to_date' => (int) round($expected),
            'projection' => $projection,
            'status' => $status,
        ];
    }

    /**
     * Which year the targets panel is about: the filtered window's year when
     * it sits inside one year, otherwise the current year.
     */
    private function targetYear(AnalyticsFilters $filters): int
    {
        $from = $filters->dateFrom !== null ? (int) substr($filters->dateFrom, 0, 4) : null;
        $to = $filters->dateTo !== null ? (int) substr($filters->dateTo, 0, 4) : null;

        if ($from !== null && ($to === null || $to === $from)) {
            return $from;
        }

        if ($from === null && $to !== null) {
            return $to;
        }

        return (int) now()->year;
    }

    /** 0.0–1.0 share of the given year already elapsed (1.0 for past years). */
    private function yearElapsedFraction(int $year): float
    {
        $now = \Carbon\CarbonImmutable::now();

        if ($year < $now->year) {
            return 1.0;
        }
        if ($year > $now->year) {
            return 0.0;
        }

        return round($now->dayOfYear / $now->daysInYear, 4);
    }

    // ---- integrity (RD only) --------------------------------------------------

    /**
     * Reads the raw verification log rather than the materialized views, on
     * purpose: "a revoked certificate is being hammered right now" is only
     * useful live, and the views refresh nightly. The table is small and
     * indexed on created_at.
     *
     * @return array<string, mixed>
     */
    public function integrity(AnalyticsFilters $filters): array
    {
        $date = $this->dateExpr('created_at');

        $outcomes = $this->vlog($filters)
            ->selectRaw('outcome, COUNT(*) AS total')
            ->groupBy('outcome')
            ->orderByDesc('total')
            ->get();

        $trend = $this->vlog($filters)
            ->selectRaw("{$date} AS date, outcome, COUNT(*) AS total")
            ->groupByRaw("{$date}, outcome")
            ->orderByRaw("{$date}")
            ->get();

        // Lookups that resolved to a certificate that is not valid. Someone is
        // presenting a revoked or invalid certificate as if it were good.
        $badHits = $this->vlog($filters)
            ->whereIn('outcome', ['revoked', 'invalid'])
            ->whereNotNull('certificate_id')
            ->join('certificates', 'certificates.id', '=', self::VLOG.'.certificate_id')
            ->selectRaw('certificates.id AS certificate_id, certificates.certificate_code,
                certificates.training_title, certificates.status,
                COUNT(*) AS attempts, COUNT(DISTINCT '.self::VLOG.'.ip_hash) AS unique_verifiers')
            ->groupBy('certificates.id', 'certificates.certificate_code', 'certificates.training_title', 'certificates.status')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        // High volume from few distinct verifiers: a link being scraped or
        // replayed rather than genuinely checked by many people.
        $concentrated = $this->vlog($filters)
            ->whereNotNull('certificate_id')
            ->selectRaw('certificate_id, COUNT(*) AS attempts, COUNT(DISTINCT ip_hash) AS unique_verifiers')
            ->groupBy('certificate_id')
            ->havingRaw('COUNT(*) >= 20 AND COUNT(*) >= 5 * COUNT(DISTINCT ip_hash)')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        $referrers = $this->vlog($filters)
            ->whereNotNull('referer_host')
            ->selectRaw('referer_host, COUNT(*) AS total')
            ->groupBy('referer_host')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $total = (int) $this->vlog($filters)->count();
        $failed = (int) $this->vlog($filters)->whereIn('outcome', ['not_found', 'malformed'])->count();
        $bots = (int) $this->vlog($filters)->where('ua_class', 'bot')->count();

        return [
            'total_attempts' => $total,
            'failed_attempts' => $failed,
            'failure_rate' => $total > 0 ? round(100 * $failed / $total, 1) : 0.0,
            'bot_share' => $total > 0 ? round(100 * $bots / $total, 1) : 0.0,
            'outcomes' => $outcomes,
            'trend' => $trend,
            'bad_hits' => $badHits,
            'concentrated' => $concentrated,
            'referrers' => $referrers,
        ];
    }

    // ---- public reach (aggregate-only, <5 suppressed, no barangay) ------------

    /** @return array<string, mixed> */
    public function publicReach(): array
    {
        if (! $this->available()) {
            return [
                'available' => false,
                'generated_at' => now()->toIso8601String(),
                'reach' => [],
                'funnel' => null,
                'activity' => [],
            ];
        }

        return [
            'available' => true,
            'generated_at' => now()->toIso8601String(),
            'reach' => DB::table(self::PR)->get(),
            'funnel' => $this->publicFunnel(),
            'activity' => $this->publicActivity(),
        ];
    }

    /**
     * Recipient-journey funnel for the public Sankey: intake -> certified ->
     * claimed/dormant. All are plain aggregate counts; any stage under 5 is
     * suppressed to null (shown as "<5"), matching mv_public_reach.
     *
     * @return array<string, int|null>
     */
    private function publicFunnel(): array
    {
        $suppress = fn (int $n): ?int => $n < 5 ? null : $n;

        $certificates = (int) DB::table(self::CF)->count();
        $people = (int) DB::table(self::CF)->distinct()->count('person_key');
        $claimed = (int) DB::table(self::CF)->where('is_claimed', true)->distinct()->count('person_key');
        $intakes = (int) DB::table(self::IF)->count();
        $converted = (int) DB::table(self::IF)->where('resulted_in_certificate', true)->count();

        return [
            'intakes' => $suppress($intakes),
            'intakes_converted' => $suppress($converted),
            'certificates' => $suppress($certificates),
            'people' => $suppress($people),
            'claimed' => $suppress($claimed),
            'dormant' => $suppress(max($people - $claimed, 0)),
        ];
    }

    /**
     * Daily certificate volume for the public calendar heatmap. Pure volume by
     * date (no demographics); days under 5 are suppressed for consistency with
     * the <5 rule, so the heatmap shows only the busier training days.
     */
    private function publicActivity(): \Illuminate\Support\Collection
    {
        return DB::table(self::CF)
            ->whereNotNull('training_date')
            ->selectRaw('training_date::text AS date, COUNT(*) AS cert_count')
            ->groupBy('training_date')
            ->havingRaw('COUNT(*) >= 5')
            ->orderBy('date')
            ->get();
    }

    // ---- builders -------------------------------------------------------------

    private function cf(AnalyticsFilters $filters): Builder
    {
        $query = DB::table(self::CF);
        $filters->applyDimensions($query);
        $filters->applyDateRange($query, 'training_date');

        return $query;
    }

    private function vf(AnalyticsFilters $filters): Builder
    {
        $query = DB::table(self::VF);
        $filters->applyDimensions($query);
        $filters->applyDateRange($query, 'training_date');

        return $query;
    }

    /**
     * The raw verification log. It carries no certificate dimensions, so only
     * the date window applies — against the verification timestamp, which is
     * the right axis for integrity questions (when was it checked, not when
     * was the training).
     */
    private function vlog(AnalyticsFilters $filters): Builder
    {
        $query = DB::table(self::VLOG);
        $filters->applyDateRange($query, self::VLOG.'.created_at');

        return $query;
    }

    /** Date truncation differs per driver; the integrity block runs on both. */
    private function dateExpr(string $column): string
    {
        $qualified = self::VLOG.'.'.$column;

        return DB::getDriverName() === 'pgsql'
            ? "{$qualified}::date"
            : "date({$qualified})";
    }

    private function ef(AnalyticsFilters $filters, ?int $scopeUserId): Builder
    {
        $query = DB::table(self::EF);
        if ($filters->program !== null) {
            $query->where('program_code', $filters->program);
        }
        if ($filters->dateFrom !== null) {
            $query->whereDate('submitted_at', '>=', $filters->dateFrom);
        }
        if ($filters->dateTo !== null) {
            $query->whereDate('submitted_at', '<=', $filters->dateTo);
        }
        if ($scopeUserId !== null) {
            $query->where('submitted_by', $scopeUserId);
        }

        return $query;
    }
}
