# Cheapest Default Variation — Public API

Everything in this document is considered a stable extension surface. Internal
classes and private methods are off-limits; if you need something they do,
open an issue and we'll promote it.

## Filters

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
