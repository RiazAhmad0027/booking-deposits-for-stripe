<?php
namespace ClinicBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All database work for one booking lives here, so every part of the plugin
 * (return page, webhook, admin page) uses the same rules.
 */
class Booking {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cb_bookings';
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id )
		);
	}

	public static function find_by_session( $session_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE stripe_session_id = %s', $session_id )
		);
	}

	/**
	 * Confirm a booking. The return page AND the webhook may both call this,
	 * so the UPDATE only changes a row that is not confirmed yet.
	 * Only the call that really changed the row sends the emails (no double emails).
	 */
	public static function confirm( $id ) {
		global $wpdb;
		$changed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . " SET status = 'confirmed' WHERE id = %d AND status <> 'confirmed'",
				$id
			)
		);

		if ( $changed ) {
			Emails::booking_confirmed( self::get( $id ) );
		}
		return true;
	}

	/**
	 * Cancel a booking (used by the admin page and when a Stripe page expires).
	 * $only_pending = true means: do not touch a booking that is already paid.
	 */
	public static function cancel( $id, $only_pending = false ) {
		global $wpdb;
		$sql = 'UPDATE ' . self::table() . " SET status = 'cancelled' WHERE id = %d AND status <> 'cancelled'";
		if ( $only_pending ) {
			$sql .= " AND status = 'pending'";
		}
		return (bool) $wpdb->query( $wpdb->prepare( $sql, $id ) );
	}
}
