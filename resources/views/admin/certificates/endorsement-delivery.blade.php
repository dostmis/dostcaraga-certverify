<x-admin-layout title="Certificate Delivery">
  @php
    $deliveryStatus = \App\Support\CertificateDeliveryStatus::class;
    $submitterName = $endorsement->submitter?->name ?? ('User #' . ($endorsement->submitted_by ?? 'N/A'));
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
  </style>

  <div class="dl-page">
    <div class="dl-shell">
      <header class="dl-hero">
        <div>
          <a class="dl-back" href="{{ route('admin.certs.index', ['group' => 'endorsements']) }}">&larr; My Certificate Endorsements</a>
          <div><span class="dl-chip">Certificate delivery</span></div>
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
        @if ($endorsement->status !== \App\Models\CertificateEndorsement::STATUS_RD_APPROVED)
          <p class="dl-empty">This package has not been approved yet, so no certificates have been sent.</p>
        @elseif ($totalCount === 0)
          <p class="dl-empty">Delivery for this package is not tracked yet. It was approved before tracking was added.</p>
        @else
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

          <nav class="dl-tabs">
            <a class="dl-tab {{ $showProblemsOnly ? '' : 'active' }}" href="{{ route('admin.certs.endorsements.delivery', ['id' => $endorsement->id]) }}">
              All ({{ number_format($totalCount) }})
            </a>
            <a class="dl-tab {{ $showProblemsOnly ? 'active' : '' }}" href="{{ route('admin.certs.endorsements.delivery', ['id' => $endorsement->id, 'problems' => 1]) }}">
              Needs attention ({{ number_format($problemCount) }})
            </a>
          </nav>

          <div class="dl-table-scroll">
            <table class="dl-table">
              <thead>
                <tr>
                  <th>Participant</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>What happened</th>
                  <th>Last update</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($rows as $row)
                  @php
                    $certificate = $row['certificate'];
                    $lastUpdate = $row['last_update']?->copy()->timezone('Asia/Manila');
                  @endphp
                  <tr>
                    <td>
                      <span class="dl-name">{{ $certificate->participant_name }}</span>
                      <span class="dl-code">{{ $certificate->certificate_code }}</span>
                    </td>
                    <td>
                      @if (trim((string) $certificate->email) !== '')
                        {{ $certificate->email }}
                      @else
                        <span class="dl-muted">No email</span>
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
                    <td colspan="5" class="dl-empty">Nothing needs attention. Every certificate in this package was sent or is waiting to send.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
</x-admin-layout>
