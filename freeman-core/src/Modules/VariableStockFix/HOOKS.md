# Variable Stock Fix — Public API

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
