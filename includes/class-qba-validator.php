<?php
/**
 * Input Validation Class
 *
 * Handles all input validation and sanitization
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Validator Class
 *
 * @since 1.0.0
 */
class QBA_Validator {

	/**
	 * Validate battle creation data
	 *
	 * @since 1.0.0
	 * @param array $data Battle creation data
	 * @return bool|WP_Error True if valid, WP_Error if invalid
	 */
	public function validate_battle_creation( $data ) {
		// Required fields
		$required = array( 'quiz_id', 'challenger_id', 'opponent_id' );
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Missing required field: %s', 'quiz-battle-arena' ), $field ) );
			}
		}

		// Validate quiz ID
		if ( ! $this->is_valid_quiz_id( $data['quiz_id'] ) ) {
			return new WP_Error( 'invalid_quiz', __( 'Invalid quiz ID', 'quiz-battle-arena' ) );
		}

		// Validate user IDs
		if ( ! $this->is_valid_user_id( $data['challenger_id'] ) ) {
			return new WP_Error( 'invalid_challenger', __( 'Invalid challenger ID', 'quiz-battle-arena' ) );
		}

		if ( ! $this->is_valid_user_id( $data['opponent_id'] ) ) {
			return new WP_Error( 'invalid_opponent', __( 'Invalid opponent ID', 'quiz-battle-arena' ) );
		}

		// Check if users are different
		if ( $data['challenger_id'] == $data['opponent_id'] ) {
			return new WP_Error( 'same_user', __( 'Cannot challenge yourself', 'quiz-battle-arena' ) );
		}

		// Check if challenger can access quiz
		if ( ! qba_user_can_access_quiz( $data['challenger_id'], $data['quiz_id'] ) ) {
			return new WP_Error( 'access_denied', __( 'Challenger does not have access to this quiz', 'quiz-battle-arena' ) );
		}

		// Check if opponent can access quiz
		if ( ! qba_user_can_access_quiz( $data['opponent_id'], $data['quiz_id'] ) ) {
			return new WP_Error( 'opponent_access_denied', __( 'Opponent does not have access to this quiz', 'quiz-battle-arena' ) );
		}

		// Check if opponent is available
		if ( ! qba_is_user_available_for_battle( $data['opponent_id'] ) ) {
			return new WP_Error( 'opponent_unavailable', __( 'Opponent is currently in another battle', 'quiz-battle-arena' ) );
		}

		// Validate challenge type
		if ( isset( $data['challenge_type'] ) && ! in_array( $data['challenge_type'], array( 'direct', 'queue' ) ) ) {
			return new WP_Error( 'invalid_challenge_type', __( 'Invalid challenge type', 'quiz-battle-arena' ) );
		}

		return true;
	}

	/**
	 * Validate answer submission
	 *
	 * @since 1.0.0
	 * @param array $data Answer submission data
	 * @return bool|WP_Error True if valid, WP_Error if invalid
	 */
	public function validate_answer_submission( $data ) {
		// Required fields
		$required = array( 'battle_id', 'question_id', 'answer', 'time_taken' );
		foreach ( $required as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Missing required field: %s', 'quiz-battle-arena' ), $field ) );
			}
		}

		// Validate battle ID
		if ( ! $this->is_valid_battle_id( $data['battle_id'] ) ) {
			return new WP_Error( 'invalid_battle', __( 'Invalid battle ID', 'quiz-battle-arena' ) );
		}

		// Validate question ID
		if ( ! $this->is_valid_question_id( $data['question_id'] ) ) {
			return new WP_Error( 'invalid_question', __( 'Invalid question ID', 'quiz-battle-arena' ) );
		}

		// Validate time taken
		if ( ! is_numeric( $data['time_taken'] ) || $data['time_taken'] < 0 ) {
			return new WP_Error( 'invalid_time', __( 'Invalid time taken', 'quiz-battle-arena' ) );
		}

		// Check if battle is active
		$battle = qba_get_battle( $data['battle_id'] );
		if ( ! $battle || $battle['status'] !== 'active' ) {
			return new WP_Error( 'battle_not_active', __( 'Battle is not active', 'quiz-battle-arena' ) );
		}

		// Check if user is participant
		$user_id = get_current_user_id();
		if ( $battle['challenger_id'] != $user_id && $battle['opponent_id'] != $user_id ) {
			return new WP_Error( 'not_participant', __( 'You are not a participant in this battle', 'quiz-battle-arena' ) );
		}

		// Check if question belongs to battle quiz
		if ( ! $this->question_belongs_to_quiz( $data['question_id'], $battle['quiz_id'] ) ) {
			return new WP_Error( 'question_not_in_quiz', __( 'Question does not belong to this quiz', 'quiz-battle-arena' ) );
		}

		return true;
	}

	/**
	 * Validate queue join data
	 *
	 * @since 1.0.0
	 * @param array $data Queue join data
	 * @return bool|WP_Error True if valid, WP_Error if invalid
	 */
	public function validate_queue_join( $data ) {
		// Required fields
		if ( empty( $data['quiz_id'] ) ) {
			return new WP_Error( 'missing_quiz_id', __( 'Quiz ID is required', 'quiz-battle-arena' ) );
		}

		// Validate quiz ID
		if ( ! $this->is_valid_quiz_id( $data['quiz_id'] ) ) {
			return new WP_Error( 'invalid_quiz', __( 'Invalid quiz ID', 'quiz-battle-arena' ) );
		}

		// Validate queue type
		if ( isset( $data['queue_type'] ) && ! in_array( $data['queue_type'], array( 'random', 'skill' ) ) ) {
			return new WP_Error( 'invalid_queue_type', __( 'Invalid queue type', 'quiz-battle-arena' ) );
		}

		// Check if user can access quiz
		$user_id = get_current_user_id();
		if ( ! qba_user_can_access_quiz( $user_id, $data['quiz_id'] ) ) {
			return new WP_Error( 'access_denied', __( 'You do not have access to this quiz', 'quiz-battle-arena' ) );
		}

		// Check if user is already in queue
		if ( qba_is_user_in_queue( $user_id ) ) {
			return new WP_Error( 'already_queued', __( 'You are already in the matchmaking queue', 'quiz-battle-arena' ) );
		}

		// Check if user is available for battle
		if ( ! qba_is_user_available_for_battle( $user_id ) ) {
			return new WP_Error( 'user_unavailable', __( 'You are currently in another battle', 'quiz-battle-arena' ) );
		}

		return true;
	}

	/**
	 * Sanitize battle creation data
	 *
	 * @since 1.0.0
	 * @param array $data Raw data
	 * @return array Sanitized data
	 */
	public function sanitize_battle_creation( $data ) {
		return array(
			'quiz_id'        => absint( $data['quiz_id'] ),
			'challenger_id'  => absint( $data['challenger_id'] ),
			'opponent_id'    => absint( $data['opponent_id'] ),
			'challenge_type' => sanitize_text_field( $data['challenge_type'] ?? 'direct' ),
			'created_at'     => current_time( 'mysql' ),
			'expires_at'     => date( 'Y-m-d H:i:s', strtotime( '+5 minutes' ) ),
		);
	}

	/**
	 * Sanitize answer submission data
	 *
	 * @since 1.0.0
	 * @param array $data Raw data
	 * @return array Sanitized data
	 */
	public function sanitize_answer_submission( $data ) {
		return array(
			'battle_id'    => absint( $data['battle_id'] ),
			'user_id'      => get_current_user_id(),
			'question_id'  => absint( $data['question_id'] ),
			'answer_given' => sanitize_text_field( $data['answer'] ),
			'time_taken'   => floatval( $data['time_taken'] ),
			'answered_at'  => current_time( 'mysql' ),
		);
	}

	/**
	 * Check if quiz ID is valid
	 *
	 * @since 1.0.0
	 * @param int $quiz_id Quiz ID
	 * @return bool
	 */
	private function is_valid_quiz_id( $quiz_id ) {
		return is_numeric( $quiz_id ) && $quiz_id > 0 && get_post_type( $quiz_id ) === 'sfwd-quiz';
	}

	/**
	 * Check if user ID is valid
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID
	 * @return bool
	 */
	private function is_valid_user_id( $user_id ) {
		return is_numeric( $user_id ) && $user_id > 0 && get_userdata( $user_id ) !== false;
	}

	/**
	 * Check if battle ID is valid
	 *
	 * @since 1.0.0
	 * @param int $battle_id Battle ID
	 * @return bool
	 */
	private function is_valid_battle_id( $battle_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_battles';
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $battle_id ) );
		return $count > 0;
	}

	/**
	 * Check if question ID is valid
	 *
	 * @since 1.0.0
	 * @param int $question_id Question ID
	 * @return bool
	 */
	private function is_valid_question_id( $question_id ) {
		return is_numeric( $question_id ) && $question_id > 0;
	}

	/**
	 * Check if question belongs to quiz
	 *
	 * @since 1.0.0
	 * @param int $question_id Question ID
	 * @param int $quiz_id Quiz ID
	 * @return bool
	 */
	private function question_belongs_to_quiz( $question_id, $quiz_id ) {
		$questions    = learndash_get_quiz_questions( $quiz_id );
		$question_ids = wp_list_pluck( $questions, 'id' );
		return in_array( $question_id, $question_ids );
	}
}
