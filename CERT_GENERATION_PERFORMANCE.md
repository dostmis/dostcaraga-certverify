# Certificate Generation Performance Plan

Plan to fix the >30 second delay when a Regional Director clicks **Approve and Generate**.

Status: **proposed — no code changed yet.**

---

## Table of Contents

- [Summary](#summary)
- [Root Cause](#root-cause)
- [Evidence](#evidence)
- [Phase 1 — Normalize the template once](#phase-1--normalize-the-template-once)
- [Phase 2 — Make the per-participant queries cheap](#phase-2--make-the-per-participant-queries-cheap)
- [Phase 3 — Move generation to the queue](#phase-3--move-generation-to-the-queue)
- [Sequencing](#sequencing)
- [Open Decisions](#open-decisions)

---

## Summary

Approval generates every certificate synchronously inside the HTTP request. Within that loop, Ghostscript re-converts the **same** template PDF once per participant. Measured on an M-series Mac, that is ~105 ms per certificate of which **98.5% is Ghostscript**, scaling linearly with participant count.

Converting the template once instead of N times is a contained change to a single method and is expected to take a 30 s approval down to roughly 3–5 s.

| Phase | Change | Expected gain | Risk |
|---|---|---|---|
| 1 | Normalize template once before the loop | ~10x; removes the dominant cost | Low |
| 2 | Index `created_at`, make the daily count sargable | Small now; prevents growth-driven slowdown | Low (see caveat) |
| 3 | Move generation to a queued job | Instant response; removes timeout risk | Medium |

---

## Root Cause

`approveEndorsement` (`app/Http/Controllers/Admin/CertificateAdminController.php:774`) does all the work in-request. The loop in `generateCertificatesFromPayload:1750` calls `stampCertificatePdf` per participant, and inside `renderStampedPdf:2470`:

```php
try   { $pageCount = $pdf->setSourceFile($sourceAbs); }
catch { $converted = $this->convertPdfWithGhostscript($sourceAbs); ... }
```

The free FPDI cannot read PDF 1.5+ compressed cross-reference streams. Anything exported from Word, Canva, Illustrator, or a scanner is 1.5–1.7, so `setSourceFile` throws and the Ghostscript fallback fires.

The critical detail is at **line 1787**: the code copies the template to a per-certificate path *first*, then converts that copy. The identical conversion therefore runs N times instead of once.

Two secondary factors:

- **Nothing is queued.** Only the Hedera anchor and the emails are jobs (`QUEUE_CONNECTION=database`, so those dispatches are cheap). Generation itself blocks the browser for the whole batch, and a large batch will eventually hit a PHP `max_execution_time` or an nginx 504 rather than merely being slow.
- **Two unindexable queries per participant** in `createCertificate:2346`, which get slower as the `certificates` table grows.

---

## Evidence

FPDI parse behaviour by PDF version:

```
heavy14.pdf    5.5 ms  OK pages=1
heavy17.pdf    1.0 ms  THROWS: CrossReferenceException
                       "compression technique which is not supported"
```

Faithful simulation of the per-certificate loop, 1.5 MB template:

```
N=100  TOTAL 10.45 s   (105 ms/cert)
  template copy :   0.01 s
  ghostscript   :  10.40 s   <-- 98.5%
  FPDI re-parse :   0.03 s
  write stamped :   0.02 s
```

Same work, normalizing the template once up front:

```
N=25   TOTAL 0.25 s  (10 ms/cert)   [one-time normalize: 0.22 s]
```

Measurements are from an M-series Mac. A regional-office server runs Ghostscript roughly 3–5x slower, putting ~300–500 ms per certificate, which places the 30 s threshold at about **60–100 participants**.

Index check on `certificates` confirms **no index on `created_at`** (21 indexes exist; none covers it).

---

## Phase 1 — Normalize the template once

**File:** `app/Http/Controllers/Admin/CertificateAdminController.php`
**Method:** `generateCertificatesFromPayload:1733`

### Change shape

Before the `foreach` at line 1750, resolve the template to a guaranteed-FPDI-readable file and use that as the copy source inside the loop:

1. Resolve `$templatePath` to an absolute path.
2. Attempt `(new Fpdi())->setSourceFile($abs)` once, in a try/catch.
3. On `CrossReferenceException`, call the existing `convertPdfWithGhostscript()` **once**, storing the result in a local.
4. Use the normalized path as the source for `$storage->copy(...)` at line 1787, for every participant.
5. `@unlink` the temp in a `finally`, so it is cleaned up even when a participant row throws.

### Why here, not at upload

This covers both callers — `store:600` (direct RD generation) and `approveEndorsement:796` — **and** it fixes endorsements already sitting in the database awaiting approval, whose templates were stored before any fix.

Normalizing at `storeTemplatePdfForRequest:351` instead would only help new uploads and would leave the current approval queue slow. That location is a reasonable follow-on, not the primary fix.

### No signature changes

`renderStampedPdf`'s existing try/catch at line 2470 stays exactly as-is — it simply always succeeds on the first attempt, because it is handed a 1.4 file. This keeps the preview paths (`1494`, `1544`, `3209`) working untouched.

### Do not change the Ghostscript preset

An earlier suggestion to drop `-dPDFSETTINGS=/prepress` should **not** be carried out. That mattered when Ghostscript ran N times; once it is one-time, its cost is irrelevant, and `/default` downsamples embedded images to 72 dpi, which visibly degrades a certificate background on print. Leave the preset alone.

### Behaviour change to note

`certificates/source/CODE.pdf` will hold the normalized 1.4 file rather than the original upload. The stamped output is unaffected. Confirm nothing downstream expects the byte-identical original.

### Verification

- Approve a package of ~50 with a PDF 1.7 template; time before and after.
- Diff a stamped PDF from each run to confirm output is visually identical.
- Confirm `storage/app/tmp` has no leftover `fpdi_*.pdf` files after a run.
- Re-check the preview endpoints still render.

---

## Phase 2 — Make the per-participant queries cheap

`createCertificate:2346` runs `Certificate::whereDate('created_at', ...)->count()` once per participant, against a table with no index on `created_at` — a sequential scan per certificate.

### Do this

- Add an index on `certificates.created_at`.
- Replace `whereDate(...)` with a half-open range (`created_at >= $start AND created_at < $end`). `whereDate` wraps the column in a function and cannot use the index even once it exists; the range form is sargable and returns identical results.

### Do NOT hoist the count out of the loop

This is the trap. The count grows by one per insert, so `$batchNumber` increments on every participant:

```
cert 1 -> 2026-PROG-TRA-01-001
cert 2 -> 2026-PROG-TRA-02-001
cert 3 -> 2026-PROG-TRA-03-001
```

Hoisting it would make the whole batch share one batch number and increment the `-001` suffix instead. That is arguably the intended scheme, but it **changes the certificate code format**, which is printed on issued PDFs and embedded in verification URLs. Treat it as a separate product decision, not a performance fix.

The same reasoning applies to a latent timezone bug in the same expression: `created_at` is stored UTC but compared against a Manila date string, so codes shift around midnight. Real, but fixing it also moves codes.

### Expected gain

Small today. The value is that it stops approval getting slower as the table grows.

---

## Phase 3 — Move generation to the queue

Only worth doing after Phase 1, and only after measuring — Phase 1 may drop the time under the pain threshold on its own.

### Change shape

- New `app/Jobs/GenerateEndorsementCertificatesJob`, wrapping the `generateCertificatesFromPayload` + `queueGeneratedCertificateEmails` + Telegram-notify sequence from `approveEndorsement:790-829`.
- Add progress columns to `certificate_endorsements`: `generation_status`, `generation_total`, `generation_progress`, `generation_error`.
- `approveEndorsement` sets status and dispatches; the button returns immediately.
- Poll from the approvals view to show progress and surface failures.

### Constraint: worker timeout

Workers run `--timeout=120` in all three deployment modes:

- `deploy/bare-metal/systemd/certverify-queue.service:12`
- `docker-compose.yml:44`
- `docker-compose.dev.yml:56`

A large batch will exceed that and be killed mid-generation. Either raise the timeout for this queue, or chunk the work into per-participant jobs. **Chunking is preferred** — it also makes retries safe.

### Constraint: partial-failure semantics

Today a throw mid-loop aborts everything but leaves already-generated certificates on disk and in the database, while the endorsement stays `endorsed`. Async makes that state visible to users. Decide up front: resume, roll back, or report-and-halt.

### Migration ordering caveat

Timestamp any new migration after `2026_06_08_*`.

Note this repo already has an ordering bug: `2026_05_29_000001_add_profile_fields_to_recipients_table` and `2026_05_29_000002_add_name_parts_to_recipients_table` sort **before** `2026_06_01_000001_create_recipients_table`, so `php artisan migrate` from scratch fails on a fresh database. A production-safe fix is to guard the two `2026_05_29` migrations with `Schema::hasTable('recipients')` and add those columns into `create_recipients_table`. Renaming the files is **not** safe — the filename is the key stored in the `migrations` table, so production would re-run both and fail on duplicate columns.

---

## Sequencing

1. **Phase 1** — contained change to one method; ship and measure on real data.
2. **Phase 2 index** — cheap and safe, fine to bundle with Phase 1.
3. **Re-measure.** Decide whether Phase 3's complexity is still justified.

Leave the certificate-code-format questions out of all three phases.

---

## Open Decisions

These need a human call before or during implementation:

| # | Decision | Blocks |
|---|---|---|
| 1 | Should the batch-number scheme be corrected (changes certificate code format)? | Phase 2 hoist |
| 2 | Should the UTC/Manila date mismatch in code generation be fixed (also moves codes)? | Phase 2 |
| 3 | Partial-failure semantics for async generation: resume, roll back, or report-and-halt? | Phase 3 |
| 4 | Raise worker timeout, or chunk into per-participant jobs? | Phase 3 |
| 5 | Is anything downstream depending on `certificates/source/*.pdf` being the byte-identical original upload? | Phase 1 |
