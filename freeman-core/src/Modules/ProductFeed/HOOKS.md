# Product Feed — Public API

## Filters

### `freeman_core/product_feed/product_query_args`
```php
apply_filters( 'freeman_core/product_feed/product_query_args', array $args );
```
Pre-query `WP_Query` args used to list products for the feed.

### `freeman_core/product_feed/product_payload`
```php
apply_filters( 'freeman_core/product_feed/product_payload', array $payload, \WC_Product $product );
```
Shape of `$payload` follows Google Merchant / Facebook catalog conventions:
`id`, `title`, `description`, `availability`, `price`, `sale_price`, `link`,
`image_link`, `brand`, `gtin`, `additional_image_link`, `custom_label_0..4`.

### `freeman_core/product_feed/should_include`
```php
apply_filters( 'freeman_core/product_feed/should_include', bool $include, \WC_Product $product );
```
Exclude specific products from the feed.

## Actions

### `freeman_core/product_feed/generated`
```php
do_action( 'freeman_core/product_feed/generated', string $path, int $product_count, int $bytes );
```
Fires after a full feed regeneration completes.

### `freeman_core/product_feed/instant_queued`
```php
do_action( 'freeman_core/product_feed/instant_queued', int $product_id, string $reason );
```
Fires when a stock/price change schedules an instant rebuild.

## Cron hooks
- `freeman_core_feed_hourly` — full rebuild (disable via "Hourly fallback" setting).
- `freeman_core_feed_instant` — debounced rebuild window (disable via "Instant updates").

## File location
Feed is written to `uploads/freeman-product-feed/products.xml.gz`. The public
URL is available through `\Freeman\Core\Modules\ProductFeed\Module::feed_url()`.

## Rewrite rule
`/product-feed` → `products.xml.gz` (301-served with gzip `Content-Encoding`).
