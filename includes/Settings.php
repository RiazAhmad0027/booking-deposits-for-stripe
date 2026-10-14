<?php
namespace BookingDepositsForStripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page: WP Admin → Bookings → Settings.
 * Keys can also be set in wp-config.php (they win over the settings page):
 *   define( 'BDFS_STRIPE_SECRET_KEY', 'sk_test_...' );
 *   define( 'BDFS_STRIPE_WEBHOOK_SECRET', 'whsec_...' );
 */
class Settings {

	const OPTION = 'bdfs_settings';
	const GROUP  = 'bdfs_settings_group';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/* ---------- Reading settings (used by the whole plugin) ---------- */

	public static function defaults() {
		return array(
			'secret_key'      => '',
			'webhook_secret'  => '',
			'currency'        => 'usd',
			'fee'             => '50',
			'deposit_percent' => 20,
			'slots'           => '10:00, 11:00, 12:00, 14:00, 15:00, 16:00',
			'hold_minutes'    => 35,
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function secret_key() {
		return defined( 'BDFS_STRIPE_SECRET_KEY' ) ? BDFS_STRIPE_SECRET_KEY : (string) self::get( 'secret_key' );
	}

	public static function webhook_secret() {
		return defined( 'BDFS_STRIPE_WEBHOOK_SECRET' ) ? BDFS_STRIPE_WEBHOOK_SECRET : (string) self::get( 'webhook_secret' );
	}

	public static function currency() {
		return strtolower( (string) self::get( 'currency' ) );
	}

	public static function fee_minor() {
		return Money::to_minor( (float) self::get( 'fee' ), self::currency() );
	}

	public static function deposit_percent() {
		return (int) self::get( 'deposit_percent' );
	}

	public static function deposit_minor() {
		return (int) round( self::fee_minor() * self::deposit_percent() / 100 );
	}

	public static function hold_minutes() {
		return max( 35, (int) self::get( 'hold_minutes' ) );
	}

	public static function slots() {
		return self::parse_slots( (string) self::get( 'slots' ) );
	}

	public static function is_ready() {
		return '' !== self::secret_key();
	}

	private static function parse_slots( $text ) {
		$slots = array();
		foreach ( explode( ',', $text ) as $slot ) {
			$slot = trim( $slot );
			if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $slot ) ) {
				$slots[] = $slot;
			}
		}
		$slots = array_values( array_unique( $slots ) );
		sort( $slots );
		return $slots;
	}

	/* ---------- Admin page ---------- */

	public function menu() {
		add_submenu_page(
			'bdfs-bookings',
			__( 'Booking Deposits for Stripe settings', 'booking-deposits-for-stripe' ),
			__( 'Settings', 'booking-deposits-for-stripe' ),
			'manage_options',
			'bdfs-settings',
			array( $this, 'render' )
		);
	}

	public function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public function sanitize( $input ) {
		$old   = self::all();
		$input = is_array( $input ) ? $input : array();
		$clean = $old;

		// Secret key: empty = keep the old one.
		$key = isset( $input['secret_key'] ) ? trim( sanitize_text_field( $input['secret_key'] ) ) : '';
		if ( '' !== $key ) {
			if ( preg_match( '/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $key ) ) {
				$clean['secret_key'] = $key;
			} else {
				add_settings_error( self::OPTION, 'secret_key', __( 'The Stripe secret key must start with sk_test_ or sk_live_.', 'booking-deposits-for-stripe' ) );
			}
		}

		$whsec = isset( $input['webhook_secret'] ) ? trim( sanitize_text_field( $input['webhook_secret'] ) ) : '';
		if ( '' !== $whsec ) {
			if ( preg_match( '/^whsec_[A-Za-z0-9]+$/', $whsec ) ) {
				$clean['webhook_secret'] = $whsec;
			} else {
				add_settings_error( self::OPTION, 'webhook_secret', __( 'The webhook signing secret must start with whsec_.', 'booking-deposits-for-stripe' ) );
			}
		}

		$currency = isset( $input['currency'] ) ? strtolower( sanitize_text_field( $input['currency'] ) ) : '';
		if ( preg_match( '/^[a-z]{3}$/', $currency ) ) {
			$clean['currency'] = $currency;
		} else {
			add_settings_error( self::OPTION, 'currency', __( 'Currency must be a 3-letter code, for example usd, eur or pkr.', 'booking-deposits-for-stripe' ) );
		}

		$fee = isset( $input['fee'] ) ? (float) $input['fee'] : 0;
		if ( $fee > 0 ) {
			$clean['fee'] = (string) round( $fee, 2 );
		} else {
			add_settings_error( self::OPTION, 'fee', __( 'The fee must be more than 0.', 'booking-deposits-for-stripe' ) );
		}

		$percent                  = isset( $input['deposit_percent'] ) ? absint( $input['deposit_percent'] ) : 0;
		$clean['deposit_percent'] = min( 100, max( 1, $percent ) );

		$slots = self::parse_slots( isset( $input['slots'] ) ? sanitize_text_field( $input['slots'] ) : '' );
		if ( $slots ) {
			$clean['slots'] = implode( ', ', $slots );
		} else {
			add_settings_error( self::OPTION, 'slots', __( 'Add at least one time slot, like 10:00, 11:30.', 'booking-deposits-for-stripe' ) );
		}

		$clean['hold_minutes'] = max( 35, isset( $input['hold_minutes'] ) ? absint( $input['hold_minutes'] ) : 35 );

		return $clean;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s           = self::all();
		$key_in_wpc  = defined( 'BDFS_STRIPE_SECRET_KEY' );
		$hook_in_wpc = defined( 'BDFS_STRIPE_WEBHOOK_SECRET' );
		$saved_text  = __( 'Saved. Leave empty to keep it.', 'booking-deposits-for-stripe' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Booking Deposits for Stripe settings', 'booking-deposits-for-stripe' ); ?></h1>
			<?php settings_errors( self::OPTION ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'Stripe', 'booking-deposits-for-stripe' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bdfs_secret_key"><?php esc_html_e( 'Secret key', 'booking-deposits-for-stripe' ); ?></label></th>
						<td>
							<?php if ( $key_in_wpc ) : ?>
								<p><?php esc_html_e( 'Set in wp-config.php.', 'booking-deposits-for-stripe' ); ?></p>
							<?php else : ?>
								<input type="password" id="bdfs_secret_key" class="regular-text" autocomplete="off"
									name="<?php echo esc_attr( self::OPTION ); ?>[secret_key]" value=""
									placeholder="<?php echo esc_attr( $s['secret_key'] ? $saved_text : 'sk_test_...' ); ?>">
								<p class="description"><?php esc_html_e( 'Stripe Dashboard → Developers → API keys. Use a test key (sk_test_) until you are ready to take real payments.', 'booking-deposits-for-stripe' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bdfs_webhook_secret"><?php esc_html_e( 'Webhook signing secret', 'booking-deposits-for-stripe' ); ?></label></th>
						<td>
							<?php if ( $hook_in_wpc ) : ?>
								<p><?php esc_html_e( 'Set in wp-config.php.', 'booking-deposits-for-stripe' ); ?></p>
							<?php else : ?>
								<input type="password" id="bdfs_webhook_secret" class="regular-text" autocomplete="off"
									name="<?php echo esc_attr( self::OPTION ); ?>[webhook_secret]" value=""
									placeholder="<?php echo esc_attr( $s['webhook_secret'] ? $saved_text : 'whsec_...' ); ?>">
							<?php endif; ?>
							<p class="description">
								<?php esc_html_e( 'In Stripe, add a webhook endpoint with this URL:', 'booking-deposits-for-stripe' ); ?><br>
								<code><?php echo esc_html( rest_url( 'booking-deposits-for-stripe/v1/stripe-webhook' ) ); ?></code><br>
								<?php esc_html_e( 'Events: checkout.session.completed and checkout.session.expired', 'booking-deposits-for-stripe' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Booking', 'booking-deposits-for-stripe' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bdfs_currency"><?php esc_html_e( 'Currency', 'booking-deposits-for-stripe' ); ?></label></th>
						<td>
							<input type="text" id="bdfs_currency" class="small-text" maxlength="3"
								name="<?php echo esc_attr( self::OPTION ); ?>[currency]" value="<?php echo esc_attr( $s['currency'] ); ?>">
							<p class="description"><?php esc_html_e( '3-letter code: usd, eur, gbp, pkr ...', 'booking-deposits-for-stripe' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bdfs_fee"><?php esc_html_e( 'Full fee', 'booking-deposits-for-stripe' ); ?></label></th>
						<td>
							<input type="number" id="bdfs_fee" class="small-text" min="0.01" step="0.01"
								name="<?php echo esc_attr( self::OPTION ); ?>[fee]" value="<?php echo esc_attr( $s['fee'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bdfs_deposit"><?php esc_html_e( 'Deposit (%)', 'booking-deposits-for-stripe' ); ?></label></th>
						<td>
							<input type="number" id="bdfs_deposit" class="small-text" min="1" max="100"
								name="<?php echo esc_attr( self::OPTION ); ?>[deposit_percent]" value="<?php echo esc_attr( $s['deposit_percent'] ); ?>">
							<p class="description">
								<?php
								/* translators: %s: deposit amount, for example "USD 10.00". */
								echo esc_html( sprintf( __( 'Customers pay %s now. Stripe has a minimum charge (about USD 0.50).', 'booking-deposits-for-stripe' ), Money::format( self::deposit_minor(), self::currency() ) ) );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bdfs_slots"><?php esc_html_e( 'Time slots', 'booking-deposits-for-stripe' ); ?></label></th>
						<td>
							<input type="text" id="bdfs_slots" class="large-text"
								name="<?php echo esc_attr( self::OPTION ); ?>[slots]" value="<?php echo esc_attr( $s['slots'] ); ?>">
							<p class="description"><?php esc_html_e( '24-hour times, separated by commas.', 'booking-deposits-for-stripe' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bdfs_hold"><?php esc_html_e( 'Hold unpaid slots for (minutes)', 'booking-deposits-for-stripe' ); ?></label></th>
						<td>
							<input type="number" id="bdfs_hold" class="small-text" min="35"
								name="<?php echo esc_attr( self::OPTION ); ?>[hold_minutes]" value="<?php echo esc_attr( $s['hold_minutes'] ); ?>">
							<p class="description"><?php esc_html_e( 'Minimum 35, because a Stripe payment page stays open for at least 30 minutes.', 'booking-deposits-for-stripe' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<p><?php esc_html_e( 'Show the booking form on any page with this shortcode:', 'booking-deposits-for-stripe' ); ?> <code>[bdfs_booking]</code></p>
		</div>
		<?php
	}
}
