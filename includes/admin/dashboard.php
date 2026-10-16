<?php
/**
 * Admin dashboard.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Portal dashboard screen.
 */
function sp_admin_page_sp_dashboard() {
	global $wpdb;

	$counts = array(
		'students'    => sp_count_profiles(),
		'teachers'    => sp_count_teachers(),
		'classes'   => sp_count_classes( array( 'status' => 'active' ) ),
		'enrollments' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'enrollments' ) . " WHERE status = 'active'" ),
		'materials'   => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'materials' ) ),
		'sessions'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'attendance_sessions' ) ),
		'results'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'results' ) . ' WHERE finalised = 1' ),
		'pending_res' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'results' ) . ' WHERE finalised = 0' ),
		'failed_mail' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'email_log' ) . " WHERE status = 'failed'" ),
	);

	$recent = $wpdb->get_results(
		'SELECT recipient, subject, status, created_at FROM ' . sp_table( 'email_log' ) . ' ORDER BY created_at DESC LIMIT 8'
	);
	?>
	<?php sp_admin_cert_notice(); ?>

	<div class="sp-admin-cards">
		<div class="sp-admin-card"><span class="sp-admin-stat"><?php echo esc_html( number_format_i18n( $counts['students'] ) ); ?></span><span>Students</span></div>
		<div class="sp-admin-card"><span class="sp-admin-stat"><?php echo esc_html( number_format_i18n( $counts['teachers'] ) ); ?></span><span>Teachers</span></div>
		<div class="sp-admin-card"><span class="sp-admin-stat"><?php echo esc_html( number_format_i18n( $counts['classes'] ) ); ?></span><span>Active classes</span></div>
		<div class="sp-admin-card"><span class="sp-admin-stat"><?php echo esc_html( number_format_i18n( $counts['enrollments'] ) ); ?></span><span>Enrolments</span></div>
		<div class="sp-admin-card"><span class="sp-admin-stat"><?php echo esc_html( number_format_i18n( $counts['sessions'] ) ); ?></span><span>Attendance sessions</span></div>
		<div class="sp-admin-card"><span class="sp-admin-stat"><?php echo esc_html( number_format_i18n( $counts['materials'] ) ); ?></span><span>Materials</span></div>
		<div class="sp-admin-card"><span class="sp-admin-stat"><?php echo esc_html( number_format_i18n( $counts['results'] ) ); ?></span><span>Published results</span></div>
		<div class="sp-admin-card"><span class="sp-admin-stat"><?php echo esc_html( number_format_i18n( $counts['pending_res'] ) ); ?></span><span>Draft results</span></div>
	</div>

	<?php if ( $counts['failed_mail'] > 0 ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php echo esc_html( number_format_i18n( $counts['failed_mail'] ) ); ?> portal email(s) failed to send.</strong>
				The site mailer is probably not configured — install an SMTP plugin, then
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-email-log' ) ); ?>">review the email log</a>.
			</p>
		</div>
	<?php endif; ?>

	<div class="sp-admin-grid">
		<div class="sp-admin-panel">
			<h2>Getting started</h2>
			<ol class="sp-admin-steps">
				<li><strong>Set the grade thresholds</strong> in <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-settings' ) ); ?>">Settings</a>. Certificates only accept A+/A/B/C, so keep the scale inside that list.</li>
				<li><strong>Add teachers</strong> on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-teachers' ) ); ?>">Teachers</a> screen, or import them in bulk.</li>
				<li><strong>Import students</strong> from your existing roster with a dry run first.</li>
				<li><strong>Create classes</strong> and assign teachers on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-classes' ) ); ?>">Classes</a> screen.</li>
				<li><strong>Enrol students</strong> into each class.</li>
				<li>Teachers then take attendance, publish materials and record results from <code><?php echo esc_html( sp_portal_url( 'teacher' ) ); ?></code>.</li>
				<li>When grades are finalised, <strong>sync them</strong> to the Certificate Manager and regenerate certificates there.</li>
			</ol>
		</div>

		<div class="sp-admin-panel">
			<h2>Recent emails</h2>
			<?php if ( ! $recent ) : ?>
				<p class="sp-admin-muted">Nothing sent yet.</p>
			<?php else : ?>
				<div class="sp-table-wrap">
				<table class="widefat striped">
					<thead>
						<tr><th>Recipient</th><th>Subject</th><th>Status</th></tr>
					</thead>
					<tbody>
						<?php foreach ( $recent as $sp_row ) : ?>
							<tr>
								<td><?php echo esc_html( $sp_row->recipient ); ?></td>
								<td><?php echo esc_html( $sp_row->subject ); ?></td>
								<td><?php echo wp_kses_post( sp_admin_status_badge( $sp_row->status ) ); ?></td>
							</tr>
					<?php endforeach; ?>
				</tbody>
				</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}