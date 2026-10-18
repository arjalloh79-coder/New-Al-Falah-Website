# Contributing

## Branching

| Branch | Purpose |
|---|---|
| `main` | Production. Always deployable. Changes arrive only through pull requests. |
| `feature/<name>` | New features or content changes |
| `fix/<name>` | Bug fixes |
| `chore/<name>` | Tooling, docs, dependencies |

## Workflow

1. `git checkout main && git pull`
2. `git checkout -b feature/short-description`
3. Make changes, commit with a clear message (e.g. `Fix newsletter export CSV header`)
4. `git push -u origin feature/short-description`
5. Open a pull request into `main` and fill in the template
6. Wait for CI to pass, review the diff, **squash and merge**
7. Delete the branch, then deploy following [DEPLOY.md](DEPLOY.md)

## Rules that prevent accidents

- **Never commit secrets**: `.env`, API keys, passwords, database dumps. They are git-ignored; keep it that way.
- **Never commit uploads or logs**: everything under `storage/` except the `.gitignore` placeholders.
- **One purpose per pull request.**
- **Do not edit files on the server.** Change them here and deploy.
- **Do not point Hostinger at any branch except `main`.**

## Recommended GitHub settings (Settings → Branches → add rule for `main`)

- Require a pull request before merging
- Require status checks to pass (select **CI / checks**)
- Block force pushes and deletion
