<?php

namespace App\Services;

use App\Models\CertificateEndorsement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram notifications for the certificate endorsement workflow.
 *
 * Extracted from CertificateAdminController because the asynchronous approval
 * path fires these from serialized Bus batch callbacks, which cannot reach
 * controller methods.
 */
class EndorsementTelegramNotifier
{
    public function notifyOnEndorsement(
        CertificateEndorsement $endorsement,
        mixed $submitter
    ): void {
        if (! config('services.telegram_bot.enabled', false)) {
            return;
        }

        $participantsCount = (int) ($endorsement->participants_count ?? 0);
        $notifyEvery = (bool) config('services.telegram_bot.notify_on_every_endorsement', true);
        $bulkThreshold = max(1, (int) config('services.telegram_bot.bulk_threshold', 50));
        if (! $notifyEvery && $participantsCount < $bulkThreshold) {
            return;
        }

        $chatIds = $this->recipientChatIds();
        if (empty($chatIds)) {
            Log::warning('Telegram endorsement notification skipped: missing Telegram chat recipients.');

            return;
        }

        $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
        $trainingTitle = trim((string) ($payload['training_title'] ?? 'Untitled training'));
        $dateRange = $this->formatDateRange($payload);
        $submittedBy = trim((string) ($submitter?->name ?? 'Unknown submitter'));
        $approvalsUrl = url('/admin/certificates/approvals');

        $message = "New certificate endorsement submitted.\n"
            ."Training: {$trainingTitle}\n"
            ."Schedule: {$dateRange}\n"
            ."Participants: {$participantsCount}\n"
            ."Submitted by: {$submittedBy}\n"
            ."Review queue: {$approvalsUrl}";

        $this->sendToChats($chatIds, $message, $endorsement->id);
    }

    /**
     * Announce that queued generation failed and the package was rolled back.
     *
     * Deliberately not subject to the bulk threshold that gates the success
     * notification: a small failed package still needs someone to know, and a
     * silent failure is worse than a noisy one.
     */
    public function notifyOnGenerationFailure(
        CertificateEndorsement $endorsement,
        string $error
    ): void {
        if (! config('services.telegram_bot.enabled', false)) {
            return;
        }

        if (! config('services.telegram_bot.notify_on_generation_failure', true)) {
            return;
        }

        $chatIds = $this->recipientChatIds();
        if (empty($chatIds)) {
            Log::warning('Telegram generation failure notification skipped: missing Telegram chat recipients.');

            return;
        }

        $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
        $trainingTitle = trim((string) ($payload['training_title'] ?? 'Untitled training'));
        $dateRange = $this->formatDateRange($payload);
        $participantsCount = (int) ($endorsement->participants_count ?? 0);
        $approvalsUrl = url('/admin/certificates/approvals');

        $message = "Certificate generation FAILED and was rolled back.\n"
            ."Training: {$trainingTitle}\n"
            ."Schedule: {$dateRange}\n"
            ."Participants: {$participantsCount}\n"
            .'Error: '.mb_substr($error, 0, 400)."\n"
            ."No certificates were issued and no emails were sent. The package is back in the approval queue.\n"
            ."Approval queue: {$approvalsUrl}";

        $this->sendToChats($chatIds, $message, $endorsement->id);
    }

    public function notifyOnRdApproval(
        CertificateEndorsement $endorsement,
        mixed $approver,
        int $generatedCount
    ): void {
        if (! config('services.telegram_bot.enabled', false)) {
            return;
        }

        if (! config('services.telegram_bot.notify_on_rd_approval', true)) {
            return;
        }

        $bulkThreshold = max(1, (int) config('services.telegram_bot.bulk_threshold', 50));
        $bulkOnly = (bool) config('services.telegram_bot.notify_on_rd_approval_bulk_only', true);
        if ($bulkOnly && $generatedCount < $bulkThreshold) {
            return;
        }

        $chatIds = $this->recipientChatIds();
        if (empty($chatIds)) {
            Log::warning('Telegram RD approval notification skipped: missing Telegram chat recipients.');

            return;
        }

        $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
        $trainingTitle = trim((string) ($payload['training_title'] ?? 'Untitled training'));
        $dateRange = $this->formatDateRange($payload);
        $participantsCount = (int) ($endorsement->participants_count ?? $generatedCount);
        $approvedBy = trim((string) ($approver?->name ?? 'Regional Director'));
        $queueUrl = url('/admin/certificates?status=rd_approved');

        $message = "RD approved certificate endorsement.\n"
            ."Training: {$trainingTitle}\n"
            ."Schedule: {$dateRange}\n"
            ."Participants: {$participantsCount}\n"
            ."Generated: {$generatedCount}\n"
            ."Approved by: {$approvedBy}\n"
            ."Approved queue: {$queueUrl}";

        $this->sendToChats($chatIds, $message, $endorsement->id);
    }

    private function recipientChatIds(): array
    {
        $chatIds = [];
        $chatIdsCsv = (string) config('services.telegram_bot.chat_ids', '');
        if ($chatIdsCsv !== '') {
            $chatIds = array_map('trim', explode(',', $chatIdsCsv));
        }

        $legacyChatId = trim((string) config('services.telegram_bot.rd_chat_id', ''));
        if ($legacyChatId !== '') {
            foreach (explode(',', $legacyChatId) as $legacyChatIdPart) {
                $legacyChatIdPart = trim($legacyChatIdPart);
                if ($legacyChatIdPart !== '') {
                    $chatIds[] = $legacyChatIdPart;
                }
            }
        }

        $uniqueValid = [];
        foreach ($chatIds as $chatId) {
            if ($chatId === '') {
                continue;
            }

            if (! preg_match('/^(-?\d+|@[A-Za-z0-9_]{5,})$/', $chatId)) {
                continue;
            }

            $uniqueValid[$chatId] = true;
        }

        return array_keys($uniqueValid);
    }

    private function sendToChats(array $chatIds, string $message, int $endorsementId): void
    {
        $botToken = trim((string) config('services.telegram_bot.bot_token', ''));
        if ($botToken === '') {
            Log::warning('Telegram notification skipped: missing bot token.', [
                'endorsement_id' => $endorsementId,
            ]);

            return;
        }

        $endpoint = "https://api.telegram.org/bot{$botToken}/sendMessage";
        foreach ($chatIds as $chatId) {
            try {
                $response = Http::asJson()
                    ->timeout(12)
                    ->post($endpoint, [
                        'chat_id' => $chatId,
                        'text' => $message,
                        'disable_web_page_preview' => true,
                    ]);

                if (! $response->successful()) {
                    Log::warning('Telegram notification failed.', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'chat_id' => $chatId,
                        'endorsement_id' => $endorsementId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Telegram notification threw an exception.', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                    'endorsement_id' => $endorsementId,
                ]);
            }
        }
    }

    public function formatDateRange(array $payload): string
    {
        $from = $payload['training_date_from'] ?? null;
        $to = $payload['training_date_to'] ?? $from;

        if (! $from) {
            return '-';
        }

        return $from === $to ? $from : ($from.' to '.$to);
    }
}
