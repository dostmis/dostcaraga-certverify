// Pure ECharts option builders. No DOM, no fetch — given data + mode, return an
// option object. Interaction/cross-filtering is wired in dashboard.js.
import { PALETTE, SEQUENTIAL, STATUS } from './echarts';
import { nf } from './util';

const AGE_ORDER = ['<18', '18-24', '25-34', '35-44', '45-59', '60+', 'Unknown'];
const GRID = { left: 8, right: 16, top: 16, bottom: 8, containLabel: true };

// Outcome -> reserved status color (state, not a series).
const OUTCOME_COLOR = {
  approved: STATUS.good,
  rejected: STATUS.critical,
  pending: STATUS.warning,
  generating: '#2a78d6',
};

export function choropleth(rows, mapName, mode, { max, nameProperty = 'name' } = {}) {
  const data = rows.map((r) => ({ name: r.name, value: Number(r.total) }));
  const maxVal = max ?? Math.max(1, ...data.map((d) => d.value));
  return {
    tooltip: {
      trigger: 'item',
      formatter: (p) => `${p.name}<br/><b>${Number.isFinite(p.value) ? nf(p.value) : 0}</b> certificates`,
    },
    visualMap: {
      min: 0,
      max: maxVal,
      left: 'left',
      bottom: 8,
      calculable: true,
      inRange: { color: SEQUENTIAL[mode] },
      textStyle: { color: mode === 'dark' ? '#c3c2b7' : '#52514e' },
    },
    series: [{
      type: 'map',
      map: mapName,
      // nameProperty must be on the series — the faeldon GeoJSON names regions
      // via adm2_en/adm3_en, not the default `name`, so data would never match
      // and every region tooltips as NaN without this.
      nameProperty,
      roam: false,
      emphasis: { label: { show: false }, itemStyle: { areaColor: PALETTE[mode][3] } },
      itemStyle: { borderColor: mode === 'dark' ? '#3f3f3c' : '#ffffff', borderWidth: 1 },
      data,
    }],
  };
}

export function agePyramid(rows, mode) {
  const male = AGE_ORDER.map((b) => sum(rows, (r) => r.age_band === b && r.gender === 'Male'));
  const female = AGE_ORDER.map((b) => sum(rows, (r) => r.age_band === b && r.gender === 'Female'));
  return {
    legend: { bottom: 0, data: ['Male', 'Female'] },
    grid: { ...GRID, bottom: 32 },
    tooltip: {
      trigger: 'axis', axisPointer: { type: 'shadow' },
      formatter: (ps) => ps.map((p) => `${p.seriesName}: <b>${nf(Math.abs(p.value))}</b>`).join('<br/>'),
    },
    xAxis: { type: 'value', axisLabel: { formatter: (v) => nf(Math.abs(v)) } },
    yAxis: { type: 'category', data: AGE_ORDER },
    series: [
      { name: 'Male', type: 'bar', stack: 'age', color: PALETTE[mode][0], itemStyle: { borderRadius: [4, 0, 0, 4] }, data: male.map((v) => -v) },
      { name: 'Female', type: 'bar', stack: 'age', color: PALETTE[mode][1], itemStyle: { borderRadius: [0, 4, 4, 0] }, data: female },
    ],
  };
}

export function programMix(rows, mode) {
  const sorted = [...rows].sort((a, b) => a.total - b.total);
  return {
    grid: { ...GRID, left: 8 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (ps) => `${ps[0].name}<br/><b>${nf(ps[0].value)}</b>` },
    xAxis: { type: 'value' },
    yAxis: { type: 'category', data: sorted.map((r) => r.program_code), axisLabel: { width: 160, overflow: 'truncate' } },
    series: [{
      type: 'bar', color: PALETTE[mode][0], itemStyle: { borderRadius: [0, 4, 4, 0] },
      label: { show: true, position: 'right', formatter: (p) => nf(p.value), color: mode === 'dark' ? '#c3c2b7' : '#52514e' },
      data: sorted.map((r) => ({ value: Number(r.total), code: r.program_code })),
    }],
  };
}

export function monthlyTrend(rows, mode) {
  const labels = rows.map((r) => `${r.training_year}-${String(r.training_month).padStart(2, '0')}`);
  return {
    grid: { ...GRID, bottom: 48 },
    tooltip: { trigger: 'axis', formatter: (ps) => `${ps[0].axisValue}<br/><b>${nf(ps[0].value)}</b> certificates` },
    xAxis: { type: 'category', data: labels, boundaryGap: false },
    yAxis: { type: 'value' },
    dataZoom: [{ type: 'slider', height: 18, bottom: 12, brushSelect: true }],
    series: [{
      type: 'line', smooth: true, symbol: 'circle', symbolSize: 7, color: PALETTE[mode][0],
      lineStyle: { width: 2 },
      areaStyle: { color: mode === 'dark' ? 'rgba(57,135,229,.18)' : 'rgba(42,120,214,.12)' },
      data: rows.map((r) => Number(r.total)),
    }],
  };
}

export function pipeline(rows, mode) {
  const data = rows.map((r) => ({ value: Number(r.total), name: r.outcome, itemStyle: { color: OUTCOME_COLOR[r.outcome] || PALETTE[mode][6] } }));
  return {
    grid: GRID,
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    xAxis: { type: 'category', data: rows.map((r) => r.outcome) },
    yAxis: { type: 'value' },
    series: [{
      type: 'bar', itemStyle: { borderRadius: [4, 4, 0, 0] },
      label: { show: true, position: 'top', formatter: (p) => nf(p.value) },
      data,
    }],
  };
}

export function errorTrend(rows, mode) {
  return {
    legend: { bottom: 0, data: ['Rejected', 'Generation errors'] },
    grid: { ...GRID, bottom: 32 },
    tooltip: { trigger: 'axis' },
    xAxis: { type: 'category', data: rows.map((r) => r.month), boundaryGap: false },
    yAxis: { type: 'value' },
    series: [
      { name: 'Rejected', type: 'line', smooth: true, symbolSize: 7, color: PALETTE[mode][7], lineStyle: { width: 2 }, data: rows.map((r) => Number(r.rejected)) },
      { name: 'Generation errors', type: 'line', smooth: true, symbolSize: 7, color: PALETTE[mode][1], lineStyle: { width: 2 }, data: rows.map((r) => Number(r.generation_errors)) },
    ],
  };
}

export function emailDelivery(rows, mode) {
  const series = rows.map((r, i) => ({
    name: r.status, type: 'bar', stack: 'email', color: PALETTE[mode][i % PALETTE[mode].length],
    label: { show: Number(r.total) > 0, formatter: (p) => `${p.seriesName}: ${nf(p.value)}` },
    emphasis: { focus: 'series' },
    data: [Number(r.total)],
  }));
  return {
    legend: { bottom: 0 },
    grid: { ...GRID, bottom: 32 },
    tooltip: { trigger: 'item', formatter: (p) => `${p.seriesName}<br/><b>${nf(p.value)}</b>` },
    xAxis: { type: 'value' },
    yAxis: { type: 'category', data: ['Delivery'] },
    series,
  };
}

export function claimFunnel(funnel, mode) {
  return {
    grid: GRID,
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    xAxis: { type: 'category', data: ['People reached', 'Claimed', 'Dormant'] },
    yAxis: { type: 'value' },
    series: [{
      type: 'bar', itemStyle: { borderRadius: [4, 4, 0, 0] },
      label: { show: true, position: 'top', formatter: (p) => nf(p.value) },
      data: [
        { value: funnel.people, itemStyle: { color: PALETTE[mode][0] } },
        { value: funnel.claimed, itemStyle: { color: STATUS.good } },
        { value: funnel.dormant, itemStyle: { color: STATUS.warning } },
      ],
    }],
  };
}

export function intakeConversion(rows, mode) {
  const labels = rows.map((r) => `Event ${r.event_id}`);
  return {
    legend: { bottom: 0, data: ['Submissions', 'Converted'] },
    grid: { ...GRID, bottom: 32 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    xAxis: { type: 'category', data: labels },
    yAxis: { type: 'value' },
    series: [
      { name: 'Submissions', type: 'bar', color: PALETTE[mode][0], itemStyle: { borderRadius: [4, 4, 0, 0] }, data: rows.map((r) => Number(r.submissions)) },
      { name: 'Converted', type: 'bar', color: STATUS.good, itemStyle: { borderRadius: [4, 4, 0, 0] }, data: rows.map((r) => Number(r.converted)) },
    ],
  };
}

function sum(rows, pred) {
  return rows.reduce((s, r) => (pred(r) ? s + Number(r.total) : s), 0);
}
