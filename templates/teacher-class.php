<?php
/**
 * A single class: roster, attendance, results and materials.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_class_id = (int) get_query_var( 'sp_teacher_id' );
$sp_teacher     = $teacher ? $teacher : sp_get_teacher_by_user_id( get_current_user_id() );
$sp_class    = sp_get_class( $sp_class_id );
$sp_base_url    = sp_teacher_url( 'classes/' . $sp_class_id );

if ( ! $sp_class ) {
	?>
	<div class="sp-notice sp-notice-error">That class no longer exists.</div>
	<?php
	return;
}

$sp_access = sp_require_assigned_teacher( $sp_class_id );

if ( is_wp_error( $sp_access ) ) {
	?>
	<div class="sp-notice sp-notice-error"><?php echo esc_html( $sp_access->get_error_message() ); ?></div>
	<?php
	return;
}

$sp_tabs = array(
	'roster'      => 'Students',
	'attendance'  => 'Attendance',
	'results'     => 'Results',
	'certificates' => 'Certificates',
	'curriculum'  => 'Curriculum',
	'materials'   => 'Materials',
);

$sp_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'roster'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.

if ( ! isset( $sp_tabs[ $sp_tab ] ) ) {
	$sp_tab = 'roster';
}
?>
<div class="sp-page-head">
	<h1><?php echo esc_html( $sp_class->title ); ?></h1>
	<p class="sp-page-sub">
		<?php if ( $sp_class->code ) : ?>
			<code><?php echo esc_html( $sp_class->code ); ?></code> &middot;
		<?php endif; ?>
		<?php echo esc_html( number_format_i18n( sp_count_class_students( $sp_class_id ) ) ); ?> students
		<?php if ( $sp_class->start_date ) : ?>
			&middot; <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_class->start_date ) ) ); ?>
		<?php endif; ?>
	</p>
</div>

<?php if ( 'pending' === (string) $sp_class->status ) : ?>
	<div class="sp-notice sp-notice-warn">
		This class is awaiting admin approval. You can set it up now — students, attendance and results
		become available to everyone once an administrator approves it.
	</div>
<?php endif; ?>

<div id="sp-class-tab-region" data-sp-async-region>
<nav class="sp-tabs" aria-label="Class sections">
	<?php foreach ( $sp_tabs as $sp_key => $sp_label ) : ?>
		<a
			class="sp-tab<?php echo $sp_key === $sp_tab ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', $sp_key, $sp_base_url ) ); ?>"
			<?php echo $sp_key === $sp_tab ? ' aria-current="page"' : ''; ?>
		><?php echo esc_html( $sp_label ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( 'roster' === $sp_tab ) : ?>
	<?php
	$sp_students = sp_get_enrollments_for_class( $sp_class_id, array( 'active', 'completed' ), 0 );

	if ( ! $sp_students ) :
		?>
		<div class="sp-empty">
			No students are enrolled in this class yet. Ask an administrator to enrol them from
			Student Portal &rarr; Enrollments.
		</div>
	<?php else : ?>
		<div class="sp-table-wrap">
			<table class="sp-table">
				<thead>
					<tr>
						<th scope="col">Student</th>
						<th scope="col">Enroll ID</th>
						<th scope="col">Email</th>
						<th scope="col">Phone</th>
						<th scope="col">Attendance</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sp_students as $sp_student ) : ?>
						<?php $sp_stats = sp_get_attendance_stats( (int) $sp_student->profile_id, $sp_class_id ); ?>
						<tr>
							<td data-label="Student">
								<strong><?php echo esc_html( $sp_student->full_name ); ?></strong>
								<?php if ( 'completed' === $sp_student->status ) : ?>
									<span  class="badge-success">Completed</span>
								<?php endif; ?>
							</td>
							<td data-label="Enroll ID"><?php echo $sp_student->enroll_id ? esc_html( $sp_student->enroll_id ) : '<span class="sp-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?></td>
							<td data-label="Email"><a href="mailto:<?php echo esc_attr( $sp_student->email ); ?>"><?php echo esc_html( $sp_student->email ); ?></a></td>
							<td data-label="Phone"><?php echo $sp_student->phone ? esc_html( $sp_student->phone ) : '<span class="sp-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?></td>
							<td data-label="Attendance">
								<?php echo wp_kses_post( sp_attendance_badge( $sp_stats['percentage'] ) ); ?>
								<span class="sp-kv-note"><?php echo esc_html( $sp_stats['present'] . '/' . $sp_stats['total'] ); ?></span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

<?php elseif ( 'attendance' === $sp_tab ) :
	$sp_date    = isset( $_GET['date'] ) ? sp_sanitize_date( wp_unslash( $_GET['date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only date selection.
	$sp_date    = $sp_date ? $sp_date : current_time( 'Y-m-d' );
	$sp_session = sp_get_session_by_date( $sp_class_id, $sp_date );
	$sp_records = $sp_session ? sp_get_session_records( $sp_session->id ) : array();
	$sp_roster  = sp_get_enrollments_for_class( $sp_class_id, array( 'active' ), 0 );
	$sp_editable = $sp_session ? sp_attendance_session_is_editable( $sp_session ) : true;
	$sp_off_day    = sp_is_off_day( $sp_date );
	$sp_no_teacher = sp_class_teachers_unavailable( $sp_date, $sp_class_id );
	$sp_cancelled  = ! $sp_no_teacher && sp_date_cancels_class( $sp_date, $sp_class_id );
	$sp_can_reopen = $sp_session ? sp_can_reopen_session( $sp_session ) : false;

	// A cancelled date has no class, so nothing may be marked and no register created.
	if ( $sp_off_day || $sp_cancelled || $sp_no_teacher ) {
		$sp_editable = false;
	}
	?>

	<div class="sp-card">
		<form class="sp-inline-form" method="get" data-sp-async action="<?php echo esc_url( $sp_base_url ); ?>">
			<input type="hidden" name="tab" value="attendance" />
			<label class="sp-inline-label" for="sp_date">Session date</label>
			<input type="date" id="sp_date" name="date" value="<?php echo esc_attr( $sp_date ); ?>" data-sp-date data-sp-off-days="<?php echo esc_attr( implode( ',', sp_off_days() ) ); ?>" />
			<button type="submit" class="sp-btn sp-btn-ghost">Open register</button>
		</form>
	</div>

	<?php if ( $sp_off_day ) : ?>
		<div class="sp-notice sp-notice-warn">
			<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_date ) ) ); ?> is an off day.</strong>
			<?php echo esc_html( sp_off_days_label() ); ?> are marked as off days, so no class runs on this date
			and no register can be opened or marked. Change the off days in Settings if this is wrong.
		</div>
	<?php elseif ( $sp_cancelled ) : ?>
		<div class="sp-notice sp-notice-warn">
			<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_date ) ) ); ?> is cancelled.</strong>
			The portal calendar lists it as a holiday or test day, so no class runs and
			no register can be opened or marked on this date.
		</div>
	<?php elseif ( $sp_no_teacher ) : ?>
		<div class="sp-notice sp-notice-warn">
			<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_date ) ) ); ?> has no teacher available.</strong>
			Every teacher assigned to this class is marked absent or on leave for this date,
			so no register can be opened or marked.
		</div>
	<?php elseif ( ! $sp_roster ) : ?>
		<div class="sp-empty">No students are enrolled, so there is nothing to mark.</div>
	<?php else : ?>
		<?php if ( $sp_session && ! $sp_editable ) : ?>
			<div class="sp-notice sp-notice-warn">
				This register was submitted <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sp_session->submitted_at ) ) ); ?>
				and is locked.
				<?php if ( sp_can_reopen_session( $sp_session ) ) : ?>
					Reopen it below to make a correction, then submit it again.
				<?php else : ?>
					Only an administrator can reopen it.
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $sp_base_url ); ?>" data-sp-register data-sp-async data-sp-date-label="<?php echo esc_attr( date_i18n( get_option( 'date_format' ), strtotime( $sp_date ) ) ); ?>" data-sp-lock-days="<?php echo esc_attr( (int) sp_setting( 'attendance_edit_days', 7 ) ); ?>">
			<?php
			sp_teacher_nonce_field( 'save_session' );
			if ( $sp_session ) {
				echo '<input type="hidden" name="session_id" value="' . esc_attr( (int) $sp_session->id ) . '" />';
			}
			?>
			<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $sp_class_id ); ?>" />
			<input type="hidden" name="session_date" value="<?php echo esc_attr( $sp_date ); ?>" />

			<div class="sp-card">
				<h2 class="sp-card-title">Attendance &mdash; <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_date ) ) ); ?></h2>

				<div class="sp-field">
					<label for="sp_topic">Topic</label>
					<input type="text" id="sp_topic" name="topic" value="<?php echo esc_attr( $sp_session && $sp_session->topic ? $sp_session->topic : '' ); ?>" <?php disabled( ! $sp_editable ); ?> />
				</div>

				<div class="sp-attendance-list">
					<?php foreach ( $sp_roster as $sp_student ) : ?>
						<?php
						$sp_profile_id = (int) $sp_student->profile_id;
						$sp_current    = isset( $sp_records[ $sp_profile_id ] ) ? $sp_records[ $sp_profile_id ] : 'present';
						?>
						<div class="sp-attendance-row">
							<span class="sp-attendance-name">
								<strong><?php echo esc_html( $sp_student->full_name ); ?></strong>
								<span class="sp-muted"><?php echo esc_html( $sp_student->enroll_id ); ?></span>
							</span>
							<span class="sp-attendance-options">
								<?php foreach ( sp_attendance_statuses() as $sp_status ) : ?>
									<label class="sp-attendance-option sp-attendance-option-<?php echo esc_attr( $sp_status ); ?>">
										<input
											type="radio"
											name="marks[<?php echo esc_attr( $sp_profile_id ); ?>]"
											value="<?php echo esc_attr( $sp_status ); ?>"
											<?php checked( $sp_current, $sp_status ); ?>
											<?php disabled( ! $sp_editable ); ?>
										/>
										<span><?php echo esc_html( ucfirst( $sp_status ) ); ?></span>
									</label>
								<?php endforeach; ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( $sp_editable ) : ?>
					<p class="sp-card-actions">
						<span class="sp-attend-count" data-sp-register-count aria-live="polite"></span>
						<button type="submit" class="sp-btn sp-btn-primary">Save register</button>
						<button type="submit" name="submit_after" value="1" class="sp-btn sp-btn-ghost" data-sp-register-submit="1">Save and submit</button>
					</p>
				<?php endif; ?>
			</div>
		</form>

		<?php if ( $sp_session && $sp_editable ) : ?>
			<div class="sp-action-row">
				<form method="post" action="<?php echo esc_url( $sp_base_url ); ?>">
					<?php sp_teacher_nonce_field( 'mark_all' ); ?>
					<input type="hidden" name="session_id" value="<?php echo esc_attr( (int) $sp_session->id ); ?>" />
					<button type="submit" class="sp-btn sp-btn-ghost">Mark all present</button>
				</form>

				<form method="post" action="<?php echo esc_url( $sp_base_url ); ?>">
					<?php sp_teacher_nonce_field( 'submit_session' ); ?>
					<input type="hidden" name="session_id" value="<?php echo esc_attr( (int) $sp_session->id ); ?>" />
					<button type="submit" class="sp-btn sp-btn-primary" data-sp-register-submit="1" data-sp-date-label="<?php echo esc_attr( date_i18n( get_option( 'date_format' ), strtotime( $sp_date ) ) ); ?>" data-sp-lock-days="<?php echo esc_attr( (int) sp_setting( 'attendance_edit_days', 7 ) ); ?>">Submit and lock</button>
				</form>
			</div>
		<?php endif; ?>

		<?php if ( $sp_can_reopen ) : ?>
			<form class="sp-card sp-reopen" method="post" action="<?php echo esc_url( $sp_base_url ); ?>">
				<?php sp_teacher_nonce_field( 'reopen_session' ); ?>
				<input type="hidden" name="session_id" value="<?php echo esc_attr( (int) $sp_session->id ); ?>" />
				<div class="sp-field">
					<label for="sp_reason">Reason for reopening</label>
					<input type="text" id="sp_reason" name="reason" />
				</div>
				<button type="submit" class="sp-btn sp-btn-ghost">Reopen register</button>
			</form>
		<?php endif; ?>

		<h2 class="sp-section-title">Recent sessions</h2>

		<div class="sp-table-wrap">
			<table class="sp-table">
				<thead>
					<tr>
						<th scope="col">Date</th>
						<th scope="col">Topic</th>
						<th scope="col">Marked</th>
						<th scope="col">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$sp_sessions = sp_get_recent_sessions( $sp_class_id, 15 );

					if ( ! $sp_sessions ) :
						?>
						<tr><td colspan="4"><span class="sp-muted">No sessions recorded yet.</span></td></tr>
						<?php
					endif;

					foreach ( $sp_sessions as $sp_row ) :
						?>
						<tr>
							<td data-label="Date">
								<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'attendance', 'date' => $sp_row->session_date ), $sp_base_url ) ); ?>">
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_row->session_date ) ) ); ?>
								</a>
							</td>
							<td data-label="Topic"><?php echo $sp_row->topic ? esc_html( $sp_row->topic ) : '<span class="sp-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?></td>
							<td data-label="Marked"><?php echo esc_html( number_format_i18n( (int) $sp_row->marked_count ) ); ?></td>
							<td data-label="Status">
								<?php echo 'submitted' === $sp_row->status ? '<span class="sp-badge sp-badge-good">Submitted</span>' : '<span class="sp-badge sp-badge-muted">Open</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed escaped strings. ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

<?php elseif ( 'results' === $sp_tab ) :
	$sp_results = sp_get_class_results( $sp_class_id );
	?>
	<div class="sp-card">
		<p class="sp-muted">
			Enter theory and practical marks — both maximum fields are required, otherwise the
			percentage cannot be calculated. Ticking <strong>Publish</strong> makes the result
			visible to the student and emails them.
		</p>
	</div>

	<?php if ( ! $sp_results ) : ?>
		<div class="sp-empty">No students are enrolled in this class.</div>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( $sp_base_url ); ?>" data-sp-async>
			<?php sp_teacher_nonce_field( 'save_results' ); ?>
			<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $sp_class_id ); ?>" />

			<div class="sp-table-wrap">
				<table class="sp-table sp-table-form">
					<thead>
						<tr>
							<th scope="col">Student</th>
							<th scope="col">Theory</th>
							<th scope="col">Theory max</th>
							<th scope="col">Practical</th>
							<th scope="col">Practical max</th>
							<th scope="col">Outcome</th>
							<th scope="col">Publish</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sp_results as $sp_row ) : ?>
							<?php $sp_id = (int) $sp_row->enrollment_id; ?>
							<tr>
								<td data-label="Student">
									<strong><?php echo esc_html( $sp_row->full_name ); ?></strong>
									<span class="sp-muted"><?php echo esc_html( $sp_row->enroll_id ); ?></span>
								</td>
								<td data-label="Theory">
									<input type="number" step="0.01" min="0" inputmode="decimal"
										name="sp_result[<?php echo esc_attr( $sp_id ); ?>][theory_obtained]"
										value="<?php echo esc_attr( $sp_row->result_id ? sp_format_number( $sp_row->theory_obtained ) : '' ); ?>" />
								</td>
								<td data-label="Theory max">
									<input type="number" step="0.01" min="0.01" inputmode="decimal" required
										name="sp_result[<?php echo esc_attr( $sp_id ); ?>][theory_max]"
										aria-label="Theory maximum for <?php echo esc_attr( $sp_row->full_name ); ?>"
										value="<?php echo esc_attr( $sp_row->result_id ? sp_format_number( $sp_row->theory_max ) : '' ); ?>" />
								</td>
								<td data-label="Practical">
									<input type="number" step="0.01" min="0" inputmode="decimal"
										name="sp_result[<?php echo esc_attr( $sp_id ); ?>][practical_obtained]"
										value="<?php echo esc_attr( $sp_row->result_id ? sp_format_number( $sp_row->practical_obtained ) : '' ); ?>" />
								</td>
								<td data-label="Practical max">
									<input type="number" step="0.01" min="0.01" inputmode="decimal" required
										name="sp_result[<?php echo esc_attr( $sp_id ); ?>][practical_max]"
										aria-label="Practical maximum for <?php echo esc_attr( $sp_row->full_name ); ?>"
										value="<?php echo esc_attr( $sp_row->result_id ? sp_format_number( $sp_row->practical_max ) : '' ); ?>" />
								</td>
								<td data-label="Outcome">
									<?php if ( $sp_row->result_id ) : ?>
										<?php echo wp_kses_post( sp_result_badge( $sp_row->result ) ); ?>
										<span class="sp-kv-note"><?php echo esc_html( sp_format_number( $sp_row->percentage ) . '%' ); ?></span>
										<?php if ( $sp_row->grade ) : ?>
											<span class="sp-kv-note">Grade <?php echo esc_html( $sp_row->grade ); ?></span>
										<?php endif; ?>
									<?php else : ?>
										<span class="sp-muted">Not entered</span>
									<?php endif; ?>
								</td>
								<td data-label="Publish">
									<input type="checkbox" name="sp_result[<?php echo esc_attr( $sp_id ); ?>][finalised]" value="1"
										<?php checked( $sp_row->result_id && (int) $sp_row->finalised === 1 ); ?> />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<p class="sp-card-actions" style="margin-bottom: 1em;">
				<button type="submit" class="sp-btn sp-btn-primary">Save results</button>
			</p>
		</form>

		<div class="sp-card">
			<p class="sp-muted">
				Results entered? Open the
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'certificates', $sp_base_url ) ); ?>">Certificates</a>
				tab to issue a printable certificate for each student who has passed.
			</p>
		</div>
	<?php endif; ?>
<?php elseif ( 'certificates' === $sp_tab ) :
	$sp_results   = sp_get_class_results( $sp_class_id );
	$sp_cert_form = isset( $_GET['cert_form'] ) ? (int) $_GET['cert_form'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	?>
	<div class="sp-card">
		<h2 class="sp-card-title">Certificates</h2>
		<p class="sp-muted">
			Issue a certificate when a student's result is a pass and their class attendance is
			at least <?php echo esc_html( sp_format_number( sp_certificate_min_attendance() ) ); ?>%.
			The form auto-fills the grade, attendance and enrolment ID from this class.
		</p>

		<?php if ( ! $sp_results ) : ?>
			<div class="sp-empty">No students are enrolled in this class.</div>
		<?php else : ?>
			<div class="sp-table-wrap">
				<table class="sp-table">
					<thead>
						<tr>
							<th scope="col">Student</th>
							<th scope="col">Attendance</th>
							<th scope="col">Certificate</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $sp_results as $sp_row ) :
							?>
							<?php
							$sp_id     = (int) $sp_row->enrollment_id;
							$sp_stats  = $sp_row->profile_id
								? sp_get_attendance_stats( (int) $sp_row->profile_id, (int) $sp_class_id )
								: null;
							$sp_cstate = $sp_id
								? sp_certificate_state_for_enrollment( $sp_id )
								: array(
									'can_generate'    => false,
									'reason'          => 'That enrollment no longer exists.',
									'generated'       => false,
									'certificate_id'  => 0,
									'serial'          => '',
									'issued_date'     => '',
									'attendance'      => 0.0,
								);

							if ( $sp_id && $sp_cert_form === $sp_id ) {
								continue;
							}
							?>
							<tr>
								<td data-label="Student">
									<strong><?php echo esc_html( $sp_row->full_name ); ?></strong>
									<span class="sp-muted"><?php echo esc_html( $sp_row->enroll_id ); ?></span>
								</td>
								<td data-label="Attendance">
									<?php
									echo $sp_stats
										? esc_html( sp_format_number( $sp_stats['percentage'] ) . '%' )
										: '<span class="sp-muted">&mdash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary.
									?>
								</td>
							<td data-label="Certificate">
								<?php if ( $sp_cstate['generated'] ) : ?>
									<div class="sp-cert-rowline">
										<span class="sp-badge sp-badge-good">Issued</span>
										<span class="sp-muted">
											<?php echo esc_html( $sp_cstate['serial'] ); ?>
											<?php if ( $sp_cstate['issued_date'] ) : ?>
												&middot; <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_cstate['issued_date'] ) ) ); ?>
											<?php endif; ?>
										</span>
									</div>
									<div class="sp-cert-rowline">
										<?php $sp_view_url = sp_certificate_vendor_url( (int) $sp_cstate['certificate_id'] ); ?>
										<?php if ( ! is_wp_error( $sp_view_url ) ) : ?>
											<a class="sp-btn sp-btn-ghost" target="_blank" rel="noopener"
												href="<?php echo esc_url( $sp_view_url ); ?>">View</a>
										<?php endif; ?>
										<a class="sp-btn sp-btn-ghost"
											href="<?php echo esc_url( add_query_arg( array( 'tab' => 'certificates', 'cert_form' => $sp_id ), $sp_base_url ) ); ?>">Reissue</a>
									</div>
								<?php elseif ( $sp_cstate['can_generate'] ) : ?>
									<a class="sp-btn sp-btn-primary"
										href="<?php echo esc_url( add_query_arg( array( 'tab' => 'certificates', 'cert_form' => $sp_id ), $sp_base_url ) ); ?>">Issue certificate</a>
								<?php else : ?>
									<span class="sp-muted"><?php echo esc_html( $sp_cstate['reason'] ); ?></span>
								<?php endif; ?>
							</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $sp_cert_form ) : ?>
				<?php sp_teacher_render_certificate_form( $sp_cert_form, $sp_class_id ); ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
<?php elseif ( 'curriculum' === $sp_tab ) :
	$sp_course = $sp_class->course_id ? sp_get_course( (int) $sp_class->course_id ) : null;
	$sp_items  = $sp_course ? sp_get_curriculum( (int) $sp_course->id ) : array();
	$sp_prog   = sp_get_class_progress( $sp_class_id );
	$sp_cov    = $sp_course ? sp_curriculum_summary( (int) $sp_course->id, $sp_class_id ) : array( 'total' => 0, 'done' => 0, 'percent' => 0 );
	?>
	<?php if ( ! $sp_course ) : ?>
		<div class="sp-empty">This class has no course selected, so there is no outline to follow.</div>
	<?php elseif ( ! $sp_items ) : ?>
		<div class="sp-empty">
			No outline has been added for <strong><?php echo esc_html( $sp_course->name ); ?></strong> yet.
			An administrator adds it under Student Portal &rarr; Courses.
		</div>
	<?php else : ?>
		<div class="sp-card">
			<h2 class="sp-card-title"><?php echo esc_html( $sp_course->name ); ?></h2>
			<p class="sp-muted">Topics this class has covered. Tick a topic when you have taught it — students see the same list.</p>

			<div class="sp-meter">
				<div class="sp-meter-bar">
					<span style="width: <?php echo esc_attr( $sp_cov['percent'] ); ?>%"></span>
				</div>
				<span class="sp-meter-value"><?php echo esc_html( $sp_cov['done'] . ' / ' . $sp_cov['total'] . ' · ' . $sp_cov['percent'] . '%' ); ?></span>
			</div>
		</div>

		<div class="sp-card-list">
			<?php foreach ( $sp_items as $sp_index => $sp_item ) : ?>
				<?php
				$sp_marked  = isset( $sp_prog[ (int) $sp_item->id ] );
				$sp_who     = $sp_marked && $sp_prog[ (int) $sp_item->id ]->completed_by ? get_userdata( (int) $sp_prog[ (int) $sp_item->id ]->completed_by ) : false;
				?>
				<article class="sp-card">
					<div class="sp-material-head">
						<div>
							<h2 class="sp-card-title"><?php echo esc_html( ( $sp_index + 1 ) . '. ' . $sp_item->title ); ?></h2>
							<?php if ( $sp_item->detail ) : ?>
								<p class="sp-muted"><?php echo nl2br( esc_html( $sp_item->detail ) ); ?></p>
							<?php endif; ?>
							<?php if ( $sp_marked ) : ?>
								<p class="sp-muted">
									Covered <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_prog[ (int) $sp_item->id ]->completed_at ) ) ); ?>
									<?php if ( $sp_who ) : ?>
										&middot; <?php echo esc_html( $sp_who->display_name ); ?>
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</div>
						<div class="sp-card-actions">
							<form method="post" action="<?php echo esc_url( $sp_base_url ); ?>" data-sp-async>
								<?php sp_teacher_nonce_field( 'toggle_curriculum_item' ); ?>
								<input type="hidden" name="class_id" value="<?php echo esc_attr( $sp_class_id ); ?>" />
								<input type="hidden" name="item_id" value="<?php echo esc_attr( (int) $sp_item->id ); ?>" />
								<button type="submit" class="sp-btn <?php echo $sp_marked ? 'sp-btn-ghost' : 'sp-btn-primary'; ?>">
									<?php echo $sp_marked ? 'Undo' : 'Mark covered'; ?>
								</button>
							</form>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

<?php else :
	$sp_materials = sp_get_materials( $sp_class_id, array( 'draft', 'published' ) );
	?>
	<p class="sp-card-actions" style="margin-bottom: 1em;">
		<a class="sp-btn sp-btn-primary" href="<?php echo esc_url( add_query_arg( 'class', $sp_class_id, sp_teacher_url( 'materials/new' ) ) ); ?>">Add material</a>
	</p>

	<?php if ( ! $sp_materials ) : ?>
		<div class="sp-empty">No notes or documents for this class yet.</div>
	<?php else : ?>
		<div class="sp-card-list">
			<?php foreach ( $sp_materials as $sp_material ) : ?>
				<article class="sp-card">
					<div class="sp-material-head">
						<div>
							<h2 class="sp-card-title"><?php echo esc_html( $sp_material->title ); ?></h2>
							<p class="sp-muted">
								<?php echo 'file' === $sp_material->kind ? 'File' : 'Note'; ?>
								&middot;
								<?php echo 'published' === $sp_material->status
									? '<span class="sp-badge sp-badge-good">Published</span>'
									: '<span class="sp-badge sp-badge-muted">Draft</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed escaped strings.
								?>
								<?php if ( $sp_material->file_size ) : ?>
									&middot; <?php echo esc_html( sp_format_file_size( $sp_material->file_size ) ); ?>
								<?php endif; ?>
							</p>
						</div>
						<div class="sp-card-actions">
							<a class="sp-btn sp-btn-ghost" href="<?php echo esc_url( sp_teacher_url( 'materials/' . (int) $sp_material->id ) ); ?>">Edit</a>
							<form method="post" action="<?php echo esc_url( $sp_base_url ); ?>" data-sp-async onsubmit="return confirm('Delete this material?');">
								<?php sp_teacher_nonce_field( 'delete_material' ); ?>
								<input type="hidden" name="material_id" value="<?php echo esc_attr( (int) $sp_material->id ); ?>" />
								<button type="submit" class="sp-btn sp-btn-danger">Delete</button>
							</form>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
<?php endif; ?>
</div>
