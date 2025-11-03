<?php
/**
 * Public Class
 *
 * Handles public-facing functionality for Quiz Battle Arena
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Public Class
 *
 * Defines all hooks for the public side of the site
 *
 * @since 1.0.0
 */
class QBA_Public {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor can be empty or used for initialization
	}

	/**
	 * Register and enqueue public-facing styles
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'qba-public-styles',
			QBA_PLUGIN_URL . 'assets/css/qba-public.css',
			array(),
			QBA_VERSION,
			'all'
		);

		// Enqueue battle modal styles
		wp_enqueue_style(
			'qba-battle-modal',
			QBA_PLUGIN_URL . 'assets/css/qba-battle-modal.css',
			array( 'qba-public-styles' ),
			QBA_VERSION,
			'all'
		);

		// Enqueue leaderboard styles
		wp_enqueue_style(
			'qba-leaderboard',
			QBA_PLUGIN_URL . 'assets/css/qba-leaderboard.css',
			array( 'qba-public-styles' ),
			QBA_VERSION,
			'all'
		);
	}

	/**
	 * Register and enqueue public-facing scripts
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			'qba-public-scripts',
			QBA_PLUGIN_URL . 'assets/js/qba-public.js',
			array( 'jquery' ),
			QBA_VERSION,
			false
		);

		// Enqueue battle engine script
		wp_enqueue_script(
			'qba-battle-engine',
			QBA_PLUGIN_URL . 'assets/js/qba-battle-engine.js',
			array( 'jquery', 'qba-public-scripts' ),
			QBA_VERSION,
			false
		);

		// Enqueue realtime handler script
		wp_enqueue_script(
			'qba-realtime',
			QBA_PLUGIN_URL . 'assets/js/qba-realtime.js',
			array( 'jquery', 'qba-public-scripts' ),
			QBA_VERSION,
			false
		);

		// Localize script with necessary data
		wp_localize_script(
			'qba-public-scripts',
			'qba_public_ajax',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'qba_public_nonce' ),
				'current_user_id' => get_current_user_id(),
				'strings'         => array(
					'loading'             => __( 'Loading...', QBA_TEXT_DOMAIN ),
					'error'               => __( 'An error occurred. Please try again.', QBA_TEXT_DOMAIN ),
					'battle_starting'     => __( 'Battle starting...', QBA_TEXT_DOMAIN ),
					'waiting_opponent'    => __( 'Waiting for opponent...', QBA_TEXT_DOMAIN ),
					'battle_complete'     => __( 'Battle complete!', QBA_TEXT_DOMAIN ),
					'challenge_sent'      => __( 'Challenge sent!', QBA_TEXT_DOMAIN ),
					'challenge_accepted'  => __( 'Challenge accepted!', QBA_TEXT_DOMAIN ),
					'challenge_declined'  => __( 'Challenge declined.', QBA_TEXT_DOMAIN ),
					'queue_joined'        => __( 'Joined matchmaking queue...', QBA_TEXT_DOMAIN ),
					'queue_left'          => __( 'Left matchmaking queue.', QBA_TEXT_DOMAIN ),
					'confirm_leave_queue' => __( 'Are you sure you want to leave the queue?', QBA_TEXT_DOMAIN ),
					'time_up'             => __( 'Time\'s up!', QBA_TEXT_DOMAIN ),
					'correct'             => __( 'Correct!', QBA_TEXT_DOMAIN ),
					'incorrect'           => __( 'Incorrect.', QBA_TEXT_DOMAIN ),
					'you_win'             => __( 'You win!', QBA_TEXT_DOMAIN ),
					'you_lose'            => __( 'You lose.', QBA_TEXT_DOMAIN ),
					'draw'                => __( 'It\'s a draw!', QBA_TEXT_DOMAIN ),
				),
				'settings'        => array(
					'battle_timeout'  => get_option( 'qba_battle_timeout', 900 ),
					'max_questions'   => get_option( 'qba_max_questions', 10 ),
					'enable_realtime' => true, // Could be made configurable
				),
			)
		);
	}

	/**
	 * Add battle button to LearnDash quiz content
	 *
	 * @since 1.0.0
	 * @param string $content The quiz content
	 * @param int    $quiz_id The quiz ID
	 * @return string
	 */
	public function add_battle_elements( $content, $quiz_id ) {
		if ( ! is_user_logged_in() || ! get_option( 'qba_enable_battles', true ) ) {
			return $content;
		}

		// Check if this quiz supports battles
		if ( ! $this->quiz_supports_battles( $quiz_id ) ) {
			return $content;
		}

		$battle_button      = $this->get_battle_button_html( $quiz_id );
		$leaderboard_widget = $this->get_leaderboard_widget_html();

		// Add battle elements after quiz content
		$content .= '<div class="qba-battle-section">';
		$content .= $battle_button;
		$content .= $leaderboard_widget;
		$content .= '</div>';

		return $content;
	}

	/**
	 * Check if a quiz supports battles
	 *
	 * @since 1.0.0
	 * @param int $quiz_id The quiz ID
	 * @return bool
	 */
	private function quiz_supports_battles( $quiz_id ) {
		// Check if quiz has enough questions
		$question_count = get_post_meta( $quiz_id, 'question_count', true );
		$min_questions  = 5; // Minimum questions for a battle

		if ( $question_count < $min_questions ) {
			return false;
		}

		// Check if quiz is published and not restricted
		$quiz_post = get_post( $quiz_id );
		if ( ! $quiz_post || $quiz_post->post_status !== 'publish' ) {
			return false;
		}

		// Check for quiz-specific battle settings (could be added later)
		$battle_enabled = get_post_meta( $quiz_id, '_qba_battle_enabled', true );
		if ( $battle_enabled === '0' ) {
			return false;
		}

		return true;
	}

	/**
	 * Get battle button HTML
	 *
	 * @since 1.0.0
	 * @param int $quiz_id The quiz ID
	 * @return string
	 */
	private function get_battle_button_html( $quiz_id ) {
		$user_id    = get_current_user_id();
		$user_stats = qba_get_user_stats( $user_id );

		ob_start();
		?>
		<div class="qba-battle-buttons">
			<button type="button" class="qba-btn qba-btn-primary qba-quick-battle-btn"
					data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
				<span class="qba-btn-icon">⚔️</span>
				<?php esc_html_e( 'Quick Battle', QBA_TEXT_DOMAIN ); ?>
			</button>

			<button type="button" class="qba-btn qba-btn-secondary qba-challenge-friend-btn"
					data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
				<span class="qba-btn-icon">👥</span>
				<?php esc_html_e( 'Challenge Friend', QBA_TEXT_DOMAIN ); ?>
			</button>

			<div class="qba-user-stats">
				<span class="qba-stat">
					<span class="qba-stat-label"><?php esc_html_e( 'Rating:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-stat-value"><?php echo esc_html( $user_stats['elo_rating'] ?? 1000 ); ?></span>
				</span>
				<span class="qba-stat">
					<span class="qba-stat-label"><?php esc_html_e( 'Wins:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-stat-value"><?php echo esc_html( $user_stats['battles_won'] ?? 0 ); ?></span>
				</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get leaderboard widget HTML
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_leaderboard_widget_html() {
		if ( ! get_option( 'qba_enable_leaderboard', true ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="qba-leaderboard-widget">
			<h3><?php esc_html_e( 'Top Players', QBA_TEXT_DOMAIN ); ?></h3>
			<div class="qba-leaderboard-preview" data-period="weekly">
				<!-- Leaderboard content loaded via AJAX -->
				<div class="qba-leaderboard-loading">
					<?php esc_html_e( 'Loading leaderboard...', QBA_TEXT_DOMAIN ); ?>
				</div>
			</div>
			<a href="#" class="qba-view-full-leaderboard">
				<?php esc_html_e( 'View Full Leaderboard', QBA_TEXT_DOMAIN ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add battle modal to footer
	 *
	 * @since 1.0.0
	 */
	public function add_battle_modal() {
		if ( ! is_user_logged_in() || ! get_option( 'qba_enable_battles', true ) ) {
			return;
		}

		include QBA_PLUGIN_DIR . 'public/partials/qba-battle-modal.php';
	}

	/**
	 * Add leaderboard shortcode
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function leaderboard_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'period'            => 'alltime',
				'limit'             => 10,
				'show_current_user' => 'true',
			),
			$atts,
			'qba_leaderboard'
		);

		if ( ! get_option( 'qba_enable_leaderboard', true ) ) {
			return '';
		}

		ob_start();
		include QBA_PLUGIN_DIR . 'public/partials/qba-leaderboard-shortcode.php';
		return ob_get_clean();
	}

	/**
	 * Add user stats shortcode
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function user_stats_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'user_id'     => get_current_user_id(),
				'show_badges' => 'true',
			),
			$atts,
			'qba_user_stats'
		);

		if ( ! $atts['user_id'] ) {
			return '';
		}

		ob_start();
		include QBA_PLUGIN_DIR . 'public/partials/qba-user-stats-shortcode.php';
		return ob_get_clean();
	}

	/**
	 * Add achievements shortcode
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function achievements_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'user_id' => get_current_user_id(),
				'layout'  => 'grid',
			),
			$atts,
			'qba_achievements'
		);

		if ( ! get_option( 'qba_enable_achievements', true ) || ! $atts['user_id'] ) {
			return '';
		}

		ob_start();
		include QBA_PLUGIN_DIR . 'public/partials/qba-achievements-shortcode.php';
		return ob_get_clean();
	}

	/**
	 * Handle user activity tracking
	 *
	 * @since 1.0.0
	 */
	public function track_user_activity() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		// Update last activity
		update_user_meta( $user_id, '_qba_last_activity', current_time( 'mysql' ) );

		// Track page views for analytics (optional)
		$current_page = $_SERVER['REQUEST_URI'] ?? '';
		if ( strpos( $current_page, 'quiz' ) !== false ) {
			// User is viewing quiz-related content
			$quiz_views = (int) get_user_meta( $user_id, '_qba_quiz_views', true );
			update_user_meta( $user_id, '_qba_quiz_views', $quiz_views + 1 );
		}
	}

	/**
	 * AJAX handler for checking queue match status
	 *
	 * @since 1.0.0
	 */
	public function ajax_check_queue_match() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$user_id       = get_current_user_id();
		$queue_manager = new QBA_Queue_Manager();
		$match         = $queue_manager->check_queue_match( $user_id );

		if ( $match ) {
			wp_send_json_success( $match );
		} else {
			wp_send_json_error( __( 'No match found yet', QBA_TEXT_DOMAIN ) );
		}
	}

	/**
	 * AJAX handler for getting friends list
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_friends() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$user_id = get_current_user_id();
		$friends = array();

		// If BuddyBoss is active, get friends
		if ( function_exists( 'friends_get_friend_user_ids' ) ) {
			$friend_ids = friends_get_friend_user_ids( $user_id );
			foreach ( $friend_ids as $friend_id ) {
				$friend_data = get_userdata( $friend_id );
				if ( $friend_data ) {
					$friends[] = array(
						'id'     => $friend_id,
						'name'   => $friend_data->display_name,
						'avatar' => get_avatar_url( $friend_id, array( 'size' => 32 ) ),
					);
				}
			}
		}

		wp_send_json_success( $friends );
	}

	/**
	 * AJAX handler for getting recent opponents
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_recent_opponents() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$user_id = get_current_user_id();

		global $wpdb;
		$table = $wpdb->prefix . 'qba_battles';

		$opponents = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT DISTINCT
				CASE
					WHEN challenger_id = %d THEN opponent_id
					ELSE challenger_id
				END as opponent_id,
				u.display_name,
				MAX(b.created_at) as last_battle
			FROM {$table} b
			INNER JOIN {$wpdb->users} u ON (
				CASE
					WHEN b.challenger_id = %d THEN b.opponent_id = u.ID
					ELSE b.challenger_id = u.ID
				END
			)
			WHERE (challenger_id = %d OR opponent_id = %d)
			AND b.status IN ('completed', 'cancelled')
			GROUP BY opponent_id, u.display_name
			ORDER BY last_battle DESC
			LIMIT 10
		",
				$user_id,
				$user_id,
				$user_id,
				$user_id
			),
			ARRAY_A
		);

		$result = array();
		foreach ( $opponents as $opponent ) {
			$result[] = array(
				'id'     => $opponent['opponent_id'],
				'name'   => $opponent['display_name'],
				'avatar' => get_avatar_url( $opponent['opponent_id'], array( 'size' => 32 ) ),
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for getting battle modal HTML
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_battle_modal() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		ob_start();
		include QBA_PLUGIN_DIR . 'public/partials/qba-battle-modal.php';
		$html = ob_get_clean();

		wp_send_json_success( $html );
	}

	/**
	 * AJAX handler for getting next question
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_next_question() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$battle_id = intval( $_POST['battle_id'] ?? 0 );
		$user_id   = get_current_user_id();

		if ( ! $battle_id || ! $user_id ) {
			wp_send_json_error( __( 'Invalid parameters', QBA_TEXT_DOMAIN ) );
		}

		$battle_engine = new QBA_Battle_Engine();
		$battle        = qba_get_battle( $battle_id );

		if ( ! $battle || ( $battle['challenger_id'] != $user_id && $battle['opponent_id'] != $user_id ) ) {
			wp_send_json_error( __( 'Not authorized for this battle', QBA_TEXT_DOMAIN ) );
		}

		if ( $battle['status'] !== 'active' ) {
			wp_send_json_error( __( 'Battle is not active', QBA_TEXT_DOMAIN ) );
		}

		// Get next question
		$question = $battle_engine->get_next_question( $battle_id, $user_id );

		if ( ! $question ) {
			wp_send_json_error( __( 'No more questions available', QBA_TEXT_DOMAIN ) );
		}

		wp_send_json_success( $question );
	}

	/**
	 * AJAX handler for tracking user activity
	 *
	 * @since 1.0.0
	 */
	public function ajax_track_activity() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$this->track_user_activity();
		wp_send_json_success();
	}

	/**
	 * Render user stats shortcode
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
public function render_user_stats_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'user_id' => get_current_user_id(),
		),
		$atts
	);

	$user_id = intval( $atts['user_id'] );

	if ( ! $user_id ) {
		return '<p>' . esc_html__( 'User not found.', QBA_TEXT_DOMAIN ) . '</p>';
	}

	$user_info = get_userdata( $user_id );
	if ( ! $user_info ) {
		return '<p>' . esc_html__( 'User not found.', QBA_TEXT_DOMAIN ) . '</p>';
	}

	$user_stats = qba_get_user_stats( $user_id );
	if ( ! $user_stats ) {
		return '<p>' . esc_html__( 'No statistics available.', QBA_TEXT_DOMAIN ) . '</p>';
	}

	// Calculate additional stats
	$win_rate = $user_stats['total_battles'] > 0
		? round( ( $user_stats['battles_won'] / $user_stats['total_battles'] ) * 100, 1 )
		: 0;
	$accuracy = $user_stats['total_questions_answered'] > 0
		? round( ( $user_stats['correct_answers'] / $user_stats['total_questions_answered'] ) * 100, 1 )
		: 0;

	ob_start();
	?>
		<div class="qba-user-stats-container">
			<div class="qba-user-header">
			<?php echo get_avatar( $user_id, 80 ); ?>
				<h3 class="qba-user-name"><?php echo esc_html( $user_info->display_name ); ?></h3>
				<span class="qba-rating-badge"><?php echo esc_html( $user_stats['elo_rating'] ); ?> ELO</span>
			</div>

			<div class="qba-stats-grid">
				<div class="qba-stat-card">
					<div class="qba-stat-number"><?php echo esc_html( $user_stats['total_points'] ); ?></div>
					<div class="qba-stat-label"><?php esc_html_e( 'Total Points', QBA_TEXT_DOMAIN ); ?></div>
				</div>
				<div class="qba-stat-card">
					<div class="qba-stat-number"><?php echo esc_html( $user_stats['total_battles'] ); ?></div>
					<div class="qba-stat-label"><?php esc_html_e( 'Total Battles', QBA_TEXT_DOMAIN ); ?></div>
				</div>
				<div class="qba-stat-card">
					<div class="qba-stat-number"><?php echo esc_html( $user_stats['battles_won'] ); ?></div>
					<div class="qba-stat-label"><?php esc_html_e( 'Wins', QBA_TEXT_DOMAIN ); ?></div>
				</div>
				<div class="qba-stat-card">
					<div class="qba-stat-number"><?php echo esc_html( $user_stats['battles_lost'] ); ?></div>
					<div class="qba-stat-label"><?php esc_html_e( 'Losses', QBA_TEXT_DOMAIN ); ?></div>
				</div>
				<div class="qba-stat-card">
					<div class="qba-stat-number"><?php echo esc_html( $win_rate ); ?>%</div>
					<div class="qba-stat-label"><?php esc_html_e( 'Win Rate', QBA_TEXT_DOMAIN ); ?></div>
				</div>
				<div class="qba-stat-card">
					<div class="qba-stat-number"><?php echo esc_html( $accuracy ); ?>%</div>
					<div class="qba-stat-label"><?php esc_html_e( 'Accuracy', QBA_TEXT_DOMAIN ); ?></div>
				</div>
				<div class="qba-stat-card">
					<div class="qba-stat-number"><?php echo esc_html( $user_stats['win_streak'] ); ?></div>
					<div class="qba-stat-label"><?php esc_html_e( 'Current Streak', QBA_TEXT_DOMAIN ); ?></div>
				</div>
				<div class="qba-stat-card">
					<div class="qba-stat-number"><?php echo esc_html( $user_stats['best_win_streak'] ); ?></div>
					<div class="qba-stat-label"><?php esc_html_e( 'Best Streak', QBA_TEXT_DOMAIN ); ?></div>
				</div>
			</div>

			<div class="qba-detailed-stats">
				<h4><?php esc_html_e( 'Detailed Statistics', QBA_TEXT_DOMAIN ); ?></h4>
				<div class="qba-stats-list">
					<div class="qba-stat-item">
						<span class="qba-stat-name"><?php esc_html_e( 'Questions Answered', QBA_TEXT_DOMAIN ); ?>:</span>
						<span class="qba-stat-value"><?php echo esc_html( $user_stats['total_questions_answered'] ); ?></span>
					</div>
					<div class="qba-stat-item">
						<span class="qba-stat-name"><?php esc_html_e( 'Correct Answers', QBA_TEXT_DOMAIN ); ?>:</span>
						<span class="qba-stat-value"><?php echo esc_html( $user_stats['correct_answers'] ); ?></span>
					</div>
					<div class="qba-stat-item">
						<span class="qba-stat-name"><?php esc_html_e( 'Average Answer Time', QBA_TEXT_DOMAIN ); ?>:</span>
						<span class="qba-stat-value"><?php echo esc_html( $user_stats['avg_answer_time'] ); ?>s</span>
					</div>
					<div class="qba-stat-item">
						<span class="qba-stat-name"><?php esc_html_e( 'Draws', QBA_TEXT_DOMAIN ); ?>:</span>
						<span class="qba-stat-value"><?php echo esc_html( $user_stats['battles_drawn'] ); ?></span>
					</div>
					<div class="qba-stat-item">
						<span class="qba-stat-name"><?php esc_html_e( 'Last Battle', QBA_TEXT_DOMAIN ); ?>:</span>
						<span class="qba-stat-value"><?php echo esc_html( qba_get_relative_time( $user_stats['last_battle_at'] ) ); ?></span>
					</div>
				</div>
			</div>

			<div class="qba-achievements-section">
				<h4><?php esc_html_e( 'Achievements', QBA_TEXT_DOMAIN ); ?></h4>
			<?php
			$achievements = new QBA_Achievements();
			echo $achievements->render_achievements( $user_id, 'compact' );
			?>
			</div>
		</div>
		<?php
		return ob_get_clean();
}
}
