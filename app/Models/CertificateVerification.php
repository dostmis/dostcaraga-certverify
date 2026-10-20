<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One verification attempt against /verify. Written by VerificationLogger.
 *
 * `updated_at` is deliberately absent: a verification is an immutable event,
 * never edited after the fact.
 */
class CertificateVerification extends Model
{
    public const UPDATED_AT = null;

    public const OUTCOME_VALID = 'valid';

    public const OUTCOME_REVOKED = 'revoked';

    public const OUTCOME_INVALID = 'invalid';

    public const OUTCOME_NOT_FOUND = 'not_found';

    public const OUTCOME_MALFORMED = 'malformed';

    /** Outcomes that mean "a real certificate was shown". */
    public const OUTCOMES_RESOLVED = [
        self::OUTCOME_VALID,
        self::OUTCOME_REVOKED,
        self::OUTCOME_INVALID,
    ];

    /** Outcomes that mean "the lookup did not resolve to a certificate". */
    public const OUTCOMES_FAILED = [
        self::OUTCOME_NOT_FOUND,
        self::OUTCOME_MALFORMED,
    ];

    protected $fillable = [
        'certificate_id',
        'outcome',
        'ip_hash',
        'ua_class',
        'referer_host',
    ];

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
