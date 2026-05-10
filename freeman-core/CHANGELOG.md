# Freeman Core — Changelog

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

- Wave 3.2a — CategorySlider autoplay / loop / indicator (Roadmap #6, sub-PR 1 of 2). New Elementor controls gated on `freeman_core_sliders_advanced_controls_enabled` (default off): Autoplay, Autoplay delay (clamped 1000–15000 ms), Loop (autoplay-wrap only), Indicator (`progress` / `dots` / `none`). Legacy `show_progress` switcher preserved as a back-compat alias — pre-existing widgets fall through to the legacy value when `indicator` is unset. Render path also gated on flag, so rollback is byte-identical. Roadmap's `loading="lazy"` line is a no-op for CategorySlider (CSS background-image, not `<img>`); real bg lazy is a separate future item. Wave 3.2b (ProductSlider) queued.

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

## [1.8.3] — 2026-04-27

- **ProductSlider: image area now uses the standard WooCommerce loop hook.** The bespoke `.cs-img` background-image, the gallery hover-swap layer, and the `.cs-card-actions` overlay introduced in 1.8.2 are all gone. `render_card()` now fires `do_action( 'woocommerce_before_shop_loop_item_title' )` inside `.cs-imgwrap` and lets WooCommerce + the site's existing plugins paint the image, sale flash, image-swap-on-hover, wishlist buttons, and quick-view buttons — exactly the same way they already do on a normal archive card. CSS sizes the rendered `<img>` (object-fit: cover) inside the slider's per-instance image height. Net effect: enabling YITH Wishlist / WPC Quick View / etc. once on the site lights them up on slider cards too, with no extra wiring. The widget's "Show sale badge" toggle now suppresses WC's `woocommerce_show_product_loop_sale_flash` callback for the card's render only, so the toggle still works without competing with the WC sale flash.

## [1.8.2] — 2026-04-27

- **ProductSlider: hover ring wraps the whole card.** The shared `.cs-ring` element used to live inside `.cs-imgwrap`, so hovering a product card outlined only the image. Moved to a direct child of `.cs-card-product` with its own positioning rules; CategorySlider's image-only ring is unchanged. Ring color still drives off the `--cs-ring-color` widget control.
- **VariationSwatches: shop picker swatches always render on a single line.** `.etucart-shop-pick__opts` switched from `flex-wrap: wrap` to `nowrap` + `overflow: hidden`. A new vanilla-JS `refreshOverflow()` measures each row's chip widths against the available container width, marks anything that doesn't fit as `is-overflow`, and updates the existing `+N` reveal button's `data-count` and label. Runs on init, after `refreshAll()`, on the MutationObserver hook for AJAX-inserted pickers, and on a debounced window resize. Expanding the `+N` drawer pauses the scanner so the user's reveal isn't fought on a resize tick.
- **VariationSwatches: quick-view price duplication fixed.** `maybe_suppress_pdp_price()` now hooks `woocommerce_single_product_summary` at priority 9 (right before WC's default `woocommerce_template_single_price` at priority 10) instead of `wp` with an `is_product()` gate. The `is_product()` check returns false inside AJAX-rendered quick-view modals, so WC's range price + the buy-box's "starting from" line both rendered and drifted apart on selection. The new placement fires during every summary render (real PDP, Elementor preview, quick-view modal, `[product_page]` shortcode), so de-duplication applies everywhere the buy-box renders.

## [1.8.1] — 2026-04-27

- **Slider runtime: fix touch on phones.** Both CategorySlider and ProductSlider were unresponsive to touch swipes — `touch-action: pan-y` blocked native horizontal pan while the JS pointer-drag waited for its 10px / 80ms / horizontal-dominance gates, so an early flick produced no scroll. The JS now skips its drag handler for non-mouse pointer types and the track is `touch-action: pan-x pan-y`, so the browser owns horizontal swipe scrolling on touch (with OS-level momentum). Desktop-mouse drag, the progress scrubber, click-suppression, and arrows are unchanged.
- **ProductSlider: drop the `.cs-price` line in the card meta.** It duplicated the price for variable products where VariationSwatches' picker injects a dynamic "starting from" line in the cart-wrap area. The `Show price` toggle and price typography/color controls have been removed accordingly. (Simple products no longer show a price on the card — open an issue if you need it back.)
- **ProductSlider: equal-height cards.** `.cs-name` now line-clamps to 2 lines and reserves 2 lines' worth of vertical space, so single-line and two-line titles produce cards of identical height — the cart row no longer jitters across the row.
- **ProductSlider: mobile defaults.** `Cards per view (mobile)` now accepts fractional values (step 0.1, default 1.4) so the next card peeks ~30% rather than showing two full cards side-by-side. The image area shrinks to 220px on phones (was 320px from the desktop default) so the card meta + cart fit comfortably on a narrow viewport.

## [1.8.0] — 2026-04-27

- **New module: ProductSlider.** Editorial Elementor widget that renders WooCommerce products as either a draggable horizontal slider — same drag/momentum/progress mechanics as the CategorySlider — or a static grid, controlled per-instance by a "Display as" toggle. Cards show image, name, price, sale badge, and an add-to-cart button (using WC's standard `woocommerce_template_loop_add_to_cart()` so VariationSwatches and other loop-link extensions integrate automatically). Query controls cover all/featured/on-sale/by-category/by-tag with multi-select term pickers; layout supports per-breakpoint cards-per-view, gap, and image height; full RTL support inherited from the shared slider runtime.
- CategorySlider runtime: card counter switched from the category-only `[data-cat="1"]` selector to the markup-agnostic `.cs-card`, and the Elementor `frontend/element_ready` action now subscribes both the category and product widget — same JS file drives both. CategorySlider rendering is unchanged. Asset registration in CategorySlider/Module.php is now idempotent (`wp_script_is` / `wp_style_is` guards) so the new ProductSlider module can defensively register the same shared handles regardless of load order or whether CategorySlider itself is enabled.

## [1.7.15] — 2026-04-27

- CategorySlider: drag now works **on the image area of a card**, not just the gaps between cards. The browser's native HTML5 drag (the "drag this link to bookmarks" gesture on `<a>` elements, and the image-drag-preview on `<img>`) was firing first on `mousedown`, swallowing our Pointer Events so the slider stopped following the cursor when the drag started over a card image. Fix: a delegated `dragstart` listener on `.cs-track` calls `preventDefault()` on every native drag attempt within the slider, plus declarative `-webkit-user-drag: none / user-drag: none` on `.cs-card` and `.cs-card img / .cs-card .cs-img` for browsers that respect the CSS hint. Cards stay clickable and the multi-gate drag detector still governs drag-vs-click.

## [1.7.14] — 2026-04-27

- CategorySlider: **mouse drag on cards is now ON by default** with a natural drag-vs-click detector that mirrors how Amazon / Asos / Swiper / Embla rails behave. Replaced the separate mouse + touch listeners with a unified Pointer Events implementation that engages drag only when ALL three gates pass: distance > 10px, elapsed > 80ms since pointerdown, and `|dx| > |dy| × 1.2` (horizontal-dominant). Click navigation always fires for sub-threshold presses; drag never fires for vertical swipes. `setPointerCapture` lets a drag continue even if the cursor leaves the slider's bounds. `touch-action: pan-y` on `.cs-track` means a vertical swipe over a card lets the browser claim the gesture for native page scroll — touch users keep their natural scrolling. The progress-bar scrubber (1.7.5) stays as an additional desktop affordance. Admins can opt out via the existing widget toggle (`Behavior → Enable mouse drag → off`); when disabled, the track falls back to the browser's native overflow-x scroll on touch and the cursor returns to default. Existing widget instances saved with the toggle untouched will pick up the new default automatically. The `data-cs-mouse-drag` attribute name is preserved for back-compat.

## [1.7.13] — 2026-04-27

- VariationSwatches: archive picker's prepared-product transient key now folds in `woocommerce_currency_pos`, decimal separator, thousand separator, and decimal count — was only keyed on currency *code*. Changing **WC → Settings → General → Currency Position** (or any of the other formatting options) now busts the cache on the next page load, so the picker's `from_price` and per-variation `price_html` re-render in the new format. Previously a position change would leave stale HTML sitting in transients for up to 6 hours, which is why archive cards weren't following the WC setting.

## [1.7.12] — 2026-04-27

- VariationSwatches: removed the 1.7.11 `woocommerce_price_format` override. Currency position now follows WooCommerce's own **Settings → General → Currency position** for every render path — left / right / left-with-space / right-with-space all work as configured. WC's `wc_price()` (used for the unselected "starting from" state) and the per-variation `price_html` (used after a variation is picked) both respect that single setting, so the two states render identically. Shops that want "₪149.90" set position to "Left"; shops that want "149.90 ₪" set position to "Right with space"; etc.

## [1.7.11] — 2026-04-27 — REVERTED in 1.7.12

- VariationSwatches: site-wide `woocommerce_price_format` filter forces currency-on-right (`%2$s&nbsp;%1$s`) on RTL sites. Hebrew/Arabic conventions universally place the symbol after the number (149.90 ₪, not ₪149.90), but installs with translation or multi-currency plugins can end up inconsistent — the QA showed "₪ 319.90" on one state and "549.90 ₪" on the next. Filter runs at priority 999 so it wins against most other format filters. LTR shops are unaffected — a USD shop with `woocommerce_currency_pos = 'left'` stays "$149.90".

## [1.7.10] — 2026-04-27

- VariationSwatches: every template that previously hardcoded `dir="rtl"` (legacy from the etucart-vs Hebrew-first port) now renders `dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"` — covers the PDP `.etucart-pdp-price` line, the variation buy-box form, the simple buy-box form, the archive variation picker, and the archive simple picker. The PDP price line was rendering LTR on RTL sites because it sits *outside* the form and inherited from whatever wrapper Elementor put around the buy-box (often LTR). Now it follows site language.


- VariationSwatches: archive price base size bumped 13px → 18px (was too small for the editorial card design); `min-height` on the price line raised 16px → 22px to match.
- VariationSwatches: currency-symbol glyph was rendering differently between selected and unselected states — root cause was a downstream rule forcing a different `font-family` on `.woocommerce-Price-currencySymbol` for one of the two markup paths (server `wc_price()` vs JS-injected `variation.price_html`). Locked `font-family: inherit !important` on every descendant of `.etucart-shop-pick__price-value`, `.etucart-shop-pick__price-prefix`, and `.etucart-pdp-price__value` so both states use the same parent-inherited font, making the ₪ (or any currency symbol) glyph render identically.

## [1.7.9] — 2026-04-27

- VariationSwatches: PDP price was rendering huge — `1.4em` on the value compounded with whatever font-size the theme had set on `.price`. Fixed by anchoring the parent `.etucart-pdp-price` itself to an explicit `1.5rem` (~24px) and locking sub-elements to `1em`. Result is a predictable ~24px price regardless of theme.
- VariationSwatches: archive picker price size mismatch between selected / unselected states fixed via universal descendant selector (`.etucart-shop-pick__price-value *`) — the JS-injected `variation.price_html` was wrapping the price in an element my class-list didn't cover, so it was inheriting a different size from the WC default. Same trick applied to PDP.
- VariationSwatches: new **Hide selected-option text** setting (`OPT_SHOP_HIDE_SELECTED`, default ON) hides the "Choose an option" / "{selected value}" text row above the swatches on shop / archive cards. The swatches' active state already shows what's picked. When this is on AND `Hide attribute labels` is on, the entire head row collapses (no orphan whitespace). PDP buy-box is unaffected.

## [1.7.8] — 2026-04-27

- VariationSwatches: PDP price line stays right-aligned + bold (per QA — centring on the buy-box looked disconnected from the rest of the form which is right-aligned in RTL); typography lock now wraps every WC sub-element in a higher-specificity `[data-pdp-price]` selector with `!important`, fixing the leftover size mismatch where Hello-Elementor's `.product .price .amount` rule was beating our previous `inherit` chain. Sale-price `<del>` strikethrough restored explicitly.
- VariationSwatches: archive picker price line is now centred (was right-aligned) and value typography locked the same way as PDP, so selected vs unselected states render at identical size.
- VariationSwatches: new **Hide attribute labels** setting (`OPT_SHOP_HIDE_ATTR_LABELS`, default ON) under WC → Settings → Products → Shop-page variation picker. Hides the `.etucart-shop-pick__attr-label` row (e.g. "Size:" / "Colour:") on shop / archive cards — swatches are usually self-explanatory and the label adds visual noise. Selected-value text stays. PDP buy-box is unaffected. Implemented via a `.etucart-shop-pick--no-labels` wrapper class so the toggle is purely CSS-driven (no template branching).

## [1.7.7] — 2026-04-27

- VariationSwatches: PDP `.etucart-pdp-price` line now centres correctly under RTL parents (was right-aligned because `display: inline-flex` shrunk to natural inline placement) and renders the price at a consistent size whether a variation is picked or not. The previous CSS let theme `.price > .amount` rules size sale/regular sub-spans differently between WC's `variation.price_html` and our `wc_price($min)` output; v1.7.7 locks every WC price sub-element inside `.etucart-pdp-price__value` to `font-size: inherit; font-weight: inherit; color: inherit` so the typography is identical in both states. Sale-price strikethrough on `<del>` is restored explicitly.

  Currency unchanged — the rendered symbol comes from `wc_price()` which respects WooCommerce's currency, position, and decimal-separator settings (₪ in ILS shops, $ in USD shops, € in EUR shops, etc.). Reported as a concern but no code change needed there.

## [1.7.6] — 2026-04-27

- VariationSwatches: archive picker now **auto-selects when only one purchasable variation is available**, regardless of `OPT_SHOP_NO_PRESELECT`. The customer has no real choice in that case, so showing an empty picker is friction. PDP behavior is unchanged.
- VariationSwatches: **single, picker-driven price line** replaces WC's default "₪20 – ₪100" range on both archive cards and the PDP buy-box. Default state shows `החל מ: ₪{min}`; on variation pick the line swaps to that variation's exact `price_html` and the prefix hides. On reset the prefix returns and the value restores to min. Implemented by:
  - Defaulting `OPT_SHOW_PRICE` to ON (was OFF) so the archive picker's existing "starting from" line surfaces by default.
  - Replacing `woocommerce_template_loop_price` with a wrapper that skips rendering for variable products where the picker is active.
  - Removing `woocommerce_template_single_price` for variable PDPs and emitting `.etucart-pdp-price` at the top of `variation-buy-box.php` instead.
  - JS update on `found_variation` / `reset_data` events on the form.
  - CSS: hides the price portion of WC's `.single_variation` pane to avoid duplicate prices below the swatches when a variation is picked. Variation stock + description still render there.

## [1.7.5] — 2026-04-27

- CategorySlider: progress bar is now a real horizontal **scrubber**. Hovering it grows the track + thumb (1px → 3px and 3px → 5px); mousedown anywhere on it jumps to that position; mousedown + drag moves the thumb continuously like a native scrollbar. RTL is handled — visual ratio is converted to the correct sign for normalized scrollLeft. This is the desktop "middle ground" between draggable cards and click-only cards: cards stay click-only by default, and desktop users get a clear, always-available drag affordance on the progress bar.

## [1.7.4] — 2026-04-27

- CategorySlider: **mouse drag is now opt-in.** Previous threshold-based attempts (8 → 16px) still felt greedy in QA — desktop users kept catching drag instead of click. New `Enable mouse drag` switcher (default OFF) decides whether mousedown/mousemove handlers are even attached. Touch drag stays attached unconditionally because mobile/tablet users have no other way to scroll a horizontal track. The grab cursor now also only shows when mouse drag is on. Threshold widened to 24px for the opt-in case.
- VariationSwatches: new **No pre-selected variation on archive** setting (default ON) under WC → Settings → Products → Shop-page variation picker. When on, every shop / archive picker renders with nothing chosen — the customer must actively pick. This bypasses both manually-set defaults from the product editor AND the auto-cheapest pick from the CheapestDefaultVariation module, fixing the case where 1.7.3's PDP-only Cheapest setting wasn't enough on its own (the source of the pre-selection wasn't always Cheapest). PDP buy-box behavior is unchanged.

## [1.7.3] — 2026-04-26

- CategorySlider: drag threshold raised 8px → 16px so mouse drag no longer fights with click intent — clicks land reliably even with jittery trackpads, while an intentional 16px+ drag still scrolls the track. Added **Hover ring color** Elementor control (bound to a new `--cs-ring-color` CSS var; falls back to `--cs-ink` so existing instances are unchanged).
- CheapestDefaultVariation: new **Apply on product pages only** setting (default ON) — auto-selection is now suppressed on shop / archive / loop contexts so swatches there render with nothing pre-selected and the customer has to actively pick a variation. PDP behavior unchanged. Admin and AJAX/REST contexts still get the cheapest pick (variation logic relies on it).

## [1.7.2] — 2026-04-26

- CategorySlider QA fixes: progress bar now reaches the parent's far edge at end-of-scroll (switched from %-of-bar-width translate to pixel-based translate3d derived from `progress.clientWidth - bar.width`); cards are reliably clickable — drag threshold raised to 8px and the track only updates `scrollLeft` once a real drag is confirmed, so tap-with-jitter doesn't sneakily shift the scroll or block the underlying anchor's navigation; `cs-dragging` class is now only applied after the threshold (was applied on every mousedown, briefly disabling pointer-events on cards mid-tap); URL fallback hardened — `get_term_link()` returning false/empty no longer renders `href=""` (which reloaded the current page), now falls back to a `?product_cat=<slug>` query on the home URL; editor-mode access guarded against `\Elementor\Plugin::$instance->editor` being null on early hooks.

## [1.7.1] — 2026-04-26

- CategorySlider polish: fonts now inherit from theme/Elementor (no more forced Fraunces/Inter); added Typography group controls for eyebrow / headline / card name plus card-name color. Drag direction in RTL fixed (the formula `scrollLeft = startScroll - dx` is direction-agnostic in modern browsers — sign flip removed). Hover ring no longer clipped at top/bottom (track has vertical padding + matching negative margin). Image rounded corners fully visible — removed wrapper background that was poking through. Arrows hardened against Elementor + theme button styles (appearance reset, locked dimensions, !important-free higher-specificity selector).

## [1.7.0] — 2026-04-26

- New module **CategorySlider**: Elementor widget rendering WooCommerce `product_cat` terms as a horizontal drag-scroll slider (free-scroll with momentum, optional per-card / per-page CSS scroll-snap). Editorial design ported from the Claude Design "Category Slider" handoff bundle. Controls use Elementor SLIDER + CHOOSE so layout/shape/snap/show-count/direction feel like the design's Tweaks panel. Term query: include / exclude (SELECT2 multiple), child-of, top-level only, order, orderby, hide-empty, limit. Real `product_cat` thumbnails with deterministic striped placeholders. Full RTL support — Direction control (Auto / Force LTR / Force RTL) flips arrow visuals + button order, drag/momentum direction, and progress-bar fill direction; Auto follows `is_rtl()`.

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
- **Swatches (VariationSwatches)** 1.1.0 — simple-product support.
  - PDP: Freeman buy box (Add to Cart + Buy Now + quantity stepper + sticky mobile bar) now renders on simple products too, via `woocommerce_simple_add_to_cart`.
  - Shop / archive: compact Add-to-cart card with a quantity stepper replaces WC's default loop link for simple products. AJAX endpoint (`wc-ajax=etucart_shop_add_to_cart`) extended to accept requests without `variation_id`, with server-side `get_max_purchase_quantity()` clamping.
  - OOS / non-purchasable simple products render the Freeman button disabled with "אזל מהמלאי" on both surfaces.
  - Grouped / external products intentionally keep the WooCommerce defaults.

## [1.0.2] — 2026-04-22

- Removed the ElevatedCards (Cards) module and all its code, assets, templates, translations, Importer and legacy wcec_* option handling.

## [1.0.1] — 2026-04-22

### Fixed
- Parse error in `src/Core/Module_Registry.php` docblock (unescaped `*/` in a path literal closed the comment early and caused a fatal error at plugin activation). The path is now written with backticks.

## [1.0.0] — 2026-04-22

### Added
- Core infrastructure: Plugin, Module_Registry, Module_Interface, Module_Base, Settings_Hub, Security, Logger, Migrations, Legacy_Importer.
- Unified `Freeman` admin menu with module toggles, per-module settings pages, Tools page.
- PSR-4 autoloader (no Composer required at runtime).
- HPOS + Cart/Checkout Blocks compatibility.
- Seven ported modules (see per-module CHANGELOG.md files for detail).
- Legacy import wizard (detect, import, deactivate, delete).
