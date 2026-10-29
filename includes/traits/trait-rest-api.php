<?php
/** NotifyFlow REST API Trait for NotifyFlow. */
defined( 'ABSPATH' ) || exit;

trait NotifyFlow_REST_API_Trait {

	/**
	 * Resolve a profile image while respecting WordPress avatar filters.
	 * Custom avatar metadata is checked first, then WordPress get_avatar_url()
	 * (which supports Gravatar and integrations using get_avatar_data filters).
	 */
	private static function get_actor_avatar_url( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return '';
		}

		$meta_keys = array( 'profile_photo', 'profile_picture', 'custom_avatar', 'avatar', 'wp_user_avatar', 'simple_local_avatar' );
		foreach ( $meta_keys as $meta_key ) {
			$value = get_user_meta( $user_id, $meta_key, true );
			if ( is_array( $value ) ) {
				$value = isset( $value['full'] ) ? $value['full'] : ( isset( $value['url'] ) ? $value['url'] : ( isset( $value['avatar'] ) ? $value['avatar'] : '' ) );
			}
			if ( is_numeric( $value ) && absint( $value ) ) {
				$value = wp_get_attachment_image_url( absint( $value ), 'thumbnail' );
			}
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$url = esc_url_raw( trim( $value ), array( 'http', 'https' ) );
				if ( $url ) {
					return $url;
				}
			}
		}

		$url = get_avatar_url( $user_id, array( 'size' => 96, 'default' => 'mystery', 'force_default' => false, 'rating' => 'g' ) );
		return is_string( $url ) ? esc_url_raw( $url, array( 'http', 'https' ) ) : '';
	}

	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/notifications',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_notifications' ),
				'permission_callback' => array( __CLASS__, 'rest_require_login' ),
				'args'                => array(
					'page' => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value >= 1;
						},
					),
					'per_page' => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value >= 1 && (int) $value <= 50;
						},
					),
					'type' => array(
						'default' => 'all',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function ( $value ) { return in_array( $value, array( 'all', 'comment', 'reply', 'mention', 'reaction_like', 'follow_author', 'follow_topic', 'custom', 'unread' ), true ); },
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/unread-count',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_unread_count' ),
				'permission_callback' => array( __CLASS__, 'rest_require_login' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/notifications/(?P<id>\d+)/read',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_mark_read' ),
				'permission_callback' => array( __CLASS__, 'rest_require_login' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/notifications/read-all',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_mark_all_read' ),
				'permission_callback' => array( __CLASS__, 'rest_require_login' ),
			)
		);

		register_rest_route( self::REST_NAMESPACE, '/preferences', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'rest_get_preferences' ), 'permission_callback' => array( __CLASS__, 'rest_require_login' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'rest_save_preferences' ), 'permission_callback' => array( __CLASS__, 'rest_require_login' ), 'args' => array( 'comment' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ), 'reply' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ), 'mention' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ), 'reaction_like' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ), 'follow_author' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ), 'follow_topic' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ), 'custom' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ) ) ),
		) );

		register_rest_route( self::REST_NAMESPACE, '/reactions', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'rest_get_reactions' ), 'permission_callback' => array( __CLASS__, 'rest_require_login' ), 'args' => array( 'post_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ) ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'rest_toggle_reaction' ), 'permission_callback' => array( __CLASS__, 'rest_require_login' ) ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/follow', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'rest_toggle_follow' ), 'permission_callback' => array( __CLASS__, 'rest_require_login' ) ) );
	}


	public static function rest_get_reactions( $request ) {
		global $wpdb; $post_id = absint( $request->get_param( 'post_id' ) ); $post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) { return new WP_Error( 'notifyflow_invalid_post', __( 'This post is not available for reactions.', 'notifyflow' ), array( 'status' => 404 ) ); }
		$table = $wpdb->prefix . 'notifyflow_reactions';
		$count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE post_id = %d", $post_id ) ) );
		$mine = $wpdb->get_var( $wpdb->prepare( "SELECT reaction_type FROM {$table} WHERE post_id = %d AND user_id = %d", $post_id, get_current_user_id() ) );
		return rest_ensure_response( array( 'count' => $count, 'mine' => $mine ) );
	}


	public static function rest_toggle_reaction( $request ) {
		$settings = self::settings(); if ( empty( $settings['enable_reactions'] ) ) { return new WP_Error( 'notifyflow_disabled', __( 'Reactions are disabled.', 'notifyflow' ), array( 'status' => 403 ) ); }
		$post_id = absint( $request->get_param( 'post_id' ) ); $post = get_post( $post_id ); $user_id = get_current_user_id();
		$reaction = sanitize_key( $request->get_param( 'reaction_type' ) );
		if ( ! in_array( $reaction, array( 'like', 'haha', 'angry', 'cry', 'care', 'love', 'celebrate' ), true ) ) { $reaction = 'like'; }
		if ( ! $post || 'publish' !== $post->post_status || ! is_user_logged_in() ) { return new WP_Error( 'notifyflow_invalid_post', __( 'This post cannot be reacted to.', 'notifyflow' ), array( 'status' => 400 ) ); }
		global $wpdb; $table = $wpdb->prefix . 'notifyflow_reactions'; $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, reaction_type FROM {$table} WHERE user_id = %d AND post_id = %d", $user_id, $post_id ) );
		if ( $existing && $existing->reaction_type === $reaction ) {
			$wpdb->delete( $table, array( 'id' => absint( $existing->id ) ), array( '%d' ) );
			$wpdb->delete( self::table_name(), array( 'actor_user_id' => $user_id, 'notification_type' => 'reaction_like', 'object_id' => absint( $existing->id ) ), array( '%d', '%s', '%d' ) );
			$active = false;
		} elseif ( $existing ) {
			$wpdb->update( $table, array( 'reaction_type' => $reaction, 'created_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $existing->id ) ), array( '%s', '%s' ), array( '%d' ) );
			$active = $reaction;
		} else {
			$ok = $wpdb->insert( $table, array( 'user_id' => $user_id, 'post_id' => $post_id, 'reaction_type' => $reaction, 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%s', '%s' ) );
			if ( false === $ok ) { return new WP_Error( 'notifyflow_reaction_failed', __( 'Could not save your reaction.', 'notifyflow' ), array( 'status' => 500 ) ); }
			$reaction_id = absint( $wpdb->insert_id ); $active = $reaction;
			if ( absint( $post->post_author ) !== $user_id ) { self::insert_notification( absint( $post->post_author ), $user_id, 'reaction_like', $reaction_id, $post_id ); }
		}
		$count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE post_id = %d", $post_id ) ) );
		return rest_ensure_response( array( 'count' => $count, 'mine' => $active ) );
	}


	public static function rest_toggle_follow( $request ) {
		$settings = self::settings(); if ( empty( $settings['enable_follows'] ) ) { return new WP_Error( 'notifyflow_disabled', __( 'Follows are disabled.', 'notifyflow' ), array( 'status' => 403 ) ); }
		$type = sanitize_key( $request->get_param( 'target_type' ) ); $target_id = absint( $request->get_param( 'target_id' ) ); $user_id = get_current_user_id();
		if ( ! in_array( $type, array( 'user', 'category', 'tag' ), true ) || ! $target_id ) { return new WP_Error( 'notifyflow_invalid_target', __( 'Invalid follow target.', 'notifyflow' ), array( 'status' => 400 ) ); }
		if ( 'user' === $type && ( ! get_userdata( $target_id ) || $target_id === $user_id ) ) { return new WP_Error( 'notifyflow_invalid_user', __( 'You cannot follow this user.', 'notifyflow' ), array( 'status' => 400 ) ); }
		if ( in_array( $type, array( 'category', 'tag' ), true ) && ! term_exists( $target_id, 'category' === $type ? 'category' : 'post_tag' ) ) { return new WP_Error( 'notifyflow_invalid_term', __( 'This topic does not exist.', 'notifyflow' ), array( 'status' => 400 ) ); }
		global $wpdb; $table = $wpdb->prefix . 'notifyflow_follows'; $where = array( 'follower_user_id' => $user_id, 'target_type' => $type, 'target_id' => $target_id ); $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE follower_user_id = %d AND target_type = %s AND target_id = %d", $user_id, $type, $target_id ) );
		if ( $existing ) {
			$deleted = $wpdb->delete( $table, $where, array( '%d', '%s', '%d' ) );
			if ( false === $deleted ) { return new WP_Error( 'notifyflow_follow_failed', __( 'Could not update this follow.', 'notifyflow' ), array( 'status' => 500 ) ); }
			$following = false;
		} else {
			$inserted = $wpdb->insert( $table, $where + array( 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%s', '%d', '%s' ) );
			if ( false === $inserted ) { return new WP_Error( 'notifyflow_follow_failed', __( 'Could not update this follow.', 'notifyflow' ), array( 'status' => 500 ) ); }
			$following = true;
		}
		return rest_ensure_response( array( 'following' => $following, 'target_type' => $type, 'target_id' => $target_id ) );
	}

	/** Public developer API: create a validated custom on-site notification. */


	public static function create_notification( $recipient_user_id, $actor_user_id, $object_id, $post_id ) {
		$recipient_user_id = absint( $recipient_user_id ); $actor_user_id = absint( $actor_user_id ); $post_id = absint( $post_id ); $object_id = absint( $object_id );
		 $post = $post_id ? get_post( $post_id ) : null;
		if ( ! $recipient_user_id || ! get_userdata( $recipient_user_id ) || ! $object_id || ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status || ( $actor_user_id && ! get_userdata( $actor_user_id ) ) ) { return new WP_Error( 'notifyflow_invalid_notification', __( 'A valid recipient, actor, object, and published post are required.', 'notifyflow' ) ); }
		$result = self::insert_notification( $recipient_user_id, $actor_user_id, 'custom', $object_id, $post_id );
		return 1 === (int) $result;
	}


	public static function default_preferences() {
		return array( 'comment' => true, 'reply' => true, 'mention' => true, 'reaction_like' => true, 'follow_author' => true, 'follow_topic' => true, 'custom' => true );
	}


	public static function rest_get_preferences() {
		$saved = get_user_meta( get_current_user_id(), 'notifyflow_preferences', true );
		$saved = is_array( $saved ) ? $saved : array();
		return rest_ensure_response( wp_parse_args( $saved, self::default_preferences() ) );
	}


	public static function rest_save_preferences( $request ) {
		$current = self::rest_get_preferences()->get_data();
		foreach ( array_keys( self::default_preferences() ) as $type ) {
			$value = $request->get_param( $type );
			if ( null !== $value ) { $current[ $type ] = rest_sanitize_boolean( $value ); }
		}
		update_user_meta( get_current_user_id(), 'notifyflow_preferences', $current );
		return rest_ensure_response( $current );
	}


	public static function rest_require_login() {
		return is_user_logged_in();
	}

	/** Return the display label and emoji for a stored reaction type. */


	private static function reaction_details( $type ) {
		$map = array(
			'like'      => array( 'emoji' => '👍', 'label' => __( 'Like', 'notifyflow' ) ),
			'haha'      => array( 'emoji' => '😂', 'label' => __( 'Haha', 'notifyflow' ) ),
			'angry'     => array( 'emoji' => '😡', 'label' => __( 'Angry', 'notifyflow' ) ),
			'cry'       => array( 'emoji' => '😢', 'label' => __( 'Cry', 'notifyflow' ) ),
			'care'      => array( 'emoji' => '🥰', 'label' => __( 'Care', 'notifyflow' ) ),
			// Preserve readable labels for reactions created by older plugin versions.
			'love'      => array( 'emoji' => '❤️', 'label' => __( 'Love', 'notifyflow' ) ),
			'celebrate' => array( 'emoji' => '🎉', 'label' => __( 'Celebrate', 'notifyflow' ) ),
		);
		return isset( $map[$type] ) ? $map[$type] : $map['like'];
	}


	public static function rest_get_notifications( $request ) {
		global $wpdb;

		$user_id  = get_current_user_id();
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = min( 50, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = self::table_name();

		$type = sanitize_key( $request->get_param( 'type' ) );
		$where = 'recipient_user_id = %d';
		$args  = array( $user_id );
		if ( 'unread' === $type ) { $where .= ' AND read_at IS NULL'; }
		elseif ( in_array( $type, array( 'comment', 'reply', 'mention', 'reaction_like', 'follow_author', 'follow_topic', 'custom' ), true ) ) { $where .= ' AND notification_type = %s'; $args[] = $type; }
		$args[] = $per_page + 1; $args[] = $offset;
		$sql = "SELECT id, actor_user_id, notification_type, object_id, post_id, created_at, read_at FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
		$has_more = count( $rows ) > $per_page;
		if ( $has_more ) { $rows = array_slice( $rows, 0, $per_page ); }

		$items = array();
		foreach ( $rows as $row ) {
			$actor   = get_userdata( absint( $row->actor_user_id ) );
			$post    = get_post( absint( $row->post_id ) );
			if ( ! ( $post instanceof WP_Post ) || ! is_post_publicly_viewable( $post ) ) { $post = null; }
			$comment = get_comment( absint( $row->object_id ) );
			$type = sanitize_key( $row->notification_type );
			$reaction_type = 'like';
			if ( 'reaction_like' === $type ) {
				$reaction_row = $wpdb->get_row( $wpdb->prepare( 'SELECT reaction_type FROM ' . $wpdb->prefix . 'notifyflow_reactions WHERE id = %d', absint( $row->object_id ) ) );
				if ( $reaction_row ) { $reaction_type = sanitize_key( $reaction_row->reaction_type ); }
			}
			$reaction_details = self::reaction_details( $reaction_type );

			$type          = sanitize_key( $row->notification_type );
			$actor_name    = $actor ? $actor->display_name : __( 'Someone', 'notifyflow' );
			$parent_comment = ( $comment && absint( $comment->comment_parent ) ) ? get_comment( absint( $comment->comment_parent ) ) : null;
			if ( 'reply' === $type ) {
				$message = ( $parent_comment && absint( $parent_comment->user_id ) === get_current_user_id() )
					? sprintf( __( '%s replied to your comment.', 'notifyflow' ), $actor_name )
					: sprintf( __( '%s replied to a discussion on your post.', 'notifyflow' ), $actor_name );
			} elseif ( 'mention' === $type ) {
				$message = sprintf( __( '%s mentioned you in a comment.', 'notifyflow' ), $actor_name );
			} elseif ( 'reaction_like' === $type ) {
				$message = sprintf( __( '%1$s reacted with %2$s %3$s to your post.', 'notifyflow' ), $actor_name, $reaction_details['emoji'], $reaction_details['label'] );
			} elseif ( 'follow_author' === $type ) {
				$message = sprintf( __( 'New post from %s you follow.', 'notifyflow' ), $actor_name );
			} elseif ( 'follow_topic' === $type ) {
				$message = sprintf( __( 'New post in a topic you follow: %s.', 'notifyflow' ), $post ? get_the_title( $post ) : '' );
			} elseif ( 'custom' === $type ) {
				$message = sprintf( __( '%s shared an update with you.', 'notifyflow' ), $actor_name );
			} else {
				$message = sprintf( __( '%s commented on your post.', 'notifyflow' ), $actor_name );
			}

			$items[] = array(
				'id'           => absint( $row->id ),
				'type'         => $type,
				'actor_name'   => $actor_name,
				'actor_avatar' => self::get_actor_avatar_url( $actor ? absint( $actor->ID ) : 0 ),
				'reaction_type' => 'reaction_like' === $type ? $reaction_type : '',
				'reaction_emoji' => 'reaction_like' === $type ? $reaction_details['emoji'] : '',
				'reaction_label' => 'reaction_like' === $type ? $reaction_details['label'] : '',
				'post_title'   => $post ? get_the_title( $post ) : '',
				'message'      => $message,
				'url'          => ( in_array( $type, array( 'comment', 'reply', 'mention' ), true ) && $comment && $post ) ? get_comment_link( $comment ) : ( $post ? get_permalink( $post ) : '' ),
				'created_at'   => mysql2date( 'c', $row->created_at, false ),
				'is_read'      => null !== $row->read_at,
			);
		}

		// Group repeated likes on the same post in this page into one readable feed item.
		$grouped = array(); $group_indexes = array();
		foreach ( $items as $item ) {
			if ( 'reaction_like' === $item['type'] ) {
				$key = 'reaction_like:' . absint( $item['post_id'] ) . ':' . sanitize_key( $item['reaction_type'] );
				if ( isset( $group_indexes[$key] ) ) { $idx = $group_indexes[$key]; $grouped[$idx]['group_count']++; $grouped[$idx]['notification_ids'][] = $item['id']; $grouped[$idx]['actor_names'][] = $item['actor_name']; $others = $grouped[$idx]['group_count'] - 1; $grouped[$idx]['message'] = $others === 1 ? sprintf( __( '%1$s and 1 other reacted with %2$s %3$s to your post.', 'notifyflow' ), $grouped[$idx]['actor_name'], $grouped[$idx]['reaction_emoji'], $grouped[$idx]['reaction_label'] ) : sprintf( __( '%1$s and %2$d others reacted with %3$s %4$s to your post.', 'notifyflow' ), $grouped[$idx]['actor_name'], $others, $grouped[$idx]['reaction_emoji'], $grouped[$idx]['reaction_label'] ); $grouped[$idx]['is_read'] = $grouped[$idx]['is_read'] && $item['is_read']; continue; }
				$item['group_count'] = 1; $item['notification_ids'] = array( $item['id'] ); $item['actor_names'] = array( $item['actor_name'] ); $group_indexes[$key] = count( $grouped );
			}
			$grouped[] = $item;
		}
		$items = array_values( $grouped );
		return rest_ensure_response(
			array(
				'items'    => $items,
				'page'     => $page,
				'per_page' => $per_page,
				'has_more' => $has_more,
			)
		);
	}


	public static function rest_get_unread_count() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE recipient_user_id = %d AND read_at IS NULL',
				get_current_user_id()
			)
		);

		return rest_ensure_response( array( 'unread_count' => absint( $count ) ) );
	}


	public static function rest_mark_read( $request ) {
		global $wpdb;

		$notification_id = absint( $request['id'] );
		$user_id         = get_current_user_id();

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table_name() . ' SET read_at = %s WHERE id = %d AND recipient_user_id = %d AND read_at IS NULL',
				current_time( 'mysql', true ),
				$notification_id,
				$user_id
			)
		);

		if ( false === $updated ) {
			return new WP_Error( 'notifyflow_database_error', __( 'Could not update the notification.', 'notifyflow' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}


	public static function rest_mark_all_read() {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table_name() . ' SET read_at = %s WHERE recipient_user_id = %d AND read_at IS NULL',
				current_time( 'mysql', true ),
				get_current_user_id()
			)
		);

		if ( false === $updated ) {
			return new WP_Error( 'notifyflow_database_error', __( 'Could not update notifications.', 'notifyflow' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

}
