// Tree-shaken ECharts core + the `certify` theme, derived from the validated
// data-viz palette (see dataviz skill references/palette.md). Categorical hues
// are assigned in fixed order and never cycled; sequential (choropleth) uses a
// single blue ramp.
import * as echarts from 'echarts/core';
import {
  BarChart,
  LineChart,
  PieChart,
  MapChart,
  HeatmapChart,
  SankeyChart,
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
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
  BarChart, LineChart, PieChart, MapChart, HeatmapChart, SankeyChart,
  TitleComponent, TooltipComponent, GridComponent, LegendComponent,
  VisualMapComponent, DataZoomComponent, ToolboxComponent, CalendarComponent,
  MarkLineComponent, CanvasRenderer,
]);

// Categorical order (fixed) — light and dark steps of the same eight hues.
export const PALETTE = {
  light: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'],
  dark: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'],
};

// Sequential blue ramp for magnitude (choropleth / heatmap).
export const SEQUENTIAL = {
  light: ['#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95', '#0d366b'],
  dark: ['#0d366b', '#184f95', '#256abf', '#3987e5', '#6da7ec', '#9ec5f4', '#cde2fb'],
};

// Reserved status hues (good / warning / serious / critical) — never a series color.
export const STATUS = {
  good: '#008300',
  warning: '#eda100',
  serious: '#eb6834',
  critical: '#e34948',
};

const SURFACE = { light: '#ffffff', dark: '#1a1a19' };
const INK = {
  light: { primary: '#0f172a', secondary: '#52514e', axis: '#cbd5e1', split: '#eef2f7' },
  dark: { primary: '#f8fafc', secondary: '#c3c2b7', axis: '#3f3f3c', split: '#2a2a28' },
};

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

echarts.registerTheme('certify', themeFor('light'));
echarts.registerTheme('certify-dark', themeFor('dark'));

export function currentMode() {
  const stamp = document.documentElement.getAttribute('data-theme');
  if (stamp === 'dark') return 'dark';
  if (stamp === 'light') return 'light';
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function makeChart(el) {
  const mode = currentMode();
  const chart = echarts.init(el, mode === 'dark' ? 'certify-dark' : 'certify', { renderer: 'canvas' });
  return chart;
}

export { echarts };
