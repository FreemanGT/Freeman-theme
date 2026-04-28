# Infinite Scroll

Infinite-scroll product grids (shop, Elementor widgets, block grids) with skeleton placeholders and preserved `/page/N/` URLs for SEO.

## What it does

- Finds the product grid via a prioritized selector list (`.products`, `.elementor-products`, `.woocommerce ul.products`, WP block-grids, etc.).
- Watches an IntersectionObserver sentinel below the grid; when it enters the viewport, fetches the next page URL with `X-Requested-With: XMLHttpRequest` and appends new items.
- **Skeleton cards** rendered during each fetch so the layout doesn't jump.
- **Dedup** — seen product IDs are tracked; duplicates skipped.
- **History API** — `history.pushState` so `?paged=N` stays in the URL and deep-linking/back navigation work.
- **Max pages** — hard cap so accidental infinite loops never kill the browser tab.
- **iOS scroll fallback** — Safari's IntersectionObserver has timing quirks under inertia; the module adds a scroll-position poll to catch the sentinel during decelerate scroll.

## Accessibility (1.2.0)

- `aria-live="polite"` status region announces "Loaded %d more products" on every successful page append.
- Error state renders a visible "Load more" button that re-triggers the fetch — usable with keyboard only.
- `MutationObserver`-based late-mount detection for Elementor widgets, replacing the old 300/1200/3000/6000 ms polling.

## Settings

Freeman → Infinite Scroll:
- **Skeleton cards** — placeholder count during loading (default 6)
- **Max pages** — absolute safety limit (default 50)
- **End-of-list message** — text when there are no more products (default: "You have reached the end.")

## Dependencies

- None beyond a product grid in the DOM.

## Legacy import

Detects `bookomers-infinite-scroll/bookomers-infinite-scroll.php`. Legacy plugin had no settings — import is a no-op.

## Public hooks

See [`HOOKS.md`](HOOKS.md).
