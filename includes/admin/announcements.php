<?php
    /**
 * Announcement composition and management.
 *
 * Layout mirrors the Classes screen: header + stats, a compose panel on the
 * left and the running list on the right, so it shares one design language
 * with the rest of the plugin admin.
 *
 * @package StudentPortal
 */

    if (! defined('ABSPATH')) {
    exit;
    }

    /**
 * Announcements screen.
 */
    function sp_admin_page_sp_announcements()
    {
    global $wpdb;

    $sp_students = sp_get_profiles(['per_page' => 500]);
    $sp_rows     = $wpdb->get_results(
        'SELECT * FROM ' . sp_table('announcements') . ' ORDER BY created_at DESC, id DESC LIMIT 200' // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
    );

    $sp_total    = count($sp_rows);
    $sp_active   = 0;
    $sp_archived = 0;
    $sp_expired  = 0;

    /*
	 * A row can still be marked active in the table while both portals already
	 * hide it because its end date has passed, so those are counted apart from
	 * the ones students and teachers can actually see right now.
	 */
    foreach ($sp_rows as $sp_count_row) {
        if ('active' !== (string) $sp_count_row->status) {
            ++$sp_archived;
            continue;
        }

        if (function_exists('sp_announcement_is_expired') && sp_announcement_is_expired($sp_count_row)) {
            ++$sp_expired;
            continue;
        }

        ++$sp_active;
    }
    ?>
<div class="sp-annc-ui">
    <header class="sp-annc-page-header">
        <div class="sp-annc-page-heading">
            <span class="sp-annc-eyebrow">STUDENT PORTAL <span aria-hidden="true">/</span> COMMUNICATION</span>
            <h1>Announcements</h1>
            <p>Publish a full banner or a thin announcement bar to students and teachers — right inside their portal.
            </p>
        </div>
        <div class="sp-annc-header-note">
            <span class="sp-annc-header-mark" aria-hidden="true">✦</span>
            <span><strong>Direct channel</strong><small>Visible in both portals</small></span>
        </div>
    </header>

    <section class="sp-annc-stats" aria-label="Announcement overview">
        <article class="sp-annc-stat-card">
            <span class="sp-annc-stat-icon sp-annc-stat-icon-total" aria-hidden="true">✉</span>
            <div><span
                    class="sp-annc-stat-label">Total</span><strong><?php echo esc_html(number_format_i18n($sp_total)); ?></strong><small>Published
                    so far</small></div>
        </article>
        <article class="sp-annc-stat-card">
            <span class="sp-annc-stat-icon sp-annc-stat-icon-active" aria-hidden="true">✓</span>
            <div><span
                    class="sp-annc-stat-label">Live</span><strong><?php echo esc_html(number_format_i18n($sp_active)); ?></strong><small>Showing
                    in portals</small></div>
        </article>
        <article class="sp-annc-stat-card">
            <span class="sp-annc-stat-icon sp-annc-stat-icon-archived" aria-hidden="true">◱</span>
            <div><span
                    class="sp-annc-stat-label">Archived</span><strong><?php echo esc_html(number_format_i18n($sp_archived)); ?></strong><small>Hidden
                    right now</small></div>
        </article>
        <article class="sp-annc-stat-card">
            <span class="sp-annc-stat-icon sp-annc-stat-icon-expired" aria-hidden="true">⏱</span>
            <div><span
                    class="sp-annc-stat-label">Expired</span><strong><?php echo esc_html(number_format_i18n($sp_expired)); ?></strong><small>Past
                    their end date</small></div>
        </article>
    </section>

    <div class="sp-annc-layout">
        <section class="sp-annc-panel sp-annc-form-panel" aria-labelledby="sp-annc-form-title">
            <div class="sp-annc-panel-heading">
                <div>
                    <span class="sp-annc-section-kicker">GET STARTED</span>
                    <h2 id="sp-annc-form-title">Publish an announcement</h2>
                    <p>Write the message, choose who sees it and how it appears.</p>
                </div>
                <span class="sp-annc-heading-icon" aria-hidden="true">＋</span>
            </div>

            <form method="post" class="sp-annc-form">
                <?php sp_admin_nonce_field('save_announcement'); ?>

                <div class="sp-annc-field">
                    <label for="sp_ann_title">Title <span class="sp-annc-muted">(optional)</span></label>
                    <input type="text" id="sp_ann_title" name="title" maxlength="255"
                        placeholder="e.g. Diwali break schedule" />
                    <p class="description">A short heading shown above the message.</p>
                </div>

                <div class="sp-annc-field">
                    <label for="sp_ann_body">Message <span class="sp-required">*</span></label>
                    <textarea id="sp_ann_body" name="body" rows="4" required
                        placeholder="What should students or teachers know?"></textarea>
                </div>

                <div class="sp-annc-divider"></div>
                <div class="sp-annc-form-section-title"><span>01</span>
                    <div><strong>How it appears</strong><small>Pick the display style.</small></div>
                </div>

                <div class="sp-annc-radio-row">
                    <label class="sp-annc-radio-card">
                        <input type="radio" name="display" value="banner" checked />
                        <span><strong>Full banner</strong><small>A large card at the top of the page.</small></span>
                    </label>
                    <label class="sp-annc-radio-card">
                        <input type="radio" name="display" value="bar" />
                        <span><strong>Thin bar</strong><small>A compact strip above the content.</small></span>
                    </label>
                </div>

                <div class="sp-annc-divider"></div>
                <div class="sp-annc-form-section-title"><span>02</span>
                    <div><strong>Who sees it</strong><small>Target everyone or one student.</small></div>
                </div>

                <div class="sp-annc-field">
                    <label for="sp_ann_audience">Audience</label>
                    <select id="sp_ann_audience" name="audience">
                        <option value="all_students">All students</option>
                        <option value="one_student">One student</option>
                        <option value="teachers">Teachers</option>
                    </select>
                </div>

                <div class="sp-annc-field">
                    <label for="sp_ann_profile">Student</label>
                    <select id="sp_ann_profile" name="profile_id"
                        data-sp-search-select
                        data-sp-ss-placeholder="Search students by name or email…"
                        data-sp-ss-empty="No matching students">
                        <option value="0">— choose a student —</option>
                        <?php foreach ($sp_students as $sp_student): ?>
                        <option value="<?php echo esc_attr((int) $sp_student->id); ?>">
                            <?php echo esc_html($sp_student->full_name . ' — ' . $sp_student->email); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Only used when the audience is “One student”.</p>
                </div>

                <div class="sp-annc-field">
                    <label for="sp_ann_ends_at">End date <span class="sp-annc-muted">(optional)</span></label>
                    <input type="date" id="sp_ann_ends_at" name="ends_at" data-sp-date />
                    <p class="description">Leave empty to show it until you archive it. Set a date and the announcement
                        hides itself the day after.</p>
                </div>

                <div class="sp-annc-field">
                    <label for="sp_ann_send_email">
                        <input type="checkbox" id="sp_ann_send_email" name="send_email" value="1" />
                        <span>Send email notification</span>
                    </label>
                    <p class="description">When checked, the announcement is also emailed to all targeted recipients.
                    </p>
                </div>
                <div class="sp-annc-divider"></div>
                <div class="sp-annc-form-section-title"><span>03</span>
                    <div><strong>Preview</strong><small>See how it will appear in portals.</small></div>
                </div>
                <?php
                    /*
					 * Both blocks below copy includes/announcements.php verbatim —
					 * same classes, same order, same conditional title — so the
					 * preview cannot drift away from what students and teachers
					 * actually see. assets/css/announcements.css is loaded on this
					 * screen for exactly these blocks.
					 *
					 * The dismiss control is a <div> here, not the portal's
					 * <form>: a nested form is invalid HTML and the browser would
					 * close this compose form early, taking the Publish button
					 * with it.
					 */
                    ?>
                <div class="sp-annc-preview" id="sp_ann_preview">
                    <section class="sp-ann sp-ann-banner" id="sp_ann_preview_banner" role="region"
                        aria-label="Announcement">
                        <div class="sp-ann-banner-inner">
                            <p class="sp-ann-eyebrow">ANNOUNCEMENT</p>
                            <h3 class="sp-ann-title" id="sp_ann_preview_title"></h3>
                            <div class="sp-ann-text" id="sp_ann_preview_text"></div>
                        </div>
                        <div class="sp-ann-dismiss">
                            <button type="button" class="sp-ann-dismiss-btn" aria-label="Dismiss announcement"
                                title="Dismiss">
                                <span aria-hidden="true">&times;</span>
                                <span class="sp-ann-dismiss-text">Dismiss</span>
                            </button>
                        </div>
                    </section>

                    <section class="sp-ann sp-ann-bar" id="sp_ann_preview_bar" role="region" aria-label="Announcement">
                        <div class="sp-ann-bar-text">
                            <strong id="sp_ann_preview_bar_title"></strong>
                            <span id="sp_ann_preview_bar_body"></span>
                        </div>
                        <div class="sp-ann-dismiss">
                            <button type="button" class="sp-ann-dismiss-btn" aria-label="Dismiss announcement"
                                title="Dismiss">
                                <span aria-hidden="true">&times;</span>
                                <span class="sp-ann-dismiss-text">Dismiss</span>
                            </button>
                        </div>
                    </section>
                </div>
                <button type="submit" style="margin-top: 1rem; margin-bottom: 1rem;"
                    class="button button-primary sp-annc-submit">Publish
                    announcement
                    <span aria-hidden="true">→</span></button>
            </form>
        </section>

        <section class="sp-annc-panel sp-annc-list-panel" aria-labelledby="sp-annc-list-title">
            <div class="sp-annc-panel-heading">
                <div>
                    <span class="sp-annc-section-kicker">INBOX</span>
                    <h2 id="sp-annc-list-title">Announcements</h2>
                    <p>Archive hides a message again; students who dismissed it never see it twice.</p>
                </div>
                <span class="sp-annc-heading-icon" aria-hidden="true">▤</span>
            </div>

            <div class="sp-annc-table-wrap">
                <table class="widefat sp-annc-table">
                    <thead>
                        <tr>
                            <th scope="col">Message</th>
                            <th scope="col">Audience</th>
                            <th scope="col">Shown as</th>
                            <th scope="col">Status</th>
                            <th scope="col">Published</th>
                            <th scope="col"><span class="screen-reader-text">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (! $sp_rows): ?>
                        <tr>
                            <td colspan="6">
                                <div class="sp-annc-empty"><span aria-hidden="true">✉</span><strong>No announcements
                                        yet</strong>
                                    <p>Compose your first message using the form.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php foreach ($sp_rows as $sp_row): ?>
                        <?php
                            $sp_target = '';

                                if ('one_student' === $sp_row->audience) {
                                    $sp_profile = sp_get_profile_by_id((int) $sp_row->audience_profile_id);
                                    $sp_target  = $sp_profile ? $sp_profile->full_name : 'Student #' . (int) $sp_row->audience_profile_id;
                                }

                                /*
								 * An active row past its end date is already hidden in both
								 * portals, so it is reported as Expired rather than Active.
								 */
                                $sp_end_date = function_exists('sp_announcement_end_date') ? sp_announcement_end_date($sp_row) : '';
                                $sp_state    = ('active' === (string) $sp_row->status && function_exists('sp_announcement_is_expired') && sp_announcement_is_expired($sp_row))
                                    ? 'expired'
                                    : (string) $sp_row->status;
                            ?>
                        <tr>
                            <td class="sp-annc-title-cell">
                                <span class="sp-annc-avatar"
                                    aria-hidden="true"><?php echo esc_html(strtoupper(function_exists('mb_substr') ? mb_substr((string) ($sp_row->title ? $sp_row->title : $sp_row->body), 0, 1) : substr((string) ($sp_row->title ? $sp_row->title : $sp_row->body), 0, 1))); ?></span>
                                <span
                                    class="sp-annc-details"><strong><?php echo esc_html($sp_row->title ? $sp_row->title : wp_trim_words(wp_strip_all_tags($sp_row->body), 10)); ?></strong><small><?php echo esc_html(wp_trim_words(wp_strip_all_tags($sp_row->body), 18)); ?></small></span>
                            </td>
                            <td>
                                <?php
                                    switch ($sp_row->audience) {
                                            case 'teachers':
                                                echo '<span class="sp-annc-course-pill">Teachers</span>';
                                                break;
                                            case 'one_student':
                                                echo '<span class="sp-annc-course-pill">' . esc_html($sp_target) . '</span>';
                                                break;
                                            default:
                                                echo '<span class="sp-annc-course-pill">All students</span>';
                                        }
                                    ?>
                            </td>
                            <td><span
                                    class="sp-annc-number-pill"><?php echo 'banner' === $sp_row->display ? 'Banner' : 'Bar'; ?></span>
                            </td>
                            <td>
                                <span
                                    class="sp-annc-status sp-annc-status-<?php echo esc_attr(sanitize_html_class($sp_state)); ?>"><?php echo esc_html(ucfirst($sp_state)); ?></span>
                                <?php if ($sp_end_date): ?>
                                <small
                                    class="sp-annc-status-note"><?php echo esc_html('expired' === $sp_state ? 'Ended ' : 'Until '); ?><?php echo esc_html(date_i18n('j M Y', strtotime($sp_end_date))); ?></small>
                                <?php else: ?>
                                <small class="sp-annc-status-note">No end date</small>
                                <?php endif; ?>
                            </td>
                            <td><span
                                    class="sp-annc-muted"><?php echo esc_html(date_i18n(get_option('date_format') . ' H:i', strtotime($sp_row->created_at))); ?></span>
                            </td>
                            <td class="sp-annc-actions-cell">
                                <div class="sp-annc-row-actions">
                                    <form method="post" class="sp-annc-inline-form">
                                        <?php sp_admin_nonce_field('announcement_status'); ?>
                                        <input type="hidden" name="announcement_id"
                                            value="<?php echo esc_attr((int) $sp_row->id); ?>" />
                                        <button type="submit" class="button button-small sp-annc-approve">
                                            <?php echo 'active' === $sp_row->status ? 'Archive' : 'Restore'; ?>
                                        </button>
                                    </form>
                                    <form method="post" class="sp-annc-inline-form sp-annc-delete-form"
                                        onsubmit="return confirm('Delete this announcement?');">
                                        <?php sp_admin_nonce_field('delete_announcement'); ?>
                                        <input type="hidden" name="announcement_id"
                                            value="<?php echo esc_attr((int) $sp_row->id); ?>" />
                                        <button type="submit" class="button button-small sp-annc-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="sp-annc-table-footer">
                <span>Showing <strong><?php echo esc_html(number_format_i18n($sp_total)); ?></strong>
                    announcement<?php echo 1 === $sp_total ? '' : 's'; ?></span>
                <span class="sp-annc-muted">Dismissals are stored per user</span>
            </div>
        </section>
    </div>

    <footer class="sp-annc-page-footer">
        <span><span class="sp-annc-footer-dot" aria-hidden="true"></span> Announcements</span>
        <span>Banners and bars appear above the page content in both portals.</span>
    </footer>
</div>
<?php
    /*
 * Inline live-preview JS for the announcement compose form.
 *
 * Every rule below mirrors includes/announcements.php so the preview cannot
 * promise something the portal does not do: the banner prints no heading when
 * the title is empty, its body goes through wpautop() (blank line = new
 * paragraph) and the thin bar shows tags stripped plain text.
 */
    ?>
<script>
(function() {
    var titleInput = document.getElementById('sp_ann_title');
    var bodyInput = document.getElementById('sp_ann_body');
    var displayRadios = document.querySelectorAll('input[name="display"]');
    var previewBanner = document.getElementById('sp_ann_preview_banner');
    var previewBar = document.getElementById('sp_ann_preview_bar');
    var previewTitle = document.getElementById('sp_ann_preview_title');
    var previewText = document.getElementById('sp_ann_preview_text');
    var previewBarTitle = document.getElementById('sp_ann_preview_bar_title');
    var previewBarBody = document.getElementById('sp_ann_preview_bar_body');

    if (!previewBanner || !previewBar) {
        return;
    }

    function escapeHtml(value) {
        return value.replace(/[&<>"']/g, function(ch) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [ch];
        });
    }

    // wpautop(): a blank line starts a new paragraph, a single one is a break.
    function toParagraphs(value) {
        var blocks = value.split(/\n\s*\n/).filter(function(block) {
            return '' !== block.trim();
        });

        if (!blocks.length) {
            return '';
        }

        return blocks.map(function(block) {
            return '<p>' + escapeHtml(block.trim()).replace(/\n/g, '<br />') + '</p>';
        }).join('');
    }

    // wp_strip_all_tags(): the thin bar never shows markup.
    function stripTags(value) {
        var probe = document.createElement('div');

        probe.innerHTML = value;

        return (probe.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function renderPreview() {
        var title = titleInput ? titleInput.value.trim() : '';
        var body = bodyInput ? bodyInput.value : '';
        var mode = 'banner';

        displayRadios.forEach(function(radio) {
            if (radio.checked) {
                mode = radio.value;
            }
        });

        // Inline display is cleared rather than forced, so the portal
        // stylesheet keeps deciding how each block lays out.
        if ('banner' === mode) {
            previewBanner.style.display = '';
            previewBar.style.display = 'none';
            previewTitle.style.display = title ? '' : 'none';
            previewTitle.textContent = title;
            previewText.innerHTML = toParagraphs(body);
        } else {
            previewBanner.style.display = 'none';
            previewBar.style.display = '';
            previewBarTitle.style.display = title ? '' : 'none';
            previewBarTitle.textContent = title;
            previewBarBody.textContent = stripTags(body);
        }
    }

    function bind(el, event) {
        if (el) {
            el.addEventListener(event, renderPreview);
        }
    }

    bind(titleInput, 'input');
    bind(bodyInput, 'input');

    displayRadios.forEach(function(radio) {
        bind(radio, 'change');
    });

    renderPreview();
})();
</script>
<?php
}