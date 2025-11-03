<?php
/**
 * User Stats Shortcode Template
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id     = intval( $atts['user_id'] );
$show_badges = $atts['show_badges'] === 'true';

$user_stats = qba_get_user_stats( $user_id );
$user_info  = get_userdata( $user_id );

if ( ! $user_info ) {
	echo '<p>' . esc_html__( 'User not found.', QBA_TEXT_DOMAIN ) . '</p>';
	return;
}

// Calculate additional stats
$accuracy = $user_stats['total_questions_answered'] > 0
	? round( ( $user_stats['correct_answers'] / $user_stats['total_questions_answered'] ) * 100, 1 )
	: 0;

$win_rate = $user_stats['total_battles'] > 0
	? round( ( $user_stats['battles_won'] / $user_stats['total_battles'] ) * 100, 1 )
	: 0;
?>

<div class="qba-user-stats-container">
	<div class="qba-user-header">
		<div class="qba-user-avatar">
			<?php echo get_avatar( $user_id, 80 ); ?>
		</div>
		<div class="qba-user-info">
			<h3 class="qba-user-name"><?php echo esc_html( $user_info->display_name ); ?></h3>
			<div class="qba-user-rating">
				<span class="qba-rating-badge"><?php echo esc_html( $user_stats['elo_rating'] ); ?> ELO</span>
			</div>
		</div>
	</div>

	<div class="qba-stats-grid">
		<div class="qba-stat-card qba-stat-primary">
			<div class="qba-stat-icon">🏆</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $user_stats['total_points'] ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Total Points', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-stat-card">
			<div class="qba-stat-icon">⚔️</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $user_stats['total_battles'] ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Total Battles', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-stat-card qba-stat-win">
			<div class="qba-stat-icon">✅</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $user_stats['battles_won'] ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Wins', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-stat-card qba-stat-loss">
			<div class="qba-stat-icon">❌</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $user_stats['battles_lost'] ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Losses', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-stat-card">
			<div class="qba-stat-icon">📊</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $win_rate ); ?>%</div>
				<div class="qba-stat-label"><?php esc_html_e( 'Win Rate', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-stat-card">
			<div class="qba-stat-icon">🎯</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $accuracy ); ?>%</div>
				<div class="qba-stat-label"><?php esc_html_e( 'Accuracy', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-stat-card">
			<div class="qba-stat-icon">🔥</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $user_stats['win_streak'] ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Current Streak', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-stat-card">
			<div class="qba-stat-icon">⭐</div>
			<div class="qba-stat-content">
				<div class="qba-stat-number"><?php echo esc_html( $user_stats['best_win_streak'] ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Best Streak', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>
	</div>

	<div class="qba-detailed-stats">
		<h4><?php esc_html_e( 'Detailed Statistics', QBA_TEXT_DOMAIN ); ?></h4>
		<div class="qba-stats-table">
			<div class="qba-stat-row">
				<span class="qba-stat-name"><?php esc_html_e( 'Questions Answered', QBA_TEXT_DOMAIN ); ?>:</span>
				<span class="qba-stat-value"><?php echo esc_html( $user_stats['total_questions_answered'] ); ?></span>
			</div>
			<div class="qba-stat-row">
				<span class="qba-stat-name"><?php esc_html_e( 'Correct Answers', QBA_TEXT_DOMAIN ); ?>:</span>
				<span class="qba-stat-value"><?php echo esc_html( $user_stats['correct_answers'] ); ?></span>
			</div>
			<div class="qba-stat-row">
				<span class="qba-stat-name"><?php esc_html_e( 'Average Answer Time', QBA_TEXT_DOMAIN ); ?>:</span>
				<span class="qba-stat-value"><?php echo esc_html( $user_stats['avg_answer_time'] ); ?>s</span>
			</div>
			<div class="qba-stat-row">
				<span class="qba-stat-name"><?php esc_html_e( 'Draws', QBA_TEXT_DOMAIN ); ?>:</span>
				<span class="qba-stat-value"><?php echo esc_html( $user_stats['battles_drawn'] ); ?></span>
			</div>
			<?php if ( $user_stats['last_battle_at'] ) : ?>
			<div class="qba-stat-row">
				<span class="qba-stat-name"><?php esc_html_e( 'Last Battle', QBA_TEXT_DOMAIN ); ?>:</span>
				<span class="qba-stat-value"><?php echo esc_html( qba_get_relative_time( $user_stats['last_battle_at'] ) ); ?></span>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $show_badges ) : ?>
	<div class="qba-user-badges">
		<h4><?php esc_html_e( 'Achievements', QBA_TEXT_DOMAIN ); ?></h4>
		<?php
		$achievements = new QBA_Achievements();
		echo $achievements->render_achievements( $user_id, 'compact' );
		?>
	</div>
	<?php endif; ?>
</div>

<style>
.qba-user-stats-container {
	max-width: 800px;
	margin: 0 auto;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.qba-user-header {
	display: flex;
	align-items: center;
	gap: 1rem;
	margin-bottom: 2rem;
	padding: 1.5rem;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-radius: 12px;
	color: white;
}

.qba-user-avatar {
	border-radius: 50%;
	overflow: hidden;
	border: 3px solid rgba(255, 255, 255, 0.3);
}

.qba-user-name {
	margin: 0 0 0.5rem 0;
	font-size: 1.5rem;
	font-weight: 600;
}

.qba-rating-badge {
	display: inline-block;
	padding: 0.5rem 1rem;
	background: rgba(255, 255, 255, 0.2);
	border-radius: 20px;
	font-weight: 600;
	font-size: 0.9rem;
}

.qba-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 1rem;
	margin-bottom: 2rem;
}

.qba-stat-card {
	background: white;
	border: 1px solid #e9ecef;
	border-radius: 8px;
	padding: 1.5rem;
	display: flex;
	align-items: center;
	gap: 1rem;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	transition: transform 0.2s ease;
}

.qba-stat-card:hover {
	transform: translateY(-2px);
}

.qba-stat-primary {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	border: none;
}

.qba-stat-win {
	border-color: #28a745;
}

.qba-stat-loss {
	border-color: #dc3545;
}

.qba-stat-icon {
	font-size: 2rem;
	opacity: 0.8;
}

.qba-stat-content {
	flex: 1;
}

.qba-stat-number {
	font-size: 2rem;
	font-weight: 700;
	margin-bottom: 0.25rem;
}

.qba-stat-label {
	font-size: 0.9rem;
	opacity: 0.8;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.qba-detailed-stats h4,
.qba-user-badges h4 {
	margin: 2rem 0 1rem 0;
	color: #495057;
	font-size: 1.2rem;
}

.qba-stats-table {
	background: #f8f9fa;
	border-radius: 8px;
	padding: 1rem;
}

.qba-stat-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 0.5rem 0;
	border-bottom: 1px solid #e9ecef;
}

.qba-stat-row:last-child {
	border-bottom: none;
}

.qba-stat-name {
	font-weight: 500;
	color: #495057;
}

.qba-stat-value {
	font-weight: 600;
	color: #007cba;
}

@media (max-width: 768px) {
	.qba-user-header {
		flex-direction: column;
		text-align: center;
	}

	.qba-stats-grid {
		grid-template-columns: 1fr;
	}

	.qba-stat-card {
		padding: 1rem;
	}

	.qba-stat-row {
		flex-direction: column;
		align-items: flex-start;
		gap: 0.25rem;
	}
}
</style>