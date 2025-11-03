<?php
/**
 * Achievements Shortcode Template
 *
 * @package QuizBattleArena
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id = intval( $atts['user_id'] );
$layout  = sanitize_text_field( $atts['layout'] ?? 'grid' );

$achievements = new QBA_Achievements();
echo $achievements->render_achievements( $user_id, $layout );
