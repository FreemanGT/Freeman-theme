# Variation Swatches — Public API

The module bundles the original `etucart-variation-swatches` codebase, so the
public filters it always exposed still work under their original names. Freeman
Core adds a namespaced layer on top.

## Filters (legacy names, still supported)

- `etucart_vs_color_swatch_markup` — filter per-swatch HTML output.
- `etucart_vs_sizes_markup`        — filter the size-pill row HTML.
- `etucart_vs_buy_box_enabled`     — return false to hide the buy-box on a product.
- `etucart_vs_shop_picker_enabled` — return false to hide the shop-grid picker.

## Filters (Freeman Core namespaced)

### `freeman_core/swatches/color_map`
```php
apply_filters( 'freeman_core/swatches/color_map', array $map );
```
`$map` is `'term-slug' => '#hex'`. Overrides for the color-attribute dictionary.

### `freeman_core/swatches/attribute_is_color`
```php
apply_filters( 'freeman_core/swatches/attribute_is_color', bool $is_color, string $taxonomy );
```
Tell the module which extra product attributes should be rendered as color
swatches (defaults: `pa_color`, `pa_colour`, `pa_צבע`).

### `freeman_core/swatches/attribute_is_size`
```php
apply_filters( 'freeman_core/swatches/attribute_is_size', bool $is_size, string $taxonomy );
```
Same idea for size pills.

## Actions

### `freeman_core/swatches/buy_box_before` / `_after`
```php
do_action( 'freeman_core/swatches/buy_box_before', \WC_Product $product );
do_action( 'freeman_core/swatches/buy_box_after',  \WC_Product $product );
```
Inject badges, urgency messaging, bundled-product CTAs, etc.

## Template overrides
Themes (including Freeman Theme itself) can override:
```
yourtheme/freeman-core/variation-swatches/shop-variation-pick.php
yourtheme/freeman-core/variation-swatches/variation-buy-box.php
```
Freeman's template loader checks the child theme first, then the parent theme,
then falls back to the module's `legacy/templates/` copy.

## Settings location
WooCommerce → Settings → Products → Swatches (kept under its original key
`etucart_vs_settings` for continuity).
