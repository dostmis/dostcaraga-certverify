<style>
  [x-cloak]{display:none!important}
  .an{max-width:1280px;margin:0 auto;color:#0f172a}
  .an-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:16px}
  .an-head h1{font-size:22px;font-weight:800;margin:0}
  .an-sub{color:#64748b;font-size:13px;margin:2px 0 0}
  .an-tabs{display:inline-flex;gap:4px;background:#f1f5f9;border-radius:12px;padding:4px}
  .an-tabs button{border:0;background:transparent;padding:8px 16px;border-radius:9px;font-weight:600;font-size:13.5px;color:#64748b;cursor:pointer}
  .an-tabs button.is-active{background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(15,23,42,.12)}
  .an-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:end;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:12px 14px;margin-bottom:12px}
  .an-filters label{display:flex;flex-direction:column;gap:4px;font-size:11.5px;font-weight:600;color:#64748b}
  .an-filters select,.an-filters input{min-height:36px;border:1px solid #cbd5e1;border-radius:9px;padding:6px 10px;font-size:13px;background:#fff;color:#0f172a}
  .an-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
  .an-chip{display:inline-flex;align-items:center;gap:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;border-radius:999px;padding:5px 12px;font-size:12.5px;font-weight:600;cursor:pointer}
  .an-chip-reset{background:#f1f5f9;border-color:#e2e8f0;color:#475569}
  .an-note{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;font-size:13.5px;color:#475569;margin:12px 0}
  .an-note-error{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
  .an-note code{background:#eef2f7;padding:1px 6px;border-radius:6px}
  .an-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:16px}
  .an-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px 18px}
  .an-kpi-label{font-size:12px;color:#64748b;font-weight:600}
  .an-kpi-value{font-size:26px;font-weight:800;margin-top:6px;letter-spacing:-.02em}
  .an-kpi-delta{font-size:12px;font-weight:700;margin-top:4px;color:#94a3b8}
  .an-kpi-delta.up{color:#008300}.an-kpi-delta.down{color:#e34948}
  .an-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:14px}
  .an-col-2{grid-column:1 / -1}
  @media (max-width:820px){.an-grid{grid-template-columns:1fr}}
  .an-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:14px 16px;margin:0 0 14px}
  .an-panel{margin:0}
  .an-card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .an-card-head h3{font-size:13.5px;font-weight:700;margin:0}
  .an-actions{display:flex;gap:6px}
  .an-act{border:1px solid #e2e8f0;background:#f8fafc;border-radius:8px;padding:3px 9px;font-size:11px;font-weight:700;color:#475569;cursor:pointer}
  .an-chart{width:100%;height:320px}
  .an-breadcrumb{display:flex;gap:10px;align-items:center;font-size:12.5px;color:#475569;margin-bottom:6px}
  .an-breadcrumb button{border:0;background:#f1f5f9;border-radius:8px;padding:4px 10px;font-weight:600;cursor:pointer;color:#0f172a}
  .an-hint{font-size:11.5px;color:#94a3b8;margin:0 0 6px}
  .an-table{width:100%;border-collapse:collapse;font-size:13px}
  .an-table th,.an-table td{text-align:left;padding:8px 6px;border-bottom:1px solid #f1f5f9}
  .an-table th:last-child,.an-table td:last-child{text-align:right}
  .an-empty{color:#94a3b8;text-align:center!important;padding:18px}
  .an-prog{color:#64748b;font-size:12px}
  .an-stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:8px 0}
  .an-stat-row>div{display:flex;flex-direction:column;gap:3px}
  .an-stat-row span{font-size:11.5px;color:#64748b;font-weight:600}
  .an-stat-row strong{font-size:19px;font-weight:800}
</style>
