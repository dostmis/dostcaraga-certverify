<x-guest-layout>
    <div style="text-align:center; margin-bottom:18px;">
        <img src="{{ asset('images/dosttt.png') }}"
             alt="DOST Logo"
             style="width:56px;height:56px;object-fit:contain;display:block;margin:0 auto 10px;">
        <div style="font-weight:900;font-size:16px;">DOST CARAGA</div>
        <div style="font-size:12px;color:#666;">Certificate Repository</div>
    </div>

    <h2 style="text-align:center; margin:0 0 10px 0;font-size:18px;font-weight:900;">Create Your Account</h2>
    <p style="text-align:center; margin:0 0 16px 0;color:#666;font-size:13px;">
        Register once to keep all your certificates in one place.
    </p>

    <form method="POST" action="{{ route('recipient.register') }}">
        @csrf

        <div>
            <x-input-label for="name" :value="'Full Name'" />
            <x-text-input id="name" class="block mt-1 w-full"
                          type="text" name="name"
                          :value="old('name')" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" :value="'Email'" />
            <x-text-input id="email" class="block mt-1 w-full"
                          type="email" name="email"
                          :value="old('email')" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="contact_number" :value="'Contact Number'" />
            <x-text-input id="contact_number" class="block mt-1 w-full"
                          type="text" name="contact_number"
                          :value="old('contact_number')" />
            <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="gender" :value="'Gender'" />
            <select id="gender" name="gender" style="
                display:block; width:100%; margin-top:4px;
                border:1px solid #d1d5db; border-radius:8px;
                padding:10px 12px; font-size:14px; color:#111827;
                background:#fff;
            ">
                <option value="">-- Select --</option>
                <option value="Male" @selected(old('gender') === 'Male')>Male</option>
                <option value="Female" @selected(old('gender') === 'Female')>Female</option>
            </select>
            <x-input-error :messages="$errors->get('gender')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="birthdate" :value="'Birthdate'" />
            <x-text-input id="birthdate" class="block mt-1 w-full"
                          type="date" name="birthdate"
                          :value="old('birthdate')" />
            <x-input-error :messages="$errors->get('birthdate')" class="mt-2" />
        </div>

        <p style="margin-top:12px; font-size:12px; color:#888;">
            No password is needed now. You'll set your password when you claim your account after receiving a certificate.
        </p>

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
            <span style="font-size:13px;color:#444;">Already have an account?</span>
            <a href="{{ route('recipient.login') }}" style="font-size:13px;color:#0b57d0;text-decoration:underline;margin-left:4px;">
                Log in
            </a>
        </div>
    </form>
</x-guest-layout>
