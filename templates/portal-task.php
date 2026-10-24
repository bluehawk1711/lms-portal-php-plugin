<?php
/**
 * One task: a paper's file, or an exam from start to graded result.
 *
 * Expects $profile from sp_render_portal() and sp_assignment_id in the query.
 * Exams render inside the fullscreen shell (no sidebar/rail) so the quiz owns
 * the viewport.
 *
 * @package StudentPortal
 */

if (! defined('ABSPATH')) {
    exit;
}

$sp_id    = (int) get_query_var('sp_assignment_id');
$sp_task  = null;
$sp_row   = null;
$sp_state = 'unavailable';

if ($profile && $sp_id) {
    // Only tasks visible to this profile may be opened.
    foreach (sp_get_assignments_for_profile($profile->id) as $sp_candidate) {
        if ((int) $sp_candidate->id === $sp_id) {
            $sp_task = $sp_candidate;
            break;
        }
    }
}

if ($sp_task) {
    $sp_row   = ! empty($sp_task->student_row_id) ? sp_get_student_assignment($sp_id, (int) $profile->id) : null;
    $sp_state = sp_assignment_student_state($sp_task, $sp_row);

    // If the clock expired while this page was open, submit an empty attempt.
    if ('in_progress' === $sp_state && ! empty($sp_task->time_limit_minutes) && 0 === sp_assignment_time_left($sp_row, $sp_task)) {
        $sp_expired = sp_submit_assignment_attempt($sp_id, (int) $profile->id, []);

        if (! is_wp_error($sp_expired)) {
            $sp_row   = $sp_expired;
            $sp_state = 'submitted';
        }
    }
}
?>

<style id="sp-assignment-badge-styles">
.sp-portal-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    width: fit-content;
    max-width: 100%;
    padding: .28rem .65rem;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 600;
    line-height: 1.25;
    letter-spacing: .01em;
    text-decoration: none;
    vertical-align: middle;
    white-space: nowrap;
}

.sp-portal-badge--brand {
    color: #c2410c;
    background: #fff7ed;
    border-color: #fed7aa;
}

.sp-portal-badge--success {
    color: #047857;
    background: #ecfdf5;
    border-color: #a7f3d0;
}

.sp-portal-badge--info {
    color: #1d4ed8;
    background: #eff6ff;
    border-color: #bfdbfe;
}

.sp-portal-badge--warning {
    color: #92400e;
    background: #fffbeb;
    border-color: #fde68a;
}

.sp-portal-badge--danger {
    color: #be123c;
    background: #fff1f2;
    border-color: #fecdd3;
}

.sp-portal-badge--neutral {
    color: #475569;
    background: #f1f5f9;
    border-color: #e2e8f0;
}

.sp-portal-badge--muted {
    color: #64748b;
    background: #f8fafc;
    border-color: #e2e8f0;
}

.sp-quiz .sp-quiz-session-status.sp-portal-badge {
    gap: .45rem;
    padding: .32rem .75rem;
    font-size: .75rem;
    font-weight: 600;
}

.sp-quiz .sp-quiz-session-status .sp-quiz-live-dot {
    flex: 0 0 auto;
    width: .4rem;
    height: .4rem;
    background: currentColor;
    animation: sp-live-dot 1.6s ease-in-out infinite;
}

@keyframes sp-live-dot {
    50% {
        opacity: .35;
    }
}

@media (prefers-reduced-motion: reduce) {
    .sp-quiz .sp-quiz-session-status .sp-quiz-live-dot {
        animation: none;
    }
}

.sp-quiz .sp-quiz-answer-status.sp-portal-badge {
    font-size: .72rem;
    font-weight: 600;
}

.sp-quiz .sp-quiz-given-label.sp-portal-badge {
    font-size: .68rem;
}

.sp-paper-file-copy .sp-portal-badge {
    margin: .1rem 0 .25rem;
    white-space: normal;
    align-self: flex-start;
}

.sp-task-message .sp-portal-badge {
    margin: 0 0 .5rem;
}

.sp-exam-state-badge-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .5rem;
    margin: 0 0 .75rem;
}

@media (max-width: 480px) {
    .sp-portal-badge {
        max-width: 100%;
        white-space: normal;
    }

    .sp-quiz .sp-quiz-session-status.sp-portal-badge {
        font-size: .7rem;
    }
}
</style>

<?php if (! $profile): ?>

<section class="sp-task-message sp-empty" role="status">
    <span class="sp-task-message-icon" aria-hidden="true">i</span>
    <span class="sp-portal-badge sp-portal-badge--warning">Profile required</span>
    <strong>Student profile not linked</strong>
    <p>This WordPress account is not linked to a student profile yet, so there is nothing to show here.</p>
</section>

<?php elseif (! $sp_task): ?>

<section class="sp-task-message sp-empty" role="status">
    <span class="sp-task-message-icon" aria-hidden="true">!</span>
    <span class="sp-portal-badge sp-portal-badge--danger">Unavailable</span>
    <strong>Task unavailable</strong>
    <p>That task is not available any more.</p>
</section>

<?php else: ?>

<?php if ('paper' === $sp_task->type): ?>

<?php $sp_state_label = sp_assignment_state_badge($sp_state); ?>

<div class="sp-task-page sp-paper-page">
    <article class="sp-card sp-paper-card">
        <div class="sp-task-eyebrow"><span class="sp-task-eyebrow-dot"></span> LEARNING MATERIAL</div>

        <div class="sp-material-head">
            <div class="sp-material-heading">
                <h1 class="sp-card-title"><?php echo esc_html($sp_task->title); ?></h1>

                <p class="sp-muted sp-material-meta">
                    <span><?php echo esc_html($sp_task->class_title); ?></span>
                    <span class="sp-meta-separator" aria-hidden="true">/</span>
                    <span
                        class="sp-portal-badge sp-portal-badge--neutral"><?php echo esc_html(sp_assignment_type_label($sp_task->type)); ?></span>
                    <?php if (! empty($sp_task->due_date)): ?>
                    <span class="sp-meta-separator" aria-hidden="true">/</span>
                    <span>Due
                        <?php echo esc_html(sp_portal_time(get_option('date_format'), strtotime($sp_task->due_date))); ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="sp-material-status">
                <?php echo $sp_state_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge markup. ?>
            </div>
        </div>

        <?php if (! empty($sp_task->description)): ?>
        <div class="sp-note-body sp-paper-description">
            <?php echo wp_kses_post(wpautop($sp_task->description)); ?>
        </div>
        <?php endif; ?>

        <div class="sp-paper-download-panel">
            <div class="sp-paper-file-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M7 3.75h6.5L19 9.25v10A1.75 1.75 0 0 1 17.25 21h-10A1.75 1.75 0 0 1 5.5 19.25v-13A2.5 2.5 0 0 1 8 3.75Z"
                        stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                    <path d="M13 4v5.5h5.5M12 12v5m0 0-2-2m2 2 2-2" stroke="currentColor" stroke-width="1.6"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <div class="sp-paper-file-copy">
                <strong>Assignment paper</strong>
                <span
                    class="sp-portal-badge <?php echo ! empty($sp_task->attachment_path) ? 'sp-portal-badge--success' : 'sp-portal-badge--warning'; ?>">
                    <?php echo ! empty($sp_task->attachment_path) ? 'File attached' : 'No file attached'; ?>
                </span>
                <span
                    class="sp-paper-file-description"><?php echo ! empty($sp_task->attachment_path) ? 'The file provided by your teacher is ready to download.' : 'Your teacher has not attached a file yet.'; ?></span>
            </div>

            <div class="sp-card-actions sp-paper-actions">
                <?php if (! empty($sp_task->attachment_path)): ?>
                <a class="sp-btn sp-btn-primary" href="<?php echo esc_url(sp_assignment_file_url($sp_id)); ?>">
                    Download paper <span aria-hidden="true">↗</span>
                </a>
                <?php else: ?>
                <span class="sp-portal-badge sp-portal-badge--warning">File not yet available</span>
                <?php endif; ?>
            </div>
        </div>
    </article>
</div>

<?php else: ?>

<?php $sp_set_id = $sp_row && ! empty($sp_row->set_id) ? (int) $sp_row->set_id : 0; ?>

<?php if ('in_progress' === $sp_state): ?>

<?php
$sp_questions = $sp_set_id ? sp_get_set_questions($sp_set_id) : [];
$sp_seconds   = sp_assignment_time_left($sp_row, $sp_task);
$sp_total_q   = count($sp_questions);
?>

<div class="sp-quiz" data-sp-quiz>
    <div class="sp-quiz-topline">
        <div class="sp-quiz-topline-brand"><span class="sp-quiz-brand-mark" aria-hidden="true">S</span><span>Student
                Portal</span></div>

        <div class="sp-quiz-session-status sp-portal-badge sp-portal-badge--brand" role="status">
            <span class="sp-quiz-live-dot" aria-hidden="true"></span><span>Exam in progress</span>
        </div>
    </div>

    <div class="sp-quiz-layout">
        <section class="sp-quiz-content" aria-label="Exam questions">
            <header class="sp-quiz-head">
                <div class="sp-quiz-heading-copy">
                    <span class="sp-quiz-eyebrow">ASSESSMENT SESSION</span>
                    <h1 class="sp-quiz-title"><?php echo esc_html($sp_task->title); ?></h1>
                    <?php if (! empty($sp_task->class_title)): ?>
                    <p class="sp-quiz-subtitle"><?php echo esc_html($sp_task->class_title); ?></p>
                    <?php endif; ?>
                </div>

                <div class="sp-quiz-meta">
                    <div class="sp-quiz-timer-wrap">
                        <span class="sp-quiz-meta-label">TIME REMAINING</span>
                        <span class="sp-quiz-timer" data-sp-timer aria-live="off" aria-label="Time remaining">
                            <?php echo esc_html(sprintf('%02d:%02d', floor($sp_seconds / 60), $sp_seconds % 60)); ?>
                        </span>
                    </div>

                    <div class="sp-quiz-meta-divider" aria-hidden="true"></div>

                    <div class="sp-quiz-question-count">
                        <span class="sp-quiz-meta-label">QUESTION</span>
                        <span class="sp-quiz-count"><strong data-sp-current>1</strong><span class="sp-quiz-count-total">
                                / <?php echo esc_html($sp_total_q); ?></span></span>
                    </div>
                </div>

                <button type="button" class="sp-btn sp-btn-quiet sp-quiz-drawer-toggle" data-sp-drawer-toggle
                    aria-expanded="false" aria-label="Show question navigator">
                    <span aria-hidden="true">☰</span>
                </button>
            </header>

            <div class="sp-quiz-progress-block">
                <div class="sp-quiz-progress-caption"><span>Exam
                        progress</span><span><?php echo esc_html($sp_total_q); ?> questions</span></div>
                <div class="sp-quiz-progress" role="progressbar" aria-label="Question progress" aria-valuemin="0"
                    aria-valuemax="100">
                    <div class="sp-quiz-progress-bar" data-sp-progress style="width:0%"></div>
                </div>
            </div>

            <form class="sp-quiz-form" method="post" data-sp-exam
                data-seconds-left="<?php echo esc_attr((int) $sp_seconds); ?>"
                data-sp-total="<?php echo esc_attr($sp_total_q); ?>">
                <?php sp_portal_nonce_field('submit_assignment'); ?>
                <input type="hidden" name="assignment_id" value="<?php echo esc_attr($sp_id); ?>" />

                <?php if (! empty($sp_task->description)): ?>
                <div class="sp-quiz-desc"><span
                        class="sp-quiz-desc-label">INSTRUCTIONS</span><?php echo wp_kses_post(wpautop($sp_task->description)); ?>
                </div>
                <?php endif; ?>

                <?php foreach ($sp_questions as $sp_index => $sp_question): ?>
                <section class="sp-quiz-question<?php echo 0 === $sp_index ? ' is-active' : ''; ?>"
                    data-sp-question="<?php echo esc_attr($sp_index); ?>"
                    aria-label="Question <?php echo esc_attr($sp_index + 1); ?>">
                    <div class="sp-quiz-question-top">
                        <p class="sp-quiz-qnum">Question <?php echo esc_html($sp_index + 1); ?><span> of
                                <?php echo esc_html($sp_total_q); ?></span></p>
                        <span class="sp-quiz-question-type sp-portal-badge sp-portal-badge--neutral">Your
                            response</span>
                    </div>

                    <p class="sp-quiz-prompt"><?php echo esc_html($sp_question->prompt); ?></p>

                    <div class="sp-quiz-options">
                        <?php sp_exam_question_inputs($sp_question); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
                    </div>
                </section>
                <?php endforeach; ?>

                <div class="sp-quiz-nav">
                    <button type="button" class="sp-btn sp-btn-quiet" data-sp-prev disabled><span
                            aria-hidden="true">←</span> Previous</button>

                    <div class="sp-quiz-nav-primary">
                        <span class="sp-quiz-nav-caption">Take your time and review carefully.</span>
                        <button type="button" class="sp-btn sp-btn-primary" data-sp-next>Next question <span
                                aria-hidden="true">→</span></button>
                        <button type="submit" class="sp-btn sp-btn-primary sp-quiz-submit-btn" data-sp-submit
                            hidden>Submit exam <span aria-hidden="true">✓</span></button>
                    </div>
                </div>

                <p class="sp-quiz-hint"><span aria-hidden="true">ⓘ</span> You cannot change your answers once you
                    submit.</p>
            </form>

            <?php // Sent on pagehide: leaving a running exam marks it failed. ?>
            <form id="sp-exam-leave" hidden>
                <?php sp_portal_nonce_field('leave_beacon'); ?>
                <input type="hidden" name="assignment_id" value="<?php echo esc_attr($sp_id); ?>" />
            </form>
        </section>

        <div class="sp-quiz-drawer-backdrop" hidden data-sp-drawer-backdrop></div>

        <aside class="sp-quiz-side" aria-label="Question navigator">
            <div class="sp-quiz-side-card">
                <div class="sp-quiz-score" data-sp-score hidden>
                    <span class="sp-quiz-score-pct" data-sp-score-pct></span>
                    <span class="sp-quiz-score-label">answers correct</span>
                </div>

                <div class="sp-quiz-side-heading">
                    <div>
                        <span class="sp-quiz-side-eyebrow">YOUR WORKSPACE</span>
                        <h2>Question navigator</h2>
                    </div>
                    <span class="sp-quiz-total-pill"><?php echo esc_html($sp_total_q); ?> total</span>
                </div>

                <p class="sp-quiz-side-help">Jump directly to any question. Your current question is highlighted.</p>

                <div class="sp-quiz-navlist-scroll" tabindex="0" aria-label="Scrollable question list">
                    <ol class="sp-quiz-navlist">
                        <?php foreach ($sp_questions as $sp_index => $sp_question): ?>
                        <li><button type="button"
                                class="sp-quiz-navitem<?php echo 0 === $sp_index ? ' is-current' : ''; ?>"
                                data-sp-goto="<?php echo esc_attr($sp_index); ?>"
                                aria-label="Go to question <?php echo esc_attr($sp_index + 1); ?>"><span
                                    class="sp-quiz-nav-number"><?php echo esc_html($sp_index + 1); ?></span></button>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <div class="sp-quiz-side-foot"><span class="sp-quiz-side-foot-icon"
                        aria-hidden="true">✓</span><span>Remember to submit your exam before the timer ends.</span>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php elseif ('submitted' === $sp_state): ?>

<?php
$sp_answers   = sp_get_attempt_answers((int) $sp_row->id);
$sp_score     = (int) $sp_row->score;
$sp_total     = (int) $sp_row->total;
$sp_percent   = $sp_total > 0 ? (int) round(($sp_score / $sp_total) * 100) : 0;
$sp_questions = $sp_set_id ? sp_get_set_questions($sp_set_id) : [];
$sp_total_q   = count($sp_questions);
$sp_wrong     = max(0, $sp_total - $sp_score);

// Pass mark is a presentation default; override with the sp_exam_pass_mark filter.
$sp_pass_mark = (int) apply_filters('sp_exam_pass_mark', 50, $sp_task);
$sp_passed    = $sp_percent >= $sp_pass_mark;

$sp_right_w = $sp_total > 0 ? min(100, max(0, ($sp_score / $sp_total) * 100)) : 0;
$sp_wrong_w = $sp_total > 0 ? min(100 - $sp_right_w, ($sp_wrong / $sp_total) * 100) : 0;

$sp_secs = 0;
if (! empty($sp_row->started_at) && ! empty($sp_row->submitted_at)) {
    $sp_secs = max(0, strtotime($sp_row->submitted_at) - strtotime($sp_row->started_at));
}
$sp_taken = $sp_secs ? sprintf('%dm %02ds', (int) floor($sp_secs / 60), $sp_secs % 60) : '—';

// Pre-compute right/wrong per question for the filter counts.
$sp_right_count = 0;
$sp_results     = [];
foreach ($sp_questions as $sp_i => $sp_q) {
    $sp_a              = isset($sp_answers[(int) $sp_q->id]) ? $sp_answers[(int) $sp_q->id] : null;
    $sp_results[$sp_i] = (bool) ($sp_a && 1 === (int) $sp_a->correct);
    if ($sp_results[$sp_i]) {
        $sp_right_count++;
    }
}
$sp_wrong_count = max(0, $sp_total_q - $sp_right_count);
?>

<div class="sp-quiz sp-quiz-review" data-sp-quiz>
    <div class="sp-quiz-topline">
        <div class="sp-quiz-topline-brand">
            <span class="sp-quiz-brand-mark" aria-hidden="true">S</span>
            <span>Student Portal</span>
        </div>

        <div class="sp-quiz-session-status is-complete sp-portal-badge sp-portal-badge--brand" role="status">
            <span class="sp-quiz-live-dot" aria-hidden="true"></span>
            <span>Exam submitted</span>
        </div>
    </div>

    <section class="sp-review-summary" aria-label="Exam result">
        <div class="sp-review-summary-top">
            <div class="sp-review-heading">
                <div class="sp-review-tags">
                    <span
                        class="sp-review-chip"><?php echo esc_html(sp_assignment_type_label($sp_task->type)); ?></span>
                </div>

                <h1 class="sp-quiz-title"><?php echo esc_html($sp_task->title); ?></h1>

                <p class="sp-review-meta">
                    <?php if (! empty($sp_task->class_title)): ?>
                    Course: <strong><?php echo esc_html($sp_task->class_title); ?></strong>
                    <?php endif; ?>
                    <?php if (! empty($sp_row->submitted_at)): ?>
                    <?php if (! empty($sp_task->class_title)): ?><span class="sp-meta-separator"
                        aria-hidden="true">•</span><?php endif; ?>
                    Submitted:
                    <strong><?php echo esc_html(sp_portal_time(get_option('date_format') . ', H:i', strtotime($sp_row->submitted_at))); ?></strong>
                    <?php endif; ?>
                </p>
            </div>

            <div class="sp-review-summary-side">
                <div class="sp-review-result<?php echo $sp_passed ? ' is-pass' : ''; ?>">
                    <span class="sp-review-result-pct"><?php echo esc_html(sp_format_number($sp_percent)); ?>%</span>
                    <span class="sp-review-result-copy">
                        <span class="sp-review-result-label">Final result</span>
                        <span class="sp-review-result-verdict">
                            <?php echo $sp_passed ? 'Passed' : 'Needs Improvement'; ?>
                            <small>(Pass mark: <?php echo esc_html($sp_pass_mark); ?>%)</small>
                        </span>
                    </span>
                </div>

                <button type="button" class="sp-btn sp-btn-quiet sp-quiz-drawer-toggle" data-sp-drawer-toggle
                    aria-expanded="false" aria-label="Show question navigator">
                    <span aria-hidden="true">☰</span>
                </button>
            </div>
        </div>

        <div class="sp-review-metrics">
            <div class="sp-review-metric">
                <span class="sp-review-metric-label">Score</span>
                <span class="sp-review-metric-value"><?php echo esc_html($sp_score); ?> /
                    <?php echo esc_html($sp_total); ?>
                    <small>(<?php echo esc_html(sp_format_number($sp_percent)); ?>%)</small></span>
            </div>
            <div class="sp-review-metric">
                <span class="sp-review-metric-label"><span class="sp-review-dot is-ok"></span> Correct</span>
                <span class="sp-review-metric-value is-ok"><?php echo esc_html($sp_score); ?></span>
            </div>
            <div class="sp-review-metric">
                <span class="sp-review-metric-label"><span class="sp-review-dot is-bad"></span> Incorrect</span>
                <span class="sp-review-metric-value is-bad"><?php echo esc_html($sp_wrong); ?></span>
            </div>
            <div class="sp-review-metric">
                <span class="sp-review-metric-label">Time spent</span>
                <span class="sp-review-metric-value"><?php echo esc_html($sp_taken); ?></span>
            </div>
        </div>

        <div class="sp-review-bar" role="progressbar" aria-label="Score" aria-valuemin="0" aria-valuemax="100"
            aria-valuenow="<?php echo (int) min(100, max(0, $sp_percent)); ?>">
            <div class="sp-review-bar-ok" style="width: <?php echo esc_attr(round($sp_right_w, 2)); ?>%"></div>
            <div class="sp-review-bar-bad" style="width: <?php echo esc_attr(round($sp_wrong_w, 2)); ?>%"></div>
        </div>
    </section>

    <div class="sp-quiz-layout">
        <section class="sp-quiz-content" aria-label="Exam result review">
            <?php // Kept for the quiz script; the visual progress now lives in the summary bar. ?>
            <div class="sp-quiz-progress" hidden>
                <div class="sp-quiz-progress-bar" data-sp-progress style="width:100%"></div>
            </div>

            <div class="sp-quiz-form sp-quiz-review-form">
                <?php foreach ($sp_questions as $sp_index => $sp_question): ?>
                <?php
                $sp_answer      = isset($sp_answers[(int) $sp_question->id]) ? $sp_answers[(int) $sp_question->id] : null;
                $sp_given       = $sp_answer ? (string) $sp_answer->given : '';
                $sp_right       = $sp_results[$sp_index];
                $sp_opts        = sp_question_options($sp_question);
                $sp_given_idx   = sp_question_answer_index($sp_question, $sp_given);
                $sp_correct_idx = sp_question_answer_index($sp_question, isset($sp_question->answer) ? $sp_question->answer : '');
                $sp_pos         = 0;
                ?>

                <section class="sp-quiz-question<?php echo 0 === $sp_index ? ' is-active' : ''; ?>"
                    data-sp-question="<?php echo esc_attr($sp_index); ?>"
                    data-sp-right="<?php echo $sp_right ? '1' : '0'; ?>"
                    aria-label="Question <?php echo esc_attr($sp_index + 1); ?>">
                    <div class="sp-quiz-question-top">
                        <p class="sp-quiz-qnum">Question <?php echo esc_html($sp_index + 1); ?><span> of
                                <?php echo esc_html($sp_total_q); ?></span></p>

                        <span
                            class="sp-quiz-answer-status sp-portal-badge <?php echo $sp_right ? 'is-correct sp-portal-badge--success' : 'is-incorrect sp-portal-badge--danger'; ?>">
                            <span aria-hidden="true"><?php echo $sp_right ? '✓' : '✕'; ?></span>
                            <?php echo $sp_right ? 'Correct' : 'Incorrect'; ?>
                        </span>
                    </div>

                    <p class="sp-quiz-prompt"><?php echo esc_html($sp_question->prompt); ?></p>

                    <div class="sp-quiz-options">
                        <?php if ($sp_opts): ?>
                        <?php foreach ($sp_opts as $sp_key => $sp_opt): ?>
                        <?php
                        $sp_is_given  = (null !== $sp_given_idx && (int) $sp_key === $sp_given_idx);
                        $sp_is_answer = (null !== $sp_correct_idx && (int) $sp_key === $sp_correct_idx);
                        $sp_letter    = chr(65 + ($sp_pos % 26));
                        $sp_pos++;
                        $sp_opt_class = 'sp-quiz-option';
                        $sp_mark      = $sp_letter;
                        $sp_prefix    = '';
                        $sp_sub       = '';

                        if ($sp_is_answer) {
                            $sp_opt_class .= ' is-correct';
                            $sp_mark       = '✓';
                            $sp_prefix     = $sp_letter . '. ';
                            $sp_sub        = $sp_is_given ? 'Your submitted answer' : 'Official answer key';
                        } elseif ($sp_is_given) {
                            $sp_opt_class .= ' is-wrong';
                            $sp_mark       = '✕';
                            $sp_prefix     = $sp_letter . '. ';
                            $sp_sub        = 'Your submitted answer';
                        } else {
                            $sp_sub = 'Unselected';
                        }
                        ?>
                        <div class="<?php echo esc_attr($sp_opt_class); ?>">
                            <span class="sp-quiz-option-mark"
                                aria-hidden="true"><?php echo esc_html($sp_mark); ?></span>

                            <span class="sp-quiz-option-body">
                                <span class="sp-quiz-option-text"><?php echo esc_html($sp_prefix . $sp_opt); ?></span>
                                <span class="sp-quiz-option-sub"><?php echo esc_html($sp_sub); ?></span>
                            </span>

                            <?php if ($sp_is_answer): ?>
                            <span class="sp-quiz-option-tag">Correct answer</span>
                            <?php elseif ($sp_is_given): ?>
                            <span class="sp-quiz-option-tag">Your answer</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <?php else: ?>

                        <p class="sp-quiz-given<?php echo $sp_right ? ' is-correct' : ' is-wrong'; ?>">
                            <span
                                class="sp-quiz-given-label sp-portal-badge <?php echo $sp_right ? 'sp-portal-badge--success' : 'sp-portal-badge--danger'; ?>">Your
                                answer</span>
                            <strong><?php echo esc_html(sp_attempt_answer_label($sp_question, $sp_given)); ?></strong>
                        </p>

                        <p class="sp-quiz-given is-answer">
                            <span class="sp-quiz-given-label sp-portal-badge sp-portal-badge--success">Correct
                                answer</span>
                            <strong><?php echo esc_html(sp_attempt_correct_label($sp_question)); ?></strong>
                        </p>

                        <?php endif; ?>
                    </div>

                    <?php if (! empty($sp_question->explanation)): ?>
                    <div class="sp-quiz-explain">
                        <span class="sp-quiz-explain-label">Explanation</span>
                        <p><?php echo esc_html($sp_question->explanation); ?></p>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>

                <div class="sp-quiz-nav">
                    <button type="button" class="sp-btn sp-btn-quiet" data-sp-prev disabled>
                        <svg class="sp-btn-icon" viewBox="0 0 20 20" width="18" height="18" fill="none"
                            aria-hidden="true" focusable="false">
                            <path d="M12.5 4.5 7 10l5.5 5.5" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Previous question</span>
                    </button>

                    <button type="button" class="sp-btn sp-btn-primary" data-sp-next>
                        <span>Next question</span>
                        <svg class="sp-btn-icon" viewBox="0 0 20 20" width="18" height="18" fill="none"
                            aria-hidden="true" focusable="false">
                            <path d="M7.5 4.5 13 10l-5.5 5.5" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <div class="sp-quiz-drawer-backdrop" hidden data-sp-drawer-backdrop></div>

        <aside class="sp-quiz-side" aria-label="Question navigator">
            <div class="sp-quiz-side-card">
                <div class="sp-quiz-side-heading">
                    <div>
                        <span class="sp-quiz-side-eyebrow">ANSWER SUMMARY</span>
                        <h2>Question navigator</h2>
                    </div>
                    <span class="sp-quiz-total-pill"><?php echo esc_html($sp_total_q); ?> total</span>
                </div>

                <div class="sp-quiz-filter" role="group" aria-label="Filter questions">
                    <button type="button" class="is-active" data-sp-filter="all" aria-pressed="true">All
                        (<?php echo esc_html($sp_total_q); ?>)</button>
                    <button type="button" data-sp-filter="wrong" aria-pressed="false">Wrong
                        (<?php echo esc_html($sp_wrong_count); ?>)</button>
                    <button type="button" data-sp-filter="right" aria-pressed="false">Right
                        (<?php echo esc_html($sp_right_count); ?>)</button>
                </div>

                <div class="sp-quiz-legend" aria-hidden="true">
                    <span><i class="is-ok"></i> Correct (<?php echo esc_html($sp_right_count); ?>)</span>
                    <span><i class="is-bad"></i> Incorrect (<?php echo esc_html($sp_wrong_count); ?>)</span>
                    <span><i class="is-now"></i> Current</span>
                </div>

                <div class="sp-quiz-navlist-scroll" tabindex="0" aria-label="Scrollable question list">
                    <ol class="sp-quiz-navlist">
                        <?php foreach ($sp_questions as $sp_index => $sp_question): ?>
                        <?php $sp_right = $sp_results[$sp_index]; ?>
                        <li data-sp-result="<?php echo $sp_right ? 'right' : 'wrong'; ?>"><button type="button"
                                class="sp-quiz-navitem<?php echo 0 === $sp_index ? ' is-current' : ''; ?><?php echo $sp_right ? ' is-right' : ' is-wrong'; ?>"
                                data-sp-goto="<?php echo esc_attr($sp_index); ?>"
                                title="Question <?php echo esc_attr($sp_index + 1); ?> (<?php echo $sp_right ? 'Correct' : 'Incorrect'; ?>)"
                                aria-label="Review question <?php echo esc_attr($sp_index + 1); ?> (<?php echo $sp_right ? 'correct' : 'incorrect'; ?>)"><span
                                    class="sp-quiz-nav-number"><?php echo esc_html($sp_index + 1); ?></span></button>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
// Navigator filter (All / Wrong / Right). Presentation only; navigation stays with the quiz script.
(function() {
    var root = document.querySelector('.sp-quiz-review');
    if (!root) {
        return;
    }
    var tabs = root.querySelectorAll('[data-sp-filter]');
    var items = root.querySelectorAll('.sp-quiz-navlist li[data-sp-result]');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var f = tab.getAttribute('data-sp-filter');
            tabs.forEach(function(t) {
                var on = (t === tab);
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            items.forEach(function(li) {
                li.hidden = !(f === 'all' || li.getAttribute('data-sp-result') === f);
            });
        });
    });
})();
</script>

<?php elseif ('available' === $sp_state): ?>

<?php $sp_questions = $sp_set_id ? sp_get_set_questions($sp_set_id) : []; ?>

<div class="sp-task-page sp-exam-page">
    <article class="sp-card sp-exam-start">
        <div class="sp-exam-start-icon" aria-hidden="true">
            <svg viewBox="0 0 48 48" width="34" height="34" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="8" y="5" width="32" height="38" rx="8" stroke="currentColor" stroke-width="2" />
                <path d="M17 17h14M17 24h14M17 31h7" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="m29 32 3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
            </svg>
        </div>

        <div class="sp-exam-state-badge-row"><span class="sp-portal-badge sp-portal-badge--brand">✓ Assessment
                ready</span></div>

        <h1 class="sp-exam-start-title"><?php echo esc_html($sp_task->title); ?></h1>

        <p class="sp-exam-start-sub">
            <?php echo esc_html($sp_task->class_title); ?>
            <?php if (! empty($sp_task->due_date)): ?><span class="sp-meta-separator"
                aria-hidden="true">/</span><?php echo esc_html(sp_portal_time(get_option('date_format'), strtotime($sp_task->due_date))); ?><?php endif; ?>
        </p>

        <ul class="sp-exam-facts">
            <?php if ($sp_questions): ?><li><span class="sp-exam-fact-icon"
                    aria-hidden="true">☰</span><span><strong><?php echo esc_html(count($sp_questions)); ?></strong><small>Questions</small></span>
            </li><?php endif; ?>
            <?php if (! empty($sp_task->time_limit_minutes)): ?><li><span class="sp-exam-fact-icon"
                    aria-hidden="true">⏱</span><span><strong><?php echo esc_html((int) $sp_task->time_limit_minutes); ?></strong><small>Minutes</small></span>
            </li><?php endif; ?>
            <li><span class="sp-exam-fact-icon"
                    aria-hidden="true">✓</span><span><strong>1</strong><small>Attempt</small></span></li>
        </ul>

        <?php if (! empty($sp_task->description)): ?>
        <div class="sp-note-body sp-exam-start-description"><?php echo wp_kses_post(wpautop($sp_task->description)); ?>
        </div>
        <?php endif; ?>

        <form method="post" class="sp-exam-start-form">
            <?php sp_portal_nonce_field('start_assignment'); ?>
            <input type="hidden" name="assignment_id" value="<?php echo esc_attr($sp_id); ?>" />
            <button type="submit" class="sp-btn sp-btn-primary sp-exam-start-btn">Start exam <span
                    aria-hidden="true">→</span></button>
        </form>

        <p class="sp-exam-start-hint"><span aria-hidden="true">ⓘ</span> Once you start, the clock keeps running until
            you submit. Leaving the page counts as a failed attempt.</p>
    </article>
</div>

<?php elseif ('failed' === $sp_state): ?>

<div class="sp-task-page sp-exam-page">
    <article class="sp-card sp-exam-start sp-exam-state-card">
        <div class="sp-exam-state-icon is-failed" aria-hidden="true">✗</div>
        <div class="sp-exam-state-badge-row"><span class="sp-portal-badge sp-portal-badge--danger">✕ Attempt
                failed</span></div>
        <h1 class="sp-exam-start-title">This attempt was marked failed</h1>
        <p class="sp-exam-start-sub">You left the exam before submitting it.</p>
        <p class="sp-exam-start-hint">Your teacher can allow you to retake this exam.</p>
    </article>
</div>

<?php elseif ('upcoming' === $sp_state): ?>

<div class="sp-task-page sp-exam-page">
    <article class="sp-card sp-exam-start sp-exam-state-card">
        <div class="sp-exam-state-icon" aria-hidden="true">📅</div>
        <div class="sp-exam-state-badge-row"><span class="sp-portal-badge sp-portal-badge--brand">◷ Upcoming
                assessment</span></div>
        <h1 class="sp-exam-start-title">Scheduled for
            <?php echo esc_html(sp_portal_time(get_option('date_format'), strtotime($sp_task->due_date))); ?></h1>
        <p class="sp-exam-start-sub">You will be able to start this exam on that day.</p>
    </article>
</div>

<?php elseif ('missed' === $sp_state): ?>

<div class="sp-task-page sp-exam-page">
    <article class="sp-card sp-exam-start sp-exam-state-card">
        <div class="sp-exam-state-icon is-failed" aria-hidden="true">!</div>
        <div class="sp-exam-state-badge-row"><span class="sp-portal-badge sp-portal-badge--danger">! Assessment
                closed</span></div>
        <h1 class="sp-exam-start-title">You did not sit this exam</h1>
        <p class="sp-exam-start-sub">It was scheduled for
            <?php echo esc_html(sp_portal_time(get_option('date_format'), strtotime($sp_task->due_date))); ?>.</p>
        <p class="sp-exam-start-hint">Ask your teacher if you need another chance.</p>
    </article>
</div>

<?php else: ?>

<div class="sp-task-page sp-exam-page">
    <div class="sp-empty">
        <span
            class="sp-portal-badge sp-portal-badge--warning"><?php echo 'closed' === $sp_state ? 'Exam closed' : 'Unavailable'; ?></span>
        <strong><?php echo 'closed' === $sp_state ? 'This exam is closed.' : 'This exam is not available.'; ?></strong>
        Please contact your teacher if you believe this is a mistake.
    </div>
</div>

<?php endif; ?>

<?php endif; ?>

<?php endif; ?>