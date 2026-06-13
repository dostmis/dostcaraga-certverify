# Blockchain Anchoring for CERTiFY — Hedera Consensus Service (HCS)

> Tamper-evident certificate verification for DOST Caraga, backed by the
> Hedera public ledger. This document explains **what** was built, **why**,
> **how** it works, and **how to operate** it.

- **Status:** Live (testnet)
- **Implemented:** 2026-06-08
- **Network:** Hedera Testnet
- **Operator account:** `0.0.9135766`
- **Topic:** `0.0.9159066` ([view on HashScan](https://hashscan.io/testnet/topic/0.0.9159066))
- **Coverage:** all 1,376 existing certificates anchored + every new one auto-anchors

---

## 1. Why blockchain on CERTiFY?

CERTiFY issues official DOST training certificates. Before this change, the
verify page (`/verify?t=...`) answered one question — *"is this token in our
database?"* — which is a **trust-us** model: anyone who can write to the
database can forge a "valid" certificate, and there is no independent way for a
third party (an employer, an auditor, another agency) to confirm authenticity.

Anchoring each certificate's fingerprint to **Hedera Consensus Service** adds a
second, independent source of truth that **no one — not even a system
administrator — can silently alter or backdate.**

### What this gives DOST

| Property | Before | After (with HCS) |
|---|---|---|
| **Tamper-evidence** | None — DB is the only record | Any change to a certificate's core data breaks the on-chain hash match |
| **Independent verification** | Must trust the CERTiFY DB | Anyone can re-check the hash against the public ledger via HashScan / Mirror Node |
| **Immutable timestamp** | `created_at` is editable | Hedera "consensus timestamp" is fixed and globally ordered |
| **Audit trail** | Internal logs only | Permanent, public, append-only ledger of every issuance |
| **Survives data loss** | Backups only | The anchor exists on Hedera's global node network forever, even if our server is destroyed |

### Why Hedera (and HCS specifically)

- **Energy-efficient & fast** — Hedera's *gossip-about-gossip* + virtual-voting
  consensus gives fair ordering and finality in seconds, at a tiny carbon cost
  (suitable for a government sustainability posture).
- **HCS instead of smart contracts** — we only need an immutable, ordered log of
  hashes. HCS does exactly that: cheaper, simpler, and lower-risk than deploying
  and maintaining an EVM smart contract.
- **Free public Mirror Nodes** — verification reads cost nothing; we never run
  our own node.
- **Predictable cost** — anchoring a message is fractions of a cent on mainnet;
  free on testnet.

---

## 2. The core design decision: we anchor *data*, not the PDF

A naive approach hashes the certificate PDF file. We deliberately **do not** do
this, because the stamped PDF is **regenerated** in normal operations (e.g. the
`certificates:refresh-qr-domain` command re-stamps the QR code when the domain
changes). Hashing the PDF bytes would make the anchor "break" on every
regeneration.

Instead we hash a **canonical, deterministic snapshot of the immutable
certificate facts**:

```
certificate_code + public_token + participant_name +
training_title + training_date + training_date_to + issuing_office
```

These are JSON-encoded deterministically and run through **SHA-256**. This hash
never drifts as long as the certificate's meaning is unchanged — and it changes
immediately if any core fact is tampered with. (See
`App\Models\Certificate::canonicalPayload()` / `canonicalHash()`.)

---

## 3. Architecture

Hedera has no maintained PHP SDK, so a tiny **Node.js bridge** holds the
operator key and submits messages. Laravel never touches the private key.

```
                          ISSUANCE (write path)
  ┌──────────┐   queue    ┌─────────────────────────┐   HTTP    ┌───────────────┐  gRPC   ┌────────┐
  │ Laravel  │──────────► │ AnchorCertificateOnHedera│─────────►│ Node bridge   │────────►│ Hedera │
  │ (issue)  │  job       │ Job (HederaService)      │ :3001    │ @hashgraph/sdk│         │  HCS   │
  └──────────┘            └─────────────────────────┘  /anchor  └───────────────┘         └────────┘
        │                                                         (holds private key)          │
        │ stores seq #, consensus timestamp, tx id, hash                                       │
        ▼                                                                                      │
  ┌──────────┐                                                                                 │
  │PostgreSQL│                                                                                 │
  └──────────┘                                                                                 │
                          VERIFICATION (read path — no bridge needed)                          │
  ┌──────────┐                              HTTP (public, free)                                ▼
  │ Verify   │───────────────────────────────────────────────────────────────────► Hedera Mirror Node
  │ page     │◄──────────────────────  on-chain hash  ──────────────────────────────  REST API
  └──────────┘   three-way match: file-data hash == DB hash == on-chain hash
```

**Key separation of concerns:**
- **Write path needs the bridge** (it signs with the operator key).
- **Read/verify path does NOT need the bridge** — it queries the public Mirror
  Node directly from PHP, so verification keeps working even if the bridge is
  down.

---

## 4. Components built

### Laravel (PHP)

| File | Purpose |
|---|---|
| `database/migrations/2026_06_08_000000_add_blockchain_anchor_fields_to_certificates_table.php` | Adds 8 additive, nullable `blockchain_*` columns |
| `app/Models/Certificate.php` | `canonicalPayload()`, `canonicalHash()`, `isAnchored()`, status constants, fillable/casts |
| `app/Services/HederaService.php` | Talks to the bridge (write) and Mirror Node (verify); degrades gracefully, never throws |
| `app/Jobs/AnchorCertificateOnHederaJob.php` | Queued, idempotent anchoring with retry/backoff |
| `app/Http/Controllers/Admin/CertificateAdminController.php` | Dispatches the anchor job after each certificate is stamped |
| `app/Http/Controllers/CertificatePublicController.php` | `verify()` runs the on-chain check and passes the result to the view |
| `app/Console/Commands/CreateHederaTopic.php` | `php artisan hedera:create-topic` (one-time) |
| `app/Console/Commands/BackfillHederaAnchors.php` | `php artisan hedera:backfill` for existing certificates |
| `config/services.php` | `hedera` config block (no secrets) |
| `resources/views/verify/show.blade.php` | The ⛓ verification panel + styles |
| `.env.example` | Documents the app-side (non-secret) Hedera keys |

### Node bridge (`hedera/`)

| File | Purpose |
|---|---|
| `hedera/server.js` | Express service: `/health`, `/create-topic`, `/anchor` |
| `hedera/package.json` | Deps: `@hashgraph/sdk`, `express`, `dotenv` |
| `hedera/.env.example` | Template for the **only** place the private key lives |
| `hedera/.gitignore` | Ensures `.env` and `node_modules` are never committed |
| `hedera/README.md` | Bridge-specific setup notes |

### Ops

| File | Purpose |
|---|---|
| `scripts/setup-bare-metal-services.sh` | Installs a `hedera-bridge` systemd unit **only if `hedera/.env` exists** |

---

## 5. Database schema (additive, nullable — zero risk to existing data)

Added to the `certificates` table:

| Column | Meaning |
|---|---|
| `blockchain_payload_hash` | SHA-256 of the canonical payload that was anchored |
| `blockchain_topic_id` | HCS topic (e.g. `0.0.9159066`) |
| `blockchain_sequence_number` | Per-topic message sequence number (#1, #2, …) |
| `blockchain_consensus_timestamp` | Hedera consensus timestamp (e.g. `1780851140.691128000`) |
| `blockchain_transaction_id` | Hedera transaction id |
| `blockchain_status` | `pending` \| `anchored` \| `failed` |
| `blockchain_error` | Last error message, if any |
| `blockchain_anchored_at` | When anchoring succeeded |

---

## 6. How issuance works (auto-anchoring)

1. Admin generates a certificate → `CertificateAdminController` creates the row
   (`status = valid`) and stamps the PDF.
2. `AnchorCertificateOnHederaJob::dispatch($cert->id)` is queued — **issuance is
   never blocked** by Hedera latency.
3. The queue worker runs the job: it builds the compact JSON message
   `{ v, system, certificate_code, public_token, hash }` and POSTs it to the
   bridge's `/anchor`.
4. The bridge submits it to HCS, waits for the receipt + record, and returns the
   **sequence number, consensus timestamp, and transaction id**.
5. The job writes those back to the certificate and sets
   `blockchain_status = anchored`.

**Resilience:** if the bridge or network is down, the job retries (5 attempts,
exponential backoff). Nothing is lost — un-anchored certificates can always be
re-processed later with `hedera:backfill`. The job is **idempotent**: an
already-anchored certificate is skipped, so re-runs never create duplicates.

---

## 7. How verification works (the three-way match)

When someone opens `https://certify.dostcaraga.ph/verify?t=<token>`:

1. Laravel loads the certificate and recomputes its **canonical hash** from the
   live database fields.
2. If the certificate is anchored, `HederaService::verifyCertificate()` fetches
   the original message from the **public Hedera Mirror Node**.
3. It compares three values:
   - the hash recomputed from the current data,
   - the hash stored in our database,
   - the hash recorded on the Hedera ledger.
4. The verify page shows one of:

| State | Panel | Meaning |
|---|---|---|
| `verified` | 🟢 **BLOCKCHAIN VERIFIED** | All three hashes match — authentic and untampered |
| `unavailable` | ⚪ **BLOCKCHAIN ANCHORED** | Anchor exists, but the Mirror Node was briefly unreachable (falls back to stored anchor) |
| `mismatch` | 🔴 **BLOCKCHAIN MISMATCH** | On-chain hash ≠ current data — possible tampering |
| (not anchored) | *(no panel)* | Legacy cert issued before anchoring — still a valid VALID page, no alarm |

Each panel includes the consensus timestamp, topic/sequence, and a **"View on
HashScan"** link so anyone can independently confirm the record on the public
ledger.

---

## 8. Configuration

### Laravel `.env` (non-secret app wiring)

```dotenv
HEDERA_ENABLED=true
HEDERA_BRIDGE_URL=http://localhost:3001
HEDERA_BRIDGE_TIMEOUT=20
HEDERA_TOPIC_ID=0.0.9159066
HEDERA_NETWORK=testnet
HEDERA_MIRROR_URL=https://testnet.mirrornode.hedera.com
HEDERA_MIRROR_TIMEOUT=8
HEDERA_EXPLORER_URL=https://hashscan.io/testnet
```

> **Feature flag:** set `HEDERA_ENABLED=false` to instantly disable all
> blockchain behaviour. The system then behaves exactly as it did before — no
> anchoring, no verify panel. Nothing else changes.

### Bridge `hedera/.env` (THE ONLY PLACE THE PRIVATE KEY LIVES)

```dotenv
HEDERA_NETWORK=testnet
HEDERA_ACCOUNT_ID=0.0.9135766
HEDERA_PRIVATE_KEY=<HEX Encoded Private Key from portal.hedera.com>
HEDERA_TOPIC_ID=0.0.9159066
HOST=127.0.0.1
PORT=3001
```

> Use the **Account ID** and the **HEX Encoded Private Key** from the Hedera
> portal. Ignore the **EVM Address** — that is for smart contracts, not HCS.
> Keep this file `chmod 600`. It is git-ignored and must never be committed.

---

## 9. Installation (from scratch)

### Prerequisites
- A Hedera account from <https://portal.hedera.com> (testnet is free and
  auto-funded). Copy the **Account ID** and **HEX Encoded Private Key**.
- Node.js 18+ on the server.

### Steps

```bash
# 1. Install & configure the bridge
cd hedera
npm install
cp .env.example .env
#   edit .env: paste HEDERA_ACCOUNT_ID + HEDERA_PRIVATE_KEY
chmod 600 .env

# 2. Start the bridge (foreground, for first run)
npm start
#   expect: [hedera-bridge] listening on http://127.0.0.1:3001 (network=testnet ...)

# 3. From the Laravel project root: run the migration
php artisan migrate --force

# 4. Create the topic (one time)
php artisan hedera:create-topic
#   prints a Topic ID, e.g. 0.0.9159066

# 5. Wire it into Laravel .env
#   HEDERA_ENABLED=true
#   HEDERA_TOPIC_ID=0.0.9159066
php artisan config:clear

# 6. Make sure the queue worker has the new config
sudo systemctl restart certverify-queue

# 7. (Optional) anchor existing certificates
php artisan hedera:backfill
```

### Make the bridge permanent (systemd)

Create `/etc/systemd/system/hedera-bridge.service` (run the bridge as the user
that owns `hedera/.env` and the Node install):

```ini
[Unit]
Description=CERTiFY Hedera Consensus Service Bridge
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=talinoserver
Group=talinoserver
Restart=always
RestartSec=5
WorkingDirectory=/home/talinoserver/Documents/dostcaraga-certverify/hedera
ExecStart=/home/talinoserver/.nvm/versions/node/v20.20.2/bin/node /home/talinoserver/Documents/dostcaraga-certverify/hedera/server.js
NoNewPrivileges=true
ProtectSystem=full

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now hedera-bridge
systemctl status hedera-bridge --no-pager
curl -s http://127.0.0.1:3001/health; echo
```

> The bare-metal helper `scripts/setup-bare-metal-services.sh` will also install
> this unit automatically whenever `hedera/.env` is present.

---

## 10. Operations & monitoring

**Check coverage:**
```bash
php artisan tinker --execute='echo App\Models\Certificate::where("blockchain_status","anchored")->count()." / ".App\Models\Certificate::where("status","valid")->count();'
```

**Backfill options:**
```bash
php artisan hedera:backfill --dry-run     # show how many need anchoring
php artisan hedera:backfill               # queue all (recommended)
php artisan hedera:backfill --sync        # run inline (no queue)
php artisan hedera:backfill --limit=100   # cap the batch
```

**Bridge health:**
```bash
curl -s http://127.0.0.1:3001/health
# {"ok":true,"network":"testnet","account":"0.0.9135766","topicConfigured":true}
```

**Service control:**
```bash
sudo systemctl status  hedera-bridge
sudo systemctl restart hedera-bridge
journalctl -u hedera-bridge -f          # live logs
```

---

## 11. Security notes

- The operator **private key lives only in `hedera/.env`** (mode `600`), never in
  Laravel's `.env`, the database, or git. Separation comes from process +
  file-permission isolation.
- The bridge **binds to `127.0.0.1` only** — it is never exposed publicly.
- Verification is **read-only** and uses public endpoints — no secrets involved.
- The anchored message contains only a hash + identifiers — **no personal data**
  is written to the public ledger.
- **Before going to mainnet:** generate a fresh key that has never been shared,
  fund a dedicated mainnet account, and rotate the testnet key out.

---

## 12. Testnet → mainnet (future)

No code changes are required — only configuration:

1. Create a **mainnet** account at the portal and fund it with real HBAR.
2. In `hedera/.env`: set `HEDERA_NETWORK=mainnet` and the mainnet account/key.
3. Restart the bridge, then `php artisan hedera:create-topic` to make a mainnet
   topic.
4. In Laravel `.env`: update `HEDERA_TOPIC_ID`, set
   `HEDERA_NETWORK=mainnet`,
   `HEDERA_MIRROR_URL=https://mainnet.mirrornode.hedera.com`,
   `HEDERA_EXPLORER_URL=https://hashscan.io/mainnet`.
5. `php artisan config:clear` and (optionally) `hedera:backfill`.

Mainnet cost is roughly **$0.0001 per certificate** — negligible even at scale.

---

## 13. Troubleshooting

| Symptom | Cause / Fix |
|---|---|
| Verify page shows no ⛓ panel | Cert not anchored yet → run `hedera:backfill`; or `HEDERA_ENABLED=false` |
| `/health` shows `topicConfigured:false` | `HEDERA_TOPIC_ID` missing in `hedera/.env` (cosmetic — anchoring still works; restart bridge to fix the display) |
| Anchored count stops climbing | Testnet HBAR low → top up at portal faucet, re-run `hedera:backfill` (idempotent) |
| `mismatch` panel (🔴) | Core certificate data changed after anchoring — investigate; the ledger is the source of truth |
| Bridge unreachable | `systemctl status hedera-bridge`; check it's listening on `127.0.0.1:3001` |
| Jobs not anchoring | Ensure `certverify-queue` was restarted after enabling Hedera |

---

## 14. Quick reference

| Item | Value |
|---|---|
| Network | Testnet |
| Operator account | `0.0.9135766` |
| Topic | `0.0.9159066` |
| HashScan topic | https://hashscan.io/testnet/topic/0.0.9159066 |
| Bridge | `http://127.0.0.1:3001` (systemd: `hedera-bridge`) |
| Mirror Node | https://testnet.mirrornode.hedera.com |
| Feature flag | `HEDERA_ENABLED` (Laravel `.env`) |
| Anchor command | `php artisan hedera:backfill` |
| Topic command | `php artisan hedera:create-topic` |

---

*Built for DOST Caraga — CERTiFY. Blockchain layer: Hedera Consensus Service.*
