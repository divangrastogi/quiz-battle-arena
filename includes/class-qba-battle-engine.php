<?php
/**
 * Battle Engine Class
 *
 * Handles battle creation, management, and logic
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Battle_Engine Class
 *
 * @since 1.0.0
 */
class QBA_Battle_Engine {

	/**
	 * Database instance
	 *
	 * @since 1.0.0
	 * @var QBA_Database
	 */
	private $db;

	/**
	 * Validator instance
	 *
	 * @since 1.0.0
	 * @var QBA_Validator
	 */
	private $validator;

	/**
	 * Initialize battle engine
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->db        = new QBA_Database();
		$this->validator = new QBA_Validator();
	}

	/**
	 * Create battle challenge
	 *
	 * @since 1.0.0
	 * @param int    $quiz_id      Quiz ID
	 * @param int    $challenger_id Challenger user ID
	 * @param int    $opponent_id   Opponent user ID
	 * @param string $challenge_type Challenge type
	 * @return int|WP_Error Battle ID or error
	 */
	public function create_battle_challenge( $quiz_id, $challenger_id, $opponent_id, $challenge_type = 'direct' ) {
		// Validate battle creation
		$validation = $this->validator->validate_battle_creation(
			array(
				'quiz_id'        => $quiz_id,
				'challenger_id'  => $challenger_id,
				'opponent_id'    => $opponent_id,
				'challenge_type' => $challenge_type,
			)
		);

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Sanitize data
		$battle_data = $this->validator->sanitize_battle_creation(
			array(
				'quiz_id'        => $quiz_id,
				'challenger_id'  => $challenger_id,
				'opponent_id'    => $opponent_id,
				'challenge_type' => $challenge_type,
			)
		);

		// Insert battle
		$battle_id = $this->db->insert_battle( $battle_data );

		if ( is_wp_error( $battle_id ) ) {
			return $battle_id;
		}

		// Trigger action
		do_action( 'qba_battle_challenge_created', $battle_id, $battle_data );

		return $battle_id;
	}

	/**
	 * Accept battle challenge
	 *
	 * @since 1.0.0
	 * @param int $battle_id Battle ID
	 * @param int $user_id   User ID accepting
	 * @return bool|WP_Error Success or error
	 */
	public function accept_battle_challenge( $battle_id, $user_id ) {
		$battle = qba_get_battle( $battle_id );

		if ( ! $battle ) {
			return new WP_Error( 'battle_not_found', __( 'Battle not found', 'quiz-battle-arena' ) );
		}

		if ( $battle['status'] !== 'pending' ) {
			return new WP_Error( 'battle_not_pending', __( 'Battle is not pending', 'quiz-battle-arena' ) );
		}

		if ( $battle['opponent_id'] != $user_id ) {
			return new WP_Error( 'not_opponent', __( 'You are not the opponent in this battle', 'quiz-battle-arena' ) );
		}

		// Check if battle has expired
		if ( strtotime( $battle['expires_at'] ) < time() ) {
			$this->db->update_battle( $battle_id, array( 'status' => 'expired' ) );
			return new WP_Error( 'battle_expired', __( 'Battle challenge has expired', 'quiz-battle-arena' ) );
		}

		// Start the battle
		$update_data = array(
			'status'     => 'active',
			'started_at' => current_time( 'mysql' ),
		);

		$result = $this->db->update_battle( $battle_id, $update_data );

		if ( $result ) {
			do_action( 'qba_battle_started', $battle_id, $battle );
		}

		return $result;
	}

	/**
	 * Decline battle challenge
	 *
	 * @since 1.0.0
	 * @param int $battle_id Battle ID
	 * @param int $user_id   User ID declining
	 * @return bool|WP_Error Success or error
	 */
	public function decline_battle_challenge( $battle_id, $user_id ) {
		$battle = qba_get_battle( $battle_id );

		if ( ! $battle ) {
			return new WP_Error( 'battle_not_found', __( 'Battle not found', 'quiz-battle-arena' ) );
		}

		if ( $battle['opponent_id'] != $user_id ) {
			return new WP_Error( 'not_opponent', __( 'You are not the opponent in this battle', 'quiz-battle-arena' ) );
		}

		$result = $this->db->update_battle( $battle_id, array( 'status' => 'cancelled' ) );

		if ( $result ) {
			do_action( 'qba_battle_declined', $battle_id, $battle );
		}

		return $result;
	}

	/**
	 * Submit battle answer
	 *
	 * @since 1.0.0
	 * @param int    $battle_id  Battle ID
	 * @param int    $question_id Question ID
	 * @param string $answer     User answer
	 * @param float  $time_taken Time taken in seconds
	 * @return array|WP_Error Result data or error
	 */
	public function submit_battle_answer( $battle_id, $question_id, $answer, $time_taken ) {
		$user_id = get_current_user_id();

		// Validate submission
		$validation = $this->validator->validate_answer_submission(
			array(
				'battle_id'   => $battle_id,
				'question_id' => $question_id,
				'answer'      => $answer,
				'time_taken'  => $time_taken,
			)
		);

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Get question data
		$question_data = learndash_get_question_data( $question_id );
		$is_correct    = $this->check_answer_correctness( $answer, $question_data );

		// Calculate score
		$points_earned = qba_calculate_answer_score( $is_correct, $time_taken );

		// Save progress
		$progress_data = $this->validator->sanitize_answer_submission(
			array(
				'battle_id'     => $battle_id,
				'question_id'   => $question_id,
				'answer_given'  => $answer,
				'is_correct'    => $is_correct,
				'points_earned' => $points_earned,
				'time_taken'    => $time_taken,
			)
		);

		$progress_id = $this->db->insert_battle_progress( $progress_data );

		if ( is_wp_error( $progress_id ) ) {
			return $progress_id;
		}

		$result = array(
			'correct'        => $is_correct,
			'points'         => $points_earned,
			'time_taken'     => $time_taken,
			'correct_answer' => $question_data['correct_answer'],
		);

		do_action( 'qba_answer_submitted', $battle_id, $user_id, $result );

		return $result;
	}

	/**
	 * Complete battle
	 *
	 * @since 1.0.0
	 * @param int $battle_id Battle ID
	 * @return array|WP_Error Results or error
	 */
	public function complete_battle( $battle_id ) {
		$battle = qba_get_battle( $battle_id );

		if ( ! $battle || $battle['status'] !== 'active' ) {
			return new WP_Error( 'invalid_battle', __( 'Invalid battle', 'quiz-battle-arena' ) );
		}

		// Get all progress
		$progress = $this->db->get_battle_all_progress( $battle_id );

		// Calculate final scores
		$challenger_score = $this->calculate_player_score( $progress, $battle['challenger_id'] );
		$opponent_score   = $this->calculate_player_score( $progress, $battle['opponent_id'] );

		// Determine winner
		if ( $challenger_score > $opponent_score ) {
			$winner_id    = $battle['challenger_id'];
			$loser_id     = $battle['opponent_id'];
			$winner_score = $challenger_score;
			$loser_score  = $opponent_score;
		} elseif ( $opponent_score > $challenger_score ) {
			$winner_id    = $battle['opponent_id'];
			$loser_id     = $battle['challenger_id'];
			$winner_score = $opponent_score;
			$loser_score  = $challenger_score;
		} else {
			// Draw - challenger wins by default
			$winner_id    = $battle['challenger_id'];
			$loser_id     = $battle['opponent_id'];
			$winner_score = $challenger_score;
			$loser_score  = $opponent_score;
		}

		// Calculate accuracy
		$challenger_accuracy = $this->calculate_player_accuracy( $progress, $battle['challenger_id'] );
		$opponent_accuracy   = $this->calculate_player_accuracy( $progress, $battle['opponent_id'] );

		// Update battle
		$update_data = array(
			'status'              => 'completed',
			'challenger_score'    => $challenger_score,
			'opponent_score'      => $opponent_score,
			'challenger_accuracy' => $challenger_accuracy,
			'opponent_accuracy'   => $opponent_accuracy,
			'winner_id'           => $winner_id,
			'completed_at'        => current_time( 'mysql' ),
		);

		$this->db->update_battle( $battle_id, $update_data );

		// Update ELO ratings
		$this->update_elo_ratings( $winner_id, $loser_id );

		// Update user stats
		$this->update_user_stats( $winner_id, $loser_id, $battle_id );

		// Check for badges
		$this->check_battle_badges( $winner_id, $battle_id );
		$this->check_battle_badges( $loser_id, $battle_id );

		$results = array(
			'winner_id'    => $winner_id,
			'loser_id'     => $loser_id,
			'winner_score' => $winner_score,
			'loser_score'  => $loser_score,
			'battle_data'  => array_merge( $battle, $update_data ),
		);

		do_action( 'qba_battle_completed', $battle_id, $results, $battle );

		return $results;
	}

	/**
	 * Check if answer is correct
	 *
	 * @since 1.0.0
	 * @param string $user_answer User answer
	 * @param array  $question_data Question data
	 * @return bool Correct or not
	 */
	private function check_answer_correctness( $user_answer, $question_data ) {
		$correct_answers = $question_data['correct_answer'];

		// Handle different question types
		if ( is_array( $correct_answers ) ) {
			return in_array( $user_answer, $correct_answers );
		}

		return $user_answer === $correct_answers;
	}

	/**
	 * Calculate player score from progress
	 *
	 * @since 1.0.0
	 * @param array $progress All battle progress
	 * @param int   $user_id  User ID
	 * @return int Total score
	 */
	private function calculate_player_score( $progress, $user_id ) {
		if ( ! isset( $progress[ $user_id ] ) ) {
			return 0;
		}

		$score = 0;
		foreach ( $progress[ $user_id ] as $answer ) {
			$score += $answer['points_earned'];
		}

		return $score;
	}

	/**
	 * Calculate player accuracy
	 *
	 * @since 1.0.0
	 * @param array $progress All battle progress
	 * @param int   $user_id  User ID
	 * @return float Accuracy percentage
	 */
	private function calculate_player_accuracy( $progress, $user_id ) {
		if ( ! isset( $progress[ $user_id ] ) || empty( $progress[ $user_id ] ) ) {
			return 0.0;
		}

		$correct = 0;
		$total   = count( $progress[ $user_id ] );

		foreach ( $progress[ $user_id ] as $answer ) {
			if ( $answer['is_correct'] ) {
				++$correct;
			}
		}

		return round( ( $correct / $total ) * 100, 2 );
	}

	/**
	 * Update ELO ratings after battle
	 *
	 * @since 1.0.0
	 * @param int $winner_id Winner user ID
	 * @param int $loser_id  Loser user ID
	 */
	private function update_elo_ratings( $winner_id, $loser_id ) {
		$winner_elo = get_user_meta( $winner_id, 'qba_elo_rating', true ) ?: 1000;
		$loser_elo  = get_user_meta( $loser_id, 'qba_elo_rating', true ) ?: 1000;

		$elo_changes = qba_calculate_elo_change( $winner_elo, $loser_elo );

		$new_winner_elo = $winner_elo + $elo_changes['winner_change'];
		$new_loser_elo  = $loser_elo + $elo_changes['loser_change'];

		update_user_meta( $winner_id, 'qba_elo_rating', $new_winner_elo );
		update_user_meta( $loser_id, 'qba_elo_rating', $new_loser_elo );
	}

	/**
	 * Update user statistics
	 *
	 * @since 1.0.0
	 * @param int $winner_id Winner user ID
	 * @param int $loser_id  Loser user ID
	 * @param int $battle_id Battle ID
	 */
	private function update_user_stats( $winner_id, $loser_id, $battle_id ) {
		// Update winner stats
		$winner_stats = qba_get_user_stats( $winner_id );
		qba_update_user_stats(
			$winner_id,
			array(
				'total_battles'   => $winner_stats['total_battles'] + 1,
				'battles_won'     => $winner_stats['battles_won'] + 1,
				'win_streak'      => $winner_stats['win_streak'] + 1,
				'best_win_streak' => max( $winner_stats['best_win_streak'], $winner_stats['win_streak'] + 1 ),
				'last_battle_at'  => current_time( 'mysql' ),
			)
		);

		// Update loser stats
		$loser_stats = qba_get_user_stats( $loser_id );
		qba_update_user_stats(
			$loser_id,
			array(
				'total_battles'  => $loser_stats['total_battles'] + 1,
				'battles_lost'   => $loser_stats['battles_lost'] + 1,
				'win_streak'     => 0, // Reset win streak
				'last_battle_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Check for battle-related badges
	 *
	 * @since 1.0.0
	 * @param int $user_id   User ID
	 * @param int $battle_id Battle ID
	 */
	private function check_battle_badges( $user_id, $battle_id ) {
		$achievements  = new QBA_Achievements();
		$earned_badges = $achievements->check_badges( $user_id, $battle_id );

		foreach ( $earned_badges as $badge_id ) {
			do_action( 'qba_badge_earned', $user_id, $badge_id, $achievements->get_badge_data( $badge_id ) );
		}
	}

	/**
	 * Get battle questions
	 *
	 * @since 1.0.0
	 * @param int $battle_id Battle ID
	 * @return array Questions
	 */
	public function get_battle_questions( $battle_id ) {
		$battle = qba_get_battle( $battle_id );

		if ( ! $battle ) {
			return array();
		}

		return qba_get_battle_questions( $battle['quiz_id'] );
	}

	/**
	 * Get battle status
	 *
	 * @since 1.0.0
	 * @param int $battle_id Battle ID
	 * @param int $user_id   User ID
	 * @return array Status data
	 */
	public function get_battle_status( $battle_id, $user_id ) {
		$battle = qba_get_battle( $battle_id );

		if ( ! $battle ) {
			return array( 'error' => 'Battle not found' );
		}

		// Check if user is participant
		if ( $battle['challenger_id'] != $user_id && $battle['opponent_id'] != $user_id ) {
			return array( 'error' => 'Not a participant' );
		}

		$progress          = $this->db->get_battle_progress( $battle_id, $user_id );
		$opponent_id       = ( $battle['challenger_id'] == $user_id ) ? $battle['opponent_id'] : $battle['challenger_id'];
		$opponent_progress = $this->db->get_battle_progress( $battle_id, $opponent_id );

		return array(
			'battle'             => $battle,
			'user_progress'      => $progress,
			'opponent_progress'  => count( $opponent_progress ),
			'questions_answered' => count( $progress ),
			'total_questions'    => count( $this->get_battle_questions( $battle_id ) ),
		);
	}

	/**
	 * AJAX handler for creating a battle
	 *
	 * @since 1.0.0
	 */
	public function ajax_create_battle() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$quiz_id     = intval( $_POST['quiz_id'] ?? 0 );
		$opponent_id = intval( $_POST['opponent_id'] ?? 0 );
		$battle_type = sanitize_text_field( $_POST['battle_type'] ?? 'direct' );
		$user_id     = get_current_user_id();

		if ( ! $quiz_id || ! $user_id ) {
			wp_send_json_error( __( 'Invalid parameters', QBA_TEXT_DOMAIN ) );
		}

		// For direct challenges, opponent is required
		if ( $battle_type === 'direct' && ! $opponent_id ) {
			wp_send_json_error( __( 'Opponent is required for direct challenges', QBA_TEXT_DOMAIN ) );
		}

		// For random battles, use queue system
		if ( $battle_type === 'random' ) {
			$queue_manager = new QBA_Queue_Manager();
			$result        = $queue_manager->join_queue( $user_id, $quiz_id );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			}

			wp_send_json_success(
				array(
					'message'     => __( 'Joined matchmaking queue', QBA_TEXT_DOMAIN ),
					'queue_id'    => $result,
					'battle_type' => 'random',
				)
			);
		}

		// Create direct battle challenge
		$battle_id = $this->create_battle_challenge( $quiz_id, $user_id, $opponent_id, $battle_type );

		if ( is_wp_error( $battle_id ) ) {
			wp_send_json_error( $battle_id->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Battle challenge sent!', QBA_TEXT_DOMAIN ),
				'battle_id'   => $battle_id,
				'battle_type' => 'direct',
			)
		);
	}

	/**
	 * AJAX handler for joining matchmaking queue
	 *
	 * @since 1.0.0
	 */
	public function ajax_join_queue() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$quiz_id = intval( $_POST['quiz_id'] ?? 0 );
		$user_id = get_current_user_id();

		if ( ! $quiz_id || ! $user_id ) {
			wp_send_json_error( __( 'Invalid parameters', QBA_TEXT_DOMAIN ) );
		}

		$queue_manager = new QBA_Queue_Manager();
		$result        = $queue_manager->join_queue( $user_id, $quiz_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Joined matchmaking queue', QBA_TEXT_DOMAIN ),
				'queue_id' => $result,
			)
		);
	}

	/**
	 * AJAX handler for leaving matchmaking queue
	 *
	 * @since 1.0.0
	 */
	public function ajax_leave_queue() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( __( 'Invalid user', QBA_TEXT_DOMAIN ) );
		}

		$queue_manager = new QBA_Queue_Manager();
		$result        = $queue_manager->leave_queue( $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Left matchmaking queue', QBA_TEXT_DOMAIN ),
			)
		);
	}

	/**
	 * AJAX handler for accepting battle challenge
	 *
	 * @since 1.0.0
	 */
	public function ajax_accept_battle() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$battle_id = intval( $_POST['battle_id'] ?? 0 );
		$user_id   = get_current_user_id();

		if ( ! $battle_id || ! $user_id ) {
			wp_send_json_error( __( 'Invalid parameters', QBA_TEXT_DOMAIN ) );
		}

		$result = $this->accept_battle_challenge( $battle_id, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Battle accepted! Starting battle...', QBA_TEXT_DOMAIN ),
				'battle_id' => $battle_id,
			)
		);
	}

	/**
	 * AJAX handler for declining battle challenge
	 *
	 * @since 1.0.0
	 */
	public function ajax_decline_battle() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$battle_id = intval( $_POST['battle_id'] ?? 0 );
		$user_id   = get_current_user_id();

		if ( ! $battle_id || ! $user_id ) {
			wp_send_json_error( __( 'Invalid parameters', QBA_TEXT_DOMAIN ) );
		}

		$result = $this->decline_battle_challenge( $battle_id, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Battle challenge declined', QBA_TEXT_DOMAIN ),
			)
		);
	}

	/**
	 * AJAX handler for submitting battle answer
	 *
	 * @since 1.0.0
	 */
	public function ajax_submit_answer() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$battle_id   = intval( $_POST['battle_id'] ?? 0 );
		$question_id = intval( $_POST['question_id'] ?? 0 );
		$answer      = sanitize_text_field( $_POST['answer'] ?? '' );
		$time_taken  = floatval( $_POST['time_taken'] ?? 0 );

		if ( ! $battle_id || ! $question_id ) {
			wp_send_json_error( __( 'Invalid parameters', QBA_TEXT_DOMAIN ) );
		}

		$result = $this->submit_battle_answer( $battle_id, $question_id, $answer, $time_taken );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for getting battle results
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_battle_results() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$battle_id = intval( $_POST['battle_id'] ?? 0 );
		$user_id   = get_current_user_id();

		if ( ! $battle_id || ! $user_id ) {
			wp_send_json_error( __( 'Invalid parameters', QBA_TEXT_DOMAIN ) );
		}

		$battle = qba_get_battle( $battle_id );

		if ( ! $battle ) {
			wp_send_json_error( __( 'Battle not found', QBA_TEXT_DOMAIN ) );
		}

		if ( $battle['challenger_id'] != $user_id && $battle['opponent_id'] != $user_id ) {
			wp_send_json_error( __( 'You are not part of this battle', QBA_TEXT_DOMAIN ) );
		}

		if ( $battle['status'] !== 'completed' ) {
			wp_send_json_error( __( 'Battle is not completed yet', QBA_TEXT_DOMAIN ) );
		}

		$results = array(
			'battle_id'           => $battle_id,
			'status'              => $battle['status'],
			'winner_id'           => $battle['winner_id'],
			'challenger_score'    => $battle['challenger_score'],
			'opponent_score'      => $battle['opponent_score'],
			'challenger_accuracy' => $battle['challenger_accuracy'],
			'opponent_accuracy'   => $battle['opponent_accuracy'],
			'completed_at'        => $battle['completed_at'],
			'is_winner'           => $battle['winner_id'] == $user_id,
		);

		wp_send_json_success( $results );
	}
}
