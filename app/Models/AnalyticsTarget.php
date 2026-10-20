<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsTarget extends Model
{
    /** province_code value meaning "region-wide" (the only scope used today). */
    public const SCOPE_REGION = 0;

    protected $fillable = [
        'program_code',
        'period_year',
        'province_code',
        'target_certificates',
        'target_people',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'province_code' => 'integer',
        'target_certificates' => 'integer',
        'target_people' => 'integer',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeForYear(\Illuminate\Database\Eloquent\Builder $query, int $year): void
    {
        $query->where('period_year', $year)->where('province_code', self::SCOPE_REGION);
    }

    /** A row with neither metric set carries no target and is not worth storing. */
    public function isEmpty(): bool
    {
        return $this->target_certificates === null && $this->target_people === null;
    }
}
