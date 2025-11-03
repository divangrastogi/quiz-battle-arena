<?php
/**
 * Admin Leaderboard Page Partial
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

// Get leaderboard data
$leaderboard = new QBA_Leaderboard();

echo '<!-- Debug: Leaderboard class loaded -->';

$global_data = $leaderboard->get_leaderboard_data( 'alltime', 50 );
// Debug: Clear cache and try again
$global_data = $leaderboard->get_leaderboard_data( 'alltime', 50 );
// Debug: Clear cache and try again
if ( empty( $global_data ) ) {
	delete_transient( 'qba_leaderboard_alltime_50' );
	$global_data = $leaderboard->get_leaderboard_data( 'alltime', 50, true );
	echo "<!-- Debug: Global data count: " . count($global_data) . " -->";
}
$weekly_data = $leaderboard->get_leaderboard_data( 'weekly', 50, true );
?>

<div class="wrap qba-leaderboard-page">
	<div class="qba-leaderboard-header">
		<div class="qba-leaderboard-title">
			<h1><?php esc_html_e( 'Quiz Battle Arena Leaderboards', QBA_TEXT_DOMAIN ); ?></h1>
			<p class="description"><?php esc_html_e( 'View and manage competitive rankings and player statistics.', QBA_TEXT_DOMAIN ); ?></p>
		</div>
		<div class="qba-leaderboard-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-settings' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Settings', QBA_TEXT_DOMAIN ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-battles' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'View Battles', QBA_TEXT_DOMAIN ); ?>
			</a>
		</div>
	</div>

	<div class="qba-leaderboard-stats">
		<div class="qba-stat-card">
			<div class="qba-stat-icon">🏆</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( count( $global_data ) ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Active Players', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>
		<div class="qba-stat-card">
			<div class="qba-stat-icon">⚡</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( !empty($global_data) ? $global_data[0]['elo_rating'] : 0 ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Top Rating', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>
		<div class="qba-stat-card">
			<div class="qba-stat-icon">🎯</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( !empty($weekly_data) ? count($weekly_data) : 0 ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Weekly Active', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>
	</div>

	<div class="qba-leaderboard-tabs">
		<h2 class="nav-tab-wrapper qba-nav-tabs">
			<a href="#global" class="nav-tab nav-tab-active"><?php esc_html_e( 'Global Leaderboard', QBA_TEXT_DOMAIN ); ?></a>
			<a href="#weekly" class="nav-tab"><?php esc_html_e( 'Weekly Leaderboard', QBA_TEXT_DOMAIN ); ?></a>
		</h2>

		<div id="global" class="tab-content">
			<div class="qba-leaderboard-content">
				<div class="qba-leaderboard-header-section">
					<h3><?php esc_html_e( 'All-Time Leaderboard', QBA_TEXT_DOMAIN ); ?></h3>
					<p><?php esc_html_e( 'Top players ranked by ELO rating across all time.', QBA_TEXT_DOMAIN ); ?></p>
				</div>
				<?php if ( ! empty( $global_data ) ) : ?>
					<div class="qba-leaderboard-table-container">
						<table class="qba-leaderboard-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Rank', QBA_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'Player', QBA_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'ELO Rating', QBA_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'Total Battles', QBA_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'Win Rate', QBA_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'Total Points', QBA_TEXT_DOMAIN ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $global_data as $entry ) : ?>
									<tr class="<?php echo $entry['rank'] <= 3 ? 'qba-top-player' : ''; ?>">
										<td class="qba-rank">
											<?php if ( $entry['rank'] <= 3 ) : ?>
												<span class="qba-rank-badge qba-rank-<?php echo esc_attr( $entry['rank'] ); ?>">
													<?php echo $entry['rank'] === 1 ? '🥇' : ( $entry['rank'] === 2 ? '🥈' : '🥉' ); ?>
												</span>
											<?php else : ?>
												<?php echo esc_html( $entry['rank'] ); ?>
											<?php endif; ?>
										</td>
										<td class="qba-player">
											<?php echo get_avatar( $entry['user_id'], 32 ); ?>
											<span><?php echo esc_html( $entry['display_name'] ); ?></span>
										</td>
										<td class="qba-rating"><?php echo esc_html( $entry['elo_rating'] ); ?></td>
										<td><?php echo esc_html( $entry['total_battles'] ); ?></td>
										<td><?php echo esc_html( $entry['win_rate'] ); ?>%</td>
										<td class="qba-points"><?php echo esc_html( $entry['total_points'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<div class="qba-no-data">
						<div class="qba-no-data-icon">🏆</div>
						<h4><?php esc_html_e( 'No Leaderboard Data Yet', QBA_TEXT_DOMAIN ); ?></h4>
						<p><?php esc_html_e( 'Players haven\'t participated in battles yet. Leaderboard data will appear here once battles are completed.', QBA_TEXT_DOMAIN ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div id="weekly" class="tab-content" style="display: none;">
			<div class="qba-leaderboard-content">
				<div class="qba-leaderboard-header-section">
					<h3><?php esc_html_e( 'Weekly Leaderboard', QBA_TEXT_DOMAIN ); ?></h3>
					<p><?php esc_html_e( 'Top performers this week based on points earned and battles won.', QBA_TEXT_DOMAIN ); ?></p>
				</div>
				<?php if ( ! empty( $weekly_data ) ) : ?>
					<div class="qba-leaderboard-table-container">
						<table class="qba-leaderboard-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Rank', QBA_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'Player', QBA_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'Weekly Points', QBA_TEXT_DOMAIN ); ?></th>
									<th><?php esc_html_e( 'Weekly Wins', QBA_TEXT_DOMAIN ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $weekly_data as $entry ) : ?>
									<tr class="<?php echo $entry['rank'] <= 3 ? 'qba-top-player' : ''; ?>">
										<td class="qba-rank">
											<?php if ( $entry['rank'] <= 3 ) : ?>
												<span class="qba-rank-badge qba-rank-<?php echo esc_attr( $entry['rank'] ); ?>">
													<?php echo $entry['rank'] === 1 ? '🥇' : ( $entry['rank'] === 2 ? '🥈' : '🥉' ); ?>
												</span>
											<?php else : ?>
												<?php echo esc_html( $entry['rank'] ); ?>
											<?php endif; ?>
										</td>
										<td class="qba-player">
											<?php echo get_avatar( $entry['user_id'], 32 ); ?>
											<span><?php echo esc_html( $entry['display_name'] ); ?></span>
										</td>
										<td class="qba-points"><?php echo esc_html( $entry['weekly_points'] ?? 0 ); ?></td>
										<td><?php echo esc_html( $entry['weekly_wins'] ?? 0 ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<div class="qba-no-data">
						<div class="qba-no-data-icon">📅</div>
						<h4><?php esc_html_e( 'No Weekly Data Yet', QBA_TEXT_DOMAIN ); ?></h4>
						<p><?php esc_html_e( 'No battles have been completed this week. Check back later for weekly rankings.', QBA_TEXT_DOMAIN ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<style>
.qba-leaderboard-page {
	max-width: none;
}

.qba-leaderboard-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 1px solid #e1e1e1;
}

.qba-leaderboard-title h1 {
	margin: 0 0 5px 0;
	font-size: 28px;
	font-weight: 300;
}

.qba-leaderboard-title .description {
	color: #666;
	margin: 0;
	font-size: 14px;
}

.qba-leaderboard-actions {
	display: flex;
	gap: 10px;
}

.qba-leaderboard-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.qba-stat-card {
	background: white;
	border: 1px solid #e1e1e1;
	border-radius: 8px;
	padding: 20px;
	display: flex;
	align-items: center;
	gap: 16px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.05);
	transition: box-shadow 0.2s ease;
}

.qba-stat-card:hover {
	box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.qba-stat-icon {
	font-size: 32px;
}

.qba-stat-content {
	flex: 1;
}

.qba-stat-number {
	font-size: 24px;
	font-weight: 700;
	color: #1d2327;
	margin-bottom: 4px;
	line-height: 1;
}

.qba-stat-label {
	color: #646970;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	font-weight: 500;
}

.qba-nav-tabs {
	margin-bottom: 30px !important;
	border-bottom: 1px solid #e1e1e1;
}

.qba-nav-tabs .nav-tab {
	border: none;
	background: #f1f1f1;
	color: #666;
	padding: 12px 20px;
	margin-right: 5px;
	border-radius: 4px 4px 0 0;
	transition: all 0.2s ease;
}

.qba-nav-tabs .nav-tab:hover {
	background: #e1e1e1;
	color: #333;
}

.qba-nav-tabs .nav-tab-active {
	background: #fff;
	color: #333;
	border-bottom: 2px solid #007cba;
	margin-bottom: -1px;
}

.qba-leaderboard-content {
	background: #fff;
	border: 1px solid #e1e1e1;
	border-radius: 4px;
	padding: 30px;
	margin-bottom: 30px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.qba-leaderboard-header-section h3 {
	margin: 0 0 10px 0;
	color: #1d2327;
	font-size: 20px;
}

.qba-leaderboard-header-section p {
	color: #646970;
	margin: 0 0 20px 0;
	font-size: 14px;
}

.qba-leaderboard-table-container {
	overflow-x: auto;
}

.qba-leaderboard-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 14px;
}

.qba-leaderboard-table th,
.qba-leaderboard-table td {
	padding: 12px 16px;
	text-align: left;
	border-bottom: 1px solid #f0f0f1;
}

.qba-leaderboard-table th {
	background: #f8f9fa;
	font-weight: 600;
	color: #1d2327;
	border-bottom: 2px solid #e1e1e1;
}

.qba-leaderboard-table tr:hover {
	background: #f8f9fa;
}

.qba-leaderboard-table .qba-rank {
	font-weight: 600;
	color: #646970;
	width: 80px;
	text-align: center;
}

.qba-rank-badge {
	font-size: 18px;
}

.qba-leaderboard-table .qba-player {
	display: flex;
	align-items: center;
	gap: 10px;
	font-weight: 500;
}

.qba-leaderboard-table .qba-rating {
	font-weight: 600;
	color: #007cba;
}

.qba-leaderboard-table .qba-points {
	font-weight: 600;
	color: #28a745;
}

.qba-top-player {
	background: linear-gradient(90deg, rgba(0, 123, 186, 0.05) 0%, transparent 100%);
}

.qba-no-data {
	text-align: center;
	padding: 60px 20px;
	color: #646970;
}

.qba-no-data-icon {
	font-size: 48px;
	margin-bottom: 20px;
	opacity: 0.5;
}

.qba-no-data h4 {
	margin: 0 0 10px 0;
	color: #1d2327;
	font-size: 18px;
}

.qba-no-data p {
	margin: 0;
	font-size: 14px;
	line-height: 1.5;
}

@media (max-width: 768px) {
	.qba-leaderboard-header {
		flex-direction: column;
		gap: 20px;
	}

	.qba-leaderboard-actions {
		align-self: flex-start;
	}

	.qba-leaderboard-stats {
		grid-template-columns: 1fr;
	}

	.qba-stat-card {
		padding: 16px;
	}

	.qba-stat-number {
		font-size: 20px;
	}

	.qba-nav-tabs .nav-tab {
		padding: 10px 16px;
		font-size: 14px;
	}

	.qba-leaderboard-content {
		padding: 20px;
	}

	.qba-leaderboard-table th,
	.qba-leaderboard-table td {
		padding: 8px 12px;
		font-size: 13px;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	$('.qba-leaderboard-tabs .nav-tab').on('click', function(e) {
		e.preventDefault();
		var target = $(this).attr('href');

		$('.qba-leaderboard-tabs .nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');

		$('.tab-content').hide();
		$(target).show();
	});
});
</script>
