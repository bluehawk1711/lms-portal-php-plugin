<?php
/**
 * Results: theory + practical entry, calculation, finalisation and listing.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save a result for an enrollment.
 *
 * @param int   $enrollment_id Enrollment id.
 * @param array $marks         theory_max, theory_obtained, practical_max, practical_obtained.
 * @param array $options       finalised (bool).
 * @return object|WP_Error The stored result row.
 */
function sp_save_result( $enrollment_id, $marks, $options = array() ) {
	global $wpdb;

	$options = wp_parse_args( $options, array( 'finalised' => false ) );

	$enrollment = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'enrollments' ) . ' WHERE id = %d', (int) $enrollment_id )
	);

	if ( ! $enrollment ) {
		return new WP_Error( 'sp_no_enrollment', 'That enrollment no longer exists.' );
	}

	$calculated = sp_calculate_result( $marks );

	if ( is_wp_error( $calculated ) ) {
		return $calculated;
	}

	$table   = sp_table( 'results' );
	$current = sp_get_result_for_enrollment( $enrollment_id );

	// Finalisation is one-way from this screen; an admin can unfinalise.
	$finalised = $options['finalised'] ? 1 : ( $current ? (int) $current->finalised : 0 );

	$data = array(
		'theory_max'         => isset( $marks['theory_max'] ) ? (float) $marks['theory_max'] : 0,
		'theory_obtained'    => isset( $marks['theory_obtained'] ) ? (float) $marks['theory_obtained'] : 0,
		'practical_max'      => isset( $marks['practical_max'] ) ? (float) $marks['practical_max'] : 0,
		'practical_obtained' => isset( $marks['practical_obtained'] ) ? (float) $marks['practical_obtained'] : 0,
		'percentage'         => $calculated['percentage'],
		'grade'              => $calculated['grade'] ? $calculated['grade'] : null,
		'result'             => $calculated['result'],
		'finalised'          => $finalised,
		'updated_by'         => get_current_user_id(),
	);

	$formats = array( '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%d', '%d' );

	if ( $current ) {
		$wpdb->update( $table, $data, array( 'enrollment_id' => (int) $enrollment_id ), $formats, array( '%d' ) );
	} else {
		$data['enrollment_id'] = (int) $enrollment_id;
		$wpdb->insert( $table, $data, array_merge( $formats, array( '%d' ) ) );
	}

	$result = sp_get_result_for_enrollment( $enrollment_id );

	if ( $result && 1 === (int) $result->finalised && ( ! $current || 0 === (int) $current->finalised ) ) {
		sp_log_activity(
			'result.finalised',
			'result',
			(int) $result->id,
			sprintf(
				'Enrollment #%d finalised: %s%% grade %s (%s)',
				(int) $enrollment_id,
				$result->percentage,
				$result->grade ? $result->grade : '-',
				$result->result
			)
		);

		sp_notify_result_finalised( $enrollment, $result );

		// Optionally hand straight to the Certificate Manager. No-op unless the
		// auto_certificate setting is on and everything lines up.
		sp_auto_generate_certificate( $enrollment, $result );
	}

	return $result;
}

/**
 * Email the student when a result is finalised for the first time.
 *
 * @param object $enrollment Enrollment row.
 * @param object $result     sp_results row.
 * @return bool
 */
function sp_notify_result_finalised( $enrollment, $result ) {
	$profile = sp_get_profile_by_id( (int) $enrollment->profile_id );

	if ( ! $profile ) {
		return false;
	}

	$row = sp_get_enrollment_with_class( (int) $enrollment->id );

	if ( ! $row ) {
		return false;
	}

	return sp_email_result_notice( $profile, $row, $result );
}

/**
 * Toggle the finalised flag on a result.
 *
 * @param int  $result_id sp_results.id.
 * @param bool $finalised Desired state.
 * @return object|WP_Error
 */
function sp_set_result_finalised( $result_id, $finalised ) {
	global $wpdb;

	$table = sp_table( 'results' );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $result_id ) );

	if ( ! $row ) {
		return new WP_Error( 'sp_no_result', 'That result no longer exists.' );
	}

	$wpdb->update(
		$table,
		array(
			'finalised'  => $finalised ? 1 : 0,
			'updated_by' => get_current_user_id(),
		),
		array( 'id' => (int) $result_id ),
		array( '%d', '%d' ),
		array( '%d' )
	);

	if ( $finalised ) {
		$enrollment = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'enrollments' ) . ' WHERE id = %d', (int) $row->enrollment_id )
		);

		if ( $enrollment ) {
			sp_notify_result_finalised( $enrollment, sp_get_result_for_enrollment( (int) $row->enrollment_id ) );
		}
	}

	return sp_get_result_for_enrollment( (int) $row->enrollment_id );
}

/**
 * Fetch one enrollment joined to its class and student.
 *
 * @param int $enrollment_id Enrollment id.
 * @return object|null
 */
function sp_get_enrollment_with_class( $enrollment_id ) {
	global $wpdb;

	$enrollment_id = (int) $enrollment_id;

	if ( $enrollment_id <= 0 ) {
		return null;
	}

	$enroll    = sp_table( 'enrollments' );
	$classes = sp_table( 'classes' );
	$profiles  = sp_table( 'student_profiles' );

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT e.*, o.title AS class_title, o.code AS class_code,
					p.full_name, p.email, p.phone, p.user_id
			FROM {$enroll} e
			INNER JOIN {$classes} o ON o.id = e.class_id
			INNER JOIN {$profiles} p ON p.id = e.profile_id
			WHERE e.id = %d",
			$enrollment_id
		)
	);
}

/**
 * Results for one student across all their classes.
 *
 * @param int $profile_id Student profile id.
 * @return object[]
 */
function sp_get_student_results( $profile_id ) {
	global $wpdb;

	$profile_id = (int) $profile_id;

	if ( $profile_id <= 0 ) {
		return array();
	}

	$enroll   = sp_table( 'enrollments' );
	$class = sp_table( 'classes' );
	$results  = sp_table( 'results' );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT e.id AS enrollment_id, o.title AS class_title, o.code AS class_code,
					r.theory_max, r.theory_obtained, r.practical_max, r.practical_obtained,
					r.percentage, r.grade, r.result, r.finalised, r.updated_at
			FROM {$enroll} e
			INNER JOIN {$class} o ON o.id = e.class_id
			LEFT JOIN {$results} r ON r.enrollment_id = e.id
			WHERE e.profile_id = %d AND e.status IN ( 'active', 'completed' )
			ORDER BY o.start_date DESC",
			$profile_id
		)
	);
}

/**
 * Enrollments in a class with whatever result exists.
 *
 * @param int $class_id Class id.
 * @return object[]
 */
function sp_get_class_results( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return array();
	}

	$enroll   = sp_table( 'enrollments' );
	$profiles = sp_table( 'student_profiles' );
	$results  = sp_table( 'results' );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT e.id AS enrollment_id, e.enroll_id, p.id AS profile_id, p.full_name,
					r.id AS result_id, r.theory_max, r.theory_obtained,
					r.practical_max, r.practical_obtained, r.percentage,
					r.grade, r.result, r.finalised
			FROM {$enroll} e
			INNER JOIN {$profiles} p ON p.id = e.profile_id
			LEFT JOIN {$results} r ON r.enrollment_id = e.id
			WHERE e.class_id = %d AND e.status IN ( 'active', 'completed' )
			ORDER BY p.full_name ASC",
			$class_id
		)
	);
}

/**
 * How many results in a class are saved but not finalised.
 *
 * @param int $class_id Class id.
 * @return int
 */
function sp_count_pending_results( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return 0;
	}

	$enroll  = sp_table( 'enrollments' );
	$results = sp_table( 'results' );

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			 FROM {$enroll} e
			 INNER JOIN {$results} r ON r.enrollment_id = e.id
			 WHERE e.class_id = %d AND e.status IN ( 'active', 'completed' ) AND r.finalised = 0",
			$class_id
		)
	);
}

/**
 * How many results exist at all in a class (entered, published or not).
 *
 * Paired with sp_count_pending_results() so screens can tell "nothing entered
 * yet" apart from "everything published".
 *
 * @param int $class_id Class id.
 * @return int
 */
function sp_count_entered_results( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return 0;
	}

	$enroll  = sp_table( 'enrollments' );
	$results = sp_table( 'results' );

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			 FROM {$enroll} e
			 INNER JOIN {$results} r ON r.enrollment_id = e.id
			 WHERE e.class_id = %d AND e.status IN ( 'active', 'completed' )",
			$class_id
		)
	);
}