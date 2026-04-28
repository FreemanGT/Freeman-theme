<?php
if (!defined('ABSPATH')) exit;

class FD_Database {
    private $o;
    public function __construct($o) {
        $this->o = $o;
        if (!empty($o['db_auto_cleanup']))
            add_action('fd_daily_maintenance', array($this,'run_cleanup'));
    }

    /**
     * Chunked DELETE helper — runs the DELETE in LIMIT-bounded batches so a single cleanup
     * job doesn't hold locks or hit cron timeouts on large stores. Batch size is filterable
     * via `fd/cleanup_batch_size` (default 5000).
     */
    private function chunked_delete($sql_template) {
        global $wpdb;
        $batch_size = (int) apply_filters('fd/cleanup_batch_size', 5000);
        if ($batch_size < 1) $batch_size = 5000;
        $sql = str_replace('{BATCH}', (string) $batch_size, $sql_template);

        $total = 0;
        $max_iterations = 2000; // hard-cap to prevent runaway loops (2000 * 5000 = 10M rows)
        for ($i = 0; $i < $max_iterations; $i++) {
            $affected = $wpdb->query($sql);
            if ($affected === false || $affected === 0) break;
            $total += (int) $affected;
            if ($affected < $batch_size) break;
        }
        return $total;
    }

    public function run_cleanup() {
        global $wpdb;
        $o = $this->o;

        /**
         * Fires before the cleanup begins. Return value ignored.
         * Extenders can use this to pause workers, flush caches, or take backups.
         */
        do_action('fd/before_run_cleanup');

        $r = array();

        if (!empty($o['db_clean_revisions'])) {
            $r['revisions'] = $this->chunked_delete(
                "DELETE a,b,c FROM {$wpdb->posts} a
                 LEFT JOIN {$wpdb->term_relationships} b ON a.ID=b.object_id
                 LEFT JOIN {$wpdb->postmeta} c ON a.ID=c.post_id
                 WHERE a.post_type='revision' LIMIT {BATCH}"
            );
        }
        if (!empty($o['db_clean_auto_drafts'])) {
            $r['auto_drafts'] = $this->chunked_delete(
                "DELETE FROM {$wpdb->posts} WHERE post_status='auto-draft' LIMIT {BATCH}"
            );
        }
        if (!empty($o['db_clean_trashed_posts'])) {
            $r['trashed_posts'] = $this->chunked_delete(
                "DELETE a,b,c FROM {$wpdb->posts} a
                 LEFT JOIN {$wpdb->term_relationships} b ON a.ID=b.object_id
                 LEFT JOIN {$wpdb->postmeta} c ON a.ID=c.post_id
                 WHERE a.post_status='trash' LIMIT {BATCH}"
            );
        }
        if (!empty($o['db_clean_spam_comments'])) {
            $r['spam_comments'] = $this->chunked_delete(
                "DELETE FROM {$wpdb->comments} WHERE comment_approved='spam' LIMIT {BATCH}"
            );
        }
        if (!empty($o['db_clean_trashed_comments'])) {
            $r['trashed_comments'] = $this->chunked_delete(
                "DELETE FROM {$wpdb->comments} WHERE comment_approved='trash' LIMIT {BATCH}"
            );
        }
        if (!empty($o['db_clean_expired_transients'])) {
            // Use a safer approach: find all timeouts < now, then delete matching value rows
            // by computing the correct name via SUBSTRING (timeout name is
            // "_transient_timeout_KEY", value name is "_transient_KEY" — replace only the first
            // occurrence of "_timeout_" which is always at a known position).
            //
            // The previous version used MySQL's REPLACE() which replaces ALL occurrences —
            // a transient key like "wc_timeout_data" would be corrupted twice.
            $time = time();
            $expired = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE %s AND option_value < %d
                 LIMIT 5000",
                $wpdb->esc_like('_transient_timeout_') . '%',
                $time
            ));
            $deleted = 0;
            foreach ($expired as $timeout_key) {
                // Remove _transient_timeout_ prefix and re-add _transient_ prefix.
                // This is surgical and unaffected by the key body containing "_timeout_".
                $key_body = substr($timeout_key, strlen('_transient_timeout_'));
                $value_key = '_transient_' . $key_body;
                $deleted += (int) $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
                    $timeout_key, $value_key
                ));
            }
            // Also clean site transients (multisite)
            $site_expired = $wpdb->get_col($wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE %s AND option_value < %d
                 LIMIT 5000",
                $wpdb->esc_like('_site_transient_timeout_') . '%',
                $time
            ));
            foreach ($site_expired as $timeout_key) {
                $key_body = substr($timeout_key, strlen('_site_transient_timeout_'));
                $value_key = '_site_transient_' . $key_body;
                $deleted += (int) $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
                    $timeout_key, $value_key
                ));
            }
            $r['expired_transients'] = $deleted;
        }
        if (!empty($o['db_clean_wc_transients'])) {
            $c = 0;
            $patterns = array('%_transient_wc_var_prices_%','%_transient_wc_product_children_%','%_transient_wc_related_%','%_transient_wc_term_counts%','%_transient_wc_count_%');
            foreach ($patterns as $p) {
                $c += (int)$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 5000", $p));
            }
            $r['wc_transients'] = $c;
        }
        if (!empty($o['db_clean_wc_sessions'])) {
            $tbl = $wpdb->prefix.'woocommerce_sessions';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tbl))) {
                $c = $wpdb->query($wpdb->prepare("DELETE FROM `{$tbl}` WHERE session_expiry < %d", time()));
                $r['wc_sessions'] = (int)$c;
            }
        }
        if (!empty($o['db_clean_action_scheduler'])) {
            $t = $wpdb->prefix.'actionscheduler_actions';
            $l = $wpdb->prefix.'actionscheduler_logs';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t))) {
                $c = $wpdb->query("DELETE FROM `{$t}` WHERE status IN ('complete','failed','canceled') LIMIT 50000");
                $r['as_actions'] = (int)$c;
            }
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $l))) {
                $c = $wpdb->query("DELETE l FROM `{$l}` l LEFT JOIN `{$t}` a ON l.action_id=a.action_id WHERE a.action_id IS NULL LIMIT 50000");
                $r['as_logs'] = (int)$c;
            }
        }
        if (!empty($o['db_clean_orphan_postmeta'])) {
            $c = $wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.ID IS NULL");
            $r['orphan_postmeta'] = (int)$c;
        }
        if (!empty($o['db_clean_orphan_commentmeta'])) {
            $c = $wpdb->query("DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID=cm.comment_id WHERE c.comment_ID IS NULL");
            $r['orphan_commentmeta'] = (int)$c;
        }
        if (!empty($o['db_clean_orphan_termmeta'])) {
            $c = $wpdb->query("DELETE tm FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id=tm.term_id WHERE t.term_id IS NULL");
            $r['orphan_termmeta'] = (int)$c;
        }
        if (!empty($o['db_clean_orphan_user_sessions'])) {
            // WordPress stores session tokens as a serialized array in usermeta with key 'session_tokens'.
            // Each entry has an 'expiration' timestamp. We need to deserialize, prune expired tokens,
            // and re-save (or delete the row if no valid tokens remain).
            $rows = $wpdb->get_results(
                "SELECT umeta_id, user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key='session_tokens'"
            );
            $cleaned = 0;
            $now = time();
            foreach ($rows as $row) {
                $tokens = maybe_unserialize($row->meta_value);
                if (!is_array($tokens) || empty($tokens)) {
                    // Empty or invalid — remove the row
                    $wpdb->delete($wpdb->usermeta, array('umeta_id' => $row->umeta_id));
                    $cleaned++;
                    continue;
                }
                $original_count = count($tokens);
                foreach ($tokens as $hash => $token) {
                    if (!is_array($token) || !isset($token['expiration']) || $token['expiration'] < $now) {
                        unset($tokens[$hash]);
                    }
                }
                if (count($tokens) === 0) {
                    // All tokens expired — delete the row
                    $wpdb->delete($wpdb->usermeta, array('umeta_id' => $row->umeta_id));
                    $cleaned++;
                } elseif (count($tokens) < $original_count) {
                    // Some expired — re-save the pruned array
                    $wpdb->update(
                        $wpdb->usermeta,
                        array('meta_value' => maybe_serialize($tokens)),
                        array('umeta_id' => $row->umeta_id)
                    );
                    $cleaned++;
                }
            }
            $r['user_sessions'] = $cleaned;
        }

        // OPTIMIZE TABLE is split out: we only run it from cron if the explicit opt-in
        // `db_optimize_in_cron` is on. The main `db_optimize_tables` toggle now controls
        // whether manual triggers perform the optimize step.
        if (!empty($o['db_optimize_tables']) && !empty($o['db_optimize_in_cron'])) {
            $r = array_merge($r, $this->run_optimize_tables());
        }

        /**
         * Fires after cleanup completes. Includes per-bucket counts.
         * Extenders can use this for notifications, exports, or resuming workers.
         */
        do_action('fd/after_run_cleanup', $r);

        if (class_exists('FD_Activity_Log')) {
            FD_Activity_Log::record('database_cleanup', array(
                'rows_affected' => array_sum(array_map('intval', $r)),
                'buckets'       => $r,
            ));
        }

        return $r;
    }

    /**
     * Run OPTIMIZE TABLE on the standard + WooCommerce tables.
     * Split out of run_cleanup() because OPTIMIZE TABLE locks InnoDB tables for minutes
     * on large stores — must be explicitly triggered, not run on every nightly cron.
     */
    public function run_optimize_tables() {
        global $wpdb;
        $tables = array($wpdb->posts,$wpdb->postmeta,$wpdb->options,$wpdb->comments,$wpdb->commentmeta,$wpdb->terms,$wpdb->term_taxonomy,$wpdb->term_relationships,$wpdb->termmeta,$wpdb->usermeta);
        $woo = array('woocommerce_order_items','woocommerce_order_itemmeta','wc_product_meta_lookup','wc_order_stats','actionscheduler_actions','actionscheduler_logs');
        foreach ($woo as $w) { $f=$wpdb->prefix.$w; if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $f))) $tables[]=$f; }
        $list = implode(', ', array_map(function($t){ return "`{$t}`"; }, $tables));
        $wpdb->query("OPTIMIZE TABLE {$list}");

        $result = array('optimized_tables' => count($tables));

        if (class_exists('FD_Activity_Log')) {
            FD_Activity_Log::record('optimize_tables', array(
                'rows_affected' => count($tables),
                'tables'        => $tables,
            ));
        }

        return $result;
    }
}
