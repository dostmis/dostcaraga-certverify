<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificate Authenticity Verification</title>
  <style>
    :root{
      --bg1:#f7fafc;
      --bg2:#eef2ff;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --line:#e5e7eb;

      --valid:#16a34a;
      --valid-bg:#dcfce7;

      --invalid:#dc2626;
      --invalid-bg:#fee2e2;

      --revoked:#b45309;
      --revoked-bg:#ffedd5;

      --brand:#0b57d0;
      --shadow: 0 18px 45px rgba(15, 23, 42, 0.10);
    }

    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Helvetica Neue", "Noto Sans", "Liberation Sans", sans-serif;
      color:var(--text);
      background: radial-gradient(1000px 600px at 20% 0%, var(--bg2), transparent 60%),
                  radial-gradient(900px 500px at 80% 10%, #e0f2fe, transparent 55%),
                  linear-gradient(180deg, var(--bg1), #f8fafc);
      padding: 28px 18px 40px;
    }

    .wrap{ max-width: 920px; margin: 0 auto; }
    .topbar{
      display:flex; align-items:center; justify-content:space-between; gap:14px;
      margin-bottom: 14px;
    }

    .brand{
      display:flex; align-items:center; gap:12px;
      min-width: 260px;
    }
    .brand img{
      width: 54px; height: 54px; object-fit: contain;
      border-radius: 12px;
      background: #fff;
      border: 1px solid var(--line);
      padding: 6px;
    }
    .brand h1{
      margin:0; font-size: 18px; letter-spacing: .2px;
      font-weight: 900;
    }
    .brand p{
      margin:2px 0 0;
      font-size: 12px; color: var(--muted);
      font-weight: 600;
    }

    .chip{
      display:inline-flex; align-items:center; gap:8px;
      padding: 9px 12px;
      border-radius: 999px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.7);
      backdrop-filter: blur(8px);
      font-size: 12px;
      color: var(--muted);
      font-weight: 700;
      white-space: nowrap;
    }

    .card{
      background: var(--card);
      border: 1px solid rgba(229,231,235,.9);
      border-radius: 18px;
      box-shadow: var(--shadow);
      overflow:hidden;
    }

    .header{
      padding: 18px 18px 14px;
      border-bottom: 1px solid var(--line);
      background: linear-gradient(180deg, rgba(11,87,208,.08), rgba(255,255,255,0));
    }

    .title{
      margin:0;
      font-size: 15px;
      letter-spacing: .9px;
      text-transform: uppercase;
      font-weight: 900;
      display:flex;
      align-items:center;
      gap:10px;
    }

    .title .dot{
      width: 10px; height: 10px;
      border-radius: 999px;
      background: var(--brand);
      box-shadow: 0 0 0 5px rgba(11,87,208,.12);
      flex: 0 0 auto;
    }

    .sub{
      margin:8px 0 0;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.5;
      font-weight: 600;
    }

    .content{
      padding: 18px;
      display:grid;
      grid-template-columns: 1.25fr .75fr;
      gap: 14px;
    }

    @media (max-width: 820px){
      .content{ grid-template-columns: 1fr; }
      .chip{ display:none; }
    }

    .panel{
      border: 1px solid var(--line);
      border-radius: 16px;
      overflow:hidden;
      background:#fff;
    }

    .rows{ padding: 4px 14px; }
    .row{
      display:grid;
      grid-template-columns: 190px 1fr;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid var(--line);
    }
    .row:last-child{ border-bottom:none; }
    .label{
      color: var(--muted);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .2px;
    }
    .value{
      font-size: 13px;
      font-weight: 900;
      color: #0b1220;
      word-break: break-word;
    }
    .mono{
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      letter-spacing: .4px;
      font-size: 12px;
    }

    .statusCard{
      border-radius: 16px;
      padding: 16px;
      border: 1px solid var(--line);
      background:#fff;
      display:flex;
      flex-direction:column;
      gap: 10px;
      position:relative;
      overflow:hidden;
    }

    .statusBanner{
      display:flex; align-items:center; gap: 10px;
      padding: 12px 12px;
      border-radius: 14px;
      font-weight: 1000;
      letter-spacing: .3px;
      border: 1px solid rgba(0,0,0,.06);
    }
    .statusIcon{
      width: 36px; height: 36px;
      display:grid; place-items:center;
      border-radius: 12px;
      font-size: 18px;
      font-weight: 900;
    }
    .statusText{
      display:flex;
      flex-direction:column;
      gap: 2px;
      line-height: 1.1;
    }
    .statusText .big{ font-size: 14px; }
    .statusText .small{ font-size: 12px; opacity:.9; font-weight: 800; }

    .meta{
      display:grid;
      gap: 8px;
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
    }
    .meta .item{
      display:flex; justify-content:space-between; gap:10px;
      padding: 10px 12px;
      border: 1px dashed rgba(100,116,139,.35);
      border-radius: 14px;
      background: rgba(248,250,252,.7);
    }
    .meta b{ color:#111827; }

    .downloadWrap{
      display:flex;
      flex-direction:column;
      gap:6px;
      margin-top: 10px;
    }
    .downloadBtn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      padding: 12px 14px;
      border-radius: 12px;
      background: var(--brand);
      color:#fff;
      text-decoration:none;
      font-weight: 800;
      font-size: 13px;
      letter-spacing: .2px;
      border: 1px solid rgba(11,87,208,.2);
      box-shadow: 0 12px 24px rgba(11,87,208,.22);
    }
    .downloadBtn:hover{ filter: brightness(0.98); }
    .downloadHint{
      font-size: 11px;
      color: var(--muted);
      font-weight: 700;
      text-align:center;
    }

    .foot{
      padding: 14px 18px 18px;
      border-top: 1px solid var(--line);
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
      background: linear-gradient(180deg, rgba(248,250,252,.6), rgba(255,255,255,1));
    }

    /* Status themes */
    .valid .statusBanner{ background: var(--valid-bg); color: #0b3d1b; }
    .valid .statusIcon{ background: rgba(22,163,74,.18); color: var(--valid); }

    .invalid .statusBanner{ background: var(--invalid-bg); color:#5f0b0b; }
    .invalid .statusIcon{ background: rgba(220,38,38,.16); color: var(--invalid); }

    .revoked .statusBanner{ background: var(--revoked-bg); color:#5a2c00; }
    .revoked .statusIcon{ background: rgba(180,83,9,.18); color: var(--revoked); }

    /* Print-friendly */
    @media print{
      body{ background:#fff; padding:0; }
      .chip{ display:none; }
      .card{ box-shadow:none; border-radius:0; border:none; }
    }
  </style>
</head>

<body>
  <div class="wrap">

    <div class="topbar">
      <div class="brand">
        {{-- Replace with your actual logo path --}}
        <img src="{{ asset('images/dosttt.png') }}" alt="DOST Logo">
        <div>
          <h1>DOST CARAGA</h1>
          <p>OneDOST4U: Solutions and Opportunities for All</p>
        </div>
      </div>

      <div class="chip">
        <span>🔒 Token-based verification</span>
        <span>•</span>
        <span>{{ now()->timezone('Asia/Manila')->format('M d, Y h:i A') }}</span>
      </div>
    </div>

   

      @php
        $status = null;
        if ($found && $cert) {
          $status = $cert->status; // valid | invalid | revoked
        }
        $boxClass = $found && $cert ? $status : 'invalid';

        $statusLabel = 'INVALID';
        $statusDesc  = 'Certificate not found or token is invalid.';
        $statusIcon  = '✖';

        if ($found && $cert) {
          if ($cert->status === 'valid') {
            $statusLabel = 'VALID';
            $statusDesc  = 'VERIFIED — Record matched successfully.';
            $statusIcon  = '✔';
          } elseif ($cert->status === 'revoked') {
            $statusLabel = 'REVOKED';
            $statusDesc  = 'NOT VERIFIED — Certificate has been revoked.';
            $statusIcon  = '⚠';
          } else {
            $statusLabel = 'INVALID';
            $statusDesc  = 'NOT VERIFIED — Record marked invalid.';
            $statusIcon  = '✖';
          }
        }
      @endphp

      <div class="content">
        {{-- LEFT: DETAILS --}}
        <div class="panel">
          <div class="{{ $boxClass }}" style="padding:14px 14px 0;">
            <div class="statusBanner">
              <div class="statusIcon">{{ $statusIcon }}</div>
              <div class="statusText">
                <div class="big">{{ $statusLabel }}</div>
                <div class="small">{{ $statusDesc }}</div>
              </div>
            </div>
          </div>

          <div class="rows">
            <div class="row">
              <div class="label">Document Code</div>
              <div class="value mono">{{ $code ?? '—' }}</div>
            </div>

            @if($found && $cert)
              <div class="row">
                <div class="label">Name</div>
                <div class="value">{{ $cert->participant_name }}</div>
              </div>
              <div class="row">
                <div class="label">Title</div>
                <div class="value">{{ $cert->training_title }}</div>
              </div>
              <div class="row">
                <div class="label">Activity Type</div>
                <div class="value">{{ $cert->activity_type ?: '—' }}</div>
              </div>
              <div class="row">
                <div class="label">Document Type</div>
                <div class="value">{{ $cert->certificate_type ?: '—' }}</div>
              </div>
              <div class="row">
                <div class="label">Date</div>
                <div class="value">
                  @php
                    $dateFrom = $cert->training_date?->format('F d, Y');
                    $dateTo = $cert->training_date_to?->format('F d, Y') ?? $dateFrom;
                  @endphp
                  {{ $dateFrom === $dateTo ? $dateFrom : ($dateFrom . ' to ' . $dateTo) }}
                </div>
              </div>
              <div class="row">
                @php
                  $activityTypeLabel = trim((string) ($cert->activity_type ?? ''));
                  $hoursLabel = $activityTypeLabel !== ''
                    ? ('Number of ' . $activityTypeLabel . ' Hours')
                    : 'Number of Training Hours';
                @endphp
                <div class="label">{{ $hoursLabel }}</div>
                <div class="value">
                  @if(!is_null($cert->number_of_training_hours))
                    {{ (int) $cert->number_of_training_hours }} {{ (int) $cert->number_of_training_hours === 1 ? 'hour' : 'hours' }}
                  @else
                    —
                  @endif
                </div>
              </div>
              <div class="row">
                <div class="label">Issuing Office/Unit</div>
                <div class="value">{{ $cert->issuing_office }}</div>
              </div>
            @else
              <div class="row">
                <div class="label">Name</div>
                <div class="value">INVALID CERTIFICATE</div>
              </div>
              <div class="row">
                <div class="label">Title</div>
                <div class="value">—</div>
              </div>
              <div class="row">
                <div class="label">Activity Type</div>
                <div class="value">—</div>
              </div>
              <div class="row">
                <div class="label">Document Type</div>
                <div class="value">—</div>
              </div>
              <div class="row">
                <div class="label">Date</div>
                <div class="value">—</div>
              </div>
              <div class="row">
                <div class="label">Number of Activity Hours</div>
                <div class="value">—</div>
              </div>
              <div class="row">
                <div class="label">Issuing Office/Unit</div>
                <div class="value">—</div>
              </div>
            @endif
          </div>
        </div>

        {{-- RIGHT: STATUS + META --}}
        <div class="statusCard {{ $boxClass }}">
          <div class="meta">

            <div class="item">
              <span>Verified At</span>
              <b>
                {{ ($found && $cert && $cert->created_at)
                    ? $cert->created_at->timezone('Asia/Manila')->format('M d, Y h:i A')
                    : 'N/A' }}
              </b>
            </div>

            @if(($found && $cert) && !$cert->isValid() && !empty($cert->remarks))
              <div class="item">
                <span>Remarks</span>
                <b>{{ $cert->remarks }}</b>
              </div>
            @endif

            @if(!$found && !empty($reason))
              <div class="item">
                <span>Note</span>
                <b>{{ $reason }}</b>
              </div>
            @endif
          </div>

          @if($found && $cert && $cert->isValid() && !empty($cert->stamped_pdf_path))
            <div class="downloadWrap">
              <a class="downloadBtn" href="{{ route('cert.download', ['t' => $cert->public_token]) }}">
                ⬇ Download Certificate (PDF)
              </a>
              <div class="downloadHint">For the certificate owner only.</div>
            </div>
          @endif
        </div>
      </div>

      <div class="foot" style="text-align:center;">
        If you suspect tampering or mismatch, please contact the issuing office shown above and provide the document code for validation.
      </div>
    </div>
  </div>
</body>
</html>
