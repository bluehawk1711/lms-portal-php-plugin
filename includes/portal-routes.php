<?php
/**
 * Student portal routing and the in-portal login handler.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes the student portal understands.
 *
 * @return array
 */
function sp_portal_routes() {
	return array(
		'home'         => array( 'label' => 'Dashboard', 'template' => 'portal-dashboard.php', 'public' => true ),
		'certificates' => array( 'label' => 'Certificates', 'template' => 'portal-certificates.php', 'public' => false ),
		'attendance'   => array( 'label' => 'Attendance', 'template' => 'portal-attendance.php', 'public' => false ),
		'curriculum'   => array( 'label' => 'Curriculum', 'template' => 'portal-curriculum.php', 'public' => false ),
		'results'      => array( 'label' => 'Results', 'template' => 'portal-results.php', 'public' => false ),
		'materials'    => array( 'label' => 'Materials', 'template' => 'portal-materials.php', 'public' => false ),
		'tasks'        => array( 'label' => 'Tasks', 'template' => 'portal-tasks.php', 'public' => false ),
		'calendar'     => array( 'label' => 'Calendar', 'template' => 'portal-calendar.php', 'public' => false ),
		'profile'      => array( 'label' => 'Profile', 'template' => 'portal-profile.php', 'public' => false ),
		// The single-task page: reachable by URL, but the nav shows the list.
		// Fullscreen: exams get a distraction-free shell with no sidebar or rail.
		'task'         => array( 'label' => 'Task', 'template' => 'portal-task.php', 'public' => false, 'hidden' => true, 'parent' => 'tasks', 'fullscreen' => true ),
	);
}

/**
 * Register every portal rewrite rule.
 *
 * The rules resolve to the anchor pages created on activation so the request is
 * still a genuine singular page query, which keeps pretty permalinks, the title
 * tag and canonicals correct. The page templates are never used —
 * sp_render_portal() takes over in template_redirect and prints its own
 * document, so no theme header or footer ever wraps portal content.
 */
function sp_register_rewrite_rules() {
	sp_portal_rewrite_rules();
	sp_teacher_rewrite_rules();
}

/**
 * Register the /portal/ rewrite rules.
 */
function sp_portal_rewrite_rules() {
	add_rewrite_rule( '^portal/?$', 'index.php?pagename=portal&sp_route=home', 'top' );
	add_rewrite_rule( '^portal/(certificates|attendance|curriculum|results|materials|tasks|profile|calendar)/?$', 'index.php?pagename=portal&sp_route=$matches[1]', 'top' );
	add_rewrite_rule( '^portal/tasks/(\d+)/?$', 'index.php?pagename=portal&sp_route=task&sp_assignment_id=$matches[1]', 'top' );

	/*
	 * The certificate link stays its own top-level route so every old link
	 * keeps working, but the portal no longer draws a certificate: the route
	 * forwards to the Certificate Manager's public verify page, which renders
	 * the generated template with its QR code and PDF download. Anyone
	 * holding the link can open and print it, which is the whole point of
	 * handing a student their certificate.
	 */
	add_rewrite_rule( '^certificate/?$', 'index.php?sp_certificate_page=1', 'top' );
}

/**
 * Forward a portal certificate link to the Certificate Manager.
 *
 * The portal does not render certificates. This route exists so links issued
 * before the hand-off (and any bookmarked ones) keep resolving: it now 302s to
 * the vendor's verify page. A certificate the portal cannot map to a generated
 * vendor certificate shows the not-found page with the reason.
 */
function sp_maybe_render_certificate_page() {
	if ( ! get_query_var( 'sp_certificate_page' ) ) {
		return;
	}

	$certificate_id = isset( $_GET['certificate'] ) ? (int) $_GET['certificate'] : 0;

	if ( $certificate_id <= 0 ) {
		sp_render_certificate_missing();
	}

	$certificate = sp_get_certificate( $certificate_id );

	if ( ! $certificate ) {
		sp_render_certificate_missing();
	}

	$vendor_url = sp_certificate_vendor_url( $certificate );

	if ( is_wp_error( $vendor_url ) ) {
		sp_render_certificate_missing( $vendor_url->get_error_message() );
	}

	wp_safe_redirect( $vendor_url, 302 );
	exit;
}
add_action( 'template_redirect', 'sp_maybe_render_certificate_page', 25 );

/**
 * Expose the route query vars.
 *
 * @param string[] $vars Registered query vars.
 * @return string[]
 */
function sp_portal_query_vars( $vars ) {
	$vars[] = 'sp_route';
	$vars[] = 'sp_teacher_route';
	$vars[] = 'sp_teacher_id';
	$vars[] = 'sp_material_id';
	$vars[] = 'sp_assignment_id';
	$vars[] = 'sp_certificate_page';

	return $vars;
}
add_filter( 'query_vars', 'sp_portal_query_vars' );

/**
 * Dispatch portal requests.
 *
 * Runs on template_redirect so the plugin never depends on a theme template and
 * never replaces content a theme might already be rendering.
 */
function sp_maybe_render_portal() {
	$route = get_query_var( 'sp_route' );

	if ( null === $route || '' === $route ) {
		return;
	}

	$routes = sp_portal_routes();
	$slug   = sanitize_key( $route );

	if ( ! isset( $routes[ $slug ] ) ) {
		// Unmatched portal slugs should look like they never existed.
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return;
	}

	// The anchor page may have been deleted, which would have left WordPress
	// with a 404 query even though the route is legitimate.
	global $wp_query;

	if ( $wp_query->is_404 ) {
		$wp_query->is_404 = false;
		status_header( 200 );
	}

	$is_admin = current_user_can( 'manage_options' );

	if ( 'home' === $slug && ! is_user_logged_in() ) {
		sp_handle_portal_login();
		sp_render_portal( $slug, null );
		return;
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( sp_portal_home_url( 'student' ) );
		exit;
	}

	// Teachers have their own portal; do not let them wander into the student UI.
	if ( ! $is_admin && ! sp_is_student() ) {
		wp_safe_redirect(
			sp_portal_url_with_notice(
				sp_user_portal() ? sp_user_portal() : 'student',
				sp_wrong_portal_notice( 'student' )
			)
		);
		exit;
	}

	sp_render_portal( $slug, sp_get_profile_by_user_id( get_current_user_id() ) );
}
add_action( 'template_redirect', 'sp_maybe_render_portal', 20 );

/**
 * Process the in-portal login form.
 *
 * Redirects back to the portal with an error code rather than rendering inline,
 * so a browser refresh does not re-post the password.
 */
function sp_handle_portal_login() {
	if ( empty( $_POST['sp_portal_login'] ) ) {
		return;
	}

	$nonce = isset( $_POST['sp_portal_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_portal_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'sp_portal_login' ) ) {
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
				sp_portal_home_url( 'student' )
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
		// The password verified but the record is disabled. Report that instead
		// of counting it as a wrong guess, which would lock the account out on
		// top of already being disabled.
		if ( 'sp_account_disabled' === $user->get_error_code() ) {
			wp_safe_redirect( add_query_arg( 'sp_error', 'disabled', sp_portal_home_url( 'student' ) ) );
			exit;
		}

		sp_record_login_failure( $email );

		wp_safe_redirect( add_query_arg( 'sp_error', 'login', sp_portal_home_url( 'student' ) ) );
		exit;
	}

	sp_clear_login_failures( $email );

	$portal = sp_user_portal( $user );

	if ( '' === $portal ) {
		// Signed in successfully but this is not a portal account.
		wp_logout();
		wp_safe_redirect( add_query_arg( 'sp_error', 'not_portal', sp_portal_home_url( 'student' ) ) );
		exit;
	}

	sp_record_login( $portal, (int) $user->ID );

	if ( 'student' !== $portal ) {
		// A teacher signed in on the student form. Land them on their own
		// portal and say why they moved, rather than silently teleporting them.
		wp_safe_redirect( sp_portal_url_with_notice( $portal, sp_wrong_portal_notice( 'student' ) ) );
		exit;
	}

	wp_safe_redirect( sp_portal_home_url( $portal ) );
	exit;
}

/**
 * Stamp last_login_at on the profile or teacher row.
 *
 * @param string $portal  'student' or 'teacher'.
 * @param int    $user_id WP user id.
 */
function sp_record_login( $portal, $user_id ) {
	global $wpdb;

	$table = ( 'teacher' === $portal ) ? sp_table( 'teachers' ) : sp_table( 'student_profiles' );

	$wpdb->update(
		$table,
		array( 'last_login_at' => current_time( 'mysql' ) ),
		array( 'user_id' => (int) $user_id ),
		array( '%s' ),
		array( '%d' )
	);
}