#!/bin/bash

# Installer for the pcurl extension on macOS.
# - Resolves PHP binary and extension dir correctly (handles Herd, asdf, etc.)
# - Generates C sources via Zephir, patches them for PHP 8.4 / clang compatibility,
#   then compiles and installs manually.
# - Re-signs the .so with an ad-hoc signature so macOS amfid accepts it on first load.

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EXTENSION_NAME="pcurl"
BUILD_SO="${SCRIPT_DIR}/ext/modules/${EXTENSION_NAME}.so"
LOG_FILE="${SCRIPT_DIR}/build.log"

if [ "${EUID:-$(id -u)}" -ne 0 ]; then
    SUDO="sudo"
else
    SUDO=""
fi

die()  { echo ""; echo "❌ $*"; exit 1; }
step() { echo "$*"; }
ok()   { echo "   ✓ $*"; }

show_failure_logs() {
    if [ -f "$LOG_FILE" ]; then
        echo ""
        echo "---- Last 80 lines of ${LOG_FILE} ----"
        tail -80 "$LOG_FILE" || true
    fi
}

reset_ext_build_tree() {
    local ext_dir="${SCRIPT_DIR}/ext"
    local item used_sudo=0

    [ -d "$ext_dir" ] || return 0

    for item in \
        "$ext_dir/build" \
        "$ext_dir/autom4te.cache" \
        "$ext_dir/modules" \
        "$ext_dir/.libs" \
        "$ext_dir/kernel/.libs" \
        "$ext_dir/pcurl/.libs" \
        "$ext_dir/src/.libs" \
        "$ext_dir/Makefile" \
        "$ext_dir/config.status" \
        "$ext_dir/config.h" \
        "$ext_dir/configure" \
        "$ext_dir/libtool" \
        "$ext_dir/config.cache" \
        "$ext_dir/run-tests.php"
    do
        [ -e "$item" ] || continue
        if rm -rf "$item" 2>/dev/null; then
            continue
        fi
        [ -n "$SUDO" ] || return 1
        $SUDO rm -rf "$item" || return 1
        used_sudo=1
    done

    if ! find "$ext_dir" \( -name '*.dep' -o -name '*.lo' -o -name '*.la' \) -delete 2>/dev/null; then
        [ -n "$SUDO" ] || return 1
        $SUDO find "$ext_dir" \( -name '*.dep' -o -name '*.lo' -o -name '*.la' \) -delete 2>/dev/null || return 1
        used_sudo=1
    fi

    if [ "$used_sudo" -eq 1 ] && [ -n "$SUDO" ]; then
        $SUDO chown -R "$(id -u)":"$(id -g)" "$ext_dir" 2>/dev/null || true
    fi
}

echo "=========================================="
echo " pcurl Extension Installer (macOS)"
echo "=========================================="
echo ""

# ── Preflight ────────────────────────────────────────────────────────────────
step "🔎 Preflight checks..."
# PHP_BIN picks the build to install into (e.g. PHP_BIN=/opt/homebrew/opt/php@8.4-zts/bin/php).
PHP="${PHP_BIN:-php}"
command -v "$PHP" >/dev/null 2>&1 || die "${PHP} not found"

if [ -n "${ZEPHIR_BIN:-}" ]; then
    ZEPHIR="$ZEPHIR_BIN"
elif command -v zephir >/dev/null 2>&1; then
    ZEPHIR="$(command -v zephir)"
elif [ -x "$HOME/.composer/vendor/bin/zephir" ]; then
    ZEPHIR="$HOME/.composer/vendor/bin/zephir"
elif [ -x "$HOME/.config/composer/vendor/bin/zephir" ]; then
    ZEPHIR="$HOME/.config/composer/vendor/bin/zephir"
else
    die "Zephir not found. Install via: composer global require phalcon/zephir  (or set ZEPHIR_BIN)"
fi
ok "Found zephir: $ZEPHIR"

PHP_VER_MM="$("$PHP" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_BIN_REAL="$("$PHP" -r 'echo PHP_BINARY;' 2>/dev/null)"
PHP_BIN_DIR="$(dirname "$PHP_BIN_REAL")"

# Resolve php-config: check next to the PHP binary first (versioned names too),
# then fall back to PATH.  The resolved path must not contain spaces because
# autoconf configure scripts cannot handle them, even when properly quoted.
_PHP_VER_NODOT="${PHP_VER_MM//./}"   # e.g. "84"
RESOLVED_PHP_CONFIG=""
for _candidate in \
    "${PHP_BIN_DIR}/php-config${_PHP_VER_NODOT}" \
    "${PHP_BIN_DIR}/php-config" \
    "$(command -v "php-config${_PHP_VER_NODOT}" 2>/dev/null || true)" \
    "$(command -v php-config 2>/dev/null || true)"
do
    if [ -x "$_candidate" ]; then
        RESOLVED_PHP_CONFIG="$_candidate"
        break
    fi
done
[ -n "$RESOLVED_PHP_CONFIG" ] || die "Could not locate php-config. Install php-dev or set PHP_CONFIG."

PHP_EXT_DIR="$("$RESOLVED_PHP_CONFIG" --extension-dir)"
[ -n "$PHP_EXT_DIR" ] || die "Could not determine PHP extension dir."

CLI_SCAN_DIR="$("$PHP" --ini 2>/dev/null | awk -F': ' '/Scan for additional \.ini files in:/{print $2}' || true)"
if [ -n "$CLI_SCAN_DIR" ] && [ "$CLI_SCAN_DIR" != "(none)" ] && [ -d "$CLI_SCAN_DIR" ] && \
   ls "$CLI_SCAN_DIR"/*.so >/dev/null 2>&1; then
    PHP_EXT_DIR="$CLI_SCAN_DIR"
fi

if [ -w "$PHP_EXT_DIR" ] && { [ -z "$CLI_SCAN_DIR" ] || [ ! -d "$CLI_SCAN_DIR" ] || [ -w "$CLI_SCAN_DIR" ]; }; then
    SUDO=""
fi

ok "PHP version:   ${PHP_VER_MM}"
ok "PHP binary:    ${PHP_BIN_REAL}"
ok "Extension dir: ${PHP_EXT_DIR}"

# pcurl hands ext-curl's CURLM* straight to libcurl, so it must link the very
# libcurl the PHP binary already loaded — never a second copy (e.g. /usr/lib vs Homebrew).
command -v pkg-config >/dev/null 2>&1 || die "pkg-config not found (brew install pkgconf)"
PHP_LIBCURL="$(otool -L "$PHP_BIN_REAL" 2>/dev/null | awk '/libcurl/{print $1; exit}' || true)"
PHP_LIBCURL="${PHP_LIBCURL/#@loader_path/$(dirname "$PHP_BIN_REAL")}"
if [ -n "$PHP_LIBCURL" ] && [ -d "$(dirname "$PHP_LIBCURL")/pkgconfig" ]; then
    export PKG_CONFIG_PATH="$(dirname "$PHP_LIBCURL")/pkgconfig${PKG_CONFIG_PATH:+:$PKG_CONFIG_PATH}"
elif command -v brew >/dev/null 2>&1 && [ -d "$(brew --prefix curl 2>/dev/null)/lib/pkgconfig" ]; then
    export PKG_CONFIG_PATH="$(brew --prefix curl)/lib/pkgconfig${PKG_CONFIG_PATH:+:$PKG_CONFIG_PATH}"
fi
pkg-config --exists libcurl || die "libcurl.pc not found. Set PKG_CONFIG_PATH to the libcurl your PHP links."
"$PHP_BIN_REAL" -m 2>/dev/null | grep -x curl >/dev/null || die "ext-curl is not loaded in ${PHP_BIN_REAL}; pcurl needs it."
ok "PHP libcurl:   ${PHP_LIBCURL:-<shared ext>} (pkg-config libcurl $(pkg-config --modversion libcurl))"
export CFLAGS="${CFLAGS:-} -Wno-error -Wno-error=incompatible-pointer-types -Wno-pointer-compare"
export CPPFLAGS="${CPPFLAGS:-} -Wno-error -Wno-error=incompatible-pointer-types"
echo ""

# ── Zephir ───────────────────────────────────────────────────────────────────
cd "${SCRIPT_DIR}"

step "🧹 reset ext/ build tree..."
if ! reset_ext_build_tree; then
    die "Could not reset ${SCRIPT_DIR}/ext build artifacts (likely root-owned from a prior sudo build). Run: sudo rm -rf ext/build ext/autom4te.cache ext/modules ext/.libs && sudo chown -R \$(id -un):\$(id -gn) ext/"
fi
ok "ext/ build tree reset"
echo ""

step "🧹 zephir fullclean..."
if ! "$ZEPHIR" fullclean >"$LOG_FILE" 2>&1; then
    show_failure_logs
    die "zephir fullclean failed. See ${LOG_FILE}."
fi
ok "zephir fullclean complete"
echo ""


step "📄 zephir stubs..."
if ! "$ZEPHIR" stubs >>"$LOG_FILE" 2>&1; then
    show_failure_logs
    die "zephir stubs failed. See ${LOG_FILE}."
fi
ok "zephir stubs complete"
echo ""

# zephir stubs re-runs generate, so it must precede pre-install or it clobbers the clang/ overlays.
step "📦 pre-install (generate, src/, clang/)..."
if ! bash "${SCRIPT_DIR}/pre-install.sh" >>"$LOG_FILE" 2>&1; then
    show_failure_logs
    die "pre-install.sh failed. See ${LOG_FILE}."
fi
ok "pre-install complete"

for required_file in \
    "${SCRIPT_DIR}/ext/kernel/file.c" \
    "${SCRIPT_DIR}/ext/kernel/require.c" \
    "${SCRIPT_DIR}/ext/kernel/main.c"
do
    [ -f "$required_file" ] || die "Missing required kernel overlay file: ${required_file}"
done
ok "clang kernel overlays are present in ext/kernel/"
echo ""

# ── Compile ───────────────────────────────────────────────────────────────────
step "   Compiling..."
cd "${SCRIPT_DIR}/ext"
PHPIZE="$(dirname "$RESOLVED_PHP_CONFIG")/phpize"
[ -x "$PHPIZE" ] || PHPIZE="phpize"
if ! "$PHPIZE" >>"$LOG_FILE" 2>&1; then
    show_failure_logs
    die "phpize failed. See ${LOG_FILE}."
fi
if ! ./configure --enable-pcurl "--with-php-config=${RESOLVED_PHP_CONFIG}" >>"$LOG_FILE" 2>&1; then
    show_failure_logs
    die "configure failed. See ${LOG_FILE}."
fi
if ! make -j"$(sysctl -n hw.logicalcpu 2>/dev/null || echo 2)" >>"$LOG_FILE" 2>&1; then
    show_failure_logs
    die "make failed. See ${LOG_FILE}."
fi
cd "${SCRIPT_DIR}"

if [ ! -f "$BUILD_SO" ]; then
    show_failure_logs
    die "Build output not found at ${BUILD_SO}."
fi
ok "Build complete"
echo ""

# ── Install .so ───────────────────────────────────────────────────────────────
step "📦 Installing binary..."
$SUDO mkdir -p "$PHP_EXT_DIR"
$SUDO cp -f "$BUILD_SO" "${PHP_EXT_DIR}/${EXTENSION_NAME}.so"
$SUDO chmod 755 "${PHP_EXT_DIR}/${EXTENSION_NAME}.so"

# Re-sign after cp: macOS Apple Silicon invalidates the linker code signature
# when the file is copied.  Without this, dlopen() triggers SIGKILL on first load.
$SUDO codesign --force --sign - "${PHP_EXT_DIR}/${EXTENSION_NAME}.so"
sleep 2  # allow amfid to finish validating the freshly codesigned binary
ok "Copied to: ${PHP_EXT_DIR}/${EXTENSION_NAME}.so"
echo ""

# ── Enable across SAPIs ───────────────────────────────────────────────────────
step "⚙️  Enabling extension..."
declare -a CONF_DIR_CANDIDATES=()

if [ -n "${CLI_SCAN_DIR:-}" ] && [ "$CLI_SCAN_DIR" != "(none)" ] && [ -d "$CLI_SCAN_DIR" ]; then
    CONF_DIR_CANDIDATES+=("$CLI_SCAN_DIR")
fi
for d in "/etc/php/${PHP_VER_MM}/cli/conf.d" "/etc/php/${PHP_VER_MM}/fpm/conf.d"; do
    [ -d "$d" ] && CONF_DIR_CANDIDATES+=("$d")
done

CONF_DIRS=()
while IFS= read -r _line; do
    CONF_DIRS+=("$_line")
done < <(printf "%s\n" "${CONF_DIR_CANDIDATES[@]:-}" | awk '!seen[$0]++')

[ "${#CONF_DIRS[@]}" -eq 0 ] && echo "   ⚠️  No conf.d directories found."

INI_NAME="40-${EXTENSION_NAME}.ini"
INI_CONTENT="extension=${PHP_EXT_DIR}/${EXTENSION_NAME}.so"
for confd in "${CONF_DIRS[@]:-}"; do
    INI_PATH="${confd}/${INI_NAME}"
    echo "$INI_CONTENT" | $SUDO tee "$INI_PATH" >/dev/null
    ok "Written: $INI_PATH"
done
echo ""

# ── Verify ────────────────────────────────────────────────────────────────────
step "🔍 Verifying installation (CLI)..."
VERIFIED=0
for attempt in 1 2 3 4 5; do
    if "$PHP_BIN_REAL" -r 'exit(class_exists("Pcurl\\Multi", false) ? 0 : 1);' 2>/dev/null; then
        ok "Pcurl\\Multi is available in CLI"
        VERIFIED=1
        break
    fi
    sleep 1
done
if [ "$VERIFIED" -eq 0 ]; then
    MODULE_LIST="$("$PHP_BIN_REAL" -m 2>&1 || true)"
    printf "%s\n" "$MODULE_LIST" | grep -i "${EXTENSION_NAME}\|error\|warn\|killed" || true
    "$PHP_BIN_REAL" --ini 2>/dev/null | grep -E "Scan for additional|Additional \.ini" || true
    die "Pcurl\\\\Multi not available after install. Check ${INI_NAME} and build.log."
fi
echo ""

step "=========================================="
step " Extension Information (CLI)"
step "=========================================="
"$PHP_BIN_REAL" --ri "${EXTENSION_NAME}" || true
echo ""

echo "✅  Installation complete!"
echo ""
echo "File locations:"
echo "  • Binary: ${PHP_EXT_DIR}/${EXTENSION_NAME}.so"
for d in "${CONF_DIRS[@]:-}"; do
    echo "  • Config: ${d}/${INI_NAME}"
done
echo ""
