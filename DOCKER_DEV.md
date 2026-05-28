# Docker Development Setup

This is a **development-only** Docker setup that runs alongside your existing production system **without conflicts**.

## 🎯 Key Features

- **No port conflicts**: Uses ports `8082` (app), `5173` (Vite), and `54330` (PostgreSQL)
- **Hot reload**: Code changes reflect immediately (volumes mounted)
- **Separate database**: Uses `certverify_dev` database, won't touch production data
- **Includes queue worker**: Background jobs run automatically
- **Vite dev server**: Frontend assets served with HMR

## 🚀 Quick Start

### 1. First Time Setup

```bash
# Copy the development environment file
cp .env.docker.dev .env.docker.dev.local

# Generate an APP_KEY (optional - Laravel will generate one on first run)
php -r "echo 'APP_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL;" >> .env.docker.dev.local

# Start the development stack
docker compose -f docker-compose.dev.yml up -d --build

# Install PHP dependencies inside container
docker compose -f docker-compose.dev.yml exec app composer install

# Install Node dependencies
docker compose -f docker-compose.dev.yml exec vite npm install

# Run database migrations
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force

# (Optional) Seed the database
docker compose -f docker-compose.dev.yml exec app php artisan db:seed
```

### 2. Access the Application

| Service | URL | Description |
|---------|-----|-------------|
| Laravel App | http://localhost:8082 | Main application |
| Vite Dev Server | http://localhost:5173 | Hot module replacement for assets |
| PostgreSQL | `localhost:54330` | Database (connect with your DB client) |

### 3. Useful Commands

```bash
# View logs
docker compose -f docker-compose.dev.yml logs -f

# View specific service logs
docker compose -f docker-compose.dev.yml logs -f app
docker compose -f docker-compose.dev.yml logs -f vite
docker compose -f docker-compose.dev.yml logs -f worker

# Run artisan commands
docker compose -f docker-compose.dev.yml exec app php artisan <command>

# Run composer commands
docker compose -f docker-compose.dev.yml exec app composer <command>

# Run npm commands
docker compose -f docker-compose.dev.yml exec vite npm <command>

# Access database
docker compose -f docker-compose.dev.yml exec db psql -U certverify_dev_user -d certverify_dev

# Stop the stack
docker compose -f docker-compose.dev.yml down

# Stop and remove volumes (WARNING: deletes dev database!)
docker compose -f docker-compose.dev.yml down -v
```

### 4. Customizing Ports

If the default ports conflict with other services, edit `.env.docker.dev.local`:

```bash
DEV_APP_PORT=8083      # Change from 8082
DEV_VITE_PORT=5174     # Change from 5173
DEV_DB_PORT=54331      # Change from 54330
```

Then restart: `docker compose -f docker-compose.dev.yml up -d`

## 🔧 How It Works

### Services

| Service | Purpose |
|---------|---------|
| `app` | Laravel PHP application (php artisan serve) |
| `vite` | Vite development server with HMR |
| `worker` | Laravel queue worker for background jobs |
| `db` | PostgreSQL 16 database |

### Volume Mounts

- Your local code is mounted into containers (`./:/var/www/html`)
- `vendor/` and `node_modules/` are kept in anonymous volumes (not synced)
- Database data persists in named volume `dev_postgres_data`

### Network Isolation

- Containers communicate on internal Docker network
- Only exposed ports (`8082`, `5173`, `54330`) are accessible from host
- Production services on ports `8080`, `5432` are untouched

## 🛠️ Troubleshooting

### "Port is already allocated"
Change the port in `.env.docker.dev.local` and restart.

### "Permission denied" on storage
```bash
docker compose -f docker-compose.dev.yml exec app chown -R www-data:www-data storage
```

### Database connection issues
```bash
# Reset the database volume
docker compose -f docker-compose.dev.yml down -v
docker compose -f docker-compose.dev.yml up -d
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force
```

### Vite assets not loading
Make sure `VITE_BACKEND_URL=http://localhost:8082` is set in `.env.docker.dev.local`

## 📁 File Structure

```
├── docker-compose.dev.yml      # Dev orchestration
├── .env.docker.dev             # Default dev environment
├── .env.docker.dev.local       # Your local overrides (gitignored)
├── docker/
│   └── dev/
│       └── Dockerfile          # Dev PHP/Node image
└── DOCKER_DEV.md               # This file
```

## ⚠️ Important Notes

1. **Development only**: This setup is not for production
2. **Database is isolated**: Uses separate PostgreSQL instance on port 54330
3. **No SSL**: HTTPS not configured (use production Docker for that)
4. **Mail**: Set to `log` driver (emails logged to storage/logs)
5. **Backups disabled**: Automatic backups are turned off in dev mode

## 🔄 Switching Between Production and Dev

Your production system remains unchanged and runs on:
- Port 8080 (web)
- Port 5432 (PostgreSQL)

Development runs on:
- Port 8082 (app)
- Port 5173 (Vite)
- Port 54330 (PostgreSQL)

They can run simultaneously without conflicts.