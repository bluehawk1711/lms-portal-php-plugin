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
						<div class="sp-result-score">
							<span style="padding-left: 0.5em;" class="sp-result-percentage"><?php echo esc_html( sp_format_number( $sp_row->percentage ) ); ?>%</span>
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
									<td data-label="Obtained"><?php echo esc_html( sp_format_number( $sp_row->theory_obtained ) ); ?></td>
									<td data-label="Maximum"><?php echo esc_html( sp_format_number( $sp_row->theory_max ) ); ?></td>
								</tr>
								<tr>
									<td data-label="Component">Practical</td>
									<td data-label="Obtained"><?php echo esc_html( sp_format_number( $sp_row->practical_obtained ) ); ?></td>
									<td data-label="Maximum"><?php echo esc_html( sp_format_number( $sp_row->practical_max ) ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>

					<?php if ( $sp_row->updated_at ) : ?>
						<p class="sp-muted">Last updated <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sp_row->updated_at ) ) ); ?>.</p>
					<?php endif; ?>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>