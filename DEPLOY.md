# Deploying to Hostinger

**Rule 1: only deploy the `main` branch of this repository.**
**Rule 2: never deploy a feature branch or an old repository.** (An old branch once overwrote the live site.)

## One-time setup (hPanel)

1. hPanel → **Websites → al-falahmarketing.com → Advanced → Git**
2. Repository: `https://github.com/arjalloh79-coder/New-Al-Falah-Website`
3. Branch: **`main`**
4. Install path: the folder that is the site's document root (normally `public_html`)
5. Leave **Auto-deployment** *off* until you have done one successful manual deploy and checked the site.

### Files that live only on the server (never in Git)

| Item | Why |
|---|---|
| `.env` | Database password, `APP_KEY`, mail settings. Copy from `.env.example` and fill in. |
| `vendor/` | Installed by Composer on the server (`composer install --no-dev`). |
| `storage/app/public/` | Uploaded blog and portfolio images. |
| `storage/logs/` | Runtime logs. |

Make sure a deploy does **not** delete these. If Hostinger's Git deploy replaces the whole folder, deploy into a clean folder first, copy these items across, then switch the domain over.

## Routine deploy

1. Merge your pull request into `main` (CI must be green).
2. hPanel → Git → **Deploy** (or let auto-deployment run).
3. In SSH, from the site folder:

   ```bash
   cd ~/domains/al-falahmarketing.com/public_html
   bash deploy.sh
   ```

   `deploy.sh` pulls `origin/main`, runs `php artisan migrate --force` and clears the caches.
4. If `composer.lock` changed: `composer install --no-dev --optimize-autoloader`
5. Open the site in a private window and test: home, blog, contact form, `/admin` login.

## Rollback

```bash
cd ~/domains/al-falahmarketing.com/public_html
git log --oneline -10                    # find the last good commit
git reset --hard <good-commit-sha>
php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan cache:clear
```

Then fix the problem on a branch and merge normally.

## Backups (do these before any big change)

- hPanel → **Files → Backups** → generate a full backup
- hPanel → **Databases → phpMyAdmin → Export** (SQL)
- Keep copies in Google Drive, e.g. `Al-Falah Backups/YYYY-MM/`

## PHP version

The site needs **PHP 8.4.1 or newer**. Select it in hPanel → Advanced → PHP Configuration. `deploy.sh` uses `/opt/alt/php84/usr/bin/php`.
