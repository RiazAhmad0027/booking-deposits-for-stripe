<?php
namespace ClinicBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingForm {

	// Clinic time slots (24-hour clock). Change these to change the slots.
	const SLOTS = array( '10:00', '11:00', '12:00', '14:00', '15:00', '16:00' );

	// Money settings (Pakistani Rupees).
	const FEE             = 2500; // Full consultation fee.
	const DEPOSIT_PERCENT = 20;   // Customer pays this % now.
	const CURRENCY        = 'pkr';

	// An unpaid (pending) booking holds the slot for this many minutes.
	const HOLD_MINUTES = 35;

	public function __construct() {
		add_shortcode( 'clinic_booking', array( $this, 'render' ) );
		add_action( 'admin_post_cb_book', array( $this, 'handle' ) );        // logged-in users
		add_action( 'admin_post_nopriv_cb_book', array( $this, 'handle' ) ); // visitors
	}

	public static function deposit() {
		return (int) round( self::FEE * self::DEPOSIT_PERCENT / 100 );
	}

	/**
	 * Show the booking form: [clinic_booking]
	 */
	public function render() {
		$code = isset( $_GET['cb'] ) ? sanitize_key( $_GET['cb'] ) : '';

		// Customer came back from Stripe: check the payment is real.
		if ( 'paid' === $code ) {
			$code = $this->confirm_from_stripe() ? 'paid' : 'notpaid';
		}

		$messages = array(
			'paid'      => 'Thank you! Your deposit is paid and your booking is confirmed.',
			'notpaid'   => 'We could not confirm your payment. Please contact us.',
			'cancelled' => 'Payment cancelled. Your booking is not confirmed.',
			'taken'     => 'Sorry, this time is already booked. Please choose another time.',
			'invalid'   => 'Please fill in all fields correctly.',
			'payerror'  => 'Could not start the payment. Please try again later.',
		);

		ob_start();

		if ( isset( $messages[ $code ] ) ) {
			echo '<p class="cb-message"><strong>' . esc_html( $messages[ $code ] ) . '</strong></p>';
		}
		?>
		<p>
			Consultation fee: Rs <?php echo esc_html( number_format( self::FEE ) ); ?>.
			Pay a <?php echo esc_html( self::DEPOSIT_PERCENT ); ?>% deposit now:
			<strong>Rs <?php echo esc_html( number_format( self::deposit() ) ); ?></strong>.
		</p>

		<form class="cb-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cb_book">
			<input type="hidden" name="cb_page" value="<?php echo esc_url( get_permalink() ); ?>">
			<?php wp_nonce_field( 'cb_book', 'cb_nonce' ); ?>

			<p><label>Name<br><input type="text" name="cb_name" required></label></p>
			<p><label>Email<br><input type="email" name="cb_email" required></label></p>
			<p><label>Phone<br><input type="text" name="cb_phone"></label></p>
			<p><label>Date<br><input type="date" name="cb_date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" required></label></p>
			<p><label>Time<br>
				<select name="cb_time" required>
					<?php foreach ( self::SLOTS as $slot ) : ?>
						<option value="<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( $slot ); ?></option>
					<?php endforeach; ?>
				</select>
			</label></p>

			<p><button type="submit">Book and pay deposit</button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save the booking, then send the customer to Stripe to pay the deposit.
	 */
	public function handle() {
		// The full page address (https://...). Stripe needs a full URL, not "/book-appointment/".
		// wp_validate_redirect() only allows addresses on this same website.
		$page = isset( $_POST['cb_page'] ) ? esc_url_raw( wp_unslash( $_POST['cb_page'] ) ) : '';
		$back = wp_validate_redirect( $page, home_url( '/' ) );
		$back = remove_query_arg( array( 'cb', 'cb_session' ), $back );

		// 1. Security: check the nonce.
		if ( ! isset( $_POST['cb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cb_nonce'] ) ), 'cb_book' ) ) {
			wp_die( 'Security check failed. Please go back and try again.' );
		}

		// 2. Clean every field.
		$name  = sanitize_text_field( wp_unslash( $_POST['cb_name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['cb_email'] ?? '' ) );
		$phone = sanitize_text_field( wp_unslash( $_POST['cb_phone'] ?? '' ) );
		$date  = sanitize_text_field( wp_unslash( $_POST['cb_date'] ?? '' ) );
		$time  = sanitize_text_field( wp_unslash( $_POST['cb_time'] ?? '' ) );

		// 3. Check the values are valid.
		$d       = \DateTime::createFromFormat( 'Y-m-d', $date );
		$date_ok = $d && $d->format( 'Y-m-d' ) === $date && $date >= wp_date( 'Y-m-d' );
		$time_ok = in_array( $time, self::SLOTS, true );

		if ( ! $name || ! is_email( $email ) || ! $date_ok || ! $time_ok ) {
			wp_safe_redirect( add_query_arg( 'cb', 'invalid', $back ) );
			exit;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'cb_bookings';

		// 4. Stop double booking. A slot is taken if it is confirmed,
		//    or pending (unpaid) but created in the last HOLD_MINUTES minutes.
		$taken = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table
				 WHERE booking_date = %s AND booking_time = %s
				 AND ( status = 'confirmed'
				       OR ( status = 'pending' AND created_at > DATE_SUB( NOW(), INTERVAL %d MINUTE ) ) )",
				$date,
				$time,
				self::HOLD_MINUTES
			)
		);

		if ( $taken > 0 ) {
			wp_safe_redirect( add_query_arg( 'cb', 'taken', $back ) );
			exit;
		}

		// 5. Save the booking as pending.
		$wpdb->insert(
			$table,
			array(
				'name'           => $name,
				'email'          => $email,
				'phone'          => $phone,
				'booking_date'   => $date,
				'booking_time'   => $time,
				'status'         => 'pending',
				'deposit_amount' => self::deposit(),
			)
		);
		$booking_id = (int) $wpdb->insert_id;

		// 6. Create a Stripe Checkout page for the deposit.
		$success = add_query_arg( 'cb', 'paid', $back ) . '&cb_session={CHECKOUT_SESSION_ID}';

		$session = StripeApi::post(
			'checkout/sessions',
			array(
				'mode'                 => 'payment',
				'customer_email'       => $email,
				'success_url'          => $success,
				'cancel_url'           => add_query_arg( 'cb', 'cancelled', $back ),
				'expires_at'           => time() + 31 * 60, // Stripe minimum is 30 minutes.
				'metadata'             => array( 'booking_id' => $booking_id ),
				'line_items'           => array(
					array(
						'quantity'   => 1,
						'price_data' => array(
							'currency'     => self::CURRENCY,
							'unit_amount'  => self::deposit() * 100, // Stripe uses the smallest unit.
							'product_data' => array(
								'name' => 'Appointment deposit - ' . $date . ' at ' . $time,
							),
						),
					),
				),
			)
		);

		if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
			error_log( 'Clinic Booking: ' . ( is_wp_error( $session ) ? $session->get_error_message() : 'no checkout url' ) );
			$wpdb->update( $table, array( 'status' => 'cancelled' ), array( 'id' => $booking_id ) );
			wp_safe_redirect( add_query_arg( 'cb', 'payerror', $back ) );
			exit;
		}

		// 7. Remember the Stripe session, then send the customer to Stripe.
		$wpdb->update( $table, array( 'stripe_session_id' => $session['id'] ), array( 'id' => $booking_id ) );

		wp_redirect( $session['url'] ); // Stripe is an outside site, so not wp_safe_redirect.
		exit;
	}

	/**
	 * Ask Stripe if this Checkout session is really paid. If yes, confirm the booking.
	 */
	private function confirm_from_stripe() {
		$session_id = isset( $_GET['cb_session'] ) ? sanitize_text_field( wp_unslash( $_GET['cb_session'] ) ) : '';

		if ( ! preg_match( '/^cs_(test|live)_[A-Za-z0-9]+$/', $session_id ) ) {
			return false;
		}

		$booking = Booking::find_by_session( $session_id );

		if ( ! $booking ) {
			return false;
		}
		if ( 'confirmed' === $booking->status ) {
			return true; // Already confirmed (page refreshed, or the webhook was faster).
		}

		// Never trust the URL alone: ask Stripe.
		$session = StripeApi::get( 'checkout/sessions/' . rawurlencode( $session_id ) );

		if ( is_wp_error( $session ) || ( $session['payment_status'] ?? '' ) !== 'paid' ) {
			return false;
		}
		if ( (int) ( $session['metadata']['booking_id'] ?? 0 ) !== (int) $booking->id ) {
			return false;
		}

		return Booking::confirm( $booking->id ); // Also sends the emails.
	}
}
