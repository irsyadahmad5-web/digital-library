# Release QA Checklist

Stage 25 turns the release checks into one repeatable gate.

## Automated gate

Run:

```bash
composer qa
```

The gate must finish with `Stage 25 QA gate: PASS`. It validates Composer metadata, PHP formatting, the complete PHPUnit suite, release artifacts/toolchain readiness, Laravel cache compilation, dependency security advisories, TypeScript, the Vite production build, service-worker syntax, tracked secret patterns, and whitespace integrity.

When Chrome/Chromium is installed, also run:

```bash
composer qa:browser
```

This browser smoke test uses a disposable SQLite database and isolated local server, verifies the public home/catalog, admin login, manifest, offline page, service worker, and readiness endpoint, and renders desktop/mobile browser journeys without modifying the configured application database.

For a deployed production candidate, also run:

```bash
php artisan quality:verify --production
php artisan operations:health
php artisan operations:restore:check
```

The production quality gate intentionally fails unless the runtime is actually production, debug is off, APP_URL uses HTTPS, APP_KEY is valid, the installer is locked, host validation/CSP/HSTS are enabled, every database migration is applied, `public/storage` is linked, required build/PWA assets exist, writable runtime paths are ready, the database responds, and PDF/database toolchains are available.

## Browser and device acceptance

Before the final production release, verify these journeys on at least one current Chromium desktop browser and one phone-sized viewport. Where an iPhone/iPad is available, also verify the Safari Add to Home Screen flow.

- Public home, catalog, search, taxonomy pages, ebook detail, reader, and permitted download.
- Admin login, dashboard, ebook management, master data, settings, analytics, audit log, profile/password, logout, and permission-denied behavior.
- Reader on a narrow viewport: previous/next, page input, search, thumbnails, settings drawer, orientation change, zoom/fit, local resume state, and swipe navigation outside continuous mode.
- PWA manifest/icons, install prompt or platform-specific install guidance, standalone launch, offline fallback, reconnect state, and update prompt.
- Maintenance mode: public pages return maintenance status while admin login, health readiness, and manifest remain reachable.
- Installer on a fresh disposable environment only: requirement checks, database validation, first Super Admin creation, lockout after completion, and no credential leakage.
- Backup/restore readiness: create a backup, verify checksums, confirm retention behavior, and run restore-readiness checks before any destructive restore drill.

## Release blockers

Do not release while any automated gate is failing, while a production quality check is red, while a security/dependency audit reports an unresolved vulnerability, while migrations are pending, while scheduler/backup health is critical, or while an essential browser journey above is broken.

The local filesystem backup is only one recovery layer. Keep an independent off-server backup and a separate secure copy of runtime secrets such as `.env`/APP_KEY/database credentials.
