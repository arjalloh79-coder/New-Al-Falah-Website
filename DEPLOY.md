# Deploying to Hostinger

**Rule 1: deploy only the `main` branch of this repository.**
**Rule 2: never connect Hostinger to an old repo or a feature branch.** (An old branch once replaced the live site with the wrong files.)

## One-time setup

1. **Back up first.** hPanel → Files → Backups → generate a full backup, and keep a copy in Google Drive.
2. **Remove the old connection.** hPanel → Websites → al-falahmarketing.com → **Advanced → Git** (or **Deployments**). Delete the link to `Al-Falah-Marketing`. Turn Auto-deployment off while you work.
3. **Connect this repo.**
   - Repository: `https://github.com/arjalloh79-coder/New-Al-Falah-Website`
   - Branch: **`main`**
   - Deploy directory: the site's document root (normally `public_html`)
4. **Deploy once manually**, then open the site in a private window and test:
   - Home page, About, Services, Blog, Gallery, Contact
   - Contact form and newsletter signup
   - A blog post (images must show)
5. When everything works, turn **Auto-deployment** back on.

## Files that live only on the server

| File or folder | Purpose |
|---|---|
| `api/config.local.php` | Passwords and API keys. Create it from `api/config.local.example.php`. |
| `data/` | SQLite database of contact messages and subscribers. Created automatically. |

These are git-ignored and blocked from the web. **Check after the first deploy that they were not deleted**, and back up `data/` regularly.

## Routine update

1. Merge a pull request into `main` (CI must be green).
2. Deploy from hPanel (or let auto-deployment run).
3. Test in a private window and hard refresh (Ctrl+Shift+R).

## Rollback

- In GitHub, open the last good commit on `main` and use **Revert** on the bad pull request, then deploy again.
- If the site is badly broken: hPanel → Files → Backups → restore the files backup from before the change.

## Security checklist

- [ ] The default admin password from the old setup is changed everywhere it was reused
- [ ] `api/config.local.php` exists on the server with real values, and is not in Git
- [ ] Visiting `/api/config.php`, `/data/` and `/DEPLOY.md` on the live site returns "Forbidden"
- [ ] HTTPS redirect enabled: uncomment the three lines in `.htaccess` once SSL is active

## Backups (monthly, and before big changes)

- hPanel → Files → Backups → generate a full backup
- Download `data/alfalah.db` from File Manager
- Store both in Google Drive, for example `Al-Falah Backups/YYYY-MM/`
