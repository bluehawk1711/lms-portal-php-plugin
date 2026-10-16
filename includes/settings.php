<?php
/**
 * Portal settings: single option array with defaults and sanitisation.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registered option name.
 */
const SP_SETTINGS_OPTION = 'sp_settings';

/**
 * Default settings, merged over whatever is stored.
 *
 * @return array
 */
function sp_settings() {
	static $settings = null;

	if ( null !== $settings ) {
		return $settings;
	}

	$stored = get_option( SP_SETTINGS_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	$settings = array_merge(
		array(
			'pass_mark'            => 35,
			'grade_scale'          => sp_default_grade_scale(),
			'attendance_edit_days' => 1,
			// Daily auto-absent run: registers older than this many days are
			// completed and submitted automatically. 0 disables the run.
			'auto_absent_days'     => 7,
			'max_upload_mb'        => 20,
		// Shown in the student portal's sidebar contact card. Email falls back
		// to the site admin address when empty.
		'institute_phone'      => '',
		'institute_email'      => '',
			'notify_on_result'     => 1,
			'notify_on_material'   => 1,
			'notify_on_assignment' => 0,
			'notify_on_enrollment' => 0,
			'auto_certificate'     => 0,
			'auto_certificate_min_grade' => '',
			// Day numbers off by default: 0 = Sunday. Classes and registers cannot
			// be created on these days.
			'off_days'             => array( 0 ),
			// Days to keep the log tables. 0 disables pruning. Attendance is never
			// pruned regardless of this value.
			'log_retention_days'   => 730,
		),
		$stored
	);

	if ( ! is_array( $settings['grade_scale'] ) || ! $settings['grade_scale'] ) {
		$settings['grade_scale'] = sp_default_grade_scale();
	}

	return $settings;
}

/**
 * Read one setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Value when unset.
 * @return mixed
 */
function sp_setting( $key, $default = null ) {
	$settings = sp_settings();

	return array_key_exists( $key, $settings ) ? $settings[ $key] : $default;
}

/**
 * Register the option with WordPress.
 */
function sp_register_settings() {
	register_setting(
		'sp_settings_group',
		SP_SETTINGS_OPTION,
		'sp_sanitize_settings'
	);
}
add_action( 'admin_init', 'sp_register_settings' );

/**
 * Sanitise the settings payload coming from the settings form.
 *
 * @param array $input Raw input.
 * @return array
 */
function sp_sanitize_settings( $input ) {
	$current = sp_settings();
	$clean   = array();

	$clean['pass_mark'] = isset( $input['pass_mark'] )
		? min( 100, max( 0, (float) $input['pass_mark'] ) )
		: $current['pass_mark'];

	$clean['attendance_edit_days'] = isset( $input['attendance_edit_days'] )
		? max( 0, (int) $input['attendance_edit_days'] )
		: $current['attendance_edit_days'];

	// 0 disables the daily auto-absent run; otherwise cap at three months.
	$clean['auto_absent_days'] = isset( $input['auto_absent_days'] )
		? min( 90, max( 0, (int) $input['auto_absent_days'] ) )
		: $current['auto_absent_days'];

	$clean['max_upload_mb'] = isset( $input['max_upload_mb'] )
		? min( 200, max( 1, (int) $input['max_upload_mb'] ) )
		: $current['max_upload_mb'];

	$clean['institute_phone'] = isset( $input['institute_phone'] )
		? sanitize_text_field( wp_unslash( $input['institute_phone'] ) )
		: $current['institute_phone'];

	$clean['institute_email'] = isset( $input['institute_email'] )
		? sanitize_email( wp_unslash( $input['institute_email'] ) )
		: $current['institute_email'];

	// 0 keeps logs forever; otherwise cap at ten years.
	$clean['log_retention_days'] = isset( $input['log_retention_days'] )
		? min( 3650, max( 0, (int) $input['log_retention_days'] ) )
		: $current['log_retention_days'];

	foreach ( array( 'notify_on_result', 'notify_on_material', 'notify_on_assignment', 'notify_on_enrollment', 'auto_certificate' ) as $flag ) {
		$clean[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
	}

	/*
	 * Optional minimum grade for automatic certificate generation. Empty means
	 * "any passing result". When set it must be a grade the Certificate Manager
	 * actually accepts, otherwise a certificate could never be produced.
	 */
	$min_grade = isset( $input['auto_certificate_min_grade'] )
		? trim( (string) $input['auto_certificate_min_grade'] )
		: '';

	if ( '' !== $min_grade ) {
		$min_grade = sanitize_text_field( $min_grade );
		$min_grade = sp_is_supported_grade( $min_grade ) ? $min_grade : '';
	}

	$clean['auto_certificate_min_grade'] = $min_grade;

	/*
	 * Off days: a list of weekday numbers (0 = Sunday .. 6 = Saturday) on which
	 * no class or attendance register may be created. Stored as a clean sorted
	 * list of ints so comparisons never have to guess at the format.
	 */
	$off_days = isset( $input['off_days'] ) ? (array) $input['off_days'] : array();
	$clean_off = array();

	foreach ( $off_days as $day ) {
		$day = (int) $day;

		if ( $day >= 0 && $day <= 6 && ! in_array( $day, $clean_off, true ) ) {
			$clean_off[] = $day;
		}
	}

	sort( $clean_off );

	$clean['off_days'] = $clean_off;

	// Rebuild the grade scale from repeated min[]/grade[] form arrays.
	$mins   = isset( $input['grade_min'] ) ? (array) $input['grade_min'] : array();
	$grades = isset( $input['grade_grade'] ) ? (array) $input['grade_grade'] : array();

	$scale = array();
	$seen  = array();

	foreach ( $mins as $index => $min ) {
		$grade = isset( $grades[ $index ] ) ? trim( (string) $grades[ $index ] ) : '';

		if ( '' === $grade ) {
			continue;
		}

		$grade = sanitize_text_field( $grade );
		$min   = (float) $min;

		if ( $min < 0 || $min > 100 ) {
			continue;
		}

		// The Certificate Manager only renders grades from its own list; drop
		// anything else rather than storing a value that can never be synced.
		if ( ! sp_is_supported_grade( $grade ) ) {
			continue;
		}

		if ( isset( $seen[ $grade ] ) ) {
			continue;
		}

		$seen[ $grade ] = true;

		$scale[] = array(
			'min'   => $min,
			'grade' => $grade,
		);
	}

	/*
	 * The form posts back to admin.php rather than wp-admin/options.php, so the
	 * Settings API never runs. sp_admin_action_save_settings() therefore calls this
	 * function and then writes the result with update_option(), which fires the
	 * registered sanitiser a second time. That second pass must be a no-op, so
	 * when no grade_min/grade_grade rows are present we keep the incoming
	 * grade_scale rather than falling back to the stored one -- otherwise the
	 * re-sanitise would silently revert a freshly saved scale.
	 */
	$clean['grade_scale'] = $scale
		? $scale
		: ( isset( $input['grade_scale'] ) && is_array( $input['grade_scale'] ) && $input['grade_scale']
			? $input['grade_scale']
			: $current['grade_scale'] );

	return $clean;
}

/**
 * Maximum upload size in bytes.
 *
 * @return int
 */
function sp_max_upload_bytes() {
	return (int) round( (float) sp_setting( 'max_upload_mb', 20 ) * MB_IN_BYTES );
}

/**
 * Whether attendance submissions may still be edited.
 *
 * A register that has not been submitted is a draft and is always editable.
 * Once submitted it is locked, and is only editable again inside the
 * `attendance_edit_days` window after submission (default 1, so a register
 * closes the day after it is submitted). A window of 0 therefore means
 * "locked immediately", which is what an administrator setting 0 expects.
 *
 * Administrators deliberately get NO automatic bypass here. If they could edit a
 * locked register inline, the lock would be a lie and the change would leave no
 * audit trail. To change a locked register an administrator uses the explicit
 * reopen action, which is recorded in sp_attendance_audit.
 *
 * @param object $session sp_attendance_sessions row.
 * @return bool
 */
function sp_attendance_session_is_editable( $session ) {
	if ( ! $session ) {
		return false;
	}

	if ( 'submitted' !== $session->status ) {
		return true;
	}

	if ( empty( $session->submitted_at ) ) {
		// Submitted but unstamped: treat as locked rather than silently editable.
		return false;
	}

	$days = (int) sp_setting( 'attendance_edit_days', 1 );

	if ( $days <= 0 ) {
		return false;
	}

	$end = strtotime( $session->submitted_at . ' +' . $days . ' days' );

	return time() <= (int) $end;
}

/**
 * Whether an administrator may reopen this register.
 *
 * Reopening is the audited route back to an editable register, so it is allowed
 * regardless of the edit window.
 *
 * @param object $session sp_attendance_sessions row.
 * @return bool
 */
function sp_can_reopen_session( $session ) {
	if ( ! $session ) {
		return false;
	}

	if ( ! current_user_can( 'manage_certificates' ) ) {
		return false;
	}

	return 'submitted' === $session->status;
}

/* -------------------------------------------------------------------------
 * Off days
 * ---------------------------------------------------------------------- */

/**
 * Weekday names keyed by PHP's date('w') numbering.
 *
 * @return string[]
 */
function sp_weekday_names() {
	return array(
		0 => 'Sunday',
		1 => 'Monday',
		2 => 'Tuesday',
		3 => 'Wednesday',
		4 => 'Thursday',
		5 => 'Friday',
		6 => 'Saturday',
	);
}

/**
 * Normalised list of configured off days.
 *
 * @return int[]
 */
function sp_off_days() {
	$days = sp_setting( 'off_days', array( 0 ) );

	if ( ! is_array( $days ) ) {
		return array();
	}

	$clean = array();

	foreach ( $days as $day ) {
		$day = (int) $day;

		if ( $day >= 0 && $day <= 6 && ! in_array( $day, $clean, true ) ) {
			$clean[] = $day;
		}
	}

	return $clean;
}

/**
 * Whether a date falls on a configured off day.
 *
 * @param string $date Y-m-d.
 * @return bool
 */
function sp_is_off_day( $date ) {
	$date = sp_sanitize_date( $date );

	if ( '' === $date ) {
		return false;
	}

	$timestamp = strtotime( $date );

	if ( false === $timestamp ) {
		return false;
	}

	// date() matches the timezone strtotime() parsed with, so the weekday is
	// computed from the date the user actually typed.
	$weekday = (int) date( 'w', $timestamp );

	return in_array( $weekday, sp_off_days(), true );
}

/**
 * Human list of off days, e.g. "Sunday" or "Sunday, Saturday".
 *
 * @return string
 */
function sp_off_days_label() {
	$names = sp_weekday_names();
	$days  = sp_off_days();

	if ( ! $days ) {
		return 'No days are marked off.';
	}

	$labels = array();

	foreach ( $days as $day ) {
		$labels[] = $names[ $day ];
	}

	return implode( ', ', $labels );
}