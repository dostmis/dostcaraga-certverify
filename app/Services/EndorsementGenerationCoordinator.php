<?php

namespace App\Services;

use App\Jobs\GenerateCertificateJob;
use App\Jobs\RefreshAnalyticsJob;
use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Drives asynchronous certificate generation for an endorsement.
 *
 * The batch callbacks are serialized closures, so they cannot reach controller
 * methods — everything they need lives here and is keyed by scalars only.
 *
 * Failure is all-or-nothing: the batch halts on the first failed job and
 * catch() deletes every certificate the run created, returning the package to
 * `endorsed` exactly as if approval had never been clicked.
 */
class EndorsementGenerationCoordinator
{
    /**
     * Where the once-per-batch normalized template lives.
     *
     * Deliberately deterministic per endorsement rather than a UUID: the
     * finally() callback has to find it again to delete it, and a stuck batch
     * leaves at most one stale file per endorsement rather than an unbounded
     * pile.
     */
    public const TEMPLATE_DIR = 'tmp/generation';

    public function __construct(
        private readonly CertificateGenerationService $generator,
        private readonly EndorsementTelegramNotifier $telegram,
    ) {}

    /**
     * Claim an endorsement for generation, or fail if it is not claimable.
     *
     * The conditional update is the double-submission guard: two concurrent
     * approvals both try to move `endorsed` -> `generating` and only one
     * affects a row, so only one batch is ever dispatched.
     */
    public function claim(CertificateEndorsement $endorsement, ?int $approverId): bool
    {
        $claimed = CertificateEndorsement::query()
            ->whereKey($endorsement->getKey())
            ->where('status', CertificateEndorsement::STATUS_ENDORSED)
            ->update([
                'status' => CertificateEndorsement::STATUS_GENERATING,
                'generation_started_at' => now(),
                'generation_error' => null,
                'generation_batch_id' => null,
                'rd_approved_by' => $approverId,
                'rd_rejected_by' => null,
                'rd_rejected_at' => null,
                'rejection_reason' => null,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $endorsement->refresh();

        return true;
    }

    /**
     * Normalize the template once, dispatch one job per participant.
     *
     * @param  array<int, array<string, mixed>>  $participants
     *
     * @throws Throwable On any dispatch-time failure, after releasing the claim.
     */
    public function dispatchGeneration(
        CertificateEndorsement $endorsement,
        array $participants,
        ?int $approverId
    ): Batch {
        $endorsementId = (int) $endorsement->getKey();
        $payload = (array) $endorsement->payload;
        $templatePath = (string) $endorsement->template_pdf_path;

        $this->generator->assertPayloadIsGeneratable($payload, $templatePath);

        // Normalized once here, in the parent, and never inside a job: doing
        // it per participant runs Ghostscript N times and reverts the whole
        // Phase 1 gain (131 ms -> 29 ms per certificate).
        [$stampFrom, $temporaryTemplatePath] = $this->generator->normalizeTemplateForFpdi($templatePath);

        $batchTemplatePath = $stampFrom;
        if ($temporaryTemplatePath !== null) {
            // Move the converted file to a path the batch callbacks can find,
            // since normalizeTemplateForFpdi() names it with a random UUID.
            $batchTemplatePath = self::TEMPLATE_DIR.'/endorsement_'.$endorsementId.'.pdf';
            // A stale file from an abandoned run would make move() throw.
            Storage::disk('local')->delete($batchTemplatePath);
            Storage::disk('local')->move($temporaryTemplatePath, $batchTemplatePath);
        }

        $recipientMatches = (array) ($payload['recipient_matches'] ?? []);

        $jobs = [];
        foreach ($participants as $i => $participant) {
            $jobs[] = new GenerateCertificateJob(
                endorsementId: $endorsementId,
                participantIndex: (int) $i,
                participant: $participant,
                recipientId: $recipientMatches[$i] ?? null,
                payload: $payload,
                templatePath: $batchTemplatePath,
                applyRegionalDirectorESign: true,
            );
        }

        // Note: allowFailures() is deliberately NOT called. The default
        // halt-on-first-failure is what makes catch() fire, and catch() is the
        // rollback trigger.
        $isTemporaryTemplate = $temporaryTemplatePath !== null;

        try {
            $batch = Bus::batch($jobs)
                ->name('certificates:endorsement:'.$endorsementId)
                ->then(function (Batch $batch) use ($endorsementId, $approverId) {
                    app(EndorsementGenerationCoordinator::class)->finalizeSuccess($endorsementId, $approverId);
                })
                ->catch(function (Batch $batch, Throwable $e) use ($endorsementId) {
                    app(EndorsementGenerationCoordinator::class)->rollback($endorsementId, $e->getMessage());
                })
                ->finally(function (Batch $batch) use ($batchTemplatePath, $isTemporaryTemplate) {
                    if ($isTemporaryTemplate) {
                        Storage::disk('local')->delete($batchTemplatePath);
                    }
                })
                ->dispatch();
        } catch (Throwable $e) {
            if ($isTemporaryTemplate) {
                Storage::disk('local')->delete($batchTemplatePath);
            }
            $this->releaseClaim($endorsement, $e->getMessage());

            throw $e;
        }

        $endorsement->forceFill([
            'generation_batch_id' => $batch->id,
            'generated_count' => 0,
        ])->save();

        return $batch;
    }

    /**
     * Mark the package approved, queue emails, notify.
     *
     * Emails are queued here rather than at dispatch time precisely because
     * then() never runs on a failed batch — nothing leaves the system unless
     * every certificate exists.
     */
    public function finalizeSuccess(int $endorsementId, ?int $approverId): void
    {
        $endorsement = CertificateEndorsement::find($endorsementId);
        if (! $endorsement) {
            Log::warning('Certificate generation finished for a missing endorsement.', [
                'endorsement_id' => $endorsementId,
            ]);

            return;
        }

        $certificates = Certificate::query()
            ->where('endorsement_id', $endorsementId)
            ->orderBy('endorsement_participant_index')
            ->get()
            ->all();

        $endorsement->forceFill([
            'status' => CertificateEndorsement::STATUS_RD_APPROVED,
            'rd_approved_by' => $approverId ?? $endorsement->rd_approved_by,
            'rd_approved_at' => now(),
            'rd_rejected_by' => null,
            'rd_rejected_at' => null,
            'rejection_reason' => null,
            'generated_count' => count($certificates),
            'generation_error' => null,
        ])->save();

        $this->generator->queueGeneratedCertificateEmails($certificates);

        $approver = $endorsement->rd_approved_by
            ? User::find($endorsement->rd_approved_by)
            : null;

        $this->telegram->notifyOnRdApproval($endorsement, $approver, count($certificates));

        // Refresh analytics off the request path. Dispatched only on success,
        // after everything above has committed; it never touches generation
        // state, so it cannot affect the rollback path.
        RefreshAnalyticsJob::dispatch();
    }

    /**
     * Undo a failed run completely: rows, PDFs, and status.
     *
     * The only destructive path in the whole feature. Every query is scoped to
     * a non-null endorsement_id so a scoping bug cannot widen into a mass
     * delete — historic certificates have a NULL link and are unreachable
     * from here by construction.
     */
    public function rollback(int $endorsementId, string $error): void
    {
        if ($endorsementId <= 0) {
            Log::error('Refusing certificate rollback without an endorsement id.');

            return;
        }

        $certificates = Certificate::query()
            ->whereNotNull('endorsement_id')
            ->where('endorsement_id', $endorsementId)
            ->get(['id', 'certificate_code', 'source_pdf_path', 'stamped_pdf_path']);

        $deleted = 0;
        if ($certificates->isNotEmpty()) {
            $deleted = DB::transaction(function () use ($endorsementId) {
                return Certificate::query()
                    ->whereNotNull('endorsement_id')
                    ->where('endorsement_id', $endorsementId)
                    ->delete();
            });
        }

        // Files only after the rows commit. An orphaned file wastes disk; an
        // orphaned row is a certificate that verifies but cannot be printed.
        $storage = Storage::disk('local');
        foreach ($certificates as $certificate) {
            foreach ([$certificate->source_pdf_path, $certificate->stamped_pdf_path] as $path) {
                if (! empty($path)) {
                    $storage->delete($path);
                }
            }
        }

        Log::warning('Certificate generation rolled back.', [
            'endorsement_id' => $endorsementId,
            'certificates_deleted' => $deleted,
            'error' => $error,
        ]);

        $endorsement = CertificateEndorsement::find($endorsementId);
        if (! $endorsement) {
            return;
        }

        $this->releaseClaim($endorsement, $error);
        $this->telegram->notifyOnGenerationFailure($endorsement, $error);
    }

    /**
     * Return the package to its pre-approval state, recording the cause.
     */
    private function releaseClaim(CertificateEndorsement $endorsement, string $error): void
    {
        $endorsement->forceFill([
            'status' => CertificateEndorsement::STATUS_ENDORSED,
            'generation_error' => mb_substr($error, 0, 2000),
            'generation_batch_id' => null,
            'generation_started_at' => null,
            'generated_count' => 0,
            'rd_approved_by' => null,
            'rd_approved_at' => null,
        ])->save();
    }

    /**
     * Progress for the approvals view and its polling endpoint.
     *
     * Read from the batch rather than duplicated into counter columns, so
     * there is nothing to keep in sync.
     *
     * @return array{status: string, processed: int, total: int, failed: int, finished: bool, error: ?string}
     */
    public function progress(CertificateEndorsement $endorsement): array
    {
        $status = (string) $endorsement->status;
        $total = (int) ($endorsement->participants_count ?? 0);
        $processed = 0;
        $failed = 0;
        $finished = $status !== CertificateEndorsement::STATUS_GENERATING;

        $batchId = $endorsement->generation_batch_id;
        if ($batchId) {
            $batch = Bus::findBatch($batchId);
            if ($batch) {
                $total = $batch->totalJobs;
                $processed = $batch->processedJobs();
                $failed = $batch->failedJobs;
                $finished = $batch->finished() || $batch->cancelled();
            }
        }

        return [
            'status' => $status,
            'processed' => $processed,
            'total' => $total,
            'failed' => $failed,
            'finished' => $finished,
            'error' => $endorsement->generation_error,
        ];
    }
}
