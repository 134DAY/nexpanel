# NexPanel

**Lightweight AI-Powered Linux Server Management Panel**

Self-hosted panel that installs directly on a Linux server (Ubuntu/Debian) and
manages that same machine — websites, databases, SSL, files, cron, terminal —
with a multi-provider AI assistant that can analyze, advise, and (with
confirmation) execute server actions.

> This is the primary, organized source tree for NexPanel. The old build was a
> stack of one-off bash generator scripts (`NexPanel_Final/`); this folder is
> the real Laravel project, ready to edit and run.

---

## Tech Stack

| Layer     | Choice                                             |
|-----------|----------------------------------------------------|
| Backend   | Laravel 11 (PHP 8.2+)                               |
| Frontend  | Blade + Alpine.js + Tailwind CSS (Vite)            |
| Database  | SQLite                                              |
| Charts    | Chart.js                                            |
| AI        | Multi-provider (Claude / Gemini / OpenAI / Groq), BYOK |

---

## Deploying to a real server

For production on Ubuntu, use the one-line installer (Nginx + PHP-FPM + MySQL +
certbot, passwordless sudo for service control): **[DEPLOY.md](DEPLOY.md)**.

```bash
sudo bash install.sh
```

## Local Development (WSL / Ubuntu 22.04+)

```bash
cd /mnt/c/Users/User/Desktop/Project\ Server\ Base/NexPanel_First
bash setup.sh
php artisan serve
```

Then open <http://127.0.0.1:8000>.

**Default login**

- Email: `admin@nexpanel.local`
- Password: `password`

> `setup.sh` runs: `composer install`, `npm install`, `.env` + `key:generate`,
> creates the SQLite file, `migrate:fresh --seed`, and `npm run build`.

### Manual setup (if you prefer)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

---

## Features

**Phase 1 — Foundation**
- Dashboard with live system metrics (CPU, RAM, disk, uptime)
- Service Control (Nginx, MySQL, PHP-FPM, Redis, …)
- Light / Dark theme

**Phase 2 — AI Assistant**
- Multi-provider AI (Claude, Gemini, GPT, Groq), Bring-Your-Own-Key
- Action mapping: Analyze / Advise / Explain / Execute
- Safety guard with 4 danger levels — always confirms before running commands
- Activity logger (audit trail) + chat history with sessions

**Phase 3 — Server Management** (real implementations)
- Website / Nginx management — parses `sites-available`, create/enable/disable/delete vhost, `nginx -t` + reload
- Database manager (MySQL) — live PDO connection: list/create/drop databases & users, `mysqldump` backup
- SSL certificates — scans `/etc/letsencrypt/live` + `openssl`, issue/renew/delete via certbot
- File manager — real filesystem: browse, edit, upload, download, rename, delete, mkdir
- Cron scheduler — reads/writes the user crontab; add/pause/run-now/delete
- Web terminal — runs real commands in a shell with persistent `cd`

> Each Phase 3 module talks to the real system and **degrades gracefully**: if
> nginx / mysql / certbot isn't present, the page shows a clear "unavailable"
> notice instead of failing. Managing system services (nginx, /etc, certbot)
> requires the panel to run with sufficient privileges on the target server.

**Phase 4 — Notifications & Deployment** (done)
- Notifications — Discord, Telegram, generic webhook, and email channels with
  per-channel enable toggles and a "Send test" button; wired to server events
  (e.g. service actions). Fire-and-forget: a failing channel never breaks the app.
- One-line production installer (`install.sh`) — see [DEPLOY.md](DEPLOY.md).

---

## Project Layout

```
app/
├── Http/Controllers/     Dashboard, Service, Website, Database, SSL,
│                         FileManager, Cron, Terminal, AI, Settings, Profile
├── Models/               User, PanelSetting, AiSetting, ChatHistory, ActivityLog
└── Services/
    ├── ServerMetricsService.php    system metrics collection
    ├── ActivityLogger.php          audit trail
    └── AI/                          multi-provider AI layer (Strategy pattern)
        ├── AIServiceInterface.php   shared contract
        ├── AIServiceFactory.php     provider selector
        ├── ClaudeService.php  GeminiService.php  OpenAIService.php  GroqService.php
        ├── SystemPromptBuilder.php
        └── Actions/  ActionClassifier.php, SafetyGuard.php
resources/views/          Blade views per module + shared layouts/components
routes/web.php            application routes
database/
├── migrations/           users, cache, jobs, panel_settings, ai_settings,
│                         chat_histories, activity_logs
└── seeders/              DatabaseSeeder → seeds admin@nexpanel.local
```

---

## Notes

- **API keys are not bundled.** They were stored encrypted against the previous
  `APP_KEY`; enter your provider key again in **Settings → AI** after setup.
- `vendor/`, `node_modules/`, `public/build` regenerate via `composer install`
  / `npm install` / `npm run build` — not committed as source.
- Target OS is Ubuntu/Debian; develop under WSL on Windows.
- AI must confirm before executing any command — never runs destructive
  operations (db reset, drop table, `rm -rf`) without explicit approval.
