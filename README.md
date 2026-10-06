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

The installer checks PHP 8.3+, required extensions/functions, writable paths, the Vite build, and Poppler (`pdfinfo` / `pdftocairo`). It only accepts MySQL or MariaDB and refuses to install into a database that already contains tables. The database user must be able to create, alter, index, and drop tables.

After the database test succeeds, the installer writes a production-safe `.env` atomically, generates the permanent `APP_KEY`, and keeps only non-secret database metadata in its pending-state file. The final step runs migrations and seeders, creates `public/storage`, creates the first Super Admin, updates the library name, writes `storage/app/installed.lock`, removes the temporary installer key, and closes the installer. Database passwords and the admin password are never written to the installer state file.

If the site is behind a reverse proxy or tunnel, enter only the real proxy IP/CIDR values in the trusted-proxy field. Do not use wildcard trust. HTTPS should be configured before installation so the installer can enable secure cookies and HSTS correctly.

## Project tracking

The implementation roadmap is tracked in Linear. Architecture and UX decisions are documented in Notion and Figma.

## Security

Never commit `.env`, credentials, API keys, storage secrets, private ebook files, or runtime uploads.

Production hardening is enabled automatically when `APP_ENV=production`: debug output is forced off, secure session cookies are required, host validation and HSTS default to enabled, and public/admin HTML receives CSP and browser security headers. Keep `APP_URL` on the canonical HTTPS origin. Use `SECURITY_ALLOWED_HOSTS` only for additional legitimate hosts.

When the application is behind a reverse proxy or tunnel, set `SECURITY_TRUSTED_PROXIES` only to the actual proxy IP/CIDR values so forwarded HTTPS/client-IP headers cannot be spoofed by arbitrary clients. Do not use `*` unless every request is guaranteed to arrive through a trusted proxy. `SECURITY_HSTS_INCLUDE_SUBDOMAINS` and `SECURITY_HSTS_PRELOAD` should remain disabled until every affected subdomain is permanently HTTPS-capable.