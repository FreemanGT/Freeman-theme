<?php
/**
 * Modern subscribers repository for RestockNotify.
 *
 * Thin static wrapper around the legacy `\RSN_Database` class. Exists so
 * Wave 2.3b (modern `Stock_Monitor` + `Email`) and Wave 2.3c (modern
 * `Frontend`) can call against a `Freeman\Core\Modules\RestockNotify\…`
 * surface instead of a global `RSN_Database::` static — without editing
 * `legacy/` (forbidden by CLAUDE.md hard rule #3).
 *
 * No callers in 2.3a. The wrapper is intentional groundwork; it becomes
 * canonical when 2.3b/c land. The legacy class continues to exist
 * unchanged in `legacy/includes/class-rsn-database.php` and is what every
 * method here delegates into.
 *
 * Surface intentionally minimal: only the four operations Wave 2.3b and
 * 2.3c will need. The other ten `RSN_Database::*` methods stay called
 * directly from `legacy/` (`RSN_Ajax`, `RSN_Admin`) since 2.3d is skipped
 * — wrapping them speculatively would violate §2 (Simplicity First).
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\RestockNotify;

defined( 'ABSPATH' ) || exit;

/**
 * Subscribers repository (read/notification surface).
 */
final class Subscribers {

	/**
	 * Subscriptions still in `'waiting'` status for a given product.
	 *
	 * @since 1.11.3
	 *
	 * @param int $product_id   Parent product id.
	 * @param int $variation_id Specific variation, or 0 for the parent-level subscription.
	 * @return object[] Row objects from `{prefix}rsn_subscribers`.
	 */
	public static function get_waiting_for_product( $product_id, $variation_id = 0 ) {
		return \RSN_Database::get_waiting_for_product( $product_id, $variation_id );
	}

	/**
	 * Mark a subscription as notified. Sets `status='notified'` and `notified_at=now()`.
	 *
	 * @since 1.11.3
	 *
	 * @param int $id Subscription row id.
	 * @return int|false Rows affected, or false on error.
	 */
	public static function mark_notified( $id ) {
		return \RSN_Database::mark_notified( $id );
	}

	/**
	 * Look up a subscription by its unsubscribe token.
	 *
	 * @since 1.11.3
	 *
	 * @param string $token Unsubscribe token (32 hex chars).
	 * @return object|null Row object or null if not found.
	 */
	public static function get_by_token( $token ) {
		return \RSN_Database::get_by_token( $token );
	}

	/**
	 * Mark a subscription as unsubscribed (status='unsubscribed').
	 *
	 * @since 1.11.3
	 *
	 * @param int $id Subscription row id.
	 * @return int|false Rows affected, or false on error.
	 */
	public static function unsubscribe( $id ) {
		return \RSN_Database::unsubscribe( $id );
	}
}
