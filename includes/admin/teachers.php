<?php
/**
 * Teacher account management screen.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the teachers screen.
 *
 * @return void
 */
function sp_admin_page_sp_teachers() {
    $teachers = sp_get_teachers();
    $teachers = is_array( $teachers ) ? $teachers : array();

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only screen selection.
    $sp_edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
    $sp_editing = $sp_edit_id ? sp_get_teacher( $sp_edit_id ) : null;

    $active_teacher_count   = 0;
    $assigned_teacher_count = 0;
    $teacher_rows           = array();

    foreach ( $teachers as $sp_teacher_row ) {
        $sp_teacher_status = isset( $sp_teacher_row->status ) ? (string) $sp_teacher_row->status : '';
        if ( 'active' === $sp_teacher_status ) {
            ++$active_teacher_count;
        }

        $sp_teacher_classes = sp_get_classes( array( 'teacher_id' => absint( $sp_teacher_row->id ) ) );
        $sp_teacher_classes = is_array( $sp_teacher_classes ) ? $sp_teacher_classes : array();

        if ( ! empty( $sp_teacher_classes ) ) {
            ++$assigned_teacher_count;
        }

        $teacher_rows[] = array(
            'teacher' => $sp_teacher_row,
            'classes' => $sp_teacher_classes,
        );
    }
    ?>
    <div class="sp-scope sp-teachers-page">
        <header class="sp-teachers-header">
            <div class="sp-teachers-heading-copy">
                <p class="sp-teachers-eyebrow">People management</p>
                <h1><?php echo esc_html( $sp_editing ? 'Edit teacher' : 'Teacher accounts' ); ?></h1>
                <p class="sp-teachers-description">
                    Create and manage teacher logins, review assigned classes, and resend account credentials.
                </p>
            </div>
            <div class="sp-teachers-header-actions">
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-import&tab=teachers' ) ); ?>">
                    <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                    Import teachers
                </a>
                <?php if ( $sp_editing ) : ?>
                    <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-teachers' ) ); ?>">Cancel editing</a>
                <?php endif; ?>
            </div>
        </header>

        <section class="sp-teacher-stats" aria-label="Teacher account overview">
            <div class="sp-teacher-stat-card">
                <span class="sp-teacher-stat-icon sp-teacher-stat-icon-blue" aria-hidden="true">
                    <span class="dashicons dashicons-groups"></span>
                </span>
                <div>
                    <span class="sp-teacher-stat-label">Total teachers</span>
                    <strong class="sp-teacher-stat-value"><?php echo esc_html( number_format_i18n( count( $teachers ) ) ); ?></strong>
                </div>
            </div>
            <div class="sp-teacher-stat-card">
                <span class="sp-teacher-stat-icon sp-teacher-stat-icon-green" aria-hidden="true">
                    <span class="dashicons dashicons-yes-alt"></span>
                </span>
                <div>
                    <span class="sp-teacher-stat-label">Active accounts</span>
                    <strong class="sp-teacher-stat-value"><?php echo esc_html( number_format_i18n( $active_teacher_count ) ); ?></strong>
                </div>
            </div>
            <div class="sp-teacher-stat-card">
                <span class="sp-teacher-stat-icon sp-teacher-stat-icon-violet" aria-hidden="true">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                </span>
                <div>
                    <span class="sp-teacher-stat-label">With assigned classes</span>
                    <strong class="sp-teacher-stat-value"><?php echo esc_html( number_format_i18n( $assigned_teacher_count ) ); ?></strong>
                </div>
            </div>
        </section>

        <div class="sp-teachers-layout">
            <section class="sp-admin-panel sp-teacher-form-panel" aria-labelledby="sp-teacher-form-title">
                <div class="sp-teacher-panel-heading">
                    <div>
                        <p class="sp-teachers-section-kicker"><?php echo esc_html( $sp_editing ? 'Update account' : 'New account' ); ?></p>
                        <h2 id="sp-teacher-form-title"><?php echo esc_html( $sp_editing ? 'Edit teacher details' : 'Add a teacher' ); ?></h2>
                        <p class="sp-teacher-panel-description">
                            <?php echo esc_html( $sp_editing ? 'Update the teacher information below. Leave the password blank to keep it unchanged.' : 'Create login details for a teacher and add their contact information.' ); ?>
                        </p>
                    </div>
                    <span class="sp-teacher-heading-icon dashicons dashicons-admin-users" aria-hidden="true"></span>
                </div>

                <form method="post" class="sp-teacher-form">
                    <?php sp_admin_nonce_field( 'save_teacher' ); ?>
                    <?php if ( $sp_editing ) : ?>
                        <input type="hidden" name="teacher_id" value="<?php echo esc_attr( absint( $sp_editing->id ) ); ?>" />
                    <?php endif; ?>

                    <div class="sp-teacher-form-fields">
                        <div class="sp-teacher-field">
                            <label for="sp_t_name">Full name <span class="sp-required" aria-hidden="true">*</span></label>
                            <input type="text" id="sp_t_name" name="full_name" required autocomplete="name" class="regular-text" value="<?php echo esc_attr( $sp_editing ? $sp_editing->full_name : '' ); ?>" placeholder="e.g. Priya Sharma" />
                        </div>

                        <div class="sp-teacher-field">
                            <label for="sp_t_email">Email address <span class="sp-required" aria-hidden="true">*</span></label>
                            <input type="email" id="sp_t_email" name="email" required autocomplete="email" class="regular-text" value="<?php echo esc_attr( $sp_editing ? $sp_editing->email : '' ); ?>" placeholder="teacher@example.com" />
                            <p class="sp-teacher-field-help">Used for account communication and sign-in.</p>
                        </div>

                        <div class="sp-teacher-field">
                            <label for="sp_t_phone">Phone number <span class="sp-teacher-optional">Optional</span></label>
                            <input type="tel" id="sp_t_phone" name="phone" autocomplete="tel" class="regular-text" value="<?php echo esc_attr( $sp_editing ? $sp_editing->phone : '' ); ?>" placeholder="Enter phone number" />
                        </div>

                        <div class="sp-teacher-field">
                            <label for="sp_t_password">Password <?php if ( ! $sp_editing ) : ?><span class="sp-required" aria-hidden="true">*</span><?php else : ?><span class="sp-teacher-optional">Optional when editing</span><?php endif; ?></label>
                            <input type="password" id="sp_t_password" name="password" <?php echo $sp_editing ? '' : 'required'; ?> autocomplete="new-password" class="regular-text" placeholder="<?php echo esc_attr( $sp_editing ? 'Leave blank to keep current password' : 'Set an initial password' ); ?>" aria-describedby="sp_t_password_help" />
                            <p class="sp-teacher-field-help" id="sp_t_password_help">
                                <?php if ( $sp_editing ) : ?>
                                    Leave blank to keep the current password. Enter a new password to replace it; the teacher will use it at their next sign-in.
                                <?php else : ?>
                                    Set a password and share it with the teacher securely. The account handler should store it as a hash.
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="sp-teacher-field sp-teacher-field-full">
                            <label for="sp_t_bio">About the teacher <span class="sp-teacher-optional">Optional</span></label>
                            <textarea id="sp_t_bio" name="bio" rows="4" class="large-text" placeholder="Add a short profile or subject expertise..."><?php echo esc_textarea( $sp_editing ? $sp_editing->bio : '' ); ?></textarea>
                        </div>
                    </div>

                    <div class="sp-teacher-form-footer">
                        <p class="sp-teacher-required-note"><span class="sp-required" aria-hidden="true">*</span> Required fields</p>
                        <p class="submit">
                            <button type="submit" class="button button-primary sp-teacher-submit"><?php echo esc_html( $sp_editing ? 'Save teacher' : 'Create teacher account' ); ?></button>
                        </p>
                    </div>
                </form>
            </section>

            <section class="sp-admin-panel sp-teacher-list-panel" aria-labelledby="sp-teacher-list-title">
                <div class="sp-teacher-panel-heading sp-teacher-list-heading">
                    <div>
                        <p class="sp-teachers-section-kicker">Directory</p>
                        <h2 id="sp-teacher-list-title">All teachers</h2>
                        <p class="sp-teacher-panel-description">Review accounts, assigned classes, and account actions.</p>
                    </div>
                    <span class="sp-teacher-count-pill"><?php echo esc_html( number_format_i18n( count( $teachers ) ) ); ?> <?php echo esc_html( 1 === count( $teachers ) ? 'teacher' : 'teachers' ); ?></span>
                </div>

                <?php if ( empty( $teachers ) ) : ?>
                    <div class="sp-teacher-empty-state">
                        <span class="sp-teacher-empty-icon dashicons dashicons-groups" aria-hidden="true"></span>
                        <h3>No teacher accounts yet</h3>
                        <p>Create the first teacher account using the form. You can also import multiple teachers from a CSV file.</p>
                        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-import&tab=teachers' ) ); ?>">Import teachers</a>
                    </div>
                <?php else : ?>
                    <div class="sp-teacher-table-wrap">
                        <table class="widefat striped sp-teacher-table">
                            <thead>
                                <tr>
                                    <th scope="col">Teacher</th>
                                    <th scope="col">Contact</th>
                                    <th scope="col">Assigned classes</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="sp-teacher-actions-heading">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $teacher_rows as $sp_teacher_entry ) : ?>
                                    <?php
                                    $sp_teacher       = $sp_teacher_entry['teacher'];
                                    $sp_classes       = $sp_teacher_entry['classes'];
                                    $sp_teacher_status = isset( $sp_teacher->status ) ? (string) $sp_teacher->status : '';
                                    $sp_teacher_email  = isset( $sp_teacher->email ) ? (string) $sp_teacher->email : '';
                                    $sp_teacher_phone  = isset( $sp_teacher->phone ) ? (string) $sp_teacher->phone : '';
                                    ?>
                                    <tr>
                                        <td class="sp-teacher-identity-cell">
                                            <div class="sp-teacher-identity">
                                                <span class="sp-teacher-avatar" aria-hidden="true"><?php
                                                    $sp_teacher_initial = trim( (string) $sp_teacher->full_name );
                                                    if ( function_exists( 'mb_substr' ) ) {
                                                        $sp_teacher_initial = mb_substr( $sp_teacher_initial, 0, 1 );
                                                        $sp_teacher_initial = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $sp_teacher_initial ) : strtoupper( $sp_teacher_initial );
                                                    } else {
                                                        $sp_teacher_initial = strtoupper( substr( $sp_teacher_initial, 0, 1 ) );
                                                    }
                                                    echo esc_html( $sp_teacher_initial );
                                                ?></span>
                                                <div>
                                                    <strong><?php echo esc_html( $sp_teacher->full_name ); ?></strong>
                                                    <span class="sp-teacher-row-meta">Teacher account</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="sp-teacher-contact-cell">
                                            <a class="sp-teacher-email" href="mailto:<?php echo esc_attr( $sp_teacher_email ); ?>"><?php echo esc_html( $sp_teacher_email ); ?></a>
                                            <span class="sp-teacher-phone"><?php echo $sp_teacher_phone ? esc_html( $sp_teacher_phone ) : esc_html__( 'No phone added', 'student-portal' ); ?></span>
                                        </td>
                                        <td class="sp-teacher-classes-cell">
                                            <?php if ( $sp_classes ) : ?>
                                                <div class="sp-teacher-class-list">
                                                    <?php foreach ( $sp_classes as $sp_class ) : ?>
                                                        <span class="sp-teacher-class-chip"><?php echo esc_html( $sp_class->title ); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else : ?>
                                                <span class="sp-teacher-unassigned">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="sp-teacher-status-cell"><?php echo wp_kses_post( sp_admin_status_badge( $sp_teacher_status ) ); ?></td>
                                        <td class="sp-teacher-actions-cell">
                                            <div class="sp-teacher-actions">
                                                <a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'sp-teachers', 'edit' => absint( $sp_teacher->id ) ), admin_url( 'admin.php' ) ) ); ?>">Edit</a>

                                                <form method="post" class="sp-teacher-action-form">
                                                    <?php sp_admin_nonce_field( 'resend_credentials' ); ?>
                                                    <input type="hidden" name="user_id" value="<?php echo esc_attr( absint( $sp_teacher->user_id ) ); ?>" />
                                                    <button type="submit" class="button button-small">Resend credentials</button>
                                                </form>

                                                <form method="post" class="sp-teacher-action-form">
                                                    <?php sp_admin_nonce_field( 'toggle_status' ); ?>
                                                    <input type="hidden" name="record" value="teacher" />
                                                    <input type="hidden" name="record_id" value="<?php echo esc_attr( absint( $sp_teacher->id ) ); ?>" />
                                                    <input type="hidden" name="status" value="<?php echo esc_attr( 'active' === $sp_teacher_status ? 'disabled' : 'active' ); ?>" />
                                                    <button type="submit" class="button button-small"><?php echo esc_html( 'active' === $sp_teacher_status ? 'Disable' : 'Enable' ); ?></button>
                                                </form>

                                                <form method="post" class="sp-teacher-action-form" onsubmit="return confirm('Delete this teacher completely — their portal row, class links, attendance overrides and login? This cannot be undone.');">
                                                    <?php sp_admin_nonce_field( 'delete_teacher' ); ?>
                                                    <input type="hidden" name="teacher_id" value="<?php echo esc_attr( absint( $sp_teacher->id ) ); ?>" />
                                                    <button type="submit" class="button button-small sp-button-subtle-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="sp-teacher-table-note">Tip: use <strong>Edit</strong> to update a teacher’s contact details or password.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <?php
}
