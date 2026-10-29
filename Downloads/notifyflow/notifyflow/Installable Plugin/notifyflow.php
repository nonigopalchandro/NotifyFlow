<?php
/**
 * Plugin Name: NotifyFlow
 * Description: A customizable WordPress notification center with animated social reactions, follows, comments, replies, and mentions.
 * Version:     1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author:      Noni Gopal Chandro
 * Author URI:  https://codecanyon.net/user/noni_gopal_chandro/portfolio
 * Text Domain: notifyflow
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'NOTIFYFLOW_VERSION', '1.0.0' );
define( 'NOTIFYFLOW_SCHEMA_VERSION', '6' );
define( 'NOTIFYFLOW_PLUGIN_FILE', __FILE__ );
define( 'NOTIFYFLOW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


define( 'NOTIFYFLOW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );


require_once NOTIFYFLOW_PLUGIN_DIR . 'includes/traits/trait-database.php';
require_once NOTIFYFLOW_PLUGIN_DIR . 'includes/traits/trait-settings.php';
require_once NOTIFYFLOW_PLUGIN_DIR . 'includes/traits/trait-follows.php';
require_once NOTIFYFLOW_PLUGIN_DIR . 'includes/traits/trait-comments.php';
require_once NOTIFYFLOW_PLUGIN_DIR . 'includes/traits/trait-rest-api.php';
require_once NOTIFYFLOW_PLUGIN_DIR . 'includes/traits/trait-frontend.php';
require_once NOTIFYFLOW_PLUGIN_DIR . 'includes/traits/trait-privacy.php';

final class NotifyFlow_Plugin {
    use NotifyFlow_Database_Trait;
    use NotifyFlow_Settings_Trait;
    use NotifyFlow_Follows_Trait;
    use NotifyFlow_Comments_Trait;
    use NotifyFlow_REST_API_Trait;
    use NotifyFlow_Frontend_Trait;
    use NotifyFlow_Privacy_Trait;

    const DB_VERSION_OPTION = 'notifyflow_db_version';
    const REST_NAMESPACE    = 'notifyflow/v1';

    private static $pending_published_posts = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade_schema' ) );
		add_action( 'init', array( __CLASS__, 'schedule_retention_cleanup' ) );
		add_action( 'notifyflow_daily_cleanup', array( __CLASS__, 'cleanup_old_notifications' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'handle_published_post' ), 10, 3 );
		// Process follow notifications after WordPress has finished saving post terms and metadata.
		add_action( 'wp_after_insert_post', array( __CLASS__, 'flush_pending_published_posts' ), 99, 4 );
		// Fallback for publishing flows that do not reach wp_after_insert_post in the expected order.
		add_action( 'shutdown', array( __CLASS__, 'flush_pending_published_posts' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_settings' ) );
		add_action( 'transition_comment_status', array( __CLASS__, 'handle_comment_status_change' ), 10, 3 );
		// Also catch comments created already approved; INSERT IGNORE and the unique key prevent duplicates.
		add_action( 'comment_post', array( __CLASS__, 'handle_new_comment' ), 20, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend_assets' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_privacy_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_privacy_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_action( 'delete_user', array( __CLASS__, 'cleanup_deleted_user_data' ), 10, 1 );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_deleted_post_data' ), 10, 2 );
		add_action( 'delete_comment', array( __CLASS__, 'cleanup_deleted_comment_data' ), 10, 2 );
		add_action( 'delete_term', array( __CLASS__, 'cleanup_deleted_term_follow_data' ), 10, 5 );
		add_shortcode( 'notifyflow_bell', array( __CLASS__, 'render_bell_shortcode' ) );
		add_shortcode( 'notifyflow_reactions', array( __CLASS__, 'render_reactions_shortcode' ) );
		add_shortcode( 'notifyflow_author', array( __CLASS__, 'render_author_shortcode' ) );
		add_shortcode( 'notifyflow_category', array( __CLASS__, 'render_category_shortcode' ) );
		add_shortcode( 'notifyflow_follow', array( __CLASS__, 'render_follow_shortcode' ) ); // Backward compatibility.
		add_filter( 'script_loader_tag', array( __CLASS__, 'add_script_config_attribute' ), 10, 3 );
	}

}

register_activation_hook( __FILE__, array( 'NotifyFlow_Plugin', 'install_schema' ) );
register_deactivation_hook( __FILE__, function () { $timestamp = wp_next_scheduled( 'notifyflow_daily_cleanup' ); if ( $timestamp ) { wp_unschedule_event( $timestamp, 'notifyflow_daily_cleanup' ); } } );
NotifyFlow_Plugin::init();
