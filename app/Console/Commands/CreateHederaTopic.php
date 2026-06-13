<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * One-time setup: ask the Node bridge to create a new HCS topic. The returned
 * topic id is what you put in HEDERA_TOPIC_ID. The bridge holds the operator
 * key, so this command never touches it.
 */
class CreateHederaTopic extends Command
{
    protected $signature = 'hedera:create-topic
        {--memo=dostcaraga-certify : Topic memo stored on-chain}';

    protected $description = 'Create a Hedera Consensus Service topic via the Node bridge (run once).';

    public function handle(): int
    {
        $bridge = rtrim((string) config('services.hedera.bridge_url'), '/');
        if ($bridge === '') {
            $this->error('HEDERA_BRIDGE_URL is not configured.');
            return self::FAILURE;
        }

        $this->info("Requesting a new topic from the bridge at {$bridge} ...");

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->asJson()
                ->post("{$bridge}/create-topic", [
                    'memo' => (string) $this->option('memo'),
                ]);
        } catch (\Throwable $e) {
            $this->error('Could not reach the Hedera bridge: ' . $e->getMessage());
            $this->line('Is the hedera-bridge service running? (systemctl status hedera-bridge)');
            return self::FAILURE;
        }

        if (!$response->successful()) {
            $this->error('Bridge returned an error (' . $response->status() . '): ' . $response->body());
            return self::FAILURE;
        }

        $topicId = (string) ($response->json('topicId') ?? '');
        if ($topicId === '') {
            $this->error('Bridge did not return a topicId. Body: ' . $response->body());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Topic created successfully.');
        $this->line('  Topic ID: ' . $topicId);
        $this->newLine();
        $this->warn('Next steps:');
        $this->line('  1. Put this in your .env:   HEDERA_TOPIC_ID=' . $topicId);
        $this->line('  2. Set                       HEDERA_ENABLED=true');
        $this->line('  3. Run                       php artisan config:clear');
        $this->line('  4. (Optional) backfill:      php artisan hedera:backfill');

        return self::SUCCESS;
    }
}
