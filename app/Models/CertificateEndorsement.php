<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class CertificateEndorsement extends Model
{
    public const STATUS_ENDORSED = 'endorsed';
    public const STATUS_RD_APPROVED = 'rd_approved';
    public const STATUS_RD_REJECTED = 'rd_rejected';

    /**
     * Certificate generation has been dispatched to the queue and is running.
     *
     * Transient: a run either finishes as STATUS_RD_APPROVED or rolls back to
     * STATUS_ENDORSED. There is deliberately no generation-failed status —
     * rollback returns the package to its pre-approval state and surfaces the
     * cause through `generation_error`.
     */
    public const STATUS_GENERATING = 'generating';

    protected $fillable = [
        'status',
        'submitted_by',
        'rd_approved_by',
        'rd_rejected_by',
        'rd_approved_at',
        'rd_rejected_at',
        'rejection_reason',
        'participants_count',
        'generated_count',
        'generation_batch_id',
        'generation_error',
        'generation_started_at',
        'participants_file_path',
        'template_pdf_path',
        'payload',
    ];

    protected $casts = [
        'generation_started_at' => 'datetime',
        'rd_approved_at' => 'datetime',
        'rd_rejected_at' => 'datetime',
        'payload' => 'array',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_ENDORSED,
            self::STATUS_GENERATING,
            self::STATUS_RD_APPROVED,
            self::STATUS_RD_REJECTED,
        ];
    }

    public function isGenerating(): bool
    {
        return $this->status === self::STATUS_GENERATING;
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
