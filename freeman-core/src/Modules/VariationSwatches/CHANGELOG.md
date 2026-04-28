# Variation Swatches — Changelog

## 1.5.3
- Archive picker's per-product transient key now includes `woocommerce_currency_pos`, `woocommerce_price_decimal_sep`, `woocommerce_price_thousand_sep`, and `woocommerce_price_num_decimals`. Previously the cache key only tracked the currency *code* (USD/ILS/EUR), so changing the position from "Left" to "Right" (or adjusting separators) would leave the rendered `from_price` + per-variation `price_html` strings stale in transients for up to 6 hours — the symptom that made archive cards "not respect WC settings" after a setting change. Old transients now have a different hash and are simply unreachable; new ones are written with the full signature on next view.

## 1.5.2
- Removed the 1.5.1 `woocommerce_price_format` RTL override. Currency position now follows WC's own Settings → General → Currency position — left / right / left-space / right-space all work as configured. Both `wc_price()` (unselected) and `variation.price_html` (selected) respect the same WC setting, so the two states render identically without any plugin-side intervention.

## 1.5.1
- New site-wide `woocommerce_price_format` filter (priority 999) forces `%2$s&nbsp;%1$s` (number, then currency, with space) on RTL sites. Hebrew/Arabic conventions universally place ₪ after the number — installs that ended up with "₪ 319.90" on one render path and "549.90 ₪" on another (translation / multi-currency plugins rewriting one but not the other) now render consistently. LTR shops unaffected; their `woocommerce_currency_pos` setting is honoured as before.

## 1.5.0
- Archive price: bumped base from 13px → 18px (was too small for the editorial card design); `min-height` raised 16 → 22px to match.
- Currency symbol consistency: locked `font-family: inherit !important` on every descendant of the price-value containers so the ₪ (or any currency) glyph renders identically between server-rendered `wc_price()` (unselected) and JS-injected `variation.price_html` (selected). A downstream rule was forcing a different font on `.woocommerce-Price-currencySymbol` for one of the two markup paths.
- Direction: every legacy template that hardcoded `dir="rtl"` (port from etucart-vs Hebrew-first) now emits `dir="rtl"` or `dir="ltr"` based on `is_rtl()`. Covers PDP price line, variation buy-box, simple buy-box, archive variation picker, archive simple picker. Price line on PDP was inheriting LTR from its Elementor wrapper because it sits outside the form.

## 1.4.3
- PDP price size: anchored `.etucart-pdp-price` to `1.5rem` so the value locks at ~24px instead of compounding `1.4em` with theme's `.price` size (was rendering enormous).
- Archive price size mismatch between selected/unselected fixed with universal descendant selector — JS-injected `variation.price_html` wraps the amount in an element our class-list didn't cover.
- New `OPT_SHOP_HIDE_SELECTED` (default ON): hides `.etucart-shop-pick__attr-selected` ("Choose an option" / picked-value text) on shop / archive cards via `.etucart-shop-pick--no-selected` wrapper class. When combined with `--no-labels` the whole `.etucart-shop-pick__attr-head` collapses.

## 1.4.2
- PDP price: stays right-aligned (was being forced centred); typography lock wrapped in `[data-pdp-price]` selectors with `!important` to beat Hello Elementor's `.product .price .amount` cascade — selected/unselected states now render at identical size.
- Archive picker price: centred + matching typography lock so both states render same size.
- New `OPT_SHOP_HIDE_ATTR_LABELS` (default ON): hides `.etucart-shop-pick__attr-label` ("Size:" / "Colour:") on shop / archive cards via a `.etucart-shop-pick--no-labels` wrapper class. Selected-value text preserved. PDP unaffected.

## 1.4.1
- PDP price line: centred under RTL parents (was inline-flex → right-aligned); locked WC sub-element typography to inherit so the price renders at identical size in both selected and unselected states; restored sale-price strikethrough on `<del>` explicitly.

## 1.4.0
- **Auto-select when only one purchasable variation is available.** Bypasses `OPT_SHOP_NO_PRESELECT` for that case alone — when the customer has no real choice, surfacing an empty picker is just friction. Detection happens after the OOS-prune so it stays consistent with what's visible.
- **Single picker-driven price line** replaces WC's default "₪20 – ₪100" range on both archive cards and the PDP buy-box. Default state: `החל מ: ₪{min}`. On variation pick: swaps to the variation's exact `price_html` and the prefix hides. On `reset_data` / `hide_variation`: prefix returns, value restores to min. Implemented via:
  - `OPT_SHOW_PRICE` default flipped to ON so the archive picker's existing "starting from" line surfaces by default.
  - `woocommerce_template_loop_price` action replaced with a wrapper that skips rendering for variable products where our picker is active (simples and excluded categories still get WC's default price).
  - `woocommerce_template_single_price` action removed for variable PDPs; `variation-buy-box.php` now emits its own `.etucart-pdp-price` line at the top of the form.
  - `found_variation` / `reset_data` handlers in `etucart-swatches.js` updated via `updatePdpPrice($form, variation)`.
  - `.etucart-buy-box .single_variation .price` hidden via CSS to suppress WC's duplicate per-variation price below the swatches; stock + variation description still render there.

## 1.3.0
- New **No pre-selected variation on archive** setting (`OPT_SHOP_NO_PRESELECT`, default ON) under WC → Settings → Products → Shop-page variation picker. When on, every shop / archive / loop picker renders with nothing chosen and the customer must actively click a swatch. This bypasses both the product editor's manually-set defaults AND any auto-pick coming from CheapestDefaultVariation, fixing the case where the source of the pre-selection wasn't the Cheapest module alone. The PDP buy-box is unaffected and continues to honour defaults.

## 1.2.0
- Compact picker now renders in every product loop by default, not just shop/category/tag/search archives:
  - Related Products, Upsells and Cross-sells on single-product pages.
  - Any Elementor/shortcode/block product grid placed on the home page or any non-archive page.
- New setting `etucart_vs_shop_apply_related` (default ON) under WC → Settings → Products → Shop swatches → "Apply on" — toggle it off if you want the default "Choose options" link on PDP loops only.
- `Etucart_VS_Settings::should_apply_on_current_archive()` rewritten: the fallback branch now applies to any frontend context that isn't cart/checkout/account, and single-product pages are governed by the new OPT_APPLY_RELATED toggle instead of being unconditionally excluded.

## 1.1.0
- Simple products now use the Freeman buy box end-to-end.
  - PDP: Add to Cart + Buy Now + quantity stepper + sticky mobile bar (no swatches, since there are no variations). The legacy WooCommerce simple add-to-cart template is swapped via `woocommerce_simple_add_to_cart`. New template: `legacy/templates/simple-buy-box.php`.
  - Shop / archive: compact Add-to-cart card with a small quantity stepper. `Etucart_VS_Archive::maybe_replace_loop_link()` now branches variable vs simple, with `prepare_simple_product_data()` feeding a new `legacy/templates/shop-simple-pick.php`. The existing `wc-ajax=etucart_shop_add_to_cart` endpoint was extended to accept simple-product requests (no `variation_id`), clamping quantity to `get_max_purchase_quantity()` and reusing the same per-product nonce.
  - Out-of-stock / non-purchasable simple products render the Freeman button in its disabled state with "אזל מהמלאי" on both contexts — no fallback to the WC default link.
  - Reuses the existing `.etucart-buy-box` / `.etucart-shop-pick` CSS and the existing toast stack; only a small `.etucart-shop-pick__qty` + `.etucart-shop-pick__row` block was added.
- Grouped and external products intentionally keep WooCommerce's default templates (their UX doesn't map onto the buy box).

## 1.0.0
- Initial port from `etucart-variation-swatches` v1.6.6.
- Legacy class bodies bundled under `legacy/includes/`; templates under `legacy/templates/`; assets at module root.
- Option keys (`etucart_vs_*`) are preserved verbatim so existing sites keep their settings.
- Module boot is idempotent — legacy `ETUCART_VS_BOOTED` guard respected.
