<?php
/**
 * Plugin Deactivator Class
 *
 * Fired during plugin deactivation
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Deactivator Class
 *
 * This class defines all code necessary to run during the plugin's deactivation
 *
 * @since 1.0.0
 */
class QBA_Deactivator {

	/**
	 * Plugin deactivation tasks
	 *
	 * Clean up temporary data, cancel active battles, and perform cleanup
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Cancel all active battles
		self::cancel_active_battles();

		// Clear matchmaking queue
		self::clear_matchmaking_queue();

		// Clear scheduled events
		self::clear_scheduled_events();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Log deactivation
		error_log( 'Quiz Battle Arena plugin deactivated successfully' );
	}

	/**
	 * Cancel all active battles
	 *
	 * @since 1.0.0
	 */
	private static function cancel_active_battles() {
		global $wpdb;

		$table_battles = $wpdb->prefix . 'qba_battles';

		// Update active battles to cancelled
		$wpdb->update(
			$table_battles,
			array(
				'status'       => 'cancelled',
				'completed_at' => current_time( 'mysql' ),
			),
			array(
				'status' => array( 'pending', 'active' ),
			),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Clear matchmaking queue
	 *
	 * @since 1.0.0
	 */
	private static function clear_matchmaking_queue() {
		global $wpdb;

		$table_queue = $wpdb->prefix . 'qba_matchmaking_queue';

		// Mark all waiting entries as cancelled
		$wpdb->update(
			$table_queue,
			array( 'status' => 'cancelled' ),
			array( 'status' => 'waiting' ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Clear scheduled events
	 *
	 * @since 1.0.0
	 */
	private static function clear_scheduled_events() {
		// Clear any scheduled cron jobs
		wp_clear_scheduled_hook( 'qba_process_matchmaking_queue' );
		wp_clear_scheduled_hook( 'qba_cleanup_expired_battles' );
		wp_clear_scheduled_hook( 'qba_generate_leaderboard_snapshots' );
	}
}
