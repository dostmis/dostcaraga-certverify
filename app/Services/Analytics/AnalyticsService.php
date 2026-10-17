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

    private ?bool $available = null;

    public function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return $this->available = false;
        }

        try {
            $row = DB::selectOne('SELECT to_regclass(?) AS t', [self::CF]);

            return $this->available = ($row->t ?? null) !== null;
        } catch (\Throwable) {
            return $this->available = false;
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
        if (! $this->available()) {
            return [
                'available' => false,
                'filters' => $filters->toArray(),
                'executive' => null,
                'operations' => null,
            ];
        }

        $scopeUserId = $user->isRegionalDirector() ? null : (int) $user->id;

        return [
            'available' => true,
            'filters' => $filters->toArray(),
            'executive' => $this->executive($filters),
            'operations' => $this->operations($filters, $scopeUserId),
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
