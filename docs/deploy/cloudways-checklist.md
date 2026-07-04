# Cloudways Deployment — Readiness Checklist

Monument Independent Research Desk. Laravel 13 + Filament v5, PHP 8.4, Spatie permissions (teams).
Dev runs SQLite + Herd; **production targets MySQL on Cloudways**. Work top to bottom; the
prod-parity and queue-worker items are the ones most likely to bite.

Legend: `[ ]` to do · ⚠️ easy to get wrong · 🔒 security.

---

## 0. Decisions to make first
- [ ] **Data:** re-import fresh from TRACER on prod (cleaner, recommended) **or** migrate the dev
      dataset (~130k finance rows + built networks) over? Re-import avoids SQLite→MySQL data quirks.
- [ ] **Owner account:** the dev seed creates `owner@monumentindependent.com` with a known password.
      🔒 Do NOT ship that. Plan to create the real owner with fresh credentials and remove/rotate the seeded one.
- [ ] **Domain:** custom domain now, or Cloudways staging URL first?

## 1. Pre-deploy, done locally
- [ ] ⚠️ **Prove MySQL parity before deploying.** Point a local DB at MySQL (Herd supports it), run
      `php artisan migrate:fresh --seed`, then run the full test suite and click through imports/graph.
      This catches SQLite-only assumptions (we've hit query-binding + locking quirks before) *before* prod.
- [ ] `php artisan test` green (currently 74 passing).
- [ ] Confirm `public/js/cytoscape.min.js` is committed (graph depends on it; there is no Node build step).
- [ ] `.env.example` updated with every key prod needs (see §3) — no secrets in it.
- [ ] Commit everything; deploy is git-based.

## 2. Cloudways server + app
- [ ] Provision server; create a Laravel app. Set **PHP 8.4**.
- [ ] Create a **MySQL** database; note credentials.
- [ ] ⚠️ Set the app **webroot to `public/`** (Cloudways default is the app root).
- [ ] Set up **git deployment** (Cloudways Git, or pull via SSH).
- [ ] SSH access confirmed (you'll run artisan + manage the worker here).

## 3. Production `.env`
- [ ] `APP_ENV=production`, `APP_DEBUG=false` 🔒
- [ ] `APP_KEY` — generate once (`php artisan key:generate`) and keep stable. ⚠️ Changing it later
      breaks the encrypted **2FA secrets** (mandatory 2FA) and existing encrypted columns.
- [ ] `APP_URL=https://<domain>` ⚠️ — the "photos not saving" bug was an APP_URL mismatch; get this right.
- [ ] `DB_CONNECTION=mysql` + host/port/db/user/pass.
- [ ] `QUEUE_CONNECTION=database` (matches how imports are dispatched).
- [ ] `SESSION_DRIVER`, `CACHE_STORE` — `database` is fine (no Redis assumed).
- [ ] `FILESYSTEM_DISK=local`.
- [ ] `MAIL_*` — configure real SMTP (password resets / notifications).
- [ ] Run `php artisan migrate --force` after DB env is set.

## 4. Database + seed
- [ ] `php artisan migrate --force`
- [ ] Seed roles/permissions (RolesAndPermissions seeder) — required for the panel to authorize.
- [ ] 🔒 Create the real **owner** user with fresh credentials; enroll 2FA on first login.
- [ ] 🔒 Remove or rotate the dev-seeded `owner@monumentindependent.com`.
- [ ] Confirm each org has its `settings.finance_filter` / `settings.finance_network` if you rely on
      the weekly refresh (they're set via the import dialog).

## 5. Queue worker — REQUIRED (the deferred fix)
Imports + the post-import donor-network build run on the queued `ImportTracerData` job (`timeout=1800s`).
Without a running worker, imports sit at `status=pending` forever (the in-app banner warns, but prod
should just work).
- [ ] Set up a **persistent worker**: `php artisan queue:work --tries=3 --timeout=1800 --sleep=3`
      via Supervisor (preferred) — or, simpler on Cloudways, a **cron every minute** running
      `php artisan queue:work --stop-when-empty --max-time=55`.
- [ ] Add `php artisan queue:restart` to the **deploy hook** so workers pick up new code. ⚠️
- [ ] Verify: queue an import, confirm it moves pending → completed on its own.

## 6. Scheduler (weekly TRACER refresh)
- [ ] Add a Cloudways **cron every minute**: `* * * * * php /path/to/artisan schedule:run`
- [ ] ⚠️ The scheduled task currently refreshes **contributions only**
      (`finance:import-tracer --all --type=contributions`, `routes/console.php`). If you want
      **expenditures/loans** auto-refreshed too, add matching `--type=expenditures` / `--type=loans`
      scheduled entries. Otherwise refresh those manually.

## 7. Storage / files
- [ ] `php artisan storage:link` (public disk → `public/storage`).
- [ ] 🔒 Confirm **documents stay on the private `local` disk** and are NOT web-reachable
      (evidence/source material must never have a public URL).
- [ ] Confirm **entity photos** (public disk) display — this is the APP_URL-sensitive path.
- [ ] Ensure `storage/` and `bootstrap/cache/` are writable.

## 8. Assets + caches
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan filament:assets` (publish Filament's precompiled assets)
- [ ] `php artisan optimize` (config/route/view cache) — and `php artisan icons:cache` if used.
- [ ] Re-run `php artisan optimize:clear && optimize` on every deploy.

## 9. Security hardening 🔒
- [ ] `APP_DEBUG=false`, generic error pages.
- [ ] Force **HTTPS**; ⚠️ configure **trusted proxies** (Cloudways runs behind a load balancer, so
      Laravel must trust `X-Forwarded-*` or HTTPS URL generation + secure cookies break). Add trusted
      proxies in `bootstrap/app.php` middleware config.
- [ ] Confirm **mandatory 2FA** enrollment works end-to-end on the live domain.
- [ ] Verify **multi-tenant isolation** with two orgs (no cross-tenant leakage).
- [ ] `.env` not web-accessible; DB not publicly exposed.
- [ ] Enable Cloudways **automated backups** for DB **and** the private documents disk.

## 10. Post-deploy smoke test
- [ ] Log in + complete 2FA on the real domain.
- [ ] Create an entity; upload a photo → it displays (APP_URL check).
- [ ] Run a small **TRACER import** (e.g. Loans, El Paso, current year) → worker processes it →
      the "imports waiting" banner does **not** appear.
- [ ] Contributions / Expenditures / Loans tabs each show their own data.
- [ ] Relationship graph renders (Cytoscape asset loads); saved views work.
- [ ] Finance Explorer populated.
- [ ] Build a committee donor network; confirm edges appear.

## 11. Deploy automation (repeatable)
Deploy hook / script:
```
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan filament:assets
php artisan queue:restart
```
- [ ] Wire this into the Cloudways deploy pipeline.
- [ ] Document a rollback (previous release + `migrate:rollback` caution on data migrations).

---

## Quick-reference: prod-parity risks specific to this app
- SQLite→MySQL: JSON columns (`source_extra`, `settings`, `filter`), `havingRaw`/aggregate binding,
  case-sensitivity of `LOWER()` matches, and concurrent-write locking all behave differently. Test on MySQL first (§1).
- 2FA secrets are encrypted with `APP_KEY` — keep it stable across deploys.
- Cytoscape is a vendored static asset, not an npm build — just needs to be committed + served.
