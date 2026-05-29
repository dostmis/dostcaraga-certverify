<x-admin-layout title="Claimed Recipients">
  <div class="mx-auto w-full">
    <div class="rounded-2xl border-2 border-slate-300 bg-white shadow-sm">
      <div class="flex flex-col gap-4 border-b border-slate-200 p-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-extrabold tracking-tight">Claimed Recipients</h1>
          <p class="mt-1 text-sm text-slate-500">Recipients who have claimed their certificate repository accounts</p>
        </div>
        @include('admin.partials.action-menu', [
          'menuId' => 'claimed-menu',
        ])
      </div>

      @if (session('success'))
        <div class="px-6 pt-6">
          <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
            <div class="font-bold">{{ session('success') }}</div>
          </div>
        </div>
      @endif

      <div class="px-6 pt-6">
        <div class="flex flex-wrap items-center gap-3">
          <a href="{{ route('admin.users.index') }}"
             class="inline-flex items-center rounded-xl border px-3 py-1.5 text-sm font-bold border-slate-200 text-slate-700 hover:bg-slate-50">
            Staff Users
          </a>
          <a href="{{ route('admin.users.claimed') }}"
             class="inline-flex items-center rounded-xl border px-3 py-1.5 text-sm font-bold border-slate-900 bg-slate-900 text-white">
            Claimed Recipients
          </a>
        </div>

        <form method="GET" action="{{ route('admin.users.claimed') }}" class="mt-4">
          <div class="relative max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
              type="text"
              name="search"
              value="{{ $search ?? '' }}"
              placeholder="Search by name, email, or contact number..."
              class="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2.5 text-sm font-semibold text-slate-700 placeholder:text-slate-400 focus:border-slate-900 focus:ring-2 focus:ring-slate-200 outline-none"
            >
          </div>
        </form>
      </div>

      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm border-2 border-slate-300">
            <thead class="text-left text-xs font-bold uppercase tracking-wide text-slate-500">
              <tr class="border-b-2 border-slate-300">
                <th class="py-3 pl-4 pr-4">Name</th>
                <th class="py-3 pr-4">Email</th>
                <th class="py-3 pr-4">Contact Number</th>
                <th class="py-3 pr-4">Gender</th>
                <th class="py-3 pr-4">Certificates</th>
                <th class="py-3 pr-4">Claimed At</th>
              </tr>
            </thead>
            <tbody class="divide-y-2 divide-slate-300">
              @forelse ($recipients as $recipient)
                <tr class="odd:bg-blue-100 even:bg-white hover:bg-blue-200/60">
                  <td class="py-3 pl-4 pr-4 font-extrabold text-slate-900 whitespace-nowrap">{{ $recipient->name }}</td>
                  <td class="py-3 pr-4 text-slate-700 whitespace-nowrap">{{ $recipient->email }}</td>
                  <td class="py-3 pr-4 text-slate-700 whitespace-nowrap">{{ $recipient->contact_number ?? '—' }}</td>
                  <td class="py-3 pr-4 text-slate-700 whitespace-nowrap">{{ $recipient->gender ?? '—' }}</td>
                  <td class="py-3 pr-4 whitespace-nowrap">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-extrabold ring-1 {{ ($recipient->certificates_count ?? 0) > 0 ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-500 ring-slate-200' }}">
                      {{ $recipient->certificates_count ?? 0 }}
                    </span>
                  </td>
                  <td class="py-3 pr-4 text-slate-500 whitespace-nowrap text-xs">
                    {{ $recipient->updated_at->format('M j, Y  g:i A') }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="py-10 text-center text-slate-500">
                    @if (!empty($search))
                      No claimed recipients matching "{{ $search }}".
                    @else
                      No claimed recipients yet.
                    @endif
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="mt-6 flex items-center justify-between">
          <div class="text-sm text-slate-500">
            Showing <span class="font-bold text-slate-900">{{ $recipients->firstItem() ?? 0 }}</span>
            to <span class="font-bold text-slate-900">{{ $recipients->lastItem() ?? 0 }}</span>
            of <span class="font-bold text-slate-900">{{ $recipients->total() }}</span> claimed recipients
          </div>
          <div class="text-sm">
            {{ $recipients->links('vendor.pagination.admin') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</x-admin-layout>
