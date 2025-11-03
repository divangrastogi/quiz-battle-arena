<?php
/**
 * BuddyBoss Integration Class
 *
 * Handles integration with BuddyBoss Platform
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_BuddyBoss_Integration Class
 *
 * @since 1.0.0
 */
class QBA_BuddyBoss_Integration {

	/**
	 * Initialize BuddyBoss integration
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Only initialize if BuddyBoss is active
		if ( ! function_exists( 'bp_is_active' ) ) {
			return;
		}

		// Register activity types
		add_action( 'bp_register_activity_actions', array( $this, 'register_activity_types' ) );

		// Add profile tab
		add_action( 'bp_setup_nav', array( $this, 'add_profile_battle_tab' ), 100 );

		// Hook into battle events
		add_action( 'qba_battle_challenge_created', array( $this, 'post_challenge_activity' ), 10, 2 );
		add_action( 'qba_battle_completed', array( $this, 'post_battle_result_activity' ), 10, 3 );
		add_action( 'qba_badge_earned', array( $this, 'post_badge_activity' ), 10, 3 );

		// Notifications
		add_action( 'qba_battle_challenge_created', array( $this, 'send_challenge_notification' ), 10, 2 );
		add_action( 'qba_battle_completed', array( $this, 'send_battle_result_notification' ), 10, 3 );
	}

	/**
	 * Register custom activity types
	 *
	 * @since 1.0.0
	 */
	public function register_activity_types() {
		// Battle challenge activity
		bp_activity_set_action(
			'quiz_battle',
			'battle_challenge',
			__( 'Battle Challenge', 'quiz-battle-arena' ),
			array( $this, 'format_challenge_activity' ),
			__( 'Battle Challenges', 'quiz-battle-arena' )
		);

		// Battle completed activity
		bp_activity_set_action(
			'quiz_battle',
			'battle_completed',
			__( 'Battle Completed', 'quiz-battle-arena' ),
			array( $this, 'format_battle_activity' ),
			__( 'Battle Results', 'quiz-battle-arena' )
		);

		// Badge earned activity
		bp_activity_set_action(
			'quiz_battle',
			'badge_earned',
			__( 'Badge Earned', 'quiz-battle-arena' ),
			array( $this, 'format_badge_activity' ),
			__( 'Badges Earned', 'quiz-battle-arena' )
		);
	}

	/**
	 * Format challenge activity
	 *
	 * @since 1.0.0
	 * @param string $action   Action text
	 * @param object $activity Activity object
	 * @return string Formatted action
	 */
	public function format_challenge_activity( $action, $activity ) {
		return $action;
	}

	/**
	 * Format battle activity
	 *
	 * @since 1.0.0
	 * @param string $action   Action text
	 * @param object $activity Activity object
	 * @return string Formatted action
	 */
	public function format_battle_activity( $action, $activity ) {
		return $action;
	}

	/**
	 * Format badge activity
	 *
	 * @since 1.0.0
	 * @param string $action   Action text
	 * @param object $activity Activity object
	 * @return string Formatted action
	 */
	public function format_badge_activity( $action, $activity ) {
		return $action;
	}

	/**
	 * Post battle challenge to activity stream
	 *
	 * @since 1.0.0
	 * @param int   $battle_id Battle ID
	 * @param array $battle_data Battle data
	 */
	public function post_challenge_activity( $battle_id, $battle_data ) {
		if ( ! function_exists( 'bp_activity_add' ) ) {
			return;
		}

		$quiz = get_post( $battle_data['quiz_id'] );
		if ( ! $quiz ) {
			return;
		}

		$challenger_name = bp_core_get_userlink( $battle_data['challenger_id'] );
		$opponent_name   = bp_core_get_userlink( $battle_data['opponent_id'] );
		$quiz_link       = '<a href="' . get_permalink( $quiz ) . '">' . esc_html( $quiz->post_title ) . '</a>';

		$action = sprintf(
			__( '%1$s challenged %2$s to a battle on %3$s', 'quiz-battle-arena' ),
			$challenger_name,
			$opponent_name,
			$quiz_link
		);

		bp_activity_add(
			array(
				'user_id'       => $battle_data['challenger_id'],
				'action'        => $action,
				'content'       => __( 'Let the battle begin!', 'quiz-battle-arena' ),
				'component'     => 'quiz_battle',
				'type'          => 'battle_challenge',
				'item_id'       => $battle_id,
				'hide_sitewide' => false,
			)
		);
	}

	/**
	 * Post battle result to activity stream
	 *
	 * @since 1.0.0
	 * @param int   $battle_id Battle ID
	 * @param array $results   Battle results
	 * @param array $battle_data Battle data
	 */
	public function post_battle_result_activity( $battle_id, $results, $battle_data ) {
		if ( ! function_exists( 'bp_activity_add' ) ) {
			return;
		}

		$quiz = get_post( $battle_data['quiz_id'] );
		if ( ! $quiz ) {
			return;
		}

		$winner_id    = $results['winner_id'];
		$loser_id     = $results['loser_id'];
		$winner_score = $results['winner_score'];
		$loser_score  = $results['loser_score'];

		$winner_name = bp_core_get_userlink( $winner_id );
		$loser_name  = bp_core_get_userlink( $loser_id );
		$quiz_link   = '<a href="' . get_permalink( $quiz ) . '">' . esc_html( $quiz->post_title ) . '</a>';

		$action = sprintf(
			__( '%1$s defeated %2$s in %3$s (%4$d-%5$d)', 'quiz-battle-arena' ),
			$winner_name,
			$loser_name,
			$quiz_link,
			$winner_score,
			$loser_score
		);

		$content = qba_generate_battle_summary( $battle_id );

		bp_activity_add(
			array(
				'user_id'       => $winner_id,
				'action'        => $action,
				'content'       => $content,
				'component'     => 'quiz_battle',
				'type'          => 'battle_completed',
				'item_id'       => $battle_id,
				'hide_sitewide' => false,
			)
		);
	}

	/**
	 * Post badge earned to activity stream
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID
	 * @param string $badge_id Badge ID
	 * @param array  $badge   Badge data
	 */
	public function post_badge_activity( $user_id, $badge_id, $badge ) {
		if ( ! function_exists( 'bp_activity_add' ) ) {
			return;
		}

		$user_name = bp_core_get_userlink( $user_id );

		$action = sprintf(
			__( '%1$s earned the "%2$s" badge!', 'quiz-battle-arena' ),
			$user_name,
			esc_html( $badge['name'] )
		);

		bp_activity_add(
			array(
				'user_id'       => $user_id,
				'action'        => $action,
				'content'       => esc_html( $badge['description'] ),
				'component'     => 'quiz_battle',
				'type'          => 'badge_earned',
				'item_id'       => $user_id,
				'hide_sitewide' => false,
			)
		);
	}

	/**
	 * Send BuddyBoss notification for battle challenge
	 *
	 * @since 1.0.0
	 * @param int   $battle_id Battle ID
	 * @param array $battle_data Battle data
	 */
	public function send_challenge_notification( $battle_id, $battle_data ) {
		if ( ! function_exists( 'bp_notifications_add_notification' ) ) {
			return;
		}

		bp_notifications_add_notification(
			array(
				'user_id'           => $battle_data['opponent_id'],
				'item_id'           => $battle_id,
				'secondary_item_id' => $battle_data['challenger_id'],
				'component_name'    => 'quiz_battle',
				'component_action'  => 'battle_challenge',
				'date_notified'     => current_time( 'mysql' ),
				'is_new'            => 1,
				'allow_duplicate'   => false,
			)
		);
	}

	/**
	 * Send BuddyBoss notification for battle result
	 *
	 * @since 1.0.0
	 * @param int   $battle_id Battle ID
	 * @param array $results   Battle results
	 * @param array $battle_data Battle data
	 */
	public function send_battle_result_notification( $battle_id, $results, $battle_data ) {
		if ( ! function_exists( 'bp_notifications_add_notification' ) ) {
			return;
		}

		// Notify the loser
		$loser_id = $results['loser_id'];
		bp_notifications_add_notification(
			array(
				'user_id'           => $loser_id,
				'item_id'           => $battle_id,
				'secondary_item_id' => $results['winner_id'],
				'component_name'    => 'quiz_battle',
				'component_action'  => 'battle_completed',
				'date_notified'     => current_time( 'mysql' ),
				'is_new'            => 1,
				'allow_duplicate'   => false,
			)
		);
	}

	/**
	 * Add Battle Stats tab to user profile
	 *
	 * @since 1.0.0
	 */
	public function add_profile_battle_tab() {
		global $bp;

		bp_core_new_nav_item(
			array(
				'name'                => __( 'Battle Stats', 'quiz-battle-arena' ),
				'slug'                => 'battle-stats',
				'screen_function'     => array( $this, 'render_battle_stats_screen' ),
				'position'            => 80,
				'default_subnav_slug' => 'overview',
			)
		);

		// Overview subnav
		bp_core_new_subnav_item(
			array(
				'name'            => __( 'Overview', 'quiz-battle-arena' ),
				'slug'            => 'overview',
				'parent_url'      => bp_displayed_user_domain() . 'battle-stats/',
				'parent_slug'     => 'battle-stats',
				'screen_function' => array( $this, 'render_overview_tab' ),
				'position'        => 10,
			)
		);

		// Battle History subnav
		bp_core_new_subnav_item(
			array(
				'name'            => __( 'Battle History', 'quiz-battle-arena' ),
				'slug'            => 'history',
				'parent_url'      => bp_displayed_user_domain() . 'battle-stats/',
				'parent_slug'     => 'battle-stats',
				'screen_function' => array( $this, 'render_history_tab' ),
				'position'        => 20,
			)
		);

		// Badges subnav
		bp_core_new_subnav_item(
			array(
				'name'            => __( 'Badges', 'quiz-battle-arena' ),
				'slug'            => 'badges',
				'parent_url'      => bp_displayed_user_domain() . 'battle-stats/',
				'parent_slug'     => 'battle-stats',
				'screen_function' => array( $this, 'render_badges_tab' ),
				'position'        => 30,
			)
		);
	}

	/**
	 * Render battle stats screen
	 *
	 * @since 1.0.0
	 */
	public function render_battle_stats_screen() {
		add_action( 'bp_template_content', array( $this, 'render_battle_stats_content' ) );
		bp_core_load_template( 'buddypress/members/single/plugins' );
	}

	/**
	 * Render overview tab
	 *
	 * @since 1.0.0
	 */
	public function render_overview_tab() {
		add_action( 'bp_template_content', array( $this, 'render_overview_content' ) );
		bp_core_load_template( 'buddypress/members/single/plugins' );
	}

	/**
	 * Render history tab
	 *
	 * @since 1.0.0
	 */
	public function render_history_tab() {
		add_action( 'bp_template_content', array( $this, 'render_history_content' ) );
		bp_core_load_template( 'buddypress/members/single/plugins' );
	}

	/**
	 * Render badges tab
	 *
	 * @since 1.0.0
	 */
	public function render_badges_tab() {
		add_action( 'bp_template_content', array( $this, 'render_badges_content' ) );
		bp_core_load_template( 'buddypress/members/single/plugins' );
	}

	/**
	 * Render battle stats content
	 *
	 * @since 1.0.0
	 */
	public function render_battle_stats_content() {
		$user_id = bp_displayed_user_id();
		include QBA_PLUGIN_DIR . 'public/partials/qba-user-profile.php';
	}

	/**
	 * Render overview content
	 *
	 * @since 1.0.0
	 */
	public function render_overview_content() {
		$user_id = bp_displayed_user_id();
		$stats   = qba_get_user_stats( $user_id );

		echo '<div class="qba-profile-overview">';
		echo '<h3>' . esc_html__( 'Battle Statistics', 'quiz-battle-arena' ) . '</h3>';

		echo '<div class="qba-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';

		echo '<div class="qba-stat-card" style="padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">';
		echo '<div class="qba-stat-value" style="font-size: 2em; font-weight: bold; color: #6366f1;">' . esc_html( $stats['total_battles'] ) . '</div>';
		echo '<div class="qba-stat-label" style="color: #6b7280;">' . esc_html__( 'Total Battles', 'quiz-battle-arena' ) . '</div>';
		echo '</div>';

		echo '<div class="qba-stat-card" style="padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">';
		echo '<div class="qba-stat-value" style="font-size: 2em; font-weight: bold; color: #10b981;">' . esc_html( $stats['battles_won'] ) . '</div>';
		echo '<div class="qba-stat-label" style="color: #6b7280;">' . esc_html__( 'Battles Won', 'quiz-battle-arena' ) . '</div>';
		echo '</div>';

		echo '<div class="qba-stat-card" style="padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">';
		echo '<div class="qba-stat-value" style="font-size: 2em; font-weight: bold; color: #6366f1;">' . esc_html( $stats['elo_rating'] ) . '</div>';
		echo '<div class="qba-stat-label" style="color: #6b7280;">' . esc_html__( 'ELO Rating', 'quiz-battle-arena' ) . '</div>';
		echo '</div>';

		echo '<div class="qba-stat-card" style="padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">';
		echo '<div class="qba-stat-value" style="font-size: 2em; font-weight: bold; color: #f59e0b;">' . esc_html( $stats['win_streak'] ) . '</div>';
		echo '<div class="qba-stat-label" style="color: #6b7280;">' . esc_html__( 'Win Streak', 'quiz-battle-arena' ) . '</div>';
		echo '</div>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render history content
	 *
	 * @since 1.0.0
	 */
	public function render_history_content() {
		$user_id = bp_displayed_user_id();

		echo '<div class="qba-profile-history">';
		echo '<h3>' . esc_html__( 'Recent Battles', 'quiz-battle-arena' ) . '</h3>';

		// Get recent battles for user
		global $wpdb;
		$table   = $wpdb->prefix . 'qba_battles';
		$battles = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
			WHERE (challenger_id = %d OR opponent_id = %d) 
			AND status = 'completed' 
			ORDER BY completed_at DESC 
			LIMIT 10",
				$user_id,
				$user_id
			),
			ARRAY_A
		);

		if ( empty( $battles ) ) {
			echo '<p>' . esc_html__( 'No battles completed yet.', 'quiz-battle-arena' ) . '</p>';
		} else {
			echo '<div class="qba-battles-list">';
			foreach ( $battles as $battle ) {
				$quiz         = get_post( $battle['quiz_id'] );
				$is_winner    = ( $battle['winner_id'] == $user_id );
				$result_text  = $is_winner ? __( 'Won', 'quiz-battle-arena' ) : __( 'Lost', 'quiz-battle-arena' );
				$result_class = $is_winner ? 'qba-result-win' : 'qba-result-loss';

				echo '<div class="qba-battle-item" style="padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 10px;">';
				echo '<div style="display: flex; justify-content: space-between; align-items: center;">';
				echo '<div>';
				echo '<strong>' . esc_html( $quiz->post_title ) . '</strong><br>';
				echo '<small style="color: #6b7280;">' . esc_html( human_time_diff( strtotime( $battle['completed_at'] ) ) ) . ' ago</small>';
				echo '</div>';
				echo '<span class="qba-result ' . $result_class . '" style="padding: 5px 10px; border-radius: 4px; font-weight: bold; ' . ( $is_winner ? 'background: #dcfce7; color: #166534;' : 'background: #fef2f2; color: #991b1b;' ) . '">' . $result_text . '</span>';
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render badges content
	 *
	 * @since 1.0.0
	 */
	public function render_badges_content() {
		$user_id = bp_displayed_user_id();
		$badges  = qba_get_user_badges( $user_id );

		echo '<div class="qba-profile-badges">';
		echo '<h3>' . esc_html__( 'Earned Badges', 'quiz-battle-arena' ) . '</h3>';

		if ( empty( $badges ) ) {
			echo '<p>' . esc_html__( 'No badges earned yet. Start battling to earn your first badge!', 'quiz-battle-arena' ) . '</p>';
		} else {
			echo '<div class="qba-badges-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px;">';

			foreach ( $badges as $badge ) {
				$badge_data = $badge['details'];
				if ( ! $badge_data ) {
					continue;
				}

				echo '<div class="qba-badge-card" style="padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">';
				echo '<div class="qba-badge-icon" style="font-size: 3em; margin-bottom: 10px;">🏆</div>';
				echo '<div class="qba-badge-name" style="font-weight: bold; margin-bottom: 5px;">' . esc_html( $badge_data['name'] ) . '</div>';
				echo '<div class="qba-badge-description" style="font-size: 0.9em; color: #6b7280;">' . esc_html( $badge_data['description'] ) . '</div>';
				echo '<div class="qba-badge-earned" style="font-size: 0.8em; color: #9ca3af; margin-top: 10px;">Earned ' . esc_html( human_time_diff( strtotime( $badge['earned_at'] ) ) ) . ' ago</div>';
				echo '</div>';
			}

			echo '</div>';
		}

		echo '</div>';
	}
}
