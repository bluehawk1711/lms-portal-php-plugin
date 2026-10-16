<?php
/**
 * Student course materials.
 *
 * Documents are embedded on this page — there are no download links in the
 * student portal. Notes render as rich text, and every file goes through
 * sp_material_embed().
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $profile ) ) {
	?>
	<div class="sp-notice sp-notice-warn">
		This WordPress account is not linked to a student profile yet. Ask your institute to create your portal account.
	</div>
	<?php
	return;
}

$sp_materials = sp_get_student_materials( $profile->id, 100 );

// The .docx preview runs off a hidden DOM contract that must be printed
// before the Mammoth bundle loads, so the page needs to know up front
// whether any material will ask for it.
$sp_needs_docx = false;

foreach ( (array) $sp_materials as $sp_check ) {
	if ( 'docx' === sp_material_embed_kind( $sp_check ) && sp_docx_preview_available() ) {
		$sp_needs_docx = true;
		break;
	}
}
?>
<div class="sp-page-head">
	
	<p class="sp-page-sub">Notes and documents published by your teachers for the courses you are enrolled in.</p>
</div>

<?php if ( ! $sp_materials ) : ?>
	<div class="sp-empty">Nothing published yet. Your teachers will add notes and documents here.</div>
<?php else : ?>
	<div class="sp-card-list">
		<?php
		foreach ( $sp_materials as $sp_material ) :
			$sp_is_file = ( 'file' === $sp_material->kind );
			?>
			<article class="sp-card" id="material-<?php echo esc_attr( (int) $sp_material->id ); ?>">
				<div class="sp-material-head">
					<div>
						<h2 class="sp-card-title"><?php echo esc_html( $sp_material->title ); ?></h2>
						<p class="sp-muted">
							<?php echo esc_html( $sp_material->class_title ); ?>
							<?php if ( $sp_material->published_at ) : ?>
								&middot; <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_material->published_at ) ) ); ?>
							<?php endif; ?>
							<?php if ( $sp_is_file && $sp_material->file_size ) : ?>
								&middot; <?php echo esc_html( sp_format_file_size( $sp_material->file_size ) ); ?>
							<?php endif; ?>
						</p>
					</div>
				</div>

				<?php if ( ! $sp_is_file && $sp_material->content ) : ?>
					<div class="sp-note-body">
						<?php echo wp_kses_post( wpautop( $sp_material->content ) ); ?>
					</div>
				<?php endif; ?>					<?php if ( $sp_is_file ) : ?>
						<?php sp_material_embed( $sp_material ); ?>

						<?php if ( $sp_material->original_filename ) : ?>
							<p class="sp-muted sp-material-file">File: <?php echo esc_html( $sp_material->original_filename ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>

	<?php if ( $sp_needs_docx ) : ?>
		<?php sp_docx_preview_host(); ?>
	<?php endif; ?>
<?php endif; ?>
