<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParticipantIntake;
use Illuminate\Http\Request;

class ParticipantSearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $results = ParticipantIntake::query()
            ->where(function ($query) use ($q) {
                $query->where('participant_name', 'ILIKE', "%{$q}%")
                    ->orWhere('first_name', 'ILIKE', "%{$q}%")
                    ->orWhere('last_name', 'ILIKE', "%{$q}%");
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $seen = [];
        $unique = [];
        foreach ($results as $intake) {
            $key = strtolower($intake->email);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = [
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
                ];
            }
        }

        return response()->json($unique);
    }
}
