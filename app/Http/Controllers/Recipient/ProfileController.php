<?php

namespace App\Http\Controllers\Recipient;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('recipient.profile.edit', [
            'recipient' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $recipient = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:recipients,email,' . $recipient->id],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'block_lot_purok' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city_municipality' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'position_designation' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:10'],
            'birthdate' => ['nullable', 'date'],
            'age_range' => ['nullable', 'string', 'max:50'],
            'pwd_status' => ['nullable', 'string', 'max:10'],
            'is_4ps_beneficiary' => ['nullable', 'string', 'max:10'],
            'is_elcac_community' => ['nullable', 'string', 'max:10'],
            'dost_program_beneficiary' => ['nullable', 'array'],
            'directly_employed_programs' => ['nullable', 'array'],
            'has_attended_dost_training' => ['nullable', 'string', 'max:10'],
            'interested_dost_services' => ['nullable', 'array'],
            'interested_dost_services_other' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['name'] = trim(
            $validated['first_name']
            . ($validated['middle_initial'] ? ' ' . $validated['middle_initial'] . '.' : '')
            . ' ' . $validated['last_name']
        );

        $recipient->fill($validated);
        $recipient->save();

        return redirect()->route('recipient.profile.edit')
            ->with('success', 'Profile updated successfully.');
    }

    public function editPassword(): View
    {
        return view('recipient.profile.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $recipient = $request->user();

        if (!Hash::check($validated['current_password'], $recipient->password)) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $recipient->password = $validated['password'];
        $recipient->save();

        return redirect()->route('recipient.profile.password')
            ->with('success', 'Password changed successfully.');
    }
}
