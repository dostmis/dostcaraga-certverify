<?php

namespace App\Http\Controllers\Recipient\Auth;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Recipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecipientAuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('recipient.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::guard('recipient')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('recipient.certificates'));
        }

        return redirect()->route('login', ['tab' => 'recipient'])
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'These credentials do not match our records.'], 'recipient_login');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('recipient')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('recipient.login');
    }

    public function showRegisterForm(): View
    {
        return view('recipient.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:recipients,email'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'string', 'in:Male,Female'],
            'birthdate' => ['nullable', 'date', 'before:today'],
        ]);

        // Prevent duplicate accounts: check if someone with the same name already exists
        $existingByName = Recipient::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))])->first();
        if ($existingByName && $existingByName->email !== $data['email']) {
            return back()->withInput()->withErrors([
                'email' => 'An account with this name already exists under a different email. If this is you, check your email for a claim link, or contact the organizer who issued your certificate.',
            ], 'recipient_login');
        }

        Recipient::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'contact_number' => $data['contact_number'] ?? null,
            'gender' => $data['gender'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'password' => null,
        ]);

        return redirect()->route('recipient.login')
            ->with('status', 'Registration successful. Check your email to claim your account when you receive a certificate.');
    }

    public function showClaimLookup(): View
    {
        return view('recipient.auth.claim-lookup');
    }

    public function claimLookup(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $name = mb_strtolower(trim($validated['name']));
        $email = mb_strtolower(trim($validated['email']));

        // Fuzzy name match: split into words and require all to appear
        $nameWords = array_filter(explode(' ', $name), fn($w) => mb_strlen($w) > 0);
        $recipient = Recipient::whereRaw('LOWER(email) = ?', [$email])
            ->where(function ($query) use ($nameWords, $name) {
                foreach ($nameWords as $word) {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%' . $word . '%']);
                }
            })
            ->first();

        if (!$recipient) {
            return response()->json(['found' => false, 'message' => 'No matching record found. Please check your name and email.']);
        }

        if ($recipient->isClaimed()) {
            return response()->json(['found' => false, 'message' => 'This account has already been claimed. Please log in instead. If you think this is a mistake, contact mis@caraga.dost.gov.ph.']);
        }

        return response()->json([
            'found' => true,
            'recipient_id' => $recipient->id,
            'name' => $recipient->name,
            'masked_contact' => $recipient->contact_number
                ? substr($recipient->contact_number, -4)
                : null,
        ]);
    }

    public function claimVerify(Request $request): RedirectResponse
    {
        $recipientId = $request->input('recipient_id');
        $recipient = $recipientId ? Recipient::find($recipientId) : null;

        $lookupFlash = [
            'verify_recipient_id' => $recipient?->id ?? $recipientId,
            'verify_recipient_name' => $recipient?->name ?? '',
            'verify_recipient_masked' => ($recipient?->contact_number)
                ? substr($recipient->contact_number, -4)
                : null,
        ];

        $validator = Validator::make($request->all(), [
            'recipient_id' => ['required', 'integer', 'exists:recipients,id'],
            'contact_number' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'The passwords do not match.',
            'password.min' => 'The password must be at least 8 characters.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with($lookupFlash);
        }

        $validated = $validator->validated();

        if (! $recipient || $recipient->isClaimed()) {
            return redirect()->route('recipient.login')
                ->with('status', 'This account has already been claimed. Please log in. If you think this is a mistake, contact mis@caraga.dost.gov.ph.');
        }

        // Verify mobile number
        $entered = preg_replace('/\D/', '', $validated['contact_number']);
        $expected = preg_replace('/\D/', '', (string) $recipient->contact_number);

        if ($entered !== $expected) {
            return back()->withErrors([
                'contact_number' => 'The mobile number does not match our records. Please try again.',
            ])->withInput()->with([
                'verify_recipient_id' => $recipient->id,
                'verify_recipient_name' => $recipient->name,
                'verify_recipient_masked' => $recipient->contact_number
                    ? substr($recipient->contact_number, -4)
                    : null,
            ]);
        }

        $recipient->update([
            'password' => $validated['password'],
            'claim_token' => null,
        ]);

        // Auto-link any certificates matching this recipient's email
        if ($recipient->email) {
            Certificate::whereNull('recipient_id')
                ->where('email', $recipient->email)
                ->update(['recipient_id' => $recipient->id]);
        }

        Auth::guard('recipient')->login($recipient);

        return redirect()->route('recipient.certificates')
            ->with('success', 'Account claimed successfully. Welcome!');
    }

    public function showClaimForm(string $token): View|RedirectResponse
    {
        $recipient = Recipient::where('claim_token', $token)->firstOrFail();

        if ($recipient->isClaimed()) {
            return redirect()->route('recipient.login')
                ->with('status', 'Your account has already been claimed. Please log in.');
        }

        return view('recipient.auth.claim', [
            'token' => $token,
            'recipient' => $recipient,
        ]);
    }

    public function claim(Request $request, string $token): RedirectResponse
    {
        $recipient = Recipient::where('claim_token', $token)->firstOrFail();

        if ($recipient->isClaimed()) {
            return redirect()->route('recipient.login')
                ->with('status', 'Your account has already been claimed. Please log in.');
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $recipient->update([
            'password' => $data['password'],
            'claim_token' => null,
        ]);

        // Auto-link any certificates matching this recipient's email
        if ($recipient->email) {
            $linked = Certificate::whereNull('recipient_id')
                ->where('email', $recipient->email)
                ->update(['recipient_id' => $recipient->id]);
        }

        Auth::guard('recipient')->login($recipient);

        return redirect()->route('recipient.certificates')
            ->with('success', 'Account created successfully. Welcome!');
    }
}
