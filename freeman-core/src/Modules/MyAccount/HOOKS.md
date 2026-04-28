# My Account — Public API

This module is a pure visual layer. It exposes no PHP filters, actions, or
template overrides. The only public surface is the CSS handle:

## CSS handle

### `freeman-core-my-account`
The stylesheet that restyles the classic My Account page. Loaded only when
`is_account_page()` is true. Dequeue it to disable the restyle without
disabling the whole module:

```php
add_action( 'wp_enqueue_scripts', function () {
    wp_dequeue_style( 'freeman-core-my-account' );
}, 20 );
```

## Style overrides
Override any rule by enqueueing your own stylesheet **after**
`freeman-core-my-account` and matching the same selectors. The module's
stylesheet uses no `!important` declarations except for the
`prefers-reduced-motion` block, so normal cascade rules apply.

## Tokens
Visual tokens come from the theme's `--fm-*` palette. To recolor the page
without touching CSS rules, override the relevant `--fm-*` variable in your
theme's tokens file or a Customizer additional-CSS rule.
