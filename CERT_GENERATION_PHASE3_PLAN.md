# Phase 3 — Asynchronous Certificate Generation

Plan to move certificate generation off the HTTP request and onto the queue, with progress reporting and all-or-nothing failure handling.

Status: **implemented, 10 September 2026.** See [As built](#as-built).

Companion to [CERT_GENERATION_PERFORMANCE.md](CERT_GENERATION_PERFORMANCE.md), which covers Phases 1 and 2.

> **Revised 3 September 2026 after the Hedera removal** (see
> [HEDERA_REMOVAL_PLAN.md](HEDERA_REMOVAL_PLAN.md)). Removing the ledger anchor
> eliminated the only irreversible side effect inside the generation loop, which
> **reversed this plan's central failure-handling decision**: rollback became
> possible, and simpler than the resume design it replaced.
>
> **Built 10 September 2026 with rollback confirmed.** None of the triggers in
> [When to build this](#when-to-build-this) had fired — this was built for
> headroom, as an explicit decision, not to fix a live problem. That trade is
> recorded here honestly so a future reader does not mistake it for necessity.

---

## Table of Contents

- [As built](#as-built)
- [When to build this](#when-to-build-this)
- [What Phase 3 delivers](#what-phase-3-delivers)
- [Inherited constraints](#inherited-constraints)
- [Architecture](#architecture)
- [Concurrency and certificate numbering](#concurrency-and-certificate-numbering)
- [Data model changes](#data-model-changes)
- [Work breakdown](#work-breakdown)
- [Failure semantics](#failure-semantics)
- [User interface](#user-interface)
- [Testing](#testing)
- [Rollout and backout](#rollout-and-backout)
- [Risks](#risks)
- [Open questions](#open-questions)

---

## As built

### Decisions taken

| # | Question | Decision |
|---|---|---|
| 1 | Rollback or resume on failure? | **Rollback.** All-or-nothing; a failed package returns to `endorsed`. |
| 2 | Email already-generated certificates on partial failure? | Moot — no certificates survive a failure. |
| 3 | Telegram notification on failure? | **Yes**, and deliberately *not* gated by `TG_BULK_THRESHOLD`. |
| 4 | Stuck-in-`generating` threshold? | **Not built.** No scheduled stuck-batch check ships in this phase — see [Known gaps](#known-gaps). |
| 5 | Move the direct-RD `store` path to the queue? | **No**, it stays synchronous. |
| 6 | Backfill `endorsement_id` on historic certificates? | **No**, historic rows stay `NULL`. |

### Files

| Path | Role |
|---|---|
| `app/Services/CertificateGenerationService.php` | Certificate row creation and PDF stamping, extracted from the controller so the synchronous and queued paths share one implementation. |
| `app/Services/EndorsementGenerationCoordinator.php` | Claim, dispatch, finalize, rollback, progress. Batch callbacks live here because serialized closures cannot reach controller methods. |
| `app/Services/EndorsementTelegramNotifier.php` | Endorsement Telegram notifications, including the new generation-failure alert. |
| `app/Jobs/GenerateCertificateJob.php` | One participant per job, with the serialization lock and idempotency guard. |
| `database/migrations/2026_09_04_000001_*` | `certificates.endorsement_id`, `certificates.endorsement_participant_index`. |
| `database/migrations/2026_09_04_000002_*` | `generation_batch_id`, `generation_error`, `generation_started_at`. |

### Deviations from the plan as written

**The shared loop body is a service, not a private controller method.** Step 3 called for extracting the loop body "into a shared private method". A queued job cannot call a private controller method, so the certificate-creation and PDF-stamping machinery moved into `CertificateGenerationService` (~850 lines) and the controller now delegates to it. The controller's preview endpoints call the same service, so previews and issued certificates cannot drift either. The controller's `STANDARD_NAME_*` and `NOT_APPLICABLE` constants are now aliases of the service's, rather than duplicates.

**Idempotency is keyed on a participant index, not a name.** The plan's guard was "`endorsement_id` + participant index", but the only proposed column was an index on `['endorsement_id', 'participant_name']`. Names are not unique within a package — two participants can legitimately share one — so a new `certificates.endorsement_participant_index` column carries the slot, with a unique constraint on `['endorsement_id', 'endorsement_participant_index']` as a database-level backstop behind the job's check-then-insert. NULLs do not collide, so the direct-RD path and historic rows are unaffected.

**The lock is `shared()`.** `WithoutOverlapping` keys on the job class by default, which already serializes every `GenerateCertificateJob`. Calling `->shared()` keys it on the string alone, so a future job that also creates certificates serializes against this one instead of racing it.

**Migrations had to be repaired to run the mandatory tests.** The plan noted the `2026_05_29_*_recipients` ordering bug in passing. In practice `migrate` could not build a fresh database at all, and 25 of 28 existing tests failed before any Phase 3 work started, so the mandatory numbering test was unrunnable. Four migrations were fixed:

- `2026_02_03_070210_add_public_token_to_certificates_table` — raw Postgres `ALTER TABLE ... ADD CONSTRAINT`, now driver-guarded.
- `2026_02_10_120000_rename_state_to_province_on_certificates_table` — raw `RENAME COLUMN` / `ALTER INDEX`, now uses the schema builder with the Postgres-only `ALTER INDEX` guarded.
- `2026_03_04_000500_expand_dost_program_length_on_certificates_table` — Postgres-only `ALTER COLUMN TYPE`, now a no-op elsewhere.
- `2026_05_29_000001` / `2026_05_29_000002` recipients migrations — skip when the table does not exist yet, with `2026_09_04_000003_backfill_recipient_profile_columns` adding those columns after the table is created. Every column is `hasColumn`-guarded, so it is a no-op on an already-migrated environment.

Postgres behaviour is unchanged in all cases; the guards only affect drivers that were previously failing outright.

### Test coverage

21 tests across four files, all passing:

| File | Covers |
|---|---|
| `tests/Feature/Admin/CertificateGenerationQueueTest.php` | Numbering continuity against seeded historic data, no duplicate codes, historic rows untouched, normalize-once-per-batch, all jobs share one template path, idempotency on replay, double-submit claim, lock key is global, a job is released rather than run while the lock is held, emails sent exactly once, temp cleanup on success, progress reporting. |
| `tests/Feature/Admin/CertificateGenerationRollbackTest.php` | Rows and both PDFs deleted, **other endorsements untouched**, historic `NULL`-linked rows untouched, status returns to `endorsed` with the error recorded, no emails on failure, temp cleanup on failure, rollback-then-re-approve completes with no code collisions, rollback refuses to run without an endorsement id. |
| `tests/Feature/Admin/CertificateApprovalRequestTest.php` | Approval returns immediately with nothing generated in-request, double-submit dispatches no extra jobs, progress endpoint output and RD authorization, a `generating` package stays visible in the approvals queue. |
| `tests/Feature/Admin/Concerns/` | Shared setup and a `FailingCertificateGenerationService` double that generates for real until it reaches a named participant, then throws. |

Tests run against the `database` queue driver, not `sync`: `sync` runs jobs inline during dispatch and bypasses the batch semantics — halt-on-failure, the middleware, and the `then`/`catch`/`finally` callbacks — that this feature depends on.

Four pre-existing test failures remain and are unrelated to this phase: a macOS `iconv //TRANSLIT` difference, and three stock Breeze assertions that no longer match the customised login, registration, and landing pages.

### Known gaps

**No stuck-batch detection.** Open question #4 was answered "not now", so nothing flags an endorsement sitting in `generating` past a threshold. With generation on the queue, a dead worker means certificates are never generated and the package sits in `generating` indefinitely, visible in the approvals queue with a progress bar that never advances. Until a scheduled check exists, queue-worker monitoring is the only thing standing between a dead worker and a silently stalled approval. This is the highest-value follow-up.

**Two genuinely parallel workers are not tested.** The plan asked for a two-worker concurrency test. In-process tests cannot run two workers, so serialization is proven differently: a test holds the code lock and asserts the queued job is released rather than run, then asserts it completes once the lock is freed. That proves the lock is enforced; it does not prove behaviour under real OS-level concurrency. Worth exercising on staging with `--processes=2` before scaling workers in production.

---

## When to build this

**This section is preserved as written, before the phase was built.** None of these triggers had fired; the phase was built anyway, as a deliberate choice for headroom.

**Not yet.** Phase 1 cut generation from 131 ms to 29 ms per certificate — a 4.4x improvement that took the reported 30 second approval to roughly 3 seconds at the package sizes that triggered the complaint. The bottleneck this phase was designed to solve is largely gone.

Build Phase 3 when at least one of these becomes true:

| Trigger | Threshold |
|---|---|
| Package sizes grow | Routine packages above ~500 participants (~15 s in-request) |
| Gateway timeouts appear | Any nginx 504 or PHP `max_execution_time` on approval |
| Per-certificate cost rises | New stamping work (extra pages, heavier templates, image processing) |
| RD workflow demands it | Staff need to approve and walk away rather than hold the browser open |

Until then this phase adds a job, a database column, rollback logic, and a polling UI to solve a problem that measures at 3 seconds. That is a poor trade.

**Measure before building.** Re-run the benchmark in `CERT_GENERATION_PERFORMANCE.md` against real production package sizes. If approval is comfortably under ~10 seconds, close this plan.

---

## What Phase 3 delivers

1. **Approval returns immediately.** The RD clicks Approve and Generate and gets a response in well under a second, regardless of package size.
2. **Visible progress.** The approvals view shows "generating 47 of 120" rather than a spinning browser tab.
3. **No timeout ceiling.** Package size stops being bounded by request or worker timeouts.
4. **Clean failures.** A failure part-way through rolls the package back to its pre-approval state. No partial packages, no half-issued certificates, and the RD simply approves again.

---

## Inherited constraints

These come from Phases 1 and 2 and are **not optional**. Violating either silently undoes completed work.

### The template must be normalized once, in the parent

Phase 1's entire gain comes from calling `normalizeTemplateForFpdi()` a single time before the participant loop. If each per-participant job normalizes its own template, Ghostscript runs N times again and the 4.4x improvement is lost.

The parent must:

1. Normalize the template once, before dispatching.
2. Persist the normalized file at a stable path.
3. Pass that path to every child job.
4. Delete it in the batch's `finally()` — **not** at the end of any individual job.

### Certificate numbering must stay serialized

See the next section. This is the single highest-risk aspect of the phase.

---

## Architecture

```
approveEndorsement (HTTP, returns immediately)
    │
    ├─ guard: status must be `endorsed`; flip to `generating` atomically
    ├─ parse participants file once
    ├─ normalizeTemplateForFpdi()  ──► storage/app/private/tmp/batch_<id>.pdf
    │
    └─ Bus::batch([ GenerateCertificateJob × N ])
           │  each job: one participant, carries endorsement id + row + template path
           │  each job: WithoutOverlapping('certificate-generation')
           │
           ├─ then()    → queueGeneratedCertificateEmails + Telegram notify
           │              + status = rd_approved
           ├─ catch()   → ROLLBACK: delete this endorsement's certificates
           │              + their PDFs, status back to `endorsed`, record error
           └─ finally() → delete normalized template
```

Note that `catch()` is the only destructive path in the design, and it is reachable only after a job has already failed. Everything it deletes was created by the same batch moments earlier and has not left the system — no emails were sent, and since the Hedera removal there is no external ledger write either.

### Why `Bus::batch()` and not a plain loop in one job

A single job containing the whole loop reintroduces the timeout problem it was meant to solve — workers run `--timeout=120` in every deployment mode, and a large package would be killed mid-generation leaving inconsistent state.

`Bus::batch()` gives three things this phase needs anyway:

- **Progress for free** via `processedJobs` / `totalJobs` — no custom counter columns.
- **Granular retries** — one participant fails, retry that one.
- **Halt-on-failure by default** — a batch cancels its remaining jobs on the first failure unless `allowFailures()` is called, and only then does `catch()` fire. That is exactly the trigger rollback needs, so **do not call `allowFailures()`** — doing so would let the batch run to completion and skip the rollback entirely.

`job_batches` already exists (created by `0001_01_01_000002_create_jobs_table.php`), so no queue infrastructure work is required.

---

## Concurrency and certificate numbering

**This is the critical risk of the phase.** Certificate codes are printed on issued PDFs and are the lookup key for `/print/{code}`, admin search, and analytics search. Corrupting them is not recoverable by redeploying.

### The race

`createCertificate` computes a code like this:

```php
$batchNumber = Certificate::where('created_at', ...)->count() + 1;   // outside the transaction
return DB::transaction(function () {
    $last = Certificate::where('certificate_code', 'like', $prefix.'%')
        ->orderByDesc('certificate_code')->value('certificate_code');
    $code = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    return Certificate::create([...]);
});
```

There is no `lockForUpdate` and no advisory lock. Under Postgres READ COMMITTED, two concurrent workers both read the same `$last`, both compute the same `$code`, and both insert. One wins; the other hits the `certificates_certificate_code_unique` violation and the job fails.

This is latent today and harmless only because **a single worker runs** — `docker-compose.yml:44` and `deploy/bare-metal/systemd/certverify-queue.service:12`, neither with replicas or `--processes`. Chunking into per-participant jobs makes the race reachable the moment anyone scales workers.

### Decision: serialize generation

Apply `WithoutOverlapping` middleware to the generation job, keyed on a single global string:

```php
public function middleware(): array
{
    return [
        (new WithoutOverlapping('certificate-code-generation'))
            ->releaseAfter(5)
            ->expireAfter(120),
    ];
}
```

This guarantees one-at-a-time execution **regardless of how many workers run**, so numbering safety does not depend on deployment configuration that a future operator might change.

Trade-offs, stated honestly:

- On the current single worker there is zero contention — the lock is always free.
- On multiple workers, losing jobs are released back to the queue and retried, causing some churn. `releaseAfter(5)` bounds it.
- Serialization means no parallel speedup. This is acceptable: after Phase 1 each certificate is ~29 ms, so 500 participants is ~15 seconds of wall clock in the background where nobody is watching.
- Requires a cache driver that supports atomic locks. `CACHE_STORE=database` supports them.

### Alternative considered and rejected

Adding `lockForUpdate` to the code lookup would allow genuine parallelism. Rejected because it is a subtler change to the numbering path, introduces lock contention on the hot table, and the parallel speedup is worthless when each unit is 29 ms. Serialization is the cheaper, more obviously-correct guarantee.

### Non-negotiable verification

Before this ships, prove numbering is intact — see [Testing](#testing). A test that generates a package against seeded historic data and asserts codes continue rather than restart is mandatory, not optional.

---

## Data model changes

### 1. `certificates.endorsement_id` — required for rollback

Rollback needs to know which certificates belong to the failed endorsement so it can delete exactly those and nothing else. **No such link exists today.** The `endorsement_id` occurrences in `CertificateAdminController` (lines 969, 975, 1101, 1123, 1130) are Telegram notification payloads, not a column.

This column is required under either failure strategy — rollback needs it to scope the deletion, resume needed it to scope the skip — so it is a prerequisite regardless of how the open question is settled.

```php
$table->foreignId('endorsement_id')->nullable()
      ->constrained('certificate_endorsements')->nullOnDelete();
$table->index(['endorsement_id', 'participant_name']);
```

Nullable, because certificates from the direct-RD path (`store`) have no endorsement, and historic rows cannot be attributed.

**Backfill:** existing certificates cannot be reliably linked — there is no stored association, and matching on training title plus date would be guesswork that could mislink records. Leave historic rows `NULL`.

This is safer under rollback than it was under resume. A `NULL` link means a historic certificate can never be selected for deletion, so the worst case of an incomplete backfill is that an old package cannot be rolled back — not that the wrong rows get destroyed. **Scope every rollback deletion by a non-null `endorsement_id`** so a bug can never widen into a mass delete.

### 2. `certificate_endorsements` generation state

```php
$table->string('generation_batch_id')->nullable(); // Bus batch id, for progress lookup
$table->text('generation_error')->nullable();      // last failure, shown to the RD
$table->timestamp('generation_started_at')->nullable();
```

A separate `generation_status` column is not needed — the endorsement's own `status` carries `generating`, and everything else is derivable from the batch.

`generated_count` already exists and is reused for the final count.

Progress itself is read from the batch (`Bus::findBatch($generation_batch_id)`), not duplicated into columns.

### 3. New endorsement status

`CertificateEndorsement` currently defines `STATUS_ENDORSED`, `STATUS_RD_APPROVED`, `STATUS_RD_REJECTED`. Add **one**:

```php
public const STATUS_GENERATING = 'generating';
```

Rollback removes the need for a second status. Earlier drafts added `STATUS_GENERATION_FAILED` because a half-generated package needed a state of its own to live in. Under rollback there is no such state — a failed package returns cleanly to `STATUS_ENDORSED`, exactly as if approval had never been clicked. The failure is surfaced through `generation_error`, not through a distinct status.

That is one fewer status for every view, filter, and query to handle. Still audit each status branch for `generating`, though — missing one means in-flight packages silently vanish from a list.

### Migration ordering

Timestamp new migrations after `2026_09_03_000001_add_created_at_index_to_certificates_table`.

~~Note the repo's existing ordering bug...~~ **Fixed while building this phase** — it made the mandatory tests unrunnable. See [Deviations from the plan as written](#deviations-from-the-plan-as-written).

---

## Work breakdown

Ordered so each step is independently reviewable.

### Step 1 — Data model

Migrations for `endorsement_id`, the endorsement generation columns, and the new status constants. Update `Certificate::$fillable` and `CertificateEndorsement::$fillable`. No behaviour change yet.

### Step 2 — Record the link in the existing synchronous path

Set `endorsement_id` in `generateCertificatesFromPayload` while it is still synchronous. Ship and verify independently — this de-risks Step 4 by proving the association is correct before anything moves to the queue.

### Step 3 — `GenerateCertificateJob`

One participant per job. Carries endorsement id, participant row, participant index, the payload, and the normalized template path.

- Reuses the existing per-participant body (`createCertificate` → `Storage::copy` → `stampCertificatePdf`).
- Applies `WithoutOverlapping` as above.
- Idempotency guard: if a certificate already exists for this `endorsement_id` + participant index, return without creating another. Still worth keeping under rollback — Laravel retries a failed job up to `$tries`, and a job that failed *after* its insert committed would otherwise create a duplicate on retry.
- `$tries = 3`, with the caveat that a genuine unique-constraint collision should fail fast rather than retry.

Extract the loop body from `generateCertificatesFromPayload` into a shared private method so the synchronous and queued paths cannot drift.

### Step 4 — Rewire `approveEndorsement`

- Atomically guard against double-submission: transition `endorsed` → `generating` in a conditional update, and bail if no row was affected. A double-click must not dispatch two batches.
- Parse participants once, normalize the template once, dispatch the batch.
- Store `generation_batch_id`.
- Return `back()->with('info', ...)` immediately.
- Move `queueGeneratedCertificateEmails` and `notifyTelegramOnRdApproval` into the batch's `then()`.
- Delete the normalized template in `finally()`.

### Step 5 — Progress and failure UI

- Approvals view renders a progress indicator for `generating` rows, reading `Bus::findBatch()`.
- A lightweight JSON endpoint returns progress; poll every few seconds. Stop polling on terminal states.
- Failed rows return to `endorsed` and show the recorded error and failing participant, so the RD knows what went wrong before approving again.

### Step 6 — Rollback

Implement the batch's `catch()`: inside a transaction, delete all certificates where `endorsement_id` equals this endorsement (scoped to non-null), then delete their source and stamped PDFs, then reset the endorsement to `endorsed` with the error recorded.

No separate Retry action is needed — the package returns to its pre-approval state, so the existing Approve and Generate button is the recovery path. That is one fewer control to build and one fewer state for staff to learn.

Guard the deletion carefully: it is the only destructive operation in this phase. Assert a non-null `endorsement_id` in the query, and log the count deleted.

### Step 7 — Documentation

Update `README.md` (approval is now asynchronous; a queue worker is mandatory) and both plan documents.

---

## Failure semantics

> **This section was reversed by the Hedera removal.** The original decision was
> report-and-halt with resume, chosen because rollback was *impossible*. That
> constraint no longer exists. See below — the recommendation has changed and
> needs re-confirmation before implementation.

**Recommended: roll back the whole package on failure.**

### Why the earlier decision no longer holds

The original reasoning was:

> Rollback is not available. `AnchorCertificateOnHederaJob` dispatches inside the generation path, and a hash written to a public ledger cannot be un-anchored.

That job no longer exists. After the Hedera removal, the per-participant path is:

```
createCertificate      →  database insert        (reversible: delete)
Storage::copy          →  writes source PDF      (reversible: delete)
stampCertificatePdf    →  writes stamped PDF     (reversible: delete)
```

**Every side effect is now local and reversible.** Emails are queued in the batch's `then()`, which never runs on a failed batch, so nothing has left the system. There is no longer any external, irreversible artifact anywhere in the generation loop.

### Why rollback is now the better choice

Resume was never the *preferred* design — it was the only one available. With rollback back on the table, it wins on both counts that matter:

**It is materially simpler.** Resume requires an idempotency guard, per-participant completion tracking, partial-batch filtering, and a "retry from participant N" UI. Rollback requires deleting the rows and files for one endorsement and setting the status back. The RD simply clicks Approve again.

**The work it discards is now trivial.** Resume's entire justification was avoiding wasted generation. After Phase 1 each certificate costs ~29 ms, so regenerating a 500-participant package costs about 15 seconds of background time that nobody is watching. Preserving that is not worth the complexity it buys.

Behaviour on failure:

| | |
|---|---|
| Already-generated certificates | **Deleted** — rows and both PDF files |
| Remaining queued jobs | Cancelled by the batch (default behaviour) |
| Endorsement status | Back to `endorsed`, with the error and failing participant recorded |
| Emails | Never sent — `then()` does not run on a failed batch |
| Recovery | RD clicks Approve again; a clean run from the start |

The result is all-or-nothing: a package either fully exists or does not exist at all. No partial state to explain to staff, and no half-generated package sitting in limbo.

### What rollback must clean up

- Certificate rows for the endorsement (needs `endorsement_id` — see [Data model changes](#data-model-changes)).
- `certificates/source/<code>.pdf` and `certificates/stamped/<code>.pdf` for each.
- The normalized template, already handled by the batch's `finally()`.

Run the deletion inside a transaction, and delete files only after the rows commit — an orphaned file is harmless, an orphaned row is not.

### Effect on certificate numbering

Deleting rolled-back certificates frees the codes they consumed. Because `createCertificate` derives the batch segment from a count of certificates created today, that count drops back and the next run reuses those numbers. This is correct and desirable — a failed package leaves no gap in the sequence.

One caveat worth testing: if another package is generated *between* the failure and the retry, the retry's codes will interleave rather than continue contiguously. That is cosmetically untidy but produces no collision, because the unique constraint and the in-transaction `LIKE $prefix.'%'` lookup still guarantee uniqueness.

### If you prefer to keep resume instead

Resume remains a legitimate choice, and is better in one specific scenario: very large packages that fail late on a *transient* fault, where redoing the work is genuinely expensive. If package sizes reach the point where regeneration costs minutes rather than seconds, revisit this. The original design is preserved in git history.

---

## User interface

Minimum viable, in the approvals view:

- `generating` → progress bar plus "Generating 47 of 120 certificates", Approve/Reject disabled.
- `endorsed` **with a non-null `generation_error`** → the normal approvable row, plus a warning showing what went wrong last time. The existing Approve and Generate button is the retry; no separate Retry control is needed.
- `rd_approved` → unchanged from today.

Rollback keeps this simple: because a failed package returns to `endorsed`, the failure state is a decoration on an existing row rather than a new one requiring its own layout, actions, and filter handling.

Polling endpoint returns `{status, processed, total, failed, error}`. Guard it with the same RD authorization as the rest of the module. Stop polling on terminal states so an idle open tab does not hammer the server.

---

## Testing

### Mandatory — certificate numbering

This is the test that must not be skipped. Mirror the verification already used for Phases 1 and 2:

1. Seed historic certificates (previous days) plus several created today.
2. Run a package through the queued path with `queue:work --once` in a loop.
3. Assert generated codes **continue** from the existing sequence and do not restart.
4. Assert historic rows are untouched.
5. Assert no duplicate `certificate_code` values exist.

Add a concurrency variant: run two workers against one batch and assert no unique-constraint violations and no duplicate codes. This is the test that proves `WithoutOverlapping` is doing its job — without it, the serialization guarantee is untested belief.

### Also required

- **Template normalized once.** Assert Ghostscript is invoked once per batch, not once per participant. Without this test, a later refactor can silently reintroduce the original bug.
- **Idempotency.** Run the same job twice; assert one certificate.
- **Rollback.** Force a mid-batch failure; assert every certificate for that endorsement is gone, both PDF files per certificate are deleted, the endorsement is back to `endorsed`, and **certificates belonging to other endorsements are untouched**. That last assertion is the important one — it is the guard against a scoping bug turning into a mass delete.
- **Rollback then re-approve.** After a rolled-back failure, approve again and assert the package generates completely with no duplicates and no code collisions.
- **Double-submission.** Two concurrent approvals of one endorsement dispatch exactly one batch.
- **Temp cleanup.** Normalized template is removed after success *and* after failure.
- **Emails.** Sent exactly once on success, not at all on failure.

### Manual

Approve a realistic package on staging with a PDF 1.7 template. Watch progress advance, confirm the PDFs are correct, and confirm the RD is never blocked.

---

## Rollout and backout

Steps 1 and 2 are additive and safe to ship alone — a new nullable column and a value written to it.

Steps 3–6 change RD-visible behaviour and should go out together, ideally outside a certificate-issuing window.

**A queue worker becomes mandatory.** Today a stopped worker only delays certificate emails; after this phase it means certificates are never generated at all, with approvals sitting in `generating` indefinitely. Before shipping:

- Confirm the worker is running and supervised in every environment.
- Add monitoring or alerting for a stalled queue.
- Consider a scheduled check that flags endorsements stuck in `generating` beyond a threshold.

**Backout:** revert Steps 3–6; the synchronous path returns. The added columns are nullable and harmless if left in place. Any endorsement stuck in `generating` at revert time needs manual status correction — write that runbook step down before deploying.

---

## Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Numbering corruption from parallel workers | **High** — unrecoverable, affects issued certificates | `WithoutOverlapping`; mandatory concurrency test |
| Phase 1 silently undone by per-job normalization | High — reverts the entire performance gain | Normalize in parent; assert Ghostscript call count |
| Queue worker down → certificates never generated | High | Monitoring; stuck-batch alerting; runbook |
| Double-dispatch from double-click | Medium — duplicate certificates | Atomic status guard |
| Status values missed in existing views/filters | Medium — packages disappear from lists | Audit every status branch |
| **Rollback scoping bug deletes the wrong certificates** | **High** — destroys issued certificates | Scope every delete by non-null `endorsement_id`; transaction; test that other endorsements survive; log deleted count |
| Rollback deletes rows but orphans PDF files | Low — wasted disk only | Delete files after the transaction commits, never before |
| Complexity added for little gain | Medium | Do not build until a trigger in [When to build this](#when-to-build-this) fires |

---

## Open questions

Needing a decision before implementation:

All settled — see [As built](#as-built) for the decisions taken.

| # | Question | Decision |
|---|---|---|
| 1 | **Rollback or resume on failure?** | **Rollback.** |
| 2 | ~~Email already-generated certificates on partial failure?~~ | Moot under rollback. |
| 3 | Should the RD be notified (Telegram) when generation *fails*? | **Yes**, ungated by the bulk threshold. |
| 4 | How long should a package sit in `generating` before it is flagged as stuck? | **Deferred** — no stuck-batch check ships in this phase. |
| 5 | Should the direct-RD path (`store`) also move to the queue? | **Stay synchronous.** |
| 6 | Is a lossy `endorsement_id` backfill wanted, or is `NULL` acceptable? | **`NULL`.** |
