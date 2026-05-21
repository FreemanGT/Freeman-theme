# Shop Filters

Faceted, context-aware AJAX product filters for shop and category pages — an in-house
replacement for Filter Everything Pro, built to work cleanly with the Elementor-rendered
product grid.

**Status:** in development (Wave 6). This version (1.12.0 / Phase 6.1) is the **foundation
only** — the index and indexer. The storefront UI (shortcode, facet engine, AJAX swap) ships
in later phases. The module is **disabled by default** and additive/reversible.

## What it will do (epic goals)

1. Hide filters that don't apply to the current category.
2. Within a filter, show only attribute values present on the current page's products.
3. Show the category filter as a relevant, pruned parent→child hierarchy.
4. Stay fast via a background index, refreshed automatically as products change.

## Architecture (Phase 6.1)

- **`Database`** — owns the index table `{prefix}freeman_shop_filter_index`
  (`product_id, taxonomy, term_id, in_stock`). Auto-installed by
  `Freeman\Core\Core\Migrations::run()` on the version bump; dropped on uninstall.
  Stores only what `wc_product_meta_lookup` can't (per-attribute-value in-stock truth +
  category membership).
- **`Index_Repository`** — the only place raw SQL for the table lives.
- **`Indexer`** — event-driven dirty queue (WC lifecycle hooks → 30s debounced drain) plus a
  ~5-minute reconcile sweep (Action Scheduler when present, wp-cron fallback), batched and
  self-chaining. Also powers the admin reindex tool.
- **`Term_Helpers`** — pure attribute helpers, deliberately duplicated rather than calling
  VariationSwatches (which only loads when that module is enabled).
- **`Admin_Page`** — the "Reindex all products" tool on Freeman → Shop Filters.

## Feature flag

`freeman_core_shop_filters_indexer_enabled` (default off) gates the indexer hooks, scheduling
and the admin tool. Enable:

```
wp option update freeman_core_shop_filters_indexer_enabled 1
```

Disable (rollback):

```
wp option update freeman_core_shop_filters_indexer_enabled 0
```
