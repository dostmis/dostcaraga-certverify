<x-admin-layout title="User Approvals">
  <div class="mx-auto w-full">
    <div class="rounded-2xl border-2 border-slate-300 bg-white shadow-sm">
      <div class="flex flex-col gap-4 border-b border-slate-200 p-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-extrabold tracking-tight">User Approvals</h1>
          <p class="mt-1 text-sm text-slate-500">Approve or reject new accounts</p>
        </div>
        <a href="{{ route('admin.certs.index') }}"
           class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
          ← Back to certificates
        </a>
      </div>

      @if (session('success'))
        <div class="px-6 pt-6">
          <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
            <div class="font-bold">{{ session('success') }}</div>
          </div>
        </div>
      @endif

      <div class="px-6 pt-6">
        <div class="flex flex-wrap items-center gap-2 text-sm font-bold">
          <a href="{{ route('admin.users.index', ['status' => 'pending', 'role' => ($role ?? 'all')]) }}"
             class="inline-flex items-center rounded-xl border px-3 py-1.5 {{ $status === 'pending' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
            Pending
          </a>
          <a href="{{ route('admin.users.index', ['status' => 'approved', 'role' => ($role ?? 'all')]) }}"
             class="inline-flex items-center rounded-xl border px-3 py-1.5 {{ $status === 'approved' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
            Approved
          </a>
          <a href="{{ route('admin.users.index', ['status' => 'rejected', 'role' => ($role ?? 'all')]) }}"
             class="inline-flex items-center rounded-xl border px-3 py-1.5 {{ $status === 'rejected' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
            Rejected
          </a>
          <a href="{{ route('admin.users.index', ['status' => 'all', 'role' => ($role ?? 'all')]) }}"
             class="inline-flex items-center rounded-xl border px-3 py-1.5 {{ $status === 'all' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
            All
          </a>
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="mt-3 flex flex-wrap items-center gap-2">
          <input type="hidden" name="status" value="{{ $status }}">
          <label for="roleFilter" class="text-xs font-bold uppercase tracking-wide text-slate-500">Role</label>
          <select id="roleFilter" name="role" class="rounded-lg border-slate-300 text-sm font-semibold" onchange="this.form.submit()">
            <option value="all" @selected(($role ?? 'all') === 'all')>All Roles</option>
            @foreach (($roles ?? []) as $roleOption)
              <option value="{{ $roleOption }}" @selected(($role ?? 'all') === $roleOption)>{{ str_replace('_', ' ', strtoupper($roleOption)) }}</option>
            @endforeach
          </select>
        </form>
      </div>

      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm border-2 border-slate-300">
            <thead class="text-left text-xs font-bold uppercase tracking-wide text-slate-500">
              <tr class="border-b-2 border-slate-300">
                <th class="py-3 pl-4 pr-4">Name</th>
                <th class="py-3 pr-4">Username</th>
                <th class="py-3 pr-4">Email</th>
                <th class="py-3 pr-4">Role</th>
                <th class="py-3 pr-4">Status</th>
                <th class="py-3 pr-4">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y-2 divide-slate-300">
              @forelse ($users as $user)
                <tr class="odd:bg-blue-100 even:bg-white hover:bg-blue-200/60">
                  <td class="py-3 pl-4 pr-4 font-extrabold text-slate-900 whitespace-nowrap">{{ $user->name }}</td>
                  <td class="py-3 pr-4 text-slate-700 whitespace-nowrap">{{ $user->username }}</td>
                  <td class="py-3 pr-4 text-slate-700 whitespace-nowrap">{{ $user->email }}</td>
                  <td class="py-3 pr-4 text-slate-700 whitespace-nowrap">
                    {{ str_replace('_', ' ', strtoupper($user->role ?? 'organizer')) }}
                  </td>
                  <td class="py-3 pr-4">
                    @php
                      $badge = match($user->approval_status){
                        'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                        'rejected' => 'bg-red-50 text-red-700 ring-red-200',
                        default => 'bg-amber-50 text-amber-700 ring-amber-200',
                      };
                    @endphp
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-extrabold ring-1 {{ $badge }}">
                      {{ strtoupper($user->approval_status) }}
                    </span>
                  </td>
                  <td class="py-3 pr-4">
                    <div class="flex items-center gap-2 whitespace-nowrap">
                      @if ($user->approval_status !== 'approved')
                        <form method="POST" action="{{ route('admin.users.approve', ['id' => $user->id]) }}">
                          @csrf
                          <select name="role" class="mr-2 rounded-lg border-slate-300 text-xs font-semibold">
                            @foreach (($roles ?? []) as $roleOption)
                              <option value="{{ $roleOption }}" @selected(($user->role ?? 'organizer') === $roleOption)>{{ str_replace('_', ' ', strtoupper($roleOption)) }}</option>
                            @endforeach
                          </select>
                          <button class="inline-flex items-center rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-extrabold text-white hover:bg-emerald-700">
                            Approve
                          </button>
                        </form>
                      @endif
                      @if ($user->approval_status !== 'rejected')
                        <form method="POST" action="{{ route('admin.users.reject', ['id' => $user->id]) }}">
                          @csrf
                          <button class="inline-flex items-center rounded-xl bg-red-600 px-3 py-1.5 text-xs font-extrabold text-white hover:bg-red-700">
                            Reject
                          </button>
                        </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="py-10 text-center text-slate-500">
                    No users found.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="mt-6 flex items-center justify-between">
          <div class="text-sm text-slate-500">
            Showing <span class="font-bold text-slate-900">{{ $users->firstItem() ?? 0 }}</span>
            to <span class="font-bold text-slate-900">{{ $users->lastItem() ?? 0 }}</span>
            of <span class="font-bold text-slate-900">{{ $users->total() }}</span> users
          </div>
          <div class="text-sm">
            {{ $users->links('vendor.pagination.admin') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</x-admin-layout>
