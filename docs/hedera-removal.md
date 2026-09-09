# Hedera retirement

Certificate issuance and public verification now use the application database
without a blockchain service. The bridge, commands, configuration, and public
blockchain panel have been removed.

Existing certificate records and the historical blockchain columns/migration are
retained to avoid losing audit data or breaking migration rollback history.
`AnchorCertificateOnHederaJob` is a no-op compatibility class so old pending or
retried jobs can finish safely. Keep it until those jobs can no longer be retried.

When deploying to a server that previously ran the bridge:

1. Stop the old queue workers before replacing the application code.
2. Stop and disable the bridge if installed:
   `sudo systemctl disable --now hedera-bridge.service`.
3. Deploy the code, run `php artisan optimize:clear`, and rebuild production
   caches with `php artisan optimize` if normally used by the deployment.
4. Start the queue workers again with the new code. Do not flush the queue;
   certificate email jobs must be preserved.
5. Remove unused `HEDERA_*` settings from the server environment and securely
   retire the old bridge credentials according to your credential policy.

These server operations are deployment steps; editing this repository does not
stop an existing remote service or alter already-published ledger messages.

## Validation (2026-09-09)

Tested in an isolated Docker copy using PHP 8.4.25, PostgreSQL 16, Imagick,
Ghostscript, and the locked Composer dependencies. Node 22 built the frontend
successfully with `npm ci --no-audit --no-fund && npm run build`.

The focused certificate suite passed: **7 tests, 77 assertions**:

```sh
php artisan test --filter 'CertificateGenerationTest|CertificateVerificationTest|CertificateCustomDostProjectTest'
```

This exercised actual PDF generation for two participants, QR image embedding,
unique certificate codes/tokens, stored PDFs, public verification, previews,
downloads, email queuing, email job handling with a fake mailer, and the legacy
anchor job. No live email was sent. Electronic signatures and scanning the QR
image with a device were not tested.

The full suite is **not green**. Existing fresh-database setup issues prevented
the database tests from reaching application code:

- SQLite cannot execute the PostgreSQL-specific token migration SQL.
- PostgreSQL fails because the May 29 recipient-field migrations precede the
  June 1 migration that creates `recipients`.
- The homepage test expects HTTP 200, but the application redirects with 302.

To run the focused tests, only the temporary copy's recipient creation migration
was renamed to `2026_05_28_000001_create_recipients_table.php`. Repository
migrations were not changed. This validates certificate behavior with the schema
present; it does not validate an unmodified fresh installation.

The unknown-token fixture uses a well-formed UUID. Testing a malformed token also
exposed an existing PostgreSQL UUID error in public verification; that separate
input-validation issue is not fixed by the Hedera removal.

Pint passes for the small changed PHP files and the new tests after formatting.
The admin controller retains pre-existing formatting violations. Shell syntax
and `git diff --check` pass.
