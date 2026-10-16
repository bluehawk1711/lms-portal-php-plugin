<?php
/**
 * Admin dashboard.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Portal dashboard screen.
 */
function sp_admin_page_sp_dashboard() {
    global $wpdb;

    $counts = array(
        'students'    => sp_count_profiles(),
        'teachers'    => sp_count_teachers(),
        'classes'     => sp_count_classes( array( 'status' => 'active' ) ),
        'enrollments' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'enrollments' ) . " WHERE status = 'active'" ),
        'materials'   => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'materials' ) ),
        'sessions'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'attendance_sessions' ) ),
        'results'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'results' ) . ' WHERE finalised = 1' ),
        'pending_res' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'results' ) . ' WHERE finalised = 0' ),
        'failed_mail' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . sp_table( 'email_log' ) . " WHERE status = 'failed'" ),
    );

    $recent = $wpdb->get_results(
        'SELECT recipient, subject, status, created_at FROM ' . sp_table( 'email_log' ) . ' ORDER BY created_at DESC LIMIT 8'
    );

    $stat_cards = array(
        array(
            'key'     => 'students',
            'label'   => 'Students',
            'value'   => $counts['students'],
            'initial' => 'ST',
            'group'   => 'PEOPLE',
            'note'    => 'Registered student profiles',
            'class'   => '',
        ),
        array(
            'key'     => 'teachers',
            'label'   => 'Teachers',
            'value'   => $counts['teachers'],
            'initial' => 'TC',
            'group'   => 'PEOPLE',
            'note'    => 'Teacher accounts',
            'class'   => '',
        ),
        array(
            'key'     => 'classes',
            'label'   => 'Active classes',
            'value'   => $counts['classes'],
            'initial' => 'CL',
            'group'   => 'LEARNING',
            'note'    => 'Currently active classes',
            'class'   => 'is-good',
        ),
        array(
            'key'     => 'enrollments',
            'label'   => 'Enrolments',
            'value'   => $counts['enrollments'],
            'initial' => 'EN',
            'group'   => 'LEARNING',
            'note'    => 'Active student enrolments',
            'class'   => '',
        ),
        array(
            'key'     => 'sessions',
            'label'   => 'Attendance sessions',
            'value'   => $counts['sessions'],
            'initial' => 'AT',
            'group'   => 'ACTIVITY',
            'note'    => 'Sessions recorded',
            'class'   => '',
        ),
        array(
            'key'     => 'materials',
            'label'   => 'Materials',
            'value'   => $counts['materials'],
            'initial' => 'MT',
            'group'   => 'ACTIVITY',
            'note'    => 'Learning resources',
            'class'   => '',
        ),
        array(
            'key'     => 'results',
            'label'   => 'Published results',
            'value'   => $counts['results'],
            'initial' => 'RS',
            'group'   => 'ASSESSMENT',
            'note'    => 'Finalised results',
            'class'   => 'is-good',
        ),
        array(
            'key'     => 'pending_res',
            'label'   => 'Draft results',
            'value'   => $counts['pending_res'],
            'initial' => 'DR',
            'group'   => 'ASSESSMENT',
            'note'    => 'Awaiting finalisation',
            'class'   => $counts['pending_res'] > 0 ? 'is-warn' : 'is-muted',
        ),
    );
    ?>

    <div class="sp-dashboard">
        <header class="sp-dashboard-header">
            <div class="sp-dashboard-heading">
                <p class="sp-dashboard-eyebrow">STUDENT PORTAL / OVERVIEW</p>
                <h2>Dashboard overview</h2>
                <p>Track your portal at a glance and jump straight into the tasks that keep it running.</p>
            </div>
            <nav class="sp-dashboard-header-actions" aria-label="Dashboard actions">
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-teachers' ) ); ?>">Manage teachers</a>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-import' ) ); ?>">Import CSV</a>
            </nav>
        </header>

        <?php sp_admin_cert_notice(); ?>

        <section class="sp-dashboard-stats" aria-label="Portal statistics">
            <div class="sp-dashboard-section-heading">
                <div>
                    <h3>Portal at a glance</h3>
                    <p>Current totals across your student and teacher portal.</p>
                </div>
            </div>

            <div class="sp-admin-cards">
                <?php foreach ( $stat_cards as $sp_stat ) : ?>
                    <article class="sp-admin-card <?php echo esc_attr( $sp_stat['class'] ); ?>" aria-label="<?php echo esc_attr( $sp_stat['label'] . ': ' . number_format_i18n( $sp_stat['value'] ) ); ?>">
                        <span class="sp-admin-card-mark" aria-hidden="true"><?php echo esc_html( $sp_stat['initial'] ); ?></span>
                        <div class="sp-admin-card-content">
                            <span class="sp-admin-card-group"><?php echo esc_html( $sp_stat['group'] ); ?></span>
                            <span class="sp-admin-stat"><?php echo esc_html( number_format_i18n( $sp_stat['value'] ) ); ?></span>
                            <span class="sp-admin-card-label"><?php echo esc_html( $sp_stat['label'] ); ?></span>
                            <span class="sp-admin-card-note"><?php echo esc_html( $sp_stat['note'] ); ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ( $counts['failed_mail'] > 0 ) : ?>
            <div class="notice notice-error sp-dashboard-mail-alert">
                <p>
                    <strong><?php echo esc_html( number_format_i18n( $counts['failed_mail'] ) ); ?> portal email(s) failed to send.</strong>
                    The site mailer may not be configured. Install or check an SMTP plugin, then
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-email-log' ) ); ?>">review the email log</a>.
                </p>
            </div>
        <?php endif; ?>

        <div class="sp-dashboard-main-grid">
            <section class="sp-admin-panel sp-dashboard-panel sp-dashboard-getting-started">
                <div class="sp-dashboard-panel-heading">
                    <div>
                        <span class="sp-dashboard-panel-kicker">SETUP GUIDE</span>
                        <h3>Getting started</h3>
                        <p>Follow this workflow to prepare the portal for everyday use.</p>
                    </div>
                    <span class="sp-dashboard-panel-count" aria-label="7 setup steps">7 steps</span>
                </div>

                <ol class="sp-admin-steps">
                    <li><div><strong>Set grade thresholds</strong><span>Open <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-settings' ) ); ?>">Settings</a> and keep the grade scale aligned with the certificate grades A+, A, B and C.</span></div></li>
                    <li><div><strong>Add teachers</strong><span>Create accounts on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-teachers' ) ); ?>">Teachers</a> screen, or <a href="<?php echo esc_url( add_query_arg( 'tab', 'teachers', admin_url( 'admin.php?page=sp-import' ) ) ); ?>">import teachers from CSV</a>.</span></div></li>
                    <li><div><strong>Import student records</strong><span>Upload your existing roster and review the preview before confirming the import.</span></div></li>
                    <li><div><strong>Create classes</strong><span>Use the <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-classes' ) ); ?>">Classes</a> screen to set up classes and assign teachers.</span></div></li>
                    <li><div><strong>Enrol students</strong><span>Add students to the appropriate class from the <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-enrollments' ) ); ?>">Enrolments</a> screen.</span></div></li>
                    <li><div><strong>Run classes</strong><span>Teachers can take attendance, publish materials and record results from <code><?php echo esc_html( sp_portal_url( 'teacher' ) ); ?></code>.</span></div></li>
                    <li><div><strong>Finalise and sync results</strong><span>Once grades are finalised, sync them to the Certificate Manager and regenerate certificates there.</span></div></li>
                </ol>
            </section>

            <section class="sp-admin-panel sp-dashboard-panel sp-dashboard-recent-emails">
                <div class="sp-dashboard-panel-heading">
                    <div>
                        <span class="sp-dashboard-panel-kicker">DELIVERY ACTIVITY</span>
                        <h3>Recent emails</h3>
                        <p>The latest messages recorded by the portal mailer.</p>
                    </div>
                    <a class="sp-dashboard-text-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-email-log' ) ); ?>">View email log <span aria-hidden="true">&rarr;</span></a>
                </div>

                <?php if ( ! $recent ) : ?>
                    <div class="sp-dashboard-empty">
                        <span class="sp-dashboard-empty-mark" aria-hidden="true">@</span>
                        <strong>No emails recorded yet</strong>
                        <p>Portal email activity will appear here when messages are sent.</p>
                    </div>
                <?php else : ?>
                    <div class="sp-table-wrap sp-dashboard-email-table-wrap">
                        <table class="widefat striped sp-dashboard-email-table">
                            <thead>
                                <tr>
                                    <th scope="col">Recipient</th>
                                    <th scope="col">Subject</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent as $sp_row ) : ?>
                                    <?php $sp_email_timestamp = ! empty( $sp_row->created_at ) ? strtotime( $sp_row->created_at ) : false; ?>
                                    <tr>
                                        <td class="sp-dashboard-email-recipient"><?php echo esc_html( $sp_row->recipient ); ?></td>
                                        <td><?php echo esc_html( $sp_row->subject ); ?></td>
                                        <td><?php echo wp_kses_post( sp_admin_status_badge( $sp_row->status ) ); ?></td>
                                        <td>
                                            <?php if ( $sp_email_timestamp ) : ?>
                                                <time datetime="<?php echo esc_attr( gmdate( 'c', $sp_email_timestamp ) ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ), $sp_email_timestamp ) ); ?></time>
                                            <?php else : ?>
                                                <span class="sp-admin-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <section class="sp-dashboard-shortcuts" aria-labelledby="sp-dashboard-shortcuts-title">
            <div class="sp-dashboard-section-heading">
                <div>
                    <h3 id="sp-dashboard-shortcuts-title">Quick access</h3>
                    <p>Common admin screens, ready when you need them.</p>
                </div>
            </div>
            <div class="sp-dashboard-shortcut-grid">
                <a class="sp-dashboard-shortcut" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-classes' ) ); ?>">
                    <span class="sp-dashboard-shortcut-mark" aria-hidden="true">CL</span>
                    <span><strong>Classes</strong><small>Create classes and assign teachers</small></span>
                    <span class="sp-dashboard-shortcut-arrow" aria-hidden="true">&rarr;</span>
                </a>
                <a class="sp-dashboard-shortcut" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-enrollments' ) ); ?>">
                    <span class="sp-dashboard-shortcut-mark" aria-hidden="true">EN</span>
                    <span><strong>Enrolments</strong><small>Manage class rosters</small></span>
                    <span class="sp-dashboard-shortcut-arrow" aria-hidden="true">&rarr;</span>
                </a>
                <a class="sp-dashboard-shortcut" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-settings' ) ); ?>">
                    <span class="sp-dashboard-shortcut-mark" aria-hidden="true">SE</span>
                    <span><strong>Settings</strong><small>Configure portal preferences</small></span>
                    <span class="sp-dashboard-shortcut-arrow" aria-hidden="true">&rarr;</span>
                </a>
                <a class="sp-dashboard-shortcut" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-email-log' ) ); ?>">
                    <span class="sp-dashboard-shortcut-mark" aria-hidden="true">EM</span>
                    <span><strong>Email log</strong><small>Review delivery status</small></span>
                    <span class="sp-dashboard-shortcut-arrow" aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </section>
    </div>
    <?php
}
