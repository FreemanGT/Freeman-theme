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
 * Wave 4.1a adds two methods (`find_by_email`, `erase_pii_by_email`) that
 * deviate from the thin-wrapper pattern by querying `$wpdb` directly. The
 * deviation is forced: WP_Privacy needs exact-email lookup and PII null
 * semantics that no existing `\RSN_Database::*` method provides, and Hard
 * Rule #3 forbids adding methods to the legacy class.
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
	 * Non-PII domain used when tombstoning erased subscriber emails.
	 */
	private const ERASED_EMAIL_DOMAIN = 'freeman.invalid';

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

	/**
	 * Find all subscriptions for an exact email address. Used by the
	 * Wave 4.1a Privacy exporter.
	 *
	 * Queries `$wpdb` directly rather than delegating to `\RSN_Database`
	 * because no legacy method offers exact-email lookup and Hard Rule #3
	 * blocks extending the legacy class.
	 *
	 * Empty-string guard: returns `[]` early when `$email === ''` so that
	 * a stray empty input cannot match any historically-erased rows from
	 * versions that used an empty-string tombstone.
	 *
	 * @since 1.11.37
	 *
	 * @param string $email Customer email (exact match).
	 * @return object[] Row objects from `{prefix}rsn_subscribers`.
	 */
	public static function find_by_email( $email ) {
		$email = (string) $email;
		if ( '' === $email ) {
			return array();
		}
		global $wpdb;
		$table = $wpdb->prefix . 'rsn_subscribers';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE customer_email = %s",
				$email
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Erase PII for all subscriptions matching an exact email address.
	 * Used by the Wave 4.1a Privacy eraser.
	 *
	 * Sets `customer_name=''`, `customer_email='erased-{id}@freeman.invalid'`,
	 * and `status='unsubscribed'`. The legacy schema declares
	 * `customer_email` as NOT NULL and has a unique key across
	 * `(customer_email, product_id, variation_id, status)`, so each erased
	 * row needs a unique non-PII tombstone value instead of a shared empty
	 * string.
	 *
	 * The row is preserved (not DELETEd) so the stock monitor's audit
	 * trail stays intact; the empty `customer_email` prevents future
	 * email matches.
	 *
	 * Empty-string guard: returns `0` early when `$email === ''` so a
	 * stray empty input cannot "erase" rows that were already erased.
	 *
	 * @since 1.11.37
	 *
	 * @param string $email Customer email (exact match).
	 * @return int|false Rows affected, or false when any matched row fails to update.
	 */
	public static function erase_pii_by_email( $email ) {
		$email = (string) $email;
		if ( '' === $email ) {
			return 0;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'rsn_subscribers';
		$rows  = self::find_by_email( $email );
		if ( empty( $rows ) ) {
			return 0;
		}

		$updated = 0;
		foreach ( $rows as $row ) {
			$row_id = (int) ( $row->id ?? 0 );
			if ( $row_id <= 0 ) {
				return false;
			}

			$result = $wpdb->update(
				$table,
				array(
					'customer_name'  => '',
					'customer_email' => self::erased_email_for_id( $row_id ),
					'status'         => 'unsubscribed',
				),
				array( 'id' => $row_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $result || 1 !== (int) $result ) {
				return false;
			}
			$updated += (int) $result;
		}

		return $updated;
	}

	/**
	 * Build a stable non-PII tombstone email that cannot collide with another row.
	 *
	 * @param int $id Subscriber row id.
	 * @return string
	 */
	private static function erased_email_for_id( $id ) {
		return 'erased-' . (int) $id . '@' . self::ERASED_EMAIL_DOMAIN;
	}

	/**
	 * Return every row from the subscribers table. Used by the Wave 4.1b
	 * CSV export.
	 *
	 * Queries `$wpdb` directly per the Hard Rule #3 precedent established
	 * by `find_by_email` / `erase_pii_by_email` — no legacy method offers
	 * an unfiltered full-table read, and the legacy class is off-limits.
	 *
	 * **Scale assumption:** returns the full table as an array. Intended
	 * for admin export at expected merchant scale (`rsn_subscribers` is
	 * one row per (email, product, variation, waiting/notified) tuple —
	 * a few thousand on busy stores, low tens of thousands at the outer
	 * end). A streaming `each(callable)` variant is deferred until a
	 * merchant actually hits the wall; swapping then is a follow-up
	 * wave, not a 4.1b concern.
	 *
	 * @since 1.11.38
	 *
	 * @return object[] All rows from `{prefix}rsn_subscribers`.
	 */
	public static function all() {
		global $wpdb;
		$table = $wpdb->prefix . 'rsn_subscribers';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table}" );
		return is_array( $rows ) ? $rows : array();
	}
}
