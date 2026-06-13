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
2. `recipients`: Certificate holders (can be dormant until they claim via email link).
- **Storage boundaries**:
  - `storage/app/public/certificates/`: Public stamped PDFs, QR codes, signatories.
  - `storage/app/private/certificates/`: Private endorsement templates and participant CSV/XLSX files.
- **PDF Generation**: Requires Ghostscript (`gs`) installed on the host/Docker image for PDF compatibility conversion. Uses `setasign/fpdi-fpdf` and `simplesoftwareio/simple-qrcode`.
- **Queues**: Uses the `database` queue driver. In local dev, `composer run dev` handles this. In Docker, the `worker` service runs `php artisan queue:work`.

## Testing Constraints
- Tests run against an in-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in `phpunit.xml`).
- Do not assume PostgreSQL-specific features will work in tests unless explicitly tested.
- Run `composer run test` to execute the suite.

## File Boundaries
- `app/`: Core Laravel backend logic.
- `certUI/`: Separate TanStack Start frontend application (managed via Bun).
- `docker/`: Container configurations (dev, backup, php, nginx).
- `scripts/`: Bare-metal deployment and utility scripts.

## Verification Order
When making changes, prefer this sequence:
1. `composer run test` (fastest feedback)
2. `vendor/bin/pint` (ensure formatting)
3. `npm run build` (verify frontend assets compile)
