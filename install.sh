#!/usr/bin/env bash
# ============================================================
#  NexPanel — Production Installer for Ubuntu 22.04 / 24.04
#
#  Run as root on a fresh server:
#     curl -fsSL https://your-host/install.sh | sudo bash
#  or, from a copy of this project:
#     sudo bash install.sh
#
#  Installs PHP-FPM + Nginx + MySQL + certbot, deploys the panel,
#  wires passwordless sudo for service control, and serves it.
# ============================================================
set -euo pipefail

# ---- config (override via env) ------------------------------------------
APP_DIR="${APP_DIR:-/var/www/nexpanel}"
APP_USER="${APP_USER:-www-data}"
PHP_VERSION="${PHP_VERSION:-8.3}"
SERVER_NAME="${SERVER_NAME:-_}"          # domain, or _ to match any host
REPO_URL="${REPO_URL:-}"                 # optional git repo to clone
# -------------------------------------------------------------------------

log()  { printf '\n\033[1;36m==>\033[0m %s\n' "$1"; }
warn() { printf '\033[1;33m[warn]\033[0m %s\n' "$1"; }
die()  { printf '\033[1;31m[error]\033[0m %s\n' "$1" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "Please run as root (sudo bash install.sh)."
command -v apt-get >/dev/null || die "This installer targets Debian/Ubuntu."

# ---- 1. system packages -------------------------------------------------
log "Installing system packages (this can take a few minutes)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y software-properties-common curl unzip git ca-certificates lsb-release
# Ondrej PPA gives predictable PHP versions on Ubuntu.
add-apt-repository -y ppa:ondrej/php 2>/dev/null || warn "Could not add ondrej PPA; using distro PHP."
apt-get update -y

apt-get install -y \
    "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-sqlite3" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-intl" \
    nginx mysql-server cron supervisor \
    certbot python3-certbot-nginx || warn "Some optional packages failed to install."

# Composer
if ! command -v composer >/dev/null; then
    log "Installing Composer"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

# ---- 2. fetch / place the application -----------------------------------
if [ -n "$REPO_URL" ]; then
    log "Cloning $REPO_URL -> $APP_DIR"
    rm -rf "$APP_DIR"
    git clone --depth 1 "$REPO_URL" "$APP_DIR"
elif [ ! -d "$APP_DIR" ]; then
    # Installer run from inside the project copy.
    SRC="$(cd "$(dirname "$0")" && pwd)"
    log "Copying project from $SRC -> $APP_DIR"
    mkdir -p "$APP_DIR"
    cp -a "$SRC/." "$APP_DIR/"
else
    log "Using existing app directory $APP_DIR"
fi
cd "$APP_DIR"

# ---- 3. dependencies + build -------------------------------------------
log "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

if [ -f package.json ] && [ ! -d public/build ]; then
    if command -v npm >/dev/null; then
        log "Building front-end assets"
        npm ci && npm run build
    else
        warn "npm not found and public/build missing — install Node to build assets."
    fi
fi

# ---- 4. environment + database -----------------------------------------
log "Configuring environment"
[ -f .env ] || cp .env.example .env
php artisan key:generate --force
touch database/database.sqlite
php artisan migrate --seed --force
php artisan storage:link || true

# Provision a dedicated MySQL admin user so the Database Manager works
# out of the box (root uses auth_socket and can't be reached over TCP).
if command -v mysql >/dev/null; then
    systemctl start mysql 2>/dev/null || true
    DBPW="$(openssl rand -hex 16)"
    if mysql <<SQL 2>/dev/null
CREATE USER IF NOT EXISTS 'nexpanel'@'127.0.0.1' IDENTIFIED BY '${DBPW}';
GRANT ALL PRIVILEGES ON *.* TO 'nexpanel'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL
    then
        sed -i '/^#\? *DB_ADMIN_/d' .env
        cat >> .env <<ENV
DB_ADMIN_HOST=127.0.0.1
DB_ADMIN_PORT=3306
DB_ADMIN_USER=nexpanel
DB_ADMIN_PASSWORD=${DBPW}
ENV
        log "MySQL admin user 'nexpanel' created for the Database Manager"
    else
        warn "Could not create MySQL admin user — set DB_ADMIN_* in .env manually."
    fi
fi

# Cache config AFTER .env is final (env() is unavailable once cached).
php artisan config:cache
php artisan route:cache

# ---- 5. permissions -----------------------------------------------------
log "Setting permissions for $APP_USER"
chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database"
# Let the panel manage nginx vhosts and certbot output directly.
mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled /etc/letsencrypt
chgrp -R "$APP_USER" /etc/nginx/sites-available /etc/nginx/sites-enabled 2>/dev/null || true
chmod -R g+w /etc/nginx/sites-available /etc/nginx/sites-enabled 2>/dev/null || true

# ---- 6. privileged command runner for the AI Assistant ------------------
# The AI "shell" fallback runs commands as root through this denylisted wrapper
# (defense-in-depth on top of the app's SafetyGuard + user confirmation).
log "Installing privileged command runner for the AI Assistant"
cat > /usr/local/bin/nexpanel-run <<'RUNNER'
#!/bin/bash
# nexpanel-run — execute a command (read from stdin) as root for NexPanel.
# Only reachable via sudo by the web user; refuses catastrophic patterns.
cmd="$(cat)"
low="${cmd,,}"
for bad in "rm -rf /" "rm -rf /*" ":(){" "mkfs" "dd if=" "> /dev/sd" "shutdown" " halt" "init 0" "chmod -r 777 /"; do
    case "$low" in *"$bad"*) echo "nexpanel-run: blocked pattern '$bad'" >&2; exit 99 ;; esac
done
# Launch via systemd-run so the command runs in the system context, escaping
# the php-fpm service sandbox (ProtectSystem) that would otherwise make /etc,
# /usr read-only even for root.
if command -v systemd-run >/dev/null; then
    exec systemd-run --collect --wait --pipe --quiet /bin/bash -c "$cmd"
fi
exec bash -c "$cmd"
RUNNER
chmod 755 /usr/local/bin/nexpanel-run

# ---- 6b. passwordless sudo for service control + AI runner --------------
log "Granting $APP_USER passwordless sudo"
cat > /etc/sudoers.d/nexpanel <<SUDO
# Allow the NexPanel web user to manage services and run confirmed AI commands.
$APP_USER ALL=(root) NOPASSWD: /usr/bin/systemctl, /bin/systemctl, /usr/sbin/nginx, /usr/bin/certbot, /usr/local/bin/nexpanel-run
SUDO
chmod 440 /etc/sudoers.d/nexpanel
visudo -cf /etc/sudoers.d/nexpanel || die "Invalid sudoers file generated."

# ---- 6b. let PHP-FPM write /etc/nginx & /etc/letsencrypt ------------------
# The php-fpm unit ships with ProtectSystem=full, which makes /etc read-only
# for the web process — so the Website/SSL modules can't write vhosts/certs.
# Punch through with ReadWritePaths.
log "Allowing PHP-FPM to manage /etc/nginx and /etc/letsencrypt"
mkdir -p "/etc/systemd/system/php${PHP_VERSION}-fpm.service.d"
cat > "/etc/systemd/system/php${PHP_VERSION}-fpm.service.d/nexpanel.conf" <<FPMOV
[Service]
ReadWritePaths=/etc/nginx /etc/letsencrypt
FPMOV
systemctl daemon-reload

# ---- 7. nginx vhost for the panel --------------------------------------
log "Configuring Nginx site for the panel"
cat > /etc/nginx/sites-available/nexpanel <<NGINX
server {
    # default_server so the panel always answers unmatched hosts (e.g. the
    # server IP) even after users/AI add other vhosts.
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${SERVER_NAME};

    root ${APP_DIR}/public;
    index index.php;

    charset utf-8;
    client_max_body_size 64M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/nexpanel /etc/nginx/sites-enabled/nexpanel
rm -f /etc/nginx/sites-enabled/default

# ---- 8. start everything ------------------------------------------------
log "Enabling and starting services"
systemctl enable --now "php${PHP_VERSION}-fpm" nginx mysql cron >/dev/null 2>&1 || true
systemctl restart "php${PHP_VERSION}-fpm"   # apply the ReadWritePaths drop-in
nginx -t && systemctl reload nginx

IP="$(hostname -I | awk '{print $1}')"
cat <<DONE

============================================================
  ✅  NexPanel is installed and running.

  URL:      http://${SERVER_NAME/_/$IP}/
  Login:    admin@nexpanel.local
  Password: password   (change it after first login!)

  App dir:  ${APP_DIR}
  Runs as:  ${APP_USER} (php-fpm) behind Nginx

  Next steps:
   • Point a domain at this server, then set SERVER_NAME and
     issue SSL from the panel (SSL Certificates page).
   • Add your AI provider key in Settings → AI.
   • Configure alerts in the Notifications page.
============================================================
DONE
