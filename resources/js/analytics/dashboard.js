// Admin analytics dashboard: shared filter state, one debounced fetch per
// change, cross-filtering, URL sync, per-panel PNG/CSV export, and a drillable
// Caraga choropleth. Registered as Alpine.data('analyticsDashboard').
import { makeChart, echarts, currentMode } from './echarts';
import * as P from './panels';
import { nf, pct, debounce, downloadCSV, exportPNG } from './util';

const FILTER_KEYS = ['date_from', 'date_to', 'program', 'province', 'municipality', 'gender', 'age_band'];

const KPI_META = {
  certificates: { label: 'Certificates issued', fmt: nf },
  people_reached: { label: 'People reached', fmt: nf },
  repeat_rate: { label: 'Repeat rate', fmt: pct },
  municipalities: { label: 'Municipalities reached', fmt: nf },
  avg_turnaround_hours: { label: 'Avg RD turnaround (h)', fmt: nf },
  claim_rate: { label: 'Claim rate', fmt: pct },
};

export default function analyticsDashboard(cfg) {
  return {
    dataUrl: cfg.dataUrl,
    geoProvUrl: cfg.geoProvUrl,
    geoMuniUrl: cfg.geoMuniUrl,

    tab: 'executive',
    loading: true,
    error: false,
    data: null,
    filters: emptyFilters(),

    charts: {},
    geo: { prov: null, muni: null },
    mapLevel: 'province',
    drill: null, // { code, name }

    _debouncedLoad: null,
    _resizeObserver: null,

    init() {
      this.readUrl();
      this._debouncedLoad = debounce(() => this.load(), 250);
      this._resizeObserver = new ResizeObserver(() => this.resizeAll());
      this._resizeObserver.observe(this.$root);
      window.addEventListener('resize', () => this.resizeAll());
      this.loadGeo().finally(() => this.load());
    },

    // ---- filter state ----
    get chips() {
      const labels = {
        date_from: 'From', date_to: 'To', program: 'Program', province: 'Province',
        municipality: 'Municipality', gender: 'Gender', age_band: 'Age',
      };
      return FILTER_KEYS.filter((k) => this.filters[k] != null && this.filters[k] !== '')
        .map((k) => ({ key: k, label: labels[k], value: this.chipValue(k) }));
    },
    chipValue(k) {
      if (k === 'province') return this.nameForProvince(this.filters.province);
      if (k === 'municipality') return this.nameForMunicipality(this.filters.municipality);
      return this.filters[k];
    },
    hasFilters() { return this.chips.length > 0; },

    setFilter(key, value) {
      // Toggle: clicking the active value again clears it.
      this.filters[key] = (this.filters[key] === value) ? null : value;
      this.afterFilterChange();
    },
    removeChip(key) {
      this.filters[key] = null;
      if (key === 'province') { this.filters.municipality = null; this.backToProvince(); }
      this.afterFilterChange();
    },
    resetAll() {
      this.filters = emptyFilters();
      this.backToProvince();
      this.afterFilterChange();
    },
    afterFilterChange() { this.syncUrl(); this._debouncedLoad(); },

    readUrl() {
      const q = new URLSearchParams(window.location.search);
      FILTER_KEYS.forEach((k) => {
        const v = q.get(k);
        if (v != null && v !== '') this.filters[k] = (k === 'province' || k === 'municipality') ? Number(v) : v;
      });
      if (q.get('tab') === 'operations') this.tab = 'operations';
    },
    syncUrl() {
      const q = new URLSearchParams();
      FILTER_KEYS.forEach((k) => { if (this.filters[k] != null && this.filters[k] !== '') q.set(k, this.filters[k]); });
      if (this.tab === 'operations') q.set('tab', 'operations');
      const qs = q.toString();
      window.history.replaceState(null, '', qs ? `?${qs}` : window.location.pathname);
    },
    queryString() {
      const q = new URLSearchParams();
      FILTER_KEYS.forEach((k) => { if (this.filters[k] != null && this.filters[k] !== '') q.set(k, this.filters[k]); });
      return q.toString();
    },

    switchTab(t) { this.tab = t; this.syncUrl(); this.$nextTick(() => { this.renderAll(); this.resizeAll(); }); },

    // ---- data ----
    async load() {
      this.loading = true; this.error = false;
      try {
        const url = this.dataUrl + (this.queryString() ? `?${this.queryString()}` : '');
        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        this.data = await res.json();
        this.$nextTick(() => this.renderAll());
      } catch (e) {
        this.error = true; console.error('[analytics] load failed', e);
      } finally {
        this.loading = false;
      }
    },

    async loadGeo() {
      try {
        const [prov, muni] = await Promise.all([
          fetch(this.geoProvUrl).then((r) => r.json()),
          fetch(this.geoMuniUrl).then((r) => r.json()),
        ]);
        this.geo.prov = prov; this.geo.muni = muni;
        echarts.registerMap('caraga-prov', prov, { nameProperty: 'adm2_en' });
      } catch (e) { console.error('[analytics] geo load failed', e); }
    },

    // ---- KPI tiles (HTML) ----
    get kpiTiles() {
      const kpis = this.data?.executive?.kpis || {};
      return Object.entries(KPI_META).map(([key, meta]) => {
        const k = kpis[key] || {};
        return {
          key, label: meta.label,
          value: meta.fmt(k.value),
          delta: k.delta,
          deltaText: k.delta == null ? '' : `${k.delta > 0 ? '▲' : (k.delta < 0 ? '▼' : '•')} ${nf(Math.abs(k.delta))} vs prev.`,
          dir: k.delta > 0 ? 'up' : (k.delta < 0 ? 'down' : ''),
        };
      });
    },
    get topTrainings() { return this.data?.executive?.top_trainings || []; },

    // ---- rendering ----
    renderAll() {
      if (!this.data || !this.data.available) return;
      if (this.tab === 'executive') {
        const ex = this.data.executive;
        this.renderMap();
        this.draw('age', P.agePyramid(ex.age_pyramid, this.mode()), ex.age_pyramid);
        const prog = this.draw('program', P.programMix(ex.program_mix, this.mode()), ex.program_mix);
        if (prog) { prog.off('click'); prog.on('click', (p) => { const code = p.data?.code ?? p.name; if (code) this.setFilter('program', code); }); }
        const trend = this.draw('trend', P.monthlyTrend(ex.monthly_trend, this.mode()), ex.monthly_trend);
        if (trend) this.bindTrendBrush(trend);
      } else {
        const ops = this.data.operations;
        this.draw('pipeline', P.pipeline(ops.pipeline, this.mode()), ops.pipeline);
        this.draw('errors', P.errorTrend(ops.error_trend, this.mode()), ops.error_trend);
        this.draw('email', P.emailDelivery(ops.email_delivery, this.mode()), ops.email_delivery);
        this.draw('claim', P.claimFunnel(ops.claim_funnel, this.mode()), ops.claim_funnel);
        this.draw('intake', P.intakeConversion(ops.intake_conversion, this.mode()), ops.intake_conversion);
      }
      this.resizeAll();
    },

    bindTrendBrush(inst) {
      inst.off('datazoom');
      inst.on('datazoom', () => {
        const rows = this.data.executive.monthly_trend;
        const dz = inst.getOption().dataZoom?.[0];
        if (!dz || !rows.length) return;
        const s = Math.max(0, Math.floor(dz.startValue ?? 0));
        const e = Math.min(rows.length - 1, Math.ceil(dz.endValue ?? rows.length - 1));
        const a = rows[s], b = rows[e];
        if (!a || !b) return;
        this.filters.date_from = `${a.training_year}-${String(a.training_month).padStart(2, '0')}-01`;
        const end = new Date(b.training_year, b.training_month, 0);
        this.filters.date_to = `${b.training_year}-${String(b.training_month).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`;
        this.afterFilterChange();
      });
    },

    renderMap() {
      const geo = this.data.executive.geo;
      if (this.mapLevel === 'province') {
        const inst = this.draw('map', P.choropleth(geo.province, 'caraga-prov', this.mode()), geo.province);
        if (inst) this.bindMapClick(inst, 'province');
      } else {
        const feats = (this.geo.muni?.features || []).filter((f) => String(f.properties.adm2_psgc) === String(this.drill.code));
        const mapName = `caraga-muni-${this.drill.code}`;
        echarts.registerMap(mapName, { type: 'FeatureCollection', features: feats }, { nameProperty: 'adm3_en' });
        const rows = geo.municipality.filter((m) => String(m.province_code) === String(this.drill.code));
        const inst = this.draw('map', P.choropleth(rows, mapName, this.mode()), rows);
        if (inst) this.bindMapClick(inst, 'municipality');
      }
    },
    bindMapClick(inst, level) {
      inst.off('click');
      inst.on('click', (p) => {
        if (level === 'province') {
          const row = this.data.executive.geo.province.find((r) => r.name === p.name);
          if (!row) return;
          this.drill = { code: row.code, name: row.name };
          this.mapLevel = 'municipality';
          this.filters.province = row.code;
          this.filters.municipality = null;
          this.afterFilterChange();
        } else {
          const row = this.data.executive.geo.municipality.find((r) => r.name === p.name);
          if (row) this.setFilter('municipality', row.code);
        }
      });
    },
    backToProvince() { this.mapLevel = 'province'; this.drill = null; },
    drillUp() { this.filters.province = null; this.filters.municipality = null; this.backToProvince(); this.afterFilterChange(); },

    // ---- chart lifecycle ----
    // Create-or-reuse the chart for a panel container, apply the option, and
    // stash the row data for CSV export. Returns the instance (or null).
    draw(id, option, data) {
      const el = this.$root.querySelector(`[data-panel="${id}"]`);
      if (!el) return null;
      let inst = this.charts[id];
      if (!inst) { inst = makeChart(el); this.charts[id] = inst; }
      inst.__data = data;
      inst.setOption(option, true);
      return inst;
    },
    resizeAll() { Object.values(this.charts).forEach((c) => { try { c.resize(); } catch (e) { /* noop */ } }); },
    mode() { return currentMode(); },
    fmtNum(v) { return nf(v); },

    // ---- exports ----
    exportPng(id, name) { exportPNG(this.charts[id], `${name}.png`); },
    exportCsv(id, name) {
      const rows = this.charts[id]?.__data;
      if (Array.isArray(rows) && rows.length) downloadCSV(`${name}.csv`, rows);
      else if (rows && typeof rows === 'object') downloadCSV(`${name}.csv`, [rows]);
    },

    // ---- name lookups for chips ----
    nameForProvince(code) {
      return this.data?.executive?.geo?.province?.find((r) => String(r.code) === String(code))?.name ?? code;
    },
    nameForMunicipality(code) {
      return this.data?.executive?.geo?.municipality?.find((r) => String(r.code) === String(code))?.name ?? code;
    },
  };
}

function emptyFilters() {
  return { date_from: null, date_to: null, program: null, province: null, municipality: null, gender: null, age_band: null };
}
