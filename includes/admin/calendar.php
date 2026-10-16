<?php
/**
 * Calendar administration.
 *
 * The shared school calendar, rendered by sp_render_schedule() so the admin
 * grid, the student portal and the teacher portal all show the same thing.
 * Holidays and tests written here cancel class everywhere — no register can
 * be opened, the auto-absent run skips those dates and sessions on them drop
 * out of attendance percentages. Exam and event entries are informational
 * and cancel nothing.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calendar screen.
 */
function sp_admin_page_sp_calendar() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$edit_id   = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
	$open_date = isset( $_GET['sp_edit_date'] ) ? sp_sanitize_date( wp_unslash( $_GET['sp_edit_date'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">Calendar</h1>
		<hr class="wp-header-end" />

		<?php
		sp_render_schedule(
			array(
				'editable'  => true,
				'context'   => 'admin',
				'base_url'  => admin_url( 'admin.php?page=sp-calendar' ),
				'open_date' => $open_date,
				'event_id'  => $edit_id,
			)
		);
		?>
	</div>
	<?php
}
