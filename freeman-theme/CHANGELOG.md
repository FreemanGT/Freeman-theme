# Freeman Theme — Changelog

## [1.11.24] — 2026-05-12

- freeman-theme: defensive grid-template-columns floor (minmax(0,1fr)) on Elementor archive product grid so a wide grid item cannot inflate one column and push cards off-page

## [1.11.23] — 2026-05-11

- Typography: `--fm-font-body` and `--fm-font-display` now resolve from Elementor's global typography (`var(--e-global-typography-sk_type_12-font-family, …)` / `…sk_type_2…`, which the Style Kits for Elementor addon writes), with the previous hardcoded `'Heebo'` / `'Assistant'` stacks as fallback. The theme no longer overrides Style Kits' fonts; `--fm-font-mono` (used for `<code>` / `<pre>`) is unchanged. Since `freeman.css`'s `body` / heading rules read those tokens — and `freeman-core`'s My Account reads `--fm-font-body` — they all follow automatically.
- Bumped `FREEMAN_THEME_VERSION` 1.0.3 → 1.11.23 so it matches `style.css` and the theme's CSS asset URLs cache-bust on this change (`wp_enqueue_style` uses that constant, not `style.css`'s `Version:`, which `tools/release.sh` bumps separately).

## [1.11.22] — 2026-05-03

- Drop PHP 7.4 from CI matrix and bump min PHP to 8.0 (freeman-core + freeman-theme headers, composer.json require, .github/workflows/ci.yml). Aligns CI to reality after Wave 2.3a-c baked PHP 8.0+ idioms (str_starts_with, str_contains) into shipped code; PHP 7.4 PHPUnit lane was de-facto failing.

## [1.10.8] — 2026-04-27

- MyAccount mobile nav: add min-width:0 to grid items + ul to fix horizontal-scroll trap (the pill row's flex-nowrap min-content was wider than the phone, growing the grid track and making the whole page scroll sideways instead of just the nav). Theme: add overflow-x:clip on html/body as a defensive guard against any future rogue element creating page-level horizontal scroll.

## [1.0.3] — 2026-04-27

- Hide the WooCommerce-injected `a.added_to_cart` "View cart" link that appears next to the add-to-cart button on shop / archive cards after a successful AJAX add. The cart is reachable from the header utilities, so the inline link is duplicate noise.

## [1.0.2] — 2026-04-27

- Removed the global `.freeman-theme a:hover { opacity: 0.75 }` rule entirely. The 1.0.1 attempt to scope it via `:has(img|...)` was in the built CSS but the dim was still showing in QA — most likely a browser cache or downstream selector winning. Killing the rule outright eliminates the failure mode. Components that genuinely want a text-link dim can opt in via the new `.fm-link--dim` class.

## [1.0.1] — 2026-04-26

- Fix: hovering an image-wrapping anchor (product card, category card, gallery thumb, slider card, etc.) no longer dims the image to 75% opacity — the global `.freeman-theme a:hover { opacity: 0.75 }` rule was matching every anchor, which read as a grey wash over the image. Now scoped via `:has(img|picture|svg|video|.cs-imgwrap|.attachment-woocommerce_thumbnail|[class*="image"])` so only text-link hovers dim. `:has()` is supported in all major browsers from late 2023 — no fallback needed for modern audiences.

## [1.0.0] — 2026-04-22

- Initial release as a child theme of Hello Elementor 3.4.x.
- Design tokens, typography, RTL stylesheet.
- Plugin-dependency bootstrap for Freeman Core.
- WooCommerce theme support + gallery features.
- HPOS + Cart/Checkout Blocks compatibility declared.
