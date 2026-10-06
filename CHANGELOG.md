# Changelog

All notable changes to Digital Library are documented in this file.

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
