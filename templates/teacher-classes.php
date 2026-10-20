<?php
/**
 * Teacher class list.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$sp_teacher = ( isset( $teacher ) && $teacher ) ? $teacher : sp_get_teacher_by_user_id( get_current_user_id() );
$sp_classes = $sp_teacher
    ? sp_get_classes(
        array(
            'teacher_id' => $sp_teacher->id,
            'orderby'    => 'start_date',
            'order'      => 'DESC',
        )
    )
    : array();

$sp_archived = $sp_teacher
    ? sp_get_classes(
        array(
            'teacher_id' => $sp_teacher->id,
            'status'     => 'archived',
            'orderby'    => 'start_date',
            'order'      => 'DESC',
        )
    )
    : array();

// The create form's course dropdown: active courses only, since an archived
// course has stopped being taught.
$sp_courses = array();

foreach ( function_exists( 'sp_get_courses' ) ? sp_get_courses() : array() as $sp_c ) {
    if ( 'archived' !== (string) $sp_c->status ) {
        $sp_courses[] = $sp_c;
    }
}

// An administrator signed in without a teacher row browses every class.
if ( ! $sp_classes && ! $sp_archived && current_user_can( 'manage_options' ) ) {
    $sp_classes = sp_get_classes( array( 'orderby' => 'start_date', 'order' => 'DESC' ) );

    $sp_archived = sp_get_classes(
        array(
            'status'  => 'archived',
            'orderby' => 'start_date',
            'order'   => 'DESC',
        )
    );
}
?>
<div class="sp-page-head sp-class-page-head">
    <h1>My classes</h1>
    <p class="sp-page-sub">Every class you teach, with attendance, materials and results in one place.</p>
</div>

<div id="sp-classes-region" data-sp-async-region>
<div class="sp-class-overview" aria-label="Class overview">
    <div class="sp-class-stat">
        <span class="sp-class-stat-icon" aria-hidden="true">▦</span>
        <span class="sp-class-stat-copy">
            <span class="sp-class-stat-value"><?php echo esc_html( number_format_i18n( count( $sp_classes ) ) ); ?></span>
            <span class="sp-class-stat-label">Active classes</span>
        </span>
    </div>
    <div class="sp-class-stat is-archived">
        <span class="sp-class-stat-icon" aria-hidden="true">▤</span>
        <span class="sp-class-stat-copy">
            <span class="sp-class-stat-value"><?php echo esc_html( number_format_i18n( count( $sp_archived ) ) ); ?></span>
            <span class="sp-class-stat-label">Archived classes</span>
        </span>
    </div>
</div>

<div class="sp-card sp-class-create-card" id="sp-create-class">
    <div class="sp-class-create-head">
        <span class="sp-class-create-icon" aria-hidden="true">+</span>
        <div class="sp-class-create-head-copy">
            <h2 class="sp-card-title">Create a class</h2>
            <p class="sp-muted">Start with a title. The code, dates and description are optional.</p>
        </div>
    </div>

    <form class="sp-class-create-form" method="post" action="<?php echo esc_url( sp_teacher_url( 'classes' ) ); ?>" data-sp-async>
        <?php sp_teacher_nonce_field( 'create_class' ); ?>

        <div class="sp-field">
            <label for="sp_class_title">Class title <span aria-hidden="true">*</span></label>
            <input type="text" id="sp_class_title" name="title" required maxlength="190" placeholder="e.g. Graphic Design — Batch 2026" />
        </div>

        <div class="sp-field-row">
            <div class="sp-field">
                <label for="sp_class_code">Class code</label>
                <input type="text" id="sp_class_code" name="code" maxlength="32" placeholder="Optional, e.g. GD-26-A" />
            </div>
            <div class="sp-field">
                <label for="sp_class_course">Course</label>
                <select id="sp_class_course" name="course_id">
                    <option value="0">No course</option>
                    <?php foreach ( $sp_courses as $sp_course ) : ?>
                        <option value="<?php echo esc_attr( (int) $sp_course->id ); ?>">
                            <?php echo esc_html( $sp_course->name . ( $sp_course->code ? ' (' . $sp_course->code . ')' : '' ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ( ! $sp_courses ) : ?>
                    <p class="sp-muted">No courses yet — ask an admin to add one on the Courses screen.</p>
                <?php else : ?>
                    <p class="sp-muted">The course this class teaches. Its code is stamped into the certificate id.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="sp-field-row">
            <div class="sp-field">
                <label for="sp_class_timing_start">Daily start time</label>
                <input type="time" id="sp_class_timing_start" name="timing_all[start]" data-sp-time />
            </div>
            <div class="sp-field">
                <label for="sp_class_timing_end">Daily end time</label>
                <input type="time" id="sp_class_timing_end" name="timing_all[end]" data-sp-time />
            </div>
        </div>
        <p class="sp-muted">The class meets at this same time every day of the week — set it once and it applies to all seven days.</p>

        <div class="sp-field-row">
            <div class="sp-field">
                <label for="sp_class_start">Start date</label>
                <input type="date" id="sp_class_start" name="start_date" data-sp-date data-sp-off-days="<?php echo esc_attr( implode( ',', sp_off_days() ) ); ?>" />
            </div>
            <div class="sp-field">
                <label for="sp_class_end">End date</label>
                <input type="date" id="sp_class_end" name="end_date" data-sp-date />
            </div>
        </div>

        <div class="sp-field">
            <label for="sp_class_description">Description</label>
            <textarea id="sp_class_description" name="description" rows="3" placeholder="What will students learn in this class?"></textarea>
        </div>

        <p class="sp-card-actions">
            <button type="submit" class="sp-btn sp-btn-primary">Create Class <span aria-hidden="true">→</span></button>
        </p>
    </form>
</div>

<?php if ( ! $sp_classes && ! $sp_archived ) : ?>
    <div class="sp-empty" style="color: #fff">
        <strong style="color: #fff">No classes yet.</strong> Create your first class above, then ask an administrator to enrol students.
    </div>
<?php endif; ?>

<?php if ( $sp_classes || $sp_archived ) : ?>
    <div class="sp-class-toolbar">
        <div class="sp-class-toolbar-copy">
            <h2 class="sp-class-toolbar-title">Your classes</h2>
            <p class="sp-class-search-help">Search by class title, code, description or student count.</p>
        </div>
        <div class="sp-class-search-control">
            <div class="sp-class-search-field">
                <span class="sp-class-search-icon" aria-hidden="true">⌕</span>
                <label class="screen-reader-text" for="sp-class-search">Search classes</label>
                <input class="sp-class-search-input" type="search" id="sp-class-search" placeholder="Search your classes…" autocomplete="off" aria-describedby="sp-class-search-status" />
            </div>
            <button class="sp-class-search-clear" type="button" id="sp-class-search-clear" hidden>Clear</button>
        </div>
    </div>
    <p class="sp-class-search-status" id="sp-class-search-status" aria-live="polite" aria-atomic="true"></p>

    <?php if ( $sp_classes ) : ?>
        <div class="sp-card-grid sp-class-grid" id="sp-active-classes-grid" data-sp-active-grid>
            <?php foreach ( $sp_classes as $sp_class ) : ?>
                <?php
                $sp_search_parts = array( (string) $sp_class->title );
                if ( ! empty( $sp_class->code ) ) {
                    $sp_search_parts[] = (string) $sp_class->code;
                }
                if ( ! empty( $sp_class->description ) ) {
                    $sp_search_parts[] = (string) $sp_class->description;
                }
                $sp_search_parts[] = (string) sp_count_class_students( $sp_class->id );
                ?>
                <article class="sp-card sp-class-card" data-sp-class-item data-sp-search="<?php echo esc_attr( implode( ' ', $sp_search_parts ) ); ?>">
                    <div class="sp-class-card-head">
                        <h3 class="sp-card-title">
                            <a href="<?php echo esc_url( sp_teacher_url( 'classes/' . (int) $sp_class->id ) ); ?>"><?php echo esc_html( $sp_class->title ); ?></a>
                        </h3>
                        <?php if ( $sp_class->code ) : ?>
                            <span style="color: #000000; font-weight: bold; background-color: transparent; padding: 0.25em 0.5em; border-radius: 0.25em; border: 1px solid transparent;" class="sp-class-code" title="Class code"><?php echo esc_html( $sp_class->code ); ?></span>
                        <?php endif; ?>
                        <?php if ( 'pending' === (string) $sp_class->status ) : ?>
                            <span class="sp-class-pending" title="An administrator must approve this class before it goes live">Awaiting approval</span>
                        <?php endif; ?>
                    </div>

                    <p class="sp-class-card-meta">
                        <span style="color: #000000; font-weight: bold; background-color: transparent; padding: 0.25em 0.5em; border-radius: 0.25em; border: 1px solid transparent;"><?php echo esc_html( number_format_i18n( sp_count_class_students( $sp_class->id ) ) ); ?> students</span>
                        <?php if ( $sp_class->start_date ) : ?>
                            <span style="color: #000000; font-weight: bold; background-color: transparent; padding: 0.25em 0.5em; border-radius: 0.25em; border: 1px solid transparent;">Starts :  <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_class->start_date ) ) ); ?></span>
                        <?php endif; ?>
                    </p>

                    <?php if ( $sp_class->description ) : ?>
                        <p class="sp-muted sp-class-card-description"><?php echo esc_html( wp_trim_words( $sp_class->description, 24 ) ); ?></p>
                    <?php else : ?>
                        <p class="sp-muted sp-class-card-description">No description added yet.</p>
                    <?php endif; ?>

                    <div class="sp-card-actions sp-class-card-actions">
                        <a class="sp-btn sp-btn-primary" href="<?php echo esc_url( add_query_arg( 'tab', 'attendance', sp_teacher_url( 'classes/' . (int) $sp_class->id ) ) ); ?>">Take attendance <span aria-hidden="true">→</span></a>
                        <a class="sp-btn sp-btn-ghost" href="<?php echo esc_url( sp_teacher_url( 'classes/' . (int) $sp_class->id ) ); ?>">Open class</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( $sp_archived ) : ?>
        <section class="sp-archived-section" data-sp-archive-section aria-labelledby="sp-archived-classes-title">
            <h2 class="sp-section-title" id="sp-archived-classes-title">Archived classes</h2>
            <ul class="sp-list sp-archived-class-list">
                <?php foreach ( $sp_archived as $sp_class ) : ?>
                    <?php
                    $sp_archived_search_parts = array( (string) $sp_class->title );
                    if ( ! empty( $sp_class->code ) ) {
                        $sp_archived_search_parts[] = (string) $sp_class->code;
                    }
                    if ( ! empty( $sp_class->description ) ) {
                        $sp_archived_search_parts[] = (string) $sp_class->description;
                    }
                    $sp_archived_search_parts[] = (string) sp_count_class_students( $sp_class->id );
                    ?>
                    <li class="sp-list-item sp-archived-class" data-sp-class-item data-sp-archived-item data-sp-search="<?php echo esc_attr( implode( ' ', $sp_archived_search_parts ) ); ?>">
                        <div class="sp-archived-class-copy">
                            <span class="sp-archived-label">Archived</span>
                            <strong><?php echo esc_html( $sp_class->title ); ?></strong>
                            <span class="sp-muted">
                                <?php echo esc_html( number_format_i18n( sp_count_class_students( $sp_class->id ) ) ); ?> students
                                <?php if ( $sp_class->code ) : ?> · <?php echo esc_html( $sp_class->code ); ?><?php endif; ?>
                            </span>
                        </div>
                        <a class="sp-btn sp-btn-ghost" href="<?php echo esc_url( sp_teacher_url( 'classes/' . (int) $sp_class->id ) ); ?>">Open class</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="sp-class-empty-search" data-sp-search-empty hidden>
        <strong>No matching classes found</strong>
        Try another title or class code, or clear your search to see all classes.
    </div>
<?php endif; ?>
</div>
