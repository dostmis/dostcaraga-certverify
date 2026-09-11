<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Compatibility shim for jobs queued before the blockchain integration was removed.
 * Keep this class until pending and failed legacy jobs can no longer be retried.
 */
class AnchorCertificateOnHederaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120];

    public function __construct(public int $certificateId) {}

    public function handle(): void
    {
        // Intentionally do nothing: legacy jobs must not contact external services.
    }
}
