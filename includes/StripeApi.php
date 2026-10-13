<?php
namespace ClinicBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Very small helper to talk to the Stripe API.
 * The secret key lives in wp-config.php:  define( 'CB_STRIPE_SECRET_KEY', 'sk_test_...' );
 */
class StripeApi {

	private static function key() {
		return defined( 'CB_STRIPE_SECRET_KEY' ) ? CB_STRIPE_SECRET_KEY : '';
	}

	private static function result( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Stripe error ' . $code;
			return new \WP_Error( 'stripe_error', $msg );
		}
		return $body;
	}

	public static function post( $endpoint, $params ) {
		if ( ! self::key() ) {
			return new \WP_Error( 'no_key', 'Stripe key is missing in wp-config.php' );
		}
		return self::result(
			wp_remote_post(
				'https://api.stripe.com/v1/' . $endpoint,
				array(
					'headers' => array( 'Authorization' => 'Bearer ' . self::key() ),
					'body'    => $params,
					'timeout' => 30,
				)
			)
		);
	}

	public static function get( $endpoint ) {
		if ( ! self::key() ) {
			return new \WP_Error( 'no_key', 'Stripe key is missing in wp-config.php' );
		}
		return self::result(
			wp_remote_get(
				'https://api.stripe.com/v1/' . $endpoint,
				array(
					'headers' => array( 'Authorization' => 'Bearer ' . self::key() ),
					'timeout' => 30,
				)
			)
		);
	}
}
