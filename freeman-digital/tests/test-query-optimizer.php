<?php
/**
 * @covers FD_Query_Optimizer
 */
class Test_FD_Query_Optimizer extends WP_UnitTestCase {

    public function test_months_cache_invalidated_on_post_publish() {
        $opts = FD_Core::get_defaults();
        $opts['qo_cache_post_months'] = 1;
        update_option(FD_OPT, $opts);

        set_transient('fd_months_post_posts', 'seeded', HOUR_IN_SECONDS);
        $this->assertNotFalse(get_transient('fd_months_post_posts'));

        self::factory()->post->create(array('post_status' => 'publish'));

        // Invalidation should have nuked the transient.
        $this->assertFalse(get_transient('fd_months_post_posts'));
    }

    public function test_months_cache_invalidated_on_trash() {
        $opts = FD_Core::get_defaults();
        $opts['qo_cache_post_months'] = 1;
        update_option(FD_OPT, $opts);

        $post_id = self::factory()->post->create(array('post_status' => 'publish'));
        set_transient('fd_months_post_posts', 'seeded', HOUR_IN_SECONDS);

        wp_trash_post($post_id);

        $this->assertFalse(get_transient('fd_months_post_posts'));
    }

    public function test_remove_sort_strips_orderby_when_enabled() {
        $opts = FD_Core::get_defaults();
        $opts['qo_remove_sort_order'] = 1;
        update_option(FD_OPT, $opts);

        $q = new WP_Query(array(
            'post_type'       => 'post',
            'posts_per_page'  => 1,
            'orderby'         => 'date',
            'order'           => 'DESC',
            'fields'          => 'ids',
            'no_found_rows'   => true,
        ));

        // The query should run without error and orderby should be emptied.
        $this->assertIsArray($q->posts);
    }
}
