{{--
  Impact Terminal layout. Tokens (both themes, typography roles) come from
  _tokens.blade.php.

  Each chapter section carries data-ch="<id>", and the block near the bottom
  maps that attribute to the chapter accent via --ch; numeral, rail, panel edge
  and KPI border all read from --ch, so a chapter is recolored in one place and
  each theme supplies its own accent values.

  Typography rule, applied throughout: --font-mono appears ONLY on .an-tele /
  .an-stamp (telemetry + system IDs). Headings, KPI values, nav, controls and
  tables are all --font-ui.
--}}
<style>
  .an{max-width:1340px;margin:0 auto;padding:0 0 72px;min-height:100vh}

  /* ---- reading progress ---- */
  .an-progress{position:fixed;top:0;left:0;right:0;height:2px;background:transparent;z-index:40}
  .an-progress-bar{
    height:100%;width:0;
    background:linear-gradient(90deg,var(--accent),var(--accent-2));
    transition:width .12s linear;
  }

  /* ---- HUD deck ---- */
  .an-deck{
    position:sticky;top:0;z-index:30;padding:16px 18px 14px;margin-bottom:10px;
    background:color-mix(in srgb,var(--plane) 92%,transparent);
    backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
    border-bottom:1px solid var(--border);
  }
  .an-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:14px}
  .an-head h1{
    font-family:var(--font-ui);font-size:21px;font-weight:700;letter-spacing:-.01em;
    margin:0;color:var(--ink);
  }
  .an-sub{font-size:12.5px;color:var(--ink-2);margin:4px 0 0}
  .an-head-meta{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:9px}
  .an-head-actions{display:flex;gap:9px;align-items:center}

  /* ---- theme toggle ---- */
  .an-theme{
    display:inline-flex;align-items:center;gap:2px;padding:3px;
    background:var(--surface-3);border:1px solid var(--border);border-radius:999px;
  }
  .an-theme button{
    display:inline-flex;align-items:center;gap:6px;border:0;background:transparent;
    padding:5px 11px;border-radius:999px;cursor:pointer;
    font-family:var(--font-ui);font-size:12px;font-weight:600;color:var(--ink-muted);
    transition:background .15s,color .15s;
  }
  .an-theme button:hover{color:var(--ink-2)}
  .an-theme button.is-on{background:var(--surface);color:var(--accent);box-shadow:var(--elev)}

  /* ---- filter bar ---- */
  .an-filters{
    display:flex;flex-wrap:wrap;gap:13px;align-items:end;
    /* Distinct from the deck behind it, so the control row reads as a surface. */
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--r-md);padding:12px 14px;box-shadow:var(--elev);
  }
  .an-filters label{
    display:flex;flex-direction:column;gap:5px;
    font-family:var(--font-ui);font-size:11px;font-weight:600;
    text-transform:uppercase;letter-spacing:.06em;color:var(--ink-muted);
  }
  .an-filters select,.an-filters input{
    min-height:36px;background:var(--surface-3);color:var(--ink);
    border:1px solid var(--border-strong);border-radius:var(--r-sm);
    padding:7px 11px;font-family:var(--font-ui);font-size:13px;
    transition:border-color .15s,box-shadow .15s,background .15s;
  }
  .an-filters select{
    appearance:none;-webkit-appearance:none;padding-right:28px;
    /* small triangle caret */
    background-image:linear-gradient(45deg,transparent 50%,currentColor 50%),
                     linear-gradient(135deg,currentColor 50%,transparent 50%);
    background-position:calc(100% - 15px) 15px,calc(100% - 10px) 15px;
    background-size:5px 5px,5px 5px;background-repeat:no-repeat;
  }
  .an-filters select:hover,.an-filters input:hover{border-color:var(--accent);background:var(--surface)}
  .an-filters select:focus,.an-filters input:focus{
    outline:none;border-color:var(--accent);background:var(--surface);
    box-shadow:0 0 0 3px var(--accent-ring);
  }
  .an-filters input[type="date"]::-webkit-calendar-picker-indicator{opacity:.6;cursor:pointer}
  :root[data-theme="dark"] .an-filters input[type="date"]::-webkit-calendar-picker-indicator{filter:invert(1)}

  /* ---- active filter chips ---- */
  .an-chips{display:flex;flex-wrap:wrap;gap:7px;margin-top:11px}
  .an-chip{
    display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:4px 11px;
    font-family:var(--font-ui);font-size:12px;font-weight:600;cursor:pointer;
    background:var(--accent-soft);border:1px solid color-mix(in srgb,var(--accent) 35%,var(--border));
    color:var(--accent);
  }
  .an-chip:hover{background:color-mix(in srgb,var(--accent) 18%,transparent)}
  .an-chip-reset{
    background:transparent;border-color:var(--border-strong);color:var(--ink-2);
  }
  .an-chip-reset:hover{border-color:var(--critical);color:var(--critical);background:transparent}

  /* ---- body: rail + flow ---- */
  .an-body{display:grid;grid-template-columns:190px minmax(0,1fr);gap:26px;align-items:start;padding:0 18px}

  .an-rail{position:sticky;top:196px}
  .an-rail-title{
    font-family:var(--font-mono);font-size:9px;letter-spacing:.16em;text-transform:uppercase;
    color:var(--ink-muted);padding:0 0 9px 12px;border-bottom:1px solid var(--border);margin-bottom:6px;
  }
  .an-rail ol{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:2px}
  .an-rail button{
    display:grid;grid-template-columns:34px 1fr;align-items:center;gap:9px;width:100%;
    border:0;border-left:3px solid transparent;background:transparent;padding:9px 12px;
    cursor:pointer;text-align:left;font-family:var(--font-ui);color:var(--ink-muted);
    border-radius:0 var(--r-sm) var(--r-sm) 0;
    transition:background .15s,color .15s,border-color .15s;
  }
  .an-rail button:hover{background:var(--surface-2);color:var(--ink-2)}
  .an-rail-n{font-family:var(--font-mono);font-size:10px;font-variant-numeric:tabular-nums}
  .an-rail-label{font-size:13px;font-weight:600}
  .an-rail button.is-active{
    background:color-mix(in srgb,var(--ch) 11%,transparent);
    border-left-color:var(--ch);color:var(--ch);
  }
  .an-rail button.is-active .an-rail-n{color:var(--ch)}

  .an-flow{display:flex;flex-direction:column;gap:10px;min-width:0}

  /* ---- a chapter ---- */
  .an-ch{scroll-margin-top:204px;padding:30px 0 40px;border-top:1px solid var(--border)}
  .an-ch:first-child{border-top:0;padding-top:14px}

  .an-ch-head{display:grid;grid-template-columns:auto 1fr;gap:18px;align-items:start;margin-bottom:20px}
  .an-ch-n{
    font-family:var(--font-mono);font-size:44px;font-weight:500;line-height:.92;
    color:transparent;-webkit-text-stroke:1.5px var(--ch);opacity:.9;
  }
  .an-ch-kicker{
    display:inline-flex;align-items:center;font-family:var(--font-ui);font-size:11px;
    font-weight:700;text-transform:uppercase;letter-spacing:.11em;color:var(--ch);margin:2px 0 8px;
    padding:3px 9px;border:1px solid color-mix(in srgb,var(--ch) 35%,var(--border));
    background:color-mix(in srgb,var(--ch) 10%,transparent);border-radius:999px;
  }
  .an-ch-head h2{
    font-family:var(--font-ui);font-size:22px;font-weight:700;letter-spacing:-.015em;
    margin:0;color:var(--ink);
  }
  .an-ch-lead{font-size:15px;line-height:1.6;color:var(--ink-2);margin:9px 0 0;max-width:72ch}

  /* Per-chapter accent — one place to recolor a chapter, per theme. */
  [data-ch="reach"]{--ch:var(--ch-reach)}
  [data-ch="who"]{--ch:var(--ch-who)}
  [data-ch="pace"]{--ch:var(--ch-pace)}
  [data-ch="proof"]{--ch:var(--ch-proof)}
  [data-ch="delivery"]{--ch:var(--ch-delivery)}
  [data-ch="integrity"]{--ch:var(--ch-integrity)}

  /* ---- panels ---- */
  .an-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;margin-bottom:13px}
  .an-col-2{grid-column:1 / -1}
  .an-pad{padding:15px 17px}
  .an-panel{margin:0;padding:15px 17px;position:relative}
  .an-ch .an-card::before{
    content:"";position:absolute;inset:0 0 auto 0;height:2px;border-radius:var(--r-md) var(--r-md) 0 0;
    background:linear-gradient(90deg,var(--ch),transparent 70%);opacity:.65;
  }
  .an-ch .an-card{position:relative;overflow:hidden}
  .an-card-head{
    display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:11px;
    padding-bottom:9px;border-bottom:1px solid var(--border);
  }
  .an-card-head h3{
    font-family:var(--font-ui);font-size:13px;font-weight:650;margin:0;color:var(--ink);
    letter-spacing:-.005em;
  }
  .an-actions{display:flex;gap:6px;flex:0 0 auto;align-items:center}
  .an-act{
    background:var(--surface-3);border:1px solid var(--border);border-radius:var(--r-sm);
    padding:3px 8px;font-family:var(--font-mono);font-size:10px;
    color:var(--ink-2);cursor:pointer;transition:border-color .15s,color .15s;
  }
  .an-act:hover{border-color:var(--accent);color:var(--accent)}
  .an-chart{width:100%;height:320px}

  /* ---- KPI modules ---- */
  .an-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(178px,1fr));gap:12px;margin-bottom:16px}
  .an-kpis-4{grid-template-columns:repeat(auto-fit,minmax(192px,1fr))}
  .an-kpi{
    position:relative;background:var(--surface);
    border:1px solid var(--border);border-top:2px solid var(--ch);
    border-radius:var(--r-md);padding:14px 16px 12px;box-shadow:var(--elev);
  }
  .an-kpi-label{
    font-family:var(--font-ui);font-size:11.5px;font-weight:600;letter-spacing:.03em;
    text-transform:uppercase;color:var(--ink-muted);
  }
  /* Primary metrics are UI sans with tabular figures — materially more legible
     than mono at this size, and the columns still align. */
  .an-kpi-value{
    font-family:var(--font-ui);font-size:31px;font-weight:700;margin-top:7px;
    letter-spacing:-.025em;color:var(--ch);font-variant-numeric:tabular-nums;line-height:1.05;
  }
  .an-kpi-delta{font-family:var(--font-ui);font-size:12px;font-weight:600;margin-top:5px;color:var(--ink-muted)}
  .an-kpi-delta.up{color:var(--good-ink)}
  .an-kpi-delta.down{color:var(--critical)}
  .an-kpi-tele{
    display:flex;justify-content:space-between;gap:8px;margin-top:10px;padding-top:8px;
    border-top:1px dashed var(--border);
  }

  /* ---- map ---- */
  .an-map-wrap{position:relative}
  .an-map-hud{
    position:absolute;top:10px;right:12px;z-index:2;display:flex;flex-direction:column;
    align-items:flex-end;gap:4px;pointer-events:none;
  }
  .an-breadcrumb{display:flex;gap:10px;align-items:center;font-size:12.5px;color:var(--ink-2);margin-bottom:9px}
  .an-breadcrumb button{
    border:1px solid var(--border-strong);background:var(--surface-3);border-radius:var(--r-sm);
    padding:4px 10px;font-family:var(--font-ui);font-size:12px;font-weight:600;
    cursor:pointer;color:var(--ink);
  }
  .an-breadcrumb button:hover{border-color:var(--accent);color:var(--accent)}

  /* ---- misc readouts ---- */
  .an-hint{font-size:12px;color:var(--ink-muted);margin:0 0 9px}
  .an-hint strong{color:var(--accent);font-weight:650}
  .an-prog{display:block;color:var(--ink-muted);font-size:12px;margin-top:2px}
  .an-mono{font-family:var(--font-mono);font-size:12px;color:var(--accent)}
  .an-stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:6px 0}
  .an-stat-row>div{display:flex;flex-direction:column;gap:4px}
  .an-stat-row span{
    font-family:var(--font-ui);font-size:11px;font-weight:600;color:var(--ink-muted);
    letter-spacing:.05em;text-transform:uppercase;
  }
  .an-stat-row strong{
    font-family:var(--font-ui);font-size:21px;font-weight:700;letter-spacing:-.02em;
    color:var(--ch);font-variant-numeric:tabular-nums;
  }

  /* Status pill: semantic hue + icon + word, so color is never the only
     channel carrying the state. */
  .an-status{
    display:inline-flex;align-items:center;gap:6px;font-family:var(--font-ui);font-size:12px;
    font-weight:650;padding:3px 9px;border-radius:999px;white-space:nowrap;color:var(--st);
    background:color-mix(in srgb,var(--st) 12%,transparent);
    border:1px solid color-mix(in srgb,var(--st) 34%,var(--border));
  }

  @media (max-width:1080px){
    .an-body{grid-template-columns:1fr;gap:8px;padding:0 14px}
    .an-rail{
      position:sticky;top:184px;z-index:20;padding:6px 0;
      background:color-mix(in srgb,var(--plane) 94%,transparent);
      backdrop-filter:blur(8px);border-bottom:1px solid var(--border);
    }
    .an-rail-title{display:none}
    .an-rail ol{flex-direction:row;overflow-x:auto;gap:3px;scrollbar-width:none}
    .an-rail ol::-webkit-scrollbar{display:none}
    .an-rail button{
      grid-template-columns:auto auto;padding:8px 12px;border-left:0;
      border-bottom:2px solid transparent;border-radius:var(--r-sm) var(--r-sm) 0 0;
    }
    .an-rail button.is-active{border-left:0;border-bottom-color:var(--ch)}
    .an-ch{scroll-margin-top:250px}
  }
  @media (max-width:820px){
    .an-grid{grid-template-columns:1fr}
    .an-deck{position:static;backdrop-filter:none}
    .an-rail{position:static}
    .an-ch{scroll-margin-top:16px}
    .an-ch-head{grid-template-columns:1fr;gap:6px}
    .an-ch-n{font-size:34px}
    .an-stat-row{grid-template-columns:repeat(2,1fr)}
    .an-chart{height:280px}
    .an-head h1{font-size:18px}
  }
</style>
