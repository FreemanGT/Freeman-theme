# Variable Stock Fix

When every visible variation of a variable product is out of stock, this module unchecks the parent's "Manage stock" box so Woo's native "Hide out of stock items" setting actually hides the product from the shop.

## The problem it solves

WooCommerce's "Hide out of stock items" setting (WC → Settings → Products → Inventory) only hides variable products when the **parent** shows as OOS. If the parent has "Manage stock" ON with any quantity > 0, Woo treats it as in-stock — even when every visible variation is sold out. The result: empty cards on the shop page.

## What it does

- **On product save** — when a variable product is saved (classic or REST) and every visible child is OOS, unchecks the parent's `manage_stock`.
- **On variation stock/status change** — same check, triggered from the parent up, **debounced 30 s** so a bulk stock import doesn't fire hundreds of inline checks.
- **Daily audit cron** — scans products modified in the last 48 hours, `BATCH_SIZE` (50) at a time, self-chaining via `wp_schedule_single_event` so huge stores never blow `max_execution_time`.
- **Bulk audit UI** — one-time full-catalog scan + dry-run toggle on the module settings page.

## Settings

Freeman → Variable Stock Fix:
- **Daily audit** — run the 48h audit cron (default ON)

## Dependencies

- WooCommerce (required)

## Legacy import

Detects `woo-variable-stock-fix/woo-variable-stock-fix.php`. The legacy plugin stored no persistent options — import just clears the legacy `vpsf_daily_audit` cron hook.

## Public hooks

See [`HOOKS.md`](HOOKS.md).
