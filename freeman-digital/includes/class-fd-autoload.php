<?php
if (!defined('ABSPATH')) exit;

class FD_Autoload {
    private $o;

    public function __construct($o) {
        $this->o = $o;
        if (!empty($o['auto_audit_enabled'])) {
            add_action('fd_daily_maintenance', array($this, 'daily_check'));
        }
    }

    /**
     * Daily autoload health check.
     * Respects user-configured threshold and auto-fix toggle.
     */
    public function daily_check() {
        // Honor the auto_fix_large_options toggle — if off, do nothing
        if (empty($this->o['auto_fix_large_options'])) return;

        global $wpdb;
        $threshold_kb = isset($this->o['auto_large_threshold_kb']) ? max(10, (int)$this->o['auto_large_threshold_kb']) : 100;
        $threshold_bytes = $threshold_kb * 1024;

        // Trigger only when total autoload exceeds the configured ceiling (default 2 MB).
        // Users with known-heavy autoload tables can raise this; lower values fire more aggressively.
        $ceiling_mb = isset($this->o['auto_ceiling_mb']) ? max(0, (float) $this->o['auto_ceiling_mb']) : 2.0;
        $ceiling_bytes = (int) ($ceiling_mb * 1024 * 1024);
        $size = (int) $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')");
        if ($ceiling_bytes > 0 && $size <= $ceiling_bytes) return;

        $protected = apply_filters('fd/protected_autoload_options', self::get_protected_options());
        $placeholders = implode(',', array_fill(0, count($protected), '%s'));

        $sql = $wpdb->prepare(
            "UPDATE {$wpdb->options} SET autoload='no'
             WHERE autoload IN ('yes','on','auto','auto-on')
             AND LENGTH(option_value) > %d
             AND option_name NOT IN ($placeholders)",
            array_merge(array($threshold_bytes), $protected)
        );
        $fixed = $wpdb->query($sql);

        if (class_exists('FD_Activity_Log') && (int) $fixed > 0) {
            FD_Activity_Log::record('autoload_auto_fix', array(
                'rows_affected' => (int) $fixed,
                'threshold_kb'  => $threshold_kb,
                'ceiling_mb'    => $ceiling_mb,
                'autoload_size' => $size,
                'source'        => 'cron',
            ));
        }
    }

    /**
     * Critical WordPress options that must always autoload.
     * Centralized so admin AJAX and daily check stay in sync.
     */
    public static function get_protected_options() {
        return array(
            'siteurl', 'home', 'blogname', 'blogdescription', 'users_can_register',
            'admin_email', 'start_of_week', 'use_balanceTags', 'use_smilies',
            'require_name_email', 'comments_notify', 'posts_per_rss', 'rss_use_excerpt',
            'mailserver_url', 'mailserver_login', 'mailserver_pass', 'mailserver_port',
            'default_category', 'default_comment_status', 'default_ping_status',
            'default_pingback_flag', 'posts_per_page', 'date_format', 'time_format',
            'links_updated_date_format', 'comment_moderation', 'moderation_notify',
            'permalink_structure', 'rewrite_rules', 'hack_file', 'blog_charset',
            'moderation_keys', 'active_plugins', 'category_base', 'ping_sites',
            'comment_max_links', 'gmt_offset', 'default_email_category',
            'recently_edited', 'template', 'stylesheet', 'comment_registration',
            'html_type', 'use_trackback', 'default_role', 'db_version', 'uploads_use_yearmonth_folders',
            'upload_path', 'blog_public', 'default_link_category', 'show_on_front',
            'tag_base', 'show_avatars', 'avatar_rating', 'upload_url_path', 'thumbnail_size_w',
            'thumbnail_size_h', 'thumbnail_crop', 'medium_size_w', 'medium_size_h',
            'avatar_default', 'large_size_w', 'large_size_h', 'image_default_link_type',
            'image_default_size', 'image_default_align', 'close_comments_for_old_posts',
            'close_comments_days_old', 'thread_comments', 'thread_comments_depth',
            'page_comments', 'comments_per_page', 'default_comments_page', 'comment_order',
            'sticky_posts', 'widget_categories', 'widget_text', 'widget_rss', 'widget_block',
            'sidebars_widgets', 'cron', 'current_theme', 'theme_mods_' . get_option('stylesheet'),
            'WPLANG', 'new_admin_email', 'recently_activated', 'auto_update_core_dev',
            'auto_update_core_minor', 'auto_update_core_major', 'wp_force_deactivated_plugins',
            'finished_splitting_shared_terms', 'site_icon', 'fresh_site',
            'fd_settings', 'fd_profiler_expires', 'fd_profiler_threshold',
        );
    }
}
