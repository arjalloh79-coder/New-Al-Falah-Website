# Contributing

## Branches

| Branch | Purpose |
|---|---|
| `main` | Production. Always deployable. Changes arrive only through pull requests. |
| `feature/<name>` | New pages, sections or content |
| `fix/<name>` | Bug fixes (broken links, typos, layout) |
| `chore/<name>` | Docs, tooling, dependencies |

## Workflow

1. `git checkout main && git pull`
2. `git checkout -b feature/short-description`
3. Make changes and run `python3 scripts/check_site.py`
4. Commit with a clear message, for example `Fix blog image paths`
5. `git push -u origin feature/short-description`
6. Open a pull request into `main` and fill in the template
7. When CI is green, **squash and merge**, delete the branch, then deploy per [DEPLOY.md](DEPLOY.md)

## Rules that prevent accidents

- **No secrets in Git:** no passwords, API keys, `.env`, or `api/config.local.php`.
- **No archives or databases in Git:** no `.zip`, `.sql`, `.db`. Put big files in Google Drive.
- **Links must resolve.** Use root-absolute paths such as `/assets/images/logo.jpg` so a page works from any folder.
- **One purpose per pull request.**
- **Never edit files directly on the server.**
- **Never point Hostinger at any branch except `main`.**

## Recommended GitHub settings

Settings → Branches → add a rule for `main`:

- Require a pull request before merging
- Require the **CI / checks** status to pass
- Block force pushes and branch deletion
