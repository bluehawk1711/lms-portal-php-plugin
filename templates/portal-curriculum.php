<?php
/**
 * Student curriculum: the outline of each enrolled class and what it has covered.
 *
 * Every enrolled class renders as a pane behind a class selection filter.
 * Shows curriculum grouped by headings with topics inside each heading.
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
	$sp_pane_course   = null;
	$sp_pane_groups   = array();
	$sp_pane_progress = array();
	$sp_pane_summary  = array( 'total' => 0, 'done' => 0, 'percent' => 0 );

	$sp_pane_class = sp_get_class( (int) $sp_pane_enrol->class_id );

	if ( $sp_pane_class && $sp_pane_class->course_id ) {
		$sp_pane_course = sp_get_course( (int) $sp_pane_class->course_id );
	}

	if ( $sp_pane_course ) {
		$sp_pane_groups   = sp_get_curriculum_grouped( (int) $sp_pane_course->id );
		$sp_pane_progress = sp_get_class_progress( (int) $sp_pane_enrol->class_id );
		$sp_pane_summary  = sp_curriculum_summary( (int) $sp_pane_course->id, (int) $sp_pane_enrol->class_id );
	}

	$sp_panes[] = array(
		'class_id'  => (int) $sp_pane_enrol->class_id,
		'title'     => (string) $sp_pane_enrol->class_title,
		'course'    => $sp_pane_course,
		'groups'    => $sp_pane_groups,
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
    <?php elseif ( empty( $sp_pane['groups'] ) ) : ?>
    <div class="sp-empty">The outline for this course has not been published yet. Check back soon.</div>
    <?php else : ?>
    <div class="sp-curriculum">
        <div class="sp-curriculum-header">
            <div class="sp-curriculum-title-row">
                <h2 class="sp-curriculum-course-title"><?php echo esc_html( $sp_pane['course']->name ); ?></h2>
                <div class="sp-curriculum-completion">
                    <?php if ( $sp_pane['summary']['total'] > 0 ) : ?>
                    <span class="sp-curriculum-percent-badge <?php echo $sp_pane['summary']['percent'] === 100 ? 'is-done' : 'is-progress'; ?>">
                        <?php if ( $sp_pane['summary']['percent'] === 100 ) : ?>
                            <?php echo esc_html( '100% Completed' ); ?>
                        <?php else : ?>
                            <?php echo esc_html( 'In Progress - ' . $sp_pane['summary']['percent'] . '% Done' ); ?>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ( ! empty( $sp_pane['course']->description ) ) : ?>
            <p class="sp-curriculum-subtitle"><?php echo esc_html( $sp_pane['course']->description ); ?></p>
            <?php endif; ?>
            <div class="sp-curriculum-stats">
                <span class="sp-curriculum-stat">
                    <span class="sp-curriculum-stat-num"><?php echo esc_html( $sp_pane['summary']['done'] ); ?></span>
                    <span class="sp-curriculum-stat-label">of <?php echo esc_html( $sp_pane['summary']['total'] ); ?> Finished</span>
                </span>
            </div>
            <div class="sp-curriculum-meter">
                <div class="sp-curriculum-meter-bar">
                    <span style="width: <?php echo esc_attr( $sp_pane['summary']['percent'] ); ?>%"></span>
                </div>
                <span class="sp-curriculum-meter-value">
                    <?php echo esc_html( $sp_pane['summary']['done'] . ' / ' . $sp_pane['summary']['total'] . ' · ' . $sp_pane['summary']['percent'] . '%' ); ?>
                </span>
            </div>
        </div>

        <div class="sp-curriculum-groups">
            <?php foreach ( $sp_pane['groups'] as $sp_index => $sp_group ) : ?>
            <?php
                $sp_group_done = 0;
                foreach ( $sp_group['topics'] as $sp_topic ) {
                    if ( isset( $sp_pane['progress'][ $sp_topic['id'] ] ) ) {
                        $sp_group_done++;
                    }
                }
                $sp_group_total = count( $sp_group['topics'] );
                $sp_group_percent = $sp_group_total > 0 ? (int) round( ( $sp_group_done / $sp_group_total ) * 100 ) : 0;
                $sp_is_group_done = $sp_group_percent === 100;
            ?>
            <div class="sp-curriculum-group <?php echo $sp_is_group_done ? 'is-done' : ''; ?>"
                data-sp-curriculum-group="<?php echo esc_attr( $sp_group['id'] ); ?>">
                <div class="sp-curriculum-group-header">
                    <div class="sp-curriculum-group-badge-row">
                        <span class="sp-curriculum-badge sp-curriculum-badge-heading <?php echo $sp_is_group_done ? 'is-done' : 'is-number'; ?>">
                            <?php echo esc_html( sprintf( '%02d', $sp_index + 1 ) ); ?>
                        </span>
                        <h3 class="sp-curriculum-group-title"><?php echo esc_html( $sp_group['group_title'] ?: $sp_group['title'] ); ?></h3>
                    </div>
                    <div class="sp-curriculum-group-meta">
                        <?php if ( ! empty( $sp_group['topics'] ) ) : ?>
                        <p class="sp-curriculum-group-subtitle">
                            <?php echo esc_html( 'Topics ' . ( $sp_index * 7 + 1 ) . ' - ' . ( $sp_index * 7 + $sp_group_total ) ); ?>
                        </p>
                        <?php endif; ?>
                        <div class="sp-curriculum-group-completion">
                            <?php if ( $sp_is_group_done ) : ?>
                            <span class="sp-curriculum-status-badge sp-curriculum-status-done">
                                <?php echo esc_html( '100% Completed' ); ?>
                            </span>
                            <?php else : ?>
                            <span class="sp-curriculum-status-badge sp-curriculum-status-progress">
                                <?php echo esc_html( 'In Progress - ' . $sp_group_percent . '% Done' ); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="sp-curriculum-group-stats">
                        <span class="sp-curriculum-group-stat">
                            <?php echo esc_html( $sp_group_done . ' of ' . $sp_group_total . ' Finished' ); ?>
                        </span>
                    </div>
                </div>

                <div class="sp-curriculum-topics">
                    <?php if ( empty( $sp_group['topics'] ) ) : ?>
                    <div class="sp-curriculum-empty-topics">No topics in this section yet.</div>
                    <?php else : ?>
                    <?php foreach ( $sp_group['topics'] as $sp_topic_index => $sp_topic ) : ?>
                    <?php
                        $sp_topic_id   = $sp_topic['id'];
                        $sp_marked     = isset( $sp_pane['progress'][ $sp_topic_id ] );
                        $sp_completed_at = $sp_marked ? $sp_pane['progress'][ $sp_topic_id ]->completed_at : '';
                    ?>
                    <div class="sp-curriculum-topic <?php echo $sp_marked ? 'is-marked' : ''; ?>"
                        data-sp-curriculum-topic="<?php echo esc_attr( $sp_topic_id ); ?>">
                        <div class="sp-curriculum-topic-row">
                            <span class="sp-curriculum-badge sp-curriculum-badge-topic <?php echo $sp_marked ? 'is-done' : 'is-number'; ?>">
                                <?php echo esc_html( $sp_topic_index + 1 ); ?>
                            </span>
                            <div class="sp-curriculum-topic-content">
                                <h4 class="sp-curriculum-topic-title"><?php echo esc_html( $sp_topic['title'] ); ?></h4>
                                <?php if ( ! empty( $sp_topic['detail'] ) ) : ?>
                                <p class="sp-curriculum-topic-detail"><?php echo esc_html( $sp_topic['detail'] ); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="sp-curriculum-topic-status">
                                <?php if ( $sp_marked ) : ?>
                                <span class="sp-curriculum-status-badge sp-curriculum-status-done">
                                    <svg class="sp-curriculum-check-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 6 9 17l-5-5" />
                                    </svg>
                                    <?php echo esc_html( 'Covered' ); ?>
                                </span>
                                <span class="sp-curriculum-date">
                                    <?php echo esc_html( sp_portal_time( get_option( 'date_format' ), strtotime( $sp_completed_at ) ) ); ?>
                                </span>
                                <?php else : ?>
                                <span class="sp-curriculum-status-badge sp-curriculum-status-not-started">
                                    <?php echo esc_html( 'Not yet scheduled' ); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>