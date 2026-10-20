<?php
/**
 * Teacher portal routing and its in-portal login handler.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes the teacher portal understands.
 *
 * @return array
 */
function sp_teacher_routes() {
	return array(
		'home'          => array( 'label' => 'Dashboard', 'template' => 'teacher-dashboard.php', 'public' => true ),
		'classes'     => array( 'label' => 'My classes', 'template' => 'teacher-classes.php', 'public' => false ),
		'class'      => array( 'label' => 'Class', 'template' => 'teacher-class.php', 'public' => false ),
		'materials'     => array( 'label' => 'Materials', 'template' => 'teacher-materials.php', 'public' => false ),
		'material-edit' => array( 'label' => 'Edit material', 'template' => 'teacher-material-edit.php', 'public' => false ),
		'assignments'     => array( 'label' => 'Assignments', 'template' => 'teacher-assignments.php', 'public' => false ),
		'attempts'        => array( 'label' => 'Attempts', 'template' => 'teacher-attempt.php', 'public' => false ),
		'assignment-edit' => array( 'label' => 'Assignment', 'template' => 'teacher-assignment-edit.php', 'public' => false ),
		'results'       => array( 'label' => 'Results', 'template' => 'teacher-results.php', 'public' => false ),
		'calendar'      => array( 'label' => 'Calendar', 'template' => 'teacher-calendar.php', 'public' => false ),
		'profile'       => array( 'label' => 'Profile', 'template' => 'teacher-profile.php', 'public' => false ),
	);
}

/**
 * Register the /teacher/ rewrite rules.
 */
function sp_teacher_rewrite_rules() {
	add_rewrite_rule( '^teacher/?$', 'index.php?pagename=teacher&sp_teacher_route=home', 'top' );
	add_rewrite_rule( '^teacher/(classes|materials|results|assignments|calendar|profile)/?$', 'index.php?pagename=teacher&sp_teacher_route=$matches[1]', 'top' );
	add_rewrite_rule( '^teacher/materials/new/?$', 'index.php?pagename=teacher&sp_teacher_route=material-edit', 'top' );
	add_rewrite_rule( '^teacher/materials/(\d+)/?$', 'index.php?pagename=teacher&sp_teacher_route=material-edit&sp_material_id=$matches[1]', 'top' );
	add_rewrite_rule( '^teacher/assignments/new/?$', 'index.php?pagename=teacher&sp_teacher_route=assignment-edit', 'top' );
	add_rewrite_rule( '^teacher/assignments/(\d+)/?$', 'index.php?pagename=teacher&sp_teacher_route=assignment-edit&sp_assignment_id=$matches[1]', 'top' );
	add_rewrite_rule( '^teacher/attempts/(\d+)/?$', 'index.php?pagename=teacher&sp_teacher_route=attempts&sp_attempt_id=$matches[1]', 'top' );
	add_rewrite_rule( '^teacher/classes/(\d+)/?$', 'index.php?pagename=teacher&sp_teacher_route=class&sp_teacher_id=$matches[1]', 'top' );
}

/**
 * Dispatch teacher portal requests.
 */
function sp_maybe_render_teacher_portal() {
	$route = get_query_var( 'sp_teacher_route' );

	if ( null === $route || '' === $route ) {
		return;
	}

	global $wp_query;

	$routes = sp_teacher_routes();
	$slug   = sanitize_key( $route );

	if ( ! isset( $routes[ $slug ] ) ) {
		$wp_query->set_404();
		status_header( 404 );
		return;
	}

	if ( $wp_query->is_404 ) {
		$wp_query->is_404 = false;
		status_header( 200 );
	}

	$is_admin = current_user_can( 'manage_options' );

	if ( 'home' === $slug && ! is_user_logged_in() ) {
		sp_handle_teacher_login();
		sp_render_teacher_portal( $slug, null );
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( sp_portal_home_url( 'teacher' ) );
		exit;
	}

	if ( ! $is_admin && ! sp_is_teacher() ) {
		wp_safe_redirect(
			sp_portal_url_with_notice(
				sp_user_portal() ? sp_user_portal() : 'teacher',
				sp_wrong_portal_notice( 'teacher' )
			)
		);
		exit;
	}

	sp_render_teacher_portal( $slug, sp_get_teacher_by_user_id( get_current_user_id() ) );
}
add_action( 'template_redirect', 'sp_maybe_render_teacher_portal', 20 );

/**
 * Process the teacher login form.
 */
function sp_handle_teacher_login() {
	if ( empty( $_POST['sp_teacher_login'] ) ) {
		return;
	}

	$nonce = isset( $_POST['sp_teacher_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_teacher_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'sp_teacher_login' ) ) {
		wp_die( esc_html__( 'Your session expired. Please try again.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	$email    = isset( $_POST['sp_email'] ) ? sanitize_email( wp_unslash( $_POST['sp_email'] ) ) : '';
	$password = isset( $_POST['sp_password'] ) ? (string) wp_unslash( $_POST['sp_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- verified against WP auth.

	// Throttle repeated guesses against this address before doing any work.
	$locked = sp_login_lockout_seconds( $email );

	if ( $locked > 0 ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'sp_error' => 'throttled',
					'sp_wait'  => $locked,
				),
				sp_portal_home_url( 'teacher' )
			)
		);
		exit;
	}

	$user = wp_signon(
		array(
			'user_login'    => $email,
			'user_password' => $password,
			'remember'      => ! empty( $_POST['sp_remember'] ),
		),
		is_ssl()
	);

	if ( is_wp_error( $user ) ) {
		// Password verified but the record is disabled: report it plainly and
		// do not add a failed attempt to the lockout counter.
		if ( 'sp_account_disabled' === $user->get_error_code() ) {
			wp_safe_redirect( add_query_arg( 'sp_error', 'disabled', sp_portal_home_url( 'teacher' ) ) );
			exit;
		}

		sp_record_login_failure( $email );

		wp_safe_redirect( add_query_arg( 'sp_error', 'login', sp_portal_home_url( 'teacher' ) ) );
		exit;
	}

	sp_clear_login_failures( $email );

	$portal = sp_user_portal( $user );

	if ( '' === $portal ) {
		wp_logout();
		wp_safe_redirect( add_query_arg( 'sp_error', 'not_portal', sp_portal_home_url( 'teacher' ) ) );
		exit;
	}

	sp_record_login( $portal, (int) $user->ID );

	if ( 'teacher' !== $portal ) {
		// A student signed in on the teacher form; explain the move instead of
		// dropping them on their dashboard without a word.
		wp_safe_redirect( sp_portal_url_with_notice( $portal, sp_wrong_portal_notice( 'teacher' ) ) );
		exit;
	}

	wp_safe_redirect( sp_portal_home_url( $portal ) );
	exit;
}

/**
 * Resolve the teacher row for a class id on the current request, checking
 * the assignment.
 *
 * @param int $class_id Class id.
 * @return object|WP_Error Teacher row.
 */
function sp_require_assigned_teacher( $class_id ) {
	$teacher = sp_get_teacher_by_user_id( get_current_user_id() );

	if ( ! $teacher && current_user_can( 'manage_options' ) ) {
		return true;
	}

	if ( ! $teacher ) {
		return new WP_Error( 'sp_not_teacher', 'This area is for teachers.', array( 'status' => 403 ) );
	}

	if ( ! sp_get_class( $class_id ) ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.', array( 'status' => 404 ) );
	}

	if ( ! sp_teacher_can_access_class( $teacher->id, $class_id ) ) {
		return new WP_Error(
			'sp_not_assigned',
			'You are not assigned to that class. Ask an administrator to assign you.',
			array( 'status' => 403 )
		);
	}

	return $teacher;
}