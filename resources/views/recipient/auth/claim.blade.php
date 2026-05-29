<x-guest-layout>

    {{-- ═══ Branding ═══ --}}
    <div style="text-align:center; margin-bottom: 22px;">
        <div style="
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px; height: 64px;
            border-radius: 18px;
            background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%);
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
            margin-bottom: 14px;
        ">
            <img src="{{ asset('images/dosttt.png') }}"
                 alt="DOST Logo"
                 style="width:44px; height:44px; object-fit:contain;">
        </div>
        <div style="font-weight:700; font-size:17px; color:#0F172A; letter-spacing:-0.01em;">
            DOST CARAGA
        </div>
        <div style="font-size:12.5px; color:#64748B; margin-top:3px; font-weight:400;">
            Certificate Repository
        </div>
    </div>

    {{-- ═══ Heading ═══ --}}
    <h2 style="
        text-align:center; margin:0 0 4px 0;
        font-size:20px; font-weight:700; color:#0F172A;
        letter-spacing:-0.02em;
    ">
        Set Your Password
    </h2>
    <p style="
        text-align:center; margin:0 0 20px 0;
        color:#64748B; font-size:13.5px; font-weight:400;
    ">
        Welcome, <strong style="color:#0F172A;">{{ $recipient->name }}</strong>! Create a password to access your certificate repository.
    </p>

    <form method="POST" action="{{ route('recipient.claim.submit', $token) }}">
        @csrf

        {{-- Password --}}
        <div>
            <x-input-label for="password" :value="'Password'" />
            <div style="position:relative;">
                <x-text-input id="password" class="block mt-1 w-full pr-10"
                              type="password" name="password" required autofocus
                              placeholder="Create a password" />
                <button type="button" onclick="togglePassword('password', this)"
                        style="position:absolute; right:4px; top:50%; transform:translateY(-50%);
                               background:none; border:none; padding:8px; cursor:pointer; color:#94A3B8;
                               display:flex; align-items:center; border-radius:8px;
                               transition:color 0.15s ease;"
                        onmouseover="this.style.color='#475569'"
                        onmouseout="this.style.color='#94A3B8'"
                        aria-label="Show password">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Confirm Password --}}
        <div class="mt-4">
            <x-input-label for="confirm_password" :value="'Confirm Password'" />
            <div style="position:relative;">
                <x-text-input id="confirm_password" class="block mt-1 w-full pr-10"
                              type="password" name="password_confirmation" required
                              placeholder="Re-enter your password" />
                <button type="button" onclick="togglePassword('confirm_password', this)"
                        style="position:absolute; right:4px; top:50%; transform:translateY(-50%);
                               background:none; border:none; padding:8px; cursor:pointer; color:#94A3B8;
                               display:flex; align-items:center; border-radius:8px;
                               transition:color 0.15s ease;"
                        onmouseover="this.style.color='#475569'"
                        onmouseout="this.style.color='#94A3B8'"
                        aria-label="Show password">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div style="margin-top:20px;">
            <button type="submit" style="
                width:100%;
                background:#0F172A;
                color:#fff;
                border:1.5px solid #0F172A;
                padding:12px 24px;
                border-radius:12px;
                font-weight:600;
                font-size:14.5px;
                font-family:inherit;
                cursor:pointer;
                transition: all 0.2s ease;
                box-shadow: 0 2px 8px rgba(15, 23, 42, 0.15);
                letter-spacing:0.01em;
            "
            onmouseover="this.style.background='#1E293B'; this.style.borderColor='#1E293B'; this.style.boxShadow='0 4px 16px rgba(15,23,42,0.22)'; this.style.transform='translateY(-1px)';"
            onmouseout="this.style.background='#0F172A'; this.style.borderColor='#0F172A'; this.style.boxShadow='0 2px 8px rgba(15,23,42,0.15)'; this.style.transform='translateY(0)';"
            >
                Set Password &amp; Sign In
            </button>
        </div>
    </form>

</x-guest-layout>
