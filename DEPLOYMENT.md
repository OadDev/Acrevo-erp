# Deploying to Hostinger (geethanworks.in)

`.github/workflows/deploy.yml` builds the app on GitHub's runner (Composer +
npm build) and `rsync`s it straight to Hostinger over SSH on every push to
`main` (or via **Actions → Deploy to Hostinger → Run workflow** for a manual
run). It never touches `.env` or `storage/` on the server, so those persist
across deploys.

## 1. Add the 5 GitHub Actions secrets

Repo → **Settings → Secrets and variables → Actions → New repository
secret**. I can't create these for you — they're encrypted at rest and the
GitHub tools available to me don't include repo-secret management, and an
SSH password shouldn't be pasted into a chat anyway. Add exactly these 5,
named exactly this way (the workflow already references them):

| Secret name | What it is | Where to find it in Hostinger hPanel |
|---|---|---|
| `HOSTINGER_SSH_HOST` | SSH hostname or IP | hPanel → **Advanced → SSH Access** — shown as "Hostname" or "Server IP" |
| `HOSTINGER_SSH_USERNAME` | SSH username | Same page — looks like `u123456789` |
| `HOSTINGER_SSH_PASSWORD` | SSH password | Your hosting account password, or a separate SSH password if you set one under SSH Access |
| `HOSTINGER_SSH_PORT` | SSH port | Same page — Hostinger shared hosting is usually `65002`; VPS plans are usually `22` |
| `HOSTINGER_DEPLOY_PATH` | Absolute path on the server the app deploys into | See step 2 |

SSH access has to be enabled for your plan first: hPanel → **Advanced → SSH
Access → Enable**.

## 2. Decide the deploy path (document root)

Laravel's web root is the `public/` folder, not the project root — Hostinger
serves `public_html` directly, so pick one of these:

**Option A — you can change the domain's document root** (VPS, or shared
plans with "Website → Document Root" in hPanel): deploy the whole app
somewhere like

```
/home/u123456789/domains/geethanworks.in/acrevo-erp
```

and point the domain's document root at
`.../acrevo-erp/public`. Set `HOSTINGER_DEPLOY_PATH` to the app folder
(`.../acrevo-erp`, not `.../acrevo-erp/public`).

**Option B — document root is locked to `public_html`** (typical shared
hosting): deploy the app one level above `public_html`, e.g.

```
/home/u123456789/domains/geethanworks.in/acrevo-erp
```

then make `public_html` serve `acrevo-erp/public`. If your plan allows
symlinks: `ln -s /home/u.../acrevo-erp/public /home/u.../domains/geethanworks.in/public_html`.
If it doesn't, copy `public/`'s contents into `public_html` once and edit
`public_html/index.php` so its two `require` lines point at
`../acrevo-erp/vendor/autoload.php` and `../acrevo-erp/bootstrap/app.php`
(the file otherwise deploys are the same). Either way,
`HOSTINGER_DEPLOY_PATH` is still the app folder, not `public_html`.

## 3. One-time server setup (before the first deploy)

SSH in once by hand and create what the workflow deliberately never touches:

```bash
mkdir -p acrevo-erp/storage/framework/{cache,sessions,views}
mkdir -p acrevo-erp/storage/{logs,app/public}
mkdir -p acrevo-erp/bootstrap/cache
cd acrevo-erp
cp .env.example .env   # then edit it — see below
```

Edit `.env` with production values:

```
APP_NAME="Acrevo ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://geethanworks.in

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=<create this in hPanel → Databases → MySQL Databases>
DB_USERNAME=<same>
DB_PASSWORD=<same>
```

Then, still over SSH:

```bash
php artisan key:generate
php artisan storage:link
```

After that, every `git push` to `main` (or a manual workflow run) re-syncs
the code and re-runs migrations/cache — this bootstrap step only happens
once.

## Notes

- Uses password auth per your request. SSH keys are more secure for CI and
  worth switching to later — say the word and I'll swap the workflow to use
  a `HOSTINGER_SSH_KEY` secret instead of the password, no other changes
  needed.
- `rsync` and PHP need to already be available over SSH on the server (true
  for virtually all Hostinger plans).
- The workflow currently triggers on push to `main`. This repo's only
  branch so far is `claude/orbitx-erp-work-order-82ptcp` — once you create
  `main` (or want deploys on a different branch), tell me and I'll update
  the trigger.
