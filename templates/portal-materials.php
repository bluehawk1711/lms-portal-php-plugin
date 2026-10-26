<?php
/**
 * Student course materials.
 *
 * Documents are embedded on this page — there are no download links in the
 * student portal. Notes render as rich text, and every file goes through
 * sp_material_embed().
 *
 * The list is presented as a header card (title, count, search and category
 * filters) above a feed of document cards, matching the portal mockup.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $profile ) ) {
	?>
<div class="sp-notice sp-notice-warn">
    This WordPress account is not linked to a student profile yet. Ask your institute to create your portal account.
</div>
<?php
	return;
}

$sp_materials = sp_get_student_materials( $profile->id, 100 );

/*
 * Category pills: "All", then "Documents"/"Notes" by kind, then one pill per
 * distinct class so a student can narrow to a single course. Counts are
 * computed up front so the labels are honest without a round trip.
 */
$sp_total    = count( $sp_materials );
$sp_kinds    = array();
$sp_classes  = array();

foreach ( $sp_materials as $sp_material ) {
	$sp_kind_key             = ( 'file' === $sp_material->kind ) ? 'file' : 'note';
	$sp_kinds[ $sp_kind_key ] = isset( $sp_kinds[ $sp_kind_key ] ) ? $sp_kinds[ $sp_kind_key ] + 1 : 1;

	if ( ! empty( $sp_material->class_title ) ) {
		$sp_classes[ $sp_material->class_title ] = isset( $sp_classes[ $sp_material->class_title ] )
			? $sp_classes[ $sp_material->class_title ] + 1
			: 1;
	}
}

/*
 * A soft accent per card, cycled by position, so the feed reads like the
 * mockup's coloured file badges rather than a wall of identical rows.
 */
$sp_accents = array( 'amber', 'orange', 'blue', 'purple' );
?>
<section class="sp-mat" data-sp-materials>
    <div class="sp-mat-head">
        <div class="sp-mat-head-top">
            <div class="sp-mat-head-lead">
                <div class="sp-mat-title-row">
                    <h1 class="sp-mat-title">Materials</h1>
                    <span class="sp-count-badge"><?php echo esc_html( sprintf(
                        _n( '%d Available', '%d Available', $sp_total, 'student-portal' ),
                        $sp_total
                    ) ); ?></span>
                </div>
                <p class="sp-mat-sub">
                    Notes, worksheets, and resources published by your teachers for the courses you are
                    currently enrolled in.
                </p>
            </div>

            <div class="sp-mat-search">
                <span class="sp-mat-search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                </span>
                <input type="search" class="sp-mat-search-input" placeholder="Search materials &amp; files..."
                    aria-label="Search materials and files" data-mat-search autocomplete="off" />
            </div>
        </div>

        <?php if ( $sp_materials ) : ?>
        <div class="sp-mat-filters">
            <div class="sp-mat-pills" role="group" aria-label="Filter materials">
                <button type="button" class="sp-mat-pill is-active" data-mat-filter="all"
                    aria-pressed="true">
                    <?php echo esc_html( sprintf(
                        _n( 'All Materials (%d)', 'All Materials (%d)', $sp_total, 'student-portal' ),
                        $sp_total
                    ) ); ?>
                </button>

                <?php if ( isset( $sp_kinds['file'] ) ) : ?>
                <button type="button" class="sp-mat-pill" data-mat-filter="kind:file" aria-pressed="false">
                    <?php echo esc_html( sprintf(
                        _n( 'Documents (%d)', 'Documents (%d)', $sp_kinds['file'], 'student-portal' ),
                        $sp_kinds['file']
                    ) ); ?>
                </button>
                <?php endif; ?>

                <?php if ( isset( $sp_kinds['note'] ) ) : ?>
                <button type="button" class="sp-mat-pill" data-mat-filter="kind:note" aria-pressed="false">
                    <?php echo esc_html( sprintf(
                        _n( 'Notes (%d)', 'Notes (%d)', $sp_kinds['note'], 'student-portal' ),
                        $sp_kinds['note']
                    ) ); ?>
                </button>
                <?php endif; ?>

                <?php foreach ( $sp_classes as $sp_class_name => $sp_class_count ) : ?>
                <button type="button" class="sp-mat-pill"
                    data-mat-filter="class:<?php echo esc_attr( $sp_class_name ); ?>" aria-pressed="false">
                    <?php echo esc_html( $sp_class_name ); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <p class="sp-mat-updated">
                <span class="sp-mat-updated-dot" aria-hidden="true"></span>
                Updated directly by Faculty
            </p>
        </div>
        <?php endif; ?>
    </div>

    <?php if ( ! $sp_materials ) : ?>
    <div class="sp-empty">Nothing published yet. Your teachers will add notes and documents here.</div>
    <?php else : ?>
    <div class="sp-mat-list" data-mat-list>
        <?php
		$sp_index = 0;

		foreach ( $sp_materials as $sp_material ) :
			$sp_is_file = ( 'file' === $sp_material->kind );
			$sp_accent  = $sp_accents[ $sp_index % count( $sp_accents ) ];
			$sp_index++;
			?>
        <article class="sp-mat-card" id="material-<?php echo esc_attr( (int) $sp_material->id ); ?>"
            data-mat-item
            data-mat-kind="<?php echo $sp_is_file ? 'file' : 'note'; ?>"
            data-mat-class="<?php echo esc_attr( (string) $sp_material->class_title ); ?>"
            data-mat-title="<?php echo esc_attr( strtolower( (string) $sp_material->title ) ); ?>">
            <div class="sp-mat-card-main">
                <span class="sp-mat-icon sp-mat-icon-<?php echo esc_attr( $sp_accent ); ?>" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor"
                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
                        <path d="M14 3v5h5" />
                        <path d="M9 13h6" />
                        <path d="M9 17h6" />
                    </svg>
                </span>

                <div class="sp-mat-info">
                    <div class="sp-mat-info-title">
                        <h2 class="sp-mat-name">
                            <a class="sp-mat-link"
                                href="<?php echo esc_url( sp_portal_url( 'materials/' . (int) $sp_material->id ) ); ?>">
                                <?php echo esc_html( $sp_material->title ); ?>
                            </a>
                        </h2>
                        <?php if ( ! empty( $sp_material->class_title ) ) : ?>
                        <span class="sp-mat-course-suffix">
                            &middot; <?php echo esc_html( $sp_material->class_title ); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="sp-mat-meta">
                        <?php if ( ! empty( $sp_material->class_title ) ) : ?>
                        <span class="sp-mat-meta-course"><?php echo esc_html( $sp_material->class_title ); ?></span>
                        <span class="sp-mat-meta-sep" aria-hidden="true">&middot;</span>
                        <?php endif; ?>

                        <?php if ( $sp_material->published_at ) : ?>
                        <span>Published
                            <?php echo esc_html( sp_portal_time( get_option( 'date_format' ), strtotime( $sp_material->published_at ) ) ); ?>
                        </span>
                        <?php endif; ?>

                        <?php if ( $sp_is_file && $sp_material->file_size ) : ?>
                        <span class="sp-mat-meta-sep" aria-hidden="true">&middot;</span>
                        <span class="sp-mat-meta-size">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                                focusable="false">
                                <path d="M12 3v12" />
                                <path d="m7 10 5 5 5-5" />
                                <path d="M5 21h14" />
                            </svg>
                            <?php echo esc_html( sp_format_file_size( $sp_material->file_size ) ); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="sp-mat-type-row">
                        <span class="sp-mat-type">
                            <?php echo $sp_is_file ? 'DOCUMENT' : 'NOTE'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="sp-mat-action">
                <a class="sp-btn sp-btn-primary sp-mat-open"
                    href="<?php echo esc_url( sp_portal_url( 'materials/' . (int) $sp_material->id ) ); ?>">
                    Open <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <p class="sp-mat-noresult" data-mat-noresult hidden>No materials match your search.</p>
    <?php endif; ?>
</section>
