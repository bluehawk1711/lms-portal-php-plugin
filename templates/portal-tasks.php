<?php
/**
 * Tasks: every paper and exam a student can see.
 *
 * Expects $profile from sp_render_portal().
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $profile ) ) {
	?>
<div class="sp-tasks">
    <div class="sp-empty sp-task-list-empty" role="status">
        <span class="sp-portal-badge sp-portal-badge--warning">Profile required</span>
        <strong>Student profile not linked</strong>
        <p>This WordPress account is not linked to a student profile yet, so there is nothing to show here.</p>
    </div>
</div>
	<?php
	return;
}

$sp_tasks = sp_get_assignments_for_profile( (int) $profile->id );
$sp_tasks = $sp_tasks ? (array) $sp_tasks : array();

$sp_now      = (int) current_time( 'timestamp' ); // Site-local "now", comparable with strtotime() of stored dates.
$sp_date_fmt = get_option( 'date_format' );
$sp_time_fmt = get_option( 'time_format' );

/*
 * Small helpers
 * ---------------------------------------------------------------------------
 * $sp_pick  returns the first non-empty property from a list of candidate
 *           column names. Optional card details (description, score, question
 *           count, ...) render only when the query returns them, so rename the
 *           candidates below to match your columns.
 */
$sp_pick = static function ( $obj, array $keys, $default = '' ) {
	foreach ( $keys as $key ) {
		if ( isset( $obj->$key ) && '' !== $obj->$key ) {
			return $obj->$key;
		}
	}
	return $default;
};

// 15.00 -> "15", 7.50 -> "7.5".
$sp_num = static function ( $value ) {
	return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' );
};

// Does a stored date string carry a real time of day?
$sp_has_time = static function ( $raw ) {
	$raw = trim( (string) $raw );
	return strlen( $raw ) > 10 && ! preg_match( '/\s00:00(:00)?$/', $raw );
};

// "Today, 11:59 PM", "Nov 12, 2026" or "Nov 12, 2026, 9:00 AM".
$sp_when = static function ( $raw ) use ( $sp_now, $sp_date_fmt, $sp_time_fmt, $sp_has_time ) {
	$ts = strtotime( (string) $raw );
	if ( ! $ts ) {
		return '';
	}
	$day = ( gmdate( 'Y-m-d', $ts ) === gmdate( 'Y-m-d', $sp_now ) )
		? 'Today'
		: sp_portal_time( $sp_date_fmt, $ts );
	return $sp_has_time( $raw ) ? $day . ', ' . sp_portal_time( $sp_time_fmt, $ts ) : $day;
};

// Inline icons (stroke = currentColor).
$sp_icon_paths = array(
	'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.2 2.5 2.5 4.5-5"/>',
	'check'        => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
	'timer'        => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2M9.5 2.5h5"/>',
	'repeat'       => '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
	'clock'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
	'info'         => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
	'calendar'     => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
	'arrow-right'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
	'download'     => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
	'file'         => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
	'shield'       => '<path d="M12 3 4.5 6v5.5c0 4.5 3.2 8 7.5 9.5 4.3-1.5 7.5-5 7.5-9.5V6z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
);
$sp_icon = static function ( $name, $size = 14 ) use ( $sp_icon_paths ) {
	return '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
		. ( isset( $sp_icon_paths[ $name ] ) ? $sp_icon_paths[ $name ] : '' )
		. '</svg>';
};

/*
 * Which card is this?
 *   done      the student has submitted (or been graded)
 *   scheduled not open yet (release date in the future)
 *   available everything else
 */
$sp_done_states = array( 'submitted', 'graded', 'completed', 'reviewed', 'finished' );
$sp_kind        = static function ( $task ) use ( $sp_pick, $sp_done_states, $sp_now ) {
	if ( in_array( strtolower( (string) $sp_pick( $task, array( 'student_status' ) ) ), $sp_done_states, true ) ) {
		return 'done';
	}
	$release = (string) $sp_pick( $task, array( 'available_from', 'start_date', 'release_date', 'opens_at' ) );
	if ( '' !== $release && strtotime( $release ) > $sp_now ) {
		return 'scheduled';
	}
	return 'available';
};

$sp_counts = array(
	'available' => 0,
	'done'      => 0,
	'scheduled' => 0,
);
foreach ( $sp_tasks as $sp_task ) {
	++$sp_counts[ $sp_kind( $sp_task ) ];
}
$sp_total = count( $sp_tasks );

// Cohort chip: the student's first active enrolment.
$sp_enrollments = function_exists( 'sp_get_enrollments_for_profile' )
	? (array) sp_get_enrollments_for_profile( (int) $profile->id, array( 'active' ) )
	: array();
$sp_cohort      = '';
if ( $sp_enrollments ) {
	$sp_first  = reset( $sp_enrollments );
	$sp_cohort = (string) ( isset( $sp_first->class_title ) ? $sp_first->class_title : '' );
	if ( ! empty( $sp_first->enroll_id ) ) {
		$sp_cohort .= ' (' . $sp_first->enroll_id . ')';
	}
	if ( count( $sp_enrollments ) > 1 ) {
		$sp_cohort .= ' +' . ( count( $sp_enrollments ) - 1 );
	}
}

// Teacher contact in the side rail: the first task that names one.
$sp_teacher = '';
foreach ( $sp_tasks as $sp_task ) {
	$sp_teacher = (string) $sp_pick( $sp_task, array( 'teacher_name', 'author_name' ) );
	if ( '' !== $sp_teacher ) {
		break;
	}
}
$sp_teacher_initials = '';
if ( '' !== $sp_teacher ) {
	// Initials skip honorifics: "Prof. Ramesh N." -> "RN".
	$sp_teacher_name = trim( preg_replace( '/^(prof|dr|mr|mrs|ms|miss|sir|madam)\.?\s+/i', '', $sp_teacher ) );
	foreach ( array_slice( array_filter( preg_split( '/\s+/', $sp_teacher_name ) ), 0, 2 ) as $sp_word ) {
		$sp_teacher_initials .= function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $sp_word, 0, 1 ) ) : strtoupper( substr( $sp_word, 0, 1 ) );
	}
}
// Return a URL from this filter to show the "Help Desk" button.
$sp_help_url = (string) apply_filters( 'sp_portal_helpdesk_url', '' );
?>

<div class="sp-tasks">

    <header class="sp-tasks-head">
        <div class="sp-tasks-head-text">
            <p class="sp-tasks-eyebrow"><?php echo $sp_icon( 'check-circle', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                Academic assessment hub</p>
            <h1 class="sp-tasks-title">Tasks &amp; Examinations</h1>
            <p class="sp-tasks-sub">Review scheduled module evaluations and submit completed assignments.</p>
        </div>

        <ul class="sp-tasks-stats" aria-label="Task summary">
            <li class="sp-tasks-stat sp-tasks-stat--total">
                <span class="sp-tasks-stat-label">Total</span>
                <span class="sp-tasks-stat-value"><?php echo esc_html( $sp_total . ( 1 === $sp_total ? ' Task' : ' Tasks' ) ); ?></span>
            </li>
            <li class="sp-tasks-stat sp-tasks-stat--available">
                <span class="sp-tasks-stat-label">Available</span>
                <span class="sp-tasks-stat-value"><?php echo esc_html( $sp_counts['available'] . ' Active' ); ?></span>
            </li>
            <li class="sp-tasks-stat sp-tasks-stat--done">
                <span class="sp-tasks-stat-label">Done</span>
                <span class="sp-tasks-stat-value"><?php echo esc_html( $sp_counts['done'] . ' Completed' ); ?></span>
            </li>
        </ul>
    </header>

    <div class="sp-tasks-toolbar">
        <div class="sp-tasks-tabs" role="group" aria-label="Filter tasks">
            <button type="button" class="sp-tasks-tab is-active" data-sp-filter="all" aria-pressed="true">All
                (<?php echo esc_html( (string) $sp_total ); ?>)</button>
            <button type="button" class="sp-tasks-tab" data-sp-filter="available" aria-pressed="false">Available
                (<?php echo esc_html( (string) $sp_counts['available'] ); ?>)</button>
            <button type="button" class="sp-tasks-tab" data-sp-filter="done" aria-pressed="false">Completed
                (<?php echo esc_html( (string) $sp_counts['done'] ); ?>)</button>
        </div>
        <?php if ( '' !== $sp_cohort ) : ?>
        <p class="sp-tasks-cohort">Cohort: <strong><?php echo esc_html( $sp_cohort ); ?></strong></p>
        <?php endif; ?>
    </div>

    <div class="sp-tasks-grid">

        <div class="sp-tasks-list" data-sp-task-list>

            <?php if ( ! $sp_tasks ) : ?>
            <div class="sp-empty sp-task-list-empty" role="status">
                <span class="sp-portal-badge sp-portal-badge--neutral">No tasks yet</span>
                <strong>Nothing assigned yet</strong>
                <p>Your teachers will post papers and exams here.</p>
            </div>
            <?php endif; ?>

            <?php
			foreach ( $sp_tasks as $sp_task ) :
				$sp_id       = (int) $sp_task->id;
				$sp_card     = $sp_kind( $sp_task );
				$sp_url      = sp_portal_url( 'tasks/' . $sp_id );
				$sp_is_paper = 'paper' === $sp_task->type;
				$sp_class    = ! empty( $sp_task->class_title ) ? (string) $sp_task->class_title : '';
				$sp_subtitle = (string) $sp_pick( $sp_task, array( 'subtitle', 'module_title', 'module' ) );
				$sp_desc     = (string) $sp_pick( $sp_task, array( 'description', 'instructions', 'summary' ) );
				$sp_desc     = '' !== $sp_desc ? wp_trim_words( wp_strip_all_tags( $sp_desc ), 40 ) : '';

				$sp_row = null;
				if ( ! empty( $sp_task->student_row_id ) ) {
					$sp_row = (object) array(
						'id'             => (int) $sp_task->student_row_id,
						'status'         => $sp_task->student_status,
						'retake_granted' => (int) $sp_task->student_retake_granted,
						'started_at'     => $sp_task->student_started_at,
					);
				}
				$sp_state = sp_assignment_student_state( $sp_task, $sp_row );

				if ( 'scheduled' === $sp_card ) :
					$sp_release = (string) $sp_pick( $sp_task, array( 'available_from', 'start_date', 'release_date', 'opens_at' ) );
					$sp_note    = '' !== $sp_subtitle ? $sp_subtitle : $sp_class;
					?>
            <article class="sp-task sp-task--scheduled" id="task-<?php echo esc_attr( (string) $sp_id ); ?>"
                data-sp-status="scheduled">
                <span class="sp-task-sched-icon" aria-hidden="true"><?php echo $sp_icon( 'calendar', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
                <div class="sp-task-sched-body">
                    <h2 class="sp-task-sched-title"><?php echo esc_html( $sp_task->title ); ?></h2>
                    <p class="sp-task-sched-sub">Releases <?php echo esc_html( $sp_when( $sp_release ) ); ?><?php echo '' !== $sp_note ? ' &middot; ' . esc_html( $sp_note ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></p>
                </div>
                <span class="sp-task-pill">Scheduled</span>
            </article>

					<?php
				elseif ( 'done' === $sp_card ) :
					$sp_status_label = ucfirst( strtolower( (string) $sp_pick( $sp_task, array( 'student_status' ) ) ) );
					$sp_started      = (string) $sp_pick( $sp_task, array( 'student_started_at' ) );
					$sp_submitted    = (string) $sp_pick( $sp_task, array( 'student_submitted_at', 'submitted_at' ) );
					$sp_secs         = ( '' !== $sp_started && '' !== $sp_submitted ) ? strtotime( $sp_submitted ) - strtotime( $sp_started ) : 0;
					$sp_duration     = ( $sp_secs > 0 && $sp_secs < 86400 ) ? floor( $sp_secs / 60 ) . 'm ' . ( $sp_secs % 60 ) . 's' : '';

					$sp_score     = $sp_pick( $sp_task, array( 'student_score', 'score', 'marks_obtained' ) );
					$sp_max       = $sp_pick( $sp_task, array( 'max_score', 'total_marks', 'max_marks' ) );
					$sp_pass_pct  = $sp_pick( $sp_task, array( 'pass_percent', 'passing_percent', 'pass_mark_percent' ) );
					$sp_has_score = is_numeric( $sp_score );
					$sp_pct       = ( $sp_has_score && is_numeric( $sp_max ) && (float) $sp_max > 0 ) ? (int) round( (float) $sp_score / (float) $sp_max * 100 ) : null;
					$sp_passed    = ( null !== $sp_pct && is_numeric( $sp_pass_pct ) ) ? $sp_pct >= (float) $sp_pass_pct : null;
					$sp_feedback  = (string) $sp_pick( $sp_task, array( 'student_feedback', 'feedback', 'teacher_remarks' ) );
					$sp_slip_url  = (string) apply_filters( 'sp_task_slip_url', '', $sp_task, $sp_row );
					$sp_sub_bits  = array_filter( array( $sp_class, $sp_row ? 'Submission #' . (int) $sp_row->id : '' ) );
					?>
            <article class="sp-task sp-task--done" id="task-<?php echo esc_attr( (string) $sp_id ); ?>"
                data-sp-status="done">
                <div class="sp-task-top">
                    <div class="sp-task-chips">
                        <span class="sp-chip sp-chip--success"><?php echo $sp_icon( 'check-circle', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                            <?php echo esc_html( $sp_status_label ); ?></span>
                        <?php if ( '' !== $sp_duration ) : ?>
                        <span class="sp-task-aside">Completed in <?php echo esc_html( $sp_duration ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ( '' !== $sp_submitted ) : ?>
                    <span class="sp-task-date"><?php echo esc_html( sp_portal_time( $sp_date_fmt, strtotime( $sp_submitted ) ) ); ?></span>
                    <?php endif; ?>
                </div>

                <h2 class="sp-task-title"><a href="<?php echo esc_url( $sp_url ); ?>"><?php echo esc_html( $sp_task->title ); ?></a></h2>
                <?php if ( $sp_sub_bits ) : ?>
                <p class="sp-task-sub"><?php echo esc_html( implode( ' · ', $sp_sub_bits ) ); ?></p>
                <?php endif; ?>

                <?php if ( $sp_has_score ) : ?>
                <div class="sp-task-result">
                    <div class="sp-task-score">
                        <span class="sp-task-score-label">Score</span>
                        <span class="sp-task-score-value"><strong><?php echo esc_html( $sp_num( $sp_score ) ); ?></strong><?php echo is_numeric( $sp_max ) ? ' <small>/ ' . esc_html( $sp_num( $sp_max ) ) . '</small>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></span>
                    </div>
                    <?php if ( null !== $sp_pct ) : ?>
                    <?php
						$sp_verdict_class = true === $sp_passed ? 'sp-chip--success' : ( false === $sp_passed ? 'sp-chip--warn' : 'sp-chip--neutral' );
						$sp_verdict       = true === $sp_passed ? 'Passed' : ( false === $sp_passed ? 'Needs Improvement' : 'Scored' );
						?>
                    <span class="sp-chip <?php echo esc_attr( $sp_verdict_class ); ?>"><?php echo esc_html( $sp_verdict . ' (' . $sp_pct . '%)' ); ?></span>
                    <?php endif; ?>
                    <?php if ( '' !== $sp_feedback ) : ?>
                    <blockquote class="sp-task-feedback">&ldquo;<?php echo esc_html( wp_strip_all_tags( $sp_feedback ) ); ?>&rdquo;</blockquote>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="sp-task-foot sp-task-foot--end">
                    <a class="sp-task-btn sp-task-btn--link" href="<?php echo esc_url( $sp_url ); ?>">Review Answers</a>
                    <?php if ( '' !== $sp_slip_url ) : ?>
                    <a class="sp-task-btn sp-task-btn--soft" href="<?php echo esc_url( $sp_slip_url ); ?>"><?php echo $sp_icon( 'download', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                        Download Slip</a>
                    <?php endif; ?>
                </div>
            </article>

					<?php
				else :
					$sp_time_limit   = ( ! $sp_is_paper && ! empty( $sp_task->time_limit_minutes ) ) ? (int) $sp_task->time_limit_minutes : 0;
					$sp_questions    = (int) $sp_pick( $sp_task, array( 'question_count', 'questions_count', 'total_questions' ), 0 );
					$sp_max_attempts = (int) $sp_pick( $sp_task, array( 'max_attempts', 'attempts_allowed' ), 0 );
					$sp_pass_pct     = $sp_pick( $sp_task, array( 'pass_percent', 'passing_percent', 'pass_mark_percent' ) );
					$sp_max          = $sp_pick( $sp_task, array( 'max_score', 'total_marks', 'max_marks' ) );
					$sp_has_file     = $sp_is_paper && ! empty( $sp_task->attachment_path );
					$sp_in_progress  = $sp_row && ! empty( $sp_row->started_at );

					$sp_chip_bits = array();
					if ( $sp_time_limit ) {
						$sp_chip_bits[] = $sp_time_limit . ' min';
					}
					if ( $sp_questions ) {
						$sp_chip_bits[] = $sp_questions . ' Questions';
					}
					if ( ! $sp_chip_bits ) {
						$sp_chip_bits[] = 'Exam';
					}

					// Facts row: attempts, passing mark, close date.
					$sp_facts = array();
					if ( $sp_max_attempts > 0 ) {
						$sp_facts[] = array( 'repeat', 'Attempts', 1 === $sp_max_attempts ? '1 Single Attempt' : $sp_max_attempts . ' Attempts' );
					}
					if ( ! empty( $sp_task->student_retake_granted ) ) {
						$sp_facts[] = array( 'repeat', 'Retake', 'Granted' );
					}
					if ( is_numeric( $sp_pass_pct ) ) {
						$sp_pass_text = $sp_num( $sp_pass_pct ) . '%';
						if ( is_numeric( $sp_max ) && (float) $sp_max > 0 ) {
							$sp_pass_text .= ' (' . $sp_num( (float) $sp_max * (float) $sp_pass_pct / 100 ) . ' / ' . $sp_num( $sp_max ) . ')';
						}
						$sp_facts[] = array( 'check-circle', 'Passing', $sp_pass_text );
					}
					if ( ! empty( $sp_task->due_date ) ) {
						$sp_due_label = $sp_is_paper ? 'Due' : ( $sp_has_time( $sp_task->due_date ) ? 'Closes' : 'Exam day' );
						$sp_facts[]   = array( 'clock', $sp_due_label, $sp_when( $sp_task->due_date ) );
					}

					if ( $sp_time_limit ) {
						$sp_note      = 'Timer cannot be paused once initiated';
						$sp_note_icon = 'info';
					} elseif ( $sp_is_paper ) {
						$sp_note      = $sp_has_file ? 'File attached' : 'File pending: your teacher has not attached it yet';
						$sp_note_icon = $sp_has_file ? 'check' : 'info';
					} else {
						$sp_note      = '';
						$sp_note_icon = 'info';
					}

					if ( $sp_is_paper ) {
						$sp_cta = 'Open Paper';
					} else {
						$sp_cta = $sp_in_progress ? 'Resume Exam' : 'Start Exam';
					}
					?>
            <article class="sp-task sp-task--available" id="task-<?php echo esc_attr( (string) $sp_id ); ?>"
                data-sp-status="available">
                <div class="sp-task-top">
                    <div class="sp-task-chips">
                        <?php if ( $sp_is_paper ) : ?>
                        <span class="sp-chip sp-chip--brand"><?php echo $sp_icon( 'file', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                            Paper</span>
                        <?php else : ?>
                        <span class="sp-chip sp-chip--brand"><?php echo $sp_icon( 'timer', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                            <?php echo esc_html( implode( ' · ', $sp_chip_bits ) ); ?></span>
                        <?php endif; ?>
                        <?php if ( '' !== $sp_class ) : ?>
                        <span class="sp-chip sp-chip--neutral"><?php echo esc_html( $sp_class ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="sp-task-state" aria-label="Task status">
                        <?php echo sp_assignment_state_badge( $sp_state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped markup. ?>
                    </div>
                </div>

                <h2 class="sp-task-title"><a href="<?php echo esc_url( $sp_url ); ?>"><?php echo esc_html( $sp_task->title ); ?></a></h2>
                <?php if ( '' !== $sp_subtitle ) : ?>
                <p class="sp-task-sub"><?php echo esc_html( $sp_subtitle ); ?></p>
                <?php endif; ?>
                <?php if ( '' !== $sp_desc ) : ?>
                <p class="sp-task-desc"><?php echo esc_html( $sp_desc ); ?></p>
                <?php endif; ?>

                <?php if ( $sp_facts ) : ?>
                <ul class="sp-task-facts">
                    <?php foreach ( $sp_facts as $sp_fact ) : ?>
                    <li class="sp-task-fact">
                        <?php echo $sp_icon( $sp_fact[0], 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                        <span><?php echo esc_html( $sp_fact[1] ); ?>: <strong><?php echo esc_html( $sp_fact[2] ); ?></strong></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <div class="sp-task-foot">
                    <?php if ( '' !== $sp_note ) : ?>
                    <p class="sp-task-note"><?php echo $sp_icon( $sp_note_icon, 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                        <?php echo esc_html( $sp_note ); ?></p>
                    <?php else : ?>
                    <span></span>
                    <?php endif; ?>
                    <a class="sp-task-btn sp-task-btn--primary" href="<?php echo esc_url( $sp_url ); ?>">
                        <?php echo esc_html( $sp_cta ); ?>
                        <?php echo $sp_icon( 'arrow-right', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                    </a>
                </div>
            </article>
					<?php
				endif;
			endforeach;
			?>

            <div class="sp-empty" data-sp-filter-empty hidden>
                <strong>Nothing in this view</strong>
                <p>Try another filter to see your other tasks.</p>
            </div>

        </div>

        <aside class="sp-tasks-rail" aria-label="Exam guidelines">
            <section class="sp-tasks-guide">
                <div class="sp-tasks-guide-head">
                    <span class="sp-tasks-guide-icon" aria-hidden="true"><?php echo $sp_icon( 'shield', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
                    <div>
                        <h2 class="sp-tasks-guide-title">Exam Guidelines</h2>
                        <p class="sp-tasks-guide-sub">Portal integrity checklist</p>
                    </div>
                </div>

                <ul class="sp-tasks-guide-list">
                    <li>
                        <?php echo $sp_icon( 'check', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                        <p><strong>Stable Internet:</strong> Ensure a stable broadband connection prior to starting the timer.</p>
                    </li>
                    <li>
                        <?php echo $sp_icon( 'check', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                        <p><strong>Auto-Save Enabled:</strong> Answers sync continually. Reconnecting resumes your current session.</p>
                    </li>
                    <li>
                        <?php echo $sp_icon( 'check', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
                        <p><strong>Timed Submissions:</strong> Answers submit automatically once the countdown reaches 00:00.</p>
                    </li>
                </ul>

                <?php if ( '' !== $sp_teacher || '' !== $sp_help_url ) : ?>
                <div class="sp-tasks-help">
                    <?php if ( '' !== $sp_teacher ) : ?>
                    <span class="sp-tasks-help-avatar" aria-hidden="true"><?php echo esc_html( $sp_teacher_initials ); ?></span>
                    <div class="sp-tasks-help-who">
                        <strong><?php echo esc_html( $sp_teacher ); ?></strong>
                        <span>Your teacher</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( '' !== $sp_help_url ) : ?>
                    <a class="sp-task-btn sp-task-btn--outline" href="<?php echo esc_url( $sp_help_url ); ?>">Help Desk</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </section>
        </aside>

    </div>
</div>

<script>
(function() {
    var root = document.querySelector('.sp-tasks');

    if (!root) {
        return;
    }

    var tabs = root.querySelectorAll('[data-sp-filter]');
    var cards = root.querySelectorAll('[data-sp-status]');
    var empty = root.querySelector('[data-sp-filter-empty]');

    Array.prototype.forEach.call(tabs, function(tab) {
        tab.addEventListener('click', function() {
            var filter = tab.getAttribute('data-sp-filter');
            var shown = 0;

            Array.prototype.forEach.call(tabs, function(other) {
                var on = other === tab;
                other.classList.toggle('is-active', on);
                other.setAttribute('aria-pressed', on ? 'true' : 'false');
            });

            Array.prototype.forEach.call(cards, function(card) {
                var show = 'all' === filter || card.getAttribute('data-sp-status') === filter;
                card.hidden = !show;
                if (show) {
                    shown++;
                }
            });

            if (empty) {
                empty.hidden = shown > 0 || !cards.length;
            }
        });
    });
})();
</script>