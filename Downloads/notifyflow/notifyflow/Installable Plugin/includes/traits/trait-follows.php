<?php
/** NotifyFlow Follows Trait for NotifyFlow. */
defined( 'ABSPATH' ) || exit;

trait NotifyFlow_Follows_Trait {

	public static function handle_published_post( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! ( $post instanceof WP_Post ) || 'post' !== $post->post_type ) {
			return;
		}
		// Do not query followed categories here: some publishing workflows fire this
		// transition before the final category relationships are available.
		self::$pending_published_posts[ absint( $post->ID ) ] = true;
	}

	/** Process queued publish events after taxonomy relationships have been saved. */


	public static function flush_pending_published_posts( $post_id = 0, $post = null, $update = null, $post_before = null ) {
		static $flushing = false;
		if ( $flushing || empty( self::$pending_published_posts ) ) {
			return;
		}
		$flushing = true;
		$ids = $post_id ? array( absint( $post_id ) ) : array_keys( self::$pending_published_posts );
		foreach ( $ids as $id ) {
			if ( empty( self::$pending_published_posts[ $id ] ) ) {
				continue;
			}
			unset( self::$pending_published_posts[ $id ] );
			$latest_post = get_post( $id );
			if ( $latest_post instanceof WP_Post && 'publish' === $latest_post->post_status && 'post' === $latest_post->post_type ) {
				self::notify_followers_for_published_post( $latest_post );
			}
		}
		$flushing = false;
	}

	/** Build recipients independently for authors, categories, and tags. Topic follows do not depend on author follows. */


	private static function notify_followers_for_published_post( $post ) {
		$settings = self::settings();
		if ( empty( $settings['enable_follows'] ) || ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status || 'post' !== $post->post_type ) { return; }
		global $wpdb;
		$table = $wpdb->prefix . 'notifyflow_follows';
		$author_id = absint( $post->post_author );
		$recipients = array();

		// Author followers are one independent recipient source.
		$author_ids = $wpdb->get_col( $wpdb->prepare( "SELECT follower_user_id FROM {$table} WHERE target_type = %s AND target_id = %d", 'user', $author_id ) );
		foreach ( (array) $author_ids as $recipient_id ) {
			$recipient_id = absint( $recipient_id );
			if ( $recipient_id ) { $recipients[ $recipient_id ] = 'follow_author'; }
		}

		// Query category and tag IDs separately: term IDs can overlap between taxonomies.
		$category_ids = wp_get_object_terms( $post->ID, 'category', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $category_ids ) && ! empty( $category_ids ) ) {
			$category_ids = array_values( array_unique( array_filter( array_map( 'absint', $category_ids ) ) ) );
			if ( $category_ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
				$sql = $wpdb->prepare( "SELECT DISTINCT follower_user_id FROM {$table} WHERE target_type = %s AND target_id IN ({$placeholders})", array_merge( array( 'category' ), $category_ids ) );
				foreach ( (array) $wpdb->get_col( $sql ) as $recipient_id ) {
					$recipient_id = absint( $recipient_id );
					if ( $recipient_id && ! isset( $recipients[ $recipient_id ] ) ) { $recipients[ $recipient_id ] = 'follow_topic'; }
				}
			}
		}

		$tag_ids = wp_get_object_terms( $post->ID, 'post_tag', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $tag_ids ) && ! empty( $tag_ids ) ) {
			$tag_ids = array_values( array_unique( array_filter( array_map( 'absint', $tag_ids ) ) ) );
			if ( $tag_ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $tag_ids ), '%d' ) );
				$sql = $wpdb->prepare( "SELECT DISTINCT follower_user_id FROM {$table} WHERE target_type = %s AND target_id IN ({$placeholders})", array_merge( array( 'tag' ), $tag_ids ) );
				foreach ( (array) $wpdb->get_col( $sql ) as $recipient_id ) {
					$recipient_id = absint( $recipient_id );
					if ( $recipient_id && ! isset( $recipients[ $recipient_id ] ) ) { $recipients[ $recipient_id ] = 'follow_topic'; }
				}
			}
		}

		foreach ( $recipients as $recipient_id => $type ) {
			if ( ! $recipient_id || $recipient_id === $author_id ) { continue; }
			self::insert_notification( $recipient_id, $author_id, $type, absint( $post->ID ), absint( $post->ID ) );
		}
	}


	public static function render_reactions_shortcode( $atts = array() ) {
		$settings = self::settings();
		if ( empty( $settings['enable_reactions'] ) || ! is_user_logged_in() || ! is_singular() ) { return ''; }
		$post_id = absint( get_queried_object_id() );
		if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) { return ''; }
		global $wpdb;
		$table = $wpdb->prefix . 'notifyflow_reactions';
		$count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE post_id = %d", $post_id ) ) );
		$mine = $wpdb->get_var( $wpdb->prepare( "SELECT reaction_type FROM {$table} WHERE post_id = %d AND user_id = %d", $post_id, get_current_user_id() ) );
		wp_enqueue_script( 'notifyflow-frontend' ); wp_enqueue_style( 'notifyflow-frontend' );
		$out = '<div class="notifyflow-reactions" data-nf-reaction data-post-id="' . esc_attr( $post_id ) . '">';
		foreach ( array( 'like' => array( '👍', __( 'Like', 'notifyflow' ) ), 'haha' => array( '😂', __( 'Haha', 'notifyflow' ) ), 'angry' => array( '😡', __( 'Angry', 'notifyflow' ) ), 'cry' => array( '😢', __( 'Cry', 'notifyflow' ) ), 'care' => array( '🥰', __( 'Care', 'notifyflow' ) ) ) as $reaction => $details ) { $out .= '<button type="button" data-reaction="' . esc_attr( $reaction ) . '" data-reaction-label="' . esc_attr( $details[1] ) . '" aria-label="' . esc_attr( $details[1] ) . '" aria-pressed="' . ( $mine === $reaction ? 'true' : 'false' ) . '"><span class="notifyflow-reaction-emoji" aria-hidden="true">' . esc_html( $details[0] ) . '</span><span class="notifyflow-reaction-label">' . esc_html( $details[1] ) . '</span></button> '; }
		return $out . '<span data-reaction-count>' . esc_html( number_format_i18n( $count ) ) . '</span> <span data-reaction-status aria-live="polite"></span></div>';
	}


	/** Render the author-follow control independently from category controls. */
	public static function render_author_shortcode( $atts = array() ) {
		if ( ! self::follow_shortcode_available() ) { return ''; }
		$post = get_post( absint( get_queried_object_id() ) );
		if ( ! $post || absint( $post->post_author ) === get_current_user_id() ) { return ''; }
		global $wpdb;
		$table = $wpdb->prefix . 'notifyflow_follows';
		$following = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE follower_user_id = %d AND target_type = %s AND target_id = %d LIMIT 1", get_current_user_id(), 'user', absint( $post->post_author ) ) );
		$settings = self::settings();
		wp_enqueue_script( 'notifyflow-frontend' ); wp_enqueue_style( 'notifyflow-frontend' );
		return '<div class="notifyflow-follow notifyflow-follow--author" data-nf-follow style="--nf-accent:' . esc_attr( $settings['accent_color'] ) . '"><button type="button" data-follow-type="user" data-follow-id="' . esc_attr( absint( $post->post_author ) ) . '" data-follow-label="' . esc_attr__( 'Follow', 'notifyflow' ) . '" data-following-label="' . esc_attr__( 'Following', 'notifyflow' ) . '" aria-pressed="' . ( $following ? 'true' : 'false' ) . '">' . ( $following ? esc_html__( 'Following', 'notifyflow' ) : esc_html__( 'Follow', 'notifyflow' ) ) . '</button><span data-follow-status aria-live="polite"></span></div>';
	}

	/** Render a separate Follow control for each category assigned to the current post. */
	public static function render_category_shortcode( $atts = array() ) {
		if ( ! self::follow_shortcode_available() ) { return ''; }
		$post_id = absint( get_queried_object_id() );
		$terms = wp_get_post_terms( $post_id, 'category' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) { return ''; }
		global $wpdb;
		$table = $wpdb->prefix . 'notifyflow_follows';
		$user_id = get_current_user_id();
		$settings = self::settings();
		wp_enqueue_script( 'notifyflow-frontend' ); wp_enqueue_style( 'notifyflow-frontend' );
		$out = '<div class="notifyflow-follow notifyflow-follow--categories" data-nf-follow style="--nf-accent:' . esc_attr( $settings['accent_color'] ) . '">';
		foreach ( $terms as $term ) {
			$term_id = absint( $term->term_id );
			$following = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE follower_user_id = %d AND target_type = %s AND target_id = %d LIMIT 1", $user_id, 'category', $term_id ) );
			$out .= '<span class="notifyflow-follow__category"><span class="notifyflow-follow__category-name">' . esc_html( $term->name ) . '</span><button type="button" data-follow-type="category" data-follow-id="' . esc_attr( $term_id ) . '" data-follow-label="' . esc_attr__( 'Follow', 'notifyflow' ) . '" data-following-label="' . esc_attr__( 'Following', 'notifyflow' ) . '" aria-label="' . esc_attr( sprintf( __( 'Follow category: %s', 'notifyflow' ), $term->name ) ) . '" aria-pressed="' . ( $following ? 'true' : 'false' ) . '">' . ( $following ? esc_html__( 'Following', 'notifyflow' ) : esc_html__( 'Follow', 'notifyflow' ) ) . '</button></span>';
		}
		return $out . '<span data-follow-status aria-live="polite"></span></div>';
	}

	/** Backward-compatible legacy shortcode: render both follow controls together. */
	public static function render_follow_shortcode( $atts = array() ) {
		return self::render_author_shortcode( $atts ) . self::render_category_shortcode( $atts );
	}

	private static function follow_shortcode_available() {
		return ! empty( self::settings()['enable_follows'] ) && is_user_logged_in() && is_singular( 'post' );
	}

	/** Handle comments inserted directly in an approved state. */

}
