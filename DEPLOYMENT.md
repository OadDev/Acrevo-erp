# Deploying to Hostinger

Live for now at **https://lightskyblue-snail-890159.hostingersite.com/** —
Hostinger's temporary preview URL, ahead of `geethanworks.in`'s DNS being
pointed at this hosting. It has its **own** document root, separate from
`geethanworks.in`'s — not a shared folder as originally assumed:

```
/home/u761085554/domains/lightskyblue-snail-890159.hostingersite.com/public_html
```

When you cut over to `geethanworks.in`, check whether that domain's
document root is this same folder or a different one in hPanel — if it's
different, `HOSTINGER_DEPLOY_PATH` needs to change too, not just `APP_URL`.

`.github/workflows/deploy.yml` builds the app on GitHub's runner (Composer +
npm build) and `rsync`s it straight to Hostinger over SSH on every push to
`main` (or via **Actions → Deploy to Hostinger → Run workflow** for a manual
run). It never touches `.env` or `storage/` on the server, so those persist
across deploys.

## 1. GitHub Actions secrets — done

Repo → **Settings → Secrets and variables → Actions → New repository
secret**. All 5 are set. For reference, what each one holds:

| Secret name | Value | Where it comes from |
|---|---|---|
| `HOSTINGER_SSH_HOST` | your server hostname/IP | hPanel → **Advanced → SSH Access** |
| `HOSTINGER_SSH_USERNAME` | e.g. `u761085554` | same page |
| `HOSTINGER_SSH_PASSWORD` | your SSH password | your hosting/SSH password |
| `HOSTINGER_SSH_PORT` | e.g. `65002` on shared hosting, `22` on VPS | same page |
| `HOSTINGER_DEPLOY_PATH` | `/home/u761085554/domains/lightskyblue-snail-890159.hostingersite.com/public_html` | you've already given me this |

SSH access has to be enabled for your plan first: hPanel → **Advanced → SSH
Access → Enable**.

## 2. Deploy layout: whole app in `public_html`

You chose to deploy the entire app directly into `public_html` rather than
the split layout, so the deploy path *is* the web root:

```
/home/u761085554/domains/lightskyblue-snail-890159.hostingersite.com/public_html
```

Laravel's actual entry point is `public/index.php`, not the project root —
so a root-level **`.htaccess`** (already added to the repo, syncs with
every deploy) transparently rewrites every request into `public/` before
Apache resolves it to a file. Visiting the site root serves
`public_html/public/index.php`; a request for `/vendor/autoload.php`
rewrites to `/public/vendor/autoload.php`, which doesn't exist, so Apache
404s it — `vendor/`, `app/`, `config/`, `.env`, etc. are never directly
web-accessible even though they physically sit in `public_html`. There's
also a `<FilesMatch>` deny rule on `.env`, `composer.json/.lock`,
`package.json`, and `artisan` as a second layer, in case the rewrite is
ever disabled.

This only works if `.htaccess` overrides are honored (`AllowOverride`
enabled) — the default on Hostinger shared hosting, since they build for
exactly this scenario. If the site ever 404s or shows a directory listing
after deploy, that's the first thing to check.

## 3. One-time server setup (before the first deploy)

SSH in once by hand and create what the workflow deliberately never touches:

```bash
cd /home/u761085554/domains/lightskyblue-snail-890159.hostingersite.com/public_html
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/{logs,app/public}
mkdir -p bootstrap/cache
cp .env.example .env   # then edit it — see below
```

Edit `.env` with production values:

```
APP_NAME="Acrevo ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lightskyblue-snail-890159.hostingersite.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=<create this in hPanel → Databases → MySQL Databases>
DB_USERNAME=<same>
DB_PASSWORD=<same>
```

When `geethanworks.in`'s DNS is pointed at this hosting and you're ready to
cut over, SSH in, change `APP_URL` to `https://geethanworks.in` in this
same `.env`, then run `php artisan config:cache` again — the deploy
workflow never edits `.env`, so this is a manual step whenever you're ready
for it, not something that happens automatically on the next push.

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
