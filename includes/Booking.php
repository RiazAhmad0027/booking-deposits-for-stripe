<?php
namespace BookingDepositsForStripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This class works with the plugin's own table, so it uses direct queries.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * All database work for bookings lives here, so the return page, the webhook
 * and the admin page all follow the same rules.
 */
class Booking {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bdfs_bookings';
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), $id ) );
	}

	public static function find_by_session( $session_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE stripe_session_id = %s', self::table(), $session_id ) );
	}

	public static function latest( $limit = 200 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY booking_date DESC, booking_time ASC LIMIT %d', self::table(), $limit )
		);
	}

	/**
	 * A slot is taken if it is confirmed, or pending (unpaid) but still inside the hold time.
	 */
	public static function slot_taken( $date, $time ) {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i
				 WHERE booking_date = %s AND booking_time = %s
				 AND ( status = 'confirmed'
				       OR ( status = 'pending' AND created_at > DATE_SUB( NOW(), INTERVAL %d MINUTE ) ) )",
				self::table(),
				$date,
				$time,
				Settings::hold_minutes()
			)
		);
		return $count > 0;
	}

	public static function create( $data ) {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	public static function set_session( $id, $session_id ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'stripe_session_id' => $session_id ), array( 'id' => $id ) );
	}

	/**
	 * Confirm a booking. The return page AND the webhook may both call this,
	 * so the UPDATE only changes a row that is not confirmed yet.
	 * Only the call that really changed the row sends the emails (no double emails).
	 */
	public static function confirm( $id ) {
		global $wpdb;
		$changed = $wpdb->query(
			$wpdb->prepare( "UPDATE %i SET status = 'confirmed' WHERE id = %d AND status <> 'confirmed'", self::table(), $id )
		);
		if ( $changed ) {
			Emails::booking_confirmed( self::get( $id ) );
		}
		return true;
	}

	/**
	 * Cancel a booking. $only_pending = true never touches a paid booking.
	 */
	public static function cancel( $id, $only_pending = false ) {
		global $wpdb;
		if ( $only_pending ) {
			return (bool) $wpdb->query(
				$wpdb->prepare( "UPDATE %i SET status = 'cancelled' WHERE id = %d AND status = 'pending'", self::table(), $id )
			);
		}
		return (bool) $wpdb->query(
			$wpdb->prepare( "UPDATE %i SET status = 'cancelled' WHERE id = %d AND status <> 'cancelled'", self::table(), $id )
		);
	}
}
