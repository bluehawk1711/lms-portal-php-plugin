<?php
/**
 * Per-day teacher presence.
 *
 * Teachers self-mark present/absent/leave from their dashboard; an
 * administrator may override any teacher for any date. The auto-absent run
 * treats a date as cancelled for a class when every teacher assigned to it
 * is absent or on leave — there is nobody to take the register, so students
 * must not be marked against it.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Statuses a teacher attendance row may carry.
 *
 * @return string[]
 */
function sp_teacher_attendance_statuses() {
	return array( 'present', 'absent', 'leave' );
}

/**
 * One teacher's attendance row for a date.
 *
 * @param int    $teacher_id Teacher id.
 * @param string $date       Y-m-d.
 * @return object|null
 */
function sp_get_teacher_attendance( $teacher_id, $date ) {
	global $wpdb;

	$teacher_id = (int) $teacher_id;
	$date       = sp_sanitize_date( $date );

	if ( $teacher_id <= 0 || '' === $date ) {
		return null;
	}

	$table = sp_table( 'teacher_attendance' );

	return $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE teacher_id = %d AND session_date = %s", $teacher_id, $date )
	);
}

/**
 * Teacher attendance rows keyed by teacher id, for one date.
 *
 * @param string $date Y-m-d.
 * @return array<int,object>
 */
function sp_teacher_attendance_map( $date ) {
	global $wpdb;

	$date = sp_sanitize_date( $date );

	if ( '' === $date ) {
		return array();
	}

	$table = sp_table( 'teacher_attendance' );

	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE session_date = %s", $date )
	);

	$map = array();

	foreach ( (array) $rows as $row ) {
		$map[ (int) $row->teacher_id ] = $row;
	}

	return $map;
}

/**
 * Record (or overwrite) a teacher's attendance for a date.
 *
 * @param int    $teacher_id Teacher id.
 * @param string $date       Y-m-d.
 * @param string $status     present|absent|leave.
 * @param array  $args       note, marked_by.
 * @return true|WP_Error
 */
function sp_mark_teacher_attendance( $teacher_id, $date, $status, $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'note'      => '',
			'marked_by' => 0,
		)
	);

	$teacher_id = (int) $teacher_id;
	$date       = sp_sanitize_date( $date );
	$status     = sanitize_key( $status );

	if ( $teacher_id <= 0 ) {
		return new WP_Error( 'sp_no_teacher', 'No teacher profile is linked to that account.' );
	}

	if ( '' === $date ) {
		return new WP_Error( 'sp_bad_date', 'Pick a valid date.' );
	}

	if ( ! in_array( $status, sp_teacher_attendance_statuses(), true ) ) {
		return new WP_Error( 'sp_bad_status', 'Choose present, absent or leave.' );
	}

	$note       = sanitize_textarea_field( $args['note'] );
	$marked_by  = (int) $args['marked_by'] ? (int) $args['marked_by'] : get_current_user_id();

	$wpdb->query(
		$wpdb->prepare(
			'INSERT INTO ' . sp_table( 'teacher_attendance' ) . ' (teacher_id, session_date, status, note, marked_by, marked_at)
			 VALUES (%d, %s, %s, %s, %d, %s)
			 ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), marked_by = VALUES(marked_by), marked_at = VALUES(marked_at)',
			$teacher_id,
			$date,
			$status,
			'' !== $note ? $note : null,
			$marked_by,
			current_time( 'mysql' )
		)
	);

	sp_log_activity( 'teacher_attendance.marked', 'teacher', $teacher_id, sprintf( '%s: %s', $date, $status ) );

	return true;
}

/**
 * Remove a teacher's attendance row for a date (back to unmarked).
 *
 * @param int    $teacher_id Teacher id.
 * @param string $date       Y-m-d.
 * @return bool True when a row was removed.
 */
function sp_clear_teacher_attendance( $teacher_id, $date ) {
	global $wpdb;

	$teacher_id = (int) $teacher_id;
	$date       = sp_sanitize_date( $date );

	if ( $teacher_id <= 0 || '' === $date ) {
		return false;
	}

	$deleted = $wpdb->delete(
		sp_table( 'teacher_attendance' ),
		array(
			'teacher_id'  => $teacher_id,
			'session_date' => $date,
		),
		array( '%d', '%s' )
	);

	if ( ! $deleted ) {
		return false;
	}

	sp_log_activity( 'teacher_attendance.cleared', 'teacher', $teacher_id, sprintf( '%s: unmarked', $date ) );

	return true;
}

/**
 * Are all teachers assigned to a class unavailable on a date?
 *
 * Unmarked counts as available — the rule only fires when every assigned
 * teacher is positively marked absent or on leave. A class with no
 * assigned teachers is never "unavailable" by this rule.
 *
 * @param string $date     Y-m-d.
 * @param int    $class_id Class id.
 * @return bool
 */
function sp_class_teachers_unavailable( $date, $class_id ) {
	global $wpdb;

	$date     = sp_sanitize_date( $date );
	$class_id = (int) $class_id;

	if ( '' === $date || $class_id <= 0 ) {
		return false;
	}

	$teachers = sp_get_class_teachers( $class_id );

	if ( ! $teachers ) {
		return false;
	}

	$ids = array_values( array_unique( array_map( 'intval', wp_list_pluck( $teachers, 'id' ) ) ) );

	if ( ! $ids ) {
		return false;
	}

	$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
	$table        = sp_table( 'teacher_attendance' );

	$matched = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT teacher_id) FROM {$table}
			 WHERE session_date = %s AND teacher_id IN ( {$placeholders} )
			   AND status IN ( 'absent', 'leave' )",
			array_merge( array( $date ), $ids )
		)
	);

	return count( $ids ) > 0 && $matched === count( $ids );
}

/**
 * Every date where all assigned teachers of a class were unavailable.
 *
 * @param int $class_id Class id.
 * @return string[] Y-m-d list.
 */
function sp_teacher_unavailable_dates( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return array();
	}

	$teachers = sp_get_class_teachers( $class_id );

	if ( ! $teachers ) {
		return array();
	}

	$ids = array_values( array_unique( array_map( 'intval', wp_list_pluck( $teachers, 'id' ) ) ) );

	if ( ! $ids ) {
		return array();
	}

	$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
	$table        = sp_table( 'teacher_attendance' );

	return array_map(
		'strval',
		(array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT session_date FROM {$table}
				 WHERE teacher_id IN ( {$placeholders} )
				   AND status IN ( 'absent', 'leave' )
				 GROUP BY session_date
				 HAVING COUNT(DISTINCT teacher_id) = %d",
				array_merge( $ids, array( count( $ids ) ) )
			)
		)
	);
}
