<?php
/** Privacy tools and related-record cleanup for NotifyFlow. */
defined( 'ABSPATH' ) || exit;

trait NotifyFlow_Privacy_Trait {
	public static function register_privacy_exporter( $exporters ) {
		$exporters['notifyflow'] = array(
			'exporter_friendly_name' => __( 'NotifyFlow activity', 'notifyflow' ),
			'callback'               => array( __CLASS__, 'privacy_export_personal_data' ),
		);
		return $exporters;
	}

	public static function register_privacy_eraser( $erasers ) {
		$erasers['notifyflow'] = array(
			'eraser_friendly_name' => __( 'NotifyFlow activity', 'notifyflow' ),
			'callback'             => array( __CLASS__, 'privacy_erase_personal_data' ),
		);
		return $erasers;
	}

	public static function privacy_export_personal_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof WP_User ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb;
		$page   = max( 1, absint( $page ) );
		$limit  = 50;
		$offset = ( $page - 1 ) * $limit;
		$items  = array();
		$notifications_table = self::table_name();
		$reactions_table = $wpdb->prefix . 'notifyflow_reactions';
		$follows_table = $wpdb->prefix . 'notifyflow_follows';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy exports must read the latest records from NotifyFlow's custom table.
		$notifications = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, recipient_user_id, actor_user_id, notification_type, object_id, post_id, created_at, read_at FROM %i WHERE recipient_user_id = %d OR actor_user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
			$notifications_table,
			$user->ID, $user->ID, $limit, $offset
		) );
		foreach ( (array) $notifications as $row ) {
			$items[] = array(
				'group_id'    => 'notifyflow-notifications',
				'group_label' => __( 'Notifications', 'notifyflow' ),
				'item_id'     => 'notifyflow-notification-' . absint( $row->id ),
				'data'        => array(
					array( 'name' => __( 'Notification type', 'notifyflow' ), 'value' => sanitize_key( $row->notification_type ) ),
					array( 'name' => __( 'Relationship to notification', 'notifyflow' ), 'value' => absint( $row->recipient_user_id ) === absint( $user->ID ) ? __( 'Recipient', 'notifyflow' ) : __( 'Actor', 'notifyflow' ) ),
					array( 'name' => __( 'Related post ID', 'notifyflow' ), 'value' => (string) absint( $row->post_id ) ),
					array( 'name' => __( 'Created at (UTC)', 'notifyflow' ), 'value' => sanitize_text_field( $row->created_at ) ),
					array( 'name' => __( 'Read status', 'notifyflow' ), 'value' => null === $row->read_at ? __( 'Unread', 'notifyflow' ) : __( 'Read', 'notifyflow' ) ),
				),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy exports must read the latest records from NotifyFlow's custom table.
		$reactions = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, post_id, reaction_type, created_at FROM %i WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
			$reactions_table,
			$user->ID, $limit, $offset
		) );
		foreach ( (array) $reactions as $row ) {
			$items[] = array(
				'group_id' => 'notifyflow-reactions', 'group_label' => __( 'Reactions', 'notifyflow' ), 'item_id' => 'notifyflow-reaction-' . absint( $row->id ),
				'data' => array(
					array( 'name' => __( 'Post ID', 'notifyflow' ), 'value' => (string) absint( $row->post_id ) ),
					array( 'name' => __( 'Reaction', 'notifyflow' ), 'value' => sanitize_key( $row->reaction_type ) ),
					array( 'name' => __( 'Created at (UTC)', 'notifyflow' ), 'value' => sanitize_text_field( $row->created_at ) ),
				),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy exports must read the latest records from NotifyFlow's custom table.
		$follows = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, follower_user_id, target_type, target_id, created_at FROM %i WHERE follower_user_id = %d OR (target_type = %s AND target_id = %d) ORDER BY id ASC LIMIT %d OFFSET %d',
			$follows_table,
			$user->ID, 'user', $user->ID, $limit, $offset
		) );
		foreach ( (array) $follows as $row ) {
			$items[] = array(
				'group_id' => 'notifyflow-follows', 'group_label' => __( 'Followed authors and topics', 'notifyflow' ), 'item_id' => 'notifyflow-follow-' . absint( $row->id ),
				'data' => array(
					array( 'name' => __( 'Follow relationship', 'notifyflow' ), 'value' => absint( $row->follower_user_id ) === absint( $user->ID ) ? __( 'Following a target', 'notifyflow' ) : __( 'Followed by a user', 'notifyflow' ) ),
					array( 'name' => __( 'Follow type', 'notifyflow' ), 'value' => sanitize_key( $row->target_type ) ),
					array( 'name' => __( 'Target ID', 'notifyflow' ), 'value' => (string) absint( $row->target_id ) ),
					array( 'name' => __( 'Created at (UTC)', 'notifyflow' ), 'value' => sanitize_text_field( $row->created_at ) ),
				),
			);
		}

		$preferences = get_user_meta( $user->ID, 'notifyflow_preferences', true );
		if ( 1 === $page && is_array( $preferences ) && ! empty( $preferences ) ) {
			$items[] = array(
				'group_id' => 'notifyflow-preferences', 'group_label' => __( 'Notification preferences', 'notifyflow' ), 'item_id' => 'notifyflow-preferences-' . absint( $user->ID ),
				'data' => array_map( function ( $key, $value ) { return array( 'name' => sanitize_key( $key ), 'value' => rest_sanitize_boolean( $value ) ? __( 'Enabled', 'notifyflow' ) : __( 'Disabled', 'notifyflow' ) ); }, array_keys( $preferences ), array_values( $preferences ) ),
			);
		}

		$done = count( (array) $notifications ) < $limit && count( (array) $reactions ) < $limit && count( (array) $follows ) < $limit;
		return array( 'data' => $items, 'done' => $done );
	}

	public static function privacy_erase_personal_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user instanceof WP_User ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		global $wpdb;
		$user_id = absint( $user->ID );
		$removed = 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Erasure must remove the user's data from NotifyFlow's custom tables immediately.
		$removed += max( 0, (int) $wpdb->delete( self::table_name(), array( 'recipient_user_id' => $user_id ), array( '%d' ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Erasure must remove the user's data from NotifyFlow's custom tables immediately.
		$removed += max( 0, (int) $wpdb->delete( self::table_name(), array( 'actor_user_id' => $user_id ), array( '%d' ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Erasure must remove the user's data from NotifyFlow's custom tables immediately.
		$removed += max( 0, (int) $wpdb->delete( $wpdb->prefix . 'notifyflow_reactions', array( 'user_id' => $user_id ), array( '%d' ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Erasure must remove the user's data from NotifyFlow's custom tables immediately.
		$removed += max( 0, (int) $wpdb->delete( $wpdb->prefix . 'notifyflow_follows', array( 'follower_user_id' => $user_id ), array( '%d' ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Erasure must remove the user's data from NotifyFlow's custom tables immediately.
		$removed += max( 0, (int) $wpdb->delete( $wpdb->prefix . 'notifyflow_follows', array( 'target_type' => 'user', 'target_id' => $user_id ), array( '%s', '%d' ) ) );
		delete_user_meta( $user_id, 'notifyflow_preferences' );
		return array( 'items_removed' => $removed > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}

	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'NotifyFlow stores on-site notification records, reactions, followed authors/topics, and per-user notification preferences in this WordPress site database. It does not send this activity data to an external NotifyFlow server. Profile images are resolved through WordPress avatar APIs and compatible custom avatar metadata; depending on the site configuration, an external avatar provider such as Gravatar may receive the visitor IP address and request metadata. Initials are shown when an avatar is unavailable.', 'notifyflow' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Site administrators can configure notification retention and choose whether plugin data is deleted during uninstall. WordPress personal data export and erasure tools include NotifyFlow data associated with a user.', 'notifyflow' ) . '</p>';
		wp_add_privacy_policy_content( __( 'NotifyFlow', 'notifyflow' ), wp_kses_post( $content ) );
	}

	public static function cleanup_deleted_user_data( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return; }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep custom-table data cleanup synchronous with account deletion.
		$wpdb->delete( self::table_name(), array( 'recipient_user_id' => $user_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep custom-table data cleanup synchronous with account deletion.
		$wpdb->delete( self::table_name(), array( 'actor_user_id' => $user_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep custom-table data cleanup synchronous with account deletion.
		$wpdb->delete( $wpdb->prefix . 'notifyflow_reactions', array( 'user_id' => $user_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep custom-table data cleanup synchronous with account deletion.
		$wpdb->delete( $wpdb->prefix . 'notifyflow_follows', array( 'follower_user_id' => $user_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep custom-table data cleanup synchronous with account deletion.
		$wpdb->delete( $wpdb->prefix . 'notifyflow_follows', array( 'target_type' => 'user', 'target_id' => $user_id ), array( '%s', '%d' ) );
		delete_user_meta( $user_id, 'notifyflow_preferences' );
	}

	public static function cleanup_deleted_post_data( $post_id, $post = null ) {
		global $wpdb;
		$post_id = absint( $post_id );
		if ( ! $post_id ) { return; }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep custom-table cleanup synchronous with post deletion.
		$wpdb->delete( self::table_name(), array( 'post_id' => $post_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep custom-table cleanup synchronous with post deletion.
		$wpdb->delete( $wpdb->prefix . 'notifyflow_reactions', array( 'post_id' => $post_id ), array( '%d' ) );
	}

	public static function cleanup_deleted_comment_data( $comment_id, $comment = null ) {
		global $wpdb;
		$comment_id = absint( $comment_id );
		if ( ! $comment_id ) { return; }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep custom-table cleanup synchronous with comment deletion.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE object_id = %d AND notification_type IN (%s, %s, %s)', self::table_name(), $comment_id, 'comment', 'reply', 'mention' ) );
	}

	public static function cleanup_deleted_term_follow_data( $term_id, $tt_id, $taxonomy, $deleted_term, $object_ids ) {
		if ( ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) { return; }
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep custom-table cleanup synchronous with term deletion.
		$wpdb->delete( $wpdb->prefix . 'notifyflow_follows', array( 'target_type' => 'category' === $taxonomy ? 'category' : 'tag', 'target_id' => absint( $term_id ) ), array( '%s', '%d' ) );
	}
}
