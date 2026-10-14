<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Services\CertificateGenerationService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generate one participant's certificate.
 *
 * One job per participant so a large package cannot exceed the worker timeout.
 * The template is normalized once by the dispatcher and its path passed in —
 * normalizing per job would run Ghostscript once per certificate and undo the
 * entire Phase 1 performance gain.
 */
class GenerateCertificateJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Lock name serializing every certificate creation in the system.
     *
     * Certificate codes are derived from a read-then-insert with no row lock,
     * so two concurrent workers would compute the same code and one would hit
     * the unique constraint. Serializing here makes numbering safe regardless
     * of how many workers a future operator runs.
     */
    public const CODE_LOCK = 'certificate-code-generation';

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $participant  One parsed participant row.
     * @param  array<string, mixed>  $payload  The endorsement training payload.
     */
    public function __construct(
        public int $endorsementId,
        public int $participantIndex,
        public array $participant,
        public ?int $recipientId,
        public array $payload,
        public string $templatePath,
        public bool $applyRegionalDirectorESign = true,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            // shared() keys the lock on the string alone rather than on the
            // job class, so any future job that also creates certificates
            // serializes against this one instead of racing it.
            (new WithoutOverlapping(self::CODE_LOCK))
                ->shared()
                ->releaseAfter(5)
                ->expireAfter(120),
        ];
    }

    public function handle(CertificateGenerationService $generator): void
    {
        // The batch halts on first failure; anything already queued should not
        // create more rows for a package that is about to be rolled back.
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->alreadyGenerated()) {
            return;
        }

        try {
            $generator->generateForParticipant(
                $this->payload,
                $this->participant,
                $this->recipientId,
                $this->templatePath,
                $this->applyRegionalDirectorESign,
                $this->endorsementId,
                $this->participantIndex
            );
        } catch (UniqueConstraintViolationException $e) {
            // Serialization via WithoutOverlapping is supposed to make code
            // collisions impossible, so one means the guarantee is broken.
            // Retrying would just paper over it — fail immediately so the
            // batch rolls back and the cause surfaces.
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Certificate generation job failed.', [
            'endorsement_id' => $this->endorsementId,
            'participant_index' => $this->participantIndex,
            'participant_name' => $this->participant['name'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Guard against a retry duplicating a row whose insert already committed.
     *
     * Laravel retries a failed job up to $tries, and a job that threw after
     * createCertificate() committed would otherwise issue a second
     * certificate for the same participant.
     */
    private function alreadyGenerated(): bool
    {
        return Certificate::query()
            ->where('endorsement_id', $this->endorsementId)
            ->where('endorsement_participant_index', $this->participantIndex)
            ->exists();
    }
}
