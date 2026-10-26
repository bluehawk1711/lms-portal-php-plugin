<?php
    /**
 * A single class: roster, attendance, results and materials.
 *
 * @package StudentPortal
 */

    if (! defined('ABSPATH')) {
    exit;
    }

    if (! function_exists('sp_class_icon')) {
    /**
     * Inline line icon for the class screen. Decorative only - the label is text.
     *
     * @param string $name Icon key.
     * @return string SVG markup.
     */
    function sp_class_icon($name)
    {
        $paths = [
            'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />',
            'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" />',
            'pencil'    => '<path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" />',
            'roster'    => '<path d="M8 6h13M8 12h13M8 18h13" /><path d="M3 6h.01M3 12h.01M3 18h.01" />',
            'check'     => '<path d="M20 6 9 17l-5-5" />',
            'cross'     => '<path d="M18 6 6 18M6 6l12 12" />',
            'clock'     => '<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />',
            'shield'    => '<path d="M12 3l7 4v5c0 4-3 7-7 9-4-2-7-5-7-9V7z" />',
            'refresh'   => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8" /><path d="M21 3v5h-5" /><path d="M21 12a9 9 0 0 1-15 6.7L3 16" /><path d="M3 21v-5h5" />',
            'lock'      => '<rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" />',
            'arrow'     => '<path d="M5 12h14" /><path d="m13 6 6 6-6 6" />',
            'gear'      => '<circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />',
            'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" /><rect x="8" y="2" width="8" height="4" rx="1" /><path d="M9 12h6M9 16h4" />',
            'eye'       => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" />',
            'dots'      => '<circle cx="12" cy="5" r="1.5" fill="currentColor" stroke="none" /><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" /><circle cx="12" cy="19" r="1.5" fill="currentColor" stroke="none" />',
            'filter'    => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" />',
            'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><path d="M7 10l5 5 5-5" /><path d="M12 15V3" />',
            'plus'      => '<path d="M12 5v14M5 12h14" />',
            'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2" /><path d="M22 6l-10 7L2 6" />',
            'phone'     => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />',
        ];

        if (! isset($paths[$name])) {
            return '';
        }

        return '<svg class="sp-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }
    }

    $sp_class_id = (int) get_query_var('sp_teacher_id');
    $sp_teacher  = $teacher ? $teacher : sp_get_teacher_by_user_id(get_current_user_id());
    $sp_class    = sp_get_class($sp_class_id);
    $sp_base_url = sp_teacher_url('classes/' . $sp_class_id);

    if (! $sp_class) {
    ?>
<div class="sp-notice sp-notice-error">That class no longer exists.</div>
<?php
    return;
    }

    $sp_access = sp_require_assigned_teacher($sp_class_id);

    if (is_wp_error($sp_access)) {
    ?>
<div class="sp-notice sp-notice-error"><?php echo esc_html($sp_access->get_error_message()); ?></div>
<?php
    return;
    }

    $sp_tabs = [
    'roster'       => 'Students',
    'attendance'   => 'Attendance',
    'results'      => 'Results',
    'certificates' => 'Certificates',
    'curriculum'   => 'Curriculum',
    'materials'    => 'Materials',
    ];

    $sp_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'roster'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.

    if (! isset($sp_tabs[$sp_tab])) {
    $sp_tab = 'roster';
    }
?>
<?php
    $sp_student_count = sp_count_class_students($sp_class_id);
    $sp_head_timings  = sp_get_class_timings($sp_class_id);
    $sp_head_timing   = $sp_head_timings ? $sp_head_timings[0] : null;

    $sp_status_map = [
    'active'    => ['Active batch', 'is-brand'],
    'pending'   => ['Awaiting approval', 'is-warn'],
    'completed' => ['Completed', 'is-good'],
    'archived'  => ['Archived', 'is-muted'],
    ];

    $sp_status_key  = isset($sp_class->status) ? (string) $sp_class->status : 'active';
    $sp_status_chip = isset($sp_status_map[$sp_status_key]) ? $sp_status_map[$sp_status_key] : $sp_status_map['active'];

    $sp_start_label = $sp_class->start_date
    ? date_i18n(get_option('date_format'), strtotime($sp_class->start_date))
    : '';

                                                                                         // Keep the teacher on the tab they saved from, with the edit panel reopened.
    $sp_edit_open = isset($_GET['edit']) && '1' === sanitize_key(wp_unslash($_GET['edit'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only panel state.

    $sp_edit_timing  = $sp_head_timing;
    $sp_edit_courses = [];

    foreach (function_exists('sp_get_courses') ? sp_get_courses() : [] as $sp_edit_course) {
    if ('archived' !== (string) $sp_edit_course->status || (int) $sp_edit_course->id === (int) $sp_class->course_id) {
        $sp_edit_courses[] = $sp_edit_course;
    }
    }
?>
<div class="sp-class-head">
    <div class="sp-class-head-bar">
        <div class="sp-class-head-chips">
            <span
                class="sp-chip sp-chip-status <?php echo esc_attr($sp_status_chip[1]); ?>"><?php echo esc_html($sp_status_chip[0]); ?></span>
            <?php if ('active' === $sp_status_key): ?>
            <span class="sp-class-head-progress">
                <span class="sp-class-head-progress-dot" aria-hidden="true"></span>
                In Progress
            </span>
            <?php endif; ?>
        </div>

        <div class="sp-class-head-actions">
            <button type="button" class="sp-btn sp-btn-ghost" data-sp-edit-open aria-haspopup="dialog">
                <?php echo sp_class_icon('gear'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                <span>Edit Class</span>
            </button>

            <dialog class="sp-class-edit-modal" id="sp-edit-class" aria-label="Edit class">
                <div class="sp-class-edit-head">
                    <h2 class="sp-class-edit-title">Edit class</h2>
                    <button type="button" class="sp-class-edit-close" data-sp-edit-close
                        aria-label="Close">&times;</button>
                </div>

                <form class="sp-class-edit-form" method="post" action="<?php echo esc_url($sp_base_url); ?>">
                    <div class="sp-class-edit-body">
                        <?php wp_nonce_field('sp_teacher_action_update_class', 'sp_teacher_nonce'); ?>
                        <input type="hidden" name="sp_action" value="update_class" />
                        <input type="hidden" name="sp_redirect"
                            value="<?php echo esc_url(add_query_arg(['tab' => $sp_tab, 'edit' => '1'], $sp_base_url)); ?>" />
                        <input type="hidden" name="class_id" value="<?php echo esc_attr((int) $sp_class_id); ?>" />

                        <div class="sp-field">
                            <label for="sp_edit_title">Class title <span class="sp-req">*</span></label>
                            <input type="text" id="sp_edit_title" name="title" required maxlength="190"
                                value="<?php echo esc_attr($sp_class->title); ?>" />
                        </div>

                        <div class="sp-field-row">
                            <div class="sp-field">
                                <label for="sp_edit_code">Class code</label>
                                <input type="text" id="sp_edit_code" name="code" maxlength="32"
                                    value="<?php echo esc_attr($sp_class->code ? $sp_class->code : ''); ?>" />
                            </div>
                            <div class="sp-field">
                                <label for="sp_edit_course">Course</label>
                                <select id="sp_edit_course" name="course_id">
                                    <option value="0">No course</option>
                                    <?php foreach ($sp_edit_courses as $sp_edit_course): ?>
                                    <option value="<?php echo esc_attr((int) $sp_edit_course->id); ?>"
                                        <?php selected((int) $sp_class->course_id, (int) $sp_edit_course->id); ?>>
                                        <?php echo esc_html($sp_edit_course->name . ($sp_edit_course->code ? ' (' . $sp_edit_course->code . ')' : '')); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="sp-field-row">
                            <div class="sp-field">
                                <label for="sp_edit_time_start">Daily start time</label>
                                <input type="time" id="sp_edit_time_start" name="timing_all[start]" data-sp-time
                                    value="<?php echo esc_attr($sp_edit_timing ? $sp_edit_timing['start_time'] : ''); ?>" />
                            </div>
                            <div class="sp-field">
                                <label for="sp_edit_time_end">Daily end time</label>
                                <input type="time" id="sp_edit_time_end" name="timing_all[end]" data-sp-time
                                    value="<?php echo esc_attr($sp_edit_timing ? $sp_edit_timing['end_time'] : ''); ?>" />
                            </div>
                        </div>
                        <p class="sp-muted sp-class-edit-note">Leave both blank to keep the current timetable. The class
                            meets at these hours on every day of the week.</p>

                        <div class="sp-field-row">
                            <div class="sp-field">
                                <label for="sp_edit_start">Start date</label>
                                <input type="date" id="sp_edit_start" name="start_date" data-sp-date
                                    data-sp-off-days="<?php echo esc_attr(implode(',', sp_off_days())); ?>"
                                    value="<?php echo esc_attr($sp_class->start_date ? $sp_class->start_date : ''); ?>" />
                            </div>
                            <div class="sp-field">
                                <label for="sp_edit_end">End date</label>
                                <input type="date" id="sp_edit_end" name="end_date" data-sp-date
                                    value="<?php echo esc_attr($sp_class->end_date ? $sp_class->end_date : ''); ?>" />
                            </div>
                        </div>

                        <div class="sp-field">
                            <label for="sp_edit_description">Description</label>
                            <textarea id="sp_edit_description" name="description" rows="3"
                                placeholder="What will students learn in this class?"><?php echo esc_textarea($sp_class->description ? $sp_class->description : ''); ?></textarea>
                        </div>

                    </div>

                    <div class="sp-class-edit-foot">
                        <div class="sp-card-actions">
                            <button type="button" class="sp-btn sp-btn-ghost" data-sp-edit-close>Cancel</button>
                            <button type="submit" class="sp-btn sp-btn-primary">Save changes</button>
                        </div>
                    </div>
                </form>
            </dialog>

            <a class="sp-btn sp-btn-dark"
                href="<?php echo esc_url(add_query_arg('tab', 'attendance', $sp_base_url)); ?>">
                <?php echo sp_class_icon('clipboard'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                <span>Take Attendance</span>
            </a>
        </div>
    </div>

    <h1 class="sp-class-head-title">
        <?php echo esc_html($sp_class->title); ?>
    </h1>

    <p class="sp-class-head-meta">
        <?php if ($sp_class->code): ?>
        <code class="sp-class-head-code"><?php echo esc_html($sp_class->code); ?></code>
        <?php endif; ?>
        <span class="sp-class-head-meta-item">
            <?php echo sp_class_icon('users'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
            <?php echo esc_html(number_format_i18n($sp_student_count)); ?>
            student<?php echo 1 === $sp_student_count ? '' : 's'; ?>
        </span>
        <?php if ($sp_start_label): ?>
        <span class="sp-class-head-meta-item">
            <?php echo sp_class_icon('calendar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
            <?php echo esc_html($sp_start_label); ?>
        </span>
        <?php endif; ?>
        <?php if ($sp_head_timing): ?>
        <span class="sp-class-head-meta-item">
            <?php echo esc_html(sp_format_clock_time($sp_head_timing['start_time']) . ' – ' . sp_format_clock_time($sp_head_timing['end_time'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html of concatenated strings. ?>
        </span>
        <?php endif; ?>
    </p>

    <?php if ($sp_class->description): ?>
    <p class="sp-class-head-desc"><?php echo esc_html($sp_class->description); ?></p>
    <?php endif; ?>
</div>

<?php if ('pending' === (string) $sp_class->status): ?>
<div class="sp-notice sp-notice-warn">
    This class is awaiting admin approval. You can set it up now — students, attendance and results
    become available to everyone once an administrator approves it.
</div>
<?php endif; ?>

<div id="sp-class-tab-region" data-sp-async-region>
    <nav class="sp-tabs" aria-label="Class sections">
        <?php foreach ($sp_tabs as $sp_key => $sp_label): ?>
        <a class="sp-tab<?php echo $sp_key === $sp_tab ? ' is-active' : ''; ?>"
            href="<?php echo esc_url(add_query_arg('tab', $sp_key, $sp_base_url)); ?>"
            <?php echo $sp_key === $sp_tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html($sp_label); ?>
            <?php if ('roster' === $sp_key && $sp_student_count): ?>
            <span class="sp-tab-count"><?php echo esc_html(number_format_i18n($sp_student_count)); ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php if ('roster' === $sp_tab): ?>
    <?php
        $sp_students = sp_get_enrollments_for_class($sp_class_id, ['active', 'completed'], 0);
        if (! $sp_students):
    ?>
    <div class="sp-empty">
        No students are enrolled in this class yet. Ask an administrator to enroll them from
        Student Portal &rarr; Enrollments.
    </div>
    <?php else: ?>
    <div class="sp-card sp-class-roster-card" data-sp-roster data-sp-per-page="10">
        <div class="sp-class-roster-bar">
            <div class="sp-class-roster-tools">
                <div class="sp-class-search-field">
                    <span class="sp-class-search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7" />
                            <path d="m21 21-4.35-4.35" />
                        </svg>
                    </span>
                    <label class="screen-reader-text" for="sp-roster-search">Search students</label>
                    <input class="sp-class-search-input" type="search" id="sp-roster-search"
                        placeholder="Search by name, enroll ID, email…" autocomplete="off" data-sp-roster-search
                        aria-describedby="sp-roster-search-status" />
                </div>
                <details class="sp-class-filter" data-sp-roster-filter>
                    <summary class="sp-btn sp-btn-ghost">
                        <?php echo sp_class_icon('filter'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                        <span>Filters</span>
                    </summary>
                    <div class="sp-class-filter-pop">
                        <p class="sp-class-filter-title">Status</p>
                        <label class="sp-class-filter-opt"><input type="radio" name="sp-roster-status" value=""
                                checked /> All students</label>
                        <label class="sp-class-filter-opt"><input type="radio" name="sp-roster-status" value="active" />
                            Active</label>
                        <label class="sp-class-filter-opt"><input type="radio" name="sp-roster-status"
                                value="completed" /> Completed</label>
                    </div>
                </details>
            </div>
            <div class="sp-class-roster-actions">
                <button type="button" class="sp-btn sp-btn-ghost" data-sp-roster-export>
                    <?php echo sp_class_icon('download'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                    <span>Export CSV</span>
                </button>
                <?php if (current_user_can('manage_options')): ?>
                <a class="sp-btn sp-btn-primary"
                    href="<?php echo esc_url(admin_url('admin.php?page=sp-enrollments&class=' . (int) $sp_class_id)); ?>">
                    <?php echo sp_class_icon('plus'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                    <span>Enroll Student</span>
                </a>
                <?php else: ?>
                <button type="button" class="sp-btn sp-btn-primary" data-sp-enroll-info>
                    <?php echo sp_class_icon('plus'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                    <span>Enroll Student</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="sp-table-wrap">
            <table class="sp-table sp-class-roster-table" data-sp-roster-table>
                <thead>
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Enroll ID</th>
                        <th scope="col">Email</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Attendance</th>
                        <th scope="col" class="sp-col-actions"><span class="screen-reader-text">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sp_students as $sp_student): ?>
                    <?php
                        $sp_stats    = sp_get_attendance_stats((int) $sp_student->profile_id, $sp_class_id);
                        $sp_row_data = wp_json_encode([
                            'name'    => (string) $sp_student->full_name,
                            'enroll'  => (string) $sp_student->enroll_id,
                            'email'   => (string) $sp_student->email,
                            'phone'   => (string) $sp_student->phone,
                            'pct'     => (int) $sp_stats['percentage'],
                            'present' => (int) $sp_stats['present'],
                            'total'   => (int) $sp_stats['total'],
                            'status'  => 'completed' === $sp_student->status ? 'Completed' : 'Active student',
                        ]);
                    ?>
                    <tr data-sp-roster-row data-sp-status="<?php echo esc_attr((string) $sp_student->status); ?>"
                        data-sp-student="<?php echo esc_attr($sp_row_data); ?>"
                        data-sp-search="<?php echo esc_attr(implode(' ', array_filter([(string) $sp_student->full_name, (string) $sp_student->enroll_id, (string) $sp_student->email]))); ?>">
                        <td data-label="Student">
                            <span class="sp-class-student">
                                <span class="sp-class-student-avatar"
                                    aria-hidden="true"><?php echo esc_html(function_exists('sp_initials') ? sp_initials($sp_student->full_name) : mb_strtoupper(mb_substr((string) $sp_student->full_name, 0, 1))); ?></span>
                                <span class="sp-class-student-copy">
                                    <strong><?php echo esc_html($sp_student->full_name); ?></strong>
                                    <?php if ('completed' === $sp_student->status): ?>
                                    <span class="sp-class-student-status is-done">Completed</span>
                                    <?php else: ?>
                                    <span class="sp-class-student-status"><span class="sp-class-student-dot"
                                            aria-hidden="true"></span>Active Student</span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </td>
                        <td data-label="Enroll ID">
                            <?php echo $sp_student->enroll_id ? '<span class="sp-chip sp-chip-mini sp-chip-code">' . esc_html($sp_student->enroll_id) . '</span>' : '<span class="sp-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?>
                        </td>
                        <td data-label="Email"><a class="sp-cell-link"
                                href="mailto:<?php echo esc_attr($sp_student->email); ?>">
                                <?php echo sp_class_icon('mail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                                <?php echo esc_html($sp_student->email); ?></a>
                        </td>
                        <td data-label="Phone">
                            <?php if ($sp_student->phone): ?><a class="sp-cell-link"
                                href="tel:<?php echo esc_attr($sp_student->phone); ?>">
                                <?php echo sp_class_icon('phone'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                                <?php echo esc_html($sp_student->phone); ?></a><?php else: ?><span
                                class="sp-muted">—</span><?php endif; ?>
                        </td>
                        <td data-label="Attendance">
                            <span class="sp-class-att">
                                <?php echo wp_kses_post(sp_attendance_badge($sp_stats['percentage'])); ?>
                                <span class="sp-class-meter" aria-hidden="true">
                                    <span
                                        class="sp-class-meter-fill<?php echo $sp_stats['percentage'] >= 70 ? ' is-good' : ($sp_stats['percentage'] >= 40 ? ' is-warn' : ' is-low'); ?>"
                                        style="width: <?php echo esc_attr((int) $sp_stats['percentage']); ?>%"></span>
                                </span>
                                <span
                                    class="sp-kv-note"><?php echo esc_html($sp_stats['present'] . '/' . $sp_stats['total']); ?></span>
                            </span>
                        </td>
                        <td data-label="Actions" class="sp-cell-actions">
                            <button type="button" class="sp-icon-btn" data-sp-roster-view
                                aria-label="<?php echo esc_attr('View ' . $sp_student->full_name); ?>">
                                <?php echo sp_class_icon('eye'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                            </button>
                            <!-- TODO: action menu opens inside table requires fix -->
                            <!-- <details class="sp-row-menu">
                                <summary class="sp-icon-btn"
                                    aria-label="<?php echo esc_attr('More actions for ' . $sp_student->full_name); ?>">
                                    <?php echo sp_class_icon('dots'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                                </summary>
                                <div class="sp-row-menu-pop">
                                    <a href="mailto:<?php echo esc_attr($sp_student->email); ?>">
                                        <?php echo sp_class_icon('mail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                                        <span>Email student</span>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg('tab', 'attendance', $sp_base_url)); ?>">
                                        <?php echo sp_class_icon('clipboard'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                                        <span>View attendance</span>
                                    </a>
                                </div>
                            </details> -->
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sp-class-roster-foot">
            <p class="sp-class-roster-count" id="sp-roster-search-status" aria-live="polite" aria-atomic="true"
                data-sp-roster-count>
                Showing <strong>1</strong> to
                <strong><?php echo esc_html(number_format_i18n(count($sp_students))); ?></strong>
                of <strong><?php echo esc_html(number_format_i18n(count($sp_students))); ?></strong>
                student<?php echo 1 === count($sp_students) ? '' : 's'; ?>
            </p>
            <nav class="sp-pager" data-sp-roster-pager aria-label="Roster pages"></nav>
        </div>
    </div>

    <dialog class="sp-student-dialog" data-sp-student-dialog aria-label="Student details">
        <div class="sp-student-dialog-head">
            <span class="sp-student-avatar" data-sd-avatar aria-hidden="true"></span>
            <div>
                <p class="sp-student-dialog-name" data-sd-name></p>
                <p class="sp-student-dialog-status" data-sd-status></p>
            </div>
            <form method="dialog"><button class="sp-icon-btn" aria-label="Close">&#10005;</button></form>
        </div>
        <dl class="sp-student-dialog-grid">
            <div>
                <dt>Enroll ID</dt>
                <dd data-sd-enroll></dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd data-sd-email></dd>
            </div>
            <div>
                <dt>Phone</dt>
                <dd data-sd-phone></dd>
            </div>
            <div>
                <dt>Attendance</dt>
                <dd data-sd-att></dd>
            </div>
        </dl>
        <div class="sp-student-dialog-foot">
            <a class="sp-btn sp-btn-ghost" data-sd-mail href="#">Email student</a>
            <a class="sp-btn sp-btn-primary" data-sd-att-link href="#">View attendance</a>
        </div>
    </dialog>

    <dialog class="sp-enroll-dialog" data-sp-enroll-dialog aria-label="Enroll students">
        <form method="dialog" class="sp-enroll-dialog-inner">
            <h2>Enroll students</h2>
            <p>Students are enrolled by an administrator. Ask your administrator to add students to
                this class from Student Portal &rarr; Enrollments.</p>
            <button class="sp-btn sp-btn-primary" value="ok">Got it</button>
        </form>
    </dialog>
    <?php endif; ?>

    <?php elseif ('attendance' === $sp_tab):
            $sp_date       = isset($_GET['date']) ? sp_sanitize_date(wp_unslash($_GET['date'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only date selection.
            $sp_date       = $sp_date ? $sp_date : current_time('Y-m-d');
            $sp_session    = sp_get_session_by_date($sp_class_id, $sp_date);
            $sp_records    = $sp_session ? sp_get_session_records($sp_session->id) : [];
            $sp_roster     = sp_get_enrollments_for_class($sp_class_id, ['active'], 0);
            $sp_editable   = $sp_session ? sp_attendance_session_is_editable($sp_session) : true;
            $sp_off_day    = sp_is_off_day($sp_date);
            $sp_no_teacher = sp_class_teachers_unavailable($sp_date, $sp_class_id);
            $sp_cancelled  = ! $sp_no_teacher && sp_date_cancels_class($sp_date, $sp_class_id);
            $sp_can_reopen = $sp_session ? sp_can_reopen_session($sp_session) : false;

            // A cancelled date has no class, so nothing may be marked and no register created.
            if ($sp_off_day || $sp_cancelled || $sp_no_teacher) {
                $sp_editable = false;
            }

            $sp_today     = current_time('Y-m-d');
            $sp_yesterday = date('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS);
            $sp_is_today  = $sp_date === $sp_today;

            // The hours this class runs on the picked weekday, for the bar under
            // the date picker.
            $sp_day_timing = sp_class_timing_for_weekday($sp_class_id, (int) date('w', strtotime($sp_date)));
            $sp_time_label = $sp_day_timing
                ? sp_format_clock_time($sp_day_timing['start_time']) . ' – ' . sp_format_clock_time($sp_day_timing['end_time'])
                : '';

            // One honest label for whatever state the register is in.
            if ($sp_off_day) {
                $sp_reg_state = ['Off day', 'is-muted'];
            } elseif ($sp_cancelled) {
            $sp_reg_state = ['Cancelled', 'is-warn'];
        } elseif ($sp_no_teacher) {
            $sp_reg_state = ['No teacher available', 'is-warn'];
        } elseif (! $sp_session) {
            $sp_reg_state = ['Not opened yet', 'is-muted'];
        } elseif ('submitted' === (string) $sp_session->status) {
            $sp_reg_state = $sp_editable ? ['Submitted / editable', 'is-brand'] : ['Submitted / locked', 'is-good'];
        } else {
            $sp_reg_state = ['In progress / open', 'is-warn'];
        }
        // Recent sessions: six per page, or the whole log on request.
        $sp_per_page     = 6;
        $sp_log_all      = isset($_GET['log']) && 'all' === sanitize_key(wp_unslash($_GET['log'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
        $sp_total        = sp_count_sessions_for_class($sp_class_id);
        $sp_pages        = $sp_log_all ? 1 : (int) ceil($sp_total / $sp_per_page);
        $sp_paged        = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page selection.
        $sp_paged        = $sp_pages ? min($sp_paged, $sp_pages) : 1;
        $sp_offset       = $sp_log_all ? 0 : ($sp_paged - 1) * $sp_per_page;
        $sp_sessions     = sp_get_recent_sessions($sp_class_id, $sp_log_all ? max(1, $sp_total) : $sp_per_page, $sp_offset);
        $sp_roster_count = count($sp_roster);
    ?>

    <div class="sp-reg-bar">
        <form class="sp-reg-date" method="get" data-sp-async action="<?php echo esc_url($sp_base_url); ?>">
            <input type="hidden" name="tab" value="attendance" />
            <label class="screen-reader-text" for="sp_date">Session date</label>
            <input type="date" id="sp_date" name="date" value="<?php echo esc_attr($sp_date); ?>" data-sp-date
                data-sp-off-days="<?php echo esc_attr(implode(',', sp_off_days())); ?>" />

            <button type="submit" class="sp-btn sp-btn-ghost">
                <?php echo sp_class_icon('calendar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                <span>Open register</span>
            </button>
        </form>

        <p class="sp-reg-state">
            <span class="sp-chip sp-chip-state <?php echo esc_attr($sp_reg_state[1]); ?>">
                <strong>Status:</strong> <?php echo esc_html($sp_reg_state[0]); ?>
            </span>
        </p>
    </div>

    <p class="sp-reg-meta">
        <span><strong>Session timing:</strong>
            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($sp_date))); ?></span>
        <?php if ($sp_time_label): ?>
        <span class="sp-reg-meta-time">
            <?php echo sp_class_icon('clock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
            Regular class
            <?php echo esc_html($sp_time_label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html of concatenated strings. ?>
        </span>
        <?php else: ?>
        <span class="sp-reg-meta-time">
            <?php echo sp_class_icon('clock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
            No class hours set for this weekday
        </span>
        <?php endif; ?>
    </p>

    <?php if ($sp_off_day): ?>
    <div class="sp-notice sp-notice-warn">
        <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($sp_date))); ?> is an off
            day.</strong>
        <?php echo esc_html(sp_off_days_label()); ?> are marked as off days, so no class runs on this date
        and no register can be opened or marked. Change the off days in Settings if this is wrong.
    </div>
    <?php elseif ($sp_cancelled): ?>
    <div class="sp-notice sp-notice-warn">
        <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($sp_date))); ?> is
            cancelled.</strong>
        The portal calendar lists it as a holiday or test day, so no class runs and
        no register can be opened or marked on this date.
    </div>
    <?php elseif ($sp_no_teacher): ?>
    <div class="sp-notice sp-notice-warn">
        <strong><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($sp_date))); ?> has no
            teacher available.</strong>
        Every teacher assigned to this class is marked absent or on leave for this date,
        so no register can be opened or marked.
    </div>
    <?php elseif (! $sp_roster): ?>
    <div class="sp-empty">No students are enrolled, so there is nothing to mark.</div>
    <?php else: ?>
    <?php if ($sp_session && ! $sp_editable): ?>
    <div class="sp-notice sp-notice-warn">
        This register was submitted
        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($sp_session->submitted_at))); ?>
        and is locked.
        <?php if (sp_can_reopen_session($sp_session)): ?>
        Reopen it below to make a correction, then submit it again.
        <?php else: ?>
        Only an administrator can reopen it.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url($sp_base_url); ?>" data-sp-register data-sp-async
        data-sp-date-label="<?php echo esc_attr(date_i18n(get_option('date_format'), strtotime($sp_date))); ?>"
        data-sp-lock-days="<?php echo esc_attr((int) sp_setting('attendance_edit_days', 7)); ?>">
        <?php
            sp_teacher_nonce_field('save_session');
            if ($sp_session) {
                echo '<input type="hidden" name="session_id" value="' . esc_attr((int) $sp_session->id) . '" />';
            }
        ?>
        <input type="hidden" name="class_id" value="<?php echo esc_attr((int) $sp_class_id); ?>" />
        <input type="hidden" name="session_date" value="<?php echo esc_attr($sp_date); ?>" />

        <div class="sp-card sp-reg-card">
            <div class="sp-reg-head">
                <div class="sp-reg-head-copy">
                    <h2 class="sp-reg-title">
                        <?php echo sp_class_icon('calendar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                        <span>Attendance &mdash;
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($sp_date))); ?></span>
                    </h2>
                    <p class="sp-reg-sub">
                        Mark each student's presence for the
                        <?php echo esc_html($sp_date); ?>
                        session<?php echo $sp_time_label ? ' (' . esc_html($sp_time_label) . ')' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?>.
                    </p>
                </div>
                <span class="sp-chip sp-chip-sync">
                    <?php echo sp_class_icon('check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                    <span>Live count active</span>
                </span>
            </div>

            <div class="sp-reg-topic">
                <div class="sp-field">
                    <label for="sp_topic">Session topic / lesson covered</label>
                    <input type="text" id="sp_topic" name="topic"
                        placeholder="e.g. Introduction to the pen tool &amp; bezier curves"
                        value="<?php echo esc_attr($sp_session && $sp_session->topic ? $sp_session->topic : ''); ?>"
                        <?php disabled(! $sp_editable); ?> />
                </div>
                <p class="sp-reg-topic-note">Saved against this session and shown in the class record.</p>
            </div>

            <div class="sp-reg-cols" aria-hidden="true">
                <span>Scheduled student</span>
                <span>Mark status</span>
            </div>

            <div class="sp-attendance-list">
                <?php foreach ($sp_roster as $sp_student): ?>
                <?php
                    $sp_profile_id = (int) $sp_student->profile_id;
                    $sp_current    = isset($sp_records[$sp_profile_id]) ? $sp_records[$sp_profile_id] : 'present';
                    $sp_stats      = sp_get_attendance_stats($sp_profile_id, $sp_class_id);
                    $sp_streak     = sp_attendance_consecutive_present($sp_profile_id, $sp_class_id);
                    $sp_pct        = (float) $sp_stats['percentage'];
                    $sp_meter      = $sp_pct >= 75 ? 'is-good' : ($sp_pct >= 50 ? 'is-warn' : 'is-bad');
                    $sp_initial    = $sp_student->full_name
                        ? mb_strtoupper(mb_substr($sp_student->full_name, 0, 1))
                        : '?';
                ?>
                <div class="sp-attendance-row">
                    <div class="sp-student">
                        <span class="sp-student-avatar" aria-hidden="true"><?php echo esc_html($sp_initial); ?></span>
                        <span class="sp-student-copy">
                            <span class="sp-student-name">
                                <strong><?php echo esc_html($sp_student->full_name); ?></strong>
                                <?php if ($sp_student->enroll_id): ?>
                                <span class="sp-muted"><?php echo esc_html($sp_student->enroll_id); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="sp-student-stats">
                                <span class="sp-student-overall">Overall
                                    <strong><?php echo esc_html(sp_format_number($sp_pct) . '%'); ?></strong>
                                </span>
                                <span class="sp-student-meter <?php echo esc_attr($sp_meter); ?>" aria-hidden="true">
                                    <span style="width: <?php echo esc_attr(min(100, max(0, $sp_pct))); ?>%"></span>
                                </span>
                                <span class="sp-streak">Consecutive presents:
                                    <strong><?php echo esc_html(number_format_i18n($sp_streak)); ?></strong>
                                </span>
                            </span>
                        </span>
                    </div>

                    <span class="sp-attendance-options">
                        <?php foreach (sp_attendance_statuses() as $sp_status): ?>
                        <?php
                            $sp_icon = 'present' === $sp_status
                                ? 'check'
                                : ('absent' === $sp_status ? 'cross' : ('late' === $sp_status ? 'clock' : 'shield'));
                        ?>
                        <label class="sp-attendance-option sp-attendance-option-<?php echo esc_attr($sp_status); ?>">
                            <input type="radio" name="marks[<?php echo esc_attr($sp_profile_id); ?>]"
                                value="<?php echo esc_attr($sp_status); ?>" <?php checked($sp_current, $sp_status); ?>
                                <?php disabled(! $sp_editable); ?> />
                            <?php echo sp_class_icon($sp_icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                            <span><?php echo esc_html(ucfirst($sp_status)); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($sp_editable): ?>
            <div class="sp-reg-foot">
                <span class="sp-attend-count" data-sp-register-count aria-live="polite"></span>
                <span class="sp-reg-foot-actions">
                    <button type="submit" class="sp-btn sp-btn-ghost">Save register</button>
                    <button type="submit" name="submit_after" value="1" class="sp-btn sp-btn-primary"
                        data-sp-register-submit="1">
                        <?php echo sp_class_icon('check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                        <span>Save and submit</span>
                    </button>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($sp_editable): ?>
    <div class="sp-reg-actions">
        <div class="sp-reg-actions-group">
            <?php if ($sp_session): ?>
            <form method="post" action="<?php echo esc_url($sp_base_url); ?>">
                <?php sp_teacher_nonce_field('mark_all'); ?>
                <input type="hidden" name="session_id" value="<?php echo esc_attr((int) $sp_session->id); ?>" />
                <button type="submit" class="sp-btn sp-btn-ghost">
                    <?php echo sp_class_icon('check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                    <span>Mark all present</span>
                </button>
            </form>
            <?php endif; ?>

            <button type="button" class="sp-btn sp-btn-ghost" data-sp-register-reset>
                <?php echo sp_class_icon('refresh'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                <span>Reset selection</span>
            </button>
        </div>

        <?php if ($sp_session): ?>
        <form method="post" action="<?php echo esc_url($sp_base_url); ?>">
            <?php sp_teacher_nonce_field('submit_session'); ?>
            <input type="hidden" name="session_id" value="<?php echo esc_attr((int) $sp_session->id); ?>" />
            <button type="submit" class="sp-btn sp-btn-dark" data-sp-register-submit="1"
                data-sp-date-label="<?php echo esc_attr(date_i18n(get_option('date_format'), strtotime($sp_date))); ?>"
                data-sp-lock-days="<?php echo esc_attr((int) sp_setting('attendance_edit_days', 7)); ?>">
                <?php echo sp_class_icon('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                <span>Submit and lock register</span>
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($sp_can_reopen): ?>
    <form class="sp-card sp-reopen" method="post" action="<?php echo esc_url($sp_base_url); ?>">
        <?php sp_teacher_nonce_field('reopen_session'); ?>
        <input type="hidden" name="session_id" value="<?php echo esc_attr((int) $sp_session->id); ?>" />
        <div class="sp-field">
            <label for="sp_reason">Reason for reopening</label>
            <input type="text" id="sp_reason" name="reason" />
        </div>
        <button type="submit" class="sp-btn sp-btn-ghost">
            <?php echo sp_class_icon('refresh'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
            <span>Reopen register</span>
        </button>
    </form>
    <?php endif; ?>

    <div class="sp-card sp-sessions-card">
        <div class="sp-sessions-head">
            <div>
                <h2 class="sp-card-title">Recent sessions</h2>
                <p class="sp-muted">Every register this class has opened, newest first.</p>
            </div>
            <a class="sp-sessions-log"
                href="<?php echo esc_url(add_query_arg(['tab' => 'attendance', 'log' => 'all'], $sp_base_url)); ?>">
                <span>View full attendance log</span>
                <?php echo sp_class_icon('arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
            </a>
        </div>

        <div class="sp-table-wrap">
            <table class="sp-table sp-sessions-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Topic</th>
                        <th scope="col">Marked</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (! $sp_sessions): ?>
                    <tr>
                        <td colspan="5"><span class="sp-muted">No sessions recorded yet.</span></td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($sp_sessions as $sp_row): ?>
                    <?php
                        $sp_row_url     = add_query_arg(['tab' => 'attendance', 'date' => $sp_row->session_date], $sp_base_url);
                        $sp_row_current = $sp_date === (string) $sp_row->session_date;
                        $sp_row_open    = 'submitted' !== (string) $sp_row->status;
                    ?>
                    <tr class="<?php echo $sp_row_current ? 'is-current' : ''; ?>">
                        <td data-label="Date">
                            <a class="sp-session-date" href="<?php echo esc_url($sp_row_url); ?>"
                                <?php echo $sp_row_current ? 'aria-current="true"' : ''; ?>>
                                <?php echo sp_class_icon('calendar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG helper. ?>
                                <time
                                    datetime="<?php echo esc_attr($sp_row->session_date); ?>"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($sp_row->session_date))); ?></time>
                            </a>
                        </td>
                        <td data-label="Topic">
                            <?php echo $sp_row->topic ? esc_html($sp_row->topic) : '<span class="sp-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?>
                        </td>
                        <td data-label="Marked">
                            <span
                                class="sp-session-marked"><?php echo esc_html(number_format_i18n((int) $sp_row->marked_count) . '/' . number_format_i18n($sp_roster_count)); ?></span>
                        </td>
                        <td data-label="Status">
                            <?php echo 'submitted' === (string) $sp_row->status
                                    ? '<span class="sp-chip sp-chip-mini is-good">Submitted</span>'
                                : '<span class="sp-chip sp-chip-mini is-warn">Open</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed escaped strings. ?>
                        </td>
                        <td data-label="Action">
                            <a class="sp-session-action" href="<?php echo esc_url($sp_row_url); ?>">
                                <?php echo $sp_row_open ? 'Edit' : 'View'; ?>
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sp-sessions-foot">
            <span class="sp-muted">
                <?php if ($sp_log_all): ?>
                Showing all
                <?php echo esc_html(number_format_i18n($sp_total)); ?> recorded
                session<?php echo 1 === $sp_total ? '' : 's'; ?>.
                <?php elseif ($sp_total): ?>
                Showing
                <?php echo esc_html(number_format_i18n($sp_offset + 1) . '–' . number_format_i18n(min($sp_offset + $sp_per_page, $sp_total))); ?>
                of <?php echo esc_html(number_format_i18n($sp_total)); ?>
                session<?php echo 1 === $sp_total ? '' : 's'; ?>.
                <?php else: ?>
                No sessions recorded yet.
                <?php endif; ?>
            </span>

            <?php if ($sp_log_all): ?>
            <a class="sp-btn sp-btn-ghost"
                href="<?php echo esc_url(add_query_arg(['tab' => 'attendance', 'date' => $sp_date], $sp_base_url)); ?>">Back
                to recent sessions</a>
            <?php elseif ($sp_pages > 1): ?>
            <nav class="sp-pager" aria-label="Recent sessions pages">
                <?php if ($sp_paged > 1): ?>
                <a class="sp-pager-link"
                    href="<?php echo esc_url(add_query_arg(['tab' => 'attendance', 'date' => $sp_date, 'paged' => $sp_paged - 1], $sp_base_url)); ?>">Previous</a>
                <?php endif; ?>

                <?php for ($sp_i = 1; $sp_i <= $sp_pages; $sp_i++): ?>
                <?php if ($sp_i === $sp_paged): ?>
                <span class="sp-pager-link is-current" aria-current="page"><?php echo esc_html($sp_i); ?></span>
                <?php else: ?>
                <a class="sp-pager-link"
                    href="<?php echo esc_url(add_query_arg(['tab' => 'attendance', 'date' => $sp_date, 'paged' => $sp_i], $sp_base_url)); ?>"><?php echo esc_html($sp_i); ?></a>
                <?php endif; ?>
                <?php endfor; ?>

                <?php if ($sp_paged < $sp_pages): ?>
                <a class="sp-pager-link"
                    href="<?php echo esc_url(add_query_arg(['tab' => 'attendance', 'date' => $sp_date, 'paged' => $sp_paged + 1], $sp_base_url)); ?>">Next</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ('results' === $sp_tab):
            $sp_results = sp_get_class_results($sp_class_id);
    ?>

    <div class="sp-card sp-results-instruction">
        <div class="sp-results-instruction-body">
            <span class="sp-results-instruction-icon" aria-hidden="true">i</span>
            <p class="sp-muted">
                Enter <strong>Theory</strong> and <strong>Practical marks</strong> — both maximum
                fields are required, otherwise the percentage cannot be calculated. Ticking
                <strong>Publish</strong> makes the result visible to the student and emails them.
            </p>
        </div>
        <span class="sp-chip sp-chip-mini is-brand sp-results-scale">
            <span class="sp-results-scale-dot" aria-hidden="true"></span>
            Grading Scale: <strong>Pass ≥ 40%</strong> · <strong>Distinction ≥ 75%</strong> · <strong>Grade A+ ≥
                85%</strong>
        </span>
    </div>

    <?php if (! $sp_results): ?>
    <div class="sp-empty">No students are enrolled in this class.</div>
    <?php else: ?>
    <form method="post" action="<?php echo esc_url($sp_base_url); ?>" data-sp-async>
        <?php sp_teacher_nonce_field('save_results'); ?>
        <input type="hidden" name="class_id" value="<?php echo esc_attr((int) $sp_class_id); ?>" />

        <div class="sp-table-wrap">
            <table class="sp-table sp-table-form">
                <thead>
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Theory</th>
                        <th scope="col">Theory max</th>
                        <th scope="col">Practical</th>
                        <th scope="col">Practical max</th>
                        <th scope="col">Outcome</th>
                        <th scope="col">Publish</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sp_results as $sp_row): ?>
                    <?php $sp_id = (int) $sp_row->enrollment_id; ?>
                    <tr>
                        <td data-label="Student">
                            <strong><?php echo esc_html($sp_row->full_name); ?></strong>
                            <span class="sp-muted"><?php echo esc_html($sp_row->enroll_id); ?></span>
                        </td>
                        <td data-label="Theory">
                            <input type="number" step="0.01" min="0" inputmode="decimal"
                                name="sp_result[<?php echo esc_attr($sp_id); ?>][theory_obtained]"
                                value="<?php echo esc_attr($sp_row->result_id ? sp_format_number($sp_row->theory_obtained) : ''); ?>" />
                        </td>
                        <td data-label="Theory max">
                            <input type="number" step="0.01" min="0.01" inputmode="decimal" required
                                name="sp_result[<?php echo esc_attr($sp_id); ?>][theory_max]"
                                aria-label="Theory maximum for <?php echo esc_attr($sp_row->full_name); ?>"
                                value="<?php echo esc_attr($sp_row->result_id ? sp_format_number($sp_row->theory_max) : ''); ?>" />
                        </td>
                        <td data-label="Practical">
                            <input type="number" step="0.01" min="0" inputmode="decimal"
                                name="sp_result[<?php echo esc_attr($sp_id); ?>][practical_obtained]"
                                value="<?php echo esc_attr($sp_row->result_id ? sp_format_number($sp_row->practical_obtained) : ''); ?>" />
                        </td>
                        <td data-label="Practical max">
                            <input type="number" step="0.01" min="0.01" inputmode="decimal" required
                                name="sp_result[<?php echo esc_attr($sp_id); ?>][practical_max]"
                                aria-label="Practical maximum for <?php echo esc_attr($sp_row->full_name); ?>"
                                value="<?php echo esc_attr($sp_row->result_id ? sp_format_number($sp_row->practical_max) : ''); ?>" />
                        </td>
                        <td data-label="Outcome">
                            <?php if ($sp_row->result_id): ?>
                            <?php echo wp_kses_post(sp_result_badge($sp_row->result)); ?>
                            <span
                                class="sp-kv-note"><?php echo esc_html(sp_format_number($sp_row->percentage) . '%'); ?></span>
                            <?php if ($sp_row->grade): ?>
                            <span class="sp-kv-note">Grade <?php echo esc_html($sp_row->grade); ?></span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="sp-muted">Not entered</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Publish">
                            <input type="checkbox" name="sp_result[<?php echo esc_attr($sp_id); ?>][finalised]"
                                value="1" <?php checked($sp_row->result_id && (int) $sp_row->finalised === 1); ?> />
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sp-results-foot">
            <span class="sp-attend-count" data-sp-results-count>
                Showing <strong><?php echo esc_html(number_format_i18n(count($sp_results))); ?></strong>
                student<?php echo 1 === count($sp_results) ? '' : 's'; ?>
                ·
                <?php
                    $sp_graded    = 0;
                    $sp_published = 0;
                    foreach ($sp_results as $sp_row) {
                        if ($sp_row->result_id) {$sp_graded++;}
                        if ($sp_row->result_id && (int) $sp_row->finalised === 1) {$sp_published++;}
                    }
                ?>
                <strong><?php echo esc_html(number_format_i18n($sp_graded)); ?></strong> graded
                · <strong><?php echo esc_html(number_format_i18n($sp_published)); ?></strong> published
            </span>
            <div class="sp-reg-foot-actions">
                <!-- <button type="submit" name="save_draft" value="1" class="sp-btn sp-btn-ghost">Save draft</button> -->
                <button type="submit" name="publish_all" value="1" class="sp-btn sp-btn-dark">
                    Publish all
                </button>
                <button type="submit" class="sp-btn sp-btn-primary">
                    <span>Save results</span>
                </button>
            </div>
        </div>
    </form>

    <div style="background-color:white;" class=" sp-card sp-next-step">
        <div class="sp-next-step-body">
            <span class="sp-next-step-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </span>
            <div>
                <h4 class="sp-next-step-title">Results entered? Generate official certificates</h4>
                <p class="sp-muted">Open the Certificates tab to issue a printable, verified
                    certificate for each student who has passed.</p>
            </div>
        </div>
        <a class="sp-btn sp-btn-ghost sp-next-step-link"
            href="<?php echo esc_url(add_query_arg('tab', 'certificates', $sp_base_url)); ?>">
            <span>Go to Certificates</span>
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </a>
    </div>
    <?php endif; ?>
    <?php elseif ('certificates' === $sp_tab):
            $sp_results   = sp_get_class_results($sp_class_id);
            $sp_cert_form = isset($_GET['cert_form']) ? (int) $_GET['cert_form'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
    ?>
    <div class="sp-card">
        <h2 class="sp-card-title">Certificates</h2>
        <p class="sp-muted">
            Issue a certificate when a student's result is a pass and their class attendance is
            at least <?php echo esc_html(sp_format_number(sp_certificate_min_attendance())); ?>%.
            The form auto-fills the grade, attendance and enrolment ID from this class.
        </p>

        <?php if (! $sp_results): ?>
        <div class="sp-empty">No students are enrolled in this class.</div>
        <?php else: ?>
        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Attendance</th>
                        <th scope="col">Certificate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        foreach ($sp_results as $sp_row):
                    ?>
                    <?php
                        $sp_id    = (int) $sp_row->enrollment_id;
                        $sp_stats = $sp_row->profile_id
                            ? sp_get_attendance_stats((int) $sp_row->profile_id, (int) $sp_class_id)
                            : null;
                        $sp_cstate = $sp_id
                            ? sp_certificate_state_for_enrollment($sp_id)
                            : [
                            'can_generate'   => false,
                            'reason'         => 'That enrollment no longer exists.',
                            'generated'      => false,
                            'certificate_id' => 0,
                            'serial'         => '',
                            'issued_date'    => '',
                            'attendance'     => 0.0,
                        ];

                        if ($sp_id && $sp_cert_form === $sp_id) {
                            continue;
                        }
                    ?>
                    <tr>
                        <td data-label="Student">
                            <strong><?php echo esc_html($sp_row->full_name); ?></strong>
                            <span class="sp-muted"><?php echo esc_html($sp_row->enroll_id); ?></span>
                        </td>
                        <td data-label="Attendance">
                            <?php
                                echo $sp_stats
                                    ? esc_html(sp_format_number($sp_stats['percentage']) . '%')
                                    : '<span class="sp-muted">&mdash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary.
                             ?>
                        </td>
                        <td data-label="Certificate">
                            <?php if ($sp_cstate['generated']): ?>
                            <div class="sp-cert-rowline">
                                <span class="sp-badge sp-badge-good">Issued</span>
                                <span class="sp-muted">
                                    <?php echo esc_html($sp_cstate['serial']); ?>
                                    <?php if ($sp_cstate['issued_date']): ?>
                                    &middot;
                                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($sp_cstate['issued_date']))); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="sp-cert-rowline">
                                <?php $sp_view_url = sp_certificate_vendor_url((int) $sp_cstate['certificate_id']); ?>
                                <?php if (! is_wp_error($sp_view_url)): ?>
                                <a class="sp-btn sp-btn-ghost" target="_blank" rel="noopener"
                                    href="<?php echo esc_url($sp_view_url); ?>">View</a>
                                <?php endif; ?>
                                <a class="sp-btn sp-btn-ghost"
                                    href="<?php echo esc_url(add_query_arg(['tab' => 'certificates', 'cert_form' => $sp_id], $sp_base_url)); ?>">Reissue</a>
                            </div>
                            <?php elseif ($sp_cstate['can_generate']): ?>
                            <a class="sp-btn sp-btn-primary"
                                href="<?php echo esc_url(add_query_arg(['tab' => 'certificates', 'cert_form' => $sp_id], $sp_base_url)); ?>">Issue
                                certificate</a>
                            <?php else: ?>
                            <span class="sp-muted"><?php echo esc_html($sp_cstate['reason']); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($sp_cert_form): ?>
        <?php sp_teacher_render_certificate_form($sp_cert_form, $sp_class_id); ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php elseif ('curriculum' === $sp_tab):
            require SP_PATH . 'templates/teacher-curriculum.php';
    ?>

    <?php else:
            $sp_materials = sp_get_materials($sp_class_id, ['draft', 'published']);
    ?>
    <p class="sp-card-actions" style="margin-bottom: 1em;">
        <a class="sp-btn sp-btn-primary"
            href="<?php echo esc_url(add_query_arg('class', $sp_class_id, sp_teacher_url('materials/new'))); ?>">Add
            material</a>
    </p>

    <?php if (! $sp_materials): ?>
    <div class="sp-empty">No notes or documents for this class yet.</div>
    <?php else: ?>
    <div class="sp-card-list">
        <?php foreach ($sp_materials as $sp_material): ?>
        <article class="sp-card">
            <div class="sp-material-head">
                <div>
                    <h2 class="sp-card-title"><?php echo esc_html($sp_material->title); ?></h2>
                    <p class="sp-muted">
                        <?php echo 'file' === $sp_material->kind ? 'File' : 'Note'; ?>
                        &middot;
                        <?php echo 'published' === $sp_material->status
                                ? '<span class="sp-badge sp-badge-good">Published</span>'
                                : '<span class="sp-badge sp-badge-muted">Draft</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed escaped strings.
                         ?>
                        <?php if ($sp_material->file_size): ?>
                        &middot; <?php echo esc_html(sp_format_file_size($sp_material->file_size)); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="sp-card-actions">
                    <a class="sp-btn sp-btn-ghost"
                        href="<?php echo esc_url(sp_teacher_url('materials/' . (int) $sp_material->id)); ?>">Edit</a>
                    <form method="post" action="<?php echo esc_url($sp_base_url); ?>" data-sp-async
                        onsubmit="return confirm('Delete this material?');">
                        <?php sp_teacher_nonce_field('delete_material'); ?>
                        <input type="hidden" name="material_id"
                            value="<?php echo esc_attr((int) $sp_material->id); ?>" />
                        <button type="submit" class="sp-btn sp-btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>