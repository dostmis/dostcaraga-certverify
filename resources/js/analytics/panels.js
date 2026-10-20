// Pure ECharts option builders. No DOM, no fetch — given data + mode, return an
// option object. Interaction/cross-filtering is wired in dashboard.js.
//
// Three house rules every builder here follows:
//
//  1. Blocks are a 15–25% fill behind a 100% 1px stroke (see `soft`), never a
//     flooded solid. `fill()` below returns that pair.
//  2. Data labels never sit on top of a mark. They go outside the bar, or they
//     are dropped in favour of the hover tooltip — which every chart has.
//  3. Anything with a semantic meaning (success / failure / pending / neutral /
//     inactive) takes its color from `semantic(mode)`, not from a categorical
//     slot, so the same idea is never two different colors across chapters.
import { HUD, soft, hudInk, FONT_UI, FONT_MONO } from './echarts';
import { nf } from './util';

const { PALETTE, SEQUENTIAL } = HUD;
const { semantic } = HUD;

const AGE_ORDER = ['<18', '18-24', '25-34', '35-44', '45-59', '60+', 'Unknown'];
const GRID = { left: 8, right: 16, top: 18, bottom: 8, containLabel: true };

/** Soft fill + solid stroke for a block mark. */
const fill = (hex, alpha = 0.22) => ({ color: soft(hex, alpha), borderColor: hex, borderWidth: 1 });

const labelStyle = (mode, extra = {}) => ({
  color: hudInk(mode).secondary, fontFamily: FONT_UI, fontSize: 11, ...extra,
});

/**
 * A styled placeholder for a panel with nothing to show. Keeps the axes and
 * gridlines so the panel still reads as a chart rather than a broken box.
 */
export function emptyState(message, mode) {
  const ink = hudInk(mode);
  return {
    grid: { ...GRID, top: 20, bottom: 20 },
    xAxis: { type: 'category', data: [], axisLine: { lineStyle: { color: ink.axis } } },
    yAxis: { type: 'value', splitLine: { lineStyle: { color: ink.split, type: 'dashed' } } },
    graphic: [{
      type: 'group',
      left: 'center',
      top: 'middle',
      children: [
        {
          type: 'text',
          style: {
            text: 'NO DATA', fill: ink.muted, font: `600 10px ${FONT_MONO}`,
            textAlign: 'center', textVerticalAlign: 'middle',
          },
          top: -14,
        },
        {
          type: 'text',
          style: {
            text: message, fill: ink.secondary, font: `400 12px ${FONT_UI}`,
            textAlign: 'center', textVerticalAlign: 'middle', width: 260, overflow: 'break',
          },
          top: 4,
        },
      ],
    }],
    series: [],
  };
}

/**
 * Holographic vector map: dark landmass, accent coastlines, magnitude from the
 * validated single-hue ramp.
 */
export function choropleth(rows, mapName, mode, { max, nameProperty = 'name' } = {}) {
  const ink = hudInk(mode);
  const data = rows.map((r) => ({ name: r.name, value: Number(r.total) }));
  const maxVal = max ?? Math.max(1, ...data.map((d) => d.value));
  const accent = PALETTE[mode][0];
  const base = mode === 'dark' ? '#10151C' : '#F1F5F9';

  return {
    tooltip: {
      trigger: 'item',
      formatter: (p) => `<b>${p.name}</b><br/>${Number.isFinite(p.value) ? nf(p.value) : 0} certificates`,
    },
    visualMap: {
      min: 0,
      max: maxVal,
      left: 'left',
      bottom: 8,
      calculable: true,
      inRange: { color: SEQUENTIAL[mode] },
      textStyle: { color: ink.secondary, fontFamily: FONT_UI, fontSize: 11 },
      borderColor: ink.axis,
      borderWidth: 1,
      backgroundColor: mode === 'dark' ? 'rgba(13,17,23,.6)' : 'rgba(255,255,255,.75)',
      padding: [6, 8],
      itemWidth: 11,
      itemHeight: 104,
    },
    series: [{
      type: 'map',
      map: mapName,
      // nameProperty must be on the series — the faeldon GeoJSON names regions
      // via adm2_en/adm3_en, not the default `name`, so data would never match
      // and every region tooltips as NaN without this.
      nameProperty,
      roam: false,
      // Regions with no data stay as outlines rather than holes.
      itemStyle: { areaColor: base, borderColor: soft(accent, 0.55), borderWidth: 0.8 },
      emphasis: {
        label: { show: false },
        itemStyle: { areaColor: accent, borderColor: accent, borderWidth: 1.4 },
      },
      select: { itemStyle: { areaColor: soft(accent, 0.75) }, label: { show: false } },
      data,
    }],
  };
}

export function agePyramid(rows, mode) {
  const male = AGE_ORDER.map((b) => sum(rows, (r) => r.age_band === b && r.gender === 'Male'));
  const female = AGE_ORDER.map((b) => sum(rows, (r) => r.age_band === b && r.gender === 'Female'));
  const [m, f] = [PALETTE[mode][0], PALETTE[mode][3]];

  return {
    legend: { bottom: 0, data: ['Male', 'Female'] },
    grid: { ...GRID, bottom: 34 },
    tooltip: {
      trigger: 'axis', axisPointer: { type: 'shadow' },
      formatter: (ps) => `<b>${ps[0].axisValue}</b><br/>`
        + ps.map((p) => `${p.seriesName}: ${nf(Math.abs(p.value))}`).join('<br/>'),
    },
    xAxis: { type: 'value', axisLabel: { formatter: (v) => nf(Math.abs(v)) } },
    yAxis: { type: 'category', data: AGE_ORDER },
    // Diverging bars carry their values in the tooltip only: labels inside the
    // bars collided with the stack boundary at narrow widths.
    series: [
      { name: 'Male', type: 'bar', stack: 'age', itemStyle: { ...fill(m), borderRadius: [3, 0, 0, 3] }, data: male.map((v) => -v) },
      { name: 'Female', type: 'bar', stack: 'age', itemStyle: { ...fill(f), borderRadius: [0, 3, 3, 0] }, data: female },
    ],
  };
}

export function programMix(rows, mode) {
  const sorted = [...rows].sort((a, b) => a.total - b.total);
  const c = PALETTE[mode][0];
  return {
    grid: { ...GRID, left: 8, right: 52 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (ps) => `<b>${ps[0].name}</b><br/>${nf(ps[0].value)} certificates` },
    xAxis: { type: 'value' },
    yAxis: { type: 'category', data: sorted.map((r) => r.program_code), axisLabel: { width: 150, overflow: 'truncate' } },
    series: [{
      type: 'bar',
      itemStyle: { ...fill(c), borderRadius: [0, 3, 3, 0] },
      // Outside the bar, so the number never crosses the stroke.
      label: { show: true, position: 'right', distance: 6, formatter: (p) => nf(p.value), ...labelStyle(mode) },
      labelLayout: { hideOverlap: true },
      data: sorted.map((r) => ({ value: Number(r.total), code: r.program_code })),
    }],
  };
}

export function monthlyTrend(rows, mode) {
  const labels = rows.map((r) => `${r.training_year}-${String(r.training_month).padStart(2, '0')}`);
  const c = PALETTE[mode][0];
  const single = rows.length === 1;

  return {
    grid: { ...GRID, bottom: single ? 16 : 50 },
    tooltip: { trigger: 'axis', formatter: (ps) => `<b>${ps[0].axisValue}</b><br/>${nf(ps[0].value)} certificates` },
    xAxis: { type: 'category', data: labels, boundaryGap: single },
    yAxis: { type: 'value' },
    // A one-point range has nothing to brush, and the slider would imply
    // otherwise.
    dataZoom: single ? [] : [{ type: 'slider', height: 16, bottom: 14, brushSelect: true }],
    series: [single
      ? {
        type: 'bar', barMaxWidth: 46, itemStyle: { ...fill(c, 0.25), borderRadius: [3, 3, 0, 0] },
        label: { show: true, position: 'top', formatter: (p) => nf(p.value), ...labelStyle(mode) },
        data: rows.map((r) => Number(r.total)),
      }
      : {
        type: 'line', smooth: true, symbol: 'circle', symbolSize: 8, showSymbol: true,
        color: c, lineStyle: { width: 2 }, areaStyle: { color: soft(c, 0.15) },
        data: rows.map((r) => Number(r.total)),
      }],
  };
}

export function pipeline(rows, mode) {
  const S = semantic(mode);
  // Semantic roles, not categorical slots.
  const OUTCOME = {
    approved: S.success,
    rejected: S.failure,
    pending: S.pending,
    generating: S.neutral,
  };
  return {
    grid: { ...GRID, top: 28 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (ps) => `<b>${ps[0].name}</b><br/>${nf(ps[0].value)} endorsements` },
    xAxis: { type: 'category', data: rows.map((r) => r.outcome) },
    yAxis: { type: 'value' },
    series: [{
      type: 'bar',
      barMaxWidth: 54,
      label: { show: true, position: 'top', distance: 5, formatter: (p) => nf(p.value), ...labelStyle(mode) },
      data: rows.map((r) => {
        const hue = OUTCOME[r.outcome] || S.inactive;
        return { value: Number(r.total), name: r.outcome, itemStyle: { ...fill(hue), borderRadius: [3, 3, 0, 0] } };
      }),
    }],
  };
}

export function errorTrend(rows, mode) {
  const S = semantic(mode);
  const single = rows.length === 1;
  const series = (name, hue, pick) => (single
    ? {
      name, type: 'bar', barMaxWidth: 34, itemStyle: { ...fill(hue, 0.25), borderRadius: [3, 3, 0, 0] },
      data: rows.map(pick),
    }
    : {
      name, type: 'line', smooth: true, symbol: 'circle', symbolSize: 8, showSymbol: true,
      color: hue, lineStyle: { width: 2 }, data: rows.map(pick),
    });

  return {
    legend: { bottom: 0, data: ['Rejected', 'Generation errors'] },
    grid: { ...GRID, bottom: 34 },
    tooltip: { trigger: 'axis' },
    xAxis: { type: 'category', data: rows.map((r) => r.month), boundaryGap: single },
    yAxis: { type: 'value' },
    series: [
      series('Rejected', S.failure, (r) => Number(r.rejected)),
      series('Generation errors', S.pending, (r) => Number(r.generation_errors)),
    ],
  };
}

export function emailDelivery(rows, mode) {
  const S = semantic(mode);
  // Delivery state -> semantic role. Previously assigned by row index, but rows
  // arrive sorted by volume, so the colors tracked *rank* and a status could
  // change color when the data shifted. Color follows the entity.
  const EMAIL = {
    sent: S.success,
    queued: S.pending,
    failed: S.failure,
    skipped_no_email: S.inactive,
    skipped_invalid_email: S.alt,
    none: S.inactive,
  };

  return {
    legend: { bottom: 0 },
    grid: { ...GRID, bottom: 34 },
    // Stacked segments are narrow and labels inside them overlapped the
    // boundaries; the legend names them and the tooltip carries the numbers.
    tooltip: { trigger: 'item', formatter: (p) => `<b>${p.seriesName}</b><br/>${nf(p.value)} certificates` },
    xAxis: { type: 'value' },
    yAxis: { type: 'category', data: ['Delivery'] },
    series: rows.map((r) => {
      const hue = EMAIL[r.status] || S.alt;
      return {
        name: r.status, type: 'bar', stack: 'email',
        itemStyle: { ...fill(hue, 0.25) },
        emphasis: { focus: 'series' },
        data: [Number(r.total)],
      };
    }),
  };
}

export function claimFunnel(funnel, mode) {
  const S = semantic(mode);
  return {
    grid: { ...GRID, top: 28 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (ps) => `<b>${ps[0].name}</b><br/>${nf(ps[0].value)} people` },
    xAxis: { type: 'category', data: ['People reached', 'Claimed', 'Dormant'] },
    yAxis: { type: 'value' },
    series: [{
      type: 'bar',
      barMaxWidth: 54,
      label: { show: true, position: 'top', distance: 5, formatter: (p) => nf(p.value), ...labelStyle(mode) },
      data: [
        // Neutral total, success, then pending — never green beside amber,
        // which is the classic deutan collision.
        { value: funnel.people, itemStyle: { ...fill(S.neutral), borderRadius: [3, 3, 0, 0] } },
        { value: funnel.claimed, itemStyle: { ...fill(S.success), borderRadius: [3, 3, 0, 0] } },
        { value: funnel.dormant, itemStyle: { ...fill(S.alt), borderRadius: [3, 3, 0, 0] } },
      ],
    }],
  };
}

export function intakeConversion(rows, mode) {
  const S = semantic(mode);
  return {
    legend: { bottom: 0, data: ['Submissions', 'Converted'] },
    grid: { ...GRID, bottom: 34 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    xAxis: { type: 'category', data: rows.map((r) => `Event ${r.event_id}`) },
    yAxis: { type: 'value' },
    series: [
      { name: 'Submissions', type: 'bar', itemStyle: { ...fill(S.neutral), borderRadius: [3, 3, 0, 0] }, data: rows.map((r) => Number(r.submissions)) },
      { name: 'Converted', type: 'bar', itemStyle: { ...fill(S.success), borderRadius: [3, 3, 0, 0] }, data: rows.map((r) => Number(r.converted)) },
    ],
  };
}

// ---- verification (the Proof chapter) --------------------------------------

export function verificationTrend(rows, mode) {
  const S = semantic(mode);
  const single = rows.length === 1;

  // One data point draws no line, so it becomes a labelled bar with the axes
  // and gridlines kept — otherwise the panel looks empty when it is not.
  if (single) {
    return {
      legend: { bottom: 0, data: ['Verifications', 'Unique verifiers'] },
      grid: { ...GRID, bottom: 34 },
      tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
      xAxis: { type: 'category', data: rows.map((r) => r.date), boundaryGap: true },
      yAxis: { type: 'value', minInterval: 1 },
      series: [
        {
          name: 'Verifications', type: 'bar', barMaxWidth: 38,
          itemStyle: { ...fill(S.neutral, 0.25), borderRadius: [3, 3, 0, 0] },
          label: { show: true, position: 'top', formatter: (p) => nf(p.value), ...labelStyle(mode) },
          data: rows.map((r) => Number(r.total)),
        },
        {
          name: 'Unique verifiers', type: 'bar', barMaxWidth: 38,
          itemStyle: { ...fill(S.alt, 0.25), borderRadius: [3, 3, 0, 0] },
          label: { show: true, position: 'top', formatter: (p) => nf(p.value), ...labelStyle(mode) },
          data: rows.map((r) => Number(r.unique_verifiers)),
        },
      ],
    };
  }

  return {
    legend: { bottom: 0, data: ['Verifications', 'Unique verifiers'] },
    grid: { ...GRID, bottom: 34 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'line' } },
    xAxis: { type: 'category', data: rows.map((r) => r.date), boundaryGap: false },
    yAxis: { type: 'value', minInterval: 1 },
    series: [
      {
        name: 'Verifications', type: 'line', smooth: true, symbol: 'circle', symbolSize: 8, showSymbol: true,
        color: S.neutral, lineStyle: { width: 2 }, areaStyle: { color: soft(S.neutral, 0.15) },
        data: rows.map((r) => Number(r.total)),
      },
      {
        name: 'Unique verifiers', type: 'line', smooth: true, symbol: 'circle', symbolSize: 8, showSymbol: true,
        color: S.alt, lineStyle: { width: 2 },
        data: rows.map((r) => Number(r.unique_verifiers)),
      },
    ],
  };
}

const UA_LABEL = { mobile: 'Mobile / QR scan', desktop: 'Desktop', bot: 'Bot / crawler', unknown: 'Unknown' };
const UA_ORDER = ['mobile', 'desktop', 'bot', 'unknown'];

export function verificationDevices(rows, mode) {
  const S = semantic(mode);
  const sorted = [...rows].sort((a, b) => Number(a.total) - Number(b.total));
  // Bots are failure-coded per the semantic standard; the rest are neutral
  // volume in fixed categorical slots (never cycled by rank).
  const hueFor = (ua) => (ua === 'bot' ? S.failure : PALETTE[mode][UA_ORDER.indexOf(ua) === 0 ? 0 : 5]);

  return {
    grid: { ...GRID, left: 8, right: 52 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (ps) => `<b>${ps[0].name}</b><br/>${nf(ps[0].value)} verifications` },
    xAxis: { type: 'value', minInterval: 1 },
    yAxis: { type: 'category', data: sorted.map((r) => UA_LABEL[r.ua_class] || r.ua_class), axisLabel: { width: 132, overflow: 'truncate' } },
    series: [{
      type: 'bar',
      barMaxWidth: 26,
      label: { show: true, position: 'right', distance: 6, formatter: (p) => nf(p.value), ...labelStyle(mode) },
      labelLayout: { hideOverlap: true },
      data: sorted.map((r) => ({ value: Number(r.total), itemStyle: { ...fill(hueFor(r.ua_class)), borderRadius: [0, 3, 3, 0] } })),
    }],
  };
}

// ---- targets (the Pace chapter) --------------------------------------------

/**
 * Status -> semantic hue + icon + word. The word and icon are always rendered
 * in the HTML table beside the chart, so color never carries the state alone.
 */
export function targetStatus(mode) {
  const S = semantic(mode);
  return {
    met: { color: S.success, label: 'Met', icon: '✓' },
    on_track: { color: S.success, label: 'On track', icon: '✓' },
    at_risk: { color: S.pending, label: 'At risk', icon: '!' },
    behind: { color: S.failure, label: 'Behind', icon: '▼' },
    too_early: { color: S.inactive, label: 'Too early', icon: '•' },
    no_target: { color: S.inactive, label: 'No target', icon: '–' },
  };
}

/**
 * Bullet chart: the actual as a thin bar inside a muted full-target track, with
 * a marker for where the year-to-date pace should have reached. One shared
 * value axis — never a second scale.
 */
export function targetBullets(programs, mode) {
  const ink = hudInk(mode);
  const META = targetStatus(mode);
  const rows = programs.filter((p) => p.certificates.target != null);
  const track = mode === 'dark' ? 'rgba(255,255,255,.05)' : 'rgba(15,23,42,.05)';

  return {
    grid: { ...GRID, left: 8, right: 62 },
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'shadow' },
      formatter: (ps) => {
        const p = rows[ps[0].dataIndex];
        const c = p.certificates;
        const st = META[c.status] || META.no_target;
        return `<b>${p.program_code}</b><br/>`
          + `Actual ${nf(c.actual)} of ${nf(c.target)} (${nf(c.attainment)}%)<br/>`
          + `Expected by now: ${nf(c.expected_to_date)}<br/>`
          + `Year-end at this pace: ${nf(c.projection)}<br/>`
          + `Status: ${st.label}`;
      },
    },
    xAxis: { type: 'value', max: Math.max(1, ...rows.map((p) => Math.max(p.certificates.target, p.certificates.actual))) },
    yAxis: { type: 'category', data: rows.map((p) => p.program_code), axisLabel: { width: 146, overflow: 'truncate' } },
    series: [
      {
        name: 'Actual', type: 'bar', barWidth: 14,
        showBackground: true, backgroundStyle: { color: track, borderRadius: 3 },
        label: {
          show: true, position: 'right', distance: 6,
          formatter: (p) => `${nf(rows[p.dataIndex].certificates.attainment)}%`,
          ...labelStyle(mode, { fontWeight: 600 }),
        },
        labelLayout: { hideOverlap: true },
        data: rows.map((p) => {
          const hue = (META[p.certificates.status] || META.no_target).color;
          return { value: p.certificates.actual, itemStyle: { ...fill(hue, 0.28), borderRadius: [0, 3, 3, 0] } };
        }),
      },
      {
        // Pace marker: where year-to-date should stand against the target.
        name: 'Expected to date', type: 'scatter', symbol: 'rect', symbolSize: [2, 22],
        itemStyle: { color: ink.primary, opacity: 0.6 },
        data: rows.map((p, i) => [p.certificates.expected_to_date, i]),
        tooltip: { show: false },
      },
    ],
  };
}

// ---- integrity (RD only) ----------------------------------------------------

/**
 * Verification outcomes are states, so they take semantic roles. The stack
 * order below is also the validated adjacency order: valid, malformed,
 * not_found, invalid, revoked keeps green away from red (those two measure
 * ΔE 7.9 deutan when adjacent).
 */
const OUTCOME_ORDER = ['valid', 'malformed', 'not_found', 'invalid', 'revoked'];

function outcomeMeta(mode) {
  const S = semantic(mode);
  return {
    valid: { color: S.success, label: 'Valid' },
    malformed: { color: S.inactive, label: 'Malformed' },
    not_found: { color: S.pending, label: 'Not found' },
    invalid: { color: S.alt, label: 'Invalid' },
    revoked: { color: S.failure, label: 'Revoked' },
  };
}

export function integrityOutcomes(rows, mode) {
  const META = outcomeMeta(mode);
  const sorted = OUTCOME_ORDER.map((o) => rows.find((r) => r.outcome === o)).filter(Boolean);
  return {
    grid: { ...GRID, top: 28 },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (ps) => `<b>${ps[0].name}</b><br/>${nf(ps[0].value)} attempts` },
    xAxis: { type: 'category', data: sorted.map((r) => META[r.outcome]?.label || r.outcome) },
    yAxis: { type: 'value', minInterval: 1 },
    series: [{
      type: 'bar',
      barMaxWidth: 48,
      label: { show: true, position: 'top', distance: 5, formatter: (p) => nf(p.value), ...labelStyle(mode) },
      data: sorted.map((r) => ({
        value: Number(r.total),
        itemStyle: { ...fill(META[r.outcome]?.color || semantic(mode).inactive), borderRadius: [3, 3, 0, 0] },
      })),
    }],
  };
}

export function integrityTrend(rows, mode) {
  const META = outcomeMeta(mode);
  const dates = [...new Set(rows.map((r) => r.date))].sort();
  const present = OUTCOME_ORDER.filter((o) => rows.some((r) => r.outcome === o));
  const single = dates.length === 1;

  return {
    legend: { bottom: 0, data: present.map((o) => META[o].label) },
    grid: { ...GRID, bottom: 34 },
    // Stacked segments carry no inline labels — they collided at small counts.
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    xAxis: { type: 'category', data: dates, boundaryGap: true },
    yAxis: { type: 'value', minInterval: 1 },
    series: present.map((outcome) => ({
      name: META[outcome].label,
      type: 'bar',
      stack: 'outcome',
      barMaxWidth: single ? 56 : undefined,
      itemStyle: { ...fill(META[outcome].color, 0.25) },
      emphasis: { focus: 'series' },
      data: dates.map((d) => {
        const hit = rows.find((r) => r.date === d && r.outcome === outcome);
        return hit ? Number(hit.total) : 0;
      }),
    })),
  };
}

function sum(rows, pred) {
  return rows.reduce((s, r) => (pred(r) ? s + Number(r.total) : s), 0);
}
