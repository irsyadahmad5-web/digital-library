# Digital Library

Modern, reading-first digital library built with Laravel, Vue, TypeScript, Inertia, Tailwind CSS, and shadcn-vue conventions.

## Technical baseline

- Laravel 13 / PHP 8.3
- Vue 3 + TypeScript
- Inertia.js
- Tailwind CSS 4
- shadcn-vue source-component architecture
- Reka UI primitives
- Lucide Vue icons
- Plus Jakarta Sans
- MySQL / MariaDB target
- Modular Monolith architecture

## Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run dev
```

For a production build:

```bash
npm run typecheck
npm run build
php artisan test
```

## Web Installer

For a fresh production deployment, point the web server document root to the project's `public/` directory, install Composer dependencies, and ensure the frontend production build exists. Keep `APP_KEY` empty in the initial `.env` (or let Composer copy `.env.example`), then open `/install` in the browser.

The installer checks PHP 8.3+, required extensions/functions, writable paths, the Vite build, Poppler (`pdfinfo` / `pdftocairo`), and the database backup/restore clients (`mariadb-dump` or `mysqldump`, plus `mariadb` or `mysql`). It only accepts MySQL or MariaDB and refuses to install into a database that already contains tables. The database user must be able to create, alter, index, and drop tables.

After the database test succeeds, the installer writes a production-safe `.env` atomically, generates the permanent `APP_KEY`, and keeps only non-secret database metadata in its pending-state file. The final step runs migrations and seeders, creates `public/storage`, creates the first Super Admin, updates the library name, writes `storage/app/installed.lock`, removes the temporary installer key, and closes the installer. Database passwords and the admin password are never written to the installer state file.

If the site is behind a reverse proxy or tunnel, enter only the real proxy IP/CIDR values in the trusted-proxy field. Do not use wildcard trust. HTTPS should be configured before installation so the installer can enable secure cookies and HSTS correctly.

## Backup, Maintenance & Health

Stage 23 adds an operational safety layer for production. Local backup snapshots are stored outside the public web root under `storage/app/backups`. Each snapshot contains the database dump plus private ebook PDFs, public ebook media, and branding assets. A SHA-256 manifest is written for every included file so restore media can be verified before use. Backup credential files are temporary, permission-restricted, and removed immediately after the database dump. The application deliberately does **not** copy `.env`, `APP_KEY`, database credentials, or other runtime secrets into these snapshots; keep those secrets in a separate secure recovery store.

The default scheduler runs a backup daily at `02:30`, verifies the newest backup at `04:00`, prunes retention at `04:30`, records a scheduler heartbeat every minute, and writes an operational health snapshot every five minutes. Configure the scheduler once on the server:

```cron
* * * * * cd /path/to/digital-library && php artisan schedule:run >> /dev/null 2>&1
```

Useful operational commands:

```bash
php artisan operations:health
php artisan operations:health --json --snapshot
php artisan operations:backup
php artisan operations:backup:list
php artisan operations:backup:verify
php artisan operations:restore:check
php artisan operations:backup:prune
php artisan site:maintenance status
php artisan site:maintenance on --message="Pemeliharaan terjadwal."
php artisan site:maintenance off
```

`/health/ready` exposes only a minimal readiness summary suitable for external monitoring; detailed database, disk, scheduler, backup, migration, and failed-job diagnostics remain available through the CLI command. A degraded state returns HTTP 200 with warnings, while a critical state returns HTTP 503.

Before a restore, run `operations:restore:check` and keep the site in maintenance. The command verifies the newest (or specified) backup checksum manifest, confirms the database restore client is available, and checks that ebook storage targets are writable. Keep an independent off-server copy of important backups; local retention protects against application mistakes but does not protect against total server or disk loss.

Backup behavior is controlled with `BACKUP_ENABLED`, `BACKUP_RETENTION_DAYS`, `BACKUP_RETENTION_COUNT`, `BACKUP_MAX_AGE_HOURS`, and `BACKUP_DAILY_AT`. Health thresholds are controlled with `HEALTH_SCHEDULER_MAX_AGE_SECONDS`, `HEALTH_DISK_WARNING_PERCENT`, and `HEALTH_DISK_CRITICAL_PERCENT`.

## PWA & Mobile

Stage 24 makes the public library installable as a Progressive Web App. The manifest uses the current library name and theme colors, includes 192 px / 512 px / maskable icons, and supports shortcuts to the catalog and search. Android/Chromium receives the native install prompt when available; iOS users receive the Safari **Add to Home Screen** guidance.

The service worker intentionally uses a conservative cache policy. Hashed frontend build assets and public images can be cached, while `/admin`, `/install`, `/health`, reader/PDF routes, and ebook downloads are excluded. Navigations remain network-first and fall back to a self-contained offline page, so the PWA does not present stale ebook metadata or silently persist private PDF responses. A waiting service-worker update is applied only after the user chooses **Perbarui**.

Mobile layouts include safe-area handling for notched devices, 44 px primary touch targets, scroll-locked mobile navigation, larger reader controls on coarse-pointer devices, safe reader drawers, and viewport-aware reader spacing. Reader progress and preferences continue to remain local to the browser as designed in Stage 15.

Production PWA installation requires HTTPS (localhost remains supported for development). After deployment, verify `/manifest.webmanifest`, `/sw.js`, `/offline.html`, and the files under `/pwa/` are reachable directly from the public document root.

## Testing & QA

Stage 25 provides a repeatable release gate. Run `composer qa` before every production candidate. The script validates Composer metadata, PHP formatting, the complete PHPUnit suite, release artifacts/toolchains, Laravel cache compilation, Composer/npm security advisories, TypeScript, the Vite production build, service-worker syntax, tracked secret patterns, and whitespace integrity. When Chrome/Chromium is available, `composer qa:browser` creates a disposable SQLite database, boots an isolated local server, checks critical HTTP endpoints, and runs desktop/mobile headless-browser smoke journeys without touching the configured application database.

`php artisan quality:verify` checks the runtime database connection, Vite/PWA release artifacts, icon dimensions, writable runtime paths, PDF tools, and database dump/restore tools. On an actual production candidate, use `php artisan quality:verify --production`; this additionally requires production environment mode, debug disabled, HTTPS APP_URL, a valid APP_KEY, completed installer lock, host validation, CSP, and HSTS.

The full browser/device acceptance checklist and release blockers are documented in `docs/RELEASE_QA_CHECKLIST.md`. Stage 25 also disables Laravel's framework-served route for the private `local` storage disk; private ebook files remain accessible only through the application's guarded reader/download controllers.

## Production Release

The first stable release is **1.0.0**. The canonical version is stored in `VERSION`; release notes live in `CHANGELOG.md`. Run `php artisan release:info` to read the runtime release identity.

A deployment-ready archive can be built from a clean commit with `composer release:package`. The resulting `dist/digital-library-v1.0.0.zip` includes full source, optimized production Composer dependencies, and compiled frontend assets, but excludes `.env`, Git metadata, `node_modules`, runtime logs, backups, and uploaded content. The archive is accompanied by an external SHA-256 file and contains `RELEASE_FILES.sha256` for per-file integrity verification.

After installing/upgrading a real production instance, run `php artisan release:verify`. This final gate combines the Stage 25 production checks with operational health and restore-readiness. For a truly empty first installation only, `--fresh-install` can temporarily waive the pre-existing backup requirement; create and verify the first backup immediately afterward and rerun the command without that option.

The complete deployment, upgrade, rollback, and final-acceptance procedure is in `docs/PRODUCTION_RELEASE.md`.

## Project tracking

The implementation roadmap is tracked in Linear. Architecture and UX decisions are documented in Notion and Figma.

## Security

Never commit `.env`, credentials, API keys, storage secrets, private ebook files, or runtime uploads.

Production hardening is enabled automatically when `APP_ENV=production`: debug output is forced off, secure session cookies are required, host validation and HSTS default to enabled, and public/admin HTML receives CSP and browser security headers. Keep `APP_URL` on the canonical HTTPS origin. Use `SECURITY_ALLOWED_HOSTS` only for additional legitimate hosts.

When the application is behind a reverse proxy or tunnel, set `SECURITY_TRUSTED_PROXIES` only to the actual proxy IP/CIDR values so forwarded HTTPS/client-IP headers cannot be spoofed by arbitrary clients. Do not use `*` unless every request is guaranteed to arrive through a trusted proxy. `SECURITY_HSTS_INCLUDE_SUBDOMAINS` and `SECURITY_HSTS_PRELOAD` should remain disabled until every affected subdomain is permanently HTTPS-capable.