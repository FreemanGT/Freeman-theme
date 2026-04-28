# Product Feed — Changelog

## 1.0.0
- Initial port from `wc-product-feed` v1.3.0.
- Rewrite rule is `/product-feed` (unchanged from legacy).
- Feed directory moved to `wp-content/uploads/freeman-product-feed/`.
- Hooks namespaced under `freeman_productfeed_*`.
- Importer clears legacy crons and carries over the last-generated timestamp.
