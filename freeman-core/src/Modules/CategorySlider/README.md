# Category Slider

Editorial Elementor widget for WooCommerce product categories — drag-scroll horizontal slider with momentum, hover ring, and a progress bar / count indicator.

Ported from the Claude Design "Category Slider" handoff bundle in `category-slider/`.

## What it does

- Registers an Elementor widget `freeman_category_slider` (under the **WooCommerce** widget panel) that renders `product_cat` terms as a draggable card slider.
- Real category thumbnails are used when available (via WC's `thumbnail_id` term meta); when missing, a deterministic striped placeholder is generated (hue derived from the term slug) so design-stage previews still look intentional.
- Free-scroll drag with momentum decay, optional per-card or per-page snap.
- Per-instance arrow buttons, progress bar, and "current/total" label — all toggle-able from the Elementor controls.
- Per-breakpoint cards-per-view (desktop / tablet / mobile).
- **Full RTL support** — set Direction to *Auto* to follow `is_rtl()`, or *Force RTL/LTR* to override per-instance. RTL flips arrow icons + button order, drag/momentum direction, and progress-bar fill direction.

## Query controls

The widget exposes the standard term-query knobs:

| Control            | Behavior |
| ------------------ | -------- |
| Max categories     | Hard limit on returned terms |
| Order by / Order   | `name`, `count`, `slug`, `menu_order` (asc/desc) |
| Hide empty         | Skip terms with no products |
| Top-level only     | Restrict to root terms (ignored when *Child of* or *Include only* is set) |
| Child of           | Show only sub-categories of this parent |
| Include only these | Multi-select — when set, **only** these terms are shown (overrides hierarchical filters) |
| Exclude these      | Multi-select — always applied |

## Defaults (from the design)

| Knob          | Default   |
| ------------- | --------- |
| `per_view`    | 5         |
| `gap`         | 20 px     |
| `card_height` | 280 px    |
| `shape`       | `soft`    |
| `show_count`  | `hover`   |
| `show_arrows` | on        |
| `snap`        | `none`    |

Tweak any of these in the Elementor editor — every control maps 1:1 to a Tweak from the design's panel.

## Settings

No global settings; everything lives on the widget instance.

## Dependencies

- WooCommerce (for `product_cat`, term thumbnails, term URLs)
- Elementor (widget host)

## Public hooks

See [`HOOKS.md`](HOOKS.md).
