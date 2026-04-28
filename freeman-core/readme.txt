=== Freeman Core ===
Contributors: freemandigital
Tags: woocommerce, product-card, variation-swatches, restock-notify, product-feed, infinite-scroll
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.8.3
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unified WooCommerce toolkit for the Freeman Theme. Nine modules in a single plugin with a single admin surface.

== Description ==

Freeman Core replaces six separate WooCommerce plugins with a single modular plugin. Each feature is independently togglable, so you only run what you use. The plugin owns its own data — deactivating the Freeman Theme never orphans a subscriber list or settings.

**Modules**

* **Swatches (VariationSwatches)** — modern variation buy-box + shop swatches.
* **Restock (RestockNotify)** — back-in-stock subscription system (owns a custom DB table).
* **StockFix (VariableStockFix)** — auto-reconciles parent stock when all variations are out.
* **Feed (ProductFeed)** — gzipped XML feed, rebuilt on stock or price changes.
* **Scroll (InfiniteScroll)** — infinite scroll for shop grids.
* **Cheapest (CheapestDefaultVariation)** — pre-selects the cheapest in-stock variation.
* **CategorySlider** — editorial Elementor widget for WooCommerce product categories with drag-scroll, momentum, and full RTL support.
* **ProductSlider** — editorial Elementor widget for WooCommerce products. Same drag/momentum/progress mechanics as CategorySlider, plus a per-instance "Display as" toggle that switches between draggable slider and static grid. Cards include image, name, price, sale badge, and add-to-cart (integrates with VariationSwatches).

All strings are translatable; Hebrew (`he_IL`) is bundled.

== Changelog ==

= 1.8.3 =

* ProductSlider: slider cards now defer their image area to the standard `woocommerce_before_shop_loop_item_title` hook — the same hook that drives normal shop / archive cards. Image-swap-on-hover, wishlist buttons, quick-view buttons, and any other plugin that already works on the site's default product widget now appears on slider cards too, with no extra configuration. The bespoke `.cs-img` background-image, the gallery hover-swap, and the custom `.cs-card-actions` host introduced in 1.8.2 are gone — the slider no longer reimplements features the site already provides.

= 1.8.2 =

* ProductSlider: hover ring now wraps the entire card (image + meta + cart) instead of only the image area.
* VariationSwatches: shop / archive picker swatches always render on a single line — anything that doesn't fit is routed into the existing `+N` reveal button. A vanilla-JS overflow scanner re-measures on init, fragment refresh, and viewport resize so the visible set always matches the card width.
* VariationSwatches: PDP price duplication inside quick-view modals (WPC Quick View, WooSQ, YITH Quick View, etc.) fixed — the price-suppression hook now fires during every `woocommerce_single_product_summary` render rather than once per request behind an `is_product()` gate, so AJAX-injected summaries get de-duplicated too.

= 1.8.1 =

* Slider runtime: fixed unresponsive touch swipes on phones for both CategorySlider and ProductSlider — the browser now owns native horizontal scrolling on touch (with OS momentum); JS pointer-drag is desktop-mouse only.
* ProductSlider: removed the duplicate `.cs-price` line in the card meta (was overlapping with VariationSwatches' picker price for variable products); equal-height cards via line-clamp on the title; mobile default tuned to 1.4 cards-per-view (peek of next card) with a shorter image area.

= 1.8.0 =

* New module: ProductSlider. Editorial Elementor widget for WooCommerce products with the same drag/momentum/progress mechanics as CategorySlider, plus a per-instance "Display as" toggle (slider or static grid). Cards include image, name, price, sale badge, and add-to-cart.
* CategorySlider runtime: the card counter and Elementor re-init hook were generalised so the same JS drives both widgets. CategorySlider behaviour unchanged.

= 1.7.15 =

* CategorySlider: drag now works when starting on a card's image too — was being swallowed by the browser's native HTML5 drag.

= 1.7.14 =

* CategorySlider: mouse drag on cards is now ON by default with a Pointer Events implementation that uses a three-gate detector (distance + time + horizontal-dominance) so clicks and drags never fight. Touch keeps native vertical page-scroll thanks to `touch-action: pan-y`.

= 1.7.13 =

* VariationSwatches archive cache now invalidates when WC currency position / separators change (was sticking to the old format until the 6h transient expired).

= 1.7.12 =

* VariationSwatches: reverted the 1.7.11 RTL currency override — currency position now follows WC's own Settings → General → Currency position verbatim.

= 1.7.11 =

* VariationSwatches: site-wide filter forces currency-on-right on RTL sites — fixes inconsistent symbol placement between selected/unselected states.

= 1.7.10 =

* VariationSwatches archive 18px; currency glyph identical between picked/unpicked; all legacy templates now follow `is_rtl()` instead of hardcoding RTL.

= 1.7.9 =

* VariationSwatches PDP price size locked (was huge); archive size mismatch fixed; new "Hide selected-option text" setting (default ON).

= 1.7.8 =

* VariationSwatches PDP price stays right-aligned + bold; archive price centred; both lock typography so picked/unpicked states match. New "Hide attribute labels" setting (default ON) for shop / archive cards.

= 1.7.7 =

* VariationSwatches PDP price line centres correctly + renders at consistent size between picked / unpicked states.

= 1.7.6 =

* VariationSwatches: archive picker auto-selects when only one purchasable variation is available; single "starting from" price line replaces WC's default range on both archive and PDP and swaps to the picked variation's exact price on selection.

= 1.7.5 =

* CategorySlider: progress bar is now a real horizontal scrubber — grows on hover, mousedown jumps + drags like a native scrollbar.

= 1.7.4 =

* CategorySlider: mouse drag is now opt-in (default OFF) so desktop clicks don't fight with drag.
* VariationSwatches: new "No pre-selected variation on archive" setting (default ON) bypasses both manually-set defaults and the auto-cheapest pick on shop/archive/loop pages.

= 1.7.3 =

* CategorySlider: drag threshold raised so mouse drag no longer fights with click intent; added Hover ring color control.
* CheapestDefaultVariation: new "Apply on product pages only" setting (default ON) — auto-selection suppressed on shop/archive/loop contexts.

= 1.7.2 =

* CategorySlider QA fixes: progress bar reaches end-of-scroll; cards reliably clickable (drag threshold + scroll only after confirmed drag); URL fallback hardened against empty `get_term_link()` returns; editor-mode access guarded.

= 1.7.1 =

* CategorySlider polish: fonts inherit from theme/Elementor + Typography controls for eyebrow/headline/name; RTL drag direction fixed; hover ring no longer clipped at top; image corners now show fully; arrows hardened against Elementor + theme button-style cascade.

= 1.7.0 =

* New module **CategorySlider**: Elementor widget for WooCommerce product categories — drag-scroll horizontal slider with momentum, optional CSS scroll-snap, hover ring, progress bar. Term-query controls (include/exclude/child-of). Full RTL support (Auto / Force LTR / Force RTL).

= 1.0.3 =
Swatches: simple products now use the Freeman buy box on both the PDP (Add to Cart + Buy Now + quantity + sticky mobile bar) and the shop grid (compact AJAX add-to-cart with quantity stepper). Out-of-stock simple products show a disabled Freeman button.

= 1.0.2 =
Removed the ElevatedCards (Cards) module and all its code.

= 1.0.1 =
Fixed a parse error in Module_Registry that prevented plugin activation.

= 1.0.0 =
Initial release.
