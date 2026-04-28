<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSN_Email {

    private static function product_name( $subscriber ) {
        $parent = wc_get_product( $subscriber->product_id );
        if ( ! $parent ) return '';
        $name = $parent->get_name();
        if ( $subscriber->variation_id ) {
            $v = wc_get_product( $subscriber->variation_id );
            if ( $v ) {
                $a = implode( ', ', array_map( function($x){ return ucfirst(str_replace('-',' ',$x)); }, $v->get_variation_attributes() ) );
                if ( $a ) $name .= ' — ' . $a;
            }
        }
        return $name;
    }

    private static function replacements( $subscriber, $product_name ) {
        $parent = wc_get_product( $subscriber->product_id );
        return array(
            '{product_name}'    => $product_name,
            '{customer_name}'   => $subscriber->customer_name ?: __( 'לקוח/ה', 'freeman-core' ),
            '{product_url}'     => $parent ? $parent->get_permalink() : home_url(),
            '{unsubscribe_url}' => add_query_arg( 'rsn_unsubscribe', $subscriber->unsubscribe_token, home_url() ),
            '{shop_url}'        => wc_get_page_permalink( 'shop' ),
            '{site_name}'       => get_bloginfo( 'name' ),
        );
    }

    public static function send_confirmation( $subscriber ) {
        $product = wc_get_product( $subscriber->variation_id ?: $subscriber->product_id );
        if ( ! $product ) return false;
        $parent = $subscriber->variation_id ? wc_get_product( $subscriber->product_id ) : $product;

        $pname = self::product_name( $subscriber );
        $r     = self::replacements( $subscriber, $pname );

        $subject = str_replace( array_keys($r), array_values($r), rsn_get_option( 'confirm_subject' ) );
        $heading = str_replace( array_keys($r), array_values($r), rsn_get_option( 'confirm_heading' ) );
        $body    = str_replace( array_keys($r), array_values($r), rsn_get_option( 'confirm_body' ) );

        $html = self::build_html( array(
            'heading' => $heading, 'body' => $body, 'product_name' => $pname,
            'product_image' => wp_get_attachment_url( $parent->get_image_id() ),
            'unsubscribe_url' => $r['{unsubscribe_url}'],
            'customer_name' => $subscriber->customer_name ?: __( 'לקוח/ה', 'freeman-core' ),
        ) );

        return self::send( $subscriber->customer_email, $subject, $html );
    }

    public static function send_notification( $subscriber ) {
        $product = wc_get_product( $subscriber->variation_id ?: $subscriber->product_id );
        if ( ! $product ) return false;
        $parent = $subscriber->variation_id ? wc_get_product( $subscriber->product_id ) : $product;

        $pname = self::product_name( $subscriber );
        $r     = self::replacements( $subscriber, $pname );

        $subject = str_replace( array_keys($r), array_values($r), rsn_get_option( 'notify_subject' ) );
        $heading = str_replace( array_keys($r), array_values($r), rsn_get_option( 'notify_heading' ) );
        $body    = str_replace( array_keys($r), array_values($r), rsn_get_option( 'notify_body' ) );
        $btn_txt = str_replace( array_keys($r), array_values($r), rsn_get_option( 'notify_button_text' ) );

        $html = self::build_html( array(
            'heading' => $heading, 'body' => $body, 'product_name' => $pname,
            'product_image'   => wp_get_attachment_url( $parent->get_image_id() ),
            'button_url'      => $parent->get_permalink(),
            'button_text'     => $btn_txt,
            'unsubscribe_url' => $r['{unsubscribe_url}'],
            'customer_name'   => $subscriber->customer_name ?: __( 'לקוח/ה', 'freeman-core' ),
        ) );

        return self::send( $subscriber->customer_email, $subject, $html );
    }

    private static function build_html( $a ) {
        $a = wp_parse_args( $a, array(
            'heading'=>'','body'=>'','product_name'=>'','product_image'=>'',
            'button_url'=>'','button_text'=>'','unsubscribe_url'=>'','customer_name'=> __( 'לקוח/ה', 'freeman-core' ),
        ) );
        $site = esc_html( get_bloginfo('name') );

        $img = '';
        if ( $a['product_image'] ) {
            $img = '<div style="text-align:center;margin:24px 0;"><img src="'.esc_url($a['product_image']).'" alt="'.esc_attr($a['product_name']).'" style="max-width:200px;height:auto;border-radius:8px;" /></div>';
        }
        $btn = '';
        if ( $a['button_url'] && $a['button_text'] ) {
            $btn = '<div style="text-align:center;margin:32px 0 16px;"><a href="'.esc_url($a['button_url']).'" style="display:inline-block;background:#000;color:#fff;text-decoration:none;padding:14px 36px;border-radius:6px;font-size:15px;font-weight:600;">'.esc_html($a['button_text']).'</a></div>';
        }
        $unsub = '';
        if ( $a['unsubscribe_url'] ) {
            $unsub = '<p style="margin:0;font-size:12px;color:#999;"><a href="'.esc_url($a['unsubscribe_url']).'" style="color:#999;text-decoration:underline;">' . esc_html__( 'הסרה מרשימת התפוצה', 'freeman-core' ) . '</a> ' . esc_html__( 'עבור מוצר זה.', 'freeman-core' ) . '</p>';
        }

        return '<!DOCTYPE html><html lang="he" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f7f7f7;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;direction:rtl;text-align:right;">
<div style="max-width:560px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);direction:rtl;text-align:right;">
<div style="padding:36px 40px 0;text-align:center;">
<div style="display:inline-block;width:48px;height:48px;background:#000;border-radius:50%;text-align:center;line-height:48px;margin-bottom:20px;"><span style="color:#fff;font-size:20px;">&#128276;</span></div>
<h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#111;">'.wp_kses_post($a['heading']).'</h1>
</div>
<div style="padding:16px 40px 24px;text-align:center;">
<p style="margin:0 0 8px;font-size:15px;line-height:1.6;color:#444;">'.esc_html( sprintf( /* translators: %s = customer name */ __( 'היי %s,', 'freeman-core' ), $a['customer_name'] ) ).'</p>
<p style="margin:0;font-size:15px;line-height:1.6;color:#444;">'.wp_kses_post($a['body']).'</p>
</div>
'.$img.$btn.'
<div style="padding:24px 40px 32px;border-top:1px solid #eee;text-align:center;">
<p style="margin:0 0 6px;font-size:12px;color:#999;">'.$site.'</p>
'.$unsub.'
</div></div></body></html>';
    }

    private static function send( $to, $subject, $html ) {
        $fn = (string) rsn_get_option( 'from_name' );
        $fe = (string) rsn_get_option( 'from_email' );

        // Strip CR/LF/NUL from the display name so an admin who pastes a
        // newline-laced value can't inject extra headers (Bcc, Cc, …).
        $fn = preg_replace( '/[\r\n\0]+/', ' ', $fn );
        $fn = trim( wp_strip_all_tags( $fn ) );
        if ( '' === $fn ) {
            $fn = get_bloginfo( 'name' );
        }

        $fe = sanitize_email( $fe );
        if ( '' === $fe || ! is_email( $fe ) ) {
            $fe = (string) get_option( 'admin_email' );
        }

        return wp_mail( $to, $subject, $html, array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: %s <%s>', $fn, $fe ),
        ) );
    }
}
