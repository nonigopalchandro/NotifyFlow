<?php
/**
 * Uninstall NotifyFlow.
 * Data is retained unless the administrator explicitly enabled deletion in Settings > NotifyFlow.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$notifyflow_settings = get_option( 'notifyflow_settings', array() );
if ( ! is_array( $notifyflow_settings ) || empty( $notifyflow_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;
wp_clear_scheduled_hook( 'notifyflow_daily_cleanup' );
$notifyflow_tables = array(
	$wpdb->prefix . 'notifyflow_notifications',
	$wpdb->prefix . 'notifyflow_reactions',
	$wpdb->prefix . 'notifyflow_follows',
);
foreach ( $notifyflow_tables as $notifyflow_table ) {
	// Table names are constructed only from the active WordPress table prefix and fixed plugin suffixes.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Drop only NotifyFlow tables after an administrator explicitly enabled uninstall cleanup.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $notifyflow_table ) );
}
delete_metadata( 'user', 0, 'notifyflow_preferences', '', true );
delete_option( 'notifyflow_settings' );
delete_option( 'notifyflow_db_version' );
