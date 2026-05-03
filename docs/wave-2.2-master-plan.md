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

**Admin-merge log (durable record)**:
- **PR #19 / 1.11.19 (slider edge-fade tweak)** — admin-merged 2026-05-03 with red CI on the PHP 7.4 / 8.0 *lint* lanes. Cause was a pre-existing latent issue in `tests/snapshots/SnapshotTestCaseTest.php:20` (`0o755` PHP 8.1+ octal literal, introduced by Wave 0.5), not the change itself. PR #20 (1.11.20) fixed that ~5 minutes later.
- **PR #22 / 1.11.21 (Wave 2.2 / 4a — Settings_Hub migration backbone)** — admin-merged 2026-05-03 with red CI on the PHP 7.4 *PHPUnit* lane only. Cause was a separate pre-existing issue: shipped freeman-core code uses `str_starts_with` / `str_contains` (PHP 8.0+ functions) which fail to call on PHP 7.4. The 7.4 PHPUnit lane has been de-facto failing since Wave 2.3a–c baked those idioms in. Tracked separately as the matrix-drop chore PR (queued ahead of 4f per Yiftach's direction "don't want admin-merge-with-red-7.4 becoming routine").

After the matrix-drop PR ships, no further admin-merges should be needed for routine Wave 2.2 work.

**Update 2026-05-03 (post-4f)**: matrix-drop landed as PR #23 (1.11.22). Wave 2.2 / 4f then shipped as PR #24 (1.11.23) with all 7 CI lanes green — first vanilla merge in Wave 2.2. Prediction confirmed.

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

**Wave 2.2 specific calibration (2026-05-03)**: ceiling raised from CLAUDE.md's project-wide 12 to **14 files for Wave 2.2 sub-PRs only**. Drops back to 12 starting Wave 3.

**Why**: three structural near-or-over-ceiling sub-PRs in a row (4b at 13 with bootstrap.php promotion = waiver #1; 4d at ≥13 pending bootstrap.php cron-stub promotion + scheduler extraction = waiver #2 + #3 territory) made it clear that Wave 2.2 is structurally broader than the waves the 12-file ceiling was calibrated against. Wave 2.2 touches: admin UI (Module.php / class-admin.php), helper class (class-plugin.php), payload assembly (class-archive.php), **two** render templates (shop-picker, buy-box), **two** stylesheets (separate enqueue contexts), Settings_Hub integration, term-meta + post-meta, WP-Cron, and the standard docs trail. Other waves (0.x infrastructure, 1.x hooks, 2.3 RestockNotify) had narrower surfaces — single classes, fewer rendering contexts, no admin extensions.

**This is a calibration fix, not a scope expansion.** The CLAUDE.md project-wide ceiling stays at 12. Future waves with similarly broad surfaces can request their own per-wave calibration; future waves with narrower surfaces use 12 unchanged.

**Waiver budget under the new ceiling**: still 2 across the wave. Already-spent: 4b's bootstrap promotion (#25) + 4d's CI workflow extension (this PR). Adding bootstrap.php for cron stubs in 4d does NOT consume a third waiver because the ceiling has been raised — the file count just needs to land within 14. Anything above 14 in 4d/4e gets surfaced explicitly as a waiver-against-the-new-14, treated the same way the original-12 waivers were.

Re-checked at each sub-PR's pre-flight; if any crosses the (wave-specific) ceiling I stop and ask (per "no self-waiver" rule).

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
| 4e | `legacy/includes/class-archive.php` *(already touched in 4f — extending)* | Render-path callsite swap at `prepare_product_data()` line 347: `Etucart_VS_Plugin::term_color()` → `Color_Sampler::resolve_term_color()`. Single-line change; flag-OFF byte-identical. |
| 4e | `legacy/templates/variation-buy-box.php` *(first touched in 4e — pre-flight correction, see below)* | PDP render-path callsite swap at line 87: same shape as the archive.php swap. Single-line change; flag-OFF byte-identical. |
| 4f | `legacy/includes/class-archive.php` *(first touched in 4f)* | Extend `prepare_product_data()` JSON payload with per-variation `image_src` / `image_srcset` / `image_sizes`. Verify during execution whether already partially present. |

**Pre-flight correction (2026-05-03, during 4e's sub-PR pre-flight)**: the original 4e row above predicted `legacy/includes/class-frontend.php` as the intercept. Pre-flight grep showed `class-frontend.php` has zero color-resolution code — it only handles asset enqueue. The actual term-color helper is `Etucart_VS_Plugin::term_color($term_id)` in `class-plugin.php`, called from four sites: two render paths (`class-archive.php:347`, `templates/variation-buy-box.php:87`) and two admin paths (`class-admin.php:130`, `class-admin.php:359`). Admin paths must NOT auto-resolve — the admin must see "what is stored" so they can recognize when manual color is unset. So 4e's intercept is `Color_Sampler::resolve_term_color($term_id, $product_id)`, called at the two render sites only. `class-frontend.php` drops off 4e's row entirely; `variation-buy-box.php` enters as a first-touch (4a precedent: causally-tied master-plan correction bundled with the code PR, not a separate decision).

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

**Scope (sealed at 4b pre-flight, 2026-05-03)**: Per-term image upload (Iconic/WPC pattern). Each attribute term gets a `freeman_core_variation_swatches_term_image_id` term-meta entry (attachment ID, namespaced under `freeman_core_*` rather than extending the legacy `etucart_*` namespace — the migration direction established by 4a is legacy → new). Image wins over color when both are set; color is the fallback. Round shape, hardcoded to match existing color circles. Two helpers parallel to `term_color()`: `term_image_id()` and `term_image_url()`. One additive filter: `freeman_core/variation_swatches/term_image_url`. Image source is admin upload via `wp.media()`; no variation-image lookup (that's 4f's territory).

**Pre-flight correction (2026-05-03)**: the original "Approximate file count: 5" was based on a misread of the scope — Option B (variation-image lookup) shape, not Option A (per-term upload, the literal Iconic/WPC pattern that the plan's own "term-meta admin UI for the image upload" phrasing describes). Sealed actual count is **12 substantive files** (under the 12-file ceiling, no waiver needed). Original 5-file forecast left in the bullet list below for the audit trail; reality enumerated in the "Shipped delta" block when the PR lands.

**Files (sealed, 12 substantive, all under VariationSwatches + docs)**:
- `legacy/includes/class-plugin.php` — 4 new static helpers: `image_meta_key()`, `term_image_id()`, `term_image_url()`, `attribute_has_images()` (cached per-request, parallel to `attribute_is_color()`).
- `legacy/includes/class-admin.php` — extend `enqueue_color_picker()` (renamed `enqueue_term_assets()`) to also enqueue `wp_enqueue_media()` + a small inline script. Extend `render_add_field()` / `render_edit_field()` with media-uploader UI. Extend `save_field()` with attachment-ID save path. Extend `render_column()` to show image preview.
- `legacy/includes/class-archive.php` — option-payload `image_url` field (flag-gated); fold flag state into `prepared_transient_key()` for implicit cache-bust on flag flip (same precedent as 4f).
- `legacy/templates/shop-variation-pick.php` — render branch when option has `image_url` set (background-image span; RTL-friendly, scales cleanly).
- `legacy/templates/variation-buy-box.php` — same render branch for buy-box.
- `assets/css/etucart-shop-swatches.css` — `.etucart-shop-pick__opt-img` rule, round, sized to match `__opt-dot`.
- `assets/css/etucart-swatches.css` — same rule for buy-box `.etucart-swatch__img`.
- `tests/VariationSwatchesImageSwatchesSnapshotTest.php` *(new)* — 10 tests: term_image_id / term_image_url / attribute_has_images / option-payload shape (flag-OFF, flag-ON-with-image, flag-ON-without-image) / filter mutation.
- `docs/wave-2.2-master-plan.md` — this estimate correction (the doc-fix you're reading).
- `docs/roadmap.md` — mark 4b shipped + bump "Last updated".
- `docs/feature-flags.md` — add `image_swatches_enabled` row.
- `CLAUDE.md` — version sync 1.11.23 → 1.11.24 + PHPUnit count update.

(Mechanical version-bump artifacts produced by `release.sh` — `freeman-core.php`, `Plugin.php`, both `CHANGELOG.md` files — not counted toward the ceiling per master-plan precedent. `tests/baseline-hooks.txt` may auto-regenerate to pick up the new `term_image_url` filter call.)

**Original prediction (preserved for audit trail)**: ~~Approximate file count: 5 (Module.php schema additions, legacy/class-frontend.php render branch, etucart-swatches.js click target, etucart-swatches.css image-shape rule, snapshot test).~~ — wrong on multiple axes: Module.php is not touched (4b is term-meta + render only, no Settings_Hub schema changes); class-frontend.php is not touched (the render branch lives in templates, not class-frontend); JS click target needs no change (the JS is attribute-value-driven, agnostic to color-vs-image rendering); CSS is two files not one (separate enqueue contexts for shop picker and buy-box); admin UI for upload was implied in the body text but not counted in the file estimate.

---

### 5.3 Sub-PR 4c — Tooltip on hover *(sealed against 4a's schema; full sub-plan at 4c's pre-flight)*

**Branch**: `wave-2.2-4c-tooltip`
**Flag**: `freeman_core_variation_swatches_tooltip_enabled` (default `false`)
**Depends on**: 4a (admin override for tooltip text per term lives under the new admin page).

**Scope (sealed at 4c pre-flight, 2026-05-03)**: Hover tooltip on swatches showing attribute term name. Per-term admin override via term meta (`freeman_core_variation_swatches_term_tooltip_text`, namespaced under the canonical convention rather than legacy `etucart_*`). Pure CSS rendering — `data-tooltip` attribute + `:hover::after { content: attr(data-tooltip) }`, mirroring the existing pattern at `etucart-shop-swatches.css:198-199` (`.etucart-shop-pick__attr-selected:empty::before` + `content: attr(data-default-text)`). Tooltips applied to color and image swatches in both shop picker and PDP buy-box; **text-button swatches skipped** (the label is inline so a tooltip is redundant). **No touch-handling JS** — master plan listed it as optional; touch devices have no `:hover` so tap-to-show would be feature creep deferred to a future PR if a client asks.

**Pre-flight correction (2026-05-03)**: the original "Approximate file count: 4" was Option-B-shape thinking. Sealed actual count is **12 substantive files** (under the 12-file ceiling, no waiver). Original 4-file forecast preserved at the bottom of the section for the audit trail; same correction shape as 4a's and 4b's.

**Files (sealed, 12 substantive)**:
- `legacy/includes/class-plugin.php` — 2 new static helpers: `tooltip_meta_key()`, `term_tooltip_text(int $term_id, string $default = '') → string` (returns override if set, else default, else empty).
- `legacy/includes/class-admin.php` — extend term-edit form fields with the tooltip text input + save handler. Reuses the existing `'edit-tags.php' / 'term.php'` screen-hook gate.
- `legacy/includes/class-archive.php` — shop-picker option-payload `tt` field (always present when flag ON; empty string when no override); fold flag state into `prepared_transient_key()` for implicit cache-bust.
- `legacy/templates/shop-variation-pick.php` — `data-tooltip="..."` attribute on color and image swatch buttons (skip text buttons).
- `legacy/templates/variation-buy-box.php` — option_items inline-build extended with `tt` field + `data-tooltip` attribute on color/image buttons.
- `assets/css/etucart-shop-swatches.css` — `[data-tooltip]:hover::after` tooltip rule for shop picker.
- `assets/css/etucart-swatches.css` — same rule for buy-box.
- `tests/VariationSwatchesTooltipTest.php` *(new)* — helper tests + payload-shape tests + filter-not-emitted-flag-OFF.
- `docs/wave-2.2-master-plan.md` — this estimate correction.
- `docs/roadmap.md` — mark 4c shipped + bump "Last updated".
- `docs/feature-flags.md` — add `tooltip_enabled` row.
- `CLAUDE.md` — version sync 1.11.24 → 1.11.25 + PHPUnit count update.

**Original prediction (preserved for audit trail)**: ~~Approximate file count: 4 (legacy/class-frontend.php tooltip wrapper, etucart-swatches.css, optional touch-handling JS, snapshot test).~~ — wrong on multiple axes: class-frontend.php is not touched (tooltip render lives in templates, not class-frontend.php); CSS is two files not one (separate enqueue contexts); admin UI for the override was implied in the body text but not counted; class-plugin.php helpers + class-archive.php payload extension + docs sync were missing entirely.

---

### 5.4 Sub-PR 4d — Auto-color sampler *(full sub-plan)*

**Branch**: `wave-2.2-4d-auto-color-sampler`
**Flag**: `freeman_core_variation_swatches_auto_color_enabled` (default `false`) — **shared with 4e**, see §6.
**Depends on**: nothing user-visible (can run after 4a but doesn't strictly need 4a's schema).

**Why split 4d/4e**: 4d ships sampling logic + caching with **no frontend behavior change** — verifiable in isolation: cache populates, marker meta exists, fixture-image tests pass. 4e wires the sampled color into the swatch render path — that's the live behavior change. Smaller blast radius per PR. Shipping 4d with the flag OFF is intentional and harmless: the sampler doesn't run unless the flag is ON, and even when ON in 4d-only mode, no frontend output changes (4e is what reads the cached value).

**Architecture redirect (sealed at 4d pre-flight, 2026-05-03 — supersedes the original "lazy on first read" design)**:

Stores that exclude product pages from cache cannot rely on first-hit-pays. Pre-warm on flag-flip + sample-on-save together ensure shoppers never trigger sampling. Hot-path lazy fallback retained as a safety net for any variation that slipped through both pre-warm and save hook (e.g., variations created via direct DB write or import tools that bypass save hooks).

Three layers of cold-cache prevention, fired in order from most-common to safety-net:

1. **Sample-on-save** — `woocommerce_save_product_variation` hook. Admin saves a variation, sampler runs inline, hex stored as variation post-meta. Standard path for all new and edited variations. Admin pays sampling cost; shoppers never see it.
2. **Pre-warm on flag-flip** — `update_option_freeman_core_variation_swatches_auto_color_enabled` (and `add_option_*`) listener. On flip ON, scheduler enumerates all variable-product variations missing a cached hex, populates a queue option, schedules a single WP-Cron event. The cron callback pops a configurable batch (default 50) per tick, samples them, saves the remaining queue, **reschedules itself ~5s out until the queue is empty**. Batched cron prevents PHP max-execution-time death on 1000+ variation stores. Filter `freeman_core/variation_swatches/sampler_prewarm_batch_size` lets sites tune.
3. **Hot-path lazy fallback** — public `Color_Sampler::sample_if_missing()` callable from the render path (consumed by 4e). Catches edge cases — direct DB writes, import tools that bypass `woocommerce_save_product_variation`, anything else that slips through layers (1) and (2).

**First-of-kind cron precedent for freeman-core**: `Sampler_Scheduler::CRON_HOOK = 'freeman_core/variation_swatches/sampler_prewarm'` is the first WP-Cron event registered by freeman-core (Migrations.php only consumes existing legacy events from Wave 1.9). Pattern established: queue option (named after the hook with `_queue` suffix) + cron callback method + filterable batch size + self-reschedule until queue empty. Future scheduled-work needs can cite this PR.

**Files (sealed actual count: 10 substantive, under 12-file ceiling, no waiver)**:

- `freeman-core/src/Modules/VariationSwatches/Color_Sampler.php` *(new)* — given a variation post-id, resolves the variation's primary image (or parent product image as fallback), runs modal-with-edge-filter sampling via GD (auto-upgrade to Imagick when `extension_loaded('imagick')`), stores hex as `_freeman_core_vs_sampled_color` post-meta. Public methods: `sample(int $variation_id): string`, `sample_if_missing(int $variation_id): string`, `clear(int $variation_id): void`.
- `freeman-core/src/Modules/VariationSwatches/Sampler_Scheduler.php` *(new)* — owns the WP-Cron + queue + batching surface. Public listeners: `handle_flag_flip()`, `handle_save_variation()`, `handle_thumbnail_change()`, `handle_variation_delete()`, `handle_attachment_delete()`, `run_prewarm_batch()` (cron callback). Constants: `CRON_HOOK`, `QUEUE_OPTION`, `DEFAULT_BATCH_SIZE`, `BATCH_SIZE_FILTER`. Extracted from Module.php so the queue/batching logic is testable in isolation — same precedent as 4a's `Settings_Reader` extraction (which is already paying off in #28's bugfix).
- `freeman-core/src/Modules/VariationSwatches/Module.php` — boot path registers Sampler_Scheduler's listeners; flag-gated. ~30 lines added.
- `tests/VariationSwatchesColorSamplerTest.php` *(new)* — sampler tests with **synthesized fixture images** built in test setUp via `imagecreatetruecolor` + `imagepng` (no committed PNG bytes; eliminates license/repo-size questions; tests express exact pixel patterns inline). Cases: solid color → that color; white-bg + colored center → center color (edge filter wins); broken attachment → empty string; idempotent (already-sampled doesn't re-sample); GD path (Imagick path skipped via `markTestSkipped` when extension absent).
- `tests/VariationSwatchesAutoColorHooksTest.php` *(new)* — Sampler_Scheduler tests using **inline `function_exists`-guarded stubs** for `wp_schedule_single_event` / `wp_next_scheduled` / `wp_clear_scheduled_hook` / `get_posts` (no other test file defines these, so no alphabetical-load-race; bootstrap.php stays untouched — the 4b waiver does not get spent again). Cases: flag-flip populates queue + schedules cron; save-variation samples inline; thumbnail-change clears cached hex; variation-delete cleans up; cron callback pops batch + samples + reschedules when queue non-empty + clears option when empty; batch-size filter mutates pop count.
- `.github/workflows/ci.yml` — **Yiftach's waiver #2** (CI extensions list extension). Adds `gd, imagick` to `extensions: dom, json, mbstring, zip` so both sampler paths get exercised on every lane. Cheap (~few seconds per lane install) and catches Imagick regressions in CI rather than silently rotting.
- `docs/wave-2.2-master-plan.md` — this §5.4 update (the doc-fix commit you're reading).
- `docs/roadmap.md` — mark 4d shipped + bump "Last updated".
- `docs/feature-flags.md` — add `auto_color_enabled` row with the shared-flag note (4d ships pipeline only; 4e wires it into render).
- `CLAUDE.md` — version sync 1.11.26 → 1.11.27 + PHPUnit count update.

**Mechanical version-bump artifacts (4)** + **auto-regenerated baselines (1–3)** not counted, per master-plan precedent.

**Cache invalidation handlers (Q3 redirect)**:
- ✅ **`updated_post_meta` for `_thumbnail_id`** on variations — clears cached hex when image changes.
- ✅ **`before_delete_post` for `product_variation`** — defense-in-depth cleanup of the orphan post-meta row + Logger info on count cleared. (WP cleans up post-meta automatically on post deletion, so this is belt-and-suspenders, not strictly required for correctness.)
- ✅ **`delete_attachment`** — finds variations whose `_thumbnail_id` referenced the deleted attachment and clears their cached hex. Without this, those variations would render with the now-broken image cached as their hex; clearing forces 4e's render path to fall through to the neutral-gray fallback.
- 📝 **Documented (not handled)**: image regeneration via plugins like `Regenerate Thumbnails`. The cached hex stays correct because the attachment ID + dominant pixel mode don't change — only the file bytes do, and we sample mode-of-pixels not file-checksum. Benign.

**Storage (Q6 = per-variation post-meta, approved)**: `_freeman_core_vs_sampled_color` on the variation post. Per-variation rather than per-term so that two products both using attribute term "blue" but with different shades sample correctly. Term meta would require averaging across variations and lose photographic accuracy.

**Sampling strategy (Q5 = modal-with-edge-filter, approved)**:
- Drop pixels touching the image bounding-box edges (typical product-photo background).
- Take the **mode** (most common color) of the remainder, after light bucket-quantization (5 bits per channel = 32k buckets) to merge near-identical pixels.
- Median was rejected as too muddy; plain modal without edge-filter gets fooled by white backgrounds; k-means is overkill.

**Sampling library (Q3 = GD with Imagick auto-upgrade, approved)**:
- GD is always available on shipped WP installs (and now on every CI lane via the workflow extension).
- Auto-upgrade to Imagick when `extension_loaded('imagick')` returns true. Imagick gives better-quality color space handling.
- Library detection lives in `Color_Sampler::sampler()` (private factory).

**Rollback**: `wp option update freeman_core_variation_swatches_auto_color_enabled 0`. Sampled meta values remain in the DB (harmless — keyed under our own meta key); they re-populate when the flag is re-enabled. The pre-warm queue option is also harmless if left behind (cron callback bails when queue is empty). Optional manual cleanup: `wp post meta delete <id> _freeman_core_vs_sampled_color` per variation; not required.

**Tests**: ~13 new tests across two files (sampler logic + scheduler hooks). New PHPUnit total at 4d ship time confirmed by `vendor/bin/phpunit`'s reported count and copied into CLAUDE.md "Current infrastructure state" — copy the **reported total**, not the method count.

**Original prediction (preserved for audit trail)**: ~~Approximate file count: 5 (Color_Sampler.php new, Module.php hook wiring, Color_Sampler_Test.php new, fixture PNGs, docs).~~ — predicted hot-path-lazy with no scheduler; the live design ships pre-warm + sample-on-save + batched cron because Yiftach's stores exclude product pages from cache and a first-shopper-pays-the-cost design fails for them. Sampler_Scheduler.php is the new file extracted to keep Module.php navigable; the synthesized-fixture decision drops the committed-PNG estimate to zero; CI workflow extension is the waiver-budget item. None of these were in the 5-file estimate. The architecture redirect was a Q4 design decision, not a forecasting miss.

---

### 5.5 Sub-PR 4e — Auto-color fallback wiring *(full sub-plan)*

**Branch**: `wave-2.2-4e-auto-color-wiring`
**Flag**: `freeman_core_variation_swatches_auto_color_enabled` (**same flag as 4d**)
**Depends on**: 4d (cached sampled-color values must exist before 4e reads them; fresh installs see lazy-sampling on first render).

**Files (3)** *(predicted — see "Shipped 1.11.28 — delta from prediction" below for actual)*:
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

**Shipped 1.11.28 — delta from prediction**:
- ✏️ **File-list correction (master-plan §5.5 + §3.5)**: predicted 1 substantive file (`class-frontend.php`); actual 3 substantive files (`Color_Sampler.php` adds the wrapper, `class-archive.php:347` and `templates/variation-buy-box.php:87` swap callsites). `class-frontend.php` was predicted because the master plan assumed render-path color resolution lived there; pre-flight grep showed `class-frontend.php` has zero color-resolution code (asset enqueue only). The actual helper `Etucart_VS_Plugin::term_color()` is in `class-plugin.php`, called from four sites — two render, two admin. Admin sites must show "what is stored" without auto-fallback, so the cleanest intercept is a new wrapper called only at render sites. Same shape as the 4a §3.5 correction — causally tied to the code PR, called out in the PR body, not a separate decision.
- ➕ **`Color_Sampler::resolve_term_color($term_id, $product_id)`** — new public static. Resolution order: manual term-meta wins (regardless of flag) → flag-OFF returns `''` (byte-identical to legacy `term_color()`) → flag-ON gathers the canonical (deduped, sorted) set of non-empty sampled hexes from variations of `$product_id` whose attribute term matches `$term_id` → 0 hexes falls through to legacy default → 1 hex returns it → ≥2 hexes apply the disagreement filter (default `#CCCCCC`).
- ➕ **Disagreement filter `freeman_core/variation_swatches/auto_color_disagreement_fallback`** — args `($default_gray, $disagreement_set, $term_id, $product_id)`. `$disagreement_set` is the deduplicated, sorted set of non-empty hexes (canonical shape — identical to what the logger emits). Filter return is sanitized via `Etucart_VS_Plugin::sanitize_hex_color()`; an invalid return falls back to `$default_gray`. Purely additive (rule #1 additive exception); no flag gating.
- ➕ **Disagreement Logger info line** — fires once per term per request via `$GLOBALS['fr_auto_color_logged']` rate-limit. Payload includes term id, product id, and the canonical hex set.
- 🧪 **8 new test methods** in `tests/VariationSwatchesAutoColorRenderTest.php` (matches sealed plan; phpunit-reported total 271 → 279). Covers: flag-OFF byte-identity, manual hex wins, single sampled hex, agreement, disagreement → gray + logger fires once, filter override + invalid return falls back, empty-sentinel ignored, no-sampled-meta falls through.
- 📊 **Final file count: 12** (sealed estimate: 12). Substantive: 4 (`Color_Sampler.php`, `class-archive.php`, `variation-buy-box.php`, new test). Mechanical: 5 (`freeman-core.php`, `Plugin.php`, two CHANGELOGs, `baseline-hooks.txt`). Doc: 3 (this master-plan correction, `roadmap.md` shipped marker, `CLAUDE.md` infra-state). Under the Wave-2.2 14-file ceiling. Zero new waivers.
- 🔁 **Wave 2.2 parent shipped-marker** lands with this PR — last sub-PR per the §6/§7 ship order (`4a → 4f → 4b → 4c → 4d → 4e`).

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

**Shipped 1.11.23 — delta from prediction**:
- ✅ Both new filters landed as planned.
- ✅ JS `refreshCardImage()` landed in `etucart-shop-swatches.js` with stash/restore mirroring `refreshPrice()`. Card ancestor located via `closest(picker, 'li.product, .product, [data-product-id]')`.
- ✅ PHP payload extension landed in `prepare_product_data()` — confirmed during pre-flight that the per-variation image fields were NOT already present (master plan asked to verify).
- ✏️ **CSS skipped** — the "optional fade transition" was deliberately not landed; image swap is currently instant. Can be added in 4b/4c or a follow-up if visual polish is wanted.
- ➕ **Helper extracted for testability** — the inline variation-entry build was extracted into `Etucart_VS_Archive::build_variation_entry()` (public static, `@internal`) so the new image-payload branch is unit-testable without standing up the full `\WC_Product_Variable` stub stack. Behavior unchanged.
- ➕ **Implicit cache-bust via transient signature** — instead of an explicit cache-clear handler on flag flip, the flag state is folded into `prepared_transient_key()`'s signature. Different flag state → different key → fresh build. Saves a `Module.php` touch + `add_action` handler and reduces blast surface.
- 📊 **Final file count: 11** (master plan predicted ~4). Substantive: 7 (`class-archive.php`, `etucart-shop-swatches.js`, snapshot test, `baseline-hooks.txt`, `roadmap.md`, `feature-flags.md`, `CLAUDE.md`). Mechanical: 4 (version-bump artifacts). Under 12-file ceiling.
- 🧪 **7 new tests** in `tests/VariationSwatchesCardImagePayloadSnapshotTest.php` covering flag-OFF byte-identity, flag-ON image emission, no-image-data path, filter mutation, filter-not-fired-on-flag-off, attrs normalization. Suite total 208 → 215.
- 🟢 **First vanilla merge** in Wave 2.2 (post-matrix-drop). All 7 CI lanes green; no admin override.

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
