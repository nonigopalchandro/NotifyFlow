<?php
/** NotifyFlow Settings Trait for NotifyFlow. */
defined( 'ABSPATH' ) || exit;

trait NotifyFlow_Settings_Trait {

	public static function default_settings() {
		return array(
			'accent_color'             => '#635bff',
			'panel_width'              => 390,
			'retention_days'           => 0,
			'enable_reactions'          => 1,
			'enable_follows'            => 1,
			'delete_data_on_uninstall' => 0,
		);
	}

	public static function settings() {
		$saved = get_option( 'notifyflow_settings', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	public static function handle_admin_settings() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || empty( $_POST['notifyflow_save_settings'] ) ) {
			return;
		}

		check_admin_referer( 'notifyflow_save_settings_action', 'notifyflow_settings_nonce' );
		$settings = self::settings();
		$color = isset( $_POST['accent_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['accent_color'] ) ) : $settings['accent_color'];
		$settings['accent_color'] = $color ? $color : '#635bff';
		$settings['panel_width'] = isset( $_POST['panel_width'] ) ? min( 560, max( 320, absint( wp_unslash( $_POST['panel_width'] ) ) ) ) : 390;
		$settings['retention_days'] = isset( $_POST['retention_days'] ) ? min( 3650, max( 0, absint( wp_unslash( $_POST['retention_days'] ) ) ) ) : 0;

		foreach ( array( 'enable_reactions', 'enable_follows', 'delete_data_on_uninstall' ) as $key ) {
			$settings[ $key ] = isset( $_POST[ $key ] ) ? 1 : 0;
		}

		update_option( 'notifyflow_settings', $settings, false );
		wp_safe_redirect( add_query_arg( 'notifyflow_saved', '1', admin_url( 'options-general.php?page=notifyflow' ) ) );
		exit;
	}

	public static function schedule_retention_cleanup() {
		if ( ! wp_next_scheduled( 'notifyflow_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'notifyflow_daily_cleanup' );
		}
	}

	public static function cleanup_old_notifications() {
		global $wpdb;
		$days = absint( self::settings()['retention_days'] );
		if ( $days < 1 ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . ' WHERE created_at < %s LIMIT 5000', gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $days ) ) );
	}

	public static function register_admin_page() {
		add_options_page(
			__( 'NotifyFlow', 'notifyflow' ),
			__( 'NotifyFlow', 'notifyflow' ),
			'manage_options',
			'notifyflow',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/** Load the modern admin stylesheet only on the NotifyFlow settings screen. */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_notifyflow' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'notifyflow-admin',
			NOTIFYFLOW_PLUGIN_URL . 'assets/notifyflow-admin.css',
			array(),
			NOTIFYFLOW_VERSION
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table_name = self::table_name();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) === $table_name;
		$count = 0;
		if ( $table_exists ) {
			$count = absint( $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" ) ); // Table name is derived from the trusted WP prefix and plugin suffix.
		}
		$settings = self::settings();
		$saved = isset( $_GET['notifyflow_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['notifyflow_saved'] ) );
		?>
		<div class="wrap notifyflow-admin">
			<header class="notifyflow-admin__hero">
				<div class="notifyflow-admin__hero-copy">
					<span class="notifyflow-admin__eyebrow"><?php esc_html_e( 'WORDPRESS NOTIFICATION CENTER', 'notifyflow' ); ?></span>
					<h1><?php esc_html_e( 'NotifyFlow', 'notifyflow' ); ?> <span class="notifyflow-admin__version">v<?php echo esc_html( NOTIFYFLOW_VERSION ); ?></span></h1>
					<p><?php esc_html_e( 'Manage your notification experience, social activity, appearance, and data lifecycle from one place.', 'notifyflow' ); ?></p>
				</div>
				<div class="notifyflow-admin__hero-mark" aria-hidden="true"><span class="dashicons dashicons-bell"></span></div>
			</header>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Your NotifyFlow settings have been saved.', 'notifyflow' ); ?></p></div>
			<?php endif; ?>

			<section class="notifyflow-admin__stats" aria-label="<?php esc_attr_e( 'Plugin status', 'notifyflow' ); ?>">
				<article class="notifyflow-admin__stat-card">
					<span class="notifyflow-admin__stat-icon dashicons dashicons-database" aria-hidden="true"></span>
					<div><span class="notifyflow-admin__stat-label"><?php esc_html_e( 'Notification storage', 'notifyflow' ); ?></span><strong><?php echo $table_exists ? esc_html__( 'Connected', 'notifyflow' ) : esc_html__( 'Table missing', 'notifyflow' ); ?></strong><small><?php esc_html_e( 'WordPress database', 'notifyflow' ); ?></small></div>
				</article>
				<article class="notifyflow-admin__stat-card">
					<span class="notifyflow-admin__stat-icon dashicons dashicons-bell" aria-hidden="true"></span>
					<div><span class="notifyflow-admin__stat-label"><?php esc_html_e( 'Stored notifications', 'notifyflow' ); ?></span><strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong><small><?php esc_html_e( 'Across all users', 'notifyflow' ); ?></small></div>
				</article>
				<article class="notifyflow-admin__stat-card">
					<span class="notifyflow-admin__stat-icon dashicons dashicons-admin-appearance" aria-hidden="true"></span>
					<div><span class="notifyflow-admin__stat-label"><?php esc_html_e( 'Accent color', 'notifyflow' ); ?></span><strong class="notifyflow-admin__color-value"><i style="--nf-admin-color:<?php echo esc_attr( $settings['accent_color'] ); ?>"></i><?php echo esc_html( strtoupper( $settings['accent_color'] ) ); ?></strong><small><?php esc_html_e( 'Current front-end theme', 'notifyflow' ); ?></small></div>
				</article>
			</section>

			<form class="notifyflow-admin__form" method="post" action="">
				<?php wp_nonce_field( 'notifyflow_save_settings_action', 'notifyflow_settings_nonce' ); ?>
				<div class="notifyflow-admin__main">
					<section class="notifyflow-admin__card">
						<div class="notifyflow-admin__card-heading"><span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Appearance', 'notifyflow' ); ?></h2><p><?php esc_html_e( 'Match the notification panel to your website design.', 'notifyflow' ); ?></p></div></div>
						<div class="notifyflow-admin__field">
							<label for="nf-accent-color"><?php esc_html_e( 'Accent color', 'notifyflow' ); ?></label>
							<div class="notifyflow-admin__color-control"><input id="nf-accent-color" type="color" name="accent_color" value="<?php echo esc_attr( $settings['accent_color'] ); ?>"><span><?php esc_html_e( 'Used for active filters, buttons, and highlights.', 'notifyflow' ); ?></span></div>
						</div>
						<div class="notifyflow-admin__field">
							<label for="nf-panel-width"><?php esc_html_e( 'Notification panel width', 'notifyflow' ); ?></label>
							<div class="notifyflow-admin__input-suffix"><input id="nf-panel-width" type="number" min="320" max="560" step="10" name="panel_width" value="<?php echo esc_attr( absint( $settings['panel_width'] ) ); ?>"><span>px</span></div>
							<p class="description"><?php esc_html_e( 'Choose a width between 320 and 560 pixels. Mobile layouts adapt to the screen width.', 'notifyflow' ); ?></p>
						</div>
					</section>

					<section class="notifyflow-admin__card">
						<div class="notifyflow-admin__card-heading"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Features', 'notifyflow' ); ?></h2><p><?php esc_html_e( 'Choose which social interaction tools are available on your site.', 'notifyflow' ); ?></p></div></div>
						<label class="notifyflow-admin__toggle"><span class="notifyflow-admin__toggle-copy"><strong><?php esc_html_e( 'Post reactions', 'notifyflow' ); ?></strong><small><?php esc_html_e( 'Allow logged-in users to react to supported posts.', 'notifyflow' ); ?></small></span><input type="checkbox" name="enable_reactions" value="1" <?php checked( ! empty( $settings['enable_reactions'] ) ); ?>><span class="notifyflow-admin__switch" aria-hidden="true"></span></label>
						<label class="notifyflow-admin__toggle"><span class="notifyflow-admin__toggle-copy"><strong><?php esc_html_e( 'Author and topic follows', 'notifyflow' ); ?></strong><small><?php esc_html_e( 'Allow users to follow authors, categories, and supported topics.', 'notifyflow' ); ?></small></span><input type="checkbox" name="enable_follows" value="1" <?php checked( ! empty( $settings['enable_follows'] ) ); ?>><span class="notifyflow-admin__switch" aria-hidden="true"></span></label>
					</section>

					<section class="notifyflow-admin__card">
						<div class="notifyflow-admin__card-heading"><span class="dashicons dashicons-clock" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Notification retention', 'notifyflow' ); ?></h2><p><?php esc_html_e( 'Control how long activity records are stored in your database.', 'notifyflow' ); ?></p></div></div>
						<div class="notifyflow-admin__field">
							<label for="nf-retention"><?php esc_html_e( 'Keep notifications for', 'notifyflow' ); ?></label>
							<div class="notifyflow-admin__input-suffix"><input id="nf-retention" type="number" min="0" max="3650" step="1" name="retention_days" value="<?php echo esc_attr( absint( $settings['retention_days'] ) ); ?>"><span><?php esc_html_e( 'days', 'notifyflow' ); ?></span></div>
							<p class="description"><?php esc_html_e( 'Use 0 to keep notifications indefinitely. Otherwise, a daily scheduled cleanup removes up to 5,000 expired notification records per run.', 'notifyflow' ); ?></p>
						</div>
					</section>

					<section class="notifyflow-admin__card notifyflow-admin__card--privacy">
						<div class="notifyflow-admin__card-heading"><span class="dashicons dashicons-lock" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Data and privacy', 'notifyflow' ); ?></h2><p><?php esc_html_e( 'Choose what happens to stored data when the plugin is removed.', 'notifyflow' ); ?></p></div></div>
						<label class="notifyflow-admin__toggle"><span class="notifyflow-admin__toggle-copy"><strong><?php esc_html_e( 'Delete data on uninstall', 'notifyflow' ); ?></strong><small><?php esc_html_e( 'When enabled, uninstalling NotifyFlow permanently deletes its tables, settings, and user preferences. Deactivation never deletes data.', 'notifyflow' ); ?></small></span><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>><span class="notifyflow-admin__switch" aria-hidden="true"></span></label>
					</section>
				</div>

				<aside class="notifyflow-admin__sidebar">
					<section class="notifyflow-admin__card notifyflow-admin__quick-card">
						<div class="notifyflow-admin__card-heading"><span class="dashicons dashicons-shortcode" aria-hidden="true"></span><div><h2><?php esc_html_e( 'Quick setup', 'notifyflow' ); ?></h2><p><?php esc_html_e( 'Add NotifyFlow to your pages.', 'notifyflow' ); ?></p></div></div>
						<div class="notifyflow-admin__shortcode"><code>[notifyflow_bell]</code><span><?php esc_html_e( 'Notification center', 'notifyflow' ); ?></span></div>
						<div class="notifyflow-admin__shortcode"><code>[notifyflow_reactions]</code><span><?php esc_html_e( 'Post reaction buttons', 'notifyflow' ); ?></span></div>
						<div class="notifyflow-admin__shortcode"><code>[notifyflow_author]</code><span><?php esc_html_e( 'Follow the current post author', 'notifyflow' ); ?></span></div>
						<div class="notifyflow-admin__shortcode"><code>[notifyflow_category]</code><span><?php esc_html_e( 'Follow categories on the current post', 'notifyflow' ); ?></span></div>
						<div class="notifyflow-admin__shortcode"><code>[notifyflow_follow]</code><span><?php esc_html_e( 'Legacy combined author and category controls', 'notifyflow' ); ?></span></div>
						<p class="notifyflow-admin__hint"><?php esc_html_e( 'Place shortcodes in a Shortcode block or a supported content area. Reaction and follow controls require a logged-in user.', 'notifyflow' ); ?></p>
					</section>
					<section class="notifyflow-admin__card notifyflow-admin__help-card">
						<div class="notifyflow-admin__card-heading"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><div><h2><?php esc_html_e( 'How it works', 'notifyflow' ); ?></h2><p><?php esc_html_e( 'A quick operational overview.', 'notifyflow' ); ?></p></div></div>
						<ul><li><?php esc_html_e( 'The feed updates in-page every five seconds while visible.', 'notifyflow' ); ?></li><li><?php esc_html_e( 'Notifications are stored in your WordPress database.', 'notifyflow' ); ?></li><li><?php esc_html_e( 'Browser push and external notification services are not required.', 'notifyflow' ); ?></li></ul>
					</section>
				</aside>

				<div class="notifyflow-admin__savebar"><span><?php esc_html_e( 'Changes apply to the front-end notification experience.', 'notifyflow' ); ?></span><button type="submit" class="button button-primary notifyflow-admin__save-button" name="notifyflow_save_settings" value="1"><span class="dashicons dashicons-saved" aria-hidden="true"></span><?php esc_html_e( 'Save settings', 'notifyflow' ); ?></button></div>
			</form>
		</div>
		<?php
	}
}
