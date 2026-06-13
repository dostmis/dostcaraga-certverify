<?php

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client around the Hedera Node bridge (write path) and the public Hedera
 * Mirror Node REST API (read path).
 *
 * Design notes:
 * - The operator private key never lives in PHP. Submitting a message is done
 *   by POSTing to the local Node bridge, which holds the key.
 * - Verification is read-only and hits the public mirror node directly, so it
 *   works even if the bridge is down.
 * - Every method degrades gracefully: on any failure it returns a null/empty
 *   result and logs, so the certificate flow and verify page never break.
 */
class HederaService
{
    public function enabled(): bool
    {
        return (bool) config('services.hedera.enabled')
            && !empty(config('services.hedera.topic_id'));
    }

    public function topicId(): ?string
    {
        return config('services.hedera.topic_id');
    }

    public function explorerTopicUrl(?string $topicId = null): ?string
    {
        $topicId = $topicId ?: $this->topicId();
        $base = rtrim((string) config('services.hedera.explorer_url'), '/');
        if ($topicId === null || $base === '') {
            return null;
        }

        return "{$base}/topic/{$topicId}";
    }

    /**
     * Submit a single HCS message via the Node bridge.
     *
     * @return array{sequence_number:int,consensus_timestamp:string,transaction_id:string}|null
     */
    public function submitMessage(string $message): ?array
    {
        $url = rtrim((string) config('services.hedera.bridge_url'), '/') . '/anchor';

        try {
            $response = Http::timeout((int) config('services.hedera.bridge_timeout', 20))
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'topicId' => $this->topicId(),
                    'message' => $message,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Hedera bridge request failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$response->successful()) {
            Log::warning('Hedera bridge returned an error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        if (!is_array($data) || empty($data['sequenceNumber'])) {
            Log::warning('Hedera bridge returned an unexpected payload', ['body' => $response->body()]);
            return null;
        }

        return [
            'sequence_number' => (int) $data['sequenceNumber'],
            'consensus_timestamp' => (string) ($data['consensusTimestamp'] ?? ''),
            'transaction_id' => (string) ($data['transactionId'] ?? ''),
        ];
    }

    /**
     * Build the JSON message that gets anchored on-chain for a certificate.
     * Keep this small: a topic message has a 1KB practical sweet spot.
     */
    public function buildMessage(Certificate $cert): string
    {
        return (string) json_encode([
            'v' => 1,
            'system' => 'dostcaraga-certify',
            'certificate_code' => $cert->certificate_code,
            'public_token' => $cert->public_token,
            'hash' => $cert->canonicalHash(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Fetch a specific topic message from the public mirror node and return the
     * decoded JSON payload, or null if not found / unreachable.
     */
    public function fetchAnchoredMessage(string $topicId, int $sequenceNumber): ?array
    {
        $base = rtrim((string) config('services.hedera.mirror_url'), '/');
        $url = "{$base}/api/v1/topics/{$topicId}/messages/{$sequenceNumber}";

        try {
            $response = Http::timeout((int) config('services.hedera.mirror_timeout', 8))
                ->acceptJson()
                ->get($url);
        } catch (\Throwable $e) {
            Log::info('Hedera mirror node unreachable', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $body = $response->json();
        if (!is_array($body) || empty($body['message'])) {
            return null;
        }

        // Mirror node returns the message base64-encoded.
        $decoded = base64_decode((string) $body['message'], true);
        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            return null;
        }

        return [
            'payload' => $payload,
            'consensus_timestamp' => (string) ($body['consensus_timestamp'] ?? ''),
            'sequence_number' => (int) ($body['sequence_number'] ?? $sequenceNumber),
        ];
    }

    /**
     * Verify a certificate against the ledger. Returns a structured result the
     * verify view can render. Never throws.
     *
     * @return array{state:string,message:string,onchain_hash:?string,consensus_timestamp:?string}
     */
    public function verifyCertificate(Certificate $cert): array
    {
        $expectedHash = $cert->canonicalHash();

        if (!$cert->isAnchored() || empty($cert->blockchain_topic_id)) {
            return [
                'state' => 'not_anchored',
                'message' => 'This certificate has not been anchored to the blockchain.',
                'onchain_hash' => null,
                'consensus_timestamp' => null,
            ];
        }

        $remote = $this->fetchAnchoredMessage(
            (string) $cert->blockchain_topic_id,
            (int) $cert->blockchain_sequence_number
        );

        if ($remote === null) {
            // Mirror node unreachable — fall back to the stored anchor record.
            return [
                'state' => 'unavailable',
                'message' => 'Blockchain record exists but the public ledger could not be reached right now.',
                'onchain_hash' => $cert->blockchain_payload_hash,
                'consensus_timestamp' => $cert->blockchain_consensus_timestamp,
            ];
        }

        $onchainHash = (string) ($remote['payload']['hash'] ?? '');

        if ($onchainHash !== '' && hash_equals($onchainHash, $expectedHash)) {
            return [
                'state' => 'verified',
                'message' => 'Anchored on Hedera Consensus Service and the on-chain hash matches this record.',
                'onchain_hash' => $onchainHash,
                'consensus_timestamp' => $remote['consensus_timestamp'] ?: $cert->blockchain_consensus_timestamp,
            ];
        }

        return [
            'state' => 'mismatch',
            'message' => 'WARNING: the on-chain hash does not match this record. Possible tampering.',
            'onchain_hash' => $onchainHash ?: null,
            'consensus_timestamp' => $remote['consensus_timestamp'] ?: null,
        ];
    }
}
