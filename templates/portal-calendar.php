<?php
/**
 * Student portal calendar.
 *
 * Read-only view of the shared school calendar: all-day event chips and the
 * class timetable blocks, rendered by the same sp_schedule_render_grid() the
 * admin Calendar screen uses.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sp-page-head">
	<h1>Calendar</h1>
	<p class="sp-muted">Holidays, tests, exams and your class timetable.</p>
</div>

<?php
sp_render_schedule(
	array(
		'editable' => false,
		'context'  => 'student',
		'base_url' => sp_portal_url( 'calendar' ),
	)
);
?>
