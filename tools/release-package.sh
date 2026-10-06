#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VERSION="$(tr -d '[:space:]' < VERSION)"
OUTPUT_DIR="${OUTPUT_DIR:-$ROOT/dist}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
SKIP_QA="${SKIP_QA:-0}"

if [[ ! "$VERSION" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(-[0-9A-Za-z.-]+)?$ ]]; then
    echo "Invalid VERSION: $VERSION" >&2
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Release packaging requires a clean Git working tree." >&2
    exit 1
fi

for command in git tar zip sha256sum rsync "$COMPOSER_BIN" "$NPM_BIN" "$PHP_BIN"; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Required command not found: $command" >&2
        exit 1
    fi
done

if [[ "$SKIP_QA" != "1" ]]; then
    "$COMPOSER_BIN" qa
    "$COMPOSER_BIN" qa:browser
fi

"$NPM_BIN" run build

COMMIT="$(git rev-parse HEAD)"
SHORT_COMMIT="$(git rev-parse --short=12 HEAD)"
GENERATED_AT="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
NAME="digital-library-v$VERSION"
TMP="$(mktemp -d -t digital-library-release-XXXXXX)"
STAGE="$TMP/$NAME"

cleanup() {
    rm -rf "$TMP"
}
trap cleanup EXIT INT TERM

mkdir -p "$STAGE" "$OUTPUT_DIR"

git archive --format=tar HEAD | tar -xf - -C "$STAGE"
rsync -a --delete public/build/ "$STAGE/public/build/"

(
    cd "$STAGE"
    "$COMPOSER_BIN" install         --no-dev         --prefer-dist         --optimize-autoloader         --no-interaction         --no-progress
)

cat > "$STAGE/RELEASE.json" <<JSON
{
    "name": "Digital Library",
    "version": "$VERSION",
    "channel": "stable",
    "commit": "$COMMIT",
    "short_commit": "$SHORT_COMMIT",
    "generated_at": "$GENERATED_AT",
    "php": "$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
}
JSON

if [[ -e "$STAGE/.env" || -e "$STAGE/.git" || -e "$STAGE/node_modules" ]]; then
    echo "Release staging contains forbidden runtime/development files." >&2
    exit 1
fi

if [[ ! -f "$STAGE/vendor/autoload.php" || ! -f "$STAGE/public/build/manifest.json" ]]; then
    echo "Release staging is missing vendor autoload or Vite build." >&2
    exit 1
fi

(
    cd "$STAGE"
    find . -type f         ! -name 'RELEASE_FILES.sha256'         -print0         | sort -z         | xargs -0 sha256sum > RELEASE_FILES.sha256
)

ARCHIVE="$OUTPUT_DIR/$NAME.zip"
CHECKSUM="$ARCHIVE.sha256"

rm -f "$ARCHIVE" "$CHECKSUM"

(
    cd "$TMP"
    zip -qr "$ARCHIVE" "$NAME"
)

(
    cd "$OUTPUT_DIR"
    sha256sum "$(basename "$ARCHIVE")" > "$(basename "$CHECKSUM")"
)

echo "Release package created:"
echo "  $ARCHIVE"
echo "  $CHECKSUM"
echo "  version=$VERSION commit=$COMMIT"
