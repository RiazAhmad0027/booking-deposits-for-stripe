<?php
namespace BookingDepositsForStripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminPage {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_bdfs_cancel', array( $this, 'cancel' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
	}

	public function menu() {
		add_menu_page(
			__( 'Bookings', 'booking-deposits-for-stripe' ),
			__( 'Bookings', 'booking-deposits-for-stripe' ),
			'manage_options',
			'bdfs-bookings',
			array( $this, 'render' ),
			'dashicons-calendar-alt',
			26
		);
		add_submenu_page(
			'bdfs-bookings',
			__( 'All bookings', 'booking-deposits-for-stripe' ),
			__( 'All bookings', 'booking-deposits-for-stripe' ),
			'manage_options',
			'bdfs-bookings',
			array( $this, 'render' )
		);
	}

	public function setup_notice() {
		if ( Settings::is_ready() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$url = admin_url( 'admin.php?page=bdfs-settings' );
		echo '<div class="notice notice-warning"><p>'
			. esc_html__( 'Booking Deposits for Stripe needs your Stripe secret key before it can take bookings.', 'booking-deposits-for-stripe' )
			. ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open settings', 'booking-deposits-for-stripe' ) . '</a></p></div>';
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$rows   = Booking::latest();
		$colors = array(
			'confirmed' => '#1e7e34',
			'pending'   => '#b26a00',
			'cancelled' => '#a00',
		);
		$labels = array(
			'confirmed' => __( 'Confirmed', 'booking-deposits-for-stripe' ),
			'pending'   => __( 'Pending', 'booking-deposits-for-stripe' ),
			'cancelled' => __( 'Cancelled', 'booking-deposits-for-stripe' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bookings', 'booking-deposits-for-stripe' ); ?></h1>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( isset( $_GET['bdfs_done'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Booking cancelled.', 'booking-deposits-for-stripe' ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>#</th>
						<th><?php esc_html_e( 'Name', 'booking-deposits-for-stripe' ); ?></th>
						<th><?php esc_html_e( 'Email', 'booking-deposits-for-stripe' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'booking-deposits-for-stripe' ); ?></th>
						<th><?php esc_html_e( 'Date', 'booking-deposits-for-stripe' ); ?></th>
						<th><?php esc_html_e( 'Time', 'booking-deposits-for-stripe' ); ?></th>
						<th><?php esc_html_e( 'Status', 'booking-deposits-for-stripe' ); ?></th>
						<th><?php esc_html_e( 'Deposit', 'booking-deposits-for-stripe' ); ?></th>
						<th><?php esc_html_e( 'Action', 'booking-deposits-for-stripe' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No bookings yet.', 'booking-deposits-for-stripe' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $rows as $b ) : ?>
					<tr>
						<td><?php echo esc_html( $b->id ); ?></td>
						<td><?php echo esc_html( $b->name ); ?></td>
						<td><?php echo esc_html( $b->email ); ?></td>
						<td><?php echo esc_html( $b->phone ); ?></td>
						<td><?php echo esc_html( $b->booking_date ); ?></td>
						<td><?php echo esc_html( $b->booking_time ); ?></td>
						<td>
							<strong style="color:<?php echo esc_attr( $colors[ $b->status ] ?? '#333' ); ?>">
								<?php echo esc_html( $labels[ $b->status ] ?? $b->status ); ?>
							</strong>
						</td>
						<td><?php echo esc_html( Money::format( (int) $b->deposit_minor, $b->currency ) ); ?></td>
						<td>
							<?php if ( 'cancelled' !== $b->status ) : ?>
								<?php
								$url = wp_nonce_url(
									admin_url( 'admin-post.php?action=bdfs_cancel&id=' . (int) $b->id ),
									'bdfs_cancel_' . (int) $b->id
								);
								?>
								<a href="<?php echo esc_url( $url ); ?>"
									onclick="return confirm('<?php echo esc_js( __( 'Cancel this booking?', 'booking-deposits-for-stripe' ) ); ?>');">
									<?php esc_html_e( 'Cancel', 'booking-deposits-for-stripe' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p><em><?php esc_html_e( 'Cancelling here does not refund the deposit. Refunds are made in the Stripe Dashboard.', 'booking-deposits-for-stripe' ); ?></em></p>
		</div>
		<?php
	}

	public function cancel() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'booking-deposits-for-stripe' ) );
		}
		check_admin_referer( 'bdfs_cancel_' . $id );

		Booking::cancel( $id );

		wp_safe_redirect( admin_url( 'admin.php?page=bdfs-bookings&bdfs_done=1' ) );
		exit;
	}
}
