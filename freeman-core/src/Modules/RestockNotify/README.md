# Restock Notify

Back-in-stock subscription system. Customers sign up on an out-of-stock product; when stock returns, they get an email with a "Buy now" CTA.

## What it does

- **Auto-injected form** on out-of-stock simple products and on variable products whose selected variation is OOS. Form also renders via `[restock_notify]` shortcode anywhere.
- **Stock monitor** watches `woocommerce_variation_set_stock*` / product stock updates and triggers a notification email to every waiting subscriber when a product/variation returns to stock.
- **Admin UI** lists subscribers (waiting / notified), supports CSV export, bulk delete, and manual re-send.
- **Rate limiting + honeypot** protect the public subscribe AJAX endpoint. 5 submissions per IP per hour; honeypot field silently drops obvious bots.

## Settings

Settings live in a top-level "Restock Notify" admin menu (preserved from the legacy plugin). Dashboard card includes a "Settings (legacy menu)" shortcut.

Stored under `rsn_*` option keys:
- `form_heading`, `form_description`, `form_button_text`, `form_success_message`, `form_duplicate_message`
- `enable_confirmation` — send a confirmation email on signup
- `enable_gdpr`, `gdpr_text` — opt-in consent checkbox
- `notify_subject`, `notify_heading`, `notify_body`, `notify_button_text` — restocked-email template (supports `{product_name}`, `{product_url}`, `{customer_name}`, `{unsubscribe_url}`, `{shop_url}`, `{site_name}`)
- `from_name`, `from_email` — sender; CR/LF stripped at send time to prevent header injection
- `auto_inject` — control where the form appears

## Database

Owns `{prefix}rsn_subscribers` (subscriber list). Version-stamped via `rsn_db_version`; `RSN_Database::create_tables()` runs on activation and on version-mismatch boot.

## Dependencies

- WooCommerce (required)
- jQuery (loaded by WP core)

## Legacy import

Detects `restock-notify/restock-notify.php`. The module reuses the same option keys + table name, so import is a no-op; subscribers keep their status and unsubscribe tokens across the migration.

## Conflict guard

If the legacy `RSN_Frontend` / `RSN_Ajax` / `RSN_Email` / `RSN_Database` class is already loaded when Freeman Core boots (usually because the legacy plugin is still active), the module bails out of `boot()` and the Freeman dashboard shows a red admin notice telling you to deactivate the legacy plugin. This prevents a fatal class-redeclare.

## Security

- `freeman_core_restock_conflict` transient surfaces conflicts.
- Nonce-verified AJAX, honeypot (`_hp`), REMOTE_ADDR rate limit via `Security::rate_limit()`.
- Email headers sanitized: `from_name` stripped of CR/LF, `from_email` run through `sanitize_email()`.

## Public hooks

See [`HOOKS.md`](HOOKS.md).

## Known limitations

- Large legacy classes (`RSN_Frontend` ~580 lines, `RSN_Admin` ~22 KB) retained verbatim from the legacy plugin; refactor is deferred.
- Assets currently enqueue on every frontend page by design ("cannot fail" visibility guarantee).
