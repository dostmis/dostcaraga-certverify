# DOST Caraga CERTiFY — Certificate Repository System

Laravel 11 application for issuing, verifying, and managing DOST Caraga training certificates. Built with PostgreSQL, Tailwind CSS, and queue-based email delivery.

**New:** The system now serves as a **permanent certificate repository** — every certificate recipient gets their own account where certificates accumulate over time instead of being scattered across disconnected emails.

---

## Table of Contents

- [System Overview](#system-overview)
- [User Roles & Access](#user-roles--access)
- [Certificate Repository (Recipient System)](#certificate-repository-recipient-system)
- [Full Workflow: End-to-End](#full-workflow-end-to-end)
- [Participant Intake Forms](#participant-intake-forms)
- [Certificate Verification (Public)](#certificate-verification-public)
- [Notifications](#notifications)
- [Development Setup](#development-setup)
- [Production Deployment](#production-deployment)
- [Backup & Restore](#backup--restore)
- [Database Schema](#database-schema)

---

## System Overview

CERTiFY handles the complete lifecycle of a DOST-issued certificate:

```
Registration  →  Certificate Issuance  →  Email Delivery  →  Public Verification
                    ↗
        Certificate Repository
    (recipients.dostcaraga.ph)
```

### Key Features

- **Certificate generation** with QR codes and e-signatures on stamped PDFs
- **Multi-step endorsement workflow** (Organizer → Regional Director approval)
- **Certificate Repository** — recipients get permanent accounts to view all their certificates
- **Recipient matching** — auto-links certificates to existing recipient accounts
- **Public verification** — anyone can verify a certificate via QR code or URL
- **Email delivery** with PDF attachment and claim/dashboard links
- **Analytics dashboard** — gender, age, geographic, and program breakdowns
- **Participant intake forms** — shareable links for event pre-registration
- **Role-based access** — three staff roles with distinct permissions
- **PSGC geographic data** — cascading region/province/municipality/barangay dropdowns
- **Telegram & Facebook Messenger** notifications for endorsements

---

## User Roles & Access

### Staff Roles (users table)

| Role | Abilities |
|------|-----------|
| **Regional Director (RD)** | Approve/reject endorsements, direct certificate generation, user management, system settings, analytics, intake management |
| **Unit Supervisor** | Create intake events, endorse certificate packages to RD, view intakes |
| **Organizer** | Create certificate packages, endorse to RD, view analytics, create intake events |

All staff roles log in at `/login`.

### Certificate Recipients (recipients table)

| Type | Abilities |
|------|-----------|
| **Recipient** | Self-register at `/recipient/register`, claim account via permanent link, view all certificates on personal dashboard at `/recipient/certificates` |

A person can be **both** a staff user AND a recipient. Their admin menu shows a "My Certificates" tab linking to their certificate repository.

---

## Certificate Repository (Recipient System)

### How Recipients Get an Account

There are **three paths** to getting a recipient account:

#### Path A: Self-Registration

1. Visit `/recipient/register`
2. Fill in name, email, contact number, gender, birthdate
3. Account is created as **dormant** (no password)
4. When a certificate is issued in their name, they receive an email with a **permanent claim link**
5. Click the link, set a password, and access their certificate dashboard

#### Path B: Created by Organizer During Certificate Issuance

1. Organizer uploads a participant CSV for a training
2. The **matching engine** tries to link each name to an existing recipient
3. For unmatched names, the organizer can **create a new dormant recipient account**
4. When the RD approves and certificates are generated, the new recipient gets an email with:
   - The certificate PDF attached
   - A **permanent claim link** to set their password
   - "This link does not expire"

#### Path C: Intake Form Submission

1. Participant fills out an intake form at `/participant-intake/{token}`
2. On submission, a dormant recipient account is automatically created
3. When certificates are later issued to them, they receive the claim email

### The Claim Flow

```
Recipient gets email  →  Clicks [Claim Your Account]  →  Sets password
                                                             │
                                                             ▼
                                                    Lands on dashboard
                                                    with all certificates
```

- **Claim link is permanent** — no expiry
- **Reminded every time** a new certificate is sent until they claim
- **Once claimed** (password set), the claim_token is cleared
- **Already claimed?** The claim link redirects to login with a message

### My Certificates Dashboard

Recipients at `/recipient/certificates` see:
- All their certificates with training title, type, date, venue
- **Download PDF** button for each certificate
- **Verify** button linking to public verification page
- Empty state message if no certificates yet

### Matching Engine

When an organizer uploads a participant list during certificate creation, the system auto-matches each name:

| Match Level | Criteria | Result |
|-------------|----------|--------|
| Level 1 | Email exact match | Auto-linked |
| Level 2 | Full name + contact number exact match | Auto-linked |
| Level 3 | Normalized name match (case-insensitive) | Auto-linked |
| Level 4 | Fuzzy name (first name + last name components) | Auto-linked if single match |
| Ambiguous | Multiple possible matches | Organizer chooses |
| No Match | No recipient found | Organizer creates new or skips |

If any names can't be auto-matched, the organizer is redirected to a **matching review screen** to resolve them before endorsing.

### Auto-Linking for Staff

When a recipient is created with an email that matches an existing staff user, the `recipients.user_id` is automatically set. The staff user sees "My Certificates" in their admin menu.

---

## Full Workflow: End-to-End

### Certificate Issuance Flow

```
Step 1: Organizer creates certificate package
  │
  ├── Fills training metadata (title, dates, venue, program, etc.)
  ├── Uploads certificate PDF template
  ├── Uploads participant CSV/XLSX
  │
  ▼
Step 2: Matching engine runs
  │
  ├── Auto-matches participants to existing recipients
  ├── Organizer reviews and resolves unmatched/ambiguous
  │
  ▼
Step 3: Organizer endorses to Regional Director
  │
  ├── Endorsement record created (status = endorsed)
  ├── Files stored in private storage
  ├── RD notified via Telegram and/or Facebook Messenger
  │
  ▼
Step 4: Regional Director reviews
  │
  ├── Views approval queue at /admin/certificates/approvals
  ├── Downloads/reviews participant list
  ├── Previews template PDF with sample stamp
  │
  ▼
Step 5: RD approves → certificates generated
  │
  ├── Each participant gets a unique certificate_code:
  │   {YEAR}-{PROGRAM}-{ACRONYM}-{BATCH}-{SEQ}
  │   Example: 2026-FOD-TRA-01-001
  │
  ├── PDF stamped with:
  │   ├── Participant name (page 1, configurable position)
  │   ├── QR code (links to verification page)
  │   ├── Certificate code
  │   └── RD e-signature (optional)
  │
  ├── Certificates linked to recipient accounts (if matched)
  │
  ▼
Step 6: Emails queued and sent
  │
  ├── For dormant recipients: email includes claim link
  ├── For claimed recipients: email includes dashboard link
  ├── For no recipient: email includes certificate only
  │
  └── Delivery status tracked: queued → sent / failed / skipped
```

### Direct RD Generation

The Regional Director can skip the endorsement step entirely:
1. Fill the same certificate creation form
2. Click "Create Certificates with QR Code" (instead of "Endorse")
3. Certificates are generated immediately
4. Emails are queued as normal

### Certificate Code Format

```
2026-FOD-BFS-01-001
│     │   │   │  └── 3-digit sequence (001, 002...)
│     │   │   └── 2-digit daily batch (01, 02...)
│     │   └── Up to 3-letter acronym of training title
│     └── Program/Office code (FOD, ADN, LGIA, CEST, etc.)
└── Year from training date
```

---

## Participant Intake Forms

Organizers and Unit Supervisors create shareable intake event links. Each event gets a unique public token.

**Creating an event:**
1. Go to `/admin/participant-intakes`
2. Enter event name → click "Create"
3. Copy the generated link: `/participant-intake/{uuid}`

**For participants opening the form:**
- If **logged in** as a recipient → shows "Welcome back" banner, pre-fills name/email/contact/gender
- If **not logged in** → full form with all demographic fields
- Form includes: personal info, geographic data (PSGC cascading dropdowns), DOST program engagement, service interests

**Submission:**
- Duplicate detection (same email + name in same event → rejected)
- Auto-creates dormant recipient account for new submitters
- Linked to the event and owner for admin tracking

**Admin management:**
- Filter by event and status (pending/done/endorsed/rd_approved)
- Export selected participants as CSV/XLSX
- RD can bulk-delete, toggle global intake on/off

---

## Certificate Verification (Public)

Three public endpoints, all accessed via certificate's `public_token`:

| Endpoint | Purpose | Throttle |
|----------|---------|----------|
| `/verify?t={token}` | View certificate details and validity | 30/min |
| `/print?t={token}` | Printer-friendly view with RD signatory | 30/min |
| `/download?t={token}` | Download stamped PDF | 30/min |

Each stamped certificate contains a QR code linking to the verify page.

---

## Notifications

### Telegram

- **On endorsement:** Notifies configured chat IDs about new certificate package
- **On RD approval:** Notifies when certificates are generated
- **Configuration:** `services.telegram_bot` in `config/services.php`
- **Webhook:** `/webhooks/telegram/{secret}` captures chat IDs

### Facebook Messenger

- **On endorsement:** Sends DM to RD's PSID
- **Configuration:** `services.facebook_messenger` in `config/services.php`
- Respects `notify_on_every_endorsement` and `bulk_threshold` settings

### Email (Certificate Delivery)

- Queued via `SendCertificateEmailJob` (3 retries)
- Email includes: PDF attachment, download link, verify link
- **Dormant recipients:** includes claim link
- **Claimed recipients:** includes dashboard link
- Delivery status tracked per certificate: `queued` → `sent` / `failed` / `skipped`

---

## Development Setup

### Prerequisites
- PHP 8.3+
- PostgreSQL 16+
- Node.js 20+
- Composer
- Ghostscript (`gs`) — for PDF compatibility conversion

### Quick Start

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# Edit .env with your DB credentials
php artisan migrate --force
npm run build
php artisan serve
```

### Docker Development

```bash
cp .env.docker.dev .env
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force
```

Access at `http://localhost:8082`.

---

## Production Deployment

### Bare-Metal (Recommended)

```bash
sudo ./scripts/setup-bare-metal-services.sh
```

Services managed by systemd: Nginx, PHP-FPM, Laravel queue worker, scheduled tasks.

### Docker

```bash
cp .env.docker.example .env
npm ci && npm run build
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

Full deployment guide in [DOCKER.md](DOCKER.md).

---

## Backup & Restore

### Database Backup

```bash
# Custom format (compressed, fast restore)
pg_dump -h 127.0.0.1 -U certverify_user -d certverify -F c -f backup/may28cert_data.dump

# Plain SQL (human readable)
pg_dump -h 127.0.0.1 -U certverify_user -d certverify -F p -f backup/may28cert_data.sql
```

### Database Restore

```bash
# From custom format
pg_restore -h 127.0.0.1 -U certverify_user -d certverify --clean backup/may28cert_data.dump

# From plain SQL
psql -U certverify_user -d certverify < backup/may28cert_data.sql
```

### Certificate Storage Backup

```bash
# Public certificates (source PDFs, stamped PDFs, signatories)
tar -czf cert_storage.tar.gz storage/app/public/certificates/

# Private files (endorsement templates, participants files)
sudo tar -czf cert_private.tar.gz storage/app/private/certificates/ storage/app/private/certificate-endorsements/
```

### Full System Restore

```bash
# 1. Restore database
psql -U certverify_user -d certverify < backup/may28cert_data.sql

# 2. Restore certificate files
tar -xzf backup/may28cert_storage.tar.gz -C /path/to/project
tar -xzf backup/may28cert_private.tar.gz -C /path/to/project

# 3. Fix permissions
sudo chown -R www-data:www-data storage/app/
```

### Automated Docker Backups

- DB backup every 2 hours (keeps 10 latest)
- Storage backup every 2 hours (keeps 10 latest)
- Optional GPG encryption
- Configurable via `.env`: `DB_BACKUP_CRON_SCHEDULE`, `BACKUP_TARGET_DIR`, etc.

---

## Database Schema

### Core Tables

| Table | Purpose |
|-------|---------|
| `users` | Staff accounts (RD, Unit Supervisor, Organizer) with role-based access |
| `recipients` | Certificate recipient accounts (new — repository system) |
| `certificates` | All issued certificates with full metadata, PDF paths, email status |
| `participant_intakes` | Intake form submissions per event |
| `participant_intake_events` | Shareable intake form links |
| `certificate_endorsements` | Endorsement workflow records |
| `settings` | Key-value application settings |
| `jobs` | Laravel queue jobs |
| `sessions` | Laravel session storage |
| `password_reset_tokens` | Staff password reset tokens |
| `cache` | Laravel cache |

### Key Relationships

```
users (staff)
  │
  └── hasOne → recipients (staff who are also certificate holders)

recipients (certificate owners)
  │
  ├── hasMany → certificates (all certs issued to this person)
  └── hasMany → participant_intakes (all intake submissions by this person)

certificates
  │
  └── belongsTo → recipients (certificate owner, nullable)

certificate_endorsements
  │
  └── belongsTo → users (submitter / RD approver)
```

### Certificate Statuses

| Status | Meaning |
|--------|---------|
| `valid` | Active, verifiable certificate |
| `invalid` | Marked invalid |
| `revoked` | Revoked certificate |

### Email Delivery Statuses

| Status | Meaning |
|--------|---------|
| `queued` | Job dispatched, waiting to send |
| `sent` | Successfully delivered |
| `failed` | Failed after 3 attempts |
| `skipped_no_email` | Participant has no email |
| `skipped_invalid_email` | Email format invalid |

### Endorsement States

| Status | Meaning |
|--------|---------|
| `endorsed` | Submitted by organizer, awaiting RD |
| `rd_approved` | RD approved, certificates generated |
| `rd_rejected` | RD rejected with reason |

### Intake States

| Status | Meaning |
|--------|---------|
| `pending` | Submitted, not yet processed |
| `done` | Exported by organizer |
| `endorsed` | Endorsed upward |
| `rd_approved` | Finalized by RD |

---

## Routes Reference

### Staff Routes (auth)

| URI | Who | Purpose |
|-----|-----|---------|
| `/admin/certificates` | All staff | Certificate index |
| `/admin/certificates/create` | All staff | Create certificate package |
| `/admin/certificates/endorse` | Unit Sup, Organizer | Endorse to RD |
| `/admin/certificates/matching-review` | Unit Sup, Organizer | Match review screen |
| `/admin/certificates/approvals` | All staff | Endorsement queue |
| `/admin/certificates/endorsements/{id}/approve` | RD | Approve & generate |
| `/admin/participant-intakes` | All staff | Intake management |
| `/admin/users` | RD | User management |
| `/admin/analytics` | RD, Organizer | Analytics dashboard |

### Recipient Routes (public / auth:recipient)

| URI | Guard | Purpose |
|-----|-------|---------|
| `/recipient/login` | Guest | Login |
| `/recipient/register` | Guest | Self-registration (dormant) |
| `/recipient/claim/{token}` | Guest | Claim account (set password) |
| `/recipient/certificates` | Auth | My Certificates dashboard |
| `/recipient/logout` | Auth | Logout |

### Public Routes (no auth)

| URI | Purpose |
|-----|---------|
| `/verify?t={token}` | Verify certificate |
| `/print?t={token}` | Print-friendly view |
| `/download?t={token}` | Download stamped PDF |
| `/participant-intake/{token}` | Intake form |

---

## Stack

- **Framework:** Laravel 11
- **Database:** PostgreSQL 16
- **Frontend:** Blade + Tailwind CSS + Alpine.js
- **PDF:** FPDI (setasign/fpdi) + Ghostscript
- **QR Codes:** simplesoftwareio/qr-code
- **Spreadsheets:** phpoffice/phpspreadsheet
- **Queue:** Laravel database queue driver
- **Notifications:** Telegram Bot API, Facebook Messenger API
- **PSGC Data:** psgc.rootscratch.com API (7-day cache)
