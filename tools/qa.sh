#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"

step() {
    printf '\n==> %s\n' "$1"
}

step "Composer metadata"
"$COMPOSER_BIN" validate --strict --no-interaction

step "PHP formatting"
vendor/bin/pint --test

step "PHP test suite"
"$PHP_BIN" artisan test --stop-on-failure

step "Release artifact/toolchain verification"
"$PHP_BIN" artisan quality:verify

step "Laravel cache compilation"
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan view:clear

step "Dependency security audits"
"$COMPOSER_BIN" audit --no-interaction
"$NPM_BIN" audit --audit-level=low

step "Frontend typecheck and production build"
"$NPM_BIN" run typecheck
"$NPM_BIN" run build

step "Service worker syntax"
node --check public/sw.js

step "Tracked secret-pattern scan"
if git grep -nE     'APP_KEY=base64:|BEGIN (RSA|OPENSSH|EC|DSA) PRIVATE KEY|AWS_SECRET_ACCESS_KEY=[^$]'     -- . ':!README.md' ':!.env.example'
then
    echo "Potential tracked secret pattern found." >&2
    exit 1
fi

step "Whitespace integrity"
git diff --check HEAD

printf '\nStage 25 QA gate: PASS\n'
