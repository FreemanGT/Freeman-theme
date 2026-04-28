<?php
if (!defined('ABSPATH')) exit;

class FD_WooCommerce {
    const MIN_WC_VERSION = '6.0';

    private $o;
    public function __construct($o) {
        $this->o = $o;
        if (!class_exists('WooCommerce')) return;
        // Version guard: several of our filters target WC-7+ admin APIs. Sites still on
        // WC < 6.0 should skip our filters and surface an admin notice rather than silently
        // mangle queries or reach for APIs that don't exist.
        if (defined('WC_VERSION') && version_compare(WC_VERSION, self::MIN_WC_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wc_version_notice'));
            return;
        }

        // Dashboard bloat
        if (!empty($o['wc_remove_dashboard_widget']))
            add_action('wp_dashboard_setup', function(){ remove_meta_box('woocommerce_dashboard_status','dashboard','normal'); remove_meta_box('woocommerce_dashboard_recent_reviews','dashboard','normal'); }, 40);
        if (!empty($o['wc_remove_marketplace_nag']))
            add_filter('woocommerce_allow_marketplace_suggestions', '__return_false');
        if (!empty($o['wc_remove_connect_nag']))
            add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');
        if (!empty($o['wc_disable_marketing_hub']))
            add_filter('woocommerce_admin_features', function($f){ return array_diff($f, array('marketing','coupons')); });
        if (!empty($o['wc_disable_setup_wizard']))
            add_filter('woocommerce_prevent_automatic_wizard_redirect', '__return_true');
        if (!empty($o['wc_disable_admin_ajax_bloat'])) {
            // The old 'woocommerce_admin_disabled' filter was removed in WC 6.0.
            // Modern approach: disable the WC Admin features, tracker, marketplace suggestions,
            // and the dashboard notes that cause the heaviest admin-ajax activity.
            add_filter('woocommerce_admin_features', array($this, 'disable_wc_admin_features'));
            add_filter('woocommerce_allow_marketplace_suggestions', '__return_false');
            add_filter('woocommerce_show_marketplace_suggestions', '__return_false');
            add_filter('woocommerce_apply_tracking', '__return_false');
            add_filter('woocommerce_admin_disabled_pages', array($this, 'disable_wc_admin_pages'));
            add_filter('woocommerce_admin_get_feature_config', array($this, 'disable_feature_config'));
            // Remove WC marketing hub, inbox notes, activity panel
            add_filter('woocommerce_admin_get_notes', '__return_empty_array');
        }

        // Custom meta box removal
        if (!empty($o['wc_remove_custom_meta_box'])) {
            add_action('admin_menu', function(){ foreach(get_post_types() as $pt) remove_meta_box('postcustom',$pt,'normal'); });
            add_filter('is_protected_meta', '__return_true', 10, 2);
        }

        // Query optimizations
        if (!empty($o['wc_optimize_delete_options']))
            add_filter('query', array($this, 'optimize_deletes'));
        if (!empty($o['wc_fix_onboarding']))
            add_filter('woocommerce_admin_onboarding_has_products', array($this, 'cached_has_products'));
        if (!empty($o['wc_optimize_attr_lookup']))
            add_filter('query', array($this, 'fix_attr_lookup'));
        if (!empty($o['wc_remove_variation_calc']))
            add_filter('woocommerce_ajax_variation_threshold', function(){ return 1; }, 10, 2);

        // Caching
        if (!empty($o['wc_cache_post_counts']))
            add_filter('wp_count_posts', array($this, 'cache_counts'), 10, 3);

        // Invalidate the `fd_has_products` transient whenever a product changes so the
        // cached answer doesn't lie to WC admin notices for up to 24 hours.
        if (!empty($o['wc_fix_onboarding'])) {
            add_action('save_post_product', array(__CLASS__, 'invalidate_has_products_cache'));
            add_action('delete_post', array(__CLASS__, 'maybe_invalidate_has_products_cache'));
            add_action('trashed_post', array(__CLASS__, 'maybe_invalidate_has_products_cache'));
            add_action('untrashed_post', array(__CLASS__, 'maybe_invalidate_has_products_cache'));
        }
        // Defer term counting ONLY during bulk admin operations (import, bulk edit),
        // not permanently on every request. The previous version called defer(true) on every
        // init without ever calling defer(false), silently breaking category counts.
        if (!empty($o['wc_defer_term_counting'])) {
            add_action('wp_loaded', array($this, 'maybe_defer_term_counting'), 5);
            add_action('shutdown', array($this, 'restore_term_counting'), 99);
        }
        // Note: Action Scheduler cleanup is handled centrally by FD_Database::run_cleanup()
        // via the db_clean_as_logs toggle. Removed duplicate handler here to avoid inconsistent limits.

        // Frontend
        if (!empty($o['wc_disable_cart_fragments']))
            add_action('wp_enqueue_scripts', function(){ if(!is_admin()) wp_dequeue_script('wc-cart-fragments'); }, 11);
        if (!empty($o['wc_limit_scripts_non_woo']))
            add_action('wp_enqueue_scripts', array($this, 'limit_scripts'), 99);
        if (!empty($o['wc_disable_password_meter']))
            add_action('wp_enqueue_scripts', function(){ if(!is_admin()){ wp_dequeue_script('zxcvbn-async'); wp_dequeue_script('wc-password-strength-meter'); }}, 100);

        // Phone home
        if (!empty($o['wc_stop_phone_home']))
            add_filter('pre_http_request', array($this, 'block_phone'), 10, 3);

        // NOTE: 'wc_ajax_attribute_edit' was removed in 1.7.2. The GET-path filter was
        // hiding already-assigned attribute terms from the product edit dropdown, and
        // the "AJAX search" side never had a real Select2 UI hooked to the endpoint.
        // See readme.txt changelog for details.
    }

    // Rewrite slow DELETE on wp_options to use index-friendly subquery.
    // Early-return on the cheapest possible check so the hot path adds ~0 overhead
    // on the many non-DELETE queries that run through the `query` filter.
    public function optimize_deletes($sql) {
        if (!isset($sql[0]) || ($sql[0] !== 'D' && $sql[0] !== 'd')) return $sql;
        global $wpdb;
        if (stripos($sql,'DELETE')===0 && stripos($sql,$wpdb->options)!==false && stripos($sql,'option_name LIKE')!==false) {
            if (preg_match("/DELETE\s+FROM\s+`?{$wpdb->options}`?\s+WHERE\s+option_name\s+LIKE\s+'([^']+)'/i",$sql,$m)) {
                // Route the captured LIKE pattern through $wpdb->prepare so `%`/`_` wildcards
                // in the pattern stay wildcards and anything else is placeholder-escaped.
                // Previous implementation used esc_sql() which quote-escapes but doesn't
                // treat LIKE wildcards specially — safe in practice (WP generates these
                // patterns, not users) but defensive hardening is cheap here.
                return $wpdb->prepare(
                    "DELETE FROM `{$wpdb->options}` WHERE option_id IN (SELECT option_id FROM (SELECT option_id FROM `{$wpdb->options}` WHERE option_name LIKE %s) AS tmp)",
                    $m[1]
                );
            }
        }
        return $sql;
    }

    public function cached_has_products() {
        $c = get_transient('fd_has_products');
        if ($c !== false) return (bool)$c;
        global $wpdb;
        $has = $wpdb->get_var("SELECT 1 FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' LIMIT 1");
        set_transient('fd_has_products', $has ? 1 : 0, DAY_IN_SECONDS);
        return (bool)$has;
    }

    public static function invalidate_has_products_cache() {
        delete_transient('fd_has_products');
    }

    public static function maybe_invalidate_has_products_cache($post_id) {
        if (get_post_type($post_id) === 'product') {
            delete_transient('fd_has_products');
        }
    }

    public function wc_version_notice() {
        if (!current_user_can('activate_plugins')) return;
        echo '<div class="notice notice-warning"><p><strong>Freeman Digital:</strong> ' .
            esc_html(sprintf(__('WooCommerce %s or newer is required. The WooCommerce optimization module has been disabled for safety.', 'freeman-digital'), self::MIN_WC_VERSION)) .
            '</p></div>';
    }

    public function fix_attr_lookup($sql) {
        // Early-return: the rewrite only applies to SELECT queries against the lookup table.
        if (!isset($sql[0]) || ($sql[0] !== 'S' && $sql[0] !== 's')) return $sql;
        global $wpdb;
        $tbl = $wpdb->prefix . 'wc_product_attributes_lookup';
        if (stripos($sql,$tbl)!==false && preg_match('/product_id\s*=\s*(\d+)\s+OR\s+product_or_parent_id\s*=\s*\1/i',$sql)) {
            return preg_replace('/product_id\s*=\s*(\d+)\s+OR\s+product_or_parent_id\s*=\s*\1/i','product_or_parent_id = $1',$sql);
        }
        return $sql;
    }

    public function cache_counts($counts, $type, $perm) {
        if ($type==='shop_order') return $counts;
        $key = "fd_pc_{$type}";
        $c = get_transient($key);
        if ($c !== false) return $c;
        remove_filter('wp_count_posts', array($this,'cache_counts'), 10);
        $counts = wp_count_posts($type, $perm);
        add_filter('wp_count_posts', array($this,'cache_counts'), 10, 3);
        set_transient($key, $counts, 12*HOUR_IN_SECONDS);
        return $counts;
    }

    public function limit_scripts() {
        if (is_admin()) return;
        if (function_exists('is_woocommerce') && (is_woocommerce()||is_cart()||is_checkout()||is_account_page())) return;
        wp_dequeue_style('woocommerce-general'); wp_dequeue_style('woocommerce-layout');
        wp_dequeue_style('woocommerce-smallscreen'); wp_dequeue_script('wc-add-to-cart');
        wp_dequeue_script('wc-cart-fragments'); wp_dequeue_script('woocommerce');
        wp_dequeue_script('jquery-blockui'); wp_dequeue_script('jquery-placeholder'); wp_dequeue_script('jquery-cookie');
    }

    /**
     * Disable the heaviest WC Admin features that cause admin-ajax bloat.
     * Called via woocommerce_admin_features filter (current API in WC 7+).
     */
    public function disable_wc_admin_features($features) {
        $disable = array(
            'activity-panels',
            'analytics',
            'remote-inbox-notifications',
            'remote-free-extensions',
            'marketing',
            'mobile-app-banner',
            'navigation',
            'onboarding',
            'onboarding-tasks',
            'shipping-label-banner',
            'store-alerts',
            'transient-notices',
            'wc-pay-promotion',
            'wc-pay-welcome-page',
            'experimental-fashion-sample-products',
        );
        return array_values(array_diff($features, $disable));
    }

    public function disable_wc_admin_pages($pages) {
        return array_merge((array)$pages, array('marketing', 'marketing-overview'));
    }

    public function disable_feature_config($config) {
        if (!is_array($config)) return $config;
        $disable_keys = array('activity-panels', 'remote-inbox-notifications', 'marketing', 'shipping-label-banner');
        foreach ($disable_keys as $k) {
            if (isset($config[$k])) $config[$k] = false;
        }
        return $config;
    }

    /**
     * Defer term counting only during bulk operations where many posts/terms change,
     * then restore it at shutdown so normal requests don't leak the deferred state.
     */
    public function maybe_defer_term_counting() {
        if (defined('DOING_CRON') && DOING_CRON) return;

        // Only defer on heavy operations where hundreds of term counts would be recalculated
        $should_defer = false;

        // WooCommerce import
        if (isset($_GET['page']) && in_array($_GET['page'], array('product_importer', 'product_exporter'))) {
            $should_defer = true;
        }

        // Bulk edit on products list
        if (isset($_REQUEST['action']) && isset($_REQUEST['action2'])) {
            $bulk = $_REQUEST['action'] !== '-1' ? $_REQUEST['action'] : $_REQUEST['action2'];
            if (in_array($bulk, array('edit', 'trash', 'delete', 'untrash'))) {
                $should_defer = true;
            }
        }

        // Action scheduler hook handling product updates (WC webhooks etc)
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'as_async_request_queue_runner') {
            $should_defer = true;
        }

        if ($should_defer) {
            wp_defer_term_counting(true);
        }
    }

    /**
     * Restore term counting at shutdown so counts are committed for the request.
     */
    public function restore_term_counting() {
        // wp_defer_term_counting(false) will process any pending updates
        wp_defer_term_counting(false);
    }

    public function block_phone($pre, $args, $url) {
        if (!is_admin()) return $pre;
        if (strpos($url,'wordpress.org')!==false || strpos($url,home_url())!==false) return $pre;
        $al = $this->o['wc_phone_home_allowlist'];
        if (!empty($al)) {
            foreach (array_filter(array_map('trim',explode("\n",$al))) as $p) {
                // Order of operations: substitute wildcards with placeholder, escape, then restore as regex.
                // The naive str_replace('*', '.*', preg_quote($p)) FAILS because preg_quote escapes * to \*,
                // so the subsequent str_replace has no * to find.
                $placeholder = '__FD_WILDCARD__';
                $rx = str_replace('*', $placeholder, $p);
                $rx = preg_quote($rx, '/');
                $rx = str_replace($placeholder, '.*', $rx);
                if (preg_match('/'.$rx.'/i',$url)) return $pre;
            }
        }
        return new WP_Error('fd_blocked','Blocked by Freeman Digital: '.$url);
    }
}

