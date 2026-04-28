#!/usr/bin/env bash
# Freeman Digital — build script
# Produces: dist/freeman-digital-<VERSION>.zip
#
# Responsibilities:
#   1. Run `php -l` across every PHP file in the plugin.
#   2. Simulated activation smoke test (require all class files; check core classes exist).
#   3. Optionally minify admin.js and admin.css if terser/cleancss are available.
#   4. Assemble a clean distribution zip that EXCLUDES tests/, .github/, tools/, node_modules/, and dev files.
#
# Usage:
#   bash tools/build-freeman-digital.sh
#
# Exits non-zero on any failure.

set -euo pipefail

# ---------- config ----------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_SLUG="freeman-digital"
DIST_DIR="${PLUGIN_DIR}/dist"
BUILD_DIR="${DIST_DIR}/_build"
STAGE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

# ---------- helpers ---------------------------------------------------------
log() { printf "\033[1;34m[build]\033[0m %s\n" "$*"; }
err() { printf "\033[1;31m[build:error]\033[0m %s\n" "$*" >&2; exit 1; }

command -v php  >/dev/null 2>&1 || err "php is required"
command -v zip  >/dev/null 2>&1 || err "zip is required"

# ---------- detect version from plugin header ------------------------------
VERSION="$(awk -F': ' '/^ \* Version:/ {print $2; exit}' "${PLUGIN_DIR}/${PLUGIN_SLUG}.php" | tr -d '[:space:]')"
[[ -n "${VERSION}" ]] || err "Could not detect Version: header in ${PLUGIN_SLUG}.php"
log "Plugin version: ${VERSION}"

# ---------- step 1: php lint -------------------------------------------------
log "Running php -l on every PHP file…"
lint_errors=0
while IFS= read -r -d '' f; do
    out="$(php -l "$f" 2>&1 || true)"
    if [[ "$out" != *"No syntax errors detected"* ]]; then
        echo "LINT FAIL: $f"
        echo "$out"
        lint_errors=$((lint_errors + 1))
    fi
done < <(find "${PLUGIN_DIR}" \
            -path "${PLUGIN_DIR}/dist" -prune -o \
            -path "${PLUGIN_DIR}/node_modules" -prune -o \
            -type f -name "*.php" -print0)
[[ "${lint_errors}" -eq 0 ]] || err "${lint_errors} file(s) failed php -l"
log "php -l: all files clean"

# ---------- step 2: activation-sim ------------------------------------------
log "Running activation simulation…"
php -d display_errors=1 -r "
define('ABSPATH', '/tmp/fake-abspath/');
define('WPINC',  'wp-includes');
define('WP_DEBUG', true);

// Minimal stubs so class files can parse under isolation.
function add_action(\$h, \$c, \$p = 10, \$a = 1) {}
function add_filter(\$h, \$c, \$p = 10, \$a = 1) {}
function register_activation_hook(\$f, \$c) {}
function register_deactivation_hook(\$f, \$c) {}
function plugin_dir_path(\$f) { return dirname(\$f) . '/'; }
function plugin_dir_url(\$f)  { return 'http://example.test/wp-content/plugins/freeman-digital/'; }
function plugin_basename(\$f) { return 'freeman-digital/freeman-digital.php'; }
function wp_doing_cron() { return false; }
function is_admin() { return false; }
function load_plugin_textdomain(\$a = null, \$b = null, \$c = null) {}

\$base = '${PLUGIN_DIR}/includes/';
foreach (glob(\$base . '*.php') as \$inc) {
    require_once \$inc;
}

\$required = array('FD_Core','FD_Admin','FD_Database','FD_Autoload','FD_Indexes',
    'FD_Query_Optimizer','FD_Profiler','FD_Woocommerce','FD_Frontend',
    'FD_Activity_Log','FD_Admin_Cache');
foreach (\$required as \$c) {
    if (!class_exists(\$c)) { fwrite(STDERR, \"MISSING CLASS: \$c\n\"); exit(1); }
}
fwrite(STDOUT, \"activation-sim: all required classes loaded\n\");
"
log "activation-sim: ok"

# ---------- step 3: stage the build -----------------------------------------
log "Staging build in ${STAGE_DIR}…"
rm -rf "${BUILD_DIR}"
mkdir -p "${STAGE_DIR}"

rsync -a \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude 'tests/' \
  --exclude 'tools/' \
  --exclude 'dist/' \
  --exclude 'node_modules/' \
  --exclude 'phpunit.xml*' \
  --exclude 'phpcs.xml*' \
  --exclude '.phpunit.result.cache' \
  --exclude '*.log' \
  --exclude '.DS_Store' \
  "${PLUGIN_DIR}/" "${STAGE_DIR}/"

# ---------- step 4: optional minification -----------------------------------
if command -v terser >/dev/null 2>&1; then
    log "Minifying admin.js with terser…"
    terser "${STAGE_DIR}/assets/js/admin.js" \
        -c passes=2 -m \
        -o "${STAGE_DIR}/assets/js/admin.js"
else
    log "terser not found — shipping admin.js unminified"
fi

if command -v cleancss >/dev/null 2>&1; then
    log "Minifying admin.css with cleancss…"
    cleancss -o "${STAGE_DIR}/assets/css/admin.css" "${STAGE_DIR}/assets/css/admin.css"
else
    log "cleancss not found — shipping admin.css unminified"
fi

# ---------- step 5: package zip ---------------------------------------------
ZIP_PATH="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"
rm -f "${ZIP_PATH}"
( cd "${BUILD_DIR}" && zip -qr "${ZIP_PATH}" "${PLUGIN_SLUG}" )
log "Built: ${ZIP_PATH}"

log "Zip contents summary:"
unzip -l "${ZIP_PATH}" | tail -n 20

log "Done."
