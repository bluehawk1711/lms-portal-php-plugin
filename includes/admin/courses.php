<?php
/**
 * Courses administration.
 *
 * Courses are portal-owned from schema 2.0: this screen is the only place they
 * are created, renamed or archived. A course's code is what the Certificate
 * Manager stamps into a certificate id, so duplicates are rejected here rather
 * than discovered on a certificate.
 *
 * Premium UI refresh: page header with live counters, segmented status control,
 * custom field rows, refined course table, card-based course outline with icon
 * actions, and a styled CSV import zone. Field names, nonce actions and hidden
 * inputs are unchanged, so the existing request handlers keep working as-is.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Courses screen.
 */
function sp_admin_page_sp_courses() {
    $edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $editing = $edit_id ? sp_get_course( $edit_id ) : null;
    $courses = sp_get_courses();

    // Header counters — derived from the list already in memory, no extra queries.
    $sp_total    = count( $courses );
    $sp_active   = 0;
    $sp_archived = 0;
    foreach ( $courses as $sp_row ) {
        if ( 'archived' === $sp_row->status ) {
            $sp_archived++;
        } else {
            $sp_active++;
        }
    }

    $sp_status = $editing ? ( 'archived' === $editing->status ? 'archived' : 'active' ) : 'active';
    ?>
    <div class="sp-scope sp-courses-ui sp-page-courses">
        <header class="sp-courses-page-header">
            <div class="sp-courses-page-heading">
                <span class="sp-courses-eyebrow">STUDENT PORTAL <span aria-hidden="true">/</span> COURSE CATALOGUE</span>
                <h1>Courses</h1>
                <p>Portal-owned catalogue: create courses here, archive them when they retire, and give each one the code that Certificate Manager stamps onto every certificate it issues.</p>
            </div>
            <div class="sp-courses-header-note">
                <span class="sp-courses-header-mark" aria-hidden="true">▤</span>
                <span>
                    <strong>Course catalogue</strong>
                    <small>Codes stamped on certificates</small>
                </span>
            </div>
        </header>

        <section class="sp-courses-stats" aria-label="Course overview">
            <article class="sp-courses-stat-card">
                <span class="sp-courses-stat-icon sp-courses-stat-icon-total" aria-hidden="true">▦</span>
                <div>
                    <span class="sp-courses-stat-label">Total courses</span>
                    <strong><?php echo esc_html( number_format_i18n( $sp_total ) ); ?></strong>
                    <small>In catalogue</small>
                </div>
            </article>
            <article class="sp-courses-stat-card">
                <span class="sp-courses-stat-icon sp-courses-stat-icon-active" aria-hidden="true">✓</span>
                <div>
                    <span class="sp-courses-stat-label">Active</span>
                    <strong><?php echo esc_html( number_format_i18n( $sp_active ) ); ?></strong>
                    <small>Open for enrolment</small>
                </div>
            </article>
            <article class="sp-courses-stat-card">
                <span class="sp-courses-stat-icon sp-courses-stat-icon-archived" aria-hidden="true">▤</span>
                <div>
                    <span class="sp-courses-stat-label">Archived</span>
                    <strong><?php echo esc_html( number_format_i18n( $sp_archived ) ); ?></strong>
                    <small>Retired</small>
                </div>
            </article>
        </section>

        <div class="sp-admin-grid">

            <div class="sp-admin-panel sp-panel-form<?php echo $editing ? ' is-editing' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal class name. ?>">
                <div class="sp-panel-head">
                    <h2>
                        <span class="dashicons dashicons-<?php echo $editing ? 'edit-page' : 'plus-alt2'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal icon names. ?>" aria-hidden="true"></span>
                        <?php echo $editing ? 'Edit course' : 'Add a course'; ?>
                    </h2>
                    <?php if ( $editing ) : ?>
                        <span class="sp-chip sp-chip-accent">
                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                            <?php echo esc_html( $editing->name ); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <form method="post" class="sp-form">
                    <?php sp_admin_nonce_field( 'save_course' ); ?>
                    <?php if ( $editing ) : ?>
                        <input type="hidden" name="course_id" value="<?php echo esc_attr( (int) $editing->id ); ?>" />
                    <?php endif; ?>

                    <div class="sp-field">
                        <label class="sp-field-label" for="sp_c_name">
                            Name <span class="sp-required" aria-hidden="true">*</span>
                        </label>
                        <div class="sp-field-control">
                            <input type="text" id="sp_c_name" name="name" required class="sp-input" autocomplete="off"
                                placeholder="e.g. Foundations of Yoga"
                                value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>" />
                            <p class="sp-hint">Shown wherever the course appears — class forms, certificates and student records.</p>
                        </div>
                    </div>

                    <div class="sp-field">
                        <label class="sp-field-label" for="sp_c_code">Code</label>
                        <div class="sp-field-control">
                            <input type="text" id="sp_c_code" name="code" class="sp-input sp-input-code" autocomplete="off"
                                spellcheck="false" maxlength="32" placeholder="YOGA101"
                                value="<?php echo esc_attr( $editing && $editing->code ? $editing->code : '' ); ?>" />
                            <p class="sp-hint">
                                Letters and digits, uppercased — for example <code>YOGA101</code>. It becomes part of every
                                certificate id issued for this course. Leave blank to let the certificate fall back to <code>GEN</code>.
                            </p>
                        </div>
                    </div>

                    <div class="sp-field">
                        <label class="sp-field-label" for="sp_c_duration">Duration</label>
                        <div class="sp-field-control">
                            <input type="text" id="sp_c_duration" name="duration" class="sp-input" placeholder="e.g. 12 weeks"
                                value="<?php echo esc_attr( $editing && $editing->duration ? $editing->duration : '' ); ?>" />
                        </div>
                    </div>

                    <div class="sp-field">
                        <span class="sp-field-label" id="sp_c_status_label">Status</span>
                        <div class="sp-field-control">
                            <div class="sp-segmented" role="radiogroup" aria-labelledby="sp_c_status_label">
                                <label class="sp-segment sp-segment-active">
                                    <input type="radio" name="status" value="active" <?php checked( $sp_status, 'active' ); ?> />
                                    <span class="sp-segment-body">
                                        <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                        Active
                                    </span>
                                </label>
                                <label class="sp-segment sp-segment-archived">
                                    <input type="radio" name="status" value="archived" <?php checked( $sp_status, 'archived' ); ?> />
                                    <span class="sp-segment-body">
                                        <span class="dashicons dashicons-archive" aria-hidden="true"></span>
                                        Archived
                                    </span>
                                </label>
                            </div>
                            <p class="sp-hint">Archived courses stop appearing in the class form but are kept, along with their certificates.</p>
                        </div>
                    </div>

                    <div class="sp-field">
                        <label class="sp-field-label" for="sp_c_desc">Description</label>
                        <div class="sp-field-control">
                            <textarea id="sp_c_desc" name="description" rows="4" class="sp-input sp-input-textarea"
                                placeholder="What the course covers, who it suits, anything students should know…"><?php echo esc_textarea( $editing && $editing->description ? $editing->description : '' ); ?></textarea>
                        </div>
                    </div>

                    <div class="sp-form-actions">
                        <?php submit_button( $editing ? 'Save course' : 'Add course', 'primary', 'submit', true ); ?>
                        <?php if ( $editing ) : ?>
                            <a class="sp-cancel-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-courses' ) ); ?>">Cancel editing</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="sp-admin-panel sp-panel-list">
                <div class="sp-panel-head">
                    <h2>
                        <span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
                        All courses
                    </h2>
                    <span class="sp-panel-meta">
                        <?php
                        printf(
                            /* translators: %s: number of courses. */
                            esc_html( _n( '%s course', '%s courses', $sp_total ) ),
                            esc_html( number_format_i18n( $sp_total ) )
                        );
                        ?>
                    </span>
                </div>

                <?php if ( ! $courses ) : ?>
                    <div class="sp-empty">
                        <div class="sp-empty-icon"><span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span></div>
                        <strong>No courses yet.</strong>
                        <p>Add one on the left — classes can then be taught from it, and its code is stamped onto the certificates they earn.</p>
                    </div>
                <?php else : ?>
                    <div class="sp-table-wrap">
                        <table class="sp-table">
                            <thead>
                                <tr>
                                    <th scope="col">Course</th>
                                    <th scope="col">Code</th>
                                    <th scope="col">Duration</th>
                                    <th scope="col" class="sp-col-num">Classes</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="sp-col-actions"><span class="screen-reader-text">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $courses as $sp_row ) : ?>
                                    <?php $sp_used = sp_count_classes_for_course( (int) $sp_row->id ); ?>
                                    <tr>
                                        <td class="sp-col-course">
                                            <strong class="sp-course-name"><?php echo esc_html( $sp_row->name ); ?></strong>
                                            <?php if ( $sp_row->description ) : ?>
                                                <div class="sp-admin-muted"><?php echo esc_html( wp_trim_words( $sp_row->description, 16 ) ); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="sp-col-code">
                                            <?php if ( $sp_row->code ) : ?>
                                                <code class="sp-chip-code"><?php echo esc_html( $sp_row->code ); ?></code>
                                            <?php else : ?>
                                                <span class="sp-admin-muted" title="No code set — certificate ids fall back to GEN">GEN</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="sp-col-duration">
                                            <?php if ( $sp_row->duration ) : ?>
                                                <span class="sp-meta"><span class="dashicons dashicons-clock" aria-hidden="true"></span><?php echo esc_html( $sp_row->duration ); ?></span>
                                            <?php else : ?>
                                                <span class="sp-admin-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="sp-col-num">
                                            <span class="sp-count-pill" title="Classes teaching this course"><?php echo esc_html( number_format_i18n( $sp_used ) ); ?></span>
                                        </td>
                                        <td class="sp-col-status"><?php echo wp_kses_post( sp_admin_status_badge( $sp_row->status ) ); ?></td>
                                        <td class="sp-col-actions">
                                            <span class="sp-admin-actions">
                                                <a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit', (int) $sp_row->id, admin_url( 'admin.php?page=sp-courses' ) ) ); ?>">
                                                    <span class="dashicons dashicons-edit" aria-hidden="true"></span> Edit
                                                </a>
                                                <form method="post" class="sp-admin-inline">
                                                    <?php sp_admin_nonce_field( 'delete_course' ); ?>
                                                    <input type="hidden" name="course_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
                                                    <button type="submit" class="button button-small sp-btn-danger"
                                                        <?php echo $sp_used ? 'disabled title="A class still teaches this course — archive it instead"' : 'title="Delete this course"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal attribute strings. ?>>
                                                        <span class="dashicons dashicons-trash" aria-hidden="true"></span> Delete
                                                    </button>
                                                </form>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( $editing ) :
            $sp_items   = sp_get_curriculum( (int) $editing->id );
            $sp_item_id = isset( $_GET['curriculum'] ) ? (int) $_GET['curriculum'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sub-editor selection.
            $sp_item    = $sp_item_id ? sp_get_curriculum_item( $sp_item_id ) : null;

            // Never render an item that belongs to a different course's outline.
            if ( $sp_item && (int) $sp_item->course_id !== (int) $editing->id ) {
                $sp_item = null;
            }

            $sp_topics = count( $sp_items );
            ?>
            <section class="sp-admin-panel sp-panel-outline">
                <div class="sp-panel-head">
                    <h2>
                        <span class="dashicons dashicons-editor-ol" aria-hidden="true"></span>
                        Course outline
                    </h2>
                    <span class="sp-panel-meta">
                        <?php
                        printf(
                            /* translators: %s: number of topics. */
                            esc_html( _n( '%s topic', '%s topics', $sp_topics ) ),
                            esc_html( number_format_i18n( $sp_topics ) )
                        );
                        ?>
                    </span>
                </div>

                <p class="sp-admin-intro">
                    The ordered list of topics this course covers. Every class teaching the course shows the same
                    outline, and each class ticks topics off as it covers them.
                </p>

                <div class="sp-outline-editor">
                    <div class="sp-outline-editor-head">
                        <h3>
                            <span class="dashicons dashicons-<?php echo $sp_item ? 'edit' : 'plus'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal icon name. ?>" aria-hidden="true"></span>
                            <?php echo $sp_item ? 'Edit topic' : 'Add a topic'; ?>
                        </h3>
                        <?php if ( $sp_item ) : ?>
                            <span class="sp-chip sp-chip-accent">Editing</span>
                        <?php endif; ?>
                    </div>

                    <form method="post">
                        <?php sp_admin_nonce_field( 'save_curriculum' ); ?>
                        <input type="hidden" name="course_id" value="<?php echo esc_attr( (int) $editing->id ); ?>" />
                        <?php if ( $sp_item ) : ?>
                            <input type="hidden" name="curriculum_id" value="<?php echo esc_attr( (int) $sp_item->id ); ?>" />
                        <?php endif; ?>

                        <div class="sp-field">
                            <label class="sp-field-label" for="sp_ci_title">
                                Topic <span class="sp-required" aria-hidden="true">*</span>
                            </label>
                            <div class="sp-field-control">
                                <input type="text" id="sp_ci_title" name="title" required class="sp-input" autocomplete="off"
                                    placeholder="e.g. Sun salutations"
                                    value="<?php echo esc_attr( $sp_item ? $sp_item->title : '' ); ?>" />
                            </div>
                        </div>

                        <div class="sp-field">
                            <label class="sp-field-label" for="sp_ci_detail">Detail</label>
                            <div class="sp-field-control">
                                <textarea id="sp_ci_detail" name="detail" rows="3" class="sp-input sp-input-textarea"
                                    placeholder="Optional: what this topic covers, reading material, and so on…"><?php echo esc_textarea( $sp_item && $sp_item->detail ? $sp_item->detail : '' ); ?></textarea>
                            </div>
                        </div>

                        <div class="sp-form-actions">
                            <?php submit_button( $sp_item ? 'Save topic' : 'Add topic', 'primary', 'submit', true ); ?>
                            <?php if ( $sp_item ) : ?>
                                <a class="sp-cancel-link" href="<?php echo esc_url( add_query_arg( 'edit', (int) $editing->id, admin_url( 'admin.php?page=sp-courses' ) ) ); ?>">Cancel editing</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="sp-outline-import">
                    <div class="sp-import-head">
                        <div class="sp-import-icon"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span></div>
                        <div>
                            <h3>Import topics from CSV</h3>
                            <p class="sp-admin-muted">Bulk-load an outline in one go — rows append after the topics below, in file order.</p>
                        </div>
                    </div>

                    <form method="post" enctype="multipart/form-data">
                        <?php sp_admin_nonce_field( 'import_curriculum' ); ?>
                        <input type="hidden" name="course_id" value="<?php echo esc_attr( (int) $editing->id ); ?>" />

                        <div class="sp-import-drop">
                            <input type="file" name="import_file" id="sp_import_file" accept=".csv,text/csv" required />
                            <?php submit_button( 'Import topics', 'secondary', 'submit', false ); ?>
                        </div>

                        <p class="sp-hint">
                            One topic per row: <code>title</code> (required) and <code>detail</code> (optional).
                            An optional header row labelled <code>title</code>/<code>topic</code>/<code>name</code>
                            is skipped, and rows are appended after the existing outline in file order.
                        </p>
                    </form>
                </div>

                <?php if ( ! $sp_items ) : ?>
                    <div class="sp-empty">
                        <div class="sp-empty-icon"><span class="dashicons dashicons-list-view" aria-hidden="true"></span></div>
                        <strong>No topics yet.</strong>
                        <p>Add the first one above — classes tick topics off as they cover them.</p>
                    </div>
                <?php else : ?>
                    <ol class="sp-outline">
                        <?php foreach ( $sp_items as $sp_index => $sp_row ) :
                            $sp_is_editing_item = $sp_item && (int) $sp_item->id === (int) $sp_row->id;
                            ?>
                            <li class="sp-outline-item<?php echo $sp_is_editing_item ? ' is-editing' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal class name. ?>">
                                <span class="sp-outline-num" aria-hidden="true"><?php echo esc_html( number_format_i18n( $sp_index + 1 ) ); ?></span>

                                <div class="sp-outline-body">
                                    <span class="sp-outline-title">
                                        <?php echo esc_html( $sp_row->title ); ?>
                                        <?php if ( $sp_is_editing_item ) : ?>
                                            <span class="sp-chip sp-chip-accent"><span class="dashicons dashicons-edit" aria-hidden="true"></span>Editing</span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ( $sp_row->detail ) : ?>
                                        <span class="sp-outline-detail"><?php echo esc_html( wp_trim_words( $sp_row->detail, 20 ) ); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="sp-outline-actions">
                                    <span class="sp-sort-group" role="group" aria-label="Reorder topic">
                                        <form method="post" class="sp-admin-inline">
                                            <?php sp_admin_nonce_field( 'move_curriculum' ); ?>
                                            <input type="hidden" name="item_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
                                            <input type="hidden" name="direction" value="up" />
                                            <button type="submit" class="sp-icon-btn" title="Move up" aria-label="Move topic up" <?php disabled( 0 === $sp_index ); ?>>
                                                <span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
                                            </button>
                                        </form>
                                        <form method="post" class="sp-admin-inline">
                                            <?php sp_admin_nonce_field( 'move_curriculum' ); ?>
                                            <input type="hidden" name="item_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
                                            <input type="hidden" name="direction" value="down" />
                                            <button type="submit" class="sp-icon-btn" title="Move down" aria-label="Move topic down" <?php disabled( $sp_index === count( $sp_items ) - 1 ); ?>>
                                                <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                            </button>
                                        </form>
                                    </span>

                                    <a class="sp-icon-btn sp-icon-btn-ghost" title="Edit topic" aria-label="Edit topic"
                                        href="<?php echo esc_url( add_query_arg( array( 'edit' => (int) $editing->id, 'curriculum' => (int) $sp_row->id ), admin_url( 'admin.php?page=sp-courses' ) ) ); ?>">
                                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                    </a>

                                    <form method="post" class="sp-admin-inline" onsubmit="return confirm('Delete this topic? Any class coverage recorded against it goes too.');">
                                        <?php sp_admin_nonce_field( 'delete_curriculum' ); ?>
                                        <input type="hidden" name="item_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
                                        <button type="submit" class="sp-icon-btn sp-icon-btn-danger" title="Delete topic" aria-label="Delete topic">
                                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <footer class="sp-courses-page-footer">
            <span><span class="sp-courses-footer-dot" aria-hidden="true"></span> Course management</span>
            <span>Course codes feed into generated certificates — keep them unique.</span>
        </footer>
    </div>

    <script>
        (function () {
            // The code is stamped in upper case — type it that way as you go.
            var code = document.getElementById('sp_c_code');
            if (code) {
                code.addEventListener('input', function () {
                    this.value = this.value.toUpperCase();
                });
            }
        })();
    </script>
    <?php
}