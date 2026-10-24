<?php
/**
 * Teacher curriculum tab: heading-groups with topics, cascade toggle.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_course = $sp_class->course_id ? sp_get_course((int) $sp_class->course_id) : null;
$sp_groups = $sp_course ? sp_get_curriculum_grouped((int) $sp_course->id) : [];
$sp_prog   = sp_get_class_progress($sp_class_id);
$sp_cov    = $sp_course ? sp_curriculum_summary((int) $sp_course->id, $sp_class_id) : ['total' => 0, 'done' => 0, 'percent' => 0];
$sp_topic_count = 0;
foreach ( $sp_groups as $sp_group ) {
	$sp_topic_count += count($sp_group['topics']);
}
?>
<?php if ( ! $sp_course ): ?>
<div class="sp-empty">This class has no course selected, so there is no outline to follow.</div>
<?php elseif ( empty( $sp_groups ) ): ?>
<div class="sp-empty">
	No outline has been added for <strong><?php echo esc_html($sp_course->name); ?></strong> yet.
	An administrator adds it under Student Portal &rarr; Courses.
</div>
<?php else: ?>
<div class="sp-curriculum">
	<div class="sp-curriculum-header">
		<div class="sp-curriculum-title-row">
			<h2 class="sp-curriculum-course-title"><?php echo esc_html($sp_course->name); ?></h2>
			<div class="sp-curriculum-completion">
				<?php if ( $sp_cov['total'] > 0 ): ?>
				<span class="sp-curriculum-percent-badge <?php echo $sp_cov['percent'] === 100 ? 'is-done' : 'is-progress'; ?>">
					<?php if ( $sp_cov['percent'] === 100 ): ?>
						<?php echo esc_html( '100% Completed' ); ?>
					<?php else: ?>
						<?php echo esc_html( 'In Progress - ' . $sp_cov['percent'] . '% Done' ); ?>
					<?php endif; ?>
				</span>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( ! empty( $sp_course->description ) ): ?>
		<p class="sp-curriculum-subtitle"><?php echo esc_html( $sp_course->description ); ?></p>
		<?php endif; ?>
		<div class="sp-curriculum-stats">
			<span class="sp-curriculum-stat">
				<span class="sp-curriculum-stat-num"><?php echo esc_html( $sp_cov['done'] ); ?></span>
				<span class="sp-curriculum-stat-label">of <?php echo esc_html( $sp_cov['total'] ); ?> Finished</span>
			</span>
		</div>
		<div class="sp-curriculum-meter">
			<div class="sp-curriculum-meter-bar">
				<span style="width: <?php echo esc_attr( $sp_cov['percent'] ); ?>%"></span>
			</div>
			<span class="sp-curriculum-meter-value">
				<?php echo esc_html( $sp_cov['done'] . ' / ' . $sp_cov['total'] . ' · ' . $sp_cov['percent'] . '%' ); ?>
			</span>
		</div>
	</div>

	<div class="sp-curriculum-groups">
		<?php foreach ( $sp_groups as $sp_index => $sp_group ): ?>
		<?php
			$sp_group_done = 0;
			foreach ( $sp_group['topics'] as $sp_topic ) {
				if ( isset( $sp_prog[ $sp_topic['id'] ] ) ) {
					$sp_group_done++;
				}
			}
			$sp_group_total = count($sp_group['topics']);
			$sp_group_percent = $sp_group_total > 0 ? (int) round( ($sp_group_done / $sp_group_total) * 100 ) : 0;
			$sp_is_group_done = $sp_group_percent === 100;
		?>
		<div class="sp-curriculum-group <?php echo $sp_is_group_done ? 'is-done' : ''; ?>"
			data-sp-curriculum-group="<?php echo esc_attr( $sp_group['id'] ); ?>">
			<div class="sp-curriculum-group-header">
				<div class="sp-curriculum-group-badge-row">
					<span class="sp-curriculum-badge sp-curriculum-badge-heading <?php echo $sp_is_group_done ? 'is-done' : 'is-number'; ?>">
						<?php echo esc_html( sprintf( '%02d', $sp_index + 1 ) ); ?>
					</span>
					<h3 class="sp-curriculum-group-title"><?php echo esc_html( $sp_group['group_title'] ?: $sp_group['title'] ); ?></h3>
				</div>
				<div class="sp-curriculum-group-meta">
					<?php if ( ! empty( $sp_group['topics'] ) ): ?>
					<p class="sp-curriculum-group-subtitle">
						<?php echo esc_html( 'Topics ' . ($sp_index * 7 + 1) . ' - ' . ($sp_index * 7 + $sp_group_total) ); ?>
					</p>
					<?php endif; ?>
					<div class="sp-curriculum-group-completion">
						<?php if ( $sp_is_group_done ): ?>
						<span class="sp-curriculum-status-badge sp-curriculum-status-done">
							<?php echo esc_html( '100% Completed' ); ?>
						</span>
						<?php else: ?>
						<span class="sp-curriculum-status-badge sp-curriculum-status-progress">
							<?php echo esc_html( 'In Progress - ' . $sp_group_percent . '% Done' ); ?>
						</span>
						<?php endif; ?>
					</div>
				</div>
				<div class="sp-curriculum-group-stats">
					<span class="sp-curriculum-group-stat">
						<?php echo esc_html( $sp_group_done . ' of ' . $sp_group_total . ' Finished' ); ?>
					</span>
				</div>
			</div>

			<div class="sp-curriculum-topics">
				<?php if ( empty( $sp_group['topics'] ) ): ?>
				<div class="sp-curriculum-empty-topics">No topics in this section yet.</div>
				<?php else: ?>
				<?php foreach ( $sp_group['topics'] as $sp_topic_index => $sp_topic ): ?>
				<?php
					$sp_topic_id = $sp_topic['id'];
					$sp_marked = isset( $sp_prog[ $sp_topic_id ] );
					$sp_completed_at = $sp_marked ? $sp_prog[ $sp_topic_id ]->completed_at : '';
					$sp_completed_by = $sp_marked && $sp_prog[ $sp_topic_id ]->completed_by ? get_userdata((int) $sp_prog[ $sp_topic_id ]->completed_by ) : false;
					$sp_item_position = $sp_topic_index + 1;
				?>
				<div class="sp-curriculum-topic <?php echo $sp_marked ? 'is-marked' : ''; ?>"
					data-sp-curriculum-topic="<?php echo esc_attr( $sp_topic_id ); ?>">
					<div class="sp-curriculum-topic-row">
						<span class="sp-curriculum-badge sp-curriculum-badge-topic <?php echo $sp_marked ? 'is-done' : 'is-number'; ?>">
							<?php echo esc_html( $sp_item_position ); ?>
						</span>
						<div class="sp-curriculum-topic-content">
							<h4 class="sp-curriculum-topic-title"><?php echo esc_html( $sp_topic['title'] ); ?></h4>
							<?php if ( ! empty( $sp_topic['detail'] ) ): ?>
							<p class="sp-curriculum-topic-detail"><?php echo esc_html( $sp_topic['detail'] ); ?></p>
							<?php endif; ?>
						</div>
						<div class="sp-curriculum-topic-status">
							<?php if ( $sp_marked ): ?>
							<span class="sp-curriculum-status-badge sp-curriculum-status-done">
								<svg class="sp-curriculum-check-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
									<path d="M20 6 9 17l-5-5" />
								</svg>
								<?php echo esc_html( 'Covered' ); ?>
							</span>
							<span class="sp-curriculum-date">
								<?php echo esc_html( sp_portal_time( get_option( 'date_format' ), strtotime( $sp_completed_at ) ) ); ?>
							</span>
							<?php else: ?>
							<span class="sp-curriculum-status-badge sp-curriculum-status-not-started">
								<?php echo esc_html( 'Not yet scheduled' ); ?>
							</span>
							<?php endif; ?>
						</div>
					</div>
					<div class="sp-curriculum-topic-actions">
						<form method="post" action="<?php echo esc_url($sp_base_url); ?>" data-sp-async>
							<?php sp_teacher_nonce_field('toggle_curriculum_item'); ?>
							<input type="hidden" name="class_id" value="<?php echo esc_attr( $sp_class_id ); ?>" />
							<input type="hidden" name="item_id" value="<?php echo esc_attr( $sp_topic_id ); ?>" />
							<button type="submit"
								class="sp-btn sp-curriculum-toggle-btn <?php echo $sp_marked ? 'sp-btn-ghost sp-curriculum-undo' : 'sp-btn-primary sp-curriculum-mark'; ?>">
								<?php echo $sp_marked ? 'Undo' : 'Mark covered'; ?>
							</button>
						</form>
					</div>
				</div>
				<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>
