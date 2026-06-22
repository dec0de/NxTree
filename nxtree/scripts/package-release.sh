#!/usr/bin/env sh
set -eu

APP_ID="nxtree"
VERSION="$(grep -m1 '<version>' "${APP_ID}/appinfo/info.xml" | sed -E 's/.*<version>([^<]+)<\/version>.*/\1/')"
BUILD_DIR="build"
ARCHIVE="${BUILD_DIR}/${APP_ID}-${VERSION}.tar.gz"

rm -rf "${BUILD_DIR}/${APP_ID}" "${ARCHIVE}"
mkdir -p "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}/${APP_ID}"

tar \
  --exclude='.git' \
  --exclude='.DS_Store' \
  --exclude='build' \
  --exclude='*.key' \
  --exclude='*.csr' \
  --exclude='*.crt' \
  -cf - "${APP_ID}" | tar -xf - -C "${BUILD_DIR}"

cp README.md "${BUILD_DIR}/${APP_ID}/README.md"

tar -czf "${ARCHIVE}" -C "${BUILD_DIR}" "${APP_ID}"

printf '%s\n' "Created ${ARCHIVE}"
