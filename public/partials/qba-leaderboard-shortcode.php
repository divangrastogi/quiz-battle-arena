<?php
/**
 * Leaderboard Shortcode Template
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$period       = sanitize_text_field( $atts['period'] ?? 'alltime' );
$limit        = intval( $atts['limit'] ?? 10 );
$show_filters = $atts['show_filters'] ?? 'true';
$show_filters = $show_filters === 'true';

$leaderboard     = new QBA_Leaderboard();
$data            = $leaderboard->get_leaderboard_data( $period, $limit );
$stats           = $leaderboard->get_leaderboard_stats( $period );
$periods         = $leaderboard->get_available_periods();
$current_user_id = get_current_user_id();
?>

<div class="qba-leaderboard-shortcode">
	<?php if ( $show_filters ) : ?>
	<div class="qba-leaderboard-filters">
		<select class="qba-period-select">
			<?php foreach ( $periods as $period_key => $period_label ) : ?>
			<option value="<?php echo esc_attr( $period_key ); ?>" <?php selected( $period, $period_key ); ?>>
				<?php echo esc_html( $period_label ); ?>
			</option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php endif; ?>

	<div class="qba-leaderboard-stats">
		<div class="qba-stat">
			<span class="qba-stat-label"><?php esc_html_e( 'Total Players:', QBA_TEXT_DOMAIN ); ?></span>
			<span class="qba-stat-value"><?php echo esc_html( qba_format_number( $stats['total_players'] ) ); ?></span>
		</div>
		<div class="qba-stat">
			<span class="qba-stat-label"><?php esc_html_e( 'Total Battles:', QBA_TEXT_DOMAIN ); ?></span>
			<span class="qba-stat-value"><?php echo esc_html( qba_format_number( $stats['total_battles'] ) ); ?></span>
		</div>
		<div class="qba-stat">
			<span class="qba-stat-label"><?php esc_html_e( 'Avg Rating:', QBA_TEXT_DOMAIN ); ?></span>
			<span class="qba-stat-value"><?php echo esc_html( $stats['avg_elo'] ); ?></span>
		</div>
	</div>

	<div class="qba-leaderboard-content">
		<?php if ( empty( $data ) ) : ?>
		<div class="qba-no-data">
			<p><?php esc_html_e( 'No battles played yet. Be the first to start battling!', QBA_TEXT_DOMAIN ); ?></p>
		</div>
		<?php else : ?>
		<table class="qba-leaderboard-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Rank', QBA_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Player', QBA_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Rating', QBA_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Wins', QBA_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Win Rate', QBA_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Streak', QBA_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Last Battle', QBA_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data as $player ) : ?>
				<tr class="<?php echo $current_user_id == $player['user_id'] ? 'qba-current-user' : ''; ?>">
					<td class="qba-rank">#<?php echo esc_html( $player['rank'] ); ?></td>
					<td class="qba-player">
						<div class="qba-player-info">
							<?php echo get_avatar( $player['user_id'], 32 ); ?>
							<div class="qba-player-details">
								<strong><?php echo esc_html( $player['display_name'] ); ?></strong>
								<?php if ( $current_user_id == $player['user_id'] ) : ?>
								<span class="qba-you-badge"><?php esc_html_e( '(You)', QBA_TEXT_DOMAIN ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</td>
					<td class="qba-rating">
						<span class="qba-rating-value"><?php echo esc_html( $player['elo_rating'] ); ?></span>
					</td>
					<td class="qba-wins"><?php echo esc_html( $player['battles_won'] ); ?></td>
					<td class="qba-win-rate"><?php echo esc_html( $player['win_rate'] ); ?>%</td>
					<td class="qba-streak">
						<?php if ( $player['win_streak'] > 0 ) : ?>
						<span class="qba-streak-hot">🔥 <?php echo esc_html( $player['win_streak'] ); ?></span>
						<?php else : ?>
						<span class="qba-streak-cold"><?php echo esc_html( $player['win_streak'] ); ?></span>
						<?php endif; ?>
					</td>
					<td class="qba-last-battle">
						<span title="<?php echo esc_attr( $player['last_battle_at'] ); ?>">
							<?php echo esc_html( $player['last_battle_relative'] ); ?>
						</span>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

			<?php if ( $current_user_id ) : ?>
		<div class="qba-user-position">
				<?php
				$user_position = $leaderboard->get_user_position( $current_user_id, $period );
				if ( $user_position ) :
					?>
			<div class="qba-user-rank-card">
				<h4><?php esc_html_e( 'Your Ranking', QBA_TEXT_DOMAIN ); ?></h4>
				<div class="qba-user-rank-info">
					<span class="qba-user-rank">#<?php echo esc_html( $user_position['rank'] ); ?></span>
					<span class="qba-user-rating"><?php echo esc_html( $user_position['elo_rating'] ); ?> <?php esc_html_e( 'ELO', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-user-wins"><?php echo esc_html( $user_position['battles_won'] ); ?> <?php esc_html_e( 'wins', QBA_TEXT_DOMAIN ); ?></span>
				</div>
			</div>
				<?php endif; ?>
		</div>
		<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	$('.qba-period-select').on('change', function() {
		var period = $(this).val();
		var $container = $(this).closest('.qba-leaderboard-shortcode');
		var limit = <?php echo intval( $limit ); ?>;
		var showFilters = <?php echo $show_filters ? 'true' : 'false'; ?>;

		// Reload leaderboard via AJAX
		$.ajax({
			url: qba_public_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'qba_get_leaderboard',
				period: period,
				limit: limit,
				nonce: qba_public_ajax.nonce
			},
			success: function(response) {
				if (response.success) {
					// Update the leaderboard content
					var newContent = generateLeaderboardHTML(response.data, period, showFilters);
					$container.html(newContent);
				}
			}
		});
	});

	function generateLeaderboardHTML(data, period, showFilters) {
		var stats = data.stats;
		var leaderboard = data.data;
		var periods = <?php echo wp_json_encode( $leaderboard->get_available_periods() ); ?>;
		var currentUserId = <?php echo intval( $current_user_id ); ?>;
		var html = '';

		if (showFilters) {
			html += '<div class="qba-leaderboard-filters">';
			html += '<select class="qba-period-select">';
			$.each(periods, function(key, label) {
				var selected = key === period ? ' selected' : '';
				html += '<option value="' + key + '"' + selected + '>' + label + '</option>';
			});
			html += '</select></div>';
		}

		html += '<div class="qba-leaderboard-stats">';
		html += '<div class="qba-stat"><span class="qba-stat-label"><?php esc_html_e( 'Total Players:', QBA_TEXT_DOMAIN ); ?></span><span class="qba-stat-value">' + stats.total_players + '</span></div>';
		html += '<div class="qba-stat"><span class="qba-stat-label"><?php esc_html_e( 'Total Battles:', QBA_TEXT_DOMAIN ); ?></span><span class="qba-stat-value">' + stats.total_battles + '</span></div>';
		html += '<div class="qba-stat"><span class="qba-stat-label"><?php esc_html_e( 'Avg Rating:', QBA_TEXT_DOMAIN ); ?></span><span class="qba-stat-value">' + stats.avg_elo + '</span></div>';
		html += '</div>';

		html += '<div class="qba-leaderboard-content">';

		if (!leaderboard || leaderboard.length === 0) {
			html += '<div class="qba-no-data"><p><?php esc_html_e( 'No battles played yet. Be the first to start battling!', QBA_TEXT_DOMAIN ); ?></p></div>';
		} else {
			html += '<table class="qba-leaderboard-table"><thead><tr>';
			html += '<th><?php esc_html_e( 'Rank', QBA_TEXT_DOMAIN ); ?></th>';
			html += '<th><?php esc_html_e( 'Player', QBA_TEXT_DOMAIN ); ?></th>';
			html += '<th><?php esc_html_e( 'Rating', QBA_TEXT_DOMAIN ); ?></th>';
			html += '<th><?php esc_html_e( 'Wins', QBA_TEXT_DOMAIN ); ?></th>';
			html += '<th><?php esc_html_e( 'Win Rate', QBA_TEXT_DOMAIN ); ?></th>';
			html += '<th><?php esc_html_e( 'Streak', QBA_TEXT_DOMAIN ); ?></th>';
			html += '<th><?php esc_html_e( 'Last Battle', QBA_TEXT_DOMAIN ); ?></th>';
			html += '</tr></thead><tbody>';

			$.each(leaderboard, function(index, player) {
				var isCurrentUser = currentUserId == player.user_id;
				var rowClass = isCurrentUser ? 'qba-current-user' : '';
				html += '<tr class="' + rowClass + '">';
				html += '<td class="qba-rank">#' + player.rank + '</td>';
				html += '<td class="qba-player"><div class="qba-player-info">';
				html += '<img src="' + player.avatar_url + '" width="32" height="32" class="qba-avatar">';
				html += '<div class="qba-player-details"><strong>' + player.display_name + '</strong>';
				if (isCurrentUser) html += '<span class="qba-you-badge">(<?php esc_html_e( 'You', QBA_TEXT_DOMAIN ); ?>)</span>';
				html += '</div></div></td>';
				html += '<td class="qba-rating"><span class="qba-rating-value">' + player.elo_rating + '</span></td>';
				html += '<td class="qba-wins">' + player.battles_won + '</td>';
				html += '<td class="qba-win-rate">' + player.win_rate + '%</td>';
				html += '<td class="qba-streak">';
				if (player.win_streak > 0) {
					html += '<span class="qba-streak-hot">🔥 ' + player.win_streak + '</span>';
				} else {
					html += '<span class="qba-streak-cold">' + player.win_streak + '</span>';
				}
				html += '</td>';
				html += '<td class="qba-last-battle"><span title="' + player.last_battle_at + '">' + player.last_battle_relative + '</span></td>';
				html += '</tr>';
			});

			html += '</tbody></table>';
		}

		html += '</div>';
		return html;
	}
});
</script>