<?php
/**
 * Add a single student.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The add/edit student form.
 *
 * Merged into the Student Accounts screen so account creation, the account
 * list and per-row actions live on one page. The old sp-add-student URL
 * redirects here, so bookmarks and older notices keep working.
 */
function sp_admin_render_student_form() {
	$classes = sp_get_classes( array( 'status' => 'active' ) );

	$edit_id   = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$editing   = $edit_id ? sp_get_profile_by_id( $edit_id ) : null;
	$missing   = ( $edit_id && ! $editing );
	$enrols    = $editing ? sp_get_enrollments_for_profile( $editing->id, array( 'active' ) ) : array();
	$dob_value = ( $editing && $editing->dob && '0000-00-00' !== $editing->dob ) ? (string) $editing->dob : '';
	?>
	<?php if ( $missing ) : ?>
		<div class="notice notice-error"><p>That student account no longer exists. It may have been removed.</p></div>
	<?php endif; ?>

	<div class="sp-admin-panel">
		<h2><?php echo $editing ? 'Edit student account' : 'New student account'; ?></h2>
		<?php if ( $editing ) : ?>
			<p class="sp-admin-muted">
				Changes apply to the portal login and the student's profile at once. Leave the password blank to
				keep the current one. Enrolment IDs are managed on the Enrollments screen, one per course.
			</p>
		<?php else : ?>
			<p class="sp-admin-muted">
				A random password is generated and emailed. If the email fails, use
				<em>Resend credentials</em> on the Student Accounts screen.
			</p>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data">
			<?php sp_admin_nonce_field( 'save_student' ); ?>
			<?php if ( $editing ) : ?>
				<input type="hidden" name="profile_id" value="<?php echo esc_attr( (int) $editing->id ); ?>" />
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="sp_email">Email address <span class="sp-required">*</span></label></th>
					<td><input type="email" id="sp_email" name="email" required class="regular-text"
						value="<?php echo esc_attr( $editing ? $editing->email : '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="sp_full_name">Full name <span class="sp-required">*</span></label></th>
					<td>
						<input type="text" id="sp_full_name" name="full_name" required class="regular-text"
							value="<?php echo esc_attr( $editing ? $editing->full_name : '' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sp_phone">Phone</label></th>
					<td><input type="text" id="sp_phone" name="phone" class="regular-text"
						value="<?php echo esc_attr( $editing && $editing->phone ? $editing->phone : '' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="sp_father_name">Father's name</label></th>
					<td>
						<input type="text" id="sp_father_name" name="father_name" class="regular-text"
							value="<?php echo esc_attr( $editing && ! empty( $editing->father_name ) ? $editing->father_name : '' ); ?>" />
						<p class="description">Printed on the student's ID card.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sp_address">Address</label></th>
					<td>
						<?php $sp_address_value = ( $editing && ! empty( $editing->address ) ) ? (string) $editing->address : ''; ?>
						<textarea id="sp_address" name="address" rows="2" maxlength="100" class="large-text"><?php echo esc_textarea( $sp_address_value ); ?></textarea>
						<p class="description">Printed in the Address row of the student's ID card.</p>
						<p class="description sp-address-count"><span data-sp-address-count><?php echo esc_html( (string) ( function_exists( 'mb_strlen' ) ? mb_strlen( $sp_address_value ) : strlen( $sp_address_value ) ) ); ?></span> / 100 characters</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sp_photo">Photo</label></th>
					<td>
						<input type="file" id="sp_photo" name="photo" accept="image/*" />
						<?php
						$sp_photo_url = ( $editing && ! empty( $editing->photo_id ) ) ? wp_get_attachment_image_url( (int) $editing->photo_id, 'thumbnail' ) : '';
						?>
						<?php if ( $sp_photo_url ) : ?>
							<p class="description">
								Current photo:
								<img src="<?php echo esc_url( $sp_photo_url ); ?>" alt=""
									style="width:44px;height:44px;object-fit:cover;border-radius:50%;vertical-align:middle;margin-left:6px;" />
							</p>
						<?php endif; ?>
						<p class="description">Shown inside the student's ID card. Leave empty to keep the current photo.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sp_dob">Date of birth</label></th>
					<td>
						<input type="date" id="sp_dob" name="dob" data-sp-date max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
							value="<?php echo esc_attr( $dob_value ); ?>" />
						<p class="description">Optional. Shown on the student's profile.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sp_password"><?php echo $editing ? 'New password' : 'Password'; ?></label></th>
					<td>
						<input type="text" id="sp_password" name="password" class="regular-text"
							autocomplete="new-password" value="" />
						<p class="description">
							<?php if ( $editing ) : ?>
								Leave blank to keep the current password. Filled in, it replaces it immediately.
							<?php else : ?>
								Optional. Leave it blank and a strong random password is generated and emailed.
							<?php endif; ?>
						</p>
					</td>
				</tr>

				<?php if ( ! $editing ) : ?>
					<tr>
						<th scope="row"><label for="sp_class">Enrol into class</label></th>
						<td>
							<select id="sp_class" name="class_id">
								<option value="0">No class</option>
								<?php foreach ( $classes as $sp_class ) : ?>
									<option value="<?php echo esc_attr( (int) $sp_class->id ); ?>">
										<?php echo esc_html( $sp_class->title . ( $sp_class->code ? ' (' . $sp_class->code . ')' : '' ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_enroll">Enrolment ID</label></th>
						<td>
							<input type="text" id="sp_enroll" name="enroll_id" class="regular-text" />
							<p class="description">
								Used for this course only. A student enrolled in a second class gets a second
								enrolment ID, set on the Enrollments screen.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sp_admission">Admission date</label></th>
						<td><input type="date" id="sp_admission" name="admission_date" /></td>
					</tr>
				<?php else : ?>
					<tr>
						<th scope="row">Enrolment</th>
						<td>
							<?php if ( $enrols ) : ?>
								<ul>
									<?php foreach ( $enrols as $sp_enrol ) : ?>
										<li>
											<strong><?php echo esc_html( $sp_enrol->class_title ); ?></strong>
											<?php if ( ! empty( $sp_enrol->class_code ) ) : ?>
												<span class="sp-admin-muted">(<?php echo esc_html( $sp_enrol->class_code ); ?>)</span>
											<?php endif; ?>
											<?php if ( ! empty( $sp_enrol->enroll_id ) ) : ?>
												<span class="sp-admin-muted">&mdash; ID <code><?php echo esc_html( $sp_enrol->enroll_id ); ?></code></span>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p class="sp-admin-muted">Not enrolled in a class yet.</p>
							<?php endif; ?>
							<p class="description">
								To enrol, move, drop or set a course enrolment ID, use the
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-enrollments' ) ); ?>">Enrollments</a> screen.
							</p>
						</td>
					</tr>
				<?php endif; ?>
			</table>

			<?php submit_button( $editing ? 'Save changes' : 'Create student account' ); ?>
		</form>

		<?php if ( $editing ) : ?>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-student-accounts' ) ); ?>">Back to student accounts</a>
			</p>
		<?php endif; ?>
	</div>

	<script>
	( function () {
		var field = document.getElementById( 'sp_address' );
		var count = document.querySelector( '[data-sp-address-count]' );

		if ( ! field || ! count ) {
			return;
		}

		var update = function () {
			count.textContent = String( field.value.length );
			count.parentNode.classList.toggle( 'is-near', field.value.length >= 90 );
		};

		field.addEventListener( 'input', update );
		update();
	} )();
	</script>
	<?php
}
