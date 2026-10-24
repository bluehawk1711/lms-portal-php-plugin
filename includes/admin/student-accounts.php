<?php
    /**
 * Student account management screen.
 *
 * One screen owns the student account lifecycle: add/edit form, account list,
 * row actions, and inline enrolment.
 *
 * @package StudentPortal
 */

    if (! defined('ABSPATH')) {
    exit;
    }

    require_once SP_PATH . 'includes/admin/add-student.php';

    /**
 * Render the Student Accounts admin screen.
 *
 * @return void
 */
    function sp_admin_page_sp_student_accounts()
    {
    $page = isset($_GET['paged']) && is_scalar($_GET['paged'])
        ? max(1, absint(wp_unslash($_GET['paged'])))
        : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $search = isset($_GET['s']) && is_scalar($_GET['s'])
        ? sanitize_text_field(wp_unslash($_GET['s']))
        : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $per   = 25;
    $total = max(0, (int) sp_count_profiles($search));
    $pages = max(1, (int) ceil($total / $per));
    $page  = min($page, $pages);

    $rows = sp_get_profiles(
        [
            'search'   => $search,
            'per_page' => $per,
            'offset'   => ($page - 1) * $per,
        ]
    );

    $rows = is_array($rows) ? $rows : [];

    // Pending teacher-created classes are not available for admin enrolment.
    $sp_classes = array_values(
        array_filter(
            (array) sp_get_classes(['per_page' => 200]),
            function ($sp_class_row) {
                return ! isset($sp_class_row->status) || 'pending' !== (string) $sp_class_row->status;
            }
        )
    );

    $showing_start = $total > 0 ? (($page - 1) * $per) + 1 : 0;
    $showing_end   = min($page * $per, $total);
    ?>
<div class="sp-student-accounts" id="sp-student-accounts-region" data-sp-async-region>
    <?php
        /*
         * Icon sprite drawn once and referenced with <use>, so the rows do not
         * repeat the markup for every student. Symbols are used deliberately
         * instead of text glyphs: characters such as ✎ and ↗ fall back to the
         * colour emoji fonts on Windows and look out of place in this UI.
         */
        ?>
    <svg class="sp-icon-sprite" xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true"
        focusable="false">
        <symbol id="sp-i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M4.5 12.5 9.5 17.5 19.5 6.5" />
        </symbol>
        <symbol id="sp-i-ban" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="8.5" />
            <path d="m6 6 12 12" />
        </symbol>
        <symbol id="sp-i-pencil" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M17.5 3.5a2.4 2.4 0 0 1 3.4 3.4L7.6 20.2 3 21l.8-4.6L17.5 3.5Z" />
        </symbol>
        <symbol id="sp-i-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 3 10.5 13.5" />
            <path d="M21 3 14.4 21l-3.9-7.5L3 9.6 21 3Z" />
        </symbol>
        <symbol id="sp-i-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="m6 6 12 12M18 6 6 18" />
        </symbol>
        <symbol id="sp-i-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 5v14M5 12h14" />
        </symbol>
        <symbol id="sp-i-updown" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 4v16" />
            <path d="m8.5 7.5 3.5-3.5 3.5 3.5" />
            <path d="m8.5 16.5 3.5 3.5 3.5-3.5" />
        </symbol>
        <symbol id="sp-i-dots" viewBox="0 0 24 24" fill="currentColor" stroke="none">
            <circle cx="5.5" cy="12" r="1.8" />
            <circle cx="12" cy="12" r="1.8" />
            <circle cx="18.5" cy="12" r="1.8" />
        </symbol>
    </svg>
    <?php sp_admin_cert_notice(); ?>

    <header class="sp-student-page-header">
        <div class="sp-student-page-heading">
            <span class="sp-student-eyebrow">STUDENT PORTAL <span aria-hidden="true">/</span> MANAGEMENT</span>
            <h1>Student accounts</h1>
            <p>Manage student profiles, access, enrolments and account activity from one place.</p>
        </div>
        <div class="sp-student-header-note">
            <span class="sp-student-header-note-mark" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24"
                    focusable="false">
                    <use href="#sp-i-check"></use>
                </svg></span>
            <span><strong>Account centre</strong><small>Profiles and enrolments</small></span>
        </div>
    </header>

    <div class="sp-student-form-area">
        <?php sp_admin_render_student_form(); ?>
    </div>

    <section class="sp-admin-panel sp-student-list-panel" aria-labelledby="sp-student-list-title">
        <div class="sp-student-list-heading">
            <div>
                <span class="sp-student-section-kicker">DIRECTORY</span>
                <h2 id="sp-student-list-title">All students</h2>
                <p class="sp-student-section-description">Find a student, review their status, or take action on their
                    account.</p>
            </div>
            <span class="sp-student-total-pill">
                <span class="sp-student-total-dot" aria-hidden="true"></span>
                <?php echo esc_html(number_format_i18n($total)); ?> matching
                account<?php echo 1 === $total ? '' : 's'; ?>
            </span>
        </div>

        <div class="sp-student-metrics" aria-label="Account list summary">
            <div class="sp-student-metric">
                <span class="sp-student-metric-label">Matching accounts</span>
                <strong><?php echo esc_html(number_format_i18n($total)); ?></strong>
                <span
                    class="sp-student-metric-foot"><?php echo $search ? 'Based on your search' : 'Across all student profiles'; ?></span>
            </div>
            <div class="sp-student-metric">
                <span class="sp-student-metric-label">Showing now</span>
                <strong><?php echo esc_html(number_format_i18n(count($rows))); ?></strong>
                <span class="sp-student-metric-foot">
                    <?php
                        if ($total > 0) {
                                printf(
                                    /* translators: 1: first record number, 2: last record number. */
                                    esc_html__('Records %1$s–%2$s', 'student-portal'),
                                    esc_html(number_format_i18n($showing_start)),
                                    esc_html(number_format_i18n($showing_end))
                                );
                            } else {
                                esc_html_e('No records to display', 'student-portal');
                            }
                        ?>
                </span>
            </div>
            <div class="sp-student-metric">
                <span class="sp-student-metric-label">Current page</span>
                <strong><?php echo esc_html(number_format_i18n($page)); ?><span class="sp-student-metric-of">/
                        <?php echo esc_html(number_format_i18n($pages)); ?></span></strong>
                <span class="sp-student-metric-foot">Up to <?php echo esc_html(number_format_i18n($per)); ?>
                    accounts per page</span>
            </div>
        </div>

        <div class="sp-student-toolbar">
            <form method="get" class="sp-admin-search sp-student-search-form" role="search" data-sp-async>
                <input type="hidden" name="page" value="sp-student-accounts" />
                <label class="screen-reader-text" for="sp-student-search">Search students</label>
                <span class="sp-student-search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8" />
                        <path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </span>
                <input id="sp-student-search" type="search" name="s" value="<?php echo esc_attr($search); ?>"
                    placeholder="Search name, email or enrolment ID…" />
                <button type="submit" class="button button-primary sp-student-search-submit">Search students</button>
                <?php if ($search): ?>
                <a class="button sp-student-clear-search"
                    href="<?php echo esc_url(admin_url('admin.php?page=sp-student-accounts')); ?>">Clear</a>
                <?php endif; ?>
            </form>
            <span class="sp-student-page-size"><span aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24"
                        focusable="false">
                        <use href="#sp-i-updown"></use>
                    </svg></span> 25 per page</span>
        </div>

        <div class="sp-student-table-wrap">
            <table class="widefat striped sp-student-table">
                <thead>
                    <tr>
                        <th scope="col" class="sp-student-col-student">Student</th>
                        <th scope="col" class="sp-student-col-status">Account status</th>
                        <th scope="col" class="sp-student-col-login">Last sign-in</th>
                        <th scope="col" class="sp-student-col-idcard">ID card</th>
                        <th scope="col" class="sp-student-col-actions"><span
                                class="screen-reader-text">Actions</span>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (! $rows): ?>
                    <tr>
                        <td colspan="5" class="sp-student-empty-cell">
                            <div class="sp-student-empty-state">
                                <span class="sp-student-empty-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.6" />
                                        <path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.6"
                                            stroke-linecap="round" />
                                    </svg>
                                </span>
                                <strong><?php echo $search ? esc_html__('No matching students found', 'student-portal') : esc_html__('No student accounts yet', 'student-portal'); ?></strong>
                                <span><?php echo $search ? esc_html__('Try a different name, email address or enrolment ID.', 'student-portal') : esc_html__('Student accounts will appear here after they are created.', 'student-portal'); ?></span>
                                <?php if ($search): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=sp-student-accounts')); ?>">Clear
                                    search</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($rows as $sp_row): ?>
                    <?php
                        $profile_id = isset($sp_row->id) ? absint($sp_row->id) : 0;
                            $user_id    = isset($sp_row->user_id) ? absint($sp_row->user_id) : 0;
                            $full_name  = isset($sp_row->full_name) ? (string) $sp_row->full_name : '';
                            $email      = isset($sp_row->email) ? (string) $sp_row->email : '';
                            $status     = isset($sp_row->status) ? (string) $sp_row->status : '';
                            $last_login = isset($sp_row->last_login_at) ? (string) $sp_row->last_login_at : '';
                            $name_parts = preg_split('/\s+/u', trim($full_name));
                            $first_name = isset($name_parts[0]) ? $name_parts[0] : $full_name;
                            $last_name  = count($name_parts) > 1 ? end($name_parts) : '';
                            $first_char = function_exists('mb_substr') ? mb_substr($first_name, 0, 1, 'UTF-8') : substr($first_name, 0, 1);
                            $last_char  = $last_name ? (function_exists('mb_substr') ? mb_substr($last_name, 0, 1, 'UTF-8') : substr($last_name, 0, 1)) : '';
                            $initials   = function_exists('mb_strtoupper') ? mb_strtoupper($first_char . $last_char, 'UTF-8') : strtoupper($first_char . $last_char);
                            $enroll_id  = 'sp-enroll-panel-' . $profile_id;
                        ?>
                    <tr class="sp-student-data-row">
                        <td class="sp-student-identity-cell">
                            <div class="sp-student-identity">
                                <span class="sp-student-avatar"
                                    aria-hidden="true"><?php echo esc_html($initials ? $initials : 'ST'); ?></span>
                                <span class="sp-student-identity-copy">
                                    <strong><?php echo esc_html($full_name ? $full_name : __('Unnamed student', 'student-portal')); ?></strong>
                                    <?php if ($email): ?>
                                    <a class="sp-student-email"
                                        href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                    <?php else: ?>
                                    <span
                                        class="sp-student-email sp-student-no-email"><?php esc_html_e('No email address', 'student-portal'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </td>
                        <td class="sp-student-status-cell">
                            <?php echo wp_kses_post(sp_admin_status_badge($status)); ?>
                        </td>
                        <td class="sp-student-login-cell">
                            <?php if ($last_login && strtotime($last_login)): ?>
                            <span
                                class="sp-student-login-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($last_login))); ?></span>
                            <span
                                class="sp-student-login-caption"><?php esc_html_e('Last activity', 'student-portal'); ?></span>
                            <?php else: ?>
                            <span class="sp-student-never-login"><span
                                    aria-hidden="true"></span><?php esc_html_e('Never signed in', 'student-portal'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="sp-student-idcard-cell">
                            <?php if (! empty($sp_row->idcard_generated_at)): ?>
                            <span class="sp-student-idcard sp-student-idcard-done">Generated</span>
                            <span
                                class="sp-student-idcard-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($sp_row->idcard_generated_at))); ?></span>
                            <?php else: ?>
                            <span class="sp-student-idcard">Not generated</span>
                            <?php endif; ?>
                        </td>
                        <td class="sp-student-actions-cell">
                            <div class="sp-admin-actions sp-student-row-actions">
                                <button type="button" class="button sp-student-enroll-button"
                                    data-sp-enroll-toggle="<?php echo esc_attr($profile_id); ?>"
                                    aria-controls="<?php echo esc_attr($enroll_id); ?>" aria-expanded="false">
                                    <span class="sp-student-enroll-plus" aria-hidden="true">+</span> Enroll
                                </button>

                                <div class="sp-kebab">
                                    <button type="button" class="button sp-kebab-btn" data-sp-kebab-btn
                                        aria-haspopup="true" aria-expanded="false"
                                        aria-controls="sp-student-menu-<?php echo esc_attr($profile_id); ?>"
                                        aria-label="<?php echo esc_attr(sprintf(__('More actions for %s', 'student-portal'), $full_name)); ?>">
                                        <span aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24"
                                                focusable="false">
                                                <use href="#sp-i-dots"></use>
                                            </svg></span>
                                    </button>
                                    <div class="sp-kebab-menu"
                                        id="sp-student-menu-<?php echo esc_attr($profile_id); ?>" hidden>
                                        <a class="sp-kebab-item"
                                            href="<?php echo esc_url(add_query_arg(['page' => 'sp-student-accounts', 'edit' => $profile_id], admin_url('admin.php'))); ?>">
                                            <span class="sp-student-menu-icon" aria-hidden="true"><svg width="15"
                                                    height="15" viewBox="0 0 24 24" focusable="false">
                                                    <use href="#sp-i-pencil"></use>
                                                </svg></span> Edit profile
                                        </a>
                                        <form method="post" class="sp-kebab-item-form" data-sp-async>
                                            <?php sp_admin_nonce_field('resend_credentials'); ?>
                                            <input type="hidden" name="user_id"
                                                value="<?php echo esc_attr($user_id); ?>" />
                                            <button type="submit" class="sp-kebab-item">
                                                <span class="sp-student-menu-icon" aria-hidden="true"><svg width="15"
                                                        height="15" viewBox="0 0 24 24" focusable="false">
                                                        <use href="#sp-i-send"></use>
                                                    </svg></span> Resend credentials
                                            </button>
                                        </form>
                                        <form method="post" class="sp-kebab-item-form" data-sp-async>
                                            <?php sp_admin_nonce_field('toggle_status'); ?>
                                            <input type="hidden" name="record" value="student" />
                                            <input type="hidden" name="record_id"
                                                value="<?php echo esc_attr($profile_id); ?>" />
                                            <input type="hidden" name="status"
                                                value="<?php echo esc_attr('active' === $status ? 'disabled' : 'active'); ?>" />
                                            <button type="submit" class="sp-kebab-item">
                                                <span class="sp-student-menu-icon" aria-hidden="true"><svg width="15"
                                                        height="15" viewBox="0 0 24 24" focusable="false">
                                                        <use
                                                            href="#sp-i-<?php echo 'active' === $status ? 'ban' : 'check'; ?>">
                                                        </use>
                                                    </svg></span>
                                                <?php echo 'active' === $status ? esc_html__('Disable account', 'student-portal') : esc_html__('Enable account', 'student-portal'); ?>
                                            </button>
                                        </form>

                                        <div class="sp-kebab-divider" aria-hidden="true"></div>
                                        <form method="post" class="sp-kebab-item-form sp-kebab-delete-form"
                                            data-sp-async
                                            onsubmit="return confirm('<?php echo esc_js(__('Delete this student completely — profile, enrolments, results, attendance marks, exam attempts and their login? This cannot be undone.', 'student-portal')); ?>');">
                                            <?php sp_admin_nonce_field('delete_student'); ?>
                                            <input type="hidden" name="profile_id"
                                                value="<?php echo esc_attr($profile_id); ?>" />
                                            <button type="submit" class="sp-kebab-item sp-kebab-danger">
                                                <span class="sp-student-menu-icon" aria-hidden="true"><svg width="15"
                                                        height="15" viewBox="0 0 24 24" focusable="false">
                                                        <use href="#sp-i-close"></use>
                                                    </svg></span> Delete student
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr class="sp-student-enroll-row" data-sp-enroll-row="<?php echo esc_attr($profile_id); ?>"
                        hidden>
                        <td colspan="5">
                            <div class="sp-student-enroll-panel" id="<?php echo esc_attr($enroll_id); ?>">
                                <div class="sp-student-enroll-panel-heading">
                                    <span class="sp-student-enroll-panel-icon" aria-hidden="true"><svg width="17"
                                            height="17" viewBox="0 0 24 24" focusable="false">
                                            <use href="#sp-i-plus"></use>
                                        </svg></span>
                                    <span><strong>Enroll
                                            <?php echo esc_html($full_name ? $full_name : __('student', 'student-portal')); ?></strong><small>Assign
                                            this student to a class and set their admission details.</small></span>
                                </div>
                                <?php if ($sp_classes): ?>
                                <form method="post" class="sp-inline-enroll" data-sp-async>
                                    <?php sp_admin_nonce_field('enroll_student'); ?>
                                    <input type="hidden" name="profile_id"
                                        value="<?php echo esc_attr($profile_id); ?>" />

                                    <label class="sp-enroll-class-field">
                                        <span>Class <em>*</em></span>
                                        <select name="class_id" required>
                                            <option value="">Choose a class…</option>
                                            <?php foreach ($sp_classes as $sp_pick): ?>
                                            <?php
                                                $class_id    = isset($sp_pick->id) ? absint($sp_pick->id) : 0;
                                                    $class_title = isset($sp_pick->title) ? (string) $sp_pick->title : '';
                                                    $class_code  = isset($sp_pick->code) ? (string) $sp_pick->code : '';
                                                ?>
                                            <option value="<?php echo esc_attr($class_id); ?>">
                                                <?php echo esc_html($class_title . ($class_code ? ' (' . $class_code . ')' : '')); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>

                                    <label>
                                        <span>Enrolment ID <small>Optional</small></span>
                                        <input type="text" name="enroll_id[<?php echo esc_attr($profile_id); ?>]"
                                            placeholder="Auto-generate if blank" />
                                    </label>

                                    <label>
                                        <span>Admission date <small>Optional</small></span>
                                        <input type="date"
                                            name="admission_date[<?php echo esc_attr($profile_id); ?>]" />
                                    </label>

                                    <button type="submit" class="button button-primary sp-inline-enroll-submit">Confirm
                                        enrolment <span aria-hidden="true">→</span></button>
                                </form>
                                <p class="sp-student-enroll-help">Leave the enrolment ID blank to automatically generate
                                    the next ID for the selected class.</p>
                                <?php else: ?>
                                <div class="sp-student-no-classes">
                                    <span><?php esc_html_e('There are no available classes to enrol this student into yet.', 'student-portal'); ?></span>
                                    <a class="button"
                                        href="<?php echo esc_url(admin_url('admin.php?page=sp-classes')); ?>">Go to
                                        Classes <span aria-hidden="true">→</span></a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <footer class="sp-student-list-footer">
            <span class="sp-student-results-caption">
                <?php if ($total > 0): ?>
                Showing
                <strong><?php echo esc_html(number_format_i18n($showing_start)); ?>–<?php echo esc_html(number_format_i18n($showing_end)); ?></strong>
                of <strong><?php echo esc_html(number_format_i18n($total)); ?></strong> accounts
                <?php else: ?>
                No accounts to show
                <?php endif; ?>
            </span>

            <?php if ($pages > 1): ?>
            <nav class="sp-student-pagination" aria-label="Student account pages">
                <?php if ($page > 1): ?>
                <a class="sp-student-page-link sp-student-page-prev"
                    href="<?php echo esc_url(add_query_arg(['page' => 'sp-student-accounts', 'paged' => $page - 1, 's' => $search], admin_url('admin.php'))); ?>"><span
                        aria-hidden="true">←</span> Previous</a>
                <?php endif; ?>

                <?php
                    $start_page = max(1, $page - 2);
                        $end_page   = min($pages, $page + 2);

                        if ($start_page > 1):
                    ?>
                <a class="sp-student-page-link"
                    href="<?php echo esc_url(add_query_arg(['page' => 'sp-student-accounts', 'paged' => 1, 's' => $search], admin_url('admin.php'))); ?>">1</a>
                <?php if ($start_page > 2): ?><span class="sp-student-page-ellipsis"
                    aria-hidden="true">…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a class="sp-student-page-link<?php echo $i === $page ? ' is-current' : ''; ?>"
                    href="<?php echo esc_url(add_query_arg(['page' => 'sp-student-accounts', 'paged' => $i, 's' => $search], admin_url('admin.php'))); ?>"
                    <?php echo $i === $page ? 'aria-current="page"' : ''; ?>><?php echo esc_html($i); ?></a>
                <?php endfor; ?>

                <?php if ($end_page < $pages): ?>
                <?php if ($end_page < $pages - 1): ?><span class="sp-student-page-ellipsis"
                    aria-hidden="true">…</span><?php endif; ?>
                <a class="sp-student-page-link"
                    href="<?php echo esc_url(add_query_arg(['page' => 'sp-student-accounts', 'paged' => $pages, 's' => $search], admin_url('admin.php'))); ?>"><?php echo esc_html($pages); ?></a>
                <?php endif; ?>

                <?php if ($page < $pages): ?>
                <a class="sp-student-page-link sp-student-page-next"
                    href="<?php echo esc_url(add_query_arg(['page' => 'sp-student-accounts', 'paged' => $page + 1, 's' => $search], admin_url('admin.php'))); ?>">Next
                    <span aria-hidden="true">→</span></a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </footer>
    </section>
</div>

<script>
(function() {
    'use strict';

    function closeMenus(exceptButton) {
        document.querySelectorAll('[data-sp-kebab-btn]').forEach(function(button) {
            if (button === exceptButton) {
                return;
            }
            button.setAttribute('aria-expanded', 'false');
            var menu = button.nextElementSibling;
            if (menu) {
                menu.hidden = true;
            }
        });
    }

    /*
     * The table wrapper scrolls sideways, so an absolutely positioned menu
     * inside it would be cut off by that scroll box — and the menu's own
     * height would push a stray scrollbar onto the wrapper. The menu is
     * therefore fixed to the viewport and anchored to its button's live
     * rectangle, flipping upward when the button sits near the bottom.
     */
    function placeMenu(button, menu) {
        menu.style.top = '0px';
        menu.style.left = '0px';

        var rect = button.getBoundingClientRect();
        var width = menu.offsetWidth;
        var height = menu.offsetHeight;
        var edge = 8;
        var left = Math.round(rect.right - width);
        var top = Math.round(rect.bottom + 7);

        if (left < edge) {
            left = edge;
        } else if (left + width > window.innerWidth - edge) {
            left = window.innerWidth - width - edge;
        }

        if (top + height > window.innerHeight - edge && rect.top - height - 7 >= edge) {
            top = Math.round(rect.top - height - 7);
        }

        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    }

    // A fixed menu would otherwise float away from its row while scrolling.
    window.addEventListener('scroll', function() {
        closeMenus(null);
    }, true);
    window.addEventListener('resize', function() {
        closeMenus(null);
    });

    function closeEnrollmentRows(exceptRow) {
        document.querySelectorAll('[data-sp-enroll-row]').forEach(function(row) {
            if (row === exceptRow) {
                return;
            }
            row.hidden = true;
            var toggle = document.querySelector('[data-sp-enroll-toggle="' + row.getAttribute(
                'data-sp-enroll-row') + '"]');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('click', function(event) {
        var target = event.target && event.target.closest ? event.target : null;
        if (!target) {
            return;
        }

        var kebabButton = target.closest('[data-sp-kebab-btn]');
        if (kebabButton) {
            var menu = kebabButton.nextElementSibling;
            var wasOpen = 'true' === kebabButton.getAttribute('aria-expanded');
            closeMenus(kebabButton);
            kebabButton.setAttribute('aria-expanded', wasOpen ? 'false' : 'true');
            if (menu) {
                menu.hidden = wasOpen;
                if (!wasOpen) {
                    placeMenu(kebabButton, menu);
                }
            }
            return;
        }

        if (!target.closest('.sp-kebab')) {
            closeMenus(null);
        }

        var enrollToggle = target.closest('[data-sp-enroll-toggle]');
        if (enrollToggle) {
            var row = document.querySelector('[data-sp-enroll-row="' + enrollToggle.getAttribute(
                'data-sp-enroll-toggle') + '"]');
            if (!row) {
                return;
            }

            var shouldOpen = row.hidden;
            closeEnrollmentRows(row);
            row.hidden = !shouldOpen;
            enrollToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

            if (shouldOpen) {
                var select = row.querySelector('select');
                if (select) {
                    select.focus();
                }
            }
        }
    });

    document.addEventListener('keydown', function(event) {
        if ('Escape' !== event.key) {
            return;
        }
        closeMenus(null);
        closeEnrollmentRows(null);
    });
}());
</script>
<?php
}