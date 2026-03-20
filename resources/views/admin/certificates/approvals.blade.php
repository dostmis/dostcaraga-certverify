<x-admin-layout title="RD Phone Mode">
  <style>
    .rda-page {
      width: 100%;
      max-width: 1080px;
      margin: 0 auto;
      color: #0f172a;
      font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    }

    .rda-hero {
      border: 1px solid #cfe0f1;
      border-radius: 26px;
      padding: 20px;
      background:
        radial-gradient(420px 190px at 0% 0%, rgba(255, 255, 255, 0.18), transparent 72%),
        radial-gradient(260px 130px at 100% 10%, rgba(34, 211, 238, 0.16), transparent 78%),
        linear-gradient(135deg, #0f4f8c 0%, #0f766e 100%);
      color: #fff;
      box-shadow: 0 22px 60px rgba(15, 23, 42, 0.16);
    }

    .rda-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 11px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, 0.45);
      background: rgba(255, 255, 255, 0.12);
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .rda-title {
      margin: 14px 0 0;
      font-size: clamp(34px, 7vw, 46px);
      line-height: 0.98;
      font-weight: 900;
      letter-spacing: -0.04em;
    }

    .rda-copy {
      margin: 12px 0 0;
      max-width: 760px;
      font-size: 15px;
      line-height: 1.55;
      color: #dbeafe;
      font-weight: 600;
    }

    .rda-hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 18px;
    }

    .rda-btn,
    .rda-hero-actions button {
      text-decoration: none;
      border: 1px solid transparent;
      border-radius: 14px;
      padding: 12px 15px;
      min-height: 48px;
      font-size: 14px;
      font-weight: 900;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .rda-btn-light {
      color: #fff;
      border-color: rgba(255, 255, 255, 0.42);
      background: rgba(255, 255, 255, 0.12);
    }

    .rda-btn-light:hover,
    .rda-hero-actions button:hover {
      background: rgba(255, 255, 255, 0.2);
    }

    .rda-btn-white {
      color: #0f4f8c;
      background: #fff;
      border-color: #fff;
    }

    .rda-btn-white:hover {
      background: #eff6ff;
    }

    .rda-btn-danger {
      color: #fff;
      background: #dc2626;
      border-color: #dc2626;
    }

    .rda-btn-danger:hover {
      background: #b91c1c;
    }

    .rda-alert-wrap {
      margin-top: 14px;
    }

    .rda-alert {
      border: 1px solid #a7f3d0;
      border-radius: 16px;
      background: #ecfdf5;
      color: #065f46;
      padding: 12px 14px;
      font-size: 14px;
      font-weight: 700;
    }

    .rda-alert-error {
      border-color: #fecaca;
      background: #fef2f2;
      color: #991b1b;
    }

    .rda-alert-list {
      margin: 6px 0 0;
      padding-left: 18px;
      font-size: 13px;
      font-weight: 600;
    }

    .rda-stats {
      margin-top: 14px;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    .rda-stat {
      border: 1px solid #dbe5ef;
      border-radius: 18px;
      background: #fff;
      padding: 14px;
      box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
    }

    .rda-stat-label {
      margin: 0;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #64748b;
    }

    .rda-stat-value {
      margin-top: 10px;
      font-size: 34px;
      line-height: 1;
      font-weight: 900;
      color: #0f172a;
    }

    .rda-stat-note {
      margin-top: 6px;
      font-size: 12px;
      color: #64748b;
      font-weight: 600;
    }

    .rda-section {
      margin-top: 16px;
    }

    .rda-section-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 10px;
      margin-bottom: 10px;
      flex-wrap: wrap;
    }

    .rda-section-head h2 {
      margin: 0;
      font-size: 22px;
      line-height: 1.02;
      font-weight: 900;
      letter-spacing: -0.03em;
      color: #0f172a;
    }

    .rda-section-head p {
      margin: 0;
      font-size: 13px;
      color: #64748b;
      font-weight: 600;
    }

    .rda-list {
      display: grid;
      gap: 12px;
    }

    .rda-card {
      border: 1px solid #dbe5ef;
      border-radius: 22px;
      background: #fff;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }

    .rda-card-body {
      padding: 16px;
      display: grid;
      gap: 12px;
    }

    .rda-card-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      flex-wrap: wrap;
    }

    .rda-badge {
      display: inline-flex;
      align-items: center;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .rda-badge-pending {
      background: #fff7ed;
      color: #c2410c;
      border: 1px solid #fdba74;
    }

    .rda-badge-approved {
      background: #ecfdf5;
      color: #166534;
      border: 1px solid #86efac;
    }

    .rda-badge-rejected {
      background: #fef2f2;
      color: #b91c1c;
      border: 1px solid #fca5a5;
    }

    .rda-training {
      margin: 10px 0 0;
      font-size: clamp(24px, 4.6vw, 30px);
      line-height: 1.02;
      font-weight: 900;
      letter-spacing: -0.04em;
      color: #0f172a;
    }

    .rda-office {
      margin: 6px 0 0;
      font-size: 13px;
      color: #64748b;
      font-weight: 700;
    }

    .rda-count {
      min-width: 120px;
      text-align: right;
      font-size: 13px;
      font-weight: 900;
      color: #0f4f8c;
    }

    .rda-meta {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      margin: 0;
    }

    .rda-meta div {
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      background: #f8fbff;
      padding: 10px 12px;
    }

    .rda-meta dt {
      margin: 0;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #64748b;
    }

    .rda-meta dd {
      margin: 7px 0 0;
      font-size: 14px;
      line-height: 1.45;
      font-weight: 800;
      color: #0f172a;
    }

    .rda-details {
      border: 1px solid #dbe5ef;
      border-radius: 16px;
      background: #f8fbff;
      padding: 12px;
    }

    .rda-details summary {
      cursor: pointer;
      font-size: 13px;
      font-weight: 900;
      color: #0f172a;
    }

    .rda-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 10px;
    }

    .rda-pill {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 7px 10px;
      background: #fff;
      border: 1px solid #bfdbfe;
      color: #1d4ed8;
      font-size: 12px;
      font-weight: 800;
    }

    .rda-note {
      margin: 10px 0 0;
      font-size: 12px;
      line-height: 1.5;
      color: #64748b;
      font-weight: 600;
    }

    .rda-warning {
      border: 1px solid #fed7aa;
      border-radius: 14px;
      background: #fff7ed;
      color: #9a3412;
      padding: 11px 12px;
      font-size: 12px;
      line-height: 1.5;
      font-weight: 700;
    }

    .rda-actions {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    .rda-actions form {
      margin: 0;
    }

    .rda-action {
      width: 100%;
      min-height: 50px;
      border: 1px solid transparent;
      border-radius: 16px;
      padding: 12px 14px;
      font-size: 14px;
      font-weight: 900;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      cursor: pointer;
    }

    .rda-action-primary {
      background: #15803d;
      border-color: #15803d;
      color: #fff;
    }

    .rda-action-secondary {
      background: #eff6ff;
      border-color: #bfdbfe;
      color: #1d4ed8;
    }

    .rda-action-danger {
      background: #dc2626;
      border-color: #dc2626;
      color: #fff;
    }

    .rda-action[disabled] {
      opacity: 0.45;
      cursor: not-allowed;
    }

    .rda-empty {
      border: 1px solid #dbe5ef;
      border-radius: 20px;
      background: #fff;
      padding: 32px 18px;
      text-align: center;
      color: #64748b;
      font-size: 15px;
      font-weight: 700;
    }

    .rda-recent-grid {
      display: grid;
      gap: 10px;
    }

    .rda-recent-card {
      border: 1px solid #dbe5ef;
      border-radius: 18px;
      background: #fff;
      padding: 13px 14px;
    }

    .rda-recent-title {
      margin: 8px 0 0;
      font-size: 16px;
      font-weight: 900;
      color: #0f172a;
    }

    .rda-recent-copy {
      margin: 6px 0 0;
      font-size: 12px;
      line-height: 1.5;
      color: #64748b;
      font-weight: 700;
    }

    @media (max-width: 760px) {
      .rda-stats,
      .rda-meta,
      .rda-actions {
        grid-template-columns: 1fr;
      }

      .rda-count {
        text-align: left;
      }
    }
  </style>

  <div class="rda-page">
    <header class="rda-hero">
      <span class="rda-chip">Phone Mode</span>
      <h1 class="rda-title">Regional Director Approvals</h1>
      <p class="rda-copy">
        Quick mobile review for endorsed certificate packages. Participant previews are loaded here so approval is easier to complete from the phone.
      </p>

      <div class="rda-hero-actions">
        <a href="{{ route('admin.certs.index') }}" class="rda-btn rda-btn-white">Desktop Mode</a>
        <a href="{{ route('admin.certs.create') }}" class="rda-btn rda-btn-light">Create (RD)</a>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="rda-btn rda-btn-danger">Logout</button>
        </form>
      </div>
    </header>

    @if (session('success'))
      <div class="rda-alert-wrap">
        <div class="rda-alert">{{ session('success') }}</div>
      </div>
    @endif

    @if ($errors->any())
      <div class="rda-alert-wrap">
        <div class="rda-alert rda-alert-error">
          <div>Unable to complete action:</div>
          <ul class="rda-alert-list">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      </div>
    @endif

    <section class="rda-stats">
      <article class="rda-stat">
        <p class="rda-stat-label">Pending Queue</p>
        <div class="rda-stat-value">{{ number_format($pendingCount ?? 0) }}</div>
        <p class="rda-stat-note">Endorsed packages waiting now</p>
      </article>
      <article class="rda-stat">
        <p class="rda-stat-label">Approved Today</p>
        <div class="rda-stat-value">{{ number_format($approvedToday ?? 0) }}</div>
        <p class="rda-stat-note">Generated from phone or desktop</p>
      </article>
      <article class="rda-stat">
        <p class="rda-stat-label">Rejected Today</p>
        <div class="rda-stat-value">{{ number_format($rejectedToday ?? 0) }}</div>
        <p class="rda-stat-note">Packages sent back for changes</p>
      </article>
    </section>

    <section class="rda-section">
      <div class="rda-section-head">
        <div>
          <h2>Approve Endorsed Packages</h2>
          <p>Large buttons and quick participant preview for phone use.</p>
        </div>
      </div>

      <div class="rda-list">
        @forelse ($pendingEndorsements as $endorsement)
          @php
            $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
          @endphp
          <article class="rda-card">
            <div class="rda-card-body">
              <div class="rda-card-head">
                <div>
                  <span class="rda-badge rda-badge-pending">Pending Approval</span>
                  <h2 class="rda-training">{{ $payload['training_title'] ?? 'Untitled Training' }}</h2>
                  <p class="rda-office">{{ $payload['issuing_office'] ?? 'Unspecified Office' }}</p>
                </div>
                <div class="rda-count">{{ number_format((int) ($endorsement->participants_count ?? 0)) }} participants</div>
              </div>

              <dl class="rda-meta">
                <div>
                  <dt>Date</dt>
                  <dd>{{ $endorsement->date_range }}</dd>
                </div>
                <div>
                  <dt>Submitted By</dt>
                  <dd>{{ $endorsement->submitter?->name ?? ('User #' . ($endorsement->submitted_by ?? 'N/A')) }}</dd>
                </div>
                <div>
                  <dt>Recipient Type</dt>
                  <dd>{{ $payload['recipient_type'] ?? '-' }}</dd>
                </div>
                <div>
                  <dt>First Participant</dt>
                  <dd>{{ $endorsement->first_participant_name ?? 'Unavailable' }}</dd>
                </div>
              </dl>

              @if (!empty($endorsement->participants_preview))
                <details class="rda-details" open>
                  <summary>Participants Preview</summary>
                  <div class="rda-pills">
                    @foreach ($endorsement->participants_preview as $participantName)
                      <span class="rda-pill">{{ $participantName }}</span>
                    @endforeach
                  </div>
                  <p class="rda-note">
                    @if (($endorsement->participants_remaining_count ?? 0) > 0)
                      +{{ number_format((int) $endorsement->participants_remaining_count) }} more participants in the uploaded file.
                    @endif
                    Preview is loaded here for quicker phone review.
                  </p>
                </details>
              @elseif (!empty($endorsement->participants_preview_error))
                <div class="rda-warning">{{ $endorsement->participants_preview_error }}</div>
              @endif

              <div class="rda-actions">
                @if (!empty($endorsement->template_pdf_path))
                  <a class="rda-action rda-action-secondary" target="_blank" rel="noopener" href="{{ route('admin.certs.endorsements.template.view', ['id' => $endorsement->id]) }}">
                    Uploaded PDF
                  </a>
                @endif

                @if (!empty($endorsement->template_pdf_path) && !empty($endorsement->participants_file_path))
                  <a class="rda-action rda-action-secondary" target="_blank" rel="noopener" href="{{ route('admin.certs.endorsements.preview', ['id' => $endorsement->id]) }}">
                    Preview PDF
                  </a>
                @endif

                <form method="POST" action="{{ route('admin.certs.endorsements.approve', ['id' => $endorsement->id]) }}" onsubmit="return confirm('Are you sure you want to approve this certificate package and generate the certificates?')">
                  @csrf
                  <button type="submit" class="rda-action rda-action-primary" {{ !($endorsement->participants_review_ready ?? false) ? 'disabled' : '' }}>
                    Approve &amp; Generate
                  </button>
                </form>

                @if (!empty($endorsement->participants_file_path))
                  <a class="rda-action rda-action-secondary" href="{{ route('admin.certs.endorsements.participants.download', ['id' => $endorsement->id]) }}">
                    Download Participants
                  </a>
                @else
                  <button type="button" class="rda-action rda-action-secondary" disabled>No Participants File</button>
                @endif

                <form method="POST" action="{{ route('admin.certs.endorsements.reject', ['id' => $endorsement->id]) }}" onsubmit="return confirm('Reject this endorsement request?')">
                  @csrf
                  <button type="submit" class="rda-action rda-action-danger">Reject</button>
                </form>
              </div>
            </div>
          </article>
        @empty
          <div class="rda-empty">
            No endorsed certificate packages are waiting right now.
          </div>
        @endforelse
      </div>
    </section>

    @if ($recentDecisions->isNotEmpty())
      <section class="rda-section">
        <div class="rda-section-head">
          <div>
            <h2>Recent Decisions</h2>
            <p>Latest approved and rejected endorsement packages.</p>
          </div>
        </div>

        <div class="rda-recent-grid">
          @foreach ($recentDecisions as $endorsement)
            @php
              $payload = is_array($endorsement->payload) ? $endorsement->payload : [];
              $statusClass = $endorsement->status === 'rd_approved' ? 'rda-badge-approved' : 'rda-badge-rejected';
              $statusLabel = $endorsement->status === 'rd_approved' ? 'Approved' : 'Rejected';
            @endphp
            <article class="rda-recent-card">
              <span class="rda-badge {{ $statusClass }}">{{ $statusLabel }}</span>
              <h3 class="rda-recent-title">{{ $payload['training_title'] ?? 'Untitled Training' }}</h3>
              <p class="rda-recent-copy">
                {{ $endorsement->date_range }} · {{ $payload['recipient_type'] ?? '-' }} ·
                {{ $endorsement->submitter?->name ?? ('User #' . ($endorsement->submitted_by ?? 'N/A')) }}
              </p>
            </article>
          @endforeach
        </div>
      </section>
    @endif
  </div>
</x-admin-layout>
