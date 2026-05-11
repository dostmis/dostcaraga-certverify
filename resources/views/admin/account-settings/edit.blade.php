<x-admin-layout title="Account Settings">
  <div class="mx-auto w-full max-w-3xl">
    <div class="rounded-2xl border-2 border-slate-300 bg-white shadow-sm">
      <div class="flex flex-col gap-4 border-b border-slate-200 p-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-extrabold tracking-tight">Account Settings</h1>
          <p class="mt-1 text-sm text-slate-500">Update your Regional Director account password.</p>
        </div>
        @include('admin.partials.action-menu', [
          'menuId' => 'account-settings-menu',
        ])
      </div>

      <div class="p-6">
        @if (session('status') === 'password-updated')
          <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
            <div class="font-bold">Password updated successfully.</div>
          </div>
        @endif

        <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
          <div class="text-sm font-bold text-slate-900">{{ $user->name }}</div>
          <div class="mt-1 text-sm text-slate-600">{{ $user->email }}</div>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
          @csrf
          @method('PUT')

          <div>
            <label for="current_password" class="block text-sm font-bold text-slate-700">Current Password</label>
            <input
              id="current_password"
              name="current_password"
              type="password"
              autocomplete="current-password"
              required
              class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
            >
            @foreach ($errors->updatePassword->get('current_password') as $message)
              <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>
            @endforeach
          </div>

          <div>
            <label for="password" class="block text-sm font-bold text-slate-700">New Password</label>
            <input
              id="password"
              name="password"
              type="password"
              autocomplete="new-password"
              required
              class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
            >
            @foreach ($errors->updatePassword->get('password') as $message)
              <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>
            @endforeach
          </div>

          <div>
            <label for="password_confirmation" class="block text-sm font-bold text-slate-700">Confirm New Password</label>
            <input
              id="password_confirmation"
              name="password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
            >
            @foreach ($errors->updatePassword->get('password_confirmation') as $message)
              <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>
            @endforeach
          </div>

          <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-extrabold text-white hover:bg-slate-800">
              Update Password
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</x-admin-layout>
