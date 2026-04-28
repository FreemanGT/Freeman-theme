<?php
if (!defined('ABSPATH')) exit;

class FD_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'register'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
        add_action('wp_ajax_fd_run_cleanup', array($this, 'ajax_cleanup'));
        add_action('wp_ajax_fd_create_indexes', array($this, 'ajax_create_idx'));
        add_action('wp_ajax_fd_drop_indexes', array($this, 'ajax_drop_idx'));
        add_action('wp_ajax_fd_deep_reindex', array($this, 'ajax_deep_reindex'));
        add_action('wp_ajax_fd_deep_revert', array($this, 'ajax_deep_revert'));
        add_action('wp_ajax_fd_import_settings', array($this, 'ajax_import'));
        add_action('wp_ajax_fd_audit_autoload', array($this, 'ajax_audit_autoload'));
        add_action('wp_ajax_fd_fix_autoload', array($this, 'ajax_fix_autoload'));
        add_action('wp_ajax_fd_convert_myisam', array($this, 'ajax_convert_myisam'));
        add_action('wp_ajax_fd_profiler_start', array($this, 'ajax_profiler_start'));
        add_action('wp_ajax_fd_profiler_stop', array($this, 'ajax_profiler_stop'));
        add_action('wp_ajax_fd_profiler_clear', array($this, 'ajax_profiler_clear'));
        add_action('wp_ajax_fd_profiler_explain', array($this, 'ajax_profiler_explain'));
        add_action('wp_ajax_fd_optimize_tables', array($this, 'ajax_optimize_tables'));
        add_action('wp_ajax_fd_clear_activity_log', array($this, 'ajax_clear_activity_log'));

        // Add "Revert deep indexes" hint to the plugin list-table entry
        add_filter('plugin_action_links_' . plugin_basename(FD_PLUGIN_FILE), array($this, 'action_links'));
    }

    public function action_links($links) {
        $settings = '<a href="' . esc_url(admin_url('admin.php?page=freeman-digital')) . '">' . esc_html__('Settings', 'freeman-digital') . '</a>';
        $revert   = '<a href="' . esc_url(admin_url('admin.php?page=freeman-digital&tab=indexes')) . '" title="' . esc_attr__('Revert deep indexes before uninstalling for a clean removal.', 'freeman-digital') . '">' . esc_html__('Revert before uninstall', 'freeman-digital') . '</a>';
        array_unshift($links, $settings, $revert);
        return $links;
    }

    public function menu() {
        add_menu_page(
            __('Freeman Digital', 'freeman-digital'),
            __('Freeman Digital', 'freeman-digital'),
            'manage_options',
            'freeman-digital',
            array($this, 'page'),
            'dashicons-performance',
            59
        );
    }

    public function register() {
        register_setting('fd_group', FD_OPT, array('sanitize_callback' => array($this, 'sanitize')));
    }

    public function sanitize($in) {
        // CRITICAL: merge with existing saved options.
        // The form only renders one tab at a time, so fields from other tabs
        // won't be in $in. We must preserve their existing values.
        $existing = get_option(FD_OPT, array());
        $defs = FD_Core::get_defaults();
        $merged = wp_parse_args($existing, $defs);

        // Enum whitelists: free-text settings that must match a known value set.
        // Any submission outside these is silently dropped (existing saved value retained).
        $enums = array(
            'spd_heartbeat_control' => array('default', 'reduce', 'disable'),
        );

        foreach ($defs as $k => $d) {
            if (!isset($in[$k])) continue; // keep existing saved value

            if (isset($enums[$k])) {
                $value = sanitize_text_field($in[$k]);
                if (in_array($value, $enums[$k], true)) {
                    $merged[$k] = $value;
                }
                // else: drop invalid enum value, retain existing
                continue;
            }

            if (is_int($d) || is_float($d)) {
                $merged[$k] = is_int($d) ? absint($in[$k]) : (float) $in[$k];
            } elseif ($k === 'wc_phone_home_allowlist' || $k === 'fe_preconnect_domains' || $k === 'fe_lcp_image_url') {
                $merged[$k] = sanitize_textarea_field($in[$k]);
            } else {
                $merged[$k] = sanitize_text_field($in[$k]);
            }
        }
        return $merged;
    }

    public function assets($hook) {
        if (strpos($hook, 'freeman-digital') === false) return;
        wp_enqueue_style('fd-admin', FD_PLUGIN_URL . 'assets/css/admin.css', array(), FD_VERSION);
        wp_enqueue_script('fd-admin', FD_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), FD_VERSION, true);
        wp_localize_script('fd-admin', 'fdVars', array(
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fd_nonce'),
        ));
    }

    public function page() {
        if (!current_user_can('manage_options')) return;
        $o = FD_Core::opts();

        // Single source of truth for tab list
        $tabs = array(
            'dashboard'   => __('📊 Dashboard', 'freeman-digital'),
            'query'       => __('⚡ Query', 'freeman-digital'),
            'woocommerce' => __('🛒 WooCommerce', 'freeman-digital'),
            'wpadmin'     => __('🖥️ WP Admin', 'freeman-digital'),
            'indexes'     => __('🗂️ Indexes', 'freeman-digital'),
            'security'    => __('🔒 Security', 'freeman-digital'),
            'speed'       => __('🚀 Speed', 'freeman-digital'),
            'frontend'    => __('🎨 Frontend', 'freeman-digital'),
            'database'    => __('🧹 Cleanup', 'freeman-digital'),
            'autoload'    => __('📦 Autoload', 'freeman-digital'),
            'profiler'    => __('🔬 Profiler', 'freeman-digital'),
            'bloat'       => __('🗑️ Bloat', 'freeman-digital'),
            'activity'    => __('📜 Activity', 'freeman-digital'),
            'tools'       => __('🔧 Tools', 'freeman-digital'),
        );

        // Whitelist check — prevent arbitrary method calls via ?tab=
        $requested_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        $tab = array_key_exists($requested_tab, $tabs) ? $requested_tab : 'dashboard';

        // Double-guard: confirm the tab method actually exists before calling
        $tab_method = 'tab_' . $tab;
        if (!method_exists($this, $tab_method)) {
            $tab = 'dashboard';
            $tab_method = 'tab_dashboard';
        }
        ?>
        <div class="wrap fd-wrap">
            <div class="fd-header">
                <h1><?php echo esc_html__('⚡ Freeman Digital', 'freeman-digital'); ?> <span class="fd-ver">v<?php echo esc_html(FD_VERSION); ?></span></h1>
                <p><?php esc_html_e('WordPress & WooCommerce Optimization Suite', 'freeman-digital'); ?></p>
            </div>
            <nav class="nav-tab-wrapper fd-tabs">
                <?php
                foreach ($tabs as $s => $l) {
                    $c = ($tab === $s) ? 'nav-tab nav-tab-active' : 'nav-tab';
                    echo '<a href="' . esc_url(admin_url("admin.php?page=freeman-digital&tab={$s}")) . '" class="' . esc_attr($c) . '">' . esc_html($l) . '</a>';
                }
                ?>
            </nav>
            <form method="post" action="options.php" id="fd-form">
                <?php settings_fields('fd_group'); ?>
                <div class="fd-content">
                    <?php $this->{$tab_method}($o); ?>
                </div>
                <?php if (!in_array($tab, array('dashboard', 'indexes', 'tools', 'profiler', 'activity'))): ?>
                    <p class="submit"><button type="submit" class="button button-primary fd-save"><?php esc_html_e('💾 Save Settings', 'freeman-digital'); ?></button></p>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    // ============================
    // TAB: DASHBOARD
    // ============================
    private function tab_dashboard($o) {
        global $wpdb;
        $woo = class_exists('WooCommerce');
        $db_size = $wpdb->get_var($wpdb->prepare("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) FROM information_schema.tables WHERE table_schema=%s", DB_NAME));
        $posts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
        $meta = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
        $auto = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')");
        $auto_size = $wpdb->get_var("SELECT ROUND(SUM(LENGTH(option_value))/1024/1024,2) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')");
        $revs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision'");
        $trans = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%_transient_%'");
        $products = $woo ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'") : 0;
        $orders = $woo ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_order'") : 0;
        $active = 0;
        foreach ($o as $v) { if ($v === 1) $active++; }

        // MyISAM check
        $myisam = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=%s AND engine='MyISAM' AND table_name LIKE %s", DB_NAME, $wpdb->esc_like($wpdb->prefix) . '%'));
        ?>
        <div class="fd-stats-grid">
            <div class="fd-card"><div class="fd-num"><?php echo $active; ?></div><div class="fd-lbl"><?php esc_html_e('Active Optimizations', 'freeman-digital'); ?></div></div>
            <div class="fd-card"><div class="fd-num"><?php echo $db_size; ?> MB</div><div class="fd-lbl"><?php esc_html_e('Database Size', 'freeman-digital'); ?></div></div>
            <div class="fd-card"><div class="fd-num"><?php echo number_format($posts); ?></div><div class="fd-lbl"><?php esc_html_e('Total Posts', 'freeman-digital'); ?></div></div>
            <div class="fd-card"><div class="fd-num"><?php echo number_format($meta); ?></div><div class="fd-lbl"><?php esc_html_e('Postmeta Rows', 'freeman-digital'); ?></div></div>
            <?php if ($woo): ?>
            <div class="fd-card"><div class="fd-num"><?php echo number_format($products); ?></div><div class="fd-lbl"><?php esc_html_e('Products', 'freeman-digital'); ?></div></div>
            <div class="fd-card"><div class="fd-num"><?php echo number_format($orders); ?></div><div class="fd-lbl"><?php esc_html_e('Orders', 'freeman-digital'); ?></div></div>
            <?php endif; ?>
            <div class="fd-card"><div class="fd-num"><?php echo number_format($auto); ?></div><div class="fd-lbl"><?php esc_html_e('Autoloaded Options', 'freeman-digital'); ?></div></div>
            <div class="fd-card"><div class="fd-num"><?php echo $auto_size; ?> MB</div><div class="fd-lbl"><?php esc_html_e('Autoload Size', 'freeman-digital'); ?></div></div>
        </div>

        <?php if ($auto > 1000 || (float)$auto_size > 1.0): ?>
            <div class="fd-alert fd-warn"><strong><?php esc_html_e('⚠️ Autoload Warning:', 'freeman-digital'); ?></strong> <?php echo esc_html(sprintf(__('You have %1$s autoloaded options (%2$s MB). This is loaded on EVERY page request. Go to the Autoload tab to audit and fix.', 'freeman-digital'), number_format($auto), $auto_size)); ?></div>
        <?php endif; ?>
        <?php if ($myisam > 0): ?>
            <div class="fd-alert fd-warn"><strong><?php esc_html_e('⚠️ MyISAM Tables Detected:', 'freeman-digital'); ?></strong> <?php echo esc_html(sprintf(__('You have %d MyISAM tables. InnoDB is faster and supports row-level locking. Go to Autoload tab to convert.', 'freeman-digital'), $myisam)); ?></div>
        <?php endif; ?>
        <?php if ($revs > 500): ?>
            <div class="fd-alert fd-info"><strong>💡</strong> <?php echo esc_html(sprintf(__('%s post revisions found. Go to Cleanup tab to purge.', 'freeman-digital'), number_format($revs))); ?></div>
        <?php endif; ?>
        <?php if ($trans > 500): ?>
            <div class="fd-alert fd-info"><strong>💡</strong> <?php echo esc_html(sprintf(__('%s transients in database. Go to Cleanup tab to clear expired ones.', 'freeman-digital'), number_format($trans))); ?></div>
        <?php endif; ?>

        <div style="margin-top:20px">
            <h3><?php esc_html_e('Quick Actions', 'freeman-digital'); ?></h3>
            <a href="<?php echo esc_url(admin_url('admin.php?page=freeman-digital&tab=indexes')); ?>" class="button"><?php esc_html_e('🗂️ Manage Indexes', 'freeman-digital'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=freeman-digital&tab=database')); ?>" class="button"><?php esc_html_e('🧹 Run Cleanup', 'freeman-digital'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=freeman-digital&tab=autoload')); ?>" class="button"><?php esc_html_e('📦 Audit Autoload', 'freeman-digital'); ?></a>
        </div>
        <?php
    }

    // ============================
    // TAB: QUERY
    // ============================
    private function tab_query($o) {
        echo '<div class="fd-section"><h2>' . esc_html__('Query Optimization', 'freeman-digital') . '</h2><p>' . esc_html__('Optimizes WP_Query SQL to prevent full table scans and use database indexes properly.', 'freeman-digital') . '</p>';
        $this->tog('qo_no_found_rows_front', __('Remove SQL_CALC_FOUND_ROWS (Front-end)', 'freeman-digital'), $o, __('Stops MySQL from counting total matching rows on archive/shop pages. Dramatically faster on large stores. Pagination shows approximate counts or switches to prev/next.', 'freeman-digital'));
        $this->tog('qo_no_found_rows_admin', __('Remove SQL_CALC_FOUND_ROWS (Admin)', 'freeman-digital'), $o, __('Same as above but for wp-admin edit.php screens. Admin pagination uses Next/Previous.', 'freeman-digital'));
        $this->tog('qo_remove_sort_order', __('Remove Sort Order (Front-end)', 'freeman-digital'), $o, __('Uses natural DB sort order instead of ORDER BY. Allows covering indexes. <strong>Warning:</strong> Disables user-facing sort dropdowns. Only enable if default product order is fine.', 'freeman-digital'));
        $this->tog('qo_remove_cast', __('Remove CAST on wp_postmeta', 'freeman-digital'), $o, __('MySQL applies CAST() to meta_value which prevents index usage. MySQL auto-casts natively. Highly recommended — this is one of the biggest single optimizations.', 'freeman-digital'));
        $this->tog('qo_optimize_groupby', __('Optimize GROUP BY → DISTINCT', 'freeman-digital'), $o, __('Replaces GROUP BY (requires expensive sort) with DISTINCT (just removes dupes) when safe. Removes both when no joins exist.', 'freeman-digital'));
        $this->tog('qo_remove_private_check', __('Remove Private Post Check', 'freeman-digital'), $o, __('Logged-in admins trigger an OR post_status="private" clause that breaks index usage. Enable for faster admin front-end browsing. Private posts won\'t show on front-end.', 'freeman-digital'));
        echo '</div>';
    }

    // ============================
    // TAB: WOOCOMMERCE
    // ============================
    private function tab_woocommerce($o) {
        if (!class_exists('WooCommerce')) echo '<div class="fd-alert fd-info">' . esc_html__('WooCommerce not active. Settings will apply when it\'s activated.', 'freeman-digital') . '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('WooCommerce Admin Bloat', 'freeman-digital') . '</h2>';
        $this->tog('wc_remove_dashboard_widget', __('Remove Dashboard Status Widget', 'freeman-digital'), $o, __('Removes the WooCommerce Status widget that runs expensive order count queries on every dashboard load.', 'freeman-digital'));
        $this->tog('wc_remove_marketplace_nag', __('Disable Marketplace Suggestions', 'freeman-digital'), $o, __('Stops WooCommerce from querying your orders to show upsell suggestions. This query runs on EVERY admin page load.', 'freeman-digital'));
        $this->tog('wc_remove_connect_nag', __('Remove "Connect Store" Notice', 'freeman-digital'), $o, __('Hides the persistent WooCommerce.com connection admin notice.', 'freeman-digital'));
        $this->tog('wc_disable_marketing_hub', __('Disable Marketing Hub', 'freeman-digital'), $o, __('Removes the WooCommerce Marketing menu that loads external resources.', 'freeman-digital'));
        $this->tog('wc_remove_custom_meta_box', __('Remove Custom Meta Select Box', 'freeman-digital'), $o, __('Removes the slow Custom Fields meta box. The underlying query runs SELECT DISTINCT meta_key FROM wp_postmeta — a full table scan on large sites.', 'freeman-digital'));
        $this->tog('wc_disable_setup_wizard', __('Disable Setup Wizard Redirect', 'freeman-digital'), $o, __('Prevents WooCommerce from redirecting to its setup wizard on activation.', 'freeman-digital'));
        $this->tog('wc_disable_admin_ajax_bloat', __('Reduce WC Admin AJAX', 'freeman-digital'), $o, __('Disables WooCommerce Admin feature that makes extra admin-ajax calls for notices, inbox messages, and activity panel on every admin page.', 'freeman-digital'));
        echo '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('WooCommerce Performance', 'freeman-digital') . '</h2>';
        $this->tog('wc_optimize_delete_options', __('Optimize wp_options DELETE Queries', 'freeman-digital'), $o, __('WooCommerce runs DELETE FROM wp_options WHERE option_name LIKE \'%transient%\' which can\'t use indexes. We rewrite it to use a subquery that can. Prevents 3-minute lockups on large stores.', 'freeman-digital'));
        $this->tog('wc_fix_onboarding', __('Cache hasProducts Check', 'freeman-digital'), $o, __('WooCommerce checks "do you have products?" on every admin page via a full wp_posts scan. We cache the result for 24 hours. Saves 10-70 seconds on large stores.', 'freeman-digital'));
        $this->tog('wc_optimize_attr_lookup', __('Fix Attribute Lookup OR Query', 'freeman-digital'), $o, __('WooCommerce queries wc_product_attributes_lookup with a redundant OR (product_id = X OR product_or_parent_id = X). We simplify to just product_or_parent_id.', 'freeman-digital'));
        $this->tog('wc_cache_post_counts', __('Cache Post Type Counts', 'freeman-digital'), $o, __('WordPress recalculates post counts after every edit. We cache them for 12 hours. Orders are always counted fresh.', 'freeman-digital'));
        $this->tog('wc_defer_term_counting', __('Defer Term Counting', 'freeman-digital'), $o, __('Defers category/term recounting during imports and bulk edits. Term counts update on the nightly cron.', 'freeman-digital'));
        $this->tog('wc_remove_variation_calc', __('Force AJAX Variation Loading', 'freeman-digital'), $o, __('Sets the variation AJAX threshold to 1, so any variable product with 2+ variations loads variations via AJAX instead of pre-rendering them all in HTML. Speeds up initial page load for products with many variations. Default WC threshold is 30.', 'freeman-digital'));
        echo '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('WooCommerce Frontend', 'freeman-digital') . '</h2>';
        $this->tog('wc_disable_cart_fragments', __('Disable Cart Fragments AJAX', 'freeman-digital'), $o, __('Stops wc-ajax=get_refreshed_fragments on every page load (~300ms per request). <strong>Warning:</strong> Mini-cart won\'t auto-update. Good if your theme doesn\'t use the default WC mini-cart or you use a dedicated cart icon plugin.', 'freeman-digital'));
        $this->tog('wc_limit_scripts_non_woo', __('Limit WC Scripts to WC Pages Only', 'freeman-digital'), $o, __('Only loads WooCommerce CSS/JS on product, shop, cart, checkout, and account pages. Saves 100-300KB of scripts on blog/homepage.', 'freeman-digital'));
        $this->tog('wc_disable_password_meter', __('Disable Password Strength Meter', 'freeman-digital'), $o, __('Removes the zxcvbn password strength script (~800KB uncompressed) from checkout and account pages.', 'freeman-digital'));
        echo '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('Phone Home Blocker', 'freeman-digital') . '</h2>';
        $this->tog('wc_stop_phone_home', __('Block Non-wp.org Outbound HTTP (Admin Context)', 'freeman-digital'), $o, esc_html__('Intercepts every outbound HTTP request originating from wp-admin / AJAX / cron that is NOT hitting wordpress.org. Can save 10+ seconds on admin loads on sites running many premium plugins. Frontend requests are untouched. WordPress.org is always allowed. Use the allowlist below to permit known-good SaaS endpoints (payment gateways, analytics, etc).', 'freeman-digital'));

        $allowlist = $o['wc_phone_home_allowlist'];
        if ($allowlist === '') {
            // Sensible defaults so common integrations don't break on first enable.
            $allowlist = "*api.stripe.com*\n*googleapis.com*\n*woocommerce.com/wc-api/*";
        }
        ?>
        <div class="fd-field" style="margin-top:10px">
            <label><strong><?php esc_html_e('Allowed URL Patterns', 'freeman-digital'); ?></strong> <?php esc_html_e('(one per line, * = wildcard)', 'freeman-digital'); ?></label>
            <textarea name="<?php echo FD_OPT; ?>[wc_phone_home_allowlist]" rows="4" class="large-text"><?php echo esc_textarea($allowlist); ?></textarea>
            <p class="description"><?php esc_html_e('Example: *wordpress.org* or *my-plugin.com/api/*. Pre-seeded with Stripe, Google APIs, and WooCommerce.com.', 'freeman-digital'); ?></p>
        </div>
        <?php
        echo '</div>';
    }

    // ============================
    // TAB: WP ADMIN
    // ============================
    private function tab_wpadmin($o) {
        echo '<div class="fd-section"><h2>' . esc_html__('WP Admin Caching', 'freeman-digital') . '</h2><p>' . esc_html__('Caches expensive admin queries that WordPress recalculates constantly.', 'freeman-digital') . '</p>';
        $this->tog('adm_cache_category_list', __('Cache Category Dropdown', 'freeman-digital'), $o, __('Caches the WP-admin category filter dropdown. WordPress\'s own cache gets wiped on any product edit — ours persists.', 'freeman-digital'));
        $this->tog('adm_cache_user_counts', __('Cache User Type Counts', 'freeman-digital'), $o, __('Caches user role counts for 12 hours. Prevents full wp_users + wp_usermeta table scan on Users page.', 'freeman-digital'));
        $this->tog('adm_cache_author_counts', __('Cache Author Post Counts', 'freeman-digital'), $o, __('Caches author post counts for 12 hours. One customer saw 16 seconds added per admin page load from this.', 'freeman-digital'));
        $this->tog('adm_cache_months_dropdown', __('Cache Months Filter Dropdown', 'freeman-digital'), $o, __('Caches the month/year dropdown on post listing pages. Prevents repeated DISTINCT YEAR(post_date) queries.', 'freeman-digital'));
        echo '</div>';
    }

    // ============================
    // TAB: INDEXES
    // ============================
    private function tab_indexes($o) {
        $caps = FD_Indexes::detect_capabilities();
        $deep_status = FD_Indexes::get_deep_status();
        $defs = FD_Indexes::get_definitions();
        $created = FD_Indexes::get_created();

        // TIER 1: Deep Reindex
        ?>
        <?php if (isset($caps->can_ddl) && $caps->can_ddl === false): ?>
            <div class="fd-alert fd-warn"><strong><?php esc_html_e('🔴 Database user lacks CREATE/ALTER privileges.', 'freeman-digital'); ?></strong> <?php esc_html_e('Index features will fail silently. Contact your host to grant DDL permissions.', 'freeman-digital'); ?><?php if (!empty($caps->ddl_error)) echo ' <code>' . esc_html($caps->ddl_error) . '</code>'; ?></div>
        <?php endif; ?>

        <div class="fd-section">
            <h2><?php esc_html_e('🔥 Tier 1: Deep Reindex (PRIMARY KEY Restructuring)', 'freeman-digital'); ?></h2>
            <p><?php echo wp_kses(__('This is the <strong>highest-impact</strong> database optimization possible. It restructures PRIMARY KEYs on core WordPress and WooCommerce meta tables into compound clustered indexes — the same technique used by enterprise WordPress hosts. This means JOINs on postmeta, usermeta, and other meta tables use the clustered index directly, eliminating bookmark lookups entirely.', 'freeman-digital'), array('strong' => array())); ?></p>
            <p><?php esc_html_e('Server:', 'freeman-digital'); ?> <code><?php echo esc_html($caps->version); ?></code> — <?php esc_html_e('Format:', 'freeman-digital'); ?> <strong><?php echo $caps->barracuda ? esc_html__('✅ Barracuda (full-length keys)', 'freeman-digital') : esc_html__('⚠️ Antelope (prefix-limited keys)', 'freeman-digital'); ?></strong></p>

            <?php if (!$caps->can_reindex): ?>
                <div class="fd-alert fd-warn"><?php esc_html_e('Your MySQL version is too old for deep reindexing. Please upgrade to MySQL 5.6+ or MariaDB 10.0+.', 'freeman-digital'); ?></div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped fd-idx-table">
                    <thead><tr><th style="width:30px"><input type="checkbox" id="fd-deep-all"></th><th><?php esc_html_e('Table', 'freeman-digital'); ?></th><th><?php esc_html_e('Status', 'freeman-digital'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($deep_status as $table => $status): ?>
                        <tr>
                            <td><input type="checkbox" class="fd-deep-cb" value="<?php echo esc_attr($table); ?>" <?php checked($status === 'standard'); ?>></td>
                            <td><code><?php echo esc_html($table); ?></code></td>
                            <td><?php echo $status === 'optimized' ? '<span style="color:green">' . esc_html__('✅ Optimized', 'freeman-digital') . '</span>' : '<span style="color:#c00">' . esc_html__('⚪ Standard (can be optimized)', 'freeman-digital') . '</span>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:15px">
                    <button type="button" class="button button-primary" id="fd-deep-apply"><?php esc_html_e('⚡ Apply Deep Reindex', 'freeman-digital'); ?></button>
                    <button type="button" class="button" id="fd-deep-revert" style="margin-left:10px"><?php esc_html_e('↩️ Revert to WordPress Standard', 'freeman-digital'); ?></button>
                </p>
                <p class="description"><?php esc_html_e('⚠️ This restructures PRIMARY KEYs. Takes 1-60 seconds per table depending on size. Safe and reversible. Your site may briefly enter maintenance mode during large table operations.', 'freeman-digital'); ?></p>
                <div id="fd-deep-msg" style="margin-top:10px"></div>
            <?php endif; ?>
        </div>

        <div class="fd-section">
            <h2><?php esc_html_e('📎 Tier 2: Secondary Indexes (Additional Coverage)', 'freeman-digital'); ?></h2>
            <p><?php esc_html_e('These add indexes on tables not covered by Tier 1 — taxonomy tables, WooCommerce order items, sessions, Action Scheduler, and more.', 'freeman-digital'); ?></p>
            <table class="wp-list-table widefat fixed striped fd-idx-table">
                <thead><tr><th style="width:30px"><input type="checkbox" id="fd-idx-all"></th><th><?php esc_html_e('Index', 'freeman-digital'); ?></th><th><?php esc_html_e('Table', 'freeman-digital'); ?></th><th><?php esc_html_e('Columns', 'freeman-digital'); ?></th><th><?php esc_html_e('Purpose', 'freeman-digital'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($defs as $i): ?>
                    <tr>
                        <td><input type="checkbox" class="fd-idx-cb" value="<?php echo esc_attr($i['name']); ?>" <?php checked(in_array($i['name'], $created)); ?>></td>
                        <td><code><?php echo esc_html($i['name']); ?></code></td>
                        <td><?php echo esc_html($i['table']); ?></td>
                        <td><code><?php echo esc_html($i['columns']); ?></code></td>
                        <td><?php echo esc_html($i['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:15px">
                <button type="button" class="button button-primary" id="fd-create-idx"><?php esc_html_e('✅ Update Secondary Indexes', 'freeman-digital'); ?></button>
                <button type="button" class="button" id="fd-drop-idx" style="margin-left:10px"><?php esc_html_e('❌ Drop All Secondary', 'freeman-digital'); ?></button>
            </p>
            <div id="fd-idx-msg" style="margin-top:10px"></div>
        </div>
        <?php
    }

    // ============================
    // TAB: SECURITY
    // ============================
    private function tab_security($o) {
        echo '<div class="fd-section"><h2>' . esc_html__('Security Hardening', 'freeman-digital') . '</h2><p>' . esc_html__('Reduce attack surface by disabling exploitable WordPress features.', 'freeman-digital') . '</p>';
        $this->tog('sec_disable_xmlrpc', __('Disable XML-RPC', 'freeman-digital'), $o, __('Blocks xmlrpc.php entirely — the #1 brute-force and DDoS vector. REST API replaces all its functionality. <strong>Note:</strong> Disable if using Jetpack.', 'freeman-digital'));
        $this->tog('sec_disable_file_editing', __('Disable File Editing', 'freeman-digital'), $o, __('Sets DISALLOW_FILE_EDIT. Prevents code injection if an attacker gets admin access.', 'freeman-digital'));
        $this->tog('sec_hide_wp_version', __('Hide WordPress Version', 'freeman-digital'), $o, __('Removes version from HTML source, RSS feeds, scripts, and styles. Prevents version-targeted attacks.', 'freeman-digital'));
        $this->tog('sec_disable_author_enum', __('Disable Author Enumeration', 'freeman-digital'), $o, __('Blocks ?author=N URL probing and /wp/v2/users REST endpoint for non-authenticated users. Prevents username discovery.', 'freeman-digital'));
        $this->tog('sec_hide_login_errors', __('Hide Login Error Details', 'freeman-digital'), $o, __('Replaces "invalid username" / "incorrect password" with generic error message.', 'freeman-digital'));
        $this->tog('sec_disable_pingbacks', __('Disable Pingbacks & Trackbacks', 'freeman-digital'), $o, __('Blocks pingback XML-RPC methods. Used for DDoS amplification attacks.', 'freeman-digital'));
        $this->tog('sec_remove_rsd_link', __('Remove RSD Link', 'freeman-digital'), $o, __('Removes Really Simple Discovery link from HTML head.', 'freeman-digital'));
        $this->tog('sec_remove_wlw_link', __('Remove WLW Manifest Link', 'freeman-digital'), $o, __('Removes Windows Live Writer link — discontinued software.', 'freeman-digital'));
        $this->tog('sec_disable_app_passwords', __('Disable Application Passwords', 'freeman-digital'), $o, __('Disables WP 5.6+ application passwords. If no external apps authenticate to your site, disable this.', 'freeman-digital'));
        $this->tog('sec_add_security_headers', __('Add Security Headers (Frontend Only)', 'freeman-digital'), $o, __('Adds X-Content-Type-Options, X-Frame-Options SAMEORIGIN, Referrer-Policy, and Permissions-Policy on frontend responses. Admin/AJAX/REST responses are intentionally skipped so embeds and cross-origin integrations keep working. X-XSS-Protection removed in 1.7.2 (deprecated).', 'freeman-digital'));
        $this->tog('sec_enable_coop', __('Add Cross-Origin-Opener-Policy Header (Opt-in)', 'freeman-digital'), $o, __('Adds <code>Cross-Origin-Opener-Policy: same-origin</code> alongside the other security headers. <strong>Warning:</strong> breaks popup-based OAuth (Google Sign-In, Facebook Login). Only enable if you have tested every login path.', 'freeman-digital'));
        $this->tog('sec_restrict_rest_api', __('Restrict REST API to Logged-in Users', 'freeman-digital'), $o, __('<strong>Warning:</strong> May break Elementor, contact forms, or themes using REST API on front-end. Test carefully.', 'freeman-digital'));
        $this->tog('sec_disable_user_rest', __('Disable User Endpoint in REST API', 'freeman-digital'), $o, __('Blocks /wp/v2/users for non-authenticated users. Safer than full REST API restriction.', 'freeman-digital'));
        echo '</div>';
    }

    // ============================
    // TAB: SPEED
    // ============================
    private function tab_speed($o) {
        echo '<div class="fd-section"><h2>' . esc_html__('Frontend Speed', 'freeman-digital') . '</h2>';
        $this->tog('spd_disable_emojis', __('Disable WordPress Emojis', 'freeman-digital'), $o, __('Removes emoji detection script + styles (~50KB). All modern browsers render emojis natively.', 'freeman-digital'));
        $this->tog('spd_disable_embeds', __('Disable WordPress Embeds', 'freeman-digital'), $o, __('Removes wp-embed.min.js and oEmbed discovery. Other sites can\'t embed your posts as rich cards.', 'freeman-digital'));
        $this->tog('spd_remove_jquery_migrate', __('Remove jQuery Migrate', 'freeman-digital'), $o, __('Dequeues jQuery Migrate on front-end. <strong>Warning:</strong> Test — some older plugins/themes depend on it.', 'freeman-digital'));
        $this->tog('spd_remove_query_strings', __('Remove ?ver= from Static Resources', 'freeman-digital'), $o, __('Strips version query strings from CSS/JS URLs for better CDN/browser caching. <strong>Default OFF in 1.7.2</strong> — may break cache invalidation on plugin/theme updates unless your CDN purges on deploy.', 'freeman-digital'));
        $this->tog('spd_remove_dashicons', __('Remove Dashicons for Visitors', 'freeman-digital'), $o, __('Dequeues Dashicons CSS for non-logged-in users (~46KB saved).', 'freeman-digital'));
        $this->tog('spd_remove_global_styles', __('Remove Block Editor Frontend Styles', 'freeman-digital'), $o, __('Removes Gutenberg global styles, block library CSS, and SVG filters. Safe when using Elementor exclusively.', 'freeman-digital'));
        $this->tog('spd_disable_self_ping', __('Disable Self-Pingbacks', 'freeman-digital'), $o, __('Prevents WordPress from sending pingbacks to itself when you link to your own posts.', 'freeman-digital'));
        echo '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('Header Cleanup', 'freeman-digital') . '</h2>';
        $this->tog('spd_remove_generator', __('Remove Generator Tag', 'freeman-digital'), $o, __('Removes &lt;meta name="generator"&gt; revealing WordPress version.', 'freeman-digital'));
        $this->tog('spd_remove_shortlink', __('Remove Shortlink', 'freeman-digital'), $o, __('Removes the ?p=123 shortlink from head and HTTP headers.', 'freeman-digital'));
        $this->tog('spd_remove_rest_link', __('Remove REST API Link', 'freeman-digital'), $o, __('Removes REST API discovery link from HTML head.', 'freeman-digital'));
        $this->tog('spd_remove_feed_links', __('Remove RSS Feed Links', 'freeman-digital'), $o, __('Removes RSS/Atom links from head. <strong>Only if you don\'t use RSS.</strong>', 'freeman-digital'));
        echo '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('Heartbeat API', 'freeman-digital') . '</h2>';
        $this->sel('spd_heartbeat_control', __('Heartbeat Behavior', 'freeman-digital'), $o, array('default' => __('Default (15s)', 'freeman-digital'), 'reduce' => __('Reduce Frequency', 'freeman-digital'), 'disable' => __('Disable', 'freeman-digital')), __('WordPress sends AJAX requests every 15 seconds. Reducing saves server resources. Disabling stops all auto-save and real-time notifications.', 'freeman-digital'));
        $this->num('spd_heartbeat_freq', __('Heartbeat Interval (seconds)', 'freeman-digital'), $o, 15, 300, __('Only applies when set to "Reduce". Default: 60.', 'freeman-digital'));
        echo '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('Post Revisions', 'freeman-digital') . '</h2>';
        $this->tog('spd_limit_revisions', __('Limit Post Revisions', 'freeman-digital'), $o, __('Caps revisions per post. Existing excess revisions stay — use Cleanup to delete them.', 'freeman-digital'));
        $this->num('spd_revisions_count', __('Max Revisions Per Post', 'freeman-digital'), $o, 0, 100, __('0 = disable revisions. 3-5 = good balance.', 'freeman-digital'));
        echo '</div>';
    }

    // ============================
    // TAB: FRONTEND
    // ============================
    private function tab_frontend($o) {
        echo '<div class="fd-section"><h2>' . esc_html__('Elementor Optimization', 'freeman-digital') . '</h2><p>' . wp_kses(__('These optimizations are specifically designed for Elementor + WooCommerce sites. They control <strong>what gets loaded</strong> (complementary to WP Rocket which controls how it\'s delivered).', 'freeman-digital'), array('strong' => array())) . '</p>';
        $this->tog('fe_cleanup_elementor', __('Smart Elementor Cleanup', 'freeman-digital'), $o, __('On pages NOT built with Elementor, removes all Elementor frontend CSS/JS. On Elementor pages, removes block editor CSS (not needed). This alone can save 200-500KB on non-Elementor pages. Automatically respects Elementor Pro theme-builder header/footer locations.', 'freeman-digital'));
        $this->tog('fe_remove_elementor_icons', __('Remove Elementor Icons Library (eicons)', 'freeman-digital'), $o, __('Removes the Elementor Icons CSS (~40KB). Only safe if your widgets use Font Awesome or custom icons instead of Elementor\'s default icons.', 'freeman-digital'));
        $this->tog('fe_defer_elementor_js', __('Defer Elementor JavaScript', 'freeman-digital'), $o, __('Adds <code>defer</code> to safe Elementor-companion scripts (Swiper, share-link, waypoints). Narrowed in 1.7.2 to avoid breaking custom widgets — <code>elementor-frontend</code> no longer deferred.', 'freeman-digital'));
        $this->tog('fe_disable_animations_mobile', __('Disable Animations on Mobile', 'freeman-digital'), $o, __('Removes all Elementor entrance animations on mobile devices (screen < 768px). Animations cause layout shifts and jank on mobile — this directly fixes LCP and CLS.', 'freeman-digital'));
        echo '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('Font Optimization', 'freeman-digital') . '</h2>';
        $this->tog('fe_disable_elementor_gfonts', __('Disable Elementor Google Fonts Loading', 'freeman-digital'), $o, __('Prevents Elementor from loading Google Fonts via its own mechanism. <strong>Only enable if you self-host fonts or use system fonts.</strong> Eliminates 1-2 render-blocking requests.', 'freeman-digital'));
        $this->tog('fe_optimize_google_fonts', __('Add display=swap to Google Fonts', 'freeman-digital'), $o, __('Ensures text is visible immediately while fonts load (prevents FOIT — Flash of Invisible Text).', 'freeman-digital'));
        $this->tog('fe_font_display_swap', __('Force font-display: swap on Inline @font-face', 'freeman-digital'), $o, __('Rewrites @font-face blocks inside inline <style> tags to include <code>font-display: swap</code>. Theme-loaded CSS files are out of scope — add the property upstream.', 'freeman-digital'));
        echo '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('Resource Hints & Preloading', 'freeman-digital') . '</h2>';
        $this->tog('fe_add_preconnect', __('Add Preconnect Hints', 'freeman-digital'), $o, __('Tells the browser to start connecting to external domains (Google Fonts, CDNs) before they\'re needed. Saves 100-300ms per external domain.', 'freeman-digital'));
        ?>
        <div class="fd-field" style="margin-top:10px">
            <label><strong><?php esc_html_e('Additional Preconnect Domains', 'freeman-digital'); ?></strong> <?php esc_html_e('(one per line)', 'freeman-digital'); ?></label>
            <textarea name="<?php echo FD_OPT; ?>[fe_preconnect_domains]" rows="3" class="large-text"><?php echo esc_textarea($o['fe_preconnect_domains']); ?></textarea>
            <p class="description"><?php esc_html_e('Example: https://cdn.example.com', 'freeman-digital'); ?></p>
        </div>
        <?php
        $this->tog('fe_preload_lcp', __('Preload LCP Image', 'freeman-digital'), $o, __('Tells the browser to start downloading your largest visible image immediately — the single most impactful fix for LCP score. Enter the URL below.', 'freeman-digital'));
        ?>
        <div class="fd-field" style="margin-top:10px">
            <label><strong><?php esc_html_e('LCP Image URL', 'freeman-digital'); ?></strong> <?php esc_html_e('(your hero image or main product image)', 'freeman-digital'); ?></label>
            <input type="text" name="<?php echo FD_OPT; ?>[fe_lcp_image_url]" value="<?php echo esc_attr($o['fe_lcp_image_url']); ?>" class="large-text" placeholder="https://yoursite.com/wp-content/uploads/hero.webp">
            <p class="description"><?php esc_html_e('Find this in Chrome DevTools → Performance → look for the largest image, or in PageSpeed Insights under "Largest Contentful Paint element".', 'freeman-digital'); ?></p>
        </div>
        <?php
        $this->tog('fe_fetchpriority_lcp', __('Add fetchpriority="high" to LCP Image', 'freeman-digital'), $o, __('Marks the image whose URL matches the LCP Image URL above as high-priority. <strong>Requires LCP Image URL to be set</strong> — otherwise this toggle is a no-op.', 'freeman-digital'));
        echo '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('Asset Cleanup', 'freeman-digital') . '</h2>';
        $this->tog('fe_disable_wc_blocks_css', __('Remove WooCommerce Blocks CSS', 'freeman-digital'), $o, __('WooCommerce loads its Blocks CSS (~50KB) on every page even if you don\'t use WC Blocks. Safe to remove if using Elementor for product layouts.', 'freeman-digital'));
        $this->tog('fe_lazy_iframes', __('Lazy Load iframes (skip first)', 'freeman-digital'), $o, __('Adds <code>loading="lazy"</code> to all iframes except the first one on a page (so hero videos still load eagerly).', 'freeman-digital'));
        $this->tog('fe_conditional_cf7', __('Load Contact Form 7 Only When Needed', 'freeman-digital'), $o, __('Detects [contact-form-7] shortcodes via the do_shortcode_tag hook (so Elementor-rendered forms also count) and dequeues CF7 assets at wp_footer when no form was rendered.', 'freeman-digital'));
        echo '</div>';
    }

    // ============================
    // TAB: DATABASE
    // ============================
    private function tab_database($o) {
        global $wpdb;
        $stats = array(
            __('Revisions', 'freeman-digital') => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision'"),
            __('Auto-drafts', 'freeman-digital') => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='auto-draft'"),
            __('Trashed Posts', 'freeman-digital') => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='trash'"),
            __('Spam Comments', 'freeman-digital') => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='spam'"),
            __('Trashed Comments', 'freeman-digital') => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='trash'"),
            __('Expired Transients', 'freeman-digital') => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_%' AND option_value < " . time()),
        );
        echo '<div class="fd-section"><h2>' . esc_html__('Database Cleanup', 'freeman-digital') . '</h2>';
        echo '<div class="fd-cleanup-stats">';
        foreach ($stats as $label => $count) echo '<span>' . esc_html($label) . ': <strong>' . esc_html(number_format((int)$count)) . '</strong></span>';
        echo '</div>';

        $this->tog('db_clean_revisions', __('Clean Post Revisions', 'freeman-digital'), $o, __('Deletes all revisions. Current versions are always preserved.', 'freeman-digital'));
        $this->tog('db_clean_auto_drafts', __('Clean Auto-Drafts', 'freeman-digital'), $o, __('Removes auto-saved drafts.', 'freeman-digital'));
        $this->tog('db_clean_trashed_posts', __('Clean Trashed Posts', 'freeman-digital'), $o, __('Permanently deletes trashed posts.', 'freeman-digital'));
        $this->tog('db_clean_spam_comments', __('Clean Spam Comments', 'freeman-digital'), $o, '');
        $this->tog('db_clean_trashed_comments', __('Clean Trashed Comments', 'freeman-digital'), $o, '');
        $this->tog('db_clean_expired_transients', __('Clean Expired Transients', 'freeman-digital'), $o, __('Removes expired transients + their timeout pairs from wp_options.', 'freeman-digital'));
        $this->tog('db_clean_wc_transients', __('Clean WooCommerce Transients', 'freeman-digital'), $o, __('Cleans _transient_wc_* entries (product children, var prices, related products) that accumulate and bloat wp_options.', 'freeman-digital'));
        $this->tog('db_clean_wc_sessions', __('Clean Expired WC Sessions', 'freeman-digital'), $o, __('Removes expired WooCommerce session data from the sessions table.', 'freeman-digital'));
        $this->tog('db_clean_action_scheduler', __('Clean Action Scheduler', 'freeman-digital'), $o, __('Removes completed/failed/canceled entries and orphaned logs from Action Scheduler tables.', 'freeman-digital'));
        $this->tog('db_clean_orphan_postmeta', __('Clean Orphaned Postmeta', 'freeman-digital'), $o, __('Removes wp_postmeta rows referencing deleted posts. <strong>Backup first.</strong>', 'freeman-digital'));
        $this->tog('db_clean_orphan_commentmeta', __('Clean Orphaned Commentmeta', 'freeman-digital'), $o, __('Removes wp_commentmeta rows for deleted comments.', 'freeman-digital'));
        $this->tog('db_clean_orphan_termmeta', __('Clean Orphaned Termmeta', 'freeman-digital'), $o, __('Removes wp_termmeta rows for deleted terms.', 'freeman-digital'));
        $this->tog('db_clean_orphan_user_sessions', __('Clean Expired User Session Tokens', 'freeman-digital'), $o, __('Removes expired WordPress session tokens stored in wp_usermeta. Sites with many users accumulate MB of session data.', 'freeman-digital'));
        $this->tog('db_optimize_tables', __('Allow OPTIMIZE TABLE (manual runs)', 'freeman-digital'), $o, __('When enabled, the <em>Optimize Tables Now</em> button below rebuilds indexes on core + WooCommerce tables. This <strong>locks tables for 1\u201315 minutes</strong> on large stores and is excluded from the nightly cron unless the next toggle is on.', 'freeman-digital'));
        $this->tog('db_optimize_in_cron', __('Also run OPTIMIZE TABLE during nightly cron', 'freeman-digital'), $o, __('<strong>OFF by default.</strong> Only enable on a scheduled maintenance window with low traffic. Otherwise, trigger manually via the button below.', 'freeman-digital'));
        echo '</div>';

        echo '<div class="fd-section"><h2>' . esc_html__('Automatic Cleanup', 'freeman-digital') . '</h2>';
        $this->tog('db_auto_cleanup', __('Enable Nightly Cleanup (3:00 AM)', 'freeman-digital'), $o, __('Runs all enabled cleanup tasks above on a daily schedule.', 'freeman-digital'));
        echo '<p style="margin-top:15px">
            <button type="button" class="button button-primary" id="fd-run-cleanup">' . esc_html__('🧹 Run Cleanup Now', 'freeman-digital') . '</button>
            <button type="button" class="button" id="fd-optimize-tables" style="margin-left:10px">' . esc_html__('⚡ Optimize Tables Now', 'freeman-digital') . '</button>
        </p>';
        echo '<p class="description">' . wp_kses(__('<strong>Warning:</strong> OPTIMIZE TABLE locks tables for 1\u201315 minutes on large stores. Run during maintenance windows only.', 'freeman-digital'), $this->allowed_desc_html()) . '</p>';
        echo '<div id="fd-cleanup-msg" style="margin-top:10px"></div>';
        echo '<div id="fd-optimize-msg" style="margin-top:10px"></div>';
        echo '</div>';
    }

    // ============================
    // TAB: AUTOLOAD
    // ============================
    private function tab_autoload($o) {
        global $wpdb;
        $total = $wpdb->get_var("SELECT ROUND(SUM(LENGTH(option_value))/1024,0) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')");
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')");
        ?>
        <div class="fd-section">
            <h2><?php esc_html_e('Autoload Optimizer', 'freeman-digital'); ?></h2>
            <p><?php echo wp_kses(sprintf(__('WordPress loads ALL autoloaded options into memory on every single page request. A healthy site should have under 1 MB of autoloaded data. Yours: <strong>%1$s KB</strong> across <strong>%2$s</strong> options.', 'freeman-digital'), number_format((int)$total), number_format((int)$count)), array('strong' => array())); ?></p>

            <p>
                <button type="button" class="button button-primary" id="fd-audit-autoload"><?php esc_html_e('🔍 Audit Top 30 Largest Autoloaded Options', 'freeman-digital'); ?></button>
            </p>
            <div id="fd-autoload-results" style="margin-top:15px"></div>
        </div>

        <div class="fd-section">
            <h2><?php esc_html_e('Fix Large Autoloaded Options', 'freeman-digital'); ?></h2>
            <p><?php esc_html_e('Automatically set autoload=no for options larger than a threshold. This forces WordPress to load them only when actually needed.', 'freeman-digital'); ?></p>

            <?php $this->tog('auto_fix_large_options', __('Auto-fix on daily cron', 'freeman-digital'), $o, __('When enabled, the nightly maintenance job will automatically flip autoload to "no" for oversized options. <strong>Only</strong> triggers when total autoload exceeds the ceiling below.', 'freeman-digital')); ?>
            <?php $this->num('auto_large_threshold_kb', __('Per-option threshold (KB)', 'freeman-digital'), $o, 10, 10240, __('Individual options must exceed this size (in kilobytes) to be eligible for auto-fix. Default: 100 KB.', 'freeman-digital')); ?>
            <?php $this->num('auto_ceiling_mb', __('Total autoload ceiling (MB)', 'freeman-digital'), $o, 0, 100, __('Only runs the auto-fix pass when total autoload exceeds this ceiling. Default: 2 MB. Set to 0 to fire any time.', 'freeman-digital')); ?>

            <p><button type="button" class="button" id="fd-fix-autoload"><?php esc_html_e('⚡ Run Auto-Fix Now (using threshold above)', 'freeman-digital'); ?></button></p>
            <div id="fd-fix-autoload-msg" style="margin-top:10px"></div>
        </div>

        <?php
        $myisam = $wpdb->get_results($wpdb->prepare("SELECT TABLE_NAME, ENGINE FROM information_schema.tables WHERE table_schema=%s AND engine='MyISAM' AND table_name LIKE %s", DB_NAME, $wpdb->esc_like($wpdb->prefix) . '%'));
        if ($myisam): ?>
        <div class="fd-section">
            <h2><?php esc_html_e('MyISAM → InnoDB Conversion', 'freeman-digital'); ?></h2>
            <p><?php esc_html_e('InnoDB provides row-level locking, crash recovery, and better concurrent performance. The following tables are still MyISAM:', 'freeman-digital'); ?></p>
            <ul>
                <?php foreach ($myisam as $t): ?>
                    <li><code><?php echo esc_html($t->TABLE_NAME); ?></code></li>
                <?php endforeach; ?>
            </ul>
            <p><button type="button" class="button button-primary" id="fd-convert-myisam"><?php esc_html_e('🔄 Convert All to InnoDB', 'freeman-digital'); ?></button></p>
            <div id="fd-myisam-msg" style="margin-top:10px"></div>
        </div>
        <?php endif;
    }

    // ============================
    // TAB: PROFILER
    // ============================
    private function tab_profiler($o) {
        $active = FD_Profiler::is_active();
        $expires = FD_Profiler::get_expires();
        $threshold = FD_Profiler::get_threshold();
        $stats = FD_Profiler::get_stats();
        ?>
        <div class="fd-section">
            <h2><?php esc_html_e('🔬 Query Profiler', 'freeman-digital'); ?></h2>
            <p><?php esc_html_e('Captures slow database queries during a timed session, identifies WHICH plugin/theme caused each one, classifies the type of slowness, and recommends exactly which setting in our plugin will fix it. Only run this when diagnosing — it has performance overhead while active.', 'freeman-digital'); ?></p>

            <div class="fd-profiler-status">
                <?php if ($active): ?>
                    <div class="fd-alert fd-ok">
                        <strong><?php esc_html_e('✅ Profiler is ACTIVE', 'freeman-digital'); ?></strong> — <?php echo esc_html(sprintf(__('Expires in %1$s (%2$s)', 'freeman-digital'), human_time_diff(time(), $expires), date('H:i:s', $expires))); ?><br>
                        <span style="font-size:12px"><?php esc_html_e('Browse your site now. Visit slow pages, edit a product, load the WooCommerce orders page — anything you want to profile.', 'freeman-digital'); ?></span>
                    </div>
                    <p><button type="button" class="button button-primary" id="fd-prof-stop"><?php esc_html_e('⏹ Stop Profiler', 'freeman-digital'); ?></button></p>
                <?php else: ?>
                    <div class="fd-alert fd-info"><strong><?php esc_html_e('⚪ Profiler is OFF', 'freeman-digital'); ?></strong> — <?php esc_html_e('Click Start to begin capturing slow queries.', 'freeman-digital'); ?></div>
                    <p>
                        <label style="margin-right:15px"><strong><?php esc_html_e('Duration:', 'freeman-digital'); ?></strong>
                            <select id="fd-prof-duration">
                                <option value="5"><?php esc_html_e('5 minutes', 'freeman-digital'); ?></option>
                                <option value="15" selected><?php esc_html_e('15 minutes', 'freeman-digital'); ?></option>
                                <option value="30"><?php esc_html_e('30 minutes', 'freeman-digital'); ?></option>
                                <option value="60"><?php esc_html_e('60 minutes', 'freeman-digital'); ?></option>
                            </select>
                        </label>
                        <label style="margin-right:15px"><strong><?php esc_html_e('Threshold:', 'freeman-digital'); ?></strong>
                            <select id="fd-prof-threshold">
                                <option value="0.01"><?php esc_html_e('10 ms (aggressive)', 'freeman-digital'); ?></option>
                                <option value="0.05" <?php selected($threshold, 0.05); ?>><?php esc_html_e('50 ms (recommended)', 'freeman-digital'); ?></option>
                                <option value="0.1"><?php esc_html_e('100 ms', 'freeman-digital'); ?></option>
                                <option value="0.25"><?php esc_html_e('250 ms (only really slow)', 'freeman-digital'); ?></option>
                                <option value="0.5"><?php esc_html_e('500 ms', 'freeman-digital'); ?></option>
                                <option value="1"><?php esc_html_e('1000 ms', 'freeman-digital'); ?></option>
                            </select>
                        </label>
                        <button type="button" class="button button-primary" id="fd-prof-start"><?php esc_html_e('▶ Start Profiler', 'freeman-digital'); ?></button>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="fd-section">
            <h2><?php esc_html_e('🗄️ Retention', 'freeman-digital'); ?></h2>
            <p class="description"><?php esc_html_e('Nightly cleanup prunes the slow-query table by age AND enforces a hard row cap. Applies to the fd_slow_queries table only; all other activity is untouched.', 'freeman-digital'); ?></p>
            <form method="post" action="options.php" style="margin-top:10px">
                <?php settings_fields('fd_group'); ?>
                <input type="hidden" name="fd_active_tab" value="profiler">
                <div class="fd-field">
                    <label for="fd_prof_retention_days"><strong><?php esc_html_e('Retention (days):', 'freeman-digital'); ?></strong></label>
                    <input type="number" min="1" max="365" id="fd_prof_retention_days" name="<?php echo esc_attr(FD_OPT); ?>[prof_retention_days]" value="<?php echo esc_attr((int) ($o['prof_retention_days'] ?? 7)); ?>" style="width:90px">
                    <span class="description"><?php esc_html_e('Rows older than this are deleted nightly. Min 1, max 365.', 'freeman-digital'); ?></span>
                </div>
                <div class="fd-field">
                    <label for="fd_prof_max_rows"><strong><?php esc_html_e('Max rows:', 'freeman-digital'); ?></strong></label>
                    <input type="number" min="1000" max="1000000" step="1000" id="fd_prof_max_rows" name="<?php echo esc_attr(FD_OPT); ?>[prof_max_rows]" value="<?php echo esc_attr((int) ($o['prof_max_rows'] ?? 50000)); ?>" style="width:120px">
                    <span class="description"><?php esc_html_e('Hard ceiling — oldest rows trimmed first on overflow. Min 1,000, max 1,000,000.', 'freeman-digital'); ?></span>
                </div>
                <?php submit_button(__('Save Retention Settings', 'freeman-digital'), 'secondary'); ?>
            </form>
        </div>

        <?php if ($stats['total_queries'] > 0): ?>
        <div class="fd-section">
            <h2><?php esc_html_e('📊 Captured Data', 'freeman-digital'); ?></h2>
            <div class="fd-stats-grid">
                <div class="fd-card"><div class="fd-num"><?php echo number_format($stats['total_queries']); ?></div><div class="fd-lbl"><?php esc_html_e('Slow Queries Captured', 'freeman-digital'); ?></div></div>
                <div class="fd-card"><div class="fd-num"><?php echo number_format($stats['unique_queries']); ?></div><div class="fd-lbl"><?php esc_html_e('Unique Patterns', 'freeman-digital'); ?></div></div>
                <div class="fd-card"><div class="fd-num"><?php echo number_format($stats['total_time'], 2); ?>s</div><div class="fd-lbl"><?php esc_html_e('Total Slow Time', 'freeman-digital'); ?></div></div>
                <div class="fd-card"><div class="fd-num"><?php echo number_format($stats['max_time'] * 1000, 0); ?>ms</div><div class="fd-lbl"><?php esc_html_e('Slowest Query', 'freeman-digital'); ?></div></div>
                <div class="fd-card"><div class="fd-num"><?php echo number_format($stats['components']); ?></div><div class="fd-lbl"><?php esc_html_e('Components', 'freeman-digital'); ?></div></div>
            </div>

            <div class="fd-prof-filters">
                <label><input type="checkbox" id="fd-prof-hide-design" checked> <?php esc_html_e('Hide design/theme queries (Elementor, Plus Addons, Animation Addons, themes)', 'freeman-digital'); ?></label>
                <button type="button" class="button" id="fd-prof-refresh" style="margin-left:15px"><?php esc_html_e('🔄 Refresh', 'freeman-digital'); ?></button>
                <button type="button" class="button" id="fd-prof-clear" style="margin-left:8px"><?php esc_html_e('🗑️ Clear All Data', 'freeman-digital'); ?></button>
            </div>

            <div id="fd-prof-results">
                <?php $this->render_profiler_table(); ?>
            </div>
        </div>
        <?php else: ?>
        <div class="fd-section">
            <h2><?php esc_html_e('📊 No Data Yet', 'freeman-digital'); ?></h2>
            <p><?php esc_html_e('Start the profiler, browse your site (visit the slow pages, try editing a product, load the orders page), then come back here to see results.', 'freeman-digital'); ?></p>
        </div>
        <?php endif; ?>

        <!-- Details modal -->
        <div id="fd-prof-modal" style="display:none">
            <div class="fd-modal-overlay"></div>
            <div class="fd-modal-content">
                <span class="fd-modal-close">&times;</span>
                <div id="fd-modal-body"></div>
            </div>
        </div>
        <?php
    }

    private function render_profiler_table() {
        $rows_all = FD_Profiler::get_aggregated(array());
        $rows_filtered = FD_Profiler::get_aggregated(array('hide_design' => true));

        echo '<div id="fd-prof-table-all" style="display:none">';
        $this->render_profiler_rows($rows_all);
        echo '</div>';
        echo '<div id="fd-prof-table-filtered">';
        $this->render_profiler_rows($rows_filtered);
        echo '</div>';
    }

    private function render_profiler_rows($rows) {
        if (empty($rows)) {
            echo '<div class="fd-alert fd-info" style="margin-top:15px">' . esc_html__('No matching slow queries captured yet. Browse your site while the profiler is running.', 'freeman-digital') . '</div>';
            return;
        }
        ?>
        <table class="wp-list-table widefat striped fd-prof-table">
            <thead>
                <tr>
                    <th style="width:22%"><?php esc_html_e('Component', 'freeman-digital'); ?></th>
                    <th style="width:8%"><?php esc_html_e('Count', 'freeman-digital'); ?></th>
                    <th style="width:10%"><?php esc_html_e('Avg / Max', 'freeman-digital'); ?></th>
                    <th style="width:10%"><?php esc_html_e('Total Time', 'freeman-digital'); ?></th>
                    <th style="width:14%"><?php esc_html_e('Classification', 'freeman-digital'); ?></th>
                    <th><?php esc_html_e('Query / Fix', 'freeman-digital'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $cls_colors = array(
                    'calc_found_rows' => '#ff9800',
                    'meta_cast' => '#ff9800',
                    'leading_wildcard' => '#f44336',
                    'random_sort' => '#f44336',
                    'autoload' => '#ff9800',
                    'meta_n_plus_1' => '#ff9800',
                    'groupby_orderby' => '#ff9800',
                    'select_all' => '#2196f3',
                    'long_in' => '#2196f3',
                    'many_joins' => '#f44336',
                    'schema' => '#9e9e9e',
                    'options_like' => '#2196f3',
                    'generic' => '#9e9e9e',
                );
                $color = isset($cls_colors[$r->classification]) ? $cls_colors[$r->classification] : '#9e9e9e';
                $type_badge = '';
                if ($r->component_type === 'plugin') $type_badge = '🔌';
                elseif ($r->component_type === 'theme') $type_badge = '🎨';
                elseif ($r->component_type === 'core') $type_badge = '⚙️';
                elseif ($r->component_type === 'mu-plugin') $type_badge = '🔧';
            ?>
                <tr>
                    <td><strong><?php echo $type_badge; ?> <?php echo esc_html($r->component); ?></strong><br><span style="font-size:11px;color:#888"><?php echo esc_html($r->component_type); ?></span></td>
                    <td><?php echo number_format($r->occurrences); ?></td>
                    <td><?php echo number_format($r->avg_duration * 1000, 0); ?>ms<br><span style="font-size:11px;color:#888"><?php echo number_format($r->max_duration * 1000, 0); ?>ms max</span></td>
                    <td><strong><?php echo number_format($r->total_duration, 2); ?>s</strong></td>
                    <td><span style="background:<?php echo $color; ?>;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;text-transform:uppercase"><?php echo esc_html(str_replace('_', ' ', $r->classification)); ?></span></td>
                    <td>
                        <code style="display:block;font-size:11px;background:#f5f5f5;padding:5px;border-radius:3px;max-height:80px;overflow:auto;margin-bottom:5px"><?php echo esc_html(substr($r->query_sample, 0, 500)); ?></code>
                        <div style="font-size:12px;color:#2e7d32"><?php echo $r->recommendation; ?></div>
                        <button type="button" class="button button-small fd-prof-explain" data-fp="<?php echo esc_attr($r->fingerprint); ?>" style="margin-top:5px">🔍 EXPLAIN</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // ============================
    // TAB: BLOAT
    // ============================
    private function tab_bloat($o) {
        echo '<div class="fd-section"><h2>' . esc_html__('Bloat Removal', 'freeman-digital') . '</h2>';
        $this->tog('bloat_disable_comments', __('Disable Comments System Entirely', 'freeman-digital'), $o, __('Removes comment menus, meta boxes, admin bar items, closes all comments. <strong>Only if your store doesn\'t use product reviews.</strong>', 'freeman-digital'));
        $this->tog('bloat_disable_gutenberg', __('Disable Gutenberg Block Editor', 'freeman-digital'), $o, __('Restores classic editor and removes all block editor frontend assets (~200KB). <strong>Recommended for Elementor-only sites.</strong>', 'freeman-digital'));
        $this->tog('bloat_remove_dns_prefetch', __('Remove DNS Prefetch Hints', 'freeman-digital'), $o, __('Removes dns-prefetch resource hints from wp_head including s.w.org.', 'freeman-digital'));
        echo '</div>';
    }

    // ============================
    // TAB: ACTIVITY LOG
    // ============================
    private function tab_activity($o) {
        $log = class_exists('FD_Activity_Log') ? FD_Activity_Log::get_all() : array();
        $action_labels = array(
            'database_cleanup'          => __('Database Cleanup', 'freeman-digital'),
            'optimize_tables'           => __('Optimize Tables', 'freeman-digital'),
            'deep_reindex_apply'        => __('Deep Reindex Applied', 'freeman-digital'),
            'deep_reindex_revert'       => __('Deep Reindex Reverted', 'freeman-digital'),
            'secondary_indexes_update'  => __('Secondary Indexes Updated', 'freeman-digital'),
            'secondary_indexes_drop_all'=> __('Secondary Indexes Dropped', 'freeman-digital'),
            'autoload_auto_fix'         => __('Autoload Auto-Fix (cron)', 'freeman-digital'),
            'autoload_manual_fix'       => __('Autoload Manual Fix', 'freeman-digital'),
            'myisam_convert'            => __('MyISAM → InnoDB', 'freeman-digital'),
        );
        ?>
        <div class="fd-section">
            <h2><?php esc_html_e('📜 Activity Log', 'freeman-digital'); ?></h2>
            <p><?php echo esc_html(sprintf(__('Last %d destructive actions performed by Freeman Digital. Newest first. Rolling FIFO — older entries are pruned automatically.', 'freeman-digital'), FD_Activity_Log::MAX_ENTRIES)); ?></p>

            <p>
                <button type="button" class="button" id="fd-clear-log"><?php esc_html_e('🗑️ Clear log', 'freeman-digital'); ?></button>
            </p>
            <div id="fd-clear-log-msg" style="margin-top:10px"></div>

            <?php if (empty($log)): ?>
                <div class="fd-alert fd-info"><?php esc_html_e('No activity recorded yet.', 'freeman-digital'); ?></div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:160px"><?php esc_html_e('When', 'freeman-digital'); ?></th>
                            <th style="width:130px"><?php esc_html_e('User', 'freeman-digital'); ?></th>
                            <th style="width:240px"><?php esc_html_e('Action', 'freeman-digital'); ?></th>
                            <th style="width:110px"><?php esc_html_e('Rows Affected', 'freeman-digital'); ?></th>
                            <th><?php esc_html_e('Details', 'freeman-digital'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($log as $e): ?>
                        <?php
                        $label = isset($action_labels[$e['action']]) ? $action_labels[$e['action']] : $e['action'];
                        $user = !empty($e['user_login']) ? $e['user_login'] : '(' . $e['user_id'] . ')';
                        $meta = isset($e['meta']) && is_array($e['meta']) ? $e['meta'] : array();
                        // Strip rows_affected from the details blob to avoid duplicate display.
                        unset($meta['rows_affected']);
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($e['timestamp']); ?></code></td>
                            <td><?php echo esc_html($user); ?></td>
                            <td><?php echo esc_html($label); ?></td>
                            <td><?php echo isset($e['rows_affected']) ? esc_html(number_format((int) $e['rows_affected'])) : '—'; ?></td>
                            <td><code style="font-size:11px;color:#555"><?php echo esc_html(wp_json_encode($meta)); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ============================
    // TAB: TOOLS
    // ============================
    private function tab_tools($o) {
        ?>
        <div class="fd-section">
            <h2><?php esc_html_e('Export Settings', 'freeman-digital'); ?></h2>
            <textarea id="fd-export" class="large-text" rows="8" readonly><?php echo esc_textarea(wp_json_encode($o, JSON_PRETTY_PRINT)); ?></textarea>
            <p><button type="button" class="button" id="fd-copy"><?php esc_html_e('📋 Copy', 'freeman-digital'); ?></button> <button type="button" class="button" id="fd-download"><?php esc_html_e('💾 Download JSON', 'freeman-digital'); ?></button></p>
        </div>
        <div class="fd-section">
            <h2><?php esc_html_e('Import Settings', 'freeman-digital'); ?></h2>
            <textarea id="fd-import" class="large-text" rows="8" placeholder="<?php esc_attr_e('Paste settings JSON...', 'freeman-digital'); ?>"></textarea>
            <p><button type="button" class="button button-primary" id="fd-do-import"><?php esc_html_e('📥 Import', 'freeman-digital'); ?></button></p>
            <div id="fd-import-msg" style="margin-top:10px"></div>
        </div>

        <div class="fd-section">
            <h2><?php esc_html_e('Changelog', 'freeman-digital'); ?></h2>
            <div style="background:#f5f7fa;padding:15px 20px;border-radius:6px;border:1px solid #e0e5ec;font-size:13px;line-height:1.8;">
                <strong>v1.7.0</strong> — <strong>Third-audit fixes — 15 more critical issues.</strong> <strong>Fixed the flagship Smart Elementor Cleanup feature</strong> which had never worked — was using function_exists() on a class name (should be class_exists), so every non-Elementor page was loading the full Elementor bundle anyway. Fixed the Phone Home Blocker wildcard pattern bug (preg_quote ran before str_replace, escaping * before substitution). Fixed tab routing security: ?tab= parameter now validated against a whitelist with method_exists double-guard — prevents PHP fatals and arbitrary method calls. Fixed wc_defer_term_counting which was permanently breaking category counts (called defer(true) without matching defer(false)). Replaced obsolete woocommerce_admin_disabled filter (removed in WC 6.0) with modern WC 7+ APIs that actually work. Fixed transient cleanup REPLACE() bug that corrupted keys containing "_timeout_" substring. Moved months dropdown cache from options to transients (with save_post invalidation and cleanup of legacy rows). Added REST API detection to query optimizer — REST consumers no longer get found_posts silently zeroed. Added XSS hardening to all form helpers (esc_html labels, wp_kses descriptions with safe HTML whitelist). Added uninstall.php that cleans up all plugin data when deleted. Set_time_limit now guarded for safe_mode environments. Disable mobile animations now uses wp_add_inline_style instead of raw wp_head echo.<br>
                <strong>v1.6.0</strong> — <strong>Major audit cleanup — 22 issues fixed.</strong> Fixed Google Fonts optimization no-op bug (str_replace was replacing href with itself). Fixed invalid bare @font-face CSS. Fixed session_tokens cleanup (now properly deserializes and prunes expired tokens). Implemented 5 previously-dead toggles: wc_ajax_attribute_edit (AJAX attribute search on edit-product), adm_cache_user_counts, adm_cache_author_counts, adm_cache_category_list (new FD_Admin_Cache module), and auto_convert_myisam (daily MyISAM→InnoDB conversion). Autoload module now respects auto_fix_large_options toggle and auto_large_threshold_kb setting (was hardcoded). Eliminated duplicate hook registrations across security/speed/frontend modules. Fixed activation cron strtotime('03:00') bug that could schedule in the past. Fixed SQL escaping inconsistency (MyISAM query, SHOW TABLES LIKE checks now use $wpdb->prepare). Corrected misleading wc_remove_variation_calc description.<br>
                <strong>v1.5.0</strong> — <strong>NEW: Query Profiler module.</strong> Captures slow database queries with full plugin/theme attribution, smart classification, and actionable recommendations linking to specific Freeman Digital settings. Runtime-toggleable (5-60 min sessions) so it has no overhead when off. "Hide design queries" filter excludes Elementor, Plus Addons, Animation Addons, and theme files so you see only actionable database bottlenecks. Click any query to see its EXPLAIN execution plan with table scan/filesort warnings highlighted. Stores data in custom table created on activation.<br>
                <strong>v1.4.0</strong> — <strong>NEW: Frontend Optimizer module (12 toggles).</strong> Smart Elementor cleanup (removes all Elementor assets on non-Elementor pages), defer Elementor JS, disable animations on mobile (fixes CLS/LCP), disable Elementor Google Fonts, font-display:swap enforcement, preconnect resource hints, LCP image preload, fetchpriority="high" for first image, WooCommerce Blocks CSS removal, iframe lazy loading, conditional Contact Form 7 loading. This module targets the actual PageSpeed bottlenecks: render-blocking CSS/JS, font loading, and image priority.<br>
                <strong>v1.3.0</strong> — <strong>Major: Integrated Index WP MySQL For Speed approach.</strong> New Tier 1 "Deep Reindex" restructures PRIMARY KEYs on 10 core tables (postmeta, usermeta, termmeta, commentmeta, options, comments, posts, users, wc_order_itemmeta, wc_orders_meta) into compound clustered indexes — the highest-impact DB optimization possible. Auto-detects Barracuda vs Antelope InnoDB format. Full revert capability. Tier 2 now has 18 secondary indexes for tables Tier 1 doesn't cover.<br>
                <strong>v1.2.0</strong> — Settings saving fix (tabs no longer reset each other). Index WP MySQL For Speed comparison analysis.<br>
                <strong>v1.1.0</strong> — Renamed to Freeman Digital. Added Autoload Optimizer tab (audit top 30 largest autoloaded options, auto-fix large options, MyISAM→InnoDB converter). Added WooCommerce transient cleanup. Added expired user session token cleanup. Added WC Admin AJAX bloat reducer. Added WC setup wizard disable. Added orphaned commentmeta cleanup. Full code audit — every toggle verified with working hooks. New dashboard stats for autoload size. MyISAM detection warning.<br>
                <strong>v1.0.0</strong> — Initial release based on Scalability Pro analysis. Query optimizer, WooCommerce optimization, database indexes, security hardening, speed tuning, database cleanup, bloat removal, import/export tools.
            </div>
        </div>
        <?php
    }

    // ============================
    // FIELD HELPERS
    // ============================
    private function tog($k, $label, $o, $desc = '') {
        $c = !empty($o[$k]) ? 'checked' : '';
        $k_esc = esc_attr($k);
        $opt_esc = esc_attr(FD_OPT);
        echo '<div class="fd-toggle-row"><label class="fd-toggle">';
        echo '<input type="hidden" name="' . $opt_esc . '[' . $k_esc . ']" value="0">';
        echo '<input type="checkbox" name="' . $opt_esc . '[' . $k_esc . ']" value="1" ' . $c . '>';
        echo '<span class="fd-slider"></span></label>';
        echo '<div class="fd-toggle-txt"><strong>' . esc_html($label) . '</strong>';
        if ($desc) echo '<p class="description">' . wp_kses($desc, $this->allowed_desc_html()) . '</p>';
        echo '</div></div>';
    }

    private function sel($k, $label, $o, $choices, $desc = '') {
        $k_esc = esc_attr($k);
        $opt_esc = esc_attr(FD_OPT);
        echo '<div class="fd-field"><label><strong>' . esc_html($label) . '</strong></label>';
        echo '<select name="' . $opt_esc . '[' . $k_esc . ']">';
        foreach ($choices as $v => $t) {
            echo '<option value="' . esc_attr($v) . '"' . selected($o[$k], $v, false) . '>' . esc_html($t) . '</option>';
        }
        echo '</select>';
        if ($desc) echo '<p class="description">' . wp_kses($desc, $this->allowed_desc_html()) . '</p>';
        echo '</div>';
    }

    private function num($k, $label, $o, $min, $max, $desc = '') {
        $k_esc = esc_attr($k);
        $opt_esc = esc_attr(FD_OPT);
        echo '<div class="fd-field"><label><strong>' . esc_html($label) . '</strong></label>';
        echo '<input type="number" name="' . $opt_esc . '[' . $k_esc . ']" value="' . esc_attr($o[$k]) . '" min="' . (int)$min . '" max="' . (int)$max . '" style="width:80px">';
        if ($desc) echo '<p class="description">' . wp_kses($desc, $this->allowed_desc_html()) . '</p>';
        echo '</div>';
    }

    /**
     * Whitelist of HTML tags allowed in toggle descriptions.
     * Keeps our intentional <strong>, <code>, <em> while blocking scripts/styles.
     */
    private function allowed_desc_html() {
        return array(
            'strong' => array(),
            'em' => array(),
            'code' => array(),
            'br' => array(),
            'a' => array('href' => array(), 'target' => array(), 'rel' => array()),
        );
    }

    // ============================
    // AJAX HANDLERS
    // ============================
    public function ajax_cleanup() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        wp_send_json_success((new FD_Database(FD_Core::opts()))->run_cleanup());
    }

    public function ajax_optimize_tables() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        $opts = FD_Core::opts();
        if (empty($opts['db_optimize_tables'])) {
            wp_send_json_error(array('message' => esc_html__('Enable "Allow OPTIMIZE TABLE" in the Cleanup tab first.', 'freeman-digital')));
            return;
        }
        $result = (new FD_Database($opts))->run_optimize_tables();
        wp_send_json_success($result);
    }

    public function ajax_clear_activity_log() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        if (class_exists('FD_Activity_Log')) {
            FD_Activity_Log::clear();
        }
        wp_send_json_success(array('message' => esc_html__('Activity log cleared.', 'freeman-digital')));
    }

    public function ajax_create_idx() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        $sel = isset($_POST['indexes']) ? array_map('sanitize_text_field', $_POST['indexes']) : array();
        wp_send_json_success(FD_Indexes::create($sel));
    }

    public function ajax_drop_idx() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        FD_Indexes::drop_all();
        wp_send_json_success(array('message' => esc_html__('All secondary indexes dropped.', 'freeman-digital')));
    }

    public function ajax_deep_reindex() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        $tables = isset($_POST['tables']) ? array_map('sanitize_text_field', $_POST['tables']) : array();
        wp_send_json_success(FD_Indexes::apply_deep($tables));
    }

    public function ajax_deep_revert() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        $tables = isset($_POST['tables']) ? array_map('sanitize_text_field', $_POST['tables']) : array();
        wp_send_json_success(FD_Indexes::revert_deep($tables));
    }

    public function ajax_import() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        $data = json_decode(wp_unslash($_POST['settings']), true);
        if (!is_array($data)) { wp_send_json_error(array('message' => esc_html__('Invalid JSON', 'freeman-digital'))); return; }
        update_option(FD_OPT, $this->sanitize($data));
        wp_send_json_success(array('message' => esc_html__('Settings imported.', 'freeman-digital')));
    }

    public function ajax_audit_autoload() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        global $wpdb;
        $rows = $wpdb->get_results("SELECT option_name, ROUND(LENGTH(option_value)/1024,1) AS size_kb FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on') ORDER BY LENGTH(option_value) DESC LIMIT 30");
        wp_send_json_success($rows);
    }

    public function ajax_fix_autoload() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        global $wpdb;

        $opts = FD_Core::opts();
        $threshold_kb = isset($opts['auto_large_threshold_kb']) ? max(10, (int)$opts['auto_large_threshold_kb']) : 100;
        $threshold_bytes = $threshold_kb * 1024;

        $protected = FD_Autoload::get_protected_options();
        $placeholders = implode(',', array_fill(0, count($protected), '%s'));

        $fixed = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET autoload='no'
             WHERE autoload IN ('yes','on','auto','auto-on')
             AND LENGTH(option_value) > %d
             AND option_name NOT IN ($placeholders)",
            array_merge(array($threshold_bytes), $protected)
        ));
        if (class_exists('FD_Activity_Log') && (int) $fixed > 0) {
            FD_Activity_Log::record('autoload_manual_fix', array(
                'rows_affected' => (int) $fixed,
                'threshold_kb'  => $threshold_kb,
            ));
        }
        wp_send_json_success(array('fixed' => (int) $fixed, 'threshold_kb' => $threshold_kb));
    }

    public function ajax_convert_myisam() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        global $wpdb;
        $tables = $wpdb->get_col($wpdb->prepare(
            "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=%s AND engine='MyISAM' AND table_name LIKE %s",
            DB_NAME,
            $wpdb->esc_like($wpdb->prefix) . '%'
        ));
        $count = 0;
        $errors = array();
        foreach ($tables as $t) {
            // Defense in depth: validate table name format AND prefix match before interpolation.
            // MySQL identifiers allow [a-zA-Z0-9_$] but we're stricter here for WordPress tables.
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $t)) {
                $errors[] = "Skipped invalid table name";
                continue;
            }
            if (strpos($t, $wpdb->prefix) !== 0) {
                continue;
            }
            $result = $wpdb->query("ALTER TABLE `{$t}` ENGINE=InnoDB");
            if ($result !== false) $count++;
            else $errors[] = $t . ': ' . $wpdb->last_error;
        }
        if (class_exists('FD_Activity_Log') && $count > 0) {
            FD_Activity_Log::record('myisam_convert', array(
                'rows_affected' => $count,
                'errors'        => $errors,
            ));
        }
        wp_send_json_success(array('converted' => $count, 'errors' => $errors));
    }

    // === PROFILER AJAX ===

    public function ajax_profiler_start() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        $duration = isset($_POST['duration']) ? (int) $_POST['duration'] : 15;
        $threshold = isset($_POST['threshold']) ? (float) $_POST['threshold'] : 0.05;
        FD_Profiler::set_threshold($threshold);
        FD_Profiler::start($duration);
        wp_send_json_success(array('message' => sprintf(__('Profiler started for %d minutes.', 'freeman-digital'), $duration)));
    }

    public function ajax_profiler_stop() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        FD_Profiler::stop();
        wp_send_json_success(array('message' => esc_html__('Profiler stopped.', 'freeman-digital')));
    }

    public function ajax_profiler_clear() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        FD_Profiler::clear_data();
        wp_send_json_success(array('message' => esc_html__('All captured data cleared.', 'freeman-digital')));
    }

    public function ajax_profiler_explain() {
        check_ajax_referer('fd_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();
        $fp = isset($_POST['fingerprint']) ? sanitize_text_field($_POST['fingerprint']) : '';
        if (!$fp) wp_send_json_error(array('message' => esc_html__('Missing fingerprint', 'freeman-digital')));
        $result = FD_Profiler::explain_query($fp);
        wp_send_json_success($result);
    }
}
