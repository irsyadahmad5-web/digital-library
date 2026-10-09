# Changelog

All notable changes to Digital Library are documented in this file.

## [1.0.1] - 2026-10-09

### Fixed

- Isolated numeric route rate-limit counters so installer, admin, public search, health, and ebook operations no longer consume one another's request budgets.
- Enforced HTTPS for non-loopback production URLs during web installation and corrected proxy-aware production URL handling.
- Removed the static `public/robots.txt` file that could shadow the dynamic SEO robots route in Apache deployments.

### Security

- Denied direct HTTP access to dotfiles under the Apache public root, including `.user.ini` and backup-style dotfiles.
- Added regression coverage for installer HTTPS policy, rate-limit namespace isolation, dynamic robots routing, and public-root dotfile protection.

### Release

- Added GitHub Actions full QA and release packaging so CPU-intensive verification and artifact builds run on hosted runners instead of production servers.
- Made clean-checkout QA self-contained with an ephemeral SQLite runtime and generated test application key.

## [1.0.0] - 2026-10-06

### Added

- Production-ready browser installer with server requirement checks, database validation, atomic environment writing, migrations/seeders, first Super Admin creation, storage linking, and installer lockout.
- Public digital library with homepage builder, catalog, taxonomy directories, advanced search/discovery, ebook metadata pages, PDF reader, controlled downloads, SEO/social sharing, analytics, PWA installation, and mobile polish.
- Admin authentication, RBAC, audit trail, ebook management, master data, PDF upload/processing, settings, homepage builder, analytics, profile/password security, and session controls.
- Privacy-preserving engagement analytics and download counters without storing reader IP addresses, user agents, or visitor identity.
- Backup, checksum verification, retention, scheduler heartbeat, restore-readiness checks, maintenance controls, and operational health reporting.
- Repeatable source QA and production-readiness verification, including disposable headless-browser smoke tests.

### Security

- Hardened security headers, CSP nonces, production HSTS, trusted-host validation, rate limits, forced password-change flow, secret redaction, SSRF protections, DNS pinning, safe storage paths, and private PDF access controls.
- Disabled Laravel framework serving for the private local storage disk so private ebook files are only exposed through guarded application reader/download endpoints.
- Service Worker deliberately excludes admin, installer, health, reader/PDF, and download responses from caching.

### Release

- First stable production release.
- Requires PHP 8.3+, MySQL/MariaDB for normal production use, Poppler `pdfinfo`/`pdftocairo`, database dump/restore clients, HTTPS, and a web root pointed to `public/`.
