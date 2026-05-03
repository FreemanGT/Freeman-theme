# Product Slider — Changelog

## 1.2.0 — Wave 3.2b (autoplay / loop / indicator)
- New Elementor controls (gated on `freeman_core_sliders_advanced_controls_enabled`, default off — the same flag introduced for CategorySlider in Wave 3.2a; reused, not redefined): **Autoplay**, **Autoplay delay** (clamped 1000–15000 ms), **Loop** (autoplay-wrap-to-start; drag-past-end-wraps deliberately out of scope), and **Indicator** (`progress` / `dots` / `none`). The legacy **Show progress bar** switcher stays in place as a back-compat alias — when `Indicator` is unset on a pre-existing widget, render falls through to the legacy value, so flipping the flag on doesn't move any saved widget away from its current behavior.
- All four advanced controls additionally inherit `display_mode = slider` so they're hidden in the editor in grid mode; the render path also gates on `$is_slider`, so grid-mode output emits no `data-cs-*` advanced attrs and no indicator markup regardless of saved settings.
- Pagination dots renderer (`.cs-dots` / `.cs-dot` / `.cs-dot-active`) is mutually exclusive with the progress bar — both occupy the same `.cs-foot` region. Per-page count = `ceil(total / per_view)`.
- Runtime is the shared `category-slider.js` (autoplay / loop / dots engine shipped in Wave 3.2a) — no JS file edits needed for ProductSlider; the new behavior is data-attribute-driven.
- Render path is gated on the flag: with the flag off, no `data-cs-indicator` / `data-cs-autoplay` / `data-cs-loop` attributes are emitted on the root, regardless of saved widget settings — rollback is byte-identical.
- Roadmap line "Add `loading=\"lazy\"` to images beyond first viewport" (Roadmap #6) is **already met by WP core** for ProductSlider — product cards render via `wc_get_template_part( 'content', 'product' )` whose `<img>` markup picks up WordPress's automatic `loading="lazy"` attribute (since WP 5.5). No in-wave work required.

## 1.1.0
- **Slider items are now standard WooCommerce shop-loop entries.** Each card is rendered via `wc_get_template_part( 'content', 'product' )` instead of a bespoke `.cs-card-product` template, so the markup is byte-identical to a default product grid: `<li class="product type-product post-X status-publish ...">` with the canonical `woocommerce_before_shop_loop_item` / `woocommerce_before_shop_loop_item_title` / `woocommerce_shop_loop_item_title` / `woocommerce_after_shop_loop_item_title` / `woocommerce_after_shop_loop_item` action stack inside. Plugins that target `.product` or hook into the standard shop-loop actions — wishlist (YITH, TI), quick-view (WPC, YITH, Elementor Pro), image-swap on hover, sale-flash customisers, ratings overrides, badge plugins — light up the slider with no extra wiring.
- **Track wrapper is now `<ul class="cs-track products columns-N">`** (was `<div class="cs-track">`). Grid mode is `<ul class="cs-grid products columns-N">`. The shared JS continues to bind via `.cs[data-cs-snap]` so no JS change was needed; the `.products .columns-N` classes match WC's archive markup so plugins that scope to those classes also apply.
- **`cs-card` is added to each `<li>` via the `post_class` filter during render.** Lets the shared CategorySlider stylesheet's flex-basis / scroll-snap / drag-suppression rules apply to slider items without duplicating CSS.
- **New query sources matching Elementor Pro's WooCommerce Products widget:**
  - `manual` — explicit comma-separated product IDs, order preserved (`orderby = post__in`).
  - `current_query` — reads `$wp_query->posts` on a product archive (shop / category / tag) so the slider reflects the page's filters. Falls back to `all` outside archive context.
  - `related` — uses `wc_get_related_products()` on a single product page. Falls back to `all` elsewhere.
- **New query toggles:** `Hide free products` (filters by `_price > 0` via `meta_query`) and `Hide out-of-stock` (forces `stock_status = instock`; when off, the WooCommerce global option still applies).
- **Show sale badge / Show cart button toggles now use hook detach/reattach** instead of bespoke markup gating. When a toggle is off, the corresponding default WC callback (`woocommerce_show_product_loop_sale_flash` / `woocommerce_template_loop_add_to_cart`) is removed for the duration of the loop and restored afterwards. Plugins that hook into the same actions stay wired regardless of the toggles.
- **Removed** the `Sale badge label` text control — WooCommerce's translated "Sale!" text now drives every card so the badge matches the rest of the catalog.
- **Removed** the bespoke `render_card`, `render_cart_button`, and `get_product_image_url` helpers. Every per-card decision now flows through the standard WC template + hooks.
- **Typography / colour controls retargeted** to standard WC class names: product-name typography binds to `.cs.cs-products .woocommerce-loop-product__title`, name colour to the same. New `Price color` control binds to `.cs.cs-products .price`. The shared `.cs-eyebrow` / `.cs-headline` selectors are unchanged.

## 1.0.1
- **Touch on phones now scrolls.** Inherited from the shared CategorySlider runtime fix in 1.0.9 — the JS drag is now desktop-mouse only and `touch-action: pan-x pan-y` lets the browser own touch scrolling.
- **Removed the `.cs-price` line from the card meta.** It duplicated the price on variable products where VariationSwatches' picker injects a dynamic "starting from" line in the cart-wrap area. The `Show price` toggle and price typography/color controls have been removed.
- **Equal-height cards.** `.cs-name` now line-clamps to 2 lines and reserves 2 lines' worth of vertical space — single-line and two-line product titles produce cards of identical height, so the cart row aligns across the row.
- **Mobile defaults tuned.** `Cards per view (mobile)` now accepts fractional values (step 0.1) and defaults to 1.4 so the next card peeks ~30% rather than showing two full cards side-by-side. The image area shrinks to 220px on phones to keep the card meta + cart in proportion on a narrow viewport.

## 1.0.0
- Initial release. Registers `freeman_product_slider` Elementor widget under the **WooCommerce Elements** panel.
- Reuses the CategorySlider runtime (CSS + JS) so the drag / momentum / progress / arrows / RTL behaviour is identical between the two widgets.
- Per-instance **Display as** toggle: `slider` (draggable horizontal track with progress bar and arrows) or `grid` (static CSS grid with no drag and no arrows). The grid-mode container omits `data-cs-snap`, so the slider JS skips it automatically — no JS branching needed.
- Card body: image, name, price, optional sale badge, optional add-to-cart button. Cart button uses WC's standard `woocommerce_template_loop_add_to_cart()` so the `woocommerce_loop_add_to_cart_link` filter — including our VariationSwatches picker swap — applies automatically. Variable products link to the product page; simple/external link to the standard `?add-to-cart=` endpoint.
- Card structure uses an absolute-fill `.cs-card-link` overlay so the cart button can sit alongside without nesting `<a>` tags. The cart wrap raises itself above the overlay (`z-index: 2`) so its clicks aren't swallowed.
- Query controls cover all/featured/on-sale/by-category/by-tag with multi-select term pickers, plus orderby (date / price / popularity / rating / menu_order / title / rand), order, limit, and exclude-by-ID. Honours `woocommerce_hide_out_of_stock_items`.
- Layout: per-breakpoint cards-per-view (desktop / tablet / mobile), gap, and image height. Image area is fixed-height; meta + cart stack below at natural height so cards line up across the row regardless of price-html length.
- Style controls: shape (soft / rect), accent color, hover-ring color, and typography groups for eyebrow / headline / name / price.
- Full RTL support inherited from the shared runtime — Direction control (Auto / Force LTR / Force RTL) flips arrows, drag direction, progress bar, and the sale badge corner.
