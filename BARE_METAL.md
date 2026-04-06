# Bare-Metal Production Setup (Cloudflare Tunnel)

This guide deploys CertVerify on a Linux server without Docker.

Target:

- domain: `certify.dostcaraga.ph`
- SSL/TLS: Cloudflare edge certificate
- origin access: Cloudflare Tunnel to local Nginx (`127.0.0.1:8080`)

## Quick auto-start setup (no `php artisan serve`)

If this repo is already deployed on your server and `.env` is set, run:

```bash
sudo ./scripts/setup-bare-metal-services.sh
```

This installs and enables on boot:

- `nginx`
- detected `php*-fpm`
- `certverify-queue`
- `certverify-scheduler`

Verify:

```bash
systemctl status nginx certverify-queue certverify-scheduler --no-pager
```

## 1. Install system packages (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install -y nginx postgresql postgresql-client \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl \
  php8.3-imagick unzip git nodejs npm composer
```

## 2. Deploy application

```bash
sudo mkdir -p /var/www
sudo chown -R "$USER":"$USER" /var/www
cd /var/www
git clone <your-repo-url> certverify
cd certverify
cp .env.example .env
```

Set production values in `.env`:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://certify.dostcaraga.ph`
- `TRUSTED_PROXIES=*`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_HTTP_ONLY=true`
- `SESSION_SAME_SITE=lax`
- `DB_CONNECTION=pgsql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=5432`
- `DB_DATABASE=certverify`
- `DB_USERNAME=certverify_user`
- `DB_PASSWORD=<strong-password>`

Install app dependencies and build assets:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Set file permissions:

```bash
sudo chown -R www-data:www-data /var/www/certverify/storage /var/www/certverify/bootstrap/cache
sudo chmod -R ug+rwX /var/www/certverify/storage /var/www/certverify/bootstrap/cache
```

## 3. Configure PostgreSQL

Create DB and user (replace password):

```bash
sudo -u postgres psql -c "CREATE USER certverify_user WITH PASSWORD 'replace-strong-password';"
sudo -u postgres psql -c "CREATE DATABASE certverify OWNER certverify_user;"
```

## 4. Configure Nginx (local-only listener for Tunnel)

Install provided config:

```bash
sudo cp deploy/bare-metal/nginx/certify.dostcaraga.ph.conf /etc/nginx/sites-available/certify.dostcaraga.ph
sudo ln -s /etc/nginx/sites-available/certify.dostcaraga.ph /etc/nginx/sites-enabled/certify.dostcaraga.ph
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx php8.3-fpm
```

The site listens only on `127.0.0.1:8080` so it is not exposed directly to the internet.

## 5. Run queue worker + scheduler with systemd

```bash
sudo cp deploy/bare-metal/systemd/certverify-queue.service /etc/systemd/system/
sudo cp deploy/bare-metal/systemd/certverify-scheduler.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now certverify-queue certverify-scheduler
sudo systemctl status certverify-queue certverify-scheduler --no-pager
```

## 6. Configure Cloudflare Tunnel service

Install cloudflared on your server, then create a tunnel in Cloudflare Zero Trust and copy its token.

Create service env file:

```bash
sudo cp deploy/bare-metal/systemd/cloudflared-certverify.env.example /etc/default/cloudflared-certverify
sudo nano /etc/default/cloudflared-certverify
```

Set:

```bash
CLOUDFLARE_TUNNEL_TOKEN=<your-tunnel-token>
```

Install the service:

```bash
sudo useradd --system --no-create-home --shell /usr/sbin/nologin cloudflared || true
sudo cp deploy/bare-metal/systemd/cloudflared-certverify.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now cloudflared-certverify
sudo systemctl status cloudflared-certverify --no-pager
```

In Cloudflare Tunnel Public Hostname:

- hostname: `certify.dostcaraga.ph`
- service: `http://127.0.0.1:8080`

In Cloudflare SSL/TLS settings, use `Full (strict)`.

## 7. Firewall baseline

Because traffic arrives via tunnel, keep only SSH open publicly:

```bash
sudo ufw allow OpenSSH
sudo ufw enable
sudo ufw status
```

## 8. Validation checklist

```bash
curl -I http://127.0.0.1:8080
php artisan about
sudo systemctl status nginx php8.3-fpm certverify-queue certverify-scheduler cloudflared-certverify --no-pager
```

Then test:

- `https://certify.dostcaraga.ph`
- login + certificate verification flow
- queued email/send jobs

## 9. Backup and restore (bare-metal)

Create backup bundle:

```bash
./scripts/backup-bare-metal-data.sh
```

Send the bundle directly to an offsite cloud server over SSH:

```bash
OFFSITE_BACKUP_ENABLED=true \
OFFSITE_BACKUP_METHOD=rsync \
OFFSITE_BACKUP_HOST=203.0.113.10 \
OFFSITE_BACKUP_USER=backupuser \
OFFSITE_BACKUP_PATH=/srv/backups/certverify \
OFFSITE_BACKUP_SSH_KEY=/home/talinoserver/.ssh/offsite_backup_ed25519 \
./scripts/backup-bare-metal-data.sh
```

Notes:

- `OFFSITE_BACKUP_METHOD` supports `rsync` or `scp`
- `OFFSITE_BACKUP_KEEP_LOCAL=true` keeps a local copy after upload
- `OFFSITE_BACKUP_PORT` defaults to `22`
- the remote server must already accept your SSH key login

Restore bundle:

```bash
CONFIRM_RESTORE_BARE_METAL=YES ./scripts/restore-bare-metal-data.sh /path/to/cert-verify_data-only_*.tar.gz
```
