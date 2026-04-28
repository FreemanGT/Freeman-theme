<?php
if (!defined('ABSPATH')) exit;

class FD_Security {
    public function __construct($o) {
        // XML-RPC: consolidate with pingbacks to avoid duplicate filter registration
        $disable_xmlrpc = !empty($o['sec_disable_xmlrpc']);
        $disable_pingbacks = !empty($o['sec_disable_pingbacks']);

        if ($disable_xmlrpc) {
            add_filter('xmlrpc_enabled', '__return_false');
            // When fully disabling XML-RPC, wipe all methods
            add_filter('xmlrpc_methods', function(){ return array(); });
        } elseif ($disable_pingbacks) {
            // Only strip pingback methods (keep other xmlrpc methods)
            add_filter('xmlrpc_methods', function($m){
                unset($m['pingback.ping'], $m['pingback.extensions.getPingbacks']);
                return $m;
            });
        }
        // X-Pingback header removal — only one filter, even if both toggles enabled
        if ($disable_xmlrpc || $disable_pingbacks) {
            add_filter('wp_headers', function($h){ unset($h['X-Pingback']); return $h; });
        }
        if ($disable_pingbacks) {
            add_filter('pings_open', '__return_false', 999);
        }

        if (!empty($o['sec_disable_file_editing']) && !defined('DISALLOW_FILE_EDIT'))
            define('DISALLOW_FILE_EDIT', true);

        // WP version hiding — handled here ONLY. Speed module defers to this.
        if (!empty($o['sec_hide_wp_version'])) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
            $strip_ver = function($src){ return strpos($src,'ver=')!==false ? remove_query_arg('ver',$src) : $src; };
            add_filter('style_loader_src', $strip_ver, 9999);
            add_filter('script_loader_src', $strip_ver, 9999);
        }

        // Author enumeration protection
        if (!empty($o['sec_disable_author_enum'])) {
            add_action('template_redirect', function(){
                if (isset($_GET['author']) && !is_admin() && preg_match('/^\d+$/', $_GET['author'])) {
                    wp_safe_redirect(home_url(), 301); exit;
                }
            });
        }

        if (!empty($o['sec_hide_login_errors']))
            add_filter('login_errors', function(){ return __('Invalid login credentials.','freeman-digital'); });

        if (!empty($o['sec_remove_rsd_link']))
            remove_action('wp_head', 'rsd_link');
        if (!empty($o['sec_remove_wlw_link']))
            remove_action('wp_head', 'wlwmanifest_link');

        if (!empty($o['sec_disable_app_passwords']))
            add_filter('wp_is_application_passwords_available', '__return_false');

        if (!empty($o['sec_add_security_headers'])) {
            $enable_coop = !empty($o['sec_enable_coop']);
            add_action('send_headers', function() use ($enable_coop) {
                // Scope these to frontend requests only. REST / AJAX / admin responses are
                // often legitimately embedded or fetched cross-origin (embeds, gateway
                // iframes, OAuth popups) and sending SAMEORIGIN/COOP breaks them.
                if (is_admin()) return;
                if (defined('DOING_AJAX') && DOING_AJAX) return;
                if (defined('REST_REQUEST') && REST_REQUEST) return;
                if (headers_sent()) return;

                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: SAMEORIGIN');
                // X-XSS-Protection removed in 1.7.2 — deprecated by Chrome/Edge/Firefox, can
                // introduce its own XSS vectors on older browsers. CSP is the modern tool.
                header('Referrer-Policy: strict-origin-when-cross-origin');
                header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
                if ($enable_coop) {
                    // Opt-in because it breaks window.opener for popup-based OAuth flows
                    // (Google Sign-In, Facebook Login, etc).
                    header('Cross-Origin-Opener-Policy: same-origin');
                }
            });
        }

        // REST API user endpoint removal — consolidated to avoid duplicate filters.
        // Triggers if ANY of: full REST restriction, user endpoint disable, or author enum.
        $hide_users_rest = !empty($o['sec_disable_user_rest']) || !empty($o['sec_disable_author_enum']);

        if (!empty($o['sec_restrict_rest_api'])) {
            // Full REST restriction — no need for finer user endpoint filter
            add_filter('rest_authentication_errors', function($result){
                if (!empty($result)) return $result;
                if (!is_user_logged_in()) return new WP_Error('rest_no_auth','REST API restricted.',array('status'=>401));
                return $result;
            });
        } elseif ($hide_users_rest) {
            // Single filter registration handles both author enum and disable user rest
            add_filter('rest_endpoints', function($ep){
                if (!is_user_logged_in()) {
                    unset($ep['/wp/v2/users'], $ep['/wp/v2/users/(?P<id>[\d]+)']);
                }
                return $ep;
            });
        }
    }
}
