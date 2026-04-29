<?php
/**
 * Hebrew (he_IL) defaults for RestockNotify options.
 *
 * Loaded by Module::defaults() when get_locale() === 'he_IL'. Strings are
 * stored as literals (not wrapped in __()) because this file IS the source
 * of the per-locale defaults — it's data, not translatable copy.
 *
 * Keep keys aligned with locales/en_US.php and Module::defaults()'s contract.
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

return array(
	'auto_inject'            => 'yes',
	'form_heading'           => 'עדכנו אותי כשיחזור למלאי',
	'form_description'       => 'השאירו את הפרטים שלכם ונעדכן אתכם ברגע שהמוצר יחזור למלאי.',
	'form_button_text'       => 'הרשמה לעדכון',
	'form_success_message'   => 'נרשמת בהצלחה! נשלח לך מייל כשהמוצר יחזור למלאי.',
	'form_duplicate_message' => 'כבר נרשמת לקבלת עדכון על מוצר זה.',
	'enable_confirmation'    => 'yes',
	'enable_gdpr'            => 'no',
	'gdpr_text'              => 'אני מסכים/ה לקבל התראות במייל על מוצר זה.',
	'confirm_subject'        => 'נרשמת לרשימת ההמתנה!',
	'confirm_heading'        => 'נעדכן אותך',
	'confirm_body'           => 'נרשמת לרשימת ההמתנה עבור <strong>{product_name}</strong>. נשלח לך מייל ברגע שהמוצר יחזור למלאי.',
	'notify_subject'         => 'חדשות טובות — {product_name} חזר למלאי!',
	'notify_heading'         => 'המוצר חזר!',
	'notify_body'            => '<strong>{product_name}</strong> חזר למלאי ומחכה לך. כדאי לתפוס לפני שייגמר שוב!',
	'notify_button_text'     => 'לרכישה',
	'from_name'              => '',
	'from_email'             => '',

	// Email-shell strings — consumed directly by Modern \Freeman\Core\Modules\
	// RestockNotify\Email; NOT seeded into rsn_* options. See Module::OPTION_KEYS
	// for the seeded subset. Pre-1.11.4 these were hardcoded literals inside
	// legacy/includes/class-rsn-email.php — modern Email reads them from here.
	'shell_customer_name_fallback' => 'לקוח/ה',
	'shell_greeting'               => 'היי %s,',
	'shell_unsubscribe_link_text'  => 'הסרה מרשימת התפוצה',
	'shell_unsubscribe_link_suffix' => 'עבור מוצר זה.',
);
