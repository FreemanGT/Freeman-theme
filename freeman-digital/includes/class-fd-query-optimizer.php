<?php
if (!defined('ABSPATH')) exit;

class FD_Query_Optimizer {
    private $o;

    public function __construct($o) {
        $this->o = $o;

        // Register all hooks unconditionally (setting checks happen inside callbacks).
        // The old code checked is_admin() at constructor time, which evaluated BEFORE WP
        // could detect REST API context. REST requests aren't "admin" but aren't "front" either,
        // so forcing no_found_rows on REST queries broke REST consumers that relied on found_posts.

        if (!empty($o['qo_no_found_rows_front'])) {
            add_action('pre_get_posts', array($this, 'no_found_rows'), 100);
        }
        if (!empty($o['qo_no_found_rows_admin'])) {
            add_action('pre_get_posts', array($this, 'no_found_rows_admin'), 100);
        }
        if (!empty($o['qo_remove_sort_order'])) {
            // Unified to PHP_INT_MAX so both filters run at the end of the filter chain.
            add_action('pre_get_posts', array($this, 'remove_sort'), PHP_INT_MAX);
            add_filter('posts_orderby', array($this, 'clear_orderby'), PHP_INT_MAX, 2);
        }
        if (!empty($o['qo_remove_cast'])) {
            add_filter('posts_where', array($this, 'remove_cast'), PHP_INT_MAX);
            add_filter('posts_where_request', array($this, 'remove_cast'), PHP_INT_MAX);
        }
        if (!empty($o['qo_optimize_groupby'])) {
            add_filter('posts_clauses', array($this, 'optimize_groupby'), PHP_INT_MAX - 5, 2);
        }
        if (!empty($o['qo_remove_private_check'])) {
            add_filter('posts_where', array($this, 'remove_private'), PHP_INT_MAX);
        }

        // Always: remove DISTINCT on LIMIT 1 (safe, no setting)
        add_filter('posts_clauses', array($this, 'strip_distinct_limit1'), PHP_INT_MAX, 2);

        if (!empty($o['adm_cache_months_dropdown']) && is_admin()) {
            add_filter('media_library_months_with_files', array($this, 'cache_media_months'));
            add_filter('pre_months_dropdown_query', array($this, 'cache_post_months'), 10, 2);
        }
    }

    /**
     * Detect whether the current request is a REST API request.
     * REST requests aren't admin and aren't front-end — they need to be excluded from
     * optimizations that break REST consumers (like forcing no_found_rows).
     */
    private function is_rest_request() {
        if (defined('REST_REQUEST') && REST_REQUEST) return true;
        if (empty($_SERVER['REQUEST_URI'])) return false;
        $rest_prefix = trailingslashit(rest_get_url_prefix());
        return strpos($_SERVER['REQUEST_URI'], $rest_prefix) !== false;
    }

    // -- SQL_CALC_FOUND_ROWS --
    public function no_found_rows($q) {
        // Guard at callback time: skip admin AND REST requests
        if (is_admin() || $this->is_rest_request()) return;
        if ($q->is_main_query()) $q->set('no_found_rows', true);
    }
    public function no_found_rows_admin($q) {
        if ($q->is_main_query() && !$q->get('no_found_rows')) {
            $q->set('no_found_rows', true);
        }
    }

    // -- SORT ORDER --
    public function remove_sort($q) {
        if (is_admin() || $this->is_rest_request()) return;
        if ($q->is_main_query() && !isset($_GET['orderby'])) {
            $q->set('orderby', 'none');
        }
    }
    public function clear_orderby($orderby, $q) {
        if (is_admin() || $this->is_rest_request()) return $orderby;
        if ($q->is_main_query() && !isset($_GET['orderby'])) return '';
        return $orderby;
    }

    // -- CAST REMOVAL --
    // Matches: CAST(wp_postmeta.meta_value AS CHAR), CAST(mt1.meta_value AS SIGNED), etc
    public function remove_cast($where) {
        return preg_replace('/CAST\(\s*([a-zA-Z0-9_.]+\.meta_value)\s+AS\s+\w+\s*\)/i', '$1', $where);
    }

    // -- GROUP BY → DISTINCT --
    public function optimize_groupby($clauses, $q) {
        global $wpdb;
        if (empty(trim($clauses['groupby']))) return $clauses;

        $join = trim($clauses['join']);
        $has_join = $join !== '';
        $fields = trim($clauses['fields']);

        // No joins, no aggregates, only wp_posts columns → remove GROUP BY entirely
        if (!$has_join && stripos($fields, 'postmeta') === false && !preg_match('/\b(COUNT|SUM|AVG|MAX|MIN)\s*\(/i', $fields)) {
            $clauses['groupby'] = '';
            $clauses['distinct'] = '';
            return $clauses;
        }

        // Conservative guard: if the JOIN references any table we don't recognize
        // (not wp_posts, wp_postmeta, or prefix_term*), leave GROUP BY alone. Plugins
        // like WPML, Polylang, WooCommerce HPOS, and custom-post-type extenders join
        // against their own tables where DISTINCT is not semantically equivalent.
        if ($has_join) {
            $known_tables = array(
                $wpdb->posts,
                $wpdb->postmeta,
                $wpdb->terms,
                $wpdb->term_relationships,
                $wpdb->term_taxonomy,
                $wpdb->termmeta,
            );
            // Extract table identifiers referenced in the JOIN clause.
            if (preg_match_all('/(?:JOIN|FROM)\s+`?([a-zA-Z0-9_]+)`?/i', $join, $matches)) {
                foreach ($matches[1] as $referenced) {
                    $ok = false;
                    foreach ($known_tables as $k) {
                        if ($referenced === $k) { $ok = true; break; }
                    }
                    // Also allow aliases of meta tables (mt1, mt2, …) and term_* variants
                    if (!$ok && preg_match('/^mt\d+$/', $referenced)) $ok = true;
                    if (!$ok && strpos($referenced, $wpdb->prefix . 'term') === 0) $ok = true;
                    if (!$ok) {
                        // Unknown join — leave the query clauses untouched.
                        return $clauses;
                    }
                }
            }
        }

        // Has joins but only selecting wp_posts.* → replace GROUP BY with DISTINCT
        if ($has_join && stripos($fields, $wpdb->postmeta . '.') === false && !preg_match('/\b(COUNT|SUM|AVG|MAX|MIN)\s*\(/i', $fields)) {
            $clauses['groupby'] = '';
            if (stripos($clauses['distinct'], 'DISTINCT') === false) {
                $clauses['distinct'] = 'DISTINCT';
            }
        }
        return $clauses;
    }

    // -- PRIVATE POST CHECK --
    public function remove_private($where) {
        if (is_admin() || $this->is_rest_request()) return $where;
        global $wpdb;
        // Remove: OR (wp_posts.post_status = 'private' AND wp_posts.post_author = N)
        return preg_replace("/\s*OR\s*\(\s*{$wpdb->posts}\.post_status\s*=\s*'private'\s+AND\s+{$wpdb->posts}\.post_author\s*=\s*\d+\s*\)/i", '', $where);
    }

    // -- DISTINCT on LIMIT 1 --
    public function strip_distinct_limit1($clauses, $q) {
        $lim = trim($clauses['limits']);
        if ($lim === 'LIMIT 0, 1' || $lim === 'LIMIT 1') {
            $clauses['distinct'] = str_ireplace('DISTINCT', '', $clauses['distinct']);
            $clauses['groupby'] = '';
        }
        return $clauses;
    }

    // -- MONTHS DROPDOWN CACHE --
    // Uses transients (auto-expiring) instead of options to avoid permanent rows in wp_options.
    public function cache_media_months($months) {
        if (null !== $months) return $months;
        return $this->cached_months('attachment');
    }
    public function cache_post_months($months, $pt) {
        if (false !== $months) return $months;
        if (isset($_GET['post_status'])) return false;
        return $this->cached_months($pt);
    }
    private function cached_months($pt) {
        global $wpdb;
        $key = "fd_months_{$pt}";
        $m = get_transient($key);
        if (is_array($m)) return $m;
        $extra = ($pt !== 'attachment') ? " AND post_status NOT IN ('auto-draft','trash')" : '';
        $m = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT YEAR(post_date) AS year, MONTH(post_date) AS month FROM {$wpdb->posts} WHERE post_type=%s {$extra} ORDER BY post_date DESC", $pt));
        set_transient($key, $m, 6 * HOUR_IN_SECONDS);
        return $m;
    }

    /**
     * Invalidate month caches when posts are published/updated/trashed/deleted.
     * Called from FD_Core's save_post / deleted_post / wp_trash_post / untrashed_post actions.
     */
    public static function invalidate_months_cache($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        $post_type = get_post_type($post_id);
        if ($post_type) {
            delete_transient("fd_months_{$post_type}");
        }
    }

    /**
     * Bulk invalidation: bulk_edit_posts passes the array of IDs. We derive the set of
     * post types touched and flush each one's transient once.
     */
    public static function invalidate_months_cache_bulk($updated_ids = array(), $shared_post_data = array()) {
        if (!is_array($updated_ids)) return;
        $seen = array();
        foreach ($updated_ids as $id) {
            $type = get_post_type($id);
            if ($type && !isset($seen[$type])) {
                delete_transient("fd_months_{$type}");
                $seen[$type] = true;
            }
        }
    }
}
