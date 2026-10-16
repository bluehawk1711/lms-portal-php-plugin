<?php
/**
 * Teacher portal calendar.
 *
 * Read-only view of the shared school calendar for the classes this teacher
 * is assigned to.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_profile = isset( $profile ) ? $profile : null;
?>
<div class="sp-page-head">
	<h1>Calendar</h1>
	<p class="sp-muted">Holidays, tests, exams and your class timetable.</p>
</div>

<?php
sp_render_schedule(
	array(
		'editable' => false,
		'context'  => 'teacher',
		'base_url' => sp_teacher_url( 'calendar' ),
	)
);
?>
