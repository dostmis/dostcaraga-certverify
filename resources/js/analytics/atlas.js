// Public "Caraga Reach Atlas". Renders only the PII-free, <5-suppressed
// aggregates from /impact/data.json. Registered as Alpine.data('impactAtlas').
import { makeChart, echarts, currentMode, PALETTE, SEQUENTIAL, STATUS } from './echarts';
import { nf } from './util';

const STATUS_GOOD = STATUS.good;

export default function impactAtlas(cfg) {
  return {
    dataUrl: cfg.dataUrl,
    geoMuniUrl: cfg.geoMuniUrl,
    loading: true,
    error: false,
    rows: [],
    funnel: null,
    activity: [],
    charts: {},

    init() {
      new ResizeObserver(() => Object.values(this.charts).forEach((c) => c.resize()))
        .observe(this.$root);
      this.loadGeo().finally(() => this.load());
    },

    get totalVisible() { return this.sum(this.rows); },
    get provinces() { return new Set(this.rows.filter((r) => r.province_code).map((r) => r.province_code)).size; },
    get municipalities() { return new Set(this.rows.filter((r) => r.municipality_code).map((r) => r.municipality_code)).size; },
    get years() { return new Set(this.rows.map((r) => r.training_year)).size; },
    fmt(v) { return nf(v); },

    async loadGeo() {
      try {
        const muni = await fetch(this.geoMuniUrl).then((r) => r.json());
        echarts.registerMap('caraga-muni-all', muni);
      } catch (e) { console.error('[atlas] geo load failed', e); }
    },

    async load() {
      this.loading = true; this.error = false;
      try {
        const res = await fetch(this.dataUrl, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const body = await res.json();
        this.rows = (body.reach || []).map((r) => ({ ...r, cert_count: r.cert_count == null ? 0 : Number(r.cert_count) }));
        this.funnel = body.funnel || null;
        this.activity = (body.activity || []).map((a) => ({ date: a.date, cert_count: Number(a.cert_count) }));
        this.$nextTick(() => this.render());
      } catch (e) { this.error = true; console.error('[atlas] load failed', e); }
      finally { this.loading = false; }
    },

    render() {
      const mode = currentMode();
      this.reachMap(mode);
      this.journeySankey(mode);
      this.activityCalendar(mode);
      this.programMix(mode);
      this.genderParity(mode);
      this.ageDistribution(mode);
    },

    journeySankey(mode) {
      const f = this.funnel || {};
      const v = (n) => (n == null ? 0 : Number(n)); // suppressed (<5) -> 0
      const nodes = [
        { name: 'Intake submissions' },
        { name: 'Certificates issued' },
        { name: 'Claimed accounts' },
        { name: 'Dormant accounts' },
      ];
      const links = [
        { source: 'Intake submissions', target: 'Certificates issued', value: v(f.intakes_converted) },
        { source: 'Certificates issued', target: 'Claimed accounts', value: v(f.claimed) },
        { source: 'Certificates issued', target: 'Dormant accounts', value: v(f.dormant) },
      ].filter((l) => l.value > 0);

      this.draw('journey', {
        tooltip: { trigger: 'item', formatter: (p) => (p.dataType === 'edge'
          ? `${p.data.source} → ${p.data.target}<br/><b>${nf(p.data.value)}</b>`
          : `${p.name}`) },
        series: [{
          type: 'sankey', left: 8, right: 120, top: 10, bottom: 10,
          nodeWidth: 14, nodeGap: 14,
          label: { color: mode === 'dark' ? '#f8fafc' : '#0f172a', fontSize: 12 },
          lineStyle: { color: 'gradient', opacity: 0.45 },
          itemStyle: { borderWidth: 0 },
          data: nodes,
          links: links.length ? links : [{ source: 'Intake submissions', target: 'Certificates issued', value: 0 }],
          color: [PALETTE[mode][0], PALETTE[mode][2], STATUS_GOOD, PALETTE[mode][3]],
        }],
      });
    },

    activityCalendar(mode) {
      const data = this.activity.map((a) => [a.date, a.cert_count]);
      const years = [...new Set(this.activity.map((a) => a.date.slice(0, 4)))].sort();
      const range = years.length ? (years.length === 1 ? years[0] : [years[0], years[years.length - 1]]) : String(new Date().getFullYear());
      const maxVal = Math.max(5, ...data.map((d) => d[1]));
      this.draw('calendar', {
        tooltip: { trigger: 'item', formatter: (p) => `${p.value[0]}<br/><b>${nf(p.value[1])}</b> certificates` },
        visualMap: { min: 0, max: maxVal, calculable: true, orient: 'horizontal', left: 'center', bottom: 0, inRange: { color: SEQUENTIAL[mode] } },
        calendar: {
          top: 30, left: 30, right: 20, cellSize: ['auto', 16], range,
          itemStyle: { borderColor: mode === 'dark' ? '#2a2a28' : '#eef2f7', color: mode === 'dark' ? '#1a1a19' : '#fff' },
          splitLine: { show: false },
          dayLabel: { color: mode === 'dark' ? '#c3c2b7' : '#52514e' },
          monthLabel: { color: mode === 'dark' ? '#c3c2b7' : '#52514e' },
          yearLabel: { show: false },
        },
        series: [{ type: 'heatmap', coordinateSystem: 'calendar', data }],
      });
    },

    reachMap(mode) {
      const byMuni = this.groupSum('municipality_name', (r) => r.municipality_code);
      const data = Object.entries(byMuni).map(([name, value]) => ({ name, value }));
      this.draw('map', {
        tooltip: { trigger: 'item', formatter: (p) => `${p.name}<br/><b>${nf(p.value ?? 0)}</b>` },
        visualMap: { min: 0, max: Math.max(1, ...data.map((d) => d.value)), left: 'left', bottom: 8, calculable: true, inRange: { color: SEQUENTIAL[mode] } },
        series: [{ type: 'map', map: 'caraga-muni-all', nameProperty: 'adm3_en', roam: false, itemStyle: { borderColor: '#fff', borderWidth: 0.6 }, data }],
      });
    },

    programMix(mode) {
      const by = this.groupSum('program_code');
      const entries = Object.entries(by).sort((a, b) => a[1] - b[1]);
      this.draw('program', {
        grid: { left: 8, right: 24, top: 10, bottom: 8, containLabel: true },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        xAxis: { type: 'value' },
        yAxis: { type: 'category', data: entries.map((e) => e[0]), axisLabel: { width: 180, overflow: 'truncate' } },
        series: [{ type: 'bar', color: PALETTE[mode][0], itemStyle: { borderRadius: [0, 4, 4, 0] }, label: { show: true, position: 'right', formatter: (p) => nf(p.value) }, data: entries.map((e) => e[1]) }],
      });
    },

    genderParity(mode) {
      const programs = [...new Set(this.rows.map((r) => r.program_code))];
      const male = programs.map((pr) => this.sum(this.rows.filter((r) => r.program_code === pr && r.gender === 'Male')));
      const female = programs.map((pr) => this.sum(this.rows.filter((r) => r.program_code === pr && r.gender === 'Female')));
      this.draw('parity', {
        legend: { bottom: 0, data: ['Male', 'Female'] },
        grid: { left: 8, right: 16, top: 10, bottom: 34, containLabel: true },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        xAxis: { type: 'category', data: programs, axisLabel: { width: 90, overflow: 'truncate', interval: 0 } },
        yAxis: { type: 'value' },
        series: [
          { name: 'Male', type: 'bar', stack: 'g', color: PALETTE[mode][0], data: male },
          { name: 'Female', type: 'bar', stack: 'g', color: PALETTE[mode][1], data: female },
        ],
      });
    },

    ageDistribution(mode) {
      const order = ['<18', '18-24', '25-34', '35-44', '45-59', '60+', 'Unknown'];
      const by = this.groupSum('age_band');
      this.draw('age', {
        grid: { left: 8, right: 16, top: 10, bottom: 8, containLabel: true },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        xAxis: { type: 'category', data: order },
        yAxis: { type: 'value' },
        series: [{ type: 'bar', color: PALETTE[mode][2], itemStyle: { borderRadius: [4, 4, 0, 0] }, label: { show: true, position: 'top', formatter: (p) => (p.value ? nf(p.value) : '') }, data: order.map((b) => by[b] || 0) }],
      });
    },

    // helpers
    sum(rows) { return rows.reduce((s, r) => s + (r.cert_count || 0), 0); },
    groupSum(key, guard) {
      const out = {};
      for (const r of this.rows) {
        if (guard && !guard(r)) continue;
        const k = r[key];
        if (k == null) continue;
        out[k] = (out[k] || 0) + (r.cert_count || 0);
      }
      return out;
    },
    draw(id, option) {
      const el = this.$root.querySelector(`[data-panel="${id}"]`);
      if (!el) return;
      let inst = this.charts[id];
      if (!inst) { inst = makeChart(el); this.charts[id] = inst; }
      inst.setOption(option, true);
    },
  };
}
