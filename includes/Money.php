<?php
namespace BookingDepositsForStripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe works with the smallest unit of a currency (cents, paisa).
 * Some currencies have no decimals (for example JPY), so they are not multiplied by 100.
 */
class Money {

	const ZERO_DECIMAL = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

	public static function is_zero_decimal( $currency ) {
		return in_array( strtoupper( $currency ), self::ZERO_DECIMAL, true );
	}

	public static function to_minor( $amount, $currency ) {
		return self::is_zero_decimal( $currency ) ? (int) round( $amount ) : (int) round( $amount * 100 );
	}

	public static function format( $minor, $currency ) {
		$decimals = self::is_zero_decimal( $currency ) ? 0 : 2;
		$value    = $decimals ? $minor / 100 : $minor;
		return strtoupper( $currency ) . ' ' . number_format_i18n( $value, $decimals );
	}
}
