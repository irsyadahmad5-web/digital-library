# Release Candidate 1.1.0-rc.1

Target stable release: **v1.1.0**

Production tetap pada v1.0.2 sampai UX-12 Production Deployment & Final Acceptance selesai. Release candidate ini tidak mengubah production dan tidak boleh diperlakukan sebagai stable release.

## Candidate identity

- Version: `1.1.0-rc.1`
- Channel: `rc`
- PHP baseline: 8.3+
- UI direction: Modern Editorial Digital Library
- Required visual qualities: modern, responsive, professional, premium, compact, consistent

`VERSION` adalah identitas source candidate. `config/release.php` dan release packager menurunkan channel `rc` dari prerelease version secara default; `RELEASE_CHANNEL` tetap dapat meng-override channel secara eksplisit untuk pipeline yang memang memerlukannya.

## Scope included

Candidate ini mencakup seluruh rangkaian UI/UX v2 sampai UX-10:

- UX-01 Design System Foundation;
- UX-02 Public Shell & Discovery;
- UX-03 Editorial Homepage;
- UX-04 Catalog, Taxonomy & Discovery;
- UX-05 Book Detail;
- UX-06 Reader Focus Mode;
- UX-07 Admin Shell & Navigation;
- UX-08 Admin Workspaces;
- UX-09 Authentication & System States;
- UX-10 Responsive & Accessibility Acceptance.

Backend reader/download/security semantics tetap dipertahankan. Tidak ada perubahan production secara langsung pada tahap ini.

## Acceptance evidence before packaging

Source candidate hanya boleh dipaketkan bila GitHub Actions lulus:

- Composer validation;
- Pint;
- TypeScript typecheck;
- Vite production build;
- complete PHPUnit suite;
- release quality verification;
- Laravel cache compilation;
- Composer/npm security audit;
- service-worker syntax;
- tracked secret scan;
- whitespace integrity;
- browser smoke;
- responsive/accessibility browser audit.

Browser acceptance menguji reference widths:

`360 / 390 / 430 / 768 / 1024 / 1280 / 1440 / 1600 px`

dan juga:

- long-content overflow stress;
- missing/minimal metadata through QA fixtures;
- accessible names for interactive controls;
- coarse-pointer touch target sizing;
- keyboard focus visibility;
- reduced-motion behavior;
- reader portrait, landscape, and desktop layouts.

## Release candidate artifact

Artifact harus dibangun **hanya di GitHub Actions**, bukan di production server.

Expected files inside the uploaded workflow artifact:

```text
digital-library-v1.1.0-rc.1.zip
digital-library-v1.1.0-rc.1.zip.sha256
```

The packaged `RELEASE.json` must report:

```json
{
  "version": "1.1.0-rc.1",
  "channel": "rc"
}
```

The archive also contains `RELEASE_FILES.sha256` for per-file integrity verification and excludes `.env`, Git metadata, `node_modules`, runtime storage, logs, backups, installer runtime state, and uploaded ebooks.

## Promotion to v1.1.0

Do not promote this candidate merely because source QA passes. UX-12 still requires the real production acceptance sequence:

1. Create and verify a production backup and restore readiness.
2. Prepare a rollback snapshot/known-good artifact.
3. Promote source version from `1.1.0-rc.1` to `1.1.0` with channel `stable`.
4. Build the stable artifact in GitHub Actions and verify its checksum.
5. Deploy while preserving `.env`, database state, private ebook storage, public uploads, and backup data.
6. Run migrations only from the verified artifact when required.
7. Clear/rebuild runtime caches.
8. Run heartbeat, health, and `php artisan release:verify`.
9. Smoke-test public home, catalog/search, book detail, reader, permitted download, admin login/workspace, PWA, maintenance behavior, and `/health/ready` on the real HTTPS domain.
10. Verify production security headers and sensitive-path protection before reopening the site.

Only after every UX-12 acceptance item passes does the release become **v1.1.0 stable**.
