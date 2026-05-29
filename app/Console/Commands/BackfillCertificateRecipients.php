<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Models\Recipient;
use App\Services\RecipientMatchingService;
use Illuminate\Console\Command;

class BackfillCertificateRecipients extends Command
{
    protected $signature = 'certificates:backfill-recipients';
    protected $description = 'Link existing certificates to recipients by email and name matching';

    public function handle(): int
    {
        $certificates = Certificate::whereNull('recipient_id')->get();
        $matchingService = app(RecipientMatchingService::class);

        $linked = 0;
        $created = 0;
        $skipped = 0;

        $this->info("Found {$certificates->count()} certificates without a recipient.");

        foreach ($certificates as $cert) {
            $result = $matchingService->match([
                'name' => $cert->participant_name,
                'email' => $cert->email,
                'contact_number' => null,
            ]);

            if ($result['recipient_id']) {
                // Linked to existing recipient
                $cert->update(['recipient_id' => $result['recipient_id']]);
                $linked++;
            } elseif (! empty($cert->email)) {
                // Create dormant recipient for unmatched certificates with email
                $recipient = Recipient::create([
                    'name' => $cert->participant_name,
                    'email' => $cert->email,
                    'gender' => $cert->gender,
                    'password' => null,
                ]);
                $cert->update(['recipient_id' => $recipient->id]);
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->info("Backfill complete.");
        $this->info("  Linked to existing recipients: {$linked}");
        $this->info("  Created new dormant recipients: {$created}");
        $this->info("  Skipped (no email): {$skipped}");

        return self::SUCCESS;
    }
}
