<?php
/**
 * Battle Modal Template
 *
 * HTML template for the battle modal interface
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- Battle Modal Container -->
<div id="qba-battle-modal" class="qba-modal-overlay" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="qba-modal-title">
	<div class="qba-modal-container">
		<!-- Modal Header -->
		<div class="qba-modal-header">
			<h2 class="qba-modal-title">
				<span class="qba-modal-icon">⚔️</span>
				<?php esc_html_e( 'Quiz Battle Arena', QBA_TEXT_DOMAIN ); ?>
			</h2>
			<button type="button" class="qba-modal-close" aria-label="<?php esc_attr_e( 'Close battle', QBA_TEXT_DOMAIN ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>

		<!-- Modal Content -->
		<div class="qba-modal-content">
			<!-- Battle Setup Screen -->
			<div id="qba-battle-setup" class="qba-battle-screen">
				<div class="qba-setup-content">
					<div class="qba-setup-header">
						<h3><?php esc_html_e( 'Choose Your Battle', QBA_TEXT_DOMAIN ); ?></h3>
						<p><?php esc_html_e( 'Select how you want to find an opponent', QBA_TEXT_DOMAIN ); ?></p>
					</div>

					<div class="qba-battle-options">
						<!-- Quick Battle Option -->
						<div class="qba-battle-option qba-quick-battle-option">
							<div class="qba-option-icon">⚡</div>
							<div class="qba-option-content">
								<h4><?php esc_html_e( 'Quick Battle', QBA_TEXT_DOMAIN ); ?></h4>
								<p><?php esc_html_e( 'Jump into a battle with a random opponent instantly', QBA_TEXT_DOMAIN ); ?></p>
								<button type="button" class="qba-btn qba-btn-primary qba-start-quick-battle">
									<?php esc_html_e( 'Find Opponent', QBA_TEXT_DOMAIN ); ?>
								</button>
							</div>
						</div>

						<!-- Challenge Friend Option -->
						<div class="qba-battle-option qba-challenge-friend-option">
							<div class="qba-option-icon">👥</div>
							<div class="qba-option-content">
								<h4><?php esc_html_e( 'Challenge a Friend', QBA_TEXT_DOMAIN ); ?></h4>
								<p><?php esc_html_e( 'Send a battle challenge to a specific player', QBA_TEXT_DOMAIN ); ?></p>

								<div class="qba-friend-selector">
									<select id="qba-friend-select" class="qba-select">
										<option value=""><?php esc_html_e( 'Select a friend...', QBA_TEXT_DOMAIN ); ?></option>
										<!-- Friends will be loaded dynamically -->
									</select>
									<button type="button" class="qba-btn qba-btn-secondary qba-challenge-friend-btn" disabled>
										<?php esc_html_e( 'Send Challenge', QBA_TEXT_DOMAIN ); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Matchmaking Queue Status -->
					<div id="qba-queue-status" class="qba-queue-status" style="display: none;">
						<div class="qba-queue-spinner"></div>
						<div class="qba-queue-message">
							<h4><?php esc_html_e( 'Finding Opponent...', QBA_TEXT_DOMAIN ); ?></h4>
							<p><?php esc_html_e( 'Please wait while we match you with another player', QBA_TEXT_DOMAIN ); ?></p>
							<div class="qba-queue-timer">00:00</div>
						</div>
						<button type="button" class="qba-btn qba-btn-secondary qba-leave-queue-btn">
							<?php esc_html_e( 'Cancel Search', QBA_TEXT_DOMAIN ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Battle Active Screen -->
			<div id="qba-battle-active" class="qba-battle-screen" style="display: none;">
				<div class="qba-battle-header">
					<div class="qba-battle-info">
						<div class="qba-battle-round">
							<?php esc_html_e( 'Question', QBA_TEXT_DOMAIN ); ?> <span id="qba-current-question">1</span> <?php esc_html_e( 'of', QBA_TEXT_DOMAIN ); ?> <span id="qba-total-questions">10</span>
						</div>
						<div class="qba-battle-timer">
							<span class="qba-timer-icon">⏱️</span>
							<span id="qba-battle-timer">15:00</span>
						</div>
					</div>

					<div class="qba-players-status">
						<div class="qba-player-status qba-player-you">
							<div class="qba-player-avatar">
								<?php echo get_avatar( get_current_user_id(), 32 ); ?>
							</div>
							<div class="qba-player-info">
								<div class="qba-player-name"><?php esc_html_e( 'You', QBA_TEXT_DOMAIN ); ?></div>
								<div class="qba-player-score">0 <?php esc_html_e( 'pts', QBA_TEXT_DOMAIN ); ?></div>
							</div>
							<div class="qba-player-progress">
								<div class="qba-progress-bar">
									<div class="qba-progress-fill" style="width: 0%"></div>
								</div>
							</div>
						</div>

						<div class="qba-vs-indicator">VS</div>

						<div class="qba-player-status qba-player-opponent">
							<div class="qba-player-avatar">
								<div class="qba-opponent-placeholder">?</div>
							</div>
							<div class="qba-player-info">
								<div class="qba-player-name"><?php esc_html_e( 'Opponent', QBA_TEXT_DOMAIN ); ?></div>
								<div class="qba-player-score">0 <?php esc_html_e( 'pts', QBA_TEXT_DOMAIN ); ?></div>
							</div>
							<div class="qba-player-progress">
								<div class="qba-progress-bar">
									<div class="qba-progress-fill" style="width: 0%"></div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="qba-battle-content">
					<!-- Question Display -->
					<div id="qba-question-display" class="qba-question-display">
						<div class="qba-question-header">
							<h3 id="qba-question-text"><?php esc_html_e( 'Loading question...', QBA_TEXT_DOMAIN ); ?></h3>
						</div>

						<div id="qba-answer-options" class="qba-answer-options">
							<!-- Answer options will be loaded dynamically -->
						</div>

						<div class="qba-question-actions">
							<button type="button" class="qba-btn qba-btn-primary qba-submit-answer-btn" disabled>
								<?php esc_html_e( 'Submit Answer', QBA_TEXT_DOMAIN ); ?>
							</button>
						</div>
					</div>

					<!-- Battle Results -->
					<div id="qba-battle-results" class="qba-battle-results" style="display: none;">
						<div class="qba-results-header">
							<h3><?php esc_html_e( 'Battle Complete!', QBA_TEXT_DOMAIN ); ?></h3>
						</div>

						<div class="qba-results-content">
							<div class="qba-results-players">
								<div class="qba-result-player qba-result-you">
									<div class="qba-result-avatar">
										<?php echo get_avatar( get_current_user_id(), 48 ); ?>
									</div>
									<div class="qba-result-info">
										<div class="qba-result-name"><?php esc_html_e( 'You', QBA_TEXT_DOMAIN ); ?></div>
										<div class="qba-result-score" id="qba-your-final-score">0</div>
										<div class="qba-result-accuracy" id="qba-your-accuracy">0%</div>
									</div>
									<div class="qba-result-status" id="qba-your-status">Draw</div>
								</div>

								<div class="qba-vs-indicator">VS</div>

								<div class="qba-result-player qba-result-opponent">
									<div class="qba-result-avatar">
										<div class="qba-opponent-result-avatar">?</div>
									</div>
									<div class="qba-result-info">
										<div class="qba-result-name" id="qba-opponent-name"><?php esc_html_e( 'Opponent', QBA_TEXT_DOMAIN ); ?></div>
										<div class="qba-result-score" id="qba-opponent-final-score">0</div>
										<div class="qba-result-accuracy" id="qba-opponent-accuracy">0%</div>
									</div>
									<div class="qba-result-status" id="qba-opponent-status">Draw</div>
								</div>
							</div>

							<div class="qba-results-actions">
								<button type="button" class="qba-btn qba-btn-primary qba-play-again-btn">
									<?php esc_html_e( 'Play Again', QBA_TEXT_DOMAIN ); ?>
								</button>
								<button type="button" class="qba-btn qba-btn-secondary qba-view-leaderboard-btn">
									<?php esc_html_e( 'View Leaderboard', QBA_TEXT_DOMAIN ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Battle Challenge Received Screen -->
			<div id="qba-battle-challenge" class="qba-battle-screen" style="display: none;">
				<div class="qba-challenge-content">
					<div class="qba-challenge-header">
						<h3><?php esc_html_e( 'Battle Challenge!', QBA_TEXT_DOMAIN ); ?></h3>
						<p id="qba-challenge-message">
							<?php esc_html_e( 'You have received a battle challenge', QBA_TEXT_DOMAIN ); ?>
						</p>
					</div>

					<div class="qba-challenge-details">
						<div class="qba-challenger-info">
							<div class="qba-challenger-avatar" id="qba-challenger-avatar"></div>
							<div class="qba-challenger-name" id="qba-challenger-name"></div>
						</div>

						<div class="qba-challenge-quiz">
							<strong><?php esc_html_e( 'Quiz:', QBA_TEXT_DOMAIN ); ?></strong>
							<span id="qba-challenge-quiz-name"></span>
						</div>

						<div class="qba-challenge-timer">
							<strong><?php esc_html_e( 'Expires in:', QBA_TEXT_DOMAIN ); ?></strong>
							<span id="qba-challenge-timer">05:00</span>
						</div>
					</div>

					<div class="qba-challenge-actions">
						<button type="button" class="qba-btn qba-btn-primary qba-accept-challenge-btn">
							<?php esc_html_e( 'Accept Challenge', QBA_TEXT_DOMAIN ); ?>
						</button>
						<button type="button" class="qba-btn qba-btn-secondary qba-decline-challenge-btn">
							<?php esc_html_e( 'Decline', QBA_TEXT_DOMAIN ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Loading Overlay -->
<div id="qba-loading-overlay" class="qba-loading-overlay" style="display: none;">
	<div class="qba-loading-content">
		<div class="qba-loading-spinner"></div>
		<div class="qba-loading-text"><?php esc_html_e( 'Loading...', QBA_TEXT_DOMAIN ); ?></div>
	</div>
</div>