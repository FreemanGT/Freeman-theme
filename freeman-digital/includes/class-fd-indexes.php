<?php
if (!defined('ABSPATH')) exit;

/**
 * Freeman Digital — Indexes Module (Two-Tier)
 *
 * TIER 1: "Deep Reindex" — restructures PRIMARY KEYs on core meta tables
 *         into compound clustered indexes. Highest-impact optimization.
 *         Auto-detects Barracuda vs Antelope InnoDB format.
 *
 * TIER 2: "Secondary Indexes" — adds indexes on tables Tier 1 doesn't cover
 *         (term_relationships, term_taxonomy, terms, WC sessions, etc.)
 */
class FD_Indexes {
    public function __construct() {}

    /** Detect MySQL capabilities: Barracuda (full keys) vs Antelope (prefix-limited), DDL grants */
    public static function detect_capabilities() {
        global $wpdb;
        $r = (object)array('can_reindex'=>true, 'barracuda'=>false, 'version'=>'', 'can_ddl'=>true, 'ddl_error'=>'');
        $ver_row = $wpdb->get_row("SELECT VERSION() AS ver");
        if (!$ver_row) { $r->can_reindex = false; $r->can_ddl = false; return $r; }
        $r->version = $ver_row->ver;
        $is_maria = (stripos($ver_row->ver,'mariadb')!==false);
        $parts = explode('.', explode('-', $ver_row->ver)[0]);
        $major = (int)($parts[0]??0); $minor = (int)($parts[1]??0);

        // Probe DDL grants (CREATE / DROP). Result cached for 1 hour so we don't run DDL every page.
        $cached = get_transient('fd_can_ddl');
        if ($cached === false) {
            $probe = 'fd_ddl_probe_' . wp_generate_password(6, false, false);
            $prev_error = $wpdb->hide_errors();
            $create_ok = $wpdb->query("CREATE TEMPORARY TABLE `{$probe}` (id INT)");
            $drop_ok   = ($create_ok !== false) ? $wpdb->query("DROP TABLE `{$probe}`") : false;
            if ($prev_error) $wpdb->show_errors();
            $r->can_ddl = ($create_ok !== false);
            $r->ddl_error = $r->can_ddl ? '' : (string) $wpdb->last_error;
            set_transient('fd_can_ddl', array('can' => $r->can_ddl, 'err' => $r->ddl_error), HOUR_IN_SECONDS);
        } else {
            $r->can_ddl = !empty($cached['can']);
            $r->ddl_error = isset($cached['err']) ? $cached['err'] : '';
        }

        if (!$is_maria && $major>=8) { $r->barracuda=true; return $r; }
        if ($is_maria && version_compare("{$major}.{$minor}",'10.3','>=')) { $r->barracuda=true; return $r; }
        if ($is_maria || ($major==5 && $minor>=6)) {
            $lp = $wpdb->get_var("SELECT @@innodb_large_prefix");
            $r->barracuda = ($lp && (strtolower($lp)==='on' || $lp==='1'));
            return $r;
        }
        if ($major<5 || ($major==5 && $minor<6)) $r->can_reindex = false;
        return $r;
    }

    // ================================================================
    // TIER 1: DEEP REINDEX
    // ================================================================

    public static function get_deep_definitions() {
        $caps = self::detect_capabilities();
        $common = array(
            'options' => array(
                'option_id'=>'ADD UNIQUE KEY option_id (option_id)',
                'PRIMARY KEY'=>'ADD PRIMARY KEY (option_name)',
                'autoload'=>'ADD KEY autoload (autoload)',
            ),
            'comments' => array(
                'comment_ID'=>'ADD UNIQUE KEY comment_ID (comment_ID)',
                'PRIMARY KEY'=>'ADD PRIMARY KEY (comment_post_ID, comment_ID)',
                'comment_approved_date_gmt'=>'ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt, comment_ID)',
                'comment_date_gmt'=>'ADD KEY comment_date_gmt (comment_date_gmt, comment_ID)',
                'comment_parent'=>'ADD KEY comment_parent (comment_parent, comment_ID)',
                'comment_author_email'=>'ADD KEY comment_author_email (comment_author_email, comment_post_ID, comment_ID)',
                'comment_post_parent_approved'=>'ADD KEY comment_post_parent_approved (comment_post_ID, comment_parent, comment_approved, comment_type, user_id, comment_date_gmt, comment_ID)',
            ),
        );
        if ($caps->barracuda) {
            $meta = array(
                'postmeta'=>array(
                    'meta_id'=>'ADD UNIQUE KEY meta_id (meta_id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (post_id, meta_key, meta_id)',
                    'meta_key'=>'ADD KEY meta_key (meta_key, meta_value(32), post_id, meta_id)',
                    'meta_value'=>'ADD KEY meta_value (meta_value(32), meta_id)'),
                'usermeta'=>array(
                    'umeta_id'=>'ADD UNIQUE KEY umeta_id (umeta_id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (user_id, meta_key, umeta_id)',
                    'meta_key'=>'ADD KEY meta_key (meta_key, meta_value(32), user_id, umeta_id)',
                    'meta_value'=>'ADD KEY meta_value (meta_value(32), umeta_id)'),
                'termmeta'=>array(
                    'meta_id'=>'ADD UNIQUE KEY meta_id (meta_id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (term_id, meta_key, meta_id)',
                    'meta_key'=>'ADD KEY meta_key (meta_key, meta_value(32), term_id, meta_id)',
                    'meta_value'=>'ADD KEY meta_value (meta_value(32), meta_id)'),
                'commentmeta'=>array(
                    'meta_id'=>'ADD UNIQUE KEY meta_id (meta_id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (meta_key, comment_id, meta_id)',
                    'comment_id'=>'ADD KEY comment_id (comment_id, meta_key, meta_value(32))',
                    'meta_value'=>'ADD KEY meta_value (meta_value(32))'),
                'posts'=>array(
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (ID)',
                    'post_name'=>'ADD KEY post_name (post_name)',
                    'post_parent'=>'ADD KEY post_parent (post_parent, post_type, post_status)',
                    'type_status_date'=>'ADD KEY type_status_date (post_type, post_status, post_date, post_author)',
                    'post_author'=>'ADD KEY post_author (post_author, post_type, post_status, post_date)'),
                'users'=>array(
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (ID)',
                    'user_login_key'=>'ADD KEY user_login_key (user_login)',
                    'user_nicename'=>'ADD KEY user_nicename (user_nicename)',
                    'user_email'=>'ADD KEY user_email (user_email)',
                    'display_name'=>'ADD KEY display_name (display_name)'),
                'woocommerce_order_itemmeta'=>array(
                    'meta_id'=>'ADD UNIQUE KEY meta_id (meta_id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (order_item_id, meta_key, meta_id)',
                    'meta_key'=>'ADD KEY meta_key (meta_key, meta_value(32), order_item_id, meta_id)',
                    'meta_value'=>'ADD KEY meta_value (meta_value(32), meta_id)'),
                'wc_orders_meta'=>array(
                    'id'=>'ADD UNIQUE KEY id (id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (order_id, meta_key, id)',
                    'meta_key_value'=>'ADD KEY meta_key_value (meta_key, meta_value(32), order_id, id)'),
            );
        } else {
            $meta = array(
                'postmeta'=>array(
                    'meta_id'=>'ADD UNIQUE KEY meta_id (meta_id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (post_id, meta_id)',
                    'post_id'=>'ADD KEY post_id (post_id, meta_key(32), meta_value(32), meta_id)',
                    'meta_key'=>'ADD KEY meta_key (meta_key(32), meta_value(32), meta_id)',
                    'meta_value'=>'ADD KEY meta_value (meta_value(32), meta_id)'),
                'usermeta'=>array(
                    'umeta_id'=>'ADD UNIQUE KEY umeta_id (umeta_id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (user_id, umeta_id)',
                    'user_id'=>'ADD KEY user_id (user_id, meta_key(32), meta_value(32), umeta_id)',
                    'meta_key'=>'ADD KEY meta_key (meta_key(32), meta_value(32), umeta_id)',
                    'meta_value'=>'ADD KEY meta_value (meta_value(32), umeta_id)'),
                'termmeta'=>array(
                    'meta_id'=>'ADD UNIQUE KEY meta_id (meta_id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (term_id, meta_id)',
                    'term_id'=>'ADD KEY term_id (term_id, meta_key(32), meta_value(32), meta_id)',
                    'meta_key'=>'ADD KEY meta_key (meta_key(32), meta_value(32), meta_id)',
                    'meta_value'=>'ADD KEY meta_value (meta_value(32), meta_id)'),
                'commentmeta'=>array(
                    'meta_id'=>'ADD UNIQUE KEY meta_id (meta_id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (meta_id)',
                    'comment_id'=>'ADD KEY comment_id (comment_id)',
                    'meta_key'=>'ADD KEY meta_key (meta_key(191))'),
                'posts'=>array(
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (ID)',
                    'post_name'=>'ADD KEY post_name (post_name(191))',
                    'post_parent'=>'ADD KEY post_parent (post_parent, post_type, post_status)',
                    'type_status_date'=>'ADD KEY type_status_date (post_type, post_status, post_date, post_author)',
                    'post_author'=>'ADD KEY post_author (post_author, post_type, post_status, post_date)'),
                'users'=>array(
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (ID)',
                    'user_login_key'=>'ADD KEY user_login_key (user_login)',
                    'user_nicename'=>'ADD KEY user_nicename (user_nicename)',
                    'user_email'=>'ADD KEY user_email (user_email)',
                    'display_name'=>'ADD KEY display_name (display_name)'),
                'woocommerce_order_itemmeta'=>array(
                    'meta_id'=>'ADD UNIQUE KEY meta_id (meta_id)',
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (order_item_id, meta_id)',
                    'order_item_id'=>'ADD KEY order_item_id (order_item_id, meta_key(32), meta_value(32))',
                    'meta_key'=>'ADD KEY meta_key (meta_key(32), meta_value(32), meta_id)',
                    'meta_value'=>'ADD KEY meta_value (meta_value(32), meta_id)'),
                'wc_orders_meta'=>array(
                    'PRIMARY KEY'=>'ADD PRIMARY KEY (id)',
                    'meta_key_value'=>'ADD KEY meta_key_value (meta_key(191), meta_value(100))',
                    'order_id_meta'=>'ADD KEY order_id_meta (order_id, meta_key(191), meta_value(100))'),
            );
        }
        return array_merge($common, $meta);
    }

    /** Read current indexes from a table as name => "ADD ..." */
    public static function get_current_indexes($table) {
        global $wpdb;
        $rows = $wpdb->get_results("SHOW INDEX FROM `{$table}`");
        if (!$rows) return array();
        $grp = array();
        foreach ($rows as $r) {
            $n = $r->Key_name;
            if (!isset($grp[$n])) $grp[$n]=array('u'=>!$r->Non_unique,'c'=>array());
            $c = $r->Column_name;
            if ($r->Sub_part) $c .= '('.$r->Sub_part.')';
            $grp[$n]['c'][$r->Seq_in_index] = $c;
        }
        $out = array();
        foreach ($grp as $n=>$i) {
            ksort($i['c']);
            $cols = implode(', ',$i['c']);
            if ($n==='PRIMARY') $out['PRIMARY KEY']="ADD PRIMARY KEY ({$cols})";
            elseif ($i['u']) $out[$n]="ADD UNIQUE KEY {$n} ({$cols})";
            else $out[$n]="ADD KEY {$n} ({$cols})";
        }
        return $out;
    }

    /** Apply deep reindex to one table via ALTER TABLE */
    public static function deep_reindex_table($tbl, $target) {
        global $wpdb;
        $pf = $wpdb->prefix . $tbl;
        if (!$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM information_schema.tables WHERE table_schema=%s AND table_name=%s",DB_NAME,$pf)))
            return false;
        $cur = self::get_current_indexes($pf);
        $actions = array();
        $all = array_unique(array_merge(array_keys($target),array_keys($cur)));
        $skip = array('woo_','crp_','yarpp_','fd_');
        foreach ($all as $name) {
            $s=false; foreach($skip as $p) if(strpos($name,$p)===0){$s=true;break;} if($s)continue;
            $ic = isset($cur[$name]); $it = isset($target[$name]);
            if ($ic && $it && $cur[$name]===$target[$name]) continue;
            if ($ic && $it && $cur[$name]!==$target[$name]) {
                $actions[] = ($name==='PRIMARY KEY') ? 'DROP PRIMARY KEY' : "DROP KEY `{$name}`";
                $actions[] = $target[$name];
            } elseif ($ic && !$it) {
                $actions[] = ($name==='PRIMARY KEY') ? 'DROP PRIMARY KEY' : "DROP KEY `{$name}`";
            } elseif (!$ic && $it) {
                $actions[] = $target[$name];
            }
        }
        if (empty($actions)) return 'already_optimized';
        // Put UNIQUE KEY adds first for autoincrement safety
        usort($actions, function($a,$b){
            return (strpos($a,'UNIQUE')!==false?0:1) - (strpos($b,'UNIQUE')!==false?0:1);
        });
        // Extend PHP execution limit for large ALTER TABLE operations.
        // Guarded because set_time_limit is disabled in some shared hosting environments
        // and was previously throwing warnings when called unconditionally.
        if (function_exists('set_time_limit') && !ini_get('safe_mode')) {
            @set_time_limit(600);
        }
        $sql = "ALTER TABLE `{$pf}` ".implode(', ',$actions);
        $r = $wpdb->query($sql);
        if ($r===false) return 'error: '.$wpdb->last_error;
        $wpdb->query("ANALYZE TABLE `{$pf}`");
        return 'reindexed';
    }

    /**
     * Enter maintenance mode by creating .maintenance in ABSPATH.
     * WordPress blocks public requests while this file exists (checked by wp-load.php).
     * Returns true if file was created (or already existed), false on failure.
     *
     * Errors on read-only filesystems (common on managed hosts) are logged to the
     * Activity Log rather than surfaced as PHP warnings — the reindex itself can
     * still proceed, just without the belt-and-suspenders maintenance window.
     */
    private static function enter_maintenance_mode() {
        $file = ABSPATH . '.maintenance';
        if (file_exists($file)) return true;
        $content = '<?php $upgrading = ' . time() . '; ?>';
        // Suppress the warning so it doesn't corrupt the AJAX JSON response, but
        // capture the error message via error_get_last() so we can surface it.
        $written = @file_put_contents($file, $content);
        if ($written === false) {
            $err = error_get_last();
            if (class_exists('FD_Activity_Log')) {
                FD_Activity_Log::record('maintenance_mode_enter_failed', array(
                    'file'    => $file,
                    'reason'  => $err ? $err['message'] : 'unknown',
                ));
            }
            return false;
        }
        return true;
    }

    private static function leave_maintenance_mode() {
        $file = ABSPATH . '.maintenance';
        if (!file_exists($file)) return;
        $ok = @unlink($file);
        if (!$ok) {
            $err = error_get_last();
            if (class_exists('FD_Activity_Log')) {
                FD_Activity_Log::record('maintenance_mode_leave_failed', array(
                    'file'    => $file,
                    'reason'  => $err ? $err['message'] : 'unknown',
                ));
            }
        }
    }

    /** Apply deep reindex to list of tables */
    public static function apply_deep($tables) {
        $defs = self::get_deep_definitions();
        $res = array();

        $opts = function_exists('FD_Core::opts') ? FD_Core::opts() : get_option(FD_OPT, array());
        $use_maintenance = !empty($opts['idx_enable_maintenance_mode']);

        if ($use_maintenance) {
            self::enter_maintenance_mode();
        }

        try {
            foreach ($tables as $t) {
                if (isset($defs[$t])) $res[$t] = self::deep_reindex_table($t, $defs[$t]);
            }
            wp_cache_flush();
        } finally {
            if ($use_maintenance) {
                self::leave_maintenance_mode();
            }
        }

        if (class_exists('FD_Activity_Log')) {
            FD_Activity_Log::record('deep_reindex_apply', array(
                'rows_affected' => count($res),
                'tables'        => array_keys($res),
                'results'       => $res,
            ));
        }
        return $res;
    }

    /** Revert to WP standard indexes */
    public static function revert_deep($tables) {
        $std = array(
            'postmeta'=>array('PRIMARY KEY'=>'ADD PRIMARY KEY (meta_id)','post_id'=>'ADD KEY post_id (post_id)','meta_key'=>'ADD KEY meta_key (meta_key(191))'),
            'usermeta'=>array('PRIMARY KEY'=>'ADD PRIMARY KEY (umeta_id)','user_id'=>'ADD KEY user_id (user_id)','meta_key'=>'ADD KEY meta_key (meta_key(191))'),
            'termmeta'=>array('PRIMARY KEY'=>'ADD PRIMARY KEY (meta_id)','term_id'=>'ADD KEY term_id (term_id)','meta_key'=>'ADD KEY meta_key (meta_key(191))'),
            'options'=>array('PRIMARY KEY'=>'ADD PRIMARY KEY (option_id)','option_name'=>'ADD UNIQUE KEY option_name (option_name)','autoload'=>'ADD KEY autoload (autoload)'),
            'comments'=>array('PRIMARY KEY'=>'ADD PRIMARY KEY (comment_ID)','comment_post_ID'=>'ADD KEY comment_post_ID (comment_post_ID)','comment_approved_date_gmt'=>'ADD KEY comment_approved_date_gmt (comment_approved, comment_date_gmt)','comment_date_gmt'=>'ADD KEY comment_date_gmt (comment_date_gmt)','comment_parent'=>'ADD KEY comment_parent (comment_parent)','comment_author_email'=>'ADD KEY comment_author_email (comment_author_email(10))'),
            'commentmeta'=>array('PRIMARY KEY'=>'ADD PRIMARY KEY (meta_id)','comment_id'=>'ADD KEY comment_id (comment_id)','meta_key'=>'ADD KEY meta_key (meta_key(191))'),
            'posts'=>array('PRIMARY KEY'=>'ADD PRIMARY KEY (ID)','post_name'=>'ADD KEY post_name (post_name(191))','post_parent'=>'ADD KEY post_parent (post_parent)','type_status_date'=>'ADD KEY type_status_date (post_type, post_status, post_date, ID)','post_author'=>'ADD KEY post_author (post_author)'),
            'users'=>array('PRIMARY KEY'=>'ADD PRIMARY KEY (ID)','user_login_key'=>'ADD KEY user_login_key (user_login)','user_nicename'=>'ADD KEY user_nicename (user_nicename)','user_email'=>'ADD KEY user_email (user_email)'),
            'woocommerce_order_itemmeta'=>array('PRIMARY KEY'=>'ADD PRIMARY KEY (meta_id)','order_item_id'=>'ADD KEY order_item_id (order_item_id)','meta_key'=>'ADD KEY meta_key (meta_key(32))'),
            'wc_orders_meta'=>array('PRIMARY KEY'=>'ADD PRIMARY KEY (id)','meta_key_value'=>'ADD KEY meta_key_value (meta_key(191), meta_value(100))','order_id_meta_key_meta_value'=>'ADD KEY order_id_meta_key_meta_value (order_id, meta_key(191), meta_value(100))'),
        );
        $res = array();
        $opts = get_option(FD_OPT, array());
        $use_maintenance = !empty($opts['idx_enable_maintenance_mode']);

        if ($use_maintenance) {
            self::enter_maintenance_mode();
        }

        try {
            foreach ($tables as $t) if (isset($std[$t])) $res[$t]=self::deep_reindex_table($t,$std[$t]);
            wp_cache_flush();
        } finally {
            if ($use_maintenance) {
                self::leave_maintenance_mode();
            }
        }

        if (class_exists('FD_Activity_Log')) {
            FD_Activity_Log::record('deep_reindex_revert', array(
                'rows_affected' => count($res),
                'tables'        => array_keys($res),
                'results'       => $res,
            ));
        }
        return $res;
    }

    /** Get status: which tables are optimized vs standard */
    public static function get_deep_status() {
        global $wpdb;
        $defs = self::get_deep_definitions();
        $st = array();
        foreach ($defs as $tbl=>$target) {
            $pf = $wpdb->prefix.$tbl;
            if (!$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM information_schema.tables WHERE table_schema=%s AND table_name=%s",DB_NAME,$pf))) continue;
            $cur = self::get_current_indexes($pf);
            $ok = true;
            foreach ($target as $n=>$add) { if (!isset($cur[$n]) || $cur[$n]!==$add) { $ok=false; break; } }
            $st[$tbl] = $ok ? 'optimized' : 'standard';
        }
        return $st;
    }

    // ================================================================
    // TIER 2: SECONDARY INDEXES
    // ================================================================
    public static function get_definitions() {
        global $wpdb;
        $all = array(
            array('name'=>'fd_p_date','table'=>'posts','columns'=>'post_type, post_status, post_date','notes'=>'Archive queries sorted by date.'),
            array('name'=>'fd_p_modified','table'=>'posts','columns'=>'post_modified_gmt','notes'=>'Sitemap/cache modification checks.'),
            array('name'=>'fd_p_title','table'=>'posts','columns'=>'post_title(50)','notes'=>'Title search queries.'),
            array('name'=>'fd_p_sitemap','table'=>'posts','columns'=>'post_status(20), post_password(20), post_type(20), post_modified','notes'=>'Sitemap generation composite.'),
            array('name'=>'fd_p_guid','table'=>'posts','columns'=>'guid(191)','notes'=>'GUID lookups (RSS).'),
            array('name'=>'fd_p_mime','table'=>'posts','columns'=>'post_type, post_mime_type','notes'=>'Media library queries.'),
            array('name'=>'fd_tr_tax','table'=>'term_relationships','columns'=>'term_taxonomy_id, object_id','notes'=>'Category/attribute filtering (critical WooCommerce).'),
            array('name'=>'fd_tt_hier','table'=>'term_taxonomy','columns'=>'taxonomy, parent, term_taxonomy_id','notes'=>'Hierarchical category/breadcrumbs.'),
            array('name'=>'fd_tt_lookup','table'=>'term_taxonomy','columns'=>'taxonomy, term_id, term_taxonomy_id','notes'=>'Term→taxonomy mapping.'),
            array('name'=>'fd_tt_join','table'=>'term_taxonomy','columns'=>'term_taxonomy_id, taxonomy, term_id','notes'=>'Taxonomy JOIN optimization.'),
            array('name'=>'fd_tt_parent','table'=>'term_taxonomy','columns'=>'parent, term_id','notes'=>'Ancestor/descendant queries.'),
            array('name'=>'fd_t_name','table'=>'terms','columns'=>'term_id, name(50), slug(50)','notes'=>'Name/slug lookups for permalinks.'),
            array('name'=>'fd_wc_sku','table'=>'wc_product_meta_lookup','columns'=>'sku','notes'=>'SKU search.'),
            array('name'=>'fd_wc_oi','table'=>'woocommerce_order_items','columns'=>'order_id, order_item_type','notes'=>'Order item lookups.'),
            array('name'=>'fd_wc_sess','table'=>'woocommerce_sessions','columns'=>'session_expiry','notes'=>'Session cleanup.'),
            array('name'=>'fd_wc_dl','table'=>'woocommerce_downloadable_product_permissions','columns'=>'order_id, product_id','notes'=>'Download permission checks.'),
            array('name'=>'fd_as_status','table'=>'actionscheduler_actions','columns'=>'status, last_attempt_gmt, action_id','notes'=>'AS queue processing.'),
            array('name'=>'fd_as_hook','table'=>'actionscheduler_actions','columns'=>'hook, status, scheduled_date_gmt','notes'=>'AS hook lookups.'),
        );
        $out = array();
        foreach ($all as $idx) {
            $tbl = $wpdb->prefix.$idx['table'];
            if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM information_schema.tables WHERE table_schema=%s AND table_name=%s",DB_NAME,$tbl)))
                $out[] = $idx;
        }
        return $out;
    }

    public static function get_created() {
        global $wpdb;
        $r = $wpdb->get_results("SELECT DISTINCT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME LIKE 'fd_%' AND TABLE_NAME LIKE '{$wpdb->prefix}%'");
        return array_map(function($x){return $x->INDEX_NAME;},$r);
    }

    public static function create($selected) {
        global $wpdb;
        $all=self::get_definitions(); $created=$dropped=0;
        foreach($all as $i){if(!in_array($i['name'],$selected)){$tbl=$wpdb->prefix.$i['table'];if($wpdb->get_var($wpdb->prepare("SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s",$tbl,$i['name']))){$wpdb->query("DROP INDEX `{$i['name']}` ON `{$tbl}`");$dropped++;}}}
        foreach($all as $i){if(in_array($i['name'],$selected)){$tbl=$wpdb->prefix.$i['table'];if(!$wpdb->get_var($wpdb->prepare("SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND INDEX_NAME=%s",$tbl,$i['name']))){$wpdb->query("CREATE INDEX `{$i['name']}` ON `{$tbl}` ({$i['columns']})");$created++;}}}
        if (class_exists('FD_Activity_Log')) {
            FD_Activity_Log::record('secondary_indexes_update', array(
                'rows_affected' => $created + $dropped,
                'created'       => $created,
                'dropped'       => $dropped,
            ));
        }
        return array('created'=>$created,'dropped'=>$dropped);
    }

    public static function drop_all() {
        global $wpdb;
        $idxs=$wpdb->get_results("SELECT INDEX_NAME,TABLE_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME LIKE 'fd_%'");
        foreach($idxs as $i) $wpdb->query("DROP INDEX `{$i->INDEX_NAME}` ON `{$i->TABLE_NAME}`");
        if (class_exists('FD_Activity_Log')) {
            FD_Activity_Log::record('secondary_indexes_drop_all', array(
                'rows_affected' => is_array($idxs) ? count($idxs) : 0,
            ));
        }
    }
}
