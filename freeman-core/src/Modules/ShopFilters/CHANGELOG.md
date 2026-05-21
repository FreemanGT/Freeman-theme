# Shop Filters — Changelog

## [1.12.4] — 2026-05-20

- Bug-fix: the recurring reconcile sweep now actually schedules. `Indexer::ensure_scheduled()` was called from `Module::boot()` on `plugins_loaded`, before Action Scheduler's store is ready — so `as_schedule_recurring_action()` silently no-op'd and the page showed "Auto-reindex: not scheduled". Scheduling is now deferred to the `init` hook (after AS initialises), so both the schedule call and the status check use the same ready scheduler. Event-driven (on-save) indexing was unaffected; only the periodic sweep.

## [1.12.3] — 2026-05-20

- Admin control surface (Phase 6.1 follow-up, by request) — manage everything from Freeman → Shop Filters without WP-CLI:
  - **Background indexing** toggle — a `settings_schema` checkbox keyed `indexer_enabled` that writes the same option the feature flag reads, so it's the same switch as the Freeman → Feature Flags entry.
  - Live **index status** — indexed products, total rows, last sweep time, and whether the reconcile sweep is scheduled.
  - The **Reindex all products** tool is now always visible while the module is enabled (previously only after the flag was already on — a chicken-and-egg).
- The admin surface loads whenever the module is enabled; the auto-indexer (lifecycle hooks + sweep) still only attaches when the toggle is on. Reindex AJAX handlers are guarded on the toggle (defence in depth).

## [1.12.2] — 2026-05-20

- Bug-fix (Phase 6.1 indexer, caught in review before live QA): non-variation global attributes on a variable product (e.g. an informational "Brand" attribute that isn't a variation axis) now follow the product's overall stock instead of being treated as variation-stock-gated. Previously every value of such an attribute was marked out-of-stock (no matching variation), which would have hidden them under in-stock-only filtering. The decision is extracted to the pure, unit-tested `Term_Helpers::resolve_in_stock()`. Also hardened `Indexer::ensure_scheduled()`'s Action Scheduler check (`! as_next_scheduled_action()` rather than strict `false ===`).

## [1.12.1] — 2026-05-20

- Facet engine (Wave 6, Phase 6.2). Pure, fully unit-tested computation core — still no storefront output:
  - `Facet_Engine` — filtered product set + per-facet availability/counts with AND-across / OR-within and **self-exclusion** (a facet you're filtering on keeps its other values visible — the classic faceted-search bug guard), hide-zero values (req #2) and hide-empty-facet (req #1).
  - `Category_Tree` — pruned, count-rolled-up, ordered parent→child tree (req #3).
  - `Url_State` — parse / serialize `?filter_pa_*=slug,slug&min_price=…` filter state (query-string params; no rewrite rules).
  - `Facet_Config` — auto-derives facets from the catalogue's attributes (every `pa_*` as a checkbox, `product_cat` as a tree); filters `freeman_core/shop_filters/{facet_config, is_facet_visible}` let code override.
- No feature flag — dormant library classes with no runtime caller yet (Hard Rule #1 additive exception). `Query_Builder` (the index → engine `$wpdb` glue) is deferred to 6.3a where it's wired and integration-tested.

## [1.12.0] — 2026-05-20

- New module (Wave 6, Phase 6.1 — foundation). Faceted, context-aware AJAX product filters for shop / category pages, backed by a lightweight background index. This version ships **only the foundation** — nothing renders on the storefront yet.
- Custom index table `{prefix}freeman_shop_filter_index` (`product_id`, `taxonomy`, `term_id`, `in_stock`) — a narrow term / category membership table with a per-attribute-value in-stock flag. Price / stock / rating are read from WooCommerce's own `wc_product_meta_lookup`, never duplicated. Auto-installed via `Migrations::run()` on the version bump; dropped on uninstall.
- Background `Indexer`: an event-driven dirty queue (WooCommerce product / stock lifecycle hooks → 30-second debounced drain) plus a ~5-minute reconciliation sweep that re-indexes products modified since the last sweep, batched and self-chaining. Scheduling prefers Action Scheduler when WooCommerce makes it available, falling back to wp-cron — never a hard dependency.
- Admin **Reindex all products** tool on the Freeman → Shop Filters page (offset-paged batches, `manage_woocommerce`).
- Gated behind the `freeman_core_shop_filters_indexer_enabled` feature flag (default **off**). The module itself is disabled by default; enabling it without the flag does nothing.
