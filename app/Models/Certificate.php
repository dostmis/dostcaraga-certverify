<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Certificate extends Model
{
    public const EMAIL_STATUS_QUEUED = 'queued';
    public const EMAIL_STATUS_SENT = 'sent';
    public const EMAIL_STATUS_FAILED = 'failed';
    public const EMAIL_STATUS_SKIPPED_NO_EMAIL = 'skipped_no_email';
    public const EMAIL_STATUS_SKIPPED_INVALID_EMAIL = 'skipped_invalid_email';

    protected $fillable = [
        'certificate_code',
        'public_token',
        'participant_name',
        'email',
        'gender',
        'age',
        'block_lot_purok',
        'region',
        'city_municipality',
        'barangay',
        'province',
        'industry',
        'training_title',
        'activity_type',
        'certificate_type',
        'recipient_type',
        'venue',
        'topic',
        'training_date',
        'training_date_to',
        'number_of_training_hours',
        'dost_program',
        'setup_office_province',
        'dost_project',
        'project_code',
        'source_of_funds',
        'pillar',
        'training_budget',
        'expected_number_of_participants',
        'issuing_office',
        'status',
        'remarks',
        'source_pdf_path',
        'stamped_pdf_path',
        'email_delivery_status',
        'email_queued_at',
        'email_last_attempt_at',
        'email_sent_at',
        'email_failed_at',
        'email_failure_message',
    ];

    protected $casts = [
        'training_date' => 'date',
        'training_date_to' => 'date',
        'number_of_training_hours' => 'integer',
        'training_budget' => 'decimal:2',
        'expected_number_of_participants' => 'integer',
        'email_queued_at' => 'datetime',
        'email_last_attempt_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'email_failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($cert) {
            if (empty($cert->public_token)) {
                $cert->public_token = (string) Str::uuid();
            }
        });
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }
}
