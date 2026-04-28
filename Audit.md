```
You are a senior WordPress plugin engineer auditing my custom WooCommerce + Elementor plugin suite. Your job is to find real problems, verify every module works as intended, and enforce naming consistency across the codebase — not generic advice, not theory.

## Context (carry forward)
- The plugin has been heavily refactored. Modules have been renamed, merged, and split.
- Different parts of the codebase use different names for the same functionality (legacy names, internal class names, hook names, option keys, asset handles, text domain slugs, admin labels — all may have drifted).
- The intended canonical module names are the eight listed below. Every reference in code, hooks, options, classes, files, assets, and UI must match these names or a deterministic, documented derivation of them.

## Plugin Modules — Canonical Names
1. **Category Slider** — Editorial Elementor widget for WooCommerce product categories. Drag-scroll slider with momentum, hover ring, progress bar.
2. **Cheapest Default Variation** — Auto-selects the cheapest in-stock variation as the default so customers can add to cart without picking options.
3. **Infinite Scroll** — Infinite scroll for WooCommerce product grids (shop, Elementor widgets, block grids) with skeleton placeholders and preserved `/page/N/` URLs.
4. **Product Feed** — Gzipped XML feed of every product (with variations, stock, pricing, attributes). Rebuilt hourly and within 30 seconds of any stock or price change. Exposed at `/product-feed`.
5. **Product Slider** — Editorial Elementor widget for WooCommerce products. Drag-scroll slider or static grid, with price, sale badge, add-to-cart.
6. **Restock Notify** — Customers subscribe to out-of-stock products and are emailed the moment stock returns. Hebrew-first UI, exportable subscriber list.
7. **Variable Stock Fix** — When all visible variations of a variable product are out of stock, this module unchecks the parent's "Manage stock" box so Woo's native "Hide out of stock items" hides the product from the shop.
8. **Variation Swatches** — Color swatches, size pills, quick-add buy box and shop-grid variation picker for variable products.

For every module, first state in one sentence what the code actually does, then verify it matches the intended behavior. Flag any mismatch.

## Naming Consistency Audit (run this FIRST, before everything else)

Before any other audit work, build a Naming Map for the entire codebase. This is non-negotiable — a refactored codebase with drifted names is the #1 cause of subtle production bugs (dead hooks, dead options, dead text-domain strings).

For each canonical module, derive the expected naming convention and then scan the code to find every actual name in use. Report drift.

**Expected derivations (use the plugin's existing prefix — detect it from the main plugin file, e.g. `myplug_` or `MyPlug\`):**

| Surface | Convention |
| Module slug (snake_case) | `category_slider`, `cheapest_default_variation`, `infinite_scroll`, `product_feed`, `product_slider`, `restock_notify`, `variable_stock_fix`, `variation_swatches` |
| Module slug (kebab-case for CSS/JS handles, REST routes, file names) | `category-slider`, `cheapest-default-variation`, etc. |
| PHP class | `{Prefix}\Modules\CategorySlider`, `{Prefix}\Modules\CheapestDefaultVariation`, etc. (PascalCase) |
| Option keys | `{prefix}_{module_slug}_{setting}` |
| Hook names (actions/filters added by us) | `{prefix}/{module_slug}/{event}` or `{prefix}_{module_slug}_{event}` — pick one and enforce it |
| Asset handles (wp_enqueue) | `{prefix}-{module-slug}` and `{prefix}-{module-slug}-admin` |
| File/folder names | `modules/{module-slug}/` |
| Elementor widget name (the `get_name()` return) | `{prefix}-{module-slug}` |
| Elementor widget title (the `get_title()` return) | Human-readable canonical name from the list above |
| Text domain | Single domain across the entire plugin — detect it and verify every `__()` / `_e()` / `esc_html__()` uses it |
| REST namespace | `{prefix}/v1` — single namespace, modules as routes |
| Action Scheduler group | `{prefix}_{module_slug}` |
| JS global object / window namespace | `window.{Prefix}.{ModuleName}` (PascalCase) |
| CSS class prefix | `.{prefix}-{module-slug}__{element}--{modifier}` (BEM) |
| Admin menu slug | `{prefix}-{module-slug}` |

**Produce these outputs:**

1. **Detected prefix** — what prefix the plugin actually uses (e.g., `myplug_`, `mp_`, `MyPlug\`). If multiple prefixes exist, list all of them and flag as drift.

2. **Detected text domain(s)** — list every text domain string used in `__()`/`_e()`/`esc_html__()` calls and in the plugin header. More than one = drift.

3. **Naming Map Table** (one row per surface per module, ~64 rows minimum):

| Module | Surface | Expected name | Actual name(s) found in code | File(s) | Drift? |

   Mark Drift = **YES** when any of the following:
   - Multiple names exist for the same thing
   - Name doesn't match the convention
   - Legacy name still referenced after a rename
   - Module slug used inconsistently (e.g., `restock_notifier` in one file, `restock_notify` in another)
   - Class name doesn't match its file path (PSR-4 violation)
   - Hook name uses the old module name
   - Option key uses the old module name (will cause settings to read defaults silently after rename — silent data loss)
   - Asset handle collides with another module or with WC/Elementor core handles

4. **Dead Reference List** — every place where code references a name that no longer exists anywhere else (orphan hook listeners, orphan `get_option` calls, orphan asset handles, orphan class autoload entries). These are bugs — the feature is silently disabled.

5. **Rename Migration Plan** — for each drift, the exact file:line edits needed to consolidate to the canonical name. Include:
   - Option key migrations: `update_option(new_key, get_option(old_key))` + `delete_option(old_key)` gated by a version flag in `{prefix}_db_version`, run once on plugin update.
   - Hook compatibility shims: if external code may listen to old hook names, fire both old and new for one release cycle, then remove. Document the deprecation timeline.
   - Asset handle renames: check the theme and other plugins aren't depending on the old handle (search the user's theme if visible).

## Audit Categories
After the Naming Consistency Audit, run all categories below. For each, scan the entire codebase and report concrete findings tied to file paths and line numbers.

1. **Security**
   - Unsanitized inputs (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`)
   - Unescaped output (missing `esc_html`, `esc_attr`, `esc_url`, `wp_kses`) — including Hebrew RTL strings in Restock Notify emails
   - SQL injection (raw `$wpdb->query`, missing `prepare()`, unsafe `LIKE`)
   - Missing nonces on forms and AJAX (`wp_verify_nonce`, `check_ajax_referer`)
   - Missing capability checks (`current_user_can`) on admin actions and AJAX
   - Restock Notify: subscription endpoint must rate-limit, validate email, prevent enumeration, use unguessable unsubscribe tokens. Subscriber export admin-only and capability-checked.
   - Variable Stock Fix: scan/modify endpoint must be admin-only, nonce-protected, idempotent. Must NEVER run on a public request.
   - Product Feed: must not leak draft/private/password-protected products or admin-only meta. Enforce caching/rate limiting to prevent DoS.
   - Infinite Scroll: AJAX must validate `paged`, taxonomy, filter args against allowlist; no arbitrary `WP_Query` arg injection.
   - Direct file access — every PHP file should have `if ( ! defined( 'ABSPATH' ) ) exit;`

2. **Hardcoded Values**
   - API keys, secrets, tokens, credentials
   - Hardcoded URLs, paths, table prefixes, user IDs, sender emails
   - Magic numbers/strings that should be constants, options, or filters
   - Untranslatable strings missing `__()` / `esc_html__()`. Restock Notify Hebrew copy must be wrapped, not hardcoded; verify text domain loaded via `load_plugin_textdomain` and `.mo` files exist for `he_IL`

3. **WooCommerce Compatibility & Options Verification**
   - HPOS compatibility declared via `FeaturesUtil::declare_compatibility`
   - Cart and Checkout Blocks compatibility
   - No deprecated WC functions, no direct `post`-table queries on products/variations
   - Variable product handling — `WC_Product_Variable`, `get_available_variations()`, `get_children()`, `get_visible_children()`
   - Stock writes use setters + `save()`; never direct meta writes when a setter exists
   - Price reads use `get_price()`, `get_variation_prices()`
   - Hook names, priorities, arg counts correct: `woocommerce_variation_is_visible`, `woocommerce_available_variation`, `woocommerce_dropdown_variation_attribute_options_html`, `woocommerce_loop_add_to_cart_link`, `woocommerce_product_set_stock_status`, `woocommerce_variation_set_stock_status`, `woocommerce_after_save_product_variation`, `woocommerce_update_product`
   - WC version checks and minimum in plugin header
   - Session via `WC()->session`

4. **Elementor Compatibility & Widget Verification**
   - Widgets registered via `elementor/widgets/register`
   - Categories registered via `elementor/elements/categories_registered`
   - Controls — correct types, defaults, conditions, responsive controls
   - `render()` ↔ `content_template()` parity for Category Slider and Product Slider
   - `Elementor\Plugin::$instance->editor->is_edit_mode()` used correctly
   - Assets enqueued via Elementor's methods, conditionally — slider/Infinite Scroll JS/CSS NOT loaded where unused
   - Slider library enqueued once, version matches widget code, no duplicates with theme
   - Infinite Scroll inside Elementor product widgets hooks the widget's pagination markup, doesn't break Editor preview
   - Elementor Free + Pro compatibility
   - Minimum Elementor version declared and runtime-checked

5. **Module-Specific Verification**

   **Category Slider:** `product_cat` query correct (`hide_empty`, `parent`, `orderby`, `order`, `number`); image fallback; pointer-based drag-scroll with momentum, no text selection, touch+mouse+pen; hover ring + progress bar update on scroll position not timer; keyboard accessibility.

   **Cheapest Default Variation:** hooks `woocommerce_product_get_default_attributes` or filters default selection — verify which actually wins; "cheapest" uses sale price when on sale, regular otherwise, deterministic tie-break; `is_in_stock()` respects backorder config; only on variable products; cache invalidation on price/stock change documented; graceful fallback when none in stock.

   **Infinite Scroll:** AJAX validates and allowlists args; `history.pushState` to `/page/N/`; skeleton dimensions match grid items (no CLS); IntersectionObserver, disconnect after last page; works on shop, category, tag archives, Elementor Products widget, core Product Collection block; first page server-rendered for SEO; `aria-live` end state.

   **Product Feed:** `/product-feed` route registered with rewrite rules + flush on activation; gzip only when `Accept-Encoding` supports it; only `publish`, non-password-protected products; variations included with own stock/price/attributes; hourly Action Scheduler job, idempotent; 30s update on stock/price change, debounced; cached on disk or transient, not regenerated per request; streaming output for 50k+ catalogs; correct `Content-Type`, `Content-Encoding`, `Last-Modified`, `ETag`.

   **Product Slider:** query args map to real `wc_get_products`/`WP_Query`; price/sale badge/stock/add-to-cart correct for simple AND variable; variable products use `is_purchasable()` and correct add-to-cart link; AJAX add-to-cart updates fragments via `woocommerce_add_to_cart_fragments`; static-mode vs drag-mode toggle removes pointer listeners cleanly.

   **Restock Notify:** custom table with indexes on `product_id`, `variation_id`, `email`, unique `(product_id, variation_id, email)`; hooks both `woocommerce_product_set_stock_status` and `woocommerce_variation_set_stock_status`, handles `instock` and `onbackorder` per intent; sends queued via Action Scheduler; debounced against on/off flapping; unsubscribe token unguessable (HMAC of subscription id); uses `WC_Email`; Hebrew RTL email renders in Gmail/Outlook; GDPR exporters/erasers registered; CSV export escapes `=`/`+`/`-`/`@`.

   **Variable Stock Fix:** walks variable products → `get_visible_children()` → if all visible variations OOS, unchecks parent `manage_stock`; uses setters + `save()`; batched via Action Scheduler; triggered by `woocommerce_variation_set_stock_status` for immediate re-evaluation; reverses when a variation returns to stock (verify original `manage_stock` value is stored, not assumed); change log with timestamp + before/after; dry-run mode; never runs on a public request.

   **Variation Swatches:** hooks `woocommerce_dropdown_variation_attribute_options_html`, returns valid markup; swatch types read term meta on attribute taxonomy; out-of-stock visually marked + selectability matches WC default or a documented setting; JS triggers `change` on underlying `select` then `wc_variation_form` reinit; quick-add submits correct `variation_id` + `attribute_*`; shop-grid AJAX add-to-cart passes `variation_id` and updates fragments; admin term-edit UI saves and reads correctly; Cart Blocks compatibility.

6. **Bugs and Code Errors**
   - PHP 8.1/8.2/8.3 notices, warnings, deprecations
   - Undefined index, null deref, type juggling
   - Wrong returns, swallowed exceptions
   - Race conditions, double-fire AJAX, missing idempotency (Variable Stock Fix, Product Feed rebuild, Restock send)
   - Hook timing (before `init`, before WC, before Elementor)
   - JS errors, unhandled promise rejections
   - Drag-scroll: `pointercancel` and window blur release pointer capture; no stuck-drag

7. **Performance**
   - N+1 in sliders, Infinite Scroll batches, Product Feed
   - Missing transients/object cache (slider results, swatch term meta, cheapest-variation lookup)
   - Autoloaded options that shouldn't be
   - Synchronous external HTTP on front-end
   - Conditional asset loading
   - Unminified production assets
   - Heavy work on `init`/`wp_loaded` instead of Action Scheduler
   - Indexes on Restock Notify table
   - Streaming feed for large catalogs
   - Cached cheapest-variation lookup with proper invalidation

8. **UI / UX**
   - Admin a11y, empty/error states, copy
   - Variable Stock Fix admin: dry-run preview, progress, summary, change log
   - Restock Notify admin: subscriber list per product, manual trigger/cancel, export, RTL Hebrew
   - Product Feed admin: last-rebuild timestamp, manual rebuild, feed URL, product count
   - Front-end: responsive sliders, swatch contrast (light-on-light needs border), AJAX loading indicators, CLS-safe skeletons
   - Settings validation feedback, destructive-action confirmations
   - Elementor controls in Content/Style/Advanced tabs, sensible defaults, responsive controls

9. **Site-Breaking Risk**
   - Activation/deactivation/update fatals
   - Dependency checks (WC, Elementor, PHP, `ext-zlib`, `XMLWriter`)
   - DB migrations gated by version flag, with rollback plan (Restock Notify table)
   - Hooks that could break checkout, cart totals, order emails, product page, Elementor Editor
   - Variable Stock Fix never auto-runs on a page request
   - Restock send debounced against flapping
   - Product Feed regeneration coalesced
   - Infinite Scroll preserves SEO pagination on first paint

## Output Format
Produce a single Markdown report with this exact structure:

```
# Plugin Audit — [plugin name]

## 0. Naming Consistency Audit
### Detected prefix(es): ...
### Detected text domain(s): ...
### Naming Map Table
| Module | Surface | Expected | Actual found | File(s) | Drift? |
[~64+ rows]

### Dead References
[list]

### Rename Migration Plan
[file:line edits + option key migration + hook shim plan]

## 1. Module Behavior Verification
[8 modules: one-sentence summary + matches intended? yes/no/partial]

## 2. Critical (fix immediately)
### [Finding title]
- Module: [name]
- File: `path/to/file.php:LINE`
- Issue: [one sentence]
- User impact: [one sentence]
- Fix:
  ```php
  // before
  ...
  // after
  ...
  ```

## 3. High (fix this sprint)
[same format]

## 4. Medium (fix this quarter)
[same format]

## 5. Low / Polish
[same format]

## 6. WooCommerce Options Verification Table
| Module | Option key | Registered in | Read in | Sanitizer correct? | Default matches? | Works in Blocks checkout? | Status |

## 7. Elementor Widget & Controls Verification Table
| Widget | Control | Default | Front-end effect verified? | Editor preview matches front-end? | Status |

## 8. Module Functional Verification Table
| Module | Behavior under test | Expected | Actual | Needs runtime test? |

## 9. Cross-Module Interaction Checks
| Interaction | Risk | Status |
| Cheapest Default Variation + Variation Swatches | Default selection both fires; one wins deterministically | |
| Variable Stock Fix + Restock Notify | Parent unchecked manage_stock doesn't suppress restock detection on variations | |
| Variation Swatches + Product Slider AJAX add-to-cart | Grid swatch click submits correct variation_id | |
| Infinite Scroll + Product Slider | Slider initializes on appended pages | |
| Product Feed + Variable Stock Fix | Feed reflects parent state correctly after auto-uncheck | |

## 10. Summary Table
| Severity | Count |
| Naming drift | N |
| Critical | N |
| High | N |
| Medium | N |
| Low | N |
```

## Rules
- Run the Naming Consistency Audit FIRST. A drifted name is often the root cause of "this feature stopped working after the refactor."
- Cite file paths and line numbers for every finding. No findings without a location.
- Show before/after code for every fix. No abstract advice.
- If a file or module is clean, say so. Do not invent issues.
- Prioritize by real impact on a live store. Theoretical issues behind admin-only capability checks are Medium, not Critical.
- Do not suggest changes outside the scope of a bug. No "while you're here" refactors EXCEPT renames required for naming consistency — those are in scope and required.
- For every option key rename, include the migration code that copies the old value to the new key and deletes the old, gated by a version flag, run once on plugin update. Silent data loss on rename is unacceptable.
- For every hook rename, include a one-release-cycle compatibility shim that fires both old and new names, with a deprecation notice and removal date.
- If the codebase is too large for one pass, list every file you can see grouped by module, complete the Naming Consistency Audit across all of them (it's cheap and high-leverage), then ask which module to deep-audit first.
- If something can't be verified from static analysis, mark it "needs runtime test" and state exactly what to click or POST.
- For Variable Stock Fix, Product Feed, and Restock Notify, treat any code path that could mass-modify products, regenerate large feeds, or mass-email customers as Critical until proven safe.

Begin by:
1. Listing every plugin file you can see, grouped by module.
2. Running the full Naming Consistency Audit across all modules.
3. Then deep-auditing modules in this order: Variable Stock Fix → Restock Notify → Product Feed → Infinite Scroll → Cheapest Default Variation → Variation Swatches → Product Slider → Category Slider.
```

🎯 Target: Claude (claude.ai or Code)
💡 Added a mandatory Naming Consistency Audit as the first pass — derives expected names from a detected prefix, builds a 64+ row Naming Map across module slugs, classes, hooks, options, asset handles, REST routes, text domains, and JS namespaces, then surfaces dead references and produces a rename migration plan with option-key migrations and hook compatibility shims. This catches the silent-data-loss bug that hits every refactored plugin.

📌 Setup note: paste into Claude (claude.ai or Code) with the plugin folder attached or open. Claude will detect your existing prefix from the main file — if it picks the wrong one, just reply "use prefix `myplug_`" (or whatever yours is) and it will redo the Naming Map against that.