# Deploying to Hostinger

Live at **https://geethanworks.in/** — this is the final domain and deploy
path (superseding the earlier temporary `lightskyblue-snail-890159.hostingersite.com`
preview URL, which is no longer used). Document root:

```
/home/u761085554/domains/geethanworks.in/public_html
```

`.github/workflows/deploy.yml` builds the app on GitHub's runner (Composer +
npm build) and `rsync`s it straight to Hostinger over SSH on every push to
`main` (or via **Actions → Deploy to Hostinger → Run workflow** for a manual
run). `.env` and the *contents* of `storage/` are excluded from the sync
and never overwritten, so logs/sessions/uploads persist across deploys —
but every deploy does `mkdir -p` the handful of `storage/framework/...`
subdirectories Laravel needs just to boot (they're empty otherwise, so
there's nothing to preserve there; without this, a brand-new deploy target
has no `storage/framework/views` at all and every page, not just
`/install`, 500s before your code ever runs).

## 1. GitHub Actions secrets — done

Repo → **Settings → Secrets and variables → Actions → New repository
secret**. All 5 are set. For reference, what each one holds:

| Secret name | Value | Where it comes from |
|---|---|---|
| `HOSTINGER_SSH_HOST` | your server hostname/IP | hPanel → **Advanced → SSH Access** |
| `HOSTINGER_SSH_USERNAME` | e.g. `u761085554` | same page |
| `HOSTINGER_SSH_PASSWORD` | your SSH password | your hosting/SSH password |
| `HOSTINGER_SSH_PORT` | e.g. `65002` on shared hosting, `22` on VPS | same page |
| `HOSTINGER_DEPLOY_PATH` | `/home/u761085554/domains/geethanworks.in/public_html` | you've already given me this |

SSH access has to be enabled for your plan first: hPanel → **Advanced → SSH
Access → Enable**.

## 2. Deploy layout: whole app in `public_html`

You chose to deploy the entire app directly into `public_html` rather than
the split layout, so the deploy path *is* the web root:

```
/home/u761085554/domains/geethanworks.in/public_html
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

## 3. First-time setup: the web installer

Once the code has been deployed at least once (Part A of the deploy —
either a push to `main` or a manual workflow run), there's a web-based
setup wizard at **`/install`** that replaces all the manual SSH `.env`
editing this section used to describe. It handles: database connection
(with a live test before saving), writing `.env`, generating `APP_KEY`,
running migrations, seeding departments/roles/permissions, creating your
admin account, and `storage:link` + config caching — no SSH required for
any of it.

It's gated because it runs before the database (or possibly `.env`
itself) exists, so it can't rely on sessions or CSRF the way the rest of
the app does. Instead:

1. SSH in once, just to read the access token:
   ```bash
   cat /home/u761085554/domains/geethanworks.in/public_html/storage/install_token.txt
   ```
2. Visit `https://geethanworks.in/install?token=<that value>`
   and follow the 4 steps (Requirements → Database → Migrate → Admin Account).
   Use `https://geethanworks.in` as the App URL on the Database step.
3. It locks itself when finished — writes `storage/installed` and deletes
   the token file, so `/install` 403s on every request after that. To run
   it again (e.g. a fresh reinstall), delete `storage/installed` over SSH.

After install, every `git push` to `main` (or a manual workflow run)
re-syncs the code and re-runs migrations/cache automatically.

## Notes

- Uses password auth per your request. SSH keys are more secure for CI and
  worth switching to later — say the word and I'll swap the workflow to use
  a `HOSTINGER_SSH_KEY` secret instead of the password, no other changes
  needed.
- `rsync` and PHP need to already be available over SSH on the server (true
  for virtually all Hostinger plans).
- `composer.json` pins `"platform": {"php": "8.2"}` under `config` so
  `composer.lock` always resolves package versions installable on PHP
  8.2+, regardless of what PHP version generates the lock file locally.
  The deploy workflow's `Setup PHP` step matches at `8.2`.
- **This server is CloudLinux-based ("PHP Selector").** `/usr/bin/php`
  is a symlink through `/etc/cl.selector/php-cli`, and on this account it
  resolves to PHP 7.2.34 — too old for Laravel 12 — even though newer
  versions are installed at `/opt/alt/php82/`, `/opt/alt/php83/`,
  `/opt/alt/php84/`, `/opt/alt/php85/` (found by SSHing in and running
  `ls /opt/alt`). Rather than depend on hPanel's CLI PHP selector, the
  deploy workflow's post-deploy step now resolves its own `$PHP_BIN` at
  runtime — preferring `/opt/alt/php84/usr/bin/php`, then 8.3, then 8.2,
  falling back to plain `php` only if none of those exist — and calls
  every `artisan` command through that. If PHP versions ever change on
  the server, no workflow edit should be needed; if they do, that
  fallback chain in the `Run post-deploy commands on server` step is
  where to adjust it.
- The workflow currently triggers on push to `main`. This repo's only
  branch so far is `claude/orbitx-erp-work-order-82ptcp` — once you create
  `main` (or want deploys on a different branch), tell me and I'll update
  the trigger.
