<?php
/**
 * Frontend: form rendering, asset loading, injection strategies.
 *
 * DESIGN PHILOSOPHY — "Cannot Fail":
 *  1. CSS & JS load on EVERY frontend page (no conditional checks).
 *  2. The form HTML carries its own inline <style> so it's visible even
 *     if the external stylesheet somehow fails to load.
 *  3. Multiple injection hooks try to place the form; a dedup tracker
 *     prevents duplicates.
 *  4. wp_footer outputs a guaranteed copy with JS relocation as last resort.
 *  5. The shortcode works from any context without relying on is_product().
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RSN_Frontend {

    /** Track rendered product IDs to prevent duplicates. */
    private static $rendered = array();

    /** Whether inline critical CSS was already printed. */
    private static $inline_css_printed = false;

    public function __construct() {

        // ── Assets: load on EVERY frontend page. They're ~3 KB total. ──
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_always' ) );

        // ── Shortcode ──
        add_shortcode( 'restock_notify', array( $this, 'shortcode' ) );

        // ── Auto-inject (multiple strategies) ──
        if ( 'yes' === rsn_get_option( 'auto_inject' ) ) {

            // Strategy A: Standard WooCommerce template hooks.
            add_action( 'woocommerce_single_product_summary',   array( $this, 'hook_product_summary' ), 31 );
            add_action( 'woocommerce_after_single_variation',   array( $this, 'hook_after_variation' ) );
            add_action( 'woocommerce_after_add_to_cart_form',   array( $this, 'hook_after_cart_form' ), 20 );
            add_action( 'woocommerce_product_meta_start',       array( $this, 'hook_product_meta' ), 5 );

            // Strategy B: Filter that fires inside ANY widget rendering stock HTML.
            add_filter( 'woocommerce_get_stock_html', array( $this, 'filter_stock_html' ), 20, 2 );

            // Strategy C: wp_footer — guaranteed output, JS relocates it.
            add_action( 'wp_footer', array( $this, 'footer_inject' ), 50 );
        }

        // ── Unsubscribe handler ──
        add_action( 'init', array( $this, 'handle_unsubscribe' ) );
    }

    /* =================================================================
       ASSETS — unconditional
       ================================================================= */

    public function enqueue_always() {
        // Skip admin / AJAX / REST to keep things clean.
        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        /**
         * Decide whether RSN assets should load on this page.
         *
         * Default: load on product pages, shop / archive, cart, checkout,
         * and any post whose content contains the `[restock_notify]`
         * shortcode. That covers every surface that could render the form
         * while keeping the rest of the site (blog, about, home) lean.
         *
         * Return true from the `rsn_should_enqueue` filter to restore the
         * old "load everywhere" behaviour; return false to suppress.
         */
        $should = $this->should_enqueue_here();
        $should = (bool) apply_filters( 'rsn_should_enqueue', $should );
        if ( ! $should ) {
            return;
        }

        $fs_base  = RSN_PLUGIN_DIR . 'assets/';
        $url_base = RSN_PLUGIN_URL . 'assets/';
        $pick     = array( '\\Freeman\\Core\\Core\\Module_Base', 'pick_min_url' );

        wp_enqueue_style(
            'rsn-frontend',
            call_user_func( $pick, $fs_base, $url_base, 'css/frontend.css' ),
            array(),
            RSN_VERSION
        );

        wp_enqueue_script(
            'rsn-frontend',
            call_user_func( $pick, $fs_base, $url_base, 'js/frontend.js' ),
            array( 'jquery' ),
            RSN_VERSION,
            true
        );

        wp_localize_script( 'rsn-frontend', 'rsn_ajax', array(
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'rsn_subscribe' ),
            'i18n'  => array(
                'invalidEmail'   => __( 'יש להזין כתובת אימייל תקינה.', 'freeman-core' ),
                'consentMissing' => __( 'יש לאשר את תיבת ההסכמה.', 'freeman-core' ),
                'productMissing' => __( 'שגיאה: מזהה מוצר חסר.', 'freeman-core' ),
                'scriptMissing'  => __( 'שגיאה: הסקריפט לא נטען כראוי. רענן את הדף.', 'freeman-core' ),
                'genericError'   => __( 'משהו השתבש. נסו שוב.', 'freeman-core' ),
                'networkError'   => __( 'שגיאת רשת. נסו שוב.', 'freeman-core' ),
            ),
        ) );
    }

    /**
     * Heuristic for conditional enqueue (see enqueue_always above).
     * Errs on the side of "yes, load it" — the goal is to trim the
     * obvious no-ops (blog posts, home page), not to prove non-usage.
     */
    private function should_enqueue_here() {
        if ( function_exists( 'is_product' ) && is_product() ) return true;
        if ( function_exists( 'is_shop' ) && is_shop() ) return true;
        if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) return true;
        if ( function_exists( 'is_cart' ) && is_cart() ) return true;
        if ( function_exists( 'is_checkout' ) && is_checkout() ) return true;
        if ( function_exists( 'is_account_page' ) && is_account_page() ) return true;

        // Shortcode on the current post / page — covers landing pages and
        // block-editor templates that drop the form outside Woo surfaces.
        if ( is_singular() ) {
            $post = get_post();
            if ( $post && has_shortcode( (string) $post->post_content, 'restock_notify' ) ) {
                return true;
            }
        }

        return false;
    }

    /* =================================================================
       SHORTCODE
       ================================================================= */

    public function shortcode( $atts ) {
        $atts       = shortcode_atts( array( 'product_id' => 0 ), $atts, 'restock_notify' );
        $product_id = absint( $atts['product_id'] );

        // Detect product from context if not explicitly given.
        if ( ! $product_id ) {
            $product_id = $this->detect_product_id();
        }
        if ( ! $product_id ) {
            return '<!-- RSN: no product detected; pass product_id="…" to the shortcode. -->';
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return '<!-- RSN: product ' . $product_id . ' not found. -->';
        }

        return $this->maybe_render( $product, 'shortcode' );
    }

    /* =================================================================
       INJECTION HOOKS (Strategy A)
       ================================================================= */

    /** Hook: woocommerce_single_product_summary (priority 31). */
    public function hook_product_summary() {
        $product = $this->get_wc_product();
        if ( ! $product ) return;
        $this->echo_render( $product, 'summary' );
    }

    /** Hook: woocommerce_after_single_variation. */
    public function hook_after_variation() {
        $product = $this->get_wc_product();
        if ( ! $product ) return;
        $this->echo_render( $product, 'variation' );
    }

    /** Hook: woocommerce_after_add_to_cart_form. */
    public function hook_after_cart_form() {
        $product = $this->get_wc_product();
        if ( ! $product ) return;
        $this->echo_render( $product, 'cart_form' );
    }

    /** Hook: woocommerce_product_meta_start. */
    public function hook_product_meta() {
        $product = $this->get_wc_product();
        if ( ! $product ) return;
        $this->echo_render( $product, 'meta' );
    }

    /* =================================================================
       INJECTION: Stock HTML filter (Strategy B)
       ================================================================= */

    public function filter_stock_html( $html, $product ) {
        if ( is_admin() || ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return $html;
        }
        // Only on singular product views.
        if ( ! is_singular( 'product' ) && ! $this->is_wc_product_page() ) {
            return $html;
        }

        $form = $this->maybe_render( $product, 'stock_filter' );
        return $html . $form;
    }

    /* =================================================================
       INJECTION: wp_footer guaranteed fallback (Strategy C)
       ================================================================= */

    public function footer_inject() {
        if ( ! is_singular( 'product' ) && ! $this->is_wc_product_page() ) {
            return;
        }

        $product_id = $this->detect_product_id();
        if ( ! $product_id ) return;

        $product = wc_get_product( $product_id );
        if ( ! $product ) return;

        // If any hook already rendered the form, skip.
        $key = $product->get_id() . '_' . ( $product->is_type( 'variable' ) ? 'variable' : 'simple' );
        if ( isset( self::$rendered[ $key ] ) ) return;

        $form = $this->maybe_render( $product, 'footer', true ); // true = force (ignore dedup for generation)
        if ( empty( $form ) ) return;

        // Output hidden container + JS relocation.
        echo '<div id="rsn-footer-fallback" style="display:none !important;">' . $form . '</div>'; // phpcs:ignore
        ?>
        <script>
        (function(){
            var src = document.getElementById('rsn-footer-fallback');
            if (!src || !src.innerHTML.trim()) return;

            var selectors = [
                '.product .summary .stock',
                '.product .summary .out-of-stock',
                '.product .summary .cart',
                '.product .summary form.cart',
                '.product .elementor-add-to-cart',
                '.product .e-add-to-cart',
                '.product .summary .product_meta',
                '.product .summary .price',
                '.product .woocommerce-product-details__short-description',
                '.product .entry-summary',
                '.product .summary',
                '.product'
            ];

            for (var i = 0; i < selectors.length; i++) {
                var el = document.querySelector(selectors[i]);
                if (el) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = src.innerHTML;
                    el.parentNode.insertBefore(tmp.firstElementChild, el.nextSibling);
                    src.remove();
                    return;
                }
            }
            // Last resort: show in place.
            src.style.display = '';
            src.style.cssText = 'display:block !important; max-width:480px; margin:24px auto;';
        })();
        </script>
        <?php
    }

    /* =================================================================
       CORE RENDER LOGIC
       ================================================================= */

    /**
     * Decide whether to render and return form HTML.
     *
     * @param WC_Product $product
     * @param string     $source  Label for dedup tracking.
     * @param bool       $force   Skip dedup check (used by footer fallback).
     * @return string    HTML or empty string.
     */
    private function maybe_render( $product, $source = '', $force = false ) {
        $is_variable = $product->is_type( 'variable' );
        $is_simple   = $product->is_type( 'simple' ) || $product->is_type( 'external' ) || ( ! $is_variable );

        // Dedup key.
        $key = $product->get_id() . '_' . ( $is_variable ? 'variable' : 'simple' );
        if ( ! $force && isset( self::$rendered[ $key ] ) ) {
            return '';
        }

        if ( $is_simple ) {
            // Only show for out-of-stock simple products.
            if ( $product->is_in_stock() ) {
                return '';
            }
            self::$rendered[ $key ] = $source;
            return $this->render_form( $product->get_id(), 0, false );
        }

        if ( $is_variable ) {
            // ── Deep variation stock check ──
            // We do NOT trust $v['is_in_stock'] from get_available_variations()
            // because WooCommerce overrides it when the parent manages stock.
            // Instead, we inspect each variation object directly.
            //
            // Cached in a transient keyed on the product id + WC's product
            // cache version so any product/variation save busts it without
            // extra hook wiring. TTL is short (15 min) to bound staleness
            // against any stock change WC's cache-helper misses.
            $cache_key = 'rsn_oos_' . $product->get_id();
            if ( class_exists( '\WC_Cache_Helper' ) ) {
                $cache_key .= '_' . \WC_Cache_Helper::get_transient_version( 'product' );
            }
            $cached = get_transient( $cache_key );

            if ( is_array( $cached ) && isset( $cached['oos_ids'], $cached['all_oos'] ) ) {
                $oos_ids = (array) $cached['oos_ids'];
                $all_oos = (bool) $cached['all_oos'];
            } else {
                $oos_ids   = array();
                $total_vars = 0;
                $child_ids = $product->get_children();

                foreach ( $child_ids as $vid ) {
                    $variation = wc_get_product( $vid );
                    if ( ! $variation || ! $variation->exists() ) continue;
                    if ( 'publish' !== $variation->get_status() ) continue;

                    $total_vars++;

                    if ( $this->is_variation_truly_oos( $variation, $product ) ) {
                        $oos_ids[] = (int) $vid;
                    }
                }

                $all_oos = ( $total_vars > 0 && count( $oos_ids ) >= $total_vars );
                set_transient(
                    $cache_key,
                    array( 'oos_ids' => array_values( $oos_ids ), 'all_oos' => $all_oos ),
                    15 * MINUTE_IN_SECONDS
                );
            }

            // Nothing to show if zero variations are out of stock.
            if ( empty( $oos_ids ) ) {
                return '';
            }

            self::$rendered[ $key ] = $source;

            // Pass variation data to JS via inline script.
            // IMPORTANT: JS will use THIS list to decide show/hide —
            // it does NOT trust WooCommerce's variation.is_in_stock.
            $json = wp_json_encode( array(
                'oos_variation_ids' => array_values( $oos_ids ),
                'all_oos'          => $all_oos,
                'product_id'       => $product->get_id(),
            ) );

            $inline_js = '<script>var rsn_variations = ' . $json . ';</script>';

            return $inline_js . $this->render_form( $product->get_id(), 0, true );
        }

        return '';
    }

    /** Echo wrapper for hooks. */
    private function echo_render( $product, $source ) {
        $html = $this->maybe_render( $product, $source );
        if ( $html ) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /* =================================================================
       FORM HTML
       ================================================================= */

    private function render_form( $product_id, $variation_id = 0, $is_variable = false ) {

        $heading     = rsn_get_option( 'form_heading' );
        $description = rsn_get_option( 'form_description' );
        $button_text = rsn_get_option( 'form_button_text' );
        $success_msg = rsn_get_option( 'form_success_message' );
        $enable_gdpr = 'yes' === rsn_get_option( 'enable_gdpr' );
        $gdpr_text   = rsn_get_option( 'gdpr_text' );

        $wrapper_class = $is_variable
            ? 'rsn-form-wrap rsn-variable-product rsn-hidden'
            : 'rsn-form-wrap';

        // Inline critical CSS (printed once) — guarantees visibility
        // even if the external stylesheet fails to load.
        $inline_style = '';
        if ( ! self::$inline_css_printed ) {
            self::$inline_css_printed = true;
            $inline_style = '<style>
                .rsn-form-wrap{margin:24px 0;direction:rtl;text-align:right}
                .rsn-form-card{background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:32px 28px;max-width:480px;direction:rtl;text-align:right;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}
                .rsn-form-heading{margin:0 0 6px;font-size:18px;font-weight:600;color:#111}
                .rsn-form-desc{margin:0 0 20px;font-size:14px;color:#666;line-height:1.6}
                .rsn-form-fields{display:flex;flex-direction:column;gap:12px}
                .rsn-field-row{display:flex;gap:10px}
                .rsn-field{flex:1}
                .rsn-input{display:block;width:100%;padding:11px 14px;font-size:14px;color:#111;background:#fafafa;border:1px solid #e0e0e0;border-radius:8px;outline:none;box-sizing:border-box;direction:rtl;text-align:right;font-family:inherit}
                .rsn-input:focus{background:#fff;border-color:#111;box-shadow:0 0 0 3px rgba(0,0,0,.06)}
                .rsn-submit-btn{display:flex;align-items:center;justify-content:center;width:100%;padding:12px 20px;font-size:14px;font-weight:600;color:#fff;background:#111;border:none;border-radius:8px;cursor:pointer;font-family:inherit}
                .rsn-submit-btn:hover{background:#333}
                .rsn-hidden{display:none!important}
                .rsn-form-icon{display:flex;align-items:center;justify-content:center;width:44px;height:44px;background:#000;border-radius:50%;margin-bottom:16px;color:#fff}
                .rsn-form-success{text-align:center;padding:8px 0}
                .rsn-success-icon{display:inline-flex;align-items:center;justify-content:center;width:52px;height:52px;background:#f0f0f0;border-radius:50%;margin-bottom:12px;color:#111}
                .rsn-error-text{margin:0;padding:10px 14px;font-size:13px;color:#c00;background:#fff5f5;border:1px solid #fdd;border-radius:8px}
                .rsn-gdpr-label{display:flex;align-items:flex-start;gap:8px;font-size:12.5px;color:#666;cursor:pointer}
                .rsn-btn-spinner{display:none}
                .rsn-loading .rsn-btn-text{display:none}
                .rsn-loading .rsn-btn-spinner{display:inline-flex;animation:rsnSpin .8s linear infinite}
                .rsn-loading{pointer-events:none;opacity:.7}
                @keyframes rsnSpin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
                @keyframes rsnFadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
                .rsn-form-wrap{animation:rsnFadeIn .4s ease-out}
                @media(max-width:480px){.rsn-field-row{flex-direction:column}.rsn-form-card{padding:24px 20px}}
            </style>';
        }

        ob_start();
        echo $inline_style; // phpcs:ignore
        ?>
        <div class="<?php echo esc_attr( $wrapper_class ); ?>" dir="rtl" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-variation-id="<?php echo esc_attr( $variation_id ); ?>">
            <div class="rsn-form-card">
                <div class="rsn-form-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </div>
                <h4 class="rsn-form-heading"><?php echo esc_html( $heading ); ?></h4>
                <p class="rsn-form-desc"><?php echo esc_html( $description ); ?></p>

                <div class="rsn-form-fields">
                    <div class="rsn-field-row">
                        <div class="rsn-field">
                            <input type="text" class="rsn-input rsn-name" placeholder="<?php esc_attr_e( 'שם מלא', 'freeman-core' ); ?>" autocomplete="name" />
                        </div>
                        <div class="rsn-field">
                            <input type="email" class="rsn-input rsn-email" placeholder="<?php esc_attr_e( 'כתובת אימייל', 'freeman-core' ); ?>" autocomplete="email" required />
                        </div>
                    </div>

                    <!-- honeypot: real users can't see this; bots fill it. -->
                    <input type="text" name="_hp" class="rsn-hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" />

                    <?php if ( $enable_gdpr ) : ?>
                        <label class="rsn-gdpr-label">
                            <input type="checkbox" class="rsn-gdpr-check" />
                            <span><?php echo esc_html( $gdpr_text ); ?></span>
                        </label>
                    <?php endif; ?>

                    <button type="button" class="rsn-submit-btn">
                        <span class="rsn-btn-text"><?php echo esc_html( $button_text ); ?></span>
                        <span class="rsn-btn-spinner">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                            </svg>
                        </span>
                    </button>
                </div>

                <div class="rsn-form-success rsn-hidden">
                    <div class="rsn-success-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <p class="rsn-success-text"><?php echo esc_html( $success_msg ); ?></p>
                </div>

                <div class="rsn-form-error rsn-hidden">
                    <p class="rsn-error-text"></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* =================================================================
       DEEP VARIATION STOCK CHECK
       Bypasses WooCommerce's stock inheritance from parent.
       ================================================================= */

    /**
     * Determine if a variation is truly out of stock, ignoring the parent's
     * managed stock quantity. This handles the scenario where:
     *  - Parent has "Manage stock" ON with qty > 0
     *  - But individual variations are actually OOS
     *
     * WooCommerce's is_in_stock() would return true in this case because
     * the parent has stock. We look at the variation directly.
     *
     * @param WC_Product_Variation $variation
     * @param WC_Product_Variable  $parent
     * @return bool True if the variation cannot be purchased.
     */
    private function is_variation_truly_oos( $variation, $parent ) {

        // ── Case 1: Variation manages its OWN stock ──
        // This is the clearest signal. If the variation has stock management
        // enabled, use its own quantity.
        if ( $variation->managing_stock() ) {
            if ( $variation->get_stock_quantity() <= 0 && ! $variation->backorders_allowed() ) {
                return true; // Variation stock is 0 or negative, no backorders.
            }
            return false; // Variation has its own stock > 0 or allows backorders.
        }

        // ── Case 2: Variation stock STATUS is explicitly "outofstock" ──
        // Even when the parent manages stock, the variation can have its
        // stock_status meta set to 'outofstock'. This is the most common
        // scenario the user described.
        $status = $variation->get_stock_status();
        if ( 'outofstock' === $status ) {
            return true;
        }

        // ── Case 3: Check the raw meta directly ──
        // Sometimes WooCommerce's getters apply filters that mask the real value.
        // Read the raw _stock_status from postmeta as a fallback.
        $raw_status = get_post_meta( $variation->get_id(), '_stock_status', true );
        if ( 'outofstock' === $raw_status ) {
            return true;
        }

        // ── Case 4: Parent manages stock with qty <= 0 ──
        // If we got here, the variation doesn't manage its own stock and
        // isn't explicitly marked OOS. Check if the parent stock is depleted.
        if ( $parent->managing_stock() ) {
            if ( $parent->get_stock_quantity() <= 0 && ! $parent->backorders_allowed() ) {
                return true;
            }
        }

        // ── Case 5: Variation is not purchasable for other reasons ──
        if ( ! $variation->is_purchasable() ) {
            return true;
        }

        // ── Case 6: Check max_qty — if WC thinks you can't add it to cart ──
        // This covers edge cases with stock thresholds, low stock amounts, etc.
        $max = $variation->get_max_purchase_quantity();
        if ( 0 === $max ) {
            return true;
        }

        return false;
    }

    /* =================================================================
       PRODUCT DETECTION HELPERS
       ================================================================= */

    /**
     * Try every method to detect the current product ID.
     */
    private function detect_product_id() {
        // 1. Global $product object.
        global $product;
        if ( $product && is_a( $product, 'WC_Product' ) ) {
            return $product->get_id();
        }

        // 2. Global $post with product post type.
        global $post;
        if ( $post && 'product' === get_post_type( $post ) ) {
            return $post->ID;
        }

        // 3. Queried object.
        $qo = get_queried_object_id();
        if ( $qo && 'product' === get_post_type( $qo ) ) {
            return $qo;
        }

        // 4. get_the_ID() in a product context.
        $the_id = get_the_ID();
        if ( $the_id && 'product' === get_post_type( $the_id ) ) {
            return $the_id;
        }

        return 0;
    }

    /**
     * Get a WC_Product from global context. Sets it up if missing.
     */
    private function get_wc_product() {
        global $product;
        if ( $product && is_a( $product, 'WC_Product' ) ) {
            return $product;
        }

        $id = $this->detect_product_id();
        if ( $id ) {
            $product = wc_get_product( $id );
            return $product;
        }

        return null;
    }

    /**
     * Is this a WooCommerce product page?
     * Multiple checks for maximum compatibility.
     */
    private function is_wc_product_page() {
        if ( function_exists( 'is_product' ) && is_product() ) return true;
        if ( is_singular( 'product' ) ) return true;

        global $post;
        if ( $post && 'product' === get_post_type( $post ) ) return true;

        return false;
    }

    /* =================================================================
       UNSUBSCRIBE
       ================================================================= */

    public function handle_unsubscribe() {
        if ( ! isset( $_GET['rsn_unsubscribe'] ) ) return;

        $token = sanitize_text_field( wp_unslash( $_GET['rsn_unsubscribe'] ) );
        $sub   = RSN_Database::get_by_token( $token );

        if ( $sub ) {
            RSN_Database::unsubscribe( $sub->id );
            wp_safe_redirect( add_query_arg( 'rsn_unsubscribed', '1', wc_get_page_permalink( 'shop' ) ) );
            exit;
        }

        wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
        exit;
    }
}
