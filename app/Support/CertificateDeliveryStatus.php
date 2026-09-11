<?php

namespace App\Support;

use App\Models\Certificate;

/**
 * Turns a certificate's raw email delivery fields into what an endorser needs
 * to see: a small set of outcomes, and a plain-language reason instead of raw
 * SMTP errors.
 *
 * "Sent" means the mail provider accepted the message. It can still bounce or
 * be blocked afterwards, which the system cannot see.
 */
final class CertificateDeliveryStatus
{
    public const SENT = 'sent';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public const NO_EMAIL = 'no_email';

    public const INVALID_EMAIL = 'invalid_email';

    public const HELD = 'held';

    /**
     * All outcomes, problems first.
     *
     * @return list<string>
     */
    public static function outcomes(): array
    {
        return [self::FAILED, self::INVALID_EMAIL, self::NO_EMAIL, self::HELD, self::PENDING, self::SENT];
    }

    public static function outcomeFor(Certificate $certificate): string
    {
        if ($certificate->email_sent_at !== null) {
            return self::SENT;
        }

        return match ($certificate->email_delivery_status) {
            Certificate::EMAIL_STATUS_HELD => self::HELD,
            Certificate::EMAIL_STATUS_SKIPPED_NO_EMAIL => self::NO_EMAIL,
            Certificate::EMAIL_STATUS_SKIPPED_INVALID_EMAIL => self::INVALID_EMAIL,
            Certificate::EMAIL_STATUS_FAILED => self::FAILED,
            default => self::PENDING,
        };
    }

    /**
     * SQL equivalent of outcomeFor(), for grouped counts. Built only from
     * class constants, never from input.
     */
    public static function outcomeSql(): string
    {
        return sprintf(
            "CASE WHEN email_sent_at IS NOT NULL THEN '%s'"
            ." WHEN email_delivery_status = '%s' THEN '%s'"
            ." WHEN email_delivery_status = '%s' THEN '%s'"
            ." WHEN email_delivery_status = '%s' THEN '%s'"
            ." WHEN email_delivery_status = '%s' THEN '%s'"
            ." ELSE '%s' END",
            self::SENT,
            Certificate::EMAIL_STATUS_HELD, self::HELD,
            Certificate::EMAIL_STATUS_SKIPPED_NO_EMAIL, self::NO_EMAIL,
            Certificate::EMAIL_STATUS_SKIPPED_INVALID_EMAIL, self::INVALID_EMAIL,
            Certificate::EMAIL_STATUS_FAILED, self::FAILED,
            self::PENDING
        );
    }

    /**
     * Outcome counts for several endorsements in one query.
     *
     * @param  array<int>  $endorsementIds
     * @return array<int, array<string, int>> endorsement id => outcome => count
     */
    public static function countsByEndorsement(array $endorsementIds): array
    {
        if ($endorsementIds === []) {
            return [];
        }

        $outcome = self::outcomeSql();
        $rows = Certificate::query()
            ->whereIn('certificate_endorsement_id', $endorsementIds)
            ->selectRaw("certificate_endorsement_id, {$outcome} AS outcome, COUNT(*) AS total")
            ->groupByRaw("certificate_endorsement_id, {$outcome}")
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->certificate_endorsement_id][(string) $row->outcome] = (int) $row->total;
        }

        return $counts;
    }

    public static function label(string $outcome): string
    {
        return match ($outcome) {
            self::SENT => 'Sent',
            self::PENDING => 'Waiting to send',
            self::FAILED => 'Failed',
            self::NO_EMAIL => 'No email',
            self::INVALID_EMAIL => 'Invalid email',
            self::HELD => 'Held',
            default => ucfirst(str_replace('_', ' ', $outcome)),
        };
    }

    /**
     * Badge class, reusing the certificate list's status colours.
     */
    public static function badgeClass(string $outcome): string
    {
        return match ($outcome) {
            self::SENT => 'status-valid',
            self::PENDING => 'status-endorsed',
            self::FAILED => 'status-invalid',
            self::NO_EMAIL, self::INVALID_EMAIL => 'status-revoked',
            default => 'status-default',
        };
    }

    /**
     * Whether someone has to act for this certificate to reach the participant.
     */
    public static function isProblem(string $outcome): bool
    {
        return in_array($outcome, [self::FAILED, self::INVALID_EMAIL, self::NO_EMAIL, self::HELD], true);
    }

    public static function reasonFor(Certificate $certificate): ?string
    {
        $message = trim((string) $certificate->email_failure_message);

        return match (self::outcomeFor($certificate)) {
            self::SENT => null,
            self::PENDING => match (true) {
                $certificate->email_delivery_status === null => 'Not queued for sending yet.',
                $certificate->email_failed_at !== null => 'An earlier attempt failed. It has been queued to send again.',
                default => 'Queued to be sent.',
            },
            self::NO_EMAIL => 'No email address was provided for this participant.',
            self::INVALID_EMAIL => sprintf('"%s" is not a valid email address.', trim((string) $certificate->email)),
            self::HELD => $message !== '' ? $message : 'Held back by the administrator.',
            self::FAILED => self::explainFailure($message),
        };
    }

    private static function explainFailure(string $message): string
    {
        if (str_contains($message, '454')) {
            return 'The mail server was temporarily busy (too many emails at once). The administrator can resend it.';
        }

        if (str_contains($message, '535') || stripos($message, 'authenticate') !== false) {
            return 'The system could not sign in to the mail server. The administrator needs to check the mail settings.';
        }

        if (stripos($message, 'PDF is missing') !== false) {
            return 'The certificate file is missing. The administrator needs to regenerate it.';
        }

        return 'The email could not be sent. Please contact the administrator.';
    }
}
