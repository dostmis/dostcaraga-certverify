# CERTiFY — End-to-End Bug Report

**Date:** 2026-10-14
**Branch:** `dostcaraga-certverify-v2`
**Tester:** Automated end-to-end review (static inspection + live HTTP testing)
**Stack:** Laravel 12 / PHP 8.5 (CLI) / PostgreSQL 16 / Vite 7 / Tailwind

---

## How this was tested

No source code was modified. The real `certverify` database (5 users, **413 live certificates**) was **not touched**. All live testing ran against a throwaway database `certverify_e2e` created for this review, seeded with test staff/recipient/intake rows.

### Environment & toolchain (all present)
PHP 8.5.7, Composer 2.10.1, Node 24.16, npm 11.13, Ghostscript 10.07.1, PostgreSQL 16.14, PHP ext: `pdo_pgsql`, `pdo_sqlite`, `gd`, `imagick`, `zip`, `bcmath`.

### Commands run
```bash
composer install                      # OK (note: setasign/fpdi-fpdf is abandoned)
npm run build                         # OK — built in ~0.5s, manifest.json generated
createdb certverify_e2e
DB_DATABASE=certverify_e2e php artisan migrate --force   # OK — all 42 migrations clean on a fresh DB
composer run test                     # FAIL — 4 failed, 47 passed (176 assertions)
vendor/bin/pint --test                # FAIL — 69 files need formatting (style only)
DB_DATABASE=certverify_e2e php artisan serve --port=8111 # OK
DB_DATABASE=certverify_e2e php artisan queue:work        # OK
```

### Flows exercised
Staff login/logout (RD, Unit Supervisor, Organizer, pending user), self-registration + approval gate, recipient claim/verify/takeover, intake-event CRUD (create/toggle/delete), public certificate verify/print/download/preview, public participant-intake form, role/permission enforcement, PSGC + participant-search APIs, registration & form validation.

### Could not be tested
- **Live browser UI / responsive testing** — the Claude browser extension was not connected, so visual/responsive defects and client-side JS behavior were not exercised. Recommend a manual pass or re-run with the browser connected.
- **Full certificate generation/email pipeline** — requires uploading real PDF templates + participant files; the queue batch/rollback path is covered by existing passing feature tests (`CertificateApprovalRequestTest`, `CertificateGenerationQueueTest`, `CertificateGenerationRollbackTest`).

---

## Severity summary

| # | Severity | Title |
|---|----------|-------|
| 1 | **Critical** | Account-approval gate bypass — any self-registered user can self-activate |
| 2 | **Critical** | Unauthenticated PII disclosure via `/api/participants/search` |
| 3 | **High** | Recipient account takeover — unauthenticated claim/verify with no rate limiting + last-4 contact leak |
| 4 | **High** | Unhandled 500 (with stack trace) on any non-UUID token across all public endpoints |
| 5 | **Medium** | Test suite is red — `composer run test` fails (4 tests) → broken CI gate |
| 6 | **Medium** | `/app/{any?}` route returns 500 (missing `public/app/index.html`) |
| 7 | **Low** | `vendor/bin/pint --test` fails — 69 files unformatted |
| 8 | **Low** | Intake export help-text shown to RD references a button the RD cannot use |
| 9 | **Low/Info** | Abandoned dependency `setasign/fpdi-fpdf`; `APP_DEBUG=true` default |

---

## 1. [CRITICAL] Account-approval gate bypass — self-registered users can self-activate

**Affected files**
- `app/Http/Requests/Auth/LoginRequest.php:49-79` (gate logic)
- `app/Http/Controllers/Auth/RegisteredUserController.php:30-54` (registration)

**Reproduction (verified live)**
1. Register a new account (created as `approval_status = 'pending'`), but set `username` to **any existing approved user's email, username, or name** — e.g. the Regional Director's email `rd@test.local`:
   ```
   POST /register  name=Attacker  username=rd@test.local  email=attacker@evil.local
                   role=organizer  password=AttackerPass123!  (+confirmation)
   ```
   DB confirms: `attacker@evil.local | username=rd@test.local | organizer | pending`.
2. Log in with `login=rd@test.local`, `password=AttackerPass123!` (the attacker's own password).
3. Result: `302 → /dashboard`; session belongs to **user id 5 (attacker, still `pending`)**. `GET /admin/certificates` → **200**, `/admin/certificates/create` → 200, `/admin/participant-intakes` → 200, `/admin/analytics` → 200.

**Control (proves the gate normally works):** a normal pending user (`pending@test.local`) is correctly blocked at login ("Your request is still pending approval by the Regional Director.") and `/admin/certificates` → 302.

**Expected:** A `pending` (or `rejected`) account can never obtain an authenticated session until the RD approves it.

**Actual:** The approval check and the credential check resolve to **different user rows**, so the gate is bypassed and the pending attacker is logged in with full organizer-level access (certificate creation, intake management, analytics, PII).

**Root cause**
`authenticate()` first looks up a single user by `email OR username OR name` to check `approval_status`:
```php
$user = User::query()->where('email',$login)->orWhere('username',$login)->orWhere('name',$login)->first();
if ($user && $user->approval_status !== 'approved') { ...throw... }
```
With `$login = rd@test.local`, this matches the **RD** (approved) and passes the gate. It then authenticates with three *separate* attempts:
```php
Auth::attempt(['email'=>$login,...]);     // fails (attacker's email differs)
Auth::attempt(['username'=>$login,...]);  // SUCCEEDS → logs in the attacker (username=rd@test.local)
Auth::attempt(['name'=>$login,...]);
```
The user whose status was validated ≠ the user who gets authenticated. Allowing login by `name` (non-unique, not validated unique) compounds the risk.

**Suggested fix (do not apply yet)**
- Re-validate approval status on the **authenticated** user *after* `Auth::attempt` succeeds (`Auth::user()`), and `logout()` + throw if not `approved`. Do the status check on the same row that authenticated, not a pre-query.
- Remove `name` as a login identifier (it is not unique). Restrict login to `email`/`username` only.
- Consider enforcing `username !== any existing email` at registration, or validate the login field format to disambiguate.

---

## 2. [CRITICAL] Unauthenticated PII disclosure via `/api/participants/search`

**Affected files**
- `routes/web.php:113` — `Route::get('/api/participants/search', ...)` with **no `auth` middleware**
- `app/Http/Controllers/Api/ParticipantSearchController.php:11-65`

**Reproduction (verified live)**
```
GET /api/participants/search?q=ma          → 200, full participant record(s)
GET /api/participants/search?q=%25%25       → 200, returns ALL rows (`%%` = SQL wildcard via ILIKE)
```
Response includes, per participant: `participant_name, first_name, last_name, middle_initial, email, contact_number, gender, age_range, pwd_status, is_4ps_beneficiary, is_elcac_community, organization_name, industry, position_designation, region, province, city_municipality, barangay, block_lot_purok, dost_program_beneficiary, ...` — i.e. **names, emails, phone numbers, home addresses, and sensitive demographic flags (PWD, 4Ps, ELCAC)** of every intake participant.

**Expected:** This endpoint (used by the authenticated certificate-creation screen) must require authentication and an appropriate staff role.

**Actual:** Any anonymous visitor can dump the entire `participant_intakes` table. Because the `q` value is interpolated into an `ILIKE '%{$q}%'` pattern, `q=%%` matches all records — full enumeration with no throttle.

**Root cause:** The route is declared outside the `admin`/`auth` middleware group. The `%` characters in user input are not escaped for `LIKE`, enabling wildcard enumeration.

**Suggested fix (do not apply yet)**
- Move the route inside `Route::prefix('admin')->middleware(['auth'])` with `role:regional_director,unit_supervisor,organizer`.
- Escape `LIKE` wildcards in `$q` (`addcslashes($q, '%_\\')`).
- Add throttling (e.g. `throttle:30,1`).
- Consider the same review for the `/api/psgc/*` endpoints (lower risk — reference geography data — but currently also unauthenticated).

---

## 3. [HIGH] Recipient account takeover — unauthenticated claim/verify, no rate limiting, last-4 contact leak

**Affected files**
- `app/Http/Controllers/Recipient/Auth/RecipientAuthController.php:91-194` (`claimLookup`, `claimVerify`)
- `routes/recipient.php:16-18` — claim routes have **no `throttle` middleware**

**Reproduction (verified live)** against a dormant recipient (`victim.recipient@example.com`, contact `09171234567`):
1. `POST /recipient/claim/lookup` with the victim's name+email returns:
   `{"found":true,"recipient_id":1,"name":"Victim Recipient","masked_contact":"4567"}` — **leaks the last 4 digits** of the phone number to an anonymous caller.
2. `POST /recipient/claim/verify` with `recipient_id=1`, `contact_number=09171234567` (the full number is itself obtainable via Bug #2), and a new password → `302 → /recipient/certificates`. DB shows the recipient is now **claimed** with the attacker's password; all certificates matching that email are auto-linked to the attacker.
3. **No rate limiting:** 25 consecutive wrong `contact_number` guesses against a dormant recipient all returned `302` (processed) — none throttled. With the last-4 digits leaked in step 1, the remaining search space is tiny (brute-forceable).

**Expected:** Claiming an account should require a secret the attacker cannot obtain (a token emailed to the recipient), and verification attempts must be rate-limited.

**Actual:** An anonymous attacker who knows (or harvests via Bug #2) a recipient's name + email can take over the dormant account — directly when the contact number is known, or by brute force (aided by the last-4 leak and absence of throttling).

**Root cause**
- `claimLookup` returns `masked_contact` (last 4 digits) for any name+email match — an information leak that shrinks the brute-force space.
- `claimVerify` authenticates on `contact_number` equality alone with **no attempt throttling**.
- The claim routes carry no `throttle` middleware.

**Suggested fix (do not apply yet)**
- Require possession of the emailed `claim_token` to set a password (the token-based `claim/{token}` flow already exists and is the intended path). Treat the contact-number "verify" path as a fallback only, and if kept:
  - Remove `masked_contact` from the lookup response.
  - Add strict rate limiting / lockout on `claim/lookup` and `claim/verify` (e.g. `throttle:5,1` keyed on IP + recipient_id), and a global per-recipient attempt counter.
- Fix Bug #2 so full contact numbers cannot be harvested.

---

## 4. [HIGH] Unhandled 500 on non-UUID token across all public endpoints

**Affected files**
- `app/Http/Controllers/CertificatePublicController.php:26, 46, 67, 90` (`verify`, `print`, `preview`, `download`)
- `app/Http/Controllers/ParticipantIntakeController.php:21, 47` (`create`, `store`)

**Reproduction (verified live)**
```
GET /verify?t=not-a-uuid               → 500   (expected: graceful "INVALID" page)
GET /print?t=not-a-uuid                → 500
GET /download?t=not-a-uuid             → 500
GET /preview?t=not-a-uuid              → 500
GET /participant-intake/not-a-uuid     → 500   (expected: 404 "link is invalid or inactive")
```
Contrast: `GET /verify` (no token) returns a clean **200** "INVALID CERTIFICATE / token is invalid" page — so the graceful path exists but is unreachable for malformed tokens. With `APP_DEBUG=true` (the project default), the 500 response **leaks a full stack trace**: `SQLSTATE[22P02]: invalid input syntax for type uuid: "not-a-uuid"`.

**Expected:** A malformed/truncated token should render the "invalid certificate" page (verify) or return 404 (intake), not a 500.

**Actual:** `certificates.public_token` and `participant_intake_events.public_token` are Postgres `uuid` columns. Querying `where('public_token', 'not-a-uuid')` throws a `QueryException` *before* the intended `if (!$cert)` / `abort(404)` fallback can run. QR codes and shared verification links are public and routinely edited/truncated by users, so this is easily triggered in normal use.

**Root cause:** No validation/format check on the token before it is used in a `WHERE` against a `uuid` column; Postgres rejects the cast and throws.

**Suggested fix (do not apply yet)**
- Validate the token with `Str::isUuid($t)` (or a regex) up front; if it fails, render the existing invalid/404 path instead of querying.
- Alternatively, cast defensively or wrap the lookup so a bad cast maps to "not found."
- Apply consistently to every `public_token` lookup from untrusted input (4 public cert endpoints + intake create/store).
- Ensure `APP_DEBUG=false` in production (see Bug #9) so these never leak stack traces.

---

## 5. [MEDIUM] Test suite is red — `composer run test` fails (4 tests)

**Command/output**
```
composer run test
→ Tests: 4 failed, 47 passed (176 assertions)
```

**Failing tests & root causes**

| Test | File:line | Root cause |
|------|-----------|------------|
| `ExampleTest > returns a successful response` | `tests/Feature/ExampleTest.php:17` | Asserts `GET /` → 200, but `/` now redirects (302) to `/login`. Stale default Breeze test. |
| `AuthenticationTest > login screen can be rendered` | `tests/Feature/Auth/AuthenticationTest.php:19` | Asserts the page contains **"Forgot your password?"**, but the redesigned login view says **"Forgot password?"** (`resources/views/auth/login.blade.php:133`). Stale assertion. |
| `RegistrationTest > new users can register` | `tests/Feature/Auth/RegistrationTest.php:28` | Asserts `assertAuthenticated()` after register, but registration now creates a **pending** user and redirects to `/login` without logging in (by design — see `RegisteredUserController:51`). Test not updated for the approval workflow. |
| `CertificateCustomDostProjectTest > windows 1252 csv names…` | `tests/Feature/Admin/CertificateCustomDostProjectTest.php:69` | The test's own assertion uses `iconv('UTF-8','ASCII//TRANSLIT//IGNORE', …)` and expects `'Pena'`, but macOS libiconv transliterates `ñ` to `~n` → `'Pe~na'`. Platform-dependent/brittle test; the app's UTF-8 normalization (line 70–71) is fine. |

**Expected:** `composer run test` passes, so CI is meaningful.

**Actual:** Three failures are **stale scaffolding tests** that were never updated after the auth flow was customized; one is a **platform-brittle** assertion. None indicates a new app regression, but a red suite masks real regressions and blocks CI.

**Suggested fix (do not apply yet)**
- `ExampleTest`: assert a redirect to `login` (or delete the placeholder).
- `AuthenticationTest`: update the expected string to "Forgot password?".
- `RegistrationTest`: assert the user row is created with `approval_status = pending` and redirected to `login`, and `assertGuest()` instead of `assertAuthenticated()`.
- `CertificateCustomDostProjectTest:69`: drop the environment-dependent `TRANSLIT` assertion or normalize via `mb_*`/a fixed table so it is deterministic across libiconv builds.

---

## 6. [MEDIUM] `/app/{any?}` route returns 500 — missing `public/app/index.html`

**Affected file:** `routes/web.php:122-124`
```php
Route::get('/app/{any?}', function () {
    return File::get(public_path('app/index.html'));
})->where('any', '.*');
```

**Reproduction (verified live):** `GET /app` → **500** (`FileNotFoundException: File does not exist at path .../public/app/index.html`). `public/app/` does not exist in the repo.

**Expected:** `/app/*` either serves the SPA or is removed/returns 404.

**Actual:** 500 for every `/app/*` path. `AGENTS.md` references a separate `certUI/` (TanStack Start) frontend that is **not present** in this repo and whose build output (`public/app/index.html`) is never produced by `npm run build`.

**Root cause:** Dangling route for a frontend bundle that isn't shipped/built in this project.

**Suggested fix (do not apply yet):** Remove the route if the SPA is not part of this deployment, or guard it with `File::exists()` and `abort(404)` otherwise, and document/automate the `certUI` build if it is intended to be served here.

---

## 7. [LOW] `vendor/bin/pint --test` fails — 69 files unformatted

**Command:** `vendor/bin/pint --test` → `result: fail`, 69 files flagged (controllers, models, services, jobs, migrations, routes, seeders, vendored font files `resources/fonts/Montserrat-*.php`).

**Impact:** Style-only; no runtime effect. But `AGENTS.md` lists `vendor/bin/pint` in the verification order, so a lint gate would be red.

**Suggested fix (do not apply yet):** Run `vendor/bin/pint` to auto-format, and consider excluding the generated `resources/fonts/*.php` font dumps via `pint.json`. Keep as a separate formatting-only commit.

---

## 8. [LOW] Intake export help-text shown to RD references a button the RD cannot use

**Affected file:** `resources/views/admin/participant-intakes/index.blade.php:423-433`

**Detail:** When logged in as RD, `GET /admin/participant-intakes` renders the note *"Export Selected CSV moves pending records to done…"*, but the **"Export Selected CSV"** button is gated to `@if ($canEndorse)` (organizer/unit-supervisor only), and the route `admin.participant-intakes.export-selected` is under `role:unit_supervisor,organizer` — the RD gets **403** there (verified: RD `POST .../export-selected` is not permitted via that role group). So the RD sees instructions for an action they can't perform.

**Expected:** Help text matches the controls actually available to the current role.

**Actual:** Mismatched copy for the RD (minor confusion, no security impact).

**Suggested fix (do not apply yet):** Show the RD-specific note only, or gate the "Export Selected CSV moves…" sentence behind `@if ($canEndorse)`. Separately decide whether the RD *should* be able to export-selected (if yes, add `regional_director` to that route group).

---

## 9. [LOW / INFO] Dependency & config notes

- **Abandoned dependency:** `composer install` warns `setasign/fpdi-fpdf` is abandoned with no suggested replacement. It underpins PDF stamping — plan a migration path (e.g. `setasign/fpdi` with a maintained PDF backend).
- **`APP_DEBUG=true` is the committed default** (`.env.example:` `APP_DEBUG=true`). Combined with Bug #4, any 500 leaks stack traces. Ensure production sets `APP_DEBUG=false` (the commented production block does, but the default is debug-on).
- **Migration ordering** (`2026_05_29_*` sort before `2026_06_01_000001_create_recipients_table`) was previously a fresh-DB breaker; it is now correctly handled by `hasTable()` guards + the `2026_09_04_000003_backfill_recipient_profile_columns` repair migration. **Verified:** a fresh `migrate --force` runs all 42 migrations cleanly. No action needed — noted for context.
- **Live browser UI/responsive testing was not performed** (extension not connected). The login view (`resources/views/auth/login.blade.php`) uses heavy inline styles and inline `onclick` handlers; recommend a manual responsive/a11y pass.

---

## Positive findings (working as intended)
- Fresh-DB migrations: all 42 run clean on PostgreSQL.
- `npm run build`: succeeds, emits `manifest.json`.
- Staff login/logout, session regeneration, and post-logout route protection: correct.
- Role enforcement on admin routes: RD-only areas (`/admin/users`, `/admin/certificates/approvals`, `/admin/account-settings`) correctly return 403 to non-RD and to the bypassed pending account.
- Intake-event CRUD happy path (organizer): create → toggle → delete all succeed and persist.
- Registration validation: invalid role (`regional_director`) and duplicate email are both rejected; no row created.
- Pending/rejected approval gate: works correctly for the normal login path (the bypass in Bug #1 is the exception).
- Public verify with a *missing* token: clean "INVALID" page (200).
- Certificate generation queue/batch/rollback: covered by existing passing feature tests.

---

## Note on test authoring (task step 7)
Automated tests **do** exist (15 feature/unit files). Per the task, no new test files were added. If you want regression coverage for the issues above, I can propose a minimal plan (e.g. tests for the approval-gate bypass, the unauthenticated search endpoint, and non-UUID token handling) for your approval before writing any test files.
