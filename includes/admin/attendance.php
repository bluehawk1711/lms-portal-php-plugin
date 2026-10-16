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

	if ( ! $classes ) {
		echo '<p>No classes yet.</p>';
		return;
	}

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
	?>
	<form method="get" class="sp-admin-search">
		<input type="hidden" name="page" value="sp-attendance" />
		<label for="sp-attendance-class">Class</label>
		<select name="class" id="sp-attendance-class">
			<?php foreach ( $classes as $sp_row ) : ?>
				<option value="<?php echo esc_attr( (int) $sp_row->id ); ?>" <?php selected( (int) $class->id, (int) $sp_row->id ); ?>>
					<?php echo esc_html( $sp_row->title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<label for="sp-attendance-date">Date</label>
		<input type="date" id="sp-attendance-date" name="date" value="<?php echo esc_attr( $date ); ?>" />
		<button type="submit" class="button">Open register</button>
	</form>

	<?php if ( $session && ! $editable ) : ?>
		<div class="notice notice-warning">
			<p>
				Submitted <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $session->submitted_at ) ) ); ?>.
				As an administrator you can always reopen it.
			</p>
		</div>
	<?php endif; ?>

	<div class="sp-admin-grid">
		<div class="sp-admin-panel">
			<h2>
				<?php
				echo esc_html(
					$class->title . ' — ' . date_i18n( get_option( 'date_format' ), strtotime( $date ) )
				);
				?>
			</h2>

			<?php if ( ! $roster ) : ?>
				<p>No students enrolled in this class.</p>
			<?php else : ?>
				<form method="post">
					<?php sp_admin_nonce_field( 'save_session' ); ?>
					<?php if ( $session ) : ?>
						<input type="hidden" name="session_id" value="<?php echo esc_attr( (int) $session->id ); ?>" />
					<?php endif; ?>
					<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $class->id ); ?>" />
					<input type="hidden" name="session_date" value="<?php echo esc_attr( $date ); ?>" />

					<p>
						<label for="sp_topic">Topic</label>
						<input type="text" id="sp_topic" name="topic" class="regular-text"
							value="<?php echo esc_attr( $session && $session->topic ? $session->topic : '' ); ?>"
							<?php disabled( ! $editable ); ?> />
					</p>

					<div class="sp-table-wrap">
					<table class="widefat striped">
						<thead>
							<tr>
								<th>Student</th>
								<?php foreach ( sp_attendance_statuses() as $sp_status ) : ?>
									<th><?php echo esc_html( ucfirst( $sp_status ) ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $roster as $sp_student ) : ?>
								<?php
								$sp_profile_id = (int) $sp_student->profile_id;
								$sp_current    = isset( $records[ $sp_profile_id ] ) ? $records[ $sp_profile_id ] : 'present';
								?>
								<tr>
									<td><strong><?php echo esc_html( $sp_student->full_name ); ?></strong></td>
									<?php foreach ( sp_attendance_statuses() as $sp_status ) : ?>
										<td>
											<input
												type="radio"
												name="marks[<?php echo esc_attr( $sp_profile_id ); ?>]"
												value="<?php echo esc_attr( $sp_status ); ?>"
												<?php checked( $sp_current, $sp_status ); ?>
												<?php disabled( ! $editable ); ?>
											/>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>

					<?php if ( $editable ) : ?>
						<p><?php submit_button( 'Save register', 'primary', 'submit', false ); ?>
							<button type="submit" name="submit_after" value="1" class="button">Save and submit</button>
						</p>
					<?php endif; ?>
				</form>

				<?php if ( $session && ! $editable ) : ?>
					<form method="post">
						<?php sp_admin_nonce_field( 'reopen_session' ); ?>
						<input type="hidden" name="session_id" value="<?php echo esc_attr( (int) $session->id ); ?>" />
						<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $class->id ); ?>" />
						<p>
							<input type="text" name="reason" placeholder="Reason for reopening" />
							<?php submit_button( 'Reopen register', 'secondary', 'submit', false ); ?>
						</p>
					</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="sp-admin-panel">
			<h2>Recent sessions</h2>

			<div class="sp-table-wrap">
			<table class="widefat striped">
				<thead><tr><th>Date</th><th>Topic</th><th>Marked</th><th>Status</th><th></th></tr></thead>
				<tbody>
					<?php if ( ! $sessions ) : ?>
						<tr><td colspan="5">Nothing recorded yet.</td></tr>
					<?php endif; ?>

					<?php foreach ( $sessions as $sp_row ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_row->session_date ) ) ); ?></td>
							<td><?php echo $sp_row->topic ? esc_html( $sp_row->topic ) : '<span class="sp-admin-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?></td>
							<td><?php echo esc_html( (int) $sp_row->marked_count ); ?></td>
							<td><?php echo wp_kses_post( sp_admin_status_badge( $sp_row->status ) ); ?></td>
							<td>
								<a class="button button-small"
									href="<?php echo esc_url( add_query_arg( array( 'class' => (int) $class->id, 'date' => $sp_row->session_date ), admin_url( 'admin.php?page=sp-attendance' ) ) ); ?>">Open</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>

			<p class="sp-admin-muted">
				Registers lock <?php echo esc_html( (int) sp_setting( 'attendance_edit_days', 7 ) ); ?> days after submission.
				Change this in <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-settings' ) ); ?>">Settings</a>.
			</p>
		</div>
	</div>
	<?php
}