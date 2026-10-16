<?php
/**
 * Classes plus teacher assignment — premium admin screen.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Classes admin screen.
 *
 * @return void
 */
function sp_admin_page_sp_classes() {
    $classes  = sp_get_classes( array( 'per_page' => 200 ) );
    $classes  = is_array( $classes ) ? $classes : array();
    $teachers = sp_get_teachers();
    $teachers = is_array( $teachers ) ? $teachers : array();
    $courses  = sp_get_courses();
    $courses  = is_array( $courses ) ? $courses : array();

    $course_map = array();
    foreach ( $courses as $sp_one ) {
        $course_map[ (int) $sp_one->id ] = $sp_one->name;
    }

    $edit_id = isset( $_GET['edit'] ) && is_scalar( $_GET['edit'] )
        ? absint( wp_unslash( $_GET['edit'] ) )
        : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $focus = isset( $_GET['class'] ) && is_scalar( $_GET['class'] )
        ? absint( wp_unslash( $_GET['class'] ) )
        : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $editing    = $edit_id ? sp_get_class( $edit_id ) : null;
    $focus_data = $focus ? sp_get_class( $focus ) : null;
    $editing_timings = $editing ? sp_get_class_timings( $editing->id ) : array();

    // All days share one timing pair, so prefill from the first stored row.
    $sp_first_timing = $editing_timings ? reset( $editing_timings ) : null;

    $sp_active_classes  = 0;
    $sp_pending_classes = 0;

    foreach ( $classes as $sp_count_row ) {
        if ( 'active' === (string) $sp_count_row->status ) {
            ++$sp_active_classes;
        } elseif ( 'pending' === (string) $sp_count_row->status ) {
            ++$sp_pending_classes;
        }
    }
    ?>
    <div class="sp-classes-ui">
        <header class="sp-classes-page-header">
            <div class="sp-classes-page-heading">
                <span class="sp-classes-eyebrow">STUDENT PORTAL <span aria-hidden="true">/</span> ACADEMIC MANAGEMENT</span>
                <h1>Classes</h1>
                <p>Create classes, manage course details, and organise teacher assignments in one place.</p>
            </div>
            <div class="sp-classes-header-note">
                <span class="sp-classes-header-mark" aria-hidden="true">▦</span>
                <span><strong>Class workspace</strong><small>Courses, schedules and teaching staff</small></span>
            </div>
        </header>

        <section class="sp-classes-stats" aria-label="Class overview">
            <article class="sp-classes-stat-card">
                <span class="sp-classes-stat-icon sp-classes-stat-icon-total" aria-hidden="true">▦</span>
                <div><span class="sp-classes-stat-label">Total classes</span><strong><?php echo esc_html( number_format_i18n( count( $classes ) ) ); ?></strong><small>Loaded in this view</small></div>
            </article>
            <article class="sp-classes-stat-card">
                <span class="sp-classes-stat-icon sp-classes-stat-icon-active" aria-hidden="true">✓</span>
                <div><span class="sp-classes-stat-label">Active</span><strong><?php echo esc_html( number_format_i18n( $sp_active_classes ) ); ?></strong><small>Ready for enrolment</small></div>
            </article>
            <article class="sp-classes-stat-card">
                <span class="sp-classes-stat-icon sp-classes-stat-icon-pending" aria-hidden="true">◷</span>
                <div><span class="sp-classes-stat-label">Awaiting approval</span><strong><?php echo esc_html( number_format_i18n( $sp_pending_classes ) ); ?></strong><small>Teacher-created classes</small></div>
            </article>
            <article class="sp-classes-stat-card">
                <span class="sp-classes-stat-icon sp-classes-stat-icon-teachers" aria-hidden="true">♙</span>
                <div><span class="sp-classes-stat-label">Teachers</span><strong><?php echo esc_html( number_format_i18n( count( $teachers ) ) ); ?></strong><small>Available to assign</small></div>
            </article>
        </section>

        <div class="sp-classes-layout">
            <section class="sp-classes-panel sp-classes-form-panel" aria-labelledby="sp-classes-form-title">
                <div class="sp-classes-panel-heading">
                    <div>
                        <span class="sp-classes-section-kicker"><?php echo $editing ? 'UPDATE DETAILS' : 'GET STARTED'; ?></span>
                        <h2 id="sp-classes-form-title"><?php echo $editing ? 'Edit class' : 'Create a class'; ?></h2>
                        <p><?php echo $editing ? 'Update the class information and schedule below.' : 'Add a class, connect it to a course, and set its schedule.'; ?></p>
                    </div>
                    <span class="sp-classes-heading-icon" aria-hidden="true">＋</span>
                </div>

                <form method="post" class="sp-classes-form">
                    <?php sp_admin_nonce_field( 'save_class' ); ?>
                    <?php if ( $editing ) : ?>
                        <input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $editing->id ); ?>" />
                    <?php endif; ?>

                    <div class="sp-classes-field">
                        <label for="sp_o_title">Class title <span class="sp-required">*</span></label>
                        <input type="text" id="sp_o_title" name="title" required value="<?php echo esc_attr( $editing ? $editing->title : '' ); ?>" placeholder="e.g. Graphic Design — Batch 02" />
                        <p class="description">Choose a recognisable name students and teachers will understand.</p>
                    </div>

                    <div class="sp-classes-form-row">
                        <div class="sp-classes-field">
                            <label for="sp_o_code">Class code</label>
                            <input type="text" id="sp_o_code" name="code" value="<?php echo esc_attr( $editing ? $editing->code : '' ); ?>" placeholder="e.g. GD-2026-B02" />
                        </div>
                        <div class="sp-classes-field">
                            <label for="sp_o_course">Course</label>
                            <select id="sp_o_course" name="course_id">
                                <option value="0">No course linked</option>
                                <?php foreach ( $courses as $sp_course ) : ?>
                                    <option value="<?php echo esc_attr( (int) $sp_course->id ); ?>" <?php selected( $editing ? (int) $editing->course_id : 0, (int) $sp_course->id ); ?>>
                                        <?php echo esc_html( $sp_course->name . ( $sp_course->code ? ' (' . $sp_course->code . ')' : '' ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p class="description sp-classes-field-note">
                        The class code is matched against the <code>class_code</code> column when importing students.
                        <?php if ( $courses ) : ?>Course selection also determines the course code stamped into certificate IDs.<?php else : ?>No courses have been created yet.<?php endif; ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-courses' ) ); ?>">Manage courses</a>
                    </p>

                    <div class="sp-classes-divider"></div>
                    <div class="sp-classes-form-section-title"><span>01</span><div><strong>Class period</strong><small>Set the expected start and end dates.</small></div></div>

                    <div class="sp-classes-form-row">
                        <div class="sp-classes-field">
                            <label for="sp_o_start">Start date</label>
                            <input type="date" id="sp_o_start" name="start_date" value="<?php echo esc_attr( $editing ? $editing->start_date : '' ); ?>" />
                        </div>
                        <div class="sp-classes-field">
                            <label for="sp_o_end">End date</label>
                            <input type="date" id="sp_o_end" name="end_date" value="<?php echo esc_attr( $editing ? $editing->end_date : '' ); ?>" />
                        </div>
                    </div>

                    <div class="sp-classes-divider"></div>
                    <div class="sp-classes-form-section-title"><span>02</span><div><strong>Daily timing</strong><small>One pair applies to every day of the week.</small></div></div>

                    <div class="sp-classes-timing-fields">
                        <div class="sp-classes-field">
                            <label class="screen-reader-text" for="sp_t_daily_start">Daily start time</label>
                            <span class="sp-classes-input-hint">Starts at</span>
                            <input type="time" id="sp_t_daily_start" name="timing_all[start]" value="<?php echo esc_attr( $sp_first_timing ? $sp_first_timing['start_time'] : '' ); ?>" />
                        </div>
                        <span class="sp-classes-time-separator" aria-hidden="true">to</span>
                        <div class="sp-classes-field">
                            <label class="screen-reader-text" for="sp_t_daily_end">Daily end time</label>
                            <span class="sp-classes-input-hint">Ends at</span>
                            <input type="time" id="sp_t_daily_end" name="timing_all[end]" value="<?php echo esc_attr( $sp_first_timing ? $sp_first_timing['end_time'] : '' ); ?>" />
                        </div>
                    </div>
                    <p class="description">Leave both fields empty if the class has no fixed daily schedule.</p>

                    <?php if ( $editing_timings ) : ?>
                        <?php $sp_set_id = $editing ? (int) $editing->timings_id : 0; ?>
                        <?php $sp_shared = $sp_set_id ? sp_count_classes_with_timings( $sp_set_id, (int) $editing->id ) : 0; ?>
                        <div class="sp-classes-pattern-note">
                            <span aria-hidden="true">◷</span>
                            <div><small>Current saved pattern</small><strong><?php echo esc_html( sp_timing_set_label( $editing_timings ) ); ?></strong>
                                <?php if ( $sp_shared > 0 ) : ?>
                                    <small>Shared with <?php echo esc_html( number_format_i18n( $sp_shared ) ); ?> other <?php echo 1 === $sp_shared ? 'class' : 'classes'; ?>.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="sp-classes-divider"></div>
                    <div class="sp-classes-form-section-title"><span>03</span><div><strong>Visibility and notes</strong><small>Choose the class status and add context.</small></div></div>

                    <div class="sp-classes-field">
                        <label for="sp_o_status">Class status</label>
                        <select id="sp_o_status" name="status">
                            <?php if ( $editing && 'pending' === (string) $editing->status ) : ?>
                                <option value="pending" selected>Pending approval</option>
                            <?php endif; ?>
                            <option value="active" <?php selected( $editing ? $editing->status : 'active', 'active' ); ?>>Active</option>
                            <option value="archived" <?php selected( $editing ? $editing->status : '', 'archived' ); ?>>Archived</option>
                        </select>
                        <?php if ( $editing && 'pending' === (string) $editing->status ) : ?>
                            <p class="description">This class was created by a teacher and is waiting for approval.</p>
                        <?php endif; ?>
                    </div>

                    <div class="sp-classes-field">
                        <label for="sp_o_desc">Description <span class="sp-classes-optional">Optional</span></label>
                        <textarea id="sp_o_desc" name="description" rows="3" placeholder="Add a short description, batch details or internal notes…"><?php echo esc_textarea( $editing ? $editing->description : '' ); ?></textarea>
                    </div>

                    <div class="sp-classes-form-footer">
                        <p><span class="sp-required">*</span> Required field</p>
                        <div class="sp-classes-form-actions">
                            <?php if ( $editing ) : ?>
                                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-classes' ) ); ?>">Cancel</a>
                            <?php endif; ?>
                            <button type="submit" class="button button-primary sp-classes-submit"><?php echo $editing ? 'Save changes' : 'Create class'; ?><span aria-hidden="true">→</span></button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="sp-classes-panel sp-classes-list-panel" aria-labelledby="sp-classes-list-title">
                <div class="sp-classes-panel-heading sp-classes-list-heading">
                    <div>
                        <span class="sp-classes-section-kicker">CLASS DIRECTORY</span>
                        <h2 id="sp-classes-list-title">All classes <span class="sp-classes-count"><?php echo esc_html( number_format_i18n( count( $classes ) ) ); ?></span></h2>
                        <p>Review class details, student totals, assigned teachers and status.</p>
                    </div>
                    <span class="sp-classes-heading-icon" aria-hidden="true">☷</span>
                </div>

                <?php if ( count( $classes ) >= 200 ) : ?>
                    <p class="sp-classes-list-notice">Showing up to 200 classes in this view.</p>
                <?php endif; ?>

                <div class="sp-classes-table-wrap">
                    <table class="widefat sp-classes-table">
                        <thead>
                            <tr>
                                <th scope="col">Class</th>
                                <th scope="col">Course</th>
                                <th scope="col">Students</th>
                                <th scope="col">Teachers</th>
                                <th scope="col">Status</th>
                                <th scope="col"><span class="screen-reader-text">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! $classes ) : ?>
                                <tr><td colspan="6"><div class="sp-classes-empty"><span aria-hidden="true">▦</span><strong>No classes yet</strong><p>Create your first class using the form to get started.</p></div></td></tr>
                            <?php endif; ?>

                            <?php foreach ( $classes as $sp_row ) : ?>
                                <?php
                                $sp_assigned  = sp_get_class_teachers( $sp_row->id );
                                $sp_assigned  = is_array( $sp_assigned ) ? $sp_assigned : array();
                                $sp_course_id = isset( $sp_row->course_id ) ? (int) $sp_row->course_id : 0;
                                $sp_students  = (int) sp_count_class_students( $sp_row->id );
                                ?>
                                <tr>
                                    <td class="sp-classes-title-cell">
                                        <span class="sp-classes-class-avatar" aria-hidden="true"><?php echo esc_html( strtoupper( function_exists( 'mb_substr' ) ? mb_substr( (string) $sp_row->title, 0, 1 ) : substr( (string) $sp_row->title, 0, 1 ) ) ); ?></span>
                                        <span class="sp-classes-class-details"><strong><?php echo esc_html( $sp_row->title ); ?></strong><small><?php echo $sp_row->code ? esc_html( $sp_row->code ) : 'No class code'; ?></small></span>
                                    </td>
                                    <td>
                                        <?php if ( $sp_course_id && isset( $course_map[ $sp_course_id ] ) ) : ?>
                                            <span class="sp-classes-course-pill"><?php echo esc_html( $course_map[ $sp_course_id ] ); ?></span>
                                        <?php else : ?>
                                            <span class="sp-classes-muted">Not linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="sp-classes-number-pill"><?php echo esc_html( number_format_i18n( $sp_students ) ); ?></span></td>
                                    <td class="sp-classes-teachers-cell">
                                        <?php if ( $sp_assigned ) : ?>
                                            <?php foreach ( array_slice( $sp_assigned, 0, 2 ) as $sp_teacher ) : ?>
                                                <span class="sp-classes-teacher-name"><?php echo esc_html( $sp_teacher->full_name ); ?><?php if ( $sp_teacher->is_primary ) : ?><small>Lead</small><?php endif; ?></span>
                                            <?php endforeach; ?>
                                            <?php if ( count( $sp_assigned ) > 2 ) : ?><span class="sp-classes-teacher-more">+<?php echo esc_html( count( $sp_assigned ) - 2 ); ?> more</span><?php endif; ?>
                                        <?php else : ?>
                                            <span class="sp-classes-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="sp-classes-status sp-classes-status-<?php echo esc_attr( sanitize_html_class( (string) $sp_row->status ) ); ?>"><?php echo esc_html( ucwords( str_replace( array( '-', '_' ), ' ', (string) $sp_row->status ) ) ); ?></span></td>
                                    <td class="sp-classes-actions-cell">
                                        <div class="sp-classes-row-actions">
                                            <?php if ( 'pending' === (string) $sp_row->status ) : ?>
                                                <form method="post" class="sp-classes-inline-form">
                                                    <?php sp_admin_nonce_field( 'approve_class' ); ?>
                                                    <input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
                                                    <button type="submit" class="button button-small sp-classes-approve">Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            <a class="button button-small button-primary sp-classes-assign" href="<?php echo esc_url( add_query_arg( 'class', (int) $sp_row->id, admin_url( 'admin.php?page=sp-classes' ) ) ); ?>">Assign</a>
                                            <a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'edit' => (int) $sp_row->id ), admin_url( 'admin.php?page=sp-classes' ) ) ); ?>">Edit</a>
                                            <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-enrollments&class=' . (int) $sp_row->id ) ); ?>">Enrolments</a>
                                            <form method="post" class="sp-classes-inline-form sp-classes-delete-form" onsubmit="return confirm('Delete this class and EVERYTHING inside it — enrolments, attendance registers, results, materials, assignments and calendar entries? This cannot be undone.');">
                                                <?php sp_admin_nonce_field( 'delete_class' ); ?>
                                                <input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
                                                <button type="submit" class="button button-small sp-classes-delete">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="sp-classes-table-footer">
                    <span>Showing <strong><?php echo esc_html( number_format_i18n( count( $classes ) ) ); ?></strong> class<?php echo 1 === count( $classes ) ? '' : 'es'; ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-courses' ) ); ?>">Manage courses <span aria-hidden="true">↗</span></a>
                </div>
            </section>
        </div>

        <?php if ( $focus_data ) : ?>
            <?php $sp_current = sp_get_class_teachers( $focus_data->id ); $sp_current = is_array( $sp_current ) ? $sp_current : array(); ?>
            <section class="sp-classes-panel sp-classes-assignment-panel" aria-labelledby="sp-classes-assignment-title">
                <div class="sp-classes-panel-heading">
                    <div>
                        <span class="sp-classes-section-kicker">STAFFING</span>
                        <h2 id="sp-classes-assignment-title">Teachers for <?php echo esc_html( $focus_data->title ); ?></h2>
                        <p>Assign teachers to this class and choose a lead teacher if needed.</p>
                    </div>
                    <span class="sp-classes-heading-icon" aria-hidden="true">♙</span>
                </div>

                <div class="sp-classes-table-wrap sp-classes-assigned-wrap">
                    <table class="widefat sp-classes-table sp-classes-assigned-table">
                        <thead><tr><th scope="col">Teacher</th><th scope="col">Role</th><th scope="col">Action</th></tr></thead>
                        <tbody>
                            <?php if ( ! $sp_current ) : ?>
                                <tr><td colspan="3"><div class="sp-classes-inline-empty"><strong>No teachers assigned yet</strong><span>Use the form below to add a teacher to this class.</span></div></td></tr>
                            <?php endif; ?>
                            <?php foreach ( $sp_current as $sp_teacher ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $sp_teacher->full_name ); ?></strong></td>
                                    <td><?php if ( $sp_teacher->is_primary ) : ?><span class="sp-classes-lead-pill">Lead teacher</span><?php else : ?><span class="sp-classes-muted">Teacher</span><?php endif; ?></td>
                                    <td>
                                        <form method="post" class="sp-classes-inline-form">
                                            <?php sp_admin_nonce_field( 'unassign_teacher' ); ?>
                                            <input type="hidden" name="teacher_id" value="<?php echo esc_attr( (int) $sp_teacher->id ); ?>" />
                                            <input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $focus_data->id ); ?>" />
                                            <button type="submit" class="button button-small sp-classes-unassign">Unassign</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ( $teachers ) : ?>
                    <form method="post" class="sp-classes-assign-form">
                        <?php sp_admin_nonce_field( 'assign_teacher' ); ?>
                        <input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $focus_data->id ); ?>" />
                        <div class="sp-classes-field sp-classes-assign-select">
                            <label for="sp_assign_teacher_id">Select teacher</label>
                            <select id="sp_assign_teacher_id" name="teacher_id" required>
                                <?php foreach ( $teachers as $sp_teacher ) : ?>
                                    <option value="<?php echo esc_attr( (int) $sp_teacher->id ); ?>"><?php echo esc_html( $sp_teacher->full_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sp-classes-assign-options">
                            <label><input type="checkbox" name="is_primary" value="1" /> <span><strong>Lead teacher</strong><small>Mark as the primary contact for this class.</small></span></label>
                            <label><input type="checkbox" name="notify" value="1" checked /> <span><strong>Email notification</strong><small>Notify the teacher about this assignment.</small></span></label>
                        </div>
                        <button type="submit" class="button button-primary sp-classes-submit">Assign teacher <span aria-hidden="true">→</span></button>
                    </form>
                <?php else : ?>
                    <div class="sp-classes-teacher-empty"><p>No teachers are available to assign yet.</p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-teachers' ) ); ?>">Go to Teachers</a></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <footer class="sp-classes-page-footer">
            <span><span class="sp-classes-footer-dot" aria-hidden="true"></span> Class management</span>
            <span>Keep course, schedule and teacher information up to date.</span>
        </footer>
    </div>
    <?php
}
