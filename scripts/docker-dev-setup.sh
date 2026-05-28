#!/usr/bin/env bash
set -e

# Docker Development Setup Script
# This script sets up the Docker development environment safely

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${PROJECT_ROOT}"

echo "🚀 Setting up Docker Development Environment..."
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker is not installed. Please install Docker first.${NC}"
    exit 1
fi

if ! command -v docker compose &> /dev/null; then
    echo -e "${RED}❌ Docker Compose is not installed. Please install Docker Compose first.${NC}"
    exit 1
fi

# Create local env file if it doesn't exist
if [ ! -f ".env.docker.dev.local" ]; then
    echo -e "${YELLOW}📄 Creating .env.docker.dev.local from template...${NC}"
    cp .env.docker.dev .env.docker.dev.local
    
    # Generate APP_KEY
    APP_KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));" 2>/dev/null || echo "")
    if [ -n "$APP_KEY" ]; then
        sed -i "s/^APP_KEY=$/APP_KEY=${APP_KEY}/" .env.docker.dev.local
        echo -e "${GREEN}✅ Generated APP_KEY${NC}"
    fi
    
    echo -e "${GREEN}✅ Created .env.docker.dev.local${NC}"
    echo ""
else
    echo -e "${GREEN}✅ .env.docker.dev.local already exists${NC}"
fi

# Check for port conflicts
echo -e "${YELLOW}🔍 Checking for port conflicts...${NC}"

check_port() {
    local port=$1
    local service=$2
    if lsof -Pi :${port} -sTCP:LISTEN -t >/dev/null 2>&1; then
        echo -e "${RED}❌ Port ${port} is already in use (for ${service})${NC}"
        return 1
    else
        echo -e "${GREEN}✅ Port ${port} is available (${service})${NC}"
        return 0
    fi
}

DEV_APP_PORT=$(grep -E '^DEV_APP_PORT=' .env.docker.dev.local | cut -d'=' -f2 || echo "8082")
DEV_VITE_PORT=$(grep -E '^DEV_VITE_PORT=' .env.docker.dev.local | cut -d'=' -f2 || echo "5173")
DEV_DB_PORT=$(grep -E '^DEV_DB_PORT=' .env.docker.dev.local | cut -d'=' -f2 || echo "54330")

PORT_CONFLICT=0
check_port "$DEV_APP_PORT" "Laravel App" || PORT_CONFLICT=1
check_port "$DEV_VITE_PORT" "Vite Dev Server" || PORT_CONFLICT=1
check_port "$DEV_DB_PORT" "PostgreSQL" || PORT_CONFLICT=1

if [ $PORT_CONFLICT -eq 1 ]; then
    echo ""
    echo -e "${YELLOW}⚠️  Port conflicts detected!${NC}"
    echo "Please edit .env.docker.dev.local and change the conflicting ports:"
    echo "  DEV_APP_PORT=<new_port>"
    echo "  DEV_VITE_PORT=<new_port>"
    echo "  DEV_DB_PORT=<new_port>"
    exit 1
fi

echo ""

# Build and start containers
echo -e "${YELLOW}🐳 Building and starting Docker containers...${NC}"
docker compose -f docker-compose.dev.yml up -d --build

echo -e "${GREEN}✅ Containers started!${NC}"
echo ""

# Wait for database to be ready
echo -e "${YELLOW}⏳ Waiting for database to be ready...${NC}"
sleep 5

# Check if vendor directory exists locally
if [ ! -d "vendor" ] || [ -z "$(ls -A vendor 2>/dev/null)" ]; then
    echo -e "${YELLOW}📦 Installing PHP dependencies...${NC}"
    docker compose -f docker-compose.dev.yml exec -T app composer install
else
    echo -e "${GREEN}✅ vendor directory already exists${NC}"
fi

# Check if node_modules exists locally
if [ ! -d "node_modules" ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
    echo -e "${YELLOW}📦 Installing Node dependencies...${NC}"
    docker compose -f docker-compose.dev.yml exec -T vite npm install
else
    echo -e "${GREEN}✅ node_modules directory already exists${NC}"
fi

# Create storage directories
echo -e "${YELLOW}📁 Setting up storage directories...${NC}"
docker compose -f docker-compose.dev.yml exec -T app mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Run migrations
echo -e "${YELLOW}🗄️  Running database migrations...${NC}"
docker compose -f docker-compose.dev.yml exec -T app php artisan migrate --force

# Optimize
echo -e "${YELLOW}⚡ Optimizing Laravel...${NC}"
docker compose -f docker-compose.dev.yml exec -T app php artisan optimize

echo ""
echo -e "${GREEN}🎉 Docker Development Environment is ready!${NC}"
echo ""
echo "📋 Access your application:"
echo "   Laravel App:     http://localhost:${DEV_APP_PORT}"
echo "   Vite Dev Server: http://localhost:${DEV_VITE_PORT}"
echo "   PostgreSQL:      localhost:${DEV_DB_PORT}"
echo ""
echo "📋 Useful commands:"
echo "   View logs:        docker compose -f docker-compose.dev.yml logs -f"
echo "   Run artisan:      docker compose -f docker-compose.dev.yml exec app php artisan <command>"
echo "   Stop:             docker compose -f docker-compose.dev.yml down"
echo "   Stop & clean:     docker compose -f docker-compose.dev.yml down -v"
echo ""
echo "📖 For more info, see: DOCKER_DEV.md"