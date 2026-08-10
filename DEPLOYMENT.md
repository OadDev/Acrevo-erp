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
   cat /home/u761085554/domains/lightskyblue-snail-890159.hostingersite.com/public_html/storage/install_token.txt
   ```
2. Visit `https://lightskyblue-snail-890159.hostingersite.com/install?token=<that value>`
   and follow the 4 steps (Requirements → Database → Migrate → Admin Account).
3. It locks itself when finished — writes `storage/installed` and deletes
   the token file, so `/install` 403s on every request after that. To run
   it again (e.g. a fresh reinstall), delete `storage/installed` over SSH.

When `geethanworks.in`'s DNS is pointed at this hosting and you're ready to
cut over, SSH in, change `APP_URL` in `.env` to `https://geethanworks.in`,
then run `php artisan config:cache` — the deploy workflow never edits
`.env` after install, so this is a manual step whenever you're ready for
it, not something that happens automatically on the next push.

After install, every `git push` to `main` (or a manual workflow run)
re-syncs the code and re-runs migrations/cache automatically.

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
