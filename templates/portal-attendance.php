<?php
/**
 * Student attendance.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $profile ) ) {
	?>
	<div class="sp-notice sp-notice-warn">
		This WordPress account is not linked to a student profile yet. Ask your institute to create your portal account.
	</div>
	<?php
	return;
}

$sp_enrollments = sp_get_enrollments_for_profile( $profile->id, array( 'active', 'completed' ) );
$sp_view_id    = isset( $_GET['class'] ) ? (int) $_GET['class'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
$sp_history    = array();
$sp_stats      = null;
$sp_class   = null;

foreach ( $sp_enrollments as $sp_candidate ) {
	if ( (int) $sp_candidate->class_id === $sp_view_id ) {
		$sp_class = $sp_candidate;
		break;
	}
}

if ( ! $sp_class && $sp_enrollments ) {
	$sp_class = $sp_enrollments[0];
}

if ( $sp_class ) {
	$sp_stats   = sp_get_attendance_stats( $profile->id, (int) $sp_class->class_id );
	$sp_history = sp_get_attendance_history( $profile->id, (int) $sp_class->class_id, 90 );
}
?>
<div class="sp-page-head">
	
	<p class="sp-page-sub">Your record for each course.</p>
</div>

<?php if ( ! $sp_enrollments ) : ?>
	<div class="sp-empty">You are not enrolled in any course yet.</div>
<?php else : ?>
	<?php if ( count( $sp_enrollments ) > 1 ) : ?>
		<div class="sp-tabs">
			<?php foreach ( $sp_enrollments as $sp_tab ) : ?>
				<a
					class="sp-tab<?php echo ( $sp_class && (int) $sp_tab->class_id === (int) $sp_class->class_id ) ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( 'class', (int) $sp_tab->class_id, sp_portal_url( 'attendance' ) ) ); ?>"
				><?php echo esc_html( $sp_tab->class_title ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $sp_stats ) : ?>
		<div class="sp-card">
			<h2 class="sp-card-title"><?php echo esc_html( $sp_class->class_title ); ?></h2>

			<div class="sp-meter">
				<div class="sp-meter-bar">
					<span style="width: <?php echo esc_attr( min( 100, max( 0, $sp_stats['percentage'] ) ) ); ?>%"></span>
				</div>
				<span class="sp-meter-value"><?php echo wp_kses_post( sp_attendance_badge( $sp_stats['percentage'] ) ); ?></span>
			</div>

			<ul class="sp-chips">
				<li class="sp-chip sp-chip-good">Present <strong><?php echo esc_html( $sp_stats['present'] ); ?></strong></li>
				<li class="sp-chip sp-chip-bad">Absent <strong><?php echo esc_html( $sp_stats['absent'] ); ?></strong></li>
				<li class="sp-chip sp-chip-warn">Late <strong><?php echo esc_html( $sp_stats['late'] ); ?></strong></li>
				<li class="sp-chip">Excused <strong><?php echo esc_html( $sp_stats['excused'] ); ?></strong></li>
				<li class="sp-chip">Sessions <strong><?php echo esc_html( $sp_stats['total'] ); ?></strong></li>
			</ul>
		</div>

		<h2 class="sp-section-title">Class History</h2>

		<?php if ( ! $sp_history ) : ?>
			<div class="sp-empty">No sessions recorded yet.</div>
		<?php else : ?>
			<div class="sp-table-wrap">
				<table class="sp-table">
					<thead>
						<tr>
							<th scope="col">Date</th>
							<th scope="col">Topic</th>
							<th scope="col">Status</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $sp_history as $sp_row ) :
							$sp_class = sp_attendance_row_class( $sp_row->status );
							?>
							<tr>
								<td data-label="Date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_row->session_date ) ) ); ?></td>
								<td data-label="Topic"><?php echo $sp_row->topic ? esc_html( $sp_row->topic ) : '<span class="sp-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ternary with escaped branches. ?></td>
								<td data-label="Status"><span class="sp-badge <?php echo esc_attr( $sp_class ); ?>"><?php echo esc_html( ucfirst( $sp_row->status ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	<?php endif; ?>
<?php endif; ?>