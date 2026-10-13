<?php
namespace ClinicBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stripe webhook: Stripe tells us about payments even if the customer
 * closes the browser before coming back to our site.
 *
 * Address: /wp-json/clinic-booking/v1/stripe-webhook
 * Secret in wp-config.php: define( 'CB_STRIPE_WEBHOOK_SECRET', 'whsec_...' );
 */
class Webhook {

	const TOLERANCE = 300; // Seconds. Reject old (replayed) events.

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'route' ) );
	}

	public function route() {
		register_rest_route(
			'clinic-booking/v1',
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true', // Security = the signature check below.
			)
		);
	}

	public function handle( \WP_REST_Request $request ) {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'stripe_signature' );

		if ( ! $this->verify( $payload, $signature ) ) {
			error_log( 'Clinic Booking webhook: bad signature' );
			return new \WP_REST_Response( array( 'error' => 'bad signature' ), 400 );
		}

		$event   = json_decode( $payload, true );
		$type    = $event['type'] ?? '';
		$session = $event['data']['object'] ?? array();

		error_log( 'Clinic Booking webhook: ' . $type );

		$booking = isset( $session['id'] ) ? Booking::find_by_session( $session['id'] ) : null;

		// Extra check: the booking number saved in Stripe must match our row.
		if ( $booking && (int) ( $session['metadata']['booking_id'] ?? 0 ) !== (int) $booking->id ) {
			$booking = null;
		}

		if ( $booking ) {
			if ( 'checkout.session.completed' === $type && 'paid' === ( $session['payment_status'] ?? '' ) ) {
				Booking::confirm( $booking->id );
				error_log( 'Clinic Booking webhook: booking #' . $booking->id . ' confirmed' );
			}

			if ( 'checkout.session.expired' === $type ) {
				Booking::cancel( $booking->id, true ); // Only if still unpaid.
				error_log( 'Clinic Booking webhook: booking #' . $booking->id . ' expired' );
			}
		}

		// Always answer 200 for valid events, so Stripe does not keep retrying.
		return new \WP_REST_Response( array( 'received' => true ), 200 );
	}

	private function verify( $payload, $header ) {
		if ( ! defined( 'CB_STRIPE_WEBHOOK_SECRET' ) || ! $header ) {
			return false;
		}

		$timestamp  = 0;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( count( $pair ) !== 2 ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( ! $timestamp || abs( time() - $timestamp ) > self::TOLERANCE ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, CB_STRIPE_WEBHOOK_SECRET );

		foreach ( $signatures as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				return true;
			}
		}
		return false;
	}
}
