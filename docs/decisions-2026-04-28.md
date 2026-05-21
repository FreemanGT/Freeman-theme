# Freeman Plugin Suite — Strategic Decisions

**Date**: 2026-04-28
**Owner**: Yiftach
**Purpose**: Resolve the Open Questions from the Gap Audit (§4.1–§4.8) so the roadmap can be executed without per-item human approval cycles.

---

## §4.1 Product positioning — **Internal-only**

Freeman Core is a private suite for Freeman Digital's clients. Not sold standalone, not competing with commercial WC plugins.

**Implications**:
- Drop competitor-parity features (Wishlist A1, Quick-View A2) unless a specific client asks.
- Opinionated defaults are fine — no need to support every merchant configuration.
- White-labeling and telemetry are not needed (see §4.7, §4.8).

---

## §4.2 Locale strategy — **English defaults, Hebrew opt-in**

New installs ship English defaults. Hebrew is selectable via a settings toggle or via WP locale (`get_locale() === 'he_IL'` → suggest Hebrew but don't force).

**Implications**:
- Roadmap #2 builds `RestockNotify/locales/en_US.php` (default) and `he_IL.php` (opt-in).
- **Existing Hebrew installs**: do NOT overwrite their option values. Activation routine checks if values exist; if yes, leave alone.
- Email templates move from option-string defaults to template files (one file per locale).

---

## §4.3 Block editor commitment — **Elementor-only forever**

No Gutenberg block patterns, no block-based templates, no FSE.

**Implications**:
- Drop Roadmap #7 (theme.json → `--fm-*` unification, B7). The block editor's pickers don't matter.
- Drop D5 (theme.json filters). Modules don't need to contribute presets to Gutenberg.
- Design-token work (B1–B6, B8) stays — those serve Elementor controls and CSS, not Gutenberg.

---

## §4.4 REST vs admin-AJAX — **Keep AJAX. REST per-feature only if needed.**

No headless app, no SPA, no third-party integration pressure. Existing AJAX works.

**Implications**:
- Drop Roadmap #8 (broad REST API surface, D2, A23) from P1.
- New endpoints default to `wp_ajax_*` / `admin_post_*` like the existing surface.
- If a specific feature later needs REST (e.g., a React-based admin tool), add the namespace then.
- Wave 1.2 of the system prompt (REST scaffold) is **deferred** — do not build until a concrete need appears.

---

## §4.5 Legacy code sunset (VariationSwatches `legacy/`, `etucart_*` keys) — **No hard date. Migrate carefully.**

Zero-downtime migration is the priority over speed. Existing sites must keep working.

**Implications**:
- Roadmap #4 (VariationSwatches → Settings_Hub) proceeds, but:
  - New Settings_Hub options are **additive** alongside `etucart_vs_*` keys.
  - A read-shim reads new key first, falls back to legacy key.
  - A migration routine copies old → new on plugin upgrade, but never deletes old.
  - Legacy code stays in `legacy/` directory indefinitely.
  - Sunset of `etucart_*` keys is a separate future decision, requires explicit approval.

---

## §4.6 freeman-digital coupling — **Stay independent**

Two separate plugins. freeman-digital does not depend on freeman-core, and vice versa.

**Implications**:
- No cross-plugin function calls. Shared code (if any) gets duplicated, not extracted into a third package.
- Logger lives in freeman-core; freeman-digital uses its own logging.
- Hooks are namespaced per package (`freeman_core/...` vs `freeman_digital/...`).

---

## §4.7 White-labeling — **No**

Internal use only. No agency resale, no per-merchant branding.

**Implications**:
- Drop D9 (frontend asset URL filter for white-labeling).
- Drop the "admin-skin filter" idea for freeman-digital.
- D8 (Logger filters) **stays** — it's needed for observability/Wave 0, not for white-labeling.

---

## §4.8 Telemetry — **Skip**

Don't ship usage analytics. Internal client-base is small and reachable directly.

**Implications**:
- Drop A25 (telemetry/opt-in usage analytics) from the roadmap entirely.
- No new privacy policy obligations, no GDPR data-flow review, no anonymization layer.

---

## Revised Roadmap Summary

After applying these decisions, the original 15-item roadmap becomes ~9 items:

| # | Status | Reason |
|---|---|---|
| 1 (D1 hooks) | **Keep — P0** | Extensibility is internal-team value |
| 2 (RestockNotify locale) | **Keep — P0**, simplified per §4.2 |
| 3 (ProductFeed multi-channel) | **Keep — P0** if any client needs Facebook/Pinterest; otherwise P2 |
| 4 (VariationSwatches → Settings_Hub) | **Keep — P0**, gated by §4.5 migration plan |
| 5 (InfiniteScroll trigger modes) | **Keep — P1** |
| 6 (Sliders autoplay/dots/lazy) | **Keep — P1** |
| 7 (theme.json unification) | **DROP** per §4.3 |
| 8 (REST API) | **DROP / defer** per §4.4 |
| 9 (CheapestDefaultVariation strategies) | **Keep — P1** |
| 10 (RestockNotify CSV export + GDPR) | **Keep — P2** |
| 11 (Slider design tokens as Elementor controls) | **Keep — P2** |
| 12 (InfiniteScroll skeleton tokens) | **Keep — P2** |
| 13 (Wishlist) | **DROP** per §4.1 |
| 14 (Quick-View) | **DROP** per §4.1 |
| 15 (Critical CSS / WebP) | **Keep — P3**, only if a client asks |

**Net**: 9 active items, in priority order: 1, 2, 4, 3 (P0) → 5, 6, 9 (P1) → 10, 11, 12 (P2).

---

## Revisit triggers

Re-open these decisions if any of the following happen:
- A client requests a feature that was dropped (Wishlist, Quick-View, REST endpoint, etc.)
- The product direction shifts to commercial / agency / standalone sale
- A second engineer joins and needs the codebase to be more conventional
- Maintenance cost of keeping freeman-core and freeman-digital separate becomes painful

---

## §5 Shop Filters module (added 2026-05-20)

A net-new module replacing the third-party "Filter Everything Pro" plugin (faceted AJAX shop/category filters). Decisions taken at planning time so the Wave 6 phases execute without per-item re-litigation:

- **§5.1 Build vs §4.1 competitor-parity caution.** Shop Filters is built despite §4.1's "drop competitor-parity unless a client asks" because it serves a concrete internal need — the suite owner relies on shop filtering today and the current third-party tool renders poorly on this Elementor store. Internal-only, opinionated-by-default (auto-derives filters from product attributes; explicit config is optional).
- **§5.2 Dedicated index table — schema change APPROVED.** A new module-owned table `{prefix}freeman_shop_filter_index` (narrow term/category membership + per-variation in-stock flag) is approved (Hard Rule #6: dbDelta install + downgrade note). It deliberately stores only what WooCommerce's `wc_product_meta_lookup` can't (per-attribute-value in-stock truth + category membership); price/stock/rating are read from `wc_product_meta_lookup`, never duplicated. Dropped on uninstall; inert on a version downgrade.
- **§5.3 Indexing approach.** Event-driven dirty-queue (WC product/stock/term hooks, ~30s debounce) + a ~5–10 min reconciliation sweep, batched. Action Scheduler when available (WooCommerce bundles it) behind a `function_exists` guard, with wp-cron as the zero-config fallback — never a hard dependency. (Existing modules use wp-cron; AS is adopted only for this rolling-reindex workload.)
- **§5.4 Placement = shortcode.** `[freeman_shop_filters]` (drops into an Elementor HTML/shortcode element), per §4.3 Elementor-only. No Elementor widget in v1.
- **§5.5 Transport = admin-AJAX.** Per §4.4, the filter endpoint is `wp_ajax_*` / `wc_ajax`, not REST.
- **§5.6 Filtered-URL SEO.** Filtered URLs (query-string params only — no rewrite rules) get canonical → clean category/shop URL + `noindex,follow`, gated by a default-off flag so an SEO-plugin-governed site can opt out. Pretty/indexable filter URLs are an explicit non-goal.
- **§5.7 Cross-module decoupling.** ShopFilters does NOT call VariationSwatches' `Etucart_VS_Plugin` (loaded only when that module is enabled); it duplicates the two pure helpers it needs and reads swatch term-meta directly — per §4.6's "duplicate, don't extract" leaning.

These are scoped to the new module and change nothing about existing modules or prior decisions.
