<?php
/**
 * Plugin Name:       Booking Deposits for Stripe
 * Plugin URI:        https://github.com/RiazAhmad0027/booking-deposits-for-stripe
 * Description:       Simple appointment booking with a deposit paid through Stripe Checkout. Time slots, double-booking protection, webhook confirmation and emails.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Riaz Ahmad
 * Author URI:        https://profiles.wordpress.org/riazahmad2211/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       booking-deposits-for-stripe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BDFS_VERSION', '1.0.0' );
define( 'BDFS_DIR', plugin_dir_path( __FILE__ ) );

// Load classes from includes/: BookingDepositsForStripe\BookingForm -> includes/BookingForm.php
spl_autoload_register(
	function ( $class ) {
		$prefix = 'BookingDepositsForStripe\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$file = BDFS_DIR . 'includes/' . substr( $class, strlen( $prefix ) ) . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Create (or update) the bookings table.
 */
function bdfs_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = $wpdb->prefix . 'bdfs_bookings';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(100) NOT NULL,
		email varchar(100) NOT NULL,
		phone varchar(30) DEFAULT '' NOT NULL,
		booking_date date NOT NULL,
		booking_time varchar(5) NOT NULL,
		status varchar(20) DEFAULT 'pending' NOT NULL,
		currency varchar(3) DEFAULT '' NOT NULL,
		deposit_minor bigint(20) DEFAULT 0 NOT NULL,
		fee_minor bigint(20) DEFAULT 0 NOT NULL,
		stripe_session_id varchar(255) DEFAULT '' NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		KEY slot (booking_date,booking_time),
		KEY stripe_session_id (stripe_session_id(191))
	) $charset;";

	dbDelta( $sql );
	update_option( 'bdfs_db_version', BDFS_VERSION );
}
register_activation_hook( __FILE__, 'bdfs_install' );

add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'bdfs_db_version' ) !== BDFS_VERSION ) {
			bdfs_install();
		}
		new BookingDepositsForStripe\BookingForm();
		new BookingDepositsForStripe\AdminPage();
		new BookingDepositsForStripe\Settings();
		new BookingDepositsForStripe\Webhook();
	}
);
