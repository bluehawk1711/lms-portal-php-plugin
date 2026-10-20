<?php
/**
 * Attendance register administration.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attendance screen.
 */
function sp_admin_page_sp_attendance() {
	$classes = sp_get_classes( array( 'per_page' => 200 ) );
	?>
	<div class="sp-attendance-page" id="sp-attendance-region" data-sp-async-region>
	<?php if ( ! $classes ) : ?>
		<section class="sp-attendance-empty sp-admin-panel">
			<span class="sp-attendance-empty-icon" aria-hidden="true">▦</span>
			<p class="sp-attendance-eyebrow">STUDENT PORTAL · ATTENDANCE</p>
			<h2>No classes available yet</h2>
			<p>Create a class and enrol students before taking attendance.</p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-classes' ) ); ?>">Go to Classes</a>
		</section>
		</div>
		<?php
		return;
	endif;

	$class_id = isset( $_GET['class'] ) ? (int) $_GET['class'] : (int) $classes[0]->id; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$class    = sp_get_class( $class_id );

	if ( ! $class ) {
		$class = $classes[0];
	}

	$date    = isset( $_GET['date'] ) ? sp_sanitize_date( wp_unslash( $_GET['date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$date    = $date ? $date : current_time( 'Y-m-d' );
	$session = sp_get_session_by_date( (int) $class->id, $date );
	$records = $session ? sp_get_session_records( $session->id ) : array();
	$roster  = sp_get_enrollments_for_class( (int) $class->id, array( 'active' ), 0 );
	$editable = $session ? sp_attendance_session_is_editable( $session ) : true;
	$sessions = sp_get_recent_sessions( (int) $class->id, 15 );
	$statuses = sp_attendance_statuses();
	$date_label = date_i18n( get_option( 'date_format' ), strtotime( $date ) );
	$marked_count = is_array( $records ) ? count( $records ) : 0;
	$present_count = 0;
	$absent_count  = 0;

	foreach ( $records as $sp_record_status ) {
		if ( 'present' === (string) $sp_record_status ) {
			++$present_count;
		} elseif ( 'absent' === (string) $sp_record_status ) {
			++$absent_count;
		}
	}
	?>

	<header class="sp-attendance-hero">
		<div class="sp-attendance-hero-copy">
			<p class="sp-attendance-eyebrow">STUDENT PORTAL / CLASSROOM</p>
			<h1>Attendance register</h1>
			<p>Record daily attendance, review recent sessions and manage submitted registers in one place.</p>
		</div>
		<div class="sp-attendance-hero-meta">
			<span class="sp-attendance-date-icon" aria-hidden="true">▦</span>
			<div>
				<span class="sp-attendance-meta-label">REGISTER DATE</span>
				<strong><?php echo esc_html( $date_label ); ?></strong>
			</div>
		</div>
	</header>

	<form method="get" class="sp-attendance-filter" data-sp-async>
		<input type="hidden" name="page" value="sp-attendance" />
		<div class="sp-attendance-filter-heading">
			<span class="sp-attendance-filter-icon" aria-hidden="true">⌕</span>
			<div>
				<strong>Choose a register</strong>
				<span>Pick a class and date to open its attendance sheet.</span>
			</div>
		</div>
		<div class="sp-attendance-filter-fields">
			<label class="sp-attendance-field" for="sp-attendance-class">
				<span>Class</span>				<select name="class" id="sp-attendance-class" data-sp-async-change>
					<?php foreach ( $classes as $sp_row ) : ?>
						<option value="<?php echo esc_attr( (int) $sp_row->id ); ?>" <?php selected( (int) $class->id, (int) $sp_row->id ); ?>>
							<?php echo esc_html( $sp_row->title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="sp-attendance-field" for="sp-attendance-date">
				<span>Attendance date</span>
				<input type="date" id="sp-attendance-date" name="date" value="<?php echo esc_attr( $date ); ?>" data-sp-date data-sp-off-days="<?php echo esc_attr( implode( ',', sp_off_days() ) ); ?>" />
			</label>
			<button type="submit" class="button button-primary sp-attendance-open-button">Open register <span aria-hidden="true">→</span></button>
		</div>
	</form>

	<?php if ( $session && ! $editable ) : ?>
		<div class="notice notice-warning sp-attendance-lock-notice">
			<p>
				<strong>Submitted register is locked.</strong>
				Submitted <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $session->submitted_at ) ) ); ?>.
				As an administrator, you can reopen it using the reason field below.
			</p>
		</div>
	<?php endif; ?>

	<section class="sp-attendance-stats" aria-label="Attendance summary">
		<div class="sp-attendance-stat">
			<span class="sp-attendance-stat-icon sp-stat-indigo" aria-hidden="true">♙</span>
			<div><span class="sp-attendance-stat-label">Enrolled students</span><strong><?php echo esc_html( number_format_i18n( count( $roster ) ) ); ?></strong><small>Active enrolments</small></div>
		</div>
		<div class="sp-attendance-stat">
			<span class="sp-attendance-stat-icon sp-stat-blue" aria-hidden="true">✓</span>
			<div><span class="sp-attendance-stat-label">Saved marks</span><strong><?php echo esc_html( number_format_i18n( $marked_count ) ); ?></strong><small>Recorded for this date</small></div>
		</div>
		<div class="sp-attendance-stat">
			<span class="sp-attendance-stat-icon sp-stat-green" aria-hidden="true">●</span>
			<div><span class="sp-attendance-stat-label">Present</span><strong><?php echo esc_html( number_format_i18n( $present_count ) ); ?></strong><small>Saved present marks</small></div>
		</div>
		<div class="sp-attendance-stat">
			<span class="sp-attendance-stat-icon sp-stat-rose" aria-hidden="true">—</span>
			<div><span class="sp-attendance-stat-label">Absent</span><strong><?php echo esc_html( number_format_i18n( $absent_count ) ); ?></strong><small>Saved absent marks</small></div>
		</div>
	</section>

	<div class="sp-admin-grid sp-attendance-layout">
		<section class="sp-admin-panel sp-attendance-register-panel">
			<div class="sp-attendance-panel-heading">
				<div>
					<p class="sp-attendance-panel-kicker">DAILY REGISTER</p>
					<h2><?php echo esc_html( $class->title ); ?></h2>
					<p class="sp-attendance-panel-subtitle"><?php echo esc_html( $date_label ); ?> <span aria-hidden="true">·</span> <?php echo esc_html( number_format_i18n( count( $roster ) ) ); ?> students</p>
				</div>
				<?php if ( $session ) : ?>
					<span class="sp-attendance-state <?php echo $editable ? 'is-open' : 'is-locked'; ?>">
						<span aria-hidden="true"></span><?php echo $editable ? 'Editable' : 'Submitted / locked'; ?>
					</span>
				<?php else : ?>
					<span class="sp-attendance-state is-new"><span aria-hidden="true"></span>New register</span>
				<?php endif; ?>
			</div>

			<?php if ( ! $roster ) : ?>
				<div class="sp-attendance-inline-empty">
					<span class="sp-attendance-empty-icon" aria-hidden="true">♙</span>
					<h3>No students enrolled</h3>
					<p>This class doesn't have any active enrolments yet. Add students before taking attendance.</p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-enrollments&class=' . (int) $class->id ) ); ?>">View enrolments</a>
				</div>
			<?php else : ?>
				<form method="post" class="sp-attendance-save-form" data-sp-register data-sp-async data-sp-date-label="<?php echo esc_attr( $date_label ); ?>" data-sp-lock-days="<?php echo esc_attr( (int) sp_setting( 'attendance_edit_days', 7 ) ); ?>">
					<?php sp_admin_nonce_field( 'save_session' ); ?>
					<?php if ( $session ) : ?>
						<input type="hidden" name="session_id" value="<?php echo esc_attr( (int) $session->id ); ?>" />
					<?php endif; ?>
					<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $class->id ); ?>" />
					<input type="hidden" name="session_date" value="<?php echo esc_attr( $date ); ?>" />

					<div class="sp-attendance-topic-field">
						<label for="sp_topic">Session topic <span class="sp-attendance-optional">Optional</span></label>
						<input type="text" id="sp_topic" name="topic" class="regular-text" placeholder="e.g. Introduction to computer networks" value="<?php echo esc_attr( $session && $session->topic ? $session->topic : '' ); ?>" <?php disabled( ! $editable ); ?> />
					</div>

					<div class="sp-table-wrap sp-attendance-table-wrap">
						<table class="widefat sp-attendance-table">
							<thead>
								<tr>
									<th scope="col" class="sp-attendance-student-heading">Student</th>
									<th scope="col">Attendance status <span class="sp-attendance-header-note">Select one for each student</span></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $roster as $sp_student ) : ?>
									<?php
									$sp_profile_id = (int) $sp_student->profile_id;
									$sp_current    = isset( $records[ $sp_profile_id ] ) ? $records[ $sp_profile_id ] : 'present';
									$sp_name        = trim( (string) $sp_student->full_name );
									$sp_initial     = function_exists( 'mb_substr' ) ? mb_substr( $sp_name, 0, 1 ) : substr( $sp_name, 0, 1 );
									?>
									<tr>
										<td class="sp-attendance-student-cell">
											<span class="sp-attendance-avatar" aria-hidden="true"><?php echo esc_html( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $sp_initial ) : strtoupper( $sp_initial ) ); ?></span>
											<span class="sp-attendance-student-name"><?php echo esc_html( $sp_name ); ?></span>
										</td>
										<td>
											<div class="sp-attendance-options" role="group" aria-label="Attendance for <?php echo esc_attr( $sp_name ); ?>">
												<?php foreach ( $statuses as $sp_status ) : ?>
													<?php $sp_status_class = sanitize_html_class( strtolower( (string) $sp_status ) ); ?>
													<label class="sp-attendance-choice sp-choice-<?php echo esc_attr( $sp_status_class ); ?>">
														<input type="radio" name="marks[<?php echo esc_attr( $sp_profile_id ); ?>]" value="<?php echo esc_attr( $sp_status ); ?>" <?php checked( $sp_current, $sp_status ); ?> <?php disabled( ! $editable ); ?> />
														<span><?php echo esc_html( ucfirst( (string) $sp_status ) ); ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<?php if ( $editable ) : ?>
						<div class="sp-attendance-form-footer">
							<p class="sp-attendance-footer-hint"><span data-sp-register-count aria-live="polite"></span> — your changes are saved when you choose an action.</p>
							<div class="sp-attendance-form-actions">
								<?php submit_button( 'Save register', 'secondary', 'submit', false ); ?>
								<button type="submit" name="submit_after" value="1" class="button button-primary" data-sp-register-submit="1">Save and submit <span aria-hidden="true">→</span></button>
							</div>
						</div>
					<?php endif; ?>
				</form>

				<?php if ( $session && ! $editable ) : ?>
					<div class="sp-attendance-reopen-box">
						<div>
							<strong>Need to make a correction?</strong>
							<p>Reopen this submitted register to edit attendance. Please provide a reason for the audit trail.</p>
						</div>
						<form method="post" class="sp-attendance-reopen-form">
							<?php sp_admin_nonce_field( 'reopen_session' ); ?>
							<input type="hidden" name="session_id" value="<?php echo esc_attr( (int) $session->id ); ?>" />
							<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $class->id ); ?>" />
							<label class="screen-reader-text" for="sp-attendance-reopen-reason">Reason for reopening</label>
							<input type="text" id="sp-attendance-reopen-reason" name="reason" placeholder="Reason for reopening" required />
							<?php submit_button( 'Reopen register', 'secondary', 'submit', false ); ?>
						</form>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</section>

		<aside class="sp-admin-panel sp-attendance-history-panel">
			<div class="sp-attendance-panel-heading sp-attendance-history-heading">
				<div>
					<p class="sp-attendance-panel-kicker">ACTIVITY</p>
					<h2>Recent sessions</h2>
					<p class="sp-attendance-panel-subtitle">Latest registers for this class</p>
				</div>
				<span class="sp-attendance-history-count"><?php echo esc_html( number_format_i18n( count( $sessions ) ) ); ?></span>
			</div>

			<?php if ( ! $sessions ) : ?>
				<div class="sp-attendance-history-empty">
					<span aria-hidden="true">◷</span>
					<strong>No sessions recorded yet</strong>
					<p>Your saved attendance registers will appear here.</p>
				</div>
			<?php else : ?>
				<div class="sp-attendance-session-list">
					<?php foreach ( $sessions as $sp_row ) : ?>
						<?php
						$sp_session_date = date_i18n( get_option( 'date_format' ), strtotime( $sp_row->session_date ) );
						$sp_session_url  = add_query_arg(
							array(
								'page'  => 'sp-attendance',
								'class' => (int) $class->id,
								'date'  => $sp_row->session_date,
							),
							admin_url( 'admin.php' )
						);
						?>
						<article class="sp-attendance-session-item">
							<div class="sp-attendance-session-date-icon" aria-hidden="true">▦</div>
							<div class="sp-attendance-session-main">
								<strong><?php echo esc_html( $sp_session_date ); ?></strong>
								<span class="sp-attendance-session-topic"><?php echo $sp_row->topic ? esc_html( $sp_row->topic ) : '<span class="sp-admin-muted">No topic added</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- conditional contains escaped text or fixed markup. ?></span>
								<span class="sp-attendance-session-meta"><?php echo esc_html( number_format_i18n( (int) $sp_row->marked_count ) ); ?> marks saved</span>
								<span class="sp-attendance-session-status"><?php echo wp_kses_post( sp_admin_status_badge( $sp_row->status ) ); ?></span>
							</div>
							<a class="sp-attendance-session-open" href="<?php echo esc_url( $sp_session_url ); ?>" aria-label="Open register for <?php echo esc_attr( $sp_session_date ); ?>">Open <span aria-hidden="true">↗</span></a>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="sp-attendance-history-footer">
				<span class="sp-attendance-lock-icon" aria-hidden="true">◷</span>
				<p>Registers lock <strong><?php echo esc_html( (int) sp_setting( 'attendance_edit_days', 7 ) ); ?> days</strong> after submission. Adjust the rule in <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-settings' ) ); ?>">Settings</a>.</p>
			</div>
		</aside>
	</div>
	</div>
	<?php
}
