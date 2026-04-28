<?php
if (!defined('ABSPATH')) exit;
if (!defined('WP_CLI') || !WP_CLI) return;

/**
 * Freeman Digital — WP-CLI commands.
 *
 * Guarded at the top so this file is only ever parsed inside a `wp` invocation.
 * All long-running commands raise the PHP time-limit defensively and surface
 * progress via WP_CLI::log so long-running store cleanups remain inspectable.
 *
 *   wp fd cleanup [--dry-run]
 *   wp fd optimize-tables
 *   wp fd reindex [--tables=<csv>] [--revert]
 *   wp fd autoload audit|fix [--threshold-kb=<int>]
 *   wp fd profiler start|stop|status|clear [--duration=<min>] [--threshold=<secs>]
 *   wp fd export [--path=<file>]
 *   wp fd import <file>
 */
class FD_CLI {

    /**
     * Run database cleanup.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Report the row counts that would be deleted, but don't actually delete anything.
     *
     * @when after_wp_load
     */
    public function cleanup($args, $assoc) {
        $dry = !empty($assoc['dry-run']);
        $opts = FD_Core::opts();
        $db = new FD_Database($opts);

        if ($dry) {
            global $wpdb;
            $stats = array(
                'revisions'          => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision'"),
                'auto_drafts'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='auto-draft'"),
                'trashed_posts'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='trash'"),
                'spam_comments'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='spam'"),
                'trashed_comments'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='trash'"),
                'expired_transients' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_%' AND option_value < " . time()),
            );
            $rows = array();
            foreach ($stats as $k => $v) $rows[] = array('bucket' => $k, 'would_delete' => $v);
            WP_CLI\Utils\format_items('table', $rows, array('bucket', 'would_delete'));
            return;
        }

        @set_time_limit(0);
        WP_CLI::log('Running Freeman Digital cleanup...');
        $results = $db->run_cleanup();
        $rows = array();
        foreach ($results as $k => $v) $rows[] = array('bucket' => $k, 'deleted' => $v);
        WP_CLI\Utils\format_items('table', $rows, array('bucket', 'deleted'));
        WP_CLI::success('Cleanup complete.');
    }

    /**
     * Rebuild indexes with OPTIMIZE TABLE.
     *
     * @when after_wp_load
     */
    public function optimize_tables($args, $assoc) {
        $opts = FD_Core::opts();
        if (empty($opts['db_optimize_tables'])) {
            WP_CLI::error('db_optimize_tables is disabled in plugin settings. Enable it first (or edit wp_options directly).');
            return;
        }
        @set_time_limit(0);
        WP_CLI::log('Running OPTIMIZE TABLE — this may take several minutes on large stores.');
        $r = (new FD_Database($opts))->run_optimize_tables();
        WP_CLI::success(sprintf('Optimized %d tables (%d errors).', isset($r['optimized']) ? (int) $r['optimized'] : 0, isset($r['errors']) ? count($r['errors']) : 0));
    }

    /**
     * Apply or revert the Tier 1 deep reindex.
     *
     * ## OPTIONS
     *
     * [--tables=<list>]
     * : CSV of tables to target. Defaults to all detected reindex-eligible tables.
     *
     * [--revert]
     * : Revert instead of applying.
     *
     * @when after_wp_load
     */
    public function reindex($args, $assoc) {
        $tables = array();
        if (!empty($assoc['tables'])) {
            $tables = array_filter(array_map('trim', explode(',', $assoc['tables'])));
        } else {
            $tables = array_keys(FD_Indexes::get_deep_status());
        }

        @set_time_limit(0);
        if (!empty($assoc['revert'])) {
            WP_CLI::log('Reverting deep reindex on ' . count($tables) . ' table(s)...');
            $r = FD_Indexes::revert_deep($tables);
            WP_CLI::success('Revert complete: ' . wp_json_encode($r));
        } else {
            WP_CLI::log('Applying deep reindex on ' . count($tables) . ' table(s)...');
            $r = FD_Indexes::apply_deep($tables);
            WP_CLI::success('Apply complete: ' . wp_json_encode($r));
        }
    }

    /**
     * Autoload audit / fix.
     *
     * ## OPTIONS
     *
     * <command>
     * : audit or fix
     *
     * [--threshold-kb=<int>]
     * : Per-option size threshold for fix (default: plugin setting, fallback 100).
     *
     * @when after_wp_load
     */
    public function autoload($args, $assoc) {
        $sub = isset($args[0]) ? $args[0] : 'audit';
        global $wpdb;
        $opts = FD_Core::opts();

        if ($sub === 'audit') {
            $rows = $wpdb->get_results("SELECT option_name, ROUND(LENGTH(option_value)/1024,1) AS size_kb FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on') ORDER BY LENGTH(option_value) DESC LIMIT 30", ARRAY_A);
            WP_CLI\Utils\format_items('table', $rows, array('option_name', 'size_kb'));
            return;
        }

        if ($sub !== 'fix') {
            WP_CLI::error('Unknown autoload subcommand. Use "audit" or "fix".');
            return;
        }

        $threshold_kb = isset($assoc['threshold-kb']) ? (int) $assoc['threshold-kb'] : (int) $opts['auto_large_threshold_kb'];
        $threshold_kb = max(10, $threshold_kb);
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
                'source'        => 'wp-cli',
            ));
        }
        WP_CLI::success(sprintf('Flipped %d autoloaded options > %d KB to autoload=no.', (int) $fixed, $threshold_kb));
    }

    /**
     * Query profiler control.
     *
     * ## OPTIONS
     *
     * <command>
     * : start, stop, status, clear
     *
     * [--duration=<minutes>]
     * : Profiler duration for start (default: 15).
     *
     * [--threshold=<seconds>]
     * : Slow-query threshold for start (default: 0.05).
     *
     * @when after_wp_load
     */
    public function profiler($args, $assoc) {
        $sub = isset($args[0]) ? $args[0] : 'status';
        switch ($sub) {
            case 'start':
                $dur = isset($assoc['duration']) ? (int) $assoc['duration'] : 15;
                $thr = isset($assoc['threshold']) ? (float) $assoc['threshold'] : 0.05;
                FD_Profiler::set_threshold($thr);
                FD_Profiler::start($dur);
                WP_CLI::success(sprintf('Profiler started for %d minutes (threshold %.3fs).', $dur, $thr));
                break;
            case 'stop':
                FD_Profiler::stop();
                WP_CLI::success('Profiler stopped.');
                break;
            case 'clear':
                FD_Profiler::clear_data();
                WP_CLI::success('Profiler data cleared.');
                break;
            case 'status':
            default:
                $stats = FD_Profiler::get_stats();
                WP_CLI::log('Active:     ' . (FD_Profiler::is_active() ? 'yes' : 'no'));
                WP_CLI::log('Threshold:  ' . FD_Profiler::get_threshold() . 's');
                WP_CLI::log('Expires:    ' . date('c', FD_Profiler::get_expires()));
                WP_CLI::log('Queries:    ' . (int) $stats['total_queries']);
                WP_CLI::log('Unique:     ' . (int) $stats['unique_queries']);
                break;
        }
    }

    /**
     * Export settings JSON.
     *
     * ## OPTIONS
     *
     * [--path=<file>]
     * : Target path (defaults to stdout).
     *
     * @when after_wp_load
     */
    public function export($args, $assoc) {
        $opts = FD_Core::opts();
        $json = wp_json_encode($opts, JSON_PRETTY_PRINT);
        if (!empty($assoc['path'])) {
            if (file_put_contents($assoc['path'], $json) === false) {
                WP_CLI::error('Failed to write ' . $assoc['path']);
            }
            WP_CLI::success('Exported settings to ' . $assoc['path']);
        } else {
            WP_CLI::line($json);
        }
    }

    /**
     * Import settings JSON.
     *
     * ## OPTIONS
     *
     * <file>
     * : JSON file to import.
     *
     * @when after_wp_load
     */
    public function import($args, $assoc) {
        if (empty($args[0])) {
            WP_CLI::error('Usage: wp fd import <file>');
            return;
        }
        $path = $args[0];
        if (!is_readable($path)) {
            WP_CLI::error("Cannot read {$path}");
            return;
        }
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            WP_CLI::error('Invalid JSON.');
            return;
        }
        // Borrow FD_Admin::sanitize() semantics. FD_Admin is admin-only, so it
        // may not be instantiated under WP-CLI — construct it manually.
        if (!class_exists('FD_Admin')) {
            WP_CLI::error('FD_Admin class not available.');
            return;
        }
        $admin = new FD_Admin();
        $sanitized = $admin->sanitize($data);
        update_option(FD_OPT, $sanitized);
        WP_CLI::success('Settings imported.');
    }
}

WP_CLI::add_command('fd', 'FD_CLI');
