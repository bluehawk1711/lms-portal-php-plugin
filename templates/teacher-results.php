<?php
/**
 * Teacher results overview across all classes.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_teacher = ! empty( $teacher ) ? $teacher : sp_get_teacher_by_user_id( get_current_user_id() );
$sp_classes = $sp_teacher
	? sp_get_classes( array( 'teacher_id' => $sp_teacher->id, 'status' => 'active' ) )
	: array();

// An administrator signed in without a teacher row browses every class.
if ( ! $sp_classes && current_user_can( 'manage_options' ) ) {
	$sp_classes = sp_get_classes( array( 'status' => 'active' ) );
}

if ( ! $sp_classes ) {
	?>
	<div class="sp-res">
		<div class="sp-empty sp-res-empty">
			<svg class="sp-res-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 19.5V5a2 2 0 0 1 2-2h12v15H6a2 2 0 0 0-2 2Zm0 0A2 2 0 0 0 6 21.5h12"/><path d="M9 8h6M9 12h4"/></svg>
			<p class="sp-res-empty-title">No classes yet</p>
			<p class="sp-res-empty-text">You are not assigned to any class yet. Once you are, each class and its results will show up here.</p>
		</div>
	</div>
	<?php
	return;
}

/*
 * Gather the numbers per class first so the summary strip can total them.
 * "Pending" is a subset of "entered", so published = entered - pending.
 */
$sp_rows          = array();
$sp_total_pending = 0;
$sp_total_done    = 0;

foreach ( $sp_classes as $sp_class ) {
	$sp_entered   = (int) sp_count_entered_results( $sp_class->id );
	$sp_pending   = min( $sp_entered, (int) sp_count_pending_results( $sp_class->id ) );
	$sp_published = $sp_entered - $sp_pending;

	if ( ! $sp_entered ) {
		$sp_state = 'empty';
	} elseif ( $sp_pending ) {
		$sp_state = 'pending';
	} else {
		$sp_state = 'done';
	}

	$sp_total_pending += $sp_pending;
	if ( 'done' === $sp_state ) {
		$sp_total_done++;
	}

	$sp_rows[] = array(
		'id'        => (int) $sp_class->id,
		'title'     => $sp_class->title,
		'url'       => add_query_arg( 'tab', 'results', sp_teacher_url( 'classes/' . (int) $sp_class->id ) ),
		'students'  => (int) sp_count_class_students( $sp_class->id ),
		'entered'   => $sp_entered,
		'pending'   => $sp_pending,
		'published' => $sp_published,
		'percent'   => $sp_entered ? (int) floor( $sp_published / $sp_entered * 100 ) : 0,
		'state'     => $sp_state,
	);
}

$sp_class_count = count( $sp_rows );
?>
<div class="sp-res">

	<div class="sp-page-head">
		<h1>Results</h1>
		<p class="sp-page-sub">How far each class has got with results, and what is still unpublished.</p>
	</div>

	<dl class="sp-res-summary" aria-label="Results summary">
		<div class="sp-res-stat">
			<dt>Classes</dt>
			<dd><?php echo esc_html( number_format_i18n( $sp_class_count ) ); ?></dd>
		</div>
		<div class="sp-res-stat<?php echo $sp_total_pending ? ' is-warn' : ''; ?>">
			<dt>Unpublished</dt>
			<dd><?php echo esc_html( number_format_i18n( $sp_total_pending ) ); ?></dd>
		</div>
		<div class="sp-res-stat<?php echo ( $sp_total_done === $sp_class_count ) ? ' is-good' : ''; ?>">
			<dt>Fully published</dt>
			<dd><?php echo esc_html( sprintf( '%s of %s', number_format_i18n( $sp_total_done ), number_format_i18n( $sp_class_count ) ) ); ?></dd>
		</div>
	</dl>

	<div class="sp-res-grid">
		<?php
		foreach ( $sp_rows as $sp_row ) :
			if ( 'empty' === $sp_row['state'] ) {
				$sp_badge_class = 'sp-badge-muted';
				$sp_badge_text  = 'No results yet';
				$sp_caption     = 'No marks entered yet';
				$sp_cta_text    = 'Enter marks';
				$sp_cta_class   = 'sp-btn-primary';
			} elseif ( 'pending' === $sp_row['state'] ) {
				$sp_badge_class = 'sp-badge-warn';
				$sp_badge_text  = number_format_i18n( $sp_row['pending'] ) . ' not published';
				$sp_caption     = number_format_i18n( $sp_row['published'] ) . ' of ' . number_format_i18n( $sp_row['entered'] ) . ' results published';
				$sp_cta_text    = 'Review & publish';
				$sp_cta_class   = 'sp-btn-primary';
			} else {
				$sp_badge_class = 'sp-badge-good';
				$sp_badge_text  = 'All published';
				$sp_caption     = 'All ' . number_format_i18n( $sp_row['entered'] ) . ' results published';
				$sp_cta_text    = 'View results';
				$sp_cta_class   = 'sp-btn-outline';
			}
			$sp_title_id = 'sp-res-title-' . $sp_row['id'];
			?>
			<article class="sp-res-card" data-state="<?php echo esc_attr( $sp_row['state'] ); ?>" aria-labelledby="<?php echo esc_attr( $sp_title_id ); ?>">

				<header class="sp-res-card-head">
					<h2 class="sp-res-card-title" id="<?php echo esc_attr( $sp_title_id ); ?>">
						<a class="sp-res-card-link" href="<?php echo esc_url( $sp_row['url'] ); ?>"><?php echo esc_html( $sp_row['title'] ); ?></a>
					</h2>
					<span class="sp-badge <?php echo esc_attr( $sp_badge_class ); ?>"><?php echo esc_html( $sp_badge_text ); ?></span>
				</header>

				<dl class="sp-res-facts">
					<div>
						<dt>Students</dt>
						<dd><?php echo esc_html( number_format_i18n( $sp_row['students'] ) ); ?></dd>
					</div>
					<div>
						<dt>Entered</dt>
						<dd><?php echo esc_html( number_format_i18n( $sp_row['entered'] ) ); ?></dd>
					</div>
					<div>
						<dt>Unpublished</dt>
						<dd><?php echo esc_html( number_format_i18n( $sp_row['pending'] ) ); ?></dd>
					</div>
				</dl>

				<div class="sp-res-progress-wrap">
					<div class="sp-res-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $sp_row['percent']; ?>" aria-label="<?php echo esc_attr( 'Results published for ' . $sp_row['title'] ); ?>">
						<span style="width: <?php echo (int) $sp_row['percent']; ?>%"></span>
					</div>
					<p class="sp-res-progress-caption"><?php echo esc_html( $sp_caption ); ?></p>
				</div>

				<footer class="sp-res-card-foot">
					<a class="sp-btn <?php echo esc_attr( $sp_cta_class ); ?>" href="<?php echo esc_url( $sp_row['url'] ); ?>" aria-label="<?php echo esc_attr( $sp_cta_text . ': ' . $sp_row['title'] ); ?>"><?php echo esc_html( $sp_cta_text ); ?></a>
				</footer>

			</article>
		<?php endforeach; ?>
	</div>

</div>