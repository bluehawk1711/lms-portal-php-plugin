<?php
/**
 * Roles, capabilities and admin-area access control.
 *
 * Both portal roles intentionally carry only `read`. Every real authorization
 * decision lives in PHP, checked against sp_enrollments / sp_teacher_classes
 * membership, because a capability alone cannot express "this student, this
 * class".
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SP_ROLE_STUDENT = 'sp_student';
const SP_ROLE_TEACHER = 'sp_teacher';

/**
 * Create (or repair) the two portal roles.
 */
function sp_install_roles() {
	$caps = array( 'read' => true );

	foreach ( array( SP_ROLE_STUDENT => 'Portal Student', SP_ROLE_TEACHER => 'Portal Teacher' ) as $slug => $label ) {
		$role = get_role( $slug );

		if ( $role ) {
			$role->add_cap( 'read' );
			continue;
		}

		add_role( $slug, $label, $caps );
	}

	// Grant the admin cap used to gate the portal's admin screens even when
	// the Certificate Manager plugin (which normally provides it) is absent.
	$admin = get_role( 'administrator' );
	if ( $admin instanceof WP_Role ) {
		$admin->add_cap( 'manage_certificates' );
	}
}

/**
 * Remove both roles. Only used by uninstall.php, never on deactivation.
 */
function sp_remove_roles() {
	remove_role( SP_ROLE_STUDENT );
	remove_role( SP_ROLE_TEACHER );
}

/**
 * Whether a user holds a given role.
 *
 * WP_User::$roles is a plain LIST of role names -- core builds it with
 * `$this->roles[] = $key;` (see WP_User::get_role_caps()) -- so
 * `$user->roles['sp_teacher']` is always unset. Testing the value with in_array()
 * is the only correct check. $user->caps is the string-keyed map if you ever
 * need the map form, but roles is a list.
 *
 * @param WP_User $user User to test.
 * @param string  $role Role slug.
 * @return bool
 */
function sp_user_has_role( $user, $role ) {
	if ( ! $user instanceof WP_User ) {
		return false;
	}

	// $user->roles is a list; fall back to caps for anything unusual.
	if ( in_array( $role, (array) $user->roles, true ) ) {
		return true;
	}

	return isset( $user->caps[ $role ] );
}

/**
 * Whether the given user holds a portal role.
 *
 * Administrators are never treated as portal users: they keep full wp-admin
 * access and can still preview the frontend.
 *
 * @param WP_User|null $user User to test. Defaults to the current user.
 * @return bool
 */
function sp_is_portal_user( $user = null ) {
	if ( ! $user instanceof WP_User ) {
		$user = wp_get_current_user();
	}

	if ( ! $user->exists() ) {
		return false;
	}

	if ( user_can( $user, 'manage_options' ) ) {
		return false;
	}

	return sp_user_has_role( $user, SP_ROLE_STUDENT ) || sp_user_has_role( $user, SP_ROLE_TEACHER );
}

/**
 * Whether the current user is a portal student.
 *
 * @return bool
 */
function sp_is_student() {
	$user = wp_get_current_user();

	return $user->exists() && ! user_can( $user, 'manage_options' ) && sp_user_has_role( $user, SP_ROLE_STUDENT );
}

/**
 * Whether the current user is a portal teacher.
 *
 * @return bool
 */
function sp_is_teacher() {
	$user = wp_get_current_user();

	return $user->exists() && ! user_can( $user, 'manage_options' ) && sp_user_has_role( $user, SP_ROLE_TEACHER );
}

/**
 * Which portal home a user belongs to.
 *
 * @param WP_User|null $user User to test. Defaults to the current user.
 * @return string 'student', 'teacher' or '' for a non-portal user.
 */
function sp_user_portal( $user = null ) {
	if ( ! $user instanceof WP_User ) {
		$user = wp_get_current_user();
	}

	if ( user_can( $user, 'manage_options' ) ) {
		return '';
	}

	if ( sp_user_has_role( $user, SP_ROLE_TEACHER ) ) {
		return 'teacher';
	}

	if ( sp_user_has_role( $user, SP_ROLE_STUDENT ) ) {
		return 'student';
	}

	return '';
}

/**
 * Redirect portal users away from wp-admin to their own dashboard.
 */
function sp_block_admin_for_portal_users() {
	if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		return;
	}

	if ( ! sp_is_portal_user() ) {
		return;
	}

	wp_safe_redirect( sp_portal_home_url( sp_user_portal() ), 302 );
	exit;
}
add_action( 'admin_init', 'sp_block_admin_for_portal_users', 1 );

/**
 * Hide the admin bar for portal users on the frontend.
 *
 * @param bool $show Current value.
 * @return bool
 */
function sp_hide_admin_bar_for_portal_users( $show ) {
	return sp_is_portal_user() ? false : $show;
}
add_filter( 'show_admin_bar', 'sp_hide_admin_bar_for_portal_users' );

/**
 * Send portal users to their own dashboard after a wp-login.php login.
 *
 * @param string           $redirect_to Requested redirect.
 * @param string           $request     Raw requested redirect.
 * @param WP_User|WP_Error $user        Authenticated user or error.
 * @return string
 */
function sp_login_redirect( $redirect_to, $request, $user ) {
	if ( ! $user instanceof WP_User ) {
		return $redirect_to;
	}

	$portal = sp_user_portal( $user );

	if ( '' === $portal ) {
		return $redirect_to;
	}

	// Honour an explicit, safe in-site destination so deep links still work.
	if ( $request && false === strpos( $request, 'wp-admin' ) ) {
		$target = wp_validate_redirect( $request, '' );

		if ( $target && false === strpos( $target, '/wp-admin' ) ) {
			return $target;
		}
	}

	return sp_portal_home_url( $portal );
}
add_filter( 'login_redirect', 'sp_login_redirect', 10, 3 );

/**
 * After logout, land portal users back on the right login screen.
 *
 * @param string $redirect_to Requested redirect.
 * @return string
 */
function sp_logout_redirect( $redirect_to ) {
	if ( ! is_string( $redirect_to ) || '' === $redirect_to ) {
		return $redirect_to;
	}

	if ( false !== strpos( $redirect_to, '/portal/' ) ) {
		return sp_portal_home_url( 'student' );
	}

	if ( false !== strpos( $redirect_to, '/teacher/' ) ) {
		return sp_portal_home_url( 'teacher' );
	}

	return $redirect_to;
}
add_filter( 'logout_redirect', 'sp_logout_redirect', 10, 1 );