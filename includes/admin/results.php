<?php
/**
 * Results administration.
 *
 * Teachers normally enter marks from the teacher portal. This screen is the
 * administrative view: review, publish and unpublish, and issue certificates as
 * soon as a passing grade exists.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Results screen.
 */
function sp_admin_page_sp_results() {
	// Pending (teacher-created, unapproved) classes are not enterable yet —
	// approval on the Classes screen is what unlocks them.
	$classes = array_values(
		array_filter(
			sp_get_classes( array( 'per_page' => 200 ) ),
			function ( $sp_row ) {
				return 'pending' !== (string) $sp_row->status;
			}
		)
	);

	if ( ! $classes ) {
		echo '<div class="sp-empty"><strong>No classes yet.</strong> Create a class first, then come back to enter marks for its students.</div>';
		return;
	}

	$class_id = isset( $_GET['class'] ) ? (int) $_GET['class'] : (int) $classes[0]->id; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$class    = sp_get_class( $class_id );

	if ( ! $class ) {
		$class = $classes[0];
	}

	$rows      = sp_get_class_results( (int) $class->id );
	$cert_form = isset( $_GET['cert_form'] ) ? (int) $_GET['cert_form'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$issuable  = 0;
	$issued    = 0;

	foreach ( $rows as $sp_scan ) {
		if ( ! $sp_scan->enrollment_id ) {
			continue;
		}

		$sp_state = sp_certificate_state_for_enrollment( (int) $sp_scan->enrollment_id );

		if ( $sp_state['generated'] ) {
			++$issued;
		} elseif ( $sp_state['can_generate'] ) {
			++$issuable;
		}
	}
	?>
	<form method="get" class="sp-admin-search">
		<input type="hidden" name="page" value="sp-results" />
		<label for="sp-results-class">Class</label>
		<select name="class" id="sp-results-class">
			<?php foreach ( $classes as $sp_row ) : ?>
				<option value="<?php echo esc_attr( (int) $sp_row->id ); ?>" <?php selected( (int) $class->id, (int) $sp_row->id ); ?>>
					<?php echo esc_html( $sp_row->title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button">Show</button>
	</form>

	<?php
	if ( $cert_form ) {
		sp_admin_render_certificate_form( $cert_form, (int) $class->id );
	}
	?>

	<div class="sp-notice sp-notice-info">
		<strong>Certificates</strong> A published passing result plus at least
		<?php echo esc_html( sp_format_number( sp_certificate_min_attendance() ) ); ?>% class attendance is all that is
		needed. Issuing opens a form with the fields defined under
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-settings&tab=certificates' ) ); ?>">Settings &rarr; Certificates</a>
		— name, enrolment ID, grade, marks and dates arrive filled in, and anything else is yours to type.
		<?php if ( $issuable > 0 ) : ?>
			<form method="post" class="sp-submit">
				<?php sp_admin_nonce_field( 'generate_certificates_bulk' ); ?>
				<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $class->id ); ?>" />
				<button type="submit" class="button button-primary">
					Quick-issue all <?php echo esc_html( number_format_i18n( $issuable ) ); ?> pending certificate(s)
				</button>
				<span class="sp-admin-muted"><?php echo esc_html( number_format_i18n( $issued ) ); ?> already issued.</span>
			</form>
		<?php endif; ?>
	</div>

	<div class="sp-admin-panel">
		<h2><?php echo esc_html( $class->title ); ?>
			<span class="sp-admin-muted">
				<?php
				printf(
					/* translators: %s: number of students enrolled in the class. */
					esc_html( _n( '%s student', '%s students', count( $rows ), 'student-portal' ) ),
					esc_html( number_format_i18n( count( $rows ) ) )
				);
				?>
			</span>
		</h2>

		<?php if ( ! $rows ) : ?>
			<div class="sp-empty">
				<strong>No students enrolled in this class yet.</strong>
				Enrol students from the
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-enrollments&class=' . (int) $class->id ) ); ?>">Enrollments screen</a>,
				or ask a teacher to enter their first marks.
			</div>
		<?php else : ?>
		<div class="sp-table-wrap">
		<table class="widefat striped sp-table">
			<thead>
				<tr>
					<th scope="col">Student</th>
					<th scope="col">Theory</th>
					<th scope="col">Practical</th>
					<th scope="col">Percentage</th>
					<th scope="col">Grade</th>
					<th scope="col">Result</th>
					<th scope="col">Status</th>
					<th scope="col">Certificate</th>
					<th scope="col"><span class="screen-reader-text">Actions</span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $sp_row ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $sp_row->full_name ); ?></strong>
							<span class="sp-admin-muted"><?php echo esc_html( $sp_row->enroll_id ); ?></span>
						</td>
						<td>
							<?php
							echo $sp_row->result_id
								? esc_html( sp_format_number( $sp_row->theory_obtained ) . ' / ' . sp_format_number( $sp_row->theory_max ) )
								: '<span class="sp-admin-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary.
							?>
						</td>
						<td>
							<?php
							echo $sp_row->result_id
								? esc_html( sp_format_number( $sp_row->practical_obtained ) . ' / ' . sp_format_number( $sp_row->practical_max ) )
								: '<span class="sp-admin-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary.
							?>
						</td>
						<td><?php echo $sp_row->percentage !== null ? esc_html( sp_format_number( $sp_row->percentage ) . '%' ) : '<span class="sp-admin-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?></td>
						<td><?php echo $sp_row->grade ? esc_html( $sp_row->grade ) : '<span class="sp-admin-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?></td>
						<td>
							<?php
							echo $sp_row->result_id
								? wp_kses_post( sp_result_badge( $sp_row->result ) )
								: '<span class="sp-admin-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped branch.
							?>
						</td>
						<td>
							<?php
							if ( $sp_row->result_id ) {
								echo wp_kses_post( sp_admin_status_badge( $sp_row->finalised ? 'active' : 'pending' ) );
							} else {
								echo '<span class="sp-admin-muted">Not entered</span>';
							}
							?>
						</td>
						<td>
							<?php
							/*
							 * A published passing result is enough to open the form.
							 * The state helper explains what is blocking otherwise.
							 */
							$sp_cert = $sp_row->enrollment_id
								? sp_certificate_state_for_enrollment( (int) $sp_row->enrollment_id )
								: array( 'can_generate' => false, 'reason' => '', 'generated' => false, 'serial' => '' );

							if ( $sp_cert['generated'] ) :
								$sp_print_url = sp_certificate_vendor_url( (int) $sp_cert['certificate_id'] );
								?>
								<div class="sp-cert-cell">
									<a class="button button-small"
										href="<?php echo esc_url( add_query_arg( array( 'class' => (int) $class->id, 'cert_form' => (int) $sp_row->enrollment_id ), admin_url( 'admin.php?page=sp-results' ) ) ); ?>">View</a>
									<?php if ( ! is_wp_error( $sp_print_url ) ) : ?>
										<a class="button button-small" target="_blank" rel="noopener"
											href="<?php echo esc_url( $sp_print_url ); ?>">Print</a>
									<?php endif; ?>
								</div>
								<?php elseif ( $sp_cert['can_generate'] ) : ?>
								<a class="button button-small button-primary"
									href="<?php echo esc_url( add_query_arg( array( 'class' => (int) $class->id, 'cert_form' => (int) $sp_row->enrollment_id ), admin_url( 'admin.php?page=sp-results' ) ) ); ?>">Issue certificate</a>
							<?php elseif ( '' !== $sp_cert['reason'] ) : ?>
								<span class="sp-admin-muted" title="<?php echo esc_attr( $sp_cert['reason'] ); ?>">&mdash;</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $sp_row->result_id ) : ?>
								<form method="post" class="sp-admin-inline">
									<?php sp_admin_nonce_field( 'finalise_result' ); ?>
									<input type="hidden" name="result_id" value="<?php echo esc_attr( (int) $sp_row->result_id ); ?>" />
									<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( (int) $sp_row->enrollment_id ); ?>" />
									<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $class->id ); ?>" />
									<button type="submit" class="button button-small">
										<?php echo (int) $sp_row->finalised ? 'Unpublish' : 'Publish'; ?>
									</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php endif; ?>

		<p class="sp-admin-muted">
			Marks are entered by teachers from the teacher portal. Publishing makes the result visible to the
			student and emails them. Certificates can be issued once the grade is a pass and attendance reaches
			<?php echo esc_html( sp_format_number( sp_certificate_min_attendance() ) ); ?>%.
		</p>
	</div>
	<?php
}

