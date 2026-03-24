#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${ENV_FILE:-${PROJECT_DIR}/.env}"

APP_HOST_OVERRIDE="${APP_HOST_OVERRIDE:-}"
APP_LISTEN_ADDR="${APP_LISTEN_ADDR:-127.0.0.1}"
APP_LISTEN_PORT="${APP_LISTEN_PORT:-8080}"
NGINX_SITE_NAME="${NGINX_SITE_NAME:-certverify}"
DISABLE_DEFAULT_NGINX_SITE="${DISABLE_DEFAULT_NGINX_SITE:-true}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
RUN_OPTIMIZE="${RUN_OPTIMIZE:-true}"

if [[ "${EUID}" -ne 0 ]]; then
    exec sudo -E bash "$0" "$@"
fi

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Required command not found: $1" >&2
        exit 1
    fi
}

read_env_value() {
    local key="$1"
    local raw

    if [[ ! -f "${ENV_FILE}" ]]; then
        return 1
    fi

    raw="$(awk -F= -v target="${key}" '$1 == target {sub(/^[^=]*=/, ""); print; exit}' "${ENV_FILE}")"
    raw="${raw%$'\r'}"

    if [[ "${raw}" =~ ^\"(.*)\"$ ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
        return 0
    fi

    if [[ "${raw}" =~ ^\'(.*)\'$ ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
        return 0
    fi

    printf '%s' "${raw}"
}

discover_php_fpm_sock() {
    local sock
    shopt -s nullglob
    for sock in /run/php/php*-fpm.sock; do
        if [[ -S "${sock}" || -e "${sock}" ]]; then
            printf '%s' "${sock}"
            return 0
        fi
    done
    return 1
}

discover_php_fpm_service() {
    local svc
    svc="$(systemctl list-unit-files --type=service --no-legend 2>/dev/null | awk '/^php[0-9.]+-fpm\.service/ {print $1; exit}')"
    if [[ -n "${svc}" ]]; then
        printf '%s' "${svc%.service}"
        return 0
    fi
    return 1
}

require_command nginx
require_command systemctl
require_command "${PHP_BIN}"

if [[ ! -f "${ENV_FILE}" ]]; then
    echo "Missing env file: ${ENV_FILE}" >&2
    exit 1
fi

APP_URL="$(read_env_value APP_URL || true)"
APP_HOST="${APP_HOST_OVERRIDE}"
if [[ -z "${APP_HOST}" && -n "${APP_URL}" ]]; then
    APP_HOST="$(printf '%s' "${APP_URL}" | sed -E 's#^[a-zA-Z][a-zA-Z0-9+.-]*://##; s#/.*$##; s#:[0-9]+$##')"
fi
APP_HOST="${APP_HOST:-localhost}"

PHP_FPM_SOCK="${PHP_FPM_SOCK:-$(discover_php_fpm_sock || true)}"
if [[ -z "${PHP_FPM_SOCK}" ]]; then
    echo "Could not detect PHP-FPM socket. Set PHP_FPM_SOCK=/run/php/php8.x-fpm.sock and rerun." >&2
    exit 1
fi

PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-$(discover_php_fpm_service || true)}"
if [[ -z "${PHP_FPM_SERVICE}" ]]; then
    echo "Could not detect php-fpm systemd service. Set PHP_FPM_SERVICE=php8.x-fpm and rerun." >&2
    exit 1
fi

echo "Configuring CertVerify production services"
echo "  Project dir: ${PROJECT_DIR}"
echo "  Env file: ${ENV_FILE}"
echo "  Host: ${APP_HOST}"
echo "  Listen: ${APP_LISTEN_ADDR}:${APP_LISTEN_PORT}"
echo "  PHP-FPM socket: ${PHP_FPM_SOCK}"
echo "  PHP-FPM service: ${PHP_FPM_SERVICE}"

install -d -m 0755 /etc/nginx/sites-available /etc/nginx/sites-enabled /etc/systemd/system

cat > "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf" <<EOF
server {
    listen ${APP_LISTEN_ADDR}:${APP_LISTEN_PORT};
    server_name ${APP_HOST};

    root ${PROJECT_DIR}/public;
    index index.php index.html;

    charset utf-8;
    server_tokens off;
    client_max_body_size 32m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
    }

    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass unix:${PHP_FPM_SOCK};
        fastcgi_index index.php;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param HTTP_X_FORWARDED_PROTO \$http_x_forwarded_proto;
        fastcgi_param HTTP_X_FORWARDED_HOST \$http_x_forwarded_host;
        fastcgi_param HTTP_X_FORWARDED_FOR \$http_x_forwarded_for;
        fastcgi_param HTTP_CF_CONNECTING_IP \$http_cf_connecting_ip;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sfn "/etc/nginx/sites-available/${NGINX_SITE_NAME}.conf" "/etc/nginx/sites-enabled/${NGINX_SITE_NAME}.conf"
if [[ "${DISABLE_DEFAULT_NGINX_SITE}" == "true" ]]; then
    rm -f /etc/nginx/sites-enabled/default
fi

cat > /etc/systemd/system/certverify-queue.service <<EOF
[Unit]
Description=CertVerify Laravel Queue Worker
After=network.target postgresql.service

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=${PROJECT_DIR}
ExecStart=${PHP_BIN} ${PROJECT_DIR}/artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600
ExecReload=${PHP_BIN} ${PROJECT_DIR}/artisan queue:restart

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/certverify-scheduler.service <<EOF
[Unit]
Description=CertVerify Laravel Scheduler
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=${PROJECT_DIR}
ExecStart=${PHP_BIN} ${PROJECT_DIR}/artisan schedule:work

[Install]
WantedBy=multi-user.target
EOF

if [[ "${RUN_OPTIMIZE}" == "true" ]]; then
    sudo -u www-data "${PHP_BIN}" "${PROJECT_DIR}/artisan" optimize || true
fi

nginx -t
systemctl daemon-reload
systemctl enable --now "${PHP_FPM_SERVICE}" nginx certverify-queue certverify-scheduler
systemctl restart "${PHP_FPM_SERVICE}" nginx certverify-queue certverify-scheduler

echo
echo "Services are installed and enabled on boot:"
echo "  - nginx"
echo "  - ${PHP_FPM_SERVICE}"
echo "  - certverify-queue"
echo "  - certverify-scheduler"
echo
echo "Check status with:"
echo "  systemctl status nginx ${PHP_FPM_SERVICE} certverify-queue certverify-scheduler --no-pager"
