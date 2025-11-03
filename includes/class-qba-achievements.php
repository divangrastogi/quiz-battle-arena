<?php
/**
 * Achievements Class
 *
 * Handles badge and achievement system
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Achievements Class
 *
 * Manages achievements, badges, and rewards
 *
 * @since 1.0.0
 */
class QBA_Achievements {

	/**
	 * Available badges configuration
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $badges = array();

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->initialize_badges();
	}

	/**
	 * Initialize available badges
	 *
	 * @since 1.0.0
	 */
	private function initialize_badges() {
		$this->badges = array(
			'first_win'        => array(
				'name'        => __( 'First Victory', QBA_TEXT_DOMAIN ),
				'description' => __( 'Win your first battle', QBA_TEXT_DOMAIN ),
				'icon'        => '🏆',
				'points'      => get_option( 'qba_badge_points_first_win', 10 ),
				'condition'   => array( $this, 'check_first_win' ),
				'rarity'      => 'common',
			),
			'ten_wins'         => array(
				'name'        => __( 'Battle Master', QBA_TEXT_DOMAIN ),
				'description' => __( 'Win 10 battles', QBA_TEXT_DOMAIN ),
				'icon'        => '⚔️',
				'points'      => get_option( 'qba_badge_points_ten_wins', 50 ),
				'condition'   => array( $this, 'check_ten_wins' ),
				'rarity'      => 'uncommon',
			),
			'speed_demon'      => array(
				'name'        => __( 'Speed Demon', QBA_TEXT_DOMAIN ),
				'description' => __( 'Answer a question in under 5 seconds', QBA_TEXT_DOMAIN ),
				'icon'        => '⚡',
				'points'      => get_option( 'qba_badge_points_speed_demon', 30 ),
				'condition'   => array( $this, 'check_speed_demon' ),
				'rarity'      => 'rare',
			),
			'perfect_score'    => array(
				'name'        => __( 'Perfect Score', QBA_TEXT_DOMAIN ),
				'description' => __( 'Get 100% accuracy in a battle', QBA_TEXT_DOMAIN ),
				'icon'        => '💯',
				'points'      => get_option( 'qba_badge_points_perfect_score', 40 ),
				'condition'   => array( $this, 'check_perfect_score' ),
				'rarity'      => 'rare',
			),
			'win_streak'       => array(
				'name'        => __( 'Unstoppable', QBA_TEXT_DOMAIN ),
				'description' => __( 'Win 5 battles in a row', QBA_TEXT_DOMAIN ),
				'icon'        => '🔥',
				'points'      => get_option( 'qba_badge_points_win_streak', 25 ),
				'condition'   => array( $this, 'check_win_streak' ),
				'rarity'      => 'epic',
			),
			'quiz_master'      => array(
				'name'        => __( 'Quiz Master', QBA_TEXT_DOMAIN ),
				'description' => __( 'Complete 50 battles', QBA_TEXT_DOMAIN ),
				'icon'        => '🧠',
				'points'      => get_option( 'qba_badge_points_quiz_master', 75 ),
				'condition'   => array( $this, 'check_quiz_master' ),
				'rarity'      => 'epic',
			),
			'high_roller'      => array(
				'name'        => __( 'High Roller', QBA_TEXT_DOMAIN ),
				'description' => __( 'Reach 1500 ELO rating', QBA_TEXT_DOMAIN ),
				'icon'        => '⭐',
				'points'      => get_option( 'qba_badge_points_high_roller', 60 ),
				'condition'   => array( $this, 'check_high_roller' ),
				'rarity'      => 'legendary',
			),
			'social_butterfly' => array(
				'name'        => __( 'Social Butterfly', QBA_TEXT_DOMAIN ),
				'description' => __( 'Challenge 10 different friends', QBA_TEXT_DOMAIN ),
				'icon'        => '🦋',
				'points'      => get_option( 'qba_badge_points_social_butterfly', 35 ),
				'condition'   => array( $this, 'check_social_butterfly' ),
				'rarity'      => 'uncommon',
			),
		);
	}

	/**
	 * Check if user has earned a specific badge
	 *
	 * @since 1.0.0
	 * @param int    $user_id The user ID
	 * @param string $badge_id The badge ID
	 * @return bool
	 */
	public function has_badge( $user_id, $badge_id ) {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT COUNT(*) FROM {$wpdb->prefix}qba_user_badges
			WHERE user_id = %d AND badge_id = %s
		",
				$user_id,
				$badge_id
			)
		);

		return $count > 0;
	}

	/**
	 * Get user's earned badges
	 *
	 * @since 1.0.0
	 * @param int $user_id The user ID
	 * @return array
	 */
	public function get_user_badges( $user_id ) {
		global $wpdb;

		$earned_badges = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT badge_id, earned_at
			FROM {$wpdb->prefix}qba_user_badges
			WHERE user_id = %d
			ORDER BY earned_at DESC
		",
				$user_id
			),
			ARRAY_A
		);

		$badges_with_data = array();
		foreach ( $earned_badges as $badge ) {
			if ( isset( $this->badges[ $badge['badge_id'] ] ) ) {
				$badge_data              = $this->badges[ $badge['badge_id'] ];
				$badge_data['id']        = $badge['badge_id'];
				$badge_data['earned_at'] = $badge['earned_at'];
				$badges_with_data[]      = $badge_data;
			}
		}

		return $badges_with_data;
	}

	/**
	 * Check for newly earned badges after battle completion
	 *
	 * @since 1.0.0
	 * @param int   $user_id The user ID
	 * @param array $battle_data The battle data
	 * @return array Array of newly earned badges
	 */
	public function check_battle_achievements( $user_id, $battle_data ) {
		$new_badges = array();

		foreach ( $this->badges as $badge_id => $badge ) {
			if ( ! $this->has_badge( $user_id, $badge_id ) && call_user_func( $badge['condition'], $user_id, $battle_data ) ) {
				$this->award_badge( $user_id, $badge_id );
				$new_badges[] = $badge;
			}
		}

		return $new_badges;
	}

	/**
	 * Award a badge to a user
	 *
	 * @since 1.0.0
	 * @param int    $user_id The user ID
	 * @param string $badge_id The badge ID
	 * @return bool
	 */
	public function award_badge( $user_id, $badge_id ) {
		if ( ! isset( $this->badges[ $badge_id ] ) ) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'qba_user_badges',
			array(
				'user_id'   => $user_id,
				'badge_id'  => $badge_id,
				'earned_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s' )
		);

		if ( $result !== false ) {
			// Award points for the badge
			$points = $this->badges[ $badge_id ]['points'];
			qba_update_user_points( $user_id, $points );

			// Trigger badge earned action
			do_action( 'qba_badge_earned', $user_id, $badge_id, $this->badges[ $badge_id ] );

			return true;
		}

		return false;
	}

	/**
	 * Get all available badges
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_all_badges() {
		$badges = array();
		foreach ( $this->badges as $badge_id => $badge ) {
			$badge['id'] = $badge_id;
			$badges[]    = $badge;
		}
		return $badges;
	}

	/**
	 * Get badge progress for user
	 *
	 * @since 1.0.0
	 * @param int $user_id The user ID
	 * @return array
	 */
	public function get_badge_progress( $user_id ) {
		$user_stats = qba_get_user_stats( $user_id );
		$progress   = array();

		foreach ( $this->badges as $badge_id => $badge ) {
			if ( $this->has_badge( $user_id, $badge_id ) ) {
				$progress[ $badge_id ] = array(
					'earned'   => true,
					'progress' => 100,
					'badge'    => $badge,
				);
			} else {
				$progress_data         = $this->calculate_badge_progress( $badge_id, $user_stats );
				$progress[ $badge_id ] = array_merge( $progress_data, array( 'badge' => $badge ) );
			}
		}

		return $progress;
	}

	/**
	 * Calculate progress towards a badge
	 *
	 * @since 1.0.0
	 * @param string $badge_id The badge ID
	 * @param array  $user_stats The user stats
	 * @return array
	 */
	private function calculate_badge_progress( $badge_id, $user_stats ) {
		switch ( $badge_id ) {
			case 'first_win':
				return array(
					'earned'   => false,
					'progress' => $user_stats['battles_won'] >= 1 ? 100 : 0,
				);
			case 'ten_wins':
				return array(
					'earned'   => false,
					'progress' => min( 100, ( $user_stats['battles_won'] / 10 ) * 100 ),
				);
			case 'win_streak':
				return array(
					'earned'   => false,
					'progress' => min( 100, ( $user_stats['win_streak'] / 5 ) * 100 ),
				);
			case 'quiz_master':
				return array(
					'earned'   => false,
					'progress' => min( 100, ( $user_stats['total_battles'] / 50 ) * 100 ),
				);
			case 'high_roller':
				return array(
					'earned'   => false,
					'progress' => min( 100, ( $user_stats['elo_rating'] / 1500 ) * 100 ),
				);
			default:
				return array(
					'earned'   => false,
					'progress' => 0,
				);
		}
	}

	// Badge condition checkers

	/**
	 * Check first win condition
	 *
	 * @since 1.0.0
	 * @param int   $user_id The user ID
	 * @param array $battle_data The battle data
	 * @return bool
	 */
	public function check_first_win( $user_id, $battle_data ) {
		$user_stats = qba_get_user_stats( $user_id );
		return $user_stats['battles_won'] >= 1;
	}

	/**
	 * Check ten wins condition
	 *
	 * @since 1.0.0
	 * @param int   $user_id The user ID
	 * @param array $battle_data The battle data
	 * @return bool
	 */
	public function check_ten_wins( $user_id, $battle_data ) {
		$user_stats = qba_get_user_stats( $user_id );
		return $user_stats['battles_won'] >= 10;
	}

	/**
	 * Check speed demon condition
	 *
	 * @since 1.0.0
	 * @param int   $user_id The user ID
	 * @param array $battle_data The battle data
	 * @return bool
	 */
	public function check_speed_demon( $user_id, $battle_data ) {
		global $wpdb;

		// Check if user answered any question in under 5 seconds in this battle
		$fast_answer = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT COUNT(*) FROM {$wpdb->prefix}qba_battle_progress
			WHERE battle_id = %d AND user_id = %d AND time_taken < 5
		",
				$battle_data['id'],
				$user_id
			)
		);

		return $fast_answer > 0;
	}

	/**
	 * Check perfect score condition
	 *
	 * @since 1.0.0
	 * @param int   $user_id The user ID
	 * @param array $battle_data The battle data
	 * @return bool
	 */
	public function check_perfect_score( $user_id, $battle_data ) {
		global $wpdb;

		// Check if user got all answers correct in this battle
		$total_questions = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT COUNT(*) FROM {$wpdb->prefix}qba_battle_progress
			WHERE battle_id = %d AND user_id = %d
		",
				$battle_data['id'],
				$user_id
			)
		);

		$correct_answers = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT COUNT(*) FROM {$wpdb->prefix}qba_battle_progress
			WHERE battle_id = %d AND user_id = %d AND is_correct = 1
		",
				$battle_data['id'],
				$user_id
			)
		);

		return $total_questions > 0 && $total_questions === $correct_answers;
	}

	/**
	 * Check win streak condition
	 *
	 * @since 1.0.0
	 * @param int   $user_id The user ID
	 * @param array $battle_data The battle data
	 * @return bool
	 */
	public function check_win_streak( $user_id, $battle_data ) {
		$user_stats = qba_get_user_stats( $user_id );
		return $user_stats['win_streak'] >= 5;
	}

	/**
	 * Check quiz master condition
	 *
	 * @since 1.0.0
	 * @param int   $user_id The user ID
	 * @param array $battle_data The battle data
	 * @return bool
	 */
	public function check_quiz_master( $user_id, $battle_data ) {
		$user_stats = qba_get_user_stats( $user_id );
		return $user_stats['total_battles'] >= 50;
	}

	/**
	 * Check high roller condition
	 *
	 * @since 1.0.0
	 * @param int   $user_id The user ID
	 * @param array $battle_data The battle data
	 * @return bool
	 */
	public function check_high_roller( $user_id, $battle_data ) {
		$user_stats = qba_get_user_stats( $user_id );
		return $user_stats['elo_rating'] >= 1500;
	}

	/**
	 * Check social butterfly condition
	 *
	 * @since 1.0.0
	 * @param int   $user_id The user ID
	 * @param array $battle_data The battle data
	 * @return bool
	 */
	public function check_social_butterfly( $user_id, $battle_data ) {
		global $wpdb;

		// Count unique opponents challenged
		$unique_opponents = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT COUNT(DISTINCT opponent_id) FROM {$wpdb->prefix}qba_battles
			WHERE challenger_id = %d AND challenge_type = 'direct'
		",
				$user_id
			)
		);

		return $unique_opponents >= 10;
	}

	/**
	 * Render achievements display
	 *
	 * @since 1.0.0
	 * @param int    $user_id The user ID
	 * @param string $layout The layout (grid, list)
	 * @return string
	 */
	public function render_achievements( $user_id = null, $layout = 'grid' ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$user_badges    = $this->get_user_badges( $user_id );
		$badge_progress = $this->get_badge_progress( $user_id );

		ob_start();
		?>
		<div class="qba-achievements-container qba-layout-<?php echo esc_attr( $layout ); ?>">
			<div class="qba-achievements-header">
				<h3><?php esc_html_e( 'Achievements', QBA_TEXT_DOMAIN ); ?></h3>
				<div class="qba-achievements-stats">
					<span class="qba-earned-count">
						<?php
						printf(
							/* translators: %d: number of badges earned */
							esc_html( _n( '%d Badge Earned', '%d Badges Earned', count( $user_badges ), QBA_TEXT_DOMAIN ) ),
							count( $user_badges )
						);
						?>
					</span>
				</div>
			</div>

			<div class="qba-achievements-<?php echo esc_attr( $layout ); ?>">
				<?php foreach ( $badge_progress as $badge_id => $progress ) : ?>
				<div class="qba-achievement-item <?php echo $progress['earned'] ? 'qba-earned' : 'qba-locked'; ?> qba-rarity-<?php echo esc_attr( $progress['badge']['rarity'] ); ?>">
					<div class="qba-achievement-icon">
						<?php echo esc_html( $progress['badge']['icon'] ); ?>
					</div>
					<div class="qba-achievement-content">
						<h4 class="qba-achievement-name">
							<?php echo esc_html( $progress['badge']['name'] ); ?>
							<?php if ( $progress['earned'] ) : ?>
							<span class="qba-earned-indicator">✓</span>
							<?php endif; ?>
						</h4>
						<p class="qba-achievement-description">
							<?php echo esc_html( $progress['badge']['description'] ); ?>
						</p>
						<?php if ( ! $progress['earned'] ) : ?>
						<div class="qba-achievement-progress">
							<div class="qba-progress-bar">
								<div class="qba-progress-fill" style="width: <?php echo esc_attr( $progress['progress'] ); ?>%"></div>
							</div>
							<span class="qba-progress-text"><?php echo esc_html( $progress['progress'] ); ?>%</span>
						</div>
						<?php else : ?>
						<div class="qba-achievement-points">
							<?php
							printf(
								/* translators: %d: points awarded */
								esc_html__( '+%d points', QBA_TEXT_DOMAIN ),
								$progress['badge']['points']
							);
							?>
						</div>
						<?php endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render achievements shortcode
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function render_achievements_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'user_id' => get_current_user_id(),
				'layout'  => 'grid',
			),
			$atts
		);

		$user_id = intval( $atts['user_id'] );
		$layout  = sanitize_text_field( $atts['layout'] );

		// Validate layout
		$valid_layouts = array( 'grid', 'list', 'compact' );
		if ( ! in_array( $layout, $valid_layouts ) ) {
			$layout = 'grid';
		}

		if ( ! $user_id ) {
			return '<p>' . esc_html__( 'Please log in to view achievements.', QBA_TEXT_DOMAIN ) . '</p>';
		}

		return $this->render_achievements( $user_id, $layout );
	}
}
