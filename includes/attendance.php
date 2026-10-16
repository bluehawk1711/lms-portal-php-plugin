<?php
/**
 * Attendance: session register, bulk save, submit, reopen and audit.
 *
 * The submit/reopen rules are enforced in the save handler as well as in the
 * UI, so a stale page cannot be used to bypass the edit window.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valid per-student attendance states.
 *
 * @return string[]
 */
function sp_attendance_statuses() {
	return array( 'present', 'absent', 'late', 'excused' );
}

/**
 * Fetch an attendance session row.
 *
 * @param int $session_id Session id.
 * @return object|null
 */
function sp_get_session( $session_id ) {
	global $wpdb;

	$session_id = (int) $session_id;

	if ( $session_id <= 0 ) {
		return null;
	}

	$table = sp_table( 'attendance_sessions' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $session_id ) );
}

/**
 * Find the session for a class on a date, if one exists.
 *
 * @param int    $class_id Class id.
 * @param string $date        Y-m-d.
 * @return object|null
 */
function sp_get_session_by_date( $class_id, $date ) {
	global $wpdb;

	$class_id = (int) $class_id;
	$date        = sp_sanitize_date( $date );

	if ( $class_id <= 0 || '' === $date ) {
		return null;
	}

	$table = sp_table( 'attendance_sessions' );

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE class_id = %d AND session_date = %s",
			$class_id,
			$date
		)
	);
}

/**
 * Create an attendance session for a date.
 *
 * @param int    $class_id Class id.
 * @param string $date        Y-m-d.
 * @param string $topic       Optional session topic.
 * @return int|WP_Error Session id.
 */
function sp_create_session( $class_id, $date, $topic = '' ) {
	global $wpdb;

	$class_id = (int) $class_id;
	$date        = sp_sanitize_date( $date );

	if ( ! sp_get_class( $class_id ) ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	if ( '' === $date ) {
		return new WP_Error( 'sp_bad_date', 'Enter a valid session date.' );
	}

	// No class runs on an off day, so no register may be opened either.
	if ( sp_is_off_day( $date ) ) {
		return new WP_Error(
			'sp_off_day',
			sprintf(
				'%s is an off day (%s), so no register can be opened. Change the off days in Settings if this is wrong.',
				date_i18n( get_option( 'date_format' ), strtotime( $date ) ),
				sp_off_days_label()
			)
		);
	}

	// Calendar holiday/test, or every assigned teacher away: same rule, no register.
	if ( sp_date_cancels_class( $date, $class_id ) ) {
		return new WP_Error(
			'sp_class_cancelled',
			sprintf(
				'%s is cancelled on the portal calendar (holiday, test, or no teacher available), so no register can be opened.',
				date_i18n( get_option( 'date_format' ), strtotime( $date ) )
			)
		);
	}

	$existing = sp_get_session_by_date( $class_id, $date );

	if ( $existing ) {
		return (int) $existing->id;
	}

	$wpdb->insert(
		sp_table( 'attendance_sessions' ),
		array(
			'class_id'  => $class_id,
			'session_date' => $date,
			'topic'        => $topic ? sanitize_text_field( $topic ) : null,
			'status'       => 'open',
		),
		array( '%d', '%s', '%s', '%s' )
	);

	return (int) $wpdb->insert_id;
}

/**
 * Update a session's topic.
 *
 * @param int    $session_id Session id.
 * @param string $topic      Topic text.
 * @return true|WP_Error
 */
function sp_set_session_topic( $session_id, $topic ) {
	global $wpdb;

	$session = sp_get_session( $session_id );

	if ( ! $session ) {
		return new WP_Error( 'sp_no_session', 'That session no longer exists.' );
	}

	if ( ! sp_attendance_session_is_editable( $session ) ) {
		return new WP_Error(
			'sp_session_locked',
			'This register has been submitted and is no longer editable. Ask an administrator to reopen it.'
		);
	}

	$wpdb->update(
		sp_table( 'attendance_sessions' ),
		array( 'topic' => $topic ? sanitize_text_field( $topic ) : null ),
		array( 'id' => (int) $session_id ),
		array( '%s' ),
		array( '%d' )
	);

	return true;
}

/**
 * Current marks for a session, keyed by profile id.
 *
 * @param int $session_id Session id.
 * @return array<int,string>
 */
function sp_get_session_records( $session_id ) {
	global $wpdb;

	$session_id = (int) $session_id;

	if ( $session_id <= 0 ) {
		return array();
	}

	$table = sp_table( 'attendance_records' );

	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT profile_id, status FROM {$table} WHERE session_id = %d", $session_id )
	);

	$records = array();

	foreach ( $rows as $row ) {
		$records[ (int) $row->profile_id ] = (string) $row->status;
	}

	return $records;
}

/**
 * Bulk-save the register for a session.
 *
 * @param int   $session_id Session id.
 * @param array $marks      profile_id => status.
 * @return true|WP_Error
 */
function sp_save_attendance_records( $session_id, $marks ) {
	global $wpdb;

	$session = sp_get_session( $session_id );

	if ( ! $session ) {
		return new WP_Error( 'sp_no_session', 'That session no longer exists.' );
	}

	if ( ! sp_attendance_session_is_editable( $session ) ) {
		return new WP_Error(
			'sp_session_locked',
			'This register has been submitted and is no longer editable. Ask an administrator to reopen it.'
		);
	}

	$valid       = sp_attendance_statuses();
	$enrolled    = sp_get_enrollments_for_class( (int) $session->class_id, array( 'active' ), 0 );
	$allowed_ids = wp_list_pluck( $enrolled, 'profile_id' );

	$existing = sp_get_session_records( $session_id );
	$now      = get_current_user_id();
	$changes  = 0;

	foreach ( $marks as $profile_id => $status ) {
		$profile_id = (int) $profile_id;
		$status     = sanitize_key( $status );

		// Ignore anything not on the active roster.
		if ( ! in_array( $profile_id, array_map( 'intval', $allowed_ids ), true ) ) {
			continue;
		}

		if ( ! in_array( $status, $valid, true ) ) {
			continue;
		}

		$old = isset( $existing[ $profile_id ] ) ? $existing[ $profile_id ] : '';

		if ( $old === $status ) {
			continue;
		}

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . sp_table( 'attendance_records' ) . ' (session_id, profile_id, status, marked_by)
				 VALUES (%d, %d, %s, %d)
				 ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), marked_at = %s',
				$session_id,
				$profile_id,
				$status,
				$now,
				current_time( 'mysql' )
			)
		);

		$wpdb->insert(
			sp_table( 'attendance_audit' ),
			array(
				'session_id'  => $session_id,
				'profile_id'  => $profile_id,
				'old_status'  => $old ? $old : null,
				'new_status'  => $status,
				'changed_by'  => $now,
			),
			array( '%d', '%d', '%s', '%s', '%d' )
		);

		++$changes;
	}

	return true;
}

/**
 * Mark every active student on the roster present in one action.
 *
 * @param int    $session_id Session id.
 * @param string $status     Default status.
 * @return int|WP_Error Number marked.
 */
function sp_mark_all( $session_id, $status = 'present' ) {
	$session = sp_get_session( $session_id );

	if ( ! $session ) {
		return new WP_Error( 'sp_no_session', 'That session no longer exists.' );
	}

	if ( ! sp_attendance_session_is_editable( $session ) ) {
		return new WP_Error( 'sp_session_locked', 'This register is no longer editable.' );
	}

	$status = in_array( $status, sp_attendance_statuses(), true ) ? $status : 'present';

	$enrolled = sp_get_enrollments_for_class( (int) $session->class_id, array( 'active' ), 0 );
	$existing = sp_get_session_records( $session_id );
	$marks    = array();

	foreach ( $enrolled as $row ) {
		$profile_id = (int) $row->profile_id;

		if ( isset( $existing[ $profile_id ] ) && $existing[ $profile_id ] === $status ) {
			continue;
		}

		$marks[ $profile_id ] = $status;
	}

	if ( ! $marks ) {
		return 0;
	}

	$saved = sp_save_attendance_records( $session_id, $marks );

	if ( is_wp_error( $saved ) ) {
		return $saved;
	}

	return count( $marks );
}

/**
 * Submit a register, locking it against further edits.
 *
 * @param int $session_id Session id.
 * @return true|WP_Error
 */
function sp_submit_session( $session_id ) {
	global $wpdb;

	$session = sp_get_session( $session_id );

	if ( ! $session ) {
		return new WP_Error( 'sp_no_session', 'That session no longer exists.' );
	}

	if ( 'submitted' === $session->status ) {
		return true;
	}

	$marked = sp_get_session_records( $session_id );

	if ( ! $marked ) {
		return new WP_Error( 'sp_nothing_marked', 'Mark at least one student before submitting.' );
	}

	$wpdb->update(
		sp_table( 'attendance_sessions' ),
		array(
			'status'       => 'submitted',
			'submitted_by' => get_current_user_id(),
			'submitted_at' => current_time( 'mysql' ),
		),
		array( 'id' => (int) $session_id ),
		array( '%s', '%d', '%s' ),
		array( '%d' )
	);

	return true;
}

/**
 * Reopen a submitted register so it can be corrected.
 *
 * Administrators may always reopen; teachers only inside the edit window.
 *
 * @param int    $session_id Session id.
 * @param string $reason     Optional reason, recorded for admins.
 * @return true|WP_Error
 */
function sp_reopen_session( $session_id, $reason = '' ) {
	global $wpdb;

	$session = sp_get_session( $session_id );

	if ( ! $session ) {
		return new WP_Error( 'sp_no_session', 'That session no longer exists.' );
	}

	if ( ! sp_can_reopen_session( $session ) ) {
		return new WP_Error(
			'sp_reopen_denied',
			'This register cannot be reopened. Only an administrator can reopen a submitted register.'
		);
	}

	$wpdb->update(
		sp_table( 'attendance_sessions' ),
		array(
			'status'       => 'open',
			'submitted_by' => null,
			'submitted_at' => null,
		),
		array( 'id' => (int) $session_id ),
		array( '%s', '%d', '%s' ),
		array( '%d' )
	);

	$reason = sanitize_text_field( $reason );

	$wpdb->insert(
		sp_table( 'attendance_audit' ),
		array(
			'session_id'  => $session_id,
			'profile_id'  => 0,
			'old_status'  => 'submitted',
			'new_status'  => 'open',
			'changed_by'  => get_current_user_id(),
		),
		array( '%d', '%d', '%s', '%s', '%d' )
	);

	if ( '' !== $reason ) {
		$wpdb->insert(
			sp_table( 'attendance_audit' ),
			array(
				'session_id'  => $session_id,
				'profile_id'  => 0,
				'old_status'  => null,
				'new_status'  => 'reopen:' . $reason,
				'changed_by'  => get_current_user_id(),
			),
			array( '%d', '%d', '%s', '%s', '%d' )
		);
	}

	return true;
}

/**
 * Recent sessions for a class, newest first.
 *
 * @param int $class_id Class id.
 * @param int $limit       Row cap.
 * @return object[]
 */
function sp_get_recent_sessions( $class_id, $limit = 15 ) {
	global $wpdb;

	$class_id = (int) $class_id;
	$limit       = max( 1, (int) $limit );

	if ( $class_id <= 0 ) {
		return array();
	}

	$table = sp_table( 'attendance_sessions' );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT s.*,
					( SELECT COUNT(*) FROM " . sp_table( 'attendance_records' ) . " r WHERE r.session_id = s.id ) AS marked_count
			 FROM {$table} s
			 WHERE s.class_id = %d
			 ORDER BY s.session_date DESC
			 LIMIT %d",
			$class_id,
			$limit
		)
	);
}