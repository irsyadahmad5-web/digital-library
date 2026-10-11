# Production Release 1.1.0

Digital Library 1.1.0 is the stable Modern Editorial Digital Library UI/UX release. It promotes the verified v1.1.0-rc.1 candidate after the complete responsive/accessibility acceptance pass while preserving the hardened installer, storage, security, backup, restore, and release pipeline.

## Release artifact

Create the production ZIP from a clean Git commit:

```bash
composer release:package
```

The packaging command runs the full QA gate and browser smoke test, rebuilds frontend assets, exports only tracked source files, installs optimized production Composer dependencies without dev packages, adds `RELEASE.json`, generates `RELEASE_FILES.sha256`, and creates:

```text
dist/digital-library-v1.1.0.zip
dist/digital-library-v1.1.0.zip.sha256
```

The archive deliberately excludes `.env`, Git metadata, `node_modules`, runtime logs, backups, uploaded ebooks, and other server state. It includes the full application source, migrations, tests/documentation, production `vendor/`, and compiled `public/build/`.

Verify the ZIP before deployment:

```bash
cd dist
sha256sum -c digital-library-v1.1.0.zip.sha256
```

After extraction, `RELEASE_FILES.sha256` can be used to validate all packaged files.

## Fresh installation

1. Extract the release to the site directory.
2. Point the web-server document root to the extracted project's `public/` directory.
3. Ensure PHP 8.3+, required extensions, Poppler `pdfinfo`/`pdftocairo`, and MySQL/MariaDB dump/restore clients are available.
4. Make `storage/` and `bootstrap/cache/` writable by the web-server user.
5. Copy `.env.example` to `.env` only when your hosting requires a pre-existing file. Keep `APP_KEY` empty for the first installer boot.
6. Open `/install` over HTTPS and complete the browser installer.
7. Add the single scheduler cron entry:
   ```cron
   * * * * * cd /path/to/digital-library && php artisan schedule:run >> /dev/null 2>&1
   ```
8. Run one heartbeat and the first backup:
   ```bash
   php artisan operations:heartbeat
   php artisan operations:backup
   php artisan operations:backup:verify
   php artisan operations:restore:check
   ```
9. Run the final production gate:
   ```bash
   php artisan release:verify
   ```
10. Verify home, catalog, reader, permitted download, admin login, PWA manifest, and `/health/ready` from the real HTTPS domain.

For a truly empty first installation only, `php artisan release:verify --fresh-install` may be used before the first backup. Create and verify the first backup immediately afterward, then rerun `php artisan release:verify` without that option.

## Upgrade / in-place release

Before changing application files, create and verify a backup:

```bash
php artisan operations:backup
php artisan operations:backup:verify
php artisan operations:restore:check
php artisan site:maintenance on --message="Pembaruan sistem sedang berlangsung."
```

Then deploy the new release files while preserving the existing `.env` and runtime storage. Never overwrite `storage/app/private`, `storage/app/public`, backup snapshots, or database credentials with files from the release ZIP.

Run:

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan operations:heartbeat
php artisan release:verify
```

Only disable maintenance after the production gate passes and the critical browser journeys work:

```bash
php artisan site:maintenance off
```

## Rollback

If the release gate or smoke test fails:

1. Keep public maintenance enabled.
2. Restore the previous known-good application files.
3. Run `php artisan optimize:clear`.
4. If a database rollback is required, restore only from a checksum-verified backup and follow the restore procedure for the active database engine.
5. Restore ebook/public storage from the same backup snapshot when required so database and files remain consistent.
6. Run `php artisan operations:restore:check`, `php artisan operations:health`, and the previous release's verification command before reopening the site.

Do not automatically run `migrate:rollback` on production. Some schema/data migrations may not be safely reversible after live writes. A verified pre-release database and storage backup is the authoritative rollback point.

## Final acceptance

The release is ready to open publicly only when:

- `composer qa` and `composer qa:browser` pass on the release source.
- The release ZIP checksum is valid.
- The production database has no pending migrations.
- `public/storage` exists and runtime directories are writable.
- Scheduler heartbeat is current and the latest backup is restore-ready.
- `php artisan release:verify` returns PASS.
- HTTPS, host validation, CSP, HSTS, and production debug settings pass.
- No unresolved dependency security advisory remains.
- Home, catalog/search, book detail, reader, download policy, admin login, PWA, maintenance behavior, and readiness endpoint have been smoke-tested on the deployed domain.
