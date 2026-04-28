# Cheapest Default Variation — Public API

Everything in this document is considered a stable extension surface. Internal
classes and private methods are off-limits; if you need something they do,
open an issue and we'll promote it.

## Shipped hooks (1.11.0+)

### `freeman_core/cheapest_variation/should_apply` (filter, since 1.11.0)
```php
apply_filters(
    'freeman_core/cheapest_variation/should_apply',
    bool $apply,
    \WC_Product $product,
    array $default_attributes
);
```
Return `false` to skip the cheapest-variation picker for this product. Existing
defaults pass through untouched. Useful for per-product opt-outs (product meta,
custom field, taxonomy gate) without disabling the module globally.

### `freeman_core/cheapest_variation/chosen` (filter, since 1.11.0)
```php
apply_filters(
    'freeman_core/cheapest_variation/chosen',
    array|null $picked,
    \WC_Product $product,
    array $variations
);
```
Filters the variation array selected by the cheapest-price scan. `$picked` is
shaped like an entry returned by `WC_Product::get_available_variations()` (must
have an `attributes` key) or `null` when no eligible variation was found.
Returning `null` or an array without `attributes` leaves `$default_attributes`
unchanged. Useful for "first in stock" / "featured" / custom strategies.

## Planned hooks (NOT YET SHIPPED)

The hooks below are documented for planning but are not yet wired into the
code. Treat as "do not rely on" until `@since` tags appear in the source.

### `freeman_core/cheapest/respect_manual_defaults`
```php
apply_filters( 'freeman_core/cheapest/respect_manual_defaults', bool $enabled, \WC_Product $product );
```
Return `true` to leave an admin-chosen default variation alone. Default is
driven by the module setting "Respect manual defaults".

### `freeman_core/cheapest/eligible_variation`
```php
apply_filters( 'freeman_core/cheapest/eligible_variation', bool $eligible, \WC_Product_Variation $variation );
```
Return `false` to exclude a specific variation from the cheapest-pick
comparison (e.g. skip sample-size SKUs).

## Actions

### `freeman_core/cheapest/default_chosen`
Fires once the module has resolved which variation to default.
```php
do_action( 'freeman_core/cheapest/default_chosen', \WC_Product $product, \WC_Product_Variation $variation );
```

## Template overrides
This module ships no templates.
