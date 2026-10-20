<?php
/**
 * Student results.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $profile ) ) {
	?>
<div class="sp-notice sp-notice-warn">
    This account is not linked to a student profile yet. Ask your institute to create your account.
</div>
<?php
	return;
}

$sp_results = sp_get_student_results( $profile->id );
?>
<div class="sp-page-head">

    <p class="sp-page-sub">Published results for each course.</p>
</div>

<?php if ( ! $sp_results ) : ?>
<div class="sp-empty">No results yet.</div>
<?php else : ?>
<div class="sp-card-list">
    <?php
		foreach ( $sp_results as $sp_row ) :
			$sp_finalised = $sp_row->percentage !== null && (int) $sp_row->finalised === 1;
			?>
    <article class="sp-card">
        <h2 class="sp-card-title"><?php echo esc_html( $sp_row->class_title ); ?></h2>

        <?php if ( ! $sp_finalised ) : ?>
        <p class="sp-muted">Your result has not been published yet.</p>
        <?php else : ?>
        <div class="sp-result-summary">
            <div style=" padding-top: 0.75em; padding-bottom: 0.75em;" class="sp-result-score">
                <span style="padding-left: 0.5em; "
                    class="sp-result-percentage"><?php echo esc_html( sp_format_number( $sp_row->percentage ) ); ?>%</span>
                <span class="sp-result-label">Overall</span>
            </div>
            <div class="sp-result-meta">
                <?php echo wp_kses_post( sp_result_badge( $sp_row->result ) ); ?>
                <?php if ( $sp_row->grade ) : ?>
                <span class="sp-badge sp-badge-grade">Grade <?php echo esc_html( $sp_row->grade ); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th scope="col">Component</th>
                        <th scope="col">Obtained</th>
                        <th scope="col">Maximum</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td data-label="Component">Theory</td>
                        <td data-label="Obtained">
                            <?php echo esc_html( sp_format_number( $sp_row->theory_obtained ) ); ?></td>
                        <td data-label="Maximum"><?php echo esc_html( sp_format_number( $sp_row->theory_max ) ); ?></td>
                    </tr>
                    <tr>
                        <td data-label="Component">Practical</td>
                        <td data-label="Obtained">
                            <?php echo esc_html( sp_format_number( $sp_row->practical_obtained ) ); ?></td>
                        <td data-label="Maximum"><?php echo esc_html( sp_format_number( $sp_row->practical_max ) ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if ( $sp_row->updated_at ) : ?>
        <p class="sp-muted">Last updated
            <?php echo esc_html( sp_portal_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sp_row->updated_at ) ) ); ?>.
        </p>
        <?php endif; ?>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
/*
 * Exam reviews: every submitted exam with the correct answer and the
 * teacher's explanation for each question, right on the Results screen.
 */
$sp_exams = array();

foreach ( sp_get_assignments_for_profile( $profile->id ) as $sp_exam_task ) {
	if ( 'exam' !== $sp_exam_task->type ) {
		continue;
	}

	$sp_exam_row = ! empty( $sp_exam_task->student_row_id )
		? sp_get_student_assignment( (int) $sp_exam_task->id, (int) $profile->id )
		: null;

	if ( $sp_exam_row && 'submitted' === $sp_exam_row->status ) {
		$sp_exams[] = array(
			'task' => $sp_exam_task,
			'row'  => $sp_exam_row,
		);
	}
}
?>

<?php if ( $sp_exams ) : ?>
<section class="sp-rx-section" aria-labelledby="sp-rx-title">
    <div class="sp-rx-head">
        <h2 id="sp-rx-title">Exam reviews</h2>
        <p>Your submitted exams, with the correct answer and explanation for every question.</p>
    </div>

    <?php foreach ( $sp_exams as $sp_exam ) : ?>
    <?php
			$sp_exam_task = $sp_exam['task'];
			$sp_exam_row  = $sp_exam['row'];
			$sp_exam_set  = ! empty( $sp_exam_row->set_id ) ? (int) $sp_exam_row->set_id : 0;
			$sp_exam_qs   = $sp_exam_set ? sp_get_set_questions( $sp_exam_set ) : array();
			$sp_exam_ans  = sp_get_attempt_answers( (int) $sp_exam_row->id );
			$sp_exam_pct  = (int) $sp_exam_row->total > 0 ? (int) round( ( (int) $sp_exam_row->score / (int) $sp_exam_row->total ) * 100 ) : 0;
			?>
    <details class="sp-rx-item">
        <summary class="sp-rx-summary">
            <span class="sp-rx-summary-main">
                <strong><?php echo esc_html( $sp_exam_task->title ); ?></strong>
                <span class="sp-rx-meta">
                    <?php if ( ! empty( $sp_exam_row->submitted_at ) ) : ?>
                    Submitted
                    <?php echo esc_html( sp_portal_time( get_option( 'date_format' ), strtotime( $sp_exam_row->submitted_at ) ) ); ?>
                    <?php endif; ?>
                    <?php if ( ! empty( $sp_exam_task->class_title ) ) : ?>
                    <span aria-hidden="true">/</span> <?php echo esc_html( $sp_exam_task->class_title ); ?>
                    <?php endif; ?>
                </span>
            </span>
            <span class="sp-rx-score<?php echo $sp_exam_pct >= 40 ? ' is-good' : ''; ?>">
                <strong><?php echo esc_html( $sp_exam_row->score ); ?>/<?php echo esc_html( $sp_exam_row->total ); ?></strong>
                <span><?php echo esc_html( sp_format_number( $sp_exam_pct ) ); ?>%</span>
            </span>
        </summary>

        <div class="sp-rx-body">
            <?php if ( ! $sp_exam_qs ) : ?>
            <p class="sp-rx-empty">No questions are attached to this exam any more.</p>
            <?php else : ?>
            <?php foreach ( $sp_exam_qs as $sp_i => $sp_q ) : ?>
            <?php
							$sp_ans   = isset( $sp_exam_ans[ (int) $sp_q->id ] ) ? $sp_exam_ans[ (int) $sp_q->id ] : null;
							$sp_given = $sp_ans ? (string) $sp_ans->given : '';
							$sp_ok    = $sp_ans && 1 === (int) $sp_ans->correct;
							?>
            <div class="sp-rx-q<?php echo $sp_ans ? ( $sp_ok ? ' is-ok' : ' is-bad' ) : ' is-miss'; ?>">
                <div class="sp-rx-q-top">
                    <span class="sp-rx-q-num">Question <?php echo esc_html( $sp_i + 1 ); ?></span>
                    <span
                        class="sp-rx-pill"><?php echo $sp_ok ? 'Correct' : ( $sp_ans ? 'Incorrect' : 'Not answered' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed strings. ?></span>
                </div>
                <p class="sp-rx-q-prompt"><?php echo esc_html( $sp_q->prompt ); ?></p>
                <div class="sp-rx-answers">
                    <span class="sp-rx-answer">
                        <em>Your answer</em>
                        <strong><?php echo '' !== $sp_given ? esc_html( sp_attempt_answer_label( $sp_q, $sp_given ) ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?></strong>
                    </span>
                    <span class="sp-rx-answer is-correct">
                        <em>Correct answer</em>
                        <strong><?php echo esc_html( sp_attempt_correct_label( $sp_q ) ); ?></strong>
                    </span>
                </div>
                <?php if ( ! empty( $sp_q->explanation ) ) : ?>
                <div class="sp-rx-explain">
                    <span class="sp-rx-explain-label">Explanation</span>
                    <p><?php echo esc_html( $sp_q->explanation ); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <a class="sp-rx-open"
                href="<?php echo esc_url( sp_portal_url( 'tasks/' . (int) $sp_exam_task->id ) ); ?>">Open full review
                <span aria-hidden="true">&#8594;</span></a>
        </div>
    </details>
    <?php endforeach; ?>
</section>
<?php endif; ?>