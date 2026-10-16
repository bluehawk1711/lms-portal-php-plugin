<?php
/**
 * Student curriculum: the outline of each enrolled class and what it has covered.
 *
 * Read-only: the coverage list is ticked by the class's teachers. The layout
 * mirrors the attendance screen — per-class tabs for students in more than one
 * class — so the selector behaves the same way everywhere.
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
$sp_view_id     = isset( $_GET['class'] ) ? (int) $_GET['class'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.
$sp_enrollment  = null;

foreach ( $sp_enrollments as $sp_candidate ) {
	if ( (int) $sp_candidate->class_id === $sp_view_id ) {
		$sp_enrollment = $sp_candidate;
		break;
	}
}

if ( ! $sp_enrollment && $sp_enrollments ) {
	$sp_enrollment = $sp_enrollments[0];
}

$sp_course   = null;
$sp_items    = array();
$sp_progress = array();
$sp_summary  = array( 'total' => 0, 'done' => 0, 'percent' => 0 );

if ( $sp_enrollment ) {
	$sp_class_row = sp_get_class( (int) $sp_enrollment->class_id );

	if ( $sp_class_row && $sp_class_row->course_id ) {
		$sp_course = sp_get_course( (int) $sp_class_row->course_id );
	}

	if ( $sp_course ) {
		$sp_items    = sp_get_curriculum( (int) $sp_course->id );
		$sp_progress = sp_get_class_progress( (int) $sp_enrollment->class_id );
		$sp_summary  = sp_curriculum_summary( (int) $sp_course->id, (int) $sp_enrollment->class_id );
	}
}
?>
<div class="sp-page-head">
	
	<p class="sp-page-sub">The topics your class covers, and how far it has got.</p>
</div>

<?php if ( ! $sp_enrollments ) : ?>
	<div class="sp-empty">You are not enrolled in any course yet.</div>
<?php else : ?>
	<?php if ( count( $sp_enrollments ) > 1 ) : ?>
		<div class="sp-tabs">
			<?php foreach ( $sp_enrollments as $sp_tab ) : ?>
				<a
					class="sp-tab<?php echo $sp_enrollment && (int) $sp_tab->class_id === (int) $sp_enrollment->class_id ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( 'class', (int) $sp_tab->class_id, sp_portal_url( 'curriculum' ) ) ); ?>"
				><?php echo esc_html( $sp_tab->class_title ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! $sp_course ) : ?>
		<div class="sp-empty">No course outline applies to this class yet.</div>
	<?php elseif ( ! $sp_items ) : ?>
		<div class="sp-empty">The outline for this course has not been published yet. Check back soon.</div>
	<?php else : ?>
		<div class="sp-card">
			<h2 class="sp-card-title"><?php echo esc_html( $sp_course->name ); ?></h2>

			<div class="sp-meter">
				<div class="sp-meter-bar">
					<span style="width: <?php echo esc_attr( $sp_summary['percent'] ); ?>%"></span>
				</div>
				<span class="sp-meter-value"><?php echo esc_html( $sp_summary['done'] . ' / ' . $sp_summary['total'] . ' · ' . $sp_summary['percent'] . '%' ); ?></span>
			</div>

			<ul class="sp-chips">
				<li class="sp-chip sp-chip-good">Covered <strong><?php echo esc_html( $sp_summary['done'] ); ?></strong></li>
				<li class="sp-chip">Remaining <strong><?php echo esc_html( $sp_summary['total'] - $sp_summary['done'] ); ?></strong></li>
				<li class="sp-chip">Topics <strong><?php echo esc_html( $sp_summary['total'] ); ?></strong></li>
			</ul>
		</div>

		<h2 class="sp-section-title">Topics</h2>

		<div class="sp-card-list">
			<?php foreach ( $sp_items as $sp_index => $sp_item ) : ?>
				<?php $sp_marked = isset( $sp_progress[ (int) $sp_item->id ] ); ?>
				<article class="sp-card">
					<h2 class="sp-card-title"><?php echo esc_html( ( $sp_index + 1 ) . '. ' . $sp_item->title ); ?></h2>

					<?php if ( $sp_item->detail ) : ?>
						<p class="sp-muted"><?php echo nl2br( esc_html( $sp_item->detail ) ); ?></p>
					<?php endif; ?>

					<p class="sp-card-meta">
						<?php if ( $sp_marked ) : ?>
							<span class="sp-badge sp-badge-good">Covered</span>
							<span><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_progress[ (int) $sp_item->id ]->completed_at ) ) ); ?></span>
						<?php else : ?>
							<span class="sp-badge sp-badge-muted">Not yet</span>
						<?php endif; ?>
					</p>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
<?php endif; ?>
