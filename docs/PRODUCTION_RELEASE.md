# Production Release 1.5.2

Digital Library 1.5.2 is the stable responsive/mobile experience patch on top of v1.5.1. It tightens homepage section spacing, corrects the mobile drawer and search button, simplifies footer and statistics, adds realtime active visitor counts with a tiny indexed database table, and uses the configured website logo for PWA icons.

## v1.5.2 acceptance checklist

- Mobile slider stays full-width/horizontal while book, category, statistics and discovery sections use compact responsive spacing and readable type. Desktop keeps editorial spacing.
- Mobile drawer has no horizontal overflow or clipped search button, and desktop top navigation contains Beranda.
- Footer has no Ruang Baca Digital promotional banner; contact fields are shown directly when configured; heading Statistik and real-time Pengguna online appear with a 120-second window.
- GET `/manifest.webmanifest` contains `/pwa/site-icon/192.png`, `/pwa/site-icon/512.png` and `/pwa/site-icon/maskable-512.png`. Icons should show the uploaded site logo.
- POST `/presence/heartbeat` requires CSRF, is rate limited and returns a non-cached visitor count after migration. No IP/user agent is stored.
- Maintain backup/restore-readiness, artifact file hash validation, search/category/books smoke, and final health/security checks before acceptance.

## Release artifact

Create the production ZIP from a clean Git commit:

```bash
composer release:package
```

The packaging command runs the full QA gate and browser smoke test, rebuilds frontend assets, exports only tracked source files, installs optimized production Composer dependencies without dev packages, adds `RELEASE.json`, generates `RELEASE_FILES.sha256`, and creates:

```text
dist/digital-library-v1.5.2.zip
dist/digital-library-v1.5.2.zip.sha256
```

The archive deliberately excludes `.env`, Git metadata, `node_modules`, runtime logs, backups, uploaded ebooks, and other server state. It includes the full application source, migrations, tests/documentation, production `vendor/`, and compiled `public/build/`.

Verify the ZIP before deployment:

```bash
cd dist
sha256sum -c digital-library-v1.5.2.zip.sha256
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
- Real public searches such as /library?q=Taqrib and /search?q=Taqrib return HTTP 200, and a no-result query returns an empty state instead of HTTP 500.
- Homepage Builder can upload and persist both desktop and mobile Hero Slider images without exposing private storage paths; public homepage rendering falls back safely when no slide image exists.
- Buku Terbaru and reader-based Buku Populer render in the intended order when data is available, and the configured favicon is present on admin/auth/public pages.
- Public book cards keep category and read-count badges on the cover, expose title/author on desktop hover/focus, keep the title usable on touch/mobile, and render no persistent metadata block beneath the cover.
- Admin category create/edit exposes the curated icon picker; persisted category icons render on homepage categories, /categories, category taxonomy headers, and public book payloads with a safe fallback.
- Admin main document scrollbar is compact and visually idle-hidden, revealing during scroll/wheel activity or when the pointer approaches the right edge.
- Admin sidebar and nested overflow regions use thin rounded scrollbars that reveal on hover/focus, with forced-colors fallback preserved.
- Authenticated /admin/homepage-builder renders its admin shell and Homepage Builder content instead of a blank client page; the deployed frontend bundle no longer contains the unsafe structuredClone(section.config) path.
- Homepage Hero Slider renders only slides backed by an uploaded desktop or mobile image; the slider itself contains no public title, description, overlay, or CTA. If no slider image is available, the homepage uses the non-carousel hero fallback instead of fabricating an image slide.
- The Hero Slider is full-bleed directly beneath the public top bar, stays horizontal on desktop and mobile, uses translucent navigation controls that auto-hide while idle, and preserves keyboard/reduced-motion behavior.
- Homepage Builder exposes desktop 1920 × 720 (8:3) and mobile 1280 × 720 (16:9) horizontal upload guidance, keeps existing custom slide media, and no longer exposes overlay/title/CTA presentation controls for the slider.
- Public document scrollbar is compact and visually idle-hidden, revealing briefly during scroll/wheel/touch activity or when the pointer approaches the right edge, with forced-colors fallback preserved.
- Public footer renders the new editorial CTA, site/navigation/contact columns, and aggregate Buku publik/Pembacaan/Kategori/Penulis statistics without exposing visitor identities.
