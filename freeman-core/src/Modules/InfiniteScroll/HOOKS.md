# Infinite Scroll — Public API

## Filters

### `freeman_core/infinite_scroll/config`
```php
apply_filters( 'freeman_core/infinite_scroll/config', array $config );
```
Shape of `$config` (as sent to `wp_localize_script`):
```php
array(
    'skeletonCount' => int,     // How many placeholders to paint.
    'maxPages'      => int,     // Hard cap on auto-loaded pages.
    'endMessage'    => string,  // Shown once the list is exhausted.
    'errorMessage'  => string,  // Shown on fetch failure.
    'selectors'     => array(
        'container' => string,  // CSS selector for the products container.
        'item'      => string,  // CSS selector for an individual card.
        'next'      => string,  // CSS selector for the next-page link.
    ),
);
```

### `freeman_core/infinite_scroll/should_enable`
```php
apply_filters( 'freeman_core/infinite_scroll/should_enable', bool $enable );
```
Disable infinite scroll on specific pages (e.g. campaign landing pages).

## Actions

### `freeman_core/infinite_scroll/page_loaded` (JS)
Fires on the `window` object in the browser after a new page of products is
appended to the DOM. Listen with:
```js
window.addEventListener( 'freeman:infinite-scroll:page-loaded', function ( event ) {
    console.log( event.detail.page, event.detail.itemsAppended );
} );
```

## Template overrides
Skeleton markup is inline in `assets/js/infinite-scroll.js`. Override by filtering
`freeman_core/infinite_scroll/config.skeletonMarkup` (string, HTML allowed).
