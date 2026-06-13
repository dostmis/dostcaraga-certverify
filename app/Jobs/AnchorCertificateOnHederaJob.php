<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Services\HederaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Anchors a single certificate's canonical hash to Hedera Consensus Service.
 *
 * Runs on the queue so certificate issuance is never blocked by network
 * latency. Safe to retry and idempotent: an already-anchored certificate is
 * skipped, so re-dispatching never produces duplicate anchors.
 */
class AnchorCertificateOnHederaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120];

    public function __construct(public int $certificateId)
    {
    }

    public function handle(HederaService $hedera): void
    {
        if (!$hedera->enabled()) {
            return;
        }

        $cert = Certificate::find($this->certificateId);
        if (!$cert || !$cert->isValid()) {
            return;
        }

        // Idempotency: already anchored, nothing to do.
        if ($cert->isAnchored()) {
            return;
        }

        $cert->forceFill([
            'blockchain_status' => Certificate::BLOCKCHAIN_STATUS_PENDING,
        ])->save();

        $hash = $cert->canonicalHash();
        $result = $hedera->submitMessage($hedera->buildMessage($cert));

        if ($result === null) {
            // Throw so the queue retries with backoff; mark failed on last try.
            if ($this->attempts() >= $this->tries) {
                $cert->forceFill([
                    'blockchain_status' => Certificate::BLOCKCHAIN_STATUS_FAILED,
                    'blockchain_error' => 'Hedera bridge did not return a receipt after retries.',
                ])->save();

                return;
            }

            throw new \RuntimeException('Hedera anchoring failed; will retry.');
        }

        $cert->forceFill([
            'blockchain_payload_hash' => $hash,
            'blockchain_topic_id' => $hedera->topicId(),
            'blockchain_sequence_number' => $result['sequence_number'],
            'blockchain_consensus_timestamp' => $result['consensus_timestamp'],
            'blockchain_transaction_id' => $result['transaction_id'],
            'blockchain_status' => Certificate::BLOCKCHAIN_STATUS_ANCHORED,
            'blockchain_error' => null,
            'blockchain_anchored_at' => now(),
        ])->save();
    }
}
