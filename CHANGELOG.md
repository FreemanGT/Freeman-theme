# Freeman Changelog

This is the aggregated changelog across both packages. See each package's own `CHANGELOG.md` for package-scoped history.

## [1.12.10] — 2026-05-20

- Shop Filters 6.3a.4 hyphen + pagination fix: filtering by a hyphenated attribute (pa_shoe-size, pa_clothing-size) returned no products. Url_State sanitize_taxonomy stripped hyphens, mangling the taxonomy (pa_shoe-size to pa_shoesize) so the tax_query matched nothing — every size filter came back blank. Hyphens are now allowed in attribute taxonomies. Also: applying a filter from a /page/N/ URL kept the pretty-pagination path and could 404 when the filtered result set had fewer pages; the front-end controller now resets the /page/N/ path segment to page 1, alongside the existing paged query-param reset.

## [1.12.9] — 2026-05-20

- Shop Filters 6.3a.3 index diagnostic: a read-only table on the Freeman Shop Filters admin page (gated by the existing indexer flag, alongside the reindex tool) listing, per attribute term, the slug, name and indexed product / in-stock counts straight from the index. Built to debug storefront-data problems where a facet value shows a count but the filtered URL returns nothing, or picking one size returns another — surfacing scrambled term name/slug pairs, terms present in the index that no longer resolve to a live term, and values whose only products are out of stock. Filters match by slug, so the slug column is highlighted as the source of truth. Read-only, no writes, no storefront effect.

## [1.12.8] — 2026-05-20

- Shop Filters 6.3a.2 stock-visibility parity: facet counts now mirror the storefront grid. When the store hides out-of-stock items (woocommerce_hide_out_of_stock_items), the facet base universe and counts exclude out-of-stock products, so a value backed only by a hidden out-of-stock product no longer shows a phantom count while the filtered grid is empty. Attribute values are counted by product-level presence within the in-stock base, matching how WooCommerce matches a product to a term.

## [1.12.7] — 2026-05-20

- Shop Filters 6.3a.1 read-path fix: storefront filters now actually filter the grid. New Query bridge applies the URL filter selection (filter_pa_*) to the main WooCommerce product query via woocommerce_product_query_tax_query (shop / category / attribute archives, preserving product_visibility) plus a scoped pre_get_posts for product search. The front-end controller now navigates (full reload) instead of swapping via AJAX, so the selection persists in the URL, sort keeps the filters, and product-search pages work; Infinite Scroll runs normally on the reloaded page. Active-filter chips are server-rendered. The public AJAX endpoint is retained but no longer called by the bundled JS. Checkbox facets only; reuses the freeman_core_shop_filters_frontend_enabled flag.

## [1.12.6] — 2026-05-20

- Shop Filters 6.3a: storefront read path. Query_Builder glues the index to the facet engine and shapes the AJAX response; the freeman_shop_filters shortcode server-renders the initial facet tree and enqueues the front-end controller; a public admin-AJAX endpoint (freeman_core_shop_filters_query, nonce + rate-limited) recomputes facets and counts. JS swaps the product grid by fetching the filtered front-end URL so Elementor card markup and Infinite Scroll coexistence are preserved. Checkbox facets only; gated by the new default-off freeman_core_shop_filters_frontend_enabled flag.

## [1.12.5] — 2026-05-20

- Shop Filters QA polish: status line shows actual last-refresh time (not the internal watermark); full reindex parks the watermark so the sweep does not re-churn; catch-up chain via Action Scheduler; DISABLE_WP_CRON note on the page.

## [1.12.4] — 2026-05-20

- Shop Filters bug-fix found in QA: the recurring reconcile sweep now schedules correctly. ensure_scheduled was running on plugins_loaded before Action Scheduler is ready (silent no-op); deferred to init. Event-driven on-save indexing was unaffected.

## [1.12.3] — 2026-05-20

- Shop Filters admin control surface (by request): Freeman -> Shop Filters page now has the background-indexing toggle, live index status (products/rows/last sweep/scheduled) and the reindex tool, all manageable from wp-admin without WP-CLI. Toggle writes the same option the feature flag reads.

## [1.12.2] — 2026-05-20

- Shop Filters 6.1 indexer bug-fix: non-variation global attributes on variable products now follow overall stock (were wrongly variation-gated, hiding them under in-stock-only filtering). Extracted Term_Helpers::resolve_in_stock + unit test; hardened ensure_scheduled Action Scheduler check.

## [1.12.1] — 2026-05-20

- Shop Filters facet engine - Wave 6 Phase 6.2: pure Facet_Engine, Category_Tree, Url_State, Facet_Config (AND/OR with self-exclusion, hide-zero, category hierarchy, URL state). No flag, no storefront output yet.

## [1.12.0] — 2026-05-20

- Shop Filters new module - Wave 6 Phase 6.1 foundation: index table, background indexer, admin reindex tool. Behind freeman_core_shop_filters_indexer_enabled flag default off; module disabled by default. Storefront UI ships in later phases.

## [1.11.51] — 2026-05-13

- VariationSwatches: respect wp_terms.term_order column for swatch ordering (honors Custom Taxonomy Order plugin et al). Falls back to wc_get_product_terms when the column is absent.

## [1.11.50] — 2026-05-13

- VariationSwatches: reorder swatch options to match taxonomy term order (matches WC native dropdown). PDP buy-box + shop archive picker.

## [freeman-core 1.11.49] — 2026-05-11

- VariationSwatches: the buy box, the shop / archive picker, the PDP price line and the toast notifications now read the kit body font from Elementor's `--e-global-typography-sk_type_12-font-family` variable (with `inherit` as fallback) instead of bare `font-family: inherit`. Bare `inherit` picked up the *wrapping element's* font when the buy box / picker is AJAX-injected into a foreign-styled container — e.g. WooSQ (Woo Smart Quick View)'s `.woosq-sidebar { font-family: "Open Sans", … }` — so the quick-view buy box wasn't matching the site's Style Kits typeface. A CSS custom property cascades through such wrappers untouched, so the kit font reaches it regardless. No regression: without Style Kits the `inherit` fallback applies as before. CSS-only.

## [freeman-core 1.11.48] — 2026-05-11

- VariationSwatches: the shop / archive variation-picker now inherits the site/theme font instead of forcing a built-in "Ploni"-first stack — matches the 1.11.47 buy-box change so the picker reads in the page typeface (i.e. whatever Style Kits / Elementor global typography sets). CSS-only.

## [freeman-theme 1.11.23] — 2026-05-11

- Typography: `--fm-font-body` / `--fm-font-display` now follow Elementor's global typography (`--e-global-typography-sk_type_12/2-font-family`, written by the Style Kits for Elementor addon) with the previous hardcoded stacks as fallback — the theme no longer overrides Style Kits' fonts. `--fm-font-mono` (code/preformatted) unchanged. Also bumped `FREEMAN_THEME_VERSION` 1.0.3 → 1.11.23 so it matches `style.css` and the theme's CSS asset URLs actually cache-bust on this change (the constant, not `style.css`'s `Version:`, is what `wp_enqueue_style` uses).

## [1.11.47] — 2026-05-11

- VariationSwatches: the simple-product buy box now renders its own `.etucart-pdp-price` line (same markup as the variable buy box). Fixes the missing price in simple-product quick-view modals — the WooSQ duplicate-price suppression was hiding WooCommerce's separately-rendered `<p class="price">`, which the simple buy box previously had nothing to replace. `maybe_suppress_pdp_price()` now also unhooks `woocommerce_template_single_price` for simple products so a plain simple PDP isn't doubled; on Elementor-built simple PDPs the 1.11.46 `:has()` rule consequently hides the Elementor "Product Price" widget there too. On a default-template simple PDP the price moves to just above the add-to-cart button (where the buy box renders), matching the variable buy box.
- VariationSwatches: the buy box (and toast) now inherit the site/theme font instead of forcing a built-in "Ploni"-first stack — the buy box reads in the same typeface as the rest of the page.
- VariationSwatches: the shop / archive variation-picker swatch row is now centred within the card; the single-product buy box's swatch row is back to its original right-anchored layout (the 1.11.46 centring there was reverted).

## [1.11.46] — 2026-05-11

- VariationSwatches: hide the Elementor "Product Price" widget on variable product pages when the buy box's own (live, variation-aware) price line is present — fixes the duplicated price on Elementor-built PDPs. Simple products keep the Elementor widget's price untouched. CSS-only; falls back to current behaviour where :has() is unsupported.
- VariationSwatches: centre the variation swatch row (size pills / colour circles / image chips) instead of right-anchoring it; RTL order is unchanged. The attribute heading above stays right-aligned.

## [1.11.45] — 2026-05-11

- Wave 2.2/4g: VariationSwatches settings moved to Freeman -> Variation Swatches page (sole editing surface); settings_hub flag retired; legacy WooCommerce Products section soft-deprecated to a moved-notice; re-sync migration for flag-off sites

## [1.11.44] — 2026-05-11

- Add Freeman -> Feature Flags admin page (checkbox per flag, grouped by module, with descriptions); Feature_Flags::registry/option_name/is_forced_by_filter helpers

## [1.11.43] — 2026-05-11

- Persist onboarding-notice dismissal so the nudge stays gone after a page reload

## [1.11.42] — 2026-05-11

- ProductSlider — popularity / rating / price orderby now actually sorts and includes products without total_sales / _wc_average_rating / _price meta (bypasses WC's INNER JOIN on the sort meta key). Other orderby values (date, title, menu_order, rand) and the manual / current_query / related sources unchanged. Defensive 5000-ID cap on the in-PHP sort.

## [1.11.41] — 2026-05-11

- Bug-fix bundle (shop archive picker): variation pill gap 6px to 8px and internal padding 12px to 16px; refreshOverflow sweeps rawChips past +N boundary (not just in-stock); render_loop_price_or_skip now skips for simple products too (was variable only, leaving duplicated WC + picker prices on archive); honeypot inputs in shop-simple-pick and shop-variation-pick swapped from left:-9999px hack to WCAG clip:rect pattern so absolute-positioned cards do not inflate slider scrollWidth past last product.

## [1.11.40] — 2026-05-11

- Wave 4.5: VariationSwatches WPC Bundles + FBT compatibility (default-off flag, JS field-forwarding to WC AJAX, single-line legacy template hook for plugin injection)

## [1.11.39] — 2026-05-11

- Wave 4.2 — CategorySlider design tokens exposed as Elementor controls. Adds 4 color controls (--cs-bg, --cs-ink, --cs-mute, --cs-line) and 3 arrow controls (size, radius, duration) on the CategorySlider widget Style tab. Colors default empty so Elementor omits the selector and the existing .cs block oklch() declarations remain. Arrow controls default to the prior hardcoded values (40px / 50% / 180ms); CSS file consumes them via var(--cs-arrow-X, fallback). No flag — purely additive, byte-identical out-of-the-box render.

## [1.11.38] — 2026-05-11

- Wave 4.1b — RestockNotify CSV export admin button. Adds an Export Subscribers submenu under the legacy restock-notify parent menu and an admin-post.php handler that streams the rsn_subscribers table as a UTF-8 BOM CSV with all 9 columns and a date-stamped filename. Gated behind freeman_core_restock_notify_csv_export_enabled (default off). Defense-in-depth: flag-OFF means neither the submenu nor the admin_post listener attaches. Closes Wave 4.1.

## [1.11.37] — 2026-05-11

- Wave 4.1a — RestockNotify WP_Privacy exporter + eraser. Registers wp_privacy_personal_data_exporters and wp_privacy_personal_data_erasers under the freeman-core-restock-notify key so a privacy admin can export or erase a customer's restock-notify subscriptions through WP Tools. Eraser nulls customer_name/customer_email (empty string; columns are NOT NULL) and flips status to unsubscribed; the row stays as audit trail. Unconditional — privacy hooks are a platform contract, not flag-gated. Wave 4.1b will add the CSV admin button behind a flag.

## [1.11.36] — 2026-05-11

- Wave 4.3 — InfiniteScroll skeleton/fade tokens exposed as 5 settings (shimmer base/highlight color, shimmer duration ms, fade duration ms, fade transform px). Emitted at runtime as --fm-is-* CSS custom properties on :root via wp_add_inline_style. Defaults map byte-identically to the prior hardcoded CSS values; flag-OFF / no-settings-saved is back-compat. Additive — no flag.

## [1.11.35] — 2026-05-10

- Wave 3.1b — InfiniteScroll PHP wrapper render path + 4 deferred extension hooks (selector filter, before_render and after_render actions, should_render_wrapper filter) + container_selector setting + JS-side selector override. Flag-gated, default off.

## [1.11.33] — 2026-05-04

- Wave 3.1a - InfiniteScroll trigger_mode / history_mode / hybrid_threshold settings + JS dispatcher (gates at attachObserver entry + post-loadNext threshold check) + applyHistoryMode wrapper around the existing pushState call. No new hooks (those land in 3.1b). Behind freeman_core_infinite_scroll_trigger_modes_enabled (default off). Flag-OFF and flag-ON-with-defaults both byte-identical to pre-3.1a behavior.

## [1.11.32] — 2026-05-04

- Wave 3.3 - CheapestDefaultVariation strategy selector (cheapest / first_in_stock) with per-product _freeman_cheapest_variation_strategy meta override and freeman_core/cheapest_variation/strategy filter. Behind freeman_core_cheapest_variation_strategy_enabled (default off).

## [1.11.31] — 2026-05-04

- ProductSlider native-scroll over-shoot -- clamp touch swipe + trackpad pan at last card via scroll-event snap-back (scoped to ProductSlider via data-cs-clamp-children opt-in; supersedes #18 which only covered JS-driven scroll)

## [1.11.30] — 2026-05-04

- Wave 3.2b -- ProductSlider autoplay / loop / indicator (advanced controls behind freeman_core_sliders_advanced_controls_enabled, default off; reuses 3.2a flag; grid mode unchanged)

## [1.11.29] — 2026-05-03

- Wave 3.2a — CategorySlider autoplay / loop / indicator (Roadmap #6, sub-PR 1 of 2). New Elementor controls behind `freeman_core_sliders_advanced_controls_enabled` (default off). Indicator selector (`progress` / `dots` / `none`) supersedes the legacy `show_progress` switcher with a back-compat shim. Render path gated on the flag — rollback is byte-identical. Wave 3.2b (ProductSlider) queued.

## [1.11.28] — 2026-05-03

- Wave 2.2 / 4e — auto-color render wiring (Color_Sampler::resolve_term_color wraps manual->sampled->fallback chain at archive.php and variation-buy-box.php render callsites; flag-OFF byte-identical; closes Wave 2.2)

## [1.11.27] — 2026-05-03

- Wave 2.2 / 4d — auto-color sampler pipeline (Color_Sampler with modal-with-edge-filter, GD/Imagick auto-upgrade) + Sampler_Scheduler (sample-on-save, pre-warm on flag-flip via batched WP-Cron, first-of-kind cron precedent, queue + filterable batch size). Cache invalidation on _thumbnail_id change, variation deletion, attachment deletion. Bootstrap.php cron stubs promoted (Wave 2.2 ceiling raised 12 to 14)

## [1.11.26] — 2026-05-03

- Bugfix: Settings_Hub storage-shape mismatch in 4a's read-shim — Settings_Reader returned 1/0/string verbatim from new keys, breaking Etucart_VS_Settings::bool yes/no comparisons and excluded_category_ids array shape on flag-ON sites. Adds normalize_new_value_for_legacy_reader path and fixes Settings_Hub render_field checkbox checked() call to FILTER_VALIDATE_BOOLEAN

## [1.11.25] — 2026-05-03

- Wave 2.2 / 4c — Hover tooltip on swatches: pure CSS via data-tooltip attr + hover::after, per-term admin override via freeman_core_variation_swatches_term_tooltip_text term-meta, gated on freeman_core_variation_swatches_tooltip_enabled flag, flag folded into transient cache key

## [1.11.24] — 2026-05-03

- Wave 2.2 / 4b — Image swatches: per-term image upload (Iconic/WPC pattern) gated on freeman_core_variation_swatches_image_swatches_enabled, freeman_core_variation_swatches_term_image_id term-meta key under the canonical namespace (not extending legacy etucart_*), image wins over color precedence, term_image_url filter, smart test stubs promoted to bootstrap.php

## [1.11.23] — 2026-05-03

- Wave 2.2 / 4f — Variation-image-on-card swap on shop / archive listings: per-variation image payload (gated on freeman_core_variation_swatches_card_image_swap_enabled), refreshCardImage() in etucart-shop-swatches.js, two new additive filters (card_image_selector and card_image_payload), flag-state folded into transient signature for implicit cache-bust on flag flip

## [1.11.22] — 2026-05-03

- Drop PHP 7.4 from CI matrix and bump min PHP to 8.0 (freeman-core + freeman-theme headers, composer.json require, .github/workflows/ci.yml). Aligns CI to reality after Wave 2.3a-c baked PHP 8.0+ idioms (str_starts_with, str_contains) into shipped code; PHP 7.4 PHPUnit lane was de-facto failing.

## [1.11.21] — 2026-05-03

- Wave 2.2 / 4a — VariationSwatches settings migration to Settings_Hub: read-shim, 1.11.21 one-shot migration of 14 etucart_vs_* keys, new admin page gated behind freeman_core_variation_swatches_settings_hub_enabled flag (default off, P1 version-skew model)

## [1.11.20] — 2026-05-03

- Fix PHP 7.4/8.0 lint failure in SnapshotTestCaseTest: replace PHP 8.1+ octal literal 0o755 with legacy 0755

## [1.11.19] — 2026-04-30

- Tweak: narrow ProductSlider/CategorySlider edge-fade mask from 24px to 4px on mobile (<=640px) so cards do not lose visible content to the softener

## [1.11.18] — 2026-04-30

- Bugfix: ProductSlider drag overshoot — clamp drag bounds at last card edge instead of scrollWidth (RTL-safe via getBoundingClientRect)

## [1.11.6] — 2026-04-29

- Bug fix: cap shop variation-pill width so long option names cannot stretch the product card column and break the archive grid (RTL: pushed cards off-screen left).

## [1.11.5] — 2026-04-29

- Wave 2.3c: modern Frontend via class_alias swap; Hebrew JS strings + form placeholders moved to locales/; freeman_core/restock_notify/should_inject filter

## [1.11.4] — 2026-04-29

- Wave 2.3b: modern Email + Stock_Monitor classes via class_alias swap; bilingual email shell fix; freeman_core/restock_notify/email_args filter and before_send action

## [1.11.3] — 2026-04-29

- Wave 2.3a: add Subscribers repository - thin static wrapper around RSN_Database with 4 methods, no callers yet, groundwork for 2.3b/c

## [1.11.2] — 2026-04-29

- Wave 1.2: RestockNotify locale bootstrapper - English defaults plus Hebrew opt-in via locales/en_US.php and he_IL.php

## [1.11.1] — 2026-04-29

- Wave 1.1b: add category_slider/query_args, category_slider/render_card, product_slider/query_args, product_feed/query_args, product_feed/item, product_feed/before_serve, product_feed/after_generate

## [1.11.0] — 2026-04-29

- Wave 1.1a: add cheapest_variation/should_apply, cheapest_variation/chosen, variable_stock_fix/should_check filters

## [1.10.17] — 2026-04-29

- Add snapshot harness (Wave 0.5): SnapshotTestCase trait, Scrubber utility, and three example tests (HTML, XML, JSON) with committed goldens

## [1.10.16] — 2026-04-29

- Wave 0.4: regression baselines (hooks, REST, CLI, freeman_/etucart_ identifiers) + tools/capture-baselines.sh + BaselinesIntegrityTest

## [1.10.15] — 2026-04-29

- Wave 0.3 - Settings export/import tool with auto-backup, halt-on-error import, and last-5 rolling backups

## [1.10.14] — 2026-04-28

- Wave 0.2 - Feature_Flags helper with explicit boolean parsing and dynamic filter hook

## [1.10.13] — 2026-04-28

- Wave 0.1: add freeman_core/logger/entry filter and freeman_core/logger/written action inside Logger::log() (D8)

## [1.10.12] — 2026-04-27

- Variation +N badge: add .etucart-shop-pick__more[hidden] display:none !important rule to fix the root cause that made the badge stay visible whenever JS tried to hide it. The class rule .etucart-shop-pick__more had display:inline-flex !important which silently overrode the browser user-agent [hidden] display:none, so moreBtn.hidden=true from refreshOverflow was a no-op. Result: badge visible with zero chips marked .is-overflow so clicking did nothing. Mirrors the existing .etucart-shop-pick__opt[hidden] rule that already worked for chips.

## [1.10.11] — 2026-04-27

- Variation +N badge: hide out-of-stock and unavailable chips entirely on shop archive instead of greying them out, so the visible row matches the JS in-stock filter (the badge now counts only what the customer can actually pick, and is hidden completely when no in-stock chip is hidden). PHP-level OPT_SHOP_HIDE_OOS handles attribute-value-level pruning (default ON); this CSS handles the cross-attribute case where a chip becomes OOS only after the customer picks another swatch and refreshAvailability marks it.

## [1.10.10] — 2026-04-27

- MyAccount: align Addresses and View Order address columns under one shared grid rule, unify card chrome across both pages so widths and gutters match identically. Tokenize raw font-weight, line-height, letter-spacing, and font-size values: every weight goes through fma-weight-medium/semi, every line-height through fma-leading-snug/base/tight, every letter-spacing through fma-tracking-tight/wide, hero size through fma-text-xxl. Mobile breakpoint already covered both selectors so no behavior change there, just visual lockstep.

## [1.10.9] — 2026-04-27

- Variation +N badge: filter to in-stock chips only when measuring overflow, marking chips as overflow, and computing the count. Out-of-stock and unavailable chips (set by refreshAvailability via .is-out-of-stock and .is-unavailable classes) are excluded so the badge never points to a sold-out variant. If zero in-stock chips are hidden, the badge stays hidden completely. Belt-and-braces fallback: hiddenCount === 0 forces the badge hidden even under pathological measurement.

## [1.10.8] — 2026-04-27

- MyAccount mobile nav: add min-width:0 to grid items + ul to fix horizontal-scroll trap (the pill row's flex-nowrap min-content was wider than the phone, growing the grid track and making the whole page scroll sideways instead of just the nav). Theme: add overflow-x:clip on html/body as a defensive guard against any future rogue element creating page-level horizontal scroll.

## [1.10.7] — 2026-04-27

- Slider: drop scroll-snap-align from middle cards (only first/last snap) and add overscroll-behavior-x contain to fully eliminate overscroll past the last product. Variation +N: add explicit display rule for revealed overflow chips with high specificity so wrap-below works even when an unrelated rule with lower specificity declared display: inline-flex.

## [1.10.6] — 2026-04-27

- Fix shop-swatches +N badge always-visible bug, expand overflow chips below row instead of inline, match +N pill height to size pills (32px), clamp slider drag to scroll bounds and switch snap to proximity to stop overscroll past last product, anchor WooSQ quick-view button to image bottom on mobile, suppress duplicate prices in WooSQ quick-view modal, polish My Account RTL with logical properties and tighter sidebar spacing

## [1.10.5] — 2026-04-27

- Improve My Account spacing, theme typography, and mobile layout

## [1.10.4] — 2026-04-27

- Fix My Account billing and shipping address card alignment

## [1.10.3] — 2026-04-27

- Fix product slider end behavior, swatch overflow cap, and card height alignment

## [1.10.2] — 2026-04-27

- My Account: hide WC clearfix pseudos that were turning grid items into 4 rows so nav and content stack; override WC's float+30%/68% widths on the nav and content; force border-box. Product Slider: bump card-sizing specificity above WC columns-N width rules so flex-basis wins (was scrolling past the last product because cards picked up WC percentage widths); restore vertical track padding so the hover ring isn't clipped by overflow-y; add overscroll-behavior-inline contain.

## [1.10.1] — 2026-04-27

- Fixed Product Slider showing all products at once - WooCommerce default ul.products grid layout was beating the slider track flex layout on specificity. Fixed My Account page RTL layout - sidebar border, content padding, active accent bar, form row gutter, and notice accents now flip correctly when site is RTL.

## [1.10.0] — 2026-04-27

- Added My Account module - editorial restyle of the classic shortcode WooCommerce my-account page (sidebar nav, serif titles, mono eyebrows, hairline tables, pill status chips). Pure CSS layer, no markup or endpoint changes.

## [1.10.0] — 2026-04-27

- Added My Account module - editorial restyle of the classic shortcode WooCommerce my-account page (sidebar nav, serif titles, mono eyebrows, hairline tables, pill status chips). Pure CSS layer, no markup or endpoint changes.

## [1.9.9] — 2026-04-27

- ProductSlider: scope every product-card style under .cs.cs-products so each rule lands at 4+ classes specificity and consistently beats WooCommerce default stylesheet (woocommerce.css) which was forcing float-based 22.05% widths, height:auto images, centered text, and a giant green sale-flash circle into the slider since 1.9.8 added the .woocommerce wrapper class for plugin-CSS compatibility

## [1.9.8] — 2026-04-27

- ProductSlider: add `woocommerce` class on the slider's outer wrapper so plugin CSS scoped to `.woocommerce ul.products li.product` (e.g. WPC Quick View's `.quickvieww` overlay) matches inside the slider — Elementor's default WC Products widget wraps in `.woocommerce` for the same reason

## [1.9.7] — 2026-04-27

- ProductSlider: gate hover scale + ring on @media (hover: hover) so mobile taps no longer trigger a hover-scale jump that looked like an image swap; add max-width/min-width container clamps on the VariationSwatches archive picker inside slider cards and dispatch a window resize after slider layout commits so the picker's +N overflow scanner re-measures against the final card width

## [1.9.6] — 2026-04-27

- ProductSlider: refactor to emit standard WooCommerce shop-loop markup via wc_get_template_part('content','product') so wishlist / quick-view / sale-flash / image-swap plugins light up the slider with no extra wiring; new Manual / Current-query / Related sources + Hide-free + Hide-out-of-stock toggles for parity with Elementor Pro WC Products

## [1.9.5] — 2026-04-27

- i18n: regenerated freeman-core.pot from current source (395 strings); merged he_IL.po preserves existing translations; new en_US.po ships English translations for all 115 Hebrew strings wrapped in 1.9.4 — non-Hebrew sites now display English by default

## [1.9.4] — 2026-04-27

- Restock Notify i18n: wrapped all remaining hardcoded Hebrew strings in __() / esc_html_e() across module defaults, admin pages, email templates, frontend form template, and frontend JS (via wp_localize_script i18n payload) (H-03)

## [1.9.3] — 2026-04-27

- ProductFeed: cache uncompressed XML byte count in a sidecar size file at generation time and emit Content-Length on the decompressed-streaming branch (M-02)

## [1.9.2] — 2026-04-27

- renamed slider/infinite-scroll asset handles to canonical freeman-core-* prefix per audit (N-07); deprecated names registered as no-source aliases that resolve via dependency on the canonical handle, removed in 2.0.0

## [1.9.1] — 2026-04-27

- security: Restock Notify unsubscribe tokens now use random_bytes (H-01); honeypot on Variation Swatches shop AJAX (L-01); slider drag-scroll releases pointer capture on window blur (L-02); doc-only clarification on Cheapest Default Variation sale-price handling (L-03)

## [1.9.0] — 2026-04-27

- renamed ProductFeed/VariableStockFix hooks and VariationSwatches filter to canonical freeman_core_* prefix per audit (N-02/N-03/N-04); added one-release deprecation shims, version-gated migrations (option copy, cron reschedule, rewrite flush); fixed plugin description to list the actual 8 modules (N-05); release.sh stamping Plugin::VERSION resolves N-01

## [1.7.15] — 2026-04-27

- CategorySlider: drag now works on the image area of a card too — was being swallowed by the browser's native HTML5 drag on `<a>` elements ("drag this link to bookmarks" gesture). Delegated `dragstart` preventDefault + `user-drag: none` CSS suppresses it.

## [1.7.14] — 2026-04-27

- CategorySlider: mouse drag on cards is now ON by default with a Pointer Events implementation that uses a three-gate detector (distance + time + horizontal-dominance) so clicks and drags never fight. Touch keeps native vertical page-scroll thanks to `touch-action: pan-y`. Admin toggle remains for opt-out.

## [1.7.13] — 2026-04-27

- VariationSwatches: archive picker's transient cache key now includes WC's currency position + separators + decimals — changing **WC → Settings → General → Currency Position** previously didn't update archive cards because the cached HTML key only tracked currency code. Cache now invalidates correctly on any format change.

## [1.7.12] — 2026-04-27

- VariationSwatches: removed the 1.7.11 RTL currency override. Currency position now follows WC's own Settings → General → Currency position for every render path; both the "starting from" line and the picked-variation price use the same `wc_price()` rendering, so the position you set in WC is honoured everywhere — archive cards, PDP, header cart, etc.

## [1.7.11] — 2026-04-27 — REVERTED in 1.7.12

- VariationSwatches: site-wide filter forces currency-on-right (`149.90 ₪`) on RTL sites — fixes inconsistency where translation/multi-currency plugins were leaving one state at "₪ 319.90" and another at "549.90 ₪". LTR shops unaffected.

## [1.7.10] — 2026-04-27

- VariationSwatches: archive price 13px → 18px; currency-symbol glyph rendering identically between selected/unselected states (was switching font-family); every previously-hardcoded `dir="rtl"` in legacy templates now follows `is_rtl()` so the price and pickers render correctly on both RTL and LTR sites.

## [1.7.9] — 2026-04-27

- VariationSwatches: PDP price size locked to ~24px (was rendering huge); archive size mismatch between selected/unselected states fixed via universal descendant selector. New "Hide selected-option text" setting (default ON) collapses the "Choose an option" row above the swatches.

## [1.7.8] — 2026-04-27

- VariationSwatches: PDP price stays right-aligned + bold; archive price centred; both lock value typography so picked/unpicked states render at identical size. New "Hide attribute labels" setting (default ON) suppresses the "Size:" / "Colour:" row on shop / archive cards.

## [1.7.7] — 2026-04-27

- VariationSwatches PDP price line centres correctly + renders at the same size whether a variation is picked or not (theme rules targeting `.price` sub-spans were sizing the picked vs unpicked states differently; locked all WC inner spans to inherit). Currency stays dynamic via `wc_price()`.

## [1.7.6] — 2026-04-27

- VariationSwatches: archive picker auto-selects when there's only one purchasable variation (no real choice → no friction). Single picker-driven price line replaces WC's "₪20 – ₪100" range on both archive and PDP — defaults to "החל מ: ₪{min}", swaps to the picked variation's exact price on selection, restores on reset.

## [1.7.5] — 2026-04-27

- CategorySlider: progress bar is now a real horizontal scrubber — grows on hover, mousedown jumps + drags like a native scrollbar. The desktop middle-ground between draggable cards and click-only cards.

## [1.7.4] — 2026-04-27 + Theme [1.0.3]

- **Freeman Theme** (1.0.3): hide the WC-injected `a.added_to_cart` "View cart" link on shop / archive cards.

## [1.7.4] — 2026-04-27

- **Freeman Core**: CategorySlider mouse drag is now opt-in via a new switcher (default OFF) — desktop clicks no longer fight with drag at all. VariationSwatches gains a "No pre-selected variation on archive" setting (default ON) that bypasses both manually-set defaults AND the auto-cheapest pick on shop / archive / loop contexts.
- **Freeman Theme** (1.0.2): Removed the global `a:hover { opacity: 0.75 }` rule entirely — the 1.0.1 `:has()`-based scoping wasn't enough; killing the rule fixes the grey hover wash on every image-wrapping anchor.

## [1.7.3] — 2026-04-26

- **Freeman Core**: CategorySlider drag threshold raised 8 → 16px so mouse drag no longer fights with click intent; new Hover ring color control. CheapestDefaultVariation gains an "Apply on product pages only" setting (default ON) — auto-selection is suppressed on shop / archive / loop contexts so swatches there render with nothing pre-selected.
- **Freeman Theme** (1.0.1): Fix grey hover wash on image-containing anchors — global `a:hover { opacity: 0.75 }` rule scoped to text links only via `:has()`.

## [1.7.2] — 2026-04-26

- CategorySlider QA fixes: progress bar reaches end-of-scroll (pixel-based translate); cards reliably clickable (drag threshold + scroll only after confirmed drag); URL fallback hardened against empty `get_term_link()` returns; editor-mode access guarded.

## [1.7.1] — 2026-04-26

- CategorySlider polish: fonts inherit from theme/Elementor + Typography controls for eyebrow/headline/name; RTL drag direction fixed; hover ring no longer clipped at top; image corners now show fully (wrapper background removed); arrows hardened against Elementor button-style cascade.

## [1.7.0] — 2026-04-26

- New Freeman Core module **CategorySlider**: Elementor widget rendering WooCommerce product categories as an editorial drag-scroll slider with momentum, optional CSS scroll-snap, hover ring, progress bar, and per-breakpoint cards-per-view. Controls use Elementor SLIDER + CHOOSE for parity with the Claude Design Tweaks panel. Term-query controls (include/exclude/child-of). Full RTL — Direction control flips arrows, drag, and progress bar; auto follows `is_rtl()`.

## [1.6.0] — 2026-04-23

- Swatches picker now renders in Related/Upsells/Cross-sells on product pages and any non-archive product loop (home-page product widgets, shortcode grids). New OPT_APPLY_RELATED toggle (default on) lets shop owners disable just the PDP loops.

## [1.5.1] — 2026-04-22

- OOS/priceless simple products now render a single disabled ATC labeled אזל מהמלאי with no Buy Now; variable products hide Buy Now and swap the ATC label when an OOS variation is picked

## [1.5.0] — 2026-04-22

- Test harness + CI: added PHPUnit ^9.6||^10.5 to composer dev-requires; phpunit.xml.dist + tests/bootstrap.php with stubbed WP environment; 44 tests across Detection_Result, Base_Importer contract, Module_Registry, Security helpers, ProductFeed split regression; tests/README.md usage guide; GitHub Actions workflow at .github/workflows/ci.yml runs php-lint + smoke + activation-sim + phpunit across PHP 7.4-8.3 plus wpcs + build-zip jobs

## [1.4.0] — 2026-04-22

- Structure: ProductFeed Module.php (663 lines) split into Generator + Server + Module (324 lines). Generator owns XML writing + file locking + paths + OPT_LAST_GEN. Server owns rewrite + query var + serve_feed. Module remains the lifecycle coordinator (boot, cron, settings, admin panel). Module::generate_feed/feed_file/feed_url/feed_dir/lock_file retained as BC proxies so third-party callers keep working. BATCH/REWRITE_SLUG/QUERY_VAR/OPT_LAST_GEN kept as class constants on Module for the same reason.

## [1.3.0] — 2026-04-22

- Deferred items: RestockNotify assets now load only on product/shop/cart/checkout/shortcode pages (filter: rsn_should_enqueue); Swatches legacy strings migrated from etucart-vs to freeman-core text-domain, .pot + he_IL.po updated; typed Detection_Result value object returned from every Base_Importer::detect() — Legacy_Importer::scan() now coerces + logs on shape mismatch instead of silently going blank

## [1.2.1] — 2026-04-22

- Fix: simple products stuck as OOS on PDP (wc_variation_form + reset_data triggered on simple forms); fall back to WC native template when !is_purchasable() so misconfigured products surface WC warnings; +/- quantity stepper on shop-archive simple picker (matches PDP)

## [1.2.0] — 2026-04-22

- Structure + UX: Base_Importer abstract extracted (6 importers collapsed from ~330 to ~150 lines); admin notice on module boot failure; Dashboard cross-links to legacy settings for Swatches/Restock; VariationSwatches on_deactivate clears stale picker transients; InfiniteScroll aria-live status announcements; :focus-visible outlines on Swatches buy-box + shop picker; per-module README.md documentation; smoke.php now tests Importer layer

## [1.1.0] — 2026-04-22

- Performance: batch-prime variation post caches in ProductFeed write_feed; paginate VariableStockFix daily audit with self-chaining cron; transient-cache Swatches shop-archive variation data and RestockNotify OOS lookups; debounce VariableStockFix variation-save hooks; esbuild minification pipeline in build.sh; SCRIPT_DEBUG-aware asset_min_url helper; MutationObserver replaces retry timeouts in InfiniteScroll

## [1.0.4] — 2026-04-22

- Security: harden email From header, class_exists guards for legacy plugin conflicts, honeypot + i18n on subscribe form, unified rate_limit, transient-backed Tools banners, swatches add-to-cart gate filter

## [1.0.3] — 2026-04-22

### Added
- **Swatches** — Simple products now use the Freeman buy box on both the PDP and the shop grid.
  - Single product page: styled Add to Cart + Buy Now + quantity stepper + sticky mobile bar.
  - Shop / archive: compact Add-to-cart card with a quantity stepper (AJAX, shared toast stack).
  - OOS / non-purchasable simple products render the Freeman button disabled with "אזל מהמלאי".
  - Grouped and external products keep WooCommerce's default templates.

## [1.0.2] — 2026-04-22

- Removed the ElevatedCards (Cards) module and all its code, assets, templates, translations, Importer and legacy wcec_* option handling.

## [1.0.1] — 2026-04-22

### Fixed
- **Freeman Core** — Parse error in `src/Core/Module_Registry.php` docblock (unescaped `*/` in the path `src/Modules/*/Module.php` closed the comment early and caused a fatal at plugin activation). Path is now written with backticks in the docblock.
- `tools/release.sh` now also bumps the `FREEMAN_CORE_VERSION` constant in the main plugin file and prepends a changelog entry automatically.
- `tools/activation-sim.php` added: offline WP-stubbed activation harness so parse errors and activation fatals are caught before `dist/` is shipped.

## [1.0.0] — 2026-04-22

### Added
- Initial release.
- **Freeman Theme** 1.0.0 — child theme of Hello Elementor 3.4.x.
- **Freeman Core** 1.0.0 — unified plugin hosting all seven modules:
  - `Cards` (ElevatedCards) — product card replacement, Quick View, Quick Add, Elementor widget.
  - `Swatches` (VariationSwatches) — variable-product buy-box + archive swatches.
  - `Restock` (RestockNotify) — back-in-stock subscription system with custom DB table.
  - `StockFix` (VariableStockFix) — parent stock reconciliation tool + daily cron.
  - `Feed` (ProductFeed) — gzipped XML product feed with cron + instant rebuild.
  - `Scroll` (InfiniteScroll) — shop grid infinite scroll.
  - `Cheapest` (CheapestDefaultVariation) — auto-select cheapest in-stock variation.
- `Legacy_Importer` migration wizard: detects each of the 7 legacy plugins, copies settings, adopts the restock-notify DB table, offers one-click "deactivate & delete legacy" action.
- Unified `Freeman` admin menu with module toggles, onboarding wizard, health checks.
- Hebrew (`he_IL`) translation.
- WooCommerce HPOS + Cart/Checkout Blocks compatibility declared centrally.
