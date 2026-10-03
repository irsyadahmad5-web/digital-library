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