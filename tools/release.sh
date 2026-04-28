#!/usr/bin/env bash
#
# Stamp a new version, update CHANGELOGs, build zips.
#
# Usage:
#   bash tools/release.sh theme 1.0.1 "Fix RTL regression in product grid"
#   bash tools/release.sh core  1.0.1 "Fix parse error in Module_Registry docblock"
#   bash tools/release.sh both  1.0.1 "Coordinated bump"
#
# After running, the dist/ zips will be named with the new version, every
# CHANGELOG.md will have a new entry stamped, and build.sh runs automatically.

set -euo pipefail

ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
TODAY="$(date +%Y-%m-%d)"

SCOPE="${1:-}"
VERSION="${2:-}"
NOTE="${3:-}"

if [[ -z "${SCOPE}" || -z "${VERSION}" ]]; then
    echo "Usage: bash tools/release.sh {theme|core|both} <version> \"<changelog note>\""
    exit 1
fi

if [[ -z "${NOTE}" ]]; then
    NOTE="Untitled change"
fi

# --- version stampers -------------------------------------------------------

bump_theme() {
    local style="${ROOT}/freeman-theme/style.css"
    sed -i.bak -E "s/^([[:space:]]*Version:[[:space:]]*).*/\1${VERSION}/" "${style}" && rm "${style}.bak"
    echo "freeman-theme/style.css → Version: ${VERSION}"
}

bump_core() {
    local main="${ROOT}/freeman-core/freeman-core.php"
    # 1. Plugin header.
    sed -i.bak -E "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*/\1${VERSION}/" "${main}" && rm "${main}.bak"
    # 2. FREEMAN_CORE_VERSION constant.
    sed -i.bak -E "s/(define\([[:space:]]*'FREEMAN_CORE_VERSION'[[:space:]]*,[[:space:]]*')[^']+('[[:space:]]*\))/\1${VERSION}\2/" "${main}" && rm "${main}.bak"
    # 3. Plugin::VERSION class const.
    local plugin_cls="${ROOT}/freeman-core/src/Core/Plugin.php"
    if [[ -f "${plugin_cls}" ]]; then
        sed -i.bak -E "s/(const VERSION[[:space:]]*=[[:space:]]*')[^']+(';)/\1${VERSION}\2/" "${plugin_cls}" && rm "${plugin_cls}.bak"
    fi
    echo "freeman-core: header + FREEMAN_CORE_VERSION + Plugin::VERSION → ${VERSION}"
}

# --- changelog prepender ----------------------------------------------------
# Inserts a new "## [VERSION] — DATE\n\n- NOTE\n" block just after the first
# top-level heading (line starting with "# ") of the given file.

prepend_changelog() {
    local file="$1"
    local label="$2"
    if [[ ! -f "${file}" ]]; then
        return
    fi

    local tmp
    tmp="$(mktemp)"
    # Insert the new entry just before the first "## [" heading (so it sits
    # above prior versions but after the file intro). If no "## [" exists yet,
    # append at end.
    awk -v ver="${VERSION}" -v date="${TODAY}" -v note="${NOTE}" '
        BEGIN { inserted = 0 }
        {
            if ( !inserted && /^## \[/ ) {
                print "## [" ver "] — " date
                print ""
                print "- " note
                print ""
                inserted = 1
            }
            print
        }
        END {
            if ( !inserted ) {
                print ""
                print "## [" ver "] — " date
                print ""
                print "- " note
            }
        }
    ' "${file}" > "${tmp}"
    mv "${tmp}" "${file}"
    echo "${file} → new entry ${VERSION}"
}

# --- scope dispatch ---------------------------------------------------------

case "${SCOPE}" in
    theme)
        bump_theme
        prepend_changelog "${ROOT}/freeman-theme/CHANGELOG.md" "theme"
        prepend_changelog "${ROOT}/CHANGELOG.md"               "theme ${VERSION}"
        ;;
    core)
        bump_core
        prepend_changelog "${ROOT}/freeman-core/CHANGELOG.md"  "core"
        prepend_changelog "${ROOT}/CHANGELOG.md"               "core ${VERSION}"
        ;;
    both)
        bump_theme
        bump_core
        prepend_changelog "${ROOT}/freeman-theme/CHANGELOG.md" "theme"
        prepend_changelog "${ROOT}/freeman-core/CHANGELOG.md"  "core"
        prepend_changelog "${ROOT}/CHANGELOG.md"               "theme+core ${VERSION}"
        ;;
    *)
        echo "Unknown scope: ${SCOPE}"
        exit 1
        ;;
esac

# --- build ------------------------------------------------------------------

bash "${ROOT}/tools/build.sh" all

echo
echo "Release artifacts ready in dist/."
echo "  $(ls -1 "${ROOT}/dist/")"
