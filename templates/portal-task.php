<?php
/**
 * One task: a paper's file, or an exam from start to graded result.
 *
 * Expects $profile from sp_render_portal() and sp_assignment_id in the query.
 * Exams render inside the fullscreen shell (no sidebar/rail) so the quiz owns
 * the viewport.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_id    = (int) get_query_var( 'sp_assignment_id' );
$sp_task  = null;
$sp_row   = null;
$sp_state = 'unavailable';

if ( $profile && $sp_id ) {
	// Only what sp_get_assignments_for_profile() shows may be opened, so a
	// guessed id (or another class's task) reads exactly like a missing one.
	foreach ( sp_get_assignments_for_profile( $profile->id ) as $sp_candidate ) {
		if ( (int) $sp_candidate->id === $sp_id ) {
			$sp_task = $sp_candidate;
			break;
		}
	}
}

if ( $sp_task ) {
	$sp_row   = ! empty( $sp_task->student_row_id ) ? sp_get_student_assignment( $sp_id, (int) $profile->id ) : null;
	$sp_state = sp_assignment_student_state( $sp_task, $sp_row );

	// A clock that ran out while the page was open still grades the attempt:
	// empty answers, exactly as resuming an expired attempt would.
	if ( 'in_progress' === $sp_state && ! empty( $sp_task->time_limit_minutes ) && 0 === sp_assignment_time_left( $sp_row, $sp_task ) ) {
		$sp_expired = sp_submit_assignment_attempt( $sp_id, (int) $profile->id, array() );

		if ( ! is_wp_error( $sp_expired ) ) {
			$sp_row   = $sp_expired;
			$sp_state = 'submitted';
		}
	}
}
?>
<?php if ( ! $profile ) : ?>
	<div class="sp-empty">This WordPress account is not linked to a student profile yet, so there is nothing to show here.</div>
<?php elseif ( ! $sp_task ) : ?>
	<div class="sp-empty">That task is not available any more.</div>
<?php else : ?>

	<?php if ( 'paper' === $sp_task->type ) : ?>
		<?php
		// A paper: the description and the gated download of the teacher's file.
		$sp_state_label = sp_assignment_state_badge( $sp_state );
		?>
		<div class="sp-exam-page">
			<article class="sp-card">
				<div class="sp-material-head">
					<div>
						<h1 class="sp-card-title"><?php echo esc_html( $sp_task->title ); ?></h1>
						<p class="sp-muted">
							<?php echo esc_html( $sp_task->class_title ); ?>
							&middot; <?php echo esc_html( sp_assignment_type_label( $sp_task->type ) ); ?>
							<?php if ( ! empty( $sp_task->due_date ) ) : ?>
								&middot; Due <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_task->due_date ) ) ); ?>
							<?php endif; ?>
						</p>
					</div>
					<?php echo $sp_state_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge markup. ?>
				</div>

				<?php if ( ! empty( $sp_task->description ) ) : ?>
					<div class="sp-note-body">
						<?php echo wp_kses_post( wpautop( $sp_task->description ) ); ?>
					</div>
				<?php endif; ?>

				<div class="sp-card-actions">
					<?php if ( ! empty( $sp_task->attachment_path ) ) : ?>
						<a class="sp-btn sp-btn-primary" href="<?php echo esc_url( sp_assignment_file_url( $sp_id ) ); ?>">Download paper</a>
					<?php else : ?>
						<p class="sp-muted">Your teacher has not attached a file yet.</p>
					<?php endif; ?>
				</div>
			</article>
		</div>

	<?php else : ?>
		<?php
		// An exam: the state decides which single view the student gets.
		$sp_set_id = $sp_row && ! empty( $sp_row->set_id ) ? (int) $sp_row->set_id : 0;
		?>

		<?php if ( 'in_progress' === $sp_state ) : ?>
			<?php
			$sp_questions = $sp_set_id ? sp_get_set_questions( $sp_set_id ) : array();
			$sp_seconds   = sp_assignment_time_left( $sp_row, $sp_task );
			$sp_total_q   = count( $sp_questions );
			?>
			<div class="sp-quiz" data-sp-quiz>
				<div class="sp-quiz-layout">
					<div class="sp-quiz-content">
						<div class="sp-quiz-head">
							<h1 class="sp-quiz-title"><?php echo esc_html( $sp_task->title ); ?></h1>
							<div class="sp-quiz-meta">
								<span class="sp-quiz-timer" data-sp-timer><?php echo esc_html( sprintf( '%02d:%02d', floor( $sp_seconds / 60 ), $sp_seconds % 60 ) ); ?></span>
								<span class="sp-quiz-count">Question <span data-sp-current>1</span>/<?php echo esc_html( $sp_total_q ); ?></span>
							</div>
						</div>

						<div class="sp-quiz-progress">
							<div class="sp-quiz-progress-bar" data-sp-progress style="width:0%"></div>
						</div>

						<form class="sp-quiz-form" method="post"
							data-sp-exam data-seconds-left="<?php echo esc_attr( (int) $sp_seconds ); ?>"
							data-sp-total="<?php echo esc_attr( $sp_total_q ); ?>">
							<?php sp_portal_nonce_field( 'submit_assignment' ); ?>
							<input type="hidden" name="assignment_id" value="<?php echo esc_attr( $sp_id ); ?>" />

							<?php if ( ! empty( $sp_task->description ) ) : ?>
								<div class="sp-quiz-desc"><?php echo wp_kses_post( wpautop( $sp_task->description ) ); ?></div>
							<?php endif; ?>

							<?php foreach ( $sp_questions as $sp_index => $sp_question ) : ?>
								<div class="sp-quiz-question<?php echo 0 === $sp_index ? ' is-active' : ''; ?>"
									data-sp-question="<?php echo esc_attr( $sp_index ); ?>">
									<p class="sp-quiz-qnum">Question <?php echo esc_html( $sp_index + 1 ); ?>/<?php echo esc_html( $sp_total_q ); ?>:</p>
									<p class="sp-quiz-prompt"><?php echo esc_html( $sp_question->prompt ); ?></p>
									<div class="sp-quiz-options">
										<?php sp_exam_question_inputs( $sp_question ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
									</div>
								</div>
							<?php endforeach; ?>

							<div class="sp-quiz-nav">
								<button type="button" class="sp-btn" data-sp-prev disabled>&larr; Previous</button>
								<button type="button" class="sp-btn sp-btn-primary" data-sp-next>Next &rarr;</button>
								<button type="submit" class="sp-btn sp-btn-primary" data-sp-submit hidden>Submit exam</button>
							</div>
							<p class="sp-quiz-hint">You cannot change your answers once you submit.</p>
						</form>

						<?php // Sent on pagehide: leaving a running exam marks it failed. ?>
						<form id="sp-exam-leave" hidden>
							<?php sp_portal_nonce_field( 'leave_beacon' ); ?>
							<input type="hidden" name="assignment_id" value="<?php echo esc_attr( $sp_id ); ?>" />
						</form>
					</div>

					<aside class="sp-quiz-side" aria-label="Question navigator">
						<div class="sp-quiz-score" data-sp-score hidden>
							<span class="sp-quiz-score-pct" data-sp-score-pct></span>
							<span class="sp-quiz-score-label">answers correct</span>
						</div>
						<ol class="sp-quiz-navlist">
							<?php foreach ( $sp_questions as $sp_index => $sp_question ) : ?>
								<li>
									<button type="button" class="sp-quiz-navitem<?php echo 0 === $sp_index ? ' is-current' : ''; ?>"
										data-sp-goto="<?php echo esc_attr( $sp_index ); ?>">
										Question <?php echo esc_html( $sp_index + 1 ); ?>
									</button>
								</li>
							<?php endforeach; ?>
						</ol>
					</aside>
				</div>
			</div>

		<?php elseif ( 'submitted' === $sp_state ) : ?>
			<?php
			$sp_answers   = sp_get_attempt_answers( (int) $sp_row->id );
			$sp_score     = (int) $sp_row->score;
			$sp_total     = (int) $sp_row->total;
			$sp_percent   = $sp_total > 0 ? (int) round( ( $sp_score / $sp_total ) * 100 ) : 0;
			$sp_questions = $sp_set_id ? sp_get_set_questions( $sp_set_id ) : array();
			$sp_total_q   = count( $sp_questions );
			?>
			<div class="sp-quiz" data-sp-quiz>
				<div class="sp-quiz-layout">
					<div class="sp-quiz-content">
						<div class="sp-quiz-head">
							<h1 class="sp-quiz-title"><?php echo esc_html( $sp_task->title ); ?></h1>
							<div class="sp-quiz-meta">
								<span class="sp-quiz-count">Question <span data-sp-current>1</span>/<?php echo esc_html( $sp_total_q ); ?></span>
								<?php echo sp_assignment_state_badge( $sp_state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge markup. ?>
							</div>
						</div>

						<div class="sp-quiz-progress">
							<div class="sp-quiz-progress-bar" data-sp-progress style="width:100%"></div>
						</div>

						<?php if ( ! empty( $sp_row->submitted_at ) ) : ?>
							<p class="sp-quiz-submitted">Submitted <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $sp_row->submitted_at ) ) ); ?></p>
						<?php endif; ?>

						<div class="sp-quiz-form">
							<?php foreach ( $sp_questions as $sp_index => $sp_question ) : ?>
								<?php
								$sp_answer = isset( $sp_answers[ (int) $sp_question->id ] ) ? $sp_answers[ (int) $sp_question->id ] : null;
								$sp_given  = $sp_answer ? (string) $sp_answer->given : '';
								$sp_right  = $sp_answer && 1 === (int) $sp_answer->correct;
								$sp_opts   = sp_question_options( $sp_question );
								?>
								<div class="sp-quiz-question<?php echo 0 === $sp_index ? ' is-active' : ''; ?>"
									data-sp-question="<?php echo esc_attr( $sp_index ); ?>"
									data-sp-right="<?php echo $sp_right ? '1' : '0'; ?>">
									<p class="sp-quiz-qnum">Question <?php echo esc_html( $sp_index + 1 ); ?>/<?php echo esc_html( $sp_total_q ); ?>:</p>
									<p class="sp-quiz-prompt"><?php echo esc_html( $sp_question->prompt ); ?></p>
									<div class="sp-quiz-options">
										<?php if ( $sp_opts ) : ?>
											<?php foreach ( $sp_opts as $sp_key => $sp_opt ) : ?>
												<?php
												$sp_is_given  = ( (string) $sp_key === (string) $sp_given );
												$sp_is_answer = ( (string) $sp_key === (string) $sp_question->answer );
												$sp_opt_class = 'sp-quiz-option';

												if ( $sp_is_answer ) {
													$sp_opt_class .= ' is-correct';
												} elseif ( $sp_is_given ) {
													$sp_opt_class .= ' is-wrong';
												}
												?>
												<div class="<?php echo esc_attr( $sp_opt_class ); ?>">
													<span class="sp-quiz-option-mark">
														<?php if ( $sp_is_answer ) : ?>
															&#10003;
														<?php elseif ( $sp_is_given ) : ?>
															&#10007;
														<?php else : ?>
															&bull;
														<?php endif; ?>
													</span>
													<span class="sp-quiz-option-text"><?php echo esc_html( $sp_opt ); ?></span>
													<?php if ( $sp_is_answer ) : ?>
														<span class="sp-quiz-option-tag">Correct answer</span>
													<?php elseif ( $sp_is_given ) : ?>
														<span class="sp-quiz-option-tag">Your answer</span>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										<?php else : ?>
											<p class="sp-quiz-given">Your answer: <?php echo esc_html( sp_attempt_answer_label( $sp_question, $sp_given ) ); ?></p>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>

							<div class="sp-quiz-nav">
								<button type="button" class="sp-btn" data-sp-prev disabled>&larr; Previous</button>
								<button type="button" class="sp-btn sp-btn-primary" data-sp-next>Next &rarr;</button>
							</div>
						</div>
					</div>

					<aside class="sp-quiz-side" aria-label="Question navigator">
						<div class="sp-quiz-score is-visible">
							<span class="sp-quiz-score-pct"><?php echo esc_html( sp_format_number( $sp_percent ) ); ?>%</span>
							<span class="sp-quiz-score-label">You got <?php echo esc_html( $sp_score ); ?> answers correct</span>
						</div>
						<ol class="sp-quiz-navlist">
							<?php foreach ( $sp_questions as $sp_index => $sp_question ) : ?>
								<?php
								$sp_answer = isset( $sp_answers[ (int) $sp_question->id ] ) ? $sp_answers[ (int) $sp_question->id ] : null;
								$sp_right  = $sp_answer && 1 === (int) $sp_answer->correct;
								?>
								<li>
									<button type="button" class="sp-quiz-navitem<?php echo 0 === $sp_index ? ' is-current' : ''; ?><?php echo $sp_right ? ' is-right' : ' is-wrong'; ?>"
										data-sp-goto="<?php echo esc_attr( $sp_index ); ?>">
										<span class="sp-quiz-navmark"><?php echo $sp_right ? '&#10003;' : '&#10007;'; ?></span>
										Question <?php echo esc_html( $sp_index + 1 ); ?>
									</button>
								</li>
							<?php endforeach; ?>
						</ol>
					</aside>
				</div>
			</div>

		<?php elseif ( 'available' === $sp_state ) : ?>
			<?php
			$sp_questions = $sp_set_id ? sp_get_set_questions( $sp_set_id ) : array();
			?>
			<div class="sp-exam-page">
				<article class="sp-card sp-exam-start">
					<h1 class="sp-exam-start-title"><?php echo esc_html( $sp_task->title ); ?></h1>
					<p class="sp-exam-start-sub">
						<?php echo esc_html( $sp_task->class_title ); ?>
						&middot; <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_task->due_date ) ) ); ?>
					</p>

					<ul class="sp-exam-facts">
						<?php if ( $sp_questions ) : ?>
							<li><strong><?php echo esc_html( count( $sp_questions ) ); ?></strong> questions</li>
						<?php endif; ?>
						<?php if ( ! empty( $sp_task->time_limit_minutes ) ) : ?>
							<li><strong><?php echo esc_html( (int) $sp_task->time_limit_minutes ); ?></strong> minute limit</li>
						<?php endif; ?>
					</ul>

					<?php if ( ! empty( $sp_task->description ) ) : ?>
						<div class="sp-note-body">
							<?php echo wp_kses_post( wpautop( $sp_task->description ) ); ?>
						</div>
					<?php endif; ?>

					<form method="post" class="sp-exam-start-form">
						<?php sp_portal_nonce_field( 'start_assignment' ); ?>
						<input type="hidden" name="assignment_id" value="<?php echo esc_attr( $sp_id ); ?>" />
						<button type="submit" class="sp-btn sp-btn-primary sp-exam-start-btn">Start exam</button>
					</form>
					<p class="sp-exam-start-hint">Once you start, the clock keeps running until you submit — leaving the page counts as a failed attempt.</p>
				</article>
			</div>

		<?php elseif ( 'failed' === $sp_state ) : ?>
			<div class="sp-exam-page">
				<article class="sp-card sp-exam-start">
					<h1 class="sp-exam-start-title">This attempt was marked failed</h1>
					<p class="sp-exam-start-sub">You left the exam before submitting it.</p>
					<p class="sp-exam-start-hint">Your teacher can allow you to retake this exam.</p>
				</article>
			</div>

		<?php elseif ( 'upcoming' === $sp_state ) : ?>
			<div class="sp-exam-page">
				<article class="sp-card sp-exam-start">
					<h1 class="sp-exam-start-title">Scheduled for <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_task->due_date ) ) ); ?></h1>
					<p class="sp-exam-start-sub">You will be able to start this exam on that day.</p>
				</article>
			</div>

		<?php elseif ( 'missed' === $sp_state ) : ?>
			<div class="sp-exam-page">
				<article class="sp-card sp-exam-start">
					<h1 class="sp-exam-start-title">You did not sit this exam</h1>
					<p class="sp-exam-start-sub">It was scheduled for <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_task->due_date ) ) ); ?>.</p>
					<p class="sp-exam-start-hint">Ask your teacher if you need another chance.</p>
				</article>
			</div>

		<?php else : ?>
			<div class="sp-exam-page">
				<div class="sp-empty">
					<?php echo 'closed' === $sp_state ? 'This exam is closed.' : 'This exam is not available.'; ?>
				</div>
			</div>
		<?php endif; ?>

	<?php endif; ?>
<?php endif; ?>
