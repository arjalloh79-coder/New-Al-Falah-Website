# Al-Falah Marketing — Official Website

Production website for **[al-falahmarketing.com](https://al-falahmarketing.com)** — a Laravel application hosted on Hostinger.

> **This repository is the single source of truth for the website code.**
> `main` is production. Never edit files directly on the server.

## Stack

- **Laravel 13** (PHP 8.4+), Blade views, MySQL
- Public pages: home, services, portfolio, blog, contact, newsletter
- Admin area (`/admin/*`): blog, portfolio, newsletter, content queue, settings
- Static assets in `public/assets/` (and mirrored in `assets/` — the live document root is the project folder, see [DEPLOY.md](DEPLOY.md))
- No front-end build step is required (views do not use `@vite`)

## Repository layout

```
app/            Controllers, models, middleware, console commands
bootstrap/      Framework bootstrap
config/         App configuration (reads from .env)
database/       Migrations, seeders, factories
lang/           Translations (EN / FR)
public/         Public assets (css, js, images, fonts)
assets/         Root-level copy of public assets used by the live document root
resources/      Blade views
routes/         web.php
storage/        Runtime files (logs, cache, uploads) — contents are git-ignored
tests/          PHPUnit tests
deploy.sh       Server-side deploy script (pull, migrate, clear caches)
.github/        CI workflow and pull request template
```

## Local setup

```bash
git clone https://github.com/arjalloh79-coder/New-Al-Falah-Website.git
cd New-Al-Falah-Website
composer install
cp .env.example .env        # then fill in values — never commit .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Requires PHP 8.4.1+ and Composer.

## Working on the site

Short version (full rules in [CONTRIBUTING.md](CONTRIBUTING.md)):

1. Branch from `main`: `git checkout -b feature/short-description`
2. Commit small, clear changes
3. Open a pull request into `main` — CI must pass
4. Merge (squash), then deploy using [DEPLOY.md](DEPLOY.md)

## Deploying

See **[DEPLOY.md](DEPLOY.md)**. Always deploy from `main` only.

## Legacy repositories (do not deploy from these)

| Repository | Status |
|---|---|
| `alfalahmarketing-laravel` | Old standalone Laravel copy (Oct 8, 2026). Superseded. |
| `Al-Falah-Website` (private) | Auto-generated mirror of the Marketing repo's `backend/live-site-extension`. Superseded. |
| `Al-Falah-Live-Site-Backup` (private) | Sep 18, 2026 snapshot of the live site. Keep as a backup only. |
| `Al-Falah-Marketing` | Earlier monorepo (docs, plans, backend experiments). Not a deploy source. |

## License

MIT — see [LICENSE](LICENSE).
