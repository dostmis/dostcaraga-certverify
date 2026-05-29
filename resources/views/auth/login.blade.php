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
            DOST Caraga
        </div>
        <div style="font-size:12.5px; color:#64748B; margin-top:3px; font-weight:400;">
            Certificate Verification &amp; Repository System
        </div>
    </div>

    {{-- ═══ Heading ═══ --}}
    <h2 style="
        text-align:center; margin:0 0 4px 0;
        font-size:20px; font-weight:700; color:#0F172A;
        letter-spacing:-0.02em;
    ">
        Welcome back
    </h2>
    <p style="
        text-align:center; margin:0 0 20px 0;
        color:#64748B; font-size:13.5px; font-weight:400;
    ">
        Sign in to your account to continue
    </p>

    {{-- ═══ Tab Switcher ═══ --}}
    <div style="
        display:flex; gap:6px; margin-bottom:22px;
        background:#F1F5F9; border-radius:12px; padding:5px;
        position:relative;
    ">
        <button type="button" id="tab-staff"
                onclick="switchTab('staff')"
                style="
                    flex:1; padding:9px 12px; border:none; border-radius:9px;
                    font-size:13.5px; font-weight:600; cursor:pointer;
                    background:#fff; color:#0F172A;
                    box-shadow:0 1px 3px rgba(15,23,42,0.10), 0 1px 2px rgba(15,23,42,0.06);
                    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                    font-family: inherit;
                ">
            <span style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Staff
            </span>
        </button>
        <button type="button" id="tab-recipient"
                onclick="switchTab('recipient')"
                style="
                    flex:1; padding:9px 12px; border:none; border-radius:9px;
                    font-size:13.5px; font-weight:600; cursor:pointer;
                    background:transparent; color:#64748B;
                    box-shadow:none;
                    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                    font-family: inherit;
                ">
            <span style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Recipient
            </span>
        </button>
    </div>

    {{-- ═══ Staff Login Form ═══ --}}
    <div id="form-staff">
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}">
            @csrf

            {{-- Email / Username --}}
            <div>
                <x-input-label for="login" :value="'Email or Username'" />
                <x-text-input id="login" class="block mt-1 w-full"
                              type="text" name="login"
                              :value="old('login')" required autofocus
                              placeholder="your.email@dost.gov.ph" />
                <x-input-error :messages="$errors->get('login')" class="mt-2" />
            </div>

            {{-- Password --}}
            <div class="mt-4">
                <x-input-label for="password" :value="'Password'" />
                <div style="position:relative;">
                    <x-text-input id="password" class="block mt-1 w-full pr-10"
                                  type="password" name="password" required
                                  placeholder="Enter your password" />
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

            {{-- Remember + Forgot --}}
            <div class="mt-4" style="display:flex;align-items:center;justify-content:space-between;">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#475569;cursor:pointer;">
                    <input type="checkbox" name="remember">
                    Remember me
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       style="font-size:13px;color:#0369A1;font-weight:500;text-decoration:none;
                              transition:color 0.2s ease;"
                       onmouseover="this.style.color='#0284C7'"
                       onmouseout="this.style.color='#0369A1'">
                        Forgot password?
                    </a>
                @endif
            </div>

            {{-- Submit --}}
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
                    Sign in
                </button>
            </div>

            {{-- Sign up link --}}
            <div style="margin-top:16px; text-align:center;">
                <span style="font-size:13px;color:#64748B;">Don't have an account?</span>
                <a href="{{ route('register') }}"
                   style="font-size:13px;color:#0369A1;font-weight:600;text-decoration:none;margin-left:5px;
                          transition:color 0.2s ease;"
                   onmouseover="this.style.color='#0284C7'"
                   onmouseout="this.style.color='#0369A1'">
                    Create one
                </a>
            </div>
        </form>
    </div>

    {{-- ═══ Recipient Login Form ═══ --}}
    <div id="form-recipient" style="display:none;">
        <form method="POST" action="{{ route('recipient.login') }}">
            @csrf
            <input type="hidden" name="login_type" value="recipient">

            {{-- Email --}}
            <div>
                <x-input-label for="recipient_email" :value="'Email'" />
                <x-text-input id="recipient_email" class="block mt-1 w-full"
                              type="email" name="email"
                              :value="old('email')" required autocomplete="email"
                              placeholder="your.email@example.com" />
                <x-input-error :messages="$errors->recipient_login->get('email')" class="mt-2" />
            </div>

            {{-- Password --}}
            <div class="mt-4">
                <x-input-label for="recipient_password" :value="'Password'" />
                <div style="position:relative;">
                    <x-text-input id="recipient_password" class="block mt-1 w-full pr-10"
                                  type="password" name="password" required autocomplete="current-password"
                                  placeholder="Enter your password" />
                    <button type="button" onclick="togglePassword('recipient_password', this)"
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
                <x-input-error :messages="$errors->recipient_login->get('password')" class="mt-2" />
            </div>

            {{-- Remember --}}
            <div class="mt-4" style="display:flex;align-items:center;justify-content:space-between;">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#475569;cursor:pointer;">
                    <input type="checkbox" name="remember">
                    Remember me
                </label>
            </div>

            {{-- Submit --}}
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
                    Sign in as Recipient
                </button>
            </div>

            {{-- Info for new recipients --}}
            <div style="margin-top:16px; text-align:center;">
                <div style="
                    background:#F8FAFC;
                    border:1px solid #E2E8F0;
                    border-radius:10px;
                    padding:14px 16px;
                    font-size:12.5px;
                    color:#475569;
                    line-height:1.6;
                ">
                    <span style="font-weight:600;color:#0F172A;">No account yet?</span><br>
                    Accounts are created when a certificate is issued in your name.
                    Check your email for a <strong style="color:#0F172A;">claim link</strong>.
                    <span> OR </span>
                    <a href="{{ route('recipient.claim.lookup') }}" style="
                        color:#0369A1; font-weight:600;
                        text-decoration:none; transition:color 0.15s;
                    " onmouseover="this.style.color='#0284C7'" onmouseout="this.style.color='#0369A1'">
                        claim your account here
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.querySelector('svg').innerHTML = show
                ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="m14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
                : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }

        function switchTab(tab) {
            const staffBtn = document.getElementById('tab-staff');
            const recipientBtn = document.getElementById('tab-recipient');
            const staffForm = document.getElementById('form-staff');
            const recipientForm = document.getElementById('form-recipient');

            const activeStyle = {
                background: '#fff',
                color: '#0F172A',
                boxShadow: '0 1px 3px rgba(15,23,42,0.10), 0 1px 2px rgba(15,23,42,0.06)'
            };
            const inactiveStyle = {
                background: 'transparent',
                color: '#64748B',
                boxShadow: 'none'
            };

            if (tab === 'staff') {
                Object.assign(staffBtn.style, activeStyle);
                Object.assign(recipientBtn.style, inactiveStyle);
                staffForm.style.display = 'block';
                recipientForm.style.display = 'none';
            } else {
                Object.assign(recipientBtn.style, activeStyle);
                Object.assign(staffBtn.style, inactiveStyle);
                recipientForm.style.display = 'block';
                staffForm.style.display = 'none';
            }
        }

        // Auto-detect tab from URL param or recipient login errors
        (function() {
            const params = new URLSearchParams(window.location.search);
            @if ($errors->recipient_login->any())
                switchTab('recipient');
            @elseif (request()->has('tab') && request()->get('tab') === 'recipient')
                switchTab('recipient');
            @endif
        })();
    </script>

</x-guest-layout>
