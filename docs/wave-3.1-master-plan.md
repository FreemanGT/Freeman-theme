# Wave 3.1 master plan — InfiniteScroll trigger-mode selector + history-API settings + PHP render path + 3 deferred Wave-1.1 hooks

**Date approved**: 2026-05-04 (decisions PR), code PRs TBD
**Owner**: Yiftach
**Roadmap item**: #5 (P1)
**Reflects decisions in**: [/docs/decisions-2026-04-28.md](decisions-2026-04-28.md) — none directly. Wave 3.1 is a P1 functional improvement with no §4.x decision dependency.
**Branch off**: `main` post-1.11.32 (decisions PR cuts a doc-only branch; code PRs cut from main post-decisions-merge)
**Sub-PRs**: 2 — `3.1a → 3.1b`, sharing flag `freeman_core_infinite_scroll_trigger_modes_enabled` (precedent: Wave 3.2a/b)

This document is the source of truth for Wave 3.1's two code sub-PRs. Each sub-PR's pre-flight cites this plan instead of re-litigating cross-cutting concerns (D1–D9 + D-extra, hook signatures, back-compat shim, file-ceiling math).

---

## 1. Context — current factual state of InfiniteScroll module

This section captures what the module *actually is* today, not what its docs claim. Three of these were discovered during the 2026-05-04 scoping pre-flight and shaped the plan below.

### 1.1 Module is JS-only; no PHP render path; no extension hooks in code

[freeman-core/src/Modules/InfiniteScroll/Module.php](../freeman-core/src/Modules/InfiniteScroll/Module.php) (135 lines) does two things only: registers `wp_enqueue_scripts` ([Module.php:83](../freeman-core/src/Modules/InfiniteScroll/Module.php#L83)) and enqueues style + script + a localized `FreemanInfiniteScroll` config object. There is **no `render()`, no shortcode, no widget, no template, no `apply_filters`, no `do_action`**. Verified by `grep -n "apply_filters\|do_action" freeman-core/src/Modules/InfiniteScroll/*.php` returning zero hits.

The 3 deferred Wave-1.1 hooks (`selector`, `before_render`, `after_render`) named in [docs/audit-2026-04-28.md:228-229](audit-2026-04-28.md) and [docs/roadmap.md:186-188](roadmap.md) **exist only in roadmap text, not in code**. Wave 3.1 introduces them.

### 1.2 Existing trigger logic is already a hybrid (under the hood)

[infinite-scroll.js](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js) ships three concurrent trigger paths:

- IntersectionObserver primary on a sentinel below the grid: [infinite-scroll.js:267-274](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L267-L274), `rootMargin: '800px 0px'`.
- Scroll-distance fallback: [infinite-scroll.js:276-301](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L276-L301) with `OPTS.scrollTriggerPx: 900` ([line 99](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L99)).
- iOS-specific `setInterval` poll: [infinite-scroll.js:303-310](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L303-L310), 1500 ms cadence, handles Safari's IO-during-inertia quirks.

This is **internal trigger-detection redundancy** ("triple-stack hybrid"). It is *not* the same concept as the roadmap's named "hybrid" trigger mode (which is a UX pattern: auto-load first N pages, then switch to button). §4-D1 disambiguates these two senses of "hybrid" explicitly.

### 1.3 History API already ships

`window.history.pushState` already fires on every successful page append: [infinite-scroll.js:411-414](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L411-L414). `popstate` listener at [infinite-scroll.js:558](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L558) triggers `resync()` after 50 ms. `pageshow` (bfcache) handler at [infinite-scroll.js:559](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L559).

**The roadmap line "History API integration (push state on each page load)" describes already-shipped behavior, not net-new work.** Wave 3.1 does not *add* History API; it *exposes its existing behavior as a configurable setting* with default = current behavior. See §3 (roadmap amendment) and §4-D5.

### 1.4 HOOKS.md describes 4 extension points that don't exist

[freeman-core/src/Modules/InfiniteScroll/HOOKS.md](../freeman-core/src/Modules/InfiniteScroll/HOOKS.md) (43 lines) and [README.md](../freeman-core/src/Modules/InfiniteScroll/README.md) document a public API that is not in code:

| Documented | HOOKS.md location | In code? |
|---|---|---|
| `freeman_core/infinite_scroll/config` filter | [HOOKS.md:5-22](../freeman-core/src/Modules/InfiniteScroll/HOOKS.md) | **No** |
| `freeman_core/infinite_scroll/should_enable` filter | [HOOKS.md:24-28](../freeman-core/src/Modules/InfiniteScroll/HOOKS.md) | **No** |
| `freeman:infinite-scroll:page-loaded` JS event | [HOOKS.md:32-39](../freeman-core/src/Modules/InfiniteScroll/HOOKS.md) | **No** — `dispatchEvent` / `CustomEvent` grep zero hits |
| `freeman_core/infinite_scroll/config.skeletonMarkup` filter | [HOOKS.md:41-43](../freeman-core/src/Modules/InfiniteScroll/HOOKS.md) | **No** |

This drift is its own decision (§4-D-extra). Cleanup ships in this decisions PR, not in the code PRs.

---

## 2. Pre-flight checks

### Decision dependencies

None from `/docs/decisions-2026-04-28.md`. Wave 3.1 is a P1 functional improvement with no §4.x dependency.

### Wave-0 prerequisites

| Item | Status | Evidence |
|---|---|---|
| 0.0 PHPUnit | ✓ | 306 tests / 893 assertions green post-3.3 |
| 0.1 Logger hooks | ✓ | `freeman_core/logger/entry`, `freeman_core/logger/written` |
| 0.2 Feature_Flags | ✓ | [Feature_Flags.php:27](../freeman-core/src/Core/Feature_Flags.php#L27) |
| 0.3 Settings export/import | ✓ | Tools admin page |
| 0.4 Regression baseline | ✓ | `/tests/baseline-*.txt` |
| 0.5 Snapshot harness | ✓ | `/tests/snapshots/` |

### Hard-rule check (CLAUDE.md §"Hard rules")

| # | Rule | Compliance |
|---|---|---|
| 1 | Feature flag, default `false` | Both 3.1a and 3.1b gated on `freeman_core_infinite_scroll_trigger_modes_enabled` (default false). 3.1b's three new hooks are purely additive (additive exception applies — but flag still gates the render path that fires them). |
| 2 | No removal of existing surfaces | All three current settings (`skeleton_count`, `max_pages`, `end_message`) preserved verbatim. JS triple-stack trigger logic preserved verbatim under flag-OFF and under flag-ON-with-default-mode='auto'. pushState behavior preserved verbatim under flag-OFF and under flag-ON-with-default-history='pushState'. |
| 3 | No `legacy/` edits | InfiniteScroll has no `legacy/` directory. N/A. |
| 4 | One roadmap item per PR | Both sub-PRs are within Roadmap #5. Each declares the split (precedent: 1.1a/b, 2.3a/b/c, 3.2a/b). |
| 5 | No major version bump | Patch bumps only. |
| 6 | No DB schema changes | None. |
| 7 | Logger stays `final` | Untouched. |
| 8 | Use `Freeman\Core\Core\Logger` | New code paths log via Logger only. |
| 9 | Roadmap update in same PR | This master-plan PR updates [/docs/roadmap.md](roadmap.md) with the scope amendment (§3 below). Each code sub-PR ships its own roadmap shipped-marker. Wave 3.1's parent shipped-marker lands with 3.1b (the last sub-PR). |

### File/module ceiling

Project-wide 12 file ceiling applies. No Wave 3.1 calibration request — see §5 for per-sub-PR file count math under the resolved D4 shape. Both sub-PRs project under 12 (~9 each). If any sub-PR's pre-flight reveals it crossing 12, stop and ask per the standing no-self-waiver rule.

---

## 3. Roadmap delta (scope amendment)

The current roadmap entry at [docs/roadmap.md:182-189](roadmap.md) reads:

> **3.1 — InfiniteScroll trigger modes (Roadmap #5) — expanded scope (committed 2026-04-29)**
> - Setting: `auto` / `button` / `hybrid` (auto first 2 pages, button after)
> - Selector override (currently hardcoded to `.products`)
> - History API integration toggle (push state on each page load)
> - Flag: `freeman_core_infinite_scroll_trigger_modes_enabled`
> - **Folds in 3 hooks deferred from Wave 1.1**:
>   - `freeman_core/infinite_scroll/selector` (filter) — replaces the hardcoded `.products` selector. Lands together with the JS-side read so the hook actually controls behavior.
>   - `freeman_core/infinite_scroll/before_render` (action) — fires before the PHP-side render that this wave introduces (the module is JS-only today).
>   - `freeman_core/infinite_scroll/after_render` (action) — fires after.
>   - Each hook gets `@since` matching the version this wave ships in, plus a hook test asserting firing + payload.

Two corrections land in the decisions PR. The first 4 bullets are rewritten; the deferred-hooks bullets (lines 186-189) stay verbatim.

```diff
-**3.1 — InfiniteScroll trigger modes (Roadmap #5) — expanded scope (committed 2026-04-29)**
-- Setting: `auto` / `button` / `hybrid` (auto first 2 pages, button after)
-- Selector override (currently hardcoded to `.products`)
-- History API integration toggle (push state on each page load)
-- Flag: `freeman_core_infinite_scroll_trigger_modes_enabled`
+**3.1 — InfiniteScroll trigger modes (Roadmap #5) — expanded scope (committed 2026-04-29; rescoped 2026-05-04 per `/docs/wave-3.1-master-plan.md`)**
+- Trigger-mode setting: `auto` / `button` / `hybrid`. Concrete semantics in master plan §4-D1; "hybrid" = page-count threshold (UX pattern), distinct from the existing JS triple-stack trigger redundancy (engineering pattern).
+- History API setting: `pushState` / `replaceState` / `disabled`. **Not net-new** — pushState already ships at `infinite-scroll.js:411-414`; this exposes existing behavior as configurable. Default `pushState` preserves current behavior byte-identically.
+- Selector override via the new `selector` filter: replaces (or augments per master plan §4-D6) the 11-selector hardcoded priority list at `infinite-scroll.js:28-40`.
+- Flag: `freeman_core_infinite_scroll_trigger_modes_enabled` (shared by 3.1a + 3.1b — precedent: 3.2a/b). Default off.
```

Plus the `**Last updated**` line at the top of roadmap.md bumps to: `2026-05-04 (Wave 3.1 scope amendment per /docs/wave-3.1-master-plan.md)`.

---

## 4. Decisions of record

### D1 — Trigger-mode enum semantics; two senses of "hybrid" pinned

| Mode | Concrete semantics | Notes |
|---|---|---|
| `auto` | Current as-shipped triple stack: IO + scroll-distance fallback + iOS poll, all running concurrently as today | Preserves existing behavior byte-identically. Default. |
| `button` | No scroll/IO trigger; render a "Load more" button after the grid (PHP-side per D4); click → `loadNext()`. Sentinel still inserted (for `attachObserver` cleanup symmetry) but not observed | Markup precedent: error-path "Load more" button at [infinite-scroll.js:520-531](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L520-L531) |
| `hybrid` | UX pattern: `auto` mode for the first N pages, then `button` mode for the rest. N = setting `hybrid_threshold`, default `2`. Switch happens server-side (PHP renders button starting at page N+1 if `pagesLoaded >= N` is detectable from the DOM) and client-side (JS suppresses sentinel triggers and surfaces button after threshold) | New setting added: `hybrid_threshold` (number, default 2) |

**Two-senses-of-hybrid disambiguation (durable):**

- **"Triple-stack hybrid"** (existing internal): IO + scroll-fallback + iOS poll, all concurrent. This is internal trigger-detection redundancy that runs inside the `auto` mode. Not user-facing.
- **"Page-count hybrid"** (new D1.hybrid mode): auto-load first N pages, then surface a button. User-facing UX choice.

The terms must not be conflated. When this plan or future docs say "hybrid mode" without qualifier, it means page-count hybrid (D1.hybrid). The triple-stack always lives under `auto` and has no user-facing name.

### D2 — Default mode + flag contract

- Default mode setting: `auto`.
- Flag-OFF: trigger-mode setting ignored entirely; current code path runs verbatim. Mode setting only takes effect with flag ON. (Matches 3.2a/b/3.3 precedent.)
- Flag-ON + mode=`auto`: behavior identical to flag-OFF (auto routes through legacy triple-stack code path).

### D3 — Mode selector location

**Global admin setting only.** Per existing precedent at [Module.php:56-77](../freeman-core/src/Modules/InfiniteScroll/Module.php#L56-L77), all InfiniteScroll settings are global. Adding per-instance Elementor controls is out of scope: the module has no `Widget.php` and the JS attaches to whatever grid it finds first per page (page-level scope). Adding a widget surface would be a separate scope expansion.

### D4 — PHP render path shape — gating decision RESOLVED

**Selected: Option (a) — wrapper-only render via `woocommerce_before_shop_loop` / `_after_shop_loop` (and equivalents for block grids).**

| Option | Listener use case | File cost | Hook symmetry |
|---|---|---|---|
| **(a) Wrapper-only** ✓ | Inject custom markup around the grid (header banner, custom skeleton stripe, "Showing X of Y" copy, fallback noscript content) | +1 file (or +0 if inline in Module.php) | Hooks fire in **all** modes |
| (b) Button render only | Replace button text/icon/wrapper | +1 file | Hooks fire **only** in button/hybrid modes — asymmetric |
| (c) Sentinel render | Replace sentinel markup or pre-skeleton content | +1 file | JS-vs-PHP timing/positioning hazard (JS today inserts sentinel after JS-resolved container; PHP can't predict that) |
| (d) Config-as-DOM | Post-modify config DOM | +0 | Largely redundant with a `config` filter (which D-extra is deleting from HOOKS.md, but a future filter could re-introduce it more cleanly) |
| (e) Combination (a)+(b) | Both | +2 files | 4 hooks, exceeds the deferred Wave-1.1 set of 2 actions |

**Recommendation argument:**
1. **Symmetry**: hooks fire across all three modes (auto/button/hybrid), not gated on mode. Listeners get a stable surface.
2. **Minimal scope**: one render path, one before/after pair, matches the deferred Wave-1.1 hook set 1-to-1.
3. **Concrete listener use case**: a theme/site-builder injects a custom loading indicator, custom skeleton container, or "filter results" UI alongside the grid without competing with the JS module's internal sentinel/skeleton.
4. **Lowest file cost** that adds a render path at all (1 file or 0 if inline).
5. **Doesn't conflict with JS**: the JS still resolves the grid via container selectors; the PHP wrapper is an outer envelope JS can ignore.
6. **Compatible with future sub-PR scope**: button-mode rendering can be added inside the wrapper later (3.1b, or a follow-up wave) without invalidating the wrapper hooks.

**Concrete shape:** an inline render method on Module (`render_grid_wrapper_open()` / `_close()`), hooked to `woocommerce_before_shop_loop` priority 5 / `woocommerce_after_shop_loop` priority 999 for the WooCommerce archive context. Block-grid context uses the equivalent block hooks (`woocommerce_before_main_content` / `_after_main_content` or block-template hooks — pinned in 3.1b's pre-flight against actual core block-grid behavior). No new partial file needed; the wrapper is a single `<div>` open/close emit.

### D4 wrapper-render predicate (LOAD-BEARING for hook semantics)

`woocommerce_before_shop_loop` and equivalents fire on every WC archive context — shop, search results, category/tag pages, related-products on PDP, upsells, cross-sells. The InfiniteScroll JS does not engage on most of those (the JS's `EXCLUDE_ANCESTORS` list at [infinite-scroll.js:81-91](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L81-L91) skips `.related`, `.upsells`, `.cross-sells`, sidebar widgets, etc., and the lateMount observer at [infinite-scroll.js:548-555](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L548-L555) gives up after 10s if no eligible grid resolved). If 3.1b's wrapper render attached to `woocommerce_before_shop_loop` unconditionally, before/after_render would fire on contexts where IS never engages — semantically wrong, and a footgun for listeners.

**Pinned: wrapper render and the surrounding `before_render` / `after_render` actions both gate on a private predicate `Module::should_render_wrapper( $context )` that returns true only when:**

1. The flag `freeman_core_infinite_scroll_trigger_modes_enabled` is on (D2 contract).
2. The current page is an IS-engaging context — concretely the union of:
   - `is_shop()`
   - `is_product_taxonomy()` (covers product category / tag / attribute archives)
   - `is_search()` *with* a product-relevant query (`get_query_var( 'post_type' ) === 'product'` or `is_post_type_archive( 'product' )`)
   - A future-proofing filter `freeman_core/infinite_scroll/should_render_wrapper` (pre-fire predicate, default = the above resolution; lets a site force-enable on custom contexts or force-disable on a specific archive).
3. The current page does **not** match WC's PDP-related-products / upsells / cross-sells contexts (mirroring the JS-side `EXCLUDE_ANCESTORS` semantically — though the JS check is DOM-based and the PHP check is template-context-based, so they aren't byte-equivalent; close enough for the contract).

**Hook contract addendum (lands in §4-D7):** `before_render` and `after_render` fire **only on IS-active contexts**, as resolved by `should_render_wrapper`. Listeners can rely on this — they will not fire on PDP related-products or upsells/cross-sells loops. The new pre-fire filter `freeman_core/infinite_scroll/should_render_wrapper` is the supported override for sites that need a different gating policy.

**Implementation note (lands in 3.1b's pre-flight, not here):** the predicate's exclusion check is template-context-based (`is_product()` returns true on a single-product page, where related/upsells render in different hook callbacks than `woocommerce_before_shop_loop`). The wrapper hooks attach to `woocommerce_before_shop_loop` priority 5 and `woocommerce_after_shop_loop` priority 999; on PDPs, those hooks do not fire for upsells/related (those use `woocommerce_after_single_product_summary` and `woocommerce_output_related_products` respectively). So the natural hook attachment is *already* archive-only by Woo convention. The predicate exists to make the contract explicit and to absorb the search/custom-context edge cases.

### D5 — History API scope (3-value enum)

| Value | Behavior | Default? |
|---|---|---|
| `pushState` | `window.history.pushState` fires on every successful page append (current behavior) | ✓ |
| `replaceState` | `window.history.replaceState` fires (no back-button entries created) |  |
| `disabled` | URL is not mutated on page append |  |

**Back-compat contract:**
- Flag-OFF: pushState fires unconditionally as today.
- Flag-ON + default `pushState`: same as flag-OFF.
- Flag-ON + `replaceState`: pushState call swapped for replaceState.
- Flag-ON + `disabled`: URL mutation skipped entirely; `popstate` and `pageshow` listeners stay wired (back-compat for users who have already navigated within the same session).

Setting key: `freeman_core_infinite_scroll_history_mode` (values: `pushState`, `replaceState`, `disabled`; default `pushState`). Lives in 3.1a (pure JS-side change behind the flag).

**popstate / pageshow no-op contract under `history='disabled'`:**

The existing popstate handler at [infinite-scroll.js:558](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L558) and pageshow handler at [infinite-scroll.js:559](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L559) **do not read `event.state`**. The popstate handler calls `resync()` which re-resolves entirely from the live DOM and `location.href` (see [infinite-scroll.js:339-368](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L339-L368)). Under `history='disabled'`, no IS-induced pushState entries exist, so no IS-relevant popstate events fire within-session; browser-level back/forward across distinct pages triggers a real navigation that reloads the page anyway. The handlers are graceful no-ops by virtue of not depending on history state — **no 3.1a guard required**.

### D6 — Selector override resolution

**Filter + setting, with replace semantics, listener can pass string OR array.**

- Setting `freeman_core_infinite_scroll_container_selector` (text field; default empty, meaning "use hardcoded list").
- Filter `freeman_core/infinite_scroll/selector` (the deferred Wave-1.1 hook).
- Resolution order: setting (if non-empty) overrides hardcoded list; filter is final word (overrides setting if listener returns non-empty).
- Filter signature: `apply_filters( 'freeman_core/infinite_scroll/selector', array|string $selectors )`. If listener returns a string, treat as single-element array. Empty array or empty string falls back to hardcoded list (avoids "merchant typo blanks IS site-wide" footgun).
- Replace semantics, not merge — explicit override; merchants/3rd parties who want additive behavior can re-export the hardcoded list themselves (which they can read via the same filter at priority < 10). Documented in HOOKS.md as part of 3.1b.

**Convention pinned**: filter-as-final-word matches Wave 3.3 D2's strategy resolution order (setting → meta → filter). This is now the freeman-core convention for setting+filter pairs unless a future wave argues otherwise. Listeners hooking new freeman-core extension filters can rely on being the last word in the resolution chain, regardless of admin-side configuration.

### D7 — Hook signatures pinned

Now that D4 is `wrapper-only render`:

| Hook | Type | Signature | Fire point |
|---|---|---|---|
| `freeman_core/infinite_scroll/selector` | filter | `apply_filters( 'freeman_core/infinite_scroll/selector', array|string $selectors )` returns array or string | Inside `Module::enqueue()` before `wp_localize_script`; resolved value passed to JS via the localized `selectors.container` key |
| `freeman_core/infinite_scroll/before_render` | action | `do_action( 'freeman_core/infinite_scroll/before_render' )` — **payload-less** | Just before the grid wrapper `<div>` opens, inside the WC/block hook callback |
| `freeman_core/infinite_scroll/after_render` | action | `do_action( 'freeman_core/infinite_scroll/after_render' )` — **payload-less** | Just after the grid wrapper `<div>` closes |
| `freeman_core/infinite_scroll/should_render_wrapper` | filter | `apply_filters( 'freeman_core/infinite_scroll/should_render_wrapper', bool $should, string $context )` returns bool | Pre-fire predicate inside `Module::should_render_wrapper()`; resolved value gates wrapper emit + before/after_render |

The `should_render_wrapper` filter is a gating predicate for the wrapper render itself, introduced here as part of the D4 resolution rather than as an additional Wave-1.1 deferral. It is a peer of `should_apply`-style predicates in other modules, not a peer of `before/after_render`.

**Payload-less rationale**: matches WooCommerce's own `woocommerce_before_shop_loop` / `_after_shop_loop` convention. Listeners that need product context can call `wc_get_loop_prop()`, `is_shop()`, `is_product_taxonomy()`, etc. themselves. Adding a context payload commits to a specific shape that's hard to revise (hook signatures are forever). Empty payload is the conservative default.

**Hook firing contract**: `before_render` and `after_render` fire **only on IS-active contexts** (resolved by `should_render_wrapper`, see §4-D4). They do not fire on PDP related-products, upsells, cross-sells, or sidebar product widgets. Sites that need different gating override via the `freeman_core/infinite_scroll/should_render_wrapper` filter.

`@since` tag: pins to the version 3.1b ships (TBD; written into the docblock during 3.1b execution).

### D8 — Back-compat shim across three substrates

| Substrate | Flag-OFF | Flag-ON + defaults | Flag-ON + non-defaults |
|---|---|---|---|
| **PHP render** | No wrapper hooks attached. Current `enqueue` runs verbatim. before/after_render do not fire. | Wrapper hooks attached. before/after_render fire. Wrapper is a single empty `<div>` envelope — visually invisible. | Same as defaults; mode/selector settings change behavior but wrapper still emits |
| **JS trigger** | Triple-stack runs verbatim. No mode dispatch. | mode='auto' routes to legacy triple-stack code path (private `applyTriggerMode('auto')` calls into existing logic). | mode='button' suppresses IO+scroll+poll, attaches click handler. mode='hybrid' starts triple-stack, switches to button after `hybrid_threshold` pages. |
| **History API** | pushState fires unconditionally as today. | history='pushState': same as flag-OFF. | history='replaceState' or 'disabled': branch in private `applyHistoryMode(url)` wrapper around the existing pushState call site. |

**Shim symbols (named for traceability):**
- JS: `applyTriggerMode(mode)` — private dispatcher inside `loadNext`'s pre-fetch gate.
- JS: `applyHistoryMode(url)` — private wrapper around the existing `window.history.pushState` call at [infinite-scroll.js:411-414](../freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L411-L414).
- PHP: `Module::should_render_wrapper()` — predicate gating the render path + hook fires.
- PHP: `Module::should_render_button()` — predicate for hybrid-threshold detection.
- PHP: `Module::render_grid_wrapper_open()` / `_close()` — the D4 render methods.

### D9 — Coupling confirmed; sub-PR split rationale in §5

before_render/after_render only have meaning if the PHP render path exists (D4). They cannot ship without it. **Wave 3.1 is therefore not splittable along the "render path vs hooks" axis.**

It *is* splittable along the "JS-only changes vs PHP-render changes" axis — see §5 sub-PR breakdown.

### D-extra — HOOKS.md drift cleanup

**Selected: Option (c) — delete the false claims; ships in this decisions PR.**

Reasoning:

- (a) Ignore: leaves a known doc hazard live for another wave cycle. Future readers (and future me) waste cycles confirming whether the documented hooks exist.
- (b) Implement what HOOKS.md claims: ships extension surface nobody asked for. Adds 4 hooks (`config`, `should_enable`, `page-loaded` event, `skeletonMarkup`) to the Wave 3.1 scope, blowing the file ceiling and the wave's intent.
- **(c) Delete + replace with accurate-but-empty stub.** Doc-only fix in a doc-only PR. No code impact. See §7 below for the exact deletion list and replacement state.

---

## 5. Sub-PR breakdown

**META decision: split into 2 sub-PRs sharing the flag.** Precedent: Wave 3.2a/b.

### File-count math under the resolved D4 shape

**Unified 3.1 (single PR):**

| Group | Files |
|---|---|
| Module.php (settings + render hooks + selector filter + version-bump pair) | Module.php + freeman-core.php + Plugin.php = 3 |
| JS file (mode dispatcher + history wrapper) | infinite-scroll.js = 1 |
| Tests | New file = 1 |
| Baselines | baseline-hooks.txt + baseline-options-declared.txt = 2 |
| Boilerplate | freeman-core/CHANGELOG.md + root CHANGELOG.md + docs/roadmap.md + CLAUDE.md = 4 |
| **Total** | **11** |

11 fits under 12 — barely. But 11 is fragile: any unexpected mid-flight surface (a snapshot fixture, a CSS tweak for the new button, a separate test file for JS-side coverage, a Plugin.php bootstrap registration if the new render path needs early hookup) crosses the ceiling and forces a stop-and-ask anyway.

**Split into 3.1a + 3.1b:**

- **3.1a (JS-only + settings)**: trigger-mode setting, history-mode setting, hybrid-threshold setting. JS dispatcher + history wrapper. Module.php settings_schema only (no PHP render path yet). No new hooks shipped.
  - Files: Module.php + freeman-core.php + Plugin.php + infinite-scroll.js + freeman-core/CHANGELOG.md + root CHANGELOG.md + docs/roadmap.md + CLAUDE.md + baseline-options-declared.txt + `tests/InfiniteScrollSettingsTest.php` (new) = **10 files**.
  - Flag introduced here.
  - Flag-OFF + flag-ON-with-defaults both byte-identical to today.

- **3.1b (PHP render path + 3 deferred hooks)**: wrapper-only render, selector filter, before/after_render actions, container_selector setting, the JS-side selector read. New tests for the 3 hooks + `should_render_wrapper` predicate.
  - Files: Module.php + freeman-core.php + Plugin.php + infinite-scroll.js (selector read only; no further trigger logic) + freeman-core/CHANGELOG.md + root CHANGELOG.md + docs/roadmap.md + CLAUDE.md + baseline-hooks.txt + baseline-options-declared.txt + `tests/InfiniteScrollHooksTest.php` (new) = **11 files**.
  - Flag reused (not redefined) per 3.2b precedent.

Both sub-PRs comfortably under 12. No ceiling pressure. Sequential ship: 3.1a → 3.1b. Wave 3.1 parent shipped-marker lands with 3.1b.

### Tests file pinning (resolved here, not in code-PR pre-flight)

- **3.1a**: new file `tests/InfiniteScrollSettingsTest.php`. Covers: trigger-mode setting registers with correct option key + default; history-mode setting same; hybrid_threshold setting same; `Feature_Flags::is_enabled('infinite_scroll', 'trigger_modes')` reads the right option. ~6 tests, ~6 assertions. Exact gate seals in 3.1a's pre-flight Stage 2.
- **3.1b**: new file `tests/InfiniteScrollHooksTest.php`, separate from 3.1a's settings tests. Concerns differ enough that combining hurts: 3.1a tests cover settings declaration (option-key generation, default values, schema shape). 3.1b tests cover the new PHP render path firing under `should_render_wrapper`, the 3 deferred hooks firing with the right payload (or no payload), the new `should_render_wrapper` predicate, and selector filter resolution. Wave 3.3's "extend existing test file" precedent applied because the new tests exercised the same module's filter surface as the existing ones; here, 3.1a's settings-shape tests and 3.1b's render+hook tests are different surfaces. 3.1b's projected file count (11) accommodates a separate test file under the 12-ceiling.

### Plugin.php scope (both sub-PRs)

**Plugin.php scope (3.1a + 3.1b)**: version-bump edit only (release.sh's `bump_core` step). No bootstrap change, no module-registration change, no `maybe_upgrade()` change. The wrapper-render hooks attach from inside `Module::boot()`, which runs during the existing module-registration pass — no earlier hook point needed. Reviewers should not expect Plugin.php diffs beyond the `VERSION` const bump.

### Why split rather than push for unified

1. **Ceiling buffer.** Unified at 11/12 invites a mid-wave stop-and-ask that the split avoids upfront.
2. **Independent rollback.** 3.1a's JS changes can be reverted without disturbing the new render path.
3. **3.1a is genuinely simpler** — pure JS dispatch + 3 new settings, no new PHP hooks, no baseline-hooks.txt change. Reviewer cycles are lower.
4. **D9 axis check.** The split is "JS+settings" vs "PHP render+hooks" — the only natural split that preserves D9's coupling (3 hooks + render together).
5. **Ship-order risk.** 3.1a is lower-risk to ship first (no public hook surface introduced); 3.1b introduces hooks last, after the trigger-mode behavior is proven.

---

## 6. Feature-flag table

| Flag | Sub-PR | Default | What it gates |
|---|---|---|---|
| `freeman_core_infinite_scroll_trigger_modes_enabled` | 3.1a (introduced), 3.1b (reused) | `false` | Both: trigger-mode + history-mode + hybrid-threshold settings (3.1a). Wrapper render + selector filter + before/after_render actions + should_render_wrapper predicate + container_selector setting (3.1b). |

One flag, two sub-PRs, precedent Wave 3.2a/b.

---

## 7. HOOKS.md cleanup — exact deletions + replacement state

[freeman-core/src/Modules/InfiniteScroll/HOOKS.md](../freeman-core/src/Modules/InfiniteScroll/HOOKS.md) replaced with a 60-word accurate-but-empty stub plus a forward-pointer to this master plan. The replacement file body:

```markdown
# Infinite Scroll — Public API

The module currently exposes no PHP or JS extension hooks. Extension hooks for selector override (`freeman_core/infinite_scroll/selector`) and render-bracket actions (`freeman_core/infinite_scroll/before_render`, `freeman_core/infinite_scroll/after_render`) are planned for Wave 3.1b — see [/docs/wave-3.1-master-plan.md](../../../../docs/wave-3.1-master-plan.md) §4-D7 for the resolved signatures. Documentation will land in this file when the hooks ship.

## Template overrides

Skeleton card markup is inline in `assets/js/infinite-scroll.js` (`makeSkeletonCard()`). No filter override exists today.
```

**Lines deleted from current HOOKS.md:**

| HOOKS.md range | Content | Why deleted |
|---|---|---|
| L1-22 | Title, "Filters" heading, full `freeman_core/infinite_scroll/config` filter doc | Filter does not exist in code |
| L24-28 | `freeman_core/infinite_scroll/should_enable` filter doc | Does not exist |
| L30-39 | "Actions" heading + `freeman:infinite-scroll:page-loaded` JS event doc | Event not dispatched anywhere; no `dispatchEvent` call |
| L41-43 | "Template overrides" section claiming `skeletonMarkup` config-filter override | `skeletonMarkup` filter does not exist |

**Replacement state recommendation: accurate-but-empty with forward-pointer.** Reasoning:

- **Forward-looking documentation of the 3.1b hooks (with placeholder `@since`)** would re-create the same drift hazard — documenting hooks before they exist.
- **Pure deletion to empty file** drops the "Template overrides" stub, which despite being inaccurate today (no filter exists) does communicate a real fact about where skeleton markup lives.
- **Accurate-but-empty + pointer to master plan** signals "this module is being actively extended; here's the source of truth for what's coming" without claiming surface exists yet. The same template-overrides paragraph is preserved with the false filter claim removed.

[README.md](../freeman-core/src/Modules/InfiniteScroll/README.md) **not in the decisions-PR file list** — verified L36-38 contains only a pointer to HOOKS.md, no hook names. Once HOOKS.md is accurate, the README's pointer is correct. No README edits needed.

---

## 8. What this plan does NOT cover

- **Per-instance Elementor controls** for trigger mode. Module has no Widget.php; adding one is a separate scope expansion. Open question for a future wave if a client needs per-widget mode.
- **Lazy-loading images** beyond first viewport. The original Roadmap #5 line included this; verified during scoping pre-flight that WP core's auto-lazy (since WP 5.5) already covers the `<img>` case. CSS-background lazy-loading would require IntersectionObserver-on-cards work that is genuinely net-new and out of scope for 3.1. Same posture as 3.2's roadmap-line acknowledgement.
- **Settings UI improvements** (section grouping, conditional hide of `hybrid_threshold` when mode!=`hybrid`). Settings_Hub does not currently support conditional fields. Out of scope; if needed later, falls into a Settings_Hub enhancement wave.
- **Snapshot tests of rendered grid wrapper.** D4 ships a single `<div>` wrapper; snapshotting the empty wrapper has near-zero value. Hook-firing tests in PHPUnit cover the relevant assertions. Reconsider if D4 ever expands to (b)/(c)/(e) options.
- **HOOKS.md re-population in 3.1b**. The hook signatures land in code; HOOKS.md is updated in 3.1b (not in this decisions PR) to document the actual shipped surface.
