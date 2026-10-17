import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// ECharts is heavy (~280 KB gzip), so the analytics components are loaded on
// demand only on the two pages that use them. Every other page (login, verify,
// intake, …) ships just Alpine.
async function boot() {
  if (document.querySelector('[data-analytics-dashboard]')) {
    const { default: analyticsDashboard } = await import('./analytics/dashboard');
    Alpine.data('analyticsDashboard', analyticsDashboard);
  }
  if (document.querySelector('[data-impact-atlas]')) {
    const { default: impactAtlas } = await import('./analytics/atlas');
    Alpine.data('impactAtlas', impactAtlas);
  }
  Alpine.start();
}

boot();
