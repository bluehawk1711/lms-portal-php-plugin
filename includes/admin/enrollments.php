<?php
/**
 * Enrolment management.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the enrolments screen.
 *
 * @return void
 */
function sp_admin_page_sp_enrollments() {
    // Approval first: a teacher-created class stays out of every enrolment
    // dropdown until an admin approves it on the Classes screen.
    $classes = array_values(
        array_filter(
            sp_get_classes( array( 'per_page' => 200 ) ),
            function ( $sp_row ) {
                return 'pending' !== (string) $sp_row->status;
            }
        )
    );

    if ( empty( $classes ) ) {
        ?>
        <div class="sp-scope sp-enrollments-page" id="sp-enrollments-region" data-sp-async-region>
            <div class="sp-enrollments-header">
                <div>
                    <p class="sp-eyebrow">Student management</p>
                    <h1>Enrolments</h1>
                    <p class="sp-page-description">Manage class rosters and add students to a class.</p>
                </div>
            </div>
            <div class="sp-admin-panel sp-empty-state">
                <span class="sp-empty-state-icon" aria-hidden="true">＋</span>
                <h2>No classes created yet</h2>
                <p>Create a class before managing student enrolments.</p>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-classes' ) ); ?>">Go to Classes</a>
            </div>
        </div>
        <?php
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only class filter.
    $selected = isset( $_GET['class'] ) ? absint( wp_unslash( $_GET['class'] ) ) : absint( $classes[0]->id );
    $class    = sp_get_class( $selected );

    if ( ! $class ) {
        $class = $classes[0];
    }

    $class_id   = absint( $class->id );
    $roster     = sp_get_enrollments_for_class( $class_id, array( 'active', 'completed', 'dropped' ), 0 );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only search filter.
    $search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $candidates = sp_get_profiles( array( 'search' => $search, 'per_page' => 10 ) );
    $roster     = is_array( $roster ) ? $roster : array();
    $candidates = is_array( $candidates ) ? $candidates : array();
    ?>
    <div class="sp-scope sp-enrollments-page" id="sp-enrollments-region" data-sp-async-region>
        <header class="sp-enrollments-header">
            <div class="sp-enrollments-heading-copy">
                <p class="sp-eyebrow">Student management</p>
                <h1>Enrolments</h1>
                <p class="sp-page-description">View class rosters, update enrolment details, and add students to a class.</p>
            </div>

            <form method="get" class="sp-enrollments-class-filter" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" data-sp-async>
                <input type="hidden" name="page" value="sp-enrollments" />
                <label for="sp-enrollments-class">Manage class</label>
                <div class="sp-filter-controls">
                    <select name="class" id="sp-enrollments-class" data-sp-async-change>
                        <?php foreach ( $classes as $sp_class_option ) : ?>
                            <option value="<?php echo esc_attr( absint( $sp_class_option->id ) ); ?>" <?php selected( $class_id, absint( $sp_class_option->id ) ); ?>>
                                <?php echo esc_html( $sp_class_option->title . ( ! empty( $sp_class_option->code ) ? ' (' . $sp_class_option->code . ')' : '' ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-primary">View class</button>
                </div>
            </form>
        </header>

        <div class="sp-admin-grid">
            <section class="sp-admin-panel sp-roster-panel" aria-labelledby="sp-roster-title">
                <div class="sp-panel-heading">
                    <div>
                        <p class="sp-section-kicker">Current class</p>
                        <h2 id="sp-roster-title">Roster <span class="sp-class-title">/ <?php echo esc_html( $class->title ); ?></span></h2>
                        <p class="sp-panel-description">Update enrolment IDs and admission dates, or change a student's status.</p>
                    </div>
                    <span class="sp-count-pill"><?php echo esc_html( count( $roster ) ); ?> student<?php echo 1 === count( $roster ) ? '' : 's'; ?></span>
                </div>

                <div class="sp-table-wrap">
                    <table class="widefat striped sp-table sp-enrollments-table sp-roster-table">
                        <thead>
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Enrolment ID</th>
                                <th scope="col">Admission date</th>
                                <th scope="col">Email</th>
                                <th scope="col">Status</th>
                                <th scope="col"><span class="screen-reader-text">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $roster ) ) : ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="sp-table-empty-state">
                                            <strong>No students enrolled yet</strong>
                                            <span>Use the enrolment section below to add students to this class.</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ( $roster as $sp_row ) : ?>
                                    <?php $save_form_id = 'sp-save-enrollment-' . absint( $sp_row->enrollment_id ); ?>
                                    <tr>
                                        <td class="sp-student-cell">
                                            <strong><?php echo esc_html( $sp_row->full_name ); ?></strong>
                                        </td>
                                        <td>
                                            <input
                                                form="<?php echo esc_attr( $save_form_id ); ?>"
                                                type="text"
                                                name="enroll_id"
                                                value="<?php echo esc_attr( $sp_row->enroll_id ); ?>"
                                                placeholder="e.g. SK-001"
                                                aria-label="<?php echo esc_attr( 'Enrolment ID for ' . $sp_row->full_name ); ?>"
                                                class="sp-table-input sp-enroll-id-input"
                                            />
                                        </td>
                                        <td>
                                            <input
                                                form="<?php echo esc_attr( $save_form_id ); ?>"
                                                type="date"
                                                name="admission_date"
                                                value="<?php echo esc_attr( $sp_row->admission_date ); ?>"
                                                aria-label="<?php echo esc_attr( 'Admission date for ' . $sp_row->full_name ); ?>"
                                                class="sp-table-input sp-date-input"
                                            />
                                        </td>
                                        <td class="sp-email-cell"><?php echo esc_html( $sp_row->email ); ?></td>
                                        <td><?php echo wp_kses_post( sp_admin_status_badge( $sp_row->status ) ); ?></td>
                                        <td class="sp-actions-cell">
                                            <div class="sp-admin-actions">
                                                <form id="<?php echo esc_attr( $save_form_id ); ?>" method="post" class="sp-admin-inline sp-save-enrollment-form" data-sp-async>
                                                    <?php sp_admin_nonce_field( 'save_enrollment_id' ); ?>
                                                    <input type="hidden" name="enrollment_id" value="<?php echo esc_attr( absint( $sp_row->enrollment_id ) ); ?>" />
                                                    <input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>" />
                                                    <button type="submit" class="button button-small">Save</button>
                                                </form>

                                                <?php if ( 'dropped' !== $sp_row->status ) : ?>
                                                    <form method="post" class="sp-admin-inline" data-sp-async>
                                                        <?php sp_admin_nonce_field( 'enrollment_status' ); ?>
                                                        <input type="hidden" name="enrollment_id" value="<?php echo esc_attr( absint( $sp_row->enrollment_id ) ); ?>" />
                                                        <input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>" />
                                                        <input type="hidden" name="status" value="<?php echo esc_attr( 'active' === $sp_row->status ? 'dropped' : 'active' ); ?>" />
                                                        <button type="submit" class="button button-small <?php echo 'active' === $sp_row->status ? 'sp-button-subtle-danger' : ''; ?>">
                                                            <?php echo 'active' === $sp_row->status ? 'Drop' : 'Reactivate'; ?>
                                                        </button>
                                                    </form>
                                                    <form method="post" class="sp-admin-inline" data-sp-async>
                                                        <?php sp_admin_nonce_field( 'enrollment_status' ); ?>
                                                        <input type="hidden" name="enrollment_id" value="<?php echo esc_attr( absint( $sp_row->enrollment_id ) ); ?>" />
                                                        <input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>" />
                                                        <input type="hidden" name="status" value="completed" />
                                                        <button type="submit" class="button button-small">Complete</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="sp-admin-panel sp-enrol-students-panel" aria-labelledby="sp-enrol-students-title">
                <div class="sp-panel-heading">
                    <div>
                        <p class="sp-section-kicker">Build your class</p>
                        <h2 id="sp-enrol-students-title">Enrol students</h2>
                        <p class="sp-panel-description">Search for students, select the people to add, and set optional enrolment details.</p>
                    </div>
                </div>

                <form method="get" class="sp-admin-search sp-student-search" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" data-sp-async>
                    <input type="hidden" name="page" value="sp-enrollments" />
                    <input type="hidden" name="class" value="<?php echo esc_attr( $class_id ); ?>" />
                    <label class="screen-reader-text" for="sp-enrollment-student-search">Search students by name or email</label>
                    <input id="sp-enrollment-student-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by student name or email…" />
                    <button type="submit" class="button">Search students</button>
                    <?php if ( $search ) : ?>
                        <a class="button sp-clear-search" href="<?php echo esc_url( add_query_arg( array( 'page' => 'sp-enrollments', 'class' => $class_id ), admin_url( 'admin.php' ) ) ); ?>">Clear search</a>
                    <?php endif; ?>
                </form>

                <?php if ( $search && empty( $candidates ) ) : ?>
                    <div class="sp-inline-notice" role="status">
                        <strong>No matching students found.</strong>
                        <span>Try another name or email, or <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-add-student' ) ); ?>">add a student</a>.</span>
                    </div>
                <?php elseif ( ! $search && empty( $candidates ) ) : ?>
                    <div class="sp-inline-notice" role="status">
                        <strong>No student profiles available.</strong>
                        <span><a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-add-student' ) ); ?>">Add a student</a> first, then return here to enrol them.</span>
                    </div>
                <?php endif; ?>

                <?php if ( ! $search && ! empty( $candidates ) ) : ?>
                    <p class="sp-admin-muted sp-results-hint">Showing the first <?php echo esc_html( count( $candidates ) ); ?> students. Search to narrow the list.</p>
                <?php elseif ( $search && ! empty( $candidates ) ) : ?>
                    <p class="sp-admin-muted sp-results-hint">Found <?php echo esc_html( count( $candidates ) ); ?> matching student<?php echo 1 === count( $candidates ) ? '' : 's'; ?> in this list.</p>
                <?php endif; ?>

                <?php if ( ! empty( $candidates ) ) : ?>
                    <form id="sp-enroll-students-form" method="post" class="sp-enrol-students-form" data-sp-async>
                        <?php sp_admin_nonce_field( 'enroll_student' ); ?>
                        <input type="hidden" name="class_id" value="<?php echo esc_attr( $class_id ); ?>" />

                        <div class="sp-table-wrap">
                            <table class="widefat striped sp-table sp-enroll-candidates-table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="sp-select-column">
                                            <input type="checkbox" id="sp-enroll-all" aria-label="Select all students" />
                                        </th>
                                        <th scope="col">Student</th>
                                        <th scope="col">Enrolment ID <span class="sp-th-optional">(optional)</span></th>
                                        <th scope="col">Admission date <span class="sp-th-optional">(optional)</span></th>
                                        <th scope="col">Notify</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $candidates as $sp_candidate ) : ?>
                                        <?php $candidate_id = absint( $sp_candidate->id ); ?>
                                        <tr>
                                            <td class="sp-select-column">
                                                <input type="checkbox" name="profile_id[]" value="<?php echo esc_attr( $candidate_id ); ?>" aria-label="<?php echo esc_attr( 'Select ' . $sp_candidate->full_name ); ?>" />
                                            </td>
                                            <td class="sp-student-cell"><?php echo esc_html( $sp_candidate->full_name ); ?></td>
                                            <td>
                                                <input type="text" name="enroll_id[<?php echo esc_attr( $candidate_id ); ?>]" placeholder="Auto-generate" aria-label="<?php echo esc_attr( 'Enrolment ID for ' . $sp_candidate->full_name ); ?>" class="sp-table-input sp-enroll-id-input" />
                                            </td>
                                            <td>
                                                <input type="date" name="admission_date[<?php echo esc_attr( $candidate_id ); ?>]" aria-label="<?php echo esc_attr( 'Admission date for ' . $sp_candidate->full_name ); ?>" class="sp-table-input sp-date-input" />
                                            </td>
                                            <td>
                                                <label class="sp-notify-label">
                                                    <input type="checkbox" name="notify[<?php echo esc_attr( $candidate_id ); ?>]" value="1" />
                                                    <span>Email</span>
                                                </label>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="sp-enrol-submit-row">
                            <div>
                                <p class="sp-selected-count" aria-live="polite"><strong data-selected-count>0</strong> students selected</p>
                                <p class="sp-admin-muted sp-form-help">Only selected students will be enrolled. Leave the ID blank to generate one automatically.</p>
                            </div>
                            <button type="submit" class="button button-primary sp-enrol-submit-button">Enrol selected students</button>
                        </div>
                    </form>

                    <script>
                    (function () {
                        function init() {
                        var form = document.getElementById('sp-enroll-students-form');
                        if (!form) {
                            return;
                        }

                        // The async region swap replaces this form; bind once per form node.
                        if (form.getAttribute('data-sp-enrol-bound')) {
                            return;
                        }
                        form.setAttribute('data-sp-enrol-bound', '1');

                        var master = form.querySelector('#sp-enroll-all');
                        var boxes = Array.prototype.slice.call(form.querySelectorAll('input[name="profile_id[]"]'));
                        var selectedCount = form.querySelector('[data-selected-count]');

                        function updateSelectionState() {
                            var checkedCount = boxes.filter(function (box) {
                                return box.checked;
                            }).length;

                            if (master) {
                                master.checked = boxes.length > 0 && checkedCount === boxes.length;
                                master.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
                            }

                            if (selectedCount) {
                                selectedCount.textContent = checkedCount;
                            }
                        }

                        if (master) {
                            master.addEventListener('change', function () {
                                boxes.forEach(function (box) {
                                    box.checked = master.checked;
                                });
                                updateSelectionState();
                            });
                        }

                        boxes.forEach(function (box) {
                            box.addEventListener('change', updateSelectionState);
                        });

                        updateSelectionState();
                        }

                        init();
                        document.addEventListener('sp:async-replace', init);
                    }());
                    </script>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <?php
}
