#!/bin/bash
# ============================================================
# NexPanel — First-time Setup (WSL / Ubuntu 22.04+)
#
# Run from inside this project folder:
#   cd /mnt/c/Users/User/Desktop/Project\ Server\ Base/NexPanel_First
#   bash setup.sh
#
# Installs PHP + JS dependencies, prepares the environment,
# builds front-end assets, and seeds the database.
# ============================================================
set -e

cd "$(dirname "$0")"

echo "==> [1/6] Installing PHP dependencies (composer)"
composer install

echo "==> [2/6] Installing JS dependencies (npm)"
npm install

echo "==> [3/6] Preparing environment"
if [ ! -f .env ]; then
    cp .env.example .env
    echo "    .env created from .env.example"
fi
php artisan key:generate

echo "==> [4/6] Preparing SQLite database"
touch database/database.sqlite

echo "==> [5/6] Running migrations + seeding admin user"
php artisan migrate:fresh --seed --force

echo "==> [6/6] Building front-end assets + storage link"
npm run build
php artisan storage:link || true

cat <<'DONE'

============================================================
  NexPanel is ready.

  Start the server:
      php artisan serve

  Open:
      http://127.0.0.1:8000

  Default login:
      Email:    admin@nexpanel.local
      Password: password
============================================================
DONE
