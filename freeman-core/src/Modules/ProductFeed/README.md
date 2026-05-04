# Product Feed

Generates a gzipped XML feed of every published product — with variations, stock, pricing, and attributes — and serves it at `/product-feed`. Rebuilt hourly and within ~30 s of any stock or price change.

## What it does

- **Feed generation** — batches products (100 per tick) into a single `.xml.gz` file under `wp-content/uploads/freeman-product-feed/`. Uses `gzopen` + `flock` so two cron ticks never collide.
- **Feed serving** — registers `/product-feed` as a rewrite rule; returns `application/xml` with proper `gzip` encoding. First-miss generates on demand; subsequent hits serve the cached file.
- **Debounced instant updates** — stock or price change → `wp_schedule_single_event(time()+30, …)` so a burst of changes triggers exactly one regeneration.
- **Hourly safety net** — `wp_schedule_event('hourly', …)` catches anything the instant-update hooks missed.
- **Status panel** in Freeman → Product Feed shows last-generated timestamp, gzipped size, next-hourly-run, and a "Generate now" button.
- **Google Shopping fields** — each parent product's XML carries a `<google_shopping>` block with `google_product_category`, `gtin`, `mpn`, `brand`, `identifier_exists`. Each value goes through a per-field filter (see [`HOOKS.md`](HOOKS.md)). Variation XML is unchanged.

## Performance

`write_feed()` batches variation post-meta via `_prime_post_caches($ids, true, true)` so each batch is a few queries instead of one query per product. On a 5,000-product catalog with ~10 variations per product, generation typically finishes in under a minute.

## Settings

Freeman → Product Feed:
- **Instant updates** — rebuild within 30 s of stock/price change (default ON)
- **Hourly fallback** — also run a full regeneration every hour (default ON)

## Dependencies

- WooCommerce (required)
- Write access to `wp-content/uploads/` (for the feed file + lock)

## Legacy import

Detects `wc-product-feed/wc-product-feed.php`. Migrates `wcpf_options.instant_update` / `wcpf_options.hourly_fallback` and `wcpf_last_generated` → Freeman option keys, clears the legacy `wcpf_hourly_cron` / `wcpf_async_generate` events.

## Uninstall

`on_uninstall()` removes the feed file + lock, clears both cron hooks, deletes `freeman_core_product_feed_*` options.

## Public hooks

See [`HOOKS.md`](HOOKS.md).
