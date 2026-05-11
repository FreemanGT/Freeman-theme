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

## Admin page

`Freeman → Feature Flags` (added Wave 5.1, 1.11.44) lists every flag from `Feature_Flags::registry()` as a checkbox grouped by module, each with its description, the equivalent `wp option update …` line, the introducing version, and a "shared switch" marker where one flag drives several sub-features. Saving posts to `admin-post.php` (action `freeman_save_feature_flags`, nonce + `manage_woocommerce`) and writes each registry flag to `1` or `0` — a flag's checkbox absent from the POST means "off". It writes nothing else; it does not run module activation routines or flush rewrites.

A flag whose effective state is **forced by a `freeman_core/feature_flag/...` filter** renders disabled, with a note — the DB option can't override a code filter, so the page reflects the effective state rather than letting you write a value the filter ignores. The save handler skips those flags too.

The page is unconditional infra (like the Dashboard and Tools pages); it is not itself behind a flag.

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

The list below is the long-form reference. The short-form canonical list — flag, label, one-line "gates" description, introducing version, and a "shared" marker — lives in code as `Freeman\Core\Core\Feature_Flags::registry()`, which also drives the **Freeman → Feature Flags** admin page (see below). When the two disagree, the registry is authoritative; a PHPUnit test (`FeatureFlagsAdminTest`) keeps the registry in sync with the actual `is_enabled()` call sites in `src/`.

| Flag | Default | Gates |
|---|---|---|
| `freeman_core_tools_settings_import_enabled` | `false` | Settings → Tools → Import form (Wave 0.3). Export, backup listing, and restore are ungated so rollback works even after disabling import. |
| `freeman_core_sliders_advanced_controls_enabled` | `false` | Wave 3.2a (1.11.29, CategorySlider) + 3.2b (1.11.30, ProductSlider) — shared flag. Flag ON: autoplay / loop / dots-or-progress indicator-selector controls on both Elementor slider widgets (on ProductSlider, additionally gated on `display_mode = slider` — autoplay/loop/indicator are meaningless in grid mode, so grid output is unchanged). The indicator selector supersedes the legacy `show_progress` switcher via a back-compat shim. Flag OFF + flag-ON-with-default-settings are byte-identical to pre-3.2. |
| `freeman_core_cheapest_variation_strategy_enabled` | `false` | Wave 3.3 (1.11.32). Flag ON: a `cheapest` / `first_in_stock` strategy selector for which variation gets pre-selected — global setting (default `cheapest`), per-product post-meta override `_freeman_cheapest_variation_strategy`, and the `freeman_core/cheapest_variation/strategy` filter (filter is final word; out-of-enum values fall back with a Logger warning). Flag OFF: the original hardcoded cheapest path verbatim. Default setting on a flag-ON site = byte-identical to flag-OFF. |
| `freeman_core_infinite_scroll_trigger_modes_enabled` | `false` | Wave 3.1a (1.11.33) + 3.1b (1.11.35) — shared flag. Flag ON: trigger-mode (observer / load-more button / hybrid), history-mode and container-selector settings; the JS dispatcher gates at `attachObserver` entry and does a post-`loadNext` threshold check; the PHP wrapper render path plus its before/after-render actions, `should_render_wrapper` predicate and `container_selector` filter. Flag OFF (and flag-ON with default settings) are byte-identical to pre-3.1. |
| `freeman_core_restock_notify_csv_export_enabled` | `false` | Wave 4.1b (1.11.38). Flag ON: an "Export Subscribers" submenu under the legacy `restock-notify` parent menu + the matching `admin_post_*` listener; the form (cap check then nonce, per WP convention; `manage_woocommerce`) streams the full `rsn_subscribers` table as UTF-8-BOM CSV, 9 columns matching the 4.1a Privacy exporter labels, filename `restock-notify-subscribers-YYYY-MM-DD.csv`. Defense-in-depth: flag OFF means neither the submenu nor the listener attaches. (The GDPR exporter/eraser registered in 4.1a is **not** gated — privacy hooks are a platform contract, OS-5 decision.) |
| `freeman_core_variation_swatches_auto_color_enabled` | `false` | Wave 2.2 / 4d (1.11.27) **+ 4e (1.11.28)** — shared flag. Flag ON activates the auto-color sampler pipeline: `Color_Sampler` (modal-with-edge-filter sampling via GD with Imagick auto-upgrade, stores hex as variation post-meta `_freeman_core_vs_sampled_color`) + `Sampler_Scheduler` (sample-on-save via `woocommerce_save_product_variation`, pre-warm on flag-flip via batched WP-Cron — first-of-kind cron precedent for freeman-core, default batch size 50, filterable via `freeman_core/variation_swatches/sampler_prewarm_batch_size`, self-reschedules until queue empty). Cache invalidation handlers cover `_thumbnail_id` change, variation deletion, and attachment deletion. Hot-path lazy fallback retained as safety net for variations created via direct DB writes / import tools that bypass the save hook. **4d alone produces no frontend behavior change** — the pipeline runs and caches but no swatch reads the cached value until 4e wires it into the render path. Shared-flag design is intentional: shipping 4d with the flag ON in production before 4e is harmless. Flag OFF: scheduler listeners are still registered (cheap option-read at hook entry) but bail before doing real work; sampler is never called. |
| `freeman_core_variation_swatches_tooltip_enabled` | `false` | Wave 2.2 / 4c (1.11.25). Flag ON: hover tooltip on color and image swatches in both shop picker and PDP buy-box. Default tooltip text is the term name; admins can override per-term via the `freeman_core_variation_swatches_term_tooltip_text` term-meta key (input added to the existing taxonomy term-edit screen, next to color and image fields). Pure CSS via `data-tooltip` attribute + `:hover::after`, mirroring the existing `data-default-text` pattern; no JS. Text-button swatches deliberately skipped (label is inline). Per-option `tt` field added to the prepared-data payload (always emitted when flag is ON, empty when no override). Flag flip is implicit in the prepared-data transient cache key. Flag OFF: no `tt` field emitted, no `data-tooltip` attributes rendered, no admin UI. Byte-identical to pre-1.11.25. |
| `freeman_core_variation_swatches_image_swatches_enabled` | `false` | Wave 2.2 / 4b (1.11.24). Flag ON: per-attribute-term swatch image upload (Iconic/WPC pattern). Each term gets a `freeman_core_variation_swatches_term_image_id` term-meta entry storing the attachment ID. Image wins over color when both are set; color is the fallback. The legacy WC term-edit screen gains a media-uploader pair (button + thumbnail preview + remove link), `wp_enqueue_media()` enqueued only on `'edit-tags.php' / 'term.php'` screens. Both shop picker (`legacy/templates/shop-variation-pick.php`) and PDP buy-box (`legacy/templates/variation-buy-box.php`) render image branches when an option's `img` URL is non-empty; class names `.etucart-shop-pick__opt-img` and `.etucart-swatch__img`, round shape hardcoded to match existing color circles. One additive filter: `freeman_core/variation_swatches/term_image_url`. Flag OFF: image-meta is never read or written, term-edit screen has no upload UI, payload omits the `img` field per option (byte-identical to pre-1.11.24). Flag flip is implicit in the prepared-data transient cache key (same trick as 4f). |
| `freeman_core_variation_swatches_card_image_swap_enabled` | `false` | Wave 2.2 / 4f (1.11.23). Flag ON: shop-listing swatch click swaps the card's main image to the matching variation's image; shopper stays on the listing (no nav, no PDP, no Quick View). The PHP-side payload (`prepare_product_data()` in `legacy/class-archive.php`) emits per-variation `image_src` / `image_srcset` / `image_sizes`; the JS-side handler (`refreshCardImage()` in `etucart-shop-swatches.js`) finds the card image via the `EtucartShopVS.cardImageSelector` localize value and swaps in/out, restoring originals when the picker returns to an unresolved state. Two filters: `freeman_core/variation_swatches/card_image_selector` (CSS selector) and `freeman_core/variation_swatches/card_image_payload` (per-variation image payload). Flag OFF: payload byte-identical to pre-1.11.23 (no image fields), JS no-ops. Flag flip is implicit in the prepared-data transient cache key, so flipping the option immediately rebuilds payloads without an explicit cache-bust. |
| `freeman_core_variation_swatches_bundle_compat_enabled` | `false` | Wave 4.5 (1.11.40). Flag gates three Freeman-shipped code paths: (1) the VariationSwatches add-to-cart JS payload-build branch — flag-ON forwards all form fields via `serializeArray()` to WC's `wc-ajax=add_to_cart` endpoint (with a denylist of WP/WC nonces), so WPC Product Bundles (`woosb-ids-*`) and WPC FBT (`woobt_ids`) hidden inputs reach the cart; flag-OFF retains the legacy `product_id`/`quantity`/`attribute_*` whitelist payload byte-identical to pre-1.11.40; (2) the `window.FreemanCoreVSFlags = { bundleCompat: <bool> }` JS global emitted via `Module::inject_feature_flags()` on `wp_enqueue_scripts` priority 10001 (the boolean itself reflects flag state); (3) the `woobt_added_to_cart` → `wc_fragment_refresh` document-body bridge for FBT's "Add All" path, bound at DOM-ready only when the global is true. Separately and not gated (Hard Rule #1 additive exception): a single `do_action( 'woocommerce_before_add_to_cart_button' )` in `legacy/templates/variation-buy-box.php` inside `.etucart-actions`. Standard WC hook, no Freeman-shipped listener attaches; the injection point exists regardless of flag state so bundle/FBT plugins can inject their hidden inputs (without it, plugins have nowhere to inject and flag-ON forwarding has nothing to forward). Single-line legacy edit, Hard Rule #3 exception approved at `docs/roadmap.md` off-roadmap Wave 4.5 entry, mirrors the same hook position in `legacy/templates/simple-buy-box.php`. |

## Retired flags

| Flag | Retired in | What happened |
|---|---|---|
| `freeman_core_variation_swatches_settings_hub_enabled` | Wave 2.2 / 4g (1.11.45) — introduced 4a (1.11.21) | The Freeman → Variation Swatches page graduated to being the **sole** editing surface for the 14 swatch settings: `Module::settings_schema()` now always returns the keys (no flag gate) and `Settings_Reader::get()` always prefers the new `freeman_core_variation_swatches_*` key with the legacy `etucart_vs_*` key as a permanent fallback (§4.5). The legacy WooCommerce → Settings → Products → "Shop swatches" section is soft-deprecated (kept registered so the URL resolves; renders a one-line "moved" notice). The `freeman_core_variation_swatches_settings_hub_enabled` option is **not deleted** but is now ignored — except by the one-shot 1.11.45 re-sync migration (`Migrations::resync_variation_swatches_settings_from_legacy()`), which reads it once to decide whether to copy current `etucart_vs_*` values onto the new keys (done on sites where the flag was OFF — the default; skipped where it was ON). |

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
