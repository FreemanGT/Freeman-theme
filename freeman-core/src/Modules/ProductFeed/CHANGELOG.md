# Product Feed — Changelog

## 1.1.0
- Feed XML now emits a `<google_shopping>` block per product after `<attributes>`, containing `<google_product_category>`, `<gtin>`, `<mpn>`, `<brand>`, `<identifier_exists>`. Variation XML is unchanged.
- Field resolution chains:
  - `google_product_category` — product meta `_freeman_google_product_category` → walk primary category + ancestors checking term meta `freeman_google_product_category` → empty
  - `gtin` — product meta `_freeman_gtin` → product meta `_global_unique_id` (Woo 8.3+ native) → empty
  - `mpn` — product meta `_freeman_mpn` → SKU → empty
  - `brand` — product meta `_freeman_brand` → `product_brand` taxonomy term name → first value of `pa_brand` attribute → empty
  - `identifier_exists` — product meta `_freeman_identifier_exists` → derived: `yes` if both gtin and brand are non-empty, else `no`
- New filters (each receives `($value, \WC_Product $p)`):
  - `freeman_core/product_feed/google_product_category`
  - `freeman_core/product_feed/gtin`
  - `freeman_core/product_feed/mpn`
  - `freeman_core/product_feed/brand`
  - `freeman_core/product_feed/identifier_exists`
- Additive only — no existing field name, order, or wrapping changed.

## 1.0.0
- Initial port from `wc-product-feed` v1.3.0.
- Rewrite rule is `/product-feed` (unchanged from legacy).
- Feed directory moved to `wp-content/uploads/freeman-product-feed/`.
- Hooks namespaced under `freeman_productfeed_*`.
- Importer clears legacy crons and carries over the last-generated timestamp.
