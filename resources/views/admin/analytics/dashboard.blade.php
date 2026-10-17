<x-admin-layout title="Dashboard">
  <div class="an-dash"
       x-data="analyticsDashboard(@js(route('admin.analytics.data')))"
       x-init="load()">

    <header class="an-dash-head">
      <div>
        <h1>Analytics Dashboard</h1>
        <p class="an-dash-sub">DOST Caraga CERTiFY — cross-filtered impact analytics</p>
      </div>
      <nav class="an-tabs" role="tablist" aria-label="Dashboard sections">
        <button type="button" role="tab" :aria-selected="tab==='executive'"
                :class="{ 'is-active': tab==='executive' }" @click="tab='executive'">Executive</button>
        <button type="button" role="tab" :aria-selected="tab==='operations'"
                :class="{ 'is-active': tab==='operations' }" @click="tab='operations'">Operations</button>
      </nav>
    </header>

    {{-- Loading / error / unavailable states --}}
    <template x-if="loading">
      <div class="an-note">Loading analytics…</div>
    </template>
    <template x-if="error">
      <div class="an-note an-note-error">
        Could not load analytics. <button type="button" @click="load()">Retry</button>
      </div>
    </template>
    <template x-if="!loading && !error && data && data.available === false">
      <div class="an-note">
        Analytics views are not available yet. Run <code>php artisan analytics:refresh</code>
        (requires PostgreSQL) to build them.
      </div>
    </template>

    {{-- Executive --}}
    <section x-show="!loading && !error && data && data.available && tab==='executive'" x-cloak>
      <div class="an-kpis">
        <template x-for="(kpi, key) in (data?.executive?.kpis ?? {})" :key="key">
          <div class="an-kpi">
            <div class="an-kpi-label" x-text="labelFor(key)"></div>
            <div class="an-kpi-value" x-text="fmt(kpi.value)"></div>
            <div class="an-kpi-delta"
                 x-show="kpi.delta !== null"
                 :class="{ 'up': kpi.delta > 0, 'down': kpi.delta < 0 }"
                 x-text="deltaText(kpi.delta)"></div>
          </div>
        </template>
      </div>
      <p class="an-note an-note-muted">
        Rich panels (choropleth, age pyramid, program mix, monthly trend, top trainings)
        render here in the next iteration. KPI tiles above are live from
        <code>/admin/analytics/data</code>.
      </p>
    </section>

    {{-- Operations --}}
    <section x-show="!loading && !error && data && data.available && tab==='operations'" x-cloak>
      <p class="an-note" x-show="data?.operations?.scoped">
        Showing only packages and intake events you created or endorsed.
      </p>
      <div class="an-ops">
        <div class="an-ops-card">
          <h3>Endorsement pipeline</h3>
          <ul>
            <template x-for="row in (data?.operations?.pipeline ?? [])" :key="row.outcome">
              <li><span x-text="row.outcome"></span><strong x-text="row.total"></strong></li>
            </template>
          </ul>
        </div>
        <div class="an-ops-card">
          <h3>RD turnaround (hours)</h3>
          <ul>
            <li><span>Average</span><strong x-text="data?.operations?.turnaround?.avg_hours ?? '—'"></strong></li>
            <li><span>Median (p50)</span><strong x-text="data?.operations?.turnaround?.p50_hours ?? '—'"></strong></li>
            <li><span>p90</span><strong x-text="data?.operations?.turnaround?.p90_hours ?? '—'"></strong></li>
          </ul>
        </div>
        <div class="an-ops-card">
          <h3>Claim funnel</h3>
          <ul>
            <li><span>People</span><strong x-text="data?.operations?.claim_funnel?.people ?? 0"></strong></li>
            <li><span>Claimed</span><strong x-text="data?.operations?.claim_funnel?.claimed ?? 0"></strong></li>
            <li><span>Dormant</span><strong x-text="data?.operations?.claim_funnel?.dormant ?? 0"></strong></li>
          </ul>
        </div>
      </div>
      <p class="an-note an-note-muted">Full operations charts render here in the next iteration.</p>
    </section>
  </div>

  <style>
    [x-cloak]{display:none!important}
    .an-dash{max-width:1200px;margin:0 auto;color:#0f172a}
    .an-dash-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:20px}
    .an-dash-head h1{font-size:22px;font-weight:800;margin:0}
    .an-dash-sub{color:#64748b;font-size:13px;margin:2px 0 0}
    .an-tabs{display:inline-flex;gap:4px;background:#f1f5f9;border-radius:12px;padding:4px}
    .an-tabs button{border:0;background:transparent;padding:8px 16px;border-radius:9px;font-weight:600;font-size:13.5px;color:#64748b;cursor:pointer}
    .an-tabs button.is-active{background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(15,23,42,.12)}
    .an-note{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;font-size:13.5px;color:#475569;margin:12px 0}
    .an-note-error{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
    .an-note-muted{color:#94a3b8;background:transparent;border-style:dashed}
    .an-note code{background:#eef2f7;padding:1px 6px;border-radius:6px;font-size:12.5px}
    .an-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}
    .an-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px 18px}
    .an-kpi-label{font-size:12.5px;color:#64748b;font-weight:600}
    .an-kpi-value{font-size:26px;font-weight:800;margin-top:6px;letter-spacing:-.02em}
    .an-kpi-delta{font-size:12px;font-weight:700;margin-top:4px;color:#94a3b8}
    .an-kpi-delta.up{color:#15803d}.an-kpi-delta.down{color:#b91c1c}
    .an-ops{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:4px}
    .an-ops-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px 18px}
    .an-ops-card h3{font-size:13.5px;font-weight:700;margin:0 0 10px}
    .an-ops-card ul{list-style:none;margin:0;padding:0}
    .an-ops-card li{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
    .an-ops-card li span{color:#64748b;text-transform:capitalize}
  </style>

  <script>
    function analyticsDashboard(dataUrl) {
      return {
        dataUrl, tab: 'executive', loading: false, error: false, data: null,
        labels: {
          certificates: 'Certificates issued', people_reached: 'People reached',
          repeat_rate: 'Repeat rate', municipalities: 'Municipalities reached',
          avg_turnaround_hours: 'Avg RD turnaround', claim_rate: 'Claim rate',
        },
        pct: { repeat_rate: 1, claim_rate: 1 },
        hours: { avg_turnaround_hours: 1 },
        labelFor(k) { return this.labels[k] ?? k; },
        fmt(v) {
          if (v == null) return '—';
          return new Intl.NumberFormat('en-PH').format(v);
        },
        deltaText(d) {
          if (d == null) return '';
          const s = d > 0 ? '▲' : (d < 0 ? '▼' : '•');
          return `${s} ${new Intl.NumberFormat('en-PH').format(Math.abs(d))} vs prev.`;
        },
        async load() {
          this.loading = true; this.error = false;
          try {
            const res = await fetch(this.dataUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            this.data = await res.json();
          } catch (e) {
            this.error = true; console.error('[analytics] load failed', e);
          } finally {
            this.loading = false;
          }
        },
      };
    }
  </script>
</x-admin-layout>
