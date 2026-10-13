# Changelog

All notable changes to Digital Library are documented in this file.

## [1.5.2] - 2026-10-13

### Improved

- Tightened mobile homepage spacing and responsive type/card sizing under the full-width hero banner, with subtle desktop spacing refinements.
- Fixed the mobile navigation panel's horizontal clipping and scrollbar, and redesigned the constrained search input/button with accessible touch targets.
- Added Beranda to the desktop navigation and simplified footer branding, navigation, contact display, and compact statistics.
- Removed the old Ruang Baca Digital promotional strip from the footer.
- Added privacy-preserving realtime active visitor counts (120-second presence window, 45-second heartbeat, no stored IP/user-agent); includes a small indexed presence table migration and throttled CSRF-protected endpoint.
- PWA manifest icons and Apple touch icon are now generated from the administrator-uploaded site logo, with a maskable safe area and generic fallback when no valid logo exists.
- Reduced the intrusive floating PWA install control to an icon-sized mobile button while retaining clear accessible naming.
- Preserved image-only hero presentation while adding a screen-reader-accessible page heading.

### Verification

- Responsive visual/browser QA covers the mobile drawer's bounds and search input, all major viewport sizes, accessible controls, and reduced motion.
- Backend feature and release route contracts cover realtime presence, logo-derived PWA images, footer, and navigation behavior.

### Deployment

- Production upgrade from v1.5.1 requires database migration `2026_10_13_020000_create_public_presence_table.php`.
- Update existing PWA installations by refreshing the manifest and, when an installed device retains the old cached icon, reinstalling the app.
- Keep full backup, verified artifact checksum, health/security gates, and rollback readiness.

## [1.5.1] - 2026-10-13

### Changed

- Reworked the public Hero Slider into a full-bleed image-only banner that attaches directly beneath the public top bar with no side gutters, card radius, text overlay, description, or CTA.
- Standardized slider proportions to horizontal formats on every breakpoint: 1920 × 720 (8:3) guidance for desktop and 1280 × 720 (16:9) guidance for mobile.
- Navigation arrows and position indicators now use translucent controls that reveal during interaction and automatically fade away while idle.
- Homepage Builder now focuses slider editing on responsive image uploads, ordering, accessibility alt text, autoplay, interval, arrows, and indicators; the obsolete overlay-strength and public title/CTA editing controls are removed from the active schema.
- Public pages now use the same compact auto-hiding document scrollbar behavior as the admin workspace.

### Quality

- Added regression coverage for full-bleed image-only rendering, horizontal desktop/mobile ratios, idle-hidden slider controls, the simplified admin slider editor, and the public auto-hiding root scrollbar.
- Frontend typecheck/build passed and the complete PHP suite passed with 227 tests and 2,314 assertions before release promotion.

### Release

- Stable presentation patch based on v1.5.0. No new database migration is required.
- Production upgrade still requires verified backup/restore-readiness, release artifact checksum validation, Homepage Builder/public slider smoke, public scrollbar smoke, security/health gates, and rollback readiness.

## [1.5.0] - 2026-10-12

### Fixed

- Fixed the blank Homepage Builder page caused by attempting to structured-clone Vue/Inertia Proxy props in the browser.
- Replaced the unsafe clone path with a JSON-safe configuration clone, normalized Hero Slider slide arrays defensively, and added an explicit recovery state instead of a blank screen.
- Hardened the Hero Slider editor with a safe default model so malformed or legacy homepage config cannot crash the admin page.

### Added

- Added a three-slide starter editorial carousel so the public homepage presents a real slider before administrators upload custom images.
- Starter slides remain editable and can be replaced with responsive desktop/mobile images through Homepage Builder.
- Added modern upload guidance for 1920 × 820 desktop and 1080 × 1350 mobile slider images with a centered safe area.
- Added a redesigned dark editorial footer with a reading CTA, site identity, quick navigation, contact details, and privacy-safe aggregate statistics for public books, reader opens, categories, and authors.
- Added short-lived cached footer statistics to avoid repeated aggregate queries on every public request.

### Quality

- Added regression coverage for the builder Proxy-clone failure, non-blank recovery state, starter slider rendering, responsive upload guidance, modern footer structure, and aggregate footer statistics.
- Focused Batch E validation passed with 26 tests and 445 assertions before release promotion.
- Full QA and Browser Smoke passed in GitHub Actions on the Batch E implementation PR.

### Release

- Stable Batch E release based on v1.4.0.
- Production upgrade requires the verified artifact, backup/restore-readiness, starter-slider migration, Homepage Builder asset/runtime checks, public slider/footer smoke, health, security, and rollback gates in docs/PRODUCTION_RELEASE.md.

## [1.4.0] - 2026-10-12

### Changed

- Modernized admin scrollbars into compact rounded overlay-style controls.
- The main admin document scrollbar is visually hidden while idle and revealed briefly during scroll/wheel activity or when the pointer approaches the right edge.
- Sidebar and nested admin overflow areas now use thin transparent-idle scrollbars that appear on hover or keyboard focus.
- Admin scrollbar styling is scoped to the administration workspace so public-reader presentation remains unchanged.

### Accessibility & Quality

- Added forced-colors fallback so operating-system high-contrast scrollbar behavior remains available.
- Preserved grouped/collapsible desktop navigation and mobile admin navigation contracts.
- Added regression coverage for document, sidebar, nested overflow, auto-hide timing, and accessibility fallback behavior.

### Release

- Stable Batch D visual polish release based on v1.3.0.
- Production upgrade requires the verified artifact, backup/restore-readiness, admin-scrollbar visual smoke, health, security, and rollback gates in docs/PRODUCTION_RELEASE.md.

## [1.3.0] - 2026-10-12

### Added

- Added curated Lucide category icons with a searchable visual icon picker in Admin → Master Data → Kategori.
- Added a persisted category icon field with sensible migration defaults for existing categories and a safe BookOpen fallback for unknown/missing values.
- Added category icons to homepage category cards, public category directory cards, category taxonomy headers, and admin category tables.

### Changed

- Redesigned public book cards into a cover-first presentation: category badge and aggregate read count live on the cover, with title/author revealed as an overlay on desktop hover/focus and kept visible on touch/mobile.
- Removed permanent metadata text below public book covers so the cover remains the primary visual element.
- Public category and book-card payloads now include category icon metadata while keeping internal library relationships unchanged.

### Quality

- Added regression coverage for category icon selection/validation, public icon propagation, and the cover-first book-card contract.
- Focused AdminMasterData, PublicLibrary, and PublicBookCard UI tests pass before release promotion; full QA and browser smoke remain mandatory in GitHub Actions.

### Release

- Stable Batch C release based on v1.2.0.
- Production upgrade requires the verified artifact, backup/restore-readiness, category-icon migration, public book-card/category smoke, health, security, and rollback gates in docs/PRODUCTION_RELEASE.md.

## [1.2.0] - 2026-10-12

### Fixed

- Fixed the public catalog/search HTTP 500 caused by a relevance-ranking SQL placeholder and binding mismatch on MariaDB/MySQL.
- Added regression coverage that keeps relevance SQL placeholders and bound parameters in sync.
- Exposed the configured site favicon in the base Inertia document so public, admin, and authentication surfaces use the same favicon.

### Added

- Added a responsive admin-managed Hero Slider with separate recommended desktop 1600 × 900 (16:9) and mobile 1080 × 1350 (4:5) images, up to six slides, safe upload replacement/removal, autoplay interval, navigation arrows, dots, accessible keyboard controls, and reduced-motion support.
- Added reader-based Buku Populer discovery using privacy-safe aggregate reader-open analytics and exposed aggregate read counts in public book card payloads.
- Added distinct visual identities for Buku Terbaru and Buku Populer using different section icons and accent colors.

### Changed

- Standardized public-facing terminology from Ebook to Buku while keeping internal database/model naming stable.
- Reordered the default public homepage to prioritize Hero Slider, Buku Terbaru, Buku Populer, Kategori, and Koleksi; the redundant standalone search section is disabled by default because global/header search remains available.
- Existing customized settings are preserved; the migration changes old wording only when values still equal the previous defaults.

### Release

- Stable post-release correction and homepage feature release based on v1.1.0.
- Production upgrade requires the verified artifact, backup/restore-readiness, migration, health, search smoke, slider/admin smoke, security-header, and rollback gates in docs/PRODUCTION_RELEASE.md.

## [1.1.0] - 2026-10-11

### Changed

- Promoted the complete Modern Editorial Digital Library UI/UX v2 redesign after UX-01 through UX-10 acceptance.
- Public shell, homepage, catalog/discovery, taxonomy, book detail, PDF reader, admin workspace, authentication, PWA, offline, maintenance, and system states now share one semantic design language.
- The interface is intentionally modern, responsive, professional, premium, compact, and consistent across desktop and mobile.

### Accessibility & Quality

- Added automated multi-viewport browser acceptance at 360/390/430/768/1024/1280/1440/1600 px, long-content stress, touch-target checks, accessible-name checks, keyboard-focus checks, reduced-motion verification, and reader portrait/landscape coverage.
- Hardened semantic text contrast, high-contrast/forced-colors behavior, safe-area handling, and coarse-pointer ergonomics.
- Release packaging continues to run the full QA and browser acceptance gates on GitHub-hosted runners before producing a verified artifact.

### Release

- Stable v1.1.0 release. Deployment requires the UX-12 backup, verified artifact, health, smoke, security-header, and rollback acceptance gates in `docs/PRODUCTION_RELEASE.md`.

## [1.1.0-rc.1] - 2026-10-11

### Changed

- Rebuilt the frontend around the Modern Editorial Digital Library direction with a semantic design system shared by public, reader, admin, authentication, PWA, and system states.
- Redesigned public navigation, homepage, catalog/discovery, taxonomy directories, book detail, reader controls, and responsive mobile flows with compact professional hierarchy.
- Redesigned the complete admin workspace including collapsible grouped navigation, dashboard/analytics, ebook management, master data, homepage builder, settings, audit log, and profile/security.
- Unified login, password recovery, maintenance, offline, 404, About, and Contact experiences with the v2 visual language.

### Accessibility & QA

- Hardened semantic text contrast, keyboard focus visibility, reduced-motion, increased-contrast, forced-colors, safe-area, and coarse-pointer touch behavior.
- Added automated Chrome acceptance across 360/390/430/768/1024/1280/1440/1600 px, long-content stress, accessible-name checks, touch targets, keyboard focus, reduced motion, and reader portrait/landscape.
- Kept heavy QA and release packaging on GitHub-hosted runners so production is not used as a build machine.

### Release

- First v1.1.0 release candidate. Production remains on v1.0.2 until the final UX-12 deployment and production acceptance gate passes.

## [1.0.2] - 2026-10-10

### Fixed

- Sanitized release staging after Composer package discovery so installer bootstrap state and other runtime storage files cannot be embedded in distributable ZIP artifacts.
- Added a fail-closed packaging check that rejects any non-placeholder file remaining under `storage/` before checksums and archive creation.

### Release

- Supersedes the v1.0.1 package artifact for deployment. Production should deploy v1.0.2 or newer.

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
