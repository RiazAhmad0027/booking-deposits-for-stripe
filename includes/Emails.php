<?php
namespace BookingDepositsForStripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emails {

	public static function booking_confirmed( $booking ) {
		if ( ! $booking ) {
			return;
		}

		$site      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$date      = wp_date( get_option( 'date_format' ), strtotime( $booking->booking_date ) );
		$deposit   = Money::format( (int) $booking->deposit_minor, $booking->currency );
		$remaining = Money::format( max( 0, (int) $booking->fee_minor - (int) $booking->deposit_minor ), $booking->currency );

		// 1. Email to the customer.
		/* translators: %s: site name. */
		$subject = sprintf( __( 'Your appointment is confirmed - %s', 'booking-deposits-for-stripe' ), $site );

		$lines = array(
			/* translators: %s: customer name. */
			sprintf( __( 'Hello %s,', 'booking-deposits-for-stripe' ), $booking->name ),
			'',
			__( 'Thank you. Your appointment is confirmed.', 'booking-deposits-for-stripe' ),
			'',
			/* translators: %s: date. */
			sprintf( __( 'Date: %s', 'booking-deposits-for-stripe' ), $date ),
			/* translators: %s: time, for example 10:00. */
			sprintf( __( 'Time: %s', 'booking-deposits-for-stripe' ), $booking->booking_time ),
			/* translators: %s: amount. */
			sprintf( __( 'Deposit paid: %s', 'booking-deposits-for-stripe' ), $deposit ),
			/* translators: %s: amount. */
			sprintf( __( 'Left to pay at your visit: %s', 'booking-deposits-for-stripe' ), $remaining ),
			'',
			/* translators: %d: booking number. */
			sprintf( __( 'Booking number: #%d', 'booking-deposits-for-stripe' ), $booking->id ),
			'',
			$site,
		);

		wp_mail( $booking->email, $subject, implode( "\n", $lines ) );

		// 2. Email to the site owner.
		/* translators: %d: booking number. */
		$admin_subject = sprintf( __( 'New confirmed booking #%d', 'booking-deposits-for-stripe' ), $booking->id );

		$admin_lines = array(
			/* translators: %s: customer name. */
			sprintf( __( 'Name: %s', 'booking-deposits-for-stripe' ), $booking->name ),
			/* translators: %s: customer email. */
			sprintf( __( 'Email: %s', 'booking-deposits-for-stripe' ), $booking->email ),
			/* translators: %s: customer phone. */
			sprintf( __( 'Phone: %s', 'booking-deposits-for-stripe' ), $booking->phone ),
			/* translators: %s: date. */
			sprintf( __( 'Date: %s', 'booking-deposits-for-stripe' ), $date ),
			/* translators: %s: time. */
			sprintf( __( 'Time: %s', 'booking-deposits-for-stripe' ), $booking->booking_time ),
			/* translators: %s: amount. */
			sprintf( __( 'Deposit paid: %s', 'booking-deposits-for-stripe' ), $deposit ),
			'',
			admin_url( 'admin.php?page=bdfs-bookings' ),
		);

		wp_mail( get_option( 'admin_email' ), $admin_subject, implode( "\n", $admin_lines ) );
	}
}
