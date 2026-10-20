<?php
/**
 * Student dashboard.
 *
 * The mockup's shape: a row of colourful course tiles, then the enrolment
 * table with start date, attendance and result, with the rail alongside
 * carrying profile, calendar and progress (see portal-layout-end.php).
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $profile ) {
	$profile = sp_get_profile_by_user_id( get_current_user_id() );
}

if ( ! $profile ) {
	?>
	<div class="sp-notice sp-notice-warn">
		This WordPress account is not linked to a student profile yet. Ask your institute to create your portal account.
	</div>
	<?php
	return;
}

$sp_enrollments  = sp_get_enrollments_for_profile( $profile->id, array( 'active' ) );
$sp_materials    = sp_get_student_materials( $profile->id, 4 );
$sp_welcome      = $profile->full_name ? $profile->full_name : $profile->email;
?>
<div class="sp-page-head">
	<p class="sp-page-sub">
		Welcome back, <strong><?php echo esc_html( $sp_welcome ); ?></strong>
		<?php if ( $sp_enrollments && ! empty( $sp_enrollments[0]->enroll_id ) ) : ?>
			&middot; Enrolment ID <strong><?php echo esc_html( $sp_enrollments[0]->enroll_id ); ?></strong>
		<?php endif; ?>
	</p>
</div>

<div class="sp-section-head">
	<h2 class="sp-section-title">Active courses</h2>
</div>

<?php if ( ! $sp_enrollments ) : ?>
	<div class="sp-empty">You are not enrolled in any course yet.</div>
<?php else : ?>
	<div class="sp-hero-grid">
		<?php
		$sp_tile_colors = array( 'is-orange', 'is-violet', 'is-blue' );
		$sp_tile_i      = 0;

		foreach ( array_slice( $sp_enrollments, 0, 3 ) as $sp_enrollment ) :
			$sp_tile_stats  = sp_get_attendance_stats( $profile->id, (int) $sp_enrollment->class_id );
			$sp_tile_cov    = sp_class_curriculum_summary( (int) $sp_enrollment->class_id );
			$sp_tile_color  = $sp_tile_colors[ $sp_tile_i % count( $sp_tile_colors ) ];
			$sp_tile_monogram = $sp_enrollment->class_code
				? mb_strtoupper( mb_substr( $sp_enrollment->class_code, 0, 2 ) )
				: mb_strtoupper( mb_substr( $sp_enrollment->class_title, 0, 2 ) );
			++$sp_tile_i;
			?>
			<a class="sp-hero-tile <?php echo esc_attr( $sp_tile_color ); ?>"
				href="<?php echo esc_url( add_query_arg( 'class', (int) $sp_enrollment->class_id, sp_portal_url( 'curriculum' ) ) ); ?>">
				<span class="sp-hero-art" aria-hidden="true">
					<span class="sp-hero-monogram"><?php echo esc_html( $sp_tile_monogram ); ?></span>
				</span>
				<strong class="sp-hero-title"><?php echo esc_html( $sp_enrollment->class_title ); ?></strong>
				<span class="sp-hero-meta"><?php echo esc_html( number_format_i18n( (int) $sp_tile_stats['total'] ) ); ?> sessions held</span>
				<?php if ( $sp_tile_cov['total'] > 0 ) : ?>
					<span class="sp-hero-meta sp-hero-progress">
						<?php echo esc_html( $sp_tile_cov['done'] . '/' . $sp_tile_cov['total'] ); ?> topics &middot;
						<?php echo esc_html( (int) $sp_tile_cov['percent'] ); ?>% complete
					</span>
					<span class="sp-hero-meter" style="aria-hidden="true" >
						<span style="width: <?php echo esc_attr( $sp_tile_cov['percent'] ); ?>%"></span>
					</span>
				<?php endif; ?>
				<span class="sp-hero-go" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6" /></svg>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<div class="sp-section-head">
	<h2 class="sp-section-title">My courses</h2>
	<a class="sp-section-link" href="<?php echo esc_url( sp_portal_url( 'curriculum' ) ); ?>">View All</a>
</div>

<?php if ( ! $sp_enrollments ) : ?>
	<div class="sp-empty">Your courses, attendance and results will appear here once you are enrolled.</div>
<?php else : ?>
	<div class="sp-table-wrap">
		<table class="sp-table sp-courses-table">
			<thead>
				<tr>
					<th scope="col">Course name</th>
					<th scope="col">Start</th>
					<th scope="col">Attendance</th>
					<th scope="col">Completion</th>
					<th scope="col">Result</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sp_enrollments as $sp_enrollment ) : ?>
					<?php
					$sp_row_stats  = sp_get_attendance_stats( $profile->id, (int) $sp_enrollment->class_id );
					$sp_row_cov    = sp_class_curriculum_summary( (int) $sp_enrollment->class_id );
					$sp_row_result = sp_get_result_for_enrollment( (int) $sp_enrollment->id );
					$sp_row_monogram = $sp_enrollment->class_code
						? mb_strtoupper( mb_substr( $sp_enrollment->class_code, 0, 2 ) )
						: mb_strtoupper( mb_substr( $sp_enrollment->class_title, 0, 2 ) );
					?>
					<tr>
						<th scope="row">
							<a class="sp-course-cell" href="<?php echo esc_url( add_query_arg( 'class', (int) $sp_enrollment->class_id, sp_portal_url( 'curriculum' ) ) ); ?>">
								<span class="sp-course-icon" aria-hidden="true"><?php echo esc_html( $sp_row_monogram ); ?></span>
								<span class="sp-course-name">
									<strong><?php echo esc_html( $sp_enrollment->class_title ); ?></strong>
									<span class="sp-muted">
										<?php echo esc_html( number_format_i18n( (int) $sp_row_stats['total'] ) ); ?> sessions
										<?php if ( $sp_enrollment->class_code ) : ?>
											&middot; <?php echo esc_html( $sp_enrollment->class_code ); ?>
										<?php endif; ?>
									</span>
								</span>
							</a>
						</th>
						<td data-label="Start">
							<?php if ( $sp_enrollment->class_start ) : ?>
								<?php echo esc_html( sp_portal_time( get_option( 'date_format' ), strtotime( $sp_enrollment->class_start ) ) ); ?>
							<?php else : ?>
								<span class="sp-muted">&mdash;</span>
							<?php endif; ?>
						</td>
					<td data-label="Attendance">
						<?php echo wp_kses_post( sp_attendance_badge( $sp_row_stats['percentage'] ) ); ?>
						<span class="sp-kv-note"><?php echo esc_html( $sp_row_stats['present'] . '/' . $sp_row_stats['total'] ); ?></span>
					</td>
					<td data-label="Completion">
						<?php if ( $sp_row_cov['total'] > 0 ) : ?>
							<div class="sp-meter">
								<div class="sp-meter-bar">
									<span style="width: <?php echo esc_attr( $sp_row_cov['percent'] ); ?>%"></span>
								</div>
								<span class="sp-meter-value"><?php echo esc_html( (int) $sp_row_cov['percent'] ); ?>%</span>
							</div>
							<span class="sp-kv-note"><?php echo esc_html( $sp_row_cov['done'] . '/' . $sp_row_cov['total'] ); ?> topics covered</span>
						<?php else : ?>
							<span class="sp-muted">No outline yet</span>
						<?php endif; ?>
					</td>
						<td data-label="Result">
							<?php if ( $sp_row_result && (int) $sp_row_result->finalised ) : ?>
								<?php echo wp_kses_post( sp_result_badge( $sp_row_result->result ) ); ?>
								<span class="sp-kv-note"><?php echo esc_html( sp_format_number( $sp_row_result->percentage ) . '%' ); ?></span>
							<?php else : ?>
								<span class="sp-muted">Not published yet</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<h2 class="sp-section-title">Recent materials</h2>

<?php if ( ! $sp_materials ) : ?>
	<div class="sp-empty">Nothing published yet. Your teachers will add notes and documents here.</div>
<?php else : ?>
	<ul class="sp-list">
		<?php foreach ( $sp_materials as $sp_material ) : ?>
			<li class="sp-list-item">
				<div>
					<strong><?php echo esc_html( $sp_material->title ); ?></strong>
					<span class="sp-muted"><?php echo esc_html( $sp_material->class_title ); ?></span>
				</div>
				<?php // Both kinds are shown inline on /portal/materials/, so this is just a pointer there. ?>
				<a class="sp-btn sp-btn-ghost" href="<?php echo esc_url( sp_portal_url( 'materials' ) . '#material-' . (int) $sp_material->id ); ?>">View</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
