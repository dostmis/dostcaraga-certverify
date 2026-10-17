<x-admin-layout title="Dashboard">
  <div class="an" data-analytics-dashboard
       x-data="analyticsDashboard({
         dataUrl: @js(route('admin.analytics.data')),
         geoProvUrl: @js(asset('geo/caraga-provinces.0.001.json')),
         geoMuniUrl: @js(asset('geo/caraga-municipalities.0.001.json'))
       })">

    <header class="an-head">
      <div>
        <h1>Analytics Dashboard</h1>
        <p class="an-sub">DOST Caraga CERTiFY — cross-filtered impact analytics</p>
      </div>
      <nav class="an-tabs" role="tablist" aria-label="Dashboard sections">
        <button type="button" role="tab" :aria-selected="tab==='executive'" :class="{'is-active':tab==='executive'}" @click="switchTab('executive')">Executive</button>
        <button type="button" role="tab" :aria-selected="tab==='operations'" :class="{'is-active':tab==='operations'}" @click="switchTab('operations')">Operations</button>
      </nav>
    </header>

    {{-- Filter controls (one row above the charts) --}}
    <div class="an-filters" role="group" aria-label="Filters">
      <label>Gender
        <select x-model="filters.gender" @change="afterFilterChange()">
          <option value="">All</option><option value="Male">Male</option>
          <option value="Female">Female</option><option value="Unknown">Unknown</option>
        </select>
      </label>
      <label>Age band
        <select x-model="filters.age_band" @change="afterFilterChange()">
          <option value="">All</option>
          <template x-for="b in ['<18','18-24','25-34','35-44','45-59','60+','Unknown']" :key="b">
            <option :value="b" x-text="b"></option>
          </template>
        </select>
      </label>
      <label>Program
        <select x-model="filters.program" @change="afterFilterChange()">
          <option value="">All</option>
          <template x-for="p in (data?.executive?.program_mix ?? [])" :key="p.program_code">
            <option :value="p.program_code" x-text="p.program_code"></option>
          </template>
        </select>
      </label>
      <label>From <input type="date" x-model="filters.date_from" @change="afterFilterChange()"></label>
      <label>To <input type="date" x-model="filters.date_to" @change="afterFilterChange()"></label>
    </div>

    {{-- Active filter chips --}}
    <div class="an-chips" x-show="hasFilters()" x-cloak>
      <template x-for="chip in chips" :key="chip.key">
        <button type="button" class="an-chip" @click="removeChip(chip.key)">
          <span x-text="chip.label + ': ' + chip.value"></span><span aria-hidden="true">×</span>
        </button>
      </template>
      <button type="button" class="an-chip an-chip-reset" @click="resetAll()">Reset all</button>
    </div>

    {{-- States --}}
    <div class="an-note" x-show="loading" x-cloak>Loading analytics…</div>
    <div class="an-note an-note-error" x-show="error" x-cloak>Could not load analytics. <button type="button" @click="load()">Retry</button></div>
    <div class="an-note" x-show="!loading && !error && data && data.available===false" x-cloak>
      Analytics views are not available yet. Run <code>php artisan analytics:refresh</code> (requires PostgreSQL).
    </div>

    {{-- ===== Executive ===== --}}
    <section x-show="!loading && !error && data && data.available && tab==='executive'" x-cloak>
      <div class="an-kpis">
        <template x-for="k in kpiTiles" :key="k.key">
          <div class="an-kpi">
            <div class="an-kpi-label" x-text="k.label"></div>
            <div class="an-kpi-value" x-text="k.value"></div>
            <div class="an-kpi-delta" :class="k.dir" x-show="k.delta!==null" x-text="k.deltaText"></div>
          </div>
        </template>
      </div>

      <div class="an-grid">
        <x-analytics.panel name="map" title="Certificates by location" class="an-col-2">
          <div class="an-breadcrumb" x-show="mapLevel==='municipality'" x-cloak>
            <button type="button" @click="drillUp()">← Caraga (provinces)</button>
            <span x-text="drill?.name"></span>
          </div>
          <p class="an-hint">Unknown location: <strong x-text="(data?.executive?.geo?.unknown_municipality_share ?? 0)+'%'"></strong> · click a region to drill in/filter</p>
        </x-analytics.panel>
        <x-analytics.panel name="age" title="Age distribution by gender" />
        <x-analytics.panel name="program" title="Program mix (click to filter)" />
        <x-analytics.panel name="trend" title="Monthly trend (brush to set date range)" class="an-col-2" />
      </div>

      <div class="an-card">
        <div class="an-card-head"><h3>Top trainings</h3></div>
        <table class="an-table">
          <thead><tr><th>Training</th><th>Program</th><th>Certificates</th></tr></thead>
          <tbody>
            <template x-for="t in topTrainings" :key="t.training_title_norm + '|' + t.program_code">
              <tr><td x-text="t.training_title"></td><td class="an-prog" x-text="t.program_code"></td><td x-text="fmtNum(t.total)"></td></tr>
            </template>
            <tr x-show="!topTrainings.length"><td colspan="3" class="an-empty">No certificates match these filters.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    {{-- ===== Operations ===== --}}
    <section x-show="!loading && !error && data && data.available && tab==='operations'" x-cloak>
      <p class="an-note" x-show="data?.operations?.scoped" x-cloak>Showing only packages and intake events you created or endorsed.</p>
      <div class="an-grid">
        <x-analytics.panel name="pipeline" title="Endorsement pipeline" />
        <div class="an-card an-panel">
          <div class="an-card-head"><h3>RD turnaround</h3></div>
          <div class="an-stat-row">
            <div><span>Average</span><strong x-text="(data?.operations?.turnaround?.avg_hours ?? '—')+' h'"></strong></div>
            <div><span>Median</span><strong x-text="(data?.operations?.turnaround?.p50_hours ?? '—')+' h'"></strong></div>
            <div><span>p90</span><strong x-text="(data?.operations?.turnaround?.p90_hours ?? '—')+' h'"></strong></div>
            <div><span>Decided</span><strong x-text="fmtNum(data?.operations?.turnaround?.decided ?? 0)"></strong></div>
          </div>
        </div>
        <x-analytics.panel name="errors" title="Rejections & generation errors" />
        <x-analytics.panel name="email" title="Email delivery" />
        <x-analytics.panel name="claim" title="Claim funnel" />
        <x-analytics.panel name="intake" title="Intake conversion by event" />
      </div>
    </section>
  </div>

  @include('admin.analytics._dashboard_styles')
</x-admin-layout>
