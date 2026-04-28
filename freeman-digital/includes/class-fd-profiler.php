<?php
if (!defined('ABSPATH')) exit;

/**
 * Freeman Digital — Query Profiler
 *
 * Captures slow database queries with component attribution, classification,
 * and actionable recommendations. Runtime-toggleable so it only runs when
 * you need it (has performance overhead when active).
 */
class FD_Profiler {

    const TABLE = 'fd_slow_queries';

    public function __construct($o) {
        // Create table on first run if it doesn't exist
        add_action('admin_init', array(__CLASS__, 'maybe_create_table'));

        if (self::is_active()) {
            // Only capture on admin and AJAX requests. Enabling save_queries on front-end
            // requests saves ALL visitor queries into PHP memory, adding meaningful overhead
            // for every customer page load on a live store. Admins who need front-end
            // profiling can browse while logged in via a separate tab.
            if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
                global $wpdb;
                $wpdb->save_queries = true;
                add_action('shutdown', array($this, 'capture_queries'), 999);
            }

            // Register nightly auto-prune to keep the table lean
            add_action('fd_daily_maintenance', array(__CLASS__, 'prune_old_rows'));
        }
    }

    /**
     * Create the slow queries table
     */
    public static function maybe_create_table() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $existing = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($existing === $table) return;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            fingerprint CHAR(32) NOT NULL,
            query_sample TEXT NOT NULL,
            duration FLOAT NOT NULL,
            component VARCHAR(100) DEFAULT '',
            component_type VARCHAR(20) DEFAULT '',
            classification VARCHAR(30) DEFAULT '',
            recommendation TEXT,
            backtrace TEXT,
            request_uri VARCHAR(500) DEFAULT '',
            is_admin TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY fingerprint (fingerprint),
            KEY duration (duration),
            KEY component (component),
            KEY created_at (created_at)
        ) {$charset};";
        dbDelta($sql);
    }

    // ================================================================
    // STATE MANAGEMENT
    // ================================================================

    public static function is_active() {
        $expires = (int) get_option('fd_profiler_expires', 0);
        return $expires > time();
    }

    public static function get_expires() {
        return (int) get_option('fd_profiler_expires', 0);
    }

    public static function get_threshold() {
        return (float) get_option('fd_profiler_threshold', 0.05);
    }

    public static function start($duration_minutes) {
        $minutes = max(1, min(60, (int) $duration_minutes));
        update_option('fd_profiler_expires', time() + ($minutes * 60), false);
    }

    public static function stop() {
        delete_option('fd_profiler_expires');
    }

    public static function set_threshold($seconds) {
        $seconds = max(0.001, min(10, (float) $seconds));
        update_option('fd_profiler_threshold', $seconds, false);
    }

    public static function clear_data() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}" . self::TABLE);
    }

    /**
     * Prune rows older than `prof_retention_days` (default 7) and enforce a hard
     * row-count ceiling of `prof_max_rows` (default 50,000). Both settings are
     * clamped to safe bounds regardless of input so a misconfigured option
     * can't produce a DELETE that wipes the whole table or retains data forever.
     * Hooked to fd_daily_maintenance cron while the profiler is active.
     */
    public static function prune_old_rows() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $opts = get_option(FD_OPT, array());
        $days = isset($opts['prof_retention_days']) ? (int) $opts['prof_retention_days'] : 7;
        $max  = isset($opts['prof_max_rows']) ? (int) $opts['prof_max_rows'] : 50000;
        if ($days < 1)   $days = 7;
        if ($days > 365) $days = 365;
        if ($max < 1000)    $max = 1000;
        if ($max > 1000000) $max = 1000000;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS))
        ));

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > $max) {
            $excess = $count - $max;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} ORDER BY id ASC LIMIT %d",
                $excess
            ));
        }
    }

    // ================================================================
    // CAPTURE: runs at shutdown when profiler is active
    // ================================================================

    public function capture_queries() {
        global $wpdb;
        if (empty($wpdb->queries)) return;

        $threshold = self::get_threshold();
        $table = $wpdb->prefix . self::TABLE;
        $uri = isset($_SERVER['REQUEST_URI']) ? substr($_SERVER['REQUEST_URI'], 0, 500) : '';
        $is_admin = is_admin() ? 1 : 0;
        $now = current_time('mysql');

        $rows = array();
        foreach ($wpdb->queries as $q) {
            // SAVEQUERIES format: [0] SQL, [1] duration in seconds, [2] callstack, [3] time_start, [4] args
            $duration = isset($q[1]) ? (float) $q[1] : 0;
            if ($duration < $threshold) continue;

            $query = isset($q[0]) ? $q[0] : '';
            $backtrace = isset($q[2]) ? $q[2] : '';

            // Skip our own profiler queries to avoid recursion
            if (strpos($query, self::TABLE) !== false) continue;

            $component = $this->attribute($backtrace);
            $classification = $this->classify($query);

            $rows[] = array(
                $this->fingerprint($query),
                substr($query, 0, 2000),
                $duration,
                $component['name'],
                $component['type'],
                $classification['type'],
                $classification['fix'],
                substr($backtrace, 0, 2000),
                $uri,
                $is_admin,
                $now,
            );
        }

        if (empty($rows)) return;

        // Batch rows in chunks of 500 to avoid hitting max_allowed_packet on very busy pages.
        foreach (array_chunk($rows, 500) as $chunk) {
            $placeholders = array_fill(0, count($chunk), '(%s,%s,%f,%s,%s,%s,%s,%s,%s,%d,%s)');
            $flat = array();
            foreach ($chunk as $row) {
                foreach ($row as $value) $flat[] = $value;
            }
            $sql = "INSERT INTO {$table} (fingerprint, query_sample, duration, component, component_type, classification, recommendation, backtrace, request_uri, is_admin, created_at) VALUES " . implode(',', $placeholders);
            $wpdb->query($wpdb->prepare($sql, $flat));
        }
    }

    // ================================================================
    // FINGERPRINTING: normalize queries for grouping
    // ================================================================

    private function fingerprint($query) {
        // Replace numbers with N
        $n = preg_replace('/\b\d+\b/', 'N', $query);
        // Replace string literals with 'S'
        $n = preg_replace("/'[^']*'/", "'S'", $n);
        // Replace IN lists
        $n = preg_replace('/IN\s*\([^)]*\)/i', 'IN (?)', $n);
        // Collapse whitespace
        $n = preg_replace('/\s+/', ' ', $n);
        return md5(strtoupper(trim($n)));
    }

    // ================================================================
    // ATTRIBUTION: which plugin/theme/core caused the query
    // ================================================================

    private function attribute($backtrace) {
        if (empty($backtrace)) return array('name' => 'unknown', 'type' => 'unknown');

        // Plugin: /wp-content/plugins/plugin-slug/
        if (preg_match('#wp-content/plugins/([^/]+)/#', $backtrace, $m)) {
            return array('name' => $m[1], 'type' => 'plugin');
        }
        // Must-use plugin
        if (preg_match('#wp-content/mu-plugins/([^/]+)#', $backtrace, $m)) {
            return array('name' => 'mu: ' . $m[1], 'type' => 'mu-plugin');
        }
        // Theme: /wp-content/themes/theme-slug/
        if (preg_match('#wp-content/themes/([^/]+)/#', $backtrace, $m)) {
            return array('name' => $m[1], 'type' => 'theme');
        }
        // Core
        if (strpos($backtrace, 'wp-includes/') !== false || strpos($backtrace, 'wp-admin/') !== false) {
            return array('name' => 'WordPress Core', 'type' => 'core');
        }
        return array('name' => 'unknown', 'type' => 'unknown');
    }

    // ================================================================
    // CLASSIFICATION: pattern-based analysis + recommendations
    // ================================================================

    private function classify($query) {
        $q = strtolower($query);

        if (stripos($query, 'SQL_CALC_FOUND_ROWS') !== false) {
            return array('type' => 'calc_found_rows', 'fix' => '✅ Enable "Remove SQL_CALC_FOUND_ROWS" in the Query tab. This query is forcing MySQL to count ALL matching rows just for pagination.');
        }
        if (preg_match('/CAST\s*\(\s*[^)]*meta_value/i', $query)) {
            return array('type' => 'meta_cast', 'fix' => '✅ Enable "Remove CAST on wp_postmeta" in the Query tab. The CAST() wrapper prevents MySQL from using indexes on meta_value.');
        }
        if (preg_match("/LIKE\s+['\"]%/i", $query)) {
            return array('type' => 'leading_wildcard', 'fix' => '⚠️ Leading wildcard LIKE "%..." cannot use indexes. This is a plugin/theme bug — consider replacing with full-text search or exact match.');
        }
        if (stripos($query, 'order by rand') !== false) {
            return array('type' => 'random_sort', 'fix' => '🔴 ORDER BY RAND() is extremely slow — it sorts the entire result set randomly. Replace with a randomized WHERE clause or cached random IDs.');
        }
        if (preg_match('/autoload\s*=\s*[\'"]?(yes|on|auto)/i', $query)) {
            return array('type' => 'autoload', 'fix' => '✅ Go to the Autoload tab and run "Audit Top 30 Largest". This query loads all autoloaded options — if autoload size is over 1MB, this is the fix.');
        }
        // Multiple postmeta joins — likely N+1 or complex meta_query
        if (substr_count($q, 'postmeta') > 3) {
            return array('type' => 'meta_n_plus_1', 'fix' => '✅ Multiple wp_postmeta JOINs detected. Apply Tier 1 Deep Reindex on postmeta (Indexes tab). Also consider if this is an N+1 pattern that should use update_meta_cache().');
        }
        if (preg_match('/group\s+by.*order\s+by/i', $q) && strpos($q, 'distinct') === false) {
            return array('type' => 'groupby_orderby', 'fix' => '✅ Enable "Optimize GROUP BY" in the Query tab. GROUP BY with ORDER BY is expensive — can often be replaced with DISTINCT.');
        }
        if (preg_match('/SELECT\s+\*\s+FROM/i', $query)) {
            return array('type' => 'select_all', 'fix' => '⚠️ SELECT * loads unnecessary columns. Plugin should specify needed columns only.');
        }
        if (preg_match('/IN\s*\([^)]{300,}\)/i', $query)) {
            return array('type' => 'long_in', 'fix' => '⚠️ Very long IN() list. Plugin should chunk or use a temporary table.');
        }
        if (preg_match('/left\s+join.*left\s+join.*left\s+join/i', $q)) {
            return array('type' => 'many_joins', 'fix' => '⚠️ Many LEFT JOINs — query optimizer may struggle. Consider if this data should be denormalized or cached.');
        }
        if (stripos($query, 'SHOW INDEX') !== false || stripos($query, 'SHOW TABLES') !== false) {
            return array('type' => 'schema', 'fix' => '⚠️ Schema introspection query — usually plugin initialization. Should be cached by the calling plugin.');
        }
        if (preg_match('/wp_options.*LIKE/i', $query)) {
            return array('type' => 'options_like', 'fix' => '✅ Plugin doing LIKE on wp_options — often transient lookups. Consider object cache (Redis) for wp_options.');
        }
        return array('type' => 'generic', 'fix' => '💡 Slow query without specific pattern. Check if result can be cached via transients. Consider adding an index on frequently filtered columns.');
    }

    // ================================================================
    // READING: for admin UI
    // ================================================================

    /**
     * Get aggregated slow queries grouped by fingerprint
     * @param array $filters: hide_design, component_type, min_duration
     */
    public static function get_aggregated($filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $where = array('1=1');
        $params = array();

        if (!empty($filters['hide_design'])) {
            // Exclude themes and Elementor ecosystem plugins.
            // Use parameterized placeholders for all values, including component_type,
            // for consistency and to avoid any future injection risk if the filter
            // values become dynamic.
            $excluded_plugins = array('elementor', 'elementor-pro', 'the-plus-addons-for-elementor',
                              'animation-addons-for-elementor', 'theplus-pro', 'plus-addons',
                              'animation-addons-for-elementor-pro');
            $where[] = "component_type != %s";
            $params[] = 'theme';
            foreach ($excluded_plugins as $slug) {
                $where[] = "component != %s";
                $params[] = $slug;
            }
        }

        if (!empty($filters['component_type'])) {
            $where[] = 'component_type = %s';
            $params[] = $filters['component_type'];
        }

        if (!empty($filters['min_duration'])) {
            $where[] = 'duration >= %f';
            $params[] = (float) $filters['min_duration'];
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT
                    fingerprint,
                    MAX(query_sample) AS query_sample,
                    MAX(component) AS component,
                    MAX(component_type) AS component_type,
                    MAX(classification) AS classification,
                    MAX(recommendation) AS recommendation,
                    MAX(backtrace) AS backtrace,
                    COUNT(*) AS occurrences,
                    ROUND(AVG(duration), 4) AS avg_duration,
                    ROUND(MAX(duration), 4) AS max_duration,
                    ROUND(SUM(duration), 4) AS total_duration,
                    MAX(created_at) AS last_seen
                FROM {$table}
                WHERE {$where_sql}
                GROUP BY fingerprint
                ORDER BY total_duration DESC
                LIMIT 100";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        return $wpdb->get_results($sql);
    }

    public static function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return array(
            'total_queries'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'unique_queries'  => (int) $wpdb->get_var("SELECT COUNT(DISTINCT fingerprint) FROM {$table}"),
            'total_time'      => (float) $wpdb->get_var("SELECT ROUND(SUM(duration), 2) FROM {$table}"),
            'max_time'        => (float) $wpdb->get_var("SELECT ROUND(MAX(duration), 4) FROM {$table}"),
            'components'      => (int) $wpdb->get_var("SELECT COUNT(DISTINCT component) FROM {$table}"),
        );
    }

    /**
     * Run EXPLAIN on a query fingerprint to see its execution plan
     */
    public static function explain_query($fingerprint) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $query = $wpdb->get_var($wpdb->prepare(
            "SELECT query_sample FROM {$table} WHERE fingerprint = %s ORDER BY duration DESC LIMIT 1",
            $fingerprint
        ));
        if (!$query) return null;

        // Only run EXPLAIN on SELECT queries
        if (!preg_match('/^\s*SELECT/i', $query)) {
            return array('error' => 'EXPLAIN only works on SELECT queries.');
        }

        $result = $wpdb->get_results("EXPLAIN " . $query, ARRAY_A);
        return array(
            'query' => $query,
            'explain' => $result,
        );
    }
}



