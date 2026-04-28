# Product Slider — Public API

## Shipped hooks (1.11.1+)

### `freeman_core/product_slider/query_args` (filter, since 1.11.1)
```php
apply_filters(
    'freeman_core/product_slider/query_args',
    array $args,
    array $settings
);
```
Filters the args about to be passed to `wc_get_products()` inside the
widget's `fetch_products()` method. `$settings` is the resolved Elementor
settings array.

**Only fires on the standard query path.** The early-return branches for
`source = 'current_query'` (reads `$wp_query` directly) and
`source = 'related'` (delegates to `wc_get_related_products()`) bypass this
filter — those code paths never build `$args`.

## WooCommerce hook stack

Each slider item is rendered with `wc_get_template_part( 'content',
'product' )`, the same template the WooCommerce shop archive loop uses.
That fires the canonical per-item action stack inside each `<li
class="product type-product post-X status-publish ...">`:

- `woocommerce_before_shop_loop_item`
  - default: `woocommerce_template_loop_product_link_open` (priority 10)
- `woocommerce_before_shop_loop_item_title`
  - default: `woocommerce_show_product_loop_sale_flash` (priority 10)
  - default: `woocommerce_template_loop_product_thumbnail` (priority 10)
- `woocommerce_shop_loop_item_title`
  - default: `woocommerce_template_loop_product_title` (priority 10)
- `woocommerce_after_shop_loop_item_title`
  - default: `woocommerce_template_loop_rating` (priority 5)
  - default: `woocommerce_template_loop_price` (priority 10)
- `woocommerce_after_shop_loop_item`
  - default: `woocommerce_template_loop_product_link_close` (priority 5)
  - default: `woocommerce_template_loop_add_to_cart` (priority 10)

This is also where third-party plugins attach: wishlist buttons (YITH,
TI), quick-view modals (WPC, YITH, Elementor Pro), image-swap on hover,
sale-flash customisers, ratings overrides, badge plugins, and so on. As
long as the plugin lights up the default WooCommerce shop archive on
your site, it lights up the slider too — no extra wiring.

The widget temporarily detaches two callbacks for its own toggles:

| Toggle | Action removed during render |
| ------ | ---------------------------- |
| **Show sale badge** = off | `woocommerce_show_product_loop_sale_flash` from `woocommerce_before_shop_loop_item_title` |
| **Show add-to-cart button** = off | `woocommerce_template_loop_add_to_cart` from `woocommerce_after_shop_loop_item` |

Both callbacks are re-attached after the loop finishes, so other product
loops on the same page are unaffected.

## WooCommerce filters that affect output

Because the slider emits standard shop-loop markup, every WC filter that
modifies the archive applies here too. The most common ones:

- **`post_class` / `woocommerce_post_class`** — class list on the `<li>`.
  The widget itself appends `cs-card` here so the shared `.cs-*` slider
  styles apply.
- **`woocommerce_loop_product_link`** — link target.
- **`woocommerce_product_loop_title_classes`** / **`woocommerce_product_loop_title`** — title markup.
- **`woocommerce_get_price_html`** / **`woocommerce_format_price_range`** /
  **`raw_woocommerce_price`** — price html.
- **`woocommerce_loop_add_to_cart_link`** — full add-to-cart anchor
  replacement (used by the VariationSwatches module to swap the standard
  link for an inline picker on variable products).
- **`woocommerce_loop_add_to_cart_args`** — args array (class list,
  quantity, attributes) for the standard button.
- **`woocommerce_sale_flash`** — sale-flash markup.

## JS

The widget shares the CategorySlider runtime — the public re-init helper is
the same:

```js
window.FreemanCategorySlider.init( document ); // or pass a scoped element
```

`init()` is idempotent; every `.cs[data-cs-snap]` element is bound at most
once via an internal flag. Grid-mode containers (no `data-cs-snap` attribute)
are skipped entirely.

The widget also wires into Elementor's standard re-init hook:
`frontend/element_ready/freeman_product_slider.default`.
