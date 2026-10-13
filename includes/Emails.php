<?php
namespace ClinicBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emails {

	public static function booking_confirmed( $booking ) {
		if ( ! $booking ) {
			return;
		}

		$site      = get_bloginfo( 'name' );
		$date      = wp_date( 'l, j F Y', strtotime( $booking->booking_date ) );
		$remaining = BookingForm::FEE - (int) $booking->deposit_amount;

		// 1. Email to the customer.
		$subject = 'Your appointment is confirmed - ' . $site;
		$message = "Hello {$booking->name},\n\n"
			. "Thank you. Your appointment is confirmed.\n\n"
			. "Date: {$date}\n"
			. "Time: {$booking->booking_time}\n"
			. 'Deposit paid: Rs ' . number_format( $booking->deposit_amount ) . "\n"
			. 'Pay at the clinic: Rs ' . number_format( $remaining ) . "\n\n"
			. "Booking number: #{$booking->id}\n\n"
			. "See you soon,\n{$site}";

		wp_mail( $booking->email, $subject, $message );

		// 2. Email to the site owner.
		$admin_subject = "New confirmed booking #{$booking->id}";
		$admin_message = "Name: {$booking->name}\n"
			. "Email: {$booking->email}\n"
			. "Phone: {$booking->phone}\n"
			. "Date: {$date}\n"
			. "Time: {$booking->booking_time}\n"
			. 'Deposit paid: Rs ' . number_format( $booking->deposit_amount ) . "\n\n"
			. 'All bookings: ' . admin_url( 'admin.php?page=cb-bookings' );

		wp_mail( get_option( 'admin_email' ), $admin_subject, $admin_message );
	}
}
