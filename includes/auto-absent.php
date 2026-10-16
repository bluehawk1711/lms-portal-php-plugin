<?php
/**
 * The daily auto-absent run.
 *
 * Once a day (chained onto the already-scheduled sp_daily_housekeeping
 * hook) the portal tidies up attendance history older than the edit window
 * (Settings → "Auto-absent after (days)", default 7, 0 disables):
 *
 * Phase A — for a scheduled class day in that age band it creates the
 * register if nobody opened one, then marks every active student with no
 * record as absent. The band runs up to today, so a class whose start time
 * has passed today already has an open register waiting for the teacher.
 *
 * Phase B — every open register older than the window is completed and
 * submitted, so history is never left half-marked.
 *
 * Both phases skip off days (sp_is_off_day() inside sp_create_session),
 * calendar holidays/tests and dates where every assigned teacher is
 * absent or on leave. An sp_attendance_audit row named `auto_absent`
 * records each automated mark, the same way reopen rows are named.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The edit window in days; 0 disables the run.
 *
 * @return int
 */
function sp_auto_absent_window() {
	return max( 0, (int) sp_setting( 'auto_absent_days', 7 ) );
}

/**
 * The age band the creation pass works on: from window+31 days ago back
 * to today (one month of catching up on the first run, plus today's
 * register as soon as the class start time has passed).
 *
 * @return array from, to (Y-m-d).
 */
function sp_auto_absent_band() {
	$window   = sp_auto_absent_window();
	$today_ts = strtotime( current_time( 'Y-m-d' ) );

	return array(
		'from' => date( 'Y-m-d', $today_ts - ( ( $window + 31 ) * DAY_IN_SECONDS ) ),
		'to'   => date( 'Y-m-d', $today_ts ),
	);
}

/**
 * Complete and submit one open register if it is past the edit window.
 *
 * @param object $session Session row.
 * @return array marked (students marked absent now), submitted (bool).
 */
function sp_auto_absent_finalize( $session ) {
	global $wpdb;

	$empty = array(
		'marked'    => 0,
		'submitted' => false,
	);

	if ( ! $session || 'open' !== $session->status ) {
		return $empty;
	}

	$window = sp_auto_absent_window();

	if ( $window <= 0 ) {
		return $empty;
	}

	// Never touch a register still inside the edit window.
	$cutoff = date( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) ) - ( $window * DAY_IN_SECONDS ) );

	if ( $session->session_date >= $cutoff ) {
		return $empty;
	}

	if ( sp_date_cancels_class( $session->session_date, (int) $session->class_id ) ) {
		return $empty;
	}

	$roster  = sp_get_enrollments_for_class( (int) $session->class_id, array( 'active' ), 0 );
	$records = sp_get_session_records( $session->id );
	$missing = array();

	foreach ( (array) $roster as $row ) {
		$profile_id = (int) $row->profile_id;

		if ( ! isset( $records[ $profile_id ] ) ) {
			$missing[] = $profile_id;
		}
	}

	$marked = 0;

	if ( $missing ) {
		$marks = array();	

		foreach ( $missing as $profile_id ) {
			$marks[ $profile_id ] = 'absent';
		}

		$saved = sp_save_attendance_records( $session->id, $marks );

		if ( ! is_wp_error( $saved ) ) {
			$marked = count( $missing );

			// Name the automation in the audit trail, as reopen rows are named.
			foreach ( $missing as $profile_id ) {
				$wpdb->insert(
					sp_table( 'attendance_audit' ),
					array(
						'session_id' => (int) $session->id,
						'profile_id' => $profile_id,
						'old_status' => null,
						'new_status' => 'auto_absent',
						'changed_by' => 0,
						'changed_at' => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s', '%s', '%d', '%s' )
				);
			}
		}
	}

	if ( ! $marked && ! $records ) {
		// Nothing and nobody to submit — leave the row alone.
		return $empty;
	}

	$result = sp_submit_session( $session->id );

	return array(
		'marked'    => $marked,
		'submitted' => ! is_wp_error( $result ),
	);
}

/**
 * Whether today's earliest slot for a weekday has already started.
 *
 * @param array $timings    Rows from sp_get_class_timings().
 * @param int   $day_of_week 0-6 (date('w') numbering).
 * @return bool True when the first start_time today is at or before now.
 */
function sp_auto_absent_start_passed( $timings, $day_of_week ) {
	$now = current_time( 'H:i' );

	$starts = array();

	foreach ( (array) $timings as $timing ) {
		if ( (int) $timing['day_of_week'] === (int) $day_of_week && ! empty( $timing['start_time'] ) ) {
			$starts[] = $timing['start_time'];
		}
	}

	if ( ! $starts ) {
		// No clock time on the timetable: treat the class day as started.
		return true;
	}

	sort( $starts );

	return $now >= $starts[0];
}

/**
 * Phase A: create and complete registers for one class in an age band.
 *
 * @param int    $class_id Class id.
 * @param string $from     Y-m-d (oldest).
 * @param string $to       Y-m-d (newest).
 * @return array created, marked, submitted.
 */
function sp_auto_absent_process_class( $class_id, $from, $to ) {
	$class_id = (int) $class_id;
	$from     = sp_sanitize_date( $from );
	$to       = sp_sanitize_date( $to );

	$counts = array(
		'created'   => 0,
		'marked'    => 0,
		'submitted' => 0,
	);

	if ( $class_id <= 0 || '' === $from || '' === $to || $from > $to ) {
		return $counts;
	}

	$timings = sp_get_class_timings( $class_id );

	if ( ! $timings ) {
		// No timetable, so no scheduled days to catch up on.
		return $counts;
	}

	// Never touch days outside the class's own run: before it started or
	// after it ended there is no register to open.
	$class = sp_get_class( $class_id );

	if ( $class && $class->start_date && $from < $class->start_date ) {
		$from = $class->start_date;
	}

	if ( $class && $class->end_date && $to > $class->end_date ) {
		$to = $class->end_date;
	}

	if ( '' === $from || '' === $to || $from > $to ) {
		return $counts;
	}

	$days = array();

	foreach ( $timings as $timing ) {
		$days[] = (int) $timing['day_of_week'];
	}

	$days = array_values( array_unique( $days ) );

	$start = strtotime( $from );
	$end   = strtotime( $to );

	for ( $ts = $start; $ts <= $end; $ts = strtotime( '+1 day', $ts ) ) {
		$date = date( 'Y-m-d', $ts );

		if ( ! in_array( (int) date( 'w', $ts ), $days, true ) ) {
			continue;
		}

		// Calendar holiday/test or every teacher unavailable.
		if ( sp_date_cancels_class( $date, $class_id ) ) {
			continue;
		}

		$session = sp_get_session_by_date( $class_id, $date );

		if ( ! $session ) {
			// Today: wait until the class start time has passed before the
			// register appears, so the teacher opens it to a real class.
			if ( $date === current_time( 'Y-m-d' ) && ! sp_auto_absent_start_passed( $timings, (int) date( 'w', $ts ) ) ) {
				continue;
			}

			$created = sp_create_session( $class_id, $date, 'Register auto-created by the daily attendance run.' );

			// A weekly off day (or a vanished class) refuses creation here.
			if ( is_wp_error( $created ) ) {
				continue;
			}

			++$counts['created'];

			$session = sp_get_session( $created );

			if ( ! $session ) {
				continue;
			}
		}

		$done = sp_auto_absent_finalize( $session );

		$counts['marked']    += (int) $done['marked'];
		$counts['submitted'] += $done['submitted'] ? 1 : 0;
	}

	return $counts;
}

/**
 * Phase B: complete and submit every open register past the edit window.
 *
 * @return array created, marked, submitted.
 */
function sp_auto_absent_submit_stale() {
	global $wpdb;

	$counts = array(
		'created'   => 0,
		'marked'    => 0,
		'submitted' => 0,
	);

	$window = sp_auto_absent_window();

	if ( $window <= 0 ) {
		return $counts;
	}

	$cutoff = date( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) ) - ( $window * DAY_IN_SECONDS ) );
	$table  = sp_table( 'attendance_sessions' );

	$sessions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'open' AND session_date < %s ORDER BY session_date ASC LIMIT 500",
			$cutoff
		)
	);

	foreach ( (array) $sessions as $session ) {
		$done            = sp_auto_absent_finalize( $session );
		$counts['marked']    += (int) $done['marked'];
		$counts['submitted'] += $done['submitted'] ? 1 : 0;
	}

	return $counts;
}

/**
 * The full daily run: Phase A over active classes, then Phase B.
 *
 * @return array created, marked, submitted.
 */
function sp_auto_absent_run() {
	$totals = array(
		'created'   => 0,
		'marked'    => 0,
		'submitted' => 0,
	);

	$window = sp_auto_absent_window();

	if ( $window <= 0 ) {
		return $totals;
	}

	$band = sp_auto_absent_band();

	foreach ( sp_get_classes( array( 'status' => 'active', 'per_page' => 1000 ) ) as $class ) {
		$done = sp_auto_absent_process_class( (int) $class->id, $band['from'], $band['to'] );

		$totals['created']   += $done['created'];
		$totals['marked']    += $done['marked'];
		$totals['submitted'] += $done['submitted'];
	}

	$stale                 = sp_auto_absent_submit_stale();
	$totals['marked']     += $stale['marked'];
	$totals['submitted']  += $stale['submitted'];

	if ( $totals['created'] || $totals['marked'] || $totals['submitted'] ) {
		sp_log_activity(
			'auto_absent.run',
			'attendance',
			0,
			sprintf(
				'%d register(s) created, %d student(s) marked absent, %d register(s) submitted',
				$totals['created'],
				$totals['marked'],
				$totals['submitted']
			)
		);
	}

	return $totals;
}

// Guaranteed schedule: sp_daily_housekeeping is registered in sp_activate().
add_action( 'sp_daily_housekeeping', 'sp_auto_absent_run' );
