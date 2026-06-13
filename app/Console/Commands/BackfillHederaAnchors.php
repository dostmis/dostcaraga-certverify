<?php

namespace App\Console\Commands;

use App\Jobs\AnchorCertificateOnHederaJob;
use App\Models\Certificate;
use App\Services\HederaService;
use Illuminate\Console\Command;

/**
 * Anchors existing certificates that were issued before Hedera was enabled.
 * Dispatches one queued anchor job per certificate (idempotent — already
 * anchored certificates are skipped).
 */
class BackfillHederaAnchors extends Command
{
    protected $signature = 'hedera:backfill
        {--limit=0 : Maximum certificates to enqueue (0 = no limit)}
        {--sync : Run anchoring inline instead of dispatching to the queue}
        {--dry-run : Show how many would be anchored without doing anything}';

    protected $description = 'Anchor existing valid certificates to Hedera that have not been anchored yet.';

    public function handle(HederaService $hedera): int
    {
        if (!$hedera->enabled()) {
            $this->error('Hedera is not enabled. Set HEDERA_ENABLED=true and HEDERA_TOPIC_ID, then retry.');
            return self::FAILURE;
        }

        $query = Certificate::query()
            ->where('status', 'valid')
            ->where(function ($q) {
                $q->whereNull('blockchain_status')
                    ->orWhere('blockchain_status', '!=', Certificate::BLOCKCHAIN_STATUS_ANCHORED);
            });

        $total = (clone $query)->count();
        $this->info("Certificates needing anchoring: {$total}");

        if ($this->option('dry-run')) {
            $this->line('Dry run — nothing dispatched.');
            return self::SUCCESS;
        }

        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $query->limit($limit);
        }

        $sync = (bool) $this->option('sync');
        $count = 0;

        $query->orderBy('id')->chunkById(200, function ($certs) use (&$count, $sync) {
            foreach ($certs as $cert) {
                if ($sync) {
                    AnchorCertificateOnHederaJob::dispatchSync($cert->id);
                } else {
                    AnchorCertificateOnHederaJob::dispatch($cert->id);
                }
                $count++;
            }
        });

        $verb = $sync ? 'anchored inline' : 'dispatched to the queue';
        $this->info("{$count} certificate(s) {$verb}.");

        return self::SUCCESS;
    }
}
