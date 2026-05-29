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

    @php
        $step2Active = session('verify_recipient_id');
    @endphp

    {{-- ═══ Heading ═══ --}}
    <h2 style="
        text-align:center; margin:0 0 4px 0;
        font-size:20px; font-weight:700; color:#0F172A;
        letter-spacing:-0.02em;
    ">
        {{ $step2Active ? 'Verify & Set Password' : 'Claim Your Account' }}
    </h2>
    <p style="
        text-align:center; margin:0 0 20px 0;
        color:#64748B; font-size:13.5px; font-weight:400;
    ">
        {{ $step2Active ? 'Verify your identity to complete your account setup.' : 'Already have a record with us? Verify your identity to set up your account.' }}
    </p>

    {{-- Step 1: Lookup --}}
    <div id="step-lookup" style="{{ $step2Active ? 'display:none;' : '' }}">
        <form id="lookupForm" onsubmit="return false;">
            <div>
                <x-input-label for="lookup_name" :value="'Full Name'" />
                <x-text-input id="lookup_name" class="block mt-1 w-full"
                              type="text" name="name" required
                              placeholder="e.g. Juan Dela S. Cruz" />
                <div id="lookup_name_error" class="input-error" style="display:none;"></div>
            </div>

            <div class="mt-4">
                <x-input-label for="lookup_email" :value="'Email'" />
                <x-text-input id="lookup_email" class="block mt-1 w-full"
                              type="email" name="email" required
                              placeholder="your.email@example.com" />
                <div id="lookup_email_error" class="input-error" style="display:none;"></div>
            </div>

            <div id="lookupError" style="
                display:none; margin-top:14px; padding:12px 16px;
                border-radius:12px; background:#fef2f2; border:1px solid #fecaca;
                color:#991b1b; font-size:13px; font-weight:600;
            "></div>

            <div style="margin-top:20px;">
                <button type="submit" id="lookupBtn" onclick="doLookup()" style="
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
                "
                onmouseover="this.style.background='#1E293B'; this.style.boxShadow='0 4px 16px rgba(15,23,42,0.22)'; this.style.transform='translateY(-1px)';"
                onmouseout="this.style.background='#0F172A'; this.style.boxShadow='0 2px 8px rgba(15,23,42,0.15)'; this.style.transform='translateY(0)';"
                >
                    Find My Account
                </button>
            </div>
        </form>

        <div style="margin-top:14px; text-align:center;">
            <a href="{{ route('login', ['tab' => 'recipient']) }}"
               style="font-size:13px;color:#64748B;text-decoration:none;
                      transition:color 0.2s ease;"
               onmouseover="this.style.color='#475569'"
               onmouseout="this.style.color='#64748B'">
                Back to sign in
            </a>
        </div>

        <div style="margin-top:10px; text-align:center; font-size:12px; color:#94a3b8;">
            Having trouble? Contact <strong style="color:#64748b;">mis@caraga.dost.gov.ph</strong> or <strong style="color:#64748b;">DOST Caraga ICT Team</strong>
        </div>
    </div>

    {{-- Step 2: Verify + Set Password --}}
    <div id="step-verify" style="{{ $step2Active ? '' : 'display:none;' }}">
        <div id="verifyInfo" style="
            text-align:center; margin-bottom:18px; padding:14px 16px;
            background:#f0fdf4; border:1px solid #bbf7d0;
            border-radius:12px; font-size:13px; color:#166534;
            font-weight:600;
        ">
            @if ($step2Active)
                Account found: <strong>{{ session('verify_recipient_name') }}</strong>
                @if (session('verify_recipient_masked'))
                    <br>Verify with your mobile number ending in <strong>***{{ session('verify_recipient_masked') }}</strong>
                @else
                    <br>Enter your registered mobile number to verify.
                @endif
            @endif
        </div>

        <form method="POST" action="{{ route('recipient.claim.verify') }}">
            @csrf
            <input type="hidden" name="recipient_id" id="verify_recipient_id" value="{{ old('recipient_id', session('verify_recipient_id')) }}">

            <div>
                <x-input-label for="verify_contact" :value="'Mobile Number (for verification)'" />
                <x-text-input id="verify_contact" class="block mt-1 w-full"
                              type="tel" name="contact_number" required
                              placeholder="e.g., 09171234567" />
                @if ($errors->has('contact_number'))
                    <div class="input-error" style="margin-top:4px;">{{ $errors->first('contact_number') }}</div>
                @endif
            </div>

            <div class="mt-4">
                <x-input-label for="verify_password" :value="'New Password'" />
                <div style="position:relative;">
                    <x-text-input id="verify_password" class="block mt-1 w-full pr-10"
                                  type="password" name="password" required
                                  placeholder="Create a password (min. 8 characters)" />
                    @if ($errors->has('password'))
                        <div class="input-error" style="margin-top:4px;">{{ $errors->first('password') }}</div>
                    @endif
                    <button type="button" onclick="togglePassword('verify_password', this)"
                            style="position:absolute; right:4px; top:50%; transform:translateY(-50%);
                                   background:none; border:none; padding:8px; cursor:pointer; color:#94A3B8;
                                   display:flex; align-items:center; border-radius:8px;"
                            onmouseover="this.style.color='#475569'"
                            onmouseout="this.style.color='#94A3B8'"
                            aria-label="Show password">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="verify_password_confirmation" :value="'Confirm Password'" />
                <div style="position:relative;">
                    <x-text-input id="verify_password_confirmation" class="block mt-1 w-full pr-10"
                                  type="password" name="password_confirmation" required
                                  placeholder="Re-enter your password" />
                    <button type="button" onclick="togglePassword('verify_password_confirmation', this)"
                            style="position:absolute; right:4px; top:50%; transform:translateY(-50%);
                                   background:none; border:none; padding:8px; cursor:pointer; color:#94A3B8;
                                   display:flex; align-items:center; border-radius:8px;"
                            onmouseover="this.style.color='#475569'"
                            onmouseout="this.style.color='#94A3B8'"
                            aria-label="Show password">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            @php
                $fieldErrors = ['contact_number', 'password', 'recipient_id'];
                $generalErrors = array_filter($errors->all(), function($e) use ($fieldErrors, $errors) {
                    foreach ($fieldErrors as $field) {
                        if ($errors->has($field) && in_array($e, $errors->get($field))) return false;
                    }
                    return true;
                });
            @endphp
            @if (!empty($generalErrors))
                <div style="margin-top:14px; padding:12px 16px; border-radius:12px;
                    background:#fef2f2; border:1px solid #fecaca; color:#991b1b;
                    font-size:13px; font-weight:600;">
                    <ul style="margin:4px 0 0; padding-left:18px;">
                        @foreach ($generalErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

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
                "
                onmouseover="this.style.background='#1E293B'; this.style.boxShadow='0 4px 16px rgba(15,23,42,0.22)'; this.style.transform='translateY(-1px)';"
                onmouseout="this.style.background='#0F172A'; this.style.boxShadow='0 2px 8px rgba(15,23,42,0.15)'; this.style.transform='translateY(0)';"
                >
                    Claim Account &amp; Sign In
                </button>
            </div>
        </form>
    </div>

    <script>
        async function doLookup() {
            const name = document.getElementById('lookup_name').value.trim();
            const email = document.getElementById('lookup_email').value.trim();
            const errorEl = document.getElementById('lookupError');
            const btn = document.getElementById('lookupBtn');

            document.getElementById('lookup_name_error').style.display = 'none';
            document.getElementById('lookup_email_error').style.display = 'none';
            errorEl.style.display = 'none';

            if (!name || !email) {
                errorEl.textContent = 'Please enter both your full name and email.';
                errorEl.style.display = '';
                return;
            }

            btn.textContent = 'Looking up...';
            btn.disabled = true;

            try {
                const res = await fetch('{{ route('recipient.claim.lookup.submit') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name, email }),
                });

                const data = await res.json();

                if (!res.ok) {
                    const errors = data.errors || {};
                    if (errors.name) {
                        document.getElementById('lookup_name_error').textContent = errors.name[0];
                        document.getElementById('lookup_name_error').style.display = '';
                    }
                    if (errors.email) {
                        document.getElementById('lookup_email_error').textContent = errors.email[0];
                        document.getElementById('lookup_email_error').style.display = '';
                    }
                    if (!errors.name && !errors.email) {
                        errorEl.textContent = 'Validation error. Please try again.';
                        errorEl.style.display = '';
                    }
                    return;
                }

                if (!data.found) {
                    errorEl.textContent = data.message;
                    errorEl.style.display = '';
                    return;
                }

                // Show step 2
                document.getElementById('step-lookup').style.display = 'none';
                document.getElementById('step-verify').style.display = '';
                document.getElementById('verify_recipient_id').value = data.recipient_id;

                let infoHtml = 'Account found: <strong>' + data.name + '</strong>';
                if (data.masked_contact) {
                    infoHtml += '<br>Verify with your mobile number ending in <strong>***' + data.masked_contact + '</strong>';
                } else {
                    infoHtml += '<br>Enter your registered mobile number to verify.';
                }
                document.getElementById('verifyInfo').innerHTML = infoHtml;

                // Update page title
                document.querySelector('h2').textContent = 'Verify & Set Password';

            } catch (e) {
                errorEl.textContent = 'Something went wrong. Please try again.';
                errorEl.style.display = '';
            } finally {
                btn.textContent = 'Find My Account';
                btn.disabled = false;
            }
        }

        // Allow Enter key to submit
        document.getElementById('lookupForm').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doLookup();
            }
        });

        // Allow Enter on lookup inputs to trigger search
        document.getElementById('lookup_name').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); doLookup(); }
        });
        document.getElementById('lookup_email').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); doLookup(); }
        });
    </script>

</x-guest-layout>
