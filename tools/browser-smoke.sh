#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-php}"
PORT="${QA_BROWSER_PORT:-8765}"
CHROME_BIN="${CHROME_BIN:-}"

if [[ -z "$CHROME_BIN" ]]; then
    for candidate in google-chrome google-chrome-stable chromium chromium-browser; do
        if command -v "$candidate" >/dev/null 2>&1; then
            CHROME_BIN="$(command -v "$candidate")"
            break
        fi
    done
fi

if [[ -z "$CHROME_BIN" ]]; then
    echo "Chrome/Chromium is required for browser smoke testing." >&2
    exit 2
fi

TMP_DIR="$(mktemp -d -t digital-library-qa-XXXXXX)"
DB_FILE="$TMP_DIR/qa.sqlite"
SERVER_LOG="$TMP_DIR/server.log"
SERVER_PID=""

cleanup() {
    if [[ -n "$SERVER_PID" ]]; then
        kill "$SERVER_PID" >/dev/null 2>&1 || true
        wait "$SERVER_PID" >/dev/null 2>&1 || true
    fi

    rm -rf "$TMP_DIR"
}
trap cleanup EXIT INT TERM

touch "$DB_FILE"

export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_FILE"
export APP_URL="http://127.0.0.1:$PORT"
export SECURITY_ENFORCE_HOST=false

"$PHP_BIN" artisan migrate:fresh --seed --force --no-interaction >/dev/null

"$PHP_BIN" artisan serve     --host=127.0.0.1     --port="$PORT"     >"$SERVER_LOG" 2>&1 &
SERVER_PID=$!

ready=false
for _ in $(seq 1 40); do
    if curl -fsS "http://127.0.0.1:$PORT/" >/dev/null 2>&1; then
        ready=true
        break
    fi
    sleep 0.25
done

if [[ "$ready" != true ]]; then
    cat "$SERVER_LOG" >&2 || true
    echo "QA server did not become ready." >&2
    exit 1
fi

for path in / /library /admin/login /manifest.webmanifest /offline.html /sw.js; do
    code="$(curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT$path")"

    if [[ "$code" != "200" ]]; then
        echo "$path returned HTTP $code" >&2
        exit 1
    fi
done

CHROME_ARGS=(
    --headless=new
    --no-sandbox
    --disable-gpu
    --hide-scrollbars
    --virtual-time-budget=4000
    --user-data-dir="$TMP_DIR/chrome"
)

"$CHROME_BIN" "${CHROME_ARGS[@]}"     --window-size=1440,1000     --dump-dom     "http://127.0.0.1:$PORT/"     >"$TMP_DIR/home-desktop.html" 2>/dev/null

grep -q 'Baca lebih nyaman' "$TMP_DIR/home-desktop.html"

"$CHROME_BIN" "${CHROME_ARGS[@]}"     --window-size=390,844     --screenshot="$TMP_DIR/home-mobile.png"     "http://127.0.0.1:$PORT/"     >/dev/null 2>&1

"$CHROME_BIN" "${CHROME_ARGS[@]}"     --window-size=390,844     --dump-dom     "http://127.0.0.1:$PORT/admin/login"     >"$TMP_DIR/admin-mobile.html" 2>/dev/null

grep -q 'Masuk ke Admin' "$TMP_DIR/admin-mobile.html"

echo "Browser smoke: PASS (desktop public + mobile public/admin)"
