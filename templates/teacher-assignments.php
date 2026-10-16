<?php
/**
 * Every assignment and exam across the teacher's classes.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_teacher = ! empty( $teacher ) ? $teacher : sp_get_teacher_by_user_id( get_current_user_id() );
$sp_classes = $sp_teacher ? sp_get_classes( array( 'teacher_id' => $sp_teacher->id ) ) : array();

// An administrator signed in without a teacher row browses every class.
if ( ! $sp_classes && current_user_can( 'manage_options' ) ) {
	$sp_classes = sp_get_classes( array( 'status' => 'active' ) );
}

if ( ! $sp_classes ) {
	?>
	<div class="sp-asg">
		<div class="sp-empty sp-asg-empty">
			<svg class="sp-asg-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9z"/><path d="M9 12h6M9 16h4"/></svg>
			<p class="sp-asg-empty-title">No classes yet</p>
			<p class="sp-asg-empty-text">You are not assigned to any class yet. Once you are, you can create papers and exams for it here.</p>
		</div>
	</div>
	<?php
	return;
}

/*
 * Gather everything first so the summary strip and jump links can use it.
 */
$sp_by_class = array();
$sp_total    = 0;
$sp_drafts   = 0;

foreach ( $sp_classes as $sp_class ) {
	$sp_rows = sp_get_class_assignments( $sp_class->id );
	$sp_rows = $sp_rows ? $sp_rows : array();

	// Administrators browsing the portal see their own classes too; skip any
	// class with nothing to show so the page stays scannable.
	if ( ! $sp_rows && ! current_user_can( 'manage_options' ) ) {
		continue;
	}

	foreach ( $sp_rows as $sp_item ) {
		$sp_total++;
		if ( 'draft' === $sp_item->status ) {
			$sp_drafts++;
		}
	}

	$sp_by_class[ (int) $sp_class->id ] = array(
		'class'       => $sp_class,
		'assignments' => $sp_rows,
	);
}

/*
 * Turns a due date into a display date plus a short relative hint
 * ("Tomorrow", "in 3 days", "2 days ago"). Returns null for no/invalid date.
 */
$sp_today        = new DateTimeImmutable( 'today', wp_timezone() );
$sp_describe_due = static function ( $due_date ) use ( $sp_today ) {
	if ( ! $due_date || 0 === strpos( (string) $due_date, '0000' ) ) {
		return null;
	}

	try {
		$due = new DateTimeImmutable( $due_date, wp_timezone() );
	} catch ( Exception $e ) {
		return null;
	}

	$days = (int) $sp_today->diff( $due->setTime( 0, 0 ) )->format( '%r%a' );

	if ( 0 === $days ) {
		$rel = 'Today';
	} elseif ( 1 === $days ) {
		$rel = 'Tomorrow';
	} elseif ( -1 === $days ) {
		$rel = 'Yesterday';
	} elseif ( $days > 1 && $days <= 14 ) {
		$rel = sprintf( 'in %d days', $days );
	} elseif ( $days < -1 && $days >= -14 ) {
		$rel = sprintf( '%d days ago', abs( $days ) );
	} else {
		$rel = '';
	}

	return array(
		'date' => wp_date( get_option( 'date_format' ), $due->getTimestamp() ),
		'rel'  => $rel,
		'soon' => $days >= 0 && $days <= 3,
	);
};

$sp_new_url = sp_teacher_url( 'assignments/new' );
?>
<div class="sp-asg">

	<div class="sp-asg-head">
		<div class="sp-page-head">
			<h1>Assignments</h1>
			<p class="sp-page-sub">Papers your students read and exams they sit, across all of your classes.</p>
		</div>
		<a class="sp-btn sp-btn-primary sp-asg-add" href="<?php echo esc_url( $sp_new_url ); ?>">New assignment</a>
	</div>

	<?php if ( ! $sp_by_class ) : ?>

		<div class="sp-empty sp-asg-empty">
			<svg class="sp-asg-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9z"/><path d="M9 12h6M9 16h4"/></svg>
			<p class="sp-asg-empty-title">No assignments yet</p>
			<p class="sp-asg-empty-text">Create a paper or an exam and choose the class it belongs to. It will show up here.</p>
		</div>

	<?php else : ?>

		<dl class="sp-asg-summary" aria-label="Assignments summary">
			<div class="sp-asg-stat">
				<dt>Classes</dt>
				<dd><?php echo esc_html( number_format_i18n( count( $sp_by_class ) ) ); ?></dd>
			</div>
			<div class="sp-asg-stat">
				<dt>Assignments</dt>
				<dd><?php echo esc_html( number_format_i18n( $sp_total ) ); ?></dd>
			</div>
			<div class="sp-asg-stat">
				<dt>Drafts</dt>
				<dd><?php echo esc_html( number_format_i18n( $sp_drafts ) ); ?></dd>
			</div>
		</dl>

		<?php if ( count( $sp_by_class ) > 2 ) : ?>
			<nav class="sp-asg-jump" aria-label="Jump to a class">
				<?php foreach ( $sp_by_class as $sp_class_id => $sp_bundle ) : ?>
					<a href="<?php echo esc_url( '#sp-asg-class-' . (int) $sp_class_id ); ?>">
						<?php echo esc_html( $sp_bundle['class']->title ); ?>
						<span class="sp-asg-count"><?php echo esc_html( number_format_i18n( count( $sp_bundle['assignments'] ) ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<div class="sp-asg-classes">
			<?php foreach ( $sp_by_class as $sp_class_id => $sp_bundle ) : ?>
				<?php $sp_heading_id = 'sp-asg-class-title-' . (int) $sp_class_id; ?>
				<section class="sp-asg-class" id="<?php echo esc_attr( 'sp-asg-class-' . (int) $sp_class_id ); ?>" aria-labelledby="<?php echo esc_attr( $sp_heading_id ); ?>">

					<header class="sp-asg-class-head">
						<h2 class="sp-asg-class-title" id="<?php echo esc_attr( $sp_heading_id ); ?>">
							<?php echo esc_html( $sp_bundle['class']->title ); ?>
							<span class="sp-asg-count"><?php echo esc_html( number_format_i18n( count( $sp_bundle['assignments'] ) ) ); ?></span>
						</h2>
						<a class="sp-btn sp-btn-ghost sp-btn-sm sp-asg-add" href="<?php echo esc_url( add_query_arg( 'class', (int) $sp_class_id, $sp_new_url ) ); ?>">Add to this class</a>
					</header>

					<?php if ( ! $sp_bundle['assignments'] ) : ?>

						<p class="sp-asg-class-empty">Nothing here yet. Add a paper or an exam for this class to get started.</p>

					<?php else : ?>

						<div class="sp-asg-tablewrap">
							<table class="sp-asg-table">
								<caption class="sp-asg-sr"><?php echo esc_html( sprintf( 'Assignments for %s', $sp_bundle['class']->title ) ); ?></caption>
								<thead>
									<tr>
										<th scope="col">Title</th>
										<th scope="col">Status</th>
										<th scope="col">When</th>
										<th scope="col">Progress</th>
										<th scope="col"><span class="sp-asg-sr">Actions</span></th>
									</tr>
								</thead>
								<tbody>
									<?php
									foreach ( $sp_bundle['assignments'] as $sp_assignment ) :
										$sp_is_exam  = 'exam' === $sp_assignment->type;
										$sp_is_draft = 'draft' === $sp_assignment->status;
										$sp_open_url = sp_teacher_url( 'assignments/' . (int) $sp_assignment->id );

										$sp_due  = $sp_describe_due( $sp_assignment->due_date );
										$sp_meta = array();

										if ( $sp_due && $sp_due['rel'] ) {
											$sp_soon_class = ( $sp_due['soon'] && ! $sp_is_draft ) ? ' class="sp-asg-soon"' : '';
											$sp_meta[]     = '<span' . $sp_soon_class . '>' . esc_html( $sp_due['rel'] ) . '</span>';
										}

										if ( $sp_is_exam && $sp_assignment->time_limit_minutes ) {
											$sp_meta[] = esc_html( sprintf( '%d min', (int) $sp_assignment->time_limit_minutes ) );
										}

										$sp_progress = ( $sp_is_exam && ! $sp_is_draft )
											? sp_assignment_progress( (int) $sp_assignment->id )
											: null;

										$sp_submitted = 0;
										$sp_students  = 0;
										$sp_percent   = 0;

										if ( $sp_progress ) {
											$sp_submitted = (int) $sp_progress['submitted'];
											$sp_students  = (int) $sp_progress['total'];
											$sp_percent   = $sp_students ? min( 100, (int) floor( $sp_submitted / $sp_students * 100 ) ) : 0;
										}
										?>
										<tr class="sp-asg-row<?php echo $sp_is_draft ? ' is-draft' : ''; ?>" data-type="<?php echo esc_attr( $sp_assignment->type ); ?>">

											<td class="sp-asg-cell-title">
												<a class="sp-asg-title-link" href="<?php echo esc_url( $sp_open_url ); ?>"><?php echo esc_html( $sp_assignment->title ); ?></a>
												<span class="sp-asg-type<?php echo $sp_is_exam ? ' is-exam' : ''; ?>"><?php echo esc_html( sp_assignment_type_label( $sp_assignment->type ) ); ?></span>
											</td>

											<td data-label="Status">
												<?php echo wp_kses_post( sp_assignment_status_badge( $sp_assignment->status ) ); ?>
											</td>

											<td data-label="When">
												<div class="sp-asg-when">
													<?php if ( $sp_due ) : ?>
														<span class="sp-asg-date"><?php echo esc_html( $sp_due['date'] ); ?></span>
													<?php else : ?>
														<span class="sp-muted" aria-hidden="true">&mdash;</span>
														<span class="sp-asg-sr">No due date</span>
													<?php endif; ?>
													<?php if ( $sp_meta ) : ?>
														<span class="sp-asg-when-meta"><?php echo wp_kses_post( implode( ' &middot; ', $sp_meta ) ); ?></span>
													<?php endif; ?>
												</div>
											</td>

											<td data-label="Progress">
												<?php if ( $sp_progress ) : ?>
													<div class="sp-asg-prog<?php echo ( $sp_students && $sp_submitted >= $sp_students ) ? ' is-complete' : ''; ?>">
														<div class="sp-asg-prog-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $sp_percent; ?>" aria-label="<?php echo esc_attr( 'Submissions for ' . $sp_assignment->title ); ?>">
															<span style="width: <?php echo (int) $sp_percent; ?>%"></span>
														</div>
														<span class="sp-asg-prog-text"><strong><?php echo esc_html( $sp_submitted . '/' . $sp_students ); ?></strong> submitted</span>
													</div>
												<?php else : ?>
													<span class="sp-muted" aria-hidden="true">&mdash;</span>
													<span class="sp-asg-sr">Not tracked</span>
												<?php endif; ?>
											</td>

											<td class="sp-asg-cell-action">
												<a class="sp-btn sp-btn-ghost sp-btn-sm sp-asg-open" href="<?php echo esc_url( $sp_open_url ); ?>" aria-label="<?php echo esc_attr( 'Open: ' . $sp_assignment->title ); ?>">Open</a>
											</td>

										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

					<?php endif; ?>

				</section>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

</div>