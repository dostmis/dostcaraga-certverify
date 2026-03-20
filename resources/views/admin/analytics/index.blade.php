<x-admin-layout title="Analytics">
  <style>
    .an-page {
      width: 100%;
      max-width: 1700px;
      margin: 0 auto;
      color: #0f172a;
      font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    }

    .an-shell {
      background: #fff;
      border: 1px solid #dbe5ef;
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 22px 60px rgba(15, 23, 42, 0.12);
    }

    .an-head {
      border-bottom: 1px solid #dbe5ef;
      background: linear-gradient(135deg, #f6fbff 0%, #edf6ff 52%, #f0fdfa 100%);
      padding: 22px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 14px;
      flex-wrap: wrap;
    }

    .an-head-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .an-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: #0f172a;
      color: #fff;
      display: grid;
      place-items: center;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.2);
      flex: 0 0 48px;
    }

    .an-title {
      margin: 0;
      font-size: 32px;
      letter-spacing: -0.02em;
      line-height: 1.05;
      font-weight: 900;
      color: #0f172a;
    }

    .an-sub {
      margin: 8px 0 0;
      font-size: 14px;
      color: #64748b;
      font-weight: 600;
    }

    .an-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .an-btn {
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
      background: #fff;
      color: #334155;
    }

    .an-btn:hover {
      filter: brightness(0.98);
    }

    .an-btn-outline {
      border-color: #cbd5e1;
    }

    .an-btn-dark {
      color: #fff;
      background: #0f172a;
      border-color: #0f172a;
    }

    .an-body {
      padding: 22px;
    }

    .an-kpis {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 10px;
    }

    .an-card {
      border: 1px solid #dbe5ef;
      border-radius: 16px;
      padding: 12px;
      background: #fff;
    }

    .an-card h3 {
      margin: 0;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      font-weight: 900;
      color: #64748b;
    }

    .an-card strong {
      display: block;
      margin-top: 8px;
      font-size: 34px;
      line-height: 1;
      font-weight: 900;
      color: #0f172a;
    }

    .an-card p {
      margin: 6px 0 0;
      font-size: 12px;
      color: #64748b;
      font-weight: 600;
    }

    .an-card.green {
      border-color: #bbf7d0;
      background: linear-gradient(145deg, #ecfdf5 0%, #fff 100%);
    }

    .an-card.green h3,
    .an-card.green p {
      color: #15803d;
    }

    .an-card.green strong {
      color: #14532d;
    }

    .an-card.sky {
      border-color: #bae6fd;
      background: linear-gradient(145deg, #f0f9ff 0%, #fff 100%);
    }

    .an-card.amber {
      border-color: #fcd34d;
      background: linear-gradient(145deg, #fffbeb 0%, #fff 100%);
    }

    .an-card.amber h3,
    .an-card.amber p {
      color: #92400e;
    }

    .an-card.amber strong {
      font-size: 24px;
      line-height: 1.1;
    }

    .an-card.violet {
      border-color: #c4b5fd;
      background: linear-gradient(145deg, #f5f3ff 0%, #fff 100%);
    }

    .an-card.violet h3,
    .an-card.violet p {
      color: #5b21b6;
    }

    .an-card.violet strong {
      font-size: 24px;
      line-height: 1.1;
      color: #4c1d95;
    }

    .an-gauge {
      margin-top: 10px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .an-gauge-bar {
      flex: 1;
      height: 8px;
      border-radius: 999px;
      overflow: hidden;
      background: #e2e8f0;
    }

    .an-gauge-fill {
      height: 8px;
      background: #0284c7;
    }

    .an-gauge-text {
      font-size: 12px;
      color: #475569;
      font-weight: 700;
      white-space: nowrap;
    }

    .an-grid3 {
      margin-top: 10px;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .an-chart-card {
      border: 1px solid #dbe5ef;
      border-radius: 16px;
      background: #fff;
      padding: 12px;
    }

    .an-chart-title {
      margin: 0;
      font-size: 12px;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #334155;
    }

    .an-chart-box {
      margin-top: 12px;
      height: 245px;
    }

    .an-grid2 {
      margin-top: 10px;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .an-list {
      margin-top: 10px;
      border-top: 1px solid #e2e8f0;
    }

    .an-item {
      border-top: 1px solid #e2e8f0;
      padding: 10px 0;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 10px;
    }

    .an-name {
      margin: 0;
      font-size: 14px;
      font-weight: 900;
      color: #0f172a;
    }

    .an-train {
      margin: 4px 0 0;
      font-size: 12px;
      color: #64748b;
      font-weight: 600;
    }

    .an-time {
      margin: 4px 0 0;
      font-size: 11px;
      color: #94a3b8;
      font-weight: 600;
    }

    .an-province {
      font-size: 11px;
      color: #475569;
      font-weight: 800;
      white-space: nowrap;
    }

    .an-form-wrap {
      margin-top: 10px;
      border-top: 1px solid #dbe5ef;
      padding-top: 14px;
    }

    .an-form {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 10px;
    }

    .an-field {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .an-field label {
      font-size: 11px;
      font-weight: 800;
      color: #475569;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    .an-input,
    .an-select {
      border: 1px solid #c7d5e4;
      border-radius: 10px;
      background: #fff;
      color: #0f172a;
      font-size: 14px;
      font-weight: 600;
      padding: 10px 11px;
      outline: none;
    }

    .an-input:focus,
    .an-select:focus {
      border-color: #4c8cc6;
      box-shadow: 0 0 0 3px rgba(76, 140, 198, 0.14);
    }

    .an-field.action {
      justify-content: flex-end;
    }

    .an-submit {
      border: 1px solid #0f172a;
      border-radius: 10px;
      background: #0f172a;
      color: #fff;
      font-size: 13px;
      font-weight: 900;
      padding: 10px 12px;
      cursor: pointer;
    }

    .an-submit:hover {
      background: #020617;
    }

    .an-heat-note {
      margin: 8px 0 0;
      font-size: 12px;
      color: #64748b;
      font-weight: 600;
    }

    .an-heat-scroll {
      margin-top: 10px;
      overflow-x: auto;
    }

    .an-heat-table {
      width: 100%;
      min-width: 720px;
      border-collapse: separate;
      border-spacing: 0;
      border: 1px solid #dbe5ef;
      border-radius: 12px;
      overflow: hidden;
    }

    .an-heat-table th,
    .an-heat-table td {
      padding: 9px 10px;
      border-top: 1px solid #e2e8f0;
      font-size: 13px;
    }

    .an-heat-table thead th {
      border-top: 0;
      background: #f8fafc;
      color: #475569;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      font-size: 11px;
      white-space: nowrap;
    }

    .an-heat-table td {
      color: #0f172a;
      font-weight: 700;
    }

    .an-heat-table td.an-cell-right,
    .an-heat-table th.an-cell-right {
      text-align: right;
    }

    .an-heat-cell {
      text-align: right;
      font-weight: 900;
      color: #0f172a;
    }

    .an-heat-cell.is-pwd {
      background: rgba(14, 165, 233, calc(0.08 + (var(--i) * 0.55)));
    }

    .an-heat-cell.is-fourps {
      background: rgba(34, 197, 94, calc(0.08 + (var(--i) * 0.55)));
    }

    .an-heat-cell.is-elcac {
      background: rgba(245, 158, 11, calc(0.08 + (var(--i) * 0.55)));
    }

    .an-heat-total {
      background: #f1f5f9;
      text-align: right;
      font-weight: 900;
      color: #0f172a;
    }

    .an-access-wrap {
      margin-top: 10px;
    }

    .an-access-card {
      border: 1px solid #dbe5ef;
      border-radius: 16px;
      background: #fff;
      padding: 12px;
    }

    .an-access-note {
      margin: 8px 0 0;
      font-size: 12px;
      color: #64748b;
      font-weight: 600;
    }

    .an-access-scroll {
      margin-top: 10px;
      overflow-x: auto;
    }

    .an-access-table {
      width: 100%;
      min-width: 860px;
      border-collapse: separate;
      border-spacing: 0;
      border: 1px solid #dbe5ef;
      border-radius: 12px;
      overflow: hidden;
    }

    .an-access-table th,
    .an-access-table td {
      padding: 9px 10px;
      border-top: 1px solid #e2e8f0;
      font-size: 13px;
      vertical-align: top;
    }

    .an-access-table thead th {
      border-top: 0;
      background: #f8fafc;
      color: #475569;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      font-size: 11px;
      white-space: nowrap;
      text-align: left;
    }

    .an-access-table td {
      color: #0f172a;
      font-weight: 700;
    }

    .an-access-table td.is-center {
      text-align: center;
      white-space: nowrap;
    }

    .an-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 66px;
      padding: 4px 8px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      border: 1px solid transparent;
    }

    .an-pill.yes {
      color: #166534;
      background: #dcfce7;
      border-color: #bbf7d0;
    }

    .an-pill.no {
      color: #991b1b;
      background: #fee2e2;
      border-color: #fecaca;
    }

    .an-flow {
      margin-top: 12px;
      display: grid;
      gap: 8px;
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .an-flow-step {
      border: 1px solid #dbe5ef;
      border-radius: 12px;
      padding: 10px;
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .an-flow-step h3 {
      margin: 0;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #64748b;
      font-weight: 900;
    }

    .an-flow-step p {
      margin: 6px 0 0;
      font-size: 13px;
      color: #0f172a;
      font-weight: 800;
    }

    @media (max-width: 1280px) {
      .an-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .an-grid3 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .an-form {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }

      .an-flow {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 980px) {
      .an-head,
      .an-body {
        padding: 14px;
      }

      .an-grid3,
      .an-grid2 {
        grid-template-columns: 1fr;
      }

      .an-kpis {
        grid-template-columns: 1fr;
      }

      .an-form {
        grid-template-columns: 1fr;
      }

      .an-flow {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <div class="an-page">
    <div class="an-shell">
      <header class="an-head">
        <div class="an-head-left">
          <div class="an-icon">
            <svg style="width: 26px; height: 26px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 3v18h18" />
              <rect x="7" y="10" width="3" height="7" rx="1" />
              <rect x="12" y="6" width="3" height="11" rx="1" />
              <rect x="17" y="13" width="3" height="4" rx="1" />
            </svg>
          </div>
          <div>
            <h1 class="an-title">Analytics</h1>
            <p class="an-sub">Audit-ready counts, filters, and export.</p>
          </div>
        </div>

        <div class="an-actions">
          <a href="{{ route('admin.certs.index') }}" class="an-btn an-btn-outline">Back to Certificates Dashboard</a>
          <a href="{{ route('admin.analytics.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="an-btn an-btn-outline">Export CSV</a>
          <a href="{{ route('admin.analytics.export', array_merge(request()->all(), ['format' => 'xlsx'])) }}" class="an-btn an-btn-dark">Export XLSX</a>
        </div>
      </header>

      <div class="an-body">
        <section class="an-kpis">
          <article class="an-card">
            <h3>Certificates</h3>
            <strong>{{ number_format($total) }}</strong>
            <p>Filtered set</p>
          </article>

          <article class="an-card green">
            <h3>Unique Participants</h3>
            <strong>{{ number_format($uniqueParticipants) }}</strong>
          </article>

          <article class="an-card sky" id="genderCard">
            <h3>Gender Split</h3>
            @php $m = $byGender['Male'] ?? 0; $f = $byGender['Female'] ?? 0; $sum = max($m+$f,1); @endphp
            <div class="an-gauge">
              <div class="an-gauge-bar">
                <div class="an-gauge-fill" style="width: {{ ($m/$sum)*100 }}%"></div>
              </div>
              <div class="an-gauge-text">M {{ $m }} / F {{ $f }}</div>
            </div>
          </article>

          <article class="an-card amber">
            <h3>Top Industries</h3>
            @php $top3 = $byIndustry->take(3); @endphp
            @if ($top3->isEmpty())
              <strong>—</strong>
              <p>0 certificates</p>
            @else
              @foreach ($top3 as $row)
                <p><strong style="display:inline;font-size:18px;line-height:1;color:#78350f;">{{ $row->industry ?: 'Unspecified' }}</strong> ({{ $row->total }})</p>
              @endforeach
            @endif
          </article>

          <article class="an-card violet">
            <h3>First-time vs Repeat Participants</h3>
            <strong>{{ number_format($firstTimeParticipants) }} : {{ number_format($repeatParticipants) }}</strong>
            <p>First-time {{ $firstTimePct }}% | Repeat {{ $repeatPct }}%</p>
            <div class="an-gauge">
              <div class="an-gauge-bar">
                <div class="an-gauge-fill" style="width: {{ $firstTimePct }}%; background:#16a34a;"></div>
              </div>
              <div class="an-gauge-text">FT {{ $firstTimePct }}%</div>
            </div>
          </article>
        </section>

        <section class="an-grid3">
          <article class="an-chart-card">
            <h2 class="an-chart-title">By Industry</h2>
            <div class="an-chart-box"><canvas id="chartIndustry"></canvas></div>
          </article>
          <article class="an-chart-card">
            <h2 class="an-chart-title">Top Provinces (with Region)</h2>
            <div class="an-chart-box"><canvas id="chartProvince"></canvas></div>
          </article>
          <article class="an-chart-card">
            <h2 class="an-chart-title">Topics (most frequent)</h2>
            <div class="an-chart-box"><canvas id="chartTopic"></canvas></div>
          </article>

          <article class="an-chart-card">
            <h2 class="an-chart-title">Top 5 recurring participants</h2>
            <div class="an-list">
              @forelse ($topParticipants as $p)
                <div class="an-item">
                  <div>
                    <p class="an-name">{{ $p->participant_name }}</p>
                    <p class="an-train">{{ number_format($p->total) }} certificates issued</p>
                    <p class="an-time">Last issued: {{ \Illuminate\Support\Carbon::parse($p->last_issued_at)->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>
                  </div>
                </div>
              @empty
                <div class="an-item">
                  <div>
                    <p class="an-train">No recurring participant data found.</p>
                  </div>
                </div>
              @endforelse
            </div>
          </article>
        </section>

        <section class="an-grid2">
          <article class="an-chart-card">
            <h2 class="an-chart-title">Certificates by training date</h2>
            <div class="an-chart-box" style="height:220px;"><canvas id="chartTimeline"></canvas></div>
          </article>

          <article class="an-chart-card">
            <h2 class="an-chart-title">Top 5 latest certificates</h2>
            <div class="an-list">
              @foreach ($latest as $c)
                <div class="an-item">
                  <div>
                    <p class="an-name">{{ $c->participant_name }}</p>
                    <p class="an-train">{{ $c->training_title }}</p>
                    <p class="an-time">{{ optional($c->created_at)->timezone('Asia/Manila')->format('Y-m-d H:i') }}</p>
                  </div>
                  <div class="an-province">{{ $c->province }}</div>
                </div>
              @endforeach
            </div>
          </article>
        </section>

        <section class="an-grid2">
          <article class="an-chart-card" style="grid-column: 1 / -1;">
            <h2 class="an-chart-title">Inclusion Heat Map (PWD / 4Ps / ELCAC)</h2>
            <p class="an-heat-note">
              Participant intake distribution by region and province. This section follows date, region, province, industry, gender, and keyword filters.
            </p>
            <div class="an-heat-scroll">
              <table class="an-heat-table">
                <thead>
                  <tr>
                    <th>Region</th>
                    <th>Province</th>
                    <th class="an-cell-right">PWD</th>
                    <th class="an-cell-right">4Ps</th>
                    <th class="an-cell-right">ELCAC</th>
                    <th class="an-cell-right">Total Inclusion</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($inclusionHeatmap as $row)
                    @php
                      $pwd = (int) $row->pwd_total;
                      $fourPs = (int) $row->four_ps_total;
                      $elcac = (int) $row->elcac_total;
                      $totalInclusion = (int) $row->inclusion_total;
                    @endphp
                    <tr>
                      <td>{{ $row->region_label }}</td>
                      <td>{{ $row->province_label }}</td>
                      <td class="an-heat-cell is-pwd" style="--i: {{ $pwd / $heatmapMax }};">{{ number_format($pwd) }}</td>
                      <td class="an-heat-cell is-fourps" style="--i: {{ $fourPs / $heatmapMax }};">{{ number_format($fourPs) }}</td>
                      <td class="an-heat-cell is-elcac" style="--i: {{ $elcac / $heatmapMax }};">{{ number_format($elcac) }}</td>
                      <td class="an-heat-total">{{ number_format($totalInclusion) }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="6" style="text-align:center;color:#64748b;font-weight:600;">No intake-based inclusion data found for the current filters.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </article>
        </section>

        <section class="an-access-wrap">
          <article class="an-access-card">
            <h2 class="an-chart-title">Data Access Rights Map</h2>
            <p class="an-access-note">
              Current access matrix by approved role, aligned with implemented route and action restrictions.
            </p>
            <div class="an-access-scroll">
              <table class="an-access-table">
                <thead>
                  <tr>
                    <th>Data / Action</th>
                    <th>Organizer</th>
                    <th>Supervising Unit</th>
                    <th>Regional Director</th>
                    <th>Scope</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Registration form submission</td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td>Public participant intake page</td>
                  </tr>
                  <tr>
                    <td>Participant intake list (view)</td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td>Shared queue monitoring</td>
                  </tr>
                  <tr>
                    <td>Export selected pending intakes (CSV)</td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td>Pending → Done after export</td>
                  </tr>
                  <tr>
                    <td>Delete pending submissions</td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td>Pending only</td>
                  </tr>
                  <tr>
                    <td>Export participant intake CSV</td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td>Done/processed intake records</td>
                  </tr>
                  <tr>
                    <td>Create certificate package and endorse</td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td>Organizer/SU endorse; RD can also prepare</td>
                  </tr>
                  <tr>
                    <td>RD approval + QR certificate generation</td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td>Endorsed package to issued certificates</td>
                  </tr>
                  <tr>
                    <td>Analytics dashboard + export</td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td>Admin analytics module</td>
                  </tr>
                  <tr>
                    <td>User approval and role assignment</td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td class="is-center"><span class="an-pill no">Deny</span></td>
                    <td class="is-center"><span class="an-pill yes">Allow</span></td>
                    <td>Pending account governance</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="an-flow">
              <article class="an-flow-step">
                <h3>Step 1</h3>
                <p>Registration Form</p>
              </article>
              <article class="an-flow-step">
                <h3>Step 2</h3>
                <p>Organizer / Supervising Unit Endorse</p>
              </article>
              <article class="an-flow-step">
                <h3>Step 3</h3>
                <p>Regional Director Approves</p>
              </article>
              <article class="an-flow-step">
                <h3>Step 4</h3>
                <p>Export Data and Generate QR</p>
              </article>
            </div>
          </article>
        </section>

        <section class="an-form-wrap">
          <form method="GET" action="{{ route('admin.analytics.index') }}" class="an-form">
            <div class="an-field">
              <label>Date From</label>
              <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="an-input">
            </div>
            <div class="an-field">
              <label>Date To</label>
              <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="an-input">
            </div>
            <div class="an-field">
              <label>Region</label>
              <input name="region" value="{{ $filters['region'] }}" placeholder="Region" class="an-input">
            </div>
            <div class="an-field">
              <label>Province</label>
              <input name="province" value="{{ $filters['province'] }}" placeholder="Province" class="an-input">
            </div>
            <div class="an-field">
              <label>Office</label>
              <input name="office" value="{{ $filters['office'] }}" placeholder="Issuing office" class="an-input">
            </div>
            <div class="an-field">
              <label>Industry</label>
              <input name="industry" value="{{ $filters['industry'] }}" placeholder="Industry" class="an-input">
            </div>
            <div class="an-field">
              <label>Gender</label>
              <select name="gender" class="an-select">
                <option value="">Any</option>
                <option value="Male" @selected($filters['gender']==='Male')>Male</option>
                <option value="Female" @selected($filters['gender']==='Female')>Female</option>
              </select>
            </div>
            <div class="an-field">
              <label>Keyword</label>
              <input name="q" value="{{ $filters['q'] }}" placeholder="Code / participant / training" class="an-input">
            </div>
            <div class="an-field action">
              <button class="an-submit">Apply</button>
            </div>
          </form>
        </section>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>
    if (typeof Chart !== 'undefined') {
      const palette = ['#0ea5e9','#6366f1','#22c55e','#f97316','#ec4899','#06b6d4','#f59e0b','#8b5cf6','#10b981','#f43f5e'];

      const industryChart = @json($industryChart);
      const ctxIndustry = document.getElementById('chartIndustry');
      if (ctxIndustry && industryChart.labels.length) {
        new Chart(ctxIndustry, {
          type: 'bar',
          data: {
            labels: industryChart.labels,
            datasets: [{ label: 'Certificates', data: industryChart.data, backgroundColor: palette }]
          },
          options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
      }

      const provinceChart = {
        labels: @json($byRegion->pluck('province')->map(fn($p)=>$p ?: 'Unspecified')->values()),
        data: @json($byRegion->pluck('total')->values()),
      };
      const ctxProvince = document.getElementById('chartProvince');
      if (ctxProvince && provinceChart.labels.length) {
        new Chart(ctxProvince, {
          type: 'bar',
          data: {
            labels: provinceChart.labels,
            datasets: [{ label: 'Certificates', data: provinceChart.data, backgroundColor: palette }]
          },
          options: {
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
          }
        });
      }

      const genderChart = @json($genderChart);
      const ctxGender = document.createElement('canvas');
      const genderCard = document.getElementById('genderCard');
      if (genderCard) {
        ctxGender.style.maxHeight = '180px';
        ctxGender.style.marginTop = '10px';
        genderCard.appendChild(ctxGender);
      }
      if (ctxGender && genderChart.data.some(v => v > 0)) {
        new Chart(ctxGender, {
          type: 'doughnut',
          data: {
            labels: genderChart.labels,
            datasets: [{ data: genderChart.data, backgroundColor: ['#2563eb','#ec4899','#cbd5e1'] }]
          },
          options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '55%' }
        });
      }

      const timelineChart = @json($timelineChart);
      const ctxTimeline = document.getElementById('chartTimeline');
      if (ctxTimeline && timelineChart.labels.length) {
        new Chart(ctxTimeline, {
          type: 'line',
          data: {
            labels: timelineChart.labels,
            datasets: [{ label: 'Certificates', data: timelineChart.data, tension: 0.25, fill: false, borderColor: '#2563eb', pointRadius: 3, pointBackgroundColor: '#2563eb' }]
          },
          options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
          }
        });
      }

      const topicChart = @json($topicChart);
      const ctxTopic = document.getElementById('chartTopic');
      if (ctxTopic && topicChart.labels.length) {
        new Chart(ctxTopic, {
          type: 'bar',
          data: {
            labels: topicChart.labels,
            datasets: [{ label: 'Certificates', data: topicChart.data, backgroundColor: palette }]
          },
          options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
      }
    }
  </script>
</x-admin-layout>
