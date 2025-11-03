<?php
/**
 * Notifications Class
 *
 * Handles all notification sending
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Notifications Class
 *
 * @since 1.0.0
 */
class QBA_Notifications {

	/**
	 * Initialize notifications
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Hook into battle events
		add_action( 'qba_battle_challenge_created', array( $this, 'send_challenge_notification' ), 10, 2 );
		add_action( 'qba_battle_completed', array( $this, 'send_battle_result_notification' ), 10, 3 );
		add_action( 'qba_badge_earned', array( $this, 'send_badge_notification' ), 10, 3 );
		add_action( 'qba_match_found', array( $this, 'send_match_found_notification' ), 10, 2 );
	}

	/**
	 * Send battle challenge notification
	 *
	 * @since 1.0.0
	 * @param int   $battle_id   Battle ID
	 * @param array $battle_data Battle data
	 */
	public function send_challenge_notification( $battle_id, $battle_data ) {
		$opponent_id   = $battle_data['opponent_id'];
		$challenger_id = $battle_data['challenger_id'];
		$quiz_id       = $battle_data['quiz_id'];

		$challenger = get_userdata( $challenger_id );
		$opponent   = get_userdata( $opponent_id );
		$quiz       = get_post( $quiz_id );

		if ( ! $challenger || ! $opponent || ! $quiz ) {
			return;
		}

		$subject = sprintf(
			__( 'Battle Challenge from %s', 'quiz-battle-arena' ),
			$challenger->display_name
		);

		$message = sprintf(
			__( "Hello %1\$s,\n\n%2\$s has challenged you to a battle on the quiz '%3\$s'!\n\nYou have 5 minutes to accept the challenge.\n\nAccept Challenge: %4\$s\n\nBest regards,\nQuiz Battle Arena", 'quiz-battle-arena' ),
			$opponent->display_name,
			$challenger->display_name,
			$quiz->post_title,
			$this->get_battle_accept_url( $battle_id )
		);

		$this->send_email( $opponent->user_email, $subject, $message );

		// Log notification
		$this->log_notification( $opponent_id, 'battle_challenge', $battle_id );
	}

	/**
	 * Send battle result notification
	 *
	 * @since 1.0.0
	 * @param int   $battle_id   Battle ID
	 * @param array $results     Battle results
	 * @param array $battle_data Battle data
	 */
	public function send_battle_result_notification( $battle_id, $results, $battle_data ) {
		$winner_id = $results['winner_id'];
		$loser_id  = $results['loser_id'];
		$quiz_id   = $battle_data['quiz_id'];

		$winner = get_userdata( $winner_id );
		$loser  = get_userdata( $loser_id );
		$quiz   = get_post( $quiz_id );

		if ( ! $winner || ! $loser || ! $quiz ) {
			return;
		}

		// Notify the loser
		$subject = sprintf(
			__( 'Battle Result: Defeated by %s', 'quiz-battle-arena' ),
			$winner->display_name
		);

		$message = sprintf(
			__( "Hello %1\$s,\n\nUnfortunately, you were defeated by %2\$s in the battle on '%3\$s'.\n\nFinal Score: %4\$s (%5\$d) vs %6\$s (%7\$d)\n\nKeep practicing and challenge again!\n\nView Results: %8\$s\n\nBest regards,\nQuiz Battle Arena", 'quiz-battle-arena' ),
			$loser->display_name,
			$winner->display_name,
			$quiz->post_title,
			$loser->display_name,
			$results['loser_score'],
			$winner->display_name,
			$results['winner_score'],
			$this->get_battle_results_url( $battle_id )
		);

		$this->send_email( $loser->user_email, $subject, $message );

		// Log notification
		$this->log_notification( $loser_id, 'battle_result', $battle_id );

		// Notify the winner (optional - could be a setting)
		if ( get_option( 'qba_notify_winners', '0' ) ) {
			$winner_subject = __( 'Battle Victory!', 'quiz-battle-arena' );

			$winner_message = sprintf(
				__( "Congratulations %1\$s!\n\nYou defeated %2\$s in the battle on '%3\$s'!\n\nFinal Score: %4\$s (%5\$d) vs %6\$s (%7\$d)\n\nView Results: %8\$s\n\nBest regards,\nQuiz Battle Arena", 'quiz-battle-arena' ),
				$winner->display_name,
				$loser->display_name,
				$quiz->post_title,
				$winner->display_name,
				$results['winner_score'],
				$loser->display_name,
				$results['loser_score'],
				$this->get_battle_results_url( $battle_id )
			);

			$this->send_email( $winner->user_email, $winner_subject, $winner_message );
			$this->log_notification( $winner_id, 'battle_victory', $battle_id );
		}
	}

	/**
	 * Send badge earned notification
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID
	 * @param string $badge_id Badge ID
	 * @param array  $badge   Badge data
	 */
	public function send_badge_notification( $user_id, $badge_id, $badge ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$subject = __( 'New Badge Earned!', 'quiz-battle-arena' );

		$message = sprintf(
			__( "Congratulations %1\$s!\n\nYou have earned the '%2\$s' badge!\n\n%3\$s\n\nKeep up the great work!\n\nBest regards,\nQuiz Battle Arena", 'quiz-battle-arena' ),
			$user->display_name,
			$badge['name'],
			$badge['description']
		);

		$this->send_email( $user->user_email, $subject, $message );

		// Log notification
		$this->log_notification( $user_id, 'badge_earned', $user_id, $badge_id );
	}

	/**
	 * Send match found notification
	 *
	 * @since 1.0.0
	 * @param int   $battle_id Battle ID
	 * @param array $match     Match data
	 */
	public function send_match_found_notification( $battle_id, $match ) {
		foreach ( $match as $player ) {
			$user = get_userdata( $player['user_id'] );
			if ( ! $user ) {
				continue;
			}

			$subject = __( 'Match Found!', 'quiz-battle-arena' );

			$message = sprintf(
				__( "Hello %s,\n\nA battle match has been found for you!\n\nThe battle will begin shortly.\n\nGood luck!\n\nBest regards,\nQuiz Battle Arena", 'quiz-battle-arena' ),
				$user->display_name
			);

			$this->send_email( $user->user_email, $subject, $message );

			// Log notification
			$this->log_notification( $player['user_id'], 'match_found', $battle_id );
		}
	}

	/**
	 * Send email notification
	 *
	 * @since 1.0.0
	 * @param string $to      Recipient email
	 * @param string $subject Email subject
	 * @param string $message Email message
	 * @return bool Success
	 */
	private function send_email( $to, $subject, $message ) {
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		return wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Log notification to database
	 *
	 * @since 1.0.0
	 * @param int    $user_id         User ID
	 * @param string $notification_type Type of notification
	 * @param int    $related_id      Related item ID
	 * @param string $badge_id        Badge ID (optional)
	 */
	private function log_notification( $user_id, $notification_type, $related_id, $badge_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qba_notifications';

		$data = array(
			'user_id'           => $user_id,
			'notification_type' => $notification_type,
			'content'           => $this->get_notification_content( $notification_type, $related_id, $badge_id ),
			'related_id'        => $related_id,
			'created_at'        => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $data );
	}

	/**
	 * Get notification content
	 *
	 * @since 1.0.0
	 * @param string $type      Notification type
	 * @param int    $related_id Related ID
	 * @param string $badge_id  Badge ID
	 * @return string Content
	 */
	private function get_notification_content( $type, $related_id, $badge_id = null ) {
		switch ( $type ) {
			case 'battle_challenge':
				return __( 'You have been challenged to a battle!', 'quiz-battle-arena' );
			case 'battle_result':
				return __( 'Battle completed - you were defeated.', 'quiz-battle-arena' );
			case 'battle_victory':
				return __( 'Battle victory!', 'quiz-battle-arena' );
			case 'badge_earned':
				return sprintf( __( 'Badge earned: %s', 'quiz-battle-arena' ), $badge_id );
			case 'match_found':
				return __( 'Match found in matchmaking queue!', 'quiz-battle-arena' );
			default:
				return __( 'Quiz Battle Arena notification', 'quiz-battle-arena' );
		}
	}

	/**
	 * Get battle accept URL
	 *
	 * @since 1.0.0
	 * @param int $battle_id Battle ID
	 * @return string URL
	 */
	private function get_battle_accept_url( $battle_id ) {
		return add_query_arg(
			array(
				'qba_action' => 'accept_battle',
				'battle_id'  => $battle_id,
			),
			home_url()
		);
	}

	/**
	 * Get battle results URL
	 *
	 * @since 1.0.0
	 * @param int $battle_id Battle ID
	 * @return string URL
	 */
	private function get_battle_results_url( $battle_id ) {
		return add_query_arg(
			array(
				'qba_action' => 'view_battle_results',
				'battle_id'  => $battle_id,
			),
			home_url()
		);
	}
}
