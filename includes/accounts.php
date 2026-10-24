<?php
/**
 * Account lifecycle: creating portal users, resending credentials, disabling.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which portal, if any, already owns an email address.
 *
 * @param string $email Email address.
 * @return string 'student', 'teacher' or '' when free.
 */
function sp_email_portal( $email ) {
	$email = sanitize_email( $email );

	if ( '' === $email ) {
		return '';
	}

	if ( sp_get_profile_by_email( $email ) ) {
		return 'student';
	}

	if ( sp_get_teacher_by_email( $email ) ) {
		return 'teacher';
	}

	return '';
}

/**
 * Create a portal account plus its profile/teacher row.
 *
 * @param array $args {
 *     @type string $role            sp_student or sp_teacher.
 *     @type string $email           Required.
 *     @type string $full_name       Required.
 *     @type string $phone           Optional.
 *     @type string $bio             Teacher only.
 *     @type string $address         Student only; printed on the ID card.
 *     @type bool   $send_email      Defaults to true.
 *     @type string $password        Optional; generated when omitted.
 * }
 * @return array|WP_Error {
 *     @type int    $user_id
 *     @type int    $record_id
 *     @type string $password
 * }
 */
function sp_create_account( $args ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'role'            => SP_ROLE_STUDENT,
			'email'           => '',
			'full_name'       => '',
			'phone'           => '',
			'dob'             => '',
			'address'         => '',
			'bio'             => '',
			'send_email'      => true,
			'password'        => '',
		)
	);

	$role      = in_array( $args['role'], array( SP_ROLE_STUDENT, SP_ROLE_TEACHER ), true ) ? $args['role'] : SP_ROLE_STUDENT;
	$email     = sanitize_email( $args['email'] );
	$full_name = sanitize_text_field( $args['full_name'] );
	$phone     = sanitize_text_field( $args['phone'] );
	$dob       = sp_sanitize_date( $args['dob'] );
	$address   = trim( sanitize_textarea_field( (string) $args['address'] ) );
	$address   = function_exists( 'mb_substr' ) ? mb_substr( $address, 0, 100 ) : substr( $address, 0, 100 );

	if ( '' === $email || ! is_email( $email ) ) {
		return new WP_Error( 'sp_bad_email', 'Enter a valid email address.' );
	}

	if ( '' === $full_name ) {
		return new WP_Error( 'sp_bad_name', 'Enter the full name.' );
	}

	$existing = sp_email_portal( $email );

	if ( $existing ) {
		return new WP_Error(
			'sp_email_taken',
			sprintf( 'That email address already belongs to a %s portal account.', $existing )
		);
	}

	if ( email_exists( $email ) ) {
		return new WP_Error( 'sp_email_taken_wp', 'That email address already has a WordPress account.' );
	}

	$password = $args['password'] ? $args['password'] : wp_generate_password( 16, true, false );

	$user_id = wp_insert_user(
		array(
			'user_login' => $email,
			'user_email' => $email,
			'user_pass'  => $password,
			'display_name' => $full_name,
			'first_name' => $full_name,
			'role'       => $role,
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	$user_id = (int) $user_id;

	if ( SP_ROLE_TEACHER === $role ) {
		$inserted = $wpdb->insert(
			sp_table( 'teachers' ),
			array(
				'user_id'   => $user_id,
				'full_name' => $full_name,
				'email'     => $email,
				'phone'     => $phone ? $phone : null,
				'bio'       => $args['bio'] ? sanitize_textarea_field( $args['bio'] ) : null,
				'status'    => 'active',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	} else {
		$inserted = $wpdb->insert(
			sp_table( 'student_profiles' ),
			array(
				'user_id'         => $user_id,
				'email'           => $email,
				'phone'           => $phone ? $phone : null,
				'dob'             => $dob ? $dob : null,
				'full_name'       => $full_name,
				'address'         => '' !== $address ? $address : null,
				'status'          => 'active',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	if ( false === $inserted ) {
		// Never leave a half-provisioned user behind.
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );

		return new WP_Error(
			'sp_profile_failed',
			'The account was created but its profile could not be saved, so it has been rolled back.'
		);
	}

	$record_id = (int) $wpdb->insert_id;

	sp_log_activity(
		'account.created',
		'account',
		$record_id,
		sprintf( '%s account created for %s', SP_ROLE_TEACHER === $role ? 'Teacher' : 'Student', $email )
	);

	if ( $args['send_email'] ) {
		// Pass the real password: $user->user_pass is a hash and cannot be used.
		sp_email_credentials( $user_id, $role, $full_name, $password );
	}

	return array(
		'user_id'   => $user_id,
		'record_id' => $record_id,
		'password'  => $password,
	);
}

/**
 * Regenerate a user's password and email the new one.
 *
 * @param int $user_id WP user id.
 * @return true|WP_Error
 */
function sp_reset_portal_password( $user_id ) {
	$user = get_user_by( 'id', (int) $user_id );

	if ( ! $user ) {
		return new WP_Error( 'sp_no_user', 'That user no longer exists.' );
	}

	$password = wp_generate_password( 16, true, false );

	wp_set_password( $password, $user->ID );

	$portal = sp_user_portal( $user );
	$name   = $user->display_name ? $user->display_name : $user->user_login;

	// Pass the freshly generated password; the stored hash is not the credential.
	sp_email_credentials( $user->ID, SP_ROLE_TEACHER === $portal ? SP_ROLE_TEACHER : SP_ROLE_STUDENT, $name, $password );

	return true;
}

/**
 * Enable or disable a portal record (and suspend its login).
 *
 * @param int    $record_id Profile or teacher row id.
 * @param string $record    'student' or 'teacher'.
 * @param string $status    'active' or 'disabled'.
 * @return true|WP_Error
 */
function sp_set_record_status( $record_id, $record, $status ) {
	global $wpdb;

	$record_id = (int) $record_id;
	$status    = in_array( $status, array( 'active', 'disabled' ), true ) ? $status : 'active';

	$table = ( 'teacher' === $record ) ? sp_table( 'teachers' ) : sp_table( 'student_profiles' );

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $record_id ) );

	if ( ! $row ) {
		return new WP_Error( 'sp_no_record', 'That record no longer exists.' );
	}

	$wpdb->update(
		$table,
		array( 'status' => $status ),
		array( 'id' => $record_id ),
		array( '%s' ),
		array( '%d' )
	);

	// The status column is the single source of truth for whether this account
	// may sign in; sp_reject_disabled_account() enforces it during
	// authentication. Scrambling the password here looked like it achieved the
	// same thing but made "disabled" unreachable (the login attempt already
	// failed as a wrong password) and permanently locked the account out even
	// after it was re-enabled, because nothing restored the old password.

	return true;
}

/**
 * The portal status attached to a WordPress user, if any.
 *
 * Administrators and users with no portal row return '' so they are never
 * treated as disabled.
 *
 * @param int $user_id WP user id.
 * @return string 'active', 'disabled' or ''.
 */
function sp_account_status_for_user( $user_id ) {
	$user_id = (int) $user_id;

	if ( $user_id <= 0 ) {
		return '';
	}

	$profile = sp_get_profile_by_user_id( $user_id );

	if ( $profile && isset( $profile->status ) ) {
		return (string) $profile->status;
	}

	$teacher = sp_get_teacher_by_user_id( $user_id );

	if ( $teacher && isset( $teacher->status ) ) {
		return (string) $teacher->status;
	}

	return '';
}

/**
 * Refuse sign-in for a portal record an administrator has disabled.
 *
 * Runs at priority 30, after wp_authenticate_username_password() and
 * wp_authenticate_email_password() have resolved a WP_User, so the password
 * has already been verified. Returning a distinct error code lets the login
 * handlers say "disabled" instead of the deliberately vague "wrong email or
 * password", without turning the login form into an account-existence oracle:
 * that distinction is only made once the password was correct.
 *
 * @param WP_User|WP_Error $user     Result of the earlier filters.
 * @param string           $username Submitted login.
 * @param string           $password Submitted password.
 * @return WP_User|WP_Error
 */
function sp_reject_disabled_account( $user, $username, $password ) {
	if ( ! $user instanceof WP_User ) {
		return $user;
	}

	if ( 'disabled' === sp_account_status_for_user( (int) $user->ID ) ) {
		return new WP_Error(
			'sp_account_disabled',
			__( 'This account has been disabled. Contact your institute to have it reactivated.', 'student-portal' )
		);
	}

	return $user;
}
add_filter( 'authenticate', 'sp_reject_disabled_account', 30, 3 );

/**
 * Remove a portal user's WordPress login, when they still have one.
 *
 * @param int $user_id WP user id.
 * @return bool Whether a user was deleted.
 */
function sp_delete_portal_user( $user_id ) {
	$user_id = (int) $user_id;

	if ( $user_id <= 0 ) {
		return false;
	}

	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return false;
	}

	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	return (bool) wp_delete_user( $user_id );
}

/**
 * Delete a student account and everything keyed to it.
 *
 * One profile can carry a lot of rows: enrolments (with their results, grade
 * history and this portal's certificate rows), attendance marks and exam
 * attempts. All of them go with the profile, then the WordPress login does.
 * Certificate Manager's own tables are never touched — a vendor record for
 * this student is their data, not ours to drop.
 *
 * @param int $profile_id Student profile id.
 * @return true|WP_Error
 */
function sp_delete_student_account( $profile_id ) {
	global $wpdb;

	$profile = sp_get_profile_by_id( $profile_id );

	if ( ! $profile ) {
		return new WP_Error( 'sp_no_profile', 'That student no longer exists.' );
	}

	$sp_enrollment_ids = $wpdb->get_col(
		$wpdb->prepare( 'SELECT id FROM ' . sp_table( 'enrollments' ) . ' WHERE profile_id = %d', (int) $profile->id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
	);

	if ( $sp_enrollment_ids ) {
		$sp_in = implode( ',', array_map( 'intval', $sp_enrollment_ids ) );

		$wpdb->query( 'DELETE FROM ' . sp_table( 'results' ) . " WHERE enrollment_id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
		$wpdb->query( 'DELETE FROM ' . sp_table( 'grade_sync_log' ) . " WHERE enrollment_id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
		$wpdb->query( 'DELETE FROM ' . sp_table( 'certificates' ) . " WHERE enrollment_id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
	}

	$wpdb->delete( sp_table( 'certificates' ), array( 'profile_id' => (int) $profile->id ), array( '%d' ) );
	$wpdb->delete( sp_table( 'attendance_records' ), array( 'profile_id' => (int) $profile->id ), array( '%d' ) );

	// Exam attempts: answers hang off the per-assignment student row.
	$sp_attempt_ids = $wpdb->get_col(
		$wpdb->prepare( 'SELECT id FROM ' . sp_table( 'assignment_students' ) . ' WHERE profile_id = %d', (int) $profile->id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
	);

	if ( $sp_attempt_ids ) {
		$sp_in = implode( ',', array_map( 'intval', $sp_attempt_ids ) );

		$wpdb->query( 'DELETE FROM ' . sp_table( 'assignment_answers' ) . " WHERE student_row_id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
	}

	$wpdb->delete( sp_table( 'assignment_students' ), array( 'profile_id' => (int) $profile->id ), array( '%d' ) );

	if ( $sp_enrollment_ids ) {
		$sp_in = implode( ',', array_map( 'intval', $sp_enrollment_ids ) );

		$wpdb->query( 'DELETE FROM ' . sp_table( 'enrollments' ) . " WHERE id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
	} else {
		$wpdb->delete( sp_table( 'enrollments' ), array( 'profile_id' => (int) $profile->id ), array( '%d' ) );
	}

	$wpdb->delete( sp_table( 'student_profiles' ), array( 'id' => (int) $profile->id ), array( '%d' ) );

	sp_delete_portal_user( (int) $profile->user_id );

	sp_log_activity(
		'student.deleted',
		'student_profile',
		(int) $profile->id,
		$profile->full_name . ' (' . $profile->email . ')'
	);

	return true;
}

/**
 * Delete a teacher account: their portal row, class links, attendance
 * overrides and WordPress login.
 *
 * Materials and assignments they authored stay — they belong to the class,
 * not to the account, and every check against `author_user_id` is a plain
 * integer comparison that stays safe when the user is gone.
 *
 * @param int $teacher_id Teacher id.
 * @return true|WP_Error
 */
function sp_delete_teacher_account( $teacher_id ) {
	global $wpdb;

	$teacher_id = (int) $teacher_id;
	$teacher    = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'teachers' ) . ' WHERE id = %d', $teacher_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
	);

	if ( ! $teacher ) {
		return new WP_Error( 'sp_no_teacher', 'That teacher no longer exists.' );
	}

	$wpdb->delete( sp_table( 'teacher_classes' ), array( 'teacher_id' => $teacher_id ), array( '%d' ) );
	$wpdb->delete( sp_table( 'teacher_attendance' ), array( 'teacher_id' => $teacher_id ), array( '%d' ) );
	$wpdb->delete( sp_table( 'teachers' ), array( 'id' => $teacher_id ), array( '%d' ) );

	sp_delete_portal_user( (int) $teacher->user_id );

	sp_log_activity(
		'teacher.deleted',
		'teacher',
		$teacher_id,
		$teacher->full_name . ' (' . $teacher->email . ')'
	);

	return true;
}