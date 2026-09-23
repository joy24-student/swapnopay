#!/usr/bin/env bash
# ==============================================================================
# SwapnoPay 1-Click Production VPS Deployment Automation
# Domain: swapnopay.top (pay.swapnopay.top, admin.swapnopay.top, api.swapnopay.top)
# Targets: Ubuntu 22.04 / 24.04 LTS & Debian 11 / 12
# ==============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${CYAN}"
echo "=========================================================================="
echo "      🚀 SwapnoPay Enterprise Platform - Production VPS Installer        "
echo "=========================================================================="
echo -e "${NC}"

DOMAIN="${1:-swapnopay.top}"
ADMIN_EMAIL="${2:-admin@$DOMAIN}"
BASE_DIR="/var/www/swapnopay"

echo -e "${BLUE}Target Base Domain :${NC} $DOMAIN"
echo -e "${BLUE}SSL Contact Email  :${NC} $ADMIN_EMAIL"
echo -e "${BLUE}Target Directory   :${NC} $BASE_DIR"
echo ""

# ──────────────────────────────────────────────────────────────────────────────
# [1/8] Check Root Privileges
# ──────────────────────────────────────────────────────────────────────────────
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Please run as root or with sudo: sudo ./deploy-vps.sh${NC}"
    exit 1
fi

# ──────────────────────────────────────────────────────────────────────────────
# [2/8] System Update & Dependencies
# ──────────────────────────────────────────────────────────────────────────────
echo -e "${YELLOW}─── [1/8] Updating system packages & installing core dependencies...${NC}"
apt-get update -y
apt-get install -y curl wget git unzip ufw software-properties-common build-essential nginx certbot python3-certbot-nginx

# ──────────────────────────────────────────────────────────────────────────────
# [3/8] Configure UFW Firewall
# ──────────────────────────────────────────────────────────────────────────────
echo -e "${YELLOW}─── [2/8] Hardening UFW Firewall (SSH, HTTP, HTTPS)...${NC}"
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw --force enable

# ──────────────────────────────────────────────────────────────────────────────
# [4/8] Install Node.js 20 LTS & PM2
# ──────────────────────────────────────────────────────────────────────────────
echo -e "${YELLOW}─── [3/8] Setting up Node.js v22 (LTS) & PM2...${NC}"
if ! command -v node &> /dev/null || [[ "$(node -v)" != v22* ]]; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y nodejs
fi
echo -e "${GREEN}✓ Node.js version:${NC} $(node -v) | ${GREEN}npm:${NC} $(npm -v)"

npm install -g pm2
pm2 startup systemd -u root --hp /root || true

# ──────────────────────────────────────────────────────────────────────────────
# [5/8] Synchronize Files into /var/www/swapnopay
# ──────────────────────────────────────────────────────────────────────────────
echo -e "${YELLOW}─── [4/8] Preparing directory structure in $BASE_DIR...${NC}"
mkdir -p "$BASE_DIR"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# If running directly inside the cloned repo
if [ -d "$SCRIPT_DIR/swapnopay-backend" ]; then
    echo "Copying repository files from $SCRIPT_DIR to $BASE_DIR..."
    cp -ru "$SCRIPT_DIR/." "$BASE_DIR/"
fi

# Ensure uploads directory with proper permissions
mkdir -p "$BASE_DIR/swapnopay-backend/uploads/kyc"
chmod -R 775 "$BASE_DIR/swapnopay-backend/uploads"

# ──────────────────────────────────────────────────────────────────────────────
# [6/8] Build Web Frontend & Admin Panel
# ──────────────────────────────────────────────────────────────────────────────
echo -e "${YELLOW}─── [5/8] Building Admin Panel & Installing Web Dependencies...${NC}"

# Admin Panel
if [ -d "$BASE_DIR/admin" ]; then
    cd "$BASE_DIR/admin"
    if [ ! -f .env ]; then
        if [ -f .env.production.example ]; then
            cp .env.production.example .env
        elif [ -f .env.example ]; then
            cp .env.example .env
        fi
    fi
    echo "Building Admin Panel with Vite..."
    npm install
    npm run build
fi

# Web Frontend
if [ -d "$BASE_DIR/web" ]; then
    cd "$BASE_DIR/web"
    npm install --production || true
fi

# ──────────────────────────────────────────────────────────────────────────────
# [7/8] Setup & Launch Backend with PM2
# ──────────────────────────────────────────────────────────────────────────────
echo -e "${YELLOW}─── [6/8] Initializing SwapnoPay Backend API with PM2...${NC}"
cd "$BASE_DIR/swapnopay-backend"
npm install --production

# Generate default security keys if .env is missing
if [ ! -f .env ]; then
    echo "Creating new .env for backend..."
    if [ -f .env.production.example ]; then
        cp .env.production.example .env
    else
        cp .env.example .env
    fi
    # Auto-generate 32-byte cryptographically secure random keys
    ADMIN_SECRET_KEY=$(openssl rand -hex 32)
    API_PEPPER_KEY=$(openssl rand -hex 32)
    WEBHOOK_SECRET_KEY=$(openssl rand -hex 32)
    sed -i "s/ADMIN_SECRET=.*/ADMIN_SECRET=$ADMIN_SECRET_KEY/" .env
    sed -i "s/API_KEY_PEPPER=.*/API_KEY_PEPPER=$API_PEPPER_KEY/" .env
    sed -i "s/PAYMENT_WEBHOOK_SECRET=.*/PAYMENT_WEBHOOK_SECRET=$WEBHOOK_SECRET_KEY/" .env
    echo -e "${GREEN}✓ Generated secure random keys for ADMIN_SECRET, API_KEY_PEPPER & PAYMENT_WEBHOOK_SECRET${NC}"
fi

# Start/Restart via PM2
pm2 delete swapnopay-backend 2>/dev/null || true
if [ -f ecosystem.config.cjs ]; then
    pm2 start ecosystem.config.cjs --env production
elif [ -f ecosystem.config.js ]; then
    cp ecosystem.config.js ecosystem.config.cjs
    pm2 start ecosystem.config.cjs --env production
else
    pm2 start src/index.js --name swapnopay-backend
fi
pm2 save

# ──────────────────────────────────────────────────────────────────────────────
# [8/8] Configure Nginx & Let's Encrypt SSL
# ──────────────────────────────────────────────────────────────────────────────
echo -e "${YELLOW}─── [7/8] Configuring Nginx Virtual Hosts for $DOMAIN...${NC}"
NGINX_CONF="/etc/nginx/sites-available/$DOMAIN"

if [ -f "$BASE_DIR/deploy/swapnopay.top.conf" ]; then
    cp "$BASE_DIR/deploy/swapnopay.top.conf" "$NGINX_CONF"
else
    cat > "$NGINX_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name pay.$DOMAIN $DOMAIN www.$DOMAIN;

    root $BASE_DIR/web;
    index index.html widget.html docs.html;

    client_max_body_size 50M;

    gzip on;
    gzip_types text/plain text/css text/xml application/json application/javascript image/svg+xml;

    location / {
        add_header Access-Control-Allow-Origin "*" always;
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS" always;
        try_files \$uri \$uri/ /index.html;
    }

    location ~* ^/(f|forms|form)/ {
        add_header Access-Control-Allow-Origin "*" always;
        try_files \$uri /form.html?\$args;
    }

    location /v1/ {
        proxy_pass http://127.0.0.1:4000/v1/;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location /uploads/ {
        proxy_pass http://127.0.0.1:4000/uploads/;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location /socket.io/ {
        proxy_pass http://127.0.0.1:4000/socket.io/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
    }

    location ~* ^/(health|healthz)$ {
        proxy_pass http://127.0.0.1:4000;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}

server {
    listen 80;
    listen [::]:80;
    server_name api.$DOMAIN;

    client_max_body_size 50M;

    location / {
        proxy_pass http://127.0.0.1:4000/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}

server {
    listen 80;
    listen [::]:80;
    server_name admin.$DOMAIN;

    root $BASE_DIR/admin/dist;
    index index.html;

    client_max_body_size 50M;

    location /v1/ {
        proxy_pass http://127.0.0.1:4000/v1/;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 60s;
    }

    location = /healthz {
        proxy_pass http://127.0.0.1:4000/healthz;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location = /health {
        if (\$http_accept !~* "text/html") {
            rewrite ^ /internal_health last;
        }
        if (\$args ~* "json") {
            rewrite ^ /internal_health last;
        }
        try_files \$uri \$uri/ /index.html;
    }

    location = /internal_health {
        internal;
        proxy_pass http://127.0.0.1:4000/health;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location / {
        try_files \$uri \$uri/ /index.html =404;
    }
}
EOF
fi

# Enable site and remove default
ln -sf "$NGINX_CONF" "/etc/nginx/sites-enabled/$DOMAIN"
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

# ──────────────────────────────────────────────────────────────────────────────
# Obtain Free Let's Encrypt SSL
# ──────────────────────────────────────────────────────────────────────────────
echo -e "${YELLOW}─── [8/8] Issuing Let's Encrypt SSL Certificates...${NC}"
certbot --nginx -d "pay.$DOMAIN" -d "admin.$DOMAIN" -d "api.$DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect || {
    echo -e "${YELLOW}⚠️  Certbot SSL generation encountered a DNS warning. Ensure your DNS A-records (pay.$DOMAIN, admin.$DOMAIN, api.$DOMAIN) point to this VPS IP and run 'sudo certbot --nginx' once propagated.${NC}"
}

# ──────────────────────────────────────────────────────────────────────────────
# Health Check Verification
# ──────────────────────────────────────────────────────────────────────────────
echo ""
echo -e "${CYAN}=========================================================================="
echo "                  🎉 DEPLOYMENT COMPLETED SUCCESSFULLY                   "
echo "=========================================================================="
echo -e "${NC}"
echo -e "${GREEN}✓ Backend Health Check:${NC}"
curl -s http://127.0.0.1:4000/healthz || echo "Backend warming up..."
echo ""
echo -e "${GREEN}Live Service Endpoints:${NC}"
echo -e "  • Hosted Checkout & Web Portal : ${CYAN}https://pay.$DOMAIN${NC}"
echo -e "  • Admin Control Panel          : ${CYAN}https://admin.$DOMAIN${NC}"
echo -e "  • API & WebSocket Gateway      : ${CYAN}https://api.$DOMAIN${NC}"
echo -e "  • Developer Documentation      : ${CYAN}https://pay.$DOMAIN/docs.html${NC}"
echo -e "  • Embedded Checkout Widget     : ${CYAN}https://pay.$DOMAIN/widget.html${NC}"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "1. Verify your Supabase keys in $BASE_DIR/swapnopay-backend/.env"
echo "2. Check PM2 logs anytime with: pm2 logs swapnopay-backend"
echo "=========================================================================="
