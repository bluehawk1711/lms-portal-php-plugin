<?php
/**
 * Minimal shell for fullscreen views (exams).
 *
 * No sidebar, no right rail — just a slim top bar with a back link and the
 * signed-in student's name, then the content. The page title deliberately does
 * not appear here: the exam screen below prints the real task title as its own
 * h1, so repeating it in the bar only ever showed the same thing twice. The
 * student stays signed in; only the chrome is removed so the exam owns the
 * viewport.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sp-exam-shell">
	<header class="sp-exam-bar">
		<a class="sp-exam-back" href="<?php echo esc_url( sp_portal_url( 'tasks' ) ); ?>">&larr; Back to tasks</a>
		<?php // The exam heading below already carries the real task title, so ?>
		<?php // printing a static route label here as well only duplicated it. ?>
		<span class="sp-exam-bar-user"><?php echo esc_html( $profile->full_name ); ?></span>
	</header>
	<main class="sp-exam-main">
