# Freeman Plugin Suite — Roadmap

**Last updated**: 2026-05-04 (Wave 3.3 shipped — strategy selector + per-product meta override on CheapestDefaultVariation)
**Owner**: Yiftach
**Reflects decisions in**: `/docs/decisions-2026-04-28.md`

This is the execution plan. Waves run in order. Items within a wave can run in parallel only if they touch separate modules.

## Shipped to date

| Wave | Description | Version | Merged | PR |
|---|---|---|---|---|
| 1.1a | Module hooks (gates / data filters) — 3 hooks | freeman-core 1.11.0 | 2026-04-28 | [#6](https://github.com/FreemanGT/Freeman-theme/pull/6) |
| 1.1b | Module hooks (render and feed) — 7 hooks + 2 snapshots | freeman-core 1.11.1 | 2026-04-28 | [#7](https://github.com/FreemanGT/Freeman-theme/pull/7) |
| 1.2 | RestockNotify locale bootstrapper (English defaults, Hebrew opt-in) | freeman-core 1.11.2 | 2026-04-28 | [#9](https://github.com/FreemanGT/Freeman-theme/pull/9) |
| 2.3a | RestockNotify modern Subscribers repository wrapper | freeman-core 1.11.3 | 2026-04-29 | [#10](https://github.com/FreemanGT/Freeman-theme/pull/10) |
| 2.3b | RestockNotify modern Email + Stock_Monitor (bilingual fix + 2 hooks) | freeman-core 1.11.4 | 2026-04-29 | [#11](https://github.com/FreemanGT/Freeman-theme/pull/11) |
| 2.3c | RestockNotify modern Frontend (Hebrew-JS-strings fix + `should_inject` hook) | freeman-core 1.11.5 | 2026-04-29 | _this PR_ |

Wave-1.1's `infinite_scroll/selector` / `before_render` / `after_render` are still folded into Wave 3.1. With 2.3c shipped, all 3 deferred Wave-1.1 RestockNotify hooks are now live (`should_inject` from this wave; `email_args` + `before_send` from 2.3b). Wave 2.3 closed; Wave 3 (P1 functional improvements) is next.

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

**1.1 — Add module hooks (Roadmap #1)** ✅ shipped — see "Shipped to date" table above

Originally scoped as 18 hooks across 9 modules per `/docs/audit-2026-04-28.md` §D1. After per-call-site reality check during pre-flight (2026-04-29), only 10 of those hooks have viable call sites in non-legacy `freeman-core` PHP today (one further hook — `infinite_scroll/selector` — was dropped from 1.1a after finding the JS doesn't yet read it; folded into Wave 3.1 instead). Wave 1.1 ships the implementable subset; the rest are deferred to natural homes (see Wave 2.3, Wave 3.1, Wave 3.4 below; VariationSwatches's 2 hooks already deferred to Wave 2.2).

Split into two PRs (waiver from "one roadmap item per PR" — stated in each PR description):

**1.1a** ✅ shipped 1.11.0 (#6, 2026-04-28) — gates / data filters (3 hooks, no rendering refactors). Lands shared infra: 1.11.0 version bump, CLAUDE.md infra-state refresh, this roadmap edit.
- `freeman_core/cheapest_variation/should_apply` (filter)
- `freeman_core/cheapest_variation/chosen` (filter — replaces audit's `strategy` because the picker returns an attributes array, not a variation_id)
- `freeman_core/variable_stock_fix/should_check` (filter)

**1.1b** ✅ shipped 1.11.1 (#7, 2026-04-28; forward-merged via #8) — render and feed (7 hooks; 2 snapshot tests).
- `freeman_core/category_slider/query_args` (filter)
- `freeman_core/category_slider/render_card` (filter, via output buffering)
- `freeman_core/product_slider/query_args` (filter)
- `freeman_core/product_feed/query_args` (filter)
- `freeman_core/product_feed/item` (filter)
- `freeman_core/product_feed/before_serve` (action)
- `freeman_core/product_feed/after_generate` (action)
- New CategorySlider snapshot + ProductFeed byte-identity snapshot

Standards for both:
- Each hook: `@since 1.11.0` PHPDoc with `@param` descriptions
- Each hook: at least one test verifying the filter mutates output / the action fires
- With no listeners attached, output must be byte-identical to current

### Deferred from 1.1

Wave 2.3 and the Wave 3.1 expansion below are committed work, approved 2026-04-29 alongside Wave 1.1a. Wave 3.4 remains proposed (not yet approved); promote only once an internal MyAccount extension need surfaces.

**1.2 — RestockNotify locale bootstrapper (Roadmap #2)** ✅ shipped 1.11.2 (#9, 2026-04-28)
- Per `/docs/decisions-2026-04-28.md` §4.2: English defaults, Hebrew opt-in
- Create `RestockNotify/locales/en_US.php` and `he_IL.php`
- On activation: detect `get_locale()`, install matching defaults
- **Existing installs**: do NOT overwrite their option values (check if values exist first)
- Email-body **option** strings ship per locale; the email **shell** strings (greeting / unsubscribe link / suffix / customer-name fallback) stayed hardcoded Hebrew in `legacy/class-rsn-email.php` until Wave 2.3b's full bilingual fix
- Reading A from pre-flight (no `legacy/` edits) — locale files = data; send path unchanged in 1.2
- Q3 from pre-flight: NO retroactive change for installs that activated pre-1.11.2; their existing `rsn_*` option values stay untouched

---

## Wave 2 — P0 features (each behind flag, each independently shippable)

**2.1 — ProductFeed multi-channel (Roadmap #3)**
- Only start this if a specific client has asked. Otherwise demote to P2.
- New submodule `ProductFeed/Channels/` with `Google_Xml`, `Facebook_Csv`, `Pinterest_Xml` implementing `Channel_Interface`
- Existing `/product-feed` endpoint keeps Google XML output unchanged
- New endpoints: `/product-feed/<channel>`
- Feature flag: `freeman_core_product_feed_multichannel_enabled`
- **Before extending Generator's surface**: read the header comment in `tests/snapshots/__fixtures__/wc_product_stub.php` — it lists which `\WC_Product` methods the snapshot stub already covers and which still need to be added (variable-product branch + `\WC_Product_Variation` stub) before variation XML can be snapshotted.

**2.2 — VariationSwatches migration + image swatches + tooltip + auto-color + card-image-swap (Roadmap #4)**

Master plan approved 2026-05-03 — see [/docs/wave-2.2-master-plan.md](wave-2.2-master-plan.md). The plan doc is the source of truth for cross-cutting concerns (hard rule #3 legacy/ coverage, decision dependencies, version-skew model, read-shim contract, feature-flag table). Sub-PR pre-flights cite anchors in the plan doc instead of re-litigating.

**Scope expansion call-out**: this wave was originally scoped as 3 sub-PRs (4a settings migration, 4b image swatches, 4c tooltip — per the audit). The approved master plan **expands to 6 sub-PRs**, adding 4d (auto-color sampler), 4e (auto-color fallback wiring), 4f (variation-image-on-card swap). 4d/4e/4f were not in the audit; they came from the 2026-05-03 pre-flight and are in some tension with §4.1 of the decisions doc (competitor-parity caution). The master plan §1 explains why they were approved despite §4.1 (cheap follow-ons on top of 4a's infrastructure; gated by flag, off by default).

Sub-PR ship order: **4a → 4f → 4b → 4c → 4d → 4e** (rationale in master plan §7). 4d and 4e share a flag — see master plan §6.

Sub-PR statuses (to be updated as each ships):

- **4a — Settings migration to Settings_Hub** — ✅ shipped 1.11.21 (2026-05-03) — flag `freeman_core_variation_swatches_settings_hub_enabled`
- **4f — Variation-image-on-card swap** — ✅ shipped 1.11.23 (2026-05-03) — flag `freeman_core_variation_swatches_card_image_swap_enabled`
- **4b — Image swatches** — ✅ shipped 1.11.24 (2026-05-03) — flag `freeman_core_variation_swatches_image_swatches_enabled`
- **4c — Tooltip on hover** — ✅ shipped 1.11.25 (2026-05-03) — flag `freeman_core_variation_swatches_tooltip_enabled`
- **4d — Auto-color sampler (pipeline + caching + scheduling)** — ✅ shipped 1.11.27 (2026-05-03) — flag `freeman_core_variation_swatches_auto_color_enabled` _(shared with 4e)_
- **4e — Auto-color fallback wiring (render path)** — ✅ shipped 1.11.28 (2026-05-03) — flag `freeman_core_variation_swatches_auto_color_enabled` _(shared with 4d)_

**Wave 2.2 — ✅ shipped 1.11.28 (2026-05-03)** — parent shipped-marker landed with 4e per ship order, per CLAUDE.md hard rule #9.

**2.3 — RestockNotify legacy migration (committed 2026-04-29)**

Parallels Wave 2.2's VariationSwatches migration. Required before the three deferred Wave-1.1 RestockNotify hooks (`should_inject`, `email_args`, `before_send`) can be added — call sites live in `legacy/includes/class-rsn-*.php`, which hard rule #3 forbids editing without a migration plan.

Master plan (approved 2026-04-29) split execution into 3 sub-PRs (2.3a → 2.3b → 2.3c); the originally-proposed 2.3d (Ajax + Admin) is **skipped indefinitely** per Q-B since neither surface has any extension demand.

**2.3a** ✅ shipped 1.11.3 (#10, 2026-04-29) — modern `Subscribers` repository wrapping `\RSN_Database`. Pure groundwork (4-method static wrapper, no callers in this PR). Becomes canonical in 2.3b/c.

**2.3b** ✅ shipped 1.11.4 (#11, 2026-04-29) — modern `Email` + `Stock_Monitor` via `class_alias` swap in `Module::boot()`. Bilingual-email shell fix (4 Hebrew literals moved to `locales/<locale>.php` `shell_*` keys). 2 of the 3 deferred hooks land: `freeman_core/restock_notify/email_args` (filter) and `before_send` (action). The 3rd deferred hook (`should_inject`) waits for 2.3c.

**2.3c** ✅ shipped 1.11.5 (this PR, 2026-04-29) — modern `Frontend` via `class_alias` swap. Lands the final deferred Wave-1.1 hook `freeman_core/restock_notify/should_inject` (per-product render gate, distinct from `rsn_should_enqueue`'s page-level asset gate) and moves 8 Hebrew literals into `locales/<locale>.php` (`js_*` for the `wp_localize_script` payload, `form_placeholder_*` for the inline form input placeholders). The 6-case `is_variation_truly_oos()` ladder copied verbatim from legacy with one unit test per branch. Browser-side JS-relocation parity for `footer_inject` is live-QA-only; PHPUnit locks the PHP-side output of all 4 injection paths.

Each sub-PR keeps `legacy/` files untouched; modern classes shadow via `class_alias`. Existing legacy filters (`rsn_should_enqueue`), AJAX action (`rsn_subscribe`), shortcode (`[restock_notify]`), unsubscribe URL pattern, transient cache key shape, asset handles, admin URLs, and the `{prefix}rsn_subscribers` table are all preserved verbatim across the migration.

---

## Wave 3 — P1 functional improvements

Each item is its own PR with its own feature flag. Order within wave doesn't matter.

**3.1 — InfiniteScroll trigger modes (Roadmap #5) — expanded scope (committed 2026-04-29)**
- Setting: `auto` / `button` / `hybrid` (auto first 2 pages, button after)
- Selector override (currently hardcoded to `.products`)
- History API integration toggle (push state on each page load)
- Flag: `freeman_core_infinite_scroll_trigger_modes_enabled`
- **Folds in 3 hooks deferred from Wave 1.1**:
  - `freeman_core/infinite_scroll/selector` (filter) — replaces the hardcoded `.products` selector. Lands together with the JS-side read so the hook actually controls behavior.
  - `freeman_core/infinite_scroll/before_render` (action) — fires before the PHP-side render that this wave introduces (the module is JS-only today).
  - `freeman_core/infinite_scroll/after_render` (action) — fires after.
  - Each hook gets `@since` matching the version this wave ships in, plus a hook test asserting firing + payload.

**3.2 — Slider autoplay/loop/dots/lazy (Roadmap #6)** — ✅ shipped (3.2a: 1.11.29 #31, 2026-05-03; 3.2b: 1.11.30 #33, 2026-05-04)
- New Elementor controls: autoplay, autoplay delay, loop, pagination dots
- Add `loading="lazy"` to images beyond first viewport
- Flag: `freeman_core_sliders_advanced_controls_enabled` (introduced in 3.2a; reused — not redefined — by 3.2b)
- Split into 2 sub-PRs sharing the same flag (precedent: Wave 2.2 / 4d+4e):
  - **3.2a** ✅ shipped 1.11.29 (#31, 2026-05-03) — CategorySlider only. Indicator selector (`progress` / `dots` / `none`) supersedes the legacy `show_progress` switcher with a back-compat shim. Autoplay-wrap loop only (drag-past-end-wraps deliberately out of scope). Render path also gated on flag — rollback is byte-identical.
  - **3.2b** ✅ shipped 1.11.30 (#33, 2026-05-04) — ProductSlider only. Same controls + back-compat shim as 3.2a; advanced controls additionally gated on `display_mode = slider` (autoplay / loop / indicator have no meaning in grid mode), so grid-mode output is unchanged. Runtime is the shared `category-slider.js` engine from 3.2a — no JS edits needed.
- **Lazy-load line is a no-op for in-wave work** — CategorySlider uses CSS `background-image` (not `<img>`), so the HTML `loading="lazy"` attribute does not apply. ProductSlider's `<img>` markup already receives WP-core auto-lazy (since WP 5.5). Real lazy-loading on CategorySlider's CSS backgrounds, if ever wanted, would be a separate Wave 3.x or later item using `IntersectionObserver`. This explicit acknowledgement exists so a future auditor doesn't read the original roadmap line as unmet.

**3.3 — CheapestDefaultVariation strategy selector (Roadmap #9)** ✅ shipped 1.11.32 (#TBD, 2026-05-04)
- Setting: `cheapest` / `first_in_stock` (select). Default `cheapest` — flag-ON sites with default setting see byte-identical behavior to flag-OFF.
- Scope reduction from original audit: dropped `featured` (undefined for variations in WC core — variations have no `featured` flag; no client need surfaced) and `disabled` (redundant with the module's own enable toggle and with the existing `should_apply` filter returning false). Hard rule #2 not engaged — neither was ever shipped.
- Per-product opt-out: meta-only via post meta key `_freeman_cheapest_variation_strategy` (string in enum, or empty/missing = use global). No admin meta-box UI in this wave — a future wave can add the box non-breakingly if a client surfaces the need.
- New filter: `freeman_core/cheapest_variation/strategy` (`@since 1.11.32`). Resolution order: setting → meta override → filter override (filter is final word). Out-of-enum filter values fall back to pre-filter resolved value with a Logger warning.
- Flag: `freeman_core_cheapest_variation_strategy_enabled`. Flag-OFF preserves the current hardcoded cheapest path verbatim.

**3.4 — MyAccount endpoint extensibility (PROPOSED, NOT YET APPROVED)**
- Proposed during Wave 1.1 pre-flight to home the two deferred MyAccount hooks (`endpoints` filter, `sidebar_html` filter). MyAccount today is CSS-only — no PHP render path exists.
- Promote to committed only when an internal MyAccount extension need surfaces; until then this stays out of scope and may be dropped entirely.

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
