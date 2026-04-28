<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSN_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_rsn_subscribe', array( $this, 'handle_subscribe' ) );
        add_action( 'wp_ajax_nopriv_rsn_subscribe', array( $this, 'handle_subscribe' ) );
    }

    public function handle_subscribe() {
        if ( ! check_ajax_referer( 'rsn_subscribe', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'freeman-core' ) ) );
        }

        // Honeypot — real users can't fill a hidden field; bots usually do.
        // Respond with a generic success to avoid signalling detection.
        if ( ! empty( $_POST['_hp'] ) ) {
            wp_send_json_success( array(
                'message' => rsn_get_option( 'form_success_message' ),
            ) );
        }

        // Per-IP rate limit (REMOTE_ADDR only — not spoofable via proxy
        // headers). 5 submissions / hour shared with any other caller that
        // uses the same bucket.
        if ( ! \Freeman\Core\Core\Security::rate_limit( 'rsn_subscribe', 5, HOUR_IN_SECONDS ) ) {
            wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'freeman-core' ) ) );
        }

        $product_id   = absint( $_POST['product_id'] ?? 0 );
        $variation_id = absint( $_POST['variation_id'] ?? 0 );
        $name         = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $email        = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product.', 'freeman-core' ) ) );
        }
        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'freeman-core' ) ) );
        }

        $product = wc_get_product( $variation_id ? $variation_id : $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Product not found.', 'freeman-core' ) ) );
        }

        if ( 'yes' === rsn_get_option( 'enable_gdpr' ) ) {
            if ( empty( $_POST['gdpr'] ) || 'yes' !== $_POST['gdpr'] ) {
                wp_send_json_error( array( 'message' => __( 'Please confirm the consent checkbox to continue.', 'freeman-core' ) ) );
            }
        }

        if ( RSN_Database::exists( $email, $product_id, $variation_id ) ) {
            wp_send_json_error( array(
                'message'   => rsn_get_option( 'form_duplicate_message' ),
                'duplicate' => true,
            ) );
        }

        $id = RSN_Database::insert( array(
            'product_id'     => $product_id,
            'variation_id'   => $variation_id,
            'customer_name'  => $name,
            'customer_email' => $email,
            'status'         => 'waiting',
        ) );

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'freeman-core' ) ) );
        }

        if ( 'yes' === rsn_get_option( 'enable_confirmation' ) ) {
            $subscriber = RSN_Database::get( $id );
            RSN_Email::send_confirmation( $subscriber );
        }

        wp_send_json_success( array(
            'message' => rsn_get_option( 'form_success_message' ),
        ) );
    }
}
