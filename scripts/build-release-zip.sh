#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_ZIP="${1:-cleanlinks.zip}"
RELEASE_DIR="${ROOT_DIR}/.release/package"

cd "${ROOT_DIR}"

if grep -Eq '^[[:space:]]*/?vendor/?[[:space:]]*$' "${ROOT_DIR}/.distignore"; then
	printf 'Release configuration error: .distignore must not exclude the root vendor directory.\n' >&2
	exit 1
fi

if [[ "${OUTPUT_ZIP}" != /* ]]; then
	OUTPUT_ZIP="${ROOT_DIR}/${OUTPUT_ZIP}"
fi

if [ ! -x node_modules/.bin/wp-scripts ]; then
	npm ci
fi

CLEANLINKS_SKIP_WP_POT=1 npm run build

rm -f "${OUTPUT_ZIP}"
rm -rf "${RELEASE_DIR}"
mkdir -p "${RELEASE_DIR}"
trap 'rm -rf "${RELEASE_DIR}"' EXIT

rsync -rc --exclude=/vendor --exclude-from="${ROOT_DIR}/.distignore" "${ROOT_DIR}/" "${RELEASE_DIR}/" --delete --delete-excluded

cp "${ROOT_DIR}/composer.json" "${ROOT_DIR}/composer.lock" "${RELEASE_DIR}/"

composer install \
	--working-dir="${RELEASE_DIR}" \
	--no-dev \
	--no-interaction \
	--optimize-autoloader

rm -rf \
	"${RELEASE_DIR}/composer.json" \
	"${RELEASE_DIR}/composer.lock" \
	"${RELEASE_DIR}/vendor/bin" \
	"${RELEASE_DIR}/vendor/composer/installers"

mkdir -p "$(dirname "${OUTPUT_ZIP}")"

(
	cd "${RELEASE_DIR}"
	zip -qr "${OUTPUT_ZIP}" .
)

php "${ROOT_DIR}/scripts/validate-release-package.php" "${OUTPUT_ZIP}"

printf 'Release package: %s\n' "${OUTPUT_ZIP}"
