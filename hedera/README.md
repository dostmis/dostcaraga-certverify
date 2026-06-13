# CERTiFY Hedera Bridge

A tiny, **localhost-only** Node service that signs Hedera Consensus Service
(HCS) submissions for the Laravel app. It exists because Hedera has no
maintained PHP SDK. The operator private key lives **only** in this service's
`.env` — never in Laravel's `.env` or in the database.

```
Laravel (PHP)  --HTTP 127.0.0.1:3001-->  hedera-bridge (Node)  --gRPC-->  Hedera
   verify  <----  public Mirror Node REST API  (no bridge needed for reads)
```

## Setup

```bash
cd hedera
npm install
cp .env.example .env
# edit .env: paste HEDERA_ACCOUNT_ID + HEDERA_PRIVATE_KEY from portal.hedera.com
chmod 600 .env
npm start            # or run as the hedera-bridge systemd service
```

Then, from the Laravel project root:

```bash
php artisan hedera:create-topic     # prints a Topic ID
# put HEDERA_TOPIC_ID=... and HEDERA_ENABLED=true in the Laravel .env
php artisan config:clear
php artisan hedera:backfill          # (optional) anchor existing certificates
```

## Endpoints (localhost only)

| Method | Path            | Purpose                                  |
|--------|-----------------|------------------------------------------|
| GET    | `/health`       | liveness + which network/account/topic   |
| POST   | `/create-topic` | one-time topic creation                   |
| POST   | `/anchor`       | submit `{ topicId, message }` to HCS      |

## Security

- Binds to `127.0.0.1` only — never expose it publicly.
- `.env` holds the private key: `chmod 600`, owned by the service user.
- Use a **testnet** account first; rotate to a dedicated mainnet key for prod.
