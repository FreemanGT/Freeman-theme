# Cheapest Default Variation

Auto-selects the cheapest in-stock variation as the default on variable products, so customers can add to cart without picking options themselves.

## What it does

- Hooks `woocommerce_product_variation_get_*` / `default_attributes` filters to pre-select the lowest-priced, in-stock variation as the default.
- Runs on every variable product unless "Respect manual defaults" is enabled and the product has manually-configured defaults in its editor.

## Settings

Freeman → Cheapest Default Variation:
- **Respect manual defaults** — when on, a manually-chosen default in the product editor takes precedence. Default ON.
- **Apply on product pages only** — when on, the auto-selection is suppressed on shop / archive / loop contexts so the variation swatches there render with nothing pre-selected; the customer has to actively pick a variation. The single-product page (PDP) still auto-selects the cheapest. Admin and AJAX contexts are unaffected (variation logic still needs the pick there). Default ON.

## Dependencies

- WooCommerce (required)

## Legacy import

Replaces the single-file `auto-default-cheapest-variation` snippet / mu-plugin. The module detects either the plugin folder or the sentinel function `cdw_default_cheapest_variation`. Import is a no-op — there's nothing to migrate.

## Public hooks

See [`HOOKS.md`](HOOKS.md).
