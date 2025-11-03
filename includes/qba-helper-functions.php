<?php
/**
 * Quiz Battle Arena Helper Functions
 *
 * Global utility functions used throughout the plugin
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get battle by ID
 *
 * @since 1.0.0
 * @param int $battle_id Battle ID
 * @return array|null Battle data or null if not found
 */
function qba_get_battle( $battle_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'qba_battles';

	$battle = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$battle_id
		),
		ARRAY_A
	);

	if ( ! $battle ) {
		return null;
	}

	// Enrich with additional data
	$battle['quiz']       = get_post( $battle['quiz_id'] );
	$battle['challenger'] = get_userdata( $battle['challenger_id'] );
	$battle['opponent']   = get_userdata( $battle['opponent_id'] );

	return $battle;
}

/**
 * Get user statistics
 *
 * @since 1.0.0
 * @param int $user_id User ID
 * @return array User statistics
 */
function qba_get_user_stats( $user_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'qba_user_stats';

	$stats = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d",
			$user_id
		),
		ARRAY_A
	);

	// Initialize stats if not exist
	if ( ! $stats ) {
		$stats = array(
			'user_id'                  => $user_id,
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
		);
	}

	return $stats;
}

/**
 * Update user statistics
 *
 * @since 1.0.0
 * @param int   $user_id User ID
 * @param array $updates Statistics to update
 * @return bool Success
 */
function qba_update_user_stats( $user_id, $updates ) {
	global $wpdb;
	$table = $wpdb->prefix . 'qba_user_stats';

	// Get current stats
	$current = qba_get_user_stats( $user_id );

	// Merge updates
	$data               = array_merge( $current, $updates );
	$data['updated_at'] = current_time( 'mysql' );

	// Check if record exists
	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
			$user_id
		)
	);

	if ( $exists ) {
		return $wpdb->update(
			$table,
			$data,
			array( 'user_id' => $user_id )
		);
	} else {
		$data['created_at'] = current_time( 'mysql' );
		return $wpdb->insert( $table, $data );
	}
}

/**
 * Calculate ELO rating change
 *
 * @since 1.0.0
 * @param int $winner_elo   Winner's current ELO
 * @param int $loser_elo    Loser's current ELO
 * @param int $k_factor     K-factor (default: 32)
 * @return array ['winner_change' => int, 'loser_change' => int]
 */
function qba_calculate_elo_change( $winner_elo, $loser_elo, $k_factor = 32 ) {
	// Calculate expected scores
	$expected_winner = 1 / ( 1 + pow( 10, ( $loser_elo - $winner_elo ) / 400 ) );
	$expected_loser  = 1 / ( 1 + pow( 10, ( $winner_elo - $loser_elo ) / 400 ) );

	// Calculate rating changes
	$winner_change = round( $k_factor * ( 1 - $expected_winner ) );
	$loser_change  = round( $k_factor * ( 0 - $expected_loser ) );

	return array(
		'winner_change' => $winner_change,
		'loser_change'  => $loser_change,
	);
}

/**
 * Calculate battle score with speed bonus
 *
 * @since 1.0.0
 * @param bool  $is_correct Is answer correct
 * @param float $time_taken Time taken in seconds
 * @param int   $base_points Base points for correct answer
 * @return int Total points earned
 */
function qba_calculate_answer_score( $is_correct, $time_taken, $base_points = 10 ) {
	if ( ! $is_correct ) {
		return 0;
	}

	$speed_bonus = 0;

	// Speed bonus tiers
	if ( $time_taken <= 2 ) {
		$speed_bonus = 5; // Lightning fast
	} elseif ( $time_taken <= 5 ) {
		$speed_bonus = 3; // Very fast
	} elseif ( $time_taken <= 10 ) {
		$speed_bonus = 1; // Fast
	}

	return $base_points + $speed_bonus;
}

/**
 * Check if user can access quiz
 *
 * @since 1.0.0
 * @param int $user_id User ID
 * @param int $quiz_id Quiz ID
 * @return bool Can access
 */
function qba_user_can_access_quiz( $user_id, $quiz_id ) {
	// Check if user is enrolled in course (if quiz is part of course)
	$course_id = learndash_get_course_id( $quiz_id );

	if ( $course_id ) {
		return sfwd_lms_has_access( $course_id, $user_id );
	}

	// For standalone quizzes, check if quiz is published
	$quiz = get_post( $quiz_id );
	return $quiz && $quiz->post_status === 'publish';
}

/**
 * Check if user is available for battle
 *
 * @since 1.0.0
 * @param int $user_id User ID
 * @return bool Is available
 */
function qba_is_user_available_for_battle( $user_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'qba_battles';

	// Check if user is in active battle
	$active_battles = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} 
		WHERE (challenger_id = %d OR opponent_id = %d) 
		AND status IN ('pending', 'active')",
			$user_id,
			$user_id
		)
	);

	return $active_battles == 0;
}

/**
 * Get available opponents for user
 *
 * @since 1.0.0
 * @param int   $user_id User ID
 * @param int   $quiz_id Quiz ID
 * @param array $args    Additional arguments
 * @return array List of available users
 */
function qba_get_available_opponents( $user_id, $quiz_id, $args = array() ) {
	$defaults = array(
		'limit'          => 20,
		'elo_range'      => 200,
		'exclude_recent' => true,
	);

	$args = wp_parse_args( $args, $defaults );

	// Get users enrolled in same course
	$course_id      = learndash_get_course_id( $quiz_id );
	$enrolled_users = array();

	if ( $course_id ) {
		$enrolled_users = learndash_get_users_for_course( $course_id, array(), false );
	} else {
		// Get all users if standalone quiz
		$enrolled_users = get_users( array( 'fields' => 'ID' ) );
	}

	// Filter available users
	$available = array();
	$user_elo  = get_user_meta( $user_id, 'qba_elo_rating', true ) ?: 1000;

	foreach ( $enrolled_users as $opponent_id ) {
		if ( $opponent_id == $user_id ) {
			continue;
		}

		// Check availability
		if ( ! qba_is_user_available_for_battle( $opponent_id ) ) {
			continue;
		}

		// Check ELO range for skill-based matching
		$opponent_elo = get_user_meta( $opponent_id, 'qba_elo_rating', true ) ?: 1000;
		if ( abs( $user_elo - $opponent_elo ) > $args['elo_range'] ) {
			continue;
		}

		$opponent    = get_userdata( $opponent_id );
		$available[] = array(
			'id'     => $opponent_id,
			'name'   => $opponent->display_name,
			'avatar' => get_avatar_url( $opponent_id ),
			'elo'    => $opponent_elo,
			'online' => qba_is_user_online( $opponent_id ),
		);
	}

	// Sort by ELO similarity
	usort(
		$available,
		function ( $a, $b ) use ( $user_elo ) {
			$diff_a = abs( $user_elo - $a['elo'] );
			$diff_b = abs( $user_elo - $b['elo'] );
			return $diff_a - $diff_b;
		}
	);

	return array_slice( $available, 0, $args['limit'] );
}

/**
 * Check if user is online (active in last 5 minutes)
 *
 * @since 1.0.0
 * @param int $user_id User ID
 * @return bool Is online
 */
function qba_is_user_online( $user_id ) {
	$last_activity = get_user_meta( $user_id, 'qba_last_activity', true );

	if ( ! $last_activity ) {
		return false;
	}

	$threshold = strtotime( '-5 minutes' );
	return strtotime( $last_activity ) > $threshold;
}

/**
 * Update user online status
 *
 * @since 1.0.0
 * @param int $user_id User ID (default: current user)
 * @return bool Success
 */
function qba_update_user_activity( $user_id = null ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return false;
	}

	return update_user_meta( $user_id, 'qba_last_activity', current_time( 'mysql' ) );
}

/**
 * Get relative time string (e.g., "5 minutes ago")
 *
 * @since 1.0.0
 * @param string $datetime MySQL datetime
 * @return string Relative time
 */
function qba_get_relative_time( $datetime ) {
	$timestamp = strtotime( $datetime );
	$diff      = time() - $timestamp;

	if ( $diff < 60 ) {
		return __( 'Just now', 'quiz-battle-arena' );
	} elseif ( $diff < 3600 ) {
		$minutes = floor( $diff / 60 );
		return sprintf( _n( '%d minute ago', '%d minutes ago', $minutes, 'quiz-battle-arena' ), $minutes );
	} elseif ( $diff < 86400 ) {
		$hours = floor( $diff / 3600 );
		return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'quiz-battle-arena' ), $hours );
	} else {
		$days = floor( $diff / 86400 );
		return sprintf( _n( '%d day ago', '%d days ago', $days, 'quiz-battle-arena' ), $days );
	}
}

/**
 * Generate battle summary for activity feed
 *
 * @since 1.0.0
 * @param int $battle_id Battle ID
 * @return string Battle summary HTML
 */
function qba_generate_battle_summary( $battle_id ) {
	$battle = qba_get_battle( $battle_id );

	if ( ! $battle ) {
		return '';
	}

	$summary = sprintf(
		__( 'Final Score: %1$s (%2$d) vs %3$s (%4$d)', 'quiz-battle-arena' ),
		$battle['challenger']->display_name,
		$battle['challenger_score'],
		$battle['opponent']->display_name,
		$battle['opponent_score']
	);

	return $summary;
}

/**
 * Add points to user account
 *
 * @since 1.0.0
 * @param int    $user_id User ID
 * @param int    $points  Points to add
 * @param string $reason  Reason for points
 * @return bool Success
 */
function qba_add_user_points( $user_id, $points, $reason = '' ) {
	$current_stats = qba_get_user_stats( $user_id );
	$new_points    = $current_stats['total_points'] + $points;

	$result = qba_update_user_stats(
		$user_id,
		array(
			'total_points' => $new_points,
		)
	);

	// Log points transaction
	do_action( 'qba_points_added', $user_id, $points, $reason, $new_points );

	return $result;
}

/**
 * Format number with suffix (e.g., 1.2K, 3.5M)
 *
 * @since 1.0.0
 * @param int $number Number to format
 * @return string Formatted number
 */
function qba_format_number( $number ) {
	if ( $number >= 1000000 ) {
		return round( $number / 1000000, 1 ) . 'M';
	} elseif ( $number >= 1000 ) {
		return round( $number / 1000, 1 ) . 'K';
	}
	return $number;
}

/**
 * Get quiz questions for battle
 *
 * @since 1.0.0
 * @param int $quiz_id Quiz ID
 * @return array Questions array
 */
function qba_get_battle_questions( $quiz_id ) {
	$questions = learndash_get_quiz_questions( $quiz_id );

	$formatted_questions = array();

	foreach ( $questions as $question ) {
		$question_data = learndash_get_question_data( $question['id'] );

		$formatted_questions[] = array(
			'id'             => $question['id'],
			'question'       => $question_data['question'],
			'image'          => $question_data['image'] ?? null,
			'answers'        => $question_data['answers'],
			'correct_answer' => $question_data['correct_answer'],
		);
	}

	return $formatted_questions;
}

/**
 * Sanitize battle data for JSON output
 *
 * @since 1.0.0
 * @param array $battle Battle data
 * @return array Sanitized battle data
 */
function qba_sanitize_battle_for_json( $battle ) {
	return array(
		'id'              => (int) $battle['id'],
		'quiz_id'         => (int) $battle['quiz_id'],
		'quiz_title'      => esc_html( $battle['quiz']->post_title ),
		'challenger_id'   => (int) $battle['challenger_id'],
		'challenger_name' => esc_html( $battle['challenger']->display_name ),
		'opponent_id'     => (int) $battle['opponent_id'],
		'opponent_name'   => esc_html( $battle['opponent']->display_name ),
		'status'          => sanitize_text_field( $battle['status'] ),
		'created_at'      => $battle['created_at'],
		'started_at'      => $battle['started_at'],
		'completed_at'    => $battle['completed_at'],
	);
}

/**
 * Get question data for battle
 *
 * @since 1.0.0
 * @param int $question_id Question ID
 * @return array|null Question data or null if not found
 */

/**
 * Complete a battle and calculate results
 *
 * @since 1.0.0
 * @param int $battle_id Battle ID
 * @return bool Success
 */
function qba_complete_battle( $battle_id ) {
	$battle_engine = new QBA_Battle_Engine();
	return $battle_engine->complete_battle( $battle_id );
}

/**
 * Get user badges
 *
 * @since 1.0.0
 * @param int $user_id User ID
 * @return array User badges
 */
function qba_get_user_badges( $user_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'qba_user_badges';

	$badges = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY earned_at DESC",
			$user_id
		),
		ARRAY_A
	);

	return $badges ?: array();
}

/**
 * Update user points
 *
 * @since 1.0.0
 * @param int    $user_id User ID
 * @param int    $points Points to add (can be negative)
 * @param string $reason Reason for points change
 * @return bool Success
 */
function qba_update_user_points( $user_id, $points, $reason = '' ) {
	$user_stats = qba_get_user_stats( $user_id );

	if ( ! $user_stats ) {
		return false;
	}

	$new_points = max( 0, $user_stats['total_points'] + $points );

	return qba_update_user_stats(
		$user_id,
		array(
			'total_points' => $new_points,
		)
	);
}
