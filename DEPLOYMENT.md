# Deployment & Environment Readiness

Operational reference for standing up RSMS outside local dev. This is a living document — update it as staging/production reality is actually confirmed, not guessed at. Anything not yet verified against the real hosting target is marked **TO VERIFY ON HOSTINGER** rather than assumed.

Related: [PROJECT_STATUS.md](PROJECT_STATUS.md) for feature/build state, [UAT_ISSUE_TEMPLATE.md](UAT_ISSUE_TEMPLATE.md) for reporting issues found while testing an environment set up from this document.

> **Deployment direction changed (2026-08-18).** RSMS is no longer targeting shared hosting. The target architecture is now a Hostinger VPS running Ubuntu 24.04 LTS + Coolify, with RSMS packaged as a Docker image (§5 onward) and PostgreSQL as a separate Coolify-managed resource. §1–§4 below are kept as a historical record of the shared-hosting-era checklist — some of it (the database safety convention in §3, the backup-timing philosophy in §4) still applies unchanged; the parts specific to shared hosting (Composer-on-server, uploading `public/build`, Hostinger PHP-extension unknowns) are superseded by §5–§10.

---

## 1. Staging environment checklist (shared-hosting era — superseded by §5–§10)

A staging environment is a full, separate deploy of RSMS — its own database, its own secrets, never a copy of or connection to the dev database (`rahai_sms`) or a shared credential set.

| Variable | Staging value | Notes |
|---|---|---|
| `APP_ENV` | `staging` | Distinct from `local`/`production` so logs/config can branch on it if ever needed. |
| `APP_DEBUG` | `false` | **Required.** `true` leaks stack traces to any visitor. |
| `APP_URL` | the real staging URL | e.g. `https://staging.example.org` — TO VERIFY ON HOSTINGER (actual subdomain). |
| `APP_KEY` | a freshly generated key, unique to staging | Generate with `php artisan key:generate` **on staging itself** — never copy the dev key. |
| `DB_CONNECTION` | `pgsql` | Matches dev; confirm Hostinger actually offers this — see §2. |
| `DB_DATABASE` | `rahai_staging` (or equivalent, visibly distinct name) | Never `rahai_sms`. See §3's naming convention. |
| `DB_USERNAME` / `DB_PASSWORD` | staging-specific credentials | Never the dev password. |
| `SESSION_DRIVER` | `database` (already the project default) | No change needed from dev's setting. |
| `SESSION_SECURE_COOKIE` | `true` once staging is served over HTTPS | Not forced in `config/session.php` today — set explicitly via env once HTTPS is confirmed. |
| `ANTHROPIC_API_KEY` | a staging-scoped key, or blank | Blank is fine — AI degrades to "temporarily unavailable," nothing else depends on it. |
| `LOG_CHANNEL` / `LOG_STACK` | `daily` instead of `single` | The dev default (`single`) never rotates; recommend `daily` with a retention window before anything long-lived. |
| `BOOTSTRAP_ADMIN_EMAIL` / `BOOTSTRAP_ADMIN_PASSWORD` | set once, deliberately, per environment | Only used by `php artisan rsms:bootstrap-admin` (§9's safety convention applies) — never left set permanently in a real `.env` beyond the one bootstrap run if avoidable. |

**Standing up staging, in order:**

```bash
composer install --no-dev
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force        # configuration/reference data only -- see DatabaseSeeder's own docblock
php artisan rsms:bootstrap-admin   # requires BOOTSTRAP_ADMIN_EMAIL/PASSWORD to already be set
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`db:seed` is safe to run in staging exactly as-is — every seeder it calls (`RolesAndPermissionsSeeder`, `GradeSeeder`, `PositionSeeder`, `StaffCategorySeeder`, `AcademicYearSeeder`, `AcademicPeriodSeeder`, `EnglishProgrammeSeeder`, `LearningPhaseSeeder`) is idempotent configuration/reference data. It creates **no** Student/Staff/Guardian/Class fixture data and, as of Pre-UAT Hardening P1, **no** default admin account.

## 2. Hostinger verification checklist

**Nothing below is confirmed from this repository.** No Hostinger configuration, deployment script, or hosting reference exists anywhere in the codebase — this checklist exists so the unknowns are explicit, not silently assumed.

- [ ] **PHP version** — RSMS targets PHP 8.4. TO VERIFY ON HOSTINGER.
- [ ] **Required PHP extensions** — standard Laravel set (`pdo_pgsql` specifically, given Postgres below) plus `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`. TO VERIFY ON HOSTINGER.
- [ ] **PostgreSQL availability and version** — RSMS is developed and verified against PostgreSQL 17; some migrations use Postgres-specific partial unique indexes and CHECK constraints (documented in [PROJECT_STATUS.md](PROJECT_STATUS.md)'s Architectural Decisions). If Hostinger's plan only offers MySQL, this is a real compatibility question, not a config tweak. TO VERIFY ON HOSTINGER.
- [ ] **Database connection model** — is Postgres reachable via a local socket, or only from an allow-listed host? Affects `DB_HOST`/`DB_PORT`. TO VERIFY ON HOSTINGER.
- [ ] **SSH/terminal access** — needed to run `artisan` commands (migrations, seeding, bootstrap, cache commands) during deploy. TO VERIFY ON HOSTINGER.
- [ ] **Composer availability** — either on the server directly, or a build-then-upload deploy flow. TO VERIFY ON HOSTINGER.
- [ ] **Node/frontend build strategy** — Tailwind v4 build step needs to run somewhere (locally then upload `public/build`, or on the server). TO VERIFY ON HOSTINGER.
- [ ] **Cron availability** — nothing in RSMS currently requires a scheduler (`app/Console/Kernel`-style scheduled tasks were not found in this codebase), but Laravel's queue is configured to the `database` driver with nothing actually queued (confirmed by the Production Readiness review) — if that changes later, cron/a worker process becomes required. Not needed today. TO VERIFY ON HOSTINGER if that changes.
- [ ] **Backup features** — does Hostinger's plan include automated database backups, or is that entirely the application's responsibility? See §4. TO VERIFY ON HOSTINGER — do not assume platform-level backup exists.
- [ ] **SSL/domain/subdomain setup** — required before `SESSION_SECURE_COOKIE=true` makes sense. TO VERIFY ON HOSTINGER.
- [ ] **Storage/file permissions** — `storage/` and `bootstrap/cache/` need to be writable by the web server process. TO VERIFY ON HOSTINGER.

## 3. Database safety convention (permanent)

Carried forward from the F3.1 permanent safety rule, now extended across every environment RSMS runs in:

**Before any manual or destructive database operation, print and verify:**
1. `APP_ENV`
2. `DB_CONNECTION`
3. `DB_DATABASE`
4. Explicitly confirm `DB_DATABASE` is the environment you intend to touch.

Only then mutate. No exceptions for "it's probably fine."

**Visibly distinct database names, always:**

| Environment | Database name |
|---|---|
| Local dev | `rahai_sms` |
| Disposable verification | `rahai_verify` (created fresh, dropped after use) |
| UAT/staging | `rahai_staging` |
| Production | a clearly distinct name — never reuse `rahai_sms` or `rahai_staging` |

Never run destructive or manual verification work directly against staging or production. Use a disposable database for anything exploratory, exactly as this project already does for local verification.

## 4. Backup timing

Backup requirements scale with what's actually at stake in each stage — do not treat "backup" as one binary requirement.

- **Controlled UAT** (small cohort, synthetic-then-limited-real data): the staging database must be independently **recoverable by re-creation** (migrate + seed + bootstrap-admin from scratch) — it does not yet need a tested backup/restore procedure, because the data in it is intentionally limited in scope and not yet the school's system of record.
- **Rahai pilot** (real Classes, real students): backup + restore **must be tested** before the pilot begins. This is a hard requirement, not a nice-to-have, because pilot data is real and losing it has a real cost.
- **Production**: daily automated backup, an off-server copy, and a rehearsed restore procedure are all required before go-live. `.env`/secrets must be backed up separately from the database dump (a password manager or encrypted vault — never committed to version control).

**No backup tooling exists in this codebase today.** Nothing here should be read as claiming otherwise — this section documents the requirement and its timing, not a built solution.

---

## 5. Docker / Coolify architecture

Target topology:

```
Hostinger VPS → Ubuntu 24.04 LTS → Coolify → RSMS application container → separate PostgreSQL resource
```

**Runtime choice: Nginx + PHP-FPM in one container, under `supervisord`** (not FrankenPHP). FrankenPHP is genuinely allowed and would be materially simpler if adopted — but this decision was made without Docker CLI access in the working environment (see §6 verification notes below), and `php:8.4-fpm` / `nginx` are long-stable, unambiguous official image names that don't depend on knowing FrankenPHP's current exact tag. Nginx and PHP-FPM are two genuinely separate processes in one container, so `supervisord` is used to manage both — not adopted for its own sake.

- **PHP 8.4** (`php:8.4-cli-bookworm` for the Composer stage, `php:8.4-fpm-bookworm` for runtime) — this is the exact version RSMS has been developed and tested against throughout the project (`php -v` on the dev machine reports 8.4.24), and `composer.json`'s `"php": "^8.3"` constraint explicitly permits 8.4. Choosing 8.4 is the *lower*-risk choice here, not a gamble — 8.3 would be the untested alternative.
- **Required PHP extensions**, derived from `composer.lock`'s own `require` blocks (not guessed): `laravel/framework` needs `ctype`, `filter`, `hash`, `mbstring`, `openssl`, `session`, `tokenizer`; `phpoffice/phpspreadsheet` (underlying `maatwebsite/excel`) needs `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `libxml`, `mbstring`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`, plus `pdo_pgsql` for the PostgreSQL driver. `ctype`/`filter`/`hash`/`openssl`/`session`/`tokenizer`/`iconv`/`zlib`/`json` ship compiled into the base `php` Docker image already; the Dockerfile installs only what's actually missing (`pdo_pgsql`, `mbstring`, `dom`, `fileinfo`, `gd`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`) via [`mlocati/docker-php-extension-installer`](https://github.com/mlocati/docker-php-extension-installer), plus `opcache` as a justified low-risk production addition. **Not installed, deliberately:** `intl`, `bcmath` (grepped for in `app/` — no usage), `pcntl`/`posix` (no queue workers), `exif` (no image-EXIF handling).
- **Multi-stage build** (`Dockerfile`): Stage 1 (`node:22-bookworm-slim`) runs `npm ci && npm run build` to produce `public/build/` — Node never ships in the runtime image. Stage 2 (`php:8.4-cli-bookworm` + Composer) runs `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader` against the locked `composer.lock` exactly — no `composer update`, no database-dependent Artisan command. Stage 3 assembles the runtime: PHP-FPM + Nginx + `supervisord`, copies in `vendor/` and `public/build/` from the earlier stages (never rebuilt in stage 3), sets `storage/`+`bootstrap/cache/` ownership to `www-data`.
- **Process model**: `supervisord` (PID 1, root) manages exactly two children — `php-fpm` and `nginx`, both configured with their standard privilege-drop model (master starts as root to bind the port / manage worker lifecycle, then forks unprivileged `www-data` workers that actually execute application code and handle requests — the same model virtually every Nginx+PHP-FPM deployment uses). This is not a fully-rootless container; the tradeoff is disclosed rather than overclaimed.
- **Port**: the container listens on `8080` internally (unprivileged, so workers never need elevated capability to bind it); Coolify's reverse proxy maps it to the public domain and terminates HTTPS.
- **Health check**: no new route was added. Laravel's built-in `/up` route (already registered in `bootstrap/app.php` via `health: '/up'`) is used as-is — it dispatches a `DiagnosingHealth` event (no listener registered, so it's a pure no-op hook today, not a live DB check) and returns `{"status":"up"}` (200) or `{"status":"down"}` (500) for JSON requests, with no stack trace, DB credentials, or Laravel version ever exposed. The Dockerfile's `HEALTHCHECK` calls it with `Accept: application/json`.
- **Cache strategy**: `config:cache` and `view:cache` run in `docker/entrypoint.sh` **at container start**, not during the image build — Coolify's environment variables only exist once the container is actually running, so caching config at build time would bake in empty/wrong values. **`route:cache` is deliberately not used** — `routes/web.php` defines report-card/document-download routes as closures (confirmed by direct inspection: 8+ `Route::get(..., function (...) { ... })` closures), and `route:cache` cannot serialize a closure. This is a pre-existing, structural property of the route file, not something this task changed or worked around.
- **PHP ini** (`docker/php.ini`): `memory_limit=256M`, `upload_max_filesize=10M`, `post_max_size=12M`, `max_execution_time=120` — sized for RSMS's actual scale (~250 students, ~47 staff; small administrative `.xlsx` files, not bulk data dumps), not left unbounded. `expose_php=Off` and a standard production `opcache` block are also set.
- **Storage**: confirmed by direct inspection — RSMS has no persistent file-upload feature (`app/Services/Import/*Builder.php` all use `response()->streamDownload()`, never `Storage::disk('public')` or `Storage::put()`; Livewire's `WithFileUploads` temp storage is transient and discarded within the same request). No Coolify volume is provisioned. If a future feature adds persistent uploads, `storage/app/public` is the path that would need one.
- **Session/cache/queue**: `SESSION_DRIVER=database` and `CACHE_STORE=database` (the project's existing default, backed by Laravel's default `sessions`/`cache` migrations — no new migration needed) are kept as-is; no Redis is introduced. `QUEUE_CONNECTION=database` is also kept as-is — confirmed by grep that no code implements `ShouldQueue` or calls `dispatch()`/`->queue()`/`Bus::dispatch()` anywhere in `app/`, so nothing is ever actually queued; no worker container/process is added.
- **Scheduler**: `routes/console.php` contains only the default `inspire` Artisan command — no `Schedule::` calls anywhere. No scheduler/cron container is added.
- **Trusted proxy**: Coolify's reverse proxy terminates HTTPS in front of the container, so Laravel must be told to trust it for HTTPS detection, secure cookies, and correct URL generation — otherwise Livewire's CSRF/URL handling and `SESSION_SECURE_COOKIE` can misbehave behind the proxy. This needs `$middleware->trustProxies(...)` added to `bootstrap/app.php` at actual deployment time, scoped to Coolify's internal proxy network — **not implemented in this task** (`bootstrap/app.php` is unmodified) because the correct trusted-range value depends on Coolify's actual network topology on the real VPS, which doesn't exist yet. Flagged here as the one concrete follow-up item before going live at `https://app.rahai.sch.id`.

## 6. Local verification performed (and its limits)

**Docker itself is not installed in this working environment** (`docker` is not on `PATH` in either the Bash or PowerShell tool, and no Docker service was found) — the image was never actually built or run as a container, and this is disclosed rather than claimed otherwise. What *was* verified, against a disposable `git worktree` checked out at the P2.1 commit (`7b1b8cd`) so nothing here ever touched the uncommitted DeepSeek work:

- `npm ci && npm run build` (Dockerfile stage 1's exact commands) — succeeded; produced `public/build/manifest.json` and the expected hashed assets.
- `composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader` (stage 2's exact command) — see completion report for result.
- `docker/entrypoint.sh` — syntax-checked with `sh -n` (POSIX-valid).
- `docker/nginx.conf` / `docker/supervisord.conf` — no `nginx`/`supervisord` binary is available in this environment either, so these were reviewed manually, not linted.
- Application boot, migrations, `rsms:bootstrap-admin`, login, and a NIK/NISN form render were smoke-tested using PHP's built-in server against the `--no-dev` vendor build and the built frontend assets, pointed at a disposable, freshly-created `rahai_verify` PostgreSQL database (dropped after) — see completion report for exact steps and results.

**Recommended next step**: run an actual `docker build` (Coolify will do this automatically from the Git repository on first deploy, which only ever sees committed code) before the first real deployment, ideally from a machine with Docker available, or let Coolify's own build serve as the first real build-and-boot test.

## 7. Environment variables (Coolify)

Set these as Coolify environment variables on the `rsms-app` resource — never baked into the image, never committed with real values.

| Variable | Example / guidance |
|---|---|
| `APP_NAME` | `"Rahai School Management System"` |
| `APP_ENV` | `staging` (initially) / `production` (later) |
| `APP_KEY` | generate fresh **for this environment** via `php artisan key:generate --show`, paste the output — never reuse the dev key |
| `APP_DEBUG` | `false` — required |
| `APP_URL` | `https://app.rahai.sch.id` |
| `LOG_CHANNEL` | `stderr` — see rationale below |
| `LOG_LEVEL` | `info` in production; `debug` acceptable during controlled UAT |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` / `DB_PORT` | Coolify's internal hostname/port for the `rsms-postgres` resource — not known until that resource is actually created; do not guess it in advance |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | set by/matched to the Coolify PostgreSQL resource |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` (nothing is ever queued today — see §5) |
| `SESSION_SECURE_COOKIE` | `true` once HTTPS is confirmed working end-to-end |
| `MAIL_MAILER` | `log` unless/until a real mail requirement exists — no email workflow depends on it today |
| `AI_ENABLED` / `AI_PROVIDER` / `AI_MODEL` / `AI_TIMEOUT` / `ANTHROPIC_API_KEY` | existing V9A configuration, unchanged by this task — no new AI configuration was invented, and DeepSeek is not part of this env-var set since it remains uncommitted |
| `BOOTSTRAP_ADMIN_EMAIL` / `BOOTSTRAP_ADMIN_PASSWORD` | set once, deliberately, immediately before running `rsms:bootstrap-admin` — see §9 |

**Logging**: `LOG_CHANNEL=stderr` is recommended for the container (the channel already exists in `config/logging.php`, unmodified — no new code needed) so Coolify's own log viewer captures everything without needing to read a persistent log file inside the container. This task does not change the application's default (`stack`/`single`, still correct for local dev) — it's an environment-variable recommendation for the Coolify resource only.

**PostgreSQL SSL**: not forced. `config/database.php`'s `pgsql` connection already defaults `sslmode` to `env('DB_SSLMODE', 'prefer')`, unmodified — `prefer` works correctly whether or not the internal Coolify network happens to offer TLS, and remains compatible if the database is later moved to an external, non-Coolify-network Postgres that does require TLS.

## 8. Coolify resource model (to create later — not created by this task)

```
Coolify Project: RSMS
  Environment: staging
    Resource: rsms-app        (application — Dockerfile build from the GitHub repo)
    Resource: rsms-postgres   (separate PostgreSQL database resource)
Domain (later): app.rahai.sch.id
```

- `rsms-postgres` is reachable only over Coolify's internal network by default — never exposed on a public port.
- The exact internal hostname Coolify assigns to `rsms-postgres` is not knowable until the resource is actually created; do not hardcode a guess anywhere.
- Coolify is configured to build from this repository's `Dockerfile` directly (not Nixpacks/auto-detection), so the `Dockerfile` at the repo root and the build context (repo root) are exactly what a future Coolify resource should point at.

## 9. First deployment sequence (documented only — not executed by this task)

1. Provision the Hostinger VPS, install Ubuntu 24.04 LTS, install Coolify.
2. Create the `RSMS` project / `staging` environment in Coolify.
3. Create the `rsms-postgres` resource.
4. Create the `rsms-app` resource from this GitHub repository, Dockerfile build.
5. Set the environment variables from §7 (with the real `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` Coolify assigns to `rsms-postgres`).
6. Configure the `app.rahai.sch.id` domain and enable HTTPS.
7. **Before running anything destructive**, print/verify `APP_ENV`/`DB_CONNECTION`/`DB_DATABASE` from inside the running container (the permanent database-safety convention, §3, applies here exactly as everywhere else).
8. Run `php artisan migrate --force` as a Coolify deploy/pre-deploy command (never automatically on every container restart — see §5's cache-vs-migrate distinction).
9. Run `php artisan db:seed --force` (idempotent configuration/reference seeders only — same guarantee as §1).
10. Run `php artisan rsms:bootstrap-admin` once, with `BOOTSTRAP_ADMIN_EMAIL`/`BOOTSTRAP_ADMIN_PASSWORD` set.
11. Remove/blank the bootstrap password environment variable once the account exists — it has no further use after this one run.
12. `config:cache`/`view:cache` already run automatically on every container start via `docker/entrypoint.sh` — no manual step needed.
13. Smoke test: health check, login, one Livewire page, one NIK/NISN form render, one Excel template download.

## 10. Normal (repeat) deployment sequence

```
git push → Coolify builds a new image → pre-deploy: migrate --force → deploy → health check → traffic switches
```

- `db:seed` is **not** run automatically on every deploy — only when a change actually adds new idempotent reference data worth re-running, decided deliberately each time, not as a standing pipeline step.
- Coolify does not guarantee zero-downtime by default for a single-VPS, single-replica setup — this document does not assume a guarantee that doesn't exist.
- **Production backups** (extending §4 to the VPS/Coolify target): once `rsms-postgres` exists, automated daily backup + an off-server destination + a tested restore procedure are still required before real school data enters it — Coolify can schedule PostgreSQL backups, but this task does not configure that (no resource exists yet to configure it on).

---

*Pre-UAT Hardening P1 — 2026-08-17. Docker/Coolify preparation — 2026-08-18. See [CHANGELOG.md](CHANGELOG.md) for what changed and why.*
