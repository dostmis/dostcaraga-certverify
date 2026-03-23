# Cert Verify

Laravel-based certificate verification and processing system.

## Production Docker Deployment

This repository includes a production-oriented Docker stack with:

- Laravel app (`app`)
- Nginx web (`web`)
- queue worker (`worker`)
- PostgreSQL (`db`)
- automated DB backups (`db-backup`)

Quick start:

```bash
cp .env.docker.example .env
npm ci
npm run build
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

Full deployment guide (including backup/restore and GitHub push flow) is in [DOCKER.md](DOCKER.md).

## Local Development (without Docker)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

## Backup and Restore

Automated backups are handled by `db-backup` and `app-storage-backup`. They write to the host path
`${BACKUP_TARGET_DIR:-./backups}`, with database dumps in `database/` and storage archives in
`app-storage/`.

<<<<<<< HEAD
Certificate templates, participant uploads, generated PDFs, and signatory images live in the Docker `app_storage`
volume, not in Git. A SQL restore only restores database rows and file paths, not the actual uploaded files.

- default DB schedule is every 2 hours (`DB_BACKUP_CRON_SCHEDULE=0 */2 * * *`)
- default app storage schedule is every 2 hours (`APP_STORAGE_BACKUP_CRON_SCHEDULE=0 */2 * * *`)
- retention keeps only latest 10 DB backup files (`DB_BACKUP_MAX_FILES=10`)
- retention keeps only latest 10 storage backup files (`APP_STORAGE_BACKUP_MAX_FILES=10`)
- backup filenames and logs use Philippine Time by default (`BACKUP_TIMEZONE=Asia/Manila`, `BACKUP_TZ_LABEL=PHT`)
=======
- default schedule is every 2 hours (`DB_BACKUP_CRON_SCHEDULE="0 */2 * * *"`)
- retention keeps only latest 10 backup files (`DB_BACKUP_MAX_FILES=10`)
>>>>>>> c5e8d13 (Improve certificate UI and add backup scripts)
- set `DB_BACKUP_GPG_ENABLED=true` to produce encrypted `.sql.gz.gpg` backups
- use `DB_BACKUP_GPG_PASSPHRASE` (symmetric) or `DB_BACKUP_GPG_RECIPIENT` (public key)

If you want backups written directly to the NAS path `\\192.168.0.206\mis\cert-verify` from this Linux
server, first mount the NAS share and then point `BACKUP_TARGET_DIR` at the mounted `cert-verify`
folder:

```bash
sudo mkdir -p /mnt/cert-verify-nas
sudo mount -t cifs //192.168.0.206/mis /mnt/cert-verify-nas \
  -o username=YOUR_NAS_USERNAME,password=YOUR_NAS_PASSWORD,vers=3.0,uid=$(id -u),gid=$(id -g),file_mode=0664,dir_mode=0775
mkdir -p /mnt/cert-verify-nas/cert-verify
```

Then set this in `.env` and recreate the backup services:

```bash
BACKUP_TARGET_DIR=/mnt/cert-verify-nas/cert-verify
docker compose up -d --build db-backup app-storage-backup
```

- trigger backup now:

```bash
docker compose exec db-backup /bin/sh /usr/local/bin/backup-db.sh
docker compose exec app-storage-backup /bin/sh /usr/local/bin/backup-app-storage.sh
```

- restore from backup file:

```bash
CONFIRM_RESTORE=YES DB_USERNAME=certverify_user DB_DATABASE=certverify ./scripts/restore-db-backup.sh /path/to/backup.sql.gz
```

- restore encrypted backup file:

```bash
CONFIRM_RESTORE=YES DB_BACKUP_GPG_PASSPHRASE='your-strong-passphrase' DB_USERNAME=certverify_user DB_DATABASE=certverify ./scripts/restore-db-backup.sh /path/to/backup.sql.gz.gpg
```

- backup app storage files:

```bash
./scripts/backup-app-storage.sh ./app-storage.tar.gz
```

- restore app storage files:

```bash
CONFIRM_RESTORE_STORAGE=YES ./scripts/restore-app-storage.sh ./app-storage.tar.gz
docker compose exec app php artisan certificates:migrate-private-storage
```

- create a full portable clone of DB + storage:

```bash
./scripts/backup-full-system.sh ./cert-verify_full-clone.tar.gz
```

- restore a full portable clone:

```bash
CONFIRM_RESTORE_FULL=YES ./scripts/restore-full-system.sh ./cert-verify_full-clone.tar.gz
```
