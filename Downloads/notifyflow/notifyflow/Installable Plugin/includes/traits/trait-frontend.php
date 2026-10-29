<?php
/** NotifyFlow Frontend Trait for NotifyFlow. */
defined( 'ABSPATH' ) || exit;

trait NotifyFlow_Frontend_Trait {

	public static function register_frontend_assets() {
		wp_register_style(
			'notifyflow-frontend',
			NOTIFYFLOW_PLUGIN_URL . 'assets/notifyflow.css',
			array(),
			NOTIFYFLOW_VERSION
		);

		wp_register_script(
			'notifyflow-frontend',
			NOTIFYFLOW_PLUGIN_URL . 'assets/notifyflow.js',
			array(),
			NOTIFYFLOW_VERSION,
			true
		);
	}


	public static function render_bell_shortcode( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		wp_enqueue_style( 'notifyflow-frontend' );
		wp_enqueue_script( 'notifyflow-frontend' );

		$atts = shortcode_atts(
			array(
				'label' => __( 'Notifications', 'notifyflow' ),
				'position' => 'right',
			),
			$atts,
			'notifyflow_bell'
		);

		$settings = self::settings();
		$config = array(
			'accentColor' => $settings['accent_color'],
			'panelWidth' => absint( $settings['panel_width'] ),
			'restRoot' => esc_url_raw( rest_url( self::REST_NAMESPACE . '/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'pollMs'   => 5000,
			'strings'  => array(
				'title'       => __( 'Notifications', 'notifyflow' ),
				'markAllRead' => __( 'Mark all as read', 'notifyflow' ),
				'empty'       => __( 'You’re all caught up.', 'notifyflow' ),
				'loading'     => __( 'Loading notifications…', 'notifyflow' ),
				'error'       => __( 'Could not load notifications. Please try again.', 'notifyflow' ),
				'viewPost'    => __( 'Open discussion', 'notifyflow' ),
				'close'       => __( 'Close notifications', 'notifyflow' ),
				'unreadCount' => __( '%d unread', 'notifyflow' ),
				'justNow' => __( 'Just now', 'notifyflow' ),
				'minutesAgo' => __( '%d m ago', 'notifyflow' ),
				'hoursAgo' => __( '%d h ago', 'notifyflow' ),
				'daysAgo' => __( '%d d ago', 'notifyflow' ),
				'newActivity' => __( 'New activity', 'notifyflow' ),
				'couldNotLoad' => __( 'Could not load notifications. Please try again.', 'notifyflow' ),
				'couldNotLoadPreferences' => __( 'Could not load preferences.', 'notifyflow' ),
				'couldNotSavePreferences' => __( 'Could not save preferences. Please try again.', 'notifyflow' ),
				'saving' => __( 'Saving…', 'notifyflow' ),
				'preferencesSaved' => __( 'Preferences saved.', 'notifyflow' ),
				'couldNotUpdate' => __( 'Could not update notifications.', 'notifyflow' ),
				'reactionSaved' => __( 'Reaction saved.', 'notifyflow' ),
				'reactionRemoved' => __( 'Reaction removed.', 'notifyflow' ),
				'couldNotSaveReaction' => __( 'Could not save your reaction. Please try again.', 'notifyflow' ),
				'followSaved' => __( 'Follow saved.', 'notifyflow' ),
				'followRemoved' => __( 'Follow removed.', 'notifyflow' ),
				'couldNotUpdateFollow' => __( 'Could not update follow preference.', 'notifyflow' ),
				'openDiscussion' => __( 'Open discussion', 'notifyflow' ),
				'reaction' => __( 'Reaction', 'notifyflow' ),
				'unread' => __( 'unread', 'notifyflow' ),
			),
		);

		$position = in_array( $atts['position'], array( 'left', 'right' ), true ) ? $atts['position'] : 'right';
		$label    = sanitize_text_field( $atts['label'] );

		ob_start();
		?>
		<div class="notifyflow" data-notifyflow data-position="<?php echo esc_attr( $position ); ?>" style="--nf-accent:<?php echo esc_attr( $settings['accent_color'] ); ?>;--nf-panel-width:<?php echo esc_attr( absint( $settings['panel_width'] ) ); ?>px" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<button class="notifyflow__trigger" type="button" aria-haspopup="dialog" aria-expanded="false" aria-label="<?php echo esc_attr( $label ); ?>">
				<svg class="notifyflow__bell-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
				</svg>
				<span class="notifyflow__badge" data-unread-count hidden>0</span>
			</button>
			<section class="notifyflow__panel" data-panel role="dialog" aria-label="<?php esc_attr_e( 'Notifications', 'notifyflow' ); ?>" hidden>
				<header class="notifyflow__header">
					<div>
						<h2><?php esc_html_e( 'Notifications', 'notifyflow' ); ?></h2>
						<p><?php esc_html_e( 'Your latest activity', 'notifyflow' ); ?></p>
					</div>
					<button class="notifyflow__icon-button" type="button" data-close aria-label="<?php esc_attr_e( 'Close notifications', 'notifyflow' ); ?>">×</button>
				</header>
				<div class="notifyflow__toolbar">
					<span data-feed-status><?php esc_html_e( 'Recent activity', 'notifyflow' ); ?></span>
					<button type="button" class="notifyflow__text-button" data-preferences-toggle><?php esc_html_e( 'Preferences', 'notifyflow' ); ?></button>
					<button type="button" class="notifyflow__text-button" data-mark-all><?php esc_html_e( 'Mark all as read', 'notifyflow' ); ?></button>
				</div>
				<nav class="notifyflow__filters" aria-label="<?php esc_attr_e( 'Filter notifications', 'notifyflow' ); ?>" data-filters>
					<button type="button" class="is-active" data-filter="all"><?php esc_html_e( 'All', 'notifyflow' ); ?></button>
					<button type="button" data-filter="unread"><?php esc_html_e( 'Unread', 'notifyflow' ); ?></button>
					<button type="button" data-filter="comment"><?php esc_html_e( 'Comments', 'notifyflow' ); ?></button>
					<button type="button" data-filter="reply"><?php esc_html_e( 'Replies', 'notifyflow' ); ?></button>
					<button type="button" data-filter="mention"><?php esc_html_e( 'Mentions', 'notifyflow' ); ?></button>
					<button type="button" data-filter="reaction_like"><?php esc_html_e( 'Likes', 'notifyflow' ); ?></button>
					<button type="button" data-filter="follow_author"><?php esc_html_e( 'Authors', 'notifyflow' ); ?></button>
					<button type="button" data-filter="follow_topic"><?php esc_html_e( 'Topics', 'notifyflow' ); ?></button>
				</nav>
				<section class="notifyflow__preferences" data-preferences hidden>
					<strong><?php esc_html_e( 'Notification preferences', 'notifyflow' ); ?></strong>
					<label><input type="checkbox" data-pref="comment" checked> <?php esc_html_e( 'Post comments', 'notifyflow' ); ?></label>
					<label><input type="checkbox" data-pref="reply" checked> <?php esc_html_e( 'Comment replies', 'notifyflow' ); ?></label>
					<label><input type="checkbox" data-pref="mention" checked> <?php esc_html_e( 'Mentions', 'notifyflow' ); ?></label>
				<label><input type="checkbox" data-pref="reaction_like" checked> <?php esc_html_e( 'Likes', 'notifyflow' ); ?></label>
				<label><input type="checkbox" data-pref="follow_author" checked> <?php esc_html_e( 'Posts from followed authors', 'notifyflow' ); ?></label>
				<label><input type="checkbox" data-pref="follow_topic" checked> <?php esc_html_e( 'Posts in followed topics', 'notifyflow' ); ?></label>
				<label><input type="checkbox" data-pref="custom" checked> <?php esc_html_e( 'Custom activity', 'notifyflow' ); ?></label>
					<span data-pref-status aria-live="polite"></span>
				</section>
				<div class="notifyflow__feed" data-feed aria-live="polite">
					<div class="notifyflow__state"><?php esc_html_e( 'Loading notifications…', 'notifyflow' ); ?></div>
				</div>
				<button type="button" class="notifyflow__load-more" data-load-more hidden><?php esc_html_e( 'Load more', 'notifyflow' ); ?></button>
				<footer class="notifyflow__footer">
					<span><?php esc_html_e( 'Updates automatically', 'notifyflow' ); ?></span>
					<span class="notifyflow__live-dot" aria-hidden="true"></span>
				</footer>
			</section>
			<div class="notifyflow__toast" data-toast role="status" aria-live="polite" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Avoid an unnecessary config attribute; retained as a safe hook point for future script strategies.
	 */


	public static function add_script_config_attribute( $tag, $handle, $src ) {
		return $tag;
	}

}
