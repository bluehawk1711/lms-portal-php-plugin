<?php
/**
 * All materials the teacher has authored or can manage, across classes.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_teacher = $teacher ? $teacher : sp_get_teacher_by_user_id( get_current_user_id() );
$sp_classes = $sp_teacher ? sp_get_classes( array( 'teacher_id' => $sp_teacher->id ) ) : array();

// An administrator signed in without a teacher row browses every class.
if ( ! $sp_classes && current_user_can( 'manage_options' ) ) {
	$sp_classes = sp_get_classes( array( 'status' => 'active' ) );
}

if ( ! $sp_classes ) {
	?>
	<div class="sp-mat-empty sp-mat-empty--page">
		<span class="sp-mat-empty__icon" aria-hidden="true">
			<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
		</span>
		<h2 class="sp-mat-empty__title">No classes yet</h2>
		<p class="sp-mat-empty__text">You are not assigned to any class yet. Once you are, your notes and documents will show up here.</p>
	</div>
	<?php
	return;
}

$sp_by_class     = array();
$sp_total        = 0;
$sp_total_pub    = 0;
$sp_total_draft  = 0;

foreach ( $sp_classes as $sp_class ) {
	$sp_items = sp_get_materials( $sp_class->id, array( 'draft', 'published' ) );
	$sp_items = $sp_items ? $sp_items : array();
	$sp_pub   = 0;

	foreach ( $sp_items as $sp_item ) {
		if ( 'published' === $sp_item->status ) {
			++$sp_pub;
		}
	}

	$sp_by_class[ (int) $sp_class->id ] = array(
		'class'     => $sp_class,
		'materials' => $sp_items,
		'published' => $sp_pub,
		'draft'     => count( $sp_items ) - $sp_pub,
	);

	$sp_total       += count( $sp_items );
	$sp_total_pub   += $sp_pub;
	$sp_total_draft += count( $sp_items ) - $sp_pub;
}

// Classes that already have materials come first, so the ones with content are
// never buried under empty ones.
uasort(
	$sp_by_class,
	function ( $a, $b ) {
		return count( $b['materials'] ) <=> count( $a['materials'] );
	}
);

$sp_icon_file = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>';
$sp_icon_note = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
$sp_icon_plus = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';
?>
<div class="sp-mat" data-sp-materials>

	<div class="sp-page-head sp-mat-head">
		<div>
			<h1>Materials</h1>
			<p class="sp-page-sub">Notes and documents across all of your classes.</p>
		</div>
	</div>

	<div class="sp-mat-stats" role="list">
		<div class="sp-mat-stat" role="listitem">
			<span class="sp-mat-stat__num"><?php echo esc_html( number_format_i18n( count( $sp_by_class ) ) ); ?></span>
			<span class="sp-mat-stat__label"><?php echo esc_html( _n( 'Class', 'Classes', count( $sp_by_class ), 'student-portal' ) ); ?></span>
		</div>
		<div class="sp-mat-stat" role="listitem">
			<span class="sp-mat-stat__num"><?php echo esc_html( number_format_i18n( $sp_total ) ); ?></span>
			<span class="sp-mat-stat__label">Total materials</span>
		</div>
		<div class="sp-mat-stat sp-mat-stat--good" role="listitem">
			<span class="sp-mat-stat__num"><?php echo esc_html( number_format_i18n( $sp_total_pub ) ); ?></span>
			<span class="sp-mat-stat__label">Published</span>
		</div>
		<div class="sp-mat-stat sp-mat-stat--muted" role="listitem">
			<span class="sp-mat-stat__num"><?php echo esc_html( number_format_i18n( $sp_total_draft ) ); ?></span>
			<span class="sp-mat-stat__label">Drafts</span>
		</div>
	</div>

	<div class="sp-mat-toolbar">
		<div class="sp-mat-search">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			<label class="screen-reader-text" for="sp-mat-search">Search materials</label>
			<input type="search" id="sp-mat-search" placeholder="Search by title…" autocomplete="off" data-sp-search>
		</div>

		<div class="sp-mat-filters" role="group" aria-label="Filter by status">
			<button type="button" class="sp-mat-chip is-active" data-sp-filter="all" aria-pressed="true">All</button>
			<button type="button" class="sp-mat-chip" data-sp-filter="published" aria-pressed="false">Published</button>
			<button type="button" class="sp-mat-chip" data-sp-filter="draft" aria-pressed="false">Drafts</button>
		</div>
	</div>

	<?php foreach ( $sp_by_class as $sp_bundle ) : ?>
		<?php $sp_cid = (int) $sp_bundle['class']->id; ?>
		<section class="sp-mat-card" id="sp-class-<?php echo esc_attr( $sp_cid ); ?>" data-sp-class>

			<header class="sp-mat-card__head">
				<div class="sp-mat-card__titlewrap">
					<h2 class="sp-mat-card__title"><?php echo esc_html( $sp_bundle['class']->title ); ?></h2>
					<p class="sp-mat-card__meta">
						<span><?php echo esc_html( number_format_i18n( count( $sp_bundle['materials'] ) ) ); ?> <?php echo esc_html( _n( 'material', 'materials', count( $sp_bundle['materials'] ), 'student-portal' ) ); ?></span>
						<span class="sp-mat-dot" aria-hidden="true"></span>
						<span><?php echo esc_html( number_format_i18n( $sp_bundle['published'] ) ); ?> published</span>
						<span class="sp-mat-dot" aria-hidden="true"></span>
						<span><?php echo esc_html( number_format_i18n( $sp_bundle['draft'] ) ); ?> <?php echo esc_html( _n( 'draft', 'drafts', $sp_bundle['draft'], 'student-portal' ) ); ?></span>
					</p>
				</div>

				<a class="sp-btn sp-btn-primary sp-mat-add" href="<?php echo esc_url( add_query_arg( 'class', $sp_cid, sp_teacher_url( 'materials/new' ) ) ); ?>">
					<?php echo $sp_icon_plus; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span>Add material</span>
				</a>
			</header>

			<?php if ( ! $sp_bundle['materials'] ) : ?>
				<div class="sp-mat-empty">
					<span class="sp-mat-empty__icon" aria-hidden="true">
						<?php echo $sp_icon_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					</span>
					<h3 class="sp-mat-empty__title">Nothing here yet</h3>
					<p class="sp-mat-empty__text">Share notes or upload a file so students in this class can find it.</p>
					<a class="sp-btn sp-btn-ghost" href="<?php echo esc_url( add_query_arg( 'class', $sp_cid, sp_teacher_url( 'materials/new' ) ) ); ?>">Create the first one</a>
				</div>
			<?php else : ?>
				<div class="sp-table-wrap sp-mat-tablewrap">
					<table class="sp-table sp-mat-table">
						<thead>
							<tr>
								<th scope="col">Title</th>
								<th scope="col">Type</th>
								<th scope="col">Status</th>
								<th scope="col">Published</th>
								<th scope="col"><span class="screen-reader-text">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $sp_bundle['materials'] as $sp_material ) : ?>
								<?php
								$sp_is_file = 'file' === $sp_material->kind;
								$sp_is_pub  = 'published' === $sp_material->status;
								?>
								<tr data-sp-row data-status="<?php echo esc_attr( $sp_is_pub ? 'published' : 'draft' ); ?>" data-title="<?php echo esc_attr( wp_strip_all_tags( $sp_material->title ) ); ?>">
									<td data-label="Title">
										<a class="sp-mat-title" href="<?php echo esc_url( sp_teacher_url( 'materials/' . (int) $sp_material->id ) ); ?>">
											<span class="sp-mat-title__icon sp-mat-title__icon--<?php echo $sp_is_file ? 'file' : 'note'; ?>">
												<?php echo $sp_is_file ? $sp_icon_file : $sp_icon_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
											</span>
											<span class="sp-mat-title__text"><?php echo esc_html( $sp_material->title ); ?></span>
										</a>
									</td>
									<td data-label="Type">
										<span class="sp-mat-type"><?php echo $sp_is_file ? 'File' : 'Note'; ?></span>
									</td>
									<td data-label="Status">
										<?php if ( $sp_is_pub ) : ?>
											<span class="sp-badge sp-badge-good sp-mat-badge"><span class="sp-mat-badge__dot"></span>Published</span>
										<?php else : ?>
											<span class="sp-badge sp-badge-muted sp-mat-badge"><span class="sp-mat-badge__dot"></span>Draft</span>
										<?php endif; ?>
									</td>
									<td data-label="Published">
										<?php
										echo $sp_material->published_at
											? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_material->published_at ) ) )
											: '<span class="sp-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary.
										?>
									</td>
									<td data-label="" class="sp-mat-actions">
										<a class="sp-btn sp-btn-ghost sp-mat-edit" href="<?php echo esc_url( sp_teacher_url( 'materials/' . (int) $sp_material->id ) ); ?>">Edit</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="sp-mat-nomatch" hidden>No materials match your search.</p>
				</div>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>

<script>
( function () {
	var root = document.querySelector( '[data-sp-materials]' );
	if ( ! root ) { return; }

	var search  = root.querySelector( '[data-sp-search]' );
	var chips   = root.querySelectorAll( '[data-sp-filter]' );
	var cards   = root.querySelectorAll( '[data-sp-class]' );
	var status  = 'all';

	function apply() {
		var q = ( search.value || '' ).trim().toLowerCase();

		cards.forEach( function ( card ) {
			var rows = card.querySelectorAll( '[data-sp-row]' );
			if ( ! rows.length ) { return; }

			var visible = 0;
			rows.forEach( function ( row ) {
				var okStatus = 'all' === status || row.getAttribute( 'data-status' ) === status;
				var okText   = ! q || ( row.getAttribute( 'data-title' ) || '' ).toLowerCase().indexOf( q ) !== -1;
				var show     = okStatus && okText;
				row.hidden   = ! show;
				if ( show ) { visible++; }
			} );

			var none = card.querySelector( '.sp-mat-nomatch' );
			var wrap = card.querySelector( 'table' );
			if ( none ) { none.hidden = visible > 0; }
			if ( wrap ) { wrap.hidden = 0 === visible; }
		} );
	}

	search.addEventListener( 'input', apply );

	chips.forEach( function ( chip ) {
		chip.addEventListener( 'click', function () {
			status = chip.getAttribute( 'data-sp-filter' );
			chips.forEach( function ( c ) {
				var on = c === chip;
				c.classList.toggle( 'is-active', on );
				c.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
			apply();
		} );
	} );
} )();
</script>