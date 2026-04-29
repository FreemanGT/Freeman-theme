<?php
/**
 * English (en_US) defaults for RestockNotify options.
 *
 * Loaded by Module::defaults() when get_locale() returns en_US or any
 * locale without a matching `locales/<locale>.php` file (en_US is the
 * fallback). Strings are stored as literals (not wrapped in __()) because
 * this file IS the source of the per-locale defaults.
 *
 * Keep keys aligned with locales/he_IL.php and Module::defaults()'s contract.
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'auto_inject'            => 'yes',
	'form_heading'           => 'Notify me when back in stock',
	'form_description'       => "Leave your details and we'll email you the moment this product is back in stock.",
	'form_button_text'       => 'Subscribe',
	'form_success_message'   => "Subscribed! We'll email you when this product is back in stock.",
	'form_duplicate_message' => "You're already on the waiting list for this product.",
	'enable_confirmation'    => 'yes',
	'enable_gdpr'            => 'no',
	'gdpr_text'              => 'I agree to receive email notifications about this product.',
	'confirm_subject'        => "You're on the waiting list!",
	'confirm_heading'        => "We'll let you know",
	'confirm_body'           => "You're on the waiting list for <strong>{product_name}</strong>. We'll email you the moment it's back in stock.",
	'notify_subject'         => 'Good news - {product_name} is back in stock!',
	'notify_heading'         => "It's back!",
	'notify_body'            => '<strong>{product_name}</strong> is back in stock and waiting for you. Better grab it before it sells out again!',
	'notify_button_text'     => 'Buy now',
	'from_name'              => '',
	'from_email'             => '',

	// Email-shell strings — consumed directly by Modern \Freeman\Core\Modules\
	// RestockNotify\Email; NOT seeded into rsn_* options. See Module::OPTION_KEYS
	// for the seeded subset.
	'shell_customer_name_fallback' => 'Customer',
	'shell_greeting'               => 'Hi %s,',
	'shell_unsubscribe_link_text'  => 'Unsubscribe',
	'shell_unsubscribe_link_suffix' => 'for this product.',
);
