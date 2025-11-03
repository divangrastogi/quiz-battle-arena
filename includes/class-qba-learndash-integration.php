<?php
/**
 * LearnDash Integration Class
 *
 * Handles integration with LearnDash LMS
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_LearnDash_Integration Class
 *
 * @since 1.0.0
 */
class QBA_LearnDash_Integration {

	/**
	 * Initialize LearnDash integration
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Add battle button to quiz content
		add_filter( 'learndash_quiz_content', array( $this, 'add_battle_button' ), 10, 2 );

		// Handle quiz submission for battles
		add_action( 'learndash_quiz_submitted', array( $this, 'handle_quiz_submission' ), 10, 2 );
		add_action( 'learndash_quiz_completed', array( $this, 'handle_quiz_completion' ), 10, 3 );

		// Modify quiz display for battle mode
		add_filter( 'learndash_quiz_question_row', array( $this, 'modify_question_display_battle' ), 10, 3 );
		add_filter( 'learndash_quiz_results', array( $this, 'modify_battle_results' ), 10, 3 );
	}

	/**
	 * Add battle button to quiz content
	 *
	 * @since 1.0.0
	 * @param string $content Quiz content
	 * @param int    $quiz_id Quiz ID
	 * @return string Modified content
	 */
	public function add_battle_button( $content, $quiz_id ) {
		// Only show on quiz pages
		if ( ! is_singular( 'sfwd-quiz' ) ) {
			return $content;
		}

		// Check if battles are enabled
		if ( ! get_option( 'qba_enable_battles', '1' ) ) {
			return $content;
		}

		// Check if user can access quiz
		if ( ! qba_user_can_access_quiz( get_current_user_id(), $quiz_id ) ) {
			return $content;
		}

		// Check if user is available for battle
		if ( ! qba_is_user_available_for_battle( get_current_user_id() ) ) {
			return $content;
		}

		// Get quiz questions count
		$questions = learndash_get_quiz_questions( $quiz_id );
		if ( count( $questions ) < get_option( 'qba_min_questions', 5 ) ) {
			return $content;
		}

		// Add battle buttons
		$battle_buttons = $this->get_battle_buttons_html( $quiz_id );
		$content       .= $battle_buttons;

		return $content;
	}

	/**
	 * Generate battle buttons HTML
	 *
	 * @since 1.0.0
	 * @param int $quiz_id Quiz ID
	 * @return string HTML content
	 */
	private function get_battle_buttons_html( $quiz_id ) {
		ob_start();
		?>
		<div class="qba-battle-buttons" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;">
			<h3 style="margin-top: 0; color: #333;"><?php esc_html_e( 'Challenge Mode', 'quiz-battle-arena' ); ?></h3>
			<p style="margin-bottom: 15px; color: #666;"><?php esc_html_e( 'Turn this quiz into a competitive battle! Challenge friends or find random opponents.', 'quiz-battle-arena' ); ?></p>

			<div style="display: flex; gap: 10px; flex-wrap: wrap;">
				<button type="button" class="qba-challenge-friend-btn" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>" style="background: #6366f1; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600;">
					<?php esc_html_e( 'Challenge Friend', 'quiz-battle-arena' ); ?>
				</button>

				<button type="button" class="qba-quick-match-btn" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>" style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600;">
					<?php esc_html_e( 'Quick Match', 'quiz-battle-arena' ); ?>
				</button>

				<button type="button" class="qba-skill-match-btn" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>" style="background: #f59e0b; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600;">
					<?php esc_html_e( 'Skill Match', 'quiz-battle-arena' ); ?>
				</button>
			</div>

			<!-- Opponent selection modal (hidden by default) -->
			<div id="qba-opponent-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
				<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
					<h3><?php esc_html_e( 'Select Opponent', 'quiz-battle-arena' ); ?></h3>
					<div id="qba-opponents-list" style="margin: 20px 0;">
						<!-- Opponents will be loaded here -->
					</div>
					<div style="text-align: right;">
						<button type="button" id="qba-cancel-challenge" style="background: #6b7280; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; margin-right: 10px;">
							<?php esc_html_e( 'Cancel', 'quiz-battle-arena' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Challenge friend button
			$('.qba-challenge-friend-btn').on('click', function() {
				var quizId = $(this).data('quiz-id');
				qba_show_opponent_modal(quizId);
			});

			// Quick match button
			$('.qba-quick-match-btn').on('click', function() {
				var quizId = $(this).data('quiz-id');
				qba_join_queue(quizId, 'random');
			});

			// Skill match button
			$('.qba-skill-match-btn').on('click', function() {
				var quizId = $(this).data('quiz-id');
				qba_join_queue(quizId, 'skill');
			});

			// Cancel challenge
			$('#qba-cancel-challenge').on('click', function() {
				$('#qba-opponent-modal').hide();
			});
		});

		function qba_show_opponent_modal(quizId) {
			$('#qba-opponent-modal').show();

			// Load available opponents
			$.ajax({
				url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
				method: 'POST',
				data: {
					action: 'qba_get_available_opponents',
					quiz_id: quizId,
					nonce: '<?php echo wp_create_nonce( 'qba_battle_nonce' ); ?>'
				},
				success: function(response) {
					if (response.success) {
						var opponentsHtml = '';
						response.data.forEach(function(opponent) {
							opponentsHtml += '<div style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 6px;">';
							opponentsHtml += '<img src="' + opponent.avatar + '" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">';
							opponentsHtml += '<div style="flex: 1;">';
							opponentsHtml += '<strong>' + opponent.name + '</strong><br>';
							opponentsHtml += '<small>ELO: ' + opponent.elo + '</small>';
							opponentsHtml += '</div>';
							opponentsHtml += '<button type="button" class="qba-challenge-btn" data-opponent-id="' + opponent.id + '" data-quiz-id="' + quizId + '" style="background: #6366f1; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Challenge</button>';
							opponentsHtml += '</div>';
						});
						$('#qba-opponents-list').html(opponentsHtml);
					}
				}
			});
		}

		function qba_join_queue(quizId, queueType) {
			$.ajax({
				url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
				method: 'POST',
				data: {
					action: 'qba_join_queue',
					quiz_id: quizId,
					queue_type: queueType,
					nonce: '<?php echo wp_create_nonce( 'qba_battle_nonce' ); ?>'
				},
				success: function(response) {
					if (response.success) {
						alert('<?php esc_html_e( 'Joined matchmaking queue! You will be matched with an opponent soon.', 'quiz-battle-arena' ); ?>');
					} else {
						alert(response.data || '<?php esc_html_e( 'Failed to join queue. Please try again.', 'quiz-battle-arena' ); ?>');
					}
				}
			});
		}

		// Handle challenge button clicks (delegated)
		$(document).on('click', '.qba-challenge-btn', function() {
			var opponentId = $(this).data('opponent-id');
			var quizId = $(this).data('quiz-id');

			$.ajax({
				url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
				method: 'POST',
				data: {
					action: 'qba_create_battle',
					quiz_id: quizId,
					opponent_id: opponentId,
					nonce: '<?php echo wp_create_nonce( 'qba_battle_nonce' ); ?>'
				},
				success: function(response) {
					if (response.success) {
						alert('<?php esc_html_e( 'Battle challenge sent! Wait for your opponent to accept.', 'quiz-battle-arena' ); ?>');
						$('#qba-opponent-modal').hide();
					} else {
						alert(response.data || '<?php esc_html_e( 'Failed to create battle. Please try again.', 'quiz-battle-arena' ); ?>');
					}
				}
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle quiz submission for battles
	 *
	 * @since 1.0.0
	 * @param array $quiz_data Quiz submission data
	 * @param int   $user_id   User ID
	 */
	public function handle_quiz_submission( $quiz_data, $user_id ) {
		// Check if this is a battle quiz submission
		if ( isset( $_POST['qba_battle_id'] ) ) {
			$battle_id = absint( $_POST['qba_battle_id'] );

			// Process battle answer
			do_action( 'qba_battle_answer_submitted', $battle_id, $user_id, $quiz_data );
		}
	}

	/**
	 * Handle quiz completion for battles
	 *
	 * @since 1.0.0
	 * @param array   $quiz_data Quiz completion data
	 * @param WP_User $user    User object
	 * @param int     $quiz_id Quiz ID
	 */
	public function handle_quiz_completion( $quiz_data, $user, $quiz_id ) {
		// Check if this is a battle quiz completion
		if ( isset( $_POST['qba_battle_id'] ) ) {
			$battle_id = absint( $_POST['qba_battle_id'] );

			// Process battle completion
			do_action( 'qba_battle_quiz_completed', $battle_id, $user->ID, $quiz_data );
		}
	}

	/**
	 * Modify question display for battle mode
	 *
	 * @since 1.0.0
	 * @param string $question_html Question HTML
	 * @param array  $question_data Question data
	 * @param int    $question_id   Question ID
	 * @return string Modified HTML
	 */
	public function modify_question_display_battle( $question_html, $question_data, $question_id ) {
		// Check if we're in battle mode
		if ( ! isset( $_GET['qba_battle_id'] ) ) {
			return $question_html;
		}

		// Add battle-specific styling and timer
		$battle_id      = absint( $_GET['qba_battle_id'] );
		$modified_html  = '<div class="qba-battle-question" data-battle-id="' . $battle_id . '" data-question-id="' . $question_id . '">';
		$modified_html .= $question_html;
		$modified_html .= '<div class="qba-question-timer" style="margin-top: 10px; font-weight: bold; color: #6366f1;">Time remaining: <span class="qba-timer-value">30</span>s</div>';
		$modified_html .= '</div>';

		return $modified_html;
	}

	/**
	 * Modify quiz results for battle mode
	 *
	 * @since 1.0.0
	 * @param string $results_html Results HTML
	 * @param array  $results_data Results data
	 * @param int    $quiz_id      Quiz ID
	 * @return string Modified HTML
	 */
	public function modify_battle_results( $results_html, $results_data, $quiz_id ) {
		// Check if we're in battle mode
		if ( ! isset( $_GET['qba_battle_id'] ) ) {
			return $results_html;
		}

		$battle_id = absint( $_GET['qba_battle_id'] );
		$battle    = qba_get_battle( $battle_id );

		if ( ! $battle ) {
			return $results_html;
		}

		// Add battle-specific results
		$battle_results  = '<div class="qba-battle-results" style="margin-top: 30px; padding: 20px; background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: 8px;">';
		$battle_results .= '<h3 style="color: #0ea5e9; margin-top: 0;">' . esc_html__( 'Battle Results', 'quiz-battle-arena' ) . '</h3>';

		// Show opponent progress
		$battle_results .= '<p>' . esc_html__( 'Waiting for opponent to complete...', 'quiz-battle-arena' ) . '</p>';
		$battle_results .= '<div class="qba-opponent-progress" style="width: 100%; height: 20px; background: #e5e7eb; border-radius: 10px; overflow: hidden;">';
		$battle_results .= '<div class="qba-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #10b981, #34d399); transition: width 0.3s;"></div>';
		$battle_results .= '</div>';

		$battle_results .= '</div>';

		return $results_html . $battle_results;
	}
}
