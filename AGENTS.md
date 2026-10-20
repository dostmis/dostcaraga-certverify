# AGENTS.md

High-signal context for AI agents working in this repository.

## Project Overview
- **Backend**: Laravel 12 (PHP 8.3+), PostgreSQL 16, Node.js 20+
- **Frontend**: TanStack Start (React 19), Tailwind CSS v4, Cloudflare Workers (in `certUI/`)
- **Domain**: DOST Caraga CERTiFY — Certificate Repository System (issuance, verification, recipient accounts, intake forms).

## Critical Commands

### Backend (Laravel)
```bash
# Initial setup
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm run build

# Local development (concurrent: server, queue, logs, vite)
composer run dev

# Run tests (uses SQLite in-memory, defined in phpunit.xml)
composer run test

# Lint / Format
vendor/bin/pint

# Analytics read-model (PostgreSQL only; no-ops elsewhere)
php artisan analytics:refresh               # rebuild all materialized views + bump cache version
php artisan analytics:refresh --skip-crosswalk
php artisan analytics:prune-verifications   # 24-month retention on the raw verification log
```

### Frontend (`certUI/`)
```bash
cd certUI
bun install
bun run dev      # Local dev server
bun run build    # Production build
bun run lint     # ESLint
bun run format   # Prettier
```

### Docker
```bash
# Development (ports: 8082 app, 5173 vite, 54330 db)
cp .env.docker.dev .env
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force

# Production build GOTCHA: You MUST run `npm run build` on the host BEFORE `docker compose build`.
# The Dockerfile explicitly fails if `public/build/manifest.json` is missing.
```

## Architecture & Domain Gotchas
- **Two distinct user models**: 
  - `users`: Staff (Regional Director, Unit Supervisor, Organizer).
  - `recipients`: Certificate holders (can be dormant until they claim via email link).
- **Storage boundaries**:
  - `storage/app/public/certificates/`: Public stamped PDFs, QR codes, signatories.
  - `storage/app/private/certificates/`: Private endorsement templates and participant CSV/XLSX files.
- **PDF Generation**: Requires Ghostscript (`gs`) installed on the host/Docker image for PDF compatibility conversion. Uses `setasign/fpdi-fpdf` and `simplesoftwareio/simple-qrcode`.
- **Queues**: Uses the `database` queue driver. In local dev, `composer run dev` handles this. In Docker, the `worker` service runs `php artisan queue:work`.

## Analytics Read-Model (`analytics` schema)

The dashboard reads a separate Postgres schema of materialized views, never the
operational tables directly. `AnalyticsService` is the only entry point.

- **Postgres-only, and guarded.** Every view migration is a no-op when
  `DB::getDriverName() !== 'pgsql'`, because materialized views do not exist on
  SQLite. `AnalyticsService::available()` probes for them and returns a
  well-formed `available=false` payload instead of throwing — **never remove
  that guard to "simplify" a query.**
- **Refresh is explicit**: `php artisan analytics:refresh` (nightly at 01:30,
  plus `RefreshAnalyticsJob` after successful generation). `AnalyticsRefresh::VIEWS`
  is **dependency-ordered** — `mv_public_reach` and `mv_verification_facts` are
  built on `mv_certificate_facts`, so they must refresh after it. A view missing
  from the database is skipped with a warning rather than failing the whole run.
- **Payload cache**: keyed by `analytics:version`, bumped by `analytics:refresh`
  **and** by saving targets. Any new write that should show up immediately must
  bump it too, or it will not appear until the next nightly refresh.
- **Plain tables vs views** — this is what decides whether a feature is testable
  on SQLite:
  - Views (`mv_*`): Postgres only, unavailable under test.
  - `certificate_verifications`, `analytics_targets`: plain tables, so they work
    everywhere, including tests.

### Verification telemetry
- `certificate_verifications` logs **every** `/verify` attempt, failures included
  — hits on revoked certificates and malformed-token bursts are the integrity
  signal, and a success-only log cannot show them.
- **Privacy invariants, enforced in `VerificationLogger` and asserted in
  `VerificationLoggingTest`** — do not weaken any of these:
  - `ip_hash` is `sha256(ip + APP_KEY)`. The raw IP is never stored.
  - `ua_class` is bucketed to `mobile|desktop|bot|unknown`. The UA string is never stored.
  - `referer_host` is the host only. A verification link's full URL contains the token.
- **Logging must never throw.** Writes are wrapped and swallowed to the log,
  because a failed analytics insert must not stop someone proving their
  certificate is real.
- Retention: `analytics:prune-verifications` (24 months, daily at 02:30, after
  the refresh so the views have already absorbed the history).

### Targets
- `analytics_targets` is one row per **program x year**, set by the RD at
  `/admin/analytics/targets` (RD-only route).
- `province_code` is `0` for region-wide, **not NULL** — Postgres treats NULLs in
  a unique index as distinct, which would allow duplicate program/year rows. Same
  `0`-as-region convention as `mv_public_reach`. The column is already in the
  unique index so per-province targets can be added without rewriting the key.
- A blank metric means *no target*, which is **not** zero. Blank inputs must stay NULL.
- **The Pace block honors only the year and program filters** and reports the rest
  in `targets.ignored_filters`. Comparing a gender- or province-filtered actual
  against a region-wide annual target yields an authoritative-looking wrong
  number — if you add a filter, decide explicitly whether Pace can honor it.

### Dashboard shape
- Payload: `available, filters, executive, operations, targets, integrity`.
  `operations` is scoped to the user's own packages unless RD; `integrity` is
  **null for non-RD** and read live from the raw log (not the nightly views),
  since current abuse is only useful while it is happening.
- The admin dashboard is a **six-chapter scrollytelling page** (Reach, Who, Pace,
  Proof, Delivery, Integrity), not tabs. Contracts to respect:
  - Each `<section>` needs `data-chapter="<id>"` (IntersectionObserver target
    for active-state + lazy chart build), `data-ch="<id>"` (drives the `--ch`
    accent), and `id="<id>"` (deep link). `DashboardNarrativeTest` enforces all three.
  - A chart's `data-panel="<id>"` must match the id used in `renderChapter()` in
    `resources/js/analytics/dashboard.js`. A mismatch silently renders a blank
    panel, so the panel ids are asserted in that test.
  - Charts build lazily on scroll-in; `renderOpened()` re-renders only opened chapters.
- **The admin dashboard has a user-facing theme toggle** (HUD dark / clean
  light, persisted to `localStorage` under `certify.theme` and stamped on
  `<html data-theme>` by an inline script in `components/admin-layout.blade.php`
  **before first paint** — do not move that script, or the page flashes the
  wrong theme). The public `/impact` atlas is separate and light-only.
  `resources/js/analytics/echarts.js` therefore holds **two palette families
  and four themes**, and the boundary is load-bearing:
  - `PALETTE` / `SEQUENTIAL` / `STATUS` + `certify` / `certify-dark` → the
    public atlas (`atlas.js`), which follows the OS via `currentMode()`.
  - `HUD.*` + `certify-hud-dark` / `certify-hud-light` → the admin terminal
    (`panels.js`, dashboard-only). `dashboard.js` owns the `theme` state.
  Collapsing the two puts terminal colors on a white public page — if you
  restyle one, check the other still renders.
- **ECharts bakes its theme at init.** A theme switch cannot be done with
  `setOption`; `dashboard.js setTheme()` disposes every live instance and
  rebuilds. Any new chart must go through `draw()`/`drawData()` so it is
  included.
- **Palette rules that are not cosmetic.** Categorical hues are assigned in
  fixed order and never cycled (color follows the entity, never its rank — the
  `EMAIL` and outcome maps in `panels.js` exist for this reason); `SEQUENTIAL`
  is one hue for magnitude; anything with a semantic meaning takes its color
  from `semantic(mode)` (success green / neutral blue / failure red / pending
  amber / inactive gray) so the same idea is never two colors; status always
  ships with an icon + word so color is never the sole channel. The slot
  **order** is the colorblind-safety mechanism — cyan sits away from violet
  (3.7 ΔE deutan adjacent), green away from amber (4.6), teal away from pink
  (3.8). Re-run `scripts/validate_palette.js` from the `dataviz` skill against
  the surface the chart actually renders on (`--surface "#161B22"` dark,
  `"#FFFFFF"` light) after any palette change — do not eyeball it.
- **Chart marks are a 15–25% fill behind a 1px solid stroke** (`soft()` +
  the local `fill()` helper in `panels.js`), never a flooded solid. Data labels
  go outside the mark or are dropped for the tooltip — never on top of it.
  Empty panels render `P.emptyState(message, mode)` via `drawData()`, and
  single-point series switch from line to labelled bar, because a one-point
  line draws nothing.
- **Typography is split by role, not taste**: `--font-mono` is reserved for
  telemetry and system IDs (`.an-tele`, `.an-stamp`); everything read as prose
  or as a number — headings, KPI values, nav, controls, tables — is
  `--font-ui`. `DashboardNarrativeTest` asserts the mono face does not leak.
- The six chapter accents in `_tokens.blade.php` are chrome only (rails,
  numerals, washes), never a data encoding, and each theme supplies its own.
- Public `/impact` atlas stays aggregate-only: `<5` suppressed, no barangay, no PII.

## Testing Constraints
- Tests run against an in-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in `phpunit.xml`).
- Do not assume PostgreSQL-specific features will work in tests unless explicitly tested.
- Run `composer run test` to execute the suite.
- **A green suite does not cover the materialized views.** Anything using
  `percentile_cont`, `COUNT(*) FILTER`, `::date`, or a `mv_*` view is skipped
  under SQLite, so a syntax error there surfaces only in production. Verify that
  work against a real Postgres — see the Verification Order below.

## File Boundaries
- `app/`: Core Laravel backend logic.
- `app/Services/Analytics/`: `AnalyticsService` (every dashboard query),
  `AnalyticsFilters` (immutable, always parameter-bound — only the fields it
  declares can reach a query), `VerificationLogger`. Adding a filter means
  touching both this class **and** `AnalyticsDataRequest::ALLOWED_KEYS`, which
  rejects unknown query keys as a 422 rather than ignoring them.
- `resources/js/analytics/`: `dashboard.js` (scrollytelling controller),
  `panels.js` (pure ECharts option builders — no DOM, no fetch),
  `echarts.js` (tree-shaken core + palette; **register new chart types here**),
  `atlas.js` (public `/impact`).
- `resources/views/admin/analytics/`: `dashboard.blade.php`, `_tokens.blade.php`
  (shared design tokens + chapter accents), `_dashboard_styles.blade.php`,
  `targets/edit.blade.php`.
- `certUI/`: Separate TanStack Start frontend application (managed via Bun).
- `docker/`: Container configurations (dev, backup, php, nginx).
- `scripts/`: Bare-metal deployment and utility scripts.

## Verification Order
When making changes, prefer this sequence:
1. `composer run test` (fastest feedback)
2. `vendor/bin/pint` (ensure formatting)
3. `npm run build` (verify frontend assets compile)

For analytics work, add these — steps 1–3 cannot catch Postgres-only breakage:

4. `php artisan migrate --force` against a real Postgres (validates the
   view SQL, which is a no-op under SQLite).
5. `php artisan analytics:refresh` (validates every view still builds, in order).
6. Exercise the changed queries on real data. To avoid touching a dev database,
   wrap inserts in `DB::beginTransaction()` / `DB::rollBack()` and use a plain
   `REFRESH MATERIALIZED VIEW` — `CONCURRENTLY` cannot run inside a transaction.
7. `composer run dev` and actually open `/admin/dashboard` — the chapter layout,
   accent colors, and lazy chart rendering are not covered by any test.
