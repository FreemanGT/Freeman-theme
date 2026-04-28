# Variation Swatches

Replaces the default WooCommerce add-to-cart surface with a modern, RTL/Hebrew-first buy box on the product page, and adds a compact inline variation picker on shop/archive pages.

## What it does

- **Product page (PDP):** Custom buy box with color swatches, size pills, quantity stepper, sticky mobile bar. Simple products get the same box (without variation picker).
- **Shop / category / archive:** Compact variation picker replaces the default "Choose options" loop button. Once every attribute is chosen, an AJAX add-to-cart sends the pick straight to the cart without leaving the shop page.
- **"Hide out-of-stock options":** When every matching variation for a given attribute value is out of stock, that value is dropped from the shop picker entirely so customers can't pick it.

## Settings

Settings live in the standard WooCommerce surface — the module preserves legacy option keys (`etucart_vs_*`) for zero-downtime migration from the standalone plugin.

**Path:** WC → Settings → Products → Shop swatches / PDP swatches. Dashboard card includes a "Settings (legacy menu)" shortcut.

Key options:
- `etucart_vs_shop_hide_oos` — hide sold-out attribute values from the shop picker (default ON)
- `etucart_vs_shop_show_price` — render the "החל מ: ₪X" prefix on the shop card (default OFF)
- `etucart_vs_shop_max_visible` — how many swatches to show per attribute before collapsing behind "+N"

## Dependencies

- WooCommerce (required)
- `wc-add-to-cart-variation` script (core) — the PDP swatch JS depends on its `check_variations` / `found_variation` / `reset_data` events for OOS grey-out

## Legacy import

Detects and replaces `etucart-variation-swatches/etucart-variation-swatches.php`. Import is a no-op because option keys are identical. Running "Deactivate & delete legacy plugins" in Freeman → Tools removes the legacy plugin without touching data.

## Public hooks

See [`HOOKS.md`](HOOKS.md). Notable:
- `freeman_core_variation_swatches_shop_add_to_cart_gate` — return `WP_Error` to short-circuit AJAX add-to-cart (membership / B2B plugins). _Renamed in 1.9.0; the previous name `freeman/swatches/shop_add_to_cart_gate` still fires via `apply_filters_deprecated` for one release cycle and is removed in 2.0.0._
- `etucart_vs_buy_now_url` — override the Buy Now destination (FunnelKit / custom checkout funnels). _Legacy carve-out, kept indefinitely._

## Accessibility

- Swatches are real `<button>` elements with `aria-label` + `aria-pressed`.
- Overflow reveal is `aria-expanded`-toggled.
- `:focus-visible` outline on every interactive element (1.2.0).
- RTL-aware CSS.

## Known limitations

- No `<noscript>` fallback — when JS is off, the shop picker doesn't render and WC falls back to its default "Choose options" button.
- Grouped / external products keep the WooCommerce defaults by design.
