# Wave 2.2 master plan — VariationSwatches migration + image swatches + tooltip + auto-color + card-image-swap

**Date approved**: 2026-05-03
**Owner**: Yiftach
**Roadmap item**: #4 (P0)
**Reflects decisions in**: [/docs/decisions-2026-04-28.md](decisions-2026-04-28.md) §4.5 (legacy migration), §4.1 (competitor-parity tension — see §1 below)
**Branch off**: `main` post-1.11.20
**Sub-PRs**: 6 (ship order: `4a → 4f → 4b → 4c → 4d → 4e`)

This document is the source of truth for Wave 2.2's six sub-PRs. Each sub-PR's pre-flight cites this plan instead of re-litigating cross-cutting concerns (hard rule #3 legacy/ touches, decision dependencies, version-skew model, read-shim contract).

---

## 1. Context

Wave 2.2 is Roadmap item #4: migrate VariationSwatches off `etucart_vs_*` option keys and the legacy WC settings tab into a Settings_Hub-backed admin page, while adding feature work the audit identified (image swatches, tooltip) and three feature additions surfaced during the 2026-05-03 pre-flight (auto-color sampler, auto-color wiring, variation-image-on-card swap).

**§4.1 tension (called out for posterity)**: §4.1 of the decisions doc says *"Drop competitor-parity features unless a specific client asks."* Auto-color sampling (4d/4e) and click-to-swap-card-image (4f) are arguably modern-Iconic/WPC parity features. Approved despite §4.1 because:

1. The infrastructure 4a ships (read-shim, term/post-meta plumbing, JSON-payload extensibility in `class-archive.php`) makes 4d–4f cheap follow-ons rather than greenfield modules. The marginal cost is low.
2. 4f addresses a known UX gap on shop archives — picking a swatch on a card today does nothing visible. Even without competitor framing, that's a reasonable polish.
3. 4d/4e are gated behind an explicit feature flag, off by default. Sites that don't enable it pay nothing.

If a future review revisits §4.1 and decides 4d–4f were a mistake, they can be removed without disturbing 4a–4c (the migration backbone). 4d/4e share a flag and revert as one unit; 4f is independent.

**Repository state context (2026-05-03)**: Main is at 1.11.20. PR #19 (1.11.19, slider edge-fade tweak) was admin-merged with red CI on the PHP 7.4 / 8.0 lint lanes — not because the change was unsafe, but because a pre-existing latent issue in `tests/snapshots/SnapshotTestCaseTest.php:20` (`0o755` PHP 8.1+ octal literal, introduced by Wave 0.5) was blocking the lint step on those lanes. PR #20 (1.11.20) fixed that ~5 minutes later. The PHP 7.4 PHPUnit lane still fails on a separate pre-existing issue (`str_starts_with`/`str_contains` calls scattered through shipped code) — that gets its own infra PR to drop PHP 7.4 from the supported matrix and is **not a Wave 2.2 dependency**.

---

## 2. Pre-flight checks

### Decision dependencies

- **§4.5 — legacy code sunset**: this wave **is** the §4.5 migration plan. Additive options, read-shim, migrate-don't-delete, no hard sunset.
- **§4.1 — internal-only positioning**: tension noted above.
- **§4.2, §4.3, §4.4, §4.6, §4.7, §4.8**: N/A.

### Wave-0 prerequisites

| Item | Status | Verified at |
|---|---|---|
| 0.0 PHPUnit | ✓ | `phpunit.xml.dist`, 187 tests / 156 methods |
| 0.1 Logger hooks | ✓ | `freeman_core/logger/entry`, `freeman_core/logger/written` |
| 0.2 Feature_Flags | ✓ | [Feature_Flags.php:27](../freeman-core/src/Core/Feature_Flags.php#L27) |
| 0.3 Settings export/import | ✓ | Tools admin page |
| 0.4 Regression baseline | ✓ | `/tests/baseline-*.txt` |
| 0.5 Snapshot harness | ✓ | `/tests/snapshots/` |

### Hard-rule check (CLAUDE.md §"Hard rules")

| # | Rule | This plan's compliance |
|---|---|---|
| 1 | Feature flag, default `false`, per roadmap item | Each sub-PR has its own flag (4d+4e share one — see §6). All default `false`. 4f's two new hooks are purely additive (additive exception). |
| 2 | No removal of existing surfaces | All 4 existing legacy filters preserved verbatim (`etucart_vs_color_swatch_markup`, `etucart_vs_sizes_markup`, `etucart_vs_buy_box_enabled`, `etucart_vs_shop_picker_enabled`). Existing `freeman_core/swatches/*` filters preserved. `freeman_core_variation_swatches_shop_add_to_cart_gate` preserved. Legacy WC settings URL keeps resolving (legacy admin page coexists with new). `etucart_vs_*` option keys preserved (read-shim). |
| 3 | No `legacy/` edits without a written migration plan + approval | **This plan is the rule #3 obligation.** Per-sub-PR legacy/ file list in §4. |
| 4 | One roadmap item per PR (waiver allowed if explicit) | All six sub-PRs are within Roadmap #4. Each declares the split in its description (precedent: 1.1a/b, 2.3a/b/c). |
| 5 | No major version bump | Patch bumps only. |
| 6 | No DB schema changes | None planned. Term meta and post meta are existing WP storage; no `dbDelta`. |
| 7 | Logger stays `final`, no interface | Untouched. |
| 8 | Use `Freeman\Core\Core\Logger`, no `error_log`/`var_dump`/`console.log` | All new logging via Logger. |
| 9 | Roadmap update in same PR | This master-plan PR updates `/docs/roadmap.md` with the scope expansion (not a shipped-marker — that's per-sub-PR). Each sub-PR ships its own roadmap update marking that sub-PR shipped. Wave 2.2's parent shipped-marker lands with the last sub-PR (4e per ship order). |

### File/module ceiling

Each sub-PR is projected under 12 files, all within VariationSwatches + minor Settings_Hub passthrough (4a) or shop-archive payload extension (4f). Re-checked at each sub-PR's pre-flight; if any crosses the ceiling I stop and ask (per "no self-waiver" rule).

---

## 3. Roadmap delta (explicit scope expansion)

`/docs/roadmap.md` previously listed Wave 2.2 with three sub-PRs:

> *split into 4a (settings migration), 4b (image swatches), 4c (tooltip), each its own PR*

This master plan **expands the scope** to six sub-PRs. The additions:

- **4d — Auto-color sampler** (sampling pipeline + caching as variation post-meta; no frontend behavior change in 4d alone)
- **4e — Auto-color fallback wiring** (renders sampled color in the swatch render path when manual term-meta color is unset)
- **4f — Variation-image-on-card swap** (shop-listing swatch click swaps the card's main image to the matching variation's image; shopper stays on the listing)

This expansion is roadmap-of-record edit, not silent drift. The roadmap PR (this PR) reflects the new sub-PR list. §4.1 tension noted above; the audit (`/docs/audit-2026-04-28.md`) **does not** cover 4d/4e/4f — those came from the pre-flight, not the audit.

---

## 4. Hard-rule #3 blanket coverage — `legacy/` files touched per sub-PR

This master plan + the 2026-05-03 written approval discharges hard rule #3 (no `legacy/` edits without a written migration plan and human approval) for every legacy/ file listed below. Sub-PR pre-flights reference this section by anchor instead of re-asking.

**Pre-flight correction (2026-05-03, during 4a's sub-PR pre-flight)**: the original table below assumed every settings read was a literal `get_option('etucart_vs_*')` call scattered across five legacy files. Pre-flight grep showed reality is different: zero callsites use literal `etucart_vs_*` strings — all reads go through three static helpers on `Etucart_VS_Settings` (`bool`, `max_visible`, `excluded_category_ids`) plus one direct `get_option(Etucart_VS_Settings::OPT_PDP_HIDE_OOS, ...)` call in `class-plugin.php`. The cleanest intercept is inside the helpers, not at scattered callsites. The table below is the corrected version. `class-archive.php`, `class-frontend.php`, `class-ajax.php`, and `class-admin.php` drop off 4a's row. 4b/4c/4e become first-touchers of `class-frontend.php`; 4f becomes the first-toucher of `class-archive.php`.

| Sub-PR | Legacy file(s) touched | Nature of edit |
|---|---|---|
| 4a | `legacy/includes/class-settings.php` | Modify three helper-method bodies (`bool`, `max_visible`, `excluded_category_ids`) to delegate to `Settings_Reader::get()` instead of calling `get_option()` directly. **Reads only.** Writes still go to legacy keys via the existing legacy WC settings page (preserved). |
| 4a | `legacy/includes/class-plugin.php` | Replace the one direct `get_option(Etucart_VS_Settings::OPT_PDP_HIDE_OOS, ...)` call with `Settings_Reader::get(...)`. The accompanying `update_option` for the activation backfill stays as-is (writes go to legacy). |
| 4b | `legacy/includes/class-frontend.php` *(first touched in 4b)* | Add image-thumbnail render branch when an attribute term has an image. Sealed against 4a's actual term-meta key shape. |
| 4c | `legacy/includes/class-frontend.php` *(already touched in 4b — extending)* | Add tooltip markup wrapper around swatch. Sealed against 4a's actual schema. |
| 4d | none | 4d is sampling pipeline + caching only. No legacy/ touches. |
| 4e | `legacy/includes/class-frontend.php` *(already touched in 4b/4c — extending)* | Add color-resolution branch: manual term-meta wins, else read sampled post-meta, else neutral gray fallback. |
| 4f | `legacy/includes/class-archive.php` *(first touched in 4f)* | Extend `prepare_product_data()` JSON payload with per-variation `image_src` / `image_srcset` / `image_sizes`. Verify during execution whether already partially present. |

**Counter-list (legacy/ files NOT touched anywhere in Wave 2.2)**: `legacy/includes/class-ajax.php`, `legacy/includes/class-admin.php`, every legacy CSS file, every legacy JS file, every legacy template, and `legacy/etucart-init.php`. (The original table speculatively listed `class-ajax.php` and `class-admin.php` under 4a; pre-flight grep confirmed neither file has any settings reads — they drop off the touched-list entirely.)

---

## 5. Sub-PR breakdown

### 5.1 Sub-PR 4a — Settings migration to Settings_Hub *(full sub-plan)*

**Branch**: `wave-2.2-4a-settings-migration` (off main)
**Flag**: `freeman_core_variation_swatches_settings_hub_enabled` (default `false`)
**Depends on**: nothing (this is the foundation)

**Files (10, all under 12-file ceiling)**:

- `freeman-core/src/Modules/VariationSwatches/Module.php` — populate `settings_schema()` for the new admin page (returns full schema when flag ON, empty array when flag OFF so the page disappears from the Settings_Hub menu); existing `legacy_settings_url()` left as-is (legacy WC settings page coexists indefinitely).
- `freeman-core/src/Modules/VariationSwatches/Settings_Reader.php` *(new)* — single class, static `::get(string $legacy_key, $default = null)` reader implementing the flag-gated read-shim.
- `freeman-core/src/Core/Migrations.php` — add a version-gated one-shot block (`migrate_to_1_11_21` → `migrate_variation_swatches_settings_to_hub`) that copies legacy → new where new is unset. Inline in `Migrations.php` to match the existing `migrate_to_1_9_0` precedent — no separate Migrator class.
- `freeman-core/src/Modules/VariationSwatches/legacy/includes/class-settings.php` — modify three helper-method bodies (`bool`, `max_visible`, `excluded_category_ids`) to delegate to `Settings_Reader::get()`. Reads only; writes still go to legacy keys via the legacy WC settings page.
- `freeman-core/src/Modules/VariationSwatches/legacy/includes/class-plugin.php` — replace one direct `get_option(Etucart_VS_Settings::OPT_PDP_HIDE_OOS, ...)` call (line 90) with `Settings_Reader::get()`. The accompanying `update_option` activation backfill stays as-is.
- `tests/VariationSwatchesSettingsReaderTest.php` *(new)* — read-shim cases: flag-OFF returns legacy, flag-ON new-wins, flag-ON legacy-fallback, flag-ON default-fallback, null-safety.
- `tests/VariationSwatchesSettingsMigrationTest.php` *(new)* — exercise the migration via `Migrations::run` with `version_compare` manipulation: copies legacy → new only when new is unset, idempotent, never deletes legacy, runs at most once per install.
- `tests/VariationSwatchesSettingsHubSnapshotTest.php` *(new)* — flag-OFF: existing legacy rendering byte-identical; flag-ON: Freeman → Variation Swatches page schema renders.
- `docs/roadmap.md` — mark 4a shipped.
- `docs/feature-flags.md` — add row to Active flags table.
- `CLAUDE.md` — version sync (1.11.21) and PHPUnit count update.

**Eleven files** — under the 12-file ceiling. Per master-plan precedent (1.1a/b, 2.3a–c) and the way the §5.1 list above is constructed, mechanical version-bump artifacts (`freeman-core/freeman-core.php` header, `Plugin::VERSION` constant, `freeman-core/CHANGELOG.md`, root `CHANGELOG.md`) are produced by `tools/release.sh` and are not counted toward the per-PR ceiling — otherwise every code-changing PR would lose 4 files of substantive headroom. Re-verified at this pre-flight.

**Read-shim contract** (`Settings_Reader::get(string $legacy_key, $default = null)`):

```
get($legacy_key, $default = null):
  if NOT Feature_Flags::is_enabled('variation_swatches', 'settings_hub'):
    # Flag OFF (P1 model): read legacy directly, no shim.
    return get_option($legacy_key, $default)

  $new_key  = str_replace('etucart_vs_', 'freeman_core_variation_swatches_', $legacy_key)
  $sentinel = '__freeman_settings_reader_unset__'
  $new_val  = get_option($new_key, $sentinel)
  if $new_val !== $sentinel:
    return $new_val
  return get_option($legacy_key, $default)
```

The reader takes the **legacy key** (not a suffix) and computes the new key by string transform. This keeps `Etucart_VS_Settings::OPT_*` constants authoritative — helpers pass them in directly without conversion.

**Migration contract** (inline in `Core/Migrations::migrate_to_1_11_21`):
- Runs once when `Migrations::maybe_run()` detects stored DB version < `1.11.21`. The existing `DB_VERSION_OPTION` is the locking mechanism; no additional marker option needed.
- For each of the 14 legacy → new key pairs: copy iff the new key is unset AND the legacy key is set.
- Idempotent by construction (won't overwrite a new key that already has a value).
- **Never deletes** legacy keys, regardless of state.

**Option-key mapping (sealed during 4a pre-flight)**: 14 keys, listed below. The initial master-plan estimate of 17 was off; the actual count from grepping `OPT_*` constants on `Etucart_VS_Settings` is 14. None of the keys are dynamically named; the legacy `class-settings.php` does not call `register_setting()` (settings live under WooCommerce's settings pipeline via the `woocommerce_get_settings_products` filter, not WP's Settings API).

| # | Legacy key (`OPT_*` constant) | New key |
|---|---|---|
| 1 | `etucart_vs_shop_enabled` | `freeman_core_variation_swatches_shop_enabled` |
| 2 | `etucart_vs_shop_max_visible` | `freeman_core_variation_swatches_shop_max_visible` |
| 3 | `etucart_vs_shop_show_price` | `freeman_core_variation_swatches_shop_show_price` |
| 4 | `etucart_vs_shop_apply_shop` | `freeman_core_variation_swatches_shop_apply_shop` |
| 5 | `etucart_vs_shop_apply_category` | `freeman_core_variation_swatches_shop_apply_category` |
| 6 | `etucart_vs_shop_apply_tag` | `freeman_core_variation_swatches_shop_apply_tag` |
| 7 | `etucart_vs_shop_apply_search` | `freeman_core_variation_swatches_shop_apply_search` |
| 8 | `etucart_vs_shop_apply_related` | `freeman_core_variation_swatches_shop_apply_related` |
| 9 | `etucart_vs_shop_excluded_categories` | `freeman_core_variation_swatches_shop_excluded_categories` |
| 10 | `etucart_vs_pdp_hide_oos` | `freeman_core_variation_swatches_pdp_hide_oos` |
| 11 | `etucart_vs_shop_hide_oos` | `freeman_core_variation_swatches_shop_hide_oos` |
| 12 | `etucart_vs_shop_no_preselect` | `freeman_core_variation_swatches_shop_no_preselect` |
| 13 | `etucart_vs_shop_hide_attr_labels` | `freeman_core_variation_swatches_shop_hide_attr_labels` |
| 14 | `etucart_vs_shop_hide_selected` | `freeman_core_variation_swatches_shop_hide_selected` |

Schema uses Settings_Hub's existing field types (`checkbox`, `number`, `text`); no new field types needed. The `excluded_categories` array key uses `text` with comma-split parsing in the helper (matches the legacy WC field).

**Legacy compatibility**:
- All 4 legacy filters (`etucart_vs_color_swatch_markup`, `etucart_vs_sizes_markup`, `etucart_vs_buy_box_enabled`, `etucart_vs_shop_picker_enabled`) preserved verbatim.
- `freeman_core_variation_swatches_shop_add_to_cart_gate` filter preserved.
- Existing legacy WC settings admin URL (`?page=wc-settings&tab=etucart_vs_settings` or equivalent) keeps resolving.
- Uninstall: legacy keys preserved per §4.5; new keys preserved (consistent with other modules); `_freeman_vs_pd_*` transients still scrubbed by existing `on_deactivate()`.

**Version-skew model (Q4 = P1, approved)**:
- **Flag OFF** disables both the new admin page AND the read-shim. `Settings_Reader::get()` returns the legacy value directly (no new-key check). This avoids the trap of a stale new-key value shadowing fresh edits made via the still-active legacy settings page.
- **Flag ON** enables both. New admin page becomes the writable surface; read-shim prefers new keys. Legacy keys still readable as fallback for any key not yet edited via the new UI.
- Rollback is a single `wp option update` (see "Rollback" below) with no data migration required.

**Rollback**: `wp option update freeman_core_variation_swatches_settings_hub_enabled 0`

**Tests**: ~9 new unit tests across the two test files (Settings_Reader + Migration) + 1 snapshot test. PHPUnit total at release will be confirmed by `vendor/bin/phpunit`'s reported count and copied into CLAUDE.md "Current infrastructure state" — copy the **reported total**, not the method count, since `@dataProvider` cases expand to one reported test per dataset.

---

### 5.2 Sub-PR 4b — Image swatches *(sealed against 4a's schema; full sub-plan at 4b's pre-flight)*

**Branch**: `wave-2.2-4b-image-swatches`
**Flag**: `freeman_core_variation_swatches_image_swatches_enabled` (default `false`)
**Depends on**: 4a (admin field for "use image instead of color" lives under Freeman → Variation Swatches; term-meta image upload uses Settings_Hub's existing media-uploader field type or a new minimal one).

**Why one-liner now**: 4b's full file list and schema-shape decisions depend on 4a's actual `settings_schema()` and how term-meta admin UI is implemented. Writing 4b in detail before 4a ships would be guessing at a schema that doesn't exist; we'd just rewrite 4b after 4a lands.

**Scope (sealed)**: Render swatches as variation-image thumbnails when admin configures an attribute term to use an image. Adds term-meta admin UI for the image upload. Approximate file count: 5 (Module.php schema additions, legacy/class-frontend.php render branch, etucart-swatches.js click target, etucart-swatches.css image-shape rule, snapshot test).

---

### 5.3 Sub-PR 4c — Tooltip on hover *(sealed against 4a's schema; full sub-plan at 4c's pre-flight)*

**Branch**: `wave-2.2-4c-tooltip`
**Flag**: `freeman_core_variation_swatches_tooltip_enabled` (default `false`)
**Depends on**: 4a (admin override for tooltip text per term lives under the new admin page).

**Scope (sealed)**: Hover tooltip on swatches showing attribute term name (admin-overridable per term via term meta). Approximate file count: 4 (legacy/class-frontend.php tooltip wrapper, etucart-swatches.css, optional touch-handling JS, snapshot test).

---

### 5.4 Sub-PR 4d — Auto-color sampler *(full sub-plan)*

**Branch**: `wave-2.2-4d-auto-color-sampler`
**Flag**: `freeman_core_variation_swatches_auto_color_enabled` (default `false`) — **shared with 4e**, see §6.
**Depends on**: nothing user-visible (can run after 4a but doesn't strictly need 4a's schema).

**Why split 4d/4e**: 4d ships sampling logic + caching with **no frontend behavior change** — verifiable in isolation: cache populates, marker meta exists, fixture-image tests pass. 4e wires the sampled color into the swatch render path — that's the live behavior change. Smaller blast radius per PR. Shipping 4d with the flag OFF is intentional and harmless: the sampler doesn't run unless the flag is ON, and even when ON in 4d-only mode, no frontend output changes (4e is what reads the cached value).

**Files (5)**:
- `freeman-core/src/Modules/VariationSwatches/Color_Sampler.php` *(new)* — given `\WC_Product_Variation`, returns dominant hex sampled from primary image. Stores result as variation post-meta `_freeman_core_vs_sampled_color`.
- `freeman-core/src/Modules/VariationSwatches/Module.php` — wire `updated_post_meta` listener for `_thumbnail_id` on variations to invalidate cached sampled color; flag-gated.
- `tests/Unit/Modules/VariationSwatches/Color_Sampler_Test.php` *(new)* — 5 fixture-image cases: white-bg + colored product, transparent PNG, dark-bg, broken image returns null, GD-only path.
- `tests/snapshots/__fixtures__/sampler/*.png` *(new)* — fixture images for the unit tests.
- `docs/roadmap.md` — mark 4d shipped.
- `docs/feature-flags.md` — add shared flag row (one row, gates both 4d sampler and 4e wiring).

**Storage (Q6 = per-variation post-meta, approved)**: `_freeman_core_vs_sampled_color` on the variation post (`\WC_Product_Variation`). Per-variation rather than per-term so that two products both using attribute term "blue" but with different shades sample correctly. Term meta would require averaging across variations and lose photographic accuracy.

**Sampling strategy (Q5 = modal-with-edge-filter, approved)**:
- Drop pixels touching the image bounding-box edges (typical product-photo background).
- Take the **mode** (most common color) of the remainder, after light bucket-quantization to merge near-identical pixels.
- Median was rejected as too muddy; plain modal without edge-filter gets fooled by white backgrounds; k-means is overkill.

**Sampling library (Q3 = GD with Imagick auto-upgrade, approved)**:
- GD is always available on shipped WP installs.
- Auto-upgrade to Imagick when `extension_loaded('imagick')` returns true. Imagick gives better-quality color space handling.
- Library detection lives in `Color_Sampler::sampler()` (private factory).

**Cache invalidation**: hook `updated_post_meta` for `_thumbnail_id` on variations. When a variation's primary image changes, the cached sampled color is deleted; next read re-samples. Lazy: never sample until first read.

**Rollback**: `wp option update freeman_core_variation_swatches_auto_color_enabled 0`. Sampled meta values remain in the DB (harmless — they're keyed under our own meta key); they re-populate when the flag is re-enabled. Optional manual cleanup: `wp post meta delete <id> _freeman_core_vs_sampled_color` per variation, but not required.

**Tests**: 5 fixture-image unit tests. New PHPUnit total at 4d ship time will depend on what 4f/4b/4c added in interim.

---

### 5.5 Sub-PR 4e — Auto-color fallback wiring *(full sub-plan)*

**Branch**: `wave-2.2-4e-auto-color-wiring`
**Flag**: `freeman_core_variation_swatches_auto_color_enabled` (**same flag as 4d**)
**Depends on**: 4d (cached sampled-color values must exist before 4e reads them; fresh installs see lazy-sampling on first render).

**Files (3)**:
- `freeman-core/src/Modules/VariationSwatches/legacy/includes/class-frontend.php` — color-resolution branch (manual term-meta wins → else sampled meta → else neutral gray); flag-gated.
- `tests/Snapshot/VariationSwatches_Auto_Color_Test.php` *(new)* — flag-OFF byte-identical; flag-ON sampled color renders.
- `docs/roadmap.md` — mark 4e shipped + Wave 2.2 parent shipped.

**Color-resolution order**:
1. Manual `term_meta` color set by admin → use it (preserves existing behavior).
2. Else if flag ON: read `_freeman_core_vs_sampled_color` from any variation using this term. If multiple variations using the same term disagree on the sampled color, fall back to neutral gray.
3. Else: existing legacy behavior (whatever the legacy color-resolution does today).

**Disagreement fallback (filterable)**: `freeman_core/variation_swatches/auto_color_disagreement_fallback` — receives the disagreement set as `array<int, string>` (variation_id → hex), returns the chosen hex (default: gray `#cccccc`). Hook lets sites override with e.g. "use the lowest-priced variation's color" or "use the first variation's color."

**Logging**: one Logger `info` line when the disagreement fallback fires, including the term, variation IDs, and the resolved hex. Rate-limited to once per term per request.

**Rollback**: same flag as 4d — single `wp option update` reverts both layers.

**Tests**: 1 snapshot test (flag-OFF + flag-ON variants).

---

### 5.6 Sub-PR 4f — Variation-image-on-card swap *(full sub-plan)*

**Branch**: `wave-2.2-4f-card-image-swap`
**Flag**: `freeman_core_variation_swatches_card_image_swap_enabled` (default `false`)
**Depends on**: nothing strictly. Order places it after 4a so the JSON-payload extension lives under a settled module shape, but technically 4f could ship in parallel with 4a if needed.

**Scope**: Listing pages (shop / category / tag / search / related-products — wherever the shop swatch picker renders). Click a swatch on a product card → that card's main image swaps to the matching variation's image. The shopper stays on the listing. **No PDP, no Quick View, no navigation.**

**Distinct from abandoned Wave 4.4**: Wave 4.4 attempted PDP-side image swap on click-through and was abandoned for a different bug. 4f is a different page (listing, not PDP), a different handler (`etucart-shop-swatches.js`, not the PDP swatch JS), and a different bug surface. No code reuse from 4.4. The "wave-4.4-preselect-timing-fix" branch is unrelated to 4f.

**Files (4)**:
- `freeman-core/src/Modules/VariationSwatches/assets/js/etucart-shop-swatches.js` — extend existing swatch click handler. After `findMatchingVariation()` resolves, find closest `<img>` inside the product card and swap `src` / `srcset` / `sizes`. Reset on unresolved-state (matches existing pattern at lines 251 / 289 / 297).
- `freeman-core/src/Modules/VariationSwatches/legacy/includes/class-archive.php` — extend `prepare_product_data()` JSON payload to include per-variation `image_src` / `image_srcset` / `image_sizes`. Verify whether already present during execution; if so, no payload extension needed (just JS-side consumption).
- `freeman-core/src/Modules/VariationSwatches/assets/css/etucart-shop-swatches.css` — optional fade transition on the swap.
- `tests/Snapshot/VariationSwatches_Card_Image_Payload_Test.php` *(new)* — snapshot the JSON payload shape (flag-OFF byte-identical; flag-ON includes the new image fields).
- `docs/roadmap.md` — mark 4f shipped.
- `docs/feature-flags.md` — add row.

**New hooks (purely additive — rule #1 additive exception)**:
- `freeman_core/variation_swatches/card_image_selector` (filter) — default `.woocommerce-loop-product__link img, .product-thumb img`. Themes can override the selector for the card image element.
- `freeman_core/variation_swatches/card_image_payload` (filter) — runs on the per-variation image data before it's serialized into the JSON payload. Lets sites strip or decorate fields.

Both hooks are no-ops when no listener is attached, so they need no flag gating per CLAUDE.md additive exception.

**Tests**: PHP snapshot test on JSON payload only. JS is **not** unit-tested — manual QA on shop / category / tag / search / related-products in Hebrew (RTL) and English.

**Rollback**: `wp option update freeman_core_variation_swatches_card_image_swap_enabled 0`.

---

## 6. Feature-flag table

| Flag | Sub-PR(s) | Default | Gates |
|---|---|---|---|
| `freeman_core_variation_swatches_settings_hub_enabled` | 4a | `false` | New Freeman → Variation Swatches admin page; `Settings_Reader` read-shim (P1: flag-OFF disables both, returns legacy directly); `Migrator` run on `Plugin::maybe_upgrade()`. |
| `freeman_core_variation_swatches_image_swatches_enabled` | 4b | `false` | Render swatches as variation-image thumbnails when admin configures an attribute term to use an image. |
| `freeman_core_variation_swatches_tooltip_enabled` | 4c | `false` | Hover tooltip on swatches with attribute term name. |
| `freeman_core_variation_swatches_auto_color_enabled` | **4d + 4e (shared)** | `false` | 4d: sampler runs and caches `_freeman_core_vs_sampled_color` post-meta. 4e: render path reads cached value when manual term-meta color is unset. **One feature, two PRs — flag is shared so the user-visible feature is one toggle.** |
| `freeman_core_variation_swatches_card_image_swap_enabled` | 4f | `false` | Shop-listing swatch click swaps the card's main image to the matching variation. |

**Why 4d and 4e share a flag (intentional design, documented at decision time)**: 4d alone ships the sampler and caching pipeline with **no frontend behavior change** — it's verifiable in isolation (cache populates, post-meta exists, unit tests pass on fixture images). 4e wires the cached value into the swatch render path. Shipping 4d with the flag ON in production before 4e ships is harmless (the sampler runs but no swatch reads its output). Shipping 4d with the flag OFF in production after 4e ships is also harmless (everything reverts to manual color resolution). One flag = one user-visible feature with two clean sub-PR boundaries. If 4d had its own flag, sites would have to enable two flags to get one feature, which is the kind of thing that looks weird six months from now.

---

## 7. Sub-PR ship order

`4a → 4f → 4b → 4c → 4d → 4e`

**Rationale**:
- **4a first** — non-negotiable. Lands the read-shim, `Settings_Reader`, `Migrator`, the new admin page. Every other sub-PR's schema decisions seal against 4a's actual implementation. Without 4a, 4b/4c are guessing at storage shape.
- **4f second** — quickest user-visible win, doesn't depend on 4b/4c. Lands variation-image-on-card swap on listings. Independent of the auto-color stack.
- **4b third** — image swatches. Sealed against 4a's schema. Adds term-meta image upload; uses the JSON payload extension 4f already shipped (or can ship its own minimal extension if 4f's payload doesn't fit).
- **4c fourth** — tooltip. Pure UX polish on top of 4b. Shortest sub-PR.
- **4d fifth** — auto-color sampler (pipeline only). No frontend change.
- **4e sixth** — auto-color wiring. Lights up 4d's cached values in the render path. Final sub-PR; ships the parent Wave 2.2 shipped-marker on `/docs/roadmap.md`.

---

## 8. Decisions of record (Q1–Q6, 2026-05-03 pre-flight)

| Q | Question | Decision |
|---|---|---|
| Q1 | Where does the master plan land — standalone PR or folded into 4a? | (a) Standalone PR (`wave-2.2-master-plan` — this PR). Roadmap edit + plan doc only, no code. |
| Q2 | Sub-PR ship order | `4a → 4f → 4b → 4c → 4d → 4e` (rationale in §7). |
| Q3 | Sampling library for auto-color | GD with Imagick auto-upgrade when `extension_loaded('imagick')`. |
| Q4 | Version-skew model for the read-shim | P1: flag-OFF disables both the admin page **and** the read-shim. Avoids stale-new-key shadowing fresh-legacy edits. |
| Q5 | Auto-color sampling strategy | (iii) Modal-with-edge-filter — drop bbox-edge pixels, take mode of remainder with light bucket quantization. |
| Q6 | Sampled-color storage location | Per-variation post-meta `_freeman_core_vs_sampled_color`. (Term meta would lose per-product photographic accuracy.) |

---

## 9. What this plan does NOT cover

Out of scope for Wave 2.2 — flagged here so sub-PR pre-flights don't accidentally pull them in:

- **Sunset of `etucart_vs_*` keys**. Per §4.5 of the decisions doc, sunset is a separate future decision requiring its own approval. Wave 2.2 migrates reads-and-some-writes; it does not delete.
- **Migrating the legacy WC settings admin URL**. The legacy URL keeps resolving. The new admin page lives under Freeman → Variation Swatches; both coexist indefinitely.
- **Rewriting `assets/css/etucart-swatches.css` and `assets/css/etucart-shop-swatches.css`** beyond the minimal edits each sub-PR needs (image-shape rule in 4b, fade transition in 4f). The audit (B5) calls out hardcoded sizing, no dark mode, no `prefers-reduced-motion` honoring; that's its own future work.
- **The two missing hooks the audit (D1) called out** — `freeman_core/variation_swatches/render_swatch` and `freeman_core/variation_swatches/buy_box_html`. These are deferred to a future hooks-only PR or folded into a sub-PR if a natural call site emerges. Not Wave 2.2.
- **PHP 7.4 PHPUnit failures** (pre-existing `str_starts_with` / `str_contains` usage in shipped code). Separate infra PR to drop PHP 7.4 from the supported matrix. Not a Wave 2.2 dependency.
- **Per-attribute display-type override** (audit C8 sub-bullet). Could fold into 4a's settings schema if cheap; otherwise its own future PR.
- **Sold-out swatch behavior** (audit C8 sub-bullet). Same as above.
- **Pre-selected attribute strategy** (audit C8 sub-bullet). Overlaps with `CheapestDefaultVariation` module — explicitly *not* in Wave 2.2.

If a sub-PR pre-flight discovers it needs one of the above, it stops and asks before pulling it in.
