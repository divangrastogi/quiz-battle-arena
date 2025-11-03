<?php
/**
 * Admin Battles Page Partial
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

// Get battle statistics
global $wpdb;
$table_battles = $wpdb->prefix . 'qba_battles';

// Get recent battles
$recent_battles = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT b.*, u1.display_name as challenger_name, u2.display_name as opponent_name, u1.user_email as challenger_email, u2.user_email as opponent_email
		FROM {$table_battles} b
		LEFT JOIN {$wpdb->users} u1 ON b.challenger_id = u1.ID
		LEFT JOIN {$wpdb->users} u2 ON b.opponent_id = u2.ID
		WHERE b.created_at >= %s
		ORDER BY b.created_at DESC
		LIMIT 50",
		date( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
	),
	ARRAY_A
);

// Get battle statistics
$total_battles = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_battles}" );
$active_battles = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_battles} WHERE status = 'active'" );
$completed_battles = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_battles} WHERE status = 'completed'" );
$pending_battles = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_battles} WHERE status = 'pending'" );
?>

<div class="wrap qba-battles-page">
	<div class="qba-battles-header">
		<div class="qba-battles-title">
			<h1><?php esc_html_e( 'Quiz Battle Arena Battles', QBA_TEXT_DOMAIN ); ?></h1>
			<p class="description"><?php esc_html_e( 'Monitor and manage all battle activities and statistics.', QBA_TEXT_DOMAIN ); ?></p>
		</div>
		<div class="qba-battles-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-settings' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Settings', QBA_TEXT_DOMAIN ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-leaderboards' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'View Leaderboards', QBA_TEXT_DOMAIN ); ?>
			</a>
		</div>
	</div>

	<div class="qba-battles-stats">
		<div class="qba-stat-card">
			<div class="qba-stat-icon">⚔️</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $total_battles ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Total Battles', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-stat-card">
			<div class="qba-stat-icon">🔥</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $active_battles ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Active Battles', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-stat-card">
			<div class="qba-stat-icon">✅</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $completed_battles ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Completed Battles', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-stat-card">
			<div class="qba-stat-icon">⏳</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $pending_battles ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Pending Battles', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>
	</div>

	<div class="qba-battles-content">
		<div class="qba-battles-header-section">
			<h2><?php esc_html_e( 'Recent Battles (Last 30 Days)', QBA_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Monitor recent battle activities and outcomes.', QBA_TEXT_DOMAIN ); ?></p>
		</div>

		<?php if ( ! empty( $recent_battles ) ) : ?>
			<div class="qba-battles-table-container">
				<table class="qba-battles-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Battle ID', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Challenger', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Opponent', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Quiz', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Status', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Winner', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Created', QBA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Actions', QBA_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_battles as $battle ) : ?>
							<tr>
								<td class="qba-battle-id">#<?php echo esc_html( $battle['id'] ); ?></td>
								<td class="qba-player">
									<?php echo get_avatar( $battle['challenger_id'], 24 ); ?>
									<span><?php echo esc_html( $battle['challenger_name'] ); ?></span>
								</td>
								<td class="qba-player">
									<?php echo get_avatar( $battle['opponent_id'], 24 ); ?>
									<span><?php echo esc_html( $battle['opponent_name'] ); ?></span>
								</td>
								<td class="qba-quiz">
									<?php
									$quiz = get_post( $battle['quiz_id'] );
									echo esc_html( $quiz ? $quiz->post_title : 'Unknown Quiz' );
									?>
								</td>
								<td>
									<span class="qba-status qba-status-<?php echo esc_attr( $battle['status'] ); ?>">
										<?php echo esc_html( ucfirst( $battle['status'] ) ); ?>
									</span>
								</td>
								<td class="qba-winner">
									<?php
									if ( $battle['status'] === 'completed' && $battle['winner_id'] ) {
										$winner = get_userdata( $battle['winner_id'] );
										if ( $winner ) {
											echo get_avatar( $winner->ID, 24 );
											echo '<span>' . esc_html( $winner->display_name ) . '</span>';
										} else {
											echo '<span>Unknown</span>';
										}
									} else {
										echo '<span class="qba-no-winner">-</span>';
									}
									?>
								</td>
								<td class="qba-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $battle['created_at'] ) ) ); ?></td>
								<td class="qba-actions">
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'qba-battles', 'action' => 'view', 'battle_id' => $battle['id'] ) ) ); ?>" class="button button-small button-secondary">
										<?php esc_html_e( 'View', QBA_TEXT_DOMAIN ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div class="qba-no-data">
				<div class="qba-no-data-icon">⚔️</div>
				<h4><?php esc_html_e( 'No Recent Battles', QBA_TEXT_DOMAIN ); ?></h4>
				<p><?php esc_html_e( 'No battles have been created in the last 30 days. Battles will appear here once players start competing.', QBA_TEXT_DOMAIN ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<style>
.qba-battles-page {
	max-width: none;
}

.qba-battles-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 1px solid #e1e1e1;
}

.qba-battles-title h1 {
	margin: 0 0 5px 0;
	font-size: 28px;
	font-weight: 300;
}

.qba-battles-title .description {
	color: #666;
	margin: 0;
	font-size: 14px;
}

.qba-battles-actions {
	display: flex;
	gap: 10px;
}

.qba-battles-stats {
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

.qba-battles-content {
	background: #fff;
	border: 1px solid #e1e1e1;
	border-radius: 4px;
	padding: 30px;
	margin-bottom: 30px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.qba-battles-header-section h2 {
	margin: 0 0 10px 0;
	color: #1d2327;
	font-size: 20px;
}

.qba-battles-header-section p {
	color: #646970;
	margin: 0 0 20px 0;
	font-size: 14px;
}

.qba-battles-table-container {
	overflow-x: auto;
}

.qba-battles-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 14px;
}

.qba-battles-table th,
.qba-battles-table td {
	padding: 12px 16px;
	text-align: left;
	border-bottom: 1px solid #f0f0f1;
}

.qba-battles-table th {
	background: #f8f9fa;
	font-weight: 600;
	color: #1d2327;
	border-bottom: 2px solid #e1e1e1;
}

.qba-battles-table tr:hover {
	background: #f8f9fa;
}

.qba-battles-table .qba-battle-id {
	font-weight: 600;
	color: #007cba;
	font-family: monospace;
}

.qba-battles-table .qba-player {
	display: flex;
	align-items: center;
	gap: 8px;
	font-weight: 500;
}

.qba-battles-table .qba-quiz {
	font-weight: 500;
	color: #495057;
}

.qba-battles-table .qba-winner {
	display: flex;
	align-items: center;
	gap: 8px;
	font-weight: 600;
	color: #28a745;
}

.qba-no-winner {
	color: #6c757d;
	font-weight: normal;
}

.qba-battles-table .qba-date {
	color: #646970;
	font-size: 13px;
}

.qba-battles-table .qba-actions .button {
	padding: 4px 12px;
	font-size: 12px;
	height: auto;
	line-height: 1.4;
}

.qba-status {
	padding: 4px 8px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.qba-status-active {
	background: #d4edda;
	color: #155724;
}

.qba-status-completed {
	background: #d1ecf1;
	color: #0c5460;
}

.qba-status-pending {
	background: #fff3cd;
	color: #856404;
}

.qba-status-expired,
.qba-status-cancelled {
	background: #f8d7da;
	color: #721c24;
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
	.qba-battles-header {
		flex-direction: column;
		gap: 20px;
	}

	.qba-battles-actions {
		align-self: flex-start;
	}

	.qba-battles-stats {
		grid-template-columns: 1fr;
	}

	.qba-stat-card {
		padding: 16px;
	}

	.qba-stat-number {
		font-size: 20px;
	}

	.qba-battles-content {
		padding: 20px;
	}

	.qba-battles-table th,
	.qba-battles-table td {
		padding: 8px 12px;
		font-size: 13px;
	}

	.qba-battles-table .qba-player {
		flex-direction: column;
		align-items: flex-start;
		gap: 4px;
	}

	.qba-battles-table .qba-winner {
		flex-direction: column;
		align-items: flex-start;
		gap: 4px;
	}
}
</style>
