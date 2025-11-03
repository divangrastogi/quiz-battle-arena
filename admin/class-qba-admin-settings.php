<?php
/**
 * Admin Settings Class
 *
 * Handles plugin settings and options
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QBA_Admin_Settings Class
 *
 * Defines settings for the plugin
 *
 * @since 1.0.0
 */
class QBA_Admin_Settings {

	/**
	 * Option group name
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $option_group = 'qba_settings_group';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor can be empty
	}

	/**
	 * Register plugin settings
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting(
			$this->option_group,
			'qba_enable_battles',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'qba_battle_timeout',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 900,
			)
		);

		register_setting(
			$this->option_group,
			'qba_challenge_expiry',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 300,
			)
		);

		register_setting(
			$this->option_group,
			'qba_max_questions',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 10,
			)
		);

		register_setting(
			$this->option_group,
			'qba_speed_bonus_max',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 5,
			)
		);

		register_setting(
			$this->option_group,
			'qba_elo_k_factor',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 32,
			)
		);

		register_setting(
			$this->option_group,
			'qba_queue_timeout',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 300,
			)
		);

		register_setting(
			$this->option_group,
			'qba_enable_leaderboard',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'qba_leaderboard_cache_timeout',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 300,
			)
		);

		register_setting(
			$this->option_group,
			'qba_enable_achievements',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'qba_enable_buddyboss_integration',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			$this->option_group,
			'qba_battle_points_base',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 10,
			)
		);

		register_setting(
			$this->option_group,
			'qba_battle_points_speed',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 5,
			)
		);

		// Badge points settings
		register_setting(
			$this->option_group,
			'qba_badge_points_first_win',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 10,
			)
		);

		register_setting(
			$this->option_group,
			'qba_badge_points_ten_wins',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 50,
			)
		);

		register_setting(
			$this->option_group,
			'qba_badge_points_speed_demon',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 30,
			)
		);

		register_setting(
			$this->option_group,
			'qba_badge_points_perfect_score',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 40,
			)
		);

		register_setting(
			$this->option_group,
			'qba_badge_points_win_streak',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 25,
			)
		);

		// Add settings sections
		add_settings_section(
			'qba_general_settings',
			__( 'General Settings', QBA_TEXT_DOMAIN ),
			array( $this, 'general_settings_section_callback' ),
			'qba_general_settings'
		);

		add_settings_section(
			'qba_battle_settings',
			__( 'Battle Settings', QBA_TEXT_DOMAIN ),
			array( $this, 'battle_settings_section_callback' ),
			'qba_battle_settings'
		);

		add_settings_section(
			'qba_scoring_settings',
			__( 'Scoring & Points', QBA_TEXT_DOMAIN ),
			array( $this, 'scoring_settings_section_callback' ),
			'qba_scoring_settings'
		);

		add_settings_section(
			'qba_integration_settings',
			__( 'Integration Settings', QBA_TEXT_DOMAIN ),
			array( $this, 'integration_settings_section_callback' ),
			'qba_integration_settings'
		);

		// Add settings fields
		$this->add_general_settings_fields();
		$this->add_battle_settings_fields();
		$this->add_scoring_settings_fields();
		$this->add_integration_settings_fields();
	}

	/**
	 * Sanitize checkbox values
	 *
	 * @since 1.0.0
	 * @param mixed $value The value to sanitize
	 * @return bool
	 */
	public function sanitize_checkbox( $value ) {
		return (bool) $value;
	}

	/**
	 * General settings section callback
	 *
	 * @since 1.0.0
	 */
	public function general_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure general plugin settings.', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Battle settings section callback
	 *
	 * @since 1.0.0
	 */
	public function battle_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure battle mechanics and timing.', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Scoring settings section callback
	 *
	 * @since 1.0.0
	 */
	public function scoring_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure scoring system and point values.', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Integration settings section callback
	 *
	 * @since 1.0.0
	 */
	public function integration_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure third-party integrations.', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Add general settings fields
	 *
	 * @since 1.0.0
	 */
	private function add_general_settings_fields() {
		add_settings_field(
			'qba_enable_battles',
			__( 'Enable Battles', QBA_TEXT_DOMAIN ),
			array( $this, 'enable_battles_callback' ),
			'qba_general_settings',
			'qba_general_settings'
		);

		add_settings_field(
			'qba_enable_leaderboard',
			__( 'Enable Leaderboard', QBA_TEXT_DOMAIN ),
			array( $this, 'enable_leaderboard_callback' ),
			'qba_general_settings',
			'qba_general_settings'
		);

		add_settings_field(
			'qba_enable_achievements',
			__( 'Enable Achievements', QBA_TEXT_DOMAIN ),
			array( $this, 'enable_achievements_callback' ),
			'qba_general_settings',
			'qba_general_settings'
		);
	}

	/**
	 * Add battle settings fields
	 *
	 * @since 1.0.0
	 */
	private function add_battle_settings_fields() {
		add_settings_field(
			'qba_battle_timeout',
			__( 'Battle Timeout (seconds)', QBA_TEXT_DOMAIN ),
			array( $this, 'battle_timeout_callback' ),
			'qba_battle_settings',
			'qba_battle_settings'
		);

		add_settings_field(
			'qba_challenge_expiry',
			__( 'Challenge Expiry (seconds)', QBA_TEXT_DOMAIN ),
			array( $this, 'challenge_expiry_callback' ),
			'qba_battle_settings',
			'qba_battle_settings'
		);

		add_settings_field(
			'qba_max_questions',
			__( 'Max Questions per Battle', QBA_TEXT_DOMAIN ),
			array( $this, 'max_questions_callback' ),
			'qba_battle_settings',
			'qba_battle_settings'
		);

		add_settings_field(
			'qba_queue_timeout',
			__( 'Queue Timeout (seconds)', QBA_TEXT_DOMAIN ),
			array( $this, 'queue_timeout_callback' ),
			'qba_battle_settings',
			'qba_battle_settings'
		);
	}

	/**
	 * Add scoring settings fields
	 *
	 * @since 1.0.0
	 */
	private function add_scoring_settings_fields() {
		add_settings_field(
			'qba_battle_points_base',
			__( 'Base Battle Points', QBA_TEXT_DOMAIN ),
			array( $this, 'battle_points_base_callback' ),
			'qba_scoring_settings',
			'qba_scoring_settings'
		);

		add_settings_field(
			'qba_battle_points_speed',
			__( 'Speed Bonus Points', QBA_TEXT_DOMAIN ),
			array( $this, 'battle_points_speed_callback' ),
			'qba_scoring_settings',
			'qba_scoring_settings'
		);

		add_settings_field(
			'qba_speed_bonus_max',
			__( 'Max Speed Bonus', QBA_TEXT_DOMAIN ),
			array( $this, 'speed_bonus_max_callback' ),
			'qba_scoring_settings',
			'qba_scoring_settings'
		);

		add_settings_field(
			'qba_elo_k_factor',
			__( 'ELO K-Factor', QBA_TEXT_DOMAIN ),
			array( $this, 'elo_k_factor_callback' ),
			'qba_scoring_settings',
			'qba_scoring_settings'
		);
	}

	/**
	 * Add integration settings fields
	 *
	 * @since 1.0.0
	 */
	private function add_integration_settings_fields() {
		add_settings_field(
			'qba_enable_buddyboss_integration',
			__( 'Enable BuddyBoss Integration', QBA_TEXT_DOMAIN ),
			array( $this, 'enable_buddyboss_callback' ),
			'qba_integration_settings',
			'qba_integration_settings'
		);

		add_settings_field(
			'qba_leaderboard_cache_timeout',
			__( 'Leaderboard Cache Timeout (seconds)', QBA_TEXT_DOMAIN ),
			array( $this, 'leaderboard_cache_timeout_callback' ),
			'qba_integration_settings',
			'qba_integration_settings'
		);
	}

	/**
	 * Enable battles field callback
	 *
	 * @since 1.0.0
	 */
	public function enable_battles_callback() {
		$value = get_option( 'qba_enable_battles', true );
		echo '<input type="checkbox" name="qba_enable_battles" value="1" ' . checked( 1, $value, false ) . ' />';
		echo '<label for="qba_enable_battles">' . esc_html__( 'Enable quiz battles functionality', QBA_TEXT_DOMAIN ) . '</label>';
	}

	/**
	 * Enable leaderboard field callback
	 *
	 * @since 1.0.0
	 */
	public function enable_leaderboard_callback() {
		$value = get_option( 'qba_enable_leaderboard', true );
		echo '<input type="checkbox" name="qba_enable_leaderboard" value="1" ' . checked( 1, $value, false ) . ' />';
		echo '<label for="qba_enable_leaderboard">' . esc_html__( 'Enable leaderboard functionality', QBA_TEXT_DOMAIN ) . '</label>';
	}

	/**
	 * Enable achievements field callback
	 *
	 * @since 1.0.0
	 */
	public function enable_achievements_callback() {
		$value = get_option( 'qba_enable_achievements', true );
		echo '<input type="checkbox" name="qba_enable_achievements" value="1" ' . checked( 1, $value, false ) . ' />';
		echo '<label for="qba_enable_achievements">' . esc_html__( 'Enable achievements and badges', QBA_TEXT_DOMAIN ) . '</label>';
	}

	/**
	 * Battle timeout field callback
	 *
	 * @since 1.0.0
	 */
	public function battle_timeout_callback() {
		$value = get_option( 'qba_battle_timeout', 900 );
		echo '<input type="number" name="qba_battle_timeout" value="' . esc_attr( $value ) . '" min="60" max="3600" />';
		echo '<p class="description">' . esc_html__( 'Maximum time allowed for a battle (in seconds)', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Challenge expiry field callback
	 *
	 * @since 1.0.0
	 */
	public function challenge_expiry_callback() {
		$value = get_option( 'qba_challenge_expiry', 300 );
		echo '<input type="number" name="qba_challenge_expiry" value="' . esc_attr( $value ) . '" min="60" max="1800" />';
		echo '<p class="description">' . esc_html__( 'Time before a challenge expires (in seconds)', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Max questions field callback
	 *
	 * @since 1.0.0
	 */
	public function max_questions_callback() {
		$value = get_option( 'qba_max_questions', 10 );
		echo '<input type="number" name="qba_max_questions" value="' . esc_attr( $value ) . '" min="5" max="50" />';
		echo '<p class="description">' . esc_html__( 'Maximum number of questions per battle', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Queue timeout field callback
	 *
	 * @since 1.0.0
	 */
	public function queue_timeout_callback() {
		$value = get_option( 'qba_queue_timeout', 300 );
		echo '<input type="number" name="qba_queue_timeout" value="' . esc_attr( $value ) . '" min="60" max="1800" />';
		echo '<p class="description">' . esc_html__( 'Maximum time to wait in matchmaking queue (in seconds)', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Battle points base field callback
	 *
	 * @since 1.0.0
	 */
	public function battle_points_base_callback() {
		$value = get_option( 'qba_battle_points_base', 10 );
		echo '<input type="number" name="qba_battle_points_base" value="' . esc_attr( $value ) . '" min="1" max="100" />';
		echo '<p class="description">' . esc_html__( 'Base points awarded for winning a battle', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Battle points speed field callback
	 *
	 * @since 1.0.0
	 */
	public function battle_points_speed_callback() {
		$value = get_option( 'qba_battle_points_speed', 5 );
		echo '<input type="number" name="qba_battle_points_speed" value="' . esc_attr( $value ) . '" min="0" max="50" />';
		echo '<p class="description">' . esc_html__( 'Bonus points for answering quickly', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Speed bonus max field callback
	 *
	 * @since 1.0.0
	 */
	public function speed_bonus_max_callback() {
		$value = get_option( 'qba_speed_bonus_max', 5 );
		echo '<input type="number" name="qba_speed_bonus_max" value="' . esc_attr( $value ) . '" min="0" max="20" />';
		echo '<p class="description">' . esc_html__( 'Maximum speed bonus points per question', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * ELO K-factor field callback
	 *
	 * @since 1.0.0
	 */
	public function elo_k_factor_callback() {
		$value = get_option( 'qba_elo_k_factor', 32 );
		echo '<input type="number" name="qba_elo_k_factor" value="' . esc_attr( $value ) . '" min="10" max="100" />';
		echo '<p class="description">' . esc_html__( 'ELO rating system K-factor (higher = more volatile ratings)', QBA_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Enable BuddyBoss field callback
	 *
	 * @since 1.0.0
	 */
	public function enable_buddyboss_callback() {
		$value = get_option( 'qba_enable_buddyboss_integration', true );
		echo '<input type="checkbox" name="qba_enable_buddyboss_integration" value="1" ' . checked( 1, $value, false ) . ' />';
		echo '<label for="qba_enable_buddyboss_integration">' . esc_html__( 'Enable BuddyBoss activity stream integration', QBA_TEXT_DOMAIN ) . '</label>';
	}

	/**
	 * Leaderboard cache timeout field callback
	 *
	 * @since 1.0.0
	 */
	public function leaderboard_cache_timeout_callback() {
		$value = get_option( 'qba_leaderboard_cache_timeout', 300 );
		echo '<input type="number" name="qba_leaderboard_cache_timeout" value="' . esc_attr( $value ) . '" min="60" max="3600" />';
		echo '<p class="description">' . esc_html__( 'How long to cache leaderboard data (in seconds)', QBA_TEXT_DOMAIN ) . '</p>';
	}
}
