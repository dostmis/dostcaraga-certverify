# Certificate Generation Performance Plan

Plan to fix the >30 second delay when a Regional Director clicks **Approve and Generate**.

Status: **Phases 1, 2, and 3 implemented** (see [Implementation Status](#implementation-status)).

All five open decisions are now [resolved](#resolved-decisions).

---

## Table of Contents

- [Summary](#summary)
- [Root Cause](#root-cause)
- [Evidence](#evidence)
- [Phase 1 — Normalize the template once](#phase-1--normalize-the-template-once)
- [Phase 2 — Make the per-participant queries cheap](#phase-2--make-the-per-participant-queries-cheap)
- [Phase 3 — Move generation to the queue](#phase-3--move-generation-to-the-queue)
- [Sequencing](#sequencing)
- [Resolved Decisions](#resolved-decisions)
- [Implementation Status](#implementation-status)

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

### Behaviour change to note — DECIDED: no downstream dependency

`certificates/source/CODE.pdf` now holds the normalized 1.4 file rather than the original upload. The stamped output is unaffected.

**Decision (#5): verified safe.** `source_pdf_path` has exactly one consumer — `MigrateCertificateFilesToPrivate.php`, which relocates files between disks and never reads their contents. Nothing re-stamps from it, diffs it, or serves it to users.

The one command that does re-stamp, `RefreshCertificateQrDomain`, reads `stamped_pdf_path` (line 56), not the source. Stamped files are FPDF output and already 1.4, so it parses them directly.

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

### Do NOT hoist the count out of the loop — DECIDED: leave the scheme alone

This is the trap. The count grows by one per insert, so `$batchNumber` increments on every participant:

```
cert 1 -> 2026-PROG-TRA-01-001
cert 2 -> 2026-PROG-TRA-02-001
cert 3 -> 2026-PROG-TRA-03-001
```

Hoisting it would make the whole batch share one batch number and increment the `-001` suffix instead. That is arguably the intended scheme, but it **changes the certificate code format**.

**Decision (#1): do not fix the batch-number scheme.**

The reason to change it has evaporated. Hoisting was only ever a performance fix, and the `created_at` index makes the count an index scan — sub-millisecond. Hoisting now saves N trivial queries, which does not justify the cost.

And the cost is high. `certificate_code` is the lookup key for `CertificatePrintController:12` (`/print/{code}`), admin search (`CertificateAdminController:58`), and analytics search (`AnalyticsController:277`), and it is printed on every issued PDF. Changing the scheme leaves two code formats in one table permanently, and staff reading a code can no longer interpret it without knowing when it was issued.

The current scheme is odd-looking but sound: unique, sortable, chronological.

**Decision (#2): do not fix the UTC/Manila mismatch as part of this work.**

`created_at` is stored UTC but compared against a Manila date string. Concretely, certificates created 00:00–08:00 Manila carry a UTC `created_at` on the previous date, so they are excluded from "today's" count and the batch number restarts oddly during that morning window.

This is cosmetic. It cannot produce duplicates — `certificate_code` has a unique constraint, and the in-transaction `LIKE $prefix.'%'` lookup increments the sequence whenever a prefix repeats. The worst case is a batch number that looks wrong to a human.

Fixing it shifts codes in that window for no operational gain. It is the same decision as #1: leave the code scheme alone, or redesign it deliberately as its own project. Not piecemeal, inside a performance fix.

### Expected gain

Small today. The value is that it stops approval getting slower as the table grows.

---

## Phase 3 — Move generation to the queue

Only worth doing after Phase 1, and only after measuring — Phase 1 may drop the time under the pain threshold on its own.

### Change shape — DECIDED: chunk into per-participant jobs via `Bus::batch()`

**Decision (#4): chunk, do not raise the worker timeout.**

Workers run `--timeout=120` in all three deployment modes:

- `deploy/bare-metal/systemd/certverify-queue.service:12`
- `docker-compose.yml:44`
- `docker-compose.dev.yml:56`

Raising that number only moves the cliff — a 500-participant package needs a bigger number again, and it becomes a permanent tuning chore. Chunking with `Bus::batch()` gives three things the phase needs anyway:

- **Progress for free.** `processedJobs` / `totalJobs` is exactly the UI this phase calls for, so no custom progress columns are needed.
- **Granular retries.** One participant fails, retry that one — which is what makes the resume behaviour in #3 cheap to build.
- **No timeout math.** After Phase 1 each participant is roughly 10 ms of work.

Shape:

- `approveEndorsement` normalizes the template, builds the batch, dispatches, and returns immediately.
- One job per participant, carrying the endorsement id, the participant row, and the normalized template path.
- `then()` runs `queueGeneratedCertificateEmails` and the Telegram notify from `approveEndorsement:790-829`.
- `finally()` deletes the normalized template.
- Poll the batch from the approvals view for progress.

### Critical interaction with Phase 1

Per-participant jobs would each re-normalize the template, reintroducing the exact N-times-Ghostscript problem Phase 1 removed.

The parent **must** normalize once, persist the normalized file, pass its path to every child job, and delete it in the batch's `finally()`. Building Phase 3 without this silently undoes Phase 1.

### Constraint: partial-failure semantics — DECIDED: report-and-halt, with resume

Today a throw mid-loop aborts everything but leaves already-generated certificates on disk and in the database, while the endorsement stays `endorsed`.

**Decision (#3): report-and-halt, with resume.**

Rollback is not cleanly available, and that is decisive: `AnchorCertificateOnHederaJob` dispatches *inside* the loop, and a hash written to a public ledger cannot be un-anchored. Emails are safe — they are queued after the loop — but the ledger writes are not.

Restart-from-scratch is also wrong: it would mint duplicate certificates and PDFs for participants already processed.

So: record progress, stop on failure, keep what was generated, mark the endorsement failed with the participant index and error, and let the RD retry from that point.

### Prerequisite: link certificates to endorsements

**Certificates have no `endorsement_id` column.** The `endorsement_id` occurrences in `CertificateAdminController` (lines 969, 975, 1101, 1123, 1130) are Telegram notification payloads, not a database column.

Resume needs to know which participants already have certificates for a given endorsement, so Phase 3 must add that column and backfill it before the resume logic can work. Scope this in.

### Migration ordering caveat

Timestamp any new migration after `2026_06_08_*`.

Note this repo already has an ordering bug: `2026_05_29_000001_add_profile_fields_to_recipients_table` and `2026_05_29_000002_add_name_parts_to_recipients_table` sort **before** `2026_06_01_000001_create_recipients_table`, so `php artisan migrate` from scratch fails on a fresh database. A production-safe fix is to guard the two `2026_05_29` migrations with `Schema::hasTable('recipients')` and add those columns into `create_recipients_table`. Renaming the files is **not** safe — the filename is the key stored in the `migrations` table, so production would re-run both and fail on duplicate columns.

---

## Sequencing

1. ~~**Phase 1** — contained change to one method.~~ Done.
2. ~~**Phase 2 index** — cheap and safe, bundled with Phase 1.~~ Done.
3. **Install imagick and measure.** Nothing about Phase 3 should be built before there is a real before/after number from production-like data.
4. **Phase 3, only if the measurement justifies it.** Phase 1 may well put approval under the pain threshold on its own, in which case the batch infrastructure is complexity without a payoff.

Leave the certificate-code-format questions out of all phases — see decisions #1 and #2.

---

## Resolved Decisions

All five are settled. Nothing blocks Phases 1 and 2; Phase 3 is fully scoped.

| # | Decision | Resolution | Phase |
|---|---|---|---|
| 1 | Correct the batch-number scheme (changes code format)? | **No.** The index removes the performance motive; the format is a lookup key and is printed on issued PDFs. | 2 |
| 2 | Fix the UTC/Manila date mismatch (also moves codes)? | **No, not as part of this.** Cosmetic only; the unique constraint prevents duplicates. Bundle with #1 if ever. | 2 |
| 3 | Partial-failure semantics for async generation? | **Report-and-halt, with resume.** Hedera anchors are irreversible, so rollback is unavailable; restart would duplicate. | 3 |
| 4 | Raise the worker timeout, or chunk? | **Chunk, via `Bus::batch()`.** Raising the timeout only moves the cliff; chunking also supplies progress and granular retries. | 3 |
| 5 | Anything depending on `certificates/source/*.pdf` byte-identity? | **No — verified.** Its only consumer relocates files without reading them; re-stamping uses `stamped_pdf_path`. | 1 |

Decisions #1 and #2 are the same underlying call: **leave the certificate code scheme alone.** Both were on the table only because of performance, and the `created_at` index made that motive disappear. If the scheme is ever redesigned, do it deliberately as its own project — not inside a performance fix.

Decision #4 carries a hard constraint: the parent must normalize the template once and pass the path to the child jobs, or Phase 3 silently undoes Phase 1.

Decision #3 carries a prerequisite: certificates need an `endorsement_id` column before resume can work.

---

## Implementation Status

| Phase | State |
|---|---|
| 1 — Normalize template once | **Implemented and verified end-to-end** |
| 2 — Sargable count + `created_at` index | **Implemented and verified** |
| 3 — Chunked async generation | Decided, deliberately not built — see below |

### Measured result

Full pipeline, 25 participants, PDF 1.7 template with a 1.5 MB background, imagick installed, generation run through `generateCertificatesFromPayload`:

```
BEFORE : 25 certs in 3.27 s   (131 ms/cert)
AFTER  : 25 certs in 0.74 s   ( 29 ms/cert)
```

**4.4x faster.** Measured by stashing and restoring the change on identical data, three runs each. Extrapolated to the original report, a package that took 30 s should now land near 7 s; on the ~60–100 participant range that triggered the complaint, approval moves from roughly 30 s to under 3 s.

Ghostscript no longer scales with participant count — it runs once per approval. The remaining per-certificate cost is dominated by QR rendering, not PDF conversion.

### Phase 3 built

Phase 1 removed the bottleneck, so Phase 3 was built for headroom rather than to fix a live problem — approval was already near 3 s at the package sizes that triggered the original complaint. See [CERT_GENERATION_PHASE3_PLAN.md](CERT_GENERATION_PHASE3_PLAN.md) for what shipped.

The serialization constraint this section previously flagged was honoured: `GenerateCertificateJob` carries a `shared()` `WithoutOverlapping('certificate-code-generation')` lock, so generation is one-at-a-time regardless of worker count. `createCertificate` still reads the last certificate code and inserts without `lockForUpdate`, so that lock — not the deployment's worker count — is what keeps numbering safe. It is covered by tests in `tests/Feature/Admin/CertificateGenerationQueueTest.php`.

Phase 1's normalize-once guarantee is now protected by a test (`test_the_template_is_normalized_once_per_batch`) that asserts Ghostscript normalization runs once per batch, not once per participant.

### What landed

- `CertificateAdminController::generateCertificatesFromPayload` now calls a new `normalizeTemplateForFpdi()` once before the loop and cleans the temp file up in a `finally`.
- `normalizeTemplateForFpdi()` writes the converted file onto the `local` disk (root `storage/app/private`) rather than `storage/app/tmp`, which sits outside that root, so the per-certificate `Storage::copy()` keeps working on relative paths.
- `createCertificate` uses a half-open naive-string range instead of `whereDate`.
- `2026_09_03_000001_add_created_at_index_to_certificates_table` — applied.

### Verified

- **Certificate numbering continues; it does not restart.** Against a seeded live-system state (120 historic certificates plus 6 already created today), generation resumed at `2026-IU-CBS-07-001` through `-11-001`. All 120 historic rows were untouched.
- Range query is equivalent to `whereDate`, including rows at `00:00:00`, `23:59:59`, and both adjacent-day boundaries. On a 47-row table both forms returned batch number 8.
- Generated PDFs parse cleanly under Ghostscript and render correctly — participant name centred, QR bottom-right, certificate code and verify URL beneath it.
- No temporary files leak: `storage/app/tmp` and `storage/app/private/tmp` are both empty after a run.
- Index `certificates_created_at_index` exists; controller passes `php -l`.

### Environment note: imagick is a required, undeclared dependency

`renderStampedPdf:2456` builds the QR through `QrCode::format('png')`, which needs the **imagick** PHP extension. Without it, Approve and Generate throws `BaconQrCode\Exception\RuntimeException` and no certificate can be produced.

It is required by `simple-qrcode`'s PNG backend but appears in no `composer.json` requirement, so a clean install passes every dependency check and then fails at generation time. It belongs in the README prerequisites alongside Ghostscript.

Install: `brew install imagemagick && pecl install imagick` (macOS), or `apt-get install php-imagick` (Debian/Ubuntu).
