<x-admin-layout title="Endorsed Certificates">
  @php
    $deliveryStatus = \App\Support\CertificateDeliveryStatus::class;
    $submitterName = $endorsement->submitter?->name ?? ('User #' . ($endorsement->submitted_by ?? 'N/A'));
    $isApproved = $endorsement->status === \App\Models\CertificateEndorsement::STATUS_RD_APPROVED;
    $isRejected = $endorsement->status === \App\Models\CertificateEndorsement::STATUS_RD_REJECTED;
    $hasCertificates = $totalCount > 0;
    $submittedAt = $endorsement->created_at?->copy()->timezone('Asia/Manila');
    $decidedAt = ($isApproved ? $endorsement->rd_approved_at : ($isRejected ? $endorsement->rd_rejected_at : null))?->copy()->timezone('Asia/Manila');
    $listRoute = fn (array $params = []) => route('admin.certs.endorsements.show', array_filter(array_merge([
      'id' => $endorsement->id,
      'q' => $search !== '' ? $search : null,
      'problems' => $showProblemsOnly ? 1 : null,
    ], $params), fn ($value) => $value !== null));
  @endphp

  <style>
    .dl-page {
      width: 100%;
      margin: 0 auto;
      max-width: 1400px;
      color: #0f172a;
      font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    }

    .dl-shell {
      background: #fff;
      border: 1px solid #dbe5ef;
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 22px 60px rgba(15, 23, 42, 0.12);
    }

    .dl-hero {
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

    .dl-back {
      display: inline-block;
      margin-bottom: 10px;
      color: #dbeafe;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
    }

    .dl-back:hover {
      color: #fff;
    }

    .dl-chip {
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

    .dl-title {
      margin: 10px 0 0;
      font-size: clamp(24px, 2.6vw, 34px);
      line-height: 1.1;
      letter-spacing: -0.02em;
      font-weight: 900;
    }

    .dl-subtitle {
      margin: 8px 0 0;
      color: #dbeafe;
      font-size: 14px;
      font-weight: 600;
    }

    .dl-hero-actions {
      position: relative;
      display: flex;
      align-items: flex-start;
      justify-content: flex-end;
      z-index: 20;
    }

    .dl-body {
      padding: 24px;
    }

    .dl-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 12px;
    }

    .dl-stat {
      border: 1px solid #dbe5ef;
      border-radius: 16px;
      padding: 14px 16px;
      background: #f8fafc;
    }

    .dl-stat strong {
      display: block;
      font-size: 28px;
      font-weight: 900;
      line-height: 1.1;
      margin-bottom: 6px;
    }

    .dl-note {
      margin: 16px 0 0;
      padding: 10px 12px;
      border-radius: 12px;
      border: 1px solid #fde68a;
      background: #fffbeb;
      color: #92400e;
      font-size: 13px;
      font-weight: 600;
      line-height: 1.45;
    }

    .dl-tabs {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin: 20px 0 12px;
    }

    .dl-tab {
      text-decoration: none;
      border: 1px solid #cbd5e1;
      border-radius: 999px;
      padding: 7px 14px;
      font-size: 13px;
      font-weight: 800;
      color: #334155;
      background: #fff;
    }

    .dl-tab.active {
      color: #fff;
      background: #0f4f8c;
      border-color: #0f4f8c;
    }

    .dl-table-scroll {
      overflow-x: auto;
      border: 1px solid #dbe5ef;
      border-radius: 16px;
    }

    .dl-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }

    .dl-table th {
      text-align: left;
      padding: 12px 14px;
      background: #f1f5f9;
      color: #475569;
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      white-space: nowrap;
    }

    .dl-table td {
      padding: 12px 14px;
      border-top: 1px solid #e2e8f0;
      vertical-align: top;
    }

    .dl-name {
      font-weight: 800;
    }

    .dl-code {
      display: block;
      color: #64748b;
      font-size: 12px;
      font-weight: 600;
    }

    .dl-muted {
      color: #94a3b8;
    }

    .dl-reason {
      max-width: 420px;
      color: #334155;
      line-height: 1.4;
    }

    .dl-nowrap {
      white-space: nowrap;
    }

    .dl-empty {
      padding: 32px 14px;
      text-align: center;
      color: #64748b;
      font-weight: 600;
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

    .dl-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 24px;
      margin: 0 0 18px;
      color: #475569;
      font-size: 14px;
      font-weight: 600;
    }

    .dl-meta strong {
      color: #0f172a;
    }

    .dl-reject {
      margin: 0 0 18px;
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid #fda4af;
      background: #fff1f2;
      color: #9f1239;
      font-size: 14px;
      line-height: 1.45;
    }

    .dl-reject p {
      margin: 4px 0 0;
      white-space: pre-line;
    }

    .dl-section-title {
      margin: 24px 0 10px;
      font-size: 18px;
      font-weight: 900;
    }

    .dl-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      margin: 20px 0 12px;
    }

    .dl-toolbar .dl-tabs {
      margin: 0;
    }

    .dl-search {
      display: flex;
      gap: 8px;
      flex: 1 1 260px;
      max-width: 420px;
    }

    .dl-search input {
      flex: 1;
      min-width: 0;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      padding: 8px 12px;
      font-size: 14px;
    }

    .dl-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      border: 1px solid #0f4f8c;
      border-radius: 10px;
      padding: 7px 12px;
      font-size: 13px;
      font-weight: 800;
      color: #0f4f8c;
      background: #fff;
      white-space: nowrap;
      cursor: pointer;
    }

    .dl-btn:hover {
      color: #fff;
      background: #0f4f8c;
    }

    .dl-index {
      width: 48px;
      color: #94a3b8;
      font-weight: 700;
    }

    .status-rd-approved {
      color: #047857;
      background: #ecfdf5;
      border-color: #6ee7b7;
    }

    .status-rd-rejected {
      color: #be123c;
      background: #fff1f2;
      border-color: #fda4af;
    }
  </style>

  <div class="dl-page">
    <div class="dl-shell">
      <header class="dl-hero">
        <div>
          <a class="dl-back" href="{{ route('admin.certs.index', ['group' => 'endorsements']) }}">&larr; My Certificate Endorsements</a>
          <div><span class="dl-chip">Endorsed certificates</span></div>
          <h1 class="dl-title">{{ $trainingTitle }}</h1>
          <p class="dl-subtitle">
            {{ $dateRange }}@if ($issuingOffice !== '') &middot; {{ $issuingOffice }}@endif &middot; Endorsed by {{ $submitterName }}
          </p>
        </div>

        <div class="dl-hero-actions">
          @include('admin.partials.action-menu', [
            'menuId' => 'cert-delivery-menu',
            'menuVariant' => 'dark',
            'pendingEndorsementsCount' => $pendingEndorsementsCount,
          ])
        </div>
      </header>

      <div class="dl-body">
        <div class="dl-meta">
          <span>Status:
            @if ($isApproved)
              <span class="cert-status status-rd-approved">Approved</span>
            @elseif ($isRejected)
              <span class="cert-status status-rd-rejected">Rejected</span>
            @else
              <span class="cert-status status-endorsed">Pending Approval</span>
            @endif
          </span>
          <span>Submitted: <strong>{{ $submittedAt ? $submittedAt->format('M j, Y g:ia') : '-' }}</strong></span>
          @if ($decidedAt)
            <span>{{ $isApproved ? 'Approved' : 'Rejected' }}: <strong>{{ $decidedAt->format('M j, Y g:ia') }}</strong></span>
          @endif
          <span>Participants: <strong>{{ number_format($hasCertificates ? $totalCount : $fileNamesTotal) }}</strong></span>
        </div>

        @if ($isRejected && $endorsement->rejection_reason)
          <div class="dl-reject">
            <strong>Reason for rejection</strong>
            <p>{{ $endorsement->rejection_reason }}</p>
          </div>
        @endif

        @if ($hasCertificates)
          <section class="dl-summary">
            @foreach ($deliveryStatus::outcomes() as $outcome)
              @if (($counts[$outcome] ?? 0) > 0)
                <article class="dl-stat">
                  <strong>{{ number_format($counts[$outcome]) }}</strong>
                  <span class="cert-status {{ $deliveryStatus::badgeClass($outcome) }}">{{ $deliveryStatus::label($outcome) }}</span>
                </article>
              @endif
            @endforeach
          </section>

          <p class="dl-note">
            "Sent" means the mail server accepted the email. It can still land in spam or be blocked afterwards,
            so if a participant says they did not receive it, ask them to check spam and let the administrator know.
          </p>

          <div class="dl-toolbar">
            <nav class="dl-tabs">
              <a class="dl-tab {{ $showProblemsOnly ? '' : 'active' }}" href="{{ $listRoute(['problems' => null]) }}">
                All ({{ number_format($totalCount) }})
              </a>
              <a class="dl-tab {{ $showProblemsOnly ? 'active' : '' }}" href="{{ $listRoute(['problems' => 1]) }}">
                Needs attention ({{ number_format($problemCount) }})
              </a>
            </nav>
            @include('admin.certificates.partials.endorsement-name-search')
          </div>

          <div class="dl-table-scroll">
            <table class="dl-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Participant</th>
                  <th>Certificate</th>
                  <th>Email delivery</th>
                  <th>What happened</th>
                  <th>Last update</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($rows as $row)
                  @php
                    $certificate = $row['certificate'];
                    $lastUpdate = $row['last_update']?->copy()->timezone('Asia/Manila');
                    $certificateStatus = (string) ($certificate->status ?? '');
                  @endphp
                  <tr>
                    <td class="dl-index">{{ $loop->iteration }}</td>
                    <td>
                      <span class="dl-name">{{ $certificate->participant_name }}</span>
                      <span class="dl-code">{{ $certificate->certificate_code }}</span>
                    </td>
                    <td>
                      @if ($certificate->stamped_pdf_path)
                        <a class="dl-btn" href="{{ route('admin.certs.endorsements.certificate', ['id' => $endorsement->id, 'certificateId' => $certificate->id]) }}" target="_blank" rel="noopener">View PDF</a>
                      @else
                        <span class="dl-muted">No PDF</span>
                      @endif
                      @if ($certificateStatus !== '' && $certificateStatus !== 'valid')
                        <span class="cert-status {{ in_array($certificateStatus, ['revoked', 'invalid'], true) ? 'status-' . $certificateStatus : 'status-default' }}">{{ ucfirst($certificateStatus) }}</span>
                      @endif
                    </td>
                    <td>
                      <span class="cert-status {{ $deliveryStatus::badgeClass($row['outcome']) }}">{{ $deliveryStatus::label($row['outcome']) }}</span>
                    </td>
                    <td class="dl-reason">
                      @if ($row['reason'])
                        {{ $row['reason'] }}
                      @else
                        <span class="dl-muted">&mdash;</span>
                      @endif
                    </td>
                    <td class="dl-nowrap">{{ $lastUpdate ? $lastUpdate->format('M j, Y g:ia') : '-' }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="dl-empty">
                      @if ($search !== '')
                        No participant matches "{{ $search }}".
                      @else
                        Nothing needs attention. Every certificate in this package was sent or is waiting to send.
                      @endif
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        @else
          <p class="dl-note">
            @if ($isApproved)
              Certificates for this package were issued before they could be matched to it, so they cannot be opened from here yet.
              The names below are from the participant list you uploaded.
            @elseif ($isRejected)
              This package was not approved, so no certificates were issued. The names below are from the participant list you uploaded.
            @else
              This package is waiting for the Regional Director's approval, so certificates have not been issued yet.
              The names below are from the participant list you uploaded.
            @endif
          </p>

          @if ($fileNamesError)
            <p class="dl-empty">{{ $fileNamesError }}</p>
          @else
            <div class="dl-toolbar">
              <h2 class="dl-section-title" style="margin: 0;">Participants ({{ number_format($fileNamesTotal) }})</h2>
              @include('admin.certificates.partials.endorsement-name-search')
            </div>

            <div class="dl-table-scroll">
              <table class="dl-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Participant</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($fileNames as $name)
                    <tr>
                      <td class="dl-index">{{ $loop->iteration }}</td>
                      <td><span class="dl-name">{{ $name }}</span></td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="2" class="dl-empty">
                        @if ($search !== '')
                          No participant matches "{{ $search }}".
                        @else
                          No participant names were found in the uploaded list.
                        @endif
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          @endif
        @endif
      </div>
    </div>
  </div>
</x-admin-layout>
