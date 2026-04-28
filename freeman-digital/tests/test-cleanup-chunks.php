<?php
/**
 * @covers FD_Database::run_cleanup
 */
class Test_FD_Cleanup_Chunks extends WP_UnitTestCase {

    public function test_cleanup_batch_size_filter_is_respected() {
        $seen = array();
        add_filter('fd/cleanup_batch_size', function ($size) use (&$seen) {
            $seen[] = (int) $size;
            return 100;
        });

        $opts = FD_Core::get_defaults();
        $opts['db_cleanup_revisions']   = 1;
        $opts['db_cleanup_auto_drafts'] = 1;
        $opts['db_cleanup_trash_posts'] = 1;
        update_option(FD_OPT, $opts);

        $db = new FD_Database($opts);
        $results = $db->run_cleanup();

        $this->assertIsArray($results);
        $this->assertNotEmpty($seen, 'cleanup_batch_size filter should have run at least once.');
        $this->assertSame(5000, $seen[0], 'Default batch size handed to the filter should be 5000.');

        remove_all_filters('fd/cleanup_batch_size');
    }

    public function test_before_and_after_hooks_fire() {
        $before = 0; $after = 0;
        add_action('fd/before_run_cleanup', function () use (&$before) { $before++; });
        add_action('fd/after_run_cleanup', function ($r) use (&$after) { $after++; });

        $opts = FD_Core::get_defaults();
        update_option(FD_OPT, $opts);

        $db = new FD_Database($opts);
        $db->run_cleanup();

        $this->assertSame(1, $before, 'fd/before_run_cleanup must fire once.');
        $this->assertSame(1, $after, 'fd/after_run_cleanup must fire once.');

        remove_all_actions('fd/before_run_cleanup');
        remove_all_actions('fd/after_run_cleanup');
    }

    public function test_revisions_are_deleted_in_chunks() {
        global $wpdb;

        $post_id = self::factory()->post->create();
        // Create several revisions.
        for ($i = 0; $i < 5; $i++) {
            wp_update_post(array('ID' => $post_id, 'post_content' => 'rev ' . $i));
        }

        $rev_count_before = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=%d", $post_id)
        );
        $this->assertGreaterThan(0, $rev_count_before);

        $opts = FD_Core::get_defaults();
        $opts['db_cleanup_revisions'] = 1;
        update_option(FD_OPT, $opts);

        $db = new FD_Database($opts);
        $db->run_cleanup();

        $rev_count_after = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=%d", $post_id)
        );
        $this->assertSame(0, $rev_count_after, 'All revisions must be removed.');
    }
}
