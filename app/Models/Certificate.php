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

    /**
     * Deliberately withheld by an administrator - e.g. the address on file
     * belongs to someone other than the participant. Never auto-resent.
     */
    public const EMAIL_STATUS_HELD = 'held';

    protected $fillable = [
        'certificate_code',
        'public_token',
        'participant_name',
        'name_alignment',
        'name_margin_left',
        'name_margin_right',
        'name_offset_x',
        'name_offset_y',
        'signature_offset_x',
        'email',
        'recipient_id',
        'certificate_endorsement_id',
        'gender',
        'age',
        'block_lot_purok',
        'region',
        'city_municipality',
        'barangay',
        'province',
        'industry',
        'training_title',
        'caption_text',
        'caption_alignment',
        'qr_show_code',
        'qr_show_link',
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
        'qr_show_code' => 'boolean',
        'qr_show_link' => 'boolean',
        'name_margin_left' => 'integer',
        'name_margin_right' => 'integer',
        'name_offset_x' => 'integer',
        'name_offset_y' => 'integer',
        'signature_offset_x' => 'integer',
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

    public function recipient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * The endorsement package this certificate was generated from, if any.
     */
    public function endorsement(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CertificateEndorsement::class, 'certificate_endorsement_id');
    }

    public function hasRecipient(): bool
    {
        return $this->recipient_id !== null;
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }
}
