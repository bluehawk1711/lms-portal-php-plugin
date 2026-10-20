<?php
/**
 * ID card design settings and the generation screen.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ID Cards screen.
 */
function sp_admin_page_sp_id_cards() {
	$settings   = sp_idcard_settings();
	$students   = sp_get_profiles( array( 'per_page' => 500 ) );
	$selected   = isset( $_GET['sp_student'] ) ? (int) $_GET['sp_student'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$profile    = $selected ? sp_get_profile_by_id( $selected ) : null;

	if ( ! $profile && $students ) {
		$profile   = sp_get_profile_by_id( (int) $students[0]->id );
		$selected  = (int) $students[0]->id;
	}

	$uploads = array(
		'background_id' => array( 'label' => 'Background image', 'hint' => 'Full-card backdrop behind the content.' ),
		'corner_id'     => array( 'label' => 'Corner design', 'hint' => 'Placed in all four corners of the card.' ),
		'logo_id'       => array( 'label' => 'Logo', 'hint' => 'Shown in the header next to the institute name.' ),
		'qr_id'         => array( 'label' => 'QR code', 'hint' => 'The scan code printed at the bottom left.' ),
		'signature_id'  => array( 'label' => 'Signature', 'hint' => 'Director signature at the bottom right.' ),
	);
	?>
	<div class="sp-idcu-ui">
		<header class="sp-idcu-page-header">
			<div class="sp-idcu-page-heading">
				<span class="sp-idcu-eyebrow">STUDENT PORTAL <span aria-hidden="true">/</span> DOCUMENTS</span>
				<h1>ID cards</h1>
				<p>Design the card once, pick a student, then download the finished card as JPG or PDF. Cards are generated here in wp-admin only.</p>
			</div>
			<div class="sp-idcu-header-note">
				<span class="sp-idcu-header-mark" aria-hidden="true">▣</span>
				<span><strong>JPG + PDF</strong><small>Rendered in the browser</small></span>
			</div>
		</header>

		<div class="sp-idcu-layout">
			<section class="sp-idcu-panel sp-idcu-settings-panel" aria-labelledby="sp-idcu-settings-title">
				<div class="sp-idcu-panel-heading">
					<div>
						<span class="sp-idcu-section-kicker">DESIGN</span>
						<h2 id="sp-idcu-settings-title">Card design</h2>
						<p>Upload the artwork once — every preview and download picks it up.</p>
					</div>
					<span class="sp-idcu-heading-icon" aria-hidden="true">✎</span>
				</div>

				<form method="post" enctype="multipart/form-data" class="sp-idcu-form">
					<?php sp_admin_nonce_field( 'save_idcard_settings' ); ?>

					<?php foreach ( $uploads as $sp_key => $sp_upload ) : ?>
						<?php
						$sp_asset_id = isset( $settings[ $sp_key ] ) ? (int) $settings[ $sp_key ] : 0;
						$sp_thumb    = $sp_asset_id ? wp_get_attachment_image_url( $sp_asset_id, 'thumbnail' ) : '';
						?>
						<div class="sp-idcu-field">
							<label for="sp_idc_<?php echo esc_attr( $sp_key ); ?>"><?php echo esc_html( $sp_upload['label'] ); ?></label>
							<div class="sp-idcu-upload-row">
								<span class="sp-idcu-thumb<?php echo $sp_thumb ? '' : ' is-empty'; ?>">
									<?php if ( $sp_thumb ) : ?>
										<img src="<?php echo esc_url( $sp_thumb ); ?>" alt="" />
									<?php else : ?>
										<span aria-hidden="true">＋</span>
									<?php endif; ?>
								</span>
								<input type="file" id="sp_idc_<?php echo esc_attr( $sp_key ); ?>" name="<?php echo esc_attr( $sp_key ); ?>" accept="image/*" />
							</div>
							<p class="description">
								<?php echo esc_html( $sp_upload['hint'] ); ?>
								<?php if ( $sp_asset_id ) : ?>
									<label class="sp-idcu-clear"><input type="checkbox" name="clear_<?php echo esc_attr( $sp_key ); ?>" value="1" /> Remove current image</label>
								<?php endif; ?>
							</p>
						</div>
					<?php endforeach; ?>

					<div class="sp-idcu-divider"></div>
					<div class="sp-idcu-form-section-title"><span>02</span><div><strong>Card text</strong><small>Header, footer and signature lines.</small></div></div>

					<div class="sp-idcu-field">
						<label for="sp_idc_institute">Institute name</label>
						<input type="text" id="sp_idc_institute" name="institute_name" maxlength="120"
							value="<?php echo esc_attr( $settings['institute_name'] ); ?>" />
					</div>

					<div class="sp-idcu-field">
						<label for="sp_idc_tagline">Tagline</label>
						<input type="text" id="sp_idc_tagline" name="tagline" maxlength="180"
							value="<?php echo esc_attr( $settings['tagline'] ); ?>"
							placeholder="e.g. &quot;One Platform. Infinite Possibilities&quot;" />
					</div>

					<div class="sp-idcu-field">
						<label for="sp_idc_address">Bottom address</label>
						<textarea id="sp_idc_address" name="bottom_address" rows="2" maxlength="400"
							placeholder="Address printed on the bottom strip"><?php echo esc_textarea( $settings['bottom_address'] ); ?></textarea>
					</div>

					<div class="sp-idcu-field">
						<label for="sp_idc_phone">Bottom phone line</label>
						<input type="text" id="sp_idc_phone" name="phone_line" maxlength="80"
							value="<?php echo esc_attr( $settings['phone_line'] ); ?>"
							placeholder="e.g. +91-9929119729" />
					</div>

					<div class="sp-idcu-field">
						<label for="sp_idc_director">Signature label</label>
						<input type="text" id="sp_idc_director" name="director_name" maxlength="80"
							value="<?php echo esc_attr( $settings['director_name'] ); ?>" />
					</div>

					<div class="sp-idcu-field">
						<label for="sp_idc_qrlabel">QR label</label>
						<input type="text" id="sp_idc_qrlabel" name="qr_label" maxlength="60"
							value="<?php echo esc_attr( $settings['qr_label'] ); ?>" />
					</div>

					<button type="submit" class="button button-primary sp-idcu-submit">Save design <span aria-hidden="true">→</span></button>
				</form>
			</section>

			<section class="sp-idcu-panel sp-idcu-preview-panel" aria-labelledby="sp-idcu-preview-title">
				<div class="sp-idcu-panel-heading">
					<div>
						<span class="sp-idcu-section-kicker">GENERATE</span>
						<h2 id="sp-idcu-preview-title">Student card</h2>
						<p>Pick the student, check the card, then download it.</p>
					</div>
					<span class="sp-idcu-heading-icon" aria-hidden="true">▤</span>
				</div>

				<div class="sp-idcu-toolbar">
					<div class="sp-idcu-field sp-idcu-student-field">
						<label for="sp-idc-student">Student</label>
						<select id="sp-idc-student" data-sp-idc-select>
							<?php foreach ( $students as $sp_student ) : ?>
								<option value="<?php echo esc_attr( (int) $sp_student->id ); ?>" <?php selected( $selected, (int) $sp_student->id ); ?>>
									<?php echo esc_html( $sp_student->full_name . ' — ' . $sp_student->email ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<span class="sp-idcu-status<?php echo ( $profile && ! empty( $profile->idcard_generated_at ) ) ? ' is-done' : ''; ?>"
						data-sp-idc-status data-generated="<?php echo $profile && ! empty( $profile->idcard_generated_at ) ? esc_attr( (string) $profile->idcard_generated_at ) : ''; ?>">
						<?php echo ( $profile && ! empty( $profile->idcard_generated_at ) ) ? 'Generated' : 'Not generated'; ?>
					</span>
				</div>

				<div class="sp-idcu-stage" data-sp-idc-stage>
					<?php if ( $profile ) : ?>
						<?php sp_render_id_card( $profile ); ?>
					<?php else : ?>
						<p class="sp-idcu-empty">No students yet — create a student account first.</p>
					<?php endif; ?>
				</div>

				<div class="sp-idcu-actions">
					<button type="button" class="button button-primary sp-idcu-download" data-sp-idc-jpg>Download JPG</button>
					<button type="button" class="button sp-idcu-download" data-sp-idc-pdf>Download PDF</button>
					<span class="sp-idcu-hint">Downloads also mark the card as generated in the student records.</span>
				</div>
			</section>
		</div>

		<footer class="sp-idcu-page-footer">
			<span><span class="sp-idcu-footer-dot" aria-hidden="true"></span> ID cards</span>
			<span>Father’s name, address and photo are edited on the Student Accounts screen.</span>
		</footer>
	</div>
	<?php
}
