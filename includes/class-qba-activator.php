<?php
/**
 * Plugin Activator Class
 *
 * Fired during plugin activation
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Activator Class
 *
 * This class defines all code necessary to run during the plugin's activation
 *
 * @since 1.0.0
 */
class QBA_Activator {

	/**
	 * Plugin activation tasks
	 *
	 * Create database tables, set default options, and perform initial setup
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Create database tables
		self::create_database_tables();

		// Set default options
		self::set_default_options();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Log activation
		error_log( 'Quiz Battle Arena plugin activated successfully' );
	}

	/**
	 * Create required database tables
	 *
	 * @since 1.0.0
	 */
	private static function create_database_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Battles table
		$table_battles = $wpdb->prefix . 'qba_battles';
		$sql_battles   = "CREATE TABLE $table_battles (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT(20) UNSIGNED NOT NULL,
			challenger_id BIGINT(20) UNSIGNED NOT NULL,
			opponent_id BIGINT(20) UNSIGNED NOT NULL,
			status ENUM('pending', 'active', 'completed', 'cancelled', 'expired') DEFAULT 'pending',
			challenge_type VARCHAR(20) DEFAULT 'direct',
			challenger_score INT(11) DEFAULT 0,
			opponent_score INT(11) DEFAULT 0,
			challenger_accuracy DECIMAL(5,2) DEFAULT 0.00,
			opponent_accuracy DECIMAL(5,2) DEFAULT 0.00,
			challenger_result ENUM('won', 'lost', 'draw') DEFAULT NULL,
			opponent_result ENUM('won', 'lost', 'draw') DEFAULT NULL,
			challenger_points INT(11) DEFAULT 0,
			opponent_points INT(11) DEFAULT 0,
			winner_id BIGINT(20) UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			started_at DATETIME DEFAULT NULL,
			completed_at DATETIME DEFAULT NULL,
			expires_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY quiz_id (quiz_id),
			KEY challenger_id (challenger_id),
			KEY opponent_id (opponent_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		// Battle progress table
		$table_progress = $wpdb->prefix . 'qba_battle_progress';
		$sql_progress   = "CREATE TABLE $table_progress (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			battle_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			question_id BIGINT(20) UNSIGNED NOT NULL,
			question_order INT(11) NOT NULL,
			answer_given TEXT DEFAULT NULL,
			is_correct TINYINT(1) DEFAULT 0,
			points_earned INT(11) DEFAULT 0,
			time_taken DECIMAL(10,2) DEFAULT 0.00,
			answered_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY battle_user_question (battle_id, user_id, question_id),
			KEY battle_id (battle_id),
			KEY user_id (user_id),
			KEY answered_at (answered_at)
		) $charset_collate;";

		// User stats table
		$table_stats = $wpdb->prefix . 'qba_user_stats';
		$sql_stats   = "CREATE TABLE $table_stats (
			user_id BIGINT(20) UNSIGNED NOT NULL,
			total_battles INT(11) DEFAULT 0,
			battles_won INT(11) DEFAULT 0,
			battles_lost INT(11) DEFAULT 0,
			battles_drawn INT(11) DEFAULT 0,
			total_points INT(11) DEFAULT 0,
			elo_rating INT(11) DEFAULT 1000,
			win_streak INT(11) DEFAULT 0,
			best_win_streak INT(11) DEFAULT 0,
			total_questions_answered INT(11) DEFAULT 0,
			correct_answers INT(11) DEFAULT 0,
			avg_answer_time DECIMAL(10,2) DEFAULT 0.00,
			last_battle_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (user_id),
			KEY elo_rating (elo_rating),
			KEY total_points (total_points),
			KEY battles_won (battles_won)
		) $charset_collate;";

		// User badges table
		$table_badges = $wpdb->prefix . 'qba_user_badges';
		$sql_badges   = "CREATE TABLE $table_badges (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			badge_id VARCHAR(50) NOT NULL,
			earned_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_badge (user_id, badge_id),
			KEY user_id (user_id),
			KEY earned_at (earned_at)
		) $charset_collate;";

		// Matchmaking queue table
		$table_queue = $wpdb->prefix . 'qba_matchmaking_queue';
		$sql_queue   = "CREATE TABLE $table_queue (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			quiz_id BIGINT(20) UNSIGNED NOT NULL,
			queue_type VARCHAR(20) DEFAULT 'random',
			preferences TEXT DEFAULT NULL,
			status ENUM('waiting', 'matched', 'cancelled', 'expired') DEFAULT 'waiting',
			battle_id BIGINT(20) UNSIGNED DEFAULT NULL,
			joined_at DATETIME NOT NULL,
			matched_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY quiz_id (quiz_id),
			KEY status (status),
			KEY joined_at (joined_at)
		) $charset_collate;";

		// Leaderboard snapshots table
		$table_snapshots = $wpdb->prefix . 'qba_leaderboard_snapshots';
		$sql_snapshots   = "CREATE TABLE $table_snapshots (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			snapshot_type VARCHAR(20) NOT NULL,
			period_start DATETIME NOT NULL,
			period_end DATETIME NOT NULL,
			leaderboard_data LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY snapshot_type (snapshot_type),
			KEY period_start (period_start)
		) $charset_collate;";

		// Execute table creation
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_battles );
		dbDelta( $sql_progress );
		dbDelta( $sql_stats );
		dbDelta( $sql_badges );
		dbDelta( $sql_queue );
		dbDelta( $sql_snapshots );
	}

	/**
	 * Set default plugin options
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		$defaults = array(
			'qba_version'                      => QBA_VERSION,
			'qba_db_version'                   => '1.0.0',
			'qba_enable_battles'               => '1',
			'qba_battle_timeout'               => '900', // 15 minutes
			'qba_challenge_expiry'             => '300', // 5 minutes
			'qba_max_questions'                => '10',
			'qba_speed_bonus_max'              => '5',
			'qba_elo_k_factor'                 => '32',
			'qba_queue_timeout'                => '300', // 5 minutes
			'qba_enable_leaderboard'           => '1',
			'qba_leaderboard_cache_timeout'    => '300', // 5 minutes
			'qba_enable_achievements'          => '1',
			'qba_enable_buddyboss_integration' => '1',
			'qba_battle_points_base'           => '10',
			'qba_battle_points_speed'          => '5',
			'qba_badge_points_first_win'       => '10',
			'qba_badge_points_ten_wins'        => '50',
			'qba_badge_points_speed_demon'     => '30',
			'qba_badge_points_perfect_score'   => '40',
			'qba_badge_points_win_streak'      => '25',
		);

		foreach ( $defaults as $option => $value ) {
			if ( ! get_option( $option ) ) {
				add_option( $option, $value );
			}
		}
	}
}
