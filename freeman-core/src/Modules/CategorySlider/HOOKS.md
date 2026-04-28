# Category Slider — Public API

This module currently exposes its surface through Elementor controls only. No
PHP filters yet — open an issue if you need one (e.g. to inject custom
categories or override the term query).

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
