<?php
if (!defined('ABSPATH')) exit;

class FD_Speed {
    private $o;
    public function __construct($o) {
        $this->o = $o;

        // Emojis
        if (!empty($o['spd_disable_emojis'])) add_action('init', array($this,'kill_emojis'));

        // Embeds
        if (!empty($o['spd_disable_embeds'])) add_action('init', function(){
            remove_action('wp_head','wp_oembed_add_discovery_links');
            remove_action('wp_head','wp_oembed_add_host_js');
            wp_deregister_script('wp-embed');
            remove_action('rest_api_init','wp_oembed_register_route');
            add_filter('embed_oembed_discover','__return_false');
            remove_filter('oembed_dataparse','wp_filter_oembed_result',10);
        }, 9999);

        // jQuery Migrate
        if (!empty($o['spd_remove_jquery_migrate']))
            add_action('wp_default_scripts', function($scripts){
                if (!is_admin() && isset($scripts->registered['jquery']) && $scripts->registered['jquery']->deps)
                    $scripts->registered['jquery']->deps = array_diff($scripts->registered['jquery']->deps, array('jquery-migrate'));
            });

        // Query strings
        if (!empty($o['spd_remove_query_strings'])) {
            $fn = function($src){ return strpos($src,'?ver=')!==false||strpos($src,'&ver=')!==false ? remove_query_arg('ver',$src) : $src; };
            add_filter('script_loader_src', $fn, 15);
            add_filter('style_loader_src', $fn, 15);
        }

        // Dashicons
        if (!empty($o['spd_remove_dashicons']))
            add_action('wp_enqueue_scripts', function(){ if(!is_user_logged_in()){ wp_dequeue_style('dashicons'); wp_deregister_style('dashicons'); }});

        // Global styles / block library
        if (!empty($o['spd_remove_global_styles']))
            add_action('wp_enqueue_scripts', function(){
                if(is_admin()) return;
                wp_dequeue_style('global-styles'); wp_dequeue_style('wp-block-library');
                wp_dequeue_style('wp-block-library-theme'); wp_dequeue_style('classic-theme-styles');
                remove_action('wp_body_open','wp_global_styles_render_svg_filters');
                remove_action('wp_footer','wp_global_styles_render_svg_filters');
            }, 100);

        // Self ping
        if (!empty($o['spd_disable_self_ping']))
            add_action('pre_ping', function(&$links){ $home=home_url(); foreach($links as $l=>$link) if(strpos($link,$home)===0) unset($links[$l]); });

        // Head cleanup — skip generator removal if sec_hide_wp_version already handles it
        if (!empty($o['spd_remove_generator']) && empty($o['sec_hide_wp_version'])) {
            remove_action('wp_head','wp_generator');
            add_filter('the_generator','__return_empty_string');
        }
        if (!empty($o['spd_remove_shortlink'])) { remove_action('wp_head','wp_shortlink_wp_head',10); remove_action('template_redirect','wp_shortlink_header',11); }
        if (!empty($o['spd_remove_rest_link'])) { remove_action('wp_head','rest_output_link_wp_head',10); remove_action('template_redirect','rest_output_link_header',11); }
        if (!empty($o['spd_remove_feed_links'])) { remove_action('wp_head','feed_links',2); remove_action('wp_head','feed_links_extra',3); }

        // Heartbeat
        if (!empty($o['spd_heartbeat_control']) && $o['spd_heartbeat_control']!=='default') {
            if ($o['spd_heartbeat_control']==='disable')
                add_action('init', function(){ wp_deregister_script('heartbeat'); }, 1);
            else
                add_filter('heartbeat_settings', function($s) use ($o){ $s['interval']=max(15,min(300,(int)$o['spd_heartbeat_freq'])); return $s; });
        }

        // Revisions
        if (!empty($o['spd_limit_revisions']))
            add_filter('wp_revisions_to_keep', function() use ($o){ return max(0,(int)$o['spd_revisions_count']); }, 10, 2);
    }

    public function kill_emojis() {
        remove_action('wp_head','print_emoji_detection_script',7);
        remove_action('admin_print_scripts','print_emoji_detection_script');
        remove_action('wp_print_styles','print_emoji_styles');
        remove_action('admin_print_styles','print_emoji_styles');
        remove_filter('the_content_feed','wp_staticize_emoji');
        remove_filter('comment_text_rss','wp_staticize_emoji');
        remove_filter('wp_mail','wp_staticize_emoji_for_email');
        add_filter('tiny_mce_plugins', function($p){ return is_array($p) ? array_diff($p,array('wpemoji')) : array(); });
        add_filter('wp_resource_hints', function($urls,$type){
            if ($type==='dns-prefetch') $urls = array_values(array_diff($urls,array(apply_filters('emoji_svg_url','https://s.w.org/images/core/emoji/2/svg/'))));
            return $urls;
        }, 10, 2);
    }
}
