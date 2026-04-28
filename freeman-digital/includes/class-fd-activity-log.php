<?php
if (!defined('ABSPATH')) exit;

/**
 * Freeman Digital — Activity Log
 *
 * Rolling FIFO log of every destructive action the plugin performs. Capped at
 * MAX_ENTRIES rows so the option itself never becomes a performance problem.
 *
 * Stored as a regular (autoload=no) option so it doesn't balloon wp_options
 * memory on every page load.
 */
class FD_Activity_Log {
    const OPTION     = 'fd_activity_log';
    const MAX_ENTRIES = 200;

    /**
     * Push an entry onto the log. Silently no-ops on failure; this class must
     * never bubble an exception up to a destructive path that just succeeded.
     *
     * @param string $action  Short identifier, e.g. 'db_cleanup', 'deep_reindex_apply'.
     * @param array  $meta    Arbitrary metadata (rows_affected, table counts, etc.).
     */
    public static function record($action, array $meta = array()) {
        $action = sanitize_key($action);
        if ($action === '') return;

        $entry = array(
            'timestamp'     => current_time('mysql'),
            'user_id'       => get_current_user_id(),
            'user_login'    => self::safe_user_login(),
            'action'        => $action,
            'rows_affected' => isset($meta['rows_affected']) ? (int) $meta['rows_affected'] : null,
            'meta'          => $meta,
        );

        $log = get_option(self::OPTION, array());
        if (!is_array($log)) $log = array();

        array_unshift($log, $entry);

        if (count($log) > self::MAX_ENTRIES) {
            $log = array_slice($log, 0, self::MAX_ENTRIES);
        }

        // Explicit autoload=no to avoid bloating page-load memory on every request.
        update_option(self::OPTION, $log, false);
    }

    public static function get_all() {
        $log = get_option(self::OPTION, array());
        return is_array($log) ? $log : array();
    }

    public static function clear() {
        delete_option(self::OPTION);
    }

    private static function safe_user_login() {
        $u = wp_get_current_user();
        if ($u && $u->exists()) return $u->user_login;
        if (defined('WP_CLI') && WP_CLI) return 'wp-cli';
        if (wp_doing_cron()) return 'cron';
        return '';
    }
}
