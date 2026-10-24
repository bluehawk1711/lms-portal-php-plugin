<?php
/**
 * Student ID cards.
 *
 * The card is composed in wp-admin only: the admin picks a student, the card
 * renders from profile data plus the uploaded design assets (background,
 * corner design, logo, QR code, signature), and downloads are produced in the
 * browser as JPG or PDF. Generating records the timestamp that drives the
 * "Generated" badge on the student records screen.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option key holding the card design settings.
 */
define( 'SP_IDCARD_OPTION', 'sp_idcard_settings' );

/**
 * Preview width of the card, in CSS pixels. Must match `.sp-idc` in
 * admin-id-cards.css.
 *
 * Artwork positions are stored as percentages so they survive a resize, but no
 * percentage-of-container unit for font-size survives html2canvas, so type
 * sizes are converted to pixels against this width instead.
 */
define( 'SP_IDCARD_RENDER_WIDTH', 420 );

/**
 * Background shipped with the plugin: a complete card design (frame, header,
 * field labels, QR code and footer) at 790x1164.
 *
 * Artwork mode falls back to it so a freshly installed card already renders a
 * finished design before anyone uploads anything.
 */
define( 'SP_IDCARD_BUNDLED_BACKGROUND', 'assets/images/ID-card-v1_page-0001.jpg' );

/**
 * Director signature shipped with the plugin.
 *
 * Artwork mode prints it on the card where the design leaves room for it, so a
 * card looks finished before anyone uploads a signature of their own.
 */
define( 'SP_IDCARD_BUNDLED_SIGNATURE', 'ramesh sir singh.png' );

/**
 * Card design settings merged over their defaults.
 *
 * @return array background_id, corner_id, logo_id, qr_id, signature_id,
 *               institute_name, tagline, bottom_address, phone_line,
 *               director_name, qr_label, bottom_note and the artwork anchors
 *               (art_photo_*, art_value_*, art_rows, art_sign_*, art_note_*).
 */
function sp_idcard_settings() {
	$defaults = array(
		'background_id'  => 0,
		'corner_id'      => 0,
		'logo_id'        => 0,
		'qr_id'          => 0,
		'signature_id'   => 0,
		'institute_name' => get_bloginfo( 'name' ),
		'tagline'        => '',
		'bottom_address' => '',
		'phone_line'     => '',
		'director_name'  => 'Director',
		'qr_label'       => 'Website QR Code',

		/*
		 * Artwork mode: the background already carries the frame, header,
		 * field labels, QR code and footer, so the card only lays the photo
		 * and the field values on top of it.
		 *
		 * Positions are percentages of the card box (photo_left/top/size,
		 * value_left, art_rows) so they stay correct if the card is resized;
		 * art_value_size is a percentage of the card width. Defaults are
		 * measured from SP_IDCARD_BUNDLED_BACKGROUND: the photo ring is
		 * centred on (394, 317) of a 790x1164 canvas with a 352px interior,
		 * the value column starts just past the printed colon at x=402, and
		 * art_rows holds the vertical centre of each printed label row.
		 */
		'background_mode' => 'artwork',
		'art_photo_left'  => 27.59,
		'art_photo_top'   => 12.11,
		'art_photo_size'  => 44.56,
		'art_value_left'  => 50.89,
		'art_value_size'  => 3.66,
		'art_value_color' => '#1a1a18',
		'art_rows'        => array( 47.62, 52.18, 56.22, 60.25, 63.86 ),

		/*
		 * The signature sits in the empty right-hand block of the artwork,
		 * beside the QR code and above the footer strip, with a rule and the
		 * name drawn under it. The bundled sign is 685x338 and its ink is not
		 * in the middle of that canvas, so the numbers below are measured on
		 * the printed strokes: the box starts at x=490 and the ink runs from
		 * x=496 to x=697 and from y=840 to y=933 of the 790x1164 artwork.
		 * The rule then falls at y=940 and the name at y=944, which leaves
		 * the footer's own line at y=1040 clear.
		 */
		'art_sign_left'  => 62.0,
		'art_sign_top'   => 70.56,
		'art_sign_width' => 30.0,

		/*
		 * The bottom line: every word of it lives in the one field below and
		 * is printed in red, bold, centred, just under the address the artwork
		 * prints (that text ends at y=1105 of the 790x1164 design, and the
		 * bottom border starts at y=1154, so the line drops into the empty
		 * band between them). top and size are the same kind of percentage
		 * anchors as the signature; size is a share of the card width because
		 * html2canvas drops container-relative font sizes.
		 */
		'bottom_note'    => '',
		'art_note_top'   => 96.2,
		'art_note_size'  => 1.9,
		'art_note_color' => '#d1131a',
	);

	$stored = get_option( SP_IDCARD_OPTION, array() );

	return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
}

/**
 * Image URL for one uploaded design asset.
 *
 * @param string $key Settings key ending in _id.
 * @return string URL, or '' when nothing is uploaded.
 */
function sp_idcard_asset_url( $key ) {
	$settings = sp_idcard_settings();
	$id       = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;

	return $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '';
}

/**
 * Whether the card is composed as a complete artwork.
 *
 * "artwork" means the background already contains the frame, header, field
 * labels, QR code and footer, so the card only overlays the photo and the field
 * values. "frame" keeps the generated layout the plugin draws itself, which is
 * what cards made before this mode existed used.
 *
 * @return bool
 */
function sp_idcard_is_artwork() {
	$settings = sp_idcard_settings();

	return 'frame' !== $settings['background_mode'];
}

/**
 * Background image the card actually draws.
 *
 * Artwork mode falls back to the design shipped with the plugin, so switching
 * a card to artwork mode always has something complete to render.
 *
 * @return string URL, or '' when there is no background to draw.
 */
function sp_idcard_background_url() {
	$background = sp_idcard_asset_url( 'background_id' );

	if ( $background ) {
		return $background;
	}

	if ( sp_idcard_is_artwork() ) {
		// SP_URL, not plugins_url(): that helper resolves against this file's
		// own directory, which would produce .../includes/assets/images/...
		return SP_URL . SP_IDCARD_BUNDLED_BACKGROUND;
	}

	return '';
}

/**
 * Signature image the card actually prints.
 *
 * An uploaded signature always wins. In artwork mode the bundled sign is used
 * when nothing is uploaded, so the card carries a signature out of the box;
 * the plain backdrop keeps its own empty signature slot.
 *
 * @return string URL, or '' when there is no signature to draw.
 */
function sp_idcard_signature_url() {
	$signature = sp_idcard_asset_url( 'signature_id' );

	if ( $signature ) {
		return $signature;
	}

	if ( sp_idcard_is_artwork() ) {
		// rawurlencode: the file name has spaces in it.
		return SP_URL . 'assets/images/' . rawurlencode( SP_IDCARD_BUNDLED_SIGNATURE );
	}

	return '';
}

/**
 * Intrinsic size of the background, used to lock the card's aspect ratio.
 *
 * Matching the card box to the artwork keeps every percentage-based overlay on
 * its printed anchor; without it `background-size: cover` would crop the design
 * and drag the photo off the printed ring.
 *
 * @return array{0:int,1:int} Width and height. Falls back to the bundled
 *                            design's 790x1164 when the attachment cannot be
 *                            measured.
 */
function sp_idcard_background_size() {
	$settings = sp_idcard_settings();
	$id       = isset( $settings['background_id'] ) ? (int) $settings['background_id'] : 0;

	if ( $id ) {
		$src = wp_get_attachment_image_src( $id, 'full' );

		if ( $src && ! empty( $src[1] ) && ! empty( $src[2] ) ) {
			return array( (int) $src[1], (int) $src[2] );
		}
	}

	return array( 790, 1164 );
}

/**
 * Vertical centre of each printed label row, as a percentage of card height.
 *
 * Always five rows — Name, Father's Name, Emergency No, Enrollment No and
 * Address — padded or trimmed to match the artwork.
 *
 * @return float[]
 */
function sp_idcard_art_rows() {
	$settings = sp_idcard_settings();
	$rows     = isset( $settings['art_rows'] ) && is_array( $settings['art_rows'] ) ? $settings['art_rows'] : array();

	$rows = array_slice( array_map( 'floatval', array_values( $rows ) ), 0, 5 );

	while ( count( $rows ) < 5 ) {
		$rows[] = 0.0;
	}

	return $rows;
}

/**
 * Enrolment number shown on the card: the active enrolment wins, then the
 * legacy profile-level ID kept by older installs.
 *
 * @param object $profile Student profile row.
 * @return string
 */
function sp_idcard_enroll_number( $profile ) {
	$enrols = sp_get_enrollments_for_profile( $profile->id, array( 'active' ) );

	if ( $enrols ) {
		foreach ( $enrols as $enrol ) {
			if ( ! empty( $enrol->enroll_id ) ) {
				return (string) $enrol->enroll_id;
			}
		}
	}

	if ( ! empty( $enrols[0]->enroll_id ) ) {
		return (string) $enrols[0]->enroll_id;
	}

	// Legacy fallback: the profile used to carry a single ID.
	if ( isset( $profile->enroll_id ) && '' !== trim( (string) $profile->enroll_id ) ) {
		return trim( (string) $profile->enroll_id );
	}

	return '';
}

/**
 * Print one student's ID card.
 *
 * Markup is static HTML/CSS so html2canvas can rasterise it for the JPG/PDF
 * downloads in the admin screen.
 *
 * @param object $profile Student profile row.
 */
function sp_render_id_card( $profile ) {
	if ( ! $profile ) {
		return;
	}

	$settings   = sp_idcard_settings();
	$background = sp_idcard_background_url();
	$corner     = sp_idcard_asset_url( 'corner_id' );
	$logo       = sp_idcard_asset_url( 'logo_id' );
	$qr         = sp_idcard_asset_url( 'qr_id' );
	$signature  = sp_idcard_signature_url();
	$photo      = ! empty( $profile->photo_id ) ? (string) wp_get_attachment_image_url( (int) $profile->photo_id, 'medium' ) : '';
	$father     = ! empty( $profile->father_name ) ? (string) $profile->father_name : '';
	$address    = ! empty( $profile->address ) ? (string) $profile->address : '';
	$phone      = ! empty( $profile->phone ) ? (string) $profile->phone : '';
	$enroll     = sp_idcard_enroll_number( $profile );
	$name       = (string) $profile->full_name;
	$initials   = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : strtoupper( substr( $name, 0, 1 ) );

	// The five printed rows, in the order the artwork lists them. Values are
	// escaped where they are output rather than here.
	$fields = array(
		array( 'label' => 'Name',          'value' => $name ),
		array( 'label' => "Father's Name", 'value' => $father ),
		array( 'label' => 'Emergency No',  'value' => $phone ),
		array( 'label' => 'Enrollment No', 'value' => $enroll ),
		array( 'label' => 'Address',       'value' => $address ),
	);

	/*
	 * Artwork mode lays only the photo and the values over a background that
	 * already carries the frame, header, labels, QR code and footer. The card
	 * box is locked to the artwork's aspect ratio so the percentage anchors
	 * below land on their printed positions instead of being cropped by
	 * background-size: cover.
	 */
	$art       = sp_idcard_is_artwork() && '' !== $background;
	$artsize   = $art ? sp_idcard_background_size() : array( 0, 0 );
	$artrows   = $art ? sp_idcard_art_rows() : array();
	$artfont   = $art ? (int) round( SP_IDCARD_RENDER_WIDTH * (float) $settings['art_value_size'] / 100 ) : 0;
	$artnote   = $art ? (int) round( SP_IDCARD_RENDER_WIDTH * (float) $settings['art_note_size'] / 100 ) : 0;
	$artline   = $art ? trim( (string) $settings['bottom_note'] ) : '';
	$cardstyle = $art ? sprintf( 'aspect-ratio: %d / %d;', $artsize[0], $artsize[1] ) : '';

	// The name under the signature rule, and its size in the rendered card.
	$signlabel = trim( (string) $settings['director_name'] );

	if ( '' === $signlabel ) {
		$signlabel = 'Director';
	}

	// A percentage of the card width, turned into pixels: html2canvas does
	// not keep container-relative font sizes.
	$signfont  = $art ? (int) round( SP_IDCARD_RENDER_WIDTH * 0.026 ) : 0;
	?>
	<div class="sp-idc<?php echo $art ? ' is-artwork' : ''; ?>" data-sp-idc<?php echo $cardstyle ? ' style="' . esc_attr( $cardstyle ) . '"' : ''; ?>>
		<?php if ( $background ) : ?>
			<div class="sp-idc-bg" style="background-image: url('<?php echo esc_url( $background ); ?>');"></div>
		<?php endif; ?>

		<?php if ( $art ) : ?>
			<?php
			/*
			 * Photo inside the printed ring, and the values beside the printed
			 * colons. Both are anchored as a percentage of the card box, which
			 * matches the artwork pixel for pixel; each row is placed on its
			 * own printed baseline because the artwork's rows are not evenly
			 * spaced.
			 */
			?>
			<div class="sp-idc-photo-wrap sp-idc-art-photo" style="left: <?php echo esc_attr( $settings['art_photo_left'] ); ?>%; top: <?php echo esc_attr( $settings['art_photo_top'] ); ?>%; width: <?php echo esc_attr( $settings['art_photo_size'] ); ?>%;">
				<div class="sp-idc-photo">
					<?php if ( $photo ) : ?>
						<img src="<?php echo esc_url( $photo ); ?>" alt="" />
					<?php else : ?>
						<span class="sp-idc-initials"><?php echo esc_html( $initials ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<dl class="sp-idc-fields" style="left: <?php echo esc_attr( $settings['art_value_left'] ); ?>%; font-size: <?php echo (int) $artfont; ?>px; color: <?php echo esc_attr( $settings['art_value_color'] ); ?>;">
				<?php foreach ( $fields as $sp_i => $sp_field ) : ?>
					<div class="sp-idc-row" style="top: <?php echo esc_attr( $artrows[ $sp_i ] ); ?>%;">
						<dt><?php echo esc_html( $sp_field['label'] ); ?></dt>
						<dd><?php echo '' !== trim( (string) $sp_field['value'] ) ? esc_html( $sp_field['value'] ) : '&mdash;'; ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>

			<?php if ( $signature ) : ?>
				<?php
				/*
				 * The signature is the one design element the artwork cannot
				 * print for us: it is a real hand-written sign, so it is laid
				 * over the design as an image, with a rule and the name
				 * under it. The whole group hangs off one percentage box, so
				 * the line and the name always sit under the sign, whatever
				 * the sign's own proportions are.
				 */
				?>
				<div class="sp-idc-art-signature"
					style="left: <?php echo esc_attr( $settings['art_sign_left'] ); ?>%; top: <?php echo esc_attr( $settings['art_sign_top'] ); ?>%; width: <?php echo esc_attr( $settings['art_sign_width'] ); ?>%; color: <?php echo esc_attr( $settings['art_value_color'] ); ?>;">
					<img class="sp-idc-art-sign" src="<?php echo esc_url( $signature ); ?>" alt="" />
					<span class="sp-idc-art-sign-rule" aria-hidden="true"></span>
					<span class="sp-idc-art-sign-label" style="font-size: <?php echo (int) $signfont; ?>px;"><?php echo esc_html( $signlabel ); ?></span>
				</div>
			<?php endif; ?>

			<?php
			/*
			 * The bottom line: one field, every word in it, printed red and
			 * bold just under the address the artwork already carries. It is
			 * anchored like the other artwork overlays so a resized card keeps
			 * it in the same place.
			 */
			?>
			<?php if ( '' !== $artline ) : ?>
				<p class="sp-idc-art-note"
					style="top: <?php echo esc_attr( $settings['art_note_top'] ); ?>%; font-size: <?php echo (int) $artnote; ?>px; color: <?php echo esc_attr( $settings['art_note_color'] ); ?>;"><?php echo esc_html( $artline ); ?></p>
			<?php endif; ?>
		<?php else : ?>

		<?php if ( $corner ) : ?>
			<span class="sp-idc-corner sp-idc-corner-tl" style="background-image: url('<?php echo esc_url( $corner ); ?>');"></span>
			<span class="sp-idc-corner sp-idc-corner-tr" style="background-image: url('<?php echo esc_url( $corner ); ?>');"></span>
			<span class="sp-idc-corner sp-idc-corner-bl" style="background-image: url('<?php echo esc_url( $corner ); ?>');"></span>
			<span class="sp-idc-corner sp-idc-corner-br" style="background-image: url('<?php echo esc_url( $corner ); ?>');"></span>
		<?php else : ?>
			<span class="sp-idc-wedge sp-idc-wedge-tl" aria-hidden="true"></span>
			<span class="sp-idc-wedge sp-idc-wedge-tr" aria-hidden="true"></span>
			<span class="sp-idc-wedge sp-idc-wedge-bl" aria-hidden="true"></span>
			<span class="sp-idc-wedge sp-idc-wedge-br" aria-hidden="true"></span>
		<?php endif; ?>

		<header class="sp-idc-head">
			<?php if ( $logo ) : ?>
				<img class="sp-idc-logo" src="<?php echo esc_url( $logo ); ?>" alt="" />
			<?php endif; ?>
			<div class="sp-idc-brand">
				<h1 class="sp-idc-name"><?php echo esc_html( $settings['institute_name'] ); ?></h1>
				<?php if ( '' !== trim( (string) $settings['tagline'] ) ) : ?>
					<p class="sp-idc-tagline"><?php echo esc_html( $settings['tagline'] ); ?></p>
				<?php endif; ?>
			</div>
		</header>

		<div class="sp-idc-photo-wrap">
			<div class="sp-idc-photo">
				<?php if ( $photo ) : ?>
					<img src="<?php echo esc_url( $photo ); ?>" alt="" />
				<?php else : ?>
					<span class="sp-idc-initials"><?php echo esc_html( $initials ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<dl class="sp-idc-fields">
			<?php foreach ( $fields as $sp_field ) : ?>
				<div class="sp-idc-row">
					<dt><?php echo esc_html( $sp_field['label'] ); ?></dt>
					<dd><?php echo '' !== trim( (string) $sp_field['value'] ) ? esc_html( $sp_field['value'] ) : '&mdash;'; ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>

		<div class="sp-idc-sign-area">
			<div class="sp-idc-qr">
				<?php if ( $qr ) : ?>
					<img src="<?php echo esc_url( $qr ); ?>" alt="" />
				<?php else : ?>
					<span class="sp-idc-qr-blank" aria-hidden="true"></span>
				<?php endif; ?>
				<span class="sp-idc-qr-label"><?php echo esc_html( $settings['qr_label'] ); ?></span>
			</div>

			<div class="sp-idc-sign">
				<span class="sp-idc-sign-art">
					<?php if ( $signature ) : ?>
						<img src="<?php echo esc_url( $signature ); ?>" alt="" />
					<?php endif; ?>
				</span>
				<span class="sp-idc-sign-rule" aria-hidden="true"></span>
				<span class="sp-idc-sign-label"><?php echo esc_html( $settings['director_name'] ); ?></span>
			</div>
		</div>

		<footer class="sp-idc-strip">
			<?php if ( '' !== trim( (string) $settings['bottom_address'] ) ) : ?>
				<p class="sp-idc-strip-addr"><?php echo esc_html( $settings['bottom_address'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) $settings['phone_line'] ) ) : ?>
				<p class="sp-idc-strip-phone"><span aria-hidden="true">&#9742;</span> <?php echo esc_html( $settings['phone_line'] ); ?></p>
			<?php endif; ?>
		</footer>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Record that the ID card was generated for this student.
 *
 * @param int $profile_id sp_student_profiles.id.
 * @return string The stored timestamp, or '' on failure.
 */
function sp_mark_id_card_generated( $profile_id ) {
	global $wpdb;

	$profile_id = (int) $profile_id;

	if ( ! $profile_id || ! sp_get_profile_by_id( $profile_id ) ) {
		return '';
	}

	$now    = current_time( 'mysql' );
	$stored = $wpdb->get_var(
		$wpdb->prepare( 'SELECT idcard_generated_at FROM ' . sp_table( 'student_profiles' ) . ' WHERE id = %d', $profile_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
	);

	if ( empty( $stored ) ) {
		$wpdb->update(
			sp_table( 'student_profiles' ),
			array( 'idcard_generated_at' => $now ),
			array( 'id' => $profile_id ),
			array( '%s' ),
			array( '%d' )
		);

		$profile = sp_get_profile_by_id( $profile_id );

		sp_log_activity(
			'idcard_generated',
			'student',
			$profile_id,
			'ID card generated for ' . ( $profile ? $profile->full_name : '#' . $profile_id )
		);

		return $now;
	}

	return (string) $stored;
}

/**
 * Admin-ajax: render the card preview for one student.
 */
function sp_ajax_idcard_preview() {
	check_ajax_referer( 'sp_idcard', 'nonce' );

	if ( ! current_user_can( 'manage_certificates' ) ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to do that.' ), 403 );
	}

	$profile_id = isset( $_POST['profile_id'] ) ? (int) $_POST['profile_id'] : 0;
	$profile    = $profile_id ? sp_get_profile_by_id( $profile_id ) : null;

	if ( ! $profile ) {
		wp_send_json_error( array( 'message' => 'That student no longer exists.' ), 404 );
	}

	ob_start();
	sp_render_id_card( $profile );
	$html = ob_get_clean();

	wp_send_json_success(
		array(
			'html'         => $html,
			'name'         => (string) $profile->full_name,
			'generated_at' => ! empty( $profile->idcard_generated_at ) ? (string) $profile->idcard_generated_at : '',
		)
	);
}

/**
 * Admin-ajax: flag the current student's card as generated.
 */
function sp_ajax_idcard_mark() {
	check_ajax_referer( 'sp_idcard', 'nonce' );

	if ( ! current_user_can( 'manage_certificates' ) ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to do that.' ), 403 );
	}

	$profile_id = isset( $_POST['profile_id'] ) ? (int) $_POST['profile_id'] : 0;
	$generated  = $profile_id ? sp_mark_id_card_generated( $profile_id ) : '';

	if ( '' === $generated ) {
		wp_send_json_error( array( 'message' => 'That student no longer exists.' ), 404 );
	}

	wp_send_json_success( array( 'generated_at' => $generated ) );
}
add_action( 'wp_ajax_sp_idcard_preview', 'sp_ajax_idcard_preview' );
add_action( 'wp_ajax_sp_idcard_mark', 'sp_ajax_idcard_mark' );
