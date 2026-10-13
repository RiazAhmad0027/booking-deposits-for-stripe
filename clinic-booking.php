<?php
/**
 * Plugin Name: Clinic Booking
 * Description: Appointment booking with deposit payments (learning project).
 * Version: 0.3.0
 * Author: Riaz Ahmad
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Load classes from the includes/ folder: ClinicBooking\BookingForm -> includes/BookingForm.php
spl_autoload_register( function ( $class ) {
	$prefix = 'ClinicBooking\\';
	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}
	$file = CB_PLUGIN_DIR . 'includes/' . substr( $class, strlen( $prefix ) ) . '.php';
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// Create the bookings table when the plugin is activated.
register_activation_hook( __FILE__, function () {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = $wpdb->prefix . 'cb_bookings';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(100) NOT NULL,
		email varchar(100) NOT NULL,
		phone varchar(30) DEFAULT '' NOT NULL,
		booking_date date NOT NULL,
		booking_time varchar(5) NOT NULL,
		status varchar(20) DEFAULT 'pending' NOT NULL,
		deposit_amount int(11) DEFAULT 0 NOT NULL,
		stripe_session_id varchar(255) DEFAULT '' NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		KEY slot (booking_date,booking_time)
	) $charset;";

	dbDelta( $sql );
} );

add_action( 'plugins_loaded', function () {
	new ClinicBooking\BookingForm();
	new ClinicBooking\AdminPage();
	new ClinicBooking\Webhook();
} );
