<?php
/** NotifyFlow Comments Trait for NotifyFlow. */
defined( 'ABSPATH' ) || exit;

trait NotifyFlow_Comments_Trait {

	public static function handle_new_comment( $comment_id, $comment_approved, $commentdata = array() ) {
		$approved = in_array( strtolower( (string) $comment_approved ), array( '1', 'approved', 'approve' ), true );
		if ( ! $approved ) {
			return;
		}

		$comment = get_comment( absint( $comment_id ) );
		if ( $comment instanceof WP_Comment ) {
			// Reuse the same recipient logic. The old status is deliberately pending so
			// the normal approval-transition guard does not suppress this new comment.
			self::process_approved_comment( $comment );
		}
	}


	public static function handle_comment_status_change( $new_status, $old_status, $comment ) {
		$approved_statuses = array( '1', 'approved', 'approve' );
		$new_status        = strtolower( (string) $new_status );
		$old_status        = strtolower( (string) $old_status );

		if ( ! in_array( $new_status, $approved_statuses, true ) || in_array( $old_status, $approved_statuses, true ) ) {
			return;
		}
		if ( ! ( $comment instanceof WP_Comment ) ) {
			$comment = get_comment( absint( $comment ) );
		}
		if ( $comment instanceof WP_Comment ) {
			self::process_approved_comment( $comment );
		}
	}

	/** Create notifications for the post owner and the author of the parent comment. */


	private static function process_approved_comment( $comment ) {
		if ( ! ( $comment instanceof WP_Comment ) || ! in_array( (string) $comment->comment_type, array( '', 'comment' ), true ) ) {
			return;
		}
		if ( ! in_array( (string) $comment->comment_approved, array( '1', 'approved' ), true ) ) {
			return;
		}

		$actor_user_id = absint( $comment->user_id );
		$post_id       = absint( $comment->comment_post_ID );
		if ( ! $actor_user_id || ! $post_id ) {
			return; // Notifications are for registered, logged-in comment authors.
		}
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		$is_reply          = absint( $comment->comment_parent ) > 0;
		$notification_type = $is_reply ? 'reply' : 'comment';
		$recipients        = array();

		// The post owner is notified about every new comment, including nested replies.
		$post_author_id = absint( $post->post_author );
		if ( $post_author_id && $post_author_id !== $actor_user_id ) {
			$recipients[ $post_author_id ] = true;
		}

		// A reply notifies the author of the exact comment being replied to.
		if ( $is_reply ) {
			$parent_comment = get_comment( absint( $comment->comment_parent ) );
			if ( $parent_comment && in_array( (string) $parent_comment->comment_approved, array( '1', 'approved' ), true ) ) {
				$parent_author_id = absint( $parent_comment->user_id );
				if ( $parent_author_id && $parent_author_id !== $actor_user_id ) {
					$recipients[ $parent_author_id ] = true;
				}
			}
		}

		// Mentions notify mentioned users unless they already receive a comment/reply alert.
		foreach ( self::find_mentioned_user_ids( $comment->comment_content ) as $mentioned_user_id ) {
			if ( $mentioned_user_id !== $actor_user_id && ! isset( $recipients[ $mentioned_user_id ] ) ) {
				self::insert_notification( $mentioned_user_id, $actor_user_id, 'mention', $comment->comment_ID, $post_id );
			}
		}
		foreach ( array_keys( $recipients ) as $recipient_user_id ) {
			self::insert_notification( $recipient_user_id, $actor_user_id, $notification_type, $comment->comment_ID, $post_id );
		}
	}

	/** Insert one notification. The database unique key prevents duplicate event/recipient rows. */


	private static function insert_notification( $recipient_user_id, $actor_user_id, $type, $comment_id, $post_id ) {
		global $wpdb;

		if ( ! $recipient_user_id || $recipient_user_id === $actor_user_id ) {
			return false;
		}
		$prefs = get_user_meta( absint( $recipient_user_id ), 'notifyflow_preferences', true );
		$prefs = is_array( $prefs ) ? wp_parse_args( $prefs, self::default_preferences() ) : self::default_preferences();
		$preference_key = in_array( $type, array( 'comment', 'reply', 'mention', 'reaction_like', 'follow_author', 'follow_topic', 'custom' ), true ) ? $type : 'comment';
		if ( empty( $prefs[ $preference_key ] ) ) { return false; }

		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::table_name() . '
				(recipient_user_id, actor_user_id, notification_type, object_id, post_id, created_at, read_at)
				VALUES (%d, %d, %s, %d, %d, %s, NULL)',
				absint( $recipient_user_id ),
				absint( $actor_user_id ),
				sanitize_key( $type ),
				absint( $comment_id ),
				absint( $post_id ),
				current_time( 'mysql', true )
			)
		);
		if ( 1 === (int) $inserted ) { do_action( 'notifyflow_notification_created', absint( $recipient_user_id ), absint( $actor_user_id ), sanitize_key( $type ), absint( $comment_id ), absint( $post_id ) ); }
		return $inserted;
	}

	/** Resolve @username references to existing WordPress users. */


	private static function find_mentioned_user_ids( $content ) {
		$ids = array();
		if ( ! is_string( $content ) || false === strpos( $content, '@' ) ) {
			return $ids;
		}

		if ( ! preg_match_all( '/(?<![A-Za-z0-9_.-])@([A-Za-z0-9_.-]{1,60})/', wp_strip_all_tags( $content ), $matches ) ) {
			return $ids;
		}

		foreach ( array_unique( $matches[1] ) as $username ) {
			$user = get_user_by( 'login', $username );
			if ( $user instanceof WP_User ) {
				$ids[] = absint( $user->ID );
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

}
