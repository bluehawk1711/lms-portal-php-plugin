<?php
/**
 * Minimal shell for fullscreen views (exams).
 *
 * No sidebar, no right rail — just a slim top bar with a back link and the
 * page title, then the content. The student stays signed in; only the chrome
 * is removed so the exam owns the viewport.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_route = isset( $route ) ? $route : array();
$sp_label = isset( $sp_route['label'] ) ? $sp_route['label'] : 'Portal';
?>
<div class="sp-exam-shell">
	<header class="sp-exam-bar">
		<a class="sp-exam-back" href="<?php echo esc_url( sp_portal_url( 'tasks' ) ); ?>">&larr; Back to tasks</a>
		<span class="sp-exam-bar-title"><?php echo esc_html( $sp_label ); ?></span>
		<span class="sp-exam-bar-user"><?php echo esc_html( $profile->full_name ); ?></span>
	</header>
	<main class="sp-exam-main">
