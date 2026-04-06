<?php

namespace App\Http\Controllers;

use App\Models\ParticipantIntake;
use App\Models\ParticipantIntakeEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ParticipantIntakeController extends Controller
{
    public function create(string $token)
    {
        if (!\App\Models\Setting::getBool('participant_intake_enabled', true)) {
            return view('participant-intake.disabled');
        }

        $event = ParticipantIntakeEvent::query()
            ->where('public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$event) {
            abort(404, 'Participant intake link is invalid or inactive.');
        }

        return view('participant-intake.create', [
            'intakeEvent' => $event,
            'yesNoOptions' => $this->yesNoOptions(),
            'beneficiaryProgramOptions' => $this->beneficiaryProgramOptions(),
            'employedProgramOptions' => $this->employedProgramOptions(),
            'serviceInterestOptions' => $this->serviceInterestOptions(),
        ]);
    }

    public function store(Request $request, string $token)
    {
        if (!\App\Models\Setting::getBool('participant_intake_enabled', true)) {
            return view('participant-intake.disabled');
        }

        $event = ParticipantIntakeEvent::query()
            ->where('public_token', $token)
            ->where('is_active', true)
            ->first();
        if (!$event) {
            abort(404, 'Participant intake link is invalid or inactive.');
        }

        $validator = Validator::make($request->all(), [
            'privacy_consent' => ['required', 'accepted'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'email' => ['required', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\\-\\s]{7,30}$/'],
            'gender' => ['required', 'string', 'in:Male,Female'],
            'age_range' => ['required', Rule::in([
                '18-24 years old',
                '25-34 years old',
                '35-44 years old',
                '45-54 years old',
                '55-64 years old',
                '65 years old and above',
            ])],
            'pwd_status' => ['required', Rule::in($this->yesNoOptions())],
            'is_4ps_beneficiary' => ['required', Rule::in($this->yesNoOptions())],
            'is_elcac_community' => ['required', Rule::in($this->yesNoOptions())],
            'dost_program_beneficiary' => ['required', 'array', 'min:1'],
            'dost_program_beneficiary.*' => [Rule::in($this->beneficiaryProgramOptions())],
            'directly_employed_programs' => ['required', 'array', 'min:1'],
            'directly_employed_programs.*' => [Rule::in($this->employedProgramOptions())],
            'has_attended_dost_training' => ['required', Rule::in($this->yesNoOptions())],
            'interested_dost_services' => ['required', 'array', 'min:1'],
            'interested_dost_services.*' => [Rule::in($this->serviceInterestOptions())],
            'interested_dost_services_other' => ['nullable', 'string', 'max:255'],
            'industry' => ['required', Rule::in([
                'Student',
                'Micro, Small, and Medium Enterprise (MSME)',
                'Local Government Unit (LGU)',
                'National Government Agency (NGA)',
                'Non-Government Organization (NGO)',
                'Government-Owned and Controlled Corporation (GOCC)',
                'Civil Society Organization (CSO)',
                'People\'s Organization (PO)',
                'Private Sector',
                'Private Individual',
                'Academe',
                'Others',
            ])],
            'organization_name' => ['required', 'string', 'max:255'],
            'position_designation' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'city_municipality' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'block_lot_purok' => ['nullable', 'string', 'max:255'],
        ], [
            'privacy_consent.required' => 'You must read and agree to the Data Privacy Consent before proceeding.',
            'privacy_consent.accepted' => 'You must read and agree to the Data Privacy Consent before proceeding.',
            'contact_number.regex' => 'Please provide a valid contact number.',
        ]);
        $validator->after(function ($validator) use ($request) {
            $beneficiary = (array) $request->input('dost_program_beneficiary', []);
            if (in_array('Not Applicable', $beneficiary, true) && count($beneficiary) > 1) {
                $validator->errors()->add(
                    'dost_program_beneficiary',
                    'For DOST Program Beneficiary, "Not Applicable" cannot be selected together with other options.'
                );
            }

            $employed = (array) $request->input('directly_employed_programs', []);
            if (in_array('Not Applicable', $employed, true) && count($employed) > 1) {
                $validator->errors()->add(
                    'directly_employed_programs',
                    'For Directly Employed Programs, "Not Applicable" cannot be selected together with other options.'
                );
            }

            $services = (array) $request->input('interested_dost_services', []);
            $servicesOther = trim((string) $request->input('interested_dost_services_other', ''));
            if (in_array('Others', $services, true) && $servicesOther === '') {
                $validator->errors()->add(
                    'interested_dost_services_other',
                    'Please specify the other DOST service you are interested in.'
                );
            }
        });
        $data = $validator->validate();

        unset($data['privacy_consent']);

        $data['last_name'] = preg_replace('/\\s+/', ' ', trim((string) $data['last_name']));
        $data['first_name'] = preg_replace('/\\s+/', ' ', trim((string) $data['first_name']));
        $middle = strtoupper(trim((string) ($data['middle_initial'] ?? '')));
        $middle = rtrim($middle, '.');
        $data['middle_initial'] = $middle === '' ? null : mb_substr($middle, 0, 1) . '.';

        $data['participant_name'] = trim(
            $data['first_name']
            . ($data['middle_initial'] ? ' ' . $data['middle_initial'] : '')
            . ' ' . $data['last_name']
        );
        $data['email'] = strtolower(trim($data['email']));
        $data['contact_number'] = preg_replace('/\\s+/', ' ', trim((string) $data['contact_number']));
        $data['industry'] = isset($data['industry']) ? trim((string) $data['industry']) : null;
        $data['organization_name'] = isset($data['organization_name']) ? trim((string) $data['organization_name']) : null;
        $data['region'] = isset($data['region']) ? trim((string) $data['region']) : null;
        $data['province'] = isset($data['province']) ? trim((string) $data['province']) : null;
        $data['city_municipality'] = isset($data['city_municipality']) ? trim((string) $data['city_municipality']) : null;
        $data['barangay'] = isset($data['barangay']) ? trim((string) $data['barangay']) : null;
        $data['block_lot_purok'] = isset($data['block_lot_purok']) ? trim((string) $data['block_lot_purok']) : null;
        $data['position_designation'] = isset($data['position_designation']) ? trim((string) $data['position_designation']) : null;
        $data['dost_program_beneficiary'] = array_values(array_unique($data['dost_program_beneficiary'] ?? []));
        $data['directly_employed_programs'] = array_values(array_unique($data['directly_employed_programs'] ?? []));
        $data['interested_dost_services'] = array_values(array_unique($data['interested_dost_services'] ?? []));
        $data['interested_dost_services_other'] = isset($data['interested_dost_services_other'])
            ? trim((string) $data['interested_dost_services_other'])
            : null;
        if (!in_array('Others', $data['interested_dost_services'], true)) {
            $data['interested_dost_services_other'] = null;
        }

        $dupExists = ParticipantIntake::query()
            ->where('participant_intake_event_id', $event->id)
            ->whereRaw('LOWER(email) = ?', [$data['email']])
            ->whereRaw('LOWER(participant_name) = ?', [strtolower($data['participant_name'])])
            ->exists();

        if ($dupExists) {
            return back()->withErrors([
                'email' => 'You have already submitted this form. Please wait for your certificate to be received via email or hard copy after the training.',
            ])->withInput();
        }

        $data['participant_intake_event_id'] = $event->id;
        $data['owner_user_id'] = $event->user_id;
        ParticipantIntake::create($data);

        return back()->with('success', 'Submission received. Thank you!');
    }

    private function yesNoOptions(): array
    {
        return ['Yes', 'No'];
    }

    private function beneficiaryProgramOptions(): array
    {
        return [
            'Grants-in-Aid (GIA)',
            'Community Empowerment through Science and Technology (CEST)',
            'Smart and Sustainable Communities Program (SSCP)',
            'Small Enterprise Technology Upgrading Program (SETUP)',
            'Not Applicable',
        ];
    }

    private function employedProgramOptions(): array
    {
        return [
            'Grants-in-Aid (GIA)',
            'Community Empowerment through Science and Technology (CEST)',
            'Small Enterprise Technology Upgrading Program (SETUP)',
            'Not Applicable',
        ];
    }

    private function serviceInterestOptions(): array
    {
        return [
            'Training',
            'Laboratory Testing',
            'Consultancy',
            'Technology Transfer',
            'Innovation Support',
            'Funding Assistance',
            'Others',
        ];
    }
}
