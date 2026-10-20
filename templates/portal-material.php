<?php
/**
 * One course material: detail and preview.
 *
 * Expects $profile from sp_render_portal() and sp_material_id in the query.
 * The list page only shows titles; opening one renders the full note body or
 * the embedded file preview here.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_material_id = (int) get_query_var( 'sp_material_id' );
$sp_material    = null;

if ( $profile && $sp_material_id ) {
	$sp_material = sp_get_material( $sp_material_id );

	if ( $sp_material && ! sp_student_can_access_class( $profile->id, (int) $sp_material->class_id ) ) {
		$sp_material = null;
	}
}

// The .docx preview runs off a hidden DOM contract that must be printed
// before the Mammoth bundle loads, so the page needs to know up front.
$sp_needs_docx = false;

if ( $sp_material && 'docx' === sp_material_embed_kind( $sp_material ) && sp_docx_preview_available() ) {
	$sp_needs_docx = true;
}
?>

<?php if ( ! $profile ) : ?>
	<section class="sp-task-message sp-empty" role="status">
		<span class="sp-task-message-icon" aria-hidden="true">i</span>
		<strong>Student profile not linked</strong>
		<p>This WordPress account is not linked to a student profile yet, so there is nothing to show here.</p>
	</section>
<?php elseif ( ! $sp_material ) : ?>
	<section class="sp-task-message sp-empty" role="status">
		<span class="sp-task-message-icon" aria-hidden="true">!</span>
		<strong>Material not available</strong>
		<p>That material is not available any more or you are not enrolled in its class.</p>
		<div class="sp-card-actions">
			<a class="sp-btn sp-btn-primary" href="<?php echo esc_url( sp_portal_url( 'materials' ) ); ?>">Back to materials</a>
		</div>
	</section>
<?php else : ?>
	<div class="sp-task-page sp-material-page">
		<article class="sp-card sp-material-card">
			<div class="sp-material-head">
				<div>
					<h1 class="sp-card-title"><?php echo esc_html( $sp_material->title ); ?></h1>
					<p class="sp-muted sp-material-meta">
						<span><?php echo esc_html( $sp_material->class_title ); ?></span>
						<?php if ( $sp_material->published_at ) : ?>
							<span class="sp-meta-separator" aria-hidden="true">/</span>
							<span><?php echo esc_html( sp_portal_time( get_option( 'date_format' ), strtotime( $sp_material->published_at ) ) ); ?></span>
						<?php endif; ?>
					</p>
				</div>
				<a class="sp-btn sp-btn-ghost" href="<?php echo esc_url( sp_portal_url( 'materials' ) ); ?>">Back to materials</a>
			</div>

			<?php $sp_is_file = ( 'file' === $sp_material->kind ); ?>

			<?php if ( ! $sp_is_file && $sp_material->content ) : ?>
				<div class="sp-note-body">
					<?php echo wp_kses_post( wpautop( $sp_material->content ) ); ?>
				</div>
			<?php elseif ( $sp_is_file ) : ?>
				<?php sp_material_embed( $sp_material ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>

				<?php if ( $sp_material->original_filename ) : ?>
					<p class="sp-muted sp-material-file">File: <?php echo esc_html( $sp_material->original_filename ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</article>
	</div>

	<?php if ( $sp_needs_docx ) : ?>
		<?php sp_docx_preview_host(); ?>
	<?php endif; ?>
<?php endif; ?>
