<?php
/**
 * Student curriculum: the outline of each enrolled class and what it has covered.
 *
 * Every enrolled class renders as a pane behind a class selection filter.
 * With JavaScript the select swaps panes in place (URL kept in sync); without
 * it the same form falls back to a plain ?class= reload.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $profile ) ) {
	?>
<div class="sp-notice sp-notice-warn">
    This WordPress account is not linked to a student profile yet. Ask the institute to create your portal account.
</div>
<?php
	return;
}

$sp_enrollments = sp_get_enrollments_for_profile( $profile->id, array( 'active', 'completed' ) );
$sp_view_id     = isset( $_GET['class'] ) ? (int) $_GET['class'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter selection.

// Resolve the visible class: the requested one, else the first enrolment.
$sp_active_id = 0;

foreach ( $sp_enrollments as $sp_candidate ) {
	if ( (int) $sp_candidate->class_id === $sp_view_id ) {
		$sp_active_id = (int) $sp_candidate->class_id;
		break;
	}
}

if ( ! $sp_active_id && $sp_enrollments ) {
	$sp_active_id = (int) $sp_enrollments[0]->class_id;
}

// Build one pane per enrolment so switching never needs a round trip.
$sp_panes = array();

foreach ( $sp_enrollments as $sp_pane_enrol ) {
	$sp_pane_course  = null;
	$sp_pane_items   = array();
	$sp_pane_progress = array();
	$sp_pane_summary = array( 'total' => 0, 'done' => 0, 'percent' => 0 );

	$sp_pane_class = sp_get_class( (int) $sp_pane_enrol->class_id );

	if ( $sp_pane_class && $sp_pane_class->course_id ) {
		$sp_pane_course = sp_get_course( (int) $sp_pane_class->course_id );
	}

	if ( $sp_pane_course ) {
		$sp_pane_items    = sp_get_curriculum( (int) $sp_pane_course->id );
		$sp_pane_progress = sp_get_class_progress( (int) $sp_pane_enrol->class_id );
		$sp_pane_summary  = sp_curriculum_summary( (int) $sp_pane_course->id, (int) $sp_pane_enrol->class_id );
	}

	$sp_panes[] = array(
		'class_id'  => (int) $sp_pane_enrol->class_id,
		'title'     => (string) $sp_pane_enrol->class_title,
		'course'    => $sp_pane_course,
		'items'     => $sp_pane_items,
		'progress'  => $sp_pane_progress,
		'summary'   => $sp_pane_summary,
	);
}
?>
<div class="sp-page-head">

    <p class="sp-page-sub">The topics your class covers, and how far it has got.</p>
</div>

<?php if ( ! $sp_enrollments ) : ?>
<div class="sp-empty">You are not enrolled in any course yet.</div>
<?php else : ?>

<form class="sp-class-filter" method="get" data-sp-class-filter
    style="display: flex; align-items: flex-end; gap: 12px; padding: 12px 0;">
    <div style="flex: 1; min-width: 0;">
        <label class="sp-class-filter-label" for="sp-cur-class-filter"
            style="display: block; margin-bottom: 6px; font-weight: 700; font-size: 18px;">
            Class
        </label>

        <select id="sp-cur-class-filter" name="class" data-sp-class-select
            style="width: 100%; height: 40px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 10px; padding: 0 12px; font-size: 16px;">
            <?php foreach ( $sp_panes as $sp_option ) : ?>
            <option value="<?php echo esc_attr( $sp_option['class_id'] ); ?>"
                <?php selected( $sp_active_id, $sp_option['class_id'] ); ?>>
                <?php echo esc_html( $sp_option['title'] ); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="sp-btn sp-btn-quiet sp-class-filter-btn"
        style="height: 40px; min-width: 80px; flex-shrink: 0; margin: 0; display: inline-flex; align-items: center; justify-content: center;">
        Switch
    </button>
</form>


<?php foreach ( $sp_panes as $sp_pane ) : ?>
<?php $sp_is_active = (int) $sp_pane['class_id'] === (int) $sp_active_id; ?>
<div style="padding-top: 16px;" class="sp-class-pane<?php echo $sp_is_active ? ' is-active' : ''; ?>"
    data-sp-class-pane="<?php echo esc_attr( $sp_pane['class_id'] ); ?>"
    <?php echo $sp_is_active ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- boolean attribute. ?>>
    <?php if ( ! $sp_pane['course'] ) : ?>
    <div class="sp-empty">No course outline applies to this class yet.</div>
    <?php elseif ( ! $sp_pane['items'] ) : ?>
    <div class="sp-empty">The outline for this course has not been published yet. Check back soon.</div>
    <?php else : ?>
    <div class="sp-card">
        <h2 class="sp-card-title"><?php echo esc_html( $sp_pane['course']->name ); ?></h2>

        <div class="sp-meter">
            <div class="sp-meter-bar">
                <span style="width: <?php echo esc_attr( $sp_pane['summary']['percent'] ); ?>%"></span>
            </div>
            <span
                class="sp-meter-value"><?php echo esc_html( $sp_pane['summary']['done'] . ' / ' . $sp_pane['summary']['total'] . ' · ' . $sp_pane['summary']['percent'] . '%' ); ?></span>
        </div>

        <ul class="sp-chips">
            <li class="sp-chip sp-chip-good">Covered
                <strong><?php echo esc_html( $sp_pane['summary']['done'] ); ?></strong>
            </li>
            <li class="sp-chip">Remaining
                <strong><?php echo esc_html( $sp_pane['summary']['total'] - $sp_pane['summary']['done'] ); ?></strong>
            </li>
            <li class="sp-chip">Topics <strong><?php echo esc_html( $sp_pane['summary']['total'] ); ?></strong></li>
        </ul>
    </div>

    <h2 class="sp-section-title">Topics</h2>

    <div class="sp-card-list">
        <?php foreach ( $sp_pane['items'] as $sp_index => $sp_item ) : ?>
        <?php $sp_marked = isset( $sp_pane['progress'][ (int) $sp_item->id ] ); ?>
        <article class="sp-card">
            <h2 class="sp-card-title"><?php echo esc_html( ( $sp_index + 1 ) . '. ' . $sp_item->title ); ?></h2>

            <?php if ( $sp_item->detail ) : ?>
            <p class="sp-muted"><?php echo nl2br( esc_html( $sp_item->detail ) ); ?></p>
            <?php endif; ?>

            <p class="sp-card-meta">
                <?php if ( $sp_marked ) : ?>
                <span class="sp-badge sp-badge-good">Covered</span>
                <span><?php echo esc_html( sp_portal_time( get_option( 'date_format' ), strtotime( $sp_pane['progress'][ (int) $sp_item->id ]->completed_at ) ) ); ?></span>
                <?php else : ?>
                <span class="sp-badge sp-badge-muted">Not yet</span>
                <?php endif; ?>
            </p>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>