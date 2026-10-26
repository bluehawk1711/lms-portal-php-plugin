<?php
    /**
 * Student results.
 *
 * @package StudentPortal
 */

    if (! defined('ABSPATH')) {
    exit;
    }

    if (empty($profile)) {
    ?>
<div class="sp-notice sp-notice-warn">
    This account is not linked to a student profile yet. Ask your institute to create your account.
</div>
    <?php
        return;
        }

        $sp_results = sp_get_student_results($profile->id);
    ?>

<div class="sp-page-head">
    <h1>Results &amp; Academic Performance</h1>
    <p class="sp-page-sub">Published evaluations, examination scores, and validated grade transcripts for your enrolled course.</p>
</div>

<?php if (! $sp_results): ?>
<div class="sp-empty">
    <span class="sp-empty-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
    </span>
    <strong>No results published yet</strong>
    Your published evaluations and grade transcripts will appear here once the academic cell releases them.
</div>
<?php else: ?>
<div class="sp-card-list">
    <?php
        foreach ($sp_results as $sp_row):
            $sp_finalised = $sp_row->percentage !== null && (int) $sp_row->finalised === 1;

            // Computed metrics for KPI tiles + perf bars.
            $sp_pct           = $sp_finalised && $sp_row->percentage !== null ? (float) $sp_row->percentage : 0.0;
            $sp_theory_max    = (float) ($sp_row->theory_max ?? 0);
            $sp_theory_obt    = (float) ($sp_row->theory_obtained ?? 0);
            $sp_practical_max = (float) ($sp_row->practical_max ?? 0);
            $sp_practical_obt = (float) ($sp_row->practical_obtained ?? 0);
            $sp_theory_pct    = $sp_theory_max > 0 ? ($sp_theory_obt / $sp_theory_max) * 100 : 0.0;
            $sp_practical_pct = $sp_practical_max > 0 ? ($sp_practical_obt / $sp_practical_max) * 100 : 0.0;
            $sp_total_obt     = $sp_theory_obt + $sp_practical_obt;
            $sp_total_max     = $sp_theory_max + $sp_practical_max;
            $sp_cum_pct       = $sp_total_max > 0 ? ($sp_total_obt / $sp_total_max) * 100 : 0.0;

            // Color tier helper: high (>=75) emerald, mid (40-75) amber, low (<40) rose.
            $sp_tier = static function ($pct) {
                if ($pct >= 75) {
                    return 'is-high';
                } elseif ($pct >= 40) {
                return 'is-mid';
            }
            return 'is-low';
        };
        $sp_theory_tier    = $sp_tier($sp_theory_pct);
        $sp_practical_tier = $sp_tier($sp_practical_pct);

        // Two-letter course badge (e.g. GD for Graphic Design).
        $sp_initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $sp_row->class_title), 0, 2));
        $sp_initials = $sp_initials ? $sp_initials : 'SP';
    ?>
    <section class="sp-results-hero" data-purpose="results-hero-card">
        <header class="sp-hero-header">
            <div class="sp-hero-id">
                <div class="sp-hero-id-badge" aria-hidden="true"><?php echo esc_html($sp_initials); ?></div>
                <div class="sp-hero-id-text">
                    <h2 class="sp-hero-title"><?php echo esc_html($sp_row->class_title); ?></h2>
                    <div class="sp-hero-meta">
                        <?php echo wp_kses_post(sp_result_badge($sp_row->result)); ?>
                        <?php if ($sp_row->grade): ?>
                        <span class="sp-badge sp-badge-grade">Grade <?php echo esc_html($sp_row->grade); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <?php if (! $sp_finalised): ?>
        <div class="sp-hero-body">
            <p class="sp-muted">Your result has not been published yet. Please check back after evaluation is finalised by the academic cell.</p>
        </div>
        <?php else: ?>
        <div class="sp-hero-body">
            <div class="sp-hero-grid">
                <div class="sp-hero-radial-wrap">
                    <div class="sp-radial-progress" style="--sp-radial-pct: <?php echo esc_attr($sp_pct); ?>;">
                        <div class="sp-radial-inner">
                            <span class="sp-radial-num"><?php echo esc_html(sp_format_number($sp_pct)); ?>%</span>
                            <span class="sp-radial-label">Overall</span>
                        </div>
                    </div>
                    <div class="sp-radial-text">
                        <?php echo wp_kses_post(sp_result_badge($sp_row->result)); ?>
                        <h3 class="sp-radial-title">Final Standing</h3>
                        <p class="sp-radial-desc">Aggregated evaluation across theoretical &amp; practical components, validated against the institute grading standard.</p>
                    </div>
                </div>

                <div class="sp-kpi-grid">
                    <div class="sp-kpi-tile">
                        <div class="sp-kpi-top">
                            <span class="sp-kpi-label">Theory</span>
                            <span class="sp-kpi-pct <?php echo esc_attr($sp_theory_tier); ?>"><?php echo esc_html(sp_format_number($sp_theory_pct)); ?>%</span>
                        </div>
                        <div class="sp-kpi-value">
                            <span class="sp-kpi-num"><?php echo esc_html(sp_format_number($sp_theory_obt)); ?></span>
                            <span class="sp-kpi-max">/ <?php echo esc_html(sp_format_number($sp_theory_max)); ?></span>
                        </div>
                        <div class="sp-kpi-bar"><div class="<?php echo esc_attr($sp_theory_tier); ?>" style="width: <?php echo esc_attr($sp_theory_pct); ?>%;"></div></div>
                    </div>

                    <div class="sp-kpi-tile">
                        <div class="sp-kpi-top">
                            <span class="sp-kpi-label">Practical</span>
                            <span class="sp-kpi-pct <?php echo esc_attr($sp_practical_tier); ?>"><?php echo esc_html(sp_format_number($sp_practical_pct)); ?>%</span>
                        </div>
                        <div class="sp-kpi-value">
                            <span class="sp-kpi-num"><?php echo esc_html(sp_format_number($sp_practical_obt)); ?></span>
                            <span class="sp-kpi-max">/ <?php echo esc_html(sp_format_number($sp_practical_max)); ?></span>
                        </div>
                        <div class="sp-kpi-bar"><div class="<?php echo esc_attr($sp_practical_tier); ?>" style="width: <?php echo esc_attr($sp_practical_pct); ?>%;"></div></div>
                    </div>

                    <div class="sp-kpi-tile">
                        <div class="sp-kpi-top">
                            <span class="sp-kpi-label">Cumulative</span>
                            <span class="sp-kpi-pct is-pass">Passed</span>
                        </div>
                        <div class="sp-kpi-value">
                            <span class="sp-kpi-num"><?php echo esc_html(sp_format_number($sp_total_obt)); ?></span>
                            <span class="sp-kpi-max">/ <?php echo esc_html(sp_format_number($sp_total_max)); ?></span>
                        </div>
                        <div class="sp-kpi-bar"><div class="<?php echo esc_attr($sp_tier($sp_cum_pct)); ?>" style="width: <?php echo esc_attr($sp_cum_pct); ?>%;"></div></div>
                    </div>
                </div>
            </div>

            <div class="sp-table-wrap sp-hero-table">
                <div class="sp-table-head">
                    <span class="sp-table-head-label">Evaluation Component Breakdown</span>
                    <span class="sp-table-head-meta">Component-level marks</span>
                </div>
                <table class="sp-table">
                    <thead>
                        <tr>
                            <th scope="col">Component</th>
                            <th scope="col">Max Marks</th>
                            <th scope="col">Marks Obtained</th>
                            <th scope="col">Performance</th>
                            <th scope="col">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-label="Component">
                                <span class="sp-dot sp-dot-theory" aria-hidden="true"></span>
                                Theory Examination
                            </td>
                            <td data-label="Max Marks"><?php echo esc_html(sp_format_number($sp_theory_max)); ?></td>
                            <td data-label="Marks Obtained"><?php echo esc_html(sp_format_number($sp_theory_obt)); ?></td>
                            <td data-label="Performance">
                                <div class="sp-perf-bar">
                                    <div class="sp-perf-track"><div class="<?php echo esc_attr($sp_theory_tier); ?>" style="width: <?php echo esc_attr($sp_theory_pct); ?>%;"></div></div>
                                    <span class="sp-perf-num"><?php echo esc_html(sp_format_number($sp_theory_pct)); ?>%</span>
                                </div>
                            </td>
                            <td data-label="Grade"><?php echo $sp_row->grade ? esc_html('Grade ' . $sp_row->grade) : '—'; ?></td>
                        </tr>
                        <tr>
                            <td data-label="Component">
                                <span class="sp-dot sp-dot-practical" aria-hidden="true"></span>
                                Practical Lab &amp; Project
                            </td>
                            <td data-label="Max Marks"><?php echo esc_html(sp_format_number($sp_practical_max)); ?></td>
                            <td data-label="Marks Obtained"><?php echo esc_html(sp_format_number($sp_practical_obt)); ?></td>
                            <td data-label="Performance">
                                <div class="sp-perf-bar">
                                    <div class="sp-perf-track"><div class="<?php echo esc_attr($sp_practical_tier); ?>" style="width: <?php echo esc_attr($sp_practical_pct); ?>%;"></div></div>
                                    <span class="sp-perf-num"><?php echo esc_html(sp_format_number($sp_practical_pct)); ?>%</span>
                                </div>
                            </td>
                            <td data-label="Grade"><?php echo $sp_row->grade ? esc_html('Grade ' . $sp_row->grade) : '—'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if ($sp_row->updated_at): ?>
            <div class="sp-hero-footer">
                <span class="sp-hero-footer-updated">
                    <svg class="sp-hero-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 8v4l3 3"/>
                    </svg>
                    Last updated
                    <?php echo esc_html(sp_portal_time(get_option('date_format') . ' ' . get_option('time_format'), strtotime($sp_row->updated_at))); ?>
                </span>
                <span class="sp-hero-footer-tag">Evaluation Authority: Academic Examination Cell</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
    /*
 * Exam reviews: every submitted exam with the correct answer and the
 * teacher's explanation for each question, right on the Results screen.
 */
    $sp_exams = [];

    foreach (sp_get_assignments_for_profile($profile->id) as $sp_exam_task) {
    if ('exam' !== $sp_exam_task->type) {
        continue;
    }

    $sp_exam_row = ! empty($sp_exam_task->student_row_id)
        ? sp_get_student_assignment((int) $sp_exam_task->id, (int) $profile->id)
        : null;

    if ($sp_exam_row && 'submitted' === $sp_exam_row->status) {
        $sp_exams[] = [
            'task' => $sp_exam_task,
            'row'  => $sp_exam_row,
        ];
    }
    }
?>

<?php if ($sp_exams): ?>
<section class="sp-rx-section" aria-labelledby="sp-rx-title">
    <div class="sp-rx-head">
        <h2 id="sp-rx-title">Exam Reviews &amp; Answer Keys</h2>
        <p>Tap any exam to open the full per-question review with correct answers and teacher explanations.</p>
        <span class="sp-rx-count"><?php echo esc_html(count($sp_exams)); ?> Submission<?php echo count($sp_exams) > 1 ? 's' : ''; ?> Available</span>
    </div>

    <div class="sp-rx-list">
    <?php foreach ($sp_exams as $sp_exam): ?>
        <?php
            $sp_exam_task = $sp_exam['task'];
            $sp_exam_row  = $sp_exam['row'];
            $sp_exam_pct  = (int) $sp_exam_row->total > 0 ? (int) round(((int) $sp_exam_row->score / (int) $sp_exam_row->total) * 100) : 0;
            $sp_exam_url  = sp_portal_url('tasks/' . (int) $sp_exam_task->id);
        ?>
    <a class="sp-rx-card" href="<?php echo esc_url($sp_exam_url); ?>">
        <span class="sp-rx-card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </span>
        <span class="sp-rx-card-main">
            <strong class="sp-rx-card-title"><?php echo esc_html($sp_exam_task->title); ?></strong>
            <span class="sp-rx-card-meta">
                <?php if (! empty($sp_exam_row->submitted_at)): ?>
                <span>Submitted <?php echo esc_html(sp_portal_time(get_option('date_format'), strtotime($sp_exam_row->submitted_at))); ?></span>
                <?php endif; ?>
                <?php if (! empty($sp_exam_task->class_title)): ?>
                <span aria-hidden="true">&bull;</span>
                <span class="sp-rx-card-class"><?php echo esc_html($sp_exam_task->class_title); ?></span>
                <?php endif; ?>
                <?php if ((int) $sp_exam_row->total > 0): ?>
                <span aria-hidden="true">&bull;</span>
                <span><?php echo esc_html((int) $sp_exam_row->total); ?> Questions</span>
                <?php endif; ?>
            </span>
        </span>
        <span class="sp-rx-card-score<?php echo $sp_exam_pct >= 40 ? ' is-good' : ''; ?>">
            <strong><?php echo esc_html($sp_exam_row->score); ?>/<?php echo esc_html($sp_exam_row->total); ?></strong>
            <span><?php echo esc_html(sp_format_number($sp_exam_pct)); ?>%</span>
        </span>
        <span class="sp-rx-card-cta" aria-hidden="true">
            Review Answers
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 5l7 7-7 7"></path>
            </svg>
        </span>
    </a>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
