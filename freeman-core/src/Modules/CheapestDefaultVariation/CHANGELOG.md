# Cheapest Default Variation — Changelog

## [1.1.0] — 2026-04-26

- New **Apply on product pages only** setting (default ON). When on, the auto-selection is suppressed on shop / archive / loop contexts — swatches there render with no pre-selected variation and the customer has to actively pick one. PDP behavior unchanged. Admin and AJAX/REST contexts still receive the cheapest pick because variation logic relies on it.

## [1.0.0] — 2026-04-22

- Initial port from `auto-default-cheapest-variation.php`.
- Added "Respect manual defaults" setting.
- Importer detects the legacy snippet for tracking.
