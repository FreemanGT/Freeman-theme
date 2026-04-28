# Category Slider — Changelog

## 1.0.9
- **Touch on phones now scrolls.** The track was unresponsive to swipe gestures because `touch-action: pan-y` blocked native horizontal pan while the JS pointer-drag waited for its three gates (10px / 80ms / horizontal-dominance) to commit — an early flick fell into the dead zone with neither the browser nor JS applying scroll. The JS now skips its drag handler for non-mouse pointer types (`if (e.pointerType !== 'mouse') return;`) and the track is `touch-action: pan-x pan-y`, so horizontal swipes scroll natively (with OS momentum) and vertical swipes scroll the page. The progress bar still tracks the position because it listens to the track's `scroll` event, which fires for native scrolls too. Desktop-mouse drag, the progress scrubber, click-suppression on confirmed drag, and arrows are unchanged.

## 1.0.8
- Runtime is now shared with the new **ProductSlider** widget — both render the same `.cs[data-cs-snap]` skeleton, so a single drag / momentum / progress engine drives both. Two surgical edits to `category-slider.js`:
  - Card counter switched from `[data-cat="1"]` (category-only attribute) to `.cs-card` (markup-agnostic), so the "current / total" footer label counts product cards correctly when the same JS runs against the product widget.
  - The Elementor `frontend/element_ready` action now subscribes both `freeman_category_slider.default` and `freeman_product_slider.default`, so the editor preview re-init fires for either widget. CategorySlider rendering is unchanged.
- Asset registration in `Module.php` is now idempotent (`wp_script_is` / `wp_style_is` guards) so the new ProductSlider module can defensively register the same shared handles regardless of load order or whether CategorySlider itself is enabled.

## 1.0.7
- Drag now works when starting on a card's image, not just the gaps between cards. The browser was firing native HTML5 drag (the "drag this link to bookmarks" gesture on the `<a class="cs-card">` anchor) on mousedown, swallowing our Pointer Events. Added a delegated `dragstart` listener on `.cs-track` that calls `preventDefault()` on every native drag attempt inside the slider; reinforced with `-webkit-user-drag: none / user-drag: none` on `.cs-card` and `.cs-card img / .cs-card .cs-img`. Cards remain clickable, click navigation unchanged, multi-gate drag detector unchanged.
- Defensive `pointer-events: none` on `.cs-card .cs-img` (the inner image div) so future markup that uses `<img>` tags doesn't intercept clicks meant for the parent anchor.

## 1.0.6
- **Mouse drag on cards is now ON by default.** Replaced the separate mouse + touch listeners with a unified Pointer Events implementation. Drag engages only when three gates all pass on the same `pointermove`:
  - Distance > 10px from press point
  - Elapsed > 80ms since pointerdown
  - `|dx| > |dy| × 1.2` (horizontal-dominant)
  Sub-threshold presses always pass through to the anchor's click. Vertical swipes never engage drag — `touch-action: pan-y` on `.cs-track` lets the browser claim vertical for native page scroll, so touch users keep their natural page scrolling on top of cards. `setPointerCapture` keeps a confirmed drag going even when the cursor leaves the slider's bounds.
- Cursor: `.cs-track` now defaults to `cursor: grab`; `.cs[data-cs-mouse-drag="0"] .cs-track` (admin opt-out) gets `cursor: default` + `touch-action: auto`.
- Click suppression: `onClickCapture` reads a one-shot `_lastDragged` flag set on `pointerend`, so a single confirmed drag suppresses exactly one trailing click and the next gesture starts clean.
- Re-entrancy guard: `state.pointerId` tracked; secondary pointers (e.g. pinch-zoom second finger) ignored until the active pointer ends or cancels.
- `pointercancel` resets all state and removes `cs-dragging` so cards become clickable again immediately if the browser claims the gesture for back/forward swipe etc.
- Back-compat: `data-cs-mouse-drag` attribute name unchanged. Saved widget instances with the toggle untouched pick up the new default; instances explicitly disabled stay disabled.

## 1.0.5
- **Progress bar is now a horizontal scrubber.** On hover it grows (track 1px → 3px, thumb 3px → 5px) and the cursor turns to `grab`; mousedown anywhere on the bar jumps the track to that position; mousedown + drag moves the thumb continuously, like a native scrollbar. RTL converts visual ratio to the correct scrollLeft sign. Cards remain click-only — this is the desktop "middle ground" between draggable cards and pure click navigation.
- The progress hit area is expanded by 10px above and below via a transparent `::before` so the bar is grabbable without pixel-perfect aim while keeping the resting visual minimal.

## 1.0.4
- **Mouse drag is now opt-in.** Default is OFF — desktop mouse interaction is pure click, no drag-scroll capture. Touch drag is always on (mobile/tablet users have no other practical way to scroll a horizontal track). Switch lives at Behavior → Enable mouse drag. Threshold widened to 24px for the opt-in case. The grab cursor only appears when mouse drag is enabled.
- Drop-through fix: when mouse drag is OFF, the click-capture handler is no longer attached at all — eliminates the entire class of "click was suppressed because dragged was true" bugs.

## 1.0.3
- Drag threshold raised 8px → 16px so mouse drag stops fighting with click intent. 16px ≈ a finger-pad width — well outside any accidental jitter range, so a click reliably navigates even on jumpy trackpads, while an intentional drag still scrolls the track. Touch drag unaffected.
- Added `--cs-ring-color` CSS variable + Elementor color control "Hover ring color" so the hover outline can be themed independently of the ink color. Falls back to `--cs-ink` when unset, so existing instances render identically.

## 1.0.2
- **Progress bar reaches the end.** Previously the bar's `translateX(N%)` was N% of the *bar's own* width, while the travel distance was computed in % of the *parent*. The bar therefore under-shot the right edge whenever its width fraction differed from the visible-content fraction. Switched to pixel-based `translate3d(Npx, 0, 0)` computed from `progress.clientWidth - bar.width`, so at ratio=1 the bar lands exactly at the parent's far edge.
- **Cards are reliably clickable.** Drag threshold raised from 4px → 8px; the track only updates `scrollLeft` once a confirmed drag passes the threshold; `cs-dragging` is no longer added on every `mousedown` (was disabling `pointer-events` on cards mid-tap and stealing the navigation click).
- **URL fallback hardened.** `get_term_link()` returning false/empty no longer renders `href=""` (which reloaded the current page). New `safe_term_url()` helper falls back to `?product_cat=<slug>` on the home URL when permalinks aren't configured.
- **Editor-mode access guarded.** `\Elementor\Plugin::$instance->editor` is now null-checked before calling `is_edit_mode()` so very-early renders don't fatal.
- **Anchor focus visible.** `.cs-card:focus-visible` gets a 2px ink outline with 4px offset so keyboard users can tell where the focus is.

## 1.0.1
- Fonts now inherit from theme/Elementor (forced Fraunces/Inter removed).
- Typography group controls added for eyebrow, headline, and card name; plus card-name color.
- RTL drag direction fixed — `scrollLeft = startScroll - dx` is direction-agnostic in browsers that normalize scrollLeft, so the previously-added RTL sign flip was removed. Arrow direction sign flip kept (semantic Previous/Next).
- Hover ring no longer clipped: track has vertical padding + matching negative margin so the ring's `inset: -10px` bleed is visible.
- Image rounded corners fully cover the wrapper — `.cs-imgwrap` background made transparent.
- Arrow buttons hardened against Elementor + theme button-style cascade: `appearance: none`, locked min/max dimensions, font/box-shadow reset, higher-specificity selector (`.cs .cs-arrow`).

## 1.0.0
- Initial implementation. Ported from Claude Design "Category Slider" handoff bundle.
- Registers `freeman_category_slider` Elementor widget under the WooCommerce panel.
- Per-breakpoint `cards per view`, gap, card height, shape (circle/soft/rect/pill), snap (none/card/page), arrows, progress bar, count visibility, accent color.
- Real `product_cat` thumbnails with deterministic striped placeholders for terms without one.
- Term-query controls: include / exclude (SELECT2 multiple), child-of, top-level only, order, orderby, hide-empty, limit.
- Full RTL support — Direction control (Auto / Force LTR / Force RTL) flips arrow visuals + order, drag/momentum direction, and progress-bar fill direction. Auto follows `is_rtl()`.
