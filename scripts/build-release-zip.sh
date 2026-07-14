#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_ZIP="${1:-cleanlinks.zip}"
RELEASE_DIR="${ROOT_DIR}/.release/package"

cd "${ROOT_DIR}"

if [ ! -f vendor/autoload.php ]; then
	composer install --no-dev --optimize-autoloader
else
	composer dump-autoload --no-dev --optimize
fi

if [ ! -x node_modules/.bin/wp-scripts ]; then
	npm ci
fi

CLEANLINKS_SKIP_WP_POT=1 npm run build

rm -f "${OUTPUT_ZIP}"
rm -rf "${RELEASE_DIR}"
mkdir -p "${RELEASE_DIR}"

rsync -rc --exclude-from="${ROOT_DIR}/.distignore" "${ROOT_DIR}/" "${RELEASE_DIR}/" --delete --delete-excluded

(
	cd "${RELEASE_DIR}"
	zip -qr "${OUTPUT_ZIP}" .
)

if [[ "${OUTPUT_ZIP}" != /* ]]; then
	mv "${RELEASE_DIR}/${OUTPUT_ZIP}" "${ROOT_DIR}/${OUTPUT_ZIP}"
fi
