#!/bin/bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_DIR="${SCRIPT_DIR}/src"
EXT_DIR="${SCRIPT_DIR}/ext"
CLANG_DIR="${SCRIPT_DIR}/clang"

die() {
    echo "pre-install: $*" >&2
    exit 1
}

if [ -n "${ZEPHIR_BIN:-}" ]; then
    ZEPHIR="$ZEPHIR_BIN"
elif command -v zephir >/dev/null 2>&1; then
    ZEPHIR="$(command -v zephir)"
elif [ -x "$HOME/.composer/vendor/bin/zephir" ]; then
    ZEPHIR="$HOME/.composer/vendor/bin/zephir"
elif [ -x "$HOME/.config/composer/vendor/bin/zephir" ]; then
    ZEPHIR="$HOME/.config/composer/vendor/bin/zephir"
else
    die "Zephir not found. Install via: composer global require phalcon/zephir (or set ZEPHIR_BIN)"
fi

[ -d "$SRC_DIR" ] || die "Missing ${SRC_DIR}/"
[ -d "$CLANG_DIR" ] || die "Missing ${CLANG_DIR}/"

cd "$SCRIPT_DIR"

echo "pre-install: zephir generate"
"$ZEPHIR" generate || die "zephir generate failed"

echo "pre-install: copy src/ -> ext/src/"
mkdir -p "$EXT_DIR"
cp -R "$SRC_DIR" "$EXT_DIR/"

echo "pre-install: copy clang/ -> ext/"
shopt -s nullglob
clang_files=("${CLANG_DIR}/"*)
[ "${#clang_files[@]}" -gt 0 ] || die "No files found in ${CLANG_DIR}/"
cp -R "${clang_files[@]}" "$EXT_DIR/"

echo "pre-install: sync clang/kernel/*.c -> ext/kernel/"
if [ -d "${CLANG_DIR}/kernel" ]; then
    mkdir -p "${EXT_DIR}/kernel"
    kernel_overlay=("${CLANG_DIR}/kernel/"*.c)
    [ "${#kernel_overlay[@]}" -gt 0 ] || die "No kernel overlay files found in ${CLANG_DIR}/kernel/"
    cp -f "${kernel_overlay[@]}" "${EXT_DIR}/kernel/"
fi

echo "pre-install: complete"
