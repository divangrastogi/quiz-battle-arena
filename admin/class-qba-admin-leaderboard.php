<?php
/**
 * Admin Leaderboard Class
 *
 * Handles leaderboard management in admin
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Admin_Leaderboard Class
 *
 * Manages leaderboard administration
 *
 * @since 1.0.0
 */
class QBA_Admin_Leaderboard {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor can be empty
	}

	/**
	 * Get leaderboard data for admin display
	 *
	 * @since 1.0.0
	 * @param string $period The period to get data for (daily, weekly, monthly, alltime)
	 * @param int    $limit Number of results to return
	 * @return array
	 */
	public function get_leaderboard_data( $period = 'alltime', $limit = 50 ) {
		global $wpdb;

		$table_stats = $wpdb->prefix . 'qba_user_stats';
		$table_users = $wpdb->users;

		$where_clause = '';
		$period_start = '';

		switch ( $period ) {
			case 'daily':
				$period_start = date( 'Y-m-d 00:00:00', strtotime( '-1 day' ) );
				$where_clause = "AND us.last_battle_at >= '$period_start'";
				break;
			case 'weekly':
				$period_start = date( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
				$where_clause = "AND us.last_battle_at >= '$period_start'";
				break;
			case 'monthly':
				$period_start = date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
				$where_clause = "AND us.last_battle_at >= '$period_start'";
				break;
			case 'alltime':
			default:
				// No additional where clause
				break;
		}

		$query = $wpdb->prepare(
			"
			SELECT
				us.user_id,
				u.display_name,
				u.user_email,
				us.total_battles,
				us.battles_won,
				us.battles_lost,
				us.battles_drawn,
				us.total_points,
				us.elo_rating,
				us.win_streak,
				us.best_win_streak,
				ROUND((us.correct_answers / GREATEST(us.total_questions_answered, 1)) * 100, 1) as accuracy,
				us.avg_answer_time,
				us.last_battle_at
			FROM {$table_stats} us
			INNER JOIN {$table_users} u ON us.user_id = u.ID
			WHERE us.total_battles > 0 {$where_clause}
			ORDER BY us.total_points DESC, us.elo_rating DESC
			LIMIT %d
		",
			$limit
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Add rank to each result
		$rank = 1;
		foreach ( $results as &$result ) {
			$result['rank']     = $rank++;
			$result['win_rate'] = $result['total_battles'] > 0
				? round( ( $result['battles_won'] / $result['total_battles'] ) * 100, 1 )
				: 0;
		}

		return $results;
	}

	/**
	 * Get leaderboard statistics
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_leaderboard_stats() {
		global $wpdb;

		$stats = array(
			'total_players' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}qba_user_stats WHERE total_battles > 0" ),
			'active_today'  => $wpdb->get_var(
				$wpdb->prepare(
					"
				SELECT COUNT(*) FROM {$wpdb->prefix}qba_user_stats
				WHERE last_battle_at >= %s
			",
					date( 'Y-m-d 00:00:00' )
				)
			),
			'active_week'   => $wpdb->get_var(
				$wpdb->prepare(
					"
				SELECT COUNT(*) FROM {$wpdb->prefix}qba_user_stats
				WHERE last_battle_at >= %s
			",
					date( 'Y-m-d 00:00:00', strtotime( '-7 days' ) )
				)
			),
			'total_battles' => $wpdb->get_var( "SELECT SUM(total_battles) FROM {$wpdb->prefix}qba_user_stats" ),
			'avg_elo'       => round( $wpdb->get_var( "SELECT AVG(elo_rating) FROM {$wpdb->prefix}qba_user_stats" ), 0 ),
			'highest_elo'   => $wpdb->get_var( "SELECT MAX(elo_rating) FROM {$wpdb->prefix}qba_user_stats" ),
			'top_player'    => $wpdb->get_row(
				"
				SELECT u.display_name, us.elo_rating, us.total_points
				FROM {$wpdb->prefix}qba_user_stats us
				INNER JOIN {$wpdb->users} u ON us.user_id = u.ID
				ORDER BY us.total_points DESC, us.elo_rating DESC
				LIMIT 1
			",
				ARRAY_A
			),
		);

		return $stats;
	}

	/**
	 * Export leaderboard data
	 *
	 * @since 1.0.0
	 * @param string $period The period to export
	 * @param string $format Export format (csv, json)
	 */
	public function export_leaderboard( $period = 'alltime', $format = 'csv' ) {
		$data = $this->get_leaderboard_data( $period, 1000 );

		if ( $format === 'csv' ) {
			$this->export_csv( $data, $period );
		} elseif ( $format === 'json' ) {
			$this->export_json( $data, $period );
		}
	}

	/**
	 * Export data as CSV
	 *
	 * @since 1.0.0
	 * @param array  $data The data to export
	 * @param string $period The period
	 */
	private function export_csv( $data, $period ) {
		$filename = 'qba-leaderboard-' . $period . '-' . date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );

		// CSV headers
		fputcsv(
			$output,
			array(
				'Rank',
				'Player Name',
				'Email',
				'Total Battles',
				'Wins',
				'Losses',
				'Draws',
				'Win Rate (%)',
				'Total Points',
				'ELO Rating',
				'Current Streak',
				'Best Streak',
				'Accuracy (%)',
				'Avg Answer Time',
				'Last Battle',
			)
		);

		// CSV data
		foreach ( $data as $row ) {
			fputcsv(
				$output,
				array(
					$row['rank'],
					$row['display_name'],
					$row['user_email'],
					$row['total_battles'],
					$row['battles_won'],
					$row['battles_lost'],
					$row['battles_drawn'],
					$row['win_rate'],
					$row['total_points'],
					$row['elo_rating'],
					$row['win_streak'],
					$row['best_win_streak'],
					$row['accuracy'],
					$row['avg_answer_time'],
					$row['last_battle_at'],
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Export data as JSON
	 *
	 * @since 1.0.0
	 * @param array  $data The data to export
	 * @param string $period The period
	 */
	private function export_json( $data, $period ) {
		$filename = 'qba-leaderboard-' . $period . '-' . date( 'Y-m-d' ) . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo wp_json_encode(
			array(
				'period'      => $period,
				'exported_at' => current_time( 'mysql' ),
				'data'        => $data,
			)
		);

		exit;
	}

	/**
	 * Clear leaderboard cache
	 *
	 * @since 1.0.0
	 */
	public function clear_cache() {
		global $wpdb;

		// Clear leaderboard transients
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_qba_leaderboard_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_qba_leaderboard_%'" );

		// Clear general QBA cache
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_qba_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_qba_%'" );
	}

	/**
	 * Recalculate leaderboard rankings
	 *
	 * @since 1.0.0
	 */
	public function recalculate_rankings() {
		global $wpdb;

		// This would be a heavy operation - in a real implementation,
		// you'd want to do this in batches or via WP Cron
		// For now, just clear the cache to force recalculation
		$this->clear_cache();

		// Log the recalculation
		error_log( 'Quiz Battle Arena: Leaderboard rankings recalculated at ' . current_time( 'mysql' ) );
	}

	/**
	 * Get user leaderboard position
	 *
	 * @since 1.0.0
	 * @param int    $user_id The user ID
	 * @param string $period The period
	 * @return array|null
	 */
	public function get_user_position( $user_id, $period = 'alltime' ) {
		$leaderboard = $this->get_leaderboard_data( $period, 1000 );

		foreach ( $leaderboard as $entry ) {
			if ( $entry['user_id'] == $user_id ) {
				return $entry;
			}
		}

		return null;
	}
}
