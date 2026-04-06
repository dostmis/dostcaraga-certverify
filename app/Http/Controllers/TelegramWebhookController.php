<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $expectedSecret = trim((string) config('services.telegram_bot.webhook_secret', ''));
        if ($expectedSecret === '' || !hash_equals($expectedSecret, $secret)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $update = $request->all();
        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (!is_array($message)) {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));
        if ($chatId === '') {
            return response()->json(['ok' => true]);
        }

        if ($text === '/start') {
            $reply = "DOST CERTiFY bot is active.\n"
                . "Your chat ID: {$chatId}\n"
                . "Share this ID with the system admin for TG_RD_CHAT_ID.";
            $this->sendTelegramMessage($chatId, $reply);
        } elseif ($text === '/chatid') {
            $this->sendTelegramMessage($chatId, "Your chat ID is: {$chatId}");
        }

        Log::info('Telegram inbound update received.', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        return response()->json(['ok' => true]);
    }

    private function sendTelegramMessage(string $chatId, string $text): void
    {
        $token = trim((string) config('services.telegram_bot.bot_token', ''));
        if ($token === '') {
            return;
        }

        $endpoint = "https://api.telegram.org/bot{$token}/sendMessage";

        try {
            Http::asJson()
                ->timeout(12)
                ->post($endpoint, [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram webhook reply failed.', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
