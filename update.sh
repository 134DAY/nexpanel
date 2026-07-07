#!/usr/bin/env bash
# ============================================================
#  NexPanel — Update to the latest version from GitHub
#
#  Run on the server:
#     cd /var/www/nexpanel && sudo bash update.sh
#
#  Pulls the latest code, updates dependencies, runs migrations,
#  rebuilds caches, and reloads services. Safe to re-run.
# ============================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/nexpanel}"
APP_USER="${APP_USER:-www-data}"

log() { printf '\n\033[1;36m==>\033[0m %s\n' "$1"; }
die() { printf '\033[1;31m[error]\033[0m %s\n' "$1" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "Please run as root (sudo bash update.sh)."
cd "$APP_DIR" || die "App not found at $APP_DIR (set APP_DIR=...)."

# Run git/artisan/composer AS the repo owner so file ownership stays correct
# and git doesn't complain about 'dubious ownership'.
asuser() { sudo -u "$APP_USER" "$@"; }

BEFORE="$(asuser git rev-parse --short HEAD 2>/dev/null || echo unknown)"

log "Pulling latest code"
asuser git fetch --prune origin
asuser git reset --hard origin/main   # discard local drift; .env & *.sqlite are gitignored

log "Updating PHP dependencies"
asuser env COMPOSER_HOME="$APP_DIR/storage/.composer" \
    composer install --no-dev --optimize-autoloader --no-interaction

log "Running database migrations"
asuser php artisan migrate --force

log "Rebuilding caches"
asuser php artisan config:cache
asuser php artisan route:cache
asuser php artisan view:cache

log "Reloading services"
FPM="$(systemctl list-units --type=service --no-legend | grep -oE 'php[0-9.]+-fpm' | head -1)"
systemctl reload "$FPM" 2>/dev/null || systemctl restart "$FPM" 2>/dev/null || true
nginx -t >/dev/null 2>&1 && systemctl reload nginx 2>/dev/null || true

AFTER="$(asuser git rev-parse --short HEAD)"
echo ""
echo "============================================================"
echo "  ✅ NexPanel updated:  ${BEFORE}  →  ${AFTER}"
echo "============================================================"
