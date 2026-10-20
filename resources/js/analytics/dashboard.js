// Admin analytics dashboard, structured as a sequential scrollytelling
// narrative: six chapters read top to bottom, each answering the question the
// previous one raises — how much and where (Reach), for whom (Who), against
// what plan (Pace), is it being used (Proof), how well do we deliver it
// (Delivery), and is anyone abusing it (Integrity).
//
// Shared filter state applies across every chapter, with one debounced fetch
// per change. Charts are created lazily the first time their chapter scrolls
// into view — six chapters of ECharts instances built up front would cost a
// second of main thread for panels most visits never scroll to.
//
// Registered as Alpine.data('analyticsDashboard').
import { makeChart, echarts, HUD_THEME } from './echarts';
import * as P from './panels';
import { nf, pct, debounce, downloadCSV, exportPNG } from './util';

const FILTER_KEYS = ['date_from', 'date_to', 'program', 'province', 'municipality', 'gender', 'age_band'];

// Narrative order. `id` doubles as the DOM anchor and the URL hash.
const CHAPTERS = [
  { id: 'reach', n: '01', label: 'Reach', title: 'How far did we get?' },
  { id: 'who', n: '02', label: 'Who', title: 'Who did we reach?' },
  { id: 'pace', n: '03', label: 'Pace', title: 'Are we on plan?' },
  { id: 'proof', n: '04', label: 'Proof', title: 'Is anyone verifying?' },
  { id: 'delivery', n: '05', label: 'Delivery', title: 'How well do we deliver?' },
  { id: 'integrity', n: '06', label: 'Integrity', title: 'Is anyone abusing it?' },
];

// `code` is the small telemetry tag printed under each module (SYS_ID: 3142).
// Decorative, but it reads off the real value — never a made-up number.
const KPI_META = {
  certificates: { label: 'Certificates issued', fmt: nf, code: 'SYS_ID' },
  people_reached: { label: 'People reached', fmt: nf, code: 'POP_IDX' },
  repeat_rate: { label: 'Repeat rate', fmt: pct, code: 'RET_STAT' },
  municipalities: { label: 'Municipalities reached', fmt: nf, code: 'GEO_NODES' },
  avg_turnaround_hours: { label: 'Avg RD turnaround (h)', fmt: nf, code: 'LAT_AVG' },
  claim_rate: { label: 'Claim rate', fmt: pct, code: 'SEC_STAT' },
};

export default function analyticsDashboard(cfg) {
  return {
    dataUrl: cfg.dataUrl,
    geoProvUrl: cfg.geoProvUrl,
    geoMuniUrl: cfg.geoMuniUrl,
    targetsUrl: cfg.targetsUrl || null,

    chapters: CHAPTERS,
    // 'dark' (HUD) or 'light' (high contrast). Seeded from the <html> stamp the
    // layout's inline script already applied, so there is no flash of the wrong
    // theme before Alpine boots.
    theme: document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark',
    active: 'reach',
    progress: 0,
    loading: true,
    error: false,
    data: null,
    filters: emptyFilters(),

    charts: {},
    // Chapters whose charts have been built. Re-renders on filter change touch
    // only these, so an unvisited chapter stays unbuilt.
    opened: {},
    geo: { prov: null, muni: null },
    mapLevel: 'province',
    drill: null, // { code, name }

    _debouncedLoad: null,
    _resizeObserver: null,
    _chapterObserver: null,
    _onScroll: null,

    init() {
      this.readUrl();
      this._debouncedLoad = debounce(() => this.load(), 250);
      this._resizeObserver = new ResizeObserver(() => this.resizeAll());
      this._resizeObserver.observe(this.$root);
      window.addEventListener('resize', () => this.resizeAll());

      this._onScroll = () => this.trackProgress();
      window.addEventListener('scroll', this._onScroll, { passive: true });

      this.loadGeo().finally(() => this.load());
      this.$nextTick(() => this.observeChapters());
    },

    destroy() {
      this._resizeObserver?.disconnect();
      this._chapterObserver?.disconnect();
      if (this._onScroll) window.removeEventListener('scroll', this._onScroll);
    },

    // ---- theme ----

    /**
     * ECharts bakes its theme in at init, so a theme switch has to dispose and
     * rebuild every live instance — setOption cannot change it. Only opened
     * chapters are rebuilt, so this stays cheap.
     */
    setTheme(next) {
      const theme = next === 'light' ? 'light' : 'dark';
      if (theme === this.theme) return;
      this.theme = theme;
      document.documentElement.setAttribute('data-theme', theme);
      try { localStorage.setItem('certify.theme', theme); } catch (e) { /* private mode */ }

      Object.values(this.charts).forEach((c) => { try { c.dispose(); } catch (e) { /* noop */ } });
      this.charts = {};
      this.$nextTick(() => this.renderOpened());
    },
    toggleTheme() { this.setTheme(this.theme === 'dark' ? 'light' : 'dark'); },

    // ---- scrollytelling ----

    /**
     * One observer drives both the active-chapter highlight and the lazy chart
     * build. The asymmetric rootMargin biases "active" toward the chapter
     * occupying the upper-middle of the viewport, which is where the reader's
     * attention is while scrolling down.
     */
    observeChapters() {
      const sections = Array.from(this.$root.querySelectorAll('[data-chapter]'));
      if (!sections.length || !('IntersectionObserver' in window)) {
        // No observer (or no sections): open everything so nothing is hidden.
        CHAPTERS.forEach((c) => { this.opened[c.id] = true; });
        this.renderOpened();
        return;
      }

      this._chapterObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          const id = entry.target.dataset.chapter;
          if (!entry.isIntersecting) return;
          this.active = id;
          this.openChapter(id);
        });
      }, { rootMargin: '-20% 0px -55% 0px', threshold: 0 });

      sections.forEach((el) => this._chapterObserver.observe(el));

      // The chapter already in view on load (deep link or restored scroll)
      // must open even if it never crosses the threshold again.
      this.openChapter(this.active);
    },

    openChapter(id) {
      if (this.opened[id]) return;
      this.opened[id] = true;
      this.$nextTick(() => this.renderChapter(id));
    },

    jumpTo(id) {
      const el = this.$root.querySelector(`[data-chapter="${id}"]`);
      if (!el) return;
      this.active = id;
      this.openChapter(id);
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      this.syncUrl();
    },

    trackProgress() {
      const doc = document.documentElement;
      const scrollable = doc.scrollHeight - window.innerHeight;
      this.progress = scrollable > 0 ? Math.min(100, Math.max(0, (window.scrollY / scrollable) * 100)) : 0;
    },

    chapterVisible(id) {
      // Integrity is RD-only: the payload carries no integrity block for
      // scoped users, so the chapter is absent rather than empty.
      if (id === 'integrity') return !!this.data?.integrity;
      return true;
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
      const chapter = (window.location.hash || '').replace('#', '') || q.get('chapter');
      if (CHAPTERS.some((c) => c.id === chapter)) this.active = chapter;
    },
    syncUrl() {
      const q = new URLSearchParams();
      FILTER_KEYS.forEach((k) => { if (this.filters[k] != null && this.filters[k] !== '') q.set(k, this.filters[k]); });
      const qs = q.toString();
      const hash = this.active && this.active !== 'reach' ? `#${this.active}` : '';
      window.history.replaceState(null, '', `${window.location.pathname}${qs ? `?${qs}` : ''}${hash}`);
    },
    queryString() {
      const q = new URLSearchParams();
      FILTER_KEYS.forEach((k) => { if (this.filters[k] != null && this.filters[k] !== '') q.set(k, this.filters[k]); });
      return q.toString();
    },

    // ---- data ----
    async load() {
      this.loading = true; this.error = false;
      try {
        const url = this.dataUrl + (this.queryString() ? `?${this.queryString()}` : '');
        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        this.data = await res.json();
        this.$nextTick(() => this.renderOpened());
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
        echarts.registerMap('caraga-prov', prov);
      } catch (e) { console.error('[analytics] geo load failed', e); }
    },

    // ---- narrative leads ----
    // Each chapter opens with a sentence read off the data, so the numbers are
    // stated in prose before they are plotted.

    get leadReach() {
      const k = this.data?.executive?.kpis;
      if (!k) return '';
      return `${nf(k.certificates?.value)} certificates reached ${nf(k.people_reached?.value)} people `
        + `across ${nf(k.municipalities?.value)} Caraga municipalities.`;
    },
    get leadWho() {
      const k = this.data?.executive?.kpis;
      const mix = this.data?.executive?.program_mix || [];
      if (!k) return '';
      const top = mix[0];
      return `${pct(k.repeat_rate?.value)} of the people reached came back for a second training`
        + (top ? `, and ${top.program_code} accounts for the largest share.` : '.');
    },
    get leadPace() {
      const t = this.data?.targets;
      if (!t) return '';
      if (!t.has_targets) return `No targets are set for ${t.year} yet, so there is nothing to pace against.`;
      const tot = t.total?.certificates;
      if (!tot || tot.target == null) return `Targets for ${t.year} are set per program.`;
      const st = this.statusFor(tot.status);
      return `${nf(tot.actual)} of ${nf(tot.target)} certificates against the ${t.year} plan `
        + `(${nf(tot.attainment)}%), ${st.label.toLowerCase()} — year-end at this pace: ${nf(tot.projection)}.`;
    },
    get leadProof() {
      const v = this.data?.executive?.verification;
      if (!v?.available) return 'Verification analytics are not available yet.';
      const k = v.kpis || {};
      const days = k.median_days_to_first;
      return `${pct(k.verification_rate)} of certificates have been verified at least once`
        + (days != null ? `, typically ${nf(days)} days after issue.` : '.');
    },
    get leadDelivery() {
      const o = this.data?.operations;
      if (!o) return '';
      const t = o.turnaround || {};
      return t.p50_hours != null
        ? `Half of endorsements are decided within ${nf(t.p50_hours)} hours; the slowest tenth take over ${nf(t.p90_hours)}.`
        : 'No endorsements have been decided in this window.';
    },
    get leadIntegrity() {
      const i = this.data?.integrity;
      if (!i) return '';
      if (!i.total_attempts) return 'No verification attempts in this window.';
      return `${nf(i.total_attempts)} verification attempts, ${pct(i.failure_rate)} of which resolved to nothing`
        + (i.bot_share > 0 ? ` and ${pct(i.bot_share)} from bots.` : '.');
    },

    // ---- derived view data ----
    get kpiTiles() {
      const kpis = this.data?.executive?.kpis || {};
      return Object.entries(KPI_META).map(([key, meta], i) => {
        const k = kpis[key] || {};
        return {
          key, label: meta.label,
          value: meta.fmt(k.value),
          delta: k.delta,
          deltaText: k.delta == null ? '' : `${k.delta > 0 ? '▲' : (k.delta < 0 ? '▼' : '•')} ${nf(Math.abs(k.delta))} vs prev.`,
          dir: k.delta > 0 ? 'up' : (k.delta < 0 ? 'down' : ''),
          // Telemetry artifacts: the module's own tag + a grid reference.
          code: meta.code,
          raw: k.value == null ? '--' : k.value,
          grid: `R${String(i + 1).padStart(2, '0')}·C${String((i % 3) + 1).padStart(2, '0')}`,
        };
      });
    },
    get topTrainings() { return this.data?.executive?.top_trainings || []; },
    get topVerified() { return this.data?.executive?.verification?.top_verified || []; },
    get verificationKpis() {
      const k = this.data?.executive?.verification?.kpis;
      if (!k) return [];
      return [
        { key: 'rate', label: 'Certificates ever verified', value: pct(k.verification_rate), code: 'VFY_RATE', raw: k.verification_rate, grid: 'R01·C01' },
        { key: 'count', label: 'Total verifications', value: nf(k.verifications), code: 'PING_CNT', raw: k.verifications, grid: 'R01·C02' },
        { key: 'uniq', label: 'Unique verifiers', value: nf(k.unique_verifiers), code: 'SRC_UNIQ', raw: k.unique_verifiers, grid: 'R01·C03' },
        {
          key: 'days',
          label: 'Median days to first check',
          value: k.median_days_to_first == null ? '—' : nf(k.median_days_to_first),
          code: 'T_FIRST',
          raw: k.median_days_to_first == null ? '--' : k.median_days_to_first,
          grid: 'R02·C01',
        },
      ];
    },
    get targetPrograms() { return this.data?.targets?.programs || []; },
    get targetStatusMeta() { return P.targetStatus(this.theme); },
    statusFor(status) { const m = P.targetStatus(this.theme); return m[status] || m.no_target; },

    /** Filters the Pace chapter cannot honor without misrepresenting a target. */
    get ignoredTargetFilters() {
      const labels = { gender: 'gender', age_band: 'age band', province: 'province', municipality: 'municipality' };
      return (this.data?.targets?.ignored_filters || []).map((k) => labels[k] || k);
    },

    // ---- rendering ----
    renderOpened() {
      if (!this.data) return;
      Object.keys(this.opened).forEach((id) => this.renderChapter(id));
      this.resizeAll();
    },

    renderChapter(id) {
      if (!this.data) return;
      const ex = this.data.executive;
      const ops = this.data.operations;

      switch (id) {
        case 'reach':
          if (ex) this.renderMap();
          break;

        case 'who': {
          if (!ex) break;
          this.drawData('age', ex.age_pyramid,
            () => P.agePyramid(ex.age_pyramid, this.mode()),
            'No participants match these filters.');
          const prog = this.drawData('program', ex.program_mix,
            () => P.programMix(ex.program_mix, this.mode()),
            'No programs recorded for the selected date range.');
          if (prog) {
            prog.off('click');
            prog.on('click', (p) => { const code = p.data?.code ?? p.name; if (code) this.setFilter('program', code); });
          }
          break;
        }

        case 'pace': {
          if (!ex) break;
          const trend = this.drawData('trend', ex.monthly_trend,
            () => P.monthlyTrend(ex.monthly_trend, this.mode()),
            'No certificates issued in the selected date range.');
          if (trend && ex.monthly_trend?.length > 1) this.bindTrendBrush(trend);
          const programs = this.targetPrograms.filter((p) => p.certificates.target != null);
          if (programs.length) this.draw('targets', P.targetBullets(programs, this.mode()), programs);
          break;
        }

        case 'proof': {
          const v = ex?.verification;
          if (!v?.available) break;
          this.drawData('verify-trend', v.trend,
            () => P.verificationTrend(v.trend, this.mode()),
            'No verifications recorded in the selected date range.');
          this.drawData('verify-devices', v.ua_mix,
            () => P.verificationDevices(v.ua_mix, this.mode()),
            'No verification devices recorded yet.');
          break;
        }

        case 'delivery':
          if (!ops) break;
          this.drawData('pipeline', ops.pipeline,
            () => P.pipeline(ops.pipeline, this.mode()),
            'No endorsements submitted in the selected date range.');
          this.drawData('errors', ops.error_trend,
            () => P.errorTrend(ops.error_trend, this.mode()),
            'No rejections or generation errors — nothing to plot.');
          this.drawData('email', ops.email_delivery,
            () => P.emailDelivery(ops.email_delivery, this.mode()),
            'No certificate emails have been attempted yet.');
          this.drawData('claim', ops.claim_funnel?.people ? ops.claim_funnel : null,
            () => P.claimFunnel(ops.claim_funnel, this.mode()),
            'No recipients match these filters.');
          this.drawData('intake', ops.intake_conversion,
            () => P.intakeConversion(ops.intake_conversion, this.mode()),
            'No intake events with submissions yet.');
          break;

        case 'integrity': {
          const i = this.data.integrity;
          if (!i) break;
          this.drawData('integrity-outcomes', i.outcomes,
            () => P.integrityOutcomes(i.outcomes, this.mode()),
            'No verification attempts recorded for the selected date range.');
          this.drawData('integrity-trend', i.trend,
            () => P.integrityTrend(i.trend, this.mode()),
            'No verification attempts recorded for the selected date range.');
          break;
        }
      }
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
        const inst = this.draw('map', P.choropleth(geo.province, 'caraga-prov', this.mode(), { nameProperty: 'adm2_en' }), geo.province);
        if (inst) this.bindMapClick(inst, 'province');
      } else {
        const feats = (this.geo.muni?.features || []).filter((f) => String(f.properties.adm2_psgc) === String(this.drill.code));
        const mapName = `caraga-muni-${this.drill.code}`;
        echarts.registerMap(mapName, { type: 'FeatureCollection', features: feats });
        const rows = geo.municipality.filter((m) => String(m.province_code) === String(this.drill.code));
        const inst = this.draw('map', P.choropleth(rows, mapName, this.mode(), { nameProperty: 'adm3_en' }), rows);
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
    // stash the row data for CSV export. Returns the instance (or null when the
    // panel is not in the DOM — an unopened chapter, or one hidden by role).
    draw(id, option, data) {
      const el = this.$root.querySelector(`[data-panel="${id}"]`);
      if (!el) return null;
      let inst = this.charts[id];
      if (!inst) { inst = makeChart(el, HUD_THEME[this.theme]); this.charts[id] = inst; }
      inst.__data = data;
      inst.setOption(option, true);
      return inst;
    },
    /**
     * Draw `rows` through `build`, or a styled empty-state placeholder when
     * there is nothing to plot. A blank panel is indistinguishable from a
     * broken one, so every data-driven panel goes through here.
     */
    drawData(id, rows, build, message) {
      const isEmpty = rows == null
        || (Array.isArray(rows) && rows.length === 0)
        || (!Array.isArray(rows) && typeof rows === 'object' && Object.keys(rows).length === 0);

      if (isEmpty) return this.draw(id, P.emptyState(message, this.mode()), []);
      return this.draw(id, build(), rows);
    },

    resizeAll() { Object.values(this.charts).forEach((c) => { try { c.resize(); } catch (e) { /* noop */ } }); },
    mode() { return this.theme; },
    fmtNum(v) { return nf(v); },
    fmtPct(v) { return pct(v); },

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
