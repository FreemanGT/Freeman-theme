# Freeman

Freeman is a two-package WordPress product for WooCommerce stores built on top of Elementor.

## Packages

- **`freeman-theme/`** — child theme of [Hello Elementor](https://wordpress.org/themes/hello-elementor/). Presentation only: design tokens, typography, RTL, Woo template overrides.
- **`freeman-core/`** — single plugin containing all business logic as independently togglable modules (Swatches, Restock, StockFix, Feed, Scroll, Cheapest).

The theme requires the plugin. The plugin works without the theme, so data (subscribers, settings, feed) is never orphaned if the theme is ever changed.

## Modules (inside Freeman Core)

| Short name | Folder | Replaces |
|---|---|---|
| Swatches | `src/Modules/VariationSwatches/` | etucart-variation-swatches |
| Restock | `src/Modules/RestockNotify/` | restock-notify |
| StockFix | `src/Modules/VariableStockFix/` | woo-variable-stock-fix |
| Feed | `src/Modules/ProductFeed/` | wc-product-feed |
| Scroll | `src/Modules/InfiniteScroll/` | bookomers-infinite-scroll |
| Cheapest | `src/Modules/CheapestDefaultVariation/` | auto-default-cheapest-variation.php |

## Getting started (development)

```bash
composer install          # installs PHPCS + WordPress-Coding-Standards
npm install               # installs esbuild + postcss
bash tools/build.sh       # produces freeman-theme.zip + freeman-core.zip in dist/
```

## Interaction protocol

When asking the agent to make changes, use `<scope>: <request>`. Scopes:

- `Theme: …` — only `freeman-theme/`
- `Core: …` — Core infrastructure (Registry, Hub, Security, Dashboard)
- `Swatches: …` / `Restock: …` / `StockFix: …` / `Feed: …` / `Scroll: …` / `Cheapest: …`
- `New module <Name>: …` — scaffold a brand-new module

See each module's `HOOKS.md` for public extension points.

## License

GPL-2.0-or-later.
