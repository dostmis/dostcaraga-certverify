<?php

namespace App\Services\Analytics;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Immutable, whitelisted set of analytics filters.
 *
 * Only the fields here can ever reach a query, and they are always bound as
 * parameters (never interpolated). Values are validated upstream by
 * AnalyticsDataRequest; this object just carries and applies them.
 */
class AnalyticsFilters
{
    public function __construct(
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?string $program = null,
        public readonly ?int $province = null,
        public readonly ?int $municipality = null,
        public readonly ?string $gender = null,
        public readonly ?string $ageBand = null,
    ) {}

    /**
     * @param  array<string, mixed>  $v  validated input
     */
    public static function fromArray(array $v): self
    {
        return new self(
            dateFrom: self::str($v['date_from'] ?? null),
            dateTo: self::str($v['date_to'] ?? null),
            program: self::str($v['program'] ?? null),
            province: isset($v['province']) && $v['province'] !== null ? (int) $v['province'] : null,
            municipality: isset($v['municipality']) && $v['municipality'] !== null ? (int) $v['municipality'] : null,
            gender: self::str($v['gender'] ?? null),
            ageBand: self::str($v['age_band'] ?? null),
        );
    }

    private static function str(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    /**
     * Apply the geography/program/gender/age filters to any view that exposes
     * the standard dimension columns (certificate and intake fact views).
     */
    public function applyDimensions(Builder $query): Builder
    {
        if ($this->program !== null) {
            $query->where('program_code', $this->program);
        }
        if ($this->province !== null) {
            $query->where('province_code', $this->province);
        }
        if ($this->municipality !== null) {
            $query->where('municipality_code', $this->municipality);
        }
        if ($this->gender !== null) {
            $query->where('gender', $this->gender);
        }
        if ($this->ageBand !== null) {
            $query->where('age_band', $this->ageBand);
        }

        return $query;
    }

    /**
     * Apply the date window against a given date column (defaults to training_date).
     */
    public function applyDateRange(Builder $query, string $column = 'training_date'): Builder
    {
        if ($this->dateFrom !== null) {
            $query->whereDate($column, '>=', $this->dateFrom);
        }
        if ($this->dateTo !== null) {
            $query->whereDate($column, '<=', $this->dateTo);
        }

        return $query;
    }

    /**
     * The previous equivalent period, used for KPI deltas. When an explicit
     * window is set, shift back by its own length; otherwise null (caller
     * falls back to a year-over-year comparison or no delta).
     */
    public function previousPeriod(): ?self
    {
        if ($this->dateFrom === null || $this->dateTo === null) {
            return null;
        }

        $from = \Carbon\CarbonImmutable::parse($this->dateFrom)->startOfDay();
        $to = \Carbon\CarbonImmutable::parse($this->dateTo)->startOfDay();
        $days = $from->diffInDays($to) + 1;

        return new self(
            dateFrom: $from->subDays($days)->toDateString(),
            dateTo: $from->subDay()->toDateString(),
            program: $this->program,
            province: $this->province,
            municipality: $this->municipality,
            gender: $this->gender,
            ageBand: $this->ageBand,
        );
    }

    public function hasAny(): bool
    {
        return $this->dateFrom !== null || $this->dateTo !== null || $this->program !== null
            || $this->province !== null || $this->municipality !== null
            || $this->gender !== null || $this->ageBand !== null;
    }

    /** @return array<string, string|int> */
    public function toArray(): array
    {
        return array_filter([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'program' => $this->program,
            'province' => $this->province,
            'municipality' => $this->municipality,
            'gender' => $this->gender,
            'age_band' => $this->ageBand,
        ], fn ($v) => $v !== null);
    }

    public function cacheKey(): string
    {
        return md5(json_encode($this->toArray()) ?: '');
    }
}
