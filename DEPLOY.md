# Deploying NexPanel to a real server

NexPanel is meant to run **on the Linux server it manages**. Target OS:
Ubuntu 22.04 / 24.04 (Debian works too).

## Option A — one-line installer (recommended)

Copy this project to the server (or host `install.sh` somewhere), then:

```bash
sudo bash install.sh
```

The installer:

1. Installs PHP-FPM, Nginx, MySQL, certbot, cron, supervisor, Composer.
2. Deploys the app to `/var/www/nexpanel`, installs dependencies, builds assets.
3. Creates `.env`, generates the app key, migrates + seeds the database.
4. Fixes permissions for the `www-data` web user.
5. **Grants `www-data` passwordless sudo** for `systemctl`, `nginx`, `certbot`
   (`/etc/sudoers.d/nexpanel`) — this is what makes Service Control, Website,
   and SSL management actually work.
6. Configures an Nginx vhost and starts everything.

Override defaults with env vars:

```bash
sudo APP_DIR=/srv/nexpanel PHP_VERSION=8.2 SERVER_NAME=panel.example.com bash install.sh
```

After it finishes, open the URL it prints and log in with
`admin@nexpanel.local` / `password` — **change the password immediately**.

## Option B — manual

```bash
sudo apt install php8.3-{fpm,cli,mysql,sqlite3,mbstring,xml,curl,zip,gd,bcmath,intl} \
                 nginx mysql-server certbot python3-certbot-nginx composer
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed --force
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache database
```

Then add the sudoers rule and an Nginx vhost (see `install.sh` steps 6–7 for the
exact content), and reload Nginx.

## Why passwordless sudo?

The panel's PHP process runs as `www-data`. Managing system services requires
root, so the installer authorises **only** these commands without a password:

```
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl, /bin/systemctl, /usr/sbin/nginx, /usr/bin/certbot
```

Without this, the Service Control / SSL pages will report
*"Passwordless sudo is not configured"* instead of acting. The panel adds
`sudo -n` (non-interactive) so a missing rule fails fast — it never hangs.

## Production notes

- **Do not** use `php artisan serve` in production — it is single-threaded and
  will feel laggy under concurrent requests. The installer runs the panel behind
  Nginx + PHP-FPM (multi-worker), which is fast.
- Configure the MySQL admin connection in `.env` (`DB_ADMIN_*`) so the Database
  Manager can list/manage databases.
- Set `APP_DEBUG=false` and a strong DB/user policy before exposing publicly.
- Issue SSL for the panel's own domain from the SSL Certificates page once DNS
  points at the server.
