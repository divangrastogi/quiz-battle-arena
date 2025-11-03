<?php
/**
 * Plugin Name: Quiz Battle Arena
 * Description: Transform LearnDash quizzes into competitive real-time 1v1 battles with leaderboards, achievements, and social integration.
 * Version: 1.0.0
 * Author: WBCom Designs
 * Text Domain: quiz-battle-arena
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'QBA_VERSION', '1.0.0' );
define( 'QBA_PLUGIN_FILE', __FILE__ );
define( 'QBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QBA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QBA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'QBA_TEXT_DOMAIN', 'quiz-battle-arena' );

// Load activator and deactivator for activation hooks
require_once QBA_PLUGIN_DIR . 'includes/class-qba-activator.php';
require_once QBA_PLUGIN_DIR . 'includes/class-qba-deactivator.php';

// Register activation/deactivation hooks
register_activation_hook( __FILE__, array( 'QBA_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'QBA_Deactivator', 'deactivate' ) );

/**
 * Main Plugin Class
 *
 * Handles plugin initialization and coordination
 *
 * @since 1.0.0
 */
class Quiz_Battle_Arena {

	/**
	 * Single instance of the plugin
	 *
	 * @since 1.0.0
	 * @var Quiz_Battle_Arena
	 */
	protected static $instance = null;

	/**
	 * Main plugin loader
	 *
	 * @since 1.0.0
	 * @var QBA_Loader
	 */
	protected $loader;

	/**
	 * Get single instance of the plugin
	 *
	 * @since 1.0.0
	 * @return Quiz_Battle_Arena
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize the plugin
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_integration_hooks();
	}

	/**
	 * Load plugin dependencies
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		// Load core classes
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-loader.php';
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-database.php';
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-api.php';
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-validator.php';
		require_once QBA_PLUGIN_DIR . 'includes/qba-helper-functions.php';

		// Load integration classes
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-learndash-integration.php';
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-buddyboss-integration.php';
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-notifications.php';

		// Load feature classes
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-queue-manager.php';
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-battle-engine.php';
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-realtime-handler.php';
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-leaderboard.php';
		require_once QBA_PLUGIN_DIR . 'includes/class-qba-achievements.php';

		// Load admin classes
		if ( is_admin() ) {
			require_once QBA_PLUGIN_DIR . 'admin/class-qba-admin.php';
			require_once QBA_PLUGIN_DIR . 'admin/class-qba-admin-settings.php';
			require_once QBA_PLUGIN_DIR . 'admin/class-qba-admin-leaderboard.php';
		}

		// Load public classes
		if ( ! is_admin() ) {
			require_once QBA_PLUGIN_DIR . 'public/class-qba-public.php';
		}

		$this->loader = new QBA_Loader();
	}

	/**
	 * Define admin hooks
	 *
	 * @since 1.0.0
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		$admin             = new QBA_Admin();
		$settings          = new QBA_Admin_Settings();
		$leaderboard_admin = new QBA_Admin_Leaderboard();

		// Admin menu and settings
		$this->loader->add_action( 'admin_menu', $admin, 'add_plugin_admin_menu' );
		$this->loader->add_action( 'admin_init', $settings, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );

		// Admin AJAX handlers
		$this->loader->add_action( 'wp_ajax_qba_admin_action', $admin, 'handle_admin_ajax' );
	}

	/**
	 * Define public hooks
	 *
	 * @since 1.0.0
	 */
	private function define_public_hooks() {
		if ( is_admin() ) {
			return;
		}

		$public        = new QBA_Public();
		$battle_engine = new QBA_Battle_Engine();
		$realtime      = new QBA_Realtime_Handler();
		$leaderboard   = new QBA_Leaderboard();
		$achievements  = new QBA_Achievements();

		// Public assets
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );

		// Battle functionality
		$this->loader->add_action( 'wp_ajax_qba_create_battle', $battle_engine, 'ajax_create_battle' );
		$this->loader->add_action( 'wp_ajax_qba_join_queue', $battle_engine, 'ajax_join_queue' );
		$this->loader->add_action( 'wp_ajax_qba_leave_queue', $battle_engine, 'ajax_leave_queue' );
		$this->loader->add_action( 'wp_ajax_qba_accept_battle', $battle_engine, 'ajax_accept_battle' );
		$this->loader->add_action( 'wp_ajax_qba_decline_battle', $battle_engine, 'ajax_decline_battle' );
		$this->loader->add_action( 'wp_ajax_qba_submit_answer', $battle_engine, 'ajax_submit_answer' );
		$this->loader->add_action( 'wp_ajax_qba_get_battle_results', $battle_engine, 'ajax_get_battle_results' );

		// Real-time handlers
		$this->loader->add_action( 'wp_ajax_qba_battle_sync', $realtime, 'ajax_battle_sync' );
		$this->loader->add_action( 'wp_ajax_qba_submit_answer', $realtime, 'ajax_submit_answer' );

		// Leaderboard
		$this->loader->add_action( 'wp_ajax_qba_get_leaderboard', $leaderboard, 'ajax_get_leaderboard' );
		$this->loader->add_action( 'wp_ajax_nopriv_qba_get_leaderboard', $leaderboard, 'ajax_get_leaderboard' );

		// Shortcodes
		add_shortcode( 'qba_leaderboard', array( $leaderboard, 'render_leaderboard_shortcode' ) );
		add_shortcode( 'qba_user_stats', array( $public, 'render_user_stats_shortcode' ) );
		add_shortcode( 'qba_achievements', array( $achievements, 'render_achievements_shortcode' ) );

		// User activity tracking
		add_action( 'wp', 'qba_update_user_activity' );
	}

	/**
	 * Define integration hooks
	 *
	 * @since 1.0.0
	 */
	private function define_integration_hooks() {
		$learndash     = new QBA_LearnDash_Integration();
		$buddyboss     = new QBA_BuddyBoss_Integration();
		$notifications = new QBA_Notifications();

		// LearnDash integration
		$this->loader->add_filter( 'learndash_quiz_content', $learndash, 'add_battle_button', 10, 2 );
		$this->loader->add_action( 'learndash_quiz_submitted', $learndash, 'handle_quiz_submission', 10, 2 );
		$this->loader->add_action( 'learndash_quiz_completed', $learndash, 'handle_quiz_completion', 10, 3 );

		// BuddyBoss integration (only if active)
		if ( function_exists( 'bp_is_active' ) ) {
			$this->loader->add_action( 'bp_setup_nav', $buddyboss, 'add_profile_battle_tab', 100 );
			$this->loader->add_action( 'qba_battle_completed', $buddyboss, 'post_battle_activity', 10, 3 );
			$this->loader->add_action( 'qba_badge_earned', $buddyboss, 'post_badge_activity', 10, 3 );
		}

		// Notifications
		$this->loader->add_action( 'qba_battle_challenge_created', $notifications, 'send_challenge_notification', 10, 2 );
		$this->loader->add_action( 'qba_battle_completed', $notifications, 'send_battle_result_notification', 10, 3 );
		$this->loader->add_action( 'qba_badge_earned', $notifications, 'send_badge_notification', 10, 3 );
	}

	/**
	 * Run the plugin
	 *
	 * @since 1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Get plugin loader
	 *
	 * @since 1.0.0
	 * @return QBA_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}
}

/**
 * Initialize the plugin
 *
 * @since 1.0.0
 */
function qba_init() {
	// Check dependencies
	if ( ! qba_check_dependencies() ) {
		add_action( 'admin_notices', 'qba_dependency_notice' );
		// Don't return - allow plugin to load but show warning
		// return;
	}

	// Initialize plugin
	$plugin = Quiz_Battle_Arena::get_instance();
	$plugin->run();
}
add_action( 'plugins_loaded', 'qba_init' );

/**
 * Check plugin dependencies
 *
 * @since 1.0.0
 * @return bool
 */
function qba_check_dependencies() {
	// Check LearnDash
	if ( ! defined( 'LEARNDASH_VERSION' ) || version_compare( LEARNDASH_VERSION, '4.0', '<' ) ) {
		return false;
	}

	// Check PHP version
	if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
		return false;
	}

	// Check WordPress version
	if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
		return false;
	}

	return true;
}

/**
 * Display dependency notice
 *
 * @since 1.0.0
 */
function qba_dependency_notice() {
	$message = sprintf(
		__( 'Quiz Battle Arena requires LearnDash 4.0+, WordPress 6.0+, and PHP 8.0+. Please update your environment. Current versions: LearnDash %1$s, WordPress %2$s, PHP %3$s.', QBA_TEXT_DOMAIN ),
		defined( 'LEARNDASH_VERSION' ) ? LEARNDASH_VERSION : 'Not installed',
		get_bloginfo( 'version' ),
		PHP_VERSION
	);

	echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
}
