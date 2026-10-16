<?php
/**
 * Activity log and retention.
 *
 * Records who did what to classes, enrolments, results, materials and accounts,
 * so an institute can answer "who changed this, and when" after the fact. Written
 * on the operation itself rather than the screen that triggers it, so the record
 * survives whichever route was used.
 *
 * Also owns retention. Logs are pruned after `log_retention_days` (default two
 * years). Attendance is NEVER pruned: those are institutional records, not logs.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Record one action.
 *
 * Never throws and never blocks the caller: an audit failure must not roll back
 * the operation it was describing.
 *
 * @param string $action      Machine slug, e.g. 'result.finalised'.
 * @param string $object_type e.g. 'result', 'class', 'enrollment'.
 * @param int    $object_id   Related row id.
 * @param string $summary     Human readable detail.
 * @return int|false Inserted row id, or false.
 */
function sp_log_activity( $action, $object_type = '', $object_id = 0, $summary = '' ) {
	global $wpdb;

	$user   = wp_get_current_user();
	$actor  = $user->exists() ? (int) $user->ID : 0;
	$email  = $user->exists() ? (string) $user->user_email : '';

	$inserted = $wpdb->insert(
		sp_table( 'activity_log' ),
		array(
			'actor_id'    => $actor > 0 ? $actor : null,
			'actor_email' => $email ? $email : null,
			'action'      => sanitize_key( $action ),
			'object_type' => sanitize_key( $object_type ),
			'object_id'   => (int) $object_id > 0 ? (int) $object_id : null,
			'summary'     => $summary ? wp_strip_all_tags( $summary ) : null,
		),
		array( '%d', '%s', '%s', '%s', '%d', '%s' )
	);

	return $inserted ? (int) $wpdb->insert_id : false;
}

/**
 * Recent activity, newest first.
 *
 * @param array $args {
 *     @type int    $limit   Rows to return.
 *     @type string $action  Optional action filter.
 *     @type int    $after   Only rows newer than this row id.
 * }
 * @return object[]
 */
function sp_get_activity_log( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'limit'  => 100,
			'action' => '',
			'after'  => 0,
		)
	);

	$limit = max( 1, min( 1000, (int) $args['limit'] ) );
	$after = (int) $args['after'];

	$table = sp_table( 'activity_log' );

	if ( '' !== $args['action'] ) {
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE action = %s AND id > %d ORDER BY id DESC LIMIT %d",
				sanitize_key( $args['action'] ),
				$after,
				$limit
			)
		);
	}

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE id > %d ORDER BY id DESC LIMIT %d",
			$after,
			$limit
		)
	);
}

/**
 * Delete log rows older than the retention window.
 *
 * Only the log tables are pruned. Attendance sessions, records and the attendance
 * audit trail are institutional records and are left alone however old they are.
 *
 * @return array {email_log, grade_sync_log, activity_log}
 */
function sp_purge_expired_logs() {
	global $wpdb;

	$days = (int) sp_setting( 'log_retention_days', 730 );

	if ( $days <= 0 ) {
		return array(
			'email_log'      => 0,
			'grade_sync_log' => 0,
			'activity_log'   => 0,
		);
	}

	// The created_at columns are written with current_time( 'mysql' ) (site
	// local), so the cutoff must be site local too or the purge window shifts
	// by the UTC offset.
	$cutoff  = current_time( 'mysql', time() - ( $days * DAY_IN_SECONDS ) );
	$purged  = array();

	foreach ( array( 'email_log', 'grade_sync_log', 'activity_log' ) as $name ) {
		$table = sp_table( $name );
		$purged[ $name ] = (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff )
		);
	}

	return $purged;
}

/**
 * Keep debug.log from growing without bound during a long development project.
 *
 * WP_DEBUG_LOG never rotates. If it passes the cap we keep the newest portion and
 * note the truncation, so the log stays useful instead of filling the disk.
 *
 * @return bool True when the log was trimmed.
 */
function sp_trim_debug_log() {
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
		return false;
	}

	$log = WP_CONTENT_DIR . '/debug.log';

	if ( ! file_exists( $log ) ) {
		return false;
	}

	$max_bytes = (int) apply_filters( 'sp_debug_log_max_bytes', 2 * MB_IN_BYTES );
	$size      = (int) filesize( $log );

	if ( $size <= $max_bytes ) {
		return false;
	}

	// Keep the newest half so a single run cannot be lost entirely.
	$keep     = (int) ( $max_bytes / 2 );
	$handle   = fopen( $log, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$new      = fopen( $log . '.trimmed', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$trimmed  = false;

	if ( $handle && $new ) {
		fseek( $handle, -$keep, SEEK_END );
		fwrite( $new, "### log truncated by Student Portal at " . current_time( 'mysql' ) . " ###\n" );
		stream_copy_to_stream( $handle, $new );
		$trimmed = true;
	}

	if ( $handle ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	if ( $new ) {
		fclose( $new ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	if ( $trimmed ) {
		rename( $log . '.trimmed', $log ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	return $trimmed;
}

/* -------------------------------------------------------------------------
 * Sign-in throttling
 * ---------------------------------------------------------------------- */

/**
 * Transient key for an address's failure counter.
 *
 * The key is a hash so student email addresses are not written into the
 * options table in readable form.
 *
 * @param string $email Email address.
 * @return string
 */
function sp_login_attempt_key( $email ) {
	$email = strtolower( trim( (string) $email ) );

	return 'sp_loginfail_' . md5( $email );
}

/**
 * Failed attempts allowed before a lockout.
 *
 * @return int
 */
function sp_login_attempt_limit() {
	return (int) apply_filters( 'sp_login_attempt_limit', 8 );
}

/**
 * Record a failed sign-in attempt.
 *
 * @param string $email Attempted email address.
 * @return int Attempts recorded in the current window.
 */
function sp_record_login_failure( $email ) {
	$key = sp_login_attempt_key( $email );
	$n   = (int) get_transient( $key );

	++$n;

	set_transient( $key, $n, 15 * MINUTE_IN_SECONDS );

	return $n;
}

/**
 * Clear the failure counter after a successful sign-in.
 *
 * @param string $email Email address that signed in.
 */
function sp_clear_login_failures( $email ) {
	delete_transient( sp_login_attempt_key( $email ) );
}

/**
 * Whether sign-in from this address is currently throttled.
 *
 * @param string $email Attempted email address.
 * @return int Seconds remaining, 0 when not locked.
 */
function sp_login_lockout_seconds( $email ) {
	if ( (int) get_transient( sp_login_attempt_key( $email ) ) < sp_login_attempt_limit() ) {
		return 0;
	}

	return 15 * MINUTE_IN_SECONDS;
}

/**
 * Daily housekeeping: prune expired logs and cap debug.log size.
 */
function sp_daily_housekeeping() {
	sp_purge_expired_logs();
	sp_trim_debug_log();
}
add_action( 'sp_daily_housekeeping', 'sp_daily_housekeeping' );
