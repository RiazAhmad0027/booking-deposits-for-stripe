<?php
namespace BookingDepositsForStripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes to the PHP error log only when WP_DEBUG is on.
 */
class Log {
	public static function write( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Booking Deposits for Stripe: ' . $message );
		}
	}
}
