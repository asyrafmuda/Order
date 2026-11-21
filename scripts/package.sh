#!/usr/bin/env bash
#
# Builds the ZIP that is uploaded to shared hosting:
#   dist/<name>-<version>.zip        upload and extract this
#   dist/PANDUAN-PEMASANGAN.html     the installation guide for the client
#
# The ZIP holds everything the server needs — PHP packages (without the
# development ones), built JavaScript and CSS — and nothing private: no .env,
# no logs, no uploaded files. It never contains a .env, so extracting a newer
# version over a running site keeps that site's settings.
#
# Usage: scripts/package.sh            (name defaults to "order-system")
#        PACKAGE_NAME=kedai scripts/package.sh
#        ALLOW_UNLICENSED=1 scripts/package.sh   (a ZIP without the license check)

set -euo pipefail
cd "$(dirname "$0")/.."

# macOS: no "._" resource-fork files in archives.
export COPYFILE_DISABLE=1

VERSION=$(tr -d '[:space:]' < VERSION)
NAME=${PACKAGE_NAME:-order-system}
DIST=dist
BUILD=$DIST/build
ZIP=$DIST/$NAME-$VERSION.zip

step() { printf '\n\033[1m→ %s\033[0m\n' "$1"; }

step "Version $VERSION"

# A ZIP for customers must carry your public key, or it runs without a license.
if grep -q "'public_key' => ''," config/licensing.php; then
    if [ "${ALLOW_UNLICENSED:-}" != "1" ]; then
        echo "Licensing is not set up: run 'php artisan license:keygen --server=https://your-license-server' first." >&2
        echo "(Or ALLOW_UNLICENSED=1 $0 for a ZIP without the license check.)" >&2
        exit 1
    fi
    echo "  ! Building WITHOUT the license check (ALLOW_UNLICENSED=1)."
else
    echo "  License server: $(grep -o "LICENSE_SERVER_URL', '[^']*'" config/licensing.php | cut -d"'" -f3)"
fi

# Start from nothing, so no file from an earlier build can end up in this one.
rm -rf "${BUILD:?}" "${ZIP:?}"
mkdir -p "$BUILD"

step "Copying the source (everything git tracks or would track, nothing it ignores)"
git ls-files -z --cached --others --exclude-standard \
    | xargs -0 -I{} sh -c 'test -e "{}" && printf "%s\0" "{}"' \
    | tar --null -T - -cf - | tar -xf - -C "$BUILD"

# The license server stays with you: customers get only the license check.
rm -rf "${BUILD:?}"/app/LicenseServer "${BUILD:?}"/database/migrations/license-server "${BUILD:?}"/resources/js/pages/Admin/Licenses "${BUILD:?}"/resources/js/pages/Admin/Releases

# Development-only files the server does not need.
rm -rf "${BUILD:?}"/{tests,.github,scripts,packaging,dist} \
       "${BUILD:?}"/{phpunit.xml,pint.json,.editorconfig,.gitattributes,CLAUDE.md,eslint.config.js,.prettierrc,.prettierignore}

step "Building JavaScript and CSS"
npm run build --silent
rm -f public/hot
rm -rf "${BUILD:?}/public/build" "${BUILD:?}/public/hot"
mkdir -p "$BUILD/public/build"
cp -R public/build/. "$BUILD/public/build/"
cmp -s public/build/manifest.json "$BUILD/public/build/manifest.json" || { echo "The built assets were not copied correctly." >&2; exit 1; }

step "PHP packages without the development ones"
# Copy what is installed (without git metadata), then let Composer remove
# the development packages and write an optimised autoloader.
tar --exclude='.git' -cf - vendor | tar -xf - -C "$BUILD"
(
    cd "$BUILD"
    COMPOSER_DISCARD_CHANGES=true composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
)

step "Clearing anything local"
rm -f "$BUILD"/.env "$BUILD"/storage/app/installed.json "$BUILD"/database/*.sqlite "$BUILD"/public/storage
rm -f "$BUILD"/bootstrap/cache/{config,routes-v7,events,services}.php
find "$BUILD/storage" -type f ! -name '.gitignore' -delete
# Folders Laravel writes to must exist (git keeps them with .gitignore files).
for dir in storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
    mkdir -p "$BUILD/$dir"
done
test -f "$BUILD/bootstrap/cache/packages.php" || { echo "packages.php was not generated" >&2; exit 1; }

step "Shared hosting extras"
cp packaging/root.htaccess "$BUILD/.htaccess"
cp packaging/root-index.php "$BUILD/index.php"

step "Listing every file with its checksum (for one-click updates)"
php scripts/release-manifest.php "$BUILD" "$VERSION"

step "Zipping"
(cd "$BUILD" && zip -qr -X "../$(basename "$ZIP")" . -x '*.DS_Store' -x '*/._*')
cp packaging/PANDUAN-PEMASANGAN.html "$DIST/PANDUAN-PEMASANGAN.html"

printf '\n\033[32m✓ %s (%s)\033[0m\n' "$ZIP" "$(du -h "$ZIP" | cut -f1)"
printf '  sha256 %s\n' "$( (command -v sha256sum >/dev/null && sha256sum "$ZIP" || shasum -a 256 "$ZIP") | cut -d' ' -f1)"
printf '  guide  %s\n' "$DIST/PANDUAN-PEMASANGAN.html"
