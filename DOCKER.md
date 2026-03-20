# Docker Production Setup

This stack is ready for production transfer to a new server and includes automated PostgreSQL backups.

## Services

- `app`: Laravel PHP-FPM
- `web`: Nginx (public HTTP entrypoint)
- `worker`: Laravel queue worker
- `db`: PostgreSQL
- `db-backup`: Scheduled PostgreSQL dump service (cron + retention policy)

## 1. Prepare environment

```bash
cp .env.docker.example .env
```

Set real values in `.env`, especially:

- `APP_URL`
- `DOCKER_APP_URL`
- `DB_PASSWORD`
- SMTP credentials (`MAIL_*`)
- backup settings (`DB_BACKUP_*`)

Generate an `APP_KEY` (paste output into `.env`):

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

## 2. Start stack

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

Default app URL is `http://localhost:8080` unless `APP_PORT`/`APP_BIND` is changed.

## 3. Backup system (automatic)

Backups are written to Docker volume `db_backups` by service `db-backup`.

Default behavior:

- runs every 2 hours (`DB_BACKUP_CRON_SCHEDULE=0 */2 * * *`)
- keeps only the latest 10 backup files (`DB_BACKUP_MAX_FILES=10`)
- runs one backup when container starts (`DB_BACKUP_RUN_ON_START=true`)

Optional GPG encryption:

- enable encryption (`DB_BACKUP_GPG_ENABLED=true`)
- symmetric encryption passphrase (`DB_BACKUP_GPG_PASSPHRASE=your-strong-passphrase`)
- or recipient key encryption (`DB_BACKUP_GPG_RECIPIENT=backup@your-domain.example`)
- keep unencrypted `.sql.gz` copy (`DB_BACKUP_KEEP_PLAIN=true|false`)

Useful commands:

```bash
docker compose logs -f db-backup
docker compose exec db-backup ls -lh /backups
docker compose exec db-backup /bin/sh /usr/local/bin/backup-db.sh
```

## 4. Restore a backup

Restore from `.sql`, `.sql.gz`, or encrypted `.sql.gz.gpg` file:

```bash
CONFIRM_RESTORE=YES DB_USERNAME=certverify_user DB_DATABASE=certverify ./scripts/restore-db-backup.sh /path/to/backup.sql.gz
```

For encrypted backups that use passphrase mode:

```bash
CONFIRM_RESTORE=YES DB_BACKUP_GPG_PASSPHRASE='your-strong-passphrase' DB_USERNAME=certverify_user DB_DATABASE=certverify ./scripts/restore-db-backup.sh /path/to/backup.sql.gz.gpg
```

Use restore carefully on production because it overwrites existing data.

## 5. Transfer to new server

On new server:

1. Install Docker Engine + Docker Compose plugin.
2. Copy project files.
3. Create `.env` from `.env.docker.example`.
4. Run start/migrate commands from section 2.
5. Point your domain or reverse proxy to `${APP_BIND}:${APP_PORT}`.

## 6. GitHub upload

If repository is not initialized yet:

```bash
git init
git add .
git commit -m "chore: production docker + db backup setup"
git branch -M main
git remote add origin https://github.com/dostmis/dostcaraga-certverify.git
git push -u origin main
```

If repo already exists locally:

```bash
git add .
git commit -m "chore: production docker + db backup setup"
git remote add origin https://github.com/dostmis/dostcaraga-certverify.git
git push -u origin main
```
