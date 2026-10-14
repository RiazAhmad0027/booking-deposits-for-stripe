<?php
namespace BookingDepositsForStripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Very small helper to talk to the Stripe API (https://api.stripe.com).
 */
class StripeApi {

	private static function result( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Stripe error ' . $code;
			return new \WP_Error( 'bdfs_stripe_error', $msg );
		}
		return $body;
	}

	private static function args() {
		return array(
			'headers' => array( 'Authorization' => 'Bearer ' . Settings::secret_key() ),
			'timeout' => 30,
		);
	}

	public static function post( $endpoint, $params ) {
		if ( ! Settings::is_ready() ) {
			return new \WP_Error( 'bdfs_no_key', 'Stripe secret key is missing.' );
		}
		$args         = self::args();
		$args['body'] = $params;
		return self::result( wp_remote_post( 'https://api.stripe.com/v1/' . $endpoint, $args ) );
	}

	public static function get( $endpoint ) {
		if ( ! Settings::is_ready() ) {
			return new \WP_Error( 'bdfs_no_key', 'Stripe secret key is missing.' );
		}
		return self::result( wp_remote_get( 'https://api.stripe.com/v1/' . $endpoint, self::args() ) );
	}
}
