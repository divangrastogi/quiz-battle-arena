<?php
/**
 * Real-time Handler Class
 *
 * Handles real-time battle interactions and AJAX responses
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Realtime_Handler Class
 *
 * Manages real-time battle functionality
 *
 * @since 1.0.0
 */
class QBA_Realtime_Handler {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor can be empty
	}

	/**
	 * Handle battle sync AJAX request
	 *
	 * @since 1.0.0
	 */
	public function ajax_battle_sync() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$battle_id = intval( $_POST['battle_id'] ?? 0 );
		$user_id   = get_current_user_id();

		if ( ! $battle_id || ! $user_id ) {
			wp_send_json_error( __( 'Invalid battle or user ID', QBA_TEXT_DOMAIN ) );
		}

		// Get battle data
		$battle = qba_get_battle( $battle_id );
		if ( ! $battle ) {
			wp_send_json_error( __( 'Battle not found', QBA_TEXT_DOMAIN ) );
		}

		// Check if user is part of this battle
		if ( $battle['challenger_id'] != $user_id && $battle['opponent_id'] != $user_id ) {
			wp_send_json_error( __( 'You are not part of this battle', QBA_TEXT_DOMAIN ) );
		}

		// Get opponent info
		$opponent_id = ( $battle['challenger_id'] == $user_id ) ? $battle['opponent_id'] : $battle['challenger_id'];
		$opponent    = get_userdata( $opponent_id );

		// Get current battle progress
		$progress          = $this->get_battle_progress( $battle_id, $user_id );
		$opponent_progress = $this->get_battle_progress( $battle_id, $opponent_id );

		// Calculate battle state
		$battle_state = array(
			'battle_id'         => $battle_id,
			'status'            => $battle['status'],
			'current_question'  => $this->get_current_question_number( $battle_id, $user_id ),
			'total_questions'   => get_option( 'qba_max_questions', 10 ),
			'time_remaining'    => $this->get_time_remaining( $battle ),
			'opponent_name'     => $opponent ? $opponent->display_name : __( 'Unknown Player', QBA_TEXT_DOMAIN ),
			'opponent_progress' => count( $opponent_progress ),
			'user_progress'     => count( $progress ),
			'is_user_turn'      => true, // For now, both players go simultaneously
			'can_answer'        => $battle['status'] === 'active',
		);

		// Get current question if battle is active
		if ( $battle['status'] === 'active' ) {
			$current_question = $this->get_current_question( $battle_id, $user_id );
			if ( $current_question ) {
				$battle_state['current_question_data'] = array(
					'id'            => $current_question['id'],
					'question'      => $current_question['question'],
					'answers'       => $current_question['answers'],
					'question_type' => $current_question['question_type'],
				);
			}
		}

		wp_send_json_success( $battle_state );
	}

	/**
	 * Handle answer submission AJAX request
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
		$user_id     = get_current_user_id();

		if ( ! $battle_id || ! $question_id || ! $user_id ) {
			wp_send_json_error( __( 'Invalid parameters', QBA_TEXT_DOMAIN ) );
		}

		// Get battle data
		$battle = qba_get_battle( $battle_id );
		if ( ! $battle || $battle['status'] !== 'active' ) {
			wp_send_json_error( __( 'Battle is not active', QBA_TEXT_DOMAIN ) );
		}

		// Check if user is part of this battle
		if ( $battle['challenger_id'] != $user_id && $battle['opponent_id'] != $user_id ) {
			wp_send_json_error( __( 'You are not part of this battle', QBA_TEXT_DOMAIN ) );
		}

		// Check if question has already been answered
		if ( $this->has_answered_question( $battle_id, $user_id, $question_id ) ) {
			wp_send_json_error( __( 'Question already answered', QBA_TEXT_DOMAIN ) );
		}

		// Get correct answer
		$question_data = qba_get_question_data( $question_id );
		$is_correct    = $this->check_answer( $question_data, $answer );
		$points_earned = $this->calculate_points( $is_correct, $time_taken );

		// Record the answer
		$result = $this->record_answer( $battle_id, $user_id, $question_id, $answer, $is_correct, $points_earned, $time_taken );

		if ( ! $result ) {
			wp_send_json_error( __( 'Failed to record answer', QBA_TEXT_DOMAIN ) );
		}

		// Check if battle should be completed
		$this->check_battle_completion( $battle_id );

		wp_send_json_success(
			array(
				'correct'       => $is_correct,
				'points_earned' => $points_earned,
				'time_taken'    => $time_taken,
				'next_question' => $this->get_next_question( $battle_id, $user_id ),
			)
		);
	}

	/**
	 * Get battle progress for a user
	 *
	 * @since 1.0.0
	 * @param int $battle_id The battle ID
	 * @param int $user_id The user ID
	 * @return array
	 */
	private function get_battle_progress( $battle_id, $user_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT * FROM {$wpdb->prefix}qba_battle_progress
			WHERE battle_id = %d AND user_id = %d
			ORDER BY question_order ASC
		",
				$battle_id,
				$user_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get current question number for user
	 *
	 * @since 1.0.0
	 * @param int $battle_id The battle ID
	 * @param int $user_id The user ID
	 * @return int
	 */
	private function get_current_question_number( $battle_id, $user_id ) {
		$progress = $this->get_battle_progress( $battle_id, $user_id );
		return count( $progress ) + 1;
	}

	/**
	 * Get time remaining for battle
	 *
	 * @since 1.0.0
	 * @param array $battle The battle data
	 * @return int
	 */
	private function get_time_remaining( $battle ) {
		if ( $battle['status'] !== 'active' || ! $battle['started_at'] ) {
			return 0;
		}

		$battle_timeout = get_option( 'qba_battle_timeout', 900 );
		$elapsed        = current_time( 'timestamp' ) - strtotime( $battle['started_at'] );

		return max( 0, $battle_timeout - $elapsed );
	}

	/**
	 * Get current question for user
	 *
	 * @since 1.0.0
	 * @param int $battle_id The battle ID
	 * @param int $user_id The user ID
	 * @return array|null
	 */
	private function get_current_question( $battle_id, $user_id ) {
		$question_number = $this->get_current_question_number( $battle_id, $user_id );
		$max_questions   = get_option( 'qba_max_questions', 10 );

		if ( $question_number > $max_questions ) {
			return null;
		}

		// Get battle questions
		$battle_questions = qba_get_battle_questions( $battle_id );
		if ( ! isset( $battle_questions[ $question_number - 1 ] ) ) {
			return null;
		}

		return qba_get_question_data( $battle_questions[ $question_number - 1 ] );
	}

	/**
	 * Check if user has already answered a question
	 *
	 * @since 1.0.0
	 * @param int $battle_id The battle ID
	 * @param int $user_id The user ID
	 * @param int $question_id The question ID
	 * @return bool
	 */
	private function has_answered_question( $battle_id, $user_id, $question_id ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT COUNT(*) FROM {$wpdb->prefix}qba_battle_progress
			WHERE battle_id = %d AND user_id = %d AND question_id = %d
		",
				$battle_id,
				$user_id,
				$question_id
			)
		);

		return $count > 0;
	}

	/**
	 * Check if answer is correct
	 *
	 * @since 1.0.0
	 * @param array  $question_data The question data
	 * @param string $answer The submitted answer
	 * @return bool
	 */
	private function check_answer( $question_data, $answer ) {
		if ( ! $question_data ) {
			return false;
		}

		// This would need to be adapted based on LearnDash question types
		// For now, simple string comparison
		$correct_answer = $question_data['correct_answer'] ?? '';
		return strtolower( trim( $answer ) ) === strtolower( trim( $correct_answer ) );
	}

	/**
	 * Calculate points for answer
	 *
	 * @since 1.0.0
	 * @param bool  $is_correct Whether answer is correct
	 * @param float $time_taken Time taken to answer
	 * @return int
	 */
	private function calculate_points( $is_correct, $time_taken ) {
		if ( ! $is_correct ) {
			return 0;
		}

		$base_points     = 10; // Base points for correct answer
		$speed_bonus_max = get_option( 'qba_speed_bonus_max', 5 );
		$time_limit      = 30; // Assume 30 seconds per question

		// Speed bonus: faster answers get more points
		$speed_ratio = max( 0, ( $time_limit - $time_taken ) / $time_limit );
		$speed_bonus = round( $speed_bonus_max * $speed_ratio );

		return $base_points + $speed_bonus;
	}

	/**
	 * Record answer in database
	 *
	 * @since 1.0.0
	 * @param int    $battle_id The battle ID
	 * @param int    $user_id The user ID
	 * @param int    $question_id The question ID
	 * @param string $answer The answer given
	 * @param bool   $is_correct Whether answer is correct
	 * @param int    $points_earned Points earned
	 * @param float  $time_taken Time taken
	 * @return bool
	 */
	private function record_answer( $battle_id, $user_id, $question_id, $answer, $is_correct, $points_earned, $time_taken ) {
		global $wpdb;

		$question_order = $this->get_current_question_number( $battle_id, $user_id );

		$result = $wpdb->insert(
			$wpdb->prefix . 'qba_battle_progress',
			array(
				'battle_id'      => $battle_id,
				'user_id'        => $user_id,
				'question_id'    => $question_id,
				'question_order' => $question_order,
				'answer_given'   => $answer,
				'is_correct'     => $is_correct ? 1 : 0,
				'points_earned'  => $points_earned,
				'time_taken'     => $time_taken,
				'answered_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%f', '%s' )
		);

		return $result !== false;
	}

	/**
	 * Check if battle should be completed
	 *
	 * @since 1.0.0
	 * @param int $battle_id The battle ID
	 */
	private function check_battle_completion( $battle_id ) {
		$battle = qba_get_battle( $battle_id );
		if ( ! $battle || $battle['status'] !== 'active' ) {
			return;
		}

		$max_questions       = get_option( 'qba_max_questions', 10 );
		$challenger_progress = $this->get_battle_progress( $battle_id, $battle['challenger_id'] );
		$opponent_progress   = $this->get_battle_progress( $battle_id, $battle['opponent_id'] );

		// Check if both players have answered all questions
		if ( count( $challenger_progress ) >= $max_questions && count( $opponent_progress ) >= $max_questions ) {
			// Complete the battle
			qba_complete_battle( $battle_id );
		}
	}

	/**
	 * Get next question for user
	 *
	 * @since 1.0.0
	 * @param int $battle_id The battle ID
	 * @param int $user_id The user ID
	 * @return array|null
	 */
	private function get_next_question( $battle_id, $user_id ) {
		$question = $this->get_current_question( $battle_id, $user_id );
		if ( $question ) {
			return array(
				'id'            => $question['id'],
				'question'      => $question['question'],
				'answers'       => $question['answers'],
				'question_type' => $question['question_type'],
			);
		}
		return null;
	}
}
