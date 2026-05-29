<?php

namespace App\Services;

use App\Models\Recipient;

class RecipientMatchingService
{
    /**
     * Match a participant array against existing recipients.
     *
     * @param  array  $participant  Must have keys: name, email (optional), contact_number (optional)
     * @return array{recipient_id: int|null, confidence: string, ambiguous: bool, candidates: array}
     */
    public function match(array $participant): array
    {
        $name = trim((string) ($participant['name'] ?? ''));
        $email = trim((string) ($participant['email'] ?? ''));
        $contactNumber = trim((string) ($participant['contact_number'] ?? ''));

        // Level 1: Exact email match (highest confidence)
        if ($email !== '') {
            $recipient = Recipient::where('email', $email)->first();
            if ($recipient) {
                return [
                    'recipient_id' => $recipient->id,
                    'confidence' => 'exact_email',
                    'ambiguous' => false,
                    'candidates' => [],
                ];
            }
        }

        // Level 2: Name + contact_number exact match
        if ($contactNumber !== '' && $name !== '') {
            $recipient = Recipient::where('contact_number', $contactNumber)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
            if ($recipient) {
                return [
                    'recipient_id' => $recipient->id,
                    'confidence' => 'exact_name_contact',
                    'ambiguous' => false,
                    'candidates' => [],
                ];
            }
        }

        // Level 3: Exact name match (case-insensitive)
        if ($name !== '') {
            $recipient = Recipient::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            if ($recipient) {
                return [
                    'recipient_id' => $recipient->id,
                    'confidence' => 'exact_name',
                    'ambiguous' => false,
                    'candidates' => [],
                ];
            }

            // Level 4: Fuzzy name — normalize and compare
            $candidates = $this->fuzzyNameMatch($name);

            if ($candidates->count() === 1) {
                return [
                    'recipient_id' => $candidates->first()->id,
                    'confidence' => 'fuzzy_name',
                    'ambiguous' => false,
                    'candidates' => [],
                ];
            }

            if ($candidates->count() > 1) {
                return [
                    'recipient_id' => null,
                    'confidence' => 'ambiguous',
                    'ambiguous' => true,
                    'candidates' => $candidates->pluck('id', 'name')->toArray(),
                ];
            }
        }

        // No match
        return [
            'recipient_id' => null,
            'confidence' => 'no_match',
            'ambiguous' => false,
            'candidates' => [],
        ];
    }

    private function fuzzyNameMatch(string $name): \Illuminate\Support\Collection
    {
        $parts = preg_split('/\s+/', $name);
        $firstName = $parts[0] ?? '';
        $lastName = count($parts) > 1 ? end($parts) : '';

        if ($firstName === '' || $lastName === '') {
            return collect();
        }

        return Recipient::whereRaw(
            'LOWER(name) LIKE ? AND LOWER(name) LIKE ?',
            [mb_strtolower($firstName) . '%', '%' . mb_strtolower($lastName)]
        )->limit(10)->get();
    }
}
