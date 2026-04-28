<?php
if (!defined('ABSPATH')) exit;

class FD_Bloat {
    public function __construct($o) {
        if (!empty($o['bloat_disable_comments'])) {
            add_action('admin_init', function(){
                global $pagenow;
                if ($pagenow==='edit-comments.php') { wp_safe_redirect(admin_url()); exit; }
                foreach (get_post_types() as $pt) {
                    remove_meta_box('commentsdiv',$pt,'normal');
                    remove_meta_box('commentstatusdiv',$pt,'normal');
                    remove_meta_box('trackbacksdiv',$pt,'normal');
                }
                if (get_option('default_comment_status')!=='closed') update_option('default_comment_status','closed');
                if (get_option('default_ping_status')!=='closed') update_option('default_ping_status','closed');
            });
            add_action('admin_menu', function(){
                remove_menu_page('edit-comments.php');
                remove_submenu_page('options-general.php','options-discussion.php');
            });
            add_filter('comments_open', '__return_false', 20, 2);
            add_filter('pings_open', '__return_false', 20, 2);
            add_filter('comments_array', '__return_empty_array', 10, 2);
            add_action('init', function(){ if(is_admin_bar_showing()) remove_action('admin_bar_menu','wp_admin_bar_comments_menu',60); });
        }

        if (!empty($o['bloat_disable_gutenberg'])) {
            add_filter('use_block_editor_for_post', '__return_false', 10);
            add_filter('use_block_editor_for_post_type', '__return_false', 10);
            add_action('wp_enqueue_scripts', function(){
                wp_dequeue_style('wp-block-library'); wp_dequeue_style('wp-block-library-theme');
                wp_dequeue_style('wc-blocks-style'); wp_dequeue_style('global-styles');
                wp_dequeue_style('classic-theme-styles');
            }, 100);
            add_action('admin_init', function(){ remove_theme_support('core-block-patterns'); });
        }

        if (!empty($o['bloat_remove_dns_prefetch']))
            remove_action('wp_head', 'wp_resource_hints', 2);
    }
}
