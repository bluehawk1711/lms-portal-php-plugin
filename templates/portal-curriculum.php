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
		'batch'     => isset( $sp_pane_enrol->class_code ) ? (string) $sp_pane_enrol->class_code : '',
		'enroll_id' => isset( $sp_pane_enrol->enroll_id ) ? (string) $sp_pane_enrol->enroll_id : '',
		'status'    => isset( $sp_pane_enrol->status ) ? (string) $sp_pane_enrol->status : 'active',
	);
}
?>
<?php if ( ! $sp_enrollments ) : ?>
<div class="sp-empty">You are not enrolled in any course yet.</div>
<?php else : ?>

<div class="sp-cur2" data-sp-curriculum>
    <?php foreach ( $sp_panes as $sp_pane ) : ?>
    <?php
        $sp_is_active = (int) $sp_pane['class_id'] === (int) $sp_active_id;
        $sp_sum       = $sp_pane['summary'];
        $sp_total     = (int) $sp_sum['total'];
        $sp_done      = (int) $sp_sum['done'];
        $sp_pct       = (int) $sp_sum['percent'];
        $sp_rem       = max( 0, $sp_total - $sp_done );

        // First uncovered topic in this pane drives the "Up next in class" pill.
        $sp_next_id = 0;
        if ( $sp_rem > 0 ) {
            foreach ( $sp_pane['groups'] as $sp_ng ) {
                foreach ( $sp_ng['topics'] as $sp_nt ) {
                    if ( ! isset( $sp_pane['progress'][ $sp_nt['id'] ] ) ) {
                        $sp_next_id = (int) $sp_nt['id'];
                        break 2;
                    }
                }
            }
        }

        $sp_label = 'active' === $sp_pane['status'] ? 'Active Enrolment' : 'Completed Enrolment';
        $sp_batch = $sp_pane['batch'] ? $sp_pane['batch'] : $sp_pane['enroll_id'];
    ?>
    <div class="sp-cur2-pane sp-class-pane<?php echo $sp_is_active ? ' is-active' : ''; ?>"
        data-sp-class-pane="<?php echo esc_attr( $sp_pane['class_id'] ); ?>"
        <?php echo $sp_is_active ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- boolean attribute. ?>>
        <?php if ( ! $sp_pane['course'] ) : ?>
        <div class="sp-empty">No course outline applies to this class yet.</div>
        <?php elseif ( empty( $sp_pane['groups'] ) ) : ?>
        <div class="sp-empty">The outline for this course has not been published yet. Check back soon.</div>
        <?php else : ?>

        <!-- ── Hero / course summary card ─────────────────────────────── -->
        <section class="sp-cur2-hero">
            <!-- <span class="sp-cur2-hero-glow" aria-hidden="true"></span> -->
            <!-- dont uncomment it  -->
            <!-- <div class="sp-cur2-hero-top">
                <div class="sp-cur2-hero-lead">
                    <div class="sp-cur2-chips">
                        <span class="sp-cur2-chip sp-cur2-chip-active"><?php echo esc_html( $sp_label ); ?></span>
                        <?php if ( $sp_batch ) : ?>
                        <span class="sp-cur2-batch">Batch Code: <?php echo esc_html( $sp_batch ); ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="sp-cur2-title">Course Curriculum</h2>
                    <p class="sp-cur2-sub">The topics your class covers, learning milestones, and your personal syllabus
                        progress.</p>
                </div>

            </div> -->
            <label class="sp-cur2-switch-label"
                for="sp-cur2-sel-<?php echo esc_attr( $sp_pane['class_id'] ); ?>">Selected Course</label>
            <form class="sp-cur2-switch" method="get" data-sp-class-filter>
                <div class="sp-cur2-select-wrap">
                    <select id="sp-cur2-sel-<?php echo esc_attr( $sp_pane['class_id'] ); ?>" name="class"
                        data-sp-class-select>
                        <?php foreach ( $sp_panes as $sp_option ) : ?>
                        <option value="<?php echo esc_attr( $sp_option['class_id'] ); ?>"
                            <?php selected( $sp_active_id, $sp_option['class_id'] ); ?>>
                            <?php echo esc_html( $sp_option['title'] ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="sp-cur2-select-caret" viewBox="0 0 24 24" width="16" height="16" fill="none"
                        stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
                <button type="submit" class="sp-cur2-switch-btn">Switch</button>
            </form>

            <div class="sp-cur2-progress">
                <div class="sp-cur2-progress-head">
                    <div class="sp-cur2-progress-label">
                        <span class="sp-cur2-progress-name">Overall Syllabus Completion</span>
                        <span
                            class="sp-cur2-percent <?php echo 100 === $sp_pct ? 'is-done' : ''; ?>"><?php echo esc_html( $sp_pct . '% Completed' ); ?></span>
                    </div>
                    <div class="sp-cur2-progress-count">
                        <b><?php echo esc_html( $sp_done ); ?></b> / <?php echo esc_html( $sp_total ); ?> Topics
                        Finished
                    </div>
                </div>

                <div class="sp-cur2-bar">
                    <span style="width: <?php echo esc_attr( $sp_pct ); ?>%"></span>
                </div>

                <div class="sp-cur2-kpis">
                    <span class="sp-cur2-kpi sp-cur2-kpi-good"><i></i>Covered: <?php echo esc_html( $sp_done ); ?>
                        Topics</span>
                    <span class="sp-cur2-kpi sp-cur2-kpi-muted"><i></i>Remaining: <?php echo esc_html( $sp_rem ); ?>
                        Topics</span>
                    <span class="sp-cur2-kpi sp-cur2-kpi-info"><i></i>Total: <?php echo esc_html( $sp_total ); ?>
                        Topics</span>

                    <div class="sp-cur2-filters" role="group" aria-label="Filter topics">
                        <button type="button" class="sp-cur2-filter is-active" data-cur-filter="all"
                            aria-pressed="true">All (<?php echo esc_html( $sp_total ); ?>)</button>
                        <button type="button" class="sp-cur2-filter" data-cur-filter="covered"
                            aria-pressed="false">Covered (<?php echo esc_html( $sp_done ); ?>)</button>
                        <button type="button" class="sp-cur2-filter" data-cur-filter="upcoming"
                            aria-pressed="false">Upcoming (<?php echo esc_html( $sp_rem ); ?>)</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Modules ────────────────────────────────────────────────── -->
        <div class="sp-cur2-modules">
            <?php $sp_topic_no = 0; ?>
            <?php foreach ( $sp_pane['groups'] as $sp_index => $sp_group ) : ?>
            <?php
                $sp_g_total = count( $sp_group['topics'] );
                $sp_g_done  = 0;
                foreach ( $sp_group['topics'] as $sp_gt ) {
                    if ( isset( $sp_pane['progress'][ $sp_gt['id'] ] ) ) {
                        $sp_g_done++;
                    }
                }
                $sp_g_pct   = $sp_g_total ? (int) round( ( $sp_g_done / $sp_g_total ) * 100 ) : 0;
                $sp_g_state = ( $sp_g_total && $sp_g_done === $sp_g_total ) ? 'done' : ( $sp_g_done > 0 ? 'progress' : 'todo' );
                $sp_g_start = $sp_topic_no + 1;
                $sp_g_end   = $sp_topic_no + $sp_g_total;

                // Collapse untouched modules (never the one holding the next topic).
                $sp_g_has_next = false;
                foreach ( $sp_group['topics'] as $sp_gt ) {
                    if ( $sp_next_id && (int) $sp_gt['id'] === $sp_next_id ) {
                        $sp_g_has_next = true;
                        break;
                    }
                }
                $sp_collapsible = ( 'todo' === $sp_g_state && $sp_g_total > 2 && ! $sp_g_has_next );

                // Footer summary for a collapsed module: name a couple, count the rest.
                $sp_more_titles = array();
                $sp_more_rest   = 0;
                if ( $sp_collapsible ) {
                    $sp_more        = array_slice( $sp_group['topics'], 2 );
                    $sp_more_titles = array();
                    foreach ( array_slice( $sp_more, 0, 2 ) as $sp_mt ) {
                        $sp_more_titles[] = $sp_mt['title'];
                    }
                    $sp_more_rest = count( $sp_more ) - count( $sp_more_titles );
                }
            ?>
            <article
                class="sp-cur2-module is-<?php echo esc_attr( $sp_g_state ); ?><?php echo $sp_collapsible ? ' is-collapsed' : ''; ?>"
                data-cur-module data-cur-collapsible="<?php echo $sp_collapsible ? '1' : '0'; ?>">
                <div class="sp-cur2-module-head">
                    <div class="sp-cur2-module-id">
                        <span
                            class="sp-cur2-num sp-cur2-num-module is-<?php echo esc_attr( $sp_g_state ); ?>"><?php echo esc_html( sprintf( '%02d', $sp_index + 1 ) ); ?></span>
                        <div class="sp-cur2-module-titles">
                            <div class="sp-cur2-module-titlerow">
                                <h3 class="sp-cur2-module-title">
                                    <?php echo esc_html( $sp_group['group_title'] ? $sp_group['group_title'] : $sp_group['title'] ); ?>
                                </h3>
                                <?php if ( 'done' === $sp_g_state ) : ?>
                                <span class="sp-cur2-status is-done">100% Completed</span>
                                <?php elseif ( 'progress' === $sp_g_state ) : ?>
                                <span class="sp-cur2-status is-progress">In Progress ·
                                    <?php echo esc_html( $sp_g_pct ); ?>% Done</span>
                                <?php else : ?>
                                <span class="sp-cur2-status is-todo">Upcoming · 0% Done</span>
                                <?php endif; ?>
                            </div>
                            <?php if ( $sp_g_total ) : ?>
                            <p class="sp-cur2-module-desc">Topics <?php echo esc_html( $sp_g_start ); ?> -
                                <?php echo esc_html( $sp_g_end ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span
                        class="sp-cur2-finished"><?php echo esc_html( $sp_g_done . ' of ' . $sp_g_total . ' Finished' ); ?></span>
                </div>

                <div class="sp-cur2-topics">
                    <?php if ( empty( $sp_group['topics'] ) ) : ?>
                    <div class="sp-cur2-topics-empty">No topics in this section yet.</div>
                    <?php else : ?>
                    <?php foreach ( $sp_group['topics'] as $sp_topic ) : ?>
                    <?php
                        $sp_topic_no++;
                        $sp_tid          = $sp_topic['id'];
                        $sp_marked       = isset( $sp_pane['progress'][ $sp_tid ] );
                        $sp_is_next      = ( $sp_next_id && (int) $sp_tid === $sp_next_id );
                        $sp_completed_at = $sp_marked ? $sp_pane['progress'][ $sp_tid ]->completed_at : '';
                    ?>
                    <div class="sp-cur2-topic<?php echo $sp_marked ? ' is-marked' : ( $sp_is_next ? ' is-next' : '' ); ?>"
                        data-cur-topic data-cur-status="<?php echo $sp_marked ? 'covered' : 'upcoming'; ?>">
                        <span
                            class="sp-cur2-num sp-cur2-num-topic<?php echo $sp_marked ? ' is-done' : ''; ?>"><?php echo esc_html( $sp_topic_no ); ?></span>
                        <div class="sp-cur2-topic-body">
                            <h4 class="sp-cur2-topic-title"><?php echo esc_html( $sp_topic['title'] ); ?></h4>
                            <?php if ( ! empty( $sp_topic['detail'] ) ) : ?>
                            <p class="sp-cur2-topic-detail"><?php echo esc_html( $sp_topic['detail'] ); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="sp-cur2-topic-status">
                            <?php if ( $sp_marked ) : ?>
                            <span class="sp-cur2-pill is-good">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                    aria-hidden="true">
                                    <path d="M20 6 9 17l-5-5" />
                                </svg>
                                Covered
                            </span>
                            <span
                                class="sp-cur2-date"><?php echo esc_html( sp_portal_time( get_option( 'date_format' ), strtotime( $sp_completed_at ) ) ); ?></span>
                            <?php elseif ( $sp_is_next ) : ?>
                            <span class="sp-cur2-pill is-next"><i></i>Up next in class</span>
                            <?php else : ?>
                            <span class="sp-cur2-pill is-todo"><i></i>Not yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ( $sp_collapsible ) : ?>
                <div class="sp-cur2-module-foot">
                    <span class="sp-cur2-foot-note">
                        Includes
                        <?php echo esc_html( implode( ', ', $sp_more_titles ) ); ?><?php echo $sp_more_rest > 0 ? ' and ' . esc_html( $sp_more_rest ) . ' more' : ''; ?>.
                    </span>
                    <button type="button" class="sp-cur2-expand" data-cur-expand aria-expanded="false"
                        data-label-collapsed="Expand Module (<?php echo esc_html( $sp_g_total - 2 ); ?> more)"
                        data-label-expanded="Collapse Module">
                        Expand Module (<?php echo esc_html( $sp_g_total - 2 ); ?> more)
                    </button>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>