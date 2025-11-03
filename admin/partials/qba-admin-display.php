<?php
/**
 * Admin Display Partial
 *
 * Main admin dashboard for Quiz Battle Arena
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Quiz Battle Arena', QBA_TEXT_DOMAIN ); ?></h1>

	<div class="qba-admin-dashboard">
		<div class="qba-dashboard-header">
			<p><?php esc_html_e( 'Transform LearnDash quizzes into competitive real-time 1v1 battles with leaderboards, achievements, and social integration.', QBA_TEXT_DOMAIN ); ?></p>
		</div>

		<div class="qba-dashboard-stats">
			<h2><?php esc_html_e( 'Quick Stats', QBA_TEXT_DOMAIN ); ?></h2>
			<div class="qba-stats-grid" id="qba-admin-stats">
				<div class="qba-stat-card">
					<div class="qba-stat-icon">⚔️</div>
					<div class="qba-stat-content">
						<div class="qba-stat-number" data-stat="total_battles">--</div>
						<div class="qba-stat-label"><?php esc_html_e( 'Total Battles', QBA_TEXT_DOMAIN ); ?></div>
					</div>
				</div>

				<div class="qba-stat-card">
					<div class="qba-stat-icon">🎯</div>
					<div class="qba-stat-content">
						<div class="qba-stat-number" data-stat="active_battles">--</div>
						<div class="qba-stat-label"><?php esc_html_e( 'Active Battles', QBA_TEXT_DOMAIN ); ?></div>
					</div>
				</div>

				<div class="qba-stat-card">
					<div class="qba-stat-icon">👥</div>
					<div class="qba-stat-content">
						<div class="qba-stat-number" data-stat="total_users">--</div>
						<div class="qba-stat-label"><?php esc_html_e( 'Active Players', QBA_TEXT_DOMAIN ); ?></div>
					</div>
				</div>

				<div class="qba-stat-card">
					<div class="qba-stat-icon">🏆</div>
					<div class="qba-stat-content">
						<div class="qba-stat-number" data-stat="total_badges">--</div>
						<div class="qba-stat-label"><?php esc_html_e( 'Badges Earned', QBA_TEXT_DOMAIN ); ?></div>
					</div>
				</div>
			</div>
		</div>

		<div class="qba-dashboard-actions">
			<h2><?php esc_html_e( 'Quick Actions', QBA_TEXT_DOMAIN ); ?></h2>
			<div class="qba-action-buttons">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-settings' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Settings', QBA_TEXT_DOMAIN ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-leaderboards' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Leaderboards', QBA_TEXT_DOMAIN ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-battles' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Manage Battles', QBA_TEXT_DOMAIN ); ?>
				</a>
				<button type="button" class="button" id="qba-clear-cache">
					<?php esc_html_e( 'Clear Cache', QBA_TEXT_DOMAIN ); ?>
				</button>
			</div>
		</div>

		<div class="qba-dashboard-recent-activity">
			<h2><?php esc_html_e( 'Recent Activity', QBA_TEXT_DOMAIN ); ?></h2>
			<div class="qba-activity-list">
				<div class="qba-activity-item">
					<div class="qba-activity-icon">🔄</div>
					<div class="qba-activity-content">
						<div class="qba-activity-text"><?php esc_html_e( 'Plugin initialized successfully', QBA_TEXT_DOMAIN ); ?></div>
						<div class="qba-activity-time"><?php echo esc_html( human_time_diff( time(), time() ) ); ?> ago</div>
					</div>
				</div>
				<!-- More activity items would be loaded dynamically -->
			</div>
		</div>

		<div class="qba-dashboard-info">
			<div class="qba-info-box">
				<h3><?php esc_html_e( 'Getting Started', QBA_TEXT_DOMAIN ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Configure settings in the Settings page', QBA_TEXT_DOMAIN ); ?></li>
					<li><?php esc_html_e( 'Create LearnDash quizzes with battle-enabled questions', QBA_TEXT_DOMAIN ); ?></li>
					<li><?php esc_html_e( 'Users can start battling from quiz pages', QBA_TEXT_DOMAIN ); ?></li>
					<li><?php esc_html_e( 'Monitor activity from the Leaderboards and Battles pages', QBA_TEXT_DOMAIN ); ?></li>
				</ol>
			</div>

			<div class="qba-info-box">
				<h3><?php esc_html_e( 'System Requirements', QBA_TEXT_DOMAIN ); ?></h3>
				<ul>
					<li><strong>LearnDash:</strong> <?php echo esc_html( LEARNDASH_VERSION ?? 'Not detected' ); ?></li>
					<li><strong>WordPress:</strong> <?php echo esc_html( get_bloginfo( 'version' ) ); ?></li>
					<li><strong>PHP:</strong> <?php echo esc_html( PHP_VERSION ); ?></li>
					<li><strong>BuddyBoss:</strong> <?php echo function_exists( 'bp_get_version' ) ? esc_html( bp_get_version() ) : esc_html__( 'Not installed', QBA_TEXT_DOMAIN ); ?></li>
				</ul>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Load stats on page load
	qba_load_admin_stats();

	// Clear cache button
	$('#qba-clear-cache').on('click', function(e) {
		e.preventDefault();

		if (!confirm(qba_admin_ajax.strings.confirm_delete)) {
			return;
		}

		$(this).prop('disabled', true).text(qba_admin_ajax.strings.loading);

		$.ajax({
			url: qba_admin_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'qba_admin_action',
				action_type: 'clear_cache',
				nonce: qba_admin_ajax.nonce
			},
			success: function(response) {
				if (response.success) {
					alert(response.data);
					qba_load_admin_stats(); // Reload stats
				} else {
					alert(qba_admin_ajax.strings.error);
				}
			},
			error: function() {
				alert(qba_admin_ajax.strings.error);
			},
			complete: function() {
				$('#qba-clear-cache').prop('disabled', false).text('<?php esc_html_e( 'Clear Cache', QBA_TEXT_DOMAIN ); ?>');
			}
		});
	});

	function qba_load_admin_stats() {
		$.ajax({
			url: qba_admin_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'qba_admin_action',
				action_type: 'get_battle_stats',
				nonce: qba_admin_ajax.nonce
			},
			success: function(response) {
				if (response.success) {
					$.each(response.data, function(key, value) {
						$('[data-stat="' + key + '"]').text(value);
					});
				}
			}
		});
	}
});
</script>