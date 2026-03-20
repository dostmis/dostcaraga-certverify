<x-admin-layout title="Participant Intakes">
  <style>
    .pi-page {
      width: 100%;
      max-width: 1700px;
      margin: 0 auto;
      color: #0f172a;
      font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    }

    .pi-shell {
      background: #fff;
      border: 1px solid #dbe5ef;
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 22px 60px rgba(15, 23, 42, 0.12);
    }

    .pi-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 14px;
      flex-wrap: wrap;
      padding: 22px;
      border-bottom: 1px solid #dbe5ef;
      background: linear-gradient(145deg, #f7fbff 0%, #eef7ff 54%, #f0fdfa 100%);
    }

    .pi-title {
      margin: 0;
      font-size: 32px;
      line-height: 1.06;
      letter-spacing: -0.02em;
      font-weight: 900;
      color: #0f172a;
    }

    .pi-sub {
      margin: 8px 0 0;
      color: #64748b;
      font-size: 14px;
      font-weight: 600;
    }

    .pi-head-actions {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 8px;
    }

    .pi-head-top,
    .pi-head-bottom {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .pi-sep {
      color: #94a3b8;
      font-size: 12px;
      font-weight: 900;
    }

    .pi-btn,
    .pi-head-actions button {
      text-decoration: none;
      border: 1px solid transparent;
      border-radius: 12px;
      padding: 10px 14px;
      font-size: 13px;
      font-weight: 800;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      background: #fff;
      color: #334155;
    }

    .pi-btn:hover,
    .pi-head-actions button:hover {
      filter: brightness(0.98);
    }

    .pi-btn-outline {
      border-color: #cbd5e1;
      background: #fff;
      color: #334155;
    }

    .pi-btn-dark {
      border-color: #0f172a;
      background: #0f172a;
      color: #fff;
    }

    .pi-alert-wrap {
      padding: 20px 22px 0;
    }

    .pi-alert {
      border: 1px solid #a7f3d0;
      background: #ecfdf5;
      color: #065f46;
      border-radius: 14px;
      padding: 10px 12px;
      font-size: 14px;
      font-weight: 700;
    }

    .pi-body {
      padding: 22px;
    }

    .pi-filter {
      border: 1px solid #dbe5ef;
      border-radius: 14px;
      background: #f8fbff;
      padding: 12px;
    }

    .pi-filter-form {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .pi-input,
    .pi-select {
      border: 1px solid #c7d5e4;
      background: #fff;
      color: #0f172a;
      border-radius: 12px;
      padding: 11px 12px;
      font-size: 14px;
      font-weight: 600;
      outline: none;
    }

    .pi-input {
      flex: 1;
      min-width: 260px;
    }

    .pi-select {
      min-width: 140px;
    }

    .pi-input:focus,
    .pi-select:focus {
      border-color: #4c8cc6;
      box-shadow: 0 0 0 3px rgba(76, 140, 198, 0.14);
    }

    .pi-bulk {
      margin-top: 12px;
      border: 1px solid #dbe5ef;
      border-radius: 14px;
      background: #fff;
      padding: 10px;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .pi-bulk button {
      border: 1px solid transparent;
      border-radius: 10px;
      padding: 8px 12px;
      font-size: 12px;
      font-weight: 900;
      color: #fff;
      cursor: pointer;
    }

    .pi-note {
      font-size: 12px;
      color: #64748b;
      font-weight: 600;
      margin-left: 2px;
    }

    .pi-table-wrap {
      margin-top: 12px;
      border: 1px solid #dbe5ef;
      border-radius: 16px;
      overflow: hidden;
      background: #fff;
    }

    .pi-table-scroll {
      overflow-x: auto;
    }

    .pi-table {
      width: 100%;
      min-width: 1480px;
      border-collapse: collapse;
      font-size: 13px;
    }

    .pi-table thead tr {
      background: #0f172a;
    }

    .pi-table th {
      text-align: left;
      padding: 12px;
      color: #e2e8f0;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .pi-table td {
      border-top: 1px solid #e2e8f0;
      padding: 11px 12px;
      color: #334155;
      vertical-align: middle;
      background: #fff;
    }

    .pi-table tbody tr:hover td {
      background: #f0f9ff;
    }

    .pi-name {
      color: #0f172a !important;
      font-weight: 800;
      white-space: nowrap;
    }

    .pi-nowrap {
      white-space: nowrap;
    }

    .pi-status {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 900;
      padding: 4px 10px;
      border: 1px solid transparent;
      white-space: nowrap;
    }

    .pi-approved {
      color: #047857;
      background: #ecfdf5;
      border-color: #6ee7b7;
    }

    .pi-pending {
      color: #a16207;
      background: #fffbeb;
      border-color: #fcd34d;
    }

    .pi-done {
      color: #1d4ed8;
      background: #eff6ff;
      border-color: #93c5fd;
    }

    .pi-default {
      color: #334155;
      background: #f8fafc;
      border-color: #cbd5e1;
    }

    .pi-action-row {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }

    .pi-mini-btn {
      border: 1px solid transparent;
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 11px;
      font-weight: 900;
      color: #fff;
      cursor: pointer;
      line-height: 1;
    }

    .pi-empty {
      text-align: center;
      color: #64748b;
      padding: 44px 12px;
      font-size: 14px;
      font-weight: 600;
    }

    .pi-foot {
      margin-top: 12px;
    }

    @media (max-width: 980px) {
      .pi-head,
      .pi-body {
        padding: 14px;
      }

      .pi-alert-wrap {
        padding: 14px 14px 0;
      }

      .pi-head-actions {
        justify-content: flex-start;
        align-items: flex-start;
      }
    }
  </style>

  <div class="pi-page">
    <div class="pi-shell">
      <header class="pi-head">
        <div>
          <h1 class="pi-title">Participant Intakes</h1>
          <p class="pi-sub">Workflow: Pending -> Export Selected CSV -> Done.</p>
        </div>

        <div class="pi-head-actions">
          <div class="pi-head-top">
            @if ($canRegionalDirectorActions)
              <a href="{{ route('admin.certs.index') }}" class="pi-btn pi-btn-outline">Back to Certificates Dashboard</a>
            @elseif ($canEndorse)
              <a href="{{ route('admin.certs.create') }}" class="pi-btn pi-btn-outline">Create & Endorse Certificate</a>
              <a href="{{ route('admin.certs.index') }}" class="pi-btn pi-btn-outline">Back to Certificates Dashboard</a>
            @endif
          </div>

          <div class="pi-head-bottom">
            @if ($canRegionalDirectorActions)
              <form method="POST" action="{{ route('admin.participant-intakes.toggle') }}">
                @csrf
                <input type="hidden" name="enabled" value="{{ $intakeEnabled ? '0' : '1' }}">
                <button
                  class="pi-btn"
                  style="color:#fff;border-color:{{ $intakeEnabled ? '#16a34a' : '#dc2626' }};background:{{ $intakeEnabled ? '#16a34a' : '#dc2626' }};"
                >
                  {{ $intakeEnabled ? 'Intake: ON' : 'Intake: OFF' }}
                </button>
              </form>
              <span class="pi-sep">|</span>
            @endif
            @if (!$canRegionalDirectorActions && !empty($activeEventUrl))
              <a href="{{ $activeEventUrl }}" class="pi-btn pi-btn-outline" target="_blank" rel="noopener">Form</a>
            @endif
            @if ($canRegionalDirectorActions)
              <span class="pi-sep">|</span>
              <a href="{{ route('admin.participant-intakes.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="pi-btn pi-btn-outline">Export CSV</a>
            @endif
            <span class="pi-sep">|</span>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button class="pi-btn" style="color:#fff;background:#dc2626;border-color:#dc2626;">Logout</button>
            </form>
          </div>
        </div>
      </header>

      @if (session('success'))
        <div class="pi-alert-wrap">
          <div class="pi-alert">{{ session('success') }}</div>
        </div>
      @endif

      <div class="pi-body">
        @if ($canEndorse)
          <section class="pi-filter" style="margin-bottom:12px;">
            <form method="POST" action="{{ route('admin.participant-intakes.events.create') }}" class="pi-filter-form">
              @csrf
              <input type="text" name="event_name" class="pi-input" placeholder="Create new event intake link (e.g. 2026 SETUP Orientation)" required>
              <button type="submit" class="pi-btn pi-btn-dark">Create Link</button>
            </form>
            @if (($eventLinks ?? collect())->count() > 0)
              <div style="margin-top:10px;display:grid;gap:8px;">
                @foreach ($eventLinks as $eventLink)
                  @php
                    $publicUrl = rtrim((string) config('app.url'), '/') . route('participant.intake', ['token' => $eventLink->public_token], false);
                  @endphp
                  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;border:1px solid #e2e8f0;border-radius:10px;padding:8px;background:#fff;">
                    <strong style="font-size:12px;color:#0f172a;">{{ $eventLink->event_name }}</strong>
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="pi-btn pi-btn-outline" style="padding:6px 10px;font-size:12px;">Open Link</a>
                    <input value="{{ $publicUrl }}" readonly style="flex:1;min-width:220px;border:1px solid #cbd5e1;border-radius:8px;padding:6px 8px;font-size:12px;">
                    <form method="POST" action="{{ route('admin.participant-intakes.events.toggle', ['event' => $eventLink->id]) }}">
                      @csrf
                      <input type="hidden" name="enabled" value="{{ $eventLink->is_active ? '0' : '1' }}">
                      <button type="submit" class="pi-btn" style="padding:6px 10px;font-size:12px;color:#fff;border-color:{{ $eventLink->is_active ? '#16a34a' : '#dc2626' }};background:{{ $eventLink->is_active ? '#16a34a' : '#dc2626' }};">
                        {{ $eventLink->is_active ? 'Active' : 'Inactive' }}
                      </button>
                    </form>
                    <form method="POST" action="{{ route('admin.participant-intakes.events.delete', ['event' => $eventLink->id]) }}" onsubmit="return confirm('Delete this intake link? This cannot be undone.')">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="pi-btn" style="padding:6px 10px;font-size:12px;color:#fff;border-color:#dc2626;background:#dc2626;">
                        Delete Link
                      </button>
                    </form>
                  </div>
                @endforeach
              </div>
            @endif
          </section>
        @endif

        <section class="pi-filter">
          <form id="intakeFilterForm" method="GET" action="{{ route('admin.participant-intakes.index') }}" class="pi-filter-form">
            <input
              id="intakeSearch"
              type="text"
              name="q"
              value="{{ $filters['q'] }}"
              placeholder="Search name, email, contact no., region, province, city..."
              class="pi-input"
            >
            <select name="event_id" class="pi-select" onchange="this.form.submit()">
              <option value="all" @selected(($filters['event_id'] ?? 'all') === 'all')>All Events</option>
              @foreach (($eventLinks ?? collect()) as $eventLink)
                <option value="{{ $eventLink->id }}" @selected((string) ($filters['event_id'] ?? 'all') === (string) $eventLink->id)>
                  {{ $eventLink->event_name }}
                </option>
              @endforeach
            </select>
            <select name="status" class="pi-select" onchange="this.form.submit()">
              <option value="pending" @selected($filters['status']==='pending')>Pending ({{ $pendingCount }})</option>
              <option value="done" @selected($filters['status']==='done')>Done ({{ $doneCount }})</option>
              <option value="all" @selected($filters['status']==='all')>All</option>
            </select>
          </form>
        </section>

        <form id="bulkIntakeForm" method="POST" class="pi-bulk">
          @csrf
          <input type="hidden" name="status" value="{{ $filters['status'] ?? 'pending' }}">
          @if ($canEndorse)
            <button type="submit" formaction="{{ route('admin.participant-intakes.export-selected', ['format' => 'csv']) }}" style="background:#1d4ed8;border-color:#1d4ed8;">
              Export Selected CSV
            </button>
          @endif

          @if ($canRegionalDirectorActions)
            <button type="submit" formaction="{{ route('admin.participant-intakes.bulk-delete') }}" style="background:#dc2626;border-color:#dc2626;" onclick="return confirm('Delete selected pending submissions?')">
              Delete Selected
            </button>
          @endif

          <span class="pi-note">
            @if ($canRegionalDirectorActions)
              Export Selected CSV moves pending records to done. Delete works on pending only.
            @else
              In Pending filter: checked rows are exported and marked done. In Done filter: checked rows are exported only.
            @endif
          </span>
        </form>

        <section class="pi-table-wrap">
          <div class="pi-table-scroll">
            <table class="pi-table">
              <thead>
                <tr>
                  <th>
                    <input id="selectAllIntakes" type="checkbox">
                  </th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Contact No.</th>
                  <th>Sex</th>
                  <th>Age Range</th>
                  <th>Affiliation/Sector</th>
                  <th>Region</th>
                  <th>Province</th>
                  <th>City/Municipality</th>
                  <th>Barangay</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($intakes as $i)
                  @php
                    $status = in_array(strtolower((string) $i->status), ['endorsed', 'rd_approved'], true)
                      ? 'done'
                      : strtolower((string) $i->status);
                    $badge = match($status){
                      'pending' => 'pi-pending',
                      'done' => 'pi-default',
                      default => 'pi-default',
                    };
                  @endphp
                  <tr>
                    <td>
                      <input type="checkbox" name="selected[]" value="{{ $i->id }}" form="bulkIntakeForm" class="intake-checkbox">
                    </td>
                    <td class="pi-name">{{ $i->participant_name }}</td>
                    <td class="pi-nowrap">{{ $i->email ?? '-' }}</td>
                    <td class="pi-nowrap">{{ $i->contact_number ?? '-' }}</td>
                    <td>{{ $i->gender ?? '-' }}</td>
                    <td>{{ $i->age_range ?? '-' }}</td>
                    <td>{{ $i->industry ?? '-' }}</td>
                    <td>{{ $i->region ?? '-' }}</td>
                    <td>{{ $i->province ?? '-' }}</td>
                    <td>{{ $i->city_municipality ?? '-' }}</td>
                    <td>{{ $i->barangay ?? '-' }}</td>
                    <td><span class="pi-status {{ $badge }}">{{ strtoupper($status) }}</span></td>
                    <td>
                      @if ($status === 'pending')
                        <div class="pi-action-row">
                          @if ($canRegionalDirectorActions)
                            <form method="POST" action="{{ route('admin.participant-intakes.destroy', $i) }}" onsubmit="return confirm('Delete this submission?')">
                              @csrf
                              @method('DELETE')
                              <button class="pi-mini-btn" style="background:#dc2626;border-color:#dc2626;">Delete</button>
                            </form>
                          @endif
                        </div>
                      @else
                        <span class="pi-note">—</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="13" class="pi-empty">No submissions found.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>

      </div>
    </div>
  </div>

  <script>
    const selectAll = document.getElementById('selectAllIntakes');
    const boxes = document.querySelectorAll('.intake-checkbox');
    if (selectAll) {
      selectAll.addEventListener('change', (e) => {
        boxes.forEach((b) => { b.checked = e.target.checked; });
      });
    }

    const intakeSearch = document.getElementById('intakeSearch');
    const intakeForm = document.getElementById('intakeFilterForm');
    if (intakeSearch && intakeForm) {
      let t = null;
      intakeSearch.addEventListener('input', () => {
        if (t) clearTimeout(t);
        t = setTimeout(() => {
          intakeForm.submit();
        }, 300);
      });
    }
  </script>
</x-admin-layout>
