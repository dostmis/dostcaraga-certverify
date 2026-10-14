<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParticipantIntake;
use Illuminate\Http\Request;

class ParticipantSearchController extends Controller
{
    /**
     * Public returning-participant lookup.
     *
     * SECURITY: this endpoint is reachable from the public intake form, so it
     * must never disclose personal data. It returns ONLY an opaque id and the
     * display name needed to render the pick list. The full profile is released
     * exclusively by verify() after the caller proves ownership of the record
     * with the matching mobile number.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // Escape LIKE wildcards so callers cannot turn the query into a
        // match-everything pattern (e.g. "%%") and enumerate the table.
        $term = '%'.addcslashes($q, '%_\\').'%';

        $results = ParticipantIntake::query()
            ->where(function ($query) use ($term) {
                $query->where('participant_name', 'ILIKE', $term)
                    ->orWhere('first_name', 'ILIKE', $term)
                    ->orWhere('last_name', 'ILIKE', $term);
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['id', 'participant_name']);

        $seen = [];
        $unique = [];
        foreach ($results as $intake) {
            $key = mb_strtolower((string) $intake->participant_name);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = [
                    'id' => $intake->id,
                    'participant_name' => $intake->participant_name,
                ];
            }
        }

        return response()->json($unique);
    }

    /**
     * Release a participant's full profile for autofill, but only after the
     * caller proves ownership by supplying the mobile number on record.
     *
     * The comparison happens server-side so the contact number is never shipped
     * to an unauthenticated browser before it is proven.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'mobile' => ['required', 'string', 'max:30'],
        ]);

        $intake = ParticipantIntake::query()->find($validated['id']);

        $entered = preg_replace('/\D/', '', $validated['mobile']);
        $expected = $intake ? preg_replace('/\D/', '', (string) $intake->contact_number) : null;

        if (! $intake || $expected === null || $expected === '' || $entered !== $expected) {
            return response()->json(['verified' => false], 422);
        }

        return response()->json([
            'verified' => true,
            'participant' => [
                'id' => $intake->id,
                'participant_name' => $intake->participant_name,
                'first_name' => $intake->first_name,
                'last_name' => $intake->last_name,
                'middle_initial' => $intake->middle_initial,
                'email' => $intake->email,
                'contact_number' => $intake->contact_number,
                'gender' => $intake->gender,
                'age_range' => $intake->age_range,
                'pwd_status' => $intake->pwd_status,
                'is_4ps_beneficiary' => $intake->is_4ps_beneficiary,
                'is_elcac_community' => $intake->is_elcac_community,
                'organization_name' => $intake->organization_name,
                'industry' => $intake->industry,
                'position_designation' => $intake->position_designation,
                'region' => $intake->region,
                'province' => $intake->province,
                'city_municipality' => $intake->city_municipality,
                'barangay' => $intake->barangay,
                'block_lot_purok' => $intake->block_lot_purok,
                'dost_program_beneficiary' => $intake->dost_program_beneficiary,
                'directly_employed_programs' => $intake->directly_employed_programs,
                'has_attended_dost_training' => $intake->has_attended_dost_training,
                'interested_dost_services' => $intake->interested_dost_services,
                'interested_dost_services_other' => $intake->interested_dost_services_other,
            ],
        ]);
    }
}
