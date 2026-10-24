<?php
    /**
 * Tasks: every paper and exam a student can see.
 *
 * Expects $profile from sp_render_portal().
 *
 * @package StudentPortal
 */

    if (! defined('ABSPATH')) {
    exit;
    }

    $sp_tasks = $profile ? sp_get_assignments_for_profile((int) $profile->id) : [];
?>

<style id="sp-task-list-badge-styles">
.sp-portal-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .35rem;
    width: fit-content;
    max-width: 100%;
    padding: .32rem .65rem;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 700;
    line-height: 1.25;
    letter-spacing: .01em;
    text-decoration: none;
    vertical-align: middle;
    white-space: nowrap;
}

.sp-portal-badge--success {
    color: #166534;
    background: #ecfdf3;
    border-color: #bbf7d0;
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
    color: #b91c1c;
    background: #fef2f2;
    border-color: #fecaca;
}

.sp-portal-badge--neutral {
    color: #475569;
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.sp-portal-badge--muted {
    color: #64748b;
    background: #f8fafc;
    border-color: #e2e8f0;
}

.sp-task-list-empty {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: .5rem;
}

.sp-task-list-empty .sp-portal-badge {
    margin: 0;
}

.sp-task-list-card {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.sp-task-list-card .sp-material-head {
    align-items: flex-start;
    gap: 1rem;
}

.sp-task-list-main {
    flex: 1 1 auto;
    min-width: 0;
}

.sp-task-list-badges {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .45rem;
    margin: 0 0 .7rem;
}

.sp-task-list-badges .sp-portal-badge {
    white-space: normal;
}

.sp-task-list-card .sp-card-title {
    margin-top: 0;
    margin-bottom: .35rem;
    overflow-wrap: anywhere;
}

.sp-task-list-card .sp-task-class-name {
    margin: 0;
}

.sp-task-list-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: .6rem;
}

.sp-task-list-status {
    display: inline-flex;
    align-items: center;
    max-width: 100%;
}

.sp-task-list-open {
    flex: 0 0 auto;
}

@media (max-width: 600px) {
    .sp-task-list-card .sp-material-head {
        flex-direction: column;
    }

    .sp-task-list-actions {
        justify-content: flex-start;
    }

    .sp-task-list-card .sp-card-actions {
        justify-content: flex-start;
    }

    .sp-portal-badge {
        white-space: normal;
    }
}
</style>

<div class="sp-page-head">
    <p class="sp-page-sub">Papers from your teachers and the exams scheduled for your classes.</p>
</div>

<?php if (! $profile): ?>
<div class="sp-empty sp-task-list-empty" role="status">
    <span class="sp-portal-badge sp-portal-badge--warning">Profile required</span>
    <strong>Student profile not linked</strong>
    <p>This WordPress account is not linked to a student profile yet, so there is nothing to show here.</p>
</div>
<?php elseif (! $sp_tasks): ?>
<div class="sp-empty sp-task-list-empty" role="status">
    <span class="sp-portal-badge sp-portal-badge--neutral">No tasks yet</span>
    <strong>Nothing assigned yet</strong>
    <p>Your teachers will post papers and exams here.</p>
</div>
<?php else: ?>
<div class="sp-card-list sp-task-list">
    <?php foreach ($sp_tasks as $sp_task): ?>
    <?php
                $sp_row = null;

                if (! empty($sp_task->student_row_id)) {
                    // The join carries only what the feed-state needs.
                    $sp_row = (object) [
                        'id'             => (int) $sp_task->student_row_id,
                        'status'         => $sp_task->student_status,
                        'retake_granted' => (int) $sp_task->student_retake_granted,
                        'started_at'     => $sp_task->student_started_at,
                    ];
                }

                $sp_state      = sp_assignment_student_state($sp_task, $sp_row);
                $sp_url        = sp_portal_url('tasks/' . (int) $sp_task->id);
                $sp_date_label = 'exam' === $sp_task->type ? 'Exam day' : 'Due';
                $sp_type_label = sp_assignment_type_label($sp_task->type);
                $sp_is_paper   = 'paper' === $sp_task->type;
                $sp_has_file   = $sp_is_paper && ! empty($sp_task->attachment_path);
                $sp_time_limit = ! $sp_is_paper && ! empty($sp_task->time_limit_minutes) ? (int) $sp_task->time_limit_minutes : 0;
            ?>

    <article class="sp-card sp-task-list-card" id="task-<?php echo esc_attr((int) $sp_task->id); ?>">
        <div class="sp-material-head">
            <div class="sp-task-list-main">
                <div class="sp-task-list-badges" aria-label="Task details">
                    <span
                        class="sp-portal-badge <?php echo $sp_is_paper ? 'sp-portal-badge--info' : 'sp-portal-badge--neutral'; ?>">
                        <span aria-hidden="true"><?php echo $sp_is_paper ? '▤' : '✓'; ?></span>
                        <?php echo esc_html($sp_type_label); ?>
                    </span>

                    <?php if (! empty($sp_task->due_date)): ?>
                    <span class="sp-portal-badge sp-portal-badge--muted">
                        <span aria-hidden="true">◷</span>
                        <?php echo esc_html($sp_date_label . ': ' . sp_portal_time(get_option('date_format'), strtotime($sp_task->due_date))); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($sp_is_paper): ?>
                    <span
                        class="sp-portal-badge <?php echo $sp_has_file ? 'sp-portal-badge--success' : 'sp-portal-badge--warning'; ?>">
                        <span aria-hidden="true"><?php echo $sp_has_file ? '✓' : '!'; ?></span>
                        <?php echo $sp_has_file ? 'File attached' : 'File pending'; ?>
                    </span>
                    <?php elseif ($sp_time_limit): ?>
                    <span class="sp-portal-badge sp-portal-badge--info">
                        <span aria-hidden="true">◷</span>
                        <?php echo esc_html($sp_time_limit . ' min time limit'); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <h2 class="sp-card-title">
                    <a href="<?php echo esc_url($sp_url); ?>"><?php echo esc_html($sp_task->title); ?></a>
                </h2>
                <?php if (! empty($sp_task->class_title)): ?>
                <p class="sp-muted sp-task-class-name"><?php echo esc_html($sp_task->class_title); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="sp-card-actions sp-task-list-actions">
            <div class="sp-task-list-status" aria-label="Task status">
                <?php echo sp_assignment_state_badge($sp_state); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted badge markup returned by the portal helper. ?>
            </div>
            <a class="sp-btn sp-btn-primary sp-task-list-open" href="<?php echo esc_url($sp_url); ?>">
                Open task <span aria-hidden="true">↗</span>
            </a>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>