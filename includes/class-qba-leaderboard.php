<?php
/**
 * Leaderboard Class
 *
 * Handles leaderboard functionality and data retrieval
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Leaderboard Class
 *
 * Manages leaderboard data and display
 *
 * @since 1.0.0
 */
class QBA_Leaderboard {

	/**
	 * Cache timeout for leaderboard data
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $cache_timeout = 300; // 5 minutes

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->cache_timeout = get_option( 'qba_leaderboard_cache_timeout', 300 );
	}

	/**
	 * Handle AJAX request for leaderboard data
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_leaderboard() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'qba_public_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', QBA_TEXT_DOMAIN ) );
		}

		$period  = sanitize_text_field( $_POST['period'] ?? 'alltime' );
		$limit   = intval( $_POST['limit'] ?? 50 );
		$user_id = get_current_user_id();

		// Validate period
		$valid_periods = array( 'daily', 'weekly', 'monthly', 'alltime' );
		if ( ! in_array( $period, $valid_periods ) ) {
			$period = 'alltime';
		}

		// Validate limit
		$limit = min( max( $limit, 10 ), 100 );

		$leaderboard_data = $this->get_leaderboard_data( $period, $limit );

		// Add current user position if not in top results
		if ( $user_id ) {
			$user_position = $this->get_user_position( $user_id, $period );
			if ( $user_position && ! in_array( $user_id, array_column( $leaderboard_data, 'user_id' ) ) ) {
				$leaderboard_data[] = $user_position;
			}
		}

		wp_send_json_success(
			array(
				'period' => $period,
				'data'   => $leaderboard_data,
				'stats'  => $this->get_leaderboard_stats( $period ),
			)
		);
	}

	/**
	 * Get leaderboard data for a specific period
	 *
	 * @since 1.0.0
	 * @param string $period The period (daily, weekly, monthly, alltime)
	 * @param int    $limit Number of results to return
	 * @return array
	 */
	public function get_leaderboard_data( $period = 'alltime', $limit = 50, $force_refresh = false ) {
		$cache_key   = 'qba_leaderboard_' . $period . '_' . $limit;
		$cached_data = get_transient( $cache_key );

		if ( $cached_data !== false ) {
			return $cached_data;
		}

		global $wpdb;

		$table_stats = $wpdb->prefix . 'qba_user_stats';
		$table_users = $wpdb->users;

		$where_clause = '';
		$period_start = '';

		switch ( $period ) {
			case 'daily':
				$period_start = date( 'Y-m-d 00:00:00', strtotime( '-1 day' ) );
				$where_clause = $wpdb->prepare( 'AND us.last_battle_at >= %s', $period_start );
				break;
			case 'weekly':
				$period_start = date( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
				$where_clause = $wpdb->prepare( 'AND us.last_battle_at >= %s', $period_start );
				break;
			case 'monthly':
				$period_start = date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
				$where_clause = $wpdb->prepare( 'AND us.last_battle_at >= %s', $period_start );
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

		// Add rank and calculate additional stats
		$rank = 1;
		foreach ( $results as &$result ) {
			$result['rank']     = $rank++;
			$result['win_rate'] = $result['total_battles'] > 0
				? round( ( $result['battles_won'] / $result['total_battles'] ) * 100, 1 )
				: 0;

			// Format last battle time
			if ( $result['last_battle_at'] ) {
				$result['last_battle_relative'] = human_time_diff( strtotime( $result['last_battle_at'] ), current_time( 'timestamp' ) ) . ' ago';
			} else {
				$result['last_battle_relative'] = __( 'Never', QBA_TEXT_DOMAIN );
			}
		}

		// Cache the results
		set_transient( $cache_key, $results, $this->cache_timeout );

		return $results;
	}

	/**
	 * Get leaderboard statistics
	 *
	 * @since 1.0.0
	 * @param string $period The period
	 * @return array
	 */
	public function get_leaderboard_stats( $period = 'alltime' ) {
		$cache_key    = 'qba_leaderboard_stats_' . $period;
		$cached_stats = get_transient( $cache_key );

		if ( $cached_stats !== false ) {
			return $cached_stats;
		}

		global $wpdb;

		$where_clause = '';
		if ( $period !== 'alltime' ) {
			$days         = ( $period === 'daily' ) ? 1 : ( ( $period === 'weekly' ) ? 7 : 30 );
			$period_start = date( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) );
			$where_clause = $wpdb->prepare( 'AND last_battle_at >= %s', $period_start );
		}

		$stats = array(
			'total_players'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}qba_user_stats WHERE total_battles > 0 {$where_clause}" ),
			'total_battles'  => $wpdb->get_var( "SELECT SUM(total_battles) FROM {$wpdb->prefix}qba_user_stats WHERE total_battles > 0 {$where_clause}" ),
			'avg_elo'        => round( $wpdb->get_var( "SELECT AVG(elo_rating) FROM {$wpdb->prefix}qba_user_stats WHERE total_battles > 0 {$where_clause}" ), 0 ),
			'highest_elo'    => $wpdb->get_var( "SELECT MAX(elo_rating) FROM {$wpdb->prefix}qba_user_stats WHERE total_battles > 0 {$where_clause}" ),
			'most_wins'      => $wpdb->get_var( "SELECT MAX(battles_won) FROM {$wpdb->prefix}qba_user_stats WHERE total_battles > 0 {$where_clause}" ),
			'longest_streak' => $wpdb->get_var( "SELECT MAX(best_win_streak) FROM {$wpdb->prefix}qba_user_stats WHERE total_battles > 0 {$where_clause}" ),
		);

		// Get top player info
		$top_player_query    = $wpdb->prepare(
			"
			SELECT u.display_name, us.elo_rating, us.total_points, us.battles_won
			FROM {$wpdb->prefix}qba_user_stats us
			INNER JOIN {$wpdb->users} u ON us.user_id = u.ID
			WHERE us.total_battles > 0 {$where_clause}
			ORDER BY us.total_points DESC, us.elo_rating DESC
			LIMIT 1
		"
		);
		$top_player          = $wpdb->get_row( $top_player_query, ARRAY_A );
		$stats['top_player'] = $top_player;

		// Cache the stats
		set_transient( $cache_key, $stats, $this->cache_timeout );

		return $stats;
	}

	/**
	 * Get user's position in leaderboard
	 *
	 * @since 1.0.0
	 * @param int    $user_id The user ID
	 * @param string $period The period
	 * @return array|null
	 */
	public function get_user_position( $user_id, $period = 'alltime' ) {
		global $wpdb;

		$table_stats = $wpdb->prefix . 'qba_user_stats';
		$table_users = $wpdb->users;

		$where_clause = '';
		if ( $period !== 'alltime' ) {
			$days         = ( $period === 'daily' ) ? 1 : ( ( $period === 'weekly' ) ? 7 : 30 );
			$period_start = date( 'Y-m-d 00:00:00', strtotime( "-{$days} days" ) );
			$where_clause = $wpdb->prepare( 'AND us.last_battle_at >= %s', $period_start );
		}

		// Get user's ranking position
		$position_query = $wpdb->prepare(
			"
			SELECT COUNT(*) + 1 as rank
			FROM {$table_stats} us1
			WHERE (us1.total_points > (SELECT total_points FROM {$table_stats} WHERE user_id = %d)
				   OR (us1.total_points = (SELECT total_points FROM {$table_stats} WHERE user_id = %d)
					   AND us1.elo_rating > (SELECT elo_rating FROM {$table_stats} WHERE user_id = %d)))
			AND us1.total_battles > 0 {$where_clause}
		",
			$user_id,
			$user_id,
			$user_id
		);

		$rank = $wpdb->get_var( $position_query );

		// Get user's stats
		$user_query = $wpdb->prepare(
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
			WHERE us.user_id = %d
		",
			$user_id
		);

		$user_data = $wpdb->get_row( $user_query, ARRAY_A );

		if ( ! $user_data ) {
			return null;
		}

		$user_data['rank']     = $rank ?: 1;
		$user_data['win_rate'] = $user_data['total_battles'] > 0
			? round( ( $user_data['battles_won'] / $user_data['total_battles'] ) * 100, 1 )
			: 0;

		if ( $user_data['last_battle_at'] ) {
			$user_data['last_battle_relative'] = human_time_diff( strtotime( $user_data['last_battle_at'] ), current_time( 'timestamp' ) ) . ' ago';
		} else {
			$user_data['last_battle_relative'] = __( 'Never', QBA_TEXT_DOMAIN );
		}

		return $user_data;
	}

	/**
	 * Clear leaderboard cache
	 *
	 * @since 1.0.0
	 */
	public function clear_cache() {
		global $wpdb;

		// Clear all leaderboard transients
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_qba_leaderboard_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_qba_leaderboard_%'" );
	}

	/**
	 * Get leaderboard periods available
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_available_periods() {
		return array(
			'daily'   => __( 'Today', QBA_TEXT_DOMAIN ),
			'weekly'  => __( 'This Week', QBA_TEXT_DOMAIN ),
			'monthly' => __( 'This Month', QBA_TEXT_DOMAIN ),
			'alltime' => __( 'All Time', QBA_TEXT_DOMAIN ),
		);
	}

	/**
	 * Render leaderboard HTML
	 *
	 * @since 1.0.0
	 * @param string $period The period
	 * @param int    $limit Number of results
	 * @param bool   $show_filters Whether to show period filters
	 * @return string
	 */
	public function render_leaderboard( $period = 'alltime', $limit = 10, $show_filters = true ) {
		$data    = $this->get_leaderboard_data( $period, $limit );
		$stats   = $this->get_leaderboard_stats( $period );
		$periods = $this->get_available_periods();

		ob_start();
		?>
		<div class="qba-leaderboard-container">
			<?php if ( $show_filters ) : ?>
			<div class="qba-leaderboard-filters">
				<ul class="qba-period-tabs">
					<?php foreach ( $periods as $period_key => $period_label ) : ?>
					<li class="<?php echo $period === $period_key ? 'active' : ''; ?>">
						<a href="#" data-period="<?php echo esc_attr( $period_key ); ?>">
							<?php echo esc_html( $period_label ); ?>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

			<div class="qba-leaderboard-stats">
				<div class="qba-stat">
					<span class="qba-stat-label"><?php esc_html_e( 'Total Players:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-stat-value"><?php echo esc_html( $stats['total_players'] ); ?></span>
				</div>
				<div class="qba-stat">
					<span class="qba-stat-label"><?php esc_html_e( 'Total Battles:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-stat-value"><?php echo esc_html( $stats['total_battles'] ); ?></span>
				</div>
				<div class="qba-stat">
					<span class="qba-stat-label"><?php esc_html_e( 'Avg Rating:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-stat-value"><?php echo esc_html( $stats['avg_elo'] ); ?></span>
				</div>
			</div>

			<div class="qba-leaderboard-table-container">
				<table class="qba-leaderboard-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rank', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Player', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Rating', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Wins', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Win Rate', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Streak', QBA_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $data ) ) : ?>
						<tr>
							<td colspan="6" class="qba-no-data">
								<?php esc_html_e( 'No battles played yet.', QBA_TEXT_DOMAIN ); ?>
							</td>
						</tr>
						<?php else : ?>
							<?php foreach ( $data as $player ) : ?>
						<tr class="<?php echo get_current_user_id() == $player['user_id'] ? 'qba-current-user' : ''; ?>">
							<td class="qba-rank"><?php echo esc_html( $player['rank'] ); ?></td>
							<td class="qba-player">
								<div class="qba-player-info">
									<strong><?php echo esc_html( $player['display_name'] ); ?></strong>
									<small><?php echo esc_html( $player['last_battle_relative'] ); ?></small>
								</div>
							</td>
							<td class="qba-rating"><?php echo esc_html( $player['elo_rating'] ); ?></td>
							<td class="qba-wins"><?php echo esc_html( $player['battles_won'] ); ?></td>
							<td class="qba-win-rate"><?php echo esc_html( $player['win_rate'] ); ?>%</td>
							<td class="qba-streak"><?php echo esc_html( $player['win_streak'] ); ?></td>
						</tr>
						<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render leaderboard shortcode
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_leaderboard_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'period'       => 'alltime',
				'limit'        => 50,
				'show_filters' => 'true',
			),
			$atts
		);

		$period       = sanitize_text_field( $atts['period'] );
		$limit        = intval( $atts['limit'] );
		$show_filters = $atts['show_filters'] === 'true';

		// Validate period
		$valid_periods = array_keys( $this->get_available_periods() );
		if ( ! in_array( $period, $valid_periods ) ) {
			$period = 'alltime';
		}

		// Validate limit
		$limit = min( max( $limit, 10 ), 100 );

		return $this->render_leaderboard( $period, $limit, $show_filters );
	}
}
	/**
	 * Render leaderboard shortcode
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
