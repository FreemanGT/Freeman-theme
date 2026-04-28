# Freeman Changelog

This is the aggregated changelog across both packages. See each package's own `CHANGELOG.md` for package-scoped history.

## [1.10.12] — 2026-04-27

- Variation +N badge: add .etucart-shop-pick__more[hidden] display:none !important rule to fix the root cause that made the badge stay visible whenever JS tried to hide it. The class rule .etucart-shop-pick__more had display:inline-flex !important which silently overrode the browser user-agent [hidden] display:none, so moreBtn.hidden=true from refreshOverflow was a no-op. Result: badge visible with zero chips marked .is-overflow so clicking did nothing. Mirrors the existing .etucart-shop-pick__opt[hidden] rule that already worked for chips.

## [1.10.11] — 2026-04-27

- Variation +N badge: hide out-of-stock and unavailable chips entirely on shop archive instead of greying them out, so the visible row matches the JS in-stock filter (the badge now counts only what the customer can actually pick, and is hidden completely when no in-stock chip is hidden). PHP-level OPT_SHOP_HIDE_OOS handles attribute-value-level pruning (default ON); this CSS handles the cross-attribute case where a chip becomes OOS only after the customer picks another swatch and refreshAvailability marks it.

## [1.10.10] — 2026-04-27

- MyAccount: align Addresses and View Order address columns under one shared grid rule, unify card chrome across both pages so widths and gutters match identically. Tokenize raw font-weight, line-height, letter-spacing, and font-size values: every weight goes through fma-weight-medium/semi, every line-height through fma-leading-snug/base/tight, every letter-spacing through fma-tracking-tight/wide, hero size through fma-text-xxl. Mobile breakpoint already covered both selectors so no behavior change there, just visual lockstep.

## [1.10.9] — 2026-04-27

- Variation +N badge: filter to in-stock chips only when measuring overflow, marking chips as overflow, and computing the count. Out-of-stock and unavailable chips (set by refreshAvailability via .is-out-of-stock and .is-unavailable classes) are excluded so the badge never points to a sold-out variant. If zero in-stock chips are hidden, the badge stays hidden completely. Belt-and-braces fallback: hiddenCount === 0 forces the badge hidden even under pathological measurement.

## [1.10.8] — 2026-04-27

- MyAccount mobile nav: add min-width:0 to grid items + ul to fix horizontal-scroll trap (the pill row's flex-nowrap min-content was wider than the phone, growing the grid track and making the whole page scroll sideways instead of just the nav). Theme: add overflow-x:clip on html/body as a defensive guard against any future rogue element creating page-level horizontal scroll.

## [1.10.7] — 2026-04-27

- Slider: drop scroll-snap-align from middle cards (only first/last snap) and add overscroll-behavior-x contain to fully eliminate overscroll past the last product. Variation +N: add explicit display rule for revealed overflow chips with high specificity so wrap-below works even when an unrelated rule with lower specificity declared display: inline-flex.

## [1.10.6] — 2026-04-27

- Fix shop-swatches +N badge always-visible bug, expand overflow chips below row instead of inline, match +N pill height to size pills (32px), clamp slider drag to scroll bounds and switch snap to proximity to stop overscroll past last product, anchor WooSQ quick-view button to image bottom on mobile, suppress duplicate prices in WooSQ quick-view modal, polish My Account RTL with logical properties and tighter sidebar spacing

## [1.10.5] — 2026-04-27

- Improve My Account spacing, theme typography, and mobile layout

## [1.10.4] — 2026-04-27

- Fix My Account billing and shipping address card alignment

## [1.10.3] — 2026-04-27

- Fix product slider end behavior, swatch overflow cap, and card height alignment

## [1.10.2] — 2026-04-27

- My Account: hide WC clearfix pseudos that were turning grid items into 4 rows so nav and content stack; override WC's float+30%/68% widths on the nav and content; force border-box. Product Slider: bump card-sizing specificity above WC columns-N width rules so flex-basis wins (was scrolling past the last product because cards picked up WC percentage widths); restore vertical track padding so the hover ring isn't clipped by overflow-y; add overscroll-behavior-inline contain.

## [1.10.1] — 2026-04-27

- Fixed Product Slider showing all products at once - WooCommerce default ul.products grid layout was beating the slider track flex layout on specificity. Fixed My Account page RTL layout - sidebar border, content padding, active accent bar, form row gutter, and notice accents now flip correctly when site is RTL.

## [1.10.0] — 2026-04-27

- Added My Account module - editorial restyle of the classic shortcode WooCommerce my-account page (sidebar nav, serif titles, mono eyebrows, hairline tables, pill status chips). Pure CSS layer, no markup or endpoint changes.

## [1.10.0] — 2026-04-27

- Added My Account module - editorial restyle of the classic shortcode WooCommerce my-account page (sidebar nav, serif titles, mono eyebrows, hairline tables, pill status chips). Pure CSS layer, no markup or endpoint changes.

## [1.9.9] — 2026-04-27

- ProductSlider: scope every product-card style under .cs.cs-products so each rule lands at 4+ classes specificity and consistently beats WooCommerce default stylesheet (woocommerce.css) which was forcing float-based 22.05% widths, height:auto images, centered text, and a giant green sale-flash circle into the slider since 1.9.8 added the .woocommerce wrapper class for plugin-CSS compatibility

## [1.9.8] — 2026-04-27

- ProductSlider: add `woocommerce` class on the slider's outer wrapper so plugin CSS scoped to `.woocommerce ul.products li.product` (e.g. WPC Quick View's `.quickvieww` overlay) matches inside the slider — Elementor's default WC Products widget wraps in `.woocommerce` for the same reason

## [1.9.7] — 2026-04-27

- ProductSlider: gate hover scale + ring on @media (hover: hover) so mobile taps no longer trigger a hover-scale jump that looked like an image swap; add max-width/min-width container clamps on the VariationSwatches archive picker inside slider cards and dispatch a window resize after slider layout commits so the picker's +N overflow scanner re-measures against the final card width

## [1.9.6] — 2026-04-27

- ProductSlider: refactor to emit standard WooCommerce shop-loop markup via wc_get_template_part('content','product') so wishlist / quick-view / sale-flash / image-swap plugins light up the slider with no extra wiring; new Manual / Current-query / Related sources + Hide-free + Hide-out-of-stock toggles for parity with Elementor Pro WC Products

## [1.9.5] — 2026-04-27

- i18n: regenerated freeman-core.pot from current source (395 strings); merged he_IL.po preserves existing translations; new en_US.po ships English translations for all 115 Hebrew strings wrapped in 1.9.4 — non-Hebrew sites now display English by default

## [1.9.4] — 2026-04-27

- Restock Notify i18n: wrapped all remaining hardcoded Hebrew strings in __() / esc_html_e() across module defaults, admin pages, email templates, frontend form template, and frontend JS (via wp_localize_script i18n payload) (H-03)

## [1.9.3] — 2026-04-27

- ProductFeed: cache uncompressed XML byte count in a sidecar size file at generation time and emit Content-Length on the decompressed-streaming branch (M-02)

## [1.9.2] — 2026-04-27

- renamed slider/infinite-scroll asset handles to canonical freeman-core-* prefix per audit (N-07); deprecated names registered as no-source aliases that resolve via dependency on the canonical handle, removed in 2.0.0

## [1.9.1] — 2026-04-27

- security: Restock Notify unsubscribe tokens now use random_bytes (H-01); honeypot on Variation Swatches shop AJAX (L-01); slider drag-scroll releases pointer capture on window blur (L-02); doc-only clarification on Cheapest Default Variation sale-price handling (L-03)

## [1.9.0] — 2026-04-27

- renamed ProductFeed/VariableStockFix hooks and VariationSwatches filter to canonical freeman_core_* prefix per audit (N-02/N-03/N-04); added one-release deprecation shims, version-gated migrations (option copy, cron reschedule, rewrite flush); fixed plugin description to list the actual 8 modules (N-05); release.sh stamping Plugin::VERSION resolves N-01

## [1.7.15] — 2026-04-27

- CategorySlider: drag now works on the image area of a card too — was being swallowed by the browser's native HTML5 drag on `<a>` elements ("drag this link to bookmarks" gesture). Delegated `dragstart` preventDefault + `user-drag: none` CSS suppresses it.

## [1.7.14] — 2026-04-27

- CategorySlider: mouse drag on cards is now ON by default with a Pointer Events implementation that uses a three-gate detector (distance + time + horizontal-dominance) so clicks and drags never fight. Touch keeps native vertical page-scroll thanks to `touch-action: pan-y`. Admin toggle remains for opt-out.

## [1.7.13] — 2026-04-27

- VariationSwatches: archive picker's transient cache key now includes WC's currency position + separators + decimals — changing **WC → Settings → General → Currency Position** previously didn't update archive cards because the cached HTML key only tracked currency code. Cache now invalidates correctly on any format change.

## [1.7.12] — 2026-04-27

- VariationSwatches: removed the 1.7.11 RTL currency override. Currency position now follows WC's own Settings → General → Currency position for every render path; both the "starting from" line and the picked-variation price use the same `wc_price()` rendering, so the position you set in WC is honoured everywhere — archive cards, PDP, header cart, etc.

## [1.7.11] — 2026-04-27 — REVERTED in 1.7.12

- VariationSwatches: site-wide filter forces currency-on-right (`149.90 ₪`) on RTL sites — fixes inconsistency where translation/multi-currency plugins were leaving one state at "₪ 319.90" and another at "549.90 ₪". LTR shops unaffected.

## [1.7.10] — 2026-04-27

- VariationSwatches: archive price 13px → 18px; currency-symbol glyph rendering identically between selected/unselected states (was switching font-family); every previously-hardcoded `dir="rtl"` in legacy templates now follows `is_rtl()` so the price and pickers render correctly on both RTL and LTR sites.

## [1.7.9] — 2026-04-27

- VariationSwatches: PDP price size locked to ~24px (was rendering huge); archive size mismatch between selected/unselected states fixed via universal descendant selector. New "Hide selected-option text" setting (default ON) collapses the "Choose an option" row above the swatches.

## [1.7.8] — 2026-04-27

- VariationSwatches: PDP price stays right-aligned + bold; archive price centred; both lock value typography so picked/unpicked states render at identical size. New "Hide attribute labels" setting (default ON) suppresses the "Size:" / "Colour:" row on shop / archive cards.

## [1.7.7] — 2026-04-27

- VariationSwatches PDP price line centres correctly + renders at the same size whether a variation is picked or not (theme rules targeting `.price` sub-spans were sizing the picked vs unpicked states differently; locked all WC inner spans to inherit). Currency stays dynamic via `wc_price()`.

## [1.7.6] — 2026-04-27

- VariationSwatches: archive picker auto-selects when there's only one purchasable variation (no real choice → no friction). Single picker-driven price line replaces WC's "₪20 – ₪100" range on both archive and PDP — defaults to "החל מ: ₪{min}", swaps to the picked variation's exact price on selection, restores on reset.

## [1.7.5] — 2026-04-27

- CategorySlider: progress bar is now a real horizontal scrubber — grows on hover, mousedown jumps + drags like a native scrollbar. The desktop middle-ground between draggable cards and click-only cards.

## [1.7.4] — 2026-04-27 + Theme [1.0.3]

- **Freeman Theme** (1.0.3): hide the WC-injected `a.added_to_cart` "View cart" link on shop / archive cards.

## [1.7.4] — 2026-04-27

- **Freeman Core**: CategorySlider mouse drag is now opt-in via a new switcher (default OFF) — desktop clicks no longer fight with drag at all. VariationSwatches gains a "No pre-selected variation on archive" setting (default ON) that bypasses both manually-set defaults AND the auto-cheapest pick on shop / archive / loop contexts.
- **Freeman Theme** (1.0.2): Removed the global `a:hover { opacity: 0.75 }` rule entirely — the 1.0.1 `:has()`-based scoping wasn't enough; killing the rule fixes the grey hover wash on every image-wrapping anchor.

## [1.7.3] — 2026-04-26

- **Freeman Core**: CategorySlider drag threshold raised 8 → 16px so mouse drag no longer fights with click intent; new Hover ring color control. CheapestDefaultVariation gains an "Apply on product pages only" setting (default ON) — auto-selection is suppressed on shop / archive / loop contexts so swatches there render with nothing pre-selected.
- **Freeman Theme** (1.0.1): Fix grey hover wash on image-containing anchors — global `a:hover { opacity: 0.75 }` rule scoped to text links only via `:has()`.

## [1.7.2] — 2026-04-26

- CategorySlider QA fixes: progress bar reaches end-of-scroll (pixel-based translate); cards reliably clickable (drag threshold + scroll only after confirmed drag); URL fallback hardened against empty `get_term_link()` returns; editor-mode access guarded.

## [1.7.1] — 2026-04-26

- CategorySlider polish: fonts inherit from theme/Elementor + Typography controls for eyebrow/headline/name; RTL drag direction fixed; hover ring no longer clipped at top; image corners now show fully (wrapper background removed); arrows hardened against Elementor button-style cascade.

## [1.7.0] — 2026-04-26

- New Freeman Core module **CategorySlider**: Elementor widget rendering WooCommerce product categories as an editorial drag-scroll slider with momentum, optional CSS scroll-snap, hover ring, progress bar, and per-breakpoint cards-per-view. Controls use Elementor SLIDER + CHOOSE for parity with the Claude Design Tweaks panel. Term-query controls (include/exclude/child-of). Full RTL — Direction control flips arrows, drag, and progress bar; auto follows `is_rtl()`.

## [1.6.0] — 2026-04-23

- Swatches picker now renders in Related/Upsells/Cross-sells on product pages and any non-archive product loop (home-page product widgets, shortcode grids). New OPT_APPLY_RELATED toggle (default on) lets shop owners disable just the PDP loops.

## [1.5.1] — 2026-04-22

- OOS/priceless simple products now render a single disabled ATC labeled אזל מהמלאי with no Buy Now; variable products hide Buy Now and swap the ATC label when an OOS variation is picked

## [1.5.0] — 2026-04-22

- Test harness + CI: added PHPUnit ^9.6||^10.5 to composer dev-requires; phpunit.xml.dist + tests/bootstrap.php with stubbed WP environment; 44 tests across Detection_Result, Base_Importer contract, Module_Registry, Security helpers, ProductFeed split regression; tests/README.md usage guide; GitHub Actions workflow at .github/workflows/ci.yml runs php-lint + smoke + activation-sim + phpunit across PHP 7.4-8.3 plus wpcs + build-zip jobs

## [1.4.0] — 2026-04-22

- Structure: ProductFeed Module.php (663 lines) split into Generator + Server + Module (324 lines). Generator owns XML writing + file locking + paths + OPT_LAST_GEN. Server owns rewrite + query var + serve_feed. Module remains the lifecycle coordinator (boot, cron, settings, admin panel). Module::generate_feed/feed_file/feed_url/feed_dir/lock_file retained as BC proxies so third-party callers keep working. BATCH/REWRITE_SLUG/QUERY_VAR/OPT_LAST_GEN kept as class constants on Module for the same reason.

## [1.3.0] — 2026-04-22

- Deferred items: RestockNotify assets now load only on product/shop/cart/checkout/shortcode pages (filter: rsn_should_enqueue); Swatches legacy strings migrated from etucart-vs to freeman-core text-domain, .pot + he_IL.po updated; typed Detection_Result value object returned from every Base_Importer::detect() — Legacy_Importer::scan() now coerces + logs on shape mismatch instead of silently going blank

## [1.2.1] — 2026-04-22

- Fix: simple products stuck as OOS on PDP (wc_variation_form + reset_data triggered on simple forms); fall back to WC native template when !is_purchasable() so misconfigured products surface WC warnings; +/- quantity stepper on shop-archive simple picker (matches PDP)

## [1.2.0] — 2026-04-22

- Structure + UX: Base_Importer abstract extracted (6 importers collapsed from ~330 to ~150 lines); admin notice on module boot failure; Dashboard cross-links to legacy settings for Swatches/Restock; VariationSwatches on_deactivate clears stale picker transients; InfiniteScroll aria-live status announcements; :focus-visible outlines on Swatches buy-box + shop picker; per-module README.md documentation; smoke.php now tests Importer layer

## [1.1.0] — 2026-04-22

- Performance: batch-prime variation post caches in ProductFeed write_feed; paginate VariableStockFix daily audit with self-chaining cron; transient-cache Swatches shop-archive variation data and RestockNotify OOS lookups; debounce VariableStockFix variation-save hooks; esbuild minification pipeline in build.sh; SCRIPT_DEBUG-aware asset_min_url helper; MutationObserver replaces retry timeouts in InfiniteScroll

## [1.0.4] — 2026-04-22

- Security: harden email From header, class_exists guards for legacy plugin conflicts, honeypot + i18n on subscribe form, unified rate_limit, transient-backed Tools banners, swatches add-to-cart gate filter

## [1.0.3] — 2026-04-22

### Added
- **Swatches** — Simple products now use the Freeman buy box on both the PDP and the shop grid.
  - Single product page: styled Add to Cart + Buy Now + quantity stepper + sticky mobile bar.
  - Shop / archive: compact Add-to-cart card with a quantity stepper (AJAX, shared toast stack).
  - OOS / non-purchasable simple products render the Freeman button disabled with "אזל מהמלאי".
  - Grouped and external products keep WooCommerce's default templates.

## [1.0.2] — 2026-04-22

- Removed the ElevatedCards (Cards) module and all its code, assets, templates, translations, Importer and legacy wcec_* option handling.

## [1.0.1] — 2026-04-22

### Fixed
- **Freeman Core** — Parse error in `src/Core/Module_Registry.php` docblock (unescaped `*/` in the path `src/Modules/*/Module.php` closed the comment early and caused a fatal at plugin activation). Path is now written with backticks in the docblock.
- `tools/release.sh` now also bumps the `FREEMAN_CORE_VERSION` constant in the main plugin file and prepends a changelog entry automatically.
- `tools/activation-sim.php` added: offline WP-stubbed activation harness so parse errors and activation fatals are caught before `dist/` is shipped.

## [1.0.0] — 2026-04-22

### Added
- Initial release.
- **Freeman Theme** 1.0.0 — child theme of Hello Elementor 3.4.x.
- **Freeman Core** 1.0.0 — unified plugin hosting all seven modules:
  - `Cards` (ElevatedCards) — product card replacement, Quick View, Quick Add, Elementor widget.
  - `Swatches` (VariationSwatches) — variable-product buy-box + archive swatches.
  - `Restock` (RestockNotify) — back-in-stock subscription system with custom DB table.
  - `StockFix` (VariableStockFix) — parent stock reconciliation tool + daily cron.
  - `Feed` (ProductFeed) — gzipped XML product feed with cron + instant rebuild.
  - `Scroll` (InfiniteScroll) — shop grid infinite scroll.
  - `Cheapest` (CheapestDefaultVariation) — auto-select cheapest in-stock variation.
- `Legacy_Importer` migration wizard: detects each of the 7 legacy plugins, copies settings, adopts the restock-notify DB table, offers one-click "deactivate & delete legacy" action.
- Unified `Freeman` admin menu with module toggles, onboarding wizard, health checks.
- Hebrew (`he_IL`) translation.
- WooCommerce HPOS + Cart/Checkout Blocks compatibility declared centrally.
