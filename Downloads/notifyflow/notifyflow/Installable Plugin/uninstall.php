<?php
/**
 * Uninstall NotifyFlow.
 * Data is retained unless the administrator explicitly enabled deletion in Settings > NotifyFlow.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'notifyflow_settings', array() );
if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;
wp_clear_scheduled_hook( 'notifyflow_daily_cleanup' );
$tables = array(
	$wpdb->prefix . 'notifyflow_notifications',
	$wpdb->prefix . 'notifyflow_reactions',
	$wpdb->prefix . 'notifyflow_follows',
);
foreach ( $tables as $table ) {
	// Table names are constructed only from the active WordPress table prefix and fixed plugin suffixes.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'notifyflow_preferences' ), array( '%s' ) );
delete_option( 'notifyflow_settings' );
delete_option( 'notifyflow_db_version' );
