<?php
/**
 * Modern Email class for RestockNotify — Wave 2.3b.
 *
 * Replaces the legacy `\RSN_Email` class via `class_alias` in `Module::boot()`.
 * Same static API as the legacy class so legacy callers
 * (`RSN_Ajax::handle_subscribe`, `RSN_Stock_Monitor::manual_notify`) work
 * unchanged after the alias resolves.
 *
 * Two changes vs legacy:
 *  1. Email-shell strings (greeting, customer-name fallback, unsubscribe link
 *     text + suffix) come from `Module::defaults( get_locale() )` instead of
 *     hardcoded Hebrew literals — fixes the bilingual-email bug Wave 1.2 left
 *     as a known limitation for English-locale installs.
 *  2. Two extension hooks land here:
 *     - `freeman_core/restock_notify/email_args` (filter) — mutate the args
 *       dictionary that drives `build_html()`.
 *     - `freeman_core/restock_notify/before_send` (action) — fires immediately
 *       before `wp_mail()`.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\RestockNotify;

defined( 'ABSPATH' ) || exit;

/**
 * Email.
 */
final class Email {

	/**
	 * Build the configurable product-name string for a subscriber, including
	 * variation attributes when the subscription is variation-scoped.
	 *
	 * @param object $subscriber Subscriber row.
	 * @return string
	 */
	private static function product_name( $subscriber ) {
		$parent = wc_get_product( $subscriber->product_id );
		if ( ! $parent ) {
			return '';
		}
		$name = $parent->get_name();
		if ( $subscriber->variation_id ) {
			$v = wc_get_product( $subscriber->variation_id );
			if ( $v ) {
				$attrs = implode(
					', ',
					array_map(
						static function ( $x ) {
							return ucfirst( str_replace( '-', ' ', $x ) );
						},
						$v->get_variation_attributes()
					)
				);
				if ( $attrs ) {
					$name .= ' — ' . $attrs;
				}
			}
		}
		return $name;
	}

	/**
	 * Build the placeholder → value map used by subject / heading / body
	 * substitution.
	 *
	 * @param object $subscriber   Subscriber row.
	 * @param string $product_name Product label.
	 * @param string $cust_fallback Localized fallback name when subscriber row
	 *                              has no `customer_name` set.
	 * @return array<string,string>
	 */
	private static function replacements( $subscriber, $product_name, $cust_fallback ) {
		$parent = wc_get_product( $subscriber->product_id );
		return array(
			'{product_name}'    => $product_name,
			'{customer_name}'   => $subscriber->customer_name ?: $cust_fallback,
			'{product_url}'     => $parent ? $parent->get_permalink() : home_url(),
			'{unsubscribe_url}' => add_query_arg( 'rsn_unsubscribe', $subscriber->unsubscribe_token, home_url() ),
			'{shop_url}'        => wc_get_page_permalink( 'shop' ),
			'{site_name}'       => get_bloginfo( 'name' ),
		);
	}

	/**
	 * Send the post-subscription confirmation email.
	 *
	 * Mirrors the legacy `\RSN_Email::send_confirmation()` static signature
	 * so the `class_alias` swap in `Module::boot()` is transparent to existing
	 * callers (`RSN_Ajax::handle_subscribe()`).
	 *
	 * @param object $subscriber Subscriber row.
	 * @return bool wp_mail() return value.
	 */
	public static function send_confirmation( $subscriber ) {
		$product = wc_get_product( $subscriber->variation_id ?: $subscriber->product_id );
		if ( ! $product ) {
			return false;
		}
		$parent = $subscriber->variation_id ? wc_get_product( $subscriber->product_id ) : $product;

		$shell        = Module::defaults();
		$cust_fallback = (string) ( $shell['shell_customer_name_fallback'] ?? '' );

		$pname = self::product_name( $subscriber );
		$r     = self::replacements( $subscriber, $pname, $cust_fallback );

		$subject = str_replace( array_keys( $r ), array_values( $r ), (string) rsn_get_option( 'confirm_subject' ) );
		$heading = str_replace( array_keys( $r ), array_values( $r ), (string) rsn_get_option( 'confirm_heading' ) );
		$body    = str_replace( array_keys( $r ), array_values( $r ), (string) rsn_get_option( 'confirm_body' ) );

		$args = array(
			'heading'         => $heading,
			'body'            => $body,
			'product_name'    => $pname,
			'product_image'   => wp_get_attachment_url( $parent->get_image_id() ),
			'unsubscribe_url' => $r['{unsubscribe_url}'],
			'customer_name'   => $subscriber->customer_name ?: $cust_fallback,
		);

		$html = self::build_html( $args, $subscriber, 'confirmation' );

		return self::send( $subscriber->customer_email, $subject, $html, $subscriber );
	}

	/**
	 * Send the back-in-stock notification email.
	 *
	 * @param object $subscriber Subscriber row.
	 * @return bool wp_mail() return value.
	 */
	public static function send_notification( $subscriber ) {
		$product = wc_get_product( $subscriber->variation_id ?: $subscriber->product_id );
		if ( ! $product ) {
			return false;
		}
		$parent = $subscriber->variation_id ? wc_get_product( $subscriber->product_id ) : $product;

		$shell        = Module::defaults();
		$cust_fallback = (string) ( $shell['shell_customer_name_fallback'] ?? '' );

		$pname = self::product_name( $subscriber );
		$r     = self::replacements( $subscriber, $pname, $cust_fallback );

		$subject = str_replace( array_keys( $r ), array_values( $r ), (string) rsn_get_option( 'notify_subject' ) );
		$heading = str_replace( array_keys( $r ), array_values( $r ), (string) rsn_get_option( 'notify_heading' ) );
		$body    = str_replace( array_keys( $r ), array_values( $r ), (string) rsn_get_option( 'notify_body' ) );
		$btn_txt = str_replace( array_keys( $r ), array_values( $r ), (string) rsn_get_option( 'notify_button_text' ) );

		$args = array(
			'heading'         => $heading,
			'body'            => $body,
			'product_name'    => $pname,
			'product_image'   => wp_get_attachment_url( $parent->get_image_id() ),
			'button_url'      => $parent->get_permalink(),
			'button_text'     => $btn_txt,
			'unsubscribe_url' => $r['{unsubscribe_url}'],
			'customer_name'   => $subscriber->customer_name ?: $cust_fallback,
		);

		$html = self::build_html( $args, $subscriber, 'notification' );

		return self::send( $subscriber->customer_email, $subject, $html, $subscriber );
	}

	/**
	 * Render the email HTML.
	 *
	 * Locale-aware: greeting line, unsubscribe link text, and unsubscribe
	 * suffix come from the active locale's `locales/<locale>.php` file
	 * (Wave 2.3b's bilingual-email fix).
	 *
	 * @param array  $a          Args dictionary (heading, body, product_name, etc.).
	 * @param object $subscriber Subscriber row (passed to listeners).
	 * @param string $kind       'confirmation' or 'notification'.
	 * @return string
	 */
	private static function build_html( $a, $subscriber, $kind ) {
		$shell = Module::defaults();

		$a = wp_parse_args(
			$a,
			array(
				'heading'         => '',
				'body'            => '',
				'product_name'    => '',
				'product_image'   => '',
				'button_url'      => '',
				'button_text'     => '',
				'unsubscribe_url' => '',
				'customer_name'   => (string) ( $shell['shell_customer_name_fallback'] ?? '' ),
			)
		);

		/**
		 * Filter the args dictionary that drives the rendered email HTML.
		 *
		 * @since 1.11.4
		 *
		 * @param array  $a          Args dictionary. Keys: heading, body, product_name,
		 *                           product_image, button_url, button_text,
		 *                           unsubscribe_url, customer_name.
		 * @param object $subscriber Subscriber row.
		 * @param string $kind       'confirmation' or 'notification'.
		 */
		$a = (array) apply_filters( 'freeman_core/restock_notify/email_args', $a, $subscriber, $kind );

		$site = esc_html( get_bloginfo( 'name' ) );

		$img = '';
		if ( $a['product_image'] ) {
			$img = '<div style="text-align:center;margin:24px 0;"><img src="' . esc_url( $a['product_image'] ) . '" alt="' . esc_attr( $a['product_name'] ) . '" style="max-width:200px;height:auto;border-radius:8px;" /></div>';
		}
		$btn = '';
		if ( $a['button_url'] && $a['button_text'] ) {
			$btn = '<div style="text-align:center;margin:32px 0 16px;"><a href="' . esc_url( $a['button_url'] ) . '" style="display:inline-block;background:#000;color:#fff;text-decoration:none;padding:14px 36px;border-radius:6px;font-size:15px;font-weight:600;">' . esc_html( $a['button_text'] ) . '</a></div>';
		}
		$unsub = '';
		if ( $a['unsubscribe_url'] ) {
			$unsub_text   = (string) ( $shell['shell_unsubscribe_link_text'] ?? '' );
			$unsub_suffix = (string) ( $shell['shell_unsubscribe_link_suffix'] ?? '' );
			$unsub        = '<p style="margin:0;font-size:12px;color:#999;"><a href="' . esc_url( $a['unsubscribe_url'] ) . '" style="color:#999;text-decoration:underline;">' . esc_html( $unsub_text ) . '</a> ' . esc_html( $unsub_suffix ) . '</p>';
		}

		$greeting_template = (string) ( $shell['shell_greeting'] ?? 'Hi %s,' );
		// translators: %s = customer name (passed into the locale-defined greeting template).
		$greeting = esc_html( sprintf( $greeting_template, $a['customer_name'] ) );

		return '<!DOCTYPE html><html lang="he" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f7f7f7;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;direction:rtl;text-align:right;">
<div style="max-width:560px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);direction:rtl;text-align:right;">
<div style="padding:36px 40px 0;text-align:center;">
<div style="display:inline-block;width:48px;height:48px;background:#000;border-radius:50%;text-align:center;line-height:48px;margin-bottom:20px;"><span style="color:#fff;font-size:20px;">&#128276;</span></div>
<h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#111;">' . wp_kses_post( $a['heading'] ) . '</h1>
</div>
<div style="padding:16px 40px 24px;text-align:center;">
<p style="margin:0 0 8px;font-size:15px;line-height:1.6;color:#444;">' . $greeting . '</p>
<p style="margin:0;font-size:15px;line-height:1.6;color:#444;">' . wp_kses_post( $a['body'] ) . '</p>
</div>
' . $img . $btn . '
<div style="padding:24px 40px 32px;border-top:1px solid #eee;text-align:center;">
<p style="margin:0 0 6px;font-size:12px;color:#999;">' . $site . '</p>
' . $unsub . '
</div></div></body></html>';
	}

	/**
	 * Dispatch via wp_mail. Strips header-injection chars from the from-name.
	 *
	 * @param string $to         Recipient email.
	 * @param string $subject    Email subject (already substituted).
	 * @param string $html       Email body (already rendered).
	 * @param object $subscriber Subscriber row (passed to before_send listeners).
	 * @return bool wp_mail return.
	 */
	private static function send( $to, $subject, $html, $subscriber ) {
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

		/**
		 * Fires immediately before the email leaves via wp_mail().
		 *
		 * Use cases: route to a transactional ESP, log the send, gate by
		 * subscriber attribute. Returning early or throwing will prevent
		 * `wp_mail()` from running below — listeners that wish to suppress
		 * the send should use `wp_mail` filters / `pre_wp_mail` instead;
		 * this hook is informational by design.
		 *
		 * @since 1.11.4
		 *
		 * @param string $to         Recipient email.
		 * @param string $subject    Subject (already substituted).
		 * @param string $html       Rendered HTML body.
		 * @param object $subscriber Subscriber row.
		 */
		do_action( 'freeman_core/restock_notify/before_send', $to, $subject, $html, $subscriber );

		return wp_mail(
			$to,
			$subject,
			$html,
			array(
				'Content-Type: text/html; charset=UTF-8',
				sprintf( 'From: %s <%s>', $fn, $fe ),
			)
		);
	}
}
