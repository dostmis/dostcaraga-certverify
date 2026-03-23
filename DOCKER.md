# Docker Production Setup

This stack is ready for production transfer to a new server and includes automated PostgreSQL and app storage backups.

## Services

- `app`: Laravel PHP-FPM
- `web`: Nginx (public HTTP entrypoint)
- `worker`: Laravel queue worker
- `db`: PostgreSQL
- `db-backup`: Scheduled PostgreSQL dump service (cron + retention policy)
- `app-storage-backup`: Scheduled Laravel storage archive service (cron + retention policy)

## 1. Prepare environment

```bash
cp .env.docker.example .env
```

Set real values in `.env`, especially:

- `APP_URL`
- `DOCKER_APP_URL`
- `DB_PASSWORD`
- SMTP credentials (`MAIL_*`)
- backup settings (`BACKUP_TARGET_DIR`, `DB_BACKUP_*`, `APP_STORAGE_BACKUP_*`)

Generate an `APP_KEY` (paste output into `.env`):

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

## 2. Start stack

```bash
npm ci
npm run build
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

Default app URL is `http://localhost:8080` unless `APP_PORT`/`APP_BIND` is changed.

## 3. Backup system (automatic)

Backups are written to `${BACKUP_TARGET_DIR:-./backups}` on the host:

- `db-backup` writes PostgreSQL dumps into `${BACKUP_TARGET_DIR}/database`
- `app-storage-backup` writes Laravel storage archives into `${BACKUP_TARGET_DIR}/app-storage`

Default behavior:

<<<<<<< HEAD
- DB backup runs every 2 hours (`DB_BACKUP_CRON_SCHEDULE=0 */2 * * *`)
- app storage backup runs every 2 hours (`APP_STORAGE_BACKUP_CRON_SCHEDULE=0 */2 * * *`)
- DB retention keeps only the latest 10 backup files (`DB_BACKUP_MAX_FILES=10`)
- app storage retention keeps only the latest 10 archive files (`APP_STORAGE_BACKUP_MAX_FILES=10`)
- backup filenames and logs use Philippine Time by default (`BACKUP_TIMEZONE=Asia/Manila`, `BACKUP_TZ_LABEL=PHT`)
- both services run one backup when containers start (`DB_BACKUP_RUN_ON_START=true`, `APP_STORAGE_BACKUP_RUN_ON_START=true`)
=======
- runs every 2 hours (`DB_BACKUP_CRON_SCHEDULE="0 */2 * * *"`)
- keeps only the latest 10 backup files (`DB_BACKUP_MAX_FILES=10`)
- runs one backup when container starts (`DB_BACKUP_RUN_ON_START=true`)
>>>>>>> c5e8d13 (Improve certificate UI and add backup scripts)

Optional GPG encryption:

- enable encryption (`DB_BACKUP_GPG_ENABLED=true`)
- symmetric encryption passphrase (`DB_BACKUP_GPG_PASSPHRASE=your-strong-passphrase`)
- or recipient key encryption (`DB_BACKUP_GPG_RECIPIENT=backup@your-domain.example`)
- keep unencrypted `.sql.gz` copy (`DB_BACKUP_KEEP_PLAIN=true|false`)

Useful commands:

```bash
docker compose logs -f db-backup app-storage-backup
docker compose exec db-backup ls -lh /backups
docker compose exec db-backup /bin/sh /usr/local/bin/backup-db.sh
docker compose exec app-storage-backup ls -lh /backups
docker compose exec app-storage-backup /bin/sh /usr/local/bin/backup-app-storage.sh
```

To write backups directly to the NAS path `\\192.168.0.206\mis\cert-verify` from this Linux host,
mount the NAS share first and point `BACKUP_TARGET_DIR` at the mounted `cert-verify` folder:

```bash
sudo mkdir -p /mnt/cert-verify-nas
sudo mount -t cifs //192.168.0.206/mis /mnt/cert-verify-nas \
  -o username=YOUR_NAS_USERNAME,password=YOUR_NAS_PASSWORD,vers=3.0,uid=$(id -u),gid=$(id -g),file_mode=0664,dir_mode=0775
mkdir -p /mnt/cert-verify-nas/cert-verify
```

Then set in `.env`:

```bash
BACKUP_TARGET_DIR=/mnt/cert-verify-nas/cert-verify
```

Recreate the backup services after changing `.env`:

```bash
docker compose up -d --build db-backup app-storage-backup
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

## 5. Move Uploaded PDFs And Templates

The database only stores paths like `certificate-endorsements/templates/...pdf` and
`certificates/stamped/...pdf`. The actual files live in the Docker `app_storage` volume,
so SQL dumps alone are not enough to migrate previews and downloads.

Create a storage archive on the old server:

```bash
./scripts/backup-app-storage.sh ./app-storage.tar.gz
```

Copy `app-storage.tar.gz` to the new server, then restore it:

```bash
CONFIRM_RESTORE_STORAGE=YES ./scripts/restore-app-storage.sh ./app-storage.tar.gz
docker compose exec app php artisan certificates:migrate-private-storage
```

Do not rely on GitHub for live generated PDFs or uploaded participant/template files. They are runtime storage
artifacts and may contain personal data. Git also cannot see Docker volume contents unless you explicitly export
them back into the repository first.

If you want the whole verifier system in one portable backup, create a combined bundle:

```bash
./scripts/backup-full-system.sh ./cert-verify_full-clone.tar.gz
```

Restore the combined bundle on the new server:

```bash
CONFIRM_RESTORE_FULL=YES ./scripts/restore-full-system.sh ./cert-verify_full-clone.tar.gz
```

## 6. Transfer to new server

On new server:

1. Install Docker Engine + Docker Compose plugin.
2. Copy project files.
3. Create `.env` from `.env.docker.example`.
4. Run start/migrate commands from section 2.
5. Point your domain or reverse proxy to `${APP_BIND}:${APP_PORT}`.

## 7. GitHub upload

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
