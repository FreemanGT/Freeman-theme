# Product Feed — Public API

## Shipped hooks (1.11.1+)

### `freeman_core/product_feed/query_args` (filter, since 1.11.1)
```php
apply_filters(
    'freeman_core/product_feed/query_args',
    array $args,
    int   $offset
);
```
Filters the args passed to `get_posts()` inside `Generator::write_feed()`.
Fires once per batch (offset advances by `Generator::BATCH` each tick) so
listeners that scope the feed (e.g. by category) must apply the same scope
on every call.

### `freeman_core/product_feed/item` (filter, since 1.11.1)
```php
apply_filters(
    'freeman_core/product_feed/item',
    string      $xml,
    \WC_Product $product
);
```
Filters the rendered XML block for a single product before it is gzipped
and written to the feed file. Returning an empty string skips the product
silently. Use to inject custom fields, prepend metadata, or rewrite the
canonical fields that `Generator::product_xml()` emits by default.

### Google Shopping fields (filters, since 1.1.0 of this module)

Each filter wraps the resolved value just before it is escaped and emitted
into the per-product `<google_shopping>` block. Returning an empty string
emits the element as empty (`<gtin></gtin>`) — the elements are never
skipped. All filters receive `($value, \WC_Product $p)`.

```php
apply_filters( 'freeman_core/product_feed/google_product_category', string $value, \WC_Product $p );
apply_filters( 'freeman_core/product_feed/gtin',                    string $value, \WC_Product $p );
apply_filters( 'freeman_core/product_feed/mpn',                     string $value, \WC_Product $p );
apply_filters( 'freeman_core/product_feed/brand',                   string $value, \WC_Product $p );
apply_filters( 'freeman_core/product_feed/identifier_exists',       string $value, \WC_Product $p );
```

Resolution chains (filter runs after the chain resolves):
- `google_product_category` — product meta `_freeman_google_product_category` → walk primary `product_cat` + ancestors checking term meta `freeman_google_product_category` → empty.
- `gtin` — product meta `_freeman_gtin` → product meta `_global_unique_id` (Woo 8.3+ native) → empty.
- `mpn` — product meta `_freeman_mpn` → SKU → empty.
- `brand` — product meta `_freeman_brand` → `product_brand` taxonomy term name → first value of `pa_brand` attribute → empty.
- `identifier_exists` — product meta `_freeman_identifier_exists` → derived: `yes` if both `gtin` and `brand` are non-empty, else `no`.

## Shipped actions (1.11.1+)

### `freeman_core/product_feed/before_serve` (action, since 1.11.1)
```php
do_action( 'freeman_core/product_feed/before_serve' );
```
Fires when a request to the `/product-feed` endpoint is about to be served,
before any headers are emitted. Does NOT fire when the rewrite rule misses
(the early `get_query_var()` guard returns first). Use cases: custom auth
gate, request logging, rate limiting, cache-purge integration.

### `freeman_core/product_feed/after_generate` (action, since 1.11.1)
```php
do_action(
    'freeman_core/product_feed/after_generate',
    string $feed_file,
    float  $elapsed
);
```
Fires after a successful feed generation, once the new file is in place
and the last-generated timestamp option has been written. Does NOT fire on
a failed run — the catch block in `Generator::generate()` handles cleanup
but never reaches this point. Use cases: ping Merchant Center / Facebook
Catalog webhooks, invalidate a CDN, push the file to S3.

## Planned hooks (NOT YET SHIPPED)

The hooks below are documented for planning but are not yet wired into the
code. Treat as "do not rely on" until `@since` tags appear in the source.

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
