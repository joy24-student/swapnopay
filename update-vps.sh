#!/usr/bin/env bash
# ==============================================================================
# SwapnoPay Production VPS 1-Click Update Script
# Usage: sudo ./update-vps.sh
# ==============================================================================

set -eo pipefail

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}====================================================${NC}"
echo -e "${GREEN}      🔄 SwapnoPay Production VPS Updater           ${NC}"
echo -e "${BLUE}====================================================${NC}"

# 1. Locate repository directory
TARGET_DIR="/var/www/swapnopay"
if [ ! -d "$TARGET_DIR" ]; then
    if [ -d "$(pwd)/swapnopay-backend" ]; then
        TARGET_DIR="$(pwd)"
    else
        echo -e "${RED}❌ Could not locate SwapnoPay installation at /var/www/swapnopay or $(pwd)${NC}"
        exit 1
    fi
fi

cd "$TARGET_DIR"
echo -e "${YELLOW}📍 Working in: $TARGET_DIR${NC}"

# 2. Pull latest changes safely
echo -e "${YELLOW}📥 Syncing latest code with origin/main...${NC}"
git fetch origin main
git reset --hard origin/main

# 3. Ensure required directories and permissions
echo -e "${YELLOW}📁 Verifying storage directories...${NC}"
mkdir -p "$TARGET_DIR/swapnopay-backend/uploads/kyc"
mkdir -p "$TARGET_DIR/swapnopay-backend/uploads/products"
mkdir -p "$TARGET_DIR/swapnopay-backend/data/shop-runtime/hosts"
mkdir -p "$TARGET_DIR/swapnopay-backend/data/shop-sites/hosts"
mkdir -p "$TARGET_DIR/swapnopay-backend/data/shop-sites/stores"
chown -R www-data:www-data "$TARGET_DIR/swapnopay-backend/data/shop-runtime" "$TARGET_DIR/swapnopay-backend/data/shop-sites" 2>/dev/null || true
chmod -R 775 "$TARGET_DIR/swapnopay-backend/uploads" "$TARGET_DIR/swapnopay-backend/data" 2>/dev/null || true

# Locate Backend directory: either $TARGET_DIR/swapnopay-backend or $TARGET_DIR
BACKEND_APP_DIR=""
if [ -f "$TARGET_DIR/swapnopay-backend/package.json" ]; then
    BACKEND_APP_DIR="$TARGET_DIR/swapnopay-backend"
elif [ -f "$TARGET_DIR/package.json" ]; then
    BACKEND_APP_DIR="$TARGET_DIR"
fi

# 4. Update Node.js Backend dependencies & restart service
if [ -n "$BACKEND_APP_DIR" ]; then
    echo -e "${YELLOW}⚙️  Updating Backend API dependencies in $BACKEND_APP_DIR...${NC}"
    cd "$BACKEND_APP_DIR"
    npm install --omit=dev --no-audit --no-fund || true
    
    # Restart PM2 process
    if command -v pm2 >/dev/null 2>&1; then
        echo -e "${YELLOW}🔄 Restarting Backend via PM2...${NC}"
        if pm2 list | grep -q "swapnopay-backend"; then
            pm2 restart swapnopay-backend
        elif [ -f ecosystem.config.cjs ]; then
            pm2 start ecosystem.config.cjs --env production
        elif [ -f ecosystem.config.js ]; then
            pm2 start ecosystem.config.js --env production
        else
            pm2 start src/index.js --name swapnopay-backend
        fi
        pm2 save 2>/dev/null || true
    fi
fi

# 5. Build Admin Panel if present
if [ -d "$TARGET_DIR/admin" ]; then
    echo -e "${YELLOW}🖥️  Verifying Admin Control Panel...${NC}"
    cd "$TARGET_DIR/admin"
    if [ -f package.json ]; then
        if [ ! -f dist/index.html ]; then
            npm install --no-audit --no-fund 2>/dev/null || true
            npm run build 2>/dev/null || true
        fi
    fi
    # If dist/index.html is missing, auto-generate it from dist/assets
    if [ -d "$TARGET_DIR/admin/dist/assets" ] && [ ! -f "$TARGET_DIR/admin/dist/index.html" ]; then
        JS_FILE=$(basename $(ls "$TARGET_DIR/admin/dist/assets"/*.js 2>/dev/null | head -n 1) 2>/dev/null || true)
        CSS_FILE=$(basename $(ls "$TARGET_DIR/admin/dist/assets"/*.css 2>/dev/null | head -n 1) 2>/dev/null || true)
        if [ -n "$JS_FILE" ]; then
            echo -e "${YELLOW}Auto-generating missing dist/index.html for Admin panel...${NC}"
            cat << EOF > "$TARGET_DIR/admin/dist/index.html"
<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SwapnoPay Admin</title>
    <script type="module" crossorigin src="/assets/${JS_FILE}"></script>
    <link rel="stylesheet" crossorigin href="/assets/${CSS_FILE}">
  </head>
  <body>
    <div id="root"></div>
  </body>
</html>
EOF
        fi
    fi
    chown -R www-data:www-data "$TARGET_DIR/admin" 2>/dev/null || true
    chmod -R 755 "$TARGET_DIR/admin" 2>/dev/null || true
fi

# 6. Check Storefront permissions
if [ -d "$TARGET_DIR/shop" ]; then
    echo -e "${YELLOW}🛍️  Setting Web Shop file permissions...${NC}"
    chown -R www-data:www-data "$TARGET_DIR/shop" 2>/dev/null || true
    chmod -R 755 "$TARGET_DIR/shop" 2>/dev/null || true
fi

# 7. Reload Nginx
if command -v nginx >/dev/null 2>&1; then
    echo -e "${YELLOW}🌐 Testing & Reloading Nginx...${NC}"
    if nginx -t 2>/dev/null; then
        systemctl reload nginx 2>/dev/null || service nginx reload 2>/dev/null || true
        echo -e "${GREEN}✓ Nginx reloaded successfully.${NC}"
    else
        echo -e "${RED}⚠️  Nginx configuration test failed. Check nginx config.${NC}"
    fi
fi

# 8. Docker Compose fallback (if running in Docker)
if command -v docker >/dev/null 2>&1 && [ -f "$TARGET_DIR/docker-compose.yml" ]; then
    if docker compose ps 2>/dev/null | grep -q "swapnopay"; then
        echo -e "${YELLOW}🐳 Updating Docker containers...${NC}"
        cd "$TARGET_DIR"
        docker compose build
        docker compose up -d --remove-orphans
    fi
fi

# 9. Health check
echo -e "${YELLOW}🩺 Performing health check...${NC}"
sleep 2
HEALTH=$(curl -s http://127.0.0.1:4000/healthz || echo "ERROR")
if echo "$HEALTH" | grep -q "ok"; then
    echo -e "${GREEN}✅ SwapnoPay Backend is healthy: $HEALTH${NC}"
else
    echo -e "${YELLOW}ℹ️  Backend response: $HEALTH (check 'pm2 logs' if not online)${NC}"
fi

echo -e "${GREEN}====================================================${NC}"
echo -e "${GREEN}  🎉 SwapnoPay VPS Update Finished Successfully!     ${NC}"
echo -e "${GREEN}====================================================${NC}"
