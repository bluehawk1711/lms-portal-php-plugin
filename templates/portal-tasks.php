<?php
/**
 * Tasks: every paper and exam a student can see.
 *
 * Expects $profile from sp_render_portal().
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_tasks = $profile ? sp_get_assignments_for_profile( $profile->id ) : array();
?>
<div class="sp-page-head">
	
	<p class="sp-page-sub">Papers from your teachers and the exams scheduled for your classes.</p>
</div>

<?php if ( ! $profile ) : ?>
	<div class="sp-empty">This WordPress account is not linked to a student profile yet, so there is nothing to show here.</div>
<?php elseif ( ! $sp_tasks ) : ?>
	<div class="sp-empty">Nothing assigned yet. Your teachers will post papers and exams here.</div>
<?php else : ?>
	<div class="sp-card-list">
		<?php foreach ( $sp_tasks as $sp_task ) : ?>
			<?php
			$sp_row = null;

			if ( ! empty( $sp_task->student_row_id ) ) {
				// The join carries only what the feed-state needs.
				$sp_row = (object) array(
					'id'             => (int) $sp_task->student_row_id,
					'status'         => $sp_task->student_status,
					'retake_granted' => (int) $sp_task->student_retake_granted,
					'started_at'     => $sp_task->student_started_at,
				);
			}

			$sp_state      = sp_assignment_student_state( $sp_task, $sp_row );
			$sp_url        = sp_portal_url( 'tasks/' . (int) $sp_task->id );
			$sp_date_label = 'exam' === $sp_task->type ? 'Exam day' : 'Due';
			?>
			<article class="sp-card" id="task-<?php echo esc_attr( (int) $sp_task->id ); ?>">
				<div class="sp-material-head">
					<div>
						<h2 class="sp-card-title">
							<a href="<?php echo esc_url( $sp_url ); ?>"><?php echo esc_html( $sp_task->title ); ?></a>
						</h2>
						<p class="sp-muted">
							<?php echo esc_html( $sp_task->class_title ); ?>
							&middot; <?php echo esc_html( sp_assignment_type_label( $sp_task->type ) ); ?>
							<?php if ( ! empty( $sp_task->due_date ) ) : ?>
								&middot; <?php echo esc_html( $sp_date_label ); ?>:
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_task->due_date ) ) ); ?>
							<?php endif; ?>
						</p>
					</div>
				</div>

				<div class="sp-card-actions">
					<?php echo sp_assignment_state_badge( $sp_state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge markup. ?>
					<a class="sp-btn sp-btn-primary" href="<?php echo esc_url( $sp_url ); ?>">Open</a>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
