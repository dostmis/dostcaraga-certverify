<?php

namespace Tests\Feature\Admin\Concerns;

use App\Models\Certificate;
use App\Services\CertificateGenerationService;

/**
 * Real generation, except it throws when it reaches one named participant.
 *
 * A subclass rather than a Mockery mock because the failure has to survive
 * being resolved inside `queue:work`, and because everything before the
 * failure must be genuinely generated — rows and PDF files — or the rollback
 * assertions would have nothing to check.
 */
class FailingCertificateGenerationService extends CertificateGenerationService
{
    public static string $failOnParticipantName = '';

    /**
     * PDF paths written before the failure, for the rollback assertions.
     *
     * @var array<int, string>
     */
    public static array $writtenPaths = [];

    public static function reset(): void
    {
        self::$failOnParticipantName = '';
        self::$writtenPaths = [];
    }

    public function generateForParticipant(
        array $payload,
        array $participant,
        ?int $recipientId,
        string $templatePath,
        bool $applyRegionalDirectorESign = false,
        ?int $endorsementId = null,
        ?int $participantIndex = null
    ): Certificate {
        if (($participant['name'] ?? null) === self::$failOnParticipantName) {
            throw new \RuntimeException('Injected generation failure');
        }

        $certificate = parent::generateForParticipant(
            $payload,
            $participant,
            $recipientId,
            $templatePath,
            $applyRegionalDirectorESign,
            $endorsementId,
            $participantIndex
        );

        foreach ([$certificate->source_pdf_path, $certificate->stamped_pdf_path] as $path) {
            if (! empty($path)) {
                self::$writtenPaths[] = $path;
            }
        }

        return $certificate;
    }
}
