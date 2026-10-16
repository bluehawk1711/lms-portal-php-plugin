<?php
/**
 * Uninstall routine.
 *
 * Nothing is deleted unless the site owner has explicitly opted in by setting
 * the `sp_delete_data_on_uninstall` option. This mirrors the plugin's own
 * policy of never destroying data on deactivation.
 *
 * @package StudentPortal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$sp_opt_in = get_option( 'sp_delete_data_on_uninstall' );

if ( ! $sp_opt_in ) {
	return;
}

global $wpdb;

	$sp_tables = array(
		'grade_sync_log',
		'email_log',
		'activity_log',
		'materials',
		'results',
		'attendance_audit',
		'attendance_records',
		'attendance_sessions',
		'enrollments',
		'teacher_classes',
		'class_timings',
		'timing_set_days',
		'timing_sets',
		'classes',
	'courses',
	'teachers',
	'student_profiles',
	'calendar_events',
	'teacher_attendance',
	'curriculum',
	'curriculum_progress',
	'assignment_answers',
	'assignment_students',
	'assignment_set_questions',
	'assignment_sets',
	'assignments',
	'questions',
);

foreach ( $sp_tables as $sp_table_name ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'sp_' . $sp_table_name ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifier built from a fixed list.
}

delete_option( 'sp_settings' );
delete_option( 'sp_db_version' );
delete_option( 'sp_delete_data_on_uninstall' );

// Remove the private course-material folder, including its deny rules.
if ( function_exists( 'sp_delete_private_material_dir' ) ) {
	sp_delete_private_material_dir();
} elseif ( is_dir( WP_CONTENT_DIR . '/uploads/sp-private' ) ) {
	// uninstall.php runs without the plugin loaded, so fall back to a plain sweep.
	$sp_private = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( WP_CONTENT_DIR . '/uploads/sp-private', FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $sp_private as $sp_item ) {
		if ( $sp_item->isDir() ) {
			@rmdir( $sp_item->getPathname() );
		} else {
			@unlink( $sp_item->getPathname() );
		}
	}
	@rmdir( WP_CONTENT_DIR . '/uploads/sp-private' );
}

// Remove the portal roles and the accounts that only ever used them.
require_once ABSPATH . 'wp-admin/includes/user.php';

foreach ( array( 'sp_student', 'sp_teacher' ) as $sp_role_slug ) {
	$sp_users = get_users(
		array(
			'role'   => $sp_role_slug,
			'fields' => 'ID',
			'number' => -1,
		)
	);

	foreach ( $sp_users as $sp_user_id ) {
		wp_delete_user( $sp_user_id );
	}

	remove_role( $sp_role_slug );
}

// The anchor pages are left in place: they may be linked from elsewhere.