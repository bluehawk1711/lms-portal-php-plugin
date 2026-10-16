<?php
/**
 * Results administration — premium admin screen.
 *
 * Teachers normally enter marks from the teacher portal. Administrators can
 * review, publish or unpublish results, and issue eligible certificates here.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Results admin screen.
 *
 * @return void
 */
function sp_admin_page_sp_results() {
    // Pending, unapproved classes cannot receive marks yet.
    $classes = array_values(
        array_filter(
            sp_get_classes( array( 'per_page' => 200 ) ),
            function ( $sp_row ) {
                return 'pending' !== (string) $sp_row->status;
            }
        )
    );

    if ( ! $classes ) {
        ?>
        <div class="sp-results-ui">
            <header class="sp-results-page-header">
                <div class="sp-results-page-heading">
                    <span class="sp-results-eyebrow">STUDENT PORTAL <span aria-hidden="true">/</span> ACADEMIC MANAGEMENT</span>
                    <h1>Results &amp; certificates</h1>
                    <p>Review student performance, publish results, and manage certificate issuance.</p>
                </div>
                <div class="sp-results-header-note">
                    <span class="sp-results-header-mark" aria-hidden="true">✓</span>
                    <span><strong>Results workspace</strong><small>Marks, publication and certificates</small></span>
                </div>
            </header>
            <div class="sp-results-empty">
                <span class="sp-results-empty-icon" aria-hidden="true">▤</span>
                <strong>No classes available yet</strong>
                <p>Create a class first, then return here to review marks for its students.</p>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-classes' ) ); ?>">Go to Classes</a>
            </div>
        </div>
        <?php
        return;
    }

    $class_id = isset( $_GET['class'] ) && is_scalar( $_GET['class'] )
        ? absint( wp_unslash( $_GET['class'] ) )
        : (int) $classes[0]->id; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $class = sp_get_class( $class_id );

    if ( ! $class ) {
        $class    = $classes[0];
        $class_id = (int) $class->id;
    }

    $rows      = sp_get_class_results( (int) $class->id );
    $rows      = is_array( $rows ) ? $rows : array();
    $cert_form = isset( $_GET['cert_form'] ) && is_scalar( $_GET['cert_form'] )
        ? absint( wp_unslash( $_GET['cert_form'] ) )
        : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $issuable        = 0;
    $issued          = 0;
    $entered_count   = 0;
    $published_count = 0;
    $unpublished_count = 0;

    foreach ( $rows as $sp_scan ) {
        if ( $sp_scan->result_id ) {
            ++$entered_count;
            if ( $sp_scan->finalised ) {
                ++$published_count;
            } else {
                ++$unpublished_count;
            }
        }

        if ( ! $sp_scan->enrollment_id ) {
            continue;
        }

        $sp_state = sp_certificate_state_for_enrollment( (int) $sp_scan->enrollment_id );

        if ( ! empty( $sp_state['generated'] ) ) {
            ++$issued;
        } elseif ( ! empty( $sp_state['can_generate'] ) ) {
            ++$issuable;
        }
    }

    $certificate_attendance = sp_certificate_min_attendance();
    ?>
    <div  class="sp-results-ui">
        <header class="sp-results-page-header">
            <div class="sp-results-page-heading">
                <span class="sp-results-eyebrow">STUDENT PORTAL <span aria-hidden="true">/</span> ACADEMIC MANAGEMENT</span>
                <h1>Results &amp; certificates</h1>
                <p>Review student performance, publish final results, and issue certificates from one workspace.</p>
            </div>
            <div class="sp-results-header-note">
                <span class="sp-results-header-mark" aria-hidden="true">✓</span>
                <span><strong>Results workspace</strong><small>Marks, publication and certificates</small></span>
            </div>
        </header>

        <section class="sp-results-stats" aria-label="Results overview">
            <article class="sp-results-stat-card">
                <span class="sp-results-stat-icon sp-results-stat-icon-total" aria-hidden="true">♙</span>
                <div>
                    <span class="sp-results-stat-label">Enrolled students</span>
                    <strong><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></strong>
                    <small>In the selected class</small>
                </div>
            </article>
            <article class="sp-results-stat-card">
                <span class="sp-results-stat-icon sp-results-stat-icon-entered" aria-hidden="true">▤</span>
                <div>
                    <span class="sp-results-stat-label">Marks entered</span>
                    <strong><?php echo esc_html( number_format_i18n( $entered_count ) ); ?></strong>
                    <small><?php echo esc_html( number_format_i18n( $unpublished_count ) ); ?> awaiting publish</small>
                </div>
            </article>
            <article class="sp-results-stat-card">
                <span class="sp-results-stat-icon sp-results-stat-icon-published" aria-hidden="true">✓</span>
                <div>
                    <span class="sp-results-stat-label">Published</span>
                    <strong><?php echo esc_html( number_format_i18n( $published_count ) ); ?></strong>
                    <small>Visible to students</small>
                </div>
            </article>
            <article class="sp-results-stat-card">
                <span class="sp-results-stat-icon sp-results-stat-icon-certificates" aria-hidden="true">✧</span>
                <div>
                    <span class="sp-results-stat-label">Certificates ready</span>
                    <strong><?php echo esc_html( number_format_i18n( $issuable ) ); ?></strong>
                    <small><?php echo esc_html( number_format_i18n( $issued ) ); ?> already issued</small>
                </div>
            </article>
        </section>

        <section class="sp-results-filter-panel" aria-label="Select class">
            <div class="sp-results-filter-copy">
                <span class="sp-results-section-kicker">CLASS WORKSPACE</span>
                <strong>Choose a class</strong>
                <small>Open a class to review marks and certificate status.</small>
            </div>
            <form method="get" class="sp-results-filter-form">
                <input type="hidden" name="page" value="sp-results" />
                <label class="screen-reader-text" for="sp-results-class">Class</label>
                <select name="class" id="sp-results-class">
                    <?php foreach ( $classes as $sp_row ) : ?>
                        <option value="<?php echo esc_attr( (int) $sp_row->id ); ?>" <?php selected( (int) $class->id, (int) $sp_row->id ); ?>>
                            <?php echo esc_html( $sp_row->title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button button-primary">Show results <span aria-hidden="true">→</span></button>
            </form>
        </section>

        <?php if ( $cert_form ) : ?>
            <div class="sp-results-certificate-form">
                <?php sp_admin_render_certificate_form( $cert_form, (int) $class->id ); ?>
            </div>
        <?php endif; ?>

        <section class="sp-results-certificate-banner" aria-labelledby="sp-results-certificate-title">
            <div class="sp-results-certificate-banner-icon" aria-hidden="true">✧</div>
            <div class="sp-results-certificate-banner-copy">
                <span class="sp-results-section-kicker">CERTIFICATE CENTRE</span>
                <h2 id="sp-results-certificate-title">Issue certificates with confidence</h2>
                <p>A published passing result and at least <strong><?php echo esc_html( sp_format_number( $certificate_attendance ) ); ?>% class attendance</strong> are required. Student name, enrolment ID, grade, marks and dates are prefilled from the record.</p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-settings&tab=certificates' ) ); ?>">Configure certificate fields <span aria-hidden="true">↗</span></a>
            </div>
            <div class="sp-results-certificate-banner-action">
                <?php if ( $issuable > 0 ) : ?>
                    <form method="post" class="sp-results-bulk-issue">
                        <?php sp_admin_nonce_field( 'generate_certificates_bulk' ); ?>
                        <input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $class->id ); ?>" />
                        <button type="submit" class="button button-primary">Quick-issue <?php echo esc_html( number_format_i18n( $issuable ) ); ?> certificate<?php echo 1 === $issuable ? '' : 's'; ?></button>
                    </form>
                <?php else : ?>
                    <span class="sp-results-no-certificates"><?php echo esc_html( number_format_i18n( $issued ) ); ?> issued</span>
                <?php endif; ?>
                <small><?php echo esc_html( number_format_i18n( $issued ) ); ?> certificate<?php echo 1 === $issued ? '' : 's'; ?> already issued</small>
            </div>
        </section>

        <section class="sp-results-panel" aria-labelledby="sp-results-list-title">
            <div class="sp-results-panel-heading">
                <div>
                    <span class="sp-results-section-kicker">STUDENT PERFORMANCE</span>
                    <h2 id="sp-results-list-title"><?php echo esc_html( $class->title ); ?></h2>
                    <p>Review theory and practical marks, check publication status, and manage individual certificates.</p>
                </div>
                <div class="sp-results-heading-count" style="display: flex; flex-direction: row; align-items: center; gap: 0.5em;">
                    <strong><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></strong>
                    <span><?php echo esc_html( _n( 'Student', 'Students', count( $rows ), 'student-portal' ) ); ?></span>
                </div>
            </div>

            <?php if ( ! $rows ) : ?>
                <div class="sp-results-empty sp-results-empty-inline">
                    <span class="sp-results-empty-icon" aria-hidden="true">▤</span>
                    <strong>No students enrolled in this class yet</strong>
                    <p>Enrol students in this class or ask a teacher to enter their first marks.</p>
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-enrollments&class=' . (int) $class->id ) ); ?>">Open Enrollments</a>
                </div>
            <?php else : ?>
                <div class="sp-results-table-wrap">
                    <table class="widefat striped sp-results-table">
                        <thead>
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Theory</th>
                                <th scope="col">Practical</th>
                                <th scope="col">Percentage</th>
                                <th scope="col">Grade</th>
                                <th scope="col">Result</th>
                                <th scope="col">Status</th>
                                <th scope="col">Certificate</th>
                                <th scope="col"><span class="screen-reader-text">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $rows as $sp_row ) : ?>
                                <?php
                                $sp_initials = '';
                                $sp_name_parts = preg_split( '/\s+/', trim( (string) $sp_row->full_name ) );
                                if ( ! empty( $sp_name_parts[0] ) ) {
                                    $sp_initials .= function_exists( 'mb_substr' ) ? mb_substr( $sp_name_parts[0], 0, 1 ) : substr( $sp_name_parts[0], 0, 1 );
                                }
                                if ( ! empty( $sp_name_parts[1] ) ) {
                                    $sp_initials .= function_exists( 'mb_substr' ) ? mb_substr( $sp_name_parts[1], 0, 1 ) : substr( $sp_name_parts[1], 0, 1 );
                                }
                                $sp_initials = strtoupper( $sp_initials );

                                $sp_cert = $sp_row->enrollment_id
                                    ? sp_certificate_state_for_enrollment( (int) $sp_row->enrollment_id )
                                    : array( 'can_generate' => false, 'reason' => '', 'generated' => false, 'serial' => '' );
                                ?>
                                <tr>
                                    <td class="sp-results-student-cell">
                                        <span class="sp-results-avatar" aria-hidden="true"><?php echo esc_html( $sp_initials ? $sp_initials : 'ST' ); ?></span>
                                        <span class="sp-results-student-copy">
                                            <strong><?php echo esc_html( $sp_row->full_name ); ?></strong>
                                            <small><?php echo esc_html( $sp_row->enroll_id ? $sp_row->enroll_id : 'Enrolment ID unavailable' ); ?></small>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ( $sp_row->result_id ) : ?>
                                            <span class="sp-results-mark-value"><?php echo esc_html( sp_format_number( $sp_row->theory_obtained ) ); ?><i>/</i><?php echo esc_html( sp_format_number( $sp_row->theory_max ) ); ?></span>
                                        <?php else : ?>
                                            <span class="sp-results-dash">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $sp_row->result_id ) : ?>
                                            <span class="sp-results-mark-value"><?php echo esc_html( sp_format_number( $sp_row->practical_obtained ) ); ?><i>/</i><?php echo esc_html( sp_format_number( $sp_row->practical_max ) ); ?></span>
                                        <?php else : ?>
                                            <span class="sp-results-dash">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( null !== $sp_row->percentage && '' !== $sp_row->percentage ) : ?>
                                            <span class="sp-results-percentage"><?php echo esc_html( sp_format_number( $sp_row->percentage ) ); ?><small>%</small></span>
                                        <?php else : ?>
                                            <span class="sp-results-dash">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $sp_row->grade ) : ?>
                                            <span class="sp-results-grade"><?php echo esc_html( $sp_row->grade ); ?></span>
                                        <?php else : ?>
                                            <span class="sp-results-dash">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $sp_row->result_id ) : ?>
                                            <?php echo wp_kses_post( sp_result_badge( $sp_row->result ) ); ?>
                                        <?php else : ?>
                                            <span class="sp-results-status-pill sp-results-status-not-entered">Not entered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $sp_row->result_id ) : ?>
                                            <?php echo wp_kses_post( sp_admin_status_badge( $sp_row->finalised ? 'active' : 'pending' ) ); ?>
                                        <?php else : ?>
                                            <span class="sp-results-dash">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="sp-results-certificate-cell">
                                        <?php if ( ! empty( $sp_cert['generated'] ) ) : ?>
                                            <?php
                                            $sp_print_url = sp_certificate_vendor_url( (int) $sp_cert['certificate_id'] );
                                            $sp_view_url  = add_query_arg(
                                                array(
                                                    'class'     => (int) $class->id,
                                                    'cert_form' => (int) $sp_row->enrollment_id,
                                                ),
                                                admin_url( 'admin.php?page=sp-results' )
                                            );
                                            ?>
                                            <a class="button button-small" href="<?php echo esc_url( $sp_view_url ); ?>">View</a>
                                            <?php if ( ! is_wp_error( $sp_print_url ) ) : ?>
                                                <a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( $sp_print_url ); ?>">Print</a>
                                            <?php endif; ?>
                                        <?php elseif ( ! empty( $sp_cert['can_generate'] ) ) : ?>
                                            <a class="button button-small button-primary" href="<?php echo esc_url( add_query_arg( array( 'class' => (int) $class->id, 'cert_form' => (int) $sp_row->enrollment_id ), admin_url( 'admin.php?page=sp-results' ) ) ); ?>">Issue</a>
                                        <?php elseif ( ! empty( $sp_cert['reason'] ) ) : ?>
                                            <span class="sp-results-cert-blocked" title="<?php echo esc_attr( $sp_cert['reason'] ); ?>">Not eligible</span>
                                        <?php else : ?>
                                            <span class="sp-results-dash">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="sp-results-action-cell">
                                        <?php if ( $sp_row->result_id ) : ?>
                                            <form method="post" class="sp-results-publish-form">
                                                <?php sp_admin_nonce_field( 'finalise_result' ); ?>
                                                <input type="hidden" name="result_id" value="<?php echo esc_attr( (int) $sp_row->result_id ); ?>" />
                                                <input type="hidden" name="enrollment_id" value="<?php echo esc_attr( (int) $sp_row->enrollment_id ); ?>" />
                                                <input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $class->id ); ?>" />
                                                <button type="submit" class="button button-small <?php echo $sp_row->finalised ? 'sp-results-unpublish-button' : 'button-primary'; ?>">
                                                    <?php echo (int) $sp_row->finalised ? 'Unpublish' : 'Publish'; ?>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <span class="sp-results-action-hint">Awaiting marks</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <footer class="sp-results-panel-footer">
                <span><strong><?php echo esc_html( number_format_i18n( $entered_count ) ); ?></strong> of <strong><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></strong> students have marks recorded.</span>
                <span>Publishing makes results visible to students and emails them.</span>
            </footer>
        </section>

        <p class="sp-results-page-footnote">Certificates can be issued once a result is published, the grade is a pass, and class attendance reaches <?php echo esc_html( sp_format_number( $certificate_attendance ) ); ?>%. Adjust certificate requirements in <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-settings&tab=certificates' ) ); ?>">Settings → Certificates</a>.</p>
    </div>
    <?php
}
