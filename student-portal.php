<?php
/**
 * Plugin Name:       Student & Teacher Portal
 * Plugin URI:        https://gaurav-dadhich-portfolio.vercel.app
 * Description:       Student and teacher portal: accounts, courses, enrollment, attendance, results, course notes and documents. Reads student records from the Certificate Manager plugin without modifying its tables.
 * Version:           0.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Gaurav Dadhich 
 * Text Domain:       student-portal
 * Domain Path:       /languages
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SP_VERSION', '0.3.0' );
define( 'SP_PATH', plugin_dir_path( __FILE__ ) );
define( 'SP_URL', plugin_dir_url( __FILE__ ) );

require_once SP_PATH . 'includes/db.php';
require_once SP_PATH . 'includes/roles.php';
require_once SP_PATH . 'includes/repository.php';
require_once SP_PATH . 'includes/grading.php';
require_once SP_PATH . 'includes/settings.php';
require_once SP_PATH . 'includes/datetime.php';
require_once SP_PATH . 'includes/mailer.php';
require_once SP_PATH . 'includes/accounts.php';
require_once SP_PATH . 'includes/classes.php';
require_once SP_PATH . 'includes/courses.php';
require_once SP_PATH . 'includes/curriculum.php';
require_once SP_PATH . 'includes/assignments.php';
require_once SP_PATH . 'includes/timings.php';
require_once SP_PATH . 'includes/materials.php';
require_once SP_PATH . 'includes/attendance.php';
require_once SP_PATH . 'includes/results.php';
require_once SP_PATH . 'includes/certificates.php';
require_once SP_PATH . 'includes/cert-push.php';
require_once SP_PATH . 'includes/activity.php';
require_once SP_PATH . 'includes/calendar.php';
require_once SP_PATH . 'includes/schedule.php';
require_once SP_PATH . 'includes/teacher-attendance.php';
require_once SP_PATH . 'includes/auto-absent.php';
require_once SP_PATH . 'includes/import.php';
require_once SP_PATH . 'includes/announcements.php';
require_once SP_PATH . 'includes/id-cards.php';
require_once SP_PATH . 'includes/portal-routes.php';
require_once SP_PATH . 'includes/portal-render.php';
require_once SP_PATH . 'includes/teacher-routes.php';
require_once SP_PATH . 'includes/teacher-render.php';
require_once SP_PATH . 'includes/admin-menu.php';

register_activation_hook( __FILE__, 'sp_activate' );
register_deactivation_hook( __FILE__, 'sp_deactivate' );

add_action( 'plugins_loaded', 'sp_maybe_upgrade' );
add_action( 'plugins_loaded', 'sp_install_roles' );
add_action( 'init', 'sp_register_rewrite_rules', 5 );
add_action( 'init', 'sp_maybe_flush_rewrite_rules', 20 );

/**
 * Activation: install schema, roles, pages and rewrite rules.
 *
 * Deliberately non-destructive beyond that — deactivation must never drop data.
 */
function sp_activate() {
	sp_install();
	sp_install_roles();
	sp_create_portal_pages();
	sp_register_rewrite_rules();
	flush_rewrite_rules();

	if ( ! wp_next_scheduled( 'sp_daily_housekeeping' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sp_daily_housekeeping' );
	}
}

/**
 * Deactivation: only flush rewrite rules. Tables, options and roles survive.
 *
 * The housekeeping event is left scheduled on purpose: deactivation must not
 * delete anything, and a deactivated plugin cannot run its callback anyway.
 */
function sp_deactivate() {
	sp_register_rewrite_rules();
	flush_rewrite_rules();
}

/**
 * Bump this whenever a route slug or the rewrite rules themselves change.
 *
 * WordPress persists the resolved rule set in the `rewrite_rules` option and only
 * rebuilds it on an explicit flush. Editing a slug in code therefore leaves the
 * stored copy stale and every affected URL 404s until someone visits
 * Settings -> Permalinks. Comparing a stored version against this constant makes
 * the plugin repair itself on the next request instead.
 */
define( 'SP_REWRITE_VERSION', '8' );

/**
 * Re-flush the stored rewrite rules when our route definitions have changed.
 *
 * Runs late on init so every rule is registered before the flush happens. The
 * guard is a cheap option read, so this is a no-op on normal requests.
 */
function sp_maybe_flush_rewrite_rules() {
	if ( get_option( 'sp_rewrite_version' ) === SP_REWRITE_VERSION ) {
		return;
	}

	// Another plugin may have rewritten the rules since we last stored our version.
	if ( ! function_exists( 'flush_rewrite_rules' ) ) {
		return;
	}

	sp_register_rewrite_rules();
	flush_rewrite_rules( false );

	update_option( 'sp_rewrite_version', SP_REWRITE_VERSION );
}

/**
 * Force a one-time rewrite flush so newly added material/task routes register
 * immediately even when an object cache serves a stale rewrite_rules option.
 */
function sp_force_flush_rewrite_rules() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	if ( ! function_exists( 'flush_rewrite_rules' ) ) {
		return;
	}

	sp_register_rewrite_rules();
	flush_rewrite_rules( false );
	update_option( 'sp_rewrite_version', SP_REWRITE_VERSION );
}
add_action( 'init', 'sp_force_flush_rewrite_rules', 19 );

/**
 * Create the anchor pages backing the portal and teacher portal slugs.
 *
 * The pages exist so WordPress has a permalink target and so the slugs appear
 * in the admin; the rewrite rules in sp_register_rewrite_rules() win at runtime.
 */
function sp_create_portal_pages() {
	$pages = array(
		'portal'  => 'Student Portal',
		'teacher' => 'Teacher Portal',
	);

	foreach ( $pages as $slug => $title ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}

		wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => '',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);
	}
}

/**
 * Public URL for a portal home page.
 *
 * @param string $which 'student' or 'teacher'.
 * @return string
 */
function sp_portal_home_url( $which = 'student' ) {
	return home_url( 'teacher' === $which ? '/teacher/' : '/portal/' );
}

/**
 * URL of a single frontend route.
 *
 * @param string $path Sub path, e.g. 'attendance'.
 * @return string
 */
function sp_portal_url( $path = '' ) {
	$base = home_url( '/portal/' . ltrim( (string) $path, '/' ) );
	return user_trailingslashit( $base );
}

/**
 * URL of a single teacher route.
 *
 * @param string $path Sub path, e.g. 'classes'.
 * @return string
 */
function sp_teacher_url( $path = '' ) {
	$base = home_url( '/teacher/' . ltrim( (string) $path, '/' ) );
	return user_trailingslashit( $base );
}

/**
 * Gated URL that streams a course material file.
 *
 * @param int $material_id sp_materials row id.
 * @return string
 */
function sp_material_url( $material_id ) {
	return add_query_arg( 'sp_material', (int) $material_id, home_url( '/' ) );
}

/**
 * Gated URL that serves a course material file for inline display.
 *
 * The stream honours `inline=1` by sending Content-Disposition: inline for
 * types a browser can render (PDF, images, plain text), which is what the
 * portal's embedded previews point at. Anything else still downloads.
 *
 * @param int $material_id sp_materials row id.
 * @return string
 */
function sp_material_inline_url( $material_id ) {
	return add_query_arg( 'inline', '1', sp_material_url( $material_id ) );
}