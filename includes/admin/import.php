<?php
/**
 * CSV import screen, shared by students and teachers.
 *
 * The two imports are the same workflow with different columns, so they live
 * behind one menu entry with a tab rather than as two screens that drift apart.
 * Each type keeps its own plan transient, so a preview only ever appears on the
 * tab it was produced for.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import screen: tab switch plus the active import type.
 */
function sp_admin_page_sp_import() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'students';

	if ( ! in_array( $tab, array( 'students', 'teachers' ), true ) ) {
		$tab = 'students';
	}

	$students_url = admin_url( 'admin.php?page=sp-import' );
	$teachers_url = add_query_arg( 'tab', 'teachers', admin_url( 'admin.php?page=sp-import' ) );
	?>
	<div class="nav-tab-wrapper">
		<a class="nav-tab <?php echo 'students' === $tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $students_url ); ?>"
			<?php echo 'students' === $tab ? 'aria-current="page"' : ''; ?>>Students</a>
		<a class="nav-tab <?php echo 'teachers' === $tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $teachers_url ); ?>"
			<?php echo 'teachers' === $tab ? 'aria-current="page"' : ''; ?>>Teachers</a>
	</div>
	<?php

	sp_admin_render_import( 'teachers' === $tab ? 'teacher' : 'student' );
}

/**
 * Render the import workflow for one record type.
 *
 * @param string $type 'student' or 'teacher'.
 */
function sp_admin_render_import( $type ) {
	$is_student = ( 'student' === $type );
	$plan       = get_transient( sp_import_plan_key( $type ) );
	$action     = $is_student ? 'import_students' : 'import_teachers';
	$tab        = $is_student ? 'students' : 'teachers';
	$columns    = $is_student ? 'name, email, phone, enroll_id, class_code, admission_date' : 'name, email, phone';
	$sample     = $is_student ? 'students' : 'teachers';
	?>
	<?php if ( $is_student ) : ?>
		<div class="sp-admin-panel">
			<h2>Expected columns</h2>
			<p><code><?php echo esc_html( $columns ); ?></code></p>
			<p>
				<code>name</code> and <code>email</code> are required. <code>enroll_id</code> sets the student's
				enrolment ID on the class they join. <code>class_code</code> matches the class code and enrols them
				in one step. Extra columns are ignored.
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'sp_sample', $sample, admin_url( 'admin.php' ) ) ); ?>">Download sample CSV</a>
			</p>
		</div>
	<?php else : ?>
		<div class="sp-admin-panel">
			<h2>Expected columns</h2>
			<p><code><?php echo esc_html( $columns ); ?></code></p>
			<p><code>name</code> and <code>email</code> are required. Extra columns are ignored.</p>
			<p>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'sp_sample', $sample, admin_url( 'admin.php' ) ) ); ?>">Download sample CSV</a>
			</p>
		</div>
	<?php endif; ?>

	<div class="sp-admin-panel">
		<h2>Upload</h2>

		<form method="post" enctype="multipart/form-data">
			<?php sp_admin_nonce_field( $action ); ?>
			<input type="hidden" name="step" value="plan" />

			<p>
				<input type="file" name="import_file" accept=".csv,.txt" required />
			</p>

			<?php submit_button( 'Preview import', 'primary', 'submit', false ); ?>
		</form>

		<p class="sp-admin-muted">Nothing is written until you confirm the preview.</p>
	</div>

	<?php if ( is_array( $plan ) && ! empty( $plan ) ) : ?>
		<?php
		$create = 0;
		$skip   = 0;
		$errors = 0;

		foreach ( $plan as $sp_row ) {
			if ( 'create' === $sp_row['action'] ) {
				++$create;
			} elseif ( 'skip' === $sp_row['action'] ) {
				++$skip;
			} else {
				++$errors;
			}
		}
		?>

		<div class="sp-admin-panel">
			<h2>Preview</h2>

			<p>
				<span class="sp-pill sp-pill-good"><?php echo esc_html( $create ); ?> will be created</span>
				<span class="sp-pill sp-pill-muted"><?php echo esc_html( $skip ); ?> skipped</span>
			<span class="sp-pill sp-pill-bad"><?php echo esc_html( $errors ); ?> problems</span>
		</p>

		<div class="sp-table-wrap">
		<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col">Line</th>
						<th scope="col">Action</th>
						<th scope="col">Name</th>
						<th scope="col">Email</th>
						<th scope="col">Detail</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plan as $sp_row ) : ?>
						<tr>
							<td><?php echo esc_html( $sp_row['line'] ); ?></td>
							<td><?php echo wp_kses_post( sp_admin_status_badge( $sp_row['action'] ) ); ?></td>
							<td><?php echo esc_html( $sp_row['name'] ); ?></td>
							<td><?php echo esc_html( $sp_row['email'] ); ?></td>
							<td><?php echo esc_html( $sp_row['detail'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>

			<form method="post">
				<?php sp_admin_nonce_field( $action ); ?>
				<input type="hidden" name="step" value="commit" />

				<p>
					<?php submit_button( sprintf( 'Create %d account(s)', $create ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		</div>
	<?php endif; ?>
	<?php
}
