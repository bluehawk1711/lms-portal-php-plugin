<?php
/**
 * Teacher view of one student's exam attempt: score and the per-question
 * review with the correct answer shown beside the student's response.
 *
 * Expects $teacher and sp_attempt_id in the query.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_attempt_id = (int) get_query_var( 'sp_attempt_id' );

$sp_attempt = $sp_attempt_id ? sp_get_assignment_attempt( $sp_attempt_id ) : null;
if ( ! $sp_attempt ) {
	?>
	<div class="sp-card">
		<p class="sp-muted">That attempt no longer exists.</p>
	</div>
	<?php
	return;
}

$sp_assignment = sp_get_assignment( (int) $sp_attempt->assignment_id );
if ( ! $sp_assignment ) {
	?>
	<div class="sp-card">
		<p class="sp-muted">That assignment no longer exists.</p>
	</div>
	<?php
	return;
}

$sp_access = sp_require_assigned_teacher( (int) $sp_assignment->class_id );
if ( is_wp_error( $sp_access ) ) {
	?>
	<div class="sp-card">
		<p class="sp-muted"><?php echo esc_html( $sp_access->get_error_message() ); ?></p>
	</div>
	<?php
	return;
}

$sp_teacher = ! empty( $teacher ) ? $teacher : sp_get_teacher_by_user_id( get_current_user_id() );
$sp_class   = sp_get_class( (int) $sp_assignment->class_id );

$sp_answers   = sp_get_attempt_answers( (int) $sp_attempt->id );
$sp_questions = $sp_attempt->set_id ? sp_get_set_questions( (int) $sp_attempt->set_id ) : array();
$sp_score     = (int) $sp_attempt->score;
$sp_total     = (int) $sp_attempt->total;
$sp_percent   = $sp_total > 0 ? (int) round( ( $sp_score / $sp_total ) * 100 ) : 0;
$sp_total_q   = count( $sp_questions );

$sp_right_count = 0;
$sp_wrong_count = 0;
foreach ( $sp_questions as $sp_question ) {
	$sp_answer = isset( $sp_answers[ (int) $sp_question->id ] ) ? $sp_answers[ (int) $sp_question->id ] : null;
	if ( $sp_answer && 1 === (int) $sp_answer->correct ) {
		++$sp_right_count;
	} elseif ( $sp_answer ) {
		++$sp_wrong_count;
	}
}

$sp_started   = $sp_attempt->started_at ? strtotime( $sp_attempt->started_at ) : 0;
$sp_finished  = $sp_attempt->submitted_at ? strtotime( $sp_attempt->submitted_at ) : 0;
$sp_taken     = ( $sp_started && $sp_finished ) ? sprintf( '%dm %02ds', (int) floor( ( $sp_finished - $sp_started ) / 60 ), ( $sp_finished - $sp_started ) % 60 ) : '';
$sp_base_url  = sp_teacher_url( 'assignments/' . (int) $sp_attempt->assignment_id );
$sp_list_url  = add_query_arg( 'tab', 'students', $sp_base_url );
?>
<div class="sp-page-head">
	<h1><?php echo esc_html( $sp_assignment->title ); ?></h1>
	<p class="sp-page-sub">
		<?php echo esc_html( sp_assignment_type_label( $sp_assignment->type ) ); ?>
		<span class="sp-meta-separator" aria-hidden="true">/</span>
		<?php echo esc_html( $sp_class ? $sp_class->title : '' ); ?>
	</p>
</div>

<div class="sp-card">
	<div class="sp-card-head is-row">
		<div>
			<h2 class="sp-card-title">Student result</h2>
			<p class="sp-ae-card-sub">
				<strong><?php echo esc_html( $sp_attempt->full_name ); ?></strong>
				<span class="sp-kv-note"><?php echo esc_html( $sp_attempt->email ); ?></span>
			</p>
		</div>
		<div class="sp-card-meta">
			<span class="sp-badge"><?php echo wp_kses_post( sp_attempt_status_badge( $sp_attempt->status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge markup. ?></span>
			<?php if ( $sp_attempt->set_label ) : ?>
				<span class="sp-ae-pill"><?php echo esc_html( $sp_attempt->set_label ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<div class="sp-quiz-result" role="status" aria-label="Exam result">
		<div class="sp-quiz-result-score">
			<span class="sp-quiz-result-pct"><?php echo esc_html( sp_format_number( $sp_percent ) ); ?><small>%</small></span>
			<span class="sp-quiz-result-score-label">Overall score</span>
		</div>
		<div class="sp-quiz-result-body">
			<p class="sp-quiz-result-headline">
				<?php echo $sp_attempt->total > 0
					? sprintf( 'You got <strong>%d</strong> out of <strong>%d</strong> answers correct', $sp_score, $sp_total )
					: 'No answers recorded.';
				// phpcs:ignore WordPress.Security.EscapeOutput -- %d placeholders only.
			?>
			</p>
			<div class="sp-quiz-result-track" role="progressbar" aria-label="Score" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) min( 100, max( 0, $sp_percent ) ); ?>">
	<div class="sp-table-wrap">
		<table class="sp-table sp-table-form">
			<caption class="sp-ae-sr">Question-by-question review</caption>
			<thead>
				<tr>
					<th scope="col">#</th>
					<th scope="col">Question</th>
					<th scope="col">Your answer</th>
					<th scope="col">Correct answer</th>
					<th scope="col">Result</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$sp_index = 0;
				foreach ( $sp_questions as $sp_question ) :
					$sp_answer = isset( $sp_answers[ (int) $sp_question->id ] ) ? $sp_answers[ (int) $sp_question->id ] : null;
					$sp_given  = $sp_answer ? (string) $sp_answer->given : '';
					$sp_right  = $sp_answer && 1 === (int) $sp_answer->correct;
					?>
					<tr>
						<td data-label="#">
							<?php echo esc_html( ++$sp_index ); ?>
						</td>
						<td data-label="Question">
							<?php echo esc_html( $sp_question->prompt ); ?>
						</td>
						<td data-label="Your answer">
							<?php
							if ( '' === $sp_given ) {
								echo '<span class="sp-muted">Not answered</span>';
							} else {
								echo esc_html( sp_attempt_answer_label( $sp_question, $sp_given ) );
							}
							?>
						</td>
						<td data-label="Correct answer">
							<?php echo esc_html( sp_attempt_correct_label( $sp_question ) ); ?>
							<?php if ( ! empty( $sp_question->explanation ) ) : ?>
								<div class="sp-ae-explain">
									<span class="sp-ae-explain-label">Explanation</span>
									<p><?php echo esc_html( $sp_question->explanation ); ?></p>
								</div>
							<?php endif; ?>
						</td>
						<td data-label="Result">
							<span class="sp-badge <?php echo $sp_right ? 'sp-badge-good' : 'sp-badge-bad'; ?>">
								<?php echo $sp_right ? 'Correct' : 'Incorrect'; ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="sp-meta">
		<?php if ( $sp_started ) : ?>
			<span class="sp-kv-note">Started <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', $sp_started ) ); ?></span>
		<?php endif; ?>
		<?php if ( $sp_finished ) : ?>
			<span class="sp-kv-note">Submitted <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', $sp_finished ) ); ?></span>
		<?php endif; ?>
	</div>

	<div class="sp-card-actions">
		<a class="sp-btn sp-btn-ghost" href="<?php echo esc_url( $sp_list_url ); ?>">&larr; Back to attempts</a>
		<?php if ( in_array( $sp_attempt->status, array( 'submitted', 'failed' ), true ) ) : ?>
			<form method="post" action="<?php echo esc_url( $sp_base_url ); ?>" style="display:inline;"
				onsubmit="return confirm('Allow this student to sit the exam again? Their current attempt is cleared.');">
				<?php sp_teacher_nonce_field( 'allow_retake' ); ?>
				<input type="hidden" name="student_row_id" value="<?php echo esc_attr( (int) $sp_attempt->id ); ?>" />
				<button type="submit" class="sp-btn sp-btn-ghost sp-btn-sm" aria-label="<?php echo esc_attr( 'Allow retake: ' . $sp_attempt->full_name ); ?>">Allow retake</button>
			</form>
		<?php endif; ?>
	</div>
</div>
				<span style="width:<?php echo (int) min( 100, max( 0, $sp_percent ) ); ?>%"></span>
			</div>
			<ul class="sp-quiz-result-facts">
				<li class="is-good"><span aria-hidden="true">&#10003;</span> <?php echo esc_html( $sp_right_count ); ?> correct</li>
				<li class="is-bad"><span aria-hidden="true">&#10007;</span> <?php echo esc_html( $sp_wrong_count ); ?> incorrect</li>
				<?php if ( $sp_taken ) : ?>
					<li class="is-muted"><span aria-hidden="true">&#9201;</span> Taken in <?php echo esc_html( $sp_taken ); ?></li>
				<?php endif; ?>
			</ul>
		</div>
	</div>