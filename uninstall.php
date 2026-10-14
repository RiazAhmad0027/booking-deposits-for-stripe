<?php
/**
 * Runs when the plugin is deleted from WP Admin → Plugins.
 * Removes the bookings table and the settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'bdfs_bookings' ) );

delete_option( 'bdfs_settings' );
delete_option( 'bdfs_db_version' );
