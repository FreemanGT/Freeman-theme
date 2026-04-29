# Restock Notify — Public API

Bundled from the legacy `restock-notify` plugin. The original `rsn_*` hooks
are still emitted; new Freeman Core hooks live under the `freeman_core/restock/`
namespace.

## Shipped hooks (1.11.5+)

### `freeman_core/restock_notify/should_inject` (filter, since 1.11.5)
```php
apply_filters(
    'freeman_core/restock_notify/should_inject',
    bool        $inject,
    \WC_Product $product,
    string      $context  // 'summary' | 'variation' | 'cart_form' | 'meta' | 'shortcode' | 'stock_filter' | 'footer'
);
```
Per-product gate for form injection. Fires inside `Frontend::maybe_render()`
once per attempted render (across all 6 entry points: 4 WC template hooks +
shortcode + stock-html filter + wp_footer fallback). Distinct from
`rsn_should_enqueue` (which gates the asset load for the whole page); this
one fires per-product and per-context.

Return `false` to short-circuit the render. Useful for "no form for this
specific product" rules without disabling the module globally.

## Shipped hooks (1.11.4+)

### `freeman_core/restock_notify/email_args` (filter, since 1.11.4)
```php
apply_filters(
    'freeman_core/restock_notify/email_args',
    array  $args,
    object $subscriber,
    string $kind   // 'confirmation' or 'notification'
);
```
Filters the args dictionary that drives the rendered email HTML inside
modern `Email::build_html()`. Keys: `heading`, `body`, `product_name`,
`product_image`, `button_url`, `button_text`, `unsubscribe_url`,
`customer_name`. Use to inject custom rows, override styling tokens, or
swap the customer-name fallback per send.

Fires for confirmation AND notification emails — distinguish via the
`$kind` parameter.

### `freeman_core/restock_notify/before_send` (action, since 1.11.4)
```php
do_action(
    'freeman_core/restock_notify/before_send',
    string $to,
    string $subject,
    string $html,
    object $subscriber
);
```
Fires immediately before `wp_mail()`. Use cases: route to a transactional
ESP, log the send, gate by subscriber attribute. Informational by design —
to actually suppress the send, listeners should use the WP `wp_mail` /
`pre_wp_mail` filters; this hook does not short-circuit.

## Filters (legacy, still supported)

- `rsn_notification_email_subject`
- `rsn_notification_email_body`
- `rsn_form_labels`
- `rsn_rate_limit_seconds`

## Filters (Freeman Core)

### `freeman_core/restock/form_enabled`
```php
apply_filters( 'freeman_core/restock/form_enabled', bool $enabled, \WC_Product $product );
```
Hide the opt-in form per product.

### `freeman_core/restock/subscription_allowed`
```php
apply_filters( 'freeman_core/restock/subscription_allowed', true|\WP_Error $ok, string $email, int $product_id );
```
Return a `WP_Error` to block a specific email/domain (e.g. disposable-email guard).

### `freeman_core/restock/email_to_send`
```php
apply_filters( 'freeman_core/restock/email_to_send', array $email, int $subscription_id, \WC_Product $product );
```
Shape: `[ 'to' => string, 'subject' => string, 'body' => string, 'headers' => string[] ]`.

## Actions

### `freeman_core/restock/subscribed`
```php
do_action( 'freeman_core/restock/subscribed', int $subscription_id, string $email, int $product_id );
```

### `freeman_core/restock/notified`
```php
do_action( 'freeman_core/restock/notified', int $subscription_id, string $email, int $product_id );
```

### `freeman_core/restock/bounced`
Fires if `wp_mail()` returns false so admins can wire in a logging service.

## Custom table
`{wpdb->prefix}rsn_subscriptions` — owned by the module. Schema is versioned;
migrations run automatically on plugin upgrade.

## AJAX endpoints
- `wp_ajax_nopriv_rsn_subscribe` — creates a subscription (nonce + rate-limited).
- `wp_ajax_nopriv_rsn_unsubscribe` — removes a subscription (signed token).

## Cron
- `freeman_core_rsn_stock_monitor` — every 5 minutes. Walks recently-restocked
  products and queues notifications.

## Template overrides
```
yourtheme/freeman-core/restock-notify/form.php
yourtheme/freeman-core/restock-notify/email.php
```
