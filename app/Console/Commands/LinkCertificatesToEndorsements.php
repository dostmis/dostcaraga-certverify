<?php

namespace App\Console\Commands;

use App\Models\Certificate;
use App\Models\CertificateEndorsement;
use Illuminate\Console\Command;

/**
 * Links certificates generated before certificates.certificate_endorsement_id
 * existed to the endorsement package they came from, so endorsers can track
 * their delivery.
 *
 * Approving a package generates its certificates immediately before the
 * approval time is saved, so a package's certificates are the N most recent
 * unlinked certificates with the same training title, office and start date
 * created just before rd_approved_at (N = generated_count). Packages where
 * that is not clear-cut are listed for review instead of guessed.
 */
class LinkCertificatesToEndorsements extends Command
{
    private const WINDOW_HOURS = 3;

    /** Another matching certificate this close to the batch makes it ambiguous. */
    private const AMBIGUITY_SECONDS = 60;

    protected $signature = 'certificates:link-endorsements
        {--apply : Save the links. Without this the command only reports what it would do}';

    protected $description = 'Link existing certificates to the endorsement package they were generated from';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $claimed = [];
        $linkedPackages = 0;
        $linkedCertificates = 0;
        $alreadyLinked = 0;
        $review = [];

        $endorsements = CertificateEndorsement::query()
            ->where('status', CertificateEndorsement::STATUS_RD_APPROVED)
            ->whereNotNull('rd_approved_at')
            ->orderBy('rd_approved_at')
            ->orderBy('id')
            ->get();

        foreach ($endorsements as $endorsement) {
            if ($endorsement->certificates()->exists()) {
                $alreadyLinked++;

                continue;
            }

            $payload = (array) $endorsement->payload;
            $expected = (int) $endorsement->generated_count;
            $title = trim((string) ($payload['training_title'] ?? '')) ?: 'Untitled';

            if ($expected < 1 || empty($payload['training_date_from'])) {
                $review[] = [$endorsement->id, $title, 'missing generated count or training date'];

                continue;
            }

            $approvedAt = $endorsement->rd_approved_at;
            $candidates = Certificate::query()
                ->whereNull('certificate_endorsement_id')
                ->where('training_title', (string) ($payload['training_title'] ?? ''))
                ->where('issuing_office', (string) ($payload['issuing_office'] ?? ''))
                ->whereDate('training_date', $payload['training_date_from'])
                ->whereBetween('created_at', [$approvedAt->copy()->subHours(self::WINDOW_HOURS), $approvedAt])
                ->when($claimed !== [], fn ($query) => $query->whereNotIn('id', array_keys($claimed)))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($expected + 1)
                ->get(['id', 'created_at']);

            $matched = $candidates->take($expected);
            if ($matched->count() < $expected) {
                $review[] = [$endorsement->id, $title, sprintf('found %d of %d certificates', $matched->count(), $expected)];

                continue;
            }

            $next = $candidates->get($expected);
            if ($next && abs($matched->last()->created_at->diffInSeconds($next->created_at)) < self::AMBIGUITY_SECONDS) {
                $review[] = [$endorsement->id, $title, 'another certificate for the same training was created at the same time'];

                continue;
            }

            $ids = $matched->pluck('id')->all();
            foreach ($ids as $id) {
                $claimed[$id] = true;
            }

            if ($apply) {
                // Base query: linking is bookkeeping, so leave updated_at alone.
                Certificate::query()->whereIn('id', $ids)->toBase()
                    ->update(['certificate_endorsement_id' => $endorsement->id]);
            }

            $linkedPackages++;
            $linkedCertificates += count($ids);
        }

        $this->info(sprintf(
            '%s %d certificates across %d packages.',
            $apply ? 'Linked' : 'Would link',
            $linkedCertificates,
            $linkedPackages
        ));

        if ($alreadyLinked > 0) {
            $this->line("Already linked: {$alreadyLinked} packages.");
        }

        if ($review !== []) {
            $this->warn('Needs review (not linked): '.count($review).' packages.');
            $this->table(['Endorsement', 'Training', 'Why'], $review);
        }

        if (! $apply && $linkedPackages > 0) {
            $this->newLine();
            $this->line('Dry run - nothing was saved. Run again with --apply to save these links.');
        }

        return self::SUCCESS;
    }
}
