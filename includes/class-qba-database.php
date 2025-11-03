<?php
/**
 * Database Operations Class
 *
 * Handles all database operations for the plugin
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Database Class
 *
 * @since 1.0.0
 */
class QBA_Database {

	/**
	 * Insert battle record
	 *
	 * @since 1.0.0
	 * @param array $battle_data Battle data
	 * @return int|WP_Error Battle ID or error
	 */
	public function insert_battle( $battle_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_battles';

		$result = $wpdb->insert( $table, $battle_data );

		if ( $result === false ) {
			return new WP_Error( 'db_insert_failed', __( 'Failed to create battle record', 'quiz-battle-arena' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update battle record
	 *
	 * @since 1.0.0
	 * @param int   $battle_id Battle ID
	 * @param array $data      Data to update
	 * @return bool Success
	 */
	public function update_battle( $battle_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_battles';

		return $wpdb->update(
			$table,
			$data,
			array( 'id' => $battle_id ),
			null,
			array( '%d' )
		) !== false;
	}

	/**
	 * Insert battle progress record
	 *
	 * @since 1.0.0
	 * @param array $progress_data Progress data
	 * @return int|WP_Error Progress ID or error
	 */
	public function insert_battle_progress( $progress_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_battle_progress';

		$result = $wpdb->insert( $table, $progress_data );

		if ( $result === false ) {
			return new WP_Error( 'db_insert_failed', __( 'Failed to save battle progress', 'quiz-battle-arena' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get battle progress for user
	 *
	 * @since 1.0.0
	 * @param int $battle_id Battle ID
	 * @param int $user_id   User ID
	 * @return array Progress records
	 */
	public function get_battle_progress( $battle_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_battle_progress';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
			WHERE battle_id = %d AND user_id = %d 
			ORDER BY question_order ASC",
				$battle_id,
				$user_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get all progress for battle
	 *
	 * @since 1.0.0
	 * @param int $battle_id Battle ID
	 * @return array Progress records grouped by user
	 */
	public function get_battle_all_progress( $battle_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_battle_progress';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE battle_id = %d ORDER BY user_id, question_order",
				$battle_id
			),
			ARRAY_A
		);

		$grouped = array();
		foreach ( $results as $progress ) {
			$grouped[ $progress['user_id'] ][] = $progress;
		}

		return $grouped;
	}

	/**
	 * Insert user badge
	 *
	 * @since 1.0.0
	 * @param array $badge_data Badge data
	 * @return int|WP_Error Badge ID or error
	 */
	public function insert_user_badge( $badge_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_user_badges';

		// Check if badge already exists
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND badge_id = %s",
				$badge_data['user_id'],
				$badge_data['badge_id']
			)
		);

		if ( $exists ) {
			return new WP_Error( 'badge_exists', __( 'Badge already earned', 'quiz-battle-arena' ) );
		}

		$result = $wpdb->insert( $table, $badge_data );

		if ( $result === false ) {
			return new WP_Error( 'db_insert_failed', __( 'Failed to award badge', 'quiz-battle-arena' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Insert matchmaking queue entry
	 *
	 * @since 1.0.0
	 * @param array $queue_data Queue data
	 * @return int|WP_Error Queue ID or error
	 */
	public function insert_queue_entry( $queue_data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_matchmaking_queue';

		$result = $wpdb->insert( $table, $queue_data );

		if ( $result === false ) {
			return new WP_Error( 'db_insert_failed', __( 'Failed to join matchmaking queue', 'quiz-battle-arena' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update queue entry
	 *
	 * @since 1.0.0
	 * @param int   $queue_id Queue ID
	 * @param array $data     Data to update
	 * @return bool Success
	 */
	public function update_queue_entry( $queue_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_matchmaking_queue';

		return $wpdb->update(
			$table,
			$data,
			array( 'id' => $queue_id ),
			null,
			array( '%d' )
		) !== false;
	}

	/**
	 * Get waiting queue entries for quiz
	 *
	 * @since 1.0.0
	 * @param int $quiz_id Quiz ID
	 * @return array Queue entries
	 */
	public function get_waiting_queue_entries( $quiz_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_matchmaking_queue';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
			WHERE quiz_id = %d AND status = 'waiting' 
			ORDER BY joined_at ASC",
				$quiz_id
			),
			ARRAY_A
		);
	}

	/**
	 * Clean up expired battles
	 *
	 * @since 1.0.0
	 * @return int Number of cleaned battles
	 */
	public function cleanup_expired_battles() {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_battles';

		$result = $wpdb->update(
			$table,
			array(
				'status'       => 'expired',
				'completed_at' => current_time( 'mysql' ),
			),
			array(
				'status'     => 'pending',
				'expires_at' => array( '<', current_time( 'mysql' ) ),
			)
		);

		return $result ?: 0;
	}

	/**
	 * Clean up expired queue entries
	 *
	 * @since 1.0.0
	 * @return int Number of cleaned entries
	 */
	public function cleanup_expired_queue_entries() {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_matchmaking_queue';

		$timeout      = get_option( 'qba_queue_timeout', 300 );
		$expired_time = date( 'Y-m-d H:i:s', strtotime( "-{$timeout} seconds" ) );

		$result = $wpdb->update(
			$table,
			array( 'status' => 'expired' ),
			array(
				'status'    => 'waiting',
				'joined_at' => array( '<', $expired_time ),
			)
		);

		return $result ?: 0;
	}

	/**
	 * Get leaderboard data
	 *
	 * @since 1.0.0
	 * @param string $type   Leaderboard type
	 * @param array  $args   Query arguments
	 * @return array Leaderboard entries
	 */
	public function get_leaderboard( $type = 'global', $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'  => 50,
			'offset' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		switch ( $type ) {
			case 'weekly':
				return $this->get_weekly_leaderboard( $args );
			default:
				return $this->get_global_leaderboard( $args );
		}
	}

	/**
	 * Get global leaderboard
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments
	 * @return array Leaderboard entries
	 */
	private function get_global_leaderboard( $args ) {
		global $wpdb;
		$table_stats = $wpdb->prefix . 'qba_user_stats';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
				s.user_id,
				s.total_battles,
				s.battles_won,
				s.battles_lost,
				s.total_points,
				s.elo_rating,
				s.win_streak,
				u.display_name,
				u.user_email
			FROM {$table_stats} s
			INNER JOIN {$wpdb->users} u ON s.user_id = u.ID
			ORDER BY s.elo_rating DESC, s.total_points DESC
			LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			),
			ARRAY_A
		);

		// Calculate ranks and win rates
		$rank = $args['offset'] + 1;
		foreach ( $results as &$entry ) {
			$entry['rank']       = $rank++;
			$entry['win_rate']   = $entry['total_battles'] > 0
				? round( ( $entry['battles_won'] / $entry['total_battles'] ) * 100, 1 )
				: 0;
			$entry['avatar_url'] = get_avatar_url( $entry['user_id'] );
		}

		return $results;
	}

	/**
	 * Get weekly leaderboard
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments
	 * @return array Leaderboard entries
	 */
	private function get_weekly_leaderboard( $args ) {
		global $wpdb;
		$table_battles = $wpdb->prefix . 'qba_battles';

		// Get start of current week (Monday)
		$week_start = date( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
				user_id,
				COUNT(*) as weekly_battles,
				SUM(CASE WHEN result = 'won' THEN 1 ELSE 0 END) as weekly_wins,
				SUM(points_earned) as weekly_points,
				u.display_name,
				u.user_email
			FROM (
				SELECT challenger_id as user_id, challenger_result as result, challenger_points as points_earned
				FROM {$table_battles}
				WHERE completed_at >= %s AND status = 'completed'
				UNION ALL
				SELECT opponent_id as user_id, opponent_result as result, opponent_points as points_earned
				FROM {$table_battles}
				WHERE completed_at >= %s AND status = 'completed'
			) as weekly_data
			INNER JOIN {$wpdb->users} u ON weekly_data.user_id = u.ID
			GROUP BY user_id
			ORDER BY weekly_points DESC, weekly_wins DESC
			LIMIT %d OFFSET %d",
				$week_start,
				$week_start,
				$args['limit'],
				$args['offset']
			),
			ARRAY_A
		);

		$rank = $args['offset'] + 1;
		foreach ( $results as &$entry ) {
			$entry['rank']       = $rank++;
			$entry['avatar_url'] = get_avatar_url( $entry['user_id'] );
		}

		return $results;
	}
}
