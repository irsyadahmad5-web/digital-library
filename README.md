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

## Project tracking

The implementation roadmap is tracked in Linear. Architecture and UX decisions are documented in Notion and Figma.

## Security

Never commit `.env`, credentials, API keys, storage secrets, private ebook files, or runtime uploads.

Production hardening is enabled automatically when `APP_ENV=production`: debug output is forced off, secure session cookies are required, host validation and HSTS default to enabled, and public/admin HTML receives CSP and browser security headers. Keep `APP_URL` on the canonical HTTPS origin. Use `SECURITY_ALLOWED_HOSTS` only for additional legitimate hosts.

When the application is behind a reverse proxy or tunnel, set `SECURITY_TRUSTED_PROXIES` only to the actual proxy IP/CIDR values so forwarded HTTPS/client-IP headers cannot be spoofed by arbitrary clients. Do not use `*` unless every request is guaranteed to arrive through a trusted proxy. `SECURITY_HSTS_INCLUDE_SUBDOMAINS` and `SECURITY_HSTS_PRELOAD` should remain disabled until every affected subdomain is permanently HTTPS-capable.