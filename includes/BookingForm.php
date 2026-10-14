<?php
namespace BookingDepositsForStripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingForm {

	public function __construct() {
		add_shortcode( 'bdfs_booking', array( $this, 'render' ) );
		add_action( 'admin_post_bdfs_book', array( $this, 'handle' ) );        // Logged-in users.
		add_action( 'admin_post_nopriv_bdfs_book', array( $this, 'handle' ) ); // Visitors.
	}

	/**
	 * Show the booking form: [bdfs_booking]
	 */
	public function render() {
		if ( ! Settings::is_ready() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p><strong>' . esc_html__( 'Booking Deposits for Stripe: add your Stripe secret key in Bookings → Settings.', 'booking-deposits-for-stripe' ) . '</strong></p>';
			}
			return '<p>' . esc_html__( 'Online booking is not available yet.', 'booking-deposits-for-stripe' ) . '</p>';
		}

		// Only a message code from our own redirects; no data is changed by reading it.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = isset( $_GET['bdfs'] ) ? sanitize_key( wp_unslash( $_GET['bdfs'] ) ) : '';

		// Customer came back from Stripe: check the payment is real.
		if ( 'paid' === $code ) {
			$code = $this->confirm_from_stripe() ? 'paid' : 'notpaid';
		}

		$messages = array(
			'paid'      => __( 'Thank you! Your deposit is paid and your booking is confirmed.', 'booking-deposits-for-stripe' ),
			'notpaid'   => __( 'We could not confirm your payment. Please contact us.', 'booking-deposits-for-stripe' ),
			'cancelled' => __( 'Payment cancelled. Your booking is not confirmed.', 'booking-deposits-for-stripe' ),
			'taken'     => __( 'Sorry, this time is already booked. Please choose another time.', 'booking-deposits-for-stripe' ),
			'invalid'   => __( 'Please fill in all fields correctly.', 'booking-deposits-for-stripe' ),
			'payerror'  => __( 'Could not start the payment. Please try again later.', 'booking-deposits-for-stripe' ),
		);

		$currency = Settings::currency();

		ob_start();

		if ( isset( $messages[ $code ] ) ) {
			echo '<p class="bdfs-message"><strong>' . esc_html( $messages[ $code ] ) . '</strong></p>';
		}
		?>
		<p class="bdfs-price">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: full fee, 2: deposit percent, 3: deposit amount. */
					__( 'Fee: %1$s. Pay a %2$d%% deposit now: %3$s.', 'booking-deposits-for-stripe' ),
					Money::format( Settings::fee_minor(), $currency ),
					Settings::deposit_percent(),
					Money::format( Settings::deposit_minor(), $currency )
				)
			);
			?>
		</p>

		<form class="bdfs-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bdfs_book">
			<input type="hidden" name="bdfs_page" value="<?php echo esc_url( get_permalink() ); ?>">
			<?php wp_nonce_field( 'bdfs_book', 'bdfs_nonce' ); ?>

			<p><label><?php esc_html_e( 'Name', 'booking-deposits-for-stripe' ); ?><br><input type="text" name="bdfs_name" required></label></p>
			<p><label><?php esc_html_e( 'Email', 'booking-deposits-for-stripe' ); ?><br><input type="email" name="bdfs_email" required></label></p>
			<p><label><?php esc_html_e( 'Phone', 'booking-deposits-for-stripe' ); ?><br><input type="tel" name="bdfs_phone"></label></p>
			<p><label><?php esc_html_e( 'Date', 'booking-deposits-for-stripe' ); ?><br><input type="date" name="bdfs_date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" required></label></p>
			<p><label><?php esc_html_e( 'Time', 'booking-deposits-for-stripe' ); ?><br>
				<select name="bdfs_time" required>
					<?php foreach ( Settings::slots() as $slot ) : ?>
						<option value="<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( $slot ); ?></option>
					<?php endforeach; ?>
				</select>
			</label></p>

			<p><button type="submit"><?php esc_html_e( 'Book and pay deposit', 'booking-deposits-for-stripe' ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save the booking, then send the customer to Stripe to pay the deposit.
	 */
	public function handle() {
		// 1. Security: check the nonce.
		if ( ! isset( $_POST['bdfs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bdfs_nonce'] ) ), 'bdfs_book' ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'booking-deposits-for-stripe' ) );
		}

		// The full page address. Stripe needs a full URL. Only addresses on this site are allowed.
		$page = isset( $_POST['bdfs_page'] ) ? esc_url_raw( wp_unslash( $_POST['bdfs_page'] ) ) : '';
		$back = wp_validate_redirect( $page, home_url( '/' ) );
		$back = remove_query_arg( array( 'bdfs', 'bdfs_session' ), $back );

		if ( ! Settings::is_ready() ) {
			wp_safe_redirect( add_query_arg( 'bdfs', 'payerror', $back ) );
			exit;
		}

		// 2. Clean every field.
		$name  = isset( $_POST['bdfs_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bdfs_name'] ) ) : '';
		$email = isset( $_POST['bdfs_email'] ) ? sanitize_email( wp_unslash( $_POST['bdfs_email'] ) ) : '';
		$phone = isset( $_POST['bdfs_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['bdfs_phone'] ) ) : '';
		$date  = isset( $_POST['bdfs_date'] ) ? sanitize_text_field( wp_unslash( $_POST['bdfs_date'] ) ) : '';
		$time  = isset( $_POST['bdfs_time'] ) ? sanitize_text_field( wp_unslash( $_POST['bdfs_time'] ) ) : '';

		// 3. Check the values are valid.
		$d       = \DateTime::createFromFormat( 'Y-m-d', $date );
		$date_ok = $d && $d->format( 'Y-m-d' ) === $date && $date >= wp_date( 'Y-m-d' );
		$time_ok = in_array( $time, Settings::slots(), true );

		if ( ! $name || ! is_email( $email ) || ! $date_ok || ! $time_ok ) {
			wp_safe_redirect( add_query_arg( 'bdfs', 'invalid', $back ) );
			exit;
		}

		// 4. Stop double booking.
		if ( Booking::slot_taken( $date, $time ) ) {
			wp_safe_redirect( add_query_arg( 'bdfs', 'taken', $back ) );
			exit;
		}

		// 5. Save the booking as pending.
		$currency = Settings::currency();
		$deposit  = Settings::deposit_minor();

		$booking_id = Booking::create(
			array(
				'name'          => $name,
				'email'         => $email,
				'phone'         => $phone,
				'booking_date'  => $date,
				'booking_time'  => $time,
				'status'        => 'pending',
				'currency'      => $currency,
				'deposit_minor' => $deposit,
				'fee_minor'     => Settings::fee_minor(),
			)
		);

		// 6. Create a Stripe Checkout page for the deposit.
		$success = add_query_arg( 'bdfs', 'paid', $back ) . '&bdfs_session={CHECKOUT_SESSION_ID}';

		$session = StripeApi::post(
			'checkout/sessions',
			array(
				'mode'           => 'payment',
				'customer_email' => $email,
				'success_url'    => $success,
				'cancel_url'     => add_query_arg( 'bdfs', 'cancelled', $back ),
				'expires_at'     => time() + 31 * MINUTE_IN_SECONDS, // Stripe minimum is 30 minutes.
				'metadata'       => array( 'bdfs_booking_id' => $booking_id ),
				'line_items'     => array(
					array(
						'quantity'   => 1,
						'price_data' => array(
							'currency'     => $currency,
							'unit_amount'  => $deposit,
							'product_data' => array(
								/* translators: 1: date, 2: time. */
								'name' => sprintf( __( 'Appointment deposit - %1$s at %2$s', 'booking-deposits-for-stripe' ), $date, $time ),
							),
						),
					),
				),
			)
		);

		if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
			Log::write( is_wp_error( $session ) ? $session->get_error_message() : 'no checkout url' );
			Booking::cancel( $booking_id );
			wp_safe_redirect( add_query_arg( 'bdfs', 'payerror', $back ) );
			exit;
		}

		// 7. Remember the Stripe session, then send the customer to Stripe.
		Booking::set_session( $booking_id, $session['id'] );

		add_filter( 'allowed_redirect_hosts', array( $this, 'allow_stripe' ) );
		wp_safe_redirect( $session['url'] );
		exit;
	}

	public function allow_stripe( $hosts ) {
		$hosts[] = 'checkout.stripe.com';
		return $hosts;
	}

	/**
	 * Ask Stripe if this Checkout session is really paid. If yes, confirm the booking.
	 */
	private function confirm_from_stripe() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$session_id = isset( $_GET['bdfs_session'] ) ? sanitize_text_field( wp_unslash( $_GET['bdfs_session'] ) ) : '';

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
		if ( (int) ( $session['metadata']['bdfs_booking_id'] ?? 0 ) !== (int) $booking->id ) {
			return false;
		}

		return Booking::confirm( $booking->id ); // Also sends the emails.
	}
}
