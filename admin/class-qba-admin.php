<?php
/**
 * Admin Class
 *
 * Handles admin-side functionality for Quiz Battle Arena
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Admin Class
 *
 * Defines all hooks for the admin area
 *
 * @since 1.0.0
 */
class QBA_Admin {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor can be empty or used for initialization
	}

	/**
	 * Register the admin menu
	 *
	 * @since 1.0.0
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'Quiz Battle Arena', QBA_TEXT_DOMAIN ),
			__( 'Battle Arena', QBA_TEXT_DOMAIN ),
			'manage_options',
			'quiz-battle-arena',
			array( $this, 'display_plugin_setup_page' ),
			'dashicons-trophy',
			30
		);

		add_submenu_page(
			'quiz-battle-arena',
			__( 'Settings', QBA_TEXT_DOMAIN ),
			__( 'Settings', QBA_TEXT_DOMAIN ),
			'manage_options',
			'qba-settings',
			array( $this, 'display_settings_page' )
		);

		add_submenu_page(
			'quiz-battle-arena',
			__( 'Leaderboards', QBA_TEXT_DOMAIN ),
			__( 'Leaderboards', QBA_TEXT_DOMAIN ),
			'manage_options',
			'qba-leaderboards',
			array( $this, 'display_leaderboards_page' )
		);

		add_submenu_page(
			'quiz-battle-arena',
			__( 'Battles', QBA_TEXT_DOMAIN ),
			__( 'Battles', QBA_TEXT_DOMAIN ),
			'manage_options',
			'qba-battles',
			array( $this, 'display_battles_page' )
		);

		add_submenu_page(
			'quiz-battle-arena',
			__( 'Test Data', QBA_TEXT_DOMAIN ),
			__( 'Test Data', QBA_TEXT_DOMAIN ),
			'manage_options',
			'qba-test-data',
			array( $this, 'display_test_data_page' )
		);
	}

	/**
	 * Display the plugin setup page
	 *
	 * @since 1.0.0
	 */
	public function display_plugin_setup_page() {
		include_once QBA_PLUGIN_DIR . 'admin/partials/qba-admin-display.php';
	}

	/**
	 * Display the settings page
	 *
	 * @since 1.0.0
	 */
	public function display_settings_page() {
		include_once QBA_PLUGIN_DIR . 'admin/partials/qba-admin-settings.php';
	}

	/**
	 * Display the leaderboards page
	 *
	 * @since 1.0.0
	 */
	public function display_leaderboards_page() {
		include_once QBA_PLUGIN_DIR . 'admin/partials/qba-admin-leaderboard.php';
	}

	/**
	 * Display the battles page
	 *
	 * @since 1.0.0
	 */
	public function display_battles_page() {
		include_once QBA_PLUGIN_DIR . 'admin/partials/qba-admin-battles.php';
	}

	/**
	 * Register and enqueue admin-specific styles
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'qba-admin-styles',
			QBA_PLUGIN_URL . 'assets/css/qba-admin.css',
			array(),
			QBA_VERSION,
			'all'
		);
	}

	/**
	 * Register and enqueue admin-specific scripts
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			'qba-admin-scripts',
			QBA_PLUGIN_URL . 'assets/js/qba-admin.js',
			array( 'jquery' ),
			QBA_VERSION,
			false
		);

		wp_localize_script(
			'qba-admin-scripts',
			'qba_admin_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'qba_admin_nonce' ),
				'strings'  => array(
					'confirm_delete' => __( 'Are you sure you want to delete this item?', QBA_TEXT_DOMAIN ),
					'loading'        => __( 'Loading...', QBA_TEXT_DOMAIN ),
				),
			)
		);
	}

	/**
	 * Handle admin AJAX requests
	 *
	 * @since 1.0.0
	 */
	public function handle_admin_ajax() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_admin_nonce' ) ) {
			wp_die( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions', QBA_TEXT_DOMAIN ) );
		}

		$action = sanitize_text_field( $_POST['action_type'] ?? '' );

		switch ( $action ) {
			case 'get_battle_stats':
				$this->ajax_get_battle_stats();
				break;
			case 'reset_user_stats':
				$this->ajax_reset_user_stats();
				break;
			case 'clear_cache':
				$this->ajax_clear_cache();
				break;
			default:
				wp_die( __( 'Invalid action', QBA_TEXT_DOMAIN ) );
		}
	}

	/**
	 * Get battle statistics for admin dashboard
	 *
	 * @since 1.0.0
	 */
	private function ajax_get_battle_stats() {
		global $wpdb;

		$stats = array(
			'total_battles'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}qba_battles" ),
			'active_battles' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}qba_battles WHERE status = 'active'" ),
			'total_users'    => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}qba_user_stats" ),
			'total_badges'   => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}qba_user_badges" ),
		);

		wp_send_json_success( $stats );
	}

	/**
	 * Reset user statistics (admin action)
	 *
	 * @since 1.0.0
	 */
	private function ajax_reset_user_stats() {
		$user_id = intval( $_POST['user_id'] ?? 0 );

		if ( ! $user_id ) {
			wp_send_json_error( __( 'Invalid user ID', QBA_TEXT_DOMAIN ) );
		}

		global $wpdb;

		// Reset user stats
		$result = $wpdb->update(
			$wpdb->prefix . 'qba_user_stats',
			array(
				'total_battles'            => 0,
				'battles_won'              => 0,
				'battles_lost'             => 0,
				'battles_drawn'            => 0,
				'total_points'             => 0,
				'elo_rating'               => 1000,
				'win_streak'               => 0,
				'best_win_streak'          => 0,
				'total_questions_answered' => 0,
				'correct_answers'          => 0,
				'avg_answer_time'          => 0.00,
				'last_battle_at'           => null,
				'updated_at'               => current_time( 'mysql' ),
			),
			array( 'user_id' => $user_id ),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%s', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			wp_send_json_success( __( 'User statistics reset successfully', QBA_TEXT_DOMAIN ) );
		} else {
			wp_send_json_error( __( 'Failed to reset user statistics', QBA_TEXT_DOMAIN ) );
		}
	}

	/**
	 * Clear plugin caches
	 *
	 * @since 1.0.0
	 */
	private function ajax_clear_cache() {
		// Clear transients
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_qba_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_qba_%'" );

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		wp_send_json_success( __( 'Cache cleared successfully', QBA_TEXT_DOMAIN ) );
	}

	/**
	 * Display test data page
	 *
	 * @since 1.0.0
	 */
	public function display_test_data_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// Handle form submission
		if ( isset( $_POST['create_test_data'] ) && wp_verify_nonce( $_POST['qba_test_data_nonce'], 'qba_create_test_data' ) ) {
			$result = $this->create_test_data();
			if ( $result ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Test data created successfully!', QBA_TEXT_DOMAIN ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Failed to create test data. Check error logs and ensure database tables exist.', QBA_TEXT_DOMAIN ) . '</p></div>';
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Create Test Data', QBA_TEXT_DOMAIN ); ?></h1>
			<p><?php esc_html_e( 'Generate sample battles, users, and statistics for testing the plugin UI.', QBA_TEXT_DOMAIN ); ?></p>

			<div class="qba-test-data-form" style="background: white; border: 1px solid #e1e1e1; border-radius: 8px; padding: 30px; margin-top: 20px;">
				<h2><?php esc_html_e( 'Generate Test Data', QBA_TEXT_DOMAIN ); ?></h2>
				<p><?php esc_html_e( 'This will create:', QBA_TEXT_DOMAIN ); ?></p>
				<ul style="margin-left: 20px;">
					<li><?php esc_html_e( '5 test users with profiles', QBA_TEXT_DOMAIN ); ?></li>
					<li><?php esc_html_e( '3 test quizzes (or posts if LearnDash not available)', QBA_TEXT_DOMAIN ); ?></li>
					<li><?php esc_html_e( '25 sample battles with various statuses', QBA_TEXT_DOMAIN ); ?></li>
					<li><?php esc_html_e( 'User statistics and rankings', QBA_TEXT_DOMAIN ); ?></li>
				</ul>

				<form method="post">
					<?php wp_nonce_field( 'qba_create_test_data', 'qba_test_data_nonce' ); ?>
					<input type="hidden" name="qba_processed" value="1" />
					<p>
						<input type="submit" name="create_test_data" class="button button-primary button-large" value="<?php esc_attr_e( 'Create Test Data', QBA_TEXT_DOMAIN ); ?>" />
					</p>
				</form>
			</div>

			<div class="qba-test-data-links" style="margin-top: 30px;">
				<h3><?php esc_html_e( 'Quick Links', QBA_TEXT_DOMAIN ); ?></h3>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-leaderboards' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'View Leaderboards', QBA_TEXT_DOMAIN ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-battles' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'View Battles', QBA_TEXT_DOMAIN ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-settings' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Plugin Settings', QBA_TEXT_DOMAIN ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Create test data
	 *
	 * @since 1.0.0
	 */
	public function create_test_data() {
		// Return status: true on success, false on failure

		global $wpdb;

		// Set time limit to prevent timeout
		set_time_limit(300); // 5 minutes

		// Enable error reporting for debugging
		if ( WP_DEBUG ) {
			error_reporting(E_ALL);
			ini_set('display_errors', 1);
		}

		// Test database connection
		if ( ! $wpdb->check_connection() ) {
			error_log( 'QBA Test Data: Database connection failed' );
			return false;
		}
		error_log( 'QBA Test Data: Database connection OK' );
		error_log( 'QBA Test Data: About to check tables' );

		// Table names
		$table_battles = $wpdb->prefix . 'qba_battles';
		$table_user_stats = $wpdb->prefix . 'qba_user_stats';
		$table_battle_progress = $wpdb->prefix . 'qba_battle_progress';

		// Check if tables exist
		$required_tables = array( $table_battles, $table_user_stats, $table_battle_progress );
		foreach ( $required_tables as $table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
			if ( ! $table_exists ) {
				return false;
			}
		}


		// Get some existing users
		$users = get_users( array( 'number' => 5 ) );
		if ( empty( $users ) ) {
			// Create some test users if none exist
			$user_ids = array();
			for ( $i = 1; $i <= 5; $i++ ) {
				$user_id = wp_create_user( "qba_testuser{$i}", "password{$i}", "qba_test{$i}@example.com" );
				if ( ! is_wp_error( $user_id ) ) {
					wp_update_user( array(
						'ID' => $user_id,
						'display_name' => "QBA Test Player {$i}",
						'first_name' => 'QBA',
						'last_name' => "Test Player {$i}"
					) );
					$user_ids[] = $user_id;
				} else {
					error_log( 'QBA Test Data: Failed to create user ' . $i . ': ' . $user_id->get_error_message() );
				}
			}
		} else {
			$user_ids = wp_list_pluck( $users, 'ID' );

			// Ensure we have at least some users
			if ( empty( $user_ids ) ) {
				error_log( 'QBA Test Data: No users available for test data creation' );
				return false;
			}
		}

		// Ensure we have at least some users
		if ( empty( $user_ids ) ) {
			error_log( 'QBA Test Data: No users available for test data creation' );
			return false;
		}

		// Create some test quizzes if LearnDash is available
		$quiz_ids = array();
		if ( function_exists( 'learndash_get_post_type_slug' ) ) {
			$quiz_slug = learndash_get_post_type_slug( 'quiz' );
			$existing_quizzes = get_posts( array(
				'post_type' => $quiz_slug,
				'numberposts' => 3,
				'post_status' => 'publish'
			) );

			if ( ! empty( $existing_quizzes ) ) {
				$quiz_ids = wp_list_pluck( $existing_quizzes, 'ID' );
			} else {
				// Create test quizzes
				for ( $i = 1; $i <= 3; $i++ ) {
					$quiz_id = wp_insert_post( array(
						'post_title' => "QBA Test Quiz {$i}",
						'post_content' => "This is a test quiz for battle testing.",
						'post_type' => $quiz_slug,
						'post_status' => 'publish'
					) );
					if ( $quiz_id && ! is_wp_error( $quiz_id ) ) {
						$quiz_ids[] = $quiz_id;
					} else {
						error_log( 'QBA Test Data: Failed to create LearnDash quiz ' . $i );
					}
				}
			}
		}

		// If no LearnDash quizzes, create regular posts as quiz placeholders
		if ( empty( $quiz_ids ) ) {
			for ( $i = 1; $i <= 3; $i++ ) {
				$quiz_id = wp_insert_post( array(
					'post_title' => "QBA Test Quiz {$i}",
					'post_content' => "This is a test quiz for battle testing.",
					'post_type' => 'post',
					'post_status' => 'publish'
				) );
				if ( $quiz_id && ! is_wp_error( $quiz_id ) ) {
					$quiz_ids[] = $quiz_id;
				} else {
					error_log( 'QBA Test Data: Failed to create post quiz ' . $i );
				}
			}
		}

		// Ensure we have quizzes
		if ( empty( $quiz_ids ) ) {
			error_log( 'QBA Test Data: No quizzes available for test data creation' );
			return;
		}

			// Clear existing test data
		error_log( 'QBA Test Data: Starting data creation with ' . count($user_ids) . ' users and ' . count($quiz_ids) . ' quizzes' );
		echo "Found " . count($user_ids) . " users and " . count($quiz_ids) . " quizzes. Starting data creation...<br>";
		if ( ! empty( $quiz_ids ) ) {
			$wpdb->query( "DELETE FROM {$table_battles} WHERE quiz_id IN (" . implode( ',', $quiz_ids ) . ")" );
		}
		if ( ! empty( $user_ids ) ) {
			$wpdb->query( "DELETE FROM {$table_user_stats} WHERE user_id IN (" . implode( ',', $user_ids ) . ")" );
		}
		$wpdb->query( "DELETE FROM {$table_battle_progress} WHERE battle_id IN (SELECT id FROM {$table_battles})" );

		// Create test battles
		$statuses = array( 'completed', 'active', 'pending', 'cancelled' );
		$winners = array_merge( $user_ids, array( null, null ) ); // Some battles without winners

		// Ensure we have at least 2 users for battles
		if ( count( $user_ids ) < 2 ) {
			error_log( 'QBA Test Data: Need at least 2 users to create battles. Skipping battle creation.' );
			echo "Need at least 2 users to create battles. Skipping battle creation.<br>";
		} else {
			for ( $i = 1; $i <= 10; $i++ ) { // Reduced from 25 to 10 to prevent timeout
				if ( empty( $user_ids ) || empty( $quiz_ids ) ) continue;

				$challenger_id = $user_ids[ array_rand( $user_ids ) ];
				$opponent_id = $user_ids[ array_rand( $user_ids ) ];

				}

			$status = $statuses[ array_rand( $statuses ) ];
			$winner_id = ( $status === 'completed' ) ? $winners[ array_rand( $winners ) ] : null;
			$quiz_id = $quiz_ids[ array_rand( $quiz_ids ) ];

			// Create battle record
			$battle_data = array(
				'challenger_id' => $challenger_id,
				'opponent_id' => $opponent_id,
				'quiz_id' => $quiz_id,
				'status' => $status,
				'winner_id' => $winner_id,
				'battle_type' => rand( 0, 1 ) ? 'quick' : 'challenge',
				'max_questions' => 10,
				'time_limit' => 900,
				'elo_change' => ( $status === 'completed' && $winner_id ) ? rand( -20, 20 ) : 0,
				'created_at' => date( 'Y-m-d H:i:s', strtotime( "-{$i} days" ) ),
				'started_at' => ( $status !== 'pending' ) ? date( 'Y-m-d H:i:s', strtotime( "-{$i} days +1 hour" ) ) : null,
				'completed_at' => ( in_array( $status, array( 'completed', 'cancelled' ) ) ) ? date( 'Y-m-d H:i:s', strtotime( "-{$i} days +2 hours" ) ) : null,
			);

			$result = $wpdb->insert( $table_battles, $battle_data );
			if ( $result === false ) {
			} else {
			}
			if ( $result === false ) {
			}

			if ( $result ) {
				$battle_id = $wpdb->insert_id;

				// Update battle with results if completed
				if ( $status === 'completed' ) {
					$challenger_score = rand( 0, 100 );
					$opponent_score = rand( 0, 100 );
					$challenger_correct = rand( 0, 10 );
					$opponent_correct = rand( 0, 10 );

					$update_data = array(
						'challenger_score' => $challenger_score,
						'opponent_score' => $opponent_score,
						'challenger_accuracy' => $challenger_correct > 0 ? round( ($challenger_correct / 10) * 100, 2 ) : 0.00,
						'opponent_accuracy' => $opponent_correct > 0 ? round( ($opponent_correct / 10) * 100, 2 ) : 0.00,
						'challenger_points' => $challenger_score,
						'opponent_points' => $opponent_score,
						'winner_id' => $winner_id,
					);

					$wpdb->update( $table_battles, $update_data, array( 'id' => $battle_id ) );
				}
			}
		}

		// Create user stats
		foreach ( $user_ids as $user_id ) {
			$total_battles = rand( 5, 20 );
			$battles_won = rand( 0, $total_battles );
			$battles_lost = $total_battles - $battles_won;

			$stats_data = array(
				'user_id' => $user_id,
				'elo_rating' => rand( 800, 1500 ),
				'total_points' => rand( 100, 1000 ),
				'total_battles' => $total_battles,
				'battles_won' => $battles_won,
				'battles_lost' => $battles_lost,
				'battles_drawn' => rand( 0, 2 ),
				'total_questions_answered' => $total_battles * 10,
				'correct_answers' => rand( $total_battles * 5, $total_battles * 10 ),
				'avg_answer_time' => rand( 10, 30 ),
				'win_streak' => rand( 0, 5 ),
				'best_win_streak' => rand( 3, 10 ),
				'last_battle_at' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
				'created_at' => date( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
				'updated_at' => date( 'Y-m-d H:i:s' ),
			);

			$result = $wpdb->insert( $table_user_stats, $stats_data );
			if ( $result === false ) {
			} else {
			}
		}

		return true;
	}
}
