<?php
namespace ClinicBooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminPage {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_cb_cancel', array( $this, 'cancel' ) );
	}

	public function menu() {
		add_menu_page(
			'Bookings',
			'Bookings',
			'manage_options',
			'cb-bookings',
			array( $this, 'render' ),
			'dashicons-calendar-alt',
			26
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . Booking::table() . ' ORDER BY booking_date DESC, booking_time ASC LIMIT 200'
		);

		$colors = array(
			'confirmed' => '#1e7e34',
			'pending'   => '#b26a00',
			'cancelled' => '#a00',
		);
		?>
		<div class="wrap">
			<h1>Bookings</h1>

			<?php if ( isset( $_GET['cb_done'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Booking cancelled.</p></div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>#</th><th>Name</th><th>Email</th><th>Phone</th>
						<th>Date</th><th>Time</th><th>Status</th><th>Deposit</th><th>Action</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="9">No bookings yet.</td></tr>
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
								<?php echo esc_html( ucfirst( $b->status ) ); ?>
							</strong>
						</td>
						<td>Rs <?php echo esc_html( number_format( $b->deposit_amount ) ); ?></td>
						<td>
							<?php if ( 'cancelled' !== $b->status ) : ?>
								<?php
								$url = wp_nonce_url(
									admin_url( 'admin-post.php?action=cb_cancel&id=' . (int) $b->id ),
									'cb_cancel_' . (int) $b->id
								);
								?>
								<a href="<?php echo esc_url( $url ); ?>"
								   onclick="return confirm('Cancel this booking?');">Cancel</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p><em>Note: cancelling here does not refund the deposit. Refunds are done in the Stripe Dashboard.</em></p>
		</div>
		<?php
	}

	public function cancel() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'cb_cancel_' . $id );

		Booking::cancel( $id );

		wp_safe_redirect( admin_url( 'admin.php?page=cb-bookings&cb_done=1' ) );
		exit;
	}
}
