# Shop Filters — Changelog

## [1.12.0] — 2026-05-20

- New module (Wave 6, Phase 6.1 — foundation). Faceted, context-aware AJAX product filters for shop / category pages, backed by a lightweight background index. This version ships **only the foundation** — nothing renders on the storefront yet.
- Custom index table `{prefix}freeman_shop_filter_index` (`product_id`, `taxonomy`, `term_id`, `in_stock`) — a narrow term / category membership table with a per-attribute-value in-stock flag. Price / stock / rating are read from WooCommerce's own `wc_product_meta_lookup`, never duplicated. Auto-installed via `Migrations::run()` on the version bump; dropped on uninstall.
- Background `Indexer`: an event-driven dirty queue (WooCommerce product / stock lifecycle hooks → 30-second debounced drain) plus a ~5-minute reconciliation sweep that re-indexes products modified since the last sweep, batched and self-chaining. Scheduling prefers Action Scheduler when WooCommerce makes it available, falling back to wp-cron — never a hard dependency.
- Admin **Reindex all products** tool on the Freeman → Shop Filters page (offset-paged batches, `manage_woocommerce`).
- Gated behind the `freeman_core_shop_filters_indexer_enabled` feature flag (default **off**). The module itself is disabled by default; enabling it without the flag does nothing.
