// Shared formatting + export helpers. Number/date formatting is en-PH.

const NF = new Intl.NumberFormat('en-PH');

export const nf = (v) => (v == null ? '—' : NF.format(v));

export const pct = (v) => (v == null ? '—' : `${NF.format(v)}%`);

const MONTHS = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
export const monthLabel = (year, month) => `${MONTHS[month] || month} ${year}`;

export function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

// Download an array of row-objects as CSV (the per-panel "table view" the
// dataviz relief rule requires).
export function downloadCSV(filename, rows) {
  if (!rows || !rows.length) return;
  const headers = Object.keys(rows[0]);
  const esc = (v) => {
    const s = String(v ?? '');
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const csv = [headers.join(','), ...rows.map((r) => headers.map((h) => esc(r[h])).join(','))].join('\n');
  triggerDownload(filename, new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
}

export function exportPNG(chart, filename) {
  if (!chart) return;
  const url = chart.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: '#ffffff' });
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
}

function triggerDownload(filename, blob) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

// Debounce helper for the single-fetch-per-change rule.
export function debounce(fn, ms = 250) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), ms);
  };
}
