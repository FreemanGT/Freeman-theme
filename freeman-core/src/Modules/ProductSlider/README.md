# Product Slider

Editorial Elementor widget for WooCommerce products — same drag-scroll /
momentum / progress UI as the **CategorySlider**, plus a per-instance
**Display as** toggle that switches between a draggable horizontal track
and a static CSS grid.

## What it does

- Registers an Elementor widget `freeman_product_slider` under the
  **WooCommerce Elements** panel.
- Renders products as a `.cs[data-cs-snap]` row in slider mode, or a
  `.cs-grid` in grid mode. Slider mode reuses the CategorySlider's
  vanilla-JS runtime — no second slider engine, no duplicated logic.
- **Each card is a standard WooCommerce shop-loop entry** rendered via
  `wc_get_template_part( 'content', 'product' )`. The markup is identical
  to a default product grid: `<li class="product type-product post-X
  status-publish ...">` with the full hook stack inside (sale flash,
  thumbnail, title, rating, price, add-to-cart). Plugins that target the
  default WC shop archive light up the slider with **no extra wiring** —
  YITH Wishlist, WPC / YITH Quick View, image-swap-on-hover plugins, sale
  flash customisers, etc. all work out of the box.
- Per-breakpoint cards-per-view (desktop / tablet / mobile), gap, and
  image height.
- **Full RTL support** inherited from the shared runtime — Direction
  control (Auto / Force LTR / Force RTL) flips arrows, drag/momentum
  direction, progress-bar fill, and the sale-flash corner.

## Query controls

| Control          | Behavior |
| ---------------- | -------- |
| Max products     | Hard limit on returned products (1–24) |
| Order by / Order | `date`, `price`, `popularity`, `rating`, `menu_order`, `title`, `rand` (asc/desc) |
| Source           | `all`, `featured`, `on_sale`, `category`, `tag`, `manual`, `current_query`, `related` |
| Categories       | Visible when **Source = By category** — multi-select `product_cat` terms |
| Tags             | Visible when **Source = By tag** — multi-select `product_tag` terms |
| Product IDs      | Visible when **Source = Manual selection** — comma-separated IDs, order is preserved |
| Exclude IDs      | Comma-separated product IDs to omit from any source |
| Hide free        | Filter out products with `_price = 0` |
| Hide out-of-stock | Force `stock_status = instock`. When off, the WC global option still applies |

**Source notes:**
- **`current_query`** — On a product archive (shop, category, tag), reads
  `$wp_query->posts` directly so the slider reflects the page's filters
  (search, attribute filters, ordering). Falls back to `all` when used
  outside an archive.
- **`related`** — On a single product page, calls `wc_get_related_products(
  current_id, limit )`. Falls back to `all` when used elsewhere.
- **`manual`** — Uses `include` + `orderby = post__in` so the user-chosen
  order is preserved.

The default-source path also honours the WooCommerce global "Hide out of
stock items from the catalog" option. Hidden products
(`catalog_visibility = hidden`) are filtered after the query.

## Display modes

| Mode     | Markup                                  | Behaviour |
| -------- | --------------------------------------- | --------- |
| `slider` | `.cs.cs-products` + `<ul class="cs-track products columns-N">` | Draggable, momentum, optional snap (none / per-card / per-page), arrows, progress bar |
| `grid`   | `.cs.cs-products.cs-grid-mode` + `<ul class="cs-grid products columns-N">` | Static CSS grid, no JS, no drag, no arrows. Same per-breakpoint cards-per-view as slider mode |

The slider JS init query is `.cs[data-cs-snap]`; grid-mode containers
omit that attribute, so the same JS file is loaded but skips the grid
container automatically.

## Defaults

| Knob              | Default   |
| ----------------- | --------- |
| `display_mode`    | `slider`  |
| `per_view`        | 4         |
| `per_view_tablet` | 3         |
| `per_view_mobile` | 1.4       |
| `gap`             | 20 px     |
| `card_height`     | 320 px    |
| `shape`           | `soft`    |
| `show_cart`       | on        |
| `show_sale_badge` | on        |
| `hide_free`       | off       |
| `hide_out_of_stock` | off (WC global still applies) |
| `snap`            | `none`    |
| `mouse_drag`      | on        |
| `show_arrows`     | on        |
| `show_progress`   | on        |

## Card structure

The slider track is `<ul class="cs-track products columns-N">`; each item
is a standard WC shop loop `<li>`:

```
<ul class="cs-track products columns-4" data-cs-track>
  <li class="product cs-card type-product post-123 status-publish ...">
    <a class="woocommerce-LoopProduct-link woocommerce-loop-product__link" href="...">
      <span class="onsale">Sale!</span>           ← woocommerce_show_product_loop_sale_flash
      <img class="attachment-woocommerce_thumbnail wp-post-image" ... />
      <h2 class="woocommerce-loop-product__title">Product name</h2>
      <span class="price">…</span>
    </a>
    <a class="button add_to_cart_button ..." href="?add-to-cart=...">…</a>
  </li>
  …
</ul>
```

This is byte-identical to what the WooCommerce default shop archive
emits, so any plugin that decorates `.product` / `.products .product` —
or hooks into `woocommerce_before_shop_loop_item_title`,
`woocommerce_after_shop_loop_item`, etc. — works inside the slider with
no plugin-specific code.

The `cs-card` class is added to each `<li>` via the `post_class` filter
during render, so the shared CategorySlider sizing / scroll-snap /
drag-suppression rules apply unchanged.

## Show sale badge / Show cart button toggles

Both work by temporarily detaching the WC default callback for the
duration of the render:

| Toggle off | Action removed |
| ---------- | -------------- |
| Show sale badge | `woocommerce_show_product_loop_sale_flash` from `woocommerce_before_shop_loop_item_title` |
| Show add-to-cart button | `woocommerce_template_loop_add_to_cart` from `woocommerce_after_shop_loop_item` |

The callback is restored after the loop, so other product loops on the
same page are unaffected. Plugins that hook into the same actions stay
wired regardless of these toggles.

## Settings

No global settings; everything lives on the widget instance.

## Dependencies

- WooCommerce (for the product query, content-product template, image
  sizes, price html, loop add-to-cart template)
- Elementor (widget host)
- The CategorySlider module ships the shared runtime
  (`assets/js/category-slider.js`, `assets/css/category-slider.css`).
  The ProductSlider module registers the same handles defensively, so it
  works whether or not CategorySlider is enabled — but the JS file lives
  in CategorySlider's directory and is the single source of truth.

## Public hooks

See [`HOOKS.md`](HOOKS.md).
