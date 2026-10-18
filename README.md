# Al-Falah Marketing — Website

Official static website for **[al-falahmarketing.com](https://al-falahmarketing.com)**, hosted on Hostinger.

> **`main` is production.** Everything on `main` is what gets deployed. Change the site through pull requests only, never by editing files on the server.

## What's in this repo

```
index.html, about.html, services.html, portfolio.html, blog.html,
gallery.html, contact.html, institutions*.html, results.html,
privacy.html, terms.html, english-app.html, 404.html    Pages
blog/                       Blog posts (7)
services/                   Service pages (6)
assets/images/              Site images (portfolio/ holds client work)
shared.js, ai-assistant.js, lang-persist.js, theme-toggle.js     Scripts
theme-light.css, sticky-header.css                                Styles
api/                        PHP endpoints: contact, newsletter, AI assistant
robots.txt, sitemap.xml     SEO
.htaccess                   Apache rules (HTTPS, 404, blocked paths, caching)
scripts/check_site.py       Pre-merge checks (also run by CI)
.github/                    CI workflow and pull request template
```

The site is plain HTML/CSS/JS plus a few PHP endpoints. There is no build step.

## Secrets (important)

Passwords and API keys are **never** stored in Git.

- Server-only settings go in `api/config.local.php` on the server. It is git-ignored and blocked from the web by `.htaccess`.
- Copy `api/config.local.example.php` to `api/config.local.php` and fill it in on the server.
- No admin account is created unless you set `ADMIN_PASS` there.

## Working on the site

1. `git checkout main && git pull`
2. `git checkout -b feature/short-description`
3. Make changes, then run `python3 scripts/check_site.py`
4. Push and open a pull request into `main`. CI must pass.
5. Squash and merge, then deploy as described in [DEPLOY.md](DEPLOY.md)

More detail in [CONTRIBUTING.md](CONTRIBUTING.md).

## Known gaps

Two videos used by `gallery.html` are not in the repo yet:
`assets/videos/brand-story.mp4` and `assets/videos/design-collaboration.mp4`.
Upload them to those paths, then remove their lines from `scripts/known-missing.txt`.

## Legacy repositories (do not deploy from these)

| Repository | Status |
|---|---|
| `Al-Falah-Marketing` | Old monorepo. Its `add-deploy-sh-to-repo` branch overwrote the live site once. Do not connect to Hostinger. |
| `alfalahmarketing-laravel`, `Al-Falah-Website` | Old Laravel site. Superseded. |
| `Al-Falah-Live-Site-Backup` | Sep 18, 2026 snapshot. Backup only. |

## License

MIT, see [LICENSE](LICENSE).
