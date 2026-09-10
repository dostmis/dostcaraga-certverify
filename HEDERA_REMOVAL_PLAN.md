# Hedera Removal Plan

Plan to safely remove Hedera Consensus Service blockchain anchoring from CERTiFY.

Status: **proposed — no code changed.**

---

## Table of Contents

- [Summary](#summary)
- [Do this first: the kill switch](#do-this-first-the-kill-switch)
- [Footprint inventory](#footprint-inventory)
- [Decisions required](#decisions-required)
- [Staged removal](#staged-removal)
- [Data retention](#data-retention)
- [Verification](#verification)
- [Rollout and backout](#rollout-and-backout)
- [Risks](#risks)
- [Open questions](#open-questions)

---

## Summary

Hedera is cleanly isolated in this codebase. It is **not** wired into `docker-compose`, the `Dockerfile`, any route, or the scheduler, and the only user-facing surface is one panel on the public verification page. That makes removal low-risk and mostly mechanical.

**Decided: the app must never display blockchain status.** The verification page will show no blockchain panel in any state — not "BLOCKCHAIN VERIFIED", not "BLOCKCHAIN ANCHORED", not "BLOCKCHAIN MISMATCH". This removes the branching that earlier drafts left open, and simplifies every stage below.

Roughly 950 lines of application code, one Node service, 8 database columns, and 414 lines of documentation come out.

### What suppresses the display

All three labels live inside a single conditional in `resources/views/verify/show.blade.php:376`:

```blade
@if(!empty($blockchain))
```

`$blockchain` is populated in exactly one place — `CertificatePublicController:41` — and only when:

```php
$hedera->enabled() && $cert->isValid() && $cert->isAnchored()
```

So setting `HEDERA_ENABLED=false` makes `$blockchain` null for every certificate, and the panel disappears entirely. **No deploy is required to satisfy the display requirement** — see the next section. The code removal that follows makes it permanent and unconditional.

---

## Do this first: the kill switch

Before any code changes, set this in production:

```
HEDERA_ENABLED=false
```

That single flag stops all Hedera behaviour immediately:

- **The blockchain panel stops rendering for every certificate**, including already-anchored ones. This satisfies the "never display blockchain status" requirement on its own, with no deploy.
- `AnchorCertificateOnHederaJob` returns without contacting anything.
- No new certificates get anchored.

Verify it worked by loading the verify page for a certificate you know is anchored and confirming no blockchain panel appears.

**This is also urgent for reliability.** Today, verifying an *anchored* certificate makes a live HTTP call to the Hedera mirror node (`HederaService::fetchAnchoredMessage`, `mirror_timeout` 8 s) on a **public, unauthenticated page**. If the Hedera account is closed, the topic is deleted, or the network is unreachable, every verification of an anchored certificate stalls for up to 8 seconds. Since you are decommissioning Hedera, that failure mode becomes likely — the flag removes it today, without waiting for a deploy of the full removal.

Everything below can then proceed at a comfortable pace.

---

## Footprint inventory

Complete as of this survey. Nothing Hedera-related appears in `routes/`, `docker-compose*.yml`, `Dockerfile`, `deploy/`, the scheduler, or `README.md`.

### Application code — delete

| File | Lines | Notes |
|---|---|---|
| `app/Services/HederaService.php` | 200 | Bridge + mirror node client |
| `app/Jobs/AnchorCertificateOnHederaJob.php` | 82 | Queued anchoring |
| `app/Console/Commands/BackfillHederaAnchors.php` | 70 | `hedera:backfill` |
| `app/Console/Commands/CreateHederaTopic.php` | 66 | `hedera:create-topic` |

### Application code — edit

| File | Change |
|---|---|
| `CertificateAdminController.php` | Remove `use App\Jobs\AnchorCertificateOnHederaJob` (line 6) and the dispatch + comment (lines 1810–1812) |
| `CertificatePublicController.php` | Remove `HederaService` import and injection; drop `$blockchain` / `$blockchainExplorerUrl` from all three `compact()` returns (lines 26, 33, 46) |
| `app/Models/Certificate.php` | Remove 3 `BLOCKCHAIN_STATUS_*` constants, 8 `$fillable` entries, 2 `$casts` entries, `isAnchored()`, and optionally `canonicalPayload()` / `canonicalHash()` |
| `resources/views/verify/show.blade.php` | Remove the whole blockchain panel (~lines 376–401) and its `chain-*` CSS — all three states go, not just `BLOCKCHAIN VERIFIED` |
| `config/services.php` | Remove the `hedera` block (lines 63–72) |
| `.env.example` | Remove the 8 `HEDERA_*` keys |

`canonicalPayload()` and `canonicalHash()` are used **only** by Hedera code. They are a reasonable general-purpose integrity primitive, so keeping them is defensible — but if nothing will use them, delete them rather than leave dead code.

### Node service — delete

The entire `hedera/` directory: `server.js` (120 lines), `package.json`, `package-lock.json`, `README.md`, `.env.example`, and any local `node_modules/`.

This service runs **outside** Docker — it is not in `docker-compose.yml` or the `Dockerfile`. Whoever operates production must stop and disable however it is actually supervised (systemd unit, `pm2`, `nohup`, or a manual `npm start`). **Confirm how it is running before deleting the directory**, or you will delete the code while the process keeps running.

### Database

8 nullable columns on `certificates` plus one index:

```
blockchain_payload_hash          varchar(64)
blockchain_topic_id              varchar(32)
blockchain_sequence_number       bigint
blockchain_consensus_timestamp   varchar(40)
blockchain_transaction_id        varchar(64)
blockchain_status                varchar(20)
blockchain_error                 text
blockchain_anchored_at           timestamp(0)
certificates_blockchain_status_index
```

Created by `2026_06_08_000000_add_blockchain_anchor_fields_to_certificates_table.php`. See [Data retention](#data-retention) — do not drop these in the same change as the code.

### Documentation

`HEDERA_BLOCKCHAIN.md` (414 lines). Delete, or keep as a historical record — see decision #3.

Also update the two performance plans, which reference Hedera anchoring as a constraint (irreversible ledger writes are why `CERT_GENERATION_PHASE3_PLAN.md` rules out rollback). Removing Hedera actually **simplifies** Phase 3: with no irreversible external side effect inside the generation loop, roll-back-on-failure becomes viable where it previously was not.

---

## Decisions required

### 1. Blockchain status on the public verify page — DECIDED: never display it

**The panel is removed entirely.** Anchored certificates verify exactly like every other certificate, with no mention of blockchain in any state.

This removes all three labels, not just the first:

| Label | State | Outcome |
|---|---|---|
| `BLOCKCHAIN VERIFIED` | hash matched the ledger | removed |
| `BLOCKCHAIN ANCHORED` | anchored, mirror unreachable | removed |
| `BLOCKCHAIN MISMATCH` | hash did not match | removed |

Dropping the `MISMATCH` state is worth a moment's thought, since it is the one that reported a *problem*. It is safe to remove: a mismatch never invalidated a certificate — validity is determined solely by `status` and the certificate record itself, which the page already displays independently. The blockchain panel was supplementary throughout.

**One consequence to accept knowingly.** If recipients, partners, or auditors were ever told these certificates are blockchain-verifiable, that claim disappears from the verification page. Certificates remain fully verifiable by their code, QR, and public token — the primary mechanism is unchanged — but anyone who was pointed at the blockchain proof specifically will no longer find it. If such an external commitment exists, no need to tell those parties before Stage 2 and let them discover it. The anchors themselves survive on the Hedera ledger independently of this app (see decision #4), so a historical proof can still be produced manually if one is ever demanded.

The retained database columns (see [Data retention](#data-retention)) preserve the internal record regardless.

### 2. Drop the database columns, or keep them?

**Recommended: keep them initially, drop later or never.** Eight nullable columns cost effectively nothing. Dropping them destroys the only remaining record that anchoring ever happened, and it is irreversible without a restore. Split into a separate, later change so removal itself is fully reversible. See [Data retention](#data-retention).

### 3. Keep `HEDERA_BLOCKCHAIN.md`?

**Recommended: keep, with a deprecation header.** It documents a real period of system history and explains what the stored columns mean. Add a header stating the feature was removed, on what date, and why. If you prefer a clean tree, deleting is fine — git history preserves it.

### 4. What happens to the Hedera account and topic?

Outside the application, but do not forget it:

- The operator account and private key live only in `hedera/.env` on the server — **destroy that file securely**, it holds a private key.
- Decide whether to delete the topic or leave it. Leaving it preserves the anchored messages on the ledger, which is the only way a historical proof could still be produced once the app stops displaying them.
- If this is mainnet, close out or drain any remaining balance in the operator account.
- Confirm whether the account incurs ongoing cost.

---

## Staged removal

Each stage ships and is verifiable independently. Do not collapse them — the value is that any stage can be stopped or reverted without touching the others.

### Stage 0 — Kill switch (no deploy)

Set `HEDERA_ENABLED=false` in production, clear config cache, restart the queue worker. Stop and disable the `hedera/` Node service.

Verify: certificates still generate; public verification still works and no longer shows the blockchain panel; no outbound calls to `hedera.com` in logs.

**Stop here for a week.** If anything unexpected surfaces, it is one env flag to reverse.

### Stage 1 — Remove the write path

Delete `AnchorCertificateOnHederaJob`, `BackfillHederaAnchors`, `CreateHederaTopic`. Remove the import and dispatch from `CertificateAdminController`.

Certificates stop being anchored at the code level. The read path still exists but is inert behind the disabled flag.

Verify: generation works end-to-end; no queue jobs reference the deleted class. **Drain the queue before deploying** — a serialized `AnchorCertificateOnHederaJob` still sitting in `jobs` will fail permanently once its class is gone.

### Stage 2 — Remove the read path and the blockchain panel

This is the stage that makes "never display blockchain status" permanent in code rather than dependent on an env flag.

- Delete `app/Services/HederaService.php`.
- Remove the `HederaService` import and constructor injection from `CertificatePublicController`, along with `$blockchain` and `$blockchainExplorerUrl` — including from all three `compact()` calls (lines 26, 33, 46). **Missing any one of them throws an undefined-variable error on a public page.**
- Remove the entire `@if(!empty($blockchain))` block from `verify/show.blade.php` (~lines 376–401) and the now-unused `chain-*` CSS rules (`chainPanel`, `chainIcon`, `chainBody`, `chainTitle`, `chainMsg`, `chainMeta`, `chainLink`, `chain-verified`, `chain-unavailable`, `chain-mismatch`).
- Remove the `hedera` block from `config/services.php` (lines 63–72) and the 8 `HEDERA_*` keys from `.env.example`.

Verify: public verification renders correctly for an anchored certificate, a non-anchored certificate, an invalid code, and a revoked certificate. This page is public and unauthenticated — test all four states, and confirm the rendered HTML contains no "BLOCKCHAIN" string in any of them.

### Stage 3 — Remove the Node service and model surface

Delete the `hedera/` directory. Remove the `BLOCKCHAIN_STATUS_*` constants, `isAnchored()`, and the 8 `$fillable` plus 2 `$casts` entries from `Certificate`.

Because nothing renders blockchain data any more, the entire model surface goes — there is no historical panel to keep it alive for. The database columns still exist and still hold their values; they are simply no longer mapped by the model.

**Only delete `hedera/` after confirming the Node process is actually stopped in production.**

### Stage 4 — Database cleanup (deferred)

Separate migration, deliberately much later. See below.

---

## Data retention

**Do not drop the columns in the same release as the code removal.** Once the code is gone, the columns are inert — they cost a few nullable fields per row and nothing else. Once dropped, the record that a certificate was ever anchored is gone permanently.

Recommended sequence:

1. Stages 0–3 remove all code. Columns remain, untouched.
2. Wait a defined retention period — **12 months** is a reasonable default, long enough to cover an audit cycle.
3. **Export before dropping.** Dump `certificate_code`, `blockchain_topic_id`, `blockchain_sequence_number`, `blockchain_consensus_timestamp`, `blockchain_transaction_id`, `blockchain_anchored_at` for every anchored row into a retained CSV.
4. Only then run the drop migration.

The drop migration must timestamp after `2026_09_03_000001_add_created_at_index_to_certificates_table` and should drop the index before the columns. Write a real `down()` that recreates them, even though the data cannot be restored.

Before dropping, run a count so the decision is informed by facts rather than assumption:

```sql
SELECT blockchain_status, COUNT(*)
FROM certificates
GROUP BY blockchain_status;
```

If that returns zero anchored rows, every question in decision #1 becomes moot and removal is purely mechanical. **Run this query first** — it may collapse the whole decision.

---

## Verification

After each stage:

- Generate a certificate package end-to-end; confirm PDFs are produced correctly.
- Public verification: valid + anchored, valid + never anchored, invalid code, revoked certificate.
- `php artisan about` and `php artisan config:clear` run without errors.
- No references remain: `grep -ri "hedera\|blockchain" app/ config/ routes/ resources/ database/` returns only what you intended to keep.
- Queue worker starts and processes a job.
- Failed jobs table contains no orphaned `AnchorCertificateOnHederaJob` entries.

Automated checks worth adding:

- **A test asserting the verify page response never contains the string "BLOCKCHAIN"**, run against a certificate that carries anchored column data. This is the regression guard for the decision in #1 — without it, a future change that re-reads those columns could quietly bring the panel back.
- A test asserting public verification renders successfully for a certificate carrying blockchain column data (guards against a stale reference to a removed variable).
- A test asserting certificate generation dispatches no Hedera job.

---

## Rollout and backout

| Stage | Backout |
|---|---|
| 0 | Set `HEDERA_ENABLED=true`, restart the bridge |
| 1–3 | Revert the deploy; restore `hedera/.env` from secure backup |
| 4 | **No backout** — data is gone; restore from database backup only |

Stages 0–3 are fully reversible provided `hedera/.env` (the operator key) is backed up securely before deletion. Once that key is destroyed, re-anchoring under the same account is impossible — a fresh account and topic would be needed, and old anchors would not chain to new ones.

Take a database backup before Stage 4 regardless of the export in step 3.

---

## Risks

| Risk | Severity | Mitigation |
|---|---|---|
| Recipients told certificates are blockchain-verified lose that claim | **High** if externally promised | Accepted by decision #1. Notify those parties before Stage 2; ledger anchors survive for manual proof |
| Panel silently returns via a future change reading the retained columns | Medium | Assert the verify response contains no "BLOCKCHAIN" string |
| Node bridge keeps running after its directory is deleted | Medium | Confirm supervision method and stop it in Stage 0 |
| Queued `AnchorCertificateOnHederaJob` fails after class deletion | Medium | Drain queue before Stage 1 |
| Private key in `hedera/.env` leaked via backup or git | Medium | Secure destruction; confirm it was never committed |
| Column drop loses audit record irreversibly | Medium | Stage 4 deferred 12 months, with export |
| Stale `blockchain_*` reference crashes the public verify page | Medium | Full grep sweep; test all four verification states |
| Public verify page hangs on mirror timeout during transition | Low after Stage 0 | Kill switch first |

---

## Open questions

| # | Question | Recommended default |
|---|---|---|
| 1 | ~~What happens to the blockchain panel?~~ | **Resolved: removed entirely, never displayed in any state** |
| 2 | Were certificates ever externally advertised as blockchain-verifiable? | No longer changes the plan, but if yes, notify those parties before Stage 2 |
| 3 | How many certificates are actually anchored? | Run the count query above — informs how many verify pages change appearance |
| 4 | How is the `hedera/` Node service supervised in production? | **Must be answered before Stage 3** |
| 5 | Keep `canonicalPayload()` / `canonicalHash()` as a general integrity primitive? | Delete — nothing else uses them |
| 6 | Retention period before dropping columns | 12 months |
| 7 | Delete the Hedera topic, or leave it published? | Leave it; costs nothing and preserves old proofs |
| 8 | Is the Hedera account mainnet or testnet, and does it hold value? | Check before closing out |
