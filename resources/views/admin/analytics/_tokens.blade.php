{{--
  Analytics design tokens for the Impact Terminal, in two themes the user picks
  between from the header:

    [data-theme="dark"]  — Refined Cyberpunk HUD (default)
    [data-theme="light"] — Clean high-contrast light

  Both are *selected*, not flipped: each has its own ink, border and shadow
  steps, and its own validated chart palette (see resources/js/analytics/echarts.js
  — HUD_CATEGORICAL / HUD_SEQUENTIAL / HUD_STATUS, each run through the data-viz
  validator against the surface it actually renders on).

  Typography is split by role, not by taste:
    --font-mono  telemetry, system IDs, code-like metadata ONLY
    --font-ui    everything a person reads as prose or as a number — headings,
                 KPI values, nav, controls, tables, body
--}}
<style>
  [x-cloak]{display:none!important}

  .an-scope{
    --font-ui:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;
    --font-mono:"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,monospace;
    --r-sm:6px;
    --r-md:10px;
    --r-lg:14px;
  }

  /* ---------- Option A: Refined Cyberpunk HUD (dark) ---------- */
  :root[data-theme="dark"] .an-scope,
  .an-scope{
    color-scheme:dark;

    --plane:#0D1117;            /* deep obsidian navy */
    --surface:#161B22;          /* dark slate blue panels */
    --surface-2:#1C222B;
    --surface-3:#11161D;
    --border:#30363D;
    --border-strong:#3D444D;

    --ink:#F3F4F6;              /* cool white */
    --ink-2:#9AA4B2;
    --ink-muted:#6B7280;        /* slate gray */

    --accent:#38BDF8;           /* electric cyan */
    --accent-2:#C084FC;         /* neon violet */
    --accent-soft:rgba(56,189,248,.12);
    --accent-ring:rgba(56,189,248,.35);

    --good:#4ADE80;
    --warning:#FBBF24;
    --serious:#C084FC;
    --critical:#F87171;
    --good-ink:#4ADE80;

    --elev:0 1px 2px rgba(0,0,0,.4),0 6px 20px rgba(0,0,0,.28);
    --grid-line:rgba(56,189,248,.04);

    /* Chapter accents, drawn from the validated dark categorical order. */
    --ch:var(--accent);
    --ch-reach:#38BDF8;
    --ch-who:#C084FC;
    --ch-pace:#FBBF24;
    --ch-proof:#2DD4BF;
    --ch-delivery:#A3E635;
    --ch-integrity:#F87171;
  }

  /* ---------- Option C: Clean high-contrast light ---------- */
  :root[data-theme="light"] .an-scope{
    color-scheme:light;

    --plane:#F8FAFC;            /* soft light gray */
    --surface:#FFFFFF;          /* pure white panels */
    --surface-2:#F8FAFC;
    --surface-3:#F1F5F9;
    --border:#E2E8F0;
    --border-strong:#CBD5E1;

    --ink:#0F172A;              /* deep slate */
    --ink-2:#64748B;            /* medium slate */
    --ink-muted:#94A3B8;

    --accent:#0284C7;           /* deep ocean blue */
    --accent-2:#7C3AED;         /* royal purple */
    --accent-soft:rgba(2,132,199,.09);
    --accent-ring:rgba(2,132,199,.30);

    --good:#059669;
    --warning:#D97706;
    --serious:#7C3AED;
    --critical:#DC2626;
    --good-ink:#059669;

    --elev:0 1px 3px rgba(0,0,0,.05);
    --grid-line:rgba(2,132,199,.035);

    --ch:var(--accent);
    --ch-reach:#0284C7;
    --ch-who:#7C3AED;
    --ch-pace:#D97706;
    --ch-proof:#0D9488;
    --ch-delivery:#15803D;
    --ch-integrity:#DC2626;
  }

  /* The page plane. Hexes are literal because --plane is declared on
     .an-scope, a *descendant* of these elements: var() would not resolve here
     and the declaration would die, letting the layout's Tailwind bg win. */
  html:has(.an-scope){background:#0D1117}
  body:has(.an-scope){background:#0D1117;color:#F3F4F6}
  /* The attribute selector adds specificity, so the light stamp wins. */
  html[data-theme="light"]:has(.an-scope){background:#F8FAFC}
  html[data-theme="light"] body:has(.an-scope){background:#F8FAFC;color:#0F172A}

  .an-scope{
    color:var(--ink);
    font-family:var(--font-ui);
    background:
      linear-gradient(var(--grid-line) 1px,transparent 1px) 0 0/100% 44px,
      linear-gradient(90deg,var(--grid-line) 1px,transparent 1px) 0 0/44px 100%,
      var(--plane);
  }

  /* ---- primitives ---- */
  .an-card{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--r-md);
    box-shadow:var(--elev);
  }

  /* Digital corner brackets, in a wrapper class so the pseudo-elements never
     collide with a component's own ::before. */
  .an-brackets{position:relative}
  .an-brackets>.an-bk{position:absolute;width:8px;height:8px;pointer-events:none;opacity:.6}
  .an-brackets>.an-bk-tl{top:-1px;left:-1px;border-top:1.5px solid var(--ch);border-left:1.5px solid var(--ch)}
  .an-brackets>.an-bk-tr{top:-1px;right:-1px;border-top:1.5px solid var(--ch);border-right:1.5px solid var(--ch)}
  .an-brackets>.an-bk-bl{bottom:-1px;left:-1px;border-bottom:1.5px solid var(--ch);border-left:1.5px solid var(--ch)}
  .an-brackets>.an-bk-br{bottom:-1px;right:-1px;border-bottom:1.5px solid var(--ch);border-right:1.5px solid var(--ch)}

  .an-note{
    background:var(--surface-2);
    border:1px solid var(--border);
    border-left:3px solid var(--accent);
    border-radius:var(--r-sm);
    padding:12px 15px;font-size:13px;line-height:1.55;color:var(--ink-2);margin:12px 0;
  }
  .an-note-error{background:color-mix(in srgb,var(--critical) 10%,var(--surface));border-color:color-mix(in srgb,var(--critical) 30%,var(--border));border-left-color:var(--critical)}
  .an-note-ok{background:color-mix(in srgb,var(--good) 10%,var(--surface));border-color:color-mix(in srgb,var(--good) 28%,var(--border));border-left-color:var(--good)}
  .an-note-warn{background:color-mix(in srgb,var(--warning) 12%,var(--surface));border-color:color-mix(in srgb,var(--warning) 32%,var(--border));border-left-color:var(--warning)}
  .an-note code{
    background:var(--accent-soft);border:1px solid var(--border);
    padding:1px 6px;border-radius:4px;color:var(--accent);font-family:var(--font-mono);font-size:12px;
  }
  .an-note a{color:var(--accent);font-weight:600}

  /* ---- buttons ---- */
  .an-btn{
    display:inline-flex;align-items:center;gap:7px;min-height:36px;padding:8px 14px;
    border:1px solid var(--border-strong);background:var(--surface);color:var(--ink);
    border-radius:var(--r-sm);font-family:var(--font-ui);font-size:13px;font-weight:600;
    cursor:pointer;text-decoration:none;
    transition:border-color .15s,box-shadow .15s,background .15s,color .15s;
  }
  .an-btn:hover{background:var(--surface-2);border-color:var(--accent);color:var(--accent)}
  .an-btn:focus-visible{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-ring)}

  /* Secondary/outline by design: the KPI metrics are the visual priority on
     this screen, so the header CTA must not out-shout them. */
  .an-btn-secondary{
    background:transparent;border-color:color-mix(in srgb,var(--accent-2) 45%,var(--border));
    color:var(--accent-2);
  }
  .an-btn-secondary:hover{
    background:color-mix(in srgb,var(--accent-2) 10%,transparent);
    border-color:var(--accent-2);color:var(--accent-2);
  }

  /* ---- tables ---- */
  .an-table{width:100%;border-collapse:collapse;font-size:13px;font-family:var(--font-ui)}
  .an-table th{
    text-align:left;padding:9px 8px;border-bottom:1px solid var(--border);
    font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--ink-muted);
  }
  .an-table td{text-align:left;padding:9px 8px;border-bottom:1px solid var(--border);color:var(--ink-2)}
  .an-table tbody tr:hover td{background:var(--accent-soft);color:var(--ink)}
  .an-table tbody tr:last-child td{border-bottom:0}
  .an-num{text-align:right!important;font-variant-numeric:tabular-nums;color:var(--ink)}
  .an-empty{color:var(--ink-muted);text-align:center!important;padding:20px;font-size:12.5px}

  /* ---- telemetry artifacts: the ONLY place monospace is used ---- */
  .an-tele{
    font-family:var(--font-mono);font-size:9px;letter-spacing:.1em;text-transform:uppercase;
    color:var(--ink-muted);
  }
  .an-tele-key{color:var(--accent);opacity:.7}
  .an-stamp{
    display:inline-flex;align-items:center;gap:5px;font-family:var(--font-mono);font-size:9px;
    letter-spacing:.12em;text-transform:uppercase;color:var(--good);
    border:1px solid color-mix(in srgb,var(--good) 32%,var(--border));
    background:color-mix(in srgb,var(--good) 8%,transparent);
    border-radius:999px;padding:2px 8px;
  }
  .an-stamp::before{
    content:"";width:4px;height:4px;border-radius:50%;background:var(--good);
    animation:an-pulse 2.4s ease-in-out infinite;
  }
  @keyframes an-pulse{0%,100%{opacity:1}50%{opacity:.25}}

  .an-scope :focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:4px}
  .an-scope ::selection{background:var(--accent-soft);color:var(--ink)}

  @media (prefers-reduced-motion:reduce){
    .an-scope *{animation:none!important;transition:none!important;scroll-behavior:auto!important}
  }
</style>
