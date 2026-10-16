<?php
/**
 * Courses administration.
 *
 * Courses are portal-owned from schema 2.0: this screen is the only place they
 * are created, renamed or archived. A course's code is what the Certificate
 * Manager stamps into a certificate id, so duplicates are rejected here rather
 * than discovered on a certificate.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Courses screen.
 */
function sp_admin_page_sp_courses() {
	$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$editing = $edit_id ? sp_get_course( $edit_id ) : null;
	$courses = sp_get_courses();
	?>
	<div class="sp-admin-grid">
		<div class="sp-admin-panel">
			<h2><?php echo $editing ? 'Edit course' : 'Add a course'; ?></h2>

			<form method="post">
				<?php sp_admin_nonce_field( 'save_course' ); ?>
				<?php if ( $editing ) : ?>
					<input type="hidden" name="course_id" value="<?php echo esc_attr( (int) $editing->id ); ?>" />
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sp_c_name">Name <span class="sp-required">*</span></label></th>
						<td><input type="text" id="sp_c_name" name="name" required class="regular-text" value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_c_code">Code</label></th>
						<td>
							<input type="text" id="sp_c_code" name="code" class="regular-text" value="<?php echo esc_attr( $editing && $editing->code ? $editing->code : '' ); ?>" />
							<p class="description">
								Letters and digits, uppercased — for example <code>YOGA101</code>. It becomes part of every
								certificate id issued for this course. Leave blank to let the certificate fall back to <code>GEN</code>.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_c_duration">Duration</label></th>
						<td>
							<input type="text" id="sp_c_duration" name="duration" class="regular-text" value="<?php echo esc_attr( $editing && $editing->duration ? $editing->duration : '' ); ?>" placeholder="e.g. 12 weeks" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_c_status">Status</label></th>
						<td>
							<select id="sp_c_status" name="status">
								<option value="active" <?php selected( $editing ? $editing->status : 'active', 'active' ); ?>>Active</option>
								<option value="archived" <?php selected( $editing ? $editing->status : '', 'archived' ); ?>>Archived</option>
							</select>
							<p class="description">Archived courses stop appearing in the class form but are kept.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_c_desc">Description</label></th>
						<td><textarea id="sp_c_desc" name="description" rows="3" class="large-text"><?php echo esc_textarea( $editing && $editing->description ? $editing->description : '' ); ?></textarea></td>
					</tr>
				</table>

				<?php submit_button( $editing ? 'Save course' : 'Add course' ); ?>
			</form>

			<?php if ( $editing ) : ?>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-courses' ) ); ?>">Cancel editing</a>
				</p>
			<?php endif; ?>
		</div>

		<div class="sp-admin-panel">
			<h2>All courses</h2>

			<?php if ( ! $courses ) : ?>
				<div class="sp-empty">
					<strong>No courses yet.</strong>
					Add one on the left. Classes can then be taught from it, and its code is stamped onto the
					certificates they earn.
				</div>
			<?php else : ?>
				<div class="sp-table-wrap">
					<table class="widefat striped sp-table">
						<thead>
							<tr>
								<th scope="col">Name</th>
								<th scope="col">Code</th>
								<th scope="col">Duration</th>
								<th scope="col">Classes</th>
								<th scope="col">Status</th>
								<th scope="col"><span class="screen-reader-text">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $courses as $sp_row ) : ?>
								<?php $sp_used = sp_count_classes_for_course( (int) $sp_row->id ); ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $sp_row->name ); ?></strong>
										<?php if ( $sp_row->description ) : ?>
											<div class="sp-admin-muted"><?php echo esc_html( wp_trim_words( $sp_row->description, 18 ) ); ?></div>
										<?php endif; ?>
									</td>
									<td><code><?php echo esc_html( $sp_row->code ? $sp_row->code : '—' ); ?></code></td>
									<td><?php echo $sp_row->duration ? esc_html( $sp_row->duration ) : '<span class="sp-admin-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?></td>
									<td><?php echo esc_html( number_format_i18n( $sp_used ) ); ?></td>
									<td><?php echo wp_kses_post( sp_admin_status_badge( $sp_row->status ) ); ?></td>
									<td>
										<span class="sp-admin-actions">
											<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit', (int) $sp_row->id, admin_url( 'admin.php?page=sp-courses' ) ) ); ?>">Edit</a>
											<form method="post" class="sp-admin-inline">
												<?php sp_admin_nonce_field( 'delete_course' ); ?>
												<input type="hidden" name="course_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
												<button type="submit" class="button button-small" <?php echo $sp_used ? 'disabled title="A class still teaches this course"' : ''; ?>>
													Delete
												</button>
											</form>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $editing ) :
		$sp_items   = sp_get_curriculum( (int) $editing->id );
		$sp_item_id = isset( $_GET['curriculum'] ) ? (int) $_GET['curriculum'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sub-editor selection.
		$sp_item    = $sp_item_id ? sp_get_curriculum_item( $sp_item_id ) : null;

		// Never render an item that belongs to a different course's outline.
		if ( $sp_item && (int) $sp_item->course_id !== (int) $editing->id ) {
			$sp_item = null;
		}
		?>
		<div class="sp-admin-panel">
			<h2>Course outline</h2>
			<p class="sp-admin-intro">
				The ordered list of topics this course covers. Every class teaching the course shows the same
				outline, and each class ticks topics off as it covers them.
			</p>

			<form method="post">
				<?php sp_admin_nonce_field( 'save_curriculum' ); ?>
				<input type="hidden" name="course_id" value="<?php echo esc_attr( (int) $editing->id ); ?>" />
				<?php if ( $sp_item ) : ?>
					<input type="hidden" name="curriculum_id" value="<?php echo esc_attr( (int) $sp_item->id ); ?>" />
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sp_ci_title">Topic <span class="sp-required">*</span></label></th>
						<td>
							<input type="text" id="sp_ci_title" name="title" required class="regular-text"
								value="<?php echo esc_attr( $sp_item ? $sp_item->title : '' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_ci_detail">Detail</label></th>
						<td>
							<textarea id="sp_ci_detail" name="detail" rows="2" class="large-text"><?php echo esc_textarea( $sp_item && $sp_item->detail ? $sp_item->detail : '' ); ?></textarea>
							<p class="description">Optional: what this topic covers, reading material, and so on.</p>
						</td>
					</tr>
				</table>

				<?php submit_button( $sp_item ? 'Save topic' : 'Add topic' ); ?>
			</form>

			<?php if ( $sp_item ) : ?>
				<p>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'edit', (int) $editing->id, admin_url( 'admin.php?page=sp-courses' ) ) ); ?>">Cancel editing</a>
				</p>
			<?php endif; ?>

			<div class="sp-outline-import">
				<h3>Import topics from CSV</h3>
				<form method="post" enctype="multipart/form-data">
					<?php sp_admin_nonce_field( 'import_curriculum' ); ?>
					<input type="hidden" name="course_id" value="<?php echo esc_attr( (int) $editing->id ); ?>" />

					<p>
						<input type="file" name="import_file" accept=".csv,text/csv" required />
						<?php submit_button( 'Import topics', 'secondary', 'submit', false ); ?>
					</p>

					<p class="description">
						One topic per row: <code>title</code> (required) and <code>detail</code> (optional).
						An optional header row labelled <code>title</code>/<code>topic</code>/<code>name</code>
						is skipped, and rows are appended after the existing outline in file order.
					</p>
				</form>
			</div>

			<?php if ( ! $sp_items ) : ?>
				<div class="sp-empty">
					<strong>No topics yet.</strong>
					Add the first one above — classes tick topics off as they cover them.
				</div>
			<?php else : ?>
				<div class="sp-table-wrap">
					<table class="widefat striped sp-table">
						<thead>
							<tr>
								<th scope="col">#</th>
								<th scope="col">Topic</th>
								<th scope="col"><span class="screen-reader-text">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $sp_items as $sp_index => $sp_row ) : ?>
								<tr>
									<td><?php echo esc_html( number_format_i18n( $sp_index + 1 ) ); ?></td>
									<td>
										<strong><?php echo esc_html( $sp_row->title ); ?></strong>
										<?php if ( $sp_row->detail ) : ?>
											<div class="sp-admin-muted"><?php echo esc_html( wp_trim_words( $sp_row->detail, 18 ) ); ?></div>
										<?php endif; ?>
									</td>
									<td>
										<span class="sp-admin-actions">
											<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'edit' => (int) $editing->id, 'curriculum' => (int) $sp_row->id ), admin_url( 'admin.php?page=sp-courses' ) ) ); ?>">Edit</a>
											<form method="post" class="sp-admin-inline">
												<?php sp_admin_nonce_field( 'move_curriculum' ); ?>
												<input type="hidden" name="item_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
												<input type="hidden" name="direction" value="up" />
												<button type="submit" class="button button-small" <?php disabled( 0 === $sp_index ); ?>>Up</button>
											</form>
											<form method="post" class="sp-admin-inline">
												<?php sp_admin_nonce_field( 'move_curriculum' ); ?>
												<input type="hidden" name="item_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
												<input type="hidden" name="direction" value="down" />
												<button type="submit" class="button button-small" <?php disabled( $sp_index === count( $sp_items ) - 1 ); ?>>Down</button>
											</form>
											<form method="post" class="sp-admin-inline" onsubmit="return confirm('Delete this topic? Any class coverage recorded against it goes too.');">
												<?php sp_admin_nonce_field( 'delete_curriculum' ); ?>
												<input type="hidden" name="item_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
												<button type="submit" class="button button-small">Delete</button>
											</form>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php
}
