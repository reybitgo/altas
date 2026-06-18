# Altas Farm — MLM Binary System

## Stack

Pure PHP 8.1+, MySQL 8.0+, Apache with mod_rewrite. **No Composer, no npm, no framework.** No automated tests, no CI/CD, no linter, no typechecker.

## Setup

```bash
mysql -u root -p -e "CREATE DATABASE mlm_db ... COLLATE utf8mb4_unicode_ci"
mysql -u root -p mlm_db < install.sql
# Edit config/db.php: DB_HOST, DB_NAME, DB_USER, DB_PASS, APP_URL, APP_ENV
```

Default admin: `admin` / `Admin@1234`.

## Dev server

Run via Apache (Laragon/XAMPP/WAMP). Local URL: `http://localhost/altas/`. Set `APP_ENV=development` in `config/db.php` for error display.

## Commands

There are no build/test/lint commands. Verification is manual only (see `QA_TESTING_GUIDE.md`).

Database reset: hit `/reset.php` in browser, type `RESET`. (Deletes all members, preserves admin + packages.)

Password hash helper: `/pass_hash.php`. Uses bcrypt cost 12.

## Architecture

- **Router**: `index.php` — maps `?page=` param to `[ControllerClass, method, role]` triplets. All HTTP requests go through here.
- **Autoloading**: `spl_autoload_register` in `index.php` — no PSR-4, no namespaces. All classes are global (PHP 5-style). Cron scripts duplicate the autoloader manually.
- **Auth guards**: `Auth::guard('admin'|'member'|'guest'|'any')` at top of each controller method. Sessions (`mlm_sess`), bcrypt (cost 12), CSRF tokens, rate-limited login.
- **Views**: `views/` — plain PHP (short echo tags `<?=`). Partials in `views/partials/`.
- **Helpers**: Global functions in `core/helpers.php` — `e()`, `fmt_money()`, `redirect()`, `flash()`, etc.
- **Commission engine**: Real-time (not batch). PV-based: entry fee × `package_pv_rate` → personal PV; binary PV at `binary_pv_pct%`. Bonuses = PV × pct × `pv_per_peso_rate`.
- **Binary placement**: Breadth-first filling left before right.
- **Cron**: `cron/midnight_reset.php`, `cron/monthly_pv_reset.php`, `cron/fund_transfer_limit_reset.php`. Must be set up manually. All log to `cron/logs/`.

## Cron setup

```bash
0 0 * * * /usr/bin/php /path/cron/midnight_reset.php >> /path/cron/logs/reset.log 2>&1
0 0 1 * * /usr/bin/php /path/cron/monthly_pv_reset.php
0 0 * * * /usr/bin/php /path/cron/fund_transfer_limit_reset.php
```

## Migrations

Sequential SQL files in `migrations/` (001–026). Apply manually in order. No migration runner.

## Gotchas

- `config/db.php` has hardcoded dev credentials + `APP_URL` (`http://localhost/altas`). Must change per environment.
- Timezone hardcoded to `Asia/Manila` in `config/db.php` and cron scripts.
- Database name `u938213108_altas_db` appears to be from a hosting provider — rename for new deployments.
- `pass_hash.php` and `reset.php` are dev utilities — delete before production.
- Repeat purchases flow product PV up the binary tree (affects commissions).
- CV/DV pair-flush: excess paired PV is lost forever (capped daily).
- Seat-limit enforcement: hard cap on member count.
- Maintenance mode with secure bypass token.
- VIP capping bypass: admin-granted unlimited earnings.
