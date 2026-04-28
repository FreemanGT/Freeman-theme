<?php
/**
 * Freeman Digital — Uninstall Handler
 *
 * Runs when the user deletes the plugin via Plugins → Delete.
 * Does NOT run on deactivation. Cleans up all plugin data:
 *  - Settings options
 *  - Cached month data (if any legacy rows remain)
 *  - Profiler table
 *  - Scheduled cron events
 *  - Transients
 *  - Index data (optionally — keeps Tier 1/2 indexes intact as they improve the DB overall)
 *
 * To preserve database indexes (recommended), we do NOT drop fd_* indexes on uninstall.
 * Users who want to fully revert should click "Revert to WordPress Standard" in the
 * Indexes tab BEFORE uninstalling.
 */

// Only run if WordPress is uninstalling this plugin
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. Delete main settings option
delete_option('fd_settings');
delete_option('fd_profiler_expires');
delete_option('fd_profiler_threshold');
delete_option('fd_legacy_months_cleaned');
delete_option('fd_activity_log');
delete_option('fd_ddl_capability');

// 2. Delete all cached month transients and any legacy option rows
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'fd_months_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fd_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_fd_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fd_cat_dropdown_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fd_user_counts_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fd_author_count_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fd_has_products'");

// 3. Drop the profiler table
$profiler_table = $wpdb->prefix . 'fd_slow_queries';
$wpdb->query("DROP TABLE IF EXISTS `{$profiler_table}`");

// 4. Clear any scheduled cron events
wp_clear_scheduled_hook('fd_daily_maintenance');

// 5. Clear any remaining site transients on multisite
if (is_multisite()) {
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_fd_%'");
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_fd_%'");
}

// NOTE: We intentionally do NOT drop the fd_* database indexes or revert the Tier 1 deep reindex.
// These are improvements to the database structure itself and benefit the site regardless of
// whether this plugin is installed. If a user wants to fully revert, they should use the
// "Revert to WordPress Standard" button in the Indexes tab BEFORE uninstalling.
