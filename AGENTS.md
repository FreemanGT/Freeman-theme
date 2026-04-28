# Project Rules — Freeman Theme

These rules are binding for any AI agent working in this repo.

## Release policy (non-negotiable)

Every change that touches **code, assets, translations, templates, or templates that end up in a shipped zip** must:

1. **Bump a version.** Use SemVer:
   - bugfix / docs only → patch bump (`1.0.0` → `1.0.1`)
   - backward-compatible feature / new module → minor (`1.0.1` → `1.1.0`)
   - breaking change / removed hook / option key rename → major (`1.x` → `2.0.0`)
2. **Update the CHANGELOG** in the package that changed:
   - `freeman-core/CHANGELOG.md` for Core changes
   - `freeman-theme/CHANGELOG.md` for theme changes
   - Root `CHANGELOG.md` always gets the aggregated entry
   - Use `Added / Changed / Fixed / Removed / Security` headings (Keep-a-Changelog style)
3. **Rebuild the zips.** `dist/freeman-core-<new-version>.zip` (and bundle) must carry the new version in the filename. Delete the old matching zip so `dist/` never contains a stale release for the same semver slot.
4. **Run the preflight.** `tools/build.sh` automatically runs `php -l` on every PHP file and `tools/activation-sim.php` before zipping; never ship a zip whose preflight failed.

### How to do all four in one command

```bash
# core-only fix (most common)
bash tools/release.sh core 1.0.2 "Short human-readable note about the change"

# theme-only change
bash tools/release.sh theme 1.1.0 "Added new design token for gold accent"

# coordinated bump
bash tools/release.sh both 2.0.0 "Breaking: renamed Swatches option keys to freeman_core_variation_swatches_*"
```

`release.sh` does the version stamping (plugin header + `FREEMAN_CORE_VERSION` + `Plugin::VERSION` + `style.css`), prepends the changelog entry, and runs `build.sh all`.

### What counts as a change
| change type | needs version bump? |
|---|---|
| PHP / JS / CSS edit in a shipped file | yes |
| Adding / removing a module | yes (minor at least) |
| Translation update (.po / .pot) | yes (patch) |
| `tools/*` edit that only affects dev workflow | no (but log in root CHANGELOG under "Internal") |
| `README.md` / `AGENTS.md` edit | no (not shipped) |
| Adding a test / smoke helper | no |

### Don't
- Don't ship two zips with the same version number and different contents. Pick a new patch number.
- Don't prepend a changelog entry dated in the past or future — the release script uses `date +%Y-%m-%d` for a reason.
- Don't edit `dist/` zips by hand. They are build artifacts. Rebuild via `tools/build.sh` or `tools/release.sh`.
- Don't hand-bump only one of the three Core version strings. Use `tools/release.sh core <version>` so all three stay in sync (plugin header, `FREEMAN_CORE_VERSION`, `Plugin::VERSION`).

## File / naming conventions

- Modules live at `freeman-core/src/Modules/<PascalCase>/Module.php` and implement `Freeman\Core\Core\Module_Interface`.
- Legacy code ported from a standalone plugin lives under `freeman-core/src/Modules/<Module>/legacy/`. The `Module::boot()` method is responsible for defining any legacy constants (`ETUCART_VS_DIR`, `RSN_PLUGIN_URL`, …) *before* requiring legacy class files.
- Assets under a module: `src/Modules/<Module>/assets/{css,js,images}/`. URL is built via `$this->asset_url( 'css/foo.css' )` in `Module_Base`.
- Option keys are `freeman_core_<module_id>_<setting>` *unless* the module intentionally preserves a legacy key for zero-downtime migration (restock = `rsn_*`, swatches = `etucart_vs_*`). This is documented in each module's `HOOKS.md`.
- **Elementor widget names are frozen.** `Widget::get_name()` returns the underscored short form `freeman_<module>_slider` (e.g. `freeman_category_slider`, `freeman_product_slider`) rather than the canonical `freeman-core-<module>-slider`. This deviates from the kebab convention by design: Elementor persists the widget name into every saved page's data structure, so renaming silently orphans every existing instance. **Do not rename `get_name()` returns.** The asset handles registered for those widgets DO follow the canonical `freeman-core-*` form (since handles are not persisted).
- Translations: `.pot` in `languages/`, `.po` per locale, compiled `.mo` committed alongside. `tools/build.sh` recompiles `.mo` from `.po` at release time so the shipped zip always has the freshest translations.

## Testing before a release

1. `php -l` on every PHP file — handled by `tools/build.sh` preflight.
2. `php tools/smoke.php` — instantiates every module and every importer, checks interface conformance.
3. `php tools/activation-sim.php` — loads the plugin via the real entry point, runs `Plugin::on_activate()` and `Plugin::boot()` against stubbed WP. Fails hard on parse errors, missing classes, and fatal throwables at activation time.
4. `bash tools/build.sh all` — runs (1) + (3) and then zips.

If any of the above fails, **do not proceed to ship**. Fix, bump (see release policy), re-run.

## Git hygiene (when the project becomes a git repo)

- Every bump should be one commit: `release: <scope> <version> — <one-line note>`.
- Tag: `git tag <scope>-<version>` (e.g. `core-1.0.1`, `theme-1.1.0`, `bundle-1.0.1`).
- Never force-push to main. Never squash a release commit away; the changelog references the bump by version.
