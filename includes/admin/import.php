<?php
/**
 * CSV import screen, shared by students and teachers.
 *
 * The two imports share one workflow and use separate plan transients, so a
 * preview is only shown on the tab for which it was created.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Import screen: tab switch plus the active import type.
 */
function sp_admin_page_sp_import() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'students';

    if ( ! in_array( $tab, array( 'students', 'teachers' ), true ) ) {
        $tab = 'students';
    }

    $students_url = admin_url( 'admin.php?page=sp-import' );
    $teachers_url = add_query_arg( 'tab', 'teachers', admin_url( 'admin.php?page=sp-import' ) );
    ?>
    <div class="sp-import-page">
        <header class="sp-import-heading">
            <div class="sp-import-heading-copy">
                <span class="sp-import-eyebrow">DATA MANAGEMENT</span>
                <h1>Import accounts</h1>
                <p>Bring student or teacher records into the portal using a CSV file. Review the preview before anything is saved.</p>
            </div>
            <div class="sp-import-heading-help">
                <span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
                <span>CSV import</span>
            </div>
        </header>

        <nav class="sp-import-tabs" aria-label="Choose account type">
            <a class="sp-import-tab <?php echo 'students' === $tab ? 'is-active' : ''; ?>"
                href="<?php echo esc_url( $students_url ); ?>"
                <?php echo 'students' === $tab ? 'aria-current="page"' : ''; ?>>
                <span class="dashicons dashicons-groups" aria-hidden="true"></span>
                <span>Students</span>
                <span class="sp-import-tab-description">Student records and enrolments</span>
            </a>
            <a class="sp-import-tab <?php echo 'teachers' === $tab ? 'is-active' : ''; ?>"
                href="<?php echo esc_url( $teachers_url ); ?>"
                <?php echo 'teachers' === $tab ? 'aria-current="page"' : ''; ?>>
                <span class="dashicons dashicons-businessperson" aria-hidden="true"></span>
                <span>Teachers</span>
                <span class="sp-import-tab-description">Teacher account details</span>
            </a>
        </nav>

        <?php sp_admin_render_import( 'teachers' === $tab ? 'teacher' : 'student' ); ?>
    </div>
    <?php
}

/**
 * Render the import workflow for one record type.
 *
 * @param string $type 'student' or 'teacher'.
 */
function sp_admin_render_import( $type ) {
    $is_student = ( 'student' === $type );
    $plan       = get_transient( sp_import_plan_key( $type ) );
    $action     = $is_student ? 'import_students' : 'import_teachers';
    $columns    = $is_student
        ? 'name, email, phone, address, enroll_id, class_code, admission_date, dob, password'
        : 'name, email, phone, password';
    $sample     = $is_student ? 'students' : 'teachers';
    ?>
    <div class="sp-import-workspace">
        <section class="sp-admin-panel sp-import-format-panel" aria-labelledby="sp-import-format-title">
            <div class="sp-import-section-heading">
                <span class="sp-import-step">01</span>
                <div>
                    <h2 id="sp-import-format-title">Prepare your CSV</h2>
                    <p class="sp-import-section-description">Use the column names below in your first row. Column names are matched by the import workflow.</p>
                </div>
            </div>

            <div class="sp-import-columns-label">Expected columns</div>
            <div class="sp-import-code-block"><code><?php echo esc_html( $columns ); ?></code></div>

            <div class="sp-import-requirements">
                <div class="sp-import-requirement">
                    <span class="sp-import-requirement-icon is-required" aria-hidden="true">*</span>
                    <div><strong>Required</strong><p><code>name</code> and <code>email</code> must be provided for each record<?php if ( ! $is_student ) : ?>, and so must <code>password</code><?php endif; ?>.</p></div>
                </div>
                <?php if ( $is_student ) : ?>
                    <div class="sp-import-requirement">
                        <span class="sp-import-requirement-icon" aria-hidden="true"><span class="dashicons dashicons-welcome-write-blog"></span></span>
                        <div><strong>Student enrolment fields</strong><p><code>enroll_id</code> sets the enrolment ID. <code>class_code</code> matches a class and enrols the student in one step. <code>admission_date</code> sets the admission date.</p></div>
                    </div>
                <?php endif; ?>
                <div class="sp-import-requirement">
                    <span class="sp-import-requirement-icon" aria-hidden="true"><span class="dashicons dashicons-info-outline"></span></span>
                    <div>
                        <strong>Optional columns</strong>
                        <p>
                            <?php if ( $is_student ) : ?>
                                <code>phone</code>, <code>dob</code>, <code>address</code> and <code>password</code> are optional. <code>address</code> is printed on the student's ID card and is cut at 100 characters. Leave <code>password</code> blank and a random one is generated and emailed to the student.
                            <?php else : ?>
                                <code>phone</code> is optional.
                            <?php endif; ?>
                            Extra columns are ignored.
                        </p>
                    </div>
                </div>
            </div>

            <div class="sp-import-format-footer">
                <p class="sp-admin-muted">Not sure about the format? Start with the sample file.</p>
                <a class="button" href="<?php echo esc_url( add_query_arg( 'sp_sample', $sample, admin_url( 'admin.php' ) ) ); ?>">
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    Download sample CSV
                </a>
            </div>
        </section>

        <section class="sp-admin-panel sp-import-upload-panel" aria-labelledby="sp-import-upload-title">
            <div class="sp-import-section-heading">
                <span class="sp-import-step">02</span>
                <div>
                    <h2 id="sp-import-upload-title">Upload and preview</h2>
                    <p class="sp-import-section-description">Choose your completed CSV file to check the rows before importing.</p>
                </div>
            </div>

            <form method="post" enctype="multipart/form-data" class="sp-import-upload-form">
                <?php sp_admin_nonce_field( $action ); ?>
                <input type="hidden" name="step" value="plan" />

                <label class="sp-import-file-drop" for="sp-import-file-<?php echo esc_attr( $type ); ?>">
                    <span class="sp-import-file-icon"><span class="dashicons dashicons-cloud-upload" aria-hidden="true"></span></span>
                    <strong>Choose a CSV file</strong>
                    <span class="sp-import-file-description">Select a file from your computer to prepare the preview.</span>
                    <span class="sp-import-file-types">Accepted formats: .csv, .txt</span>
                    <input type="file" id="sp-import-file-<?php echo esc_attr( $type ); ?>" name="import_file" accept=".csv,.txt,text/csv,text/plain" required />
                </label>

                <div class="sp-import-upload-actions">
                    <?php submit_button( 'Preview import', 'primary', 'submit', false ); ?>
                    <p class="sp-admin-muted"><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span> Nothing is saved until you confirm the preview.</p>
                </div>
            </form>
        </section>
    </div>

    <?php if ( is_array( $plan ) && ! empty( $plan ) ) : ?>
        <?php
        $create = 0;
        $skip   = 0;
        $errors = 0;

        foreach ( $plan as $sp_row ) {
            if ( isset( $sp_row['action'] ) && 'create' === $sp_row['action'] ) {
                ++$create;
            } elseif ( isset( $sp_row['action'] ) && 'skip' === $sp_row['action'] ) {
                ++$skip;
            } else {
                ++$errors;
            }
        }
        ?>

        <section class="sp-admin-panel sp-import-preview-panel" aria-labelledby="sp-import-preview-title">
            <div class="sp-import-preview-heading">
                <div class="sp-import-section-heading">
                    <span class="sp-import-step">03</span>
                    <div>
                        <h2 id="sp-import-preview-title">Review import preview</h2>
                        <p class="sp-import-section-description">Check the results below. Continue only when the records look correct.</p>
                    </div>
                </div>
                <div class="sp-import-preview-counts" aria-label="Import preview summary">
                    <span class="sp-pill sp-pill-good"><?php echo esc_html( $create ); ?> to create</span>
                    <span class="sp-pill sp-pill-muted"><?php echo esc_html( $skip ); ?> skipped</span>
                    <span class="sp-pill sp-pill-bad"><?php echo esc_html( $errors ); ?> problems</span>
                </div>
            </div>

            <div class="sp-table-wrap sp-import-preview-table-wrap">
                <table class="widefat striped sp-import-preview-table">
                    <thead>
                        <tr>
                            <th scope="col">Line</th>
                            <th scope="col">Action</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $plan as $sp_row ) : ?>
                            <tr>
                                <td><?php echo esc_html( isset( $sp_row['line'] ) ? $sp_row['line'] : '' ); ?></td>
                                <td><?php echo wp_kses_post( sp_admin_status_badge( isset( $sp_row['action'] ) ? $sp_row['action'] : 'error' ) ); ?></td>
                                <td><strong><?php echo esc_html( isset( $sp_row['name'] ) ? $sp_row['name'] : '' ); ?></strong></td>
                                <td><?php echo esc_html( isset( $sp_row['email'] ) ? $sp_row['email'] : '' ); ?></td>
                                <td><?php echo esc_html( isset( $sp_row['detail'] ) ? $sp_row['detail'] : '' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="sp-import-confirm-bar">
                <div>
                    <strong>Ready to import?</strong>
                    <p class="sp-admin-muted">Only records marked for creation will be created when you confirm.</p>
                </div>
                <form method="post" class="sp-import-commit-form">
                    <?php sp_admin_nonce_field( $action ); ?>
                    <input type="hidden" name="step" value="commit" />
                    <?php submit_button( sprintf( 'Create %d account(s)', $create ), 'primary', 'submit', false ); ?>
                </form>
            </div>
        </section>
    <?php endif; ?>
    <?php
}
