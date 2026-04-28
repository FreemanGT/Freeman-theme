# Restock Notify — Changelog

## 1.0.0
- Initial port from `restock-notify` v1.2.0.
- Legacy class bodies bundled under `legacy/includes/`; assets at module root.
- Helper shims (`rsn_option_defaults`, `rsn_get_option`) re-declared in `legacy/helpers.php`.
- Table name (`{prefix}rsn_subscribers`) and option keys preserved so existing data carries over automatically.
