<x-admin-layout title="Certificates">
  @php
    $isGrouped = ($group ?? '') === 'training';
    $isRegionalDirector = (bool) ($isRegionalDirector ?? (auth()->user() && auth()->user()->isRegionalDirector()));
    $canEndorseCertificates = (bool) ($canEndorseCertificates ?? false);
    $endorsementRequests = $endorsementRequests ?? collect();
    $pendingEndorsementsCount = (int) ($pendingEndorsementsCount ?? 0);
  @endphp

  <style>
    .cert-page {
      width: 100%;
      margin: 0 auto;
      max-width: 1700px;
      color: #0f172a;
      font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    }

    .cert-shell {
      background: #fff;
      border: 1px solid #dbe5ef;
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 22px 60px rgba(15, 23, 42, 0.12);
    }

    .cert-hero {
      position: relative;
      padding: 24px;
      border-bottom: 1px solid #dbe5ef;
      background:
        radial-gradient(380px 170px at 0% 0%, rgba(255, 255, 255, 0.22), transparent 72%),
        radial-gradient(320px 150px at 100% 14%, rgba(34, 211, 238, 0.2), transparent 80%),
        linear-gradient(120deg, #0f4f8c 0%, #0e74ab 56%, #0f9186 100%);
      color: #fff;
      display: flex;
      justify-content: space-between;
      gap: 18px;
      flex-wrap: wrap;
    }

    .cert-hero-left {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      min-width: 260px;
      flex: 1;
    }

    .cert-icon {
      width: 54px;
      height: 54px;
      border-radius: 14px;
      border: 1px solid rgba(255, 255, 255, 0.45);
      background: rgba(255, 255, 255, 0.18);
      backdrop-filter: blur(6px);
      display: grid;
      place-items: center;
      flex: 0 0 54px;
    }

    .cert-chip {
      display: inline-flex;
      align-items: center;
      padding: 5px 10px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.45);
      background: rgba(255, 255, 255, 0.15);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
    }

    .cert-title {
      margin: 10px 0 0;
      font-size: clamp(30px, 3vw, 42px);
      line-height: 1.06;
      letter-spacing: -0.03em;
      font-weight: 900;
    }

    .cert-subtitle {
      margin: 10px 0 0;
      max-width: 720px;
      color: #dbeafe;
      font-size: 14px;
      font-weight: 600;
      line-height: 1.4;
    }

    .cert-hero-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      align-content: flex-start;
    }

    .cert-btn {
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
    }

    .cert-btn-light {
      color: #fff;
      border-color: rgba(255, 255, 255, 0.4);
      background: rgba(255, 255, 255, 0.12);
    }

    .cert-btn-light:hover {
      background: rgba(255, 255, 255, 0.2);
    }

    .cert-btn-white {
      color: #1e40af;
      background: #fff;
      border-color: #fff;
    }

    .cert-btn-white:hover {
      background: #eff6ff;
    }

    .cert-btn-danger {
      color: #fff;
      background: #dc2626;
      border-color: #dc2626;
    }

    .cert-btn-danger:hover {
      background: #b91c1c;
      border-color: #b91c1c;
    }

    .cert-alert-wrap {
      padding: 20px 24px 0;
    }

    .cert-alert {
      border: 1px solid #a7f3d0;
      background: #ecfdf5;
      color: #065f46;
      border-radius: 14px;
      padding: 10px 12px;
      font-size: 14px;
      font-weight: 700;
    }

    .cert-alert-error {
      border-color: #fecaca;
      background: #fef2f2;
      color: #991b1b;
    }

    .cert-alert-list {
      margin: 6px 0 0;
      padding-left: 18px;
      font-size: 13px;
      font-weight: 600;
    }

    .cert-body {
      padding: 24px;
    }

    .cert-search {
      border: 1px solid #dbe5ef;
      border-radius: 16px;
      background: #f8fbff;
      padding: 14px;
    }

    .cert-search-form {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }

    .cert-input-wrap {
      position: relative;
      flex: 1;
      min-width: 260px;
    }

    .cert-search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      pointer-events: none;
    }

    .cert-input {
      width: 100%;
      border-radius: 12px;
      border: 1px solid #c7d5e4;
      background: #fff;
      color: #0f172a;
      font-size: 14px;
      font-weight: 600;
      padding: 11px 12px 11px 38px;
      outline: none;
      transition: border-color 120ms ease, box-shadow 120ms ease;
    }

    .cert-input:focus {
      border-color: #4c8cc6;
      box-shadow: 0 0 0 3px rgba(76, 140, 198, 0.15);
    }

    .cert-btn-dark {
      color: #fff;
      background: #0f172a;
      border-color: #0f172a;
    }

    .cert-btn-dark:hover {
      background: #020617;
      border-color: #020617;
    }

    .cert-btn-ghost {
      color: #334155;
      background: #fff;
      border-color: #cbd5e1;
    }

    .cert-btn-ghost:hover {
      background: #f8fafc;
    }

    .cert-tabs-row {
      margin-top: 12px;
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    .cert-tabs {
      display: inline-flex;
      border: 1px solid #dbe5ef;
      border-radius: 12px;
      background: #fff;
      padding: 4px;
      gap: 4px;
    }

    .cert-tab {
      text-decoration: none;
      border-radius: 8px;
      padding: 7px 11px;
      font-size: 13px;
      font-weight: 800;
      color: #334155;
      border: 1px solid transparent;
    }

    .cert-tab:hover {
      background: #f1f5f9;
    }

    .cert-tab.active {
      color: #fff;
      border-color: #0f172a;
      background: #0f172a;
    }

    .cert-result {
      font-size: 13px;
      color: #64748b;
      font-weight: 600;
    }

    .cert-result strong {
      color: #0f172a;
      font-weight: 900;
    }

    .cert-stats {
      margin-top: 14px;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    .cert-stat {
      border: 1px solid #dbe5ef;
      border-radius: 14px;
      background: #fff;
      padding: 12px;
    }

    .cert-stat h3 {
      margin: 0;
      font-size: 11px;
      font-weight: 900;
      color: #64748b;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .cert-stat strong {
      display: block;
      margin-top: 8px;
      font-size: 33px;
      line-height: 1;
      color: #0f172a;
      font-weight: 900;
    }

    .cert-stat p {
      margin: 6px 0 0;
      font-size: 12px;
      color: #64748b;
      font-weight: 600;
    }

    .cert-stat.blue {
      border-color: #bfdbfe;
      background: linear-gradient(150deg, #eff6ff 0%, #fff 100%);
    }

    .cert-stat.blue h3,
    .cert-stat.blue p {
      color: #1d4ed8;
    }

    .cert-stat.blue strong {
      color: #1e3a8a;
    }

    .cert-stat.cyan {
      border-color: #a5f3fc;
      background: linear-gradient(150deg, #ecfeff 0%, #fff 100%);
    }

    .cert-stat.cyan h3,
    .cert-stat.cyan p {
      color: #0f766e;
    }

    .cert-stat.cyan strong {
      color: #134e4a;
    }

    .cert-stat.green {
      border-color: #bbf7d0;
      background: linear-gradient(150deg, #ecfdf5 0%, #fff 100%);
    }

    .cert-stat.green h3,
    .cert-stat.green p {
      color: #15803d;
    }

    .cert-stat.green strong {
      color: #14532d;
    }

    .cert-stat.indigo {
      border-color: #c7d2fe;
      background: linear-gradient(150deg, #eef2ff 0%, #fff 100%);
    }

    .cert-stat.indigo h3,
    .cert-stat.indigo p {
      color: #4338ca;
    }

    .cert-stat.indigo strong {
      color: #312e81;
    }

    .cert-table-wrap {
      margin-top: 14px;
      border: 1px solid #dbe5ef;
      border-radius: 16px;
      overflow: hidden;
      background: #fff;
    }

    .cert-table-scroll {
      overflow-x: auto;
    }

    .cert-table {
      min-width: 1120px;
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .cert-table thead tr {
      background: #0f172a;
    }

    .cert-table th {
      text-align: left;
      padding: 12px 12px;
      color: #e2e8f0;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .cert-table td {
      border-top: 1px solid #e2e8f0;
      padding: 11px 12px;
      color: #334155;
      vertical-align: middle;
      background: #fff;
    }

    .cert-table tbody tr:hover td {
      background: #f0f9ff;
    }

    .cert-code {
      color: #0f172a !important;
      font-weight: 900;
      white-space: nowrap;
    }

    .cert-name {
      color: #0f172a !important;
      font-weight: 700;
      white-space: nowrap;
    }

    .cert-td-now {
      white-space: nowrap;
    }

    .cert-status {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 900;
      padding: 4px 10px;
      border: 1px solid transparent;
      white-space: nowrap;
    }

    .status-valid {
      color: #047857;
      background: #ecfdf5;
      border-color: #6ee7b7;
    }

    .status-invalid {
      color: #be123c;
      background: #fff1f2;
      border-color: #fda4af;
    }

    .status-revoked {
      color: #a16207;
      background: #fffbeb;
      border-color: #fcd34d;
    }

    .status-default {
      color: #334155;
      background: #f8fafc;
      border-color: #cbd5e1;
    }

    .status-endorsed {
      color: #155e75;
      background: #ecfeff;
      border-color: #a5f3fc;
    }

    .status-rd-approved {
      color: #166534;
      background: #ecfdf5;
      border-color: #86efac;
    }

    .status-rd-rejected {
      color: #991b1b;
      background: #fef2f2;
      border-color: #fca5a5;
    }

    .cert-link-row {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }

    .cert-mini-btn {
      text-decoration: none;
      border-radius: 8px;
      font-size: 11px;
      font-weight: 900;
      padding: 5px 10px;
      border: 1px solid transparent;
      line-height: 1;
      white-space: nowrap;
    }

    .cert-mini-verify {
      color: #1d4ed8;
      border-color: #bfdbfe;
      background: #eff6ff;
    }

    .cert-mini-verify:hover {
      background: #dbeafe;
    }

    .cert-mini-pdf,
    .cert-mini-zip {
      color: #fff;
      border-color: #0f172a;
      background: #0f172a;
    }

    .cert-mini-pdf:hover,
    .cert-mini-zip:hover {
      background: #020617;
    }

    .cert-mini-approve {
      border: 1px solid #16a34a;
      background: #16a34a;
      color: #fff;
    }

    .cert-mini-reject {
      border: 1px solid #dc2626;
      background: #dc2626;
      color: #fff;
    }

    .cert-inline-form {
      display: inline-flex;
      margin: 0;
    }

    .cert-empty {
      text-align: center;
      font-size: 14px;
      color: #64748b;
      font-weight: 600;
      padding: 44px 12px;
    }

    .cert-muted {
      font-size: 11px;
      font-weight: 700;
      color: #94a3b8;
    }

    .cert-footer {
      margin-top: 14px;
      border: 1px solid #dbe5ef;
      border-radius: 14px;
      background: #f8fbff;
      padding: 10px 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .cert-footer-text {
      font-size: 13px;
      color: #64748b;
      font-weight: 600;
    }

    .cert-footer-text strong {
      color: #0f172a;
      font-weight: 900;
    }

    .cert-pagination nav {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }

    @media (max-width: 980px) {
      .cert-stats {
        grid-template-columns: 1fr;
      }

      .cert-body,
      .cert-hero {
        padding: 14px;
      }

      .cert-alert-wrap {
        padding: 14px 14px 0;
      }
    }
  </style>

  <div class="cert-page">
    <div class="cert-shell">
      <header class="cert-hero">
        <div class="cert-hero-left">
          <div class="cert-icon" aria-hidden="true">
            <svg style="width: 30px; height: 30px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path d="M12 15a7 7 0 1 0 0-14 7 7 0 0 0 0 14z"></path>
              <path d="M8.21 13.89 7 23l5-3 5 3-1.21-9.11"></path>
            </svg>
          </div>
          <div>
            <span class="cert-chip">Certificates Dashboard</span>
            <h1 class="cert-title">Issued Certificates</h1>
            <p class="cert-subtitle">Workflow: Organizer/Supervising Unit prepare and endorse; Regional Director approves and generates QR certificates.</p>
          </div>
        </div>

        <div class="cert-hero-actions">
          @if ($canViewAnalytics)
            <a href="{{ route('admin.analytics.index') }}" class="cert-btn cert-btn-light">Analytics</a>
          @endif
          <a href="{{ route('admin.participant-intakes.index') }}" class="cert-btn cert-btn-light">Intakes</a>
          @if ($isRegionalDirector)
            <a href="{{ route('admin.certs.approvals') }}" class="cert-btn cert-btn-light">Phone Mode</a>
          @endif
          @if ($canEndorseCertificates || $isRegionalDirector)
            <a href="{{ route('admin.certs.create') }}" class="cert-btn cert-btn-white">{{ $isRegionalDirector ? '+ Create (RD)' : '+ Create & Endorse' }}</a>
          @endif
          @if ($isRegionalDirector)
            <span class="cert-btn cert-btn-light">Endorsed Queue: {{ number_format($pendingEndorsementsCount) }}</span>
          @endif
          @if ($isRegionalDirector)
            <a href="{{ route('admin.users.index') }}" class="cert-btn cert-btn-light">Users</a>
          @endif
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="cert-btn cert-btn-danger">Logout</button>
          </form>
        </div>
      </header>

      @if (session('success'))
        <div class="cert-alert-wrap">
          <div class="cert-alert">{{ session('success') }}</div>
        </div>
      @endif
      @if ($errors->any())
        <div class="cert-alert-wrap">
          <div class="cert-alert cert-alert-error">
            <div>Unable to complete action:</div>
            <ul class="cert-alert-list">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      <div class="cert-body">
        <section class="cert-search">
          <form method="GET" action="{{ route('admin.certs.index') }}" class="cert-search-form">
            <div class="cert-input-wrap">
              <svg class="cert-search-icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="m21 21-4.35-4.35"></path>
                <circle cx="11" cy="11" r="7"></circle>
              </svg>
              <input
                type="text"
                name="q"
                value="{{ $search ?? '' }}"
                placeholder="Search code, participant, training, office, status..."
                class="cert-input"
              />
              @if ($isGrouped)
                <input type="hidden" name="group" value="training" />
              @endif
            </div>
            <button type="submit" class="cert-btn cert-btn-dark">Search</button>
            @if (!empty($search))
              <a href="{{ route('admin.certs.index', $isGrouped ? ['group' => 'training'] : []) }}" class="cert-btn cert-btn-ghost">Reset</a>
            @endif
          </form>

          <div class="cert-tabs-row">
            <div class="cert-tabs">
              <a href="{{ route('admin.certs.index', ['q' => $search]) }}" class="cert-tab {{ $isGrouped ? '' : 'active' }}">All certificates</a>
              <a href="{{ route('admin.certs.index', ['group' => 'training', 'q' => $search]) }}" class="cert-tab {{ $isGrouped ? 'active' : '' }}">Group by training</a>
            </div>
            @if (!empty($search))
              <div class="cert-result">Showing results for <strong>{{ $search }}</strong></div>
            @endif
          </div>
        </section>

        <section class="cert-table-wrap" style="margin-top:14px;">
          <div class="cert-table-scroll">
            <table class="cert-table">
              <thead>
                <tr>
                  <th>Endorsed Certificate Package</th>
                  <th>Training Date</th>
                  <th>Submitted By</th>
                  <th>Recipient Type</th>
                  <th>No. of Participants</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($endorsementRequests as $requestItem)
                  @php
                    $payload = is_array($requestItem->payload) ? $requestItem->payload : [];
                    $from = $payload['training_date_from'] ?? null;
                    $to = $payload['training_date_to'] ?? $from;
                    $dateRange = $from
                      ? ($from === $to ? $from : ($from . ' to ' . $to))
                      : '-';
                    $status = strtolower((string) $requestItem->status);
                    $statusClass = match($status) {
                      'endorsed' => 'status-endorsed',
                      'rd_approved' => 'status-rd-approved',
                      'rd_rejected' => 'status-rd-rejected',
                      default => 'status-default',
                    };
                  @endphp
                  @php
                    $participantsReviewed = (bool) session('cert_endorsements.participants_reviewed.' . $requestItem->id, false);
                  @endphp
                  <tr>
                    <td>
                      <div class="cert-code">{{ $payload['training_title'] ?? 'Untitled Training' }}</div>
                      <div class="cert-muted">{{ $payload['issuing_office'] ?? 'Unspecified Office' }}</div>
                      @if ($isRegionalDirector)
                        <div class="cert-muted" style="margin-top:4px;">
                          First participant: {{ $requestItem->first_participant_name ?? 'Unavailable' }}
                        </div>
                      @endif
                    </td>
                    <td class="cert-td-now">{{ $dateRange }}</td>
                    <td class="cert-td-now">
                      {{ $requestItem->submitter?->name ?? ('User #' . ($requestItem->submitted_by ?? 'N/A')) }}
                    </td>
                    <td class="cert-td-now">{{ $payload['recipient_type'] ?? '-' }}</td>
                    <td class="cert-td-now">{{ number_format((int) ($requestItem->participants_count ?? 0)) }}</td>
                    <td>
                      <span class="cert-status {{ $statusClass }}">{{ strtoupper(str_replace('_', ' ', $requestItem->status)) }}</span>
                      @if (!empty($requestItem->rejection_reason))
                        <div class="cert-muted" style="margin-top:6px;max-width:280px;">Reason: {{ $requestItem->rejection_reason }}</div>
                      @endif
                    </td>
                    <td>
                      <div class="cert-link-row">
                        @if ($isRegionalDirector && !empty($requestItem->template_pdf_path))
                          <a class="cert-mini-btn cert-mini-verify" target="_blank" rel="noopener" href="{{ route('admin.certs.endorsements.template.view', ['id' => $requestItem->id]) }}">
                            Uploaded PDF
                          </a>
                        @endif
                        @if ($isRegionalDirector && !empty($requestItem->template_pdf_path) && !empty($requestItem->participants_file_path))
                          <a class="cert-mini-btn cert-mini-pdf" target="_blank" rel="noopener" href="{{ route('admin.certs.endorsements.preview', ['id' => $requestItem->id]) }}">
                            Preview PDF
                          </a>
                        @endif
                        @if ($isRegionalDirector && !empty($requestItem->participants_file_path))
                          <a class="cert-mini-btn cert-mini-verify" target="_blank" rel="noopener" href="{{ route('admin.certs.endorsements.participants.download', ['id' => $requestItem->id]) }}">
                            Download Participants
                          </a>
                        @endif
                        @if ($isRegionalDirector && $requestItem->status === 'endorsed')
                          <span class="cert-muted">
                            {{ $participantsReviewed ? 'Participants reviewed' : 'Review participants before approval' }}
                          </span>
                          <form method="POST" action="{{ route('admin.certs.endorsements.approve', ['id' => $requestItem->id]) }}" class="cert-inline-form" onsubmit="return confirm('Are you sure you want to approve this certificate package and generate the certificates?')">
                            @csrf
                            <button type="submit" class="cert-mini-btn cert-mini-approve">Approve & Generate</button>
                          </form>
                          <form method="POST" action="{{ route('admin.certs.endorsements.reject', ['id' => $requestItem->id]) }}" class="cert-inline-form" onsubmit="return confirm('Reject this endorsement request?')">
                            @csrf
                            <button type="submit" class="cert-mini-btn cert-mini-reject">Reject</button>
                          </form>
                        @elseif ($requestItem->status === 'rd_approved')
                          <span class="cert-muted">Generated: {{ number_format((int) ($requestItem->generated_count ?? 0)) }}</span>
                        @else
                          <span class="cert-muted">Awaiting RD action</span>
                        @endif
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="cert-empty">
                      {{ $isRegionalDirector ? 'No endorsed certificate packages pending review.' : 'You have no endorsed certificate packages yet.' }}
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </section>

        @if ($isGrouped)
          @php
            $visibleGroups = $groups->count();
            $visibleCertificates = $groups->sum('total_count');
            $visiblePdfs = $groups->sum('pdf_count');
          @endphp
          <section class="cert-stats">
            <article class="cert-stat">
              <h3>Training Groups</h3>
              <strong>{{ number_format($groups->total()) }}</strong>
              <p>All grouped training batches</p>
            </article>
            <article class="cert-stat blue">
              <h3>Certificates on this page</h3>
              <strong>{{ number_format($visibleCertificates) }}</strong>
              <p>Across {{ number_format($visibleGroups) }} visible groups</p>
            </article>
            <article class="cert-stat cyan">
              <h3>PDFs Ready</h3>
              <strong>{{ number_format($visiblePdfs) }}</strong>
              <p>Downloadable certificate files</p>
            </article>
          </section>
        @else
          @php
            $visibleCount = $certs->count();
            $validCount = $certs->where('status', 'valid')->count();
            $withPdf = $certs->whereNotNull('stamped_pdf_path')->count();
          @endphp
          <section class="cert-stats">
            <article class="cert-stat">
              <h3>Total Certificates</h3>
              <strong>{{ number_format($certs->total()) }}</strong>
              <p>Records matching current filters</p>
            </article>
            <article class="cert-stat green">
              <h3>Valid on this page</h3>
              <strong>{{ number_format($validCount) }}</strong>
              <p>Out of {{ number_format($visibleCount) }} visible records</p>
            </article>
            <article class="cert-stat indigo">
              <h3>With PDF Attached</h3>
              <strong>{{ number_format($withPdf) }}</strong>
              <p>Ready for quick download</p>
            </article>
          </section>
        @endif

        @if ($isGrouped)
          <section class="cert-table-wrap">
            <div class="cert-table-scroll">
              <table class="cert-table">
                <thead>
                  <tr>
                    <th>Training</th>
                    <th>Date</th>
                    <th>Date created</th>
                    <th>Time created</th>
                    <th>Office</th>
                    <th>Certificates</th>
                    <th>PDFs</th>
                    <th>Bulk</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($groups as $g)
                    @php
                      $dateFrom = \Illuminate\Support\Carbon::parse($g->training_date)->format('Y-m-d');
                      $dateTo = $g->training_date_to
                        ? \Illuminate\Support\Carbon::parse($g->training_date_to)->format('Y-m-d')
                        : $dateFrom;
                      $dateRange = $dateFrom === $dateTo ? $dateFrom : ($dateFrom . ' to ' . $dateTo);
                      $createdAt = $g->created_at
                        ? \Illuminate\Support\Carbon::parse($g->created_at)->timezone('Asia/Manila')
                        : null;
                    @endphp
                    <tr>
                      <td class="cert-code">{{ $g->training_title }}</td>
                      <td class="cert-td-now">{{ $dateRange }}</td>
                      <td class="cert-td-now">{{ $createdAt ? $createdAt->format('Y-m-d') : '-' }}</td>
                      <td class="cert-td-now">{{ $createdAt ? $createdAt->format('g:ia') : '-' }}</td>
                      <td class="cert-td-now">{{ $g->issuing_office }}</td>
                      <td class="cert-td-now">{{ $g->total_count }}</td>
                      <td class="cert-td-now">{{ $g->pdf_count }}</td>
                      <td>
                        @if ($canDownloadCertificates)
                          <a
                            class="cert-mini-btn cert-mini-zip"
                            href="{{ route('admin.certs.group-download', ['title' => $g->training_title, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'office' => $g->issuing_office]) }}"
                          >
                            Download ZIP
                          </a>
                        @else
                          <span class="cert-muted">Organizer/RD only</span>
                        @endif
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="8" class="cert-empty">No training groups found.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </section>
        @else
          <section class="cert-table-wrap">
            <div class="cert-table-scroll">
              <table class="cert-table">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Participant</th>
                    <th>Training</th>
                    <th>Date</th>
                    <th>Office</th>
                    <th>Status</th>
                    <th>Links</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($certs as $c)
                    @php
                      $dateFrom = $c->training_date?->format('Y-m-d');
                      $dateTo = $c->training_date_to?->format('Y-m-d') ?? $dateFrom;
                      $status = strtolower($c->status);
                      $statusClass = match($status){
                        'valid' => 'status-valid',
                        'invalid' => 'status-invalid',
                        'revoked' => 'status-revoked',
                        default => 'status-default',
                      };
                    @endphp
                    <tr>
                      <td class="cert-code">{{ $c->certificate_code }}</td>
                      <td class="cert-name">{{ $c->participant_name }}</td>
                      <td>{{ $c->training_title }}</td>
                      <td class="cert-td-now">{{ $dateFrom === $dateTo ? $dateFrom : ($dateFrom . ' to ' . $dateTo) }}</td>
                      <td class="cert-td-now">{{ $c->issuing_office }}</td>
                      <td><span class="cert-status {{ $statusClass }}">{{ strtoupper($c->status) }}</span></td>
                      <td>
                        <div class="cert-link-row">
                          <a class="cert-mini-btn cert-mini-verify" href="{{ route('cert.verify', ['t' => $c->public_token]) }}" target="_blank">Verify</a>
                          @if ($canDownloadCertificates && !empty($c->stamped_pdf_path))
                            <a class="cert-mini-btn cert-mini-pdf" href="{{ route('admin.certs.download', ['id' => $c->id]) }}">PDF</a>
                          @elseif (!$canDownloadCertificates)
                            <span class="cert-muted">Organizer/RD only</span>
                          @else
                            <span class="cert-muted">No PDF</span>
                          @endif
                        </div>
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="7" class="cert-empty">No certificates found.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </section>
        @endif

        <div class="cert-footer">
          @if ($isGrouped)
            <div class="cert-footer-text">
              Showing <strong>{{ $groups->firstItem() ?? 0 }}</strong>
              to <strong>{{ $groups->lastItem() ?? 0 }}</strong>
              of <strong>{{ $groups->total() }}</strong> training groups
            </div>
            <div class="cert-pagination">{{ $groups->links('vendor.pagination.admin') }}</div>
          @else
            <div class="cert-footer-text">
              Showing <strong>{{ $certs->firstItem() ?? 0 }}</strong>
              to <strong>{{ $certs->lastItem() ?? 0 }}</strong>
              of <strong>{{ $certs->total() }}</strong> certificates
            </div>
            <div class="cert-pagination">{{ $certs->links('vendor.pagination.admin') }}</div>
          @endif
        </div>
      </div>
    </div>
  </div>
</x-admin-layout>
