<?php
if (!defined('ABSPATH')) exit;

/**
 * Freeman Digital — Admin Caching Module
 *
 * Implements toggles that were declared in defaults but had no implementation:
 *  - adm_cache_category_list
 *  - adm_cache_user_counts
 *  - adm_cache_author_counts
 *  - auto_convert_myisam (daily check)
 */
class FD_Admin_Cache {
    private $o;

    public function __construct($o) {
        $this->o = $o;

        // Cache user counts on the Users admin page (prevents full wp_users + wp_usermeta scan)
        if (!empty($o['adm_cache_user_counts']) && is_admin()) {
            add_filter('pre_count_users', array($this, 'cached_user_counts'), 10, 3);
        }

        // Cache author post counts in the admin author dropdown
        if (!empty($o['adm_cache_author_counts']) && is_admin()) {
            add_filter('get_usernumposts', array($this, 'cached_author_count'), 10, 4);
        }

        // Cache the WP-admin product category filter dropdown.
        // Uses pre_get_terms (WP 4.6+) to short-circuit the query entirely on cache hits,
        // and get_terms to capture the result for the cache on misses.
        if (!empty($o['adm_cache_category_list']) && is_admin()) {
            add_filter('pre_get_terms', array($this, 'maybe_serve_cat_cache'), 10, 1);
            // Invalidate cache when products or their categories change
            add_action('save_post_product', array($this, 'invalidate_cat_cache'));
            add_action('deleted_term_relationships', array($this, 'invalidate_cat_cache'));
            add_action('created_product_cat', array($this, 'invalidate_cat_cache'));
            add_action('edited_product_cat', array($this, 'invalidate_cat_cache'));
            add_action('delete_product_cat', array($this, 'invalidate_cat_cache'));
        }

        // Auto-convert MyISAM tables to InnoDB on the daily maintenance cron
        if (!empty($o['auto_convert_myisam'])) {
            add_action('fd_daily_maintenance', array($this, 'auto_convert_myisam'));
        }
    }

    /**
     * Cache wp_count_users() result for 12 hours.
     * Filter signature: pre_count_users($result, $strategy, $site_id)
     * Return non-null to short-circuit the expensive query.
     */
    public function cached_user_counts($result, $strategy = 'time', $site_id = null) {
        // If a previous filter already returned data, respect it
        if ($result !== null) return $result;

        $blog_id = $site_id ?: get_current_blog_id();
        $key = 'fd_user_counts_' . $blog_id . '_' . $strategy;
        $cached = get_transient($key);
        if ($cached !== false) return $cached;

        // Compute fresh — but unhook ourselves first to avoid recursion
        remove_filter('pre_count_users', array($this, 'cached_user_counts'), 10);
        // count_users() does the actual work and accepts the same signature
        if (function_exists('count_users')) {
            $fresh = count_users($strategy, $blog_id);
        } else {
            $fresh = null;
        }
        add_filter('pre_count_users', array($this, 'cached_user_counts'), 10, 3);

        if ($fresh) {
            set_transient($key, $fresh, 12 * HOUR_IN_SECONDS);
        }
        return $fresh;
    }

    /**
     * Cache count_user_posts() result per author.
     * Filter signature: get_usernumposts($count, $userid, $post_type, $public_only)
     */
    public function cached_author_count($count, $userid, $post_type = 'post', $public_only = false) {
        $key = 'fd_author_count_' . $userid . '_' . md5(serialize($post_type)) . '_' . ($public_only ? '1' : '0');
        $cached = get_transient($key);
        if ($cached !== false) return (int) $cached;

        // Compute fresh by querying directly (avoid recursion through count_user_posts)
        global $wpdb;
        $where = get_posts_by_author_sql($post_type, true, $userid, $public_only);
        $fresh = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} {$where}");

        set_transient($key, $fresh, 12 * HOUR_IN_SECONDS);
        return $fresh;
    }

    /**
     * Cache the product category dropdown query in wp-admin.
     *
     * Hooks pre_get_terms (WP 4.6+) which fires before any SQL runs.
     * On a cache hit, injects terms directly into the query object — no SQL executes.
     * On a cache miss, hooks get_terms once to capture and store the result.
     *
     * The previous implementation used get_terms_args + terms_clauses but the hit path
     * only stuffed $args['fd_cached_result'] and then did nothing with it — SQL ran anyway.
     */
    public function maybe_serve_cat_cache($query) {
        // Only target the product category dropdown on the products list page
        global $pagenow;
        if ($pagenow !== 'edit.php') return;
        if (!isset($_GET['post_type']) || sanitize_key($_GET['post_type']) !== 'product') return;

        $taxonomies = $query->query_vars['taxonomy'];
        if (!is_array($taxonomies)) $taxonomies = array($taxonomies);
        if (!in_array('product_cat', $taxonomies, true)) return;

        // Build a stable cache key from the query vars
        $key = 'fd_cat_dropdown_' . md5(serialize($query->query_vars));

        $cached = get_transient($key);
        if ($cached !== false && is_array($cached)) {
            // Short-circuit: inject cached terms directly, no SQL runs
            $query->terms = $cached;
            return;
        }

        // Cache miss — hook get_terms once to capture the live result, then unhook ourselves
        // so we can't pollute the cache with a different query's results later in the same request.
        $cb = null;
        $cb = function($terms, $taxos, $args_unused, $q) use ($key, &$cb) {
            if (is_array($terms) && !empty($terms)) {
                set_transient($key, $terms, 6 * HOUR_IN_SECONDS);
            }
            if ($cb) {
                remove_filter('get_terms', $cb, 999);
            }
            return $terms;
        };
        add_filter('get_terms', $cb, 999, 4);
    }

    public function invalidate_cat_cache() {
        // Clear all our category dropdown transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fd_cat_dropdown_%' OR option_name LIKE '_transient_timeout_fd_cat_dropdown_%'");
    }

    /**
     * Daily cron: convert any MyISAM tables to InnoDB
     */
    public function auto_convert_myisam() {
        global $wpdb;
        $tables = $wpdb->get_col($wpdb->prepare(
            "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=%s AND engine='MyISAM' AND table_name LIKE %s",
            DB_NAME,
            $wpdb->esc_like($wpdb->prefix) . '%'
        ));
        foreach ($tables as $t) {
            if (strpos($t, $wpdb->prefix) !== 0) continue;
            $wpdb->query("ALTER TABLE `{$t}` ENGINE=InnoDB");
        }
    }
}
