<?php
/**
 * Admin form handlers.
 *
 * Every action is nonce-checked and capability-checked before it touches data.
 * Each one ends in a redirect so a refresh cannot repeat the write.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatch an admin POST action.
 */
function sp_handle_admin_action() {
	if ( empty( $_POST['sp_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['sp_action'] ) );

	$nonce = isset( $_POST['sp_admin_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_admin_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'sp_admin_action_' . $action ) ) {
		wp_die( esc_html__( 'Your session expired. Please go back and try again.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	$capability = in_array( $action, array( 'save_settings', 'send_test_email' ), true ) ? 'manage_options' : 'manage_certificates';

	if ( ! current_user_can( $capability ) ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	switch ( $action ) {
		case 'save_student':
			sp_admin_action_save_student();
			break;
		case 'save_teacher':
			sp_admin_action_save_teacher();
			break;
		case 'resend_credentials':
			sp_admin_action_resend_credentials();
			break;
		case 'reset_password':
			sp_admin_action_reset_password();
			break;
		case 'toggle_status':
			sp_admin_action_toggle_status();
			break;
		case 'delete_student':
			sp_admin_action_delete_student();
			break;
		case 'delete_teacher':
			sp_admin_action_delete_teacher();
			break;
		case 'import_students':
			sp_admin_action_import_students();
			break;
		case 'import_teachers':
			sp_admin_action_import_teachers();
			break;
		case 'save_class':
			sp_admin_action_save_class();
			break;
		case 'approve_class':
			sp_admin_action_approve_class();
			break;
		case 'save_course':
			sp_admin_action_save_course();
			break;
		case 'delete_course':
			sp_admin_action_delete_course();
			break;
		case 'delete_class':
			sp_admin_action_delete_class();
			break;
		case 'save_curriculum':
			sp_admin_action_save_curriculum();
			break;
		case 'delete_curriculum':
			sp_admin_action_delete_curriculum();
			break;
		case 'move_curriculum':
			sp_admin_action_move_curriculum();
			break;
		case 'assign_teacher':
			sp_admin_action_assign_teacher();
			break;
		case 'unassign_teacher':
			sp_admin_action_unassign_teacher();
			break;
		case 'enroll_student':
			sp_admin_action_enroll_student();
			break;
		case 'enrollment_status':
			sp_admin_action_enrollment_status();
			break;
		case 'save_enrollment_id':
			sp_admin_action_save_enrollment_id();
			break;
		case 'save_certificate':
			sp_admin_action_save_certificate();
			break;
		case 'save_certificate_fields':
			sp_admin_action_save_certificate_fields();
			break;
		case 'reset_certificate_fields':
			sp_admin_action_reset_certificate_fields();
			break;
		case 'save_session':
			sp_admin_action_save_session();
			break;
		case 'submit_session':
			sp_admin_action_submit_session();
			break;
		case 'reopen_session':
			sp_admin_action_reopen_session();
			break;
		case 'finalise_result':
			sp_admin_action_finalise_result();
			break;
		case 'generate_certificate':
			sp_admin_action_generate_certificate();
			break;
		case 'generate_certificates_bulk':
			sp_admin_action_generate_certificates_bulk();
			break;
		case 'save_calendar_event':
			sp_admin_action_save_calendar_event();
			break;
		case 'delete_calendar_event':
			sp_admin_action_delete_calendar_event();
			break;
		case 'import_curriculum':
			sp_admin_action_import_curriculum();
			break;
		case 'save_idcard_settings':
			sp_admin_action_save_idcard_settings();
			break;
		case 'save_announcement':
			sp_admin_action_save_announcement();
			break;
		case 'announcement_status':
			sp_admin_action_announcement_status();
			break;
		case 'delete_announcement':
			sp_admin_action_delete_announcement();
			break;
		case 'save_settings':
			/*
			 * The Settings form posts back to admin.php, not wp-admin/options.php,
			 * so WordPress never processes it and register_setting()'s sanitiser is
			 * never invoked. Persist the option here explicitly instead of assuming
			 * something else already wrote it.
			 */
			$sp_payload = isset( $_POST['sp_settings'] ) ? (array) wp_unslash( $_POST['sp_settings'] ) : array();
			$sp_clean   = sp_sanitize_settings( $sp_payload );

			update_option( SP_SETTINGS_OPTION, array_merge( sp_settings(), $sp_clean ) );

			sp_admin_redirect( 'sp-settings', 'Settings saved.', 'good' );
			break;
		case 'send_test_email':
			sp_admin_action_send_test_email();
			break;
		default:
			wp_die( esc_html__( 'Unknown action.', 'student-portal' ), '', array( 'response' => 400 ) );
	}
}
add_action( 'admin_init', 'sp_handle_admin_action', 5 );

/**
 * Redirect within the portal admin and flash a message.
 *
 * @param string $page    Admin page slug.
 * @param string $message Message text. Empty shows nothing.
 * @param string $type    'good', 'error' or 'info'.
 * @param array  $args    Extra query arguments.
 */
function sp_admin_redirect( $page, $message = '', $type = 'good', $args = array() ) {
	$url = add_query_arg(
		array_merge(
			array( 'page' => $page ),
			$args
		),
		admin_url( 'admin.php' )
	);

	if ( '' !== $message ) {
		$url = add_query_arg(
			array(
				'sp_msg'   => rawurlencode( $message ),
				'sp_notice' => $type,
			),
			$url
		);
	}

	wp_safe_redirect( $url );
	exit;
}

/**
 * Read and clear the flashed admin notice.
 *
 * @return array {message, type}
 */
function sp_admin_notice() {
	if ( empty( $_GET['sp_msg'] ) ) {
		return array(
			'message' => '',
			'type'    => 'info',
		);
	}

	$type = isset( $_GET['sp_notice'] ) ? sanitize_key( wp_unslash( $_GET['sp_notice'] ) ) : 'good';

	if ( ! in_array( $type, array( 'good', 'error', 'info' ), true ) ) {
		$type = 'good';
	}

	return array(
		'message' => sanitize_text_field( wp_unslash( $_GET['sp_msg'] ) ),
		'type'    => $type,
	);
}

/* -------------------------------------------------------------------------
 * Accounts
 * ---------------------------------------------------------------------- */

/**
 * Create a student account from the Add Student form, or update one when the
 * form carries a profile_id (the Edit mode of the same screen).
 */
/**
 * Publish an announcement to one student, all students or the teachers.
 */
function sp_admin_action_save_announcement() {
	global $wpdb;

	$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$body     = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
	$audience = isset( $_POST['audience'] ) ? sanitize_key( wp_unslash( $_POST['audience'] ) ) : 'all_students';
	$display  = isset( $_POST['display'] ) && 'bar' === $_POST['display'] ? 'bar' : 'banner';
	$profile  = isset( $_POST['profile_id'] ) ? (int) $_POST['profile_id'] : 0;
	$send_email = ! empty( $_POST['send_email'] ) ? 1 : 0;
	$ends_at  = sp_announcement_sanitise_end_date( isset( $_POST['ends_at'] ) ? wp_unslash( $_POST['ends_at'] ) : '' );

	$audience = in_array( $audience, array( 'all_students', 'one_student', 'teachers' ), true ) ? $audience : 'all_students';

	if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
		sp_admin_redirect( 'sp-announcements', 'Enter the announcement message.', 'error' );
	}

	if ( 'one_student' === $audience ) {
		if ( ! $profile ) {
			sp_admin_redirect( 'sp-announcements', 'Choose the student this announcement is for.', 'error' );
		}

		if ( ! sp_get_profile_by_id( $profile ) ) {
			sp_admin_redirect( 'sp-announcements', 'That student no longer exists.', 'error' );
		}
	} else {
		$profile = 0;
	}

	$wpdb->insert(
		sp_table( 'announcements' ),
		array(
			'title'               => $title,
			'body'                => $body,
			'audience'            => $audience,
			'audience_profile_id' => $profile,
			'display'             => $display,
			'send_email'          => $send_email,
			'ends_at'             => $ends_at,
			'status'              => 'active',
			'created_by'          => get_current_user_id(),
		),
		array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d' )
	);

	$announcement_id = (int) $wpdb->insert_id;

	sp_log_activity(
		'announcement_created',
		'announcement',
		$announcement_id,
		sprintf( 'Announcement published (%s, %s)', $audience, $display )
	);

	if ( $send_email ) {
		$sent = sp_send_announcement_email( $announcement_id, $audience, $profile, $title, $body );

		sp_admin_redirect(
			'sp-announcements',
			sprintf(
				'Announcement published and emailed to %d recipient%s.',
				$sent,
				1 === $sent ? '' : 's'
			)
		);
	}

	sp_admin_redirect( 'sp-announcements', 'Announcement published.' );
}

/**
 * Park an announcement back on active, or archive it.
 */
function sp_admin_action_announcement_status() {
	global $wpdb;

	$announcement_id = isset( $_POST['announcement_id'] ) ? (int) $_POST['announcement_id'] : 0;
	$row             = $announcement_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . sp_table( 'announcements' ) . ' WHERE id = %d', $announcement_id ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().

	if ( ! $row ) {
		sp_admin_redirect( 'sp-announcements', 'That announcement no longer exists.', 'error' );
	}

	$new_status = 'active' === $row->status ? 'archived' : 'active';

	$wpdb->update(
		sp_table( 'announcements' ),
		array( 'status' => $new_status ),
		array( 'id' => $announcement_id ),
		array( '%s' ),
		array( '%d' )
	);

	sp_log_activity(
		'announcement_status',
		'announcement',
		$announcement_id,
		'Announcement ' . ( 'active' === $new_status ? 'restored' : 'archived' )
	);

	sp_admin_redirect( 'sp-announcements', 'active' === $new_status ? 'Announcement restored.' : 'Announcement archived.' );
}

/**
 * Delete an announcement outright.
 */
function sp_admin_action_delete_announcement() {
	global $wpdb;

	$announcement_id = isset( $_POST['announcement_id'] ) ? (int) $_POST['announcement_id'] : 0;
	$row             = $announcement_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . sp_table( 'announcements' ) . ' WHERE id = %d', $announcement_id ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().

	if ( ! $row ) {
		sp_admin_redirect( 'sp-announcements', 'That announcement no longer exists.', 'error' );
	}

	$wpdb->delete( sp_table( 'announcements' ), array( 'id' => $announcement_id ), array( '%d' ) );

	sp_log_activity( 'announcement_deleted', 'announcement', $announcement_id, 'Announcement deleted' );

	sp_admin_redirect( 'sp-announcements', 'Announcement deleted.' );
}

/**
 * Upload one student photo for the ID card, if a new file was picked.
 *
 * @return int|WP_Error Attachment id, 0 when nothing was sent, or WP_Error.
 */
function sp_admin_upload_student_photo() {
	if ( empty( $_FILES['photo']['name'] ) || '' === (string) $_FILES['photo']['name'] ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	return media_handle_upload( 'photo', 0 );
}

/**
 * Save the ID card design: uploaded artwork plus the card text lines.
 */
function sp_admin_action_save_idcard_settings() {
	$settings = sp_idcard_settings();
	$uploads  = array( 'background_id', 'corner_id', 'logo_id', 'qr_id', 'signature_id' );

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	foreach ( $uploads as $sp_key ) {
		$sp_has_file = ! empty( $_FILES[ $sp_key ]['name'] );

		if ( ! $sp_has_file && ! empty( $_POST[ 'clear_' . $sp_key ] ) ) {
			$settings[ $sp_key ] = 0;
			continue;
		}

		if ( ! $sp_has_file ) {
			continue;
		}

		$attachment_id = media_handle_upload( $sp_key, 0 );

		if ( is_wp_error( $attachment_id ) ) {
			sp_admin_redirect( 'sp-id-cards', 'Upload failed: ' . $attachment_id->get_error_message(), 'error' );
		}

		$settings[ $sp_key ] = (int) $attachment_id;
	}

	$lines = array( 'institute_name', 'tagline', 'phone_line', 'director_name', 'qr_label' );

	foreach ( $lines as $sp_field ) {
		if ( isset( $_POST[ $sp_field ] ) ) {
			$settings[ $sp_field ] = sanitize_text_field( wp_unslash( $_POST[ $sp_field ] ) );
		}
	}

	if ( isset( $_POST['bottom_address'] ) ) {
		$settings['bottom_address'] = sanitize_textarea_field( wp_unslash( $_POST['bottom_address'] ) );
	}

	// The bottom line: one field, so every word of it travels together.
	if ( isset( $_POST['bottom_note'] ) ) {
		$settings['bottom_note'] = sanitize_text_field( wp_unslash( $_POST['bottom_note'] ) );
	}

	/*
	 * Artwork mode: the background style plus the anchors that place the photo
	 * and the field values on a complete design. Percentages are clamped to the
	 * card box and the colour has to be a real hex triplet, so a hand-edited
	 * POST can never put an arbitrary string into the card's inline style.
	 */
	$sp_mode = isset( $_POST['background_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['background_mode'] ) ) : 'artwork';

	$settings['background_mode'] = 'frame' === $sp_mode ? 'frame' : 'artwork';

	$sp_percents = array( 'art_photo_left', 'art_photo_top', 'art_photo_size', 'art_value_left', 'art_value_size', 'art_sign_left', 'art_sign_top', 'art_sign_width', 'art_note_top', 'art_note_size' );

	foreach ( $sp_percents as $sp_field ) {
		if ( isset( $_POST[ $sp_field ] ) ) {
			$settings[ $sp_field ] = round( min( 100, max( 0, (float) wp_unslash( $_POST[ $sp_field ] ) ) ), 2 );
		}
	}

	// Every colour on the card has to be a real hex triplet.
	$sp_colors = array( 'art_value_color', 'art_note_color' );

	foreach ( $sp_colors as $sp_field ) {
		$sp_color = isset( $_POST[ $sp_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $sp_field ] ) ) : '';

		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $sp_color ) ) {
			$settings[ $sp_field ] = strtolower( $sp_color );
		}
	}

	// Always five rows, in the order the artwork prints them.
	if ( isset( $_POST['art_rows'] ) && is_array( $_POST['art_rows'] ) ) {
		$sp_rows = array_slice( array_values( wp_unslash( $_POST['art_rows'] ) ), 0, 5 );

		$sp_rows = array_map(
			function ( $sp_row ) {
				return round( min( 100, max( 0, (float) $sp_row ) ), 2 );
			},
			$sp_rows
		);

		while ( count( $sp_rows ) < 5 ) {
			$sp_rows[] = 0.0;
		}

		$settings['art_rows'] = $sp_rows;
	}

	update_option( SP_IDCARD_OPTION, $settings );

	sp_log_activity( 'idcard_settings_saved', 'settings', 0, 'ID card design updated' );

	sp_admin_redirect( 'sp-id-cards', 'ID card design saved.' );
}

function sp_admin_action_save_student() {
	global $wpdb;

	$profile_id = isset( $_POST['profile_id'] ) ? (int) $_POST['profile_id'] : 0;

	if ( $profile_id > 0 ) {
		sp_admin_action_update_student( $profile_id );
		return;
	}

	$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$name   = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
	$phone  = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$enroll = isset( $_POST['enroll_id'] ) ? sanitize_text_field( wp_unslash( $_POST['enroll_id'] ) ) : '';
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$admission  = isset( $_POST['admission_date'] ) ? sanitize_text_field( wp_unslash( $_POST['admission_date'] ) ) : '';
	$dob        = isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '';

	// Optional for a student: blank means "generate one and email it".
	$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- verified by wp_insert_user.

	$result = sp_create_account(
		array(
			'role'            => SP_ROLE_STUDENT,
			'email'           => $email,
			'full_name'       => $name,
			'phone'           => $phone,
			'dob'             => $dob,
			'password'        => trim( $password ),
		)
	);

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-student-accounts', $result->get_error_message(), 'error' );
	}

	// ID card fields: father's name, address and an optional photo.
	$sp_father  = isset( $_POST['father_name'] ) ? sanitize_text_field( wp_unslash( $_POST['father_name'] ) ) : '';
	$sp_address = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
	$sp_address = function_exists( 'mb_substr' ) ? mb_substr( $sp_address, 0, 100 ) : substr( $sp_address, 0, 100 );
	$sp_photo   = sp_admin_upload_student_photo();

	if ( is_wp_error( $sp_photo ) ) {
		sp_admin_redirect( 'sp-student-accounts', 'Account created, but the photo upload failed: ' . $sp_photo->get_error_message(), 'error' );
	}

	$sp_extra = array();
	$sp_extra_formats = array();

	if ( '' !== $sp_father ) {
		$sp_extra['father_name'] = $sp_father;
		$sp_extra_formats[]     = '%s';
	}

	if ( '' !== $sp_address ) {
		$sp_extra['address'] = $sp_address;
		$sp_extra_formats[] = '%s';
	}

	if ( $sp_photo ) {
		$sp_extra['photo_id'] = (int) $sp_photo;
		$sp_extra_formats[]   = '%d';
	}

	if ( $sp_extra ) {
		$wpdb->update(
			sp_table( 'student_profiles' ),
			$sp_extra,
			array( 'id' => (int) $result['record_id'] ),
			$sp_extra_formats,
			array( '%d' )
		);
	}

	if ( $class_id > 0 ) {
		$enrolled = sp_enroll_student( $result['record_id'], $class_id, $admission, true, $enroll );

		if ( is_wp_error( $enrolled ) ) {
			sp_admin_redirect(
				'sp-student-accounts',
				sprintf( 'Account created for %s, but enrolment failed: %s', $email, $enrolled->get_error_message() ),
				'error'
			);
		}
	}

	sp_admin_redirect(
		'sp-student-accounts',
		sprintf( 'Account created for %s. Check the Email Log to confirm the credentials email was delivered.', $email )
	);
}

/**
 * Update an existing student account from the Edit mode of the Add Student form.
 *
 * Email, name, phone and date of birth are editable; the password is only
 * replaced when a new one is typed. Enrolment IDs live on the Enrollments
 * screen -- one per course -- so they are deliberately not editable here.
 *
 * @param int $profile_id sp_student_profiles.id.
 */
function sp_admin_action_update_student( $profile_id ) {
	global $wpdb;

	$profile_id = (int) $profile_id;
	$profile    = sp_get_profile_by_id( $profile_id );

	if ( ! $profile ) {
		sp_admin_redirect( 'sp-student-accounts', 'That student account no longer exists.', 'error' );
	}

	$back = array( 'edit' => $profile_id );

	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$name    = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$dob_raw = isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '';

	// ID card fields: father's name, address and an optional new photo.
	$sp_father  = isset( $_POST['father_name'] ) ? sanitize_text_field( wp_unslash( $_POST['father_name'] ) ) : '';
	$sp_address = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
	$sp_address = function_exists( 'mb_substr' ) ? mb_substr( $sp_address, 0, 100 ) : substr( $sp_address, 0, 100 );
	$sp_photo   = sp_admin_upload_student_photo();

	if ( is_wp_error( $sp_photo ) ) {
		sp_admin_redirect( 'sp-student-accounts', 'The photo upload failed: ' . $sp_photo->get_error_message(), 'error', $back );
	}

	// Blank keeps the current password; anything else replaces it immediately.
	$password = isset( $_POST['password'] ) ? trim( (string) wp_unslash( $_POST['password'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- hashed by wp_update_user.

	if ( '' === $email || ! is_email( $email ) ) {
		sp_admin_redirect( 'sp-student-accounts', 'Enter a valid email address.', 'error', $back );
	}

	if ( '' === $name ) {
		sp_admin_redirect( 'sp-student-accounts', 'Enter the full name.', 'error', $back );
	}

	$dob = '';

	if ( '' !== $dob_raw ) {
		$dob = sp_sanitize_date( $dob_raw );

		if ( '' === $dob ) {
			sp_admin_redirect( 'sp-student-accounts', 'Pick a valid date of birth.', 'error', $back );
		}
	}

	// Uniqueness only matters when the email actually changed.
	if ( $email !== (string) $profile->email ) {
		$taken_profile = sp_get_profile_by_email( $email );

		if ( $taken_profile && (int) $taken_profile->id !== $profile_id ) {
			sp_admin_redirect( 'sp-student-accounts', 'That email address already belongs to a student portal account.', 'error', $back );
		}

		if ( sp_get_teacher_by_email( $email ) ) {
			sp_admin_redirect( 'sp-student-accounts', 'That email address already belongs to a teacher portal account.', 'error', $back );
		}

		$taken_user = email_exists( $email );

		if ( $taken_user && (int) $taken_user !== (int) $profile->user_id ) {
			sp_admin_redirect( 'sp-student-accounts', 'That email address already has a WordPress account.', 'error', $back );
		}
	}

	$user_args = array(
		'ID'           => (int) $profile->user_id,
		'user_email'   => $email,
		'display_name' => $name,
		'first_name'   => $name,
	);

	if ( '' !== $password ) {
		$user_args['user_pass'] = $password;
	}

	$saved_user = wp_update_user( $user_args );

	if ( is_wp_error( $saved_user ) ) {
		sp_admin_redirect( 'sp-student-accounts', $saved_user->get_error_message(), 'error', $back );
	}

	$sp_profile_data = array(
		'email'       => $email,
		'full_name'   => $name,
		'phone'       => '' !== $phone ? $phone : null,
		'dob'         => '' !== $dob ? $dob : null,
		'father_name' => '' !== $sp_father ? $sp_father : null,
		'address'     => '' !== $sp_address ? $sp_address : null,
	);

	$sp_profile_formats = array( '%s', '%s', '%s', '%s', '%s', '%s' );

	if ( $sp_photo ) {
		$sp_profile_data['photo_id'] = (int) $sp_photo;
		$sp_profile_formats[]        = '%d';
	}

	$updated = $wpdb->update(
		sp_table( 'student_profiles' ),
		$sp_profile_data,
		array( 'id' => $profile_id ),
		$sp_profile_formats,
		array( '%d' )
	);

	if ( false === $updated ) {
		sp_admin_redirect(
			'sp-student-accounts',
			'The profile could not be saved' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' ),
			'error',
			$back
		);
	}

	sp_log_activity(
		'account.updated',
		'student_profile',
		$profile_id,
		sprintf( 'Student account updated for %s', $email )
	);

	sp_admin_redirect( 'sp-student-accounts', sprintf( 'Account for %s updated.', $email ) );
}

/**
 * Create a teacher account.
 */
function sp_admin_action_save_teacher() {
	$password  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- verified by wp_insert_user / wp_set_password.
	$teacher_id = isset( $_POST['teacher_id'] ) ? (int) $_POST['teacher_id'] : 0;

	// Editing an existing teacher: no password required, everything else is
	// rewritten. This is the "modify any data" path for teacher accounts.
	if ( $teacher_id > 0 ) {
		$teacher = sp_get_teacher( $teacher_id );

		if ( ! $teacher ) {
			sp_admin_redirect( 'sp-teachers', 'That teacher no longer exists.', 'error' );
		}

		$sp_email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$sp_name     = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$sp_phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$sp_bio      = isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '';

		if ( ! is_email( $sp_email ) ) {
			sp_admin_redirect( 'sp-teachers', 'Enter a valid email address.', 'error', array( 'edit' => $teacher_id ) );
		}

		if ( '' === trim( $sp_name ) ) {
			sp_admin_redirect( 'sp-teachers', 'Enter the teacher\'s name.', 'error', array( 'edit' => $teacher_id ) );
		}

		$sp_taken = get_user_by( 'email', $sp_email );

		if ( $sp_taken && (int) $sp_taken->ID !== (int) $teacher->user_id ) {
			sp_admin_redirect( 'sp-teachers', 'Another account already uses that email address.', 'error', array( 'edit' => $teacher_id ) );
		}

		global $wpdb;

		$wpdb->update(
			sp_table( 'teachers' ),
			array(
				'full_name' => $sp_name,
				'email'      => $sp_email,
				'phone'      => $sp_phone,
				'bio'        => $sp_bio,
			),
			array( 'id' => $teacher_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Keep the WordPress login in step with the portal row.
		wp_update_user(
			array(
				'ID'           => (int) $teacher->user_id,
				'user_email'   => $sp_email,
				'display_name' => $sp_name,
			)
		);

		if ( '' !== trim( $password ) ) {
			wp_set_password( $password, (int) $teacher->user_id );
		}

		sp_log_activity( 'teacher.updated', 'teacher', $teacher_id, $sp_name . ' (' . $sp_email . ')' );

		sp_admin_redirect( 'sp-teachers', 'Teacher updated.' );
	}

	/*
	 * A teacher password is required on creation. Refusing here rather than
	 * falling back to a generated one keeps the institute's chosen password
	 * authoritative.
	 */
	if ( '' === trim( $password ) ) {
		sp_admin_redirect( 'sp-teachers', 'Enter a password for the teacher.', 'error' );
	}

	$result = sp_create_account(
		array(
			'role'       => SP_ROLE_TEACHER,
			'email'      => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'full_name'  => isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '',
			'phone'      => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'bio'        => isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '',
			'password'   => $password,
		)
	);

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-teachers', $result->get_error_message(), 'error' );
	}

	sp_admin_redirect(
		'sp-teachers',
		sprintf( 'Teacher %s created. Assign them to a class from Classes.', $result['record_id'] )
	);
}

/**
 * Re-send the credentials email for a portal user.
 *
 * WordPress only ever stores a password hash, so the original generated
 * password cannot be recovered. Resending therefore issues a fresh password.
 */
function sp_admin_action_resend_credentials() {
	$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
	$user    = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		sp_admin_redirect( 'sp-student-accounts', 'That user no longer exists.', 'error' );
	}

	$portal = sp_user_portal( $user );

	if ( '' === $portal ) {
		sp_admin_redirect( 'sp-student-accounts', 'That user is not a portal account.', 'error' );
	}

	sp_reset_portal_password( $user_id );

	$page = ( 'teacher' === $portal ) ? 'sp-teachers' : 'sp-student-accounts';

	sp_admin_redirect( $page, 'A new password was generated and emailed.', 'good' );
}

/**
 * Generate a fresh password for a portal user and email it.
 */
function sp_admin_action_reset_password() {
	$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;

	$result = sp_reset_portal_password( $user_id );

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-student-accounts', $result->get_error_message(), 'error' );
	}

	sp_admin_redirect( 'sp-student-accounts', 'Password reset and emailed.', 'good' );
}

/**
 * Enable or disable a student or teacher record.
 */
function sp_admin_action_toggle_status() {
	$record    = isset( $_POST['record'] ) ? sanitize_key( wp_unslash( $_POST['record'] ) ) : 'student';
	$record_id = isset( $_POST['record_id'] ) ? (int) $_POST['record_id'] : 0;
	$status    = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active';

	$result = sp_set_record_status( $record_id, $record, $status );

	$page = ( 'teacher' === $record ) ? 'sp-teachers' : 'sp-student-accounts';

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( $page, $result->get_error_message(), 'error' );
	}

	sp_admin_redirect(
		$page,
		sprintf( 'Account %s.', 'disabled' === $status ? 'disabled and its password reset' : 'enabled' )
	);
}

/* -------------------------------------------------------------------------
 * Imports
 * ---------------------------------------------------------------------- */

/**
 * Transient key holding a reviewed import plan.
 *
 * @param string $type student or teacher.
 * @return string
 */
function sp_import_plan_key( $type ) {
	return 'sp_import_plan_' . get_current_user_id() . '_' . $type;
}

/**
 * Plan or commit the student CSV import.
 */
function sp_admin_action_import_students() {
	$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : 'plan';

	if ( 'commit' === $step ) {
		$plan = get_transient( sp_import_plan_key( 'student' ) );

		if ( ! is_array( $plan ) ) {
			sp_admin_redirect( 'sp-import', 'That import preview expired. Upload the file again.', 'error', array( 'tab' => 'students' ) );
		}

		delete_transient( sp_import_plan_key( 'student' ) );

		$summary = sp_execute_student_import( $plan );
		$message = sprintf( '%d student account(s) created.', $summary['created'] );

		if ( $summary['errors'] ) {
			sp_admin_redirect( 'sp-import', $message . ' Problems: ' . implode( ' ', $summary['errors'] ), 'error', array( 'tab' => 'students' ) );
		}

		sp_admin_redirect( 'sp-import', $message, 'good', array( 'tab' => 'students' ) );
	}

	if ( empty( $_FILES['import_file']['name'] ) ) {
		sp_admin_redirect( 'sp-import', 'Choose a CSV file to import.', 'error', array( 'tab' => 'students' ) );
	}

	$parsed = sp_read_import_csv( $_FILES['import_file'], sp_import_student_column_map() );

	if ( is_wp_error( $parsed ) ) {
		sp_admin_redirect( 'sp-import', $parsed->get_error_message(), 'error', array( 'tab' => 'students' ) );
	}

	$plan = sp_plan_student_import( $parsed['rows'] );

	// 10 minutes is enough to review and confirm.
	set_transient( sp_import_plan_key( 'student' ), $plan, 10 * MINUTE_IN_SECONDS );

	$create = 0;
	$skip   = 0;
	$errors = 0;

	foreach ( $plan as $row ) {
		if ( 'create' === $row['action'] ) {
			++$create;
		} elseif ( 'skip' === $row['action'] ) {
			++$skip;
		} else {
			++$errors;
		}
	}

	sp_admin_redirect(
		'sp-import',
		sprintf( 'Preview ready: %d to create, %d to skip, %d with problems.', $create, $skip, $errors ),
		'good',
		array( 'preview' => 1, 'tab' => 'students' )
	);
}

/**
 * Plan or commit the teacher CSV import.
 */
function sp_admin_action_import_teachers() {
	$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : 'plan';

	if ( 'commit' === $step ) {
		$plan = get_transient( sp_import_plan_key( 'teacher' ) );

		if ( ! is_array( $plan ) ) {
			sp_admin_redirect( 'sp-import', 'That import preview expired. Upload the file again.', 'error', array( 'tab' => 'teachers' ) );
		}

		delete_transient( sp_import_plan_key( 'teacher' ) );

		$summary = sp_execute_teacher_import( $plan );
		$message = sprintf( '%d teacher account(s) created.', $summary['created'] );

		if ( $summary['errors'] ) {
			sp_admin_redirect( 'sp-import', $message . ' Problems: ' . implode( ' ', $summary['errors'] ), 'error', array( 'tab' => 'teachers' ) );
		}

		sp_admin_redirect( 'sp-import', $message, 'good', array( 'tab' => 'teachers' ) );
	}

	if ( empty( $_FILES['import_file']['name'] ) ) {
		sp_admin_redirect( 'sp-import', 'Choose a CSV file to import.', 'error', array( 'tab' => 'teachers' ) );
	}

	$parsed = sp_read_import_csv( $_FILES['import_file'], sp_import_teacher_column_map() );

	if ( is_wp_error( $parsed ) ) {
		sp_admin_redirect( 'sp-import', $parsed->get_error_message(), 'error', array( 'tab' => 'teachers' ) );
	}

	$plan = sp_plan_teacher_import( $parsed['rows'] );

	set_transient( sp_import_plan_key( 'teacher' ), $plan, 10 * MINUTE_IN_SECONDS );

	$create = 0;
	$skip   = 0;
	$errors = 0;

	foreach ( $plan as $row ) {
		if ( 'create' === $row['action'] ) {
			++$create;
		} elseif ( 'skip' === $row['action'] ) {
			++$skip;
		} else {
			++$errors;
		}
	}

	sp_admin_redirect(
		'sp-import',
		sprintf( 'Preview ready: %d to create, %d to skip, %d with problems.', $create, $skip, $errors ),
		'good',
		array( 'preview' => 1, 'tab' => 'teachers' )
	);
}

/* -------------------------------------------------------------------------
 * Classes, teachers, enrolments
 * ---------------------------------------------------------------------- */

/**
 * Create or update a class, with its weekly timings.
 *
 * Timings are validated before the class row is written so a half-filled day
 * reports back into the edit form instead of saving half a change.
 */
function sp_admin_action_save_class() {
	$edit_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$back    = $edit_id ? array( 'edit' => $edit_id ) : array();
	$timings = sp_normalize_timing_rows( sp_timing_rows_from_request() );

	if ( is_wp_error( $timings ) ) {
		sp_admin_redirect( 'sp-classes', $timings->get_error_message(), 'error', $back );
	}

	$class_id = sp_save_class(
		array(
			'id'          => $edit_id,
			'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'code'        => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '',
			'course_id'   => isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0,
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'start_date'  => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'end_date'    => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
			'status'      => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active',
		)
	);

	if ( is_wp_error( $class_id ) ) {
		sp_admin_redirect( 'sp-classes', $class_id->get_error_message(), 'error', $back );
	}

	$saved = sp_save_class_timings( $class_id, $timings );

	if ( is_wp_error( $saved ) ) {
		sp_admin_redirect(
			'sp-classes',
			sprintf( 'Class #%d saved, but the weekly timings were not: %s', $class_id, $saved->get_error_message() ),
			'error',
			array( 'edit' => (int) $class_id )
		);
	}

	sp_admin_redirect(
		'sp-classes',
		sprintf(
			'Class #%d saved%s.',
			$class_id,
			$saved ? sprintf( ' with %d weekly timing%s', $saved, 1 === $saved ? '' : 's' ) : ''
		)
	);
}

/**
 * Approve a teacher-created class so it goes live.
 *
 * Teacher-created classes land as `pending`; flipping the status to `active`
 * is the whole approval — enrolment dropdowns and the student portal only
 * ever offer active classes.
 */
function sp_admin_action_approve_class() {
	global $wpdb;

	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$class    = $class_id ? sp_get_class( $class_id ) : null;

	if ( ! $class ) {
		sp_admin_redirect( 'sp-classes', 'That class no longer exists.', 'error' );
	}

	if ( 'pending' !== (string) $class->status ) {
		sp_admin_redirect( 'sp-classes', 'That class is not waiting for approval.', 'error', array( 'class' => (int) $class_id ) );
	}

	$wpdb->update(
		sp_table( 'classes' ),
		array( 'status' => 'active' ),
		array( 'id' => (int) $class_id ),
		array( '%s' ),
		array( '%d' )
	);

	sp_log_activity( 'class.approved', 'class', (int) $class_id, $class->title );

	sp_admin_redirect( 'sp-classes', sprintf( 'Class “%s” approved.', $class->title ), 'good' );
}

/**
 * Create or update a course.
 */
function sp_admin_action_save_course() {
	$course_id = sp_save_course(
		array(
			'id'          => isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0,
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'code'        => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '',
			'duration'    => isset( $_POST['duration'] ) ? sanitize_text_field( wp_unslash( $_POST['duration'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'status'      => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active',
		)
	);

	if ( is_wp_error( $course_id ) ) {
		sp_admin_redirect( 'sp-courses', $course_id->get_error_message(), 'error' );
	}

	sp_admin_redirect( 'sp-courses', sprintf( 'Course #%d saved.', $course_id ) );
}

/**
 * Delete a course no class still teaches from.
 */
function sp_admin_action_delete_course() {
	$result = sp_delete_course( isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0 );

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-courses', $result->get_error_message(), 'error' );
	}

	sp_admin_redirect( 'sp-courses', 'Course deleted.' );
}

/**
 * Delete a class and everything the class owns.
 *
 * The cascade lives in sp_delete_class(): attendance, enrolments with their
 * results and portal certificate rows, assignments (taken or not), materials
 * and their files, question bank, timetable rows, calendar entries and the
 * teacher links. A confirm() on the button is the only thing standing
 * between the click and that, so it lists exactly what goes.
 */
function sp_admin_action_delete_class() {
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

	$result = sp_delete_class( $class_id );

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-classes', $result->get_error_message(), 'error' );
	}

	sp_admin_redirect(
		'sp-classes',
		'Class deleted, with its enrolments, attendance, results, materials and assignments.'
	);
}

/**
 * Delete a student account completely: profile, enrolments, results,
 * attendance marks, exam attempts and the WordPress login.
 */
function sp_admin_action_delete_student() {
	$profile_id = isset( $_POST['profile_id'] ) ? (int) $_POST['profile_id'] : 0;

	$result = sp_delete_student_account( $profile_id );

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-student-accounts', $result->get_error_message(), 'error' );
	}

	sp_admin_redirect(
		'sp-student-accounts',
		'Student deleted, with their enrolments, results and login.'
	);
}

/**
 * Delete a teacher account: portal row, class links and the WordPress login.
 */
function sp_admin_action_delete_teacher() {
	$teacher_id = isset( $_POST['teacher_id'] ) ? (int) $_POST['teacher_id'] : 0;

	$result = sp_delete_teacher_account( $teacher_id );

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-teachers', $result->get_error_message(), 'error' );
	}

	sp_admin_redirect( 'sp-teachers', 'Teacher deleted, with their class links and login.' );
}

/**
 * Create or update an outline item on a course — a topic or a heading.
 *
 * `item_type=heading` turns the row into a section header (`group_title` set,
 * `parent_id` null). `item_type=topic` (the default) keeps it a leaf and optionally
 * nests it under a chosen heading via `parent_id`.
 */
function sp_admin_action_save_curriculum() {
	global $wpdb;

	$course_id  = isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0;
	$item_id    = isset( $_POST['curriculum_id'] ) ? (int) $_POST['curriculum_id'] : 0;
	$title      = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$detail     = isset( $_POST['detail'] ) ? sanitize_textarea_field( wp_unslash( $_POST['detail'] ) ) : '';
	$item_type  = isset( $_POST['item_type'] ) ? sanitize_key( wp_unslash( $_POST['item_type'] ) ) : 'topic';
	$is_heading = ( 'heading' === $item_type );
	$parent_id  = isset( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : 0;

	// A heading carries its own title in group_title; a topic leaves it empty.
	$group_title = $is_heading ? $title : '';

	// Converting an existing row: a heading must shed its parent, and a row that
	// *was* a heading must not leave orphaned children behind.
	if ( $item_id > 0 ) {
		$existing = sp_get_curriculum_item( $item_id );

		if ( $existing && (int) $existing->course_id === $course_id ) {
			$was_heading = ! empty( $existing->group_title );

			if ( $is_heading && ! $was_heading ) {
				$parent_id = 0; // A heading cannot sit under another heading.
			}

			if ( ! $is_heading && $was_heading ) {
				// Demote: its children become top-level topics rather than orphans.
				$wpdb->update(
					sp_table( 'curriculum' ),
					array( 'parent_id' => null ),
					array( 'parent_id' => (int) $item_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	$result = sp_save_curriculum(
		array(
			'id'          => $item_id,
			'course_id'   => $course_id,
			'title'       => $title,
			'detail'      => $detail,
			'is_heading'  => $is_heading,
			'group_title' => $group_title,
			'parent_id'   => $parent_id,
		)
	);

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-courses', $result->get_error_message(), 'error', array( 'edit' => $course_id ) );
	}

	sp_admin_redirect(
		'sp-courses',
		$is_heading ? 'Heading saved.' : 'Topic saved.',
		'good',
		array( 'edit' => $course_id )
	);
}

/**
 * Delete an outline item (and the coverage recorded against it).
 */
function sp_admin_action_delete_curriculum() {
	$item_id = isset( $_POST['item_id'] ) ? (int) $_POST['item_id'] : 0;
	$item    = sp_get_curriculum_item( $item_id );
	$result  = sp_delete_curriculum_item( $item_id );

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-courses', $result->get_error_message(), 'error', array( 'edit' => $item ? (int) $item->course_id : 0 ) );
	}

	sp_admin_redirect( 'sp-courses', 'Topic deleted.', 'good', array( 'edit' => (int) $item->course_id ) );
}

/**
 * Move an outline item one step up or down.
 */
function sp_admin_action_move_curriculum() {
	$item_id   = isset( $_POST['item_id'] ) ? (int) $_POST['item_id'] : 0;
	$direction = isset( $_POST['direction'] ) ? sanitize_key( wp_unslash( $_POST['direction'] ) ) : '';
	$item      = sp_get_curriculum_item( $item_id );
	$result    = sp_move_curriculum_item( $item_id, $direction );

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-courses', $result->get_error_message(), 'error', array( 'edit' => $item ? (int) $item->course_id : 0 ) );
	}

	sp_admin_redirect( 'sp-courses', 'Outline reordered.', 'good', array( 'edit' => (int) $item->course_id ) );
}

/**
 * Bulk-import outline items for one course from a CSV.
 *
 * Columns: `title` (required; `topic` and `name` accepted), `detail`
 * (optional; also `description`/`notes`), `type` (optional: `topic` or
 * `heading`) and `parent`/`heading` (optional: the heading title a topic sits
 * under). A header row is detected by its first cell and skipped. Rows are
 * appended after the existing outline, in file order.
 *
 * Headings may be declared explicitly with `type=heading`, or implicitly: a
 * row with a `parent` value that does not yet exist creates that heading on
 * the fly, so a simple two-column syllabus indents cleanly.
 */
function sp_admin_action_import_curriculum() {
	global $wpdb;

	$course_id = isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0;

	if ( ! sp_get_course( $course_id ) ) {
		sp_admin_redirect( 'sp-courses', 'That course no longer exists.', 'error' );
	}

	$back = array( 'edit' => (int) $course_id );

	if ( empty( $_FILES['import_file']['name'] ) ) {
		sp_admin_redirect( 'sp-courses', 'Choose a CSV file to import.', 'error', $back );
	}

	if ( ! empty( $_FILES['import_file']['error'] ) ) {
		sp_admin_redirect( 'sp-courses', 'The CSV upload did not complete. Try again.', 'error', $back );
	}

	$handle = fopen( (string) $_FILES['import_file']['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- upload tmp file, read-only.

	if ( ! $handle ) {
		sp_admin_redirect( 'sp-courses', 'That file could not be read as CSV.', 'error', $back );
	}

	$added      = 0;
	$errors     = array();
	$row_no     = 0;
	$title_col  = 0;
	$detail_col = 1;
	$type_col   = null;
	$parent_col = null;

	// Heading title -> id, so a topic's `parent` cell can resolve within this run.
	$heading_map = array();

	while ( false !== ( $line = fgetcsv( $handle ) ) ) {
		++$row_no;

		// One item per row, 1,000 rows max — a pasted whole-syllabus file
		// should not be able to run the request out of time.
		if ( $row_no > 1000 ) {
			$errors[] = 'Stopped after 1,000 rows.';
			break;
		}

		if ( 1 === count( $line ) && '' === trim( (string) $line[0] ) ) {
			continue;
		}

		if ( 1 === $row_no ) {
			$first = strtolower( trim( (string) $line[0] ) );

			if ( in_array( $first, array( 'title', 'topic', 'name', 'detail', 'description', 'notes', 'type', 'parent', 'heading' ), true ) ) {
				$title_col  = 0;
				$detail_col = null;

				foreach ( $line as $sp_idx => $sp_head ) {
					$sp_head = strtolower( trim( (string) $sp_head ) );

					if ( in_array( $sp_head, array( 'title', 'topic', 'name' ), true ) ) {
						$title_col = $sp_idx;
					} elseif ( in_array( $sp_head, array( 'detail', 'description', 'notes' ), true ) ) {
						$detail_col = $sp_idx;
					} elseif ( 'type' === $sp_head ) {
						$type_col = $sp_idx;
					} elseif ( in_array( $sp_head, array( 'parent', 'heading' ), true ) ) {
						$parent_col = $sp_idx;
					}
				}

				if ( null === $detail_col && count( $line ) > 1 ) {
					$detail_col = 1;
				}

				continue;
			}
		}

		$title = trim( (string) ( isset( $line[ $title_col ] ) ? $line[ $title_col ] : '' ) );
		$detail = ( null !== $detail_col && isset( $line[ $detail_col ] ) )
			? trim( (string) $line[ $detail_col ] )
			: '';

		$raw_type   = ( null !== $type_col && isset( $line[ $type_col ] ) ) ? strtolower( trim( (string) $line[ $type_col ] ) ) : '';
		$is_heading = in_array( $raw_type, array( 'heading', 'section', 'unit', 'h' ), true );

		$raw_parent = ( null !== $parent_col && isset( $line[ $parent_col ] ) ) ? trim( (string) $line[ $parent_col ] ) : '';

		if ( '' === $title ) {
			$errors[] = sprintf( 'Row %d: no title.', $row_no );
			continue;
		}

		$parent_id = 0;

		// Resolve (or lazily create) the heading this topic belongs under.
		if ( ! $is_heading && '' !== $raw_parent ) {
			$sp_map_key = strtolower( $raw_parent );

			if ( isset( $heading_map[ $sp_map_key ] ) ) {
				$parent_id = (int) $heading_map[ $sp_map_key ];
			} else {
				// Look for an existing heading with this title in the course.
				$sp_existing = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT id FROM ' . sp_table( 'curriculum' ) . ' WHERE course_id = %d AND group_title = %s ORDER BY id ASC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
						(int) $course_id,
						$raw_parent
					)
				);

				if ( $sp_existing ) {
					$parent_id = (int) $sp_existing;
					$heading_map[ $sp_map_key ] = $parent_id;
				} else {
					// Create the heading on the fly so a flat syllabus still nests.
					$sp_new_heading = sp_save_curriculum(
						array(
							'course_id'   => (int) $course_id,
							'title'       => $raw_parent,
							'is_heading'  => true,
							'group_title' => $raw_parent,
						)
					);

					if ( ! is_wp_error( $sp_new_heading ) ) {
						$parent_id = (int) $sp_new_heading;
						$heading_map[ $sp_map_key ] = $parent_id;
						++$added;
					}
				}
			}
		}

		$saved = sp_save_curriculum(
			array(
				'course_id'   => (int) $course_id,
				'title'       => $title,
				'detail'      => $detail,
				'is_heading'  => $is_heading,
				'group_title' => $is_heading ? $title : '',
				'parent_id'   => $parent_id,
			)
		);

		if ( is_wp_error( $saved ) ) {
			$errors[] = sprintf( 'Row %d: %s', $row_no, $saved->get_error_message() );
			continue;
		}

		if ( $is_heading ) {
			$heading_map[ strtolower( $title ) ] = (int) $saved;
		}

		++$added;
	}

	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- paired with the fopen above.

	$message = sprintf( '%d item(s) imported.', $added );

	if ( $errors ) {
		$message .= ' ' . implode( ' ', array_slice( $errors, 0, 5 ) );

		if ( count( $errors ) > 5 ) {
			$message .= sprintf( ' (and %d more.)', count( $errors ) - 5 );
		}

		sp_admin_redirect( 'sp-courses', $message, $added > 0 ? 'good' : 'error', $back );
	}

	sp_admin_redirect( 'sp-courses', $message, 'good', $back );
}

/**
 * Assign a teacher to a class.
 */
function sp_admin_action_assign_teacher() {
	$teacher_id  = isset( $_POST['teacher_id'] ) ? (int) $_POST['teacher_id'] : 0;
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$is_primary  = ! empty( $_POST['is_primary'] );
	$notify      = ! empty( $_POST['notify'] );

	$result = sp_assign_teacher( $teacher_id, $class_id, $is_primary, $notify );

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-classes', $result->get_error_message(), 'error', array( 'class' => $class_id ) );
	}

	sp_admin_redirect(
		'sp-classes',
		'Teacher assigned.',
		'good',
		array( 'class' => $class_id )
	);
}

/**
 * Remove a teacher's assignment.
 */
function sp_admin_action_unassign_teacher() {
	$teacher_id  = isset( $_POST['teacher_id'] ) ? (int) $_POST['teacher_id'] : 0;
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

	$result = sp_unassign_teacher( $teacher_id, $class_id );

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-classes', $result->get_error_message(), 'error', array( 'class' => $class_id ) );
	}

	sp_admin_redirect( 'sp-classes', 'Teacher unassigned.', 'good', array( 'class' => $class_id ) );
}

/**
 * Enrol one or more students into a class from the Enrollments screen.
 *
 * The form submits one row per candidate, so profile_id, enroll_id,
 * admission_date and notify are all keyed by profile id. A single legacy
 * scalar profile_id is still accepted.
 */
function sp_admin_action_enroll_student() {
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

	if ( ! sp_get_class( $class_id ) ) {
		sp_admin_redirect( 'sp-enrollments', 'Pick a class before enrolling students.', 'error' );
	}

	// The form posts profile_id[] — one value per ticked row. A scalar is
	// still accepted so older markup keeps working.
	$profile_ids = isset( $_POST['profile_id'] ) ? wp_unslash( $_POST['profile_id'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- cast to int below.

	$profile_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $profile_ids ) ) ) );

	if ( ! $profile_ids ) {
		sp_admin_redirect(
			'sp-enrollments',
			'Tick at least one student, then click Enrol selected.',
			'error',
			array( 'class' => $class_id )
		);
	}

	$enroll_ids   = isset( $_POST['enroll_id'] ) && is_array( $_POST['enroll_id'] ) ? wp_unslash( $_POST['enroll_id'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised
	$admissions   = isset( $_POST['admission_date'] ) && is_array( $_POST['admission_date'] ) ? wp_unslash( $_POST['admission_date'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised
	$notifies     = isset( $_POST['notify'] ) && is_array( $_POST['notify'] ) ? wp_unslash( $_POST['notify'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised

	$enrolled = 0;
	$errors   = array();

	foreach ( $profile_ids as $sp_pid ) {
		$sp_enroll  = isset( $enroll_ids[ $sp_pid ] ) ? sanitize_text_field( $enroll_ids[ $sp_pid ] ) : '';
		$sp_admit   = isset( $admissions[ $sp_pid ] ) ? sanitize_text_field( $admissions[ $sp_pid ] ) : '';
		$sp_notify  = ! empty( $notifies[ $sp_pid ] );

		$result = sp_enroll_student( $sp_pid, $class_id, $sp_admit, $sp_notify, $sp_enroll );

		if ( is_wp_error( $result ) ) {
			// Name the student, so a rejected row in a bulk run is obvious.
			$sp_profile = sp_get_profile_by_id( $sp_pid );
			$sp_label   = $sp_profile ? $sp_profile->full_name : ( 'student #' . $sp_pid );
			$errors[]   = $sp_label . ': ' . $result->get_error_message();
			continue;
		}

		++$enrolled;
	}

	if ( $errors ) {
		sp_admin_redirect(
			'sp-enrollments',
			sprintf( 'Enrolled %d student(s).', $enrolled ) . ' ' . implode( ' ', $errors ),
			$enrolled > 0 ? 'good' : 'error',
			array( 'class' => $class_id )
		);
	}

	sp_admin_redirect(
		'sp-enrollments',
		$enrolled > 0 ? sprintf( 'Enrolled %d student(s).', $enrolled ) : 'Nobody was enrolled.',
		$enrolled > 0 ? 'good' : 'error',
		array( 'class' => $class_id )
	);
}

/**
 * Set the enrolment ID and admission date for one class enrolment.
 *
 * The ID is the institute's own number for this student on this course. It is
 * free text: nothing validates it against an outside system, because the portal
 * no longer needs a certificate record to already exist.
 *
 * A blank admission date keeps the stored one; a filled one overwrites it, so
 * certificates auto-fill from a date that was missing at enrolment time.
 */
function sp_admin_action_save_enrollment_id() {
	global $wpdb;

	$enrollment_id = isset( $_POST['enrollment_id'] ) ? (int) $_POST['enrollment_id'] : 0;
	$class_id      = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$enroll        = isset( $_POST['enroll_id'] ) ? sanitize_text_field( wp_unslash( $_POST['enroll_id'] ) ) : '';
	$admission_raw = isset( $_POST['admission_date'] ) ? sanitize_text_field( wp_unslash( $_POST['admission_date'] ) ) : '';

	$enrollment = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'enrollments' ) . ' WHERE id = %d', $enrollment_id )
	);

	if ( ! $enrollment ) {
		sp_admin_redirect( 'sp-enrollments', 'That enrolment no longer exists.', 'error', array( 'class' => $class_id ) );
	}

	// One ID, one student — the Certificate Manager keys its rows on it.
	if ( '' !== $enroll && sp_enroll_id_is_taken( $enroll, (int) $enrollment->id ) ) {
		sp_admin_redirect(
			'sp-enrollments',
			sprintf( 'Another student already uses the enrolment ID %s — pick a different one.', $enroll ),
			'error',
			array( 'class' => $class_id )
		);
	}

	$data = array( 'enroll_id' => '' !== $enroll ? $enroll : null );

	if ( '' !== $admission_raw ) {
		$admission = sp_sanitize_date( $admission_raw );

		if ( '' === $admission ) {
			sp_admin_redirect( 'sp-enrollments', 'Pick a valid admission date.', 'error', array( 'class' => $class_id ) );
		}

		$data['admission_date'] = $admission;
	}

	$updated = $wpdb->update(
		sp_table( 'enrollments' ),
		$data,
		array( 'id' => $enrollment_id ),
		array_fill( 0, count( $data ), '%s' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		sp_admin_redirect(
			'sp-enrollments',
			'The enrolment could not be saved' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' ),
			'error',
			array( 'class' => $class_id )
		);
	}

	sp_log_activity(
		'enrollment.enroll_id',
		'enrollment',
		$enrollment_id,
		'Enrolment ID set to ' . ( $enroll ? $enroll : '(cleared)' )
			. ( isset( $data['admission_date'] ) ? ' — admission date ' . $data['admission_date'] : '' )
	);

	sp_admin_redirect( 'sp-enrollments', 'Enrolment ID saved.', 'good', array( 'class' => $class_id ) );
}

/**
 * Change an enrolment status.
 */
function sp_admin_action_enrollment_status() {
	$enrollment_id = isset( $_POST['enrollment_id'] ) ? (int) $_POST['enrollment_id'] : 0;
	$status        = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active';
	$class_id   = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

	$result = sp_set_enrollment_status( $enrollment_id, $status );

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-enrollments', $result->get_error_message(), 'error', array( 'class' => $class_id ) );
	}

	sp_admin_redirect( 'sp-enrollments', 'Enrolment updated.', 'good', array( 'class' => $class_id ) );
}

/* -------------------------------------------------------------------------
 * Attendance and results
 * ---------------------------------------------------------------------- */

/**
 * Save a register from the admin Attendance screen.
 */
function sp_admin_action_save_session() {
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$date        = isset( $_POST['session_date'] ) ? sanitize_text_field( wp_unslash( $_POST['session_date'] ) ) : '';
	$topic       = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';

	$session_id = sp_create_session( $class_id, $date, $topic );

	if ( is_wp_error( $session_id ) ) {
		sp_admin_redirect( 'sp-attendance', $session_id->get_error_message(), 'error', array( 'class' => $class_id, 'date' => $date ) );
	}

	$marks = array();

	if ( isset( $_POST['marks'] ) && is_array( $_POST['marks'] ) ) {
		foreach ( wp_unslash( $_POST['marks'] ) as $profile_id => $status ) {
			$marks[ (int) $profile_id ] = sanitize_key( $status );
		}
	}

	$saved = sp_save_attendance_records( $session_id, $marks );

	if ( is_wp_error( $saved ) ) {
		sp_admin_redirect( 'sp-attendance', $saved->get_error_message(), 'error', array( 'class' => $class_id, 'date' => $date ) );
	}

	if ( ! empty( $_POST['submit_after'] ) ) {
		sp_submit_session( $session_id );
		sp_admin_redirect( 'sp-attendance', 'Register saved and submitted.', 'good', array( 'class' => $class_id, 'date' => $date ) );
	}

	sp_admin_redirect( 'sp-attendance', 'Register saved.', 'good', array( 'class' => $class_id, 'date' => $date ) );
}

/**
 * Submit a register from the admin screen.
 */
function sp_admin_action_submit_session() {
	$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$session     = sp_get_session( $session_id );

	if ( ! $session ) {
		sp_admin_redirect( 'sp-attendance', 'That session no longer exists.', 'error', array( 'class' => $class_id ) );
	}

	sp_submit_session( $session_id );

	sp_admin_redirect(
		'sp-attendance',
		'Register submitted and locked.',
		'good',
		array(
			'class' => $class_id,
			'date'     => $session->session_date,
		)
	);
}

/**
 * Reopen a register from the admin screen.
 */
function sp_admin_action_reopen_session() {
	$session_id  = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$session     = sp_get_session( $session_id );

	if ( ! $session ) {
		sp_admin_redirect( 'sp-attendance', 'That session no longer exists.', 'error', array( 'class' => $class_id ) );
	}

	$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : 'corrected by administrator';

	sp_reopen_session( $session_id, $reason );

	sp_admin_redirect(
		'sp-attendance',
		'Register reopened.',
		'good',
		array(
			'class' => $class_id,
			'date'     => $session->session_date,
		)
	);
}

/**
 * Publish or unpublish a result.
 */
function sp_admin_action_finalise_result() {
	$result_id     = isset( $_POST['result_id'] ) ? (int) $_POST['result_id'] : 0;
	$enrollment_id = isset( $_POST['enrollment_id'] ) ? (int) $_POST['enrollment_id'] : 0;
	$class_id   = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

	$result = sp_get_result_for_enrollment( $enrollment_id );

	if ( ! $result || (int) $result->id !== $result_id ) {
		sp_admin_redirect( 'sp-results', 'That result no longer exists.', 'error', array( 'class' => $class_id ) );
	}

	$publish = empty( $result->finalised );

	sp_set_result_finalised( $result_id, $publish );

	sp_admin_redirect(
		'sp-results',
		$publish ? 'Result published.' : 'Result unpublished.',
		'good',
		array( 'class' => $class_id )
	);
}

/**
 * Send a test email to confirm the mailer works.
 */
function sp_admin_action_send_test_email() {
	$to = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : get_option( 'admin_email' );

	$sent = sp_send_mail(
		$to,
		'Student Portal test email',
		sp_email_shell(
			'Test email',
			'<p>This is a test from the Student Portal plugin. If you received it, portal emails will be delivered.</p>'
		),
		array( 'context' => 'test' )
	);

	sp_admin_redirect(
		'sp-settings',
		$sent
			? sprintf( 'Test email accepted for delivery to %s. Check that it actually arrives.', $to )
			: 'wp_mail() refused the message. The site mailer is not configured — install and set up an SMTP plugin.',
		$sent ? 'good' : 'error'
	);

}

/**
 * Open the certificate form for one student from the admin Results screen.
 *
 * This only routes: the form is a GET back to the Results screen with
 * `cert_form` set, which renders the dynamic field list pre-filled from the
 * student's data. Issuing happens in sp_admin_action_save_certificate().
 */
function sp_admin_action_generate_certificate() {
	$enrollment_id = isset( $_POST['enrollment_id'] ) ? (int) $_POST['enrollment_id'] : 0;
	$class_id      = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

	if ( $enrollment_id <= 0 ) {
		sp_admin_redirect( 'sp-results', 'No student was selected.', 'error' );
	}

	$state = sp_certificate_state_for_enrollment( $enrollment_id );

	if ( ! $state['can_generate'] && ! $state['generated'] ) {
		sp_admin_redirect( 'sp-results', $state['reason'], 'error', $class_id > 0 ? array( 'class' => $class_id ) : array() );
	}

	sp_admin_redirect(
		'sp-results',
		'',
		'good',
		array_filter(
			array(
				'class'     => $class_id,
				'cert_form' => $enrollment_id,
			)
		)
	);
}

/**
 * Save a certificate from the dynamic form on the Results screen.
 */
function sp_admin_action_save_certificate() {
	$enrollment_id = isset( $_POST['enrollment_id'] ) ? (int) $_POST['enrollment_id'] : 0;
	$class_id      = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$values        = isset( $_POST['cert_values'] ) && is_array( $_POST['cert_values'] )
		? wp_unslash( $_POST['cert_values'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- sanitised per field below.
		: array();

	$sanitised = array();

	foreach ( $values as $sp_key => $sp_value ) {
		$sp_key = sanitize_key( $sp_key );

		$sanitised[ $sp_key ] = is_array( $sp_value )
			? implode( ' ', array_map( 'sanitize_textarea_field', $sp_value ) )
			: sanitize_textarea_field( $sp_value );
	}

	$certificate = sp_issue_certificate( $enrollment_id, $sanitised );

	if ( is_wp_error( $certificate ) ) {
		sp_admin_redirect(
			'sp-results',
			$certificate->get_error_message(),
			'error',
			array_filter(
				array(
					'class'     => $class_id,
					'cert_form' => $enrollment_id,
				)
			)
		);
	}

	sp_admin_redirect(
		'sp-results',
		sprintf( 'Certificate %s issued.', $certificate->serial ),
		'good',
		$class_id > 0 ? array( 'class' => $class_id ) : array()
	);
}

/**
 * Save the certificate field list from the Certificate fields screen.
 *
 * Labels, types, sources and required flags are configuration. Keys are
 * preserved across saves so an issued certificate's stored values keep matching
 * the field they were filled from.
 */
function sp_admin_action_save_certificate_fields() {
	$raw = isset( $_POST['sp_cert_fields'] ) && is_array( $_POST['sp_cert_fields'] )
		? wp_unslash( $_POST['sp_cert_fields'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- sanitised per key below.
		: array();

	$sources = sp_certificate_field_sources();
	$types   = sp_certificate_field_types();
	$fields  = array();
	$index   = 0;

	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';

		if ( '' === $label ) {
			continue;
		}

		$type   = isset( $row['type'] ) && isset( $types[ $row['type'] ] ) ? sanitize_key( $row['type'] ) : 'text';
		$source = isset( $row['source'] ) && array_key_exists( $row['source'], $sources ) ? sanitize_key( $row['source'] ) : '';
		$key    = isset( $row['key'] ) && '' !== trim( (string) $row['key'] )
			? sanitize_key( $row['key'] )
			: 'field_' . ( $index + 1 );

		$fields[] = array(
			'key'      => $key,
			'label'    => $label,
			'type'     => $type,
			'source'   => $source,
			'required' => ! empty( $row['required'] ),
		);

		++$index;
	}

	update_option( 'sp_certificate_fields', $fields );

	sp_log_activity( 'certificate.fields_updated', 'settings', 0, count( $fields ) . ' certificate field(s)' );

	sp_admin_redirect( 'sp-certificate-fields', 'Certificate fields saved.' );
}

/**
 * Restore the default certificate field list.
 */
function sp_admin_action_reset_certificate_fields() {
	update_option( 'sp_certificate_fields', sp_certificate_default_fields() );

	sp_log_activity( 'certificate.fields_reset', 'settings', 0, 'Certificate field list reset to defaults' );

	sp_admin_redirect( 'sp-certificate-fields', 'Certificate fields reset to the default list.' );
}

/**
 * Issue certificates for every passing student in one class.
 */
function sp_admin_action_generate_certificates_bulk() {
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

	if ( $class_id <= 0 ) {
		sp_admin_redirect( 'sp-results', 'No class was selected.', 'error' );
	}

	$class = sp_get_class( $class_id );

	if ( ! $class ) {
		sp_admin_redirect( 'sp-results', 'That class no longer exists.', 'error' );
	}

	$rows       = sp_get_class_results( $class_id );
	$generated  = 0;
	$skipped    = 0;
	$problems   = array();

	foreach ( $rows as $row ) {
		if ( ! $row->enrollment_id ) {
			continue;
		}

		$state = sp_certificate_state_for_enrollment( (int) $row->enrollment_id );

		// Already issued, or not eligible: leave it alone.
		if ( $state['generated'] ) {
			++$skipped;
			continue;
		}

		if ( ! $state['can_generate'] ) {
			++$skipped;
			continue;
		}

		$result = sp_issue_certificate( (int) $row->enrollment_id );

		if ( is_wp_error( $result ) ) {
			$problems[] = sprintf( '%s: %s', $row->full_name, $result->get_error_message() );
			continue;
		}

		++$generated;
	}

	$message = sprintf(
		'%d certificate(s) issued for %s. %d skipped (already issued, failed, below the attendance criteria, or not yet graded).',
		$generated,
		$class->title,
		$skipped
	);

	if ( $problems ) {
		sp_admin_redirect( 'sp-results', $message . ' Problems: ' . implode( ' ', $problems ), 'error' );
	}

	sp_admin_redirect( 'sp-results', $message, 'good' );
}

/* -------------------------------------------------------------------------
 * Calendar and teacher attendance
 * ---------------------------------------------------------------------- */

/**
 * Create or update a calendar event from the Calendar screen.
 */
function sp_admin_action_save_calendar_event() {
	$result = sp_save_calendar_event(
		array(
			'id'         => isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0,
			'event_date' => isset( $_POST['event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) ) : '',
			'type'       => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'event',
			'scope'      => isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'global',
			'class_id'   => isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0,
			'title'      => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'note'       => isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '',
		)
	);

	$args = sp_schedule_redirect_args();

	if ( is_wp_error( $result ) ) {
		sp_admin_redirect( 'sp-calendar', $result->get_error_message(), 'error', $args );
	}

	sp_admin_redirect( 'sp-calendar', 'Calendar entry saved.', 'good', $args );
}

/**
 * The view/date/edit query args the sidebar form posts, so the redirect
 * reopens the schedule on the same date after a save or delete.
 *
 * @return array
 */
function sp_schedule_redirect_args() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$view  = isset( $_POST['sp_view'] ) ? sanitize_key( wp_unslash( $_POST['sp_view'] ) ) : '';
	$date  = isset( $_POST['sp_date'] ) ? sp_sanitize_date( wp_unslash( $_POST['sp_date'] ) ) : '';
	$edit  = isset( $_POST['sp_edit_date'] ) ? sp_sanitize_date( wp_unslash( $_POST['sp_edit_date'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$args = array();

	if ( $view && in_array( $view, sp_schedule_views(), true ) ) {
		$args['view'] = $view;
	}

	if ( '' !== $date ) {
		$args['date'] = $date;
	}

	if ( '' !== $edit ) {
		$args['sp_edit_date'] = $edit;
	}

	return $args;
}

/**
 * Delete a calendar event from the Calendar screen.
 */
function sp_admin_action_delete_calendar_event() {
	$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
	$args     = sp_schedule_redirect_args();

	if ( ! sp_delete_calendar_event( $event_id ) ) {
		sp_admin_redirect( 'sp-calendar', 'That calendar entry no longer exists.', 'error', $args );
	}

	sp_admin_redirect( 'sp-calendar', 'Calendar entry removed.', 'good', $args );
}
