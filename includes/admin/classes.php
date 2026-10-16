<?php
/**
 * Classes plus teacher assignment.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classes screen.
 */
function sp_admin_page_sp_classes() {
	$classes  = sp_get_classes( array( 'per_page' => 200 ) );
	$teachers   = sp_get_teachers();
	$courses    = sp_get_courses();
	$course_map = array();

	foreach ( $courses as $sp_one ) {
		$course_map[ (int) $sp_one->id ] = $sp_one->name;
	}

	$edit_id    = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$focus      = isset( $_GET['class'] ) ? (int) $_GET['class'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$editing    = $edit_id ? sp_get_class( $edit_id ) : null;
	$focus_data = $focus ? sp_get_class( $focus ) : null;

	$editing_timings = $editing ? sp_get_class_timings( $editing->id ) : array();

	// The form asks for one daily pair now; prefill it from any stored row
	// (all days carry the same times since the single-pair form was introduced).
	$sp_first_timing = $editing_timings ? reset( $editing_timings ) : null;
	?>
	<div class="sp-admin-grid">
		<div class="sp-admin-panel">
			<h2><?php echo $editing ? 'Edit class' : 'Create a class'; ?></h2>

			<form method="post">
				<?php sp_admin_nonce_field( 'save_class' ); ?>
				<?php if ( $editing ) : ?>
					<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $editing->id ); ?>" />
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sp_o_title">Title <span class="sp-required">*</span></label></th>
						<td><input type="text" id="sp_o_title" name="title" required class="regular-text" value="<?php echo esc_attr( $editing ? $editing->title : '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_o_code">Code</label></th>
						<td>
							<input type="text" id="sp_o_code" name="code" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->code : '' ); ?>" />
							<p class="description">Matched against the <code>class_code</code> column when importing students.</p>
						</td>
					</tr>
				<tr>
					<th scope="row"><label for="sp_o_course">Course</label></th>
						<td>
							<select id="sp_o_course" name="course_id">
								<option value="0">No course</option>
								<?php foreach ( $courses as $sp_course ) : ?>
									<option value="<?php echo esc_attr( (int) $sp_course->id ); ?>" <?php selected( $editing ? (int) $editing->course_id : 0, (int) $sp_course->id ); ?>>
										<?php echo esc_html( $sp_course->name . ( $sp_course->code ? ' (' . $sp_course->code . ')' : '' ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php if ( $courses ) : ?>
									The course this class teaches. Its code is stamped into the certificate id.
								<?php else : ?>
									No courses yet.
								<?php endif; ?>
								Manage them on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-courses' ) ); ?>">Courses</a> screen.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_o_start">Start date</label></th>
						<td><input type="date" id="sp_o_start" name="start_date" value="<?php echo esc_attr( $editing ? $editing->start_date : '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_o_end">End date</label></th>
						<td><input type="date" id="sp_o_end" name="end_date" value="<?php echo esc_attr( $editing ? $editing->end_date : '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row">Daily timing</th>
						<td>
							<p class="sp-admin-timing-row">
								<label class="screen-reader-text" for="sp_t_daily_start">
									Daily start time
								</label>
								<input type="time" id="sp_t_daily_start" name="timing_all[start]" value="<?php echo esc_attr( $sp_first_timing ? $sp_first_timing['start_time'] : '' ); ?>" />
								<span aria-hidden="true">&ndash;</span>
								<label class="screen-reader-text" for="sp_t_daily_end">
									Daily end time
								</label>
								<input type="time" id="sp_t_daily_end" name="timing_all[end]" value="<?php echo esc_attr( $sp_first_timing ? $sp_first_timing['end_time'] : '' ); ?>" />
							</p>

							<p class="description">
								The class meets at this same time every day of the week — enter it once here and it
								applies to all seven days. Leave both fields blank when there is no fixed timing.
							</p>

							<?php if ( $editing_timings ) : ?>
								<?php $sp_set_id = $editing ? (int) $editing->timings_id : 0; ?>
								<?php $sp_shared = $sp_set_id ? sp_count_classes_with_timings( $sp_set_id, (int) $editing->id ) : 0; ?>
								<p class="description">
									Pattern: <strong><?php echo esc_html( sp_timing_set_label( $editing_timings ) ); ?></strong>
									<?php if ( $sp_shared > 0 ) : ?>
										<span class="sp-admin-muted">
											— shared with <?php echo esc_html( number_format_i18n( $sp_shared ) ); ?> other <?php echo 1 === $sp_shared ? 'class' : 'classes'; ?>.
										</span>
									<?php endif; ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_o_status">Status</label></th>
						<td>
							<select id="sp_o_status" name="status">
								<?php if ( $editing && 'pending' === (string) $editing->status ) : ?>
									<option value="pending" selected>Pending approval</option>
								<?php endif; ?>
								<option value="active" <?php selected( $editing ? $editing->status : 'active', 'active' ); ?>>Active</option>
								<option value="archived" <?php selected( $editing ? $editing->status : '', 'archived' ); ?>>Archived</option>
							</select>
							<?php if ( $editing && 'pending' === (string) $editing->status ) : ?>
								<p class="description">This class was created by a teacher and is waiting for approval.</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_o_desc">Description</label></th>
						<td><textarea id="sp_o_desc" name="description" rows="3" class="large-text"><?php echo esc_textarea( $editing ? $editing->description : '' ); ?></textarea></td>
					</tr>
				</table>

				<?php submit_button( $editing ? 'Save class' : 'Create class' ); ?>
			</form>

			<?php if ( $editing ) : ?>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-classes' ) ); ?>">Cancel editing</a>
				</p>
			<?php endif; ?>
		</div>

		<div class="sp-admin-panel">
			<h2>All classes</h2>

			<div class="sp-table-wrap">
			<table class="widefat striped">
				<thead>
					<tr><th>Title</th><th>Code</th><th>Course</th><th>Students</th><th>Teachers</th><th>Status</th><th></th></tr>
				</thead>
				<tbody>
					<?php if ( ! $classes ) : ?>
						<tr><td colspan="7">No classes yet.</td></tr>
					<?php endif; ?>

					<?php foreach ( $classes as $sp_row ) : ?>
						<?php
						$sp_assigned  = sp_get_class_teachers( $sp_row->id );
						$sp_course_id = isset( $sp_row->course_id ) ? (int) $sp_row->course_id : 0;
						?>
						<tr>
							<td><strong><?php echo esc_html( $sp_row->title ); ?></strong></td>
							<td><code><?php echo esc_html( $sp_row->code ); ?></code></td>
							<td>
								<?php if ( $sp_course_id && isset( $course_map[ $sp_course_id ] ) ) : ?>
									<span class="sp-pill sp-pill-info"><?php echo esc_html( $course_map[ $sp_course_id ] ); ?></span>
								<?php else : ?>
									<span class="sp-admin-muted">—</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format_i18n( sp_count_class_students( $sp_row->id ) ) ); ?></td>
							<td>
								<?php if ( $sp_assigned ) : ?>
									<?php foreach ( $sp_assigned as $sp_teacher ) : ?>
										<div>
											<?php echo esc_html( $sp_teacher->full_name ); ?>
											<?php if ( $sp_teacher->is_primary ) : ?>
												<span class="sp-pill sp-pill-info">Lead</span>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								<?php else : ?>
									<span class="sp-admin-muted">None</span>
								<?php endif; ?>
							</td>
							<td><?php echo wp_kses_post( sp_admin_status_badge( $sp_row->status ) ); ?></td>
							<td>
								<span class="sp-admin-actions">
									<?php if ( 'pending' === (string) $sp_row->status ) : ?>
										<form method="post" class="sp-admin-inline">
											<?php sp_admin_nonce_field( 'approve_class' ); ?>
											<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
											<button type="submit" class="button button-small button-primary">Approve</button>
										</form>
									<?php endif; ?>
									<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'class', (int) $sp_row->id, admin_url( 'admin.php?page=sp-classes' ) ) ); ?>">Assign</a>
									<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'edit' => (int) $sp_row->id ), admin_url( 'admin.php?page=sp-classes' ) ) ); ?>">Edit</a>
									<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-enrollments&class=' . (int) $sp_row->id ) ); ?>">Enrolments</a>
									<form method="post" class="sp-admin-inline" onsubmit="return confirm('Delete this class and EVERYTHING inside it — enrolments, attendance registers, results, materials, assignments and calendar entries? This cannot be undone.');">
										<?php sp_admin_nonce_field( 'delete_class' ); ?>
										<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
										<button type="submit" class="button button-small">Delete</button>
									</form>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div>
	</div>

	<?php if ( $focus_data ) : ?>
		<div class="sp-admin-panel">
			<h2>Teachers for <?php echo esc_html( $focus_data->title ); ?></h2>

			<?php $sp_current = sp_get_class_teachers( $focus_data->id ); ?>

			<div class="sp-table-wrap">
			<table class="widefat striped">
				<thead>
					<tr><th>Teacher</th><th>Role</th><th></th></tr>
				</thead>
				<tbody>
					<?php if ( ! $sp_current ) : ?>
						<tr><td colspan="3">No teachers assigned yet.</td></tr>
					<?php endif; ?>

					<?php foreach ( $sp_current as $sp_teacher ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $sp_teacher->full_name ); ?></strong></td>
							<td><?php echo $sp_teacher->is_primary ? 'Lead teacher' : 'Teacher'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed strings. ?></td>
							<td>
								<form method="post" class="sp-admin-inline">
									<?php sp_admin_nonce_field( 'unassign_teacher' ); ?>
									<input type="hidden" name="teacher_id" value="<?php echo esc_attr( (int) $sp_teacher->id ); ?>" />
									<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $focus_data->id ); ?>" />
									<button type="submit" class="button button-small">Unassign</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>

			<?php if ( $teachers ) : ?>
				<form method="post">
					<?php sp_admin_nonce_field( 'assign_teacher' ); ?>
					<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $focus_data->id ); ?>" />

					<p>
						<select name="teacher_id">
							<?php foreach ( $teachers as $sp_teacher ) : ?>
								<option value="<?php echo esc_attr( (int) $sp_teacher->id ); ?>"><?php echo esc_html( $sp_teacher->full_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<label><input type="checkbox" name="is_primary" value="1" /> Lead teacher</label>
						<label><input type="checkbox" name="notify" value="1" checked /> Email the teacher</label>
						<?php submit_button( 'Assign', 'primary', 'submit', false ); ?>
					</p>
				</form>
			<?php else : ?>
				<p class="sp-admin-muted">
					Create a teacher on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-teachers' ) ); ?>">Teachers</a> screen first.
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php
}