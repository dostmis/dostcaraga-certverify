<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class CertificateEndorsement extends Model
{
    public const STATUS_ENDORSED = 'endorsed';
    public const STATUS_RD_APPROVED = 'rd_approved';
    public const STATUS_RD_REJECTED = 'rd_rejected';

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
        'participants_file_path',
        'template_pdf_path',
        'payload',
    ];

    protected $casts = [
        'rd_approved_at' => 'datetime',
        'rd_rejected_at' => 'datetime',
        'payload' => 'array',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_ENDORSED,
            self::STATUS_RD_APPROVED,
            self::STATUS_RD_REJECTED,
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
