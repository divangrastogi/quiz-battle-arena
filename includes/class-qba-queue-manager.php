<?php
/**
 * Queue Manager Class
 *
 * Handles matchmaking queue operations
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Queue_Manager Class
 *
 * @since 1.0.0
 */
class QBA_Queue_Manager {

	/**
	 * Database instance
	 *
	 * @since 1.0.0
	 * @var QBA_Database
	 */
	private $db;

	/**
	 * Initialize queue manager
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->db = new QBA_Database();

		// Schedule queue processing
		if ( ! wp_next_scheduled( 'qba_process_matchmaking_queue' ) ) {
			wp_schedule_event( time(), 'qba_queue_interval', 'qba_process_matchmaking_queue' );
		}

		// Hook into cron
		add_action( 'qba_process_matchmaking_queue', array( $this, 'process_queue' ) );

		// Clean up expired entries
		add_action( 'qba_cleanup_expired_queues', array( $this, 'cleanup_expired_entries' ) );
	}

	/**
	 * Add user to matchmaking queue
	 *
	 * @since 1.0.0
	 * @param int    $user_id   User ID
	 * @param int    $quiz_id   Quiz ID
	 * @param string $queue_type Queue type
	 * @param array  $preferences Match preferences
	 * @return int|WP_Error Queue entry ID or error
	 */
	public function add_to_queue( $user_id, $quiz_id, $queue_type = 'random', $preferences = array() ) {
		// Validate input
		$validator  = new QBA_Validator();
		$validation = $validator->validate_queue_join(
			array(
				'quiz_id'    => $quiz_id,
				'queue_type' => $queue_type,
			)
		);

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Prepare queue data
		$queue_data = array(
			'user_id'     => $user_id,
			'quiz_id'     => $quiz_id,
			'queue_type'  => $queue_type,
			'preferences' => json_encode( $preferences ),
			'status'      => 'waiting',
			'joined_at'   => current_time( 'mysql' ),
		);

		// Insert into database
		$queue_id = $this->db->insert_queue_entry( $queue_data );

		if ( is_wp_error( $queue_id ) ) {
			return $queue_id;
		}

		// Try immediate matching
		$this->process_queue();

		// Update user activity
		qba_update_user_activity( $user_id );

		do_action( 'qba_user_joined_queue', $user_id, $queue_id, $queue_type );

		return $queue_id;
	}

	/**
	 * Remove user from matchmaking queue
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID
	 * @return bool Success
	 */
	public function remove_from_queue( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_matchmaking_queue';

		$result = $wpdb->update(
			$table,
			array( 'status' => 'cancelled' ),
			array(
				'user_id' => $user_id,
				'status'  => 'waiting',
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		if ( $result !== false ) {
			do_action( 'qba_user_left_queue', $user_id );
		}

		return $result !== false;
	}

	/**
	 * Get user's queue status
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID
	 * @return array|null Queue status or null if not in queue
	 */
	public function get_user_queue_status( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_matchmaking_queue';

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND status = 'waiting' ORDER BY joined_at DESC LIMIT 1",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $entry ) {
			return null;
		}

		// Calculate wait time
		$wait_time                    = time() - strtotime( $entry['joined_at'] );
		$entry['wait_time']           = $wait_time;
		$entry['wait_time_formatted'] = $this->format_wait_time( $wait_time );

		return $entry;
	}

	/**
	 * Process matchmaking queue
	 *
	 * @since 1.0.0
	 * @return array Created battles
	 */
	public function process_queue() {
		$created_battles = array();

		// Get all waiting entries grouped by quiz
		$waiting_entries = $this->db->get_waiting_queue_entries_grouped();

		foreach ( $waiting_entries as $quiz_id => $entries ) {
			if ( count( $entries ) < 2 ) {
				continue;
			}

			// Find matches for this quiz
			$matches = $this->find_matches( $entries );

			foreach ( $matches as $match ) {
				$battle_id = $this->create_battle_from_match( $match, $quiz_id );

				if ( ! is_wp_error( $battle_id ) ) {
					$created_battles[] = $battle_id;

					// Update queue entries
					$this->update_matched_entries( $match['player1']['id'], $match['player2']['id'], $battle_id );
				}
			}
		}

		return $created_battles;
	}

	/**
	 * Find optimal matches from queue entries
	 *
	 * @since 1.0.0
	 * @param array $entries Queue entries for a quiz
	 * @return array Matched pairs
	 */
	private function find_matches( $entries ) {
		$matches = array();
		$used    = array();

		foreach ( $entries as $i => $entry1 ) {
			if ( in_array( $i, $used ) ) {
				continue;
			}

			$best_match = null;
			$best_score = -1;

			foreach ( $entries as $j => $entry2 ) {
				if ( $i === $j || in_array( $j, $used ) ) {
					continue;
				}

				$match_score = $this->calculate_match_score( $entry1, $entry2 );

				if ( $match_score > $best_score ) {
					$best_score = $match_score;
					$best_match = $j;
				}
			}

			if ( $best_match !== null ) {
				$matches[] = array(
					'player1' => $entries[ $i ],
					'player2' => $entries[ $best_match ],
				);
				$used[]    = $i;
				$used[]    = $best_match;
			}
		}

		return $matches;
	}

	/**
	 * Calculate match compatibility score
	 *
	 * @since 1.0.0
	 * @param array $entry1 First player entry
	 * @param array $entry2 Second player entry
	 * @return float Match score (0-100)
	 */
	private function calculate_match_score( $entry1, $entry2 ) {
		$score = 50; // Base score

		// Skill-based matching
		if ( $entry1['queue_type'] === 'skill' || $entry2['queue_type'] === 'skill' ) {
			$elo1 = get_user_meta( $entry1['user_id'], 'qba_elo_rating', true ) ?: 1000;
			$elo2 = get_user_meta( $entry2['user_id'], 'qba_elo_rating', true ) ?: 1000;

			$elo_diff    = abs( $elo1 - $elo2 );
			$skill_bonus = max( 0, 50 - ( $elo_diff / 4 ) ); // Bonus for similar skill levels
			$score      += $skill_bonus;
		}

		// Wait time bonus (prefer players waiting longer)
		$wait1        = strtotime( $entry1['joined_at'] );
		$wait2        = strtotime( $entry2['joined_at'] );
		$avg_wait     = ( $wait1 + $wait2 ) / 2;
		$wait_minutes = ( time() - $avg_wait ) / 60;
		$wait_bonus   = min( 20, $wait_minutes ); // Bonus up to 20 points
		$score       += $wait_bonus;

		// Random factor to prevent predictable matching
		$score += rand( -5, 5 );

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Create battle from matched players
	 *
	 * @since 1.0.0
	 * @param array $match  Match data
	 * @param int   $quiz_id Quiz ID
	 * @return int|WP_Error Battle ID or error
	 */
	private function create_battle_from_match( $match, $quiz_id ) {
		$battle_data = array(
			'quiz_id'        => $quiz_id,
			'challenger_id'  => $match['player1']['user_id'],
			'opponent_id'    => $match['player2']['user_id'],
			'status'         => 'pending',
			'challenge_type' => 'queue',
			'created_at'     => current_time( 'mysql' ),
			'expires_at'     => date( 'Y-m-d H:i:s', strtotime( '+30 seconds' ) ), // Short expiry for queue matches
		);

		return $this->db->insert_battle( $battle_data );
	}

	/**
	 * Update matched queue entries
	 *
	 * @since 1.0.0
	 * @param int $entry1_id First entry ID
	 * @param int $entry2_id Second entry ID
	 * @param int $battle_id Battle ID
	 */
	private function update_matched_entries( $entry1_id, $entry2_id, $battle_id ) {
		$this->db->update_queue_entry(
			$entry1_id,
			array(
				'status'     => 'matched',
				'battle_id'  => $battle_id,
				'matched_at' => current_time( 'mysql' ),
			)
		);

		$this->db->update_queue_entry(
			$entry2_id,
			array(
				'status'     => 'matched',
				'battle_id'  => $battle_id,
				'matched_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Clean up expired queue entries
	 *
	 * @since 1.0.0
	 * @return int Number of cleaned entries
	 */
	public function cleanup_expired_entries() {
		return $this->db->cleanup_expired_queue_entries();
	}

	/**
	 * Get waiting queue entries grouped by quiz
	 *
	 * @since 1.0.0
	 * @return array Grouped entries
	 */
	private function get_waiting_queue_entries_grouped() {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_matchmaking_queue';

		$entries = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'waiting' ORDER BY joined_at ASC",
			ARRAY_A
		);

		$grouped = array();
		foreach ( $entries as $entry ) {
			$grouped[ $entry['quiz_id'] ][] = $entry;
		}

		return $grouped;
	}

	/**
	 * Format wait time for display
	 *
	 * @since 1.0.0
	 * @param int $seconds Seconds
	 * @return string Formatted time
	 */
	private function format_wait_time( $seconds ) {
		if ( $seconds < 60 ) {
			return sprintf( _n( '%d second', '%d seconds', $seconds, 'quiz-battle-arena' ), $seconds );
		}

		$minutes = floor( $seconds / 60 );
		return sprintf( _n( '%d minute', '%d minutes', $minutes, 'quiz-battle-arena' ), $minutes );
	}

	/**
	 * Get queue statistics
	 *
	 * @since 1.0.0
	 * @return array Statistics
	 */
	public function get_queue_stats() {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_matchmaking_queue';

		$stats = $wpdb->get_row(
			"SELECT
				COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting,
				COUNT(CASE WHEN status = 'matched' THEN 1 END) as matched_today,
				AVG(TIMESTAMPDIFF(SECOND, joined_at, matched_at)) as avg_wait_time
			FROM {$table}
			WHERE joined_at >= CURDATE()",
			ARRAY_A
		);

		return array(
			'waiting'       => (int) $stats['waiting'],
			'matched_today' => (int) $stats['matched_today'],
			'avg_wait_time' => $stats['avg_wait_time'] ? round( $stats['avg_wait_time'] ) : 0,
		);
	}

	/**
	 * Join matchmaking queue (public method for AJAX)
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID
	 * @param int $quiz_id Quiz ID
	 * @return int|WP_Error Queue entry ID or error
	 */
	public function join_queue( $user_id, $quiz_id ) {
		// Check if user is already in queue
		if ( $this->get_user_queue_status( $user_id ) ) {
			return new WP_Error( 'already_in_queue', __( 'You are already in the matchmaking queue', 'quiz-battle-arena' ) );
		}

		// Check if user has active battle
		if ( $this->has_active_battle( $user_id ) ) {
			return new WP_Error( 'active_battle', __( 'You have an active battle in progress', 'quiz-battle-arena' ) );
		}

		return $this->add_to_queue( $user_id, $quiz_id, 'random' );
	}

	/**
	 * Leave matchmaking queue (public method for AJAX)
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID
	 * @return bool Success
	 */
	public function leave_queue( $user_id ) {
		return $this->remove_from_queue( $user_id );
	}

	/**
	 * Check if user has an active battle
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID
	 * @return bool
	 */
	private function has_active_battle( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_battles';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
			 WHERE (challenger_id = %d OR opponent_id = %d)
			 AND status IN ('pending', 'active')",
				$user_id,
				$user_id
			)
		);

		return $count > 0;
	}

	/**
	 * Check queue match status for user (AJAX helper)
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID
	 * @return array|null Match data or null
	 */
	public function check_queue_match( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_matchmaking_queue';

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
			 WHERE user_id = %d AND status = 'matched'
			 ORDER BY matched_at DESC LIMIT 1",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $entry || ! $entry['battle_id'] ) {
			return null;
		}

		return array(
			'battle_id'  => $entry['battle_id'],
			'matched_at' => $entry['matched_at'],
		);
	}
}
