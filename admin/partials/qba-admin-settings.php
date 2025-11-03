<?php
/**
 * Admin Settings Page Partial
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
$tabs       = array(
	'general'      => __( 'General', QBA_TEXT_DOMAIN ),
	'battle'       => __( 'Battle Settings', QBA_TEXT_DOMAIN ),
	'scoring'      => __( 'Scoring & Points', QBA_TEXT_DOMAIN ),
	'integrations' => __( 'Integrations', QBA_TEXT_DOMAIN ),
);

// Define which sections belong to which tab
$tab_sections = array(
	'general'      => array( 'qba_general_settings' ),
	'battle'       => array( 'qba_battle_settings' ),
	'scoring'      => array( 'qba_scoring_settings' ),
	'integrations' => array( 'qba_integration_settings' ),
);
?>

<div class="wrap qba-settings-page">
	<div class="qba-settings-header">
		<div class="qba-settings-title">
			<h1><?php esc_html_e( 'Quiz Battle Arena Settings', QBA_TEXT_DOMAIN ); ?></h1>
			<p class="description"><?php esc_html_e( 'Configure your competitive quiz battle system settings.', QBA_TEXT_DOMAIN ); ?></p>
		</div>
		<div class="qba-settings-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-leaderboards' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'View Leaderboards', QBA_TEXT_DOMAIN ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qba-battles' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'View Battles', QBA_TEXT_DOMAIN ); ?>
			</a>
		</div>
	</div>

	<?php settings_errors(); ?>

	<h2 class="nav-tab-wrapper qba-nav-tabs">
		<?php foreach ( $tabs as $tab_key => $tab_name ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key ) ); ?>"
				class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<form method="post" action="options.php" class="qba-settings-form">
		<?php
		settings_fields( 'qba_settings_group' );
		
		// Include ALL settings sections in the form (hidden if not active tab)
		foreach ( $tab_sections as $tab_key => $sections ) {
			foreach ( $sections as $section ) {
				if ( $tab_key === $active_tab ) {
					// Show active tab sections
					do_settings_sections( $section );
				} else {
					// Include inactive tab sections but hide them
					echo '<div style="display: none;">';
					do_settings_sections( $section );
					echo '</div>';
				}
			}
		}
		?>
					// Include inactive tab sections but hide them
					echo '<div style="display: none;">';
					do_settings_sections( $section );
					echo '</div>';
				}
			}
		}
		?>

		<div class="qba-settings-submit">
			<?php submit_button( __( 'Save Settings', QBA_TEXT_DOMAIN ), 'primary', 'submit', false ); ?>
			<span class="spinner"></span>
		</div>
	</form>

	<div class="qba-settings-footer">
		<div class="qba-settings-info">
			<h3><?php esc_html_e( 'Need Help?', QBA_TEXT_DOMAIN ); ?></h3>
			<p><?php esc_html_e( 'Configure your battle system settings above. For detailed documentation and support, visit our knowledge base.', QBA_TEXT_DOMAIN ); ?></p>
			<div class="qba-help-links">
				<a href="#" class="button button-secondary" target="_blank">
					<span class="dashicons dashicons-book"></span>
					<?php esc_html_e( 'Documentation', QBA_TEXT_DOMAIN ); ?>
				</a>
				<a href="#" class="button button-secondary" target="_blank">
					<span class="dashicons dashicons-sos"></span>
					<?php esc_html_e( 'Support', QBA_TEXT_DOMAIN ); ?>
				</a>
			</div>
		</div>

		<div class="qba-settings-status">
			<h4><?php esc_html_e( 'System Status', QBA_TEXT_DOMAIN ); ?></h4>
			<ul class="qba-status-list">
				<li class="qba-status-item">
					<span class="qba-status-label"><?php esc_html_e( 'LearnDash:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-status-value <?php echo defined( 'LEARNDASH_VERSION' ) ? 'success' : 'error'; ?>">
						<?php echo defined( 'LEARNDASH_VERSION' ) ? esc_html( LEARNDASH_VERSION ) : esc_html__( 'Not Installed', QBA_TEXT_DOMAIN ); ?>
					</span>
				</li>
				<li class="qba-status-item">
					<span class="qba-status-label"><?php esc_html_e( 'PHP Version:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-status-value <?php echo version_compare( PHP_VERSION, '8.0', '>=' ) ? 'success' : 'warning'; ?>">
						<?php echo esc_html( PHP_VERSION ); ?>
					</span>
				</li>
				<li class="qba-status-item">
					<span class="qba-status-label"><?php esc_html_e( 'WordPress:', QBA_TEXT_DOMAIN ); ?></span>
					<span class="qba-status-value <?php echo version_compare( get_bloginfo( 'version' ), '6.0', '>=' ) ? 'success' : 'warning'; ?>">
						<?php echo esc_html( get_bloginfo( 'version' ) ); ?>
					</span>
				</li>
			</ul>
		</div>
	</div>
</div>

<style>
.qba-settings-page {
	max-width: none;
}

.qba-settings-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 1px solid #e1e1e1;
}

.qba-settings-title h1 {
	margin: 0 0 5px 0;
	font-size: 28px;
	font-weight: 300;
}

.qba-settings-title .description {
	color: #666;
	margin: 0;
	font-size: 14px;
}

.qba-settings-actions {
	display: flex;
	gap: 10px;
}

.qba-nav-tabs {
	margin-bottom: 30px !important;
	border-bottom: 1px solid #e1e1e1;
}

.qba-nav-tabs .nav-tab {
	border: none;
	background: #f1f1f1;
	color: #666;
	padding: 12px 20px;
	margin-right: 5px;
	border-radius: 4px 4px 0 0;
	transition: all 0.2s ease;
}

.qba-nav-tabs .nav-tab:hover {
	background: #e1e1e1;
	color: #333;
}

.qba-nav-tabs .nav-tab-active {
	background: #fff;
	color: #333;
	border-bottom: 2px solid #007cba;
	margin-bottom: -1px;
}

.qba-settings-form {
	background: #fff;
	border: 1px solid #e1e1e1;
	border-radius: 4px;
	padding: 30px;
	margin-bottom: 30px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.qba-settings-form .form-table {
	margin-top: 0;
}

.qba-settings-form .form-table th {
	width: 250px;
	padding: 20px 20px 20px 0;
	font-weight: 600;
	color: #333;
}

.qba-settings-form .form-table td {
	padding: 15px 0;
}

.qba-settings-form input[type="text"],
.qba-settings-form input[type="number"],
.qba-settings-form select {
	padding: 8px 12px;
	border: 1px solid #ddd;
	border-radius: 4px;
	font-size: 14px;
	min-width: 200px;
}

.qba-settings-form input[type="checkbox"] {
	margin-right: 8px;
}

.qba-settings-submit {
	padding-top: 20px;
	border-top: 1px solid #e1e1e1;
	margin-top: 30px;
	text-align: left;
	position: relative;
}

.qba-settings-submit .spinner {
	float: none;
	margin-left: 10px;
}

.qba-settings-footer {
	display: grid;
	grid-template-columns: 1fr 300px;
	gap: 30px;
	margin-top: 40px;
}

.qba-settings-info {
	background: #fff;
	border: 1px solid #e1e1e1;
	border-radius: 4px;
	padding: 25px;
}

.qba-settings-info h3 {
	margin-top: 0;
	color: #333;
}

.qba-help-links {
	display: flex;
	gap: 10px;
	margin-top: 15px;
}

.qba-help-links .button {
	display: flex;
	align-items: center;
	gap: 5px;
}

.qba-settings-status {
	background: #f8f9fa;
	border: 1px solid #e1e1e1;
	border-radius: 4px;
	padding: 25px;
}

.qba-settings-status h4 {
	margin-top: 0;
	color: #333;
}

.qba-status-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.qba-status-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 0;
	border-bottom: 1px solid #e9ecef;
}

.qba-status-item:last-child {
	border-bottom: none;
}

.qba-status-label {
	font-weight: 500;
	color: #666;
}

.qba-status-value {
	font-weight: 600;
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 12px;
	text-transform: uppercase;
}

.qba-status-value.success {
	background: #d4edda;
	color: #155724;
}

.qba-status-value.warning {
	background: #fff3cd;
	color: #856404;
}

.qba-status-value.error {
	background: #f8d7da;
	color: #721c24;
}

@media (max-width: 768px) {
	.qba-settings-header {
		flex-direction: column;
		gap: 20px;
	}

	.qba-settings-actions {
		align-self: flex-start;
	}

	.qba-settings-footer {
		grid-template-columns: 1fr;
		gap: 20px;
	}

	.qba-help-links {
		flex-direction: column;
	}

	.qba-help-links .button {
		justify-content: center;
	}
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Form submission feedback
	$('.qba-settings-form').on('submit', function() {
		$(this).find('.spinner').addClass('is-active');
		$(this).find('input[type="submit"]').prop('disabled', true);
	});

	// Show/hide dependent fields
	function toggleDependentFields() {
		var battlesEnabled = $('input[name="qba_enable_battles"]').is(':checked');
		var leaderboardEnabled = $('input[name="qba_enable_leaderboard"]').is(':checked');
		var achievementsEnabled = $('input[name="qba_enable_achievements"]').is(':checked');

		// Battle-related settings
		$('.qba-battle-setting').closest('tr').toggle(battlesEnabled);

		// Leaderboard-related settings
		$('.qba-leaderboard-setting').closest('tr').toggle(leaderboardEnabled);

		// Achievement-related settings
		$('.qba-achievement-setting').closest('tr').toggle(achievementsEnabled);
	}

	toggleDependentFields();
	$('input[name="qba_enable_battles"], input[name="qba_enable_leaderboard"], input[name="qba_enable_achievements"]').on('change', toggleDependentFields);

	// Add visual feedback for checkboxes
	$('.qba-settings-form input[type="checkbox"]').on('change', function() {
		var $row = $(this).closest('tr');
		if ($(this).is(':checked')) {
			$row.addClass('qba-setting-enabled');
		} else {
			$row.removeClass('qba-setting-enabled');
		}
	}).trigger('change');
});
</script>