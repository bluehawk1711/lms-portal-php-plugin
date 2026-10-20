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

<?php if (! $profile): ?>
<section class="sp-task-message sp-empty" role="status">
    <span class="sp-task-message-icon" aria-hidden="true">i</span>
    <strong>Student profile not linked</strong>
    <p>This WordPress account is not linked to a student profile yet, so there is nothing to show here.</p>
</section>
<?php elseif (! $sp_task): ?>
<section class="sp-task-message sp-empty" role="status">
    <span class="sp-task-message-icon" aria-hidden="true">!</span>
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
                    <span><?php echo esc_html(sp_assignment_type_label($sp_task->type)); ?></span>
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
                <span><?php echo ! empty($sp_task->attachment_path) ? 'The file provided by your teacher is ready.' : 'Your teacher has not attached a file yet.'; ?></span>
            </div>
            <div class="sp-card-actions sp-paper-actions">
                <?php if (! empty($sp_task->attachment_path)): ?>
                <a class="sp-btn sp-btn-primary" href="<?php echo esc_url(sp_assignment_file_url($sp_id)); ?>">
                    Download paper <span aria-hidden="true">&#8599;</span>
                </a>
                <?php else: ?>
                <span class="sp-muted">File not yet available</span>
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
        <div class="sp-quiz-session-status"><span class="sp-quiz-live-dot"></span> Exam in progress</div>
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
                    <span aria-hidden="true">&#9776;</span>
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
                        <p class="sp-quiz-qnum">QUESTION <?php echo esc_html($sp_index + 1); ?><span> OF
                                <?php echo esc_html($sp_total_q); ?></span></p>
                        <span class="sp-quiz-question-type">Your response</span>
                    </div>
                    <p class="sp-quiz-prompt"><?php echo esc_html($sp_question->prompt); ?></p>
                    <div class="sp-quiz-options">
                        <?php sp_exam_question_inputs($sp_question); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
                    </div>
                </section>
                <?php endforeach; ?>

                <div class="sp-quiz-nav">
                    <button type="button" class="sp-btn sp-btn-quiet" data-sp-prev disabled><span
                            aria-hidden="true">&#8592;</span> Previous</button>
                    <div class="sp-quiz-nav-primary">
                        <span class="sp-quiz-nav-caption">Take your time and review carefully.</span>
                        <button type="button" class="sp-btn sp-btn-primary" data-sp-next>Next question <span
                                aria-hidden="true">&#8594;</span></button>
                        <button type="submit" class="sp-btn sp-btn-primary sp-quiz-submit-btn" data-sp-submit
                            hidden>Submit exam <span aria-hidden="true">&#10003;</span></button>
                    </div>
                </div>
                <p class="sp-quiz-hint"><span aria-hidden="true">&#9432;</span> You cannot change your answers once you
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
                    <div><span class="sp-quiz-side-eyebrow">YOUR WORKSPACE</span>
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
                                    class="sp-quiz-nav-number"><?php echo esc_html($sp_index + 1); ?></span><span
                                    class="sp-quiz-nav-label">Question
                                    <?php echo esc_html($sp_index + 1); ?></span><span class="sp-quiz-nav-chevron"
                                    aria-hidden="true">&#8594;</span></button></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <div class="sp-quiz-side-foot"><span class="sp-quiz-side-foot-icon"
                        aria-hidden="true">&#10003;</span><span>Remember to submit your exam before the timer
                        ends.</span></div>
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
?>
<div class="sp-quiz sp-quiz-review" data-sp-quiz>

    <div class="sp-quiz-topline">
        <div class="sp-quiz-topline-brand">
            <span class="sp-quiz-brand-mark" aria-hidden="true">S</span>
            <span>Student Portal</span>
        </div>

        <div class="sp-quiz-session-status is-complete" role="status">
            <span class="sp-quiz-live-dot" aria-hidden="true"></span>
            <span>Exam submitted</span>
        </div>
    </div>


    <div class="sp-quiz-layout">
        <section class="sp-quiz-content" aria-label="Exam result review">
            <header class="sp-quiz-head">
                <div class="sp-quiz-heading-copy">
                    <span class="sp-quiz-eyebrow">ASSESSMENT REVIEW</span>
                    <h1 class="sp-quiz-title"><?php echo esc_html($sp_task->title); ?></h1>
                    <?php if (! empty($sp_task->class_title)): ?><p class="sp-quiz-subtitle">
                        <?php echo esc_html($sp_task->class_title); ?></p><?php endif; ?>
                </div>
                <div class="sp-quiz-meta sp-quiz-review-meta">
                    <div class="sp-quiz-review-status"><span class="sp-quiz-review-status-icon"
                            aria-hidden="true">&#10003;</span><span><?php echo esc_html(sp_assignment_type_label($sp_task->type)); ?></span>
                    </div>
                    <?php echo sp_assignment_state_badge($sp_state); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge markup. ?>
                </div>
                <button type="button" class="sp-btn sp-btn-quiet sp-quiz-drawer-toggle" data-sp-drawer-toggle
                    aria-expanded="false" aria-label="Show question navigator">
                    <span aria-hidden="true">&#9776;</span>
                </button>
            </header>

            <div class="sp-quiz-progress-block">
                <div class="sp-quiz-progress-caption"><span>Review
                        progress</span><span><?php echo esc_html($sp_total_q); ?> questions</span></div>
                <div class="sp-quiz-progress">
                    <div class="sp-quiz-progress-bar" data-sp-progress style="width:100%"></div>
                </div>
            </div>

            <?php if (! empty($sp_row->submitted_at)): ?>
            <p class="sp-quiz-submitted"><span aria-hidden="true">&#10003;</span> Submitted
                <?php echo esc_html(sp_portal_time(get_option('date_format') . ' H:i', strtotime($sp_row->submitted_at))); ?>
            </p>
            <?php endif; ?>

            <?php
                $sp_secs = 0;
                if (! empty($sp_row->started_at) && ! empty($sp_row->submitted_at)) {
                    $sp_secs = max(0, strtotime($sp_row->submitted_at) - strtotime($sp_row->started_at));
                }

                $sp_taken = $sp_secs ? sprintf('%dm %02ds', (int) floor($sp_secs / 60), $sp_secs % 60) : '';
            ?>
            <section class="sp-quiz-result" role="status" aria-label="Exam result">
                <div class="sp-quiz-result-score">
                    <span
                        class="sp-quiz-result-pct"><?php echo esc_html(sp_format_number($sp_percent)); ?><small>%</small></span>
                    <span class="sp-quiz-result-score-label">Overall score</span>
                </div>
                <div class="sp-quiz-result-body">
                    <p class="sp-quiz-result-headline">You got <strong><?php echo esc_html($sp_score); ?></strong> out
                        of <strong><?php echo esc_html($sp_total); ?></strong> answers correct</p>
                    <div class="sp-quiz-result-track" role="progressbar" aria-label="Score" aria-valuemin="0"
                        aria-valuemax="100" aria-valuenow="<?php echo (int) min(100, max(0, $sp_percent)); ?>">
                        <span style="width: <?php echo (int) min(100, max(0, $sp_percent)); ?>%"></span>
                    </div>
                    <ul class="sp-quiz-result-facts">
                        <li class="is-good"><span aria-hidden="true">&#10003;</span>
                            <?php echo esc_html($sp_score); ?> correct</li>
                        <li class="is-bad"><span aria-hidden="true">&#10007;</span>
                            <?php echo esc_html(max(0, $sp_total - $sp_score)); ?> incorrect</li>
                        <?php if ($sp_taken): ?>
                        <li class="is-muted"><span aria-hidden="true">&#9201;</span> Taken in
                            <?php echo esc_html($sp_taken); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>
            <div class="sp-quiz-form sp-quiz-review-form">
                <?php foreach ($sp_questions as $sp_index => $sp_question): ?>
                <?php
                    $sp_answer      = isset($sp_answers[(int) $sp_question->id]) ? $sp_answers[(int) $sp_question->id] : null;
                    $sp_given       = $sp_answer ? (string) $sp_answer->given : '';
                    $sp_right       = $sp_answer && 1 === (int) $sp_answer->correct;
                    $sp_opts        = sp_question_options($sp_question);
                    $sp_given_idx   = sp_question_answer_index($sp_question, $sp_given);
                    $sp_correct_idx = sp_question_answer_index($sp_question, isset($sp_question->answer) ? $sp_question->answer : '');
                ?>
                <section class="sp-quiz-question<?php echo 0 === $sp_index ? ' is-active' : ''; ?>"
                    data-sp-question="<?php echo esc_attr($sp_index); ?>"
                    data-sp-right="<?php echo $sp_right ? '1' : '0'; ?>"
                    aria-label="Question <?php echo esc_attr($sp_index + 1); ?>">
                    <div class="sp-quiz-question-top">
                        <p class="sp-quiz-qnum">QUESTION <?php echo esc_html($sp_index + 1); ?><span> OF
                                <?php echo esc_html($sp_total_q); ?></span></p>
                        <span
                            class="sp-quiz-answer-status <?php echo $sp_right ? 'is-correct' : 'is-incorrect'; ?>"><?php echo $sp_right ? 'Correct answer' : 'Needs review'; ?></span>
                    </div>
                    <p class="sp-quiz-prompt"><?php echo esc_html($sp_question->prompt); ?></p>
                    <div class="sp-quiz-options">
                        <?php if ($sp_opts): ?>
                        <?php foreach ($sp_opts as $sp_key => $sp_opt): ?>
                        <?php
                            $sp_is_given  = (null !== $sp_given_idx && (int) $sp_key === $sp_given_idx);
                            $sp_is_answer = (null !== $sp_correct_idx && (int) $sp_key === $sp_correct_idx);
                            $sp_opt_class = 'sp-quiz-option';

                            if ($sp_is_answer) {
                                $sp_opt_class .= ' is-correct';
                            } elseif ($sp_is_given) {
                                $sp_opt_class .= ' is-wrong';
                            }
                        ?>
                        <div class="<?php echo esc_attr($sp_opt_class); ?>">
                            <span class="sp-quiz-option-mark"
                                aria-hidden="true"><?php echo $sp_is_answer ? '&#10003;' : ($sp_is_given ? '&#10007;' : '&bull;'); ?></span>
                            <span class="sp-quiz-option-text"><?php echo esc_html($sp_opt); ?></span>
                            <?php if ($sp_is_answer): ?><span class="sp-quiz-option-tag">Correct
                                answer</span><?php elseif ($sp_is_given): ?><span class="sp-quiz-option-tag">Your
                                answer</span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p class="sp-quiz-given<?php echo $sp_right ? ' is-correct' : ' is-wrong'; ?>">
                            <span class="sp-quiz-given-label">Your answer</span>
                            <strong><?php echo esc_html(sp_attempt_answer_label($sp_question, $sp_given)); ?></strong>
                        </p>
                        <p class="sp-quiz-given is-answer">
                            <span class="sp-quiz-given-label">Correct answer</span>
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
                    <button type="button" class="sp-btn sp-btn-quiet" data-sp-prev disabled><span
                            aria-hidden="true">&#8592;</span> Previous</button>
                    <button type="button" class="sp-btn sp-btn-primary" data-sp-next>Next question <span
                            aria-hidden="true">&#8594;</span></button>
                </div>
            </div>
        </section>

        <div class="sp-quiz-drawer-backdrop" hidden data-sp-drawer-backdrop></div>
        <aside class="sp-quiz-side" aria-label="Question navigator and score">
            <div class="sp-quiz-side-card">
                <div class="sp-quiz-score is-visible">
                    <span class="sp-quiz-score-label">YOUR RESULT</span>
                    <span
                        class="sp-quiz-score-pct"><?php echo esc_html(sp_format_number($sp_percent)); ?><small>%</small></span>
                    <span class="sp-quiz-score-label">You got <strong><?php echo esc_html($sp_score); ?></strong> out
                        of <strong><?php echo esc_html($sp_total); ?></strong> answers correct</span>
                    <div class="sp-quiz-score-track"><span
                            style="width:<?php echo esc_attr(min(100, max(0, $sp_percent))); ?>%"></span></div>
                </div>
                <div class="sp-quiz-side-heading sp-quiz-review-heading">
                    <div><span class="sp-quiz-side-eyebrow">ANSWER SUMMARY</span>
                        <h2>Question navigator</h2>
                    </div>
                    <span class="sp-quiz-total-pill"><?php echo esc_html($sp_total_q); ?> total</span>
                </div>
                <p class="sp-quiz-side-help">Select a question to review your response and the correct answer.</p>
                <div class="sp-quiz-navlist-scroll" tabindex="0" aria-label="Scrollable question list">
                    <ol class="sp-quiz-navlist">
                        <?php foreach ($sp_questions as $sp_index => $sp_question): ?>
                        <?php
                            $sp_answer = isset($sp_answers[(int) $sp_question->id]) ? $sp_answers[(int) $sp_question->id] : null;
                            $sp_right  = $sp_answer && 1 === (int) $sp_answer->correct;
                        ?>
                        <li><button type="button"
                                class="sp-quiz-navitem<?php echo 0 === $sp_index ? ' is-current' : ''; ?><?php echo $sp_right ? ' is-right' : ' is-wrong'; ?>"
                                data-sp-goto="<?php echo esc_attr($sp_index); ?>"
                                aria-label="Review question <?php echo esc_attr($sp_index + 1); ?>"><span
                                    class="sp-quiz-navmark"
                                    aria-hidden="true"><?php echo $sp_right ? '&#10003;' : '&#10007;'; ?></span><span
                                    class="sp-quiz-nav-label">Question
                                    <?php echo esc_html($sp_index + 1); ?></span><span class="sp-quiz-nav-chevron"
                                    aria-hidden="true">&#8594;</span></button></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </aside>
    </div>
</div>

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
        <span class="sp-quiz-eyebrow">ASSESSMENT READY</span>
        <h1 class="sp-exam-start-title"><?php echo esc_html($sp_task->title); ?></h1>
        <p class="sp-exam-start-sub">
            <?php echo esc_html($sp_task->class_title); ?>
            <?php if (! empty($sp_task->due_date)): ?><span class="sp-meta-separator"
                aria-hidden="true">/</span><?php echo esc_html(sp_portal_time(get_option('date_format'), strtotime($sp_task->due_date))); ?><?php endif; ?>
        </p>

        <ul class="sp-exam-facts">
            <?php if ($sp_questions): ?><li><span class="sp-exam-fact-icon"
                    aria-hidden="true">&#9776;</span><span><strong><?php echo esc_html(count($sp_questions)); ?></strong><small>Questions</small></span>
            </li><?php endif; ?>
            <?php if (! empty($sp_task->time_limit_minutes)): ?><li><span class="sp-exam-fact-icon"
                    aria-hidden="true">&#9201;</span><span><strong><?php echo esc_html((int) $sp_task->time_limit_minutes); ?></strong><small>Minutes</small></span>
            </li><?php endif; ?>
            <li><span class="sp-exam-fact-icon"
                    aria-hidden="true">&#10003;</span><span><strong>1</strong><small>Attempt</small></span></li>
        </ul>

        <?php if (! empty($sp_task->description)): ?>
        <div class="sp-note-body sp-exam-start-description">
            <?php echo wp_kses_post(wpautop($sp_task->description)); ?></div>
        <?php endif; ?>

        <form method="post" class="sp-exam-start-form">
            <?php sp_portal_nonce_field('start_assignment'); ?>
            <input type="hidden" name="assignment_id" value="<?php echo esc_attr($sp_id); ?>" />
            <button type="submit" class="sp-btn sp-btn-primary sp-exam-start-btn">Start exam <span
                    aria-hidden="true">&#8594;</span></button>
        </form>
        <p class="sp-exam-start-hint"><span aria-hidden="true">&#9432;</span> Once you start, the clock keeps running
            until you submit. Leaving the page counts as a failed attempt.</p>
    </article>
</div>

<?php elseif ('failed' === $sp_state): ?>
<div class="sp-task-page sp-exam-page">
    <article class="sp-card sp-exam-start sp-exam-state-card">
        <div class="sp-exam-state-icon is-failed" aria-hidden="true">&#10007;</div>
        <span class="sp-quiz-eyebrow">ATTEMPT STATUS</span>
        <h1 class="sp-exam-start-title">This attempt was marked failed</h1>
        <p class="sp-exam-start-sub">You left the exam before submitting it.</p>
        <p class="sp-exam-start-hint">Your teacher can allow you to retake this exam.</p>
    </article>
</div>

<?php elseif ('upcoming' === $sp_state): ?>
<div class="sp-task-page sp-exam-page">
    <article class="sp-card sp-exam-start sp-exam-state-card">
        <div class="sp-exam-state-icon" aria-hidden="true">&#128197;</div>
        <span class="sp-quiz-eyebrow">UPCOMING ASSESSMENT</span>
        <h1 class="sp-exam-start-title">Scheduled for
            <?php echo esc_html(sp_portal_time(get_option('date_format'), strtotime($sp_task->due_date))); ?>
        </h1>
        <p class="sp-exam-start-sub">You will be able to start this exam on that day.</p>
    </article>
</div>

<?php elseif ('missed' === $sp_state): ?>
<div class="sp-task-page sp-exam-page">
    <article class="sp-card sp-exam-start sp-exam-state-card">
        <div class="sp-exam-state-icon is-failed" aria-hidden="true">&#33;</div>
        <span class="sp-quiz-eyebrow">ASSESSMENT CLOSED</span>
        <h1 class="sp-exam-start-title">You did not sit this exam</h1>
        <p class="sp-exam-start-sub">It was scheduled for
            <?php echo esc_html(sp_portal_time(get_option('date_format'), strtotime($sp_task->due_date))); ?>.
        </p>
        <p class="sp-exam-start-hint">Ask your teacher if you need another chance.</p>
    </article>
</div>

<?php else: ?>
<div class="sp-task-page sp-exam-page">
    <div class="sp-empty">
        <strong><?php echo 'closed' === $sp_state ? 'This exam is closed.' : 'This exam is not available.'; ?></strong>Please
        contact your teacher if you believe this is a mistake.
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>