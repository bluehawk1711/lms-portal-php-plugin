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
 * Card design settings merged over their defaults.
 *
 * @return array background_id, corner_id, logo_id, qr_id, signature_id,
 *               institute_name, tagline, bottom_address, phone_line,
 *               director_name, qr_label.
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
	$background = sp_idcard_asset_url( 'background_id' );
	$corner     = sp_idcard_asset_url( 'corner_id' );
	$logo       = sp_idcard_asset_url( 'logo_id' );
	$qr         = sp_idcard_asset_url( 'qr_id' );
	$signature  = sp_idcard_asset_url( 'signature_id' );
	$photo      = ! empty( $profile->photo_id ) ? (string) wp_get_attachment_image_url( (int) $profile->photo_id, 'medium' ) : '';
	$father     = ! empty( $profile->father_name ) ? (string) $profile->father_name : '';
	$address    = ! empty( $profile->address ) ? (string) $profile->address : '';
	$phone      = ! empty( $profile->phone ) ? (string) $profile->phone : '';
	$enroll     = sp_idcard_enroll_number( $profile );
	$name       = (string) $profile->full_name;
	$initials   = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : strtoupper( substr( $name, 0, 1 ) );
	?>
	<div class="sp-idc" data-sp-idc>
		<?php if ( $background ) : ?>
			<div class="sp-idc-bg" style="background-image: url('<?php echo esc_url( $background ); ?>');"></div>
		<?php endif; ?>

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
			<div class="sp-idc-row">
				<dt>Name</dt>
				<dd><?php echo esc_html( $name ); ?></dd>
			</div>
			<div class="sp-idc-row">
				<dt>Father's Name</dt>
				<dd><?php echo '' !== $father ? esc_html( $father ) : '&mdash;'; ?></dd>
			</div>
			<div class="sp-idc-row">
				<dt>Emergency No</dt>
				<dd><?php echo '' !== $phone ? esc_html( $phone ) : '&mdash;'; ?></dd>
			</div>
			<div class="sp-idc-row">
				<dt>Enrollment No</dt>
				<dd><?php echo '' !== $enroll ? esc_html( $enroll ) : '&mdash;'; ?></dd>
			</div>
			<div class="sp-idc-row">
				<dt>Address</dt>
				<dd><?php echo '' !== $address ? esc_html( $address ) : '&mdash;'; ?></dd>
			</div>
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
