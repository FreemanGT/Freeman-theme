# Feature flags

Every roadmap item that changes runtime behavior ships behind a feature flag (CLAUDE.md hard rule #1). This doc defines the convention.

## Naming

```
freeman_core_<module>_<feature>_enabled
```

- `<module>` — snake_case module slug (e.g. `sliders`, `infinite_scroll`, `restock_notify`).
- `<feature>` — snake_case feature slug (e.g. `advanced_controls`, `trigger_modes`).
- ASCII only. No spaces, no hyphens.

Example: `freeman_core_sliders_advanced_controls_enabled`.

## Default

Flags default to `false`. Code paths must assume OFF until the option is explicitly set. Callers therefore never need to seed the option on activation.

## Reading a flag

```php
use Freeman\Core\Core\Feature_Flags;

if ( Feature_Flags::is_enabled( 'sliders', 'advanced_controls' ) ) {
    // ...new behavior...
}
```

`is_enabled()` returns a strict `bool`. Boolean parsing is explicit:

| Stored value | Result |
|---|---|
| missing, `false`, `0`, `'0'`, `''`, `'false'`, `'no'`, `'off'` | `false` |
| `true`, `1`, `'1'`, `'true'`, `'yes'`, `'on'` | `true` |
| anything else (e.g. `'banana'`) | `false` |

This avoids the `(bool) 'false' === true` footgun and matches WP-CLI's usual `0`/`1` convention.

## Where to call it

At the **top of feature entry points**, before any side effect (enqueue, hook registration, output). The OFF path must produce byte-identical output to the pre-flag baseline.

## Enabling and disabling

```bash
# Enable:
wp option update freeman_core_sliders_advanced_controls_enabled 1

# Rollback (disable):
wp option update freeman_core_sliders_advanced_controls_enabled 0
```

The rollback command is what every PR's "Rollback plan" section refers to.

## Programmatic override (filter)

Each flag exposes a dynamic filter mirroring WordPress's `option_{$option}` pattern:

```
freeman_core/feature_flag/{$module}/{$feature}
```

Useful for forcing a flag ON in `wp-config.php`-loaded mu-plugins (staging, dev) without DB writes:

```php
add_filter( 'freeman_core/feature_flag/sliders/advanced_controls', '__return_true' );
```

Listeners receive `( bool $enabled, string $module, string $feature )`.

## Active flags

| Flag | Default | Gates |
|---|---|---|
| `freeman_core_tools_settings_import_enabled` | `false` | Settings → Tools → Import form (Wave 0.3). Export, backup listing, and restore are ungated so rollback works even after disabling import. |
| `freeman_core_variation_swatches_preselect_timing_fix_enabled` | `false` | Wave 4.4. Defers the archive→PDP preselect apply until after `wc_variation_form()` binds, so WC's `found_variation` event fires and the gallery image swaps to the chosen variation. OFF preserves the legacy synchronous-apply behavior. |

## Settings export/import notes (Wave 0.3)

- Envelope shape: `{ "version": 1, "exported_at": "<ISO>", "site_url": "<url>", "options": { ... } }`. Only `version: 1` is accepted; every other version is rejected with no write and no backup.
- `site_url` is **decorative metadata only** — used to label the export and the backup rows in the admin UI. It is not validated and not enforced. Cross-site imports (staging → prod) work as long as both sides run a compatible plugin version. If a future operational concern needs enforcement, a follow-up PR adds an opt-in filter; do not enforce by default.
- **Import is not atomic.** Writes happen in deterministic key-sorted order. On the first `update_option()` returning false, the loop halts; options written before the failure point remain. The auto-backup created pre-write is the rollback path — restore from it.
- Auto-backup fires **after validation, before first write**. Failed/rejected imports never consume a backup slot. Last 5 are kept in a non-autoloaded option; oldest is dropped on overflow.

## What doesn't need a flag

Per CLAUDE.md hard rule #1, the additive exception covers:

- A new hook or filter (no behavior change without a listener).
- A new CSS variable that has a backward-compatible fallback.
- A new helper class with no callers (added in Wave 0.2 — purely infrastructure).

Anything else: gate it.
