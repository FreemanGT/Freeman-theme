# Freeman Plugin Suite — Roadmap

**Last updated**: 2026-04-28
**Owner**: Yiftach
**Reflects decisions in**: `/docs/decisions-2026-04-28.md`

This is the execution plan. Waves run in order. Items within a wave can run in parallel only if they touch separate modules.

---

## Active items (9)

The original 15-item roadmap was reduced to 9 after the strategic decisions. Dropped: #7, #8, #13, #14, and parts of #15. See `/docs/decisions-2026-04-28.md` for reasoning.

| # | Priority | Module | Description |
|---|---|---|---|
| 1 | P0 | Core / all modules | Add per-module `apply_filters` / `do_action` hooks (D1) |
| 2 | P0 | RestockNotify | English defaults + Hebrew opt-in locale bootstrapper |
| 4 | P0 | VariationSwatches | Migrate settings to Settings_Hub + image swatches + tooltip |
| 3 | P0 | ProductFeed | Multi-channel feed support (only if a client needs it; otherwise P2) |
| 5 | P1 | InfiniteScroll | Trigger-mode selector + history API |
| 6 | P1 | Sliders | Autoplay / loop / pagination dots / lazy-load images |
| 9 | P1 | CheapestDefaultVariation | Strategy selector + per-product opt-out |
| 10 | P2 | RestockNotify | CSV export of subscribers + GDPR data-export hook |
| 11 | P2 | Sliders | Expose hardcoded design tokens as Elementor controls |
| 12 | P2 | InfiniteScroll | Expose skeleton + fade-in tokens as admin settings |

---

## Wave 0 — Safety net (must land before any feature work)

These items are prerequisites. Nothing in Wave 1+ can start until Wave 0 is complete.

**0.0 — Set up PHPUnit**
- Configure `/tests/phpunit.xml` and `/tests/bootstrap.php`
- Add one example test that passes
- Document how to run tests in `/tests/README.md`

**0.1 — Add Logger extension hooks (D8)**
- Add `freeman_core/logger/entry` filter inside `Logger::log()`
- Add `freeman_core/logger/written` action after the option write
- Logger stays `final`. Do not extract an interface.
- Test: filter mutates entry, action fires with correct payload

**0.2 — Feature-flag helper**
- Add `Freeman\Core\Core\Feature_Flags::is_enabled($module, $feature)`
- Reads `freeman_core_<module>_<feature>_enabled`
- Default `false`
- Document the convention in `/docs/feature-flags.md`

**0.3 — Settings export/import tool**
- New admin page under Freeman menu: "Tools"
- Export: dump all `freeman_core_*` and `freeman_digital_*` options as JSON
- Import: validate JSON shape, write options, log changes
- This is the rollback path if a later change corrupts settings

**0.4 — Regression baseline**
- Run and commit:
  - `wp option list | grep freeman_` → `/tests/baseline-options.txt`
  - `grep -rn "apply_filters\|do_action" freeman-core/src/` → `/tests/baseline-hooks.txt`
  - `grep -rn "register_rest_route" freeman-*` → `/tests/baseline-rest.txt`
  - `grep -rn "WP_CLI::add_command" freeman-*` → `/tests/baseline-cli.txt`
- These are the snapshots Wave 1+ must not break

**0.5 — Snapshot harness**
- Create `/tests/snapshots/` with helpers to capture:
  - Rendered HTML for any module's frontend output
  - Generated XML for ProductFeed
  - JSON dump of any settings page
- One example per type, committed
- This unlocks the "diff must be empty when flag is OFF" requirement

---

## Wave 1 — P0 extensibility (additive, zero behavior change)

**1.1 — Add 18 module hooks (Roadmap #1)**
- Single PR (waiver from the "one roadmap item per PR" rule, stated in description)
- Add the hooks listed in `/docs/audit-2026-04-28.md` §D1
- Each hook: `@since 1.11.0` PHPDoc with `@param` descriptions
- Each hook: at least one test verifying the filter mutates output / the action fires
- With no listeners attached, output must be byte-identical to current — verify with snapshot test

**1.2 — RestockNotify locale bootstrapper (Roadmap #2)**
- Per `/docs/decisions-2026-04-28.md` §4.2: English defaults, Hebrew opt-in
- Create `RestockNotify/locales/en_US.php` and `he_IL.php`
- On activation: detect `get_locale()`, install matching defaults
- **Existing installs**: do NOT overwrite their option values (check if values exist first)
- Move email body templates from option-string defaults to template files
- Highest-risk Wave-1 item — write upgrade routine first, dry-run on a clone

---

## Wave 2 — P0 features (each behind flag, each independently shippable)

**2.1 — ProductFeed multi-channel (Roadmap #3)**
- Only start this if a specific client has asked. Otherwise demote to P2.
- New submodule `ProductFeed/Channels/` with `Google_Xml`, `Facebook_Csv`, `Pinterest_Xml` implementing `Channel_Interface`
- Existing `/product-feed` endpoint keeps Google XML output unchanged
- New endpoints: `/product-feed/<channel>`
- Feature flag: `freeman_core_product_feed_multichannel_enabled`
- **Before extending Generator's surface**: read the header comment in `tests/snapshots/__fixtures__/wc_product_stub.php` — it lists which `\WC_Product` methods the snapshot stub already covers and which still need to be added (variable-product branch + `\WC_Product_Variation` stub) before variation XML can be snapshotted.

**2.2 — VariationSwatches migration plan (Roadmap #4)**
- **STOP after writing the plan.** Wait for human approval before coding.
- Plan must cover:
  - Option-key mapping (`etucart_vs_*` → `freeman_core_variation_swatches_*`)
  - Read-shim that reads new key first, falls back to legacy
  - Legacy filter compatibility (`freeman_core_variation_swatches_shop_add_to_cart_gate` preserved)
  - Uninstall behavior (do not delete legacy keys)
  - Version-skew handling during rollout
- After approval: split into 4a (settings migration), 4b (image swatches), 4c (tooltip), each its own PR

---

## Wave 3 — P1 functional improvements

Each item is its own PR with its own feature flag. Order within wave doesn't matter.

**3.1 — InfiniteScroll trigger modes (Roadmap #5)**
- Setting: `auto` / `button` / `hybrid` (auto first 2 pages, button after)
- Selector override (currently hardcoded to `.products`)
- History API integration toggle (push state on each page load)
- Flag: `freeman_core_infinite_scroll_trigger_modes_enabled`

**3.2 — Slider autoplay/loop/dots/lazy (Roadmap #6)**
- New Elementor controls: autoplay, autoplay delay, loop, pagination dots
- Add `loading="lazy"` to images beyond first viewport
- Flag: `freeman_core_sliders_advanced_controls_enabled`

**3.3 — CheapestDefaultVariation strategy selector (Roadmap #9)**
- Setting: `cheapest` / `first_in_stock` / `featured` / `disabled`
- Per-product opt-out via product meta box
- New filter: `freeman_core/cheapest_variation/strategy`
- Flag: `freeman_core_cheapest_variation_strategy_enabled`

---

## Wave 4 — P2 polish

**4.1 — RestockNotify CSV export + GDPR (Roadmap #10)**
- Admin button: download `rsn_subscribers` as CSV
- Hook into `wp_privacy_personal_data_export_*` and `wp_privacy_personal_data_eraser_*`
- Flag: `freeman_core_restock_notify_csv_export_enabled`

**4.2 — Slider design tokens as Elementor controls (Roadmap #11)**
- Expose `--cs-bg`, `--cs-ink`, `--cs-mute`, `--cs-line`, arrow size/radius/duration as widget controls
- Existing CSS variables stay as fallbacks
- No flag (additive — controls default to current values)

**4.3 — InfiniteScroll skeleton/fade tokens (Roadmap #12)**
- Settings: skeleton shimmer color, animation duration, fade-in transform/duration
- Existing CSS variables stay as fallbacks
- No flag (additive)

---

## Bug-proofing techniques (apply to every PR)

- **Snapshot before/after**: capture module output before first commit. After changes, diff with flag OFF — must be empty.
- **Local testing first**: every Wave-1 and Wave-2 PR runs on a separate local WP install with real product data before merge.
- **Rollback drill**: for each P0 PR, document and test the exact `wp option update` command on the local install.
- **Conflict detection**: re-run the legacy-plugin conflict check (`Dashboard.php:97`) after each Wave-2 merge. New code must not trigger false positives.

---

## What is NOT on the roadmap

Items dropped per `/docs/decisions-2026-04-28.md`:

- ~~Roadmap #7~~ — theme.json unification (Elementor-only, §4.3)
- ~~Roadmap #8~~ — REST API surface (no headless need, §4.4)
- ~~Roadmap #13~~ — Wishlist module (internal-only, §4.1)
- ~~Roadmap #14~~ — Quick-View module (internal-only, §4.1)
- ~~Roadmap #15~~ — Critical CSS / WebP (P3, only if a client asks; can use WP Rocket meanwhile)
- ~~A25~~ — Telemetry (skipped, §4.8)
- ~~D9~~ — Frontend asset URL filter (no white-labeling, §4.7)

Revisit these only if the strategic decisions change.
