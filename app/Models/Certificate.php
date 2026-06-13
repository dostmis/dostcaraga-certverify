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

    public const BLOCKCHAIN_STATUS_PENDING = 'pending';
    public const BLOCKCHAIN_STATUS_ANCHORED = 'anchored';
    public const BLOCKCHAIN_STATUS_FAILED = 'failed';

    protected $fillable = [
        'certificate_code',
        'public_token',
        'participant_name',
        'email',
        'recipient_id',
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
        'blockchain_payload_hash',
        'blockchain_topic_id',
        'blockchain_sequence_number',
        'blockchain_consensus_timestamp',
        'blockchain_transaction_id',
        'blockchain_status',
        'blockchain_error',
        'blockchain_anchored_at',
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
        'blockchain_sequence_number' => 'integer',
        'blockchain_anchored_at' => 'datetime',
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

    public function hasRecipient(): bool
    {
        return $this->recipient_id !== null;
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function isAnchored(): bool
    {
        return $this->blockchain_status === self::BLOCKCHAIN_STATUS_ANCHORED
            && !empty($this->blockchain_sequence_number);
    }

    /**
     * Canonical, deterministic representation of the immutable certificate
     * facts. This is what we hash and anchor to Hedera. It deliberately
     * excludes the PDF bytes and any mutable presentation fields so the hash
     * never drifts when a stamped PDF is regenerated (e.g. QR domain refresh).
     */
    public function canonicalPayload(): array
    {
        return [
            'certificate_code' => (string) $this->certificate_code,
            'public_token' => (string) $this->public_token,
            'participant_name' => (string) $this->participant_name,
            'training_title' => (string) $this->training_title,
            'training_date' => optional($this->training_date)->format('Y-m-d'),
            'training_date_to' => optional($this->training_date_to)->format('Y-m-d'),
            'issuing_office' => (string) $this->issuing_office,
        ];
    }

    /**
     * SHA-256 of the canonical payload, encoded deterministically.
     */
    public function canonicalHash(): string
    {
        $json = json_encode(
            $this->canonicalPayload(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return hash('sha256', (string) $json);
    }
}
