# My Account

Editorial restyle of the classic shortcode-based WooCommerce My Account page.

## What it does
Enqueues a stylesheet on pages where `is_account_page()` returns true. The
stylesheet targets the markup produced by `[woocommerce_my_account]` and the
default WooCommerce templates (orders, downloads, addresses, payment-methods,
edit-address, edit-account, view-order) and reshapes it into a sidebar +
canvas layout with serif headings, mono eyebrows, hairline tables, and pill
status chips.

## What it does *not* do
- No new endpoints, blocks, or shortcodes.
- No PHP template overrides — markup is whatever WooCommerce ships.
- No JavaScript.
- No Block-based My Account template support. If your store uses the new
  block-based My Account experience, this module has no effect.

## Scope
All selectors are scoped under `body.woocommerce-account` so styles never
leak to the rest of the storefront.

## Tokens
The stylesheet consumes the theme's `--fm-*` design tokens (palette, type
scale, radii, motion). Override tokens at the theme level to recolor or
reshape this page along with everything else.
