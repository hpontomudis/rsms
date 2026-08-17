# Deployment & Environment Readiness

Operational reference for standing up RSMS outside local dev. This is a living document — update it as staging/production reality is actually confirmed, not guessed at. Anything not yet verified against the real hosting target is marked **TO VERIFY ON HOSTINGER** rather than assumed.

Related: [PROJECT_STATUS.md](PROJECT_STATUS.md) for feature/build state, [UAT_ISSUE_TEMPLATE.md](UAT_ISSUE_TEMPLATE.md) for reporting issues found while testing an environment set up from this document.

---

## 1. Staging environment checklist

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

*Pre-UAT Hardening P1 — 2026-08-17. See [CHANGELOG.md](CHANGELOG.md) for what changed and why.*
