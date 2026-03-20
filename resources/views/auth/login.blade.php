<x-guest-layout>
    <div style="text-align:center; margin-bottom:18px;">
        <img src="{{ asset('images/dosttt.png') }}"
             alt="DOST Logo"
             style="width:56px;height:56px;object-fit:contain;display:block;margin:0 auto 10px;">
        <div style="font-weight:900;font-size:16px;">DOST CARAGA</div>
        <div style="font-size:12px;color:#666;">Certificate Verification System</div>
    </div>

    <h2 style="text-align:center; margin:0 0 10px 0;font-size:18px;font-weight:900;">Sign in</h2>
    <p style="text-align:center; margin:0 0 16px 0;color:#666;font-size:13px;">
        Authorized personnel only.
    </p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="login" :value="__('Email or Username')" />
            <x-text-input id="login" class="block mt-1 w-full"
                          type="text" name="login"
                          :value="old('login')" required autofocus />
            <x-input-error :messages="$errors->get('login')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full"
                          type="password" name="password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4" style="display:flex;align-items:center;justify-content:space-between;">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#444;">
                <input type="checkbox" name="remember">
                Remember me
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" style="font-size:13px;color:#0b57d0;text-decoration:underline;">
                    Forgot your password?
                </a>
            @endif
        </div>

        <div class="mt-5" style="margin-top:18px;">
            <button type="submit" style="
                width:100%;
                background:#111;
                color:#fff;
                border:none;
                padding:12px;
                border-radius:12px;
                font-weight:900;
                cursor:pointer;
            ">
                Login
            </button>
        </div>

        <div class="mt-4" style="text-align:center;">
            <span style="font-size:13px;color:#444;">Don't have an account?</span>
            <a href="{{ route('register') }}" style="font-size:13px;color:#0b57d0;text-decoration:underline;margin-left:4px;">
                Sign up
            </a>
        </div>
    </form>
</x-guest-layout>
