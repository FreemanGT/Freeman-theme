<?php
if (!defined('ABSPATH')) exit;

class FD_Core {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $o = self::opts();
        // Remove FD_Indexes - it's just a static class wrapper, no instance needed
        if (is_admin()) new FD_Admin();
        new FD_Query_Optimizer($o);
        new FD_WooCommerce($o);
        new FD_Security($o);
        new FD_Speed($o);
        new FD_Database($o);
        new FD_Bloat($o);
        new FD_Autoload($o);
        new FD_Frontend($o);
        new FD_Profiler($o);
        new FD_Admin_Cache($o);

        // Invalidate month cache on any event that changes the set of published post dates.
        // Before 1.7.2 we only listened to save_post/deleted_post, missing trash + bulk-edit flows.
        add_action('save_post', array('FD_Query_Optimizer', 'invalidate_months_cache'));
        add_action('deleted_post', array('FD_Query_Optimizer', 'invalidate_months_cache'));
        add_action('wp_trash_post', array('FD_Query_Optimizer', 'invalidate_months_cache'));
        add_action('untrashed_post', array('FD_Query_Optimizer', 'invalidate_months_cache'));
        add_action('bulk_edit_posts', array('FD_Query_Optimizer', 'invalidate_months_cache_bulk'), 10, 2);

        // One-time cleanup: remove legacy fd_months_* option rows from pre-v1.7.0 versions
        // (they were stored as options instead of transients and never cleaned up)
        if (!get_option('fd_legacy_months_cleaned')) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'fd_months_%' AND option_name NOT LIKE '_transient_%'");
            update_option('fd_legacy_months_cleaned', 1, false);
        }
    }

    public static function opts() {
        return wp_parse_args(get_option(FD_OPT, array()), self::get_defaults());
    }

    public static function get_defaults() {
        return array(
            // === QUERY OPTIMIZATION ===
            'qo_no_found_rows_front'     => 1,
            'qo_no_found_rows_admin'     => 0,
            'qo_remove_sort_order'       => 0,
            'qo_remove_cast'             => 1,
            'qo_optimize_groupby'        => 1,
            'qo_remove_private_check'    => 0,

            // === WOOCOMMERCE ===
            'wc_remove_dashboard_widget'    => 1,
            'wc_remove_marketplace_nag'     => 1,
            'wc_remove_connect_nag'         => 1,
            'wc_disable_marketing_hub'      => 1,
            'wc_remove_custom_meta_box'     => 1,
            'wc_optimize_delete_options'    => 1,
            'wc_fix_onboarding'             => 1,
            'wc_optimize_attr_lookup'       => 1,
            'wc_cache_post_counts'          => 1,
            'wc_defer_term_counting'        => 0,
            'wc_remove_variation_calc'      => 0,
            'wc_disable_cart_fragments'     => 0,
            'wc_limit_scripts_non_woo'      => 0,
            'wc_disable_password_meter'     => 0,
            // 'wc_ajax_attribute_edit' removed in 1.7.2 (was half-implemented — hid already-assigned terms)
            'wc_stop_phone_home'            => 0,
            'wc_phone_home_allowlist'       => '',
            'wc_disable_admin_ajax_bloat'   => 1,
            'wc_disable_setup_wizard'       => 1,

            // === WP ADMIN ===
            'adm_cache_category_list'   => 1,
            'adm_cache_user_counts'     => 1,
            'adm_cache_author_counts'   => 0,
            'adm_cache_months_dropdown' => 1,

            // === SECURITY ===
            'sec_disable_xmlrpc'            => 1,
            'sec_disable_file_editing'      => 1,
            'sec_hide_wp_version'           => 1,
            'sec_disable_author_enum'       => 1,
            'sec_hide_login_errors'         => 1,
            'sec_disable_pingbacks'         => 1,
            'sec_remove_rsd_link'           => 1,
            'sec_remove_wlw_link'           => 1,
            'sec_disable_app_passwords'     => 1,
            'sec_add_security_headers'      => 1,
            'sec_enable_coop'               => 0,
            'sec_restrict_rest_api'         => 0,
            'sec_disable_user_rest'         => 1,

            // === SPEED ===
            'spd_disable_emojis'            => 1,
            'spd_disable_embeds'            => 1,
            'spd_remove_jquery_migrate'     => 0,
            'spd_limit_revisions'           => 1,
            'spd_revisions_count'           => 5,
            'spd_heartbeat_control'         => 'reduce',
            'spd_heartbeat_freq'            => 60,
            'spd_remove_query_strings'      => 0,
            'spd_remove_shortlink'          => 1,
            'spd_remove_rest_link'          => 1,
            'spd_remove_feed_links'         => 0,
            'spd_remove_dashicons'          => 1,
            'spd_remove_global_styles'      => 0,
            'spd_disable_self_ping'         => 1,
            'spd_remove_generator'          => 1,

            // === DATABASE CLEANUP ===
            'db_clean_revisions'            => 0,
            'db_clean_auto_drafts'          => 1,
            'db_clean_trashed_posts'        => 1,
            'db_clean_spam_comments'        => 1,
            'db_clean_trashed_comments'     => 1,
            'db_clean_expired_transients'   => 1,
            'db_clean_orphan_postmeta'      => 0,
            'db_clean_orphan_commentmeta'   => 1,
            'db_clean_orphan_termmeta'      => 0,
            'db_optimize_tables'            => 1,
            'db_clean_wc_sessions'          => 1,
            'db_clean_action_scheduler'     => 1,
            'db_clean_wc_transients'        => 1,
            'db_clean_orphan_user_sessions' => 1,
            'db_auto_cleanup'               => 1,

            // === BLOAT REMOVAL ===
            'bloat_disable_comments'    => 0,
            'bloat_disable_gutenberg'   => 0,
            'bloat_remove_dns_prefetch' => 0,

            // === FRONTEND OPTIMIZER ===
            'fe_cleanup_elementor'          => 1,
            'fe_remove_elementor_icons'     => 0,
            'fe_disable_elementor_gfonts'   => 0,
            'fe_optimize_google_fonts'      => 1,
            'fe_font_display_swap'          => 1,
            'fe_add_preconnect'             => 1,
            'fe_preconnect_domains'         => '',
            'fe_preload_lcp'                => 0,
            'fe_lcp_image_url'              => '',
            'fe_disable_wc_blocks_css'      => 1,
            'fe_disable_animations_mobile'  => 1,
            'fe_lazy_iframes'               => 1,
            'fe_defer_elementor_js'         => 1,
            'fe_conditional_cf7'            => 1,
            'fe_fetchpriority_lcp'          => 1,

            // === AUTOLOAD OPTIMIZER ===
            'auto_audit_enabled'        => 1,
            'auto_fix_large_options'    => 0,
            'auto_large_threshold_kb'   => 100,
            'auto_ceiling_mb'           => 2,
            'auto_convert_myisam'       => 0,

            // === ACTIVITY LOG / MAINTENANCE MODE ===
            'idx_enable_maintenance_mode' => 1,
            'db_optimize_in_cron'         => 0,

            // === PROFILER RETENTION ===
            // prune_old_rows() deletes older rows AND enforces a row-count ceiling.
            // Zero/unset falls back to built-in defaults (7 days / 50k rows).
            'prof_retention_days' => 7,
            'prof_max_rows'       => 50000,
        );
    }
}
