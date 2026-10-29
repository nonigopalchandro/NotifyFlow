<?php
/** NotifyFlow Database Trait for NotifyFlow. */
defined( 'ABSPATH' ) || exit;

trait NotifyFlow_Database_Trait {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'notifyflow_notifications';
	}


	public static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			recipient_user_id bigint(20) unsigned NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL,
			notification_type varchar(50) NOT NULL DEFAULT 'comment',
			object_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			read_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_recipient (recipient_user_id, notification_type, object_id),
			KEY recipient_read_created (recipient_user_id, read_at, created_at),
			KEY post_id (post_id)
		) {$charset_collate};";

		dbDelta( $sql );
		$reactions_table = $wpdb->prefix . 'notifyflow_reactions';
		$reactions_sql = "CREATE TABLE {$reactions_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			reaction_type varchar(20) NOT NULL DEFAULT 'like',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_post (user_id, post_id),
			KEY post_id (post_id)
		) {$charset_collate};";
		dbDelta( $reactions_sql );
		$follow_table = $wpdb->prefix . 'notifyflow_follows';
		$follow_sql = "CREATE TABLE {$follow_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			follower_user_id bigint(20) unsigned NOT NULL,
			target_type varchar(20) NOT NULL,
			target_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY follower_target (follower_user_id, target_type, target_id),
			KEY target_lookup (target_type, target_id)
		) {$charset_collate};";
		dbDelta( $follow_sql );
		if ( ! get_option( 'notifyflow_settings' ) ) { add_option( 'notifyflow_settings', self::default_settings(), '', false ); }
		update_option( self::DB_VERSION_OPTION, NOTIFYFLOW_SCHEMA_VERSION, false );
	}


	public static function maybe_upgrade_schema() {
		if ( get_option( self::DB_VERSION_OPTION ) !== NOTIFYFLOW_SCHEMA_VERSION ) {
			self::install_schema();
		}
	}

}
