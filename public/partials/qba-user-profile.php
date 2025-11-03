<?php
/**
 * User Profile Partial for BuddyBoss
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id    = bp_displayed_user_id();
$user_stats = qba_get_user_stats( $user_id );
$user_info  = get_userdata( $user_id );

// Calculate additional stats
$accuracy = $user_stats['total_questions_answered'] > 0
	? round( ( $user_stats['correct_answers'] / $user_stats['total_questions_answered'] ) * 100, 1 )
	: 0;

$win_rate = $user_stats['total_battles'] > 0
	? round( ( $user_stats['battles_won'] / $user_stats['total_battles'] ) * 100, 1 )
	: 0;
?>

<div class="qba-profile-container">
	<div class="qba-profile-header">
		<div class="qba-profile-avatar">
			<?php echo get_avatar( $user_id, 100 ); ?>
		</div>
		<div class="qba-profile-info">
			<h2><?php echo esc_html( $user_info->display_name ); ?></h2>
			<div class="qba-profile-rating">
				<span class="qba-rating-badge"><?php echo esc_html( $user_stats['elo_rating'] ); ?> ELO</span>
				<?php if ( $user_stats['win_streak'] > 0 ) : ?>
				<span class="qba-streak-badge">🔥 <?php echo esc_html( $user_stats['win_streak'] ); ?> streak</span>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="qba-profile-stats">
		<div class="qba-stats-overview">
			<div class="qba-stat-item">
				<div class="qba-stat-value"><?php echo esc_html( $user_stats['total_battles'] ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Total Battles', QBA_TEXT_DOMAIN ); ?></div>
			</div>
			<div class="qba-stat-item">
				<div class="qba-stat-value"><?php echo esc_html( $user_stats['battles_won'] ); ?></div>
				<div class="qba-stat-label"><?php esc_html_e( 'Wins', QBA_TEXT_DOMAIN ); ?></div>
			</div>
			<div class="qba-stat-item">
				<div class="qba-stat-value"><?php echo esc_html( $win_rate ); ?>%</div>
				<div class="qba-stat-label"><?php esc_html_e( 'Win Rate', QBA_TEXT_DOMAIN ); ?></div>
			</div>
			<div class="qba-stat-item">
				<div class="qba-stat-value"><?php echo esc_html( $accuracy ); ?>%</div>
				<div class="qba-stat-label"><?php esc_html_e( 'Accuracy', QBA_TEXT_DOMAIN ); ?></div>
			</div>
		</div>

		<div class="qba-detailed-stats">
			<h3><?php esc_html_e( 'Detailed Statistics', QBA_TEXT_DOMAIN ); ?></h3>
			<div class="qba-stats-grid">
				<div class="qba-detail-stat">
					<span class="qba-detail-label"><?php esc_html_e( 'Questions Answered:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-detail-value"><?php echo esc_html( $user_stats['total_questions_answered'] ); ?></span>
				</div>
				<div class="qba-detail-stat">
					<span class="qba-detail-label"><?php esc_html_e( 'Correct Answers:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-detail-value"><?php echo esc_html( $user_stats['correct_answers'] ); ?></span>
				</div>
				<div class="qba-detail-stat">
					<span class="qba-detail-label"><?php esc_html_e( 'Average Answer Time:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-detail-value"><?php echo esc_html( $user_stats['avg_answer_time'] ); ?>s</span>
				</div>
				<div class="qba-detail-stat">
					<span class="qba-detail-label"><?php esc_html_e( 'Best Win Streak:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-detail-value"><?php echo esc_html( $user_stats['best_win_streak'] ); ?></span>
				</div>
				<div class="qba-detail-stat">
					<span class="qba-detail-label"><?php esc_html_e( 'Total Points:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-detail-value"><?php echo esc_html( $user_stats['total_points'] ); ?></span>
				</div>
				<?php if ( $user_stats['last_battle_at'] ) : ?>
				<div class="qba-detail-stat">
					<span class="qba-detail-label"><?php esc_html_e( 'Last Battle:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-detail-value"><?php echo esc_html( qba_get_relative_time( $user_stats['last_battle_at'] ) ); ?></span>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="qba-profile-badges">
		<h3><?php esc_html_e( 'Achievements', QBA_TEXT_DOMAIN ); ?></h3>
		<?php
		$achievements = new QBA_Achievements();
		echo $achievements->render_achievements( $user_id, 'grid' );
		?>
	</div>

	<div class="qba-profile-actions">
		<?php if ( bp_loggedin_user_id() !== $user_id ) : ?>
		<button type="button" class="qba-challenge-btn button">
			<?php esc_html_e( 'Challenge to Battle', QBA_TEXT_DOMAIN ); ?>
		</button>
		<?php endif; ?>

		<a href="<?php echo esc_url( bp_displayed_user_domain() . 'battle-stats/history/' ); ?>" class="qba-view-history button secondary">
			<?php esc_html_e( 'View Battle History', QBA_TEXT_DOMAIN ); ?>
		</a>
	</div>
</div>

<style>
.qba-profile-container {
	max-width: 800px;
	margin: 0 auto;
}

.qba-profile-header {
	display: flex;
	align-items: center;
	gap: 20px;
	margin-bottom: 30px;
	padding: 20px;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-radius: 12px;
	color: white;
}

.qba-profile-avatar {
	border-radius: 50%;
	overflow: hidden;
	border: 4px solid rgba(255, 255, 255, 0.3);
}

.qba-profile-info h2 {
	margin: 0 0 10px 0;
	font-size: 1.8rem;
}

.qba-profile-rating {
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
}

.qba-rating-badge,
.qba-streak-badge {
	display: inline-block;
	padding: 5px 12px;
	border-radius: 20px;
	font-weight: 600;
	font-size: 0.9rem;
}

.qba-rating-badge {
	background: rgba(255, 255, 255, 0.2);
}

.qba-streak-badge {
	background: rgba(255, 255, 255, 0.3);
}

.qba-profile-stats {
	margin-bottom: 30px;
}

.qba-stats-overview {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.qba-stat-item {
	text-align: center;
	padding: 20px;
	background: #f8f9fa;
	border-radius: 8px;
	border: 1px solid #e9ecef;
}

.qba-stat-value {
	font-size: 2rem;
	font-weight: 700;
	color: #495057;
	margin-bottom: 5px;
}

.qba-stat-label {
	color: #6c757d;
	font-size: 0.9rem;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.qba-detailed-stats h3 {
	margin-bottom: 20px;
	color: #495057;
}

.qba-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 15px;
}

.qba-detail-stat {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px 15px;
	background: #f8f9fa;
	border-radius: 6px;
	border: 1px solid #e9ecef;
}

.qba-detail-label {
	font-weight: 500;
	color: #495057;
}

.qba-detail-value {
	font-weight: 600;
	color: #007cba;
}

.qba-profile-badges {
	margin-bottom: 30px;
}

.qba-profile-badges h3 {
	margin-bottom: 20px;
	color: #495057;
}

.qba-profile-actions {
	display: flex;
	gap: 15px;
	justify-content: center;
	flex-wrap: wrap;
}

.qba-challenge-btn {
	background: #007cba;
	color: white;
	border: none;
	padding: 12px 24px;
	border-radius: 6px;
	font-weight: 600;
	cursor: pointer;
	transition: background 0.2s ease;
}

.qba-challenge-btn:hover {
	background: #005a87;
}

.qba-view-history {
	color: #007cba;
	text-decoration: none;
	padding: 12px 24px;
	border: 1px solid #007cba;
	border-radius: 6px;
	font-weight: 600;
	transition: all 0.2s ease;
}

.qba-view-history:hover {
	background: #007cba;
	color: white;
}

@media (max-width: 768px) {
	.qba-profile-header {
		flex-direction: column;
		text-align: center;
	}

	.qba-stats-overview {
		grid-template-columns: repeat(2, 1fr);
	}

	.qba-stats-grid {
		grid-template-columns: 1fr;
	}

	.qba-profile-actions {
		flex-direction: column;
		align-items: center;
	}
}
</style>