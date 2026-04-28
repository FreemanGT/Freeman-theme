# Freeman Codebase Review — April 2026

Full review of `freeman-theme/` + `freeman-core/` + tooling, covering security, performance, structure/architecture, and UI/UX / missing features / quality.

Every finding is pinned to a file:line. Two claims from the initial scan were verified as false positives and excluded (noted below). One planned fix was also dropped after verification.

---

## A. Executive summary

| Dimension | 🔴 Critical | 🟠 High | 🟡 Medium | 🔵 Low |
|---|---|---|---|---|
| Security | 1 | 2 | 3 | 0 |
| Performance | 0 | 4 | 2 | 2 |
| Structure | 1 | 2 | 2 | 2 |
| UX / Quality | 0 | 3 | 3 | 2 |

**Top 5 things to fix first** (risk × effort):

1. 🔴 Email header injection in [class-rsn-email.php:124](freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-email.php#L124) — trivially exploitable if any admin account is compromised or any other plugin lets you write those options.
2. 🔴 Unnamespaced legacy global classes (`RSN_*`, `Etucart_VS_*`) instantiated without `class_exists` guards — fatal redeclare if any other plugin uses the same names. Fix with one-line guards.
3. 🟠 N+1 variation fetches in `ProductFeed::write_feed()` — feed generation scales ~O(products × variations). One-query batch fix.
4. 🟠 Unbounded 1000-post daily audit in `VariableStockFix` — WP-Cron timeout risk. Split into 50-post chunks chained via `wp_schedule_single_event`.
5. 🟠 Hardcoded untranslatable Hebrew strings in `RSN_Ajax` — locks UI to Hebrew. 10 lines of `__()` wrapping + regenerate `.pot` / `.po` / `.mo`.

**What's already good** (don't break these):
- Centralized `Security` helper with nonce/cap/sanitize/rate-limit — cleaner than most WP plugins.
- PSR-4 autoloader in `freeman-core.php` with **zero runtime dependencies**.
- Every module implements `Module_Interface` via `Module_Base` consistently.
- `defined('ABSPATH') || exit;` guards everywhere.
- `Settings_Hub` sanitizes + escapes every field type correctly.
- `uninstall.php` is scoped and delegates to per-module `on_uninstall()`.
- No `unserialize()` of user data, no `eval()`, no remote URL fetching → no SSRF/RCE vectors.
- `wp_mail()` called correctly (modulo the From-header injection).
- Batching + file locking in `ProductFeed::write_feed()`, debounced regeneration (30s), gzip support.
- `dependencies_met()` gates PHP / Woo / Elementor version; `health()` surfaces via dashboard dots.

**Claims dismissed after verification** (was in exploration output, is NOT a bug):
- ❌ "RestockNotify CSRF bypass on nonce failure" — false. [class-rsn-ajax.php:12-14](freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-ajax.php#L12-L14) calls `wp_send_json_error` which internally calls `wp_die()`, so execution stops on failure.
- ❌ "innerHTML XSS in RestockNotify footer fallback" — false. The JSON at [class-rsn-frontend.php:286-290](freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-frontend.php#L286-L290) contains only integer variation IDs + booleans, wrapped in `wp_json_encode()`.
- ❌ "`freeman_core_onboarded` never written" — false. Written at [Dashboard.php:229](freeman-core/src/Admin/Dashboard.php#L229) (skip link) and [Dashboard.php:237-242](freeman-core/src/Admin/Dashboard.php#L237-L242) (`update_option_freeman_core_modules` hook).

---

## B. Security findings

### 🔴 CRITICAL — 1 finding

**S-01. Email header injection in `RSN_Email::send()`**
[`freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-email.php:117-126`](freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-email.php#L117-L126)

```php
private static function send( $to, $subject, $html ) {
    $fn = rsn_get_option( 'from_name' );
    $fe = rsn_get_option( 'from_email' );
    …
    return wp_mail( $to, $subject, $html, array(
        'Content-Type: text/html; charset=UTF-8',
        sprintf( 'From: %s <%s>', $fn, $fe ),   // ← not sanitized
    ) );
}
```

Neither `$fn` nor `$fe` is passed through `sanitize_text_field()` / `sanitize_email()` before interpolation into a header. An admin who sets `from_name` to `Attacker\r\nBcc: victim@example.com` gets BCC injected into every restock email. Settings_Hub sanitizes on write for its own fields, but Restock's admin page predates Settings_Hub and writes via `update_option` directly in `class-rsn-admin.php`.

**Fix:** Strip `\r\n` / `\0` from `$fn`, run `$fe` through `sanitize_email()` and fall back to `get_option('admin_email')` if invalid. Or use `wp_mail_from` / `wp_mail_from_name` filters instead of raw header array.

### 🟠 HIGH — 2 findings

**S-02. Unnamespaced legacy global classes — no `class_exists` guard**
[`freeman-core/src/Modules/RestockNotify/Module.php:111-142`](freeman-core/src/Modules/RestockNotify/Module.php#L111-L142), [`freeman-core/src/Modules/VariationSwatches/Module.php:79-91`](freeman-core/src/Modules/VariationSwatches/Module.php#L79-L91)

Classes named `RSN_Frontend`, `RSN_Ajax`, `RSN_Stock_Monitor`, `RSN_Email`, `RSN_Database`, `Etucart_VS_Plugin`, `Etucart_VS_Frontend`, `Etucart_VS_Archive`, etc. live in the global namespace (no `namespace …;` line). When Freeman Core requires these files and another plugin — including the original standalone one that Freeman replaces — has the same class, PHP fatals with `Cannot declare class RSN_Frontend, because the name is already in use`.

**Not a direct vuln** but a safety issue classified as security because it can bring the entire WP admin down hard at activation.

**Fix:** Before `require_once` each legacy file, guard with `if ( ! class_exists( 'RSN_Frontend', false ) )` etc.; on conflict, set a transient admin notice and skip booting the module.

**S-03. Public AJAX `wc_ajax_nopriv_etucart_shop_add_to_cart` — no membership check**
[`freeman-core/src/Modules/VariationSwatches/legacy/includes/class-archive.php:65-66, 413-424`](freeman-core/src/Modules/VariationSwatches/legacy/includes/class-archive.php#L65-L66)

Nonce protects against CSRF, but there's no filter allowing a membership/B2B plugin to reject the request before it runs. WooCommerce core `woocommerce_add_to_cart_validation` does get called, so in practice restricted products are blocked — but the Freeman handler doesn't document this, and code that assumes "logged-out users can add anything" could slip in.

**Fix:** Add a `do_action( 'freeman/swatches/before_shop_add_to_cart', $product_id, $variation_id )` explicitly so membership plugins can short-circuit, and document the reliance on `woocommerce_add_to_cart_validation`.

### 🟡 MEDIUM — 3 findings

**S-04. Spoofable forwarded-IP headers trusted for rate limiting**
[`freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-ajax.php:76-83`](freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-ajax.php#L76-L83)

`get_client_ip()` walks `HTTP_CF_CONNECTING_IP` → `HTTP_X_FORWARDED_FOR` → `HTTP_X_REAL_IP` → `REMOTE_ADDR`. On sites **not** behind a proxy, any client can send `X-Forwarded-For` and bypass the 5-requests-per-hour rate limit completely. (Note: `Freeman\Core\Core\Security::rate_limit` at [Security.php:93-101](freeman-core/src/Core/Security.php#L93-L101) only uses `REMOTE_ADDR`, so it's already safer — the inconsistency is in the legacy RSN module.)

**Fix:** Either use `Security::rate_limit('rsn_subscribe', 5, HOUR_IN_SECONDS)` directly, or add an allowlist setting so admins can opt into trusting `X-Forwarded-For` only when they know they're behind Cloudflare/Nginx proxy.

**S-05. No honeypot on public subscribe endpoint**
[`freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-ajax.php:11-73`](freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-ajax.php#L11-L73)

Rate limit is the only bot defense. A bot that rotates IPs will defeat it.

**Fix:** Add `<input type="text" name="_hp" style="display:none" tabindex="-1" autocomplete="off">` to the form and reject any submission where `_hp` is non-empty. 5 lines total, zero UX cost.

**S-06. Cosmetic spoof of import success banner**
[`freeman-core/src/Admin/views/tools.php:27-28`](freeman-core/src/Admin/views/tools.php#L27-L28)

```php
<?php if ( isset( $_GET['imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    echo '<div class="notice notice-success"><p>' . esc_html__( 'Legacy data imported.', 'freeman-core' ) . '</p></div>';
endif; ?>
```

Anyone with admin read access who bookmarks `/wp-admin/admin.php?page=freeman-tools&imported=1` sees a "data imported" confirmation that isn't tied to an actual import. Not an exploit — just misleading.

**Fix:** Set a short-lived transient keyed by user in `Legacy_Importer::handle_import()` redirect, read and delete it on tools.php load.

---

## C. Performance findings

### 🟠 HIGH — 4 findings

**P-01. N+1 variation fetches in `ProductFeed::write_feed()`**
[`freeman-core/src/Modules/ProductFeed/Module.php:277-346`](freeman-core/src/Modules/ProductFeed/Module.php#L277-L346)

For each variable product, every child variation ID is resolved via a separate `wc_get_product( $vid )` call inside the inner loop. A store with 5,000 products averaging 10 variations each triggers ~50,000 individual `get_post` / `get_post_meta` queries during generation.

**Fix:** Before iterating, collect all variation IDs for the batch, run **one** `_prime_post_caches( $ids, true, true )` so `wc_get_product` hits the in-memory cache. Or use `wc_get_products( [ 'include' => $ids, 'type' => 'variation', 'limit' => -1 ] )` to load them in a single query and build a lookup map.

**Estimated impact:** 60–80% query reduction on large catalogs; feed generation from minutes to seconds.

**P-02. Unbounded `VariableStockFix::run_daily_audit()`**
[`freeman-core/src/Modules/VariableStockFix/Module.php:376-401`](freeman-core/src/Modules/VariableStockFix/Module.php#L376-L401)

`posts_per_page = 1000` with no pagination, and then `all_variations_out_of_stock()` calls `wc_get_product` per child variation (another N+1). On a 5,000-product store this blows past PHP `max_execution_time`, kills the cron event, and blocks every other `wp_cron` job scheduled in that tick.

**Fix:** Paginate to `BATCH_SIZE = 50` per run, schedule the next page via `wp_schedule_single_event( time() + 60, self::CRON_HOOK, [ $next_offset ] )`. Same fix used throughout the WooCommerce core async-processing stack.

**P-03. No cache around `get_available_variations()` in shop loop**
[`freeman-core/src/Modules/VariationSwatches/legacy/includes/class-archive.php:202-227`](freeman-core/src/Modules/VariationSwatches/legacy/includes/class-archive.php#L202-L227)

`prepare_product_data()` runs per variable product in the shop loop. `get_available_variations()` is one of the most expensive WC calls (fetches all variation posts + meta + builds availability arrays). On a 24-tile shop with 15 variable products, that's 15 of these per page view, unscoped by any cache.

`$this->prepared_cache` does memoize **within one request**, but nothing persists across requests.

**Fix:** Cache the rendered swatch markup (or just `$available_variations`) in a transient keyed by `{product_id}.{stock_status_hash}.{price_hash}`. Invalidate on `woocommerce_update_product`, `woocommerce_save_product_variation`, and the variation stock-set hooks. TTL 6 h.

**Estimated impact:** 300–800 ms faster shop/category TTFB on catalog pages.

**P-04. Deep variation iteration on every variable product page render**
[`freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-frontend.php:256-271`](freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-frontend.php#L256-L271)

Same story as P-03, but on the single-product page: `maybe_render()` loops every child via `wc_get_product()` and then runs `is_variation_truly_oos()` per variation. 50-variation products add 500–2000 ms on first load.

**Fix:** Same pattern — compute `$oos_ids` once, cache in a transient keyed by parent product ID + a stock-status hash; invalidate on variation stock changes.

### 🟡 MEDIUM — 2 findings

**P-05. Assets shipped unminified, `tools/build.sh` has no minification step**
[`tools/build.sh:116-136`](tools/build.sh#L116-L136), [`package.json:10-15`](package.json#L10-L15)

`esbuild`, `postcss`, `postcss-cli`, `autoprefixer` are declared in `devDependencies` but never invoked. `build.sh` `rsync`s source → stage → zip, no compile step. Potential compression:

| Asset | Current | Minified est. | Saves |
|---|---|---|---|
| etucart-swatches.js | 36 KB | 12 KB | 24 KB |
| etucart-shop-swatches.js | 27 KB | 9 KB | 18 KB |
| infinite-scroll.js | 21 KB | 7 KB | 14 KB |
| etucart-swatches.css | 30 KB | 8 KB | 22 KB |
| etucart-shop-swatches.css | 16 KB | 5 KB | 11 KB |
| **Total** |  | | **~89 KB** |

**Fix:** Add a `minify_assets()` step to `build.sh` that runs between `stage_dir` and `zip`; emit `.min.js` / `.min.css` and enqueue them when `! WP_DEBUG && ! SCRIPT_DEBUG`.

**P-06. RestockNotify assets enqueued on every frontend page**
[`freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-frontend.php:56-82`](freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-frontend.php#L56-L82)

Comment labels this a "cannot fail" design — CSS/JS load on every non-admin, non-ajax, non-REST request so the form renders no matter what hook-ordering anomaly occurs. Reasonable, but conservative — easily ~5 KB on every page hit.

**Fix:** Keep the fallback, but hook into `wp` and skip enqueue when `!is_product() && !is_shop() && !is_product_taxonomy() && !is_cart() && !is_checkout()`. The "cannot fail" claim still holds because the form shortcode forces a late-enqueue via `wp_print_footer_scripts`.

### 🔵 LOW — 2 findings

**P-07. No debounce on `VariableStockFix` stock-change hooks**
[`freeman-core/src/Modules/VariableStockFix/Module.php:107-113`](freeman-core/src/Modules/VariableStockFix/Module.php#L107-L113)

A bulk stock import that touches 100 variations triggers 100 immediate checks. Compare to ProductFeed's `schedule_debounced( 30 )` pattern ([ProductFeed/Module.php:150-165](freeman-core/src/Modules/ProductFeed/Module.php#L150-L165)).

**Fix:** Mirror the same pattern — queue one `wp_schedule_single_event` 30 s out, idempotent.

**P-08. InfiniteScroll retries boot at 300/1200/3000/6000 ms**
[`freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js:512-522`](freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js#L512-L522)

Defensive against Elementor's late-mount patterns. Works, but a single `MutationObserver` watching `document.body` for the grid node is cleaner and fires exactly once.

**Fix:** Replace the four timeouts with one observer that disconnects on boot.

---

## D. Structure / architecture findings

### 🔴 CRITICAL — 1 finding

**ST-01. Legacy global classes without `class_exists` guards** — same issue as **S-02** above; counted once under Security.

### 🟠 HIGH — 2 findings

**ST-02. `ProductFeed/Module.php` (663 lines) is a god class**
[`freeman-core/src/Modules/ProductFeed/Module.php`](freeman-core/src/Modules/ProductFeed/Module.php)

Mixes: XML generation, gzip writing, file locking, scheduled + debounced cron wiring, rewrite-rule registration, HTTP feed serving (with `status_header`, charset headers, 503 fallback), admin status panel rendering + inline JS.

**Fix:** Split into three files in the same module:
- `Generator.php` — `write_feed`, `product_xml`, `variation_xml`, `common_fields`, `schedule_debounced`
- `Server.php` — `register_rewrite`, `register_query_var`, `serve_feed`
- thin `Module.php` — boot, on_activate, on_deactivate, schema, health, admin panel

Public hooks and the existing option keys stay unchanged, so no migration needed.

**ST-03. Settings surface split between Freeman menu and legacy menus**
[`freeman-core/src/Modules/RestockNotify/Module.php:70`](freeman-core/src/Modules/RestockNotify/Module.php#L70), [`freeman-core/src/Modules/VariationSwatches/Module.php:71`](freeman-core/src/Modules/VariationSwatches/Module.php#L71)

Four modules (Cheapest, Scroll, StockFix, Feed) expose settings in `Settings_Hub`. Restock keeps its legacy top-level "Restock Notify" menu. Swatches keeps its settings under "WC → Settings → Products". Admins have to hunt for two of six modules. The Freeman dashboard card doesn't link out to either.

**Fix:** Cheap win — update the dashboard card's Settings button to point to the legacy settings URL when `settings_schema()` is empty. Right win — migrate those two modules into `Settings_Hub` (preserving legacy option keys for zero-downtime).

### 🟡 MEDIUM — 2 findings

**ST-04. Importer boilerplate duplicated 6×**
[`freeman-core/src/Modules/*/Importer.php`](freeman-core/src/Modules/)

Each `Importer.php` has near-identical `detect()`, `delete_legacy_options()`, `mark_imported()`, result array shape. ~55 × 6 ≈ 330 lines of repetition.

**Fix:** Extract `Freeman\Core\Core\Base_Importer` abstract implementing the shared bits; each concrete importer only overrides `const LEGACY_PLUGIN_FILE` and `import()`.

**ST-05. `Plugin::boot()` swallows module boot exceptions silently**
[`freeman-core/src/Core/Plugin.php:132-140`](freeman-core/src/Core/Plugin.php#L132-L140)

`Logger::log()` writes to a rotating log — but no admin notice fires. Store owner won't see that a module is broken unless they tail the log.

**Fix:** Store a `freeman_core_boot_failures` transient on catch; read it in `Dashboard::maybe_onboarding_notice`-adjacent code and render a dismissable notice listing which modules failed and their error message (with `esc_html`). Also surface on the dashboard card header (red dot + tooltip).

### 🔵 LOW — 2 findings

**ST-06. Fragmented i18n domains**
`class-etucart-vs-*.php` still calls `load_plugin_textdomain( 'etucart-vs', … )`. Freeman Core uses `'freeman-core'`. Any string a translator fixes in one domain isn't reflected in the other.

**Fix:** Switch legacy files to `'freeman-core'` text domain; regenerate `.pot` to include legacy strings. No user visible change.

**ST-07. Legacy god classes `class-rsn-frontend.php` (576 lines) and `class-rsn-admin.php` (22 KB)**

Flagged only. Legacy, large, out of scope for this review cycle. Refactor target for a future major.

---

## E. UI / UX / missing / quality findings

### 🟠 HIGH — 3 findings

**UX-01. Hardcoded Hebrew error messages, no `__()` wrapping**
[`freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-ajax.php:13-42`](freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-ajax.php#L13-L42)

Every error path sends raw Hebrew strings. No `__()`. No text-domain. Translators can't reach them. UI will always display Hebrew even on English installs.

**Fix:** Wrap each in `__( '…', 'freeman-core' )`, then `cd freeman-core && wp i18n make-pot . languages/freeman-core.pot` (or the existing tooling path), re-translate `.po`, `msgfmt` to `.mo`.

**UX-02. Swatch templates lack ARIA and keyboard affordances**
[`freeman-core/src/Modules/VariationSwatches/legacy/templates/shop-variation-pick.php`](freeman-core/src/Modules/VariationSwatches/legacy/templates/shop-variation-pick.php), [`variation-buy-box.php`](freeman-core/src/Modules/VariationSwatches/legacy/templates/variation-buy-box.php)

Swatches are `<span>` / `<div>` elements without `role="button"`, `tabindex="0"`, `aria-label`, or `aria-checked`/`aria-selected` state. No visible focus outline in CSS. No `<noscript>` fallback when JS is disabled.

**Fix:**
1. Make swatches actual `<button type="button">` with `role="radio"` inside a `role="radiogroup"`.
2. Add `:focus-visible` outline to the swatch CSS (respects user setting to disable focus ring on mouse).
3. Wrap each swatch group in `<noscript>` that reveals the stock WC variations form as a fallback.

**UX-03. InfiniteScroll has no screen-reader announcement or keyboard fallback**
[`freeman-core/src/Modules/InfiniteScroll/Module.php`](freeman-core/src/Modules/InfiniteScroll/Module.php), [`assets/js/infinite-scroll.js`](freeman-core/src/Modules/InfiniteScroll/assets/js/infinite-scroll.js)

New products append silently. Screen reader users don't know more items loaded. Keyboard users can't trigger loads without scrolling. This also hurts SEO (Googlebot doesn't scroll).

**Fix:**
1. Wrap the grid in a region with `aria-live="polite"` and announce e.g. "12 more products loaded" when a page arrives.
2. Render a **visible** "Load more" button above the sentinel; infinite-scroll behavior becomes an enhancement, not the only path.
3. Preserve `?paged=` query so deep links work for crawlers.

### 🟡 MEDIUM — 3 findings

**UX-04. No `on_deactivate()` in VariationSwatches module**
[`freeman-core/src/Modules/VariationSwatches/Module.php`](freeman-core/src/Modules/VariationSwatches/Module.php)

The other five modules clear their crons / transients on deactivate. Swatches doesn't, so disabling the module leaves legacy `etucart_vs_*` transients and any registered hooks active until page-load sees the module is off.

**Fix:** Implement `on_deactivate()` that clears `etucart_vs_*` transients via `$wpdb->options LIKE '_transient_etucart_vs_%'` cleanup.

**UX-05. No per-module README — only `HOOKS.md`**
`HOOKS.md` documents public extension points for developers. There's nothing that tells a site owner how to use a module, what its settings mean, or where to look for it.

**Fix:** Add one-pager README.md per module: what it does, settings reference, screenshot placeholder, dependencies, public hooks (link to HOOKS.md).

**UX-06. No tests, no CI**
No `tests/` dir, no `phpunit.xml.dist`, no GitHub Actions or any CI. `tools/smoke.php` + `activation-sim.php` are the only automated checks.

**Fix (scoped):** Add a minimal `phpunit.xml.dist` + one smoke test per module that verifies `boot()` registers its expected hooks. Out of scope for this pass, noted for the next initiative.

### 🔵 LOW — 2 findings

**UX-07. `Legacy_Importer::scan()` loose array access**
[`freeman-core/src/Core/Legacy_Importer.php:74-78`](freeman-core/src/Core/Legacy_Importer.php#L74-L78)

Trusts every importer's `detect()` return value has `installed` / `active` / `file` keys. If a new importer returns a bare boolean the UI silently goes blank.

**Fix:** Require the return to be a typed DTO (simple value object or strict array shape check with `ArrayAccess`).

**UX-08. Inline style block printed multiple times on OOS pages**
[`freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-frontend.php:327-357`](freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-frontend.php#L327-L357)

Guarded by `self::$inline_css_printed` so only once per request — but defensive. Fine as-is for now.

---

## F. Build / distribution findings

- `tools/build.sh` [preflight:180-199](tools/build.sh#L180-L199) runs `php -l` on every source file and `activation-sim.php` before zipping. **Good.**
- No asset minification (see P-05).
- `tools/build.sh` passes `COMMON_EXCLUDES` including `*.map`, `node_modules`, `.git`, etc. **Good.**
- `COMMON_EXCLUDES` does NOT include `*.po` — shipped zips contain `.po` files alongside `.mo`. Usually fine; WP only reads `.mo`. Keep for translator debugging.
- `tools/build.sh` deletes `composer.json` and `tools/` from the shipped core zip. **Good** — dev-only tooling doesn't leak.
- `dist/` is not inspected in this pass beyond confirming filename → version mapping; verify zip contents after each wave.

---

## G. Prioritized action list

| # | Finding | Severity | Effort | Wave |
|---|---|---|---|---|
| S-01 | Email header injection | 🔴 | S | **1** |
| S-02 / ST-01 | `class_exists` guards for legacy classes | 🔴 | S | **1** |
| UX-01 | i18n wrap hardcoded Hebrew AJAX errors | 🟠 | S | **1** |
| S-05 | Add honeypot to subscribe form | 🟡 | S | **1** |
| S-06 | Replace `?imported=1` with transient flag | 🟡 | S | **1** |
| S-03 | Explicit pre-add-to-cart action in Swatches | 🟠 | S | **1** |
| S-04 | Use `Security::rate_limit` in RSN AJAX | 🟡 | S | **1** |
| P-01 | Batch-prime variation caches in ProductFeed | 🟠 | M | **2** |
| P-02 | Paginate VariableStockFix audit | 🟠 | M | **2** |
| P-03 | Transient-cache `get_available_variations()` | 🟠 | M | **2** |
| P-04 | Cache `is_variation_truly_oos()` per product | 🟠 | S | **2** |
| P-05 | Add minification step to build.sh | 🟡 | M | **2** |
| P-07 | Debounce VariableStockFix stock-change hooks | 🔵 | S | **2** |
| P-08 | Replace InfiniteScroll retries with MutationObserver | 🔵 | S | **2** |
| P-06 | Conditional enqueue for RestockNotify assets | 🟡 | S | **2** |
| ST-02 | Split ProductFeed god class | 🟠 | L | **3** |
| ST-03 | Link Swatches / Restock settings from dashboard | 🟠 | S | **3** |
| ST-04 | Extract `Base_Importer` | 🟡 | M | **3** |
| ST-05 | Admin notice on module boot failure | 🟡 | S | **3** |
| ST-06 | Unify legacy text-domains | 🔵 | S | **3** |
| UX-02 | ARIA + focus + noscript for Swatches | 🟠 | M | **3** |
| UX-03 | aria-live + Load-more button for InfiniteScroll | 🟠 | M | **3** |
| UX-04 | Add `on_deactivate()` to Swatches | 🟡 | S | **3** |
| UX-05 | Per-module README.md | 🟡 | M | **3** |
| UX-07 | Typed `detect()` DTO in Legacy_Importer | 🔵 | S | **3** |

**Out of scope** (flagged, not in any wave):
- ST-07: God-class refactor of `class-rsn-frontend.php` / `class-rsn-admin.php` (separate major).
- UX-06: PHPUnit harness + CI setup (separate major).
- Full type-hint pass across the codebase.
- Licensing / auto-update server integration.

---

## Execution order (per your approved plan)

1. Wave 1 (security): patch bump Core → **1.0.4**. `release.sh core 1.0.4 "Security hardening: email header, class_exists guards, i18n, honeypot, rate limit, cap guard, tools banner"`.
2. Wave 2 (performance): minor bump Core → **1.1.0**. `release.sh core 1.1.0 "Performance: batch variation loading, paginated audit, variation caches, minification"`.
3. Wave 3 (structure + UX): minor bump Core → **1.2.0** (theme stays or gets a patch). `release.sh core 1.2.0 "Structure + UX: ProductFeed split, Base_Importer, admin boot-failure notice, a11y, deactivate cleanup, README per module, settings cross-links"`.

Each wave runs `tools/build.sh all` → preflight (php -l + activation-sim) + smoke.php before shipping.
