<?php

namespace App\Services\Analytics;

use App\Models\Certificate;
use App\Models\CertificateVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Records verification attempts on /verify without ever storing PII.
 *
 * Three rules this class exists to enforce in one place:
 *
 *  1. Logging can never break verification. Every write is wrapped; a failure
 *     is logged to the application log and swallowed, because a broken
 *     analytics insert must not stop a recipient from proving their
 *     certificate is real.
 *  2. No raw IP, ever. The address is reduced to a peppered SHA-256 whose only
 *     job is to tell "20 scans by one person" apart from "20 people".
 *  3. The user agent is bucketed, not stored. `mobile|desktop|bot|unknown` is
 *     enough to separate QR scans off a printed certificate from a desktop HR
 *     check, and not enough to fingerprint anyone.
 */
class VerificationLogger
{
    /**
     * Log an attempt that resolved to a certificate. The outcome is derived
     * from the certificate's own status, so a revoked certificate being looked
     * up is recorded as `revoked` rather than as a success.
     */
    public function logCertificate(Request $request, Certificate $certificate): void
    {
        $this->write($request, $this->outcomeFor($certificate), $certificate->id);
    }

    /**
     * Log an attempt that never reached a certificate: a missing or non-UUID
     * token (`malformed`), or a well-formed token with no match (`not_found`).
     */
    public function logFailure(Request $request, string $outcome): void
    {
        $this->write($request, $outcome, null);
    }

    private function outcomeFor(Certificate $certificate): string
    {
        return match ($certificate->status) {
            'valid' => CertificateVerification::OUTCOME_VALID,
            'revoked' => CertificateVerification::OUTCOME_REVOKED,
            default => CertificateVerification::OUTCOME_INVALID,
        };
    }

    private function write(Request $request, string $outcome, ?int $certificateId): void
    {
        try {
            CertificateVerification::create([
                'certificate_id' => $certificateId,
                'outcome' => $outcome,
                'ip_hash' => $this->ipHash($request),
                'ua_class' => $this->uaClass($request),
                'referer_host' => $this->refererHost($request),
            ]);
        } catch (\Throwable $e) {
            // Deliberately swallowed: see rule 1 in the class docblock.
            Log::warning('Verification logging failed.', [
                'outcome' => $outcome,
                'certificate_id' => $certificateId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Peppered hash of the client address. The pepper is the app key, so the
     * digest is not reproducible from an IP alone by anyone without it — which
     * rules out the obvious "hash every IPv4 address" reversal.
     */
    private function ipHash(Request $request): ?string
    {
        $ip = $request->ip();

        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.'|'.(string) config('app.key'));
    }

    private function uaClass(Request $request): string
    {
        $ua = strtolower((string) $request->userAgent());

        if ($ua === '') {
            return 'unknown';
        }

        // Bot first: plenty of crawlers also claim to be mobile.
        if (preg_match('/bot|crawl|spider|slurp|curl|wget|headless|monitor|preview/', $ua)) {
            return 'bot';
        }

        if (preg_match('/mobile|android|iphone|ipad|ipod|tablet/', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Host only. The full referer can carry a query string (and on a
     * verification link, that query string is the token itself).
     */
    private function refererHost(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? mb_substr($host, 0, 255) : null;
    }
}
