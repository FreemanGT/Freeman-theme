# Freeman Plugin Suite — Roadmap

**Last updated**: 2026-05-20 (Wave 6 — Shop Filters new module — Phase 6.1 foundation ✅ shipped 1.12.0, Phase 6.2 facet engine ✅ shipped 1.12.1, + 1.12.2 indexer bug-fix + 1.12.3 admin toggle/status surface + 1.12.4 sweep-scheduling fix + 1.12.5 status/sweep QA polish, Phase 6.3a frontend read path ✅ shipped 1.12.6 + 1.12.7 (6.3a.1) read-path fix — real query bridge + reload transport + 1.12.8 (6.3a.2) stock-visibility parity + 1.12.9 (6.3a.3) index diagnostic + 1.12.10 (6.3a.4) hyphen-taxonomy + pagination fix, Phase 6.3b Facet UI ✅ shipped 1.12.11 — category-tree facet (navigation) + colour/image swatches + panel styling + mobile drawer + render hooks, Phase 6.3c storefront string labels ✅ shipped 1.12.12 — editable panel wording (blank = English default), enabling Hebrew without code, Phase 6.5b price facet + sort ✅ shipped 1.12.13 (built ahead of 6.4/6.5a — price bands on top + sort dropdown + site default-sort; on-sale/in-stock deferred to 6.5c) + 1.12.14 in-stock-filtering bug-fix (grid + counts now use the index's per-variation in-stock truth, so a sold-out size drops out) + 1.12.15 UI polish (minimal/rounded panel buttons via theme `--fm-*` tokens) + 1.12.16 button restyle follow-up (panel buttons now use the theme's `.fm-btn` / `.fm-select` primitives so they match the site's buttons; 1.12.15 core styles were theme-overridden) + 1.12.17 search-results faceting (panel facets/counts/tree/price now scope to the search-result set; resolves the 6.3a.1 deferral) + 1.12.18 AWS search-grid enforcement attempt (`the_posts` safety net — did NOT resolve it; AWS isn't rendering from the main query loop, see OPEN note below) + 1.12.19 desktop-toggle fix (mobile Filter button no longer shows on desktop) + 1.12.20 AWS search enforcement at priority 99999 (run last — QA-confirmed; search now filters correctly end-to-end), Phase 6.4 admin facet config ✅ shipped 1.12.21 — per-attribute matrix (show / order / hide-on-categories) writing the facet_config option, flag `freeman_core_shop_filters_admin_config_enabled` (off = auto-derived defaults); per-facet type selector dropped as a no-op on this storefront. Prior: Wave 5.1 — Feature Flags admin page — ✅ shipped 1.11.44; Wave 2.2 / 4g — VariationSwatches settings surface graduated, `settings_hub` flag retired — ✅ shipped 1.11.45)
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

## Active items (2)

The original 15-item roadmap was reduced to 9 after the strategic decisions (dropped: #7, #8, #13, #14, and parts of #15; see `/docs/decisions-2026-04-28.md`). Of the remaining 9, seven shipped through Waves 1.1, 1.2, 2.2, 3.1, 3.2, 3.3, 4.1, 4.3 — see the "Shipped to date" table and wave sections below. #11 partially shipped via Wave 4.2 (CategorySlider variant only); ProductSlider variant remains open.

| # | Priority | Module | Description |
|---|---|---|---|
| 3 | P0 | ProductFeed | Multi-channel feed support (only if a client needs it; otherwise P2) |
| 11 | P2 | ProductSlider | Expose hardcoded design tokens as Elementor controls (CategorySlider variant shipped Wave 4.2 / 1.11.39) |

**Net-new epic (2026-05-20):** Wave 6 — **Shop Filters** (new module, faceted AJAX product filters) — replaces the third-party Filter Everything Pro. Disabled by default, flag-gated per phase, fully reversible. Not an original-audit item. See Wave 6 below.

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
- **4g — Settings surface graduated** — ✅ shipped 1.11.45 (#TBD, 2026-05-11). Completes 4a: the Freeman → Variation Swatches page is now the **sole** editing surface (the `settings_hub` flag is **retired** — `settings_schema()` always returns the 14 keys; `Settings_Reader` always prefers the new `freeman_core_variation_swatches_*` key with the legacy `etucart_vs_*` key as a permanent fallback per §4.5). The legacy WooCommerce → Settings → Products → "Shop swatches" section is **soft-deprecated** — kept registered (Hard Rule #2: the `?section=etucart_vs_shop_pick` URL keeps resolving) but renders only a one-line "moved" notice; WC's save pipeline no longer writes `etucart_vs_*` from there. **Hard Rule #3 `legacy/` edit** in `legacy/includes/class-settings.php` — approved 2026-05-11 for this wave (the `OPT_*` constants and the static read helpers `bool` / `max_visible` / `excluded_category_ids` / `should_apply_on_current_archive` are unchanged; only `add_settings()` changes). A version-gated re-sync migration at 1.11.45 (`Migrations::resync_variation_swatches_settings_from_legacy()`) copies the current `etucart_vs_*` values onto their new-namespace counterparts on sites where the flag was OFF (the default) — closing the post-1.11.21 drift the master plan's "P1 read model" had been guarding against — and is skipped on sites where the flag was ON (their new keys are already current). The `etucart_vs_*` option keys and the `freeman_core_variation_swatches_settings_hub_enabled` option are never deleted (the latter is now vestigial — read once by the migration, ignored thereafter). _(No new flag — this wave retires one. The legacy_settings_url shim repoints at the Freeman page.)_
  - **Tests**: rewrote `tests/VariationSwatchesSettingsReaderTest.php` (dropped the flag-OFF/ON split — the shim is now unconditional); deleted `tests/VariationSwatchesSettingsHubSnapshotTest.php` (the flag-OFF "empty schema" contract no longer exists); added `tests/VariationSwatchesSettingsRelocationTest.php` (schema-unconditional, WC section is a moved-notice, section still registered, flag absent from registry, re-sync method + version/flag gating). `FeatureFlagsAdminTest`'s registry-completeness scan now skips `Core/Migrations.php` (migrations may read a retired flag's option to choose an upgrade path; that's not a runtime gate). Net suite: 389 tests / 1308 assertions.

**Wave 2.2 — ✅ shipped 1.11.28 (2026-05-03); 4g graduation 1.11.45 (2026-05-11)** — the 4e shipped-marker landed the parent per CLAUDE.md hard rule #9; 4g is a later additive sub-PR that closes the §4.5 migration of Roadmap item #4.

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

**3.1 — InfiniteScroll trigger modes (Roadmap #5) — expanded scope (committed 2026-04-29; rescoped 2026-05-04 per `/docs/wave-3.1-master-plan.md`) — ✅ shipped (3.1a: 1.11.33 #38, 2026-05-04; 3.1b: 1.11.35 #TBD, 2026-05-10)**
- Trigger-mode setting: `auto` / `button` / `hybrid`. Concrete semantics in master plan §4-D1; "hybrid" = page-count threshold (UX pattern), distinct from the existing JS triple-stack trigger redundancy (engineering pattern).
- History API setting: `pushState` / `replaceState` / `disabled`. **Not net-new** — pushState already ships at `infinite-scroll.js:411-414`; this exposes existing behavior as configurable. Default `pushState` preserves current behavior byte-identically.
- Selector override via the new `selector` filter: replaces (or augments per master plan §4-D6) the 11-selector hardcoded priority list at `infinite-scroll.js:28-40`.
- Flag: `freeman_core_infinite_scroll_trigger_modes_enabled` (shared by 3.1a + 3.1b — precedent: 3.2a/b). Default off.
- **3.1a** ✅ shipped 1.11.33 (#38, 2026-05-04) — JS-only + settings: trigger_mode / history_mode / hybrid_threshold settings, JS dispatcher gates at `attachObserver` entry + post-`loadNext` threshold check, `applyHistoryMode` wrapper around the existing pushState call. No new hooks (those land in 3.1b). Flag introduced. Behind `freeman_core_infinite_scroll_trigger_modes_enabled` (default off); flag-OFF + flag-ON-default both byte-identical to pre-3.1a.
- **3.1b** ✅ shipped 1.11.35 (#TBD, 2026-05-10) — PHP wrapper render path + 4 deferred hooks + container_selector setting/filter + JS-side selector read.
- **Wave 3.1 known limitation**: Button-mode UI and hybrid post-threshold UI deferred — pick mode='auto' for full functionality. Button mode is functionally equivalent to max_pages=1; hybrid mode auto-loads up to threshold then halts. A future sub-PR or wave can add JS-side button render if a client need surfaces.
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

**3.3 — CheapestDefaultVariation strategy selector (Roadmap #9)** ✅ shipped 1.11.32 (#36, 2026-05-04)
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

**4.1 — RestockNotify CSV export + GDPR (Roadmap #10) — ✅ shipped 1.11.37 (4.1a) + 1.11.38 (4.1b); split per Wave 3.1 precedent**

- **4.1a** ✅ shipped 1.11.37 (#44, 2026-05-11) — WP_Privacy exporter + eraser registered under `freeman-core-restock-notify`. Exporter returns one item per matching subscription with all 9 column fields. Eraser nulls `customer_name`/`customer_email` (empty string — columns are NOT NULL) and flips `status` to `'unsubscribed'`; row preserved as audit trail. Unconditional — privacy hooks are a platform contract, not flag-gated (OS-5 decision call, 2026-05-11). Two new `Subscribers` methods (`find_by_email`, `erase_pii_by_email`) query `$wpdb` directly because no legacy `\RSN_Database` method offers exact-email lookup or PII null semantics, and Hard Rule #3 blocks adding to the legacy class.
- **4.1b** ✅ shipped 1.11.38 (#TBD, 2026-05-11) — `Export Subscribers` submenu under the legacy `restock-notify` parent (OS-1); admin-post.php form + `manage_woocommerce` capability check (cap first, nonce second per WP convention); CSV streams the full `rsn_subscribers` table with UTF-8 BOM, comma delimiter, all 9 columns matching 4.1a Privacy exporter labels byte-for-byte, filename `restock-notify-subscribers-YYYY-MM-DD.csv`. Empty `notified_at` renders as empty cell; empty table emits headers-only CSV. Flag-gated behind `freeman_core_restock_notify_csv_export_enabled` (default off); defense-in-depth — flag-OFF means neither the submenu nor the `admin_post_*` listener attaches. `Subscribers::all()` reads the full table into memory; intended for expected merchant scale (≤ low tens of thousands), streaming variant deferred.

**4.2 — Slider design tokens as Elementor controls (Roadmap #11) — ✅ shipped 1.11.39 (#TBD, 2026-05-11); CategorySlider only**
- 4 color controls (`cs_bg_color` / `cs_ink_color` / `cs_mute_color` / `cs_line_color`) emit `--cs-bg|ink|mute|line` overrides on `{{WRAPPER}} .cs`; empty default → Elementor omits selector → `.cs` block's existing `oklch()` declarations remain (byte-identical pre-4.2 render).
- 3 arrow controls (`cs_arrow_size` px, `cs_arrow_radius` px/%, `cs_arrow_duration` ms) emit new `--cs-arrow-*` vars. CSS consumes them via `var(--cs-arrow-X, <hardcoded fallback>)` so unset → 40px / 50% / .18s (prior values byte-identical).
- No flag — purely additive. `--cs-accent` was already exposed before 4.2; this wave covers the 4 remaining color tokens + the 3 arrow values.
- ProductSlider scope deferred — roadmap line names `--cs-*` only. Future wave can mirror the pattern for `--ps-*` tokens if needed.

**4.3 — InfiniteScroll skeleton/fade tokens (Roadmap #12) — ✅ shipped 1.11.36 (#TBD, 2026-05-11)**
- 5 settings: `shimmer_base_color`, `shimmer_highlight_color`, `shimmer_duration_ms`, `fade_duration_ms`, `fade_transform_px`
- Emitted at runtime as `--fm-is-*` CSS custom properties on `:root` via `wp_add_inline_style` (uniform-shape Mechanism A from Wave 3.1b precedent)
- Existing hardcoded CSS values preserved as `var(--fm-is-X, fallback)` so flag-OFF / no-settings-saved is byte-identical to pre-4.3 render

---

### Off-roadmap waves (4.4 / 4.5 — VariationSwatches compatibility work)

Waves 4.4 and 4.5 were **not** drawn from the original 9-item P2 list. They came out of an early-May client-compat investigation (1.11.6–1.11.17 era) that ran on a separate branch lineage from Waves 4.1 / 4.2 / 4.3. They are recorded here so the 4.4/4.5 numbers are not silently floating; neither has shipped to main.

**4.4 — VariationSwatches preselect timing fix (DROPPED — kept on origin as artifact)**
- Scope intent: archive→PDP variation image race (Issue 3 from internal QA notes).
- Outcome: **dropped.** The original bug did not reproduce on staging; multiple fix attempts regressed Quick View. The investigation surfaced no reliable repro path. Branch `wave-4.4-preselect-timing-fix` is preserved on origin (per the explicit note in PR #17's body) as an artifact of the attempt; not merged, not intended for future merge.
- No version on main carries any 4.4 work.

**4.5 — VariationSwatches WPC FBT + Bundles compatibility (✅ shipped 1.11.40)**
- Scope: forward all `serializeArray()` form fields through to WC's standard `wc-ajax=add_to_cart` endpoint so WPC Product Bundles (`woosb-ids-*`) and WPC FBT (`woobt_ids`) hidden inputs reach the cart. Sister work to the dropped 4.4 attempt.
- **Approved single-line `legacy/` edit** in `templates/variation-buy-box.php` — adds `do_action( 'woocommerce_before_add_to_cart_button' )` inside `.etucart-actions` so WPC FBT's `woobt_ids` injection point exists. Standard WC hook; no-op on sites without compat plugins. Hard Rule #3 exception explicitly framed in PR #17's body and in this entry.
- Flag `freeman_core_variation_swatches_bundle_compat_enabled` (default off) gates the JS payload-build branch, the `window.FreemanCoreVSFlags = { bundleCompat: <bool> }` global, and the `woobt_added_to_cart` → `wc_fragment_refresh` document-body bridge. The template `do_action` is unconditional (Hard Rule #1 additive exception — purely a new injection point, no Freeman-shipped listener).
- PHP-side plumbing: new `Module::inject_feature_flags()` on `wp_enqueue_scripts` priority 10001, emitting the JS global before the `freeman-core` script handle.
- **PR history**: PR #17 (1.11.17 era) carried the design + staging-validated QA against WPC FBT + FunnelKit Cart but never merged — by 1.11.39 it was 22+ version bumps stale and the JS/template/Module diffs would have regressed Waves 2.2/4a, 4b, 4c, 4d, 4e. Re-shipped on a fresh branch from current main, extracting only the bundle-compat additions; PR #17 closed with a link to the v2 PR. The stale `wave-4.5-bundle-fbt-compat` branch is preserved on origin as an artifact (same pattern as Wave 4.4's `wave-4.4-preselect-timing-fix`).
- **Test coverage**: 4 new PHPUnit tests in `tests/VariationSwatchesBundleCompatTest.php` (one in an isolated process to bypass `Etucart_VS_Plugin` pre-loading) covering the inject_feature_flags() plumbing; +4 reported tests / +8 reported assertions. JS behavior changes (full-form serialize + woobt bridge) remain staging-validated per PR #17, as PHPUnit cannot exercise them.

---

## Wave 5 — Admin & operability

Not from the original 9-item audit list. Operability work surfaced from day-to-day use of the suite.

**5.1 — Feature Flags admin page — ✅ shipped 1.11.44 (#TBD, 2026-05-11)**
- New `Freeman → Feature Flags` submenu: one checkbox per flag, grouped by module, each with a one-line "what this gates" description, the equivalent `wp option update …` line, the introducing version, and a "shared switch" marker where one flag drives several sub-features. Saving posts to `admin-post.php` (`freeman_save_feature_flags`, nonce + `manage_woocommerce`), writing each registry flag to `1`/`0`.
- New `Feature_Flags::registry()` — canonical list of all 11 flags (`tools/settings_import`, `variation_swatches/{settings_hub, card_image_swap, image_swatches, tooltip, auto_color, bundle_compat}`, `sliders/advanced_controls`, `cheapest_variation/strategy`, `infinite_scroll/trigger_modes`, `restock_notify/csv_export`) — and `Feature_Flags::option_name()` / `Feature_Flags::is_forced_by_filter()` helpers. The registry is the single source of truth shared by the admin page and the `/docs/feature-flags.md` table; a test asserts it stays in sync with the `is_enabled()` call sites in `src/` (no missing or stale entries).
- A flag whose effective state is forced by a `freeman_core/feature_flag/{module}/{feature}` filter renders its checkbox **disabled** with a "forced by code" note — the DB option can't override a filter, so the UI says so rather than lying. Such flags are also skipped by the save handler.
- **No feature flag** — this is infra (a new admin submenu, writes only on user action), same class as the unflagged Dashboard / Tools pages; Hard Rule #1's additive exception applies. Also fixed the stale `/docs/feature-flags.md` "Active flags" table (added the missing 3.1 / 3.2 / 3.3 / 4.1b rows; corrected the auto-color "4e (pending)" marker to 1.11.28) and added an "Admin page" + registry-is-canonical note there — stated separately in the PR description per "decisions separate from code".
- **Test coverage**: 8 new PHPUnit tests in `tests/FeatureFlagsAdminTest.php` (registry⇄source completeness both directions, registry-entry shape, no duplicates, `option_name()` format, `is_forced_by_filter()` true/false, hub registers the save handler); +8 reported tests / +239 reported assertions (the assertion count is loop-heavy — several tests iterate the 11-entry registry). Added a `has_filter` / `has_action` smart stub to `tests/bootstrap.php` backed by the existing hook registry.

---

## Wave 6 — Shop Filters (new module — faceted AJAX product filters)

Net-new epic (not from the original audit). Replaces the third-party "Filter Everything Pro" plugin, which renders poorly on this Elementor store (the "shop grid blowout" the theme stopgaps in 1.11.24). Approved 2026-05-20 — see `/docs/decisions-2026-04-28.md` §5 (2026-05-20 addendum) for the schema-change sign-off, indexing approach, and filtered-URL SEO stance. Justified despite §4.1's competitor-parity caution because it serves a concrete internal need (the suite owner relies on shop filtering today and the current third-party tool is failing); internal-only, opinionated-by-default per §4.1.

New module `src/Modules/ShopFilters/` (namespace `Freeman\Core\Modules\ShopFilters`), **disabled by default** and additionally gated per phase by `freeman_core_shop_filters_<feature>_enabled` flags (default off) — purely additive and reversible; touches no other module or theme core. Carries the suite's first module-owned index table (`{prefix}freeman_shop_filter_index`), auto-installed via `Migrations::run()` (dbDelta), dropped on uninstall. Admin-AJAX per §4.4 (no REST). Catalog scale: medium (~1k–10k). Versioning: **1.12.0** for Phase 6.1, then 1.12.x per phase (minor bump for a new module; not a major bump).

Sub-PR phases (each ≤12 files, ≤3 modules, independently shippable, flag-gated; each code phase marks its own bullet `✅ shipped <version> (#PR, date)`, bumps the Last-updated line, and — when it adds tests — the CLAUDE.md PHPUnit count):

- **6.0 — Roadmap + decision record** — docs only (this entry + the §5 decisions addendum). No code, no version bump. Kept separate from code per "decisions separate from code".
- **6.1 — Foundations** ✅ shipped 1.12.0 (2026-05-20) — module skeleton + `Database` index table (the schema change) + event-driven `Indexer` (dirty-queue + reconcile sweep; Action-Scheduler-or-wp-cron) + `Term_Helpers` (duplicated pure swatch/in-stock helpers, decoupled from VariationSwatches) + admin "Reindex now" tool. No storefront output. Flag `freeman_core_shop_filters_indexer_enabled`. **1.12.2 bug-fix** (caught in review before QA): non-variation global attributes on variable products now follow overall stock instead of being wrongly variation-gated (which would have hidden them under in-stock-only filtering). **1.12.3** (by request): the Freeman → Shop Filters page now carries the indexing toggle, live index status (products / rows / last sweep / scheduled) and the reindex tool — all manageable from wp-admin without WP-CLI. The toggle is a `settings_schema` checkbox writing the same option the feature flag reads. **1.12.4** bug-fix (found in QA): the recurring reconcile sweep now schedules correctly — `ensure_scheduled()` was running on `plugins_loaded` before Action Scheduler's store is ready (silent no-op); deferred to `init`. **1.12.5** (QA polish): the status line shows the actual last-refresh time (not the internal resume watermark, which read as a confusing old date during catch-up); a full reindex parks the watermark at "now" so the sweep doesn't re-churn; the catch-up chain uses Action Scheduler; an on-page note appears when `DISABLE_WP_CRON` is set.
- **6.2 — Facet engine** ✅ shipped 1.12.1 (2026-05-20) — pure `Facet_Engine` / `Category_Tree` / `Url_State` / `Facet_Config`: AND-across / OR-within with self-exclusion, hide-zero (req #2), hide-empty-facet (req #1), category hierarchy (req #3). No flag (dormant helper classes — Hard Rule #1 additive exception). Hooks `freeman_core/shop_filters/{facet_config, is_facet_visible}`. **Scope drift:** `Query_Builder` (the index→facet-engine `$wpdb` glue) moved to 6.3a, where it's wired to the AJAX endpoint and integration-tested rather than shipped dormant.
- **6.3a — Frontend read path** ✅ shipped 1.12.6 (2026-05-20) — `Query_Builder` (index → facet-engine glue, moved from 6.2) + `[freeman_shop_filters]` shortcode (server-renders the initial facet tree) + public admin-AJAX query endpoint `freeman_core_shop_filters_query` (nonce + rate-limited) + JS grid-swap (reuses InfiniteScroll container detection; fetches the filtered front-end URL for Elementor markup parity + IS coexistence) + checkbox facets. Flag `freeman_core_shop_filters_frontend_enabled` (default off; gates both shortcode output and the public endpoint). **Scope trim:** the `default_orderby` / `products_per_page` / `in_stock_only_default` option keys the plan pencilled in for 3a are deferred — the grid is the filtered front-end URL, so WooCommerce owns pagination/orderby/per-page; revisit in 6.5b. No render hooks here (those are 6.3b). **1.12.7 (6.3a.1) read-path fix** (found in QA): 6.3a never actually filtered the grid — there was no server-side bridge and our `filter_pa_*` params are intentionally distinct from WC's native `filter_<attr>`. Added `Query` (a `woocommerce_product_query_tax_query` filter for shop/category/attribute archives + a scoped `pre_get_posts` for product search) so a plain load filters the main query, and switched the front-end controller from AJAX grid-swap to full **reload** navigation — selection now persists in the URL, sort keeps the filters, search pages work, and Infinite Scroll runs normally on the reloaded page. Chips are server-rendered; the public AJAX endpoint is retained (Hard Rule #2) but no longer called by the bundled JS. Known deferral: facet *counts* on a search page still reflect the whole catalogue (the index has no full-text search subset) — a later refinement. **1.12.8 (6.3a.2) stock-visibility parity** (found in QA — a facet value showed "(1)" but the filtered URL returned 0 products): the facet base + counts now mirror the storefront grid when the store hides out-of-stock items (`woocommerce_hide_out_of_stock_items`), so a value backed only by a hidden out-of-stock product no longer shows a phantom count; counts are by product-level presence within the in-stock base, matching WC's term matching. **1.12.9 (6.3a.3) index diagnostic** (QA aid): a read-only table on the Freeman → Shop Filters page (gated by the indexer flag, beside the reindex tool) listing each attribute term's slug / name / indexed product + in-stock counts straight from the index — to debug storefront *data* problems (scrambled term name↔slug pairs, orphaned index terms, all-out-of-stock values) that make a count disagree with the grid or make one size return another. Reproduced by a third-party filter plugin too — but the diagnostic showed the data was fine, which pointed the finger back at our code (see 1.12.10). **1.12.10 (6.3a.4) hyphen + pagination fix** (the actual blank-grid root cause): `Url_State::sanitize_taxonomy` stripped hyphens, mangling hyphenated attribute taxonomies (`pa_shoe-size` → `pa_shoesize`) so the `tax_query` matched nothing — every hyphenated-attribute (i.e. size) filter returned blank; hyphens are now allowed. Also: applying a filter from a `/page/N/` URL kept the pretty-pagination path and could 404 on a smaller filtered set — the controller now resets the `/page/N/` segment to page 1. (This re-frames 1.12.8: the grid was empty because of the mangled taxonomy, not OOS; the stock-parity fix stays correct but wasn't the grid fix.)
- **6.3b — Facet UI** ✅ shipped 1.12.11 (2026-05-20) — the deferred storefront UI from 6.3a. Category-tree facet renders on top as a pruned, count-rolled-up parent → child hierarchy (`Category_Tree` wired into `Query_Builder`); **design decision: category nodes navigate to their own archive (`get_term_link`), not a `filter_product_cat=` param** — categories are navigation on this store. Attribute facets whose terms carry VariationSwatches term meta render as colour/image swatches, read directly via `get_term_meta` (`etucart_swatch_color` / `freeman_core_variation_swatches_term_image_id`) — no call into the VariationSwatches module (decisions §5.7); the underlying checkbox is preserved so the reload transport is unchanged. New stylesheet (removes the default list bullets) + mobile bottom-sheet drawer: focus-trapped, ESC/overlay close, `prefers-reduced-motion`, and **defer-until-Apply on mobile** (ticking collects the selection; only "Apply" navigates — the standard drawer pattern, vs desktop's navigate-on-tick). **Hook-name scope change:** the planned `.../grid_html` filter has no grid to filter under the 6.3a.1 reload transport (WooCommerce renders the grid on reload), so it ships as `freeman_core/shop_filters/panel_html` (filters the returned panel markup) alongside the `before_render` / `after_render` actions. Reuses `frontend_enabled` — no new flag.
- **6.3c — Storefront string labels** ✅ shipped 1.12.12 (2026-05-20) — by request: every user-facing string in the filter panel (mobile toggle, drawer title/aria, Close, Categories heading + aria, Clear all, Apply, Clear, and the singular/plural result-count line) is now an editable `text` field on the Freeman → Shop Filters settings page. A new `Labels` class is the single source of truth (key → admin-label + English default); `settings_schema()` builds the fields from it and the templates resolve through `Labels::get()`. **Additive, no flag** (Hard Rule #1 exception): a blank field falls back to the English default, so output is byte-identical until the owner types an override — which is how the site sets Hebrew wording without code (resolves the 6.3b i18n open question, §4.2).
- **6.5b — Price facet + sort** ✅ shipped 1.12.13 (2026-05-20) — built **ahead of 6.4 / 6.5a** by request, so it took the next sequential version (1.12.13), not its originally-pencilled 1.12.15. Price renders on top as OR-checkbox **bands** (a `price_bands` setting of comma-separated upper bounds, or auto-derived rounded bands when blank); band counts + storefront grid filtering read min/max from `wc_product_meta_lookup` (§5.2 — never duplicated) via a `posts_clauses` overlap join, carried in a dedicated `filter_price=min-max,min-` URL param. A **Sort by** dropdown sets `?orderby` (WooCommerce honours it on reload) and a **default-sort** setting drives `woocommerce_default_catalog_orderby` site-wide. Reuses `frontend_enabled`. **Scope note:** the original 6.5b also listed **on-sale / in-stock** facets — those are **deferred** (not yet built); revisit as a later 6.5c if wanted. v1 approximation: term-facet counts don't subtract an active price selection (price lives outside the term engine); grid + band counts stay correct.
- **1.12.14 in-stock-filtering bug-fix** (found in QA) — filtering an attribute returned products whose selected value was sold out: WooCommerce assigns a variable product *all* its variation option terms, so the grid's `tax_query` matched a parent even when that size's variations were out of stock, and the counts mirrored it (`postings_for_products(..., false)`). Now both read the index's per-variation in-stock truth — counts use `postings_for_products($base, $hide_oos)` and the grid is constrained to the index's in-stock product set via `post__in` (`Query::apply_instock_post_in`, gated on the index having rows; the `tax_query` stays as the fallback when indexing is off). Realigns the read path with requirement #2. Keys off `woocommerce_hide_out_of_stock_items` (enabled on the live store).
- **1.12.15 UI polish** (by request) — filter-panel buttons (mobile toggle, active-filter chips, clear/clear-all, Apply, sort `<select>`) restyled more minimal + rounded, referencing the theme's `--fm-*` design tokens (`--fm-radius-pill`, `--fm-radius-md`, `--fm-button-font-weight`, `--fm-button-tracking`) with fallbacks so they harmonise with the store. CSS-only (`shop-filters.css`, cache-busted by `FREEMAN_CORE_VERSION`).
- **1.12.16 button restyle follow-up** — the 1.12.15 core button CSS was being overridden by the theme/Elementor on the live store (so no visible change). Instead of fighting specificity, the panel toggle / Apply / Clear / sort now carry the theme's own `.fm-btn` (+ `--ghost` / `--block` / `--link`) and `.fm-select` primitives, so they render identically to the site's buttons (and inherit RTL handling). Template-only; the core `shop-filters.css` button styles remain as the no-theme fallback.
- **1.12.17 search-results faceting** (urgent, by request; resolves the 6.3a.1 deferral) — on a product-search page (`?s=…&post_type=product`) the panel now facets over the products matching the query instead of the whole catalogue. `Shortcode` passes `get_search_query()` into the request; `Query_Builder` seeds the base universe from an unpaginated product search (`search_product_ids()`) intersected with the index via the new `Index_Repository::filter_indexed()` (in-stock-aware when the store hides OOS). Facets, counts, category tree and price bands all follow. Selecting a filter already worked on search (the `Query` bridge); this fixes the *panel relevance*. **Caveat:** the base uses a generic product `WP_Query` `s=` search — a search plugin (e.g. Advanced Woo Search / `type_aws`) that only refines the *main* query may yield a slightly different set than its own results; revisit with an AWS-specific integration if the panel and grid diverge.
- **1.12.18 AWS search-grid enforcement** (urgent follow-up to 1.12.17) — on the live store the search-results grid is supplied by **Advanced Woo Search** (`type_aws=true`), which bypasses our query-level `post__in` / `tax_query`, so a product whose only in-stock size was outside the selection still showed (the bug was gone on category pages, present only on search). Added a `the_posts` safety net (`Query::enforce_filters_on_search` + pure `filter_posts_to_ids`) that drops any product not in the index's in-stock set for the active selection — scoped to the front-end main *search* product query, only when filters are active and the index has rows. Works regardless of how the search plugin builds the list (SQL or short-circuit), as long as the grid uses the main query loop. Known limitation: post-filtering can leave a search page slightly under-filled vs its reported count (pagination is computed pre-filter) — acceptable for correctness; revisit if it bites.
- **1.12.19 desktop-toggle fix** — the mobile "Filter" toggle was leaking onto desktop: after 1.12.16 the toggle carries the theme's `.fm-btn` (`display:inline-flex`, 0,2,0), which beat the plain `display:none`. The desktop hide + mobile show are now `!important` so they win. CSS-only.
- **6.4 — Admin facet config** ✅ shipped 1.12.21 (2026-05-20) — per-attribute matrix on `freeman_core/module_page/shop_filters`: show/hide a filter, reorder it, and hide it on chosen categories (a fetched product-category multiselect). Writes the `freeman_core_shop_filters_facet_config` option `Facet_Config` already reads; the read is now gated by the new flag `freeman_core_shop_filters_admin_config_enabled` (default off) so flag-off fully reverts any saved config to the auto-derived defaults. Save via `admin_post_freeman_core_shop_filters_save_facet_config` → pure `Facet_Config::sanitize()`. **Scope trim:** the pencilled per-facet **type** selector was dropped — on this storefront type is auto-derived (categories = navigation tree, attributes = checkbox auto-upgrading to swatch when term meta exists), so a type control would change nothing; the matrix exposes only show / order / hide-on-category. No JS (plain POST form), no new hooks (deferred the speculative `admin_facet_types` filter).
- **6.5a — SEO policy** (targets 1.12.22) — filtered URLs get canonical → clean URL + `noindex,follow` (query-string params only; no rewrite rules). Flag `freeman_core_shop_filters_seo_policy_enabled`.
- **6.5c — On-sale / in-stock facets** (deferred from 6.5b; targets 1.12.23) — the remaining `wc_product_meta_lookup`-sourced numeric facets. Reuses `frontend_enabled`. Promote on need.

> **RESOLVED — AWS search grid (1.12.20):** on a search page the grid responds to our `tax_query` (it IS the main query), but sold-out-size products survived because Advanced Woo Search re-asserts its own result list on `the_posts` *after* us. Moving our `the_posts` enforcement (`Query::enforce_filters_on_search`) to priority `99999` so it runs last fixed it (QA-confirmed). Together with 1.12.17 (search-scoped facets), search now filters correctly end-to-end.

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
