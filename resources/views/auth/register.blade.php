<x-guest-layout>
    <div style="text-align:center; margin-bottom:18px;">
        <img src="{{ asset('images/dosttt.png') }}"
             alt="DOST Logo"
             style="width:56px;height:56px;object-fit:contain;display:block;margin:0 auto 10px;">
        <div style="font-weight:900;font-size:16px;">DOST CARAGA</div>
        <div style="font-size:12px;color:#666;">Certificate Verification System</div>
    </div>

    <h2 style="text-align:center; margin:0 0 10px 0;font-size:18px;font-weight:900;">Create account</h2>
    <p style="text-align:center; margin:0 0 16px 0;color:#666;font-size:13px;">
        Authorized personnel only.
    </p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full"
                          type="text" name="name"
                          :value="old('name')" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username" class="block mt-1 w-full"
                          type="text" name="username"
                          :value="old('username')" required />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full"
                          type="email" name="email"
                          :value="old('email')" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="role" :value="__('Role')" />
            <select id="role" name="role" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                <option value="">Select role</option>
                <option value="organizer" @selected(old('role') === 'organizer')>Organizer</option>
                <option value="unit_supervisor" @selected(old('role') === 'unit_supervisor')>Supervising Unit</option>
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full"
                          type="password" name="password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                          type="password" name="password_confirmation" required />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
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
                Register
            </button>
        </div>

        <div class="mt-4" style="text-align:center;">
            <a href="{{ route('login') }}" style="font-size:13px;color:#444;text-decoration:underline;">
                Already registered?
            </a>
        </div>
    </form>
</x-guest-layout>
