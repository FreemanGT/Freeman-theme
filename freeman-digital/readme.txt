=== Freeman Digital ===
Contributors: freemandigital
Tags: woocommerce, optimization, performance, database, security, speed, indexes, cleanup, autoload
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.7.3
License: GPLv3 or later

All-in-one WordPress & WooCommerce optimization — database indexes, query tuning, autoload optimizer, security hardening, speed tuning, transient management, and bloat removal.

== Description ==

Freeman Digital is a comprehensive optimization suite built for WordPress + WooCommerce + Elementor stores. It targets the real-world database bottlenecks that slow down WordPress sites: bloated wp_postmeta table scans, autoloaded options exceeding 1MB, WooCommerce transient flooding, missing composite indexes, cart fragments AJAX overhead, and unnecessary admin queries.

Unlike general caching plugins (which this complements — works great alongside WP Rocket), Freeman Digital focuses on what happens *before* the cache: raw SQL query performance, database structure, and WordPress overhead removal.

**10 Modules, 60+ Toggles:**

= Query Optimization (6 toggles) =
* Remove SQL_CALC_FOUND_ROWS (front-end + admin separately)
* Remove CAST on wp_postmeta — the single biggest query optimization for WooCommerce
* Optimize GROUP BY → DISTINCT (avoids expensive sort operations)
* Remove private post check for faster admin browsing
* Auto-strip DISTINCT on LIMIT 1 queries

= WooCommerce (19 toggles) =
* Remove dashboard widget, marketplace nag, connect nag, marketing hub
* Optimize DELETE queries on wp_options (prevents 3-minute lockups)
* Cache hasProducts check (saves 10-70 seconds on large stores)
* Fix redundant OR in product attributes lookup table
* Cache post type counts, defer term counting
* Auto-clean Action Scheduler nightly
* Disable cart fragments, limit WC scripts to WC pages only
* Disable password strength meter (saves ~800KB)
* Phone-home blocker with custom allowlist
* Disable WC Admin AJAX bloat, setup wizard redirect

= Database Indexes (28 indexes) =
* Composite indexes on wp_postmeta (the #1 bottleneck table)
* wp_posts covering indexes for archives, dates, sitemaps, parent lookups
* Taxonomy/term relationship indexes for category/attribute filtering
* wp_options autoload index
* WooCommerce order items, sessions, download permissions
* Action Scheduler queue optimization indexes
* wp_usermeta composite index for user meta lookups

= Security Hardening (12 toggles) =
* Disable XML-RPC, file editing, application passwords
* Hide WordPress version, author enumeration, login error details
* Disable pingbacks, remove RSD/WLW links
* Security headers (X-Content-Type-Options, X-Frame-Options, etc)
* REST API restriction options

= Speed Optimization (14 toggles) =
* Disable emojis, embeds, jQuery Migrate
* Remove query strings, Dashicons, block editor frontend styles
* Heartbeat API control (reduce/disable)
* Post revision limiting
* Header cleanup (generator, shortlink, REST link, feed links)

= Database Cleanup (14 toggles + auto-scheduler) =
* Revisions, auto-drafts, trashed posts/comments, spam
* Expired transients + WooCommerce-specific transient cleanup
* Expired WC sessions, Action Scheduler logs
* Orphaned postmeta, commentmeta, termmeta
* Expired user session tokens
* OPTIMIZE TABLE on core + WooCommerce tables
* Nightly automatic cleanup

= Autoload Optimizer (NEW) =
* Audit top 30 largest autoloaded options with risk indicators
* Auto-fix large options (set autoload=no for options >100KB)
* MyISAM → InnoDB table conversion
* Daily autoload health check (auto-fixes if >5MB)

= Bloat Removal (3 toggles) =
* Disable comments system entirely
* Disable Gutenberg block editor (for Elementor-only sites)
* Remove DNS prefetch hints

= Tools =
* Export/import settings as JSON
* Full changelog

== Changelog ==

= 1.7.3 =
* SECURITY: `wc_optimize_delete_options` DELETE rewriter now routes the captured LIKE pattern through `$wpdb->prepare()` with a `%s` placeholder instead of `esc_sql()`. No exploit vector was known (the LIKE patterns are always plugin/core-generated, never user input), but the prepared-statement form is the correct defensive pattern and removes a long-running audit flag.
* FEATURE: Profiler retention is now configurable. New "Retention" card on the Profiler tab exposes `prof_retention_days` (1–365, default 7) and `prof_max_rows` (1,000–1,000,000, default 50,000). `prune_old_rows()` clamps both values to the safe range so a corrupt option can't wipe the table or let it grow unbounded.
* CORRECTNESS: `.maintenance` file write / unlink failures during deep reindex are now recorded to the Activity Log as `maintenance_mode_enter_failed` / `maintenance_mode_leave_failed` with the underlying PHP error message, instead of being silently swallowed. The reindex itself still proceeds on read-only filesystems — it just proceeds without a maintenance window, which the operator can now see.

= 1.7.2 =
* CORRECTNESS: Fixed `fe_fetchpriority_lcp` which was setting high priority on the first image rendered (usually the logo or sidebar thumbnail), not the actual LCP image. It now only fires when `fe_lcp_image_url` is set and the image URL matches.
* CORRECTNESS: Fixed `fe_conditional_cf7` to detect Contact Form 7 shortcodes rendered through Elementor widgets and sidebar widgets. Previously only scanned `$post->post_content`, silently breaking forms placed via Elementor.
* CORRECTNESS: Fixed `fe_cleanup_elementor` to detect Elementor Pro global header/footer documents before dequeueing Elementor assets. Non-Elementor posts with Elementor-built headers/footers no longer lose styling.
* CORRECTNESS: Narrowed `fe_defer_elementor_js` to only defer safe handles (swiper, share-link, elementor-waypoints). `elementor-frontend` and `elementor-pro-frontend` are no longer deferred because defer breaks widget init order on custom widgets.
* CORRECTNESS: Removed the half-implemented `wc_ajax_attribute_edit` feature. The GET-path term limit was hiding already-assigned values from product edit dropdowns.
* CORRECTNESS: Added `auto_ceiling_mb` setting (default 2 MB) to fix the mismatch between UI copy ("fix options > 100 KB") and runtime ("only when total autoload > 2 MB"). The ceiling is now configurable.
* CORRECTNESS: `fe_lazy_iframes` now skips the first iframe on the page so hero videos don't get lazy-loaded.
* CORRECTNESS: `qo_optimize_groupby` now leaves GROUP BY alone when the query joins to an unknown table (not posts/postmeta/terms*).
* CORRECTNESS: `adm_cache_category_list` cache-miss handler now unhooks itself after one invocation so it can't pollute the cache with a different query's results.
* CORRECTNESS: Import/settings sanitize now rejects non-whitelisted enum values for `spd_heartbeat_control`.
* CORRECTNESS: Unified `remove_sort` and `clear_orderby` filter priorities to `PHP_INT_MAX`.
* PERFORMANCE: Early-return in `wc_optimize_delete_options` and `wc_optimize_attr_lookup` query filters (cheap `$sql[0]` check) eliminates ~500µs per admin page load.
* PERFORMANCE: `run_cleanup` now chunks revision, auto-draft, trash, spam, and trashed-comment DELETEs in 5,000-row batches. No more multi-minute locks on large stores.
* PERFORMANCE: `OPTIMIZE TABLE` removed from the automatic nightly cleanup path (was locking InnoDB tables for minutes). Now runs only when explicitly triggered via the new "Optimize Tables Now" button, or when `db_optimize_in_cron` (new, default OFF) is enabled.
* PERFORMANCE: Deep reindex now optionally puts the site in maintenance mode for the duration of the ALTER TABLE. Controlled by `idx_enable_maintenance_mode` (default ON).
* PERFORMANCE: Profiler now writes slow queries as a single multi-row INSERT at shutdown instead of N sequential INSERTs.
* PERFORMANCE: Replaced the `@font-face` output-buffer rewrite (which wrapped every request) with a narrower, lower-overhead approach that only touches enqueued stylesheets.
* PERFORMANCE: `fd_has_products` transient now invalidated on `save_post_product` / `deleted_post`.
* PERFORMANCE: Months-dropdown cache now also invalidated on `wp_trash_post` and `bulk_edit_posts`.
* SECURITY: Removed deprecated `X-XSS-Protection` header (OWASP recommends against it — known to introduce XSS bugs in older browsers).
* SECURITY: Security headers now scoped to frontend requests only (admin, AJAX, and REST no longer receive `X-Frame-Options` / `Referrer-Policy` etc.).
* SECURITY: Flipped default for `spd_remove_query_strings` to OFF for new installs. Existing users keep their setting. This option breaks cache invalidation on plugin/theme updates unless the CDN purges on deploy.
* SECURITY: Phone-home blocker now pre-seeds allowlist with common legitimate APIs (Stripe, Google APIs, woocommerce.com) so new users don't accidentally break checkout.
* FEATURE: New Activity Log (capped at 200 entries) records every destructive operation with user, timestamp, rows affected, and metadata. Surfaced in new "Activity" admin tab with a "Clear log" button.
* FEATURE: Destructive UI buttons (deep reindex, drop indexes, fix autoload, MyISAM convert, optimize tables) now require confirming "I have a recent backup" before the action enables.
* FEATURE: Database user's DDL capability (CREATE / ALTER) pre-flight checked at dashboard load. Shows red banner on managed hosts that lack privileges.
* FEATURE: WP-CLI commands: `wp fd cleanup`, `wp fd reindex`, `wp fd autoload`, `wp fd profiler`, `wp fd export`, `wp fd import`.
* FEATURE: Public extension hooks — `fd/before_run_cleanup`, `fd/after_run_cleanup`, `fd/cleanup_batch_size` filter, `fd/protected_autoload_options` filter.
* QUALITY: Full i18n pass — user-facing strings wrapped in `__()` / `esc_html__()`. Empty `languages/freeman-digital.pot` shipped for translators.
* QUALITY: WooCommerce minimum version (6.0) now enforced at runtime, not just in the plugin header.
* QUALITY: PHPUnit smoke tests + GitHub Actions CI matrix (PHP 7.4 / 8.1 / 8.3) added.
* QUALITY: Admin JS and CSS minified in the release zip (`admin.min.js`, `admin.min.css`) when `!SCRIPT_DEBUG`.
* FIX: `readme.txt` Stable tag bumped to match the plugin header version.

= 1.7.1 =
* Fixed adm_cache_category_list (Cache Category Dropdown) — the cache was being written correctly but never actually served. The previous implementation tagged $args and registered a get_terms save hook, but the cache-hit path did nothing and SQL ran every time. Replaced with pre_get_terms (WP 4.6+) which short-circuits the query entirely on a hit. Added invalidation on created_product_cat, edited_product_cat, and delete_product_cat actions (previously only save_post_product and deleted_term_relationships).
* Fixed 3 remaining DB_NAME raw string interpolations in class-fd-admin.php (dashboard db_size, dashboard myisam count, autoload tab myisam list) — all now use $wpdb->prepare().
* Fixed 3 Quick Action hrefs in the dashboard missing esc_url() around admin_url() calls.
* Fixed the one remaining unescaped SHOW TABLES LIKE in db_optimize_tables — now uses $wpdb->prepare() consistently with the rest of class-fd-database.php.
* Fixed adm_cache_months_dropdown registering its hooks on all requests (front-end, REST) — added is_admin() guard. These filters only ever fire in admin; registering them site-wide was pointless overhead.
* Fixed Profiler: wpdb->save_queries = true was being set on every request (including live customer browsing) while the profiler was active, saving all visitor queries into PHP memory. Now restricted to is_admin() and DOING_AJAX requests only.
* Fixed Profiler: table grew unboundedly with no automatic cleanup. Added prune_old_rows() static method (hooked to fd_daily_maintenance) that deletes rows older than 7 days and enforces a hard cap of 50,000 rows.
* Fixed Profiler: get_aggregated() had one non-parameterized WHERE clause (component_type != 'theme'). Now fully parameterized with a %s placeholder for consistency.
* Fixed limit_attribute_terms() in FD_WooCommerce: replaced fragile debug_backtrace() save-context detection (would silently break if WooCommerce renames internal methods) with a simple POST/DOING_AJAX check.



= 1.7.0 =
* CRITICAL FIX: Settings now persist correctly across tabs. Previously saving one tab would reset all other tabs to their defaults because the form only renders the active tab's fields. The sanitize callback now merges incoming data with existing saved options.
* Reviewed "Index WP MySQL For Speed" plugin for overlap analysis. Our indexes are complementary — we cover 12 tables they don't touch (term_relationships, term_taxonomy, terms, woocommerce_order_items, woocommerce_sessions, woocommerce_downloadable_product_permissions, actionscheduler_actions x2, wc_product_meta_lookup). They do deeper PRIMARY KEY restructuring on meta tables; we add secondary indexes for broader coverage. Both plugins can run together safely without conflicts.
* Version bump to 1.7.0

= 1.1.0 =
* Renamed plugin to Freeman Digital
* NEW: Autoload Optimizer tab — audit top 30 largest autoloaded options, auto-fix options >100KB, MyISAM→InnoDB converter
* NEW: WooCommerce transient cleanup (wc_var_prices, wc_product_children, wc_related, wc_term_counts)
* NEW: Expired user session token cleanup
* NEW: WC Admin AJAX bloat reducer (disables wc-admin feature)
* NEW: WC setup wizard disable
* NEW: Orphaned commentmeta cleanup
* NEW: Daily autoload health check — auto-fixes if autoload exceeds 5MB
* NEW: wp_options autoload composite index
* NEW: wp_posts parent composite index (speeds up WC variation lookups)
* NEW: wp_postmeta composite covering index (meta_key, post_id, meta_value)
* NEW: Action Scheduler hook composite index
* NEW: Dashboard shows autoload size and count, MyISAM table warnings
* Full code audit — every toggle verified with correct WordPress hooks
* Revision cleanup now properly deletes associated term_relationships and postmeta
* Trashed post cleanup now properly deletes associated data
* Version bump to 1.1.0

= 1.0.0 =
* Initial release
* Query optimizer (SQL_CALC_FOUND_ROWS, CAST removal, GROUP BY optimization)
* WooCommerce admin bloat removal and performance optimizations
* 25 database indexes for WordPress core and WooCommerce
* Security hardening (12 toggles)
* Speed optimization (14 toggles)
* Database cleanup with auto-scheduler
* Bloat removal for Elementor-focused sites
* Import/export settings
