# Variable Stock Fix — Public API

## Shipped hooks (1.11.0+)

### `freeman_core/variable_stock_fix/should_check` (filter, since 1.11.0)
```php
apply_filters(
    'freeman_core/variable_stock_fix/should_check',
    bool $check,
    \WC_Product $product
);
```
Return `false` to skip the all-out-of-stock evaluation for this variable
product entirely. Fires *after* the `'not variable'` early guard, so listeners
do not need to type-check `$product`. Useful for per-product overrides (product
meta, taxonomy gates, custom fields) without disabling the module globally.

When the filter returns `false`, `maybe_uncheck_manage_stock()` returns
`array( 'changed' => false, 'reason' => 'skipped by freeman_core/variable_stock_fix/should_check' )`.

## Planned hooks (NOT YET SHIPPED)

The hooks below are documented for planning but are not yet wired into the
code. Treat as "do not rely on" until `@since` tags appear in the source.

## Filters

### `freeman_core/vsf/should_process`
```php
apply_filters( 'freeman_core/vsf/should_process', bool $process, \WC_Product $product );
```
Return `false` to skip a specific variable product during lifecycle hooks
(save, stock change) and audits.

### `freeman_core/vsf/visible_variations`
```php
apply_filters( 'freeman_core/vsf/visible_variations', int[] $variation_ids, \WC_Product_Variable $parent );
```
Override the list of variations the module considers "visible" when deciding
whether everything is out of stock.

## Actions

### `freeman_core/vsf/parent_cleared`
```php
do_action( 'freeman_core/vsf/parent_cleared', int $product_id );
```
Fires whenever the module unchecks "Manage stock" on a parent.

### `freeman_core/vsf/audit_complete`
```php
do_action( 'freeman_core/vsf/audit_complete', array $report );
```
`$report` contains `scanned`, `matched`, `fixed`, and `skipped` counts.

## AJAX endpoints
- `wp_ajax_freeman_vsf_scan_batch` — scans a batch of product IDs, nonce-guarded.
- `wp_ajax_freeman_vsf_fix_batch`  — applies fixes for a batch, nonce-guarded.

## Cron
- `freeman_core_vsf_daily_audit` — scheduled daily. Disable by unchecking the
  "Daily audit" setting, or with:
```php
add_filter( 'freeman_core/vsf/schedule_daily_audit', '__return_false' );
```
