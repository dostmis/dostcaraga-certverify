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

Automated PostgreSQL backups are handled by `db-backup` service and stored in Docker volume `db_backups`.

- trigger backup now:

```bash
docker compose exec db-backup /bin/sh /usr/local/bin/backup-db.sh
```

- restore from backup file:

```bash
CONFIRM_RESTORE=YES DB_USERNAME=certverify_user DB_DATABASE=certverify ./scripts/restore-db-backup.sh /path/to/backup.sql.gz
```
