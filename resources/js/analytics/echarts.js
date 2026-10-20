// Tree-shaken ECharts core plus the registered themes.
//
// There are deliberately TWO palette families here, for two audiences:
//
//   `certify` / `certify-dark`          — the public /impact Reach Atlas, a
//       light page that follows the OS via currentMode(). Uses the validated
//       default data-viz palette. Do not repaint it for the admin aesthetic.
//   `certify-hud-dark` / `-hud-light`   — the admin Impact Terminal, which has
//       its own user-facing theme toggle (HUD dark / high-contrast light).
//
// panels.js (dashboard-only) consumes HUD; atlas.js (public-only) consumes the
// default exports. Keep that boundary — collapsing it is what puts terminal
// colors on a white public page.
import * as echarts from 'echarts/core';
import {
  BarChart,
  LineChart,
  PieChart,
  MapChart,
  HeatmapChart,
  SankeyChart,
  // The target bullets use a thin rect scatter mark for the "expected by now"
  // pace indicator.
  ScatterChart,
} from 'echarts/charts';
import {
  TitleComponent,
  TooltipComponent,
  GridComponent,
  LegendComponent,
  VisualMapComponent,
  DataZoomComponent,
  ToolboxComponent,
  CalendarComponent,
  MarkLineComponent,
  GraphicComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
  BarChart, LineChart, PieChart, MapChart, HeatmapChart, SankeyChart, ScatterChart,
  TitleComponent, TooltipComponent, GridComponent, LegendComponent,
  VisualMapComponent, DataZoomComponent, ToolboxComponent, CalendarComponent,
  MarkLineComponent, GraphicComponent, CanvasRenderer,
]);

// ---- default palette (public atlas only) ----------------------------------
export const PALETTE = {
  light: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'],
  dark: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'],
};
export const SEQUENTIAL = {
  light: ['#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95', '#0d366b'],
  dark: ['#0d366b', '#184f95', '#256abf', '#3987e5', '#6da7ec', '#9ec5f4', '#cde2fb'],
};
export const STATUS = {
  good: '#008300',
  warning: '#eda100',
  serious: '#eb6834',
  critical: '#e34948',
  muted: '#898781',
};

// ---- Impact Terminal palettes (admin) -------------------------------------
//
// Every set below was run through the data-viz validator against the surface it
// actually renders on, not eyeballed:
//
//   dark categorical (8) @#161B22 .. CVD ΔE 15.3, normal-vision 23.0 — PASS
//   dark status (5) ................ CVD ΔE 20.6, normal-vision 21.3 — PASS
//   dark ramp (6) .................. all ordinal checks PASS
//   light categorical (8) @#FFFFFF . CVD ΔE 8.6, normal-vision 24.3 — PASS
//   light status (5) ............... CVD ΔE 11.0, normal-vision 17.6 — PASS
//   light ramp (5) ................. all ordinal checks PASS
//
// The slot ORDER is the colorblind-safety mechanism, not cosmetic. Cyan beside
// violet measures 3.7 deutan and teal beside pink 3.8 — both invisible pairs —
// so those hues are deliberately held apart, and pink is absent from the light
// series set entirely. Re-run scripts/validate_palette.js from the dataviz
// skill after ANY change here.
//
// Known accepted exception: the dark categorical set sits above the dark-mode
// lightness band (these are Tailwind-400-level hues on a dark surface, which is
// the requested look). Contrast-vs-surface passes everywhere.
const HUD_CATEGORICAL = {
  dark: ['#38BDF8', '#F87171', '#A3E635', '#C084FC', '#FBBF24', '#2DD4BF', '#FB923C', '#E879F9'],
  light: ['#0284C7', '#DC2626', '#15803D', '#7C3AED', '#D97706', '#0D9488', '#C2410C', '#4338CA'],
};

const HUD_SEQUENTIAL = {
  dark: ['#2C5478', '#1C6B9C', '#2B96CC', '#4FC6FA', '#8EDAFC', '#C9EEFE'],
  light: ['#55BCEC', '#2399D4', '#0C7BB8', '#06608F', '#044668'],
};

// Reserved status hues — never a series color.
//
// `serious` (an invalid certificate) is violet rather than magenta or orange:
// magenta beside red measured 10.1 in normal vision and orange beside amber
// 10.5, both unreadable even with full color vision, and no amount of
// secondary encoding excuses that.
const HUD_STATUS = {
  dark: { good: '#4ADE80', warning: '#FBBF24', serious: '#C084FC', critical: '#F87171', muted: '#6B7280' },
  light: { good: '#059669', warning: '#D97706', serious: '#7C3AED', critical: '#DC2626', muted: '#9CA3AF' },
};

/**
 * Semantic roles, applied consistently across all six chapters:
 *   success  green  — validated / sent / approved
 *   neutral  blue   — plain counts, volume, totals
 *   failure  red    — errors, rejections, bots, revoked
 *   pending  amber  — in progress, queued, awaiting a decision
 *   inactive gray   — skipped, dormant, noise
 * Anything carrying one of these meanings must take its color from here rather
 * than from a categorical slot, so the same idea is never two colors.
 */
export function semantic(mode) {
  const s = HUD_STATUS[mode];
  return {
    success: s.good,
    neutral: HUD_CATEGORICAL[mode][0],
    failure: s.critical,
    pending: s.warning,
    inactive: s.muted,
    alt: s.serious,
  };
}

export const HUD = {
  PALETTE: HUD_CATEGORICAL,
  SEQUENTIAL: HUD_SEQUENTIAL,
  STATUS: HUD_STATUS,
  semantic,
};

/**
 * Translucent fill for a solid hue. Chart blocks are drawn as a 15–25% fill
 * behind a 100% 1px stroke of the same color, which keeps large areas from
 * flooding the panel while the outline still reads as crisp.
 */
export function soft(hex, alpha = 0.22) {
  const h = hex.replace('#', '');
  const full = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
  const n = parseInt(full, 16);
  return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${alpha})`;
}

// ---- type -----------------------------------------------------------------
// Mono is reserved for telemetry/system metadata. Everything a person reads as
// prose or as a number — axis ticks, legends, tooltips, data labels — is UI
// sans, which is materially more legible at small sizes.
export const FONT_UI = 'Inter, system-ui, -apple-system, "Segoe UI", sans-serif';
export const FONT_MONO = '"JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace';

const SURFACE = { light: '#ffffff', dark: '#1a1a19' };
const INK = {
  light: { primary: '#0f172a', secondary: '#52514e', axis: '#cbd5e1', split: '#eef2f7' },
  dark: { primary: '#f8fafc', secondary: '#c3c2b7', axis: '#3f3f3c', split: '#2a2a28' },
};

/** The public atlas theme (unchanged). */
function themeFor(mode) {
  const ink = INK[mode];
  return {
    color: PALETTE[mode],
    backgroundColor: 'transparent',
    textStyle: { fontFamily: 'Figtree, system-ui, "Segoe UI", sans-serif', color: ink.primary },
    title: { textStyle: { color: ink.primary, fontWeight: 700 }, subtextStyle: { color: ink.secondary } },
    legend: { textStyle: { color: ink.secondary }, inactiveColor: ink.axis, icon: 'roundRect' },
    tooltip: {
      backgroundColor: SURFACE[mode],
      borderColor: ink.split,
      textStyle: { color: ink.primary, fontSize: 12.5 },
      extraCssText: 'box-shadow:0 6px 24px rgba(15,23,42,.14);border-radius:10px;',
    },
    categoryAxis: {
      axisLine: { lineStyle: { color: ink.axis } },
      axisTick: { show: false },
      axisLabel: { color: ink.secondary },
      splitLine: { show: false },
    },
    valueAxis: {
      axisLine: { show: false },
      axisTick: { show: false },
      axisLabel: { color: ink.secondary },
      splitLine: { lineStyle: { color: ink.split } },
    },
  };
}

const HUD_INK = {
  dark: {
    primary: '#F3F4F6', secondary: '#9AA4B2', muted: '#6B7280',
    axis: '#30363D', split: 'rgba(48,54,61,.75)',
    tooltipBg: 'rgba(22,27,34,.97)', tooltipBorder: '#30363D',
    shadow: '0 8px 28px rgba(0,0,0,.5)',
  },
  light: {
    primary: '#0F172A', secondary: '#64748B', muted: '#94A3B8',
    axis: '#E2E8F0', split: '#EEF2F7',
    tooltipBg: 'rgba(255,255,255,.98)', tooltipBorder: '#E2E8F0',
    shadow: '0 4px 16px rgba(15,23,42,.12)',
  },
};

/** The admin Impact Terminal theme, in either mode. */
function hudTheme(mode) {
  const ink = HUD_INK[mode];
  return {
    color: HUD_CATEGORICAL[mode],
    backgroundColor: 'transparent',
    textStyle: { fontFamily: FONT_UI, color: ink.primary },
    title: { textStyle: { color: ink.primary, fontWeight: 600 }, subtextStyle: { color: ink.secondary } },
    legend: {
      textStyle: { color: ink.secondary, fontFamily: FONT_UI, fontSize: 11 },
      inactiveColor: ink.muted,
      icon: 'roundRect',
      itemWidth: 10,
      itemHeight: 10,
      itemGap: 14,
    },
    tooltip: {
      backgroundColor: ink.tooltipBg,
      borderColor: ink.tooltipBorder,
      borderWidth: 1,
      padding: [8, 11],
      textStyle: { color: ink.primary, fontSize: 12, fontFamily: FONT_UI },
      extraCssText: `box-shadow:${ink.shadow};border-radius:8px;`,
      axisPointer: {
        lineStyle: { color: ink.axis },
        crossStyle: { color: ink.axis },
        shadowStyle: { color: mode === 'dark' ? 'rgba(56,189,248,.08)' : 'rgba(2,132,199,.07)' },
      },
    },
    categoryAxis: {
      axisLine: { lineStyle: { color: ink.axis } },
      axisTick: { show: false },
      axisLabel: { color: ink.secondary, fontFamily: FONT_UI, fontSize: 11 },
      splitLine: { show: false },
    },
    // Gridlines are always on for the value axis: a chart with one data point
    // is unreadable without them.
    valueAxis: {
      axisLine: { show: false },
      axisTick: { show: false },
      axisLabel: { color: ink.secondary, fontFamily: FONT_UI, fontSize: 11 },
      splitLine: { show: true, lineStyle: { color: ink.split, type: 'dashed' } },
    },
  };
}

echarts.registerTheme('certify', themeFor('light'));
echarts.registerTheme('certify-dark', themeFor('dark'));
echarts.registerTheme('certify-hud-dark', hudTheme('dark'));
echarts.registerTheme('certify-hud-light', hudTheme('light'));

export const HUD_THEME = { dark: 'certify-hud-dark', light: 'certify-hud-light' };

/** Ink tokens for builders that need to draw their own text/axis colors. */
export function hudInk(mode) {
  return HUD_INK[mode];
}

/**
 * Resolves the viewer's light/dark preference. Used by the public atlas. The
 * admin terminal uses its own explicit toggle instead (dashboard.js `theme`).
 */
export function currentMode() {
  const stamp = document.documentElement.getAttribute('data-theme');
  if (stamp === 'dark') return 'dark';
  if (stamp === 'light') return 'light';
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/**
 * @param {HTMLElement} el
 * @param {string} [theme] registered theme name; defaults to the light/dark
 *   pair the public atlas expects.
 */
export function makeChart(el, theme) {
  const resolved = theme || (currentMode() === 'dark' ? 'certify-dark' : 'certify');
  return echarts.init(el, resolved, { renderer: 'canvas' });
}

export { echarts };
