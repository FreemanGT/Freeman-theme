# Category Slider — Public API

## Shipped hooks (1.11.1+)

### `freeman_core/category_slider/query_args` (filter, since 1.11.1)
```php
apply_filters(
    'freeman_core/category_slider/query_args',
    array $args,
    array $settings
);
```
Filters the args about to be passed to `get_terms()` inside the widget's
`fetch_terms()` method. `$settings` is the resolved Elementor settings array
(post-coercion: `include` / `exclude` already converted to int arrays).

### `freeman_core/category_slider/render_card` (filter, since 1.11.1)
```php
apply_filters(
    'freeman_core/category_slider/render_card',
    string   $card_html,
    \WP_Term $term,
    array    $context
);
```
Filters the rendered HTML for a single category card. Fires once per term
inside the render loop. `$context` keys: `url`, `count`, `thumb`, `hue`,
`shape`, `show_count`. Returning a different string replaces the card markup
entirely; the wrapping `.cs-track` is not affected.

`$card_html` is already escaped — return strings should preserve the same
escaping invariants if you replace the markup.

## JS

A re-init helper is exposed for callers that swap markup at runtime (custom
AJAX content loaders, etc.):

```js
window.FreemanCategorySlider.init( document ); // or pass a scoped element
```

`init()` is idempotent — every `.cs` element is bound at most once via an
internal flag, so repeated calls during Elementor editor re-renders are safe.

The widget also wires into Elementor's standard re-init hook:
`frontend/element_ready/freeman_category_slider.default`.
