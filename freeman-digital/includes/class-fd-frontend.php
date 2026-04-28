<?php
if (!defined('ABSPATH')) exit;

/**
 * Freeman Digital — Frontend Optimizer
 *
 * Controls WHAT assets get loaded (complementary to WP Rocket which handles HOW they're delivered).
 * Elementor-aware: knows which scripts/styles to safely remove.
 */
class FD_Frontend {
    private $o;

    public function __construct($o) {
        $this->o = $o;
        if (is_admin()) return;

        // Elementor cleanup
        if (!empty($o['fe_cleanup_elementor']))
            add_action('wp_enqueue_scripts', array($this, 'cleanup_elementor'), 999);

        // Elementor icons
        if (!empty($o['fe_remove_elementor_icons']))
            add_action('wp_enqueue_scripts', function(){ wp_dequeue_style('elementor-icons'); }, 20);

        // Google Fonts optimization
        if (!empty($o['fe_optimize_google_fonts']))
            add_filter('style_loader_tag', array($this, 'optimize_font_loading'), 10, 4);

        // font-display: swap enforcement
        if (!empty($o['fe_font_display_swap']))
            add_filter('wp_head', array($this, 'add_font_display_swap'), 1);

        // Preconnect hints
        if (!empty($o['fe_add_preconnect']))
            add_action('wp_head', array($this, 'add_preconnect'), 1);

        // Preload LCP image
        if (!empty($o['fe_preload_lcp']) && !empty($o['fe_lcp_image_url']))
            add_action('wp_head', array($this, 'preload_lcp_image'), 1);

        // Disable WooCommerce blocks CSS (loaded even without blocks)
        if (!empty($o['fe_disable_wc_blocks_css']))
            add_action('wp_enqueue_scripts', array($this, 'remove_wc_blocks'), 100);

        // Disable Elementor Pro animations on mobile
        if (!empty($o['fe_disable_animations_mobile'])) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_mobile_animation_disabler'), 999);
        }

        // Lazy load iframes (YouTube, maps)
        if (!empty($o['fe_lazy_iframes']))
            add_filter('the_content', array($this, 'lazy_load_iframes'), 99);

        // Remove Elementor frontend JS when not needed (static pages)
        if (!empty($o['fe_defer_elementor_js']))
            add_filter('script_loader_tag', array($this, 'defer_elementor_scripts'), 10, 3);

        // Disable Contact Form 7 everywhere except where needed.
        // The previous has_shortcode($post->post_content, ...) approach missed
        // Elementor-widget-rendered and sidebar-widget-rendered CF7 instances.
        // We register a shortcode-tag listener that flips a flag when ANY CF7 form renders
        // (Elementor calls do_shortcode internally), then dequeue at wp_footer:1 if the flag
        // never flipped.
        if (!empty($o['fe_conditional_cf7'])) {
            add_filter('do_shortcode_tag', array($this, 'detect_cf7_shortcode'), 10, 2);
            add_action('wp_footer', array($this, 'maybe_dequeue_cf7'), 1);
        }

        // Disable Elementor Google Fonts (use system fonts or self-hosted)
        if (!empty($o['fe_disable_elementor_gfonts']))
            add_filter('elementor/frontend/print_google_fonts', '__return_false');

        // Add fetchpriority="high" to LCP image (must match fe_lcp_image_url — no-op if empty)
        if (!empty($o['fe_fetchpriority_lcp']) && !empty($o['fe_lcp_image_url']))
            add_filter('wp_get_attachment_image_attributes', array($this, 'add_fetchpriority'), 10, 3);
    }

    /**
     * Remove Elementor assets that aren't needed on the current page.
     * Previously broken: used function_exists() to check for a class, which always returned false.
     * Now uses class_exists() and verifies the singleton instance is available.
     */
    public function cleanup_elementor() {
        $elementor_active = class_exists('\\Elementor\\Plugin')
            && isset(\Elementor\Plugin::$instance)
            && \Elementor\Plugin::$instance !== null;

        if ($elementor_active) {
            $post_id = get_the_ID();
            if ($post_id) {
                try {
                    $document = \Elementor\Plugin::$instance->documents->get($post_id);
                    $is_elementor_page = ($document && method_exists($document, 'is_built_with_elementor') && $document->is_built_with_elementor());
                } catch (\Exception $e) {
                    $is_elementor_page = false;
                }

                // Check Elementor Pro theme-builder: non-Elementor posts may still inherit
                // an Elementor-built header, footer, or archive template. If ANY theme-builder
                // location has a document, treat the page as an Elementor page so we don't
                // strip assets the header/footer needs.
                if (!$is_elementor_page && class_exists('\\ElementorPro\\Modules\\ThemeBuilder\\Module')) {
                    try {
                        $tb = \ElementorPro\Modules\ThemeBuilder\Module::instance();
                        if ($tb && method_exists($tb, 'get_locations_manager')) {
                            $mgr = $tb->get_locations_manager();
                            $locations = array_merge(
                                (array) $mgr->get_documents_for_location('header'),
                                (array) $mgr->get_documents_for_location('footer'),
                                (array) $mgr->get_documents_for_location('archive'),
                                (array) $mgr->get_documents_for_location('single')
                            );
                            if (!empty($locations)) $is_elementor_page = true;
                        }
                    } catch (\Exception $e) {
                        // If detection fails, err on the side of keeping Elementor assets
                        $is_elementor_page = true;
                    }
                }

                if (!$is_elementor_page) {
                    // This page doesn't use Elementor — remove all Elementor frontend assets.
                    wp_dequeue_style('elementor-frontend');
                    wp_dequeue_style('elementor-post-' . $post_id);
                    wp_dequeue_style('elementor-global');
                    wp_dequeue_style('elementor-icons');
                    wp_dequeue_style('elementor-animations');
                    wp_dequeue_script('elementor-frontend');
                    wp_dequeue_script('elementor-pro-frontend');
                    wp_dequeue_script('elementor-waypoints');
                    return;
                }
            }
        }

        // On Elementor pages (or if Elementor not active): remove block library CSS (not needed with Elementor)
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('classic-theme-styles');

        // Remove eicons if not used (Elementor default icons — most themes use Font Awesome instead)
        // Only remove if 'fe_remove_elementor_icons' is set
    }

    /**
     * Add preconnect resource hints for external domains
     */
    public function add_preconnect() {
        $domains = array();

        // Google Fonts (if used)
        if (empty($this->o['fe_disable_elementor_gfonts'])) {
            $domains[] = 'https://fonts.googleapis.com';
            $domains[] = 'https://fonts.gstatic.com';
        }

        // Common CDNs used by Elementor/addons
        $domains[] = 'https://cdnjs.cloudflare.com';

        // Custom domains from settings
        if (!empty($this->o['fe_preconnect_domains'])) {
            $custom = array_filter(array_map('trim', explode("\n", $this->o['fe_preconnect_domains'])));
            $domains = array_merge($domains, $custom);
        }

        foreach (array_unique($domains) as $domain) {
            if (!empty($domain)) {
                echo '<link rel="preconnect" href="' . esc_url($domain) . '" crossorigin>' . "\n";
            }
        }
    }

    /**
     * Preload the LCP image so the browser fetches it immediately
     */
    public function preload_lcp_image() {
        $url = $this->o['fe_lcp_image_url'];
        if (empty($url)) return;
        $type = '';
        if (preg_match('/\.(webp)$/i', $url)) $type = 'image/webp';
        elseif (preg_match('/\.(jpg|jpeg)$/i', $url)) $type = 'image/jpeg';
        elseif (preg_match('/\.(png)$/i', $url)) $type = 'image/png';
        elseif (preg_match('/\.(avif)$/i', $url)) $type = 'image/avif';

        echo '<link rel="preload" as="image" href="' . esc_url($url) . '"';
        if ($type) echo ' type="' . $type . '"';
        echo ' fetchpriority="high">' . "\n";
    }

    /**
     * Remove WooCommerce Blocks CSS (loaded on every page even without blocks)
     */
    public function remove_wc_blocks() {
        wp_dequeue_style('wc-blocks-style');
        wp_dequeue_style('wc-blocks-vendors-style');
        wp_dequeue_style('wc-all-blocks-style');
    }

    /**
     * Disable animations on mobile via CSS media query.
     * Uses wp_add_inline_style to attach to an already-enqueued stylesheet
     * (falls back to registering a dummy handle if none available) instead of
     * raw echo in wp_head, so the CSS is properly minified/cached by WP Rocket.
     */
    public function enqueue_mobile_animation_disabler() {
        $css = '@media (max-width: 767px) {'
            . '.elementor-invisible { visibility: visible !important; }'
            . '.animated { animation: none !important; transition: none !important; }'
            . '[data-settings*="animation"] { animation: none !important; }'
            . '.elementor-widget[data-settings] { animation: none !important; opacity: 1 !important; }'
            . '}';

        // Attach to an already-enqueued stylesheet when possible
        $targets = array('elementor-frontend', 'woocommerce-general', 'wp-block-library');
        foreach ($targets as $handle) {
            if (wp_style_is($handle, 'enqueued')) {
                wp_add_inline_style($handle, $css);
                return;
            }
        }

        // Fallback: register a tiny empty handle and attach the inline style
        wp_register_style('fd-mobile-anim', false);
        wp_enqueue_style('fd-mobile-anim');
        wp_add_inline_style('fd-mobile-anim', $css);
    }

    /**
     * Add loading="lazy" to iframes, but skip the FIRST iframe on the page.
     * Hero/above-the-fold video iframes should load eagerly; lazy-loading them hurts LCP.
     */
    public function lazy_load_iframes($content) {
        if (empty($content)) return $content;
        $count = 0;
        return preg_replace_callback(
            '/<iframe((?![^>]*loading=)[^>]*)>/i',
            function ($m) use (&$count) {
                $count++;
                if ($count === 1) return $m[0]; // leave hero iframe alone
                return '<iframe' . $m[1] . ' loading="lazy">';
            },
            $content
        );
    }

    /**
     * Add defer attribute to Elementor JS files.
     *
     * Narrowed list: deferring elementor-frontend / elementor-pro-frontend breaks custom
     * widget initialization order (their init handlers run before the frontend core is ready).
     * elementor-webpack-runtime must load synchronously so subsequent chunks can register.
     * Only swiper, share-link, and elementor-waypoints are safe to defer — they either
     * self-initialize or attach late.
     */
    public function defer_elementor_scripts($tag, $handle, $src) {
        $defer_handles = array('swiper', 'share-link', 'elementor-waypoints');
        if (in_array($handle, $defer_handles, true) && strpos($tag, 'defer') === false) {
            $tag = str_replace(' src=', ' defer src=', $tag);
        }
        return $tag;
    }

    /**
     * Shortcode-tag listener — flips a flag when a CF7 form renders anywhere
     * (including Elementor widgets and sidebar widgets that call do_shortcode internally).
     */
    private $has_cf7_rendered = false;
    public function detect_cf7_shortcode($output, $tag) {
        if ($tag === 'contact-form-7') {
            $this->has_cf7_rendered = true;
        }
        return $output;
    }

    /**
     * At wp_footer priority 1, all content has rendered (main content, Elementor widgets,
     * sidebar widgets). If no CF7 form was encountered, safe to dequeue its assets.
     * Uses priority 1 (early) so scripts aren't printed yet at the footer bottom.
     */
    public function maybe_dequeue_cf7() {
        if ($this->has_cf7_rendered) return;
        wp_dequeue_style('contact-form-7');
        wp_dequeue_script('contact-form-7');
        wp_dequeue_script('wpcf7-recaptcha');
        wp_dequeue_script('google-recaptcha');
    }

    /**
     * Force font-display:swap on Google Fonts to prevent invisible text during loading.
     * Captures the original href BEFORE modification (this was the bug in the original version —
     * it overwrote $href with add_query_arg then tried to str_replace, which replaced the string
     * with itself).
     */
    public function optimize_font_loading($html, $handle, $href, $media) {
        if (empty($href)) return $html;
        if (strpos($href, 'fonts.googleapis.com') === false
            && strpos($href, 'fonts.bunny.net') === false
            && strpos($href, 'use.typekit.net') === false) {
            return $html;
        }
        if (strpos($href, 'display=') !== false) return $html; // already has display param

        $new_href = add_query_arg('display', 'swap', $href);
        // Replace the ORIGINAL href in HTML with the new one
        $html = str_replace($href, $new_href, $html);
        return $html;
    }

    /**
     * Enforce font-display:swap on enqueued inline stylesheets.
     *
     * Previous implementation wrapped the entire wp_head→wp_footer response in an output
     * buffer and regex-rewrote @font-face blocks. That approach buffered every byte of
     * every page, costing ~1-3ms per request and breaking incremental flushing.
     *
     * This version is narrower: we hook `style_loader_tag` (per-stylesheet) and rewrite
     * any @font-face declarations inside inline <style> tags generated by wp_add_inline_style.
     * Self-hosted @font-face rules shipped by the active theme in its own CSS files aren't
     * rewritten (out of scope — can't be modified from a plugin without parsing every CSS
     * file on disk). Theme authors should add `font-display: swap;` upstream.
     */
    public function add_font_display_swap() {
        add_filter('style_loader_tag', array($this, 'inject_font_display_swap'), 20, 4);
    }

    /**
     * Rewrite inline <style>...@font-face...</style> blocks wp_add_inline_style emits
     * to include font-display: swap (if missing).
     */
    public function inject_font_display_swap($html, $handle = '', $href = '', $media = '') {
        if (empty($html) || stripos($html, '@font-face') === false) return $html;
        return preg_replace_callback(
            '/@font-face\s*\{([^}]*)\}/is',
            function ($m) {
                $body = $m[1];
                if (stripos($body, 'font-display') !== false) return $m[0];
                $body = rtrim($body);
                if (substr($body, -1) !== ';') $body .= ';';
                $body .= ' font-display: swap;';
                return '@font-face {' . $body . '}';
            },
            $html
        );
    }

    /**
     * Add fetchpriority="high" + loading="eager" to the configured LCP image.
     *
     * Previous implementation used a static flag that targeted whichever image rendered
     * first — usually a logo, sidebar thumbnail, or Elementor placeholder — which defeats
     * the purpose. Now it only modifies the attachment whose URL matches fe_lcp_image_url.
     */
    public function add_fetchpriority($attr, $attachment, $size) {
        if (is_admin() || empty($this->o['fe_lcp_image_url']) || empty($attachment)) return $attr;

        $target_url = trim($this->o['fe_lcp_image_url']);
        if ($target_url === '') return $attr;

        $attachment_id = is_object($attachment) ? (int) $attachment->ID : 0;
        if (!$attachment_id) return $attr;

        $attachment_url = wp_get_attachment_url($attachment_id);
        if (!$attachment_url) return $attr;

        if (strpos($attachment_url, $target_url) === 0 || $attachment_url === $target_url) {
            $attr['fetchpriority'] = 'high';
            $attr['loading'] = 'eager';
        }
        return $attr;
    }
}
