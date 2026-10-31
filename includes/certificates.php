<?php
/**
 * Certificate generation.
 *
 * The portal owns certificates end to end. An administrator defines the field
 * list once (Settings -> Certificates): every field has a label, a type and,
 * where the portal already knows the value, an auto-fill source. When an
 * administrator or the class's teacher opens the form on the Results screen the
 * fields arrive pre-filled and anything the portal cannot know is left for them
 * to type.
 *
 * A certificate can only be issued once the student's attendance for that class
 * is at least `sp_certificate_min_attendance()` percent AND the result has been
 * published. Both criteria are checked at issue time, so a later attendance
 * change or an unpublished result re-blocks the button.
 *
 * The portal does not draw certificates itself. Issuing requires the
 * Certificate Manager plugin: its generator produces the actual certificate
 * (design, QR code, PDF, verification and download) and every "open and print"
 * link in the portal resolves to its public verify page. The portal record
 * stays the bookkeeping source of truth -- eligibility, serial and the stored
 * field values -- but if Certificate Manager is inactive or generation fails,
 * no certificate is issued.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The auto-fill sources a field definition may point at.
 *
 * Each source resolves against one enrolment at issue time. `manual` means the
 * portal knows nothing and the person issuing types the value.
 *
 * @return array key => human label.
 */
function sp_certificate_field_sources() {
	return array(
		''            => 'No auto-fill (type it in)',
		'student_name' => 'Student name',
		'enroll_id'    => 'Enrolment ID (this course)',
		'class_title'  => 'Class title',
		'course_name'  => 'Course name',
		'grade'        => 'Grade',
		'percentage'   => 'Result percentage',
		'attendance'   => 'Attendance percentage',
		'theory'       => 'Theory marks',
		'practical'    => 'Practical marks',
		'admission'    => 'Admission date',
		'completion'   => 'Completion date',
		'issued'       => 'Issue date',
		'dob'          => 'Date of birth',
		'email'        => 'Email address',
		'phone'        => 'Phone',
	);
}

/**
 * The field types a definition may use.
 *
 * @return array key => human label.
 */
function sp_certificate_field_types() {
	return array(
		'text'     => 'Text',
		'textarea' => 'Paragraph',
		'date'     => 'Date',
		'number'   => 'Number',
		'select'   => 'Select (list)',
	);
}

/**
 * The grade choices a select field offers.
 *
 * Prefers the Certificate Manager's own list — the only grades it renders —
 * then the configured grade scale, then the built-in fallback list.
 *
 * @return string[]
 */
function sp_certificate_grade_options() {
	$sp_options = array();

	if ( function_exists( 'cert_mgr_grade_options' ) ) {
		foreach ( (array) cert_mgr_grade_options() as $sp_option ) {
			$sp_option = trim( (string) $sp_option );

			if ( '' !== $sp_option ) {
				$sp_options[] = $sp_option;
			}
		}
	}

	if ( ! $sp_options ) {
		foreach ( sp_normalized_grade_scale() as $sp_row ) {
			$sp_options[] = (string) $sp_row['grade'];
		}
	}

	if ( ! $sp_options ) {
		$sp_options = array( 'A+', 'A', 'B', 'C', 'D' );
	}

	return array_values( array_unique( $sp_options ) );
}

/**
 * The choices for one field when it should render as a select.
 *
 * Only the Grade field has a fixed list the portal can offer; every other
 * field has no defined choices and renders as its configured type instead.
 *
 * @param array $field Field definition.
 * @return string[] Empty when the field is not a select.
 */
function sp_certificate_field_options( $field ) {
	$sp_source = is_array( $field ) && isset( $field['source'] ) ? (string) $field['source'] : '';

	if ( 'grade' === $sp_source ) {
		return sp_certificate_grade_options();
	}

	return array();
}

/**
 * The configured certificate field list, with defaults on first run.
 *
 * Every entry: key (stable, generated), label, type, source, required.
 *
 * A field auto-filled from the grade always renders as a select of the
 * supported grades, so a stored `text`/`number` definition is normalised here
 * (the settings screen then shows and saves it as a select).
 *
 * @return array[]
 */
function sp_certificate_fields() {
	$stored = get_option( 'sp_certificate_fields', null );

	$fields = is_array( $stored ) ? $stored : sp_certificate_default_fields();

	foreach ( $fields as $sp_index => $sp_field ) {
		if ( is_array( $sp_field ) && isset( $sp_field['source'] ) && 'grade' === $sp_field['source'] ) {
			$fields[ $sp_index ]['type'] = 'select';
		}
	}

	return $fields;
}

/**
 * The default field list: everything the portal can fill itself, in the order a
 * certificate normally shows it.
 *
 * @return array[]
 */
function sp_certificate_default_fields() {
	$fields = array();

	$defaults = array(
		array( 'label' => 'Student name', 'type' => 'text', 'source' => 'student_name', 'required' => true ),
		array( 'label' => 'Enrolment ID', 'type' => 'text', 'source' => 'enroll_id', 'required' => true ),
		array( 'label' => 'Class', 'type' => 'text', 'source' => 'class_title', 'required' => true ),
		array( 'label' => 'Course', 'type' => 'text', 'source' => 'course_name', 'required' => false ),
		array( 'label' => 'Grade', 'type' => 'select', 'source' => 'grade', 'required' => true ),
		array( 'label' => 'Percentage', 'type' => 'text', 'source' => 'percentage', 'required' => false ),
		array( 'label' => 'Attendance', 'type' => 'text', 'source' => 'attendance', 'required' => false ),
		array( 'label' => 'Theory marks', 'type' => 'text', 'source' => 'theory', 'required' => false ),
		array( 'label' => 'Practical marks', 'type' => 'text', 'source' => 'practical', 'required' => false ),
		array( 'label' => 'Admission date', 'type' => 'date', 'source' => 'admission', 'required' => false ),
		array( 'label' => 'Completion date', 'type' => 'date', 'source' => 'completion', 'required' => false ),
		array( 'label' => 'Issue date', 'type' => 'date', 'source' => 'issued', 'required' => true ),
	);

	foreach ( $defaults as $index => $row ) {
		$fields[] = array(
			'key'      => 'field_' . ( $index + 1 ),
			'label'    => $row['label'],
			'type'     => $row['type'],
			'source'   => $row['source'],
			'required' => $row['required'],
		);
	}

	return $fields;
}

/**
 * Resolve every auto-fill source for one enrolment.
 *
 * @param object $enrollment sp_enrollments row.
 * @param object $result     sp_results row, or null.
 * @return array source key => resolved value (string).
 */
function sp_certificate_auto_values( $enrollment, $result = null ) {
	$values = array(
		'student_name' => '',
		'enroll_id'    => '',
		'class_title'  => '',
		'course_name'  => '',
		'grade'        => '',
		'percentage'   => '',
		'attendance'   => '',
		'theory'       => '',
		'practical'    => '',
		'admission'    => '',
		'completion'   => '',
		'issued'       => current_time( 'Y-m-d' ),
		'dob'          => '',
		'email'        => '',
		'phone'        => '',
	);

	if ( ! $enrollment ) {
		return $values;
	}

	$profile = sp_get_profile_by_id( (int) $enrollment->profile_id );
	$class   = sp_get_class( (int) $enrollment->class_id );
	$course  = ( $class && ! empty( $class->course_id ) ) ? sp_get_course( (int) $class->course_id ) : null;

	if ( $profile ) {
		$values['student_name'] = (string) $profile->full_name;
		$values['email']        = (string) $profile->email;
		$values['phone']        = $profile->phone ? (string) $profile->phone : '';
		$values['dob']          = ( $profile->dob && '0000-00-00' !== $profile->dob ) ? (string) $profile->dob : '';
	}

	$values['enroll_id']   = ! empty( $enrollment->enroll_id ) ? (string) $enrollment->enroll_id : '';
	$values['admission']   = ! empty( $enrollment->admission_date ) ? (string) $enrollment->admission_date : '';
	$values['class_title'] = $class ? (string) $class->title : '';
	$values['course_name'] = $course ? (string) $course->name : '';
	$values['completion']  = ( $class && ! empty( $class->end_date ) ) ? (string) $class->end_date : '';

	if ( $result ) {
		$values['grade']      = $result->grade ? (string) $result->grade : '';
		$values['percentage'] = null !== $result->percentage ? sp_format_number( $result->percentage ) . '%' : '';
		$values['theory']     = sp_format_number( $result->theory_obtained ) . ' / ' . sp_format_number( $result->theory_max );
		$values['practical']  = sp_format_number( $result->practical_obtained ) . ' / ' . sp_format_number( $result->practical_max );
	}

	$stats = sp_get_attendance_stats( (int) $enrollment->profile_id, (int) $enrollment->class_id );

	if ( ! empty( $stats['total'] ) ) {
		$values['attendance'] = sp_format_number( $stats['percentage'] ) . '%';
	}

	return $values;
}

/**
 * Whether automatic generation is switched on and usable right now.
 *
 * @return bool
 */
function sp_auto_certificate_enabled() {
	if ( ! sp_setting( 'auto_certificate', 0 ) ) {
		return false;
	}

	return true;
}

/**
 * Issue a certificate automatically when a result is finalised.
 *
 * Only acts when the `auto_certificate` setting is on and the enrolment is
 * eligible; the same field list and auto-fill sources are used, so an automatic
 * certificate is identical to one issued by hand with no edits.
 *
 * @param object $enrollment sp_enrollments row.
 * @param object $result     sp_results row.
 * @return bool True when a certificate was issued.
 */
function sp_auto_generate_certificate( $enrollment, $result ) {
	if ( ! sp_auto_certificate_enabled() ) {
		return false;
	}

	if ( ! $enrollment || ! $result ) {
		return false;
	}

	$state = sp_certificate_state_for_enrollment( (int) $enrollment->id );

	if ( ! $state['can_generate'] ) {
		return false;
	}

	$certificate = sp_issue_certificate( (int) $enrollment->id );

	return ! is_wp_error( $certificate );
}

/**
 * The minimum class attendance percentage a student needs to be certificated.
 *
 * @return float
 */
function sp_certificate_min_attendance() {
	return (float) sp_setting( 'certificate_min_attendance', 75 );
}

/**
 * Whether a certificate can be issued for an enrolment right now, and why not.
 *
 * Criteria: the result must be published (finalised), the grade must be a pass,
 * class attendance must reach `sp_certificate_min_attendance()`, and the
 * enrolment must carry an ID.
 *
 * @param int $enrollment_id Enrollment id.
 * @return array {
 *     @type bool   $can_generate
 *     @type string $reason        Why not, for display.
 *     @type bool   $generated     A portal certificate already exists.
 *     @type int    $certificate_id
 *     @type string $serial
 *     @type float  $attendance
 * }
 */
function sp_certificate_state_for_enrollment( $enrollment_id ) {
	global $wpdb;

	$state = array(
		'can_generate'   => false,
		'reason'         => '',
		'generated'      => false,
		'certificate_id' => 0,
		'serial'         => '',
		'issued_date'    => '',
		'attendance'     => 0.0,
	);

	$enrollment = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'enrollments' ) . ' WHERE id = %d', (int) $enrollment_id )
	);

	if ( ! $enrollment ) {
		$state['reason'] = 'That enrollment no longer exists.';
		return $state;
	}

	if ( ! sp_get_profile_by_id( $enrollment->profile_id ) ) {
		$state['reason'] = 'This enrollment has no student profile.';
		return $state;
	}

	$existing = sp_get_certificate_for_enrollment( (int) $enrollment->id );

	if ( $existing ) {
		$state['generated']      = true;
		$state['certificate_id'] = (int) $existing->id;
		$state['serial']         = (string) $existing->serial;
		$state['issued_date']    = (string) $existing->issued_date;
	}

	$result = sp_get_result_for_enrollment( (int) $enrollment->id );

	if ( ! $result ) {
		$state['reason'] = 'No grade has been entered yet.';
		return $state;
	}

	if ( ! (int) $result->finalised ) {
		$state['reason'] = 'Publish the result first — certificates are only issued for published results.';
		return $state;
	}

	if ( 'pass' !== (string) $result->result ) {
		$state['reason'] = 'Only a passing result can be certificated.';
		return $state;
	}

	$stats   = sp_get_attendance_stats( (int) $enrollment->profile_id, (int) $enrollment->class_id );
	$scored  = (int) $stats['total'] - (int) $stats['excused'];
	$needed  = sp_certificate_min_attendance();

	$state['attendance'] = (float) $stats['percentage'];

	if ( $scored <= 0 ) {
		$state['reason'] = 'No attendance has been recorded for this class yet.';
		return $state;
	}

	if ( $state['attendance'] < $needed ) {
		$state['reason'] = sprintf(
			/* translators: 1: attendance percentage achieved, 2: attendance percentage required. */
			'Attendance is %1$s%% — a minimum of %2$s%% is required.',
			sp_format_number( $state['attendance'] ),
			sp_format_number( $needed )
		);
		return $state;
	}

	if ( '' === trim( (string) $enrollment->enroll_id ) ) {
		$state['reason'] = 'This enrolment has no ID — set one on the Enrollments screen.';
		return $state;
	}

	$state['can_generate'] = true;

	return $state;
}

/**
 * The portal certificate row for one enrolment.
 *
 * @param int $enrollment_id Enrollment id.
 * @return object|null
 */
function sp_get_certificate_for_enrollment( $enrollment_id ) {
	global $wpdb;

	if ( (int) $enrollment_id <= 0 ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'certificates' ) . ' WHERE enrollment_id = %d', (int) $enrollment_id )
	);
}

/**
 * Every portal certificate belonging to one student profile.
 *
 * @param int $profile_id Student profile id.
 * @return object[]
 */
function sp_get_certificates_for_profile( $profile_id ) {
	global $wpdb;

	if ( (int) $profile_id <= 0 ) {
		return array();
	}

	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . sp_table( 'certificates' ) . ' WHERE profile_id = %d ORDER BY issued_date DESC, id DESC',
			(int) $profile_id
		)
	);
}

/**
 * The next human-facing serial, e.g. SK-2026-0001.
 *
 * @return string
 */
function sp_certificate_next_serial() {
	global $wpdb;

	$table = sp_table( 'certificates' );
	$year  = current_time( 'Y' );
	$like  = 'SK-' . $year . '-%';

	$highest = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT serial FROM {$table} WHERE serial LIKE %s ORDER BY id DESC LIMIT 1",
			$like
		)
	);

	$sequence = 1;

	if ( $highest ) {
		$parts = explode( '-', (string) $highest );
		$last  = end( $parts );

		if ( is_numeric( $last ) ) {
			$sequence = (int) $last + 1;
		}
	}

	return sp_certificate_next_serial_for( $year, $sequence );
}

/**
 * The first free serial at or above one sequence number in a given year.
 *
 * @param string $year     Four digit year.
 * @param int    $sequence First sequence to try.
 * @return string
 */
function sp_certificate_next_serial_for( $year, $sequence ) {
	global $wpdb;

	$table = sp_table( 'certificates' );

	for ( $i = 0; $i < 50; $i++ ) {
		$serial = sprintf( 'SK-%s-%04d', $year, $sequence );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE serial = %s", $serial ) );

		if ( ! $exists ) {
			return $serial;
		}

		$sequence++;
	}

	return sprintf( 'SK-%s-%04d', $year, $sequence );
}

/**
 * Issue a certificate for one enrolment.
 *
 * @param int   $enrollment_id Enrollment id.
 * @param array $submitted     Submitted field values, keyed by field key.
 * @return object|WP_Error The saved certificate row.
 */
function sp_issue_certificate( $enrollment_id, $submitted = array() ) {
	global $wpdb;

	/*
	 * Certificate Manager owns certificate generation, so without it there is
	 * nothing to issue. Refuse up front rather than saving a portal row that
	 * could never be opened, printed or verified.
	 */
	if ( ! sp_certificate_manager_active() || ! function_exists( 'cert_mgr_generate_certificate' ) ) {
		return new WP_Error(
			'sp_no_cert_manager',
			'Certificate Manager is required to issue certificates. Activate it and try again.'
		);
	}

	$state    = sp_certificate_state_for_enrollment( (int) $enrollment_id );
	$existing = sp_get_certificate_for_enrollment( (int) $enrollment_id );

	/*
	 * A fresh issue must clear every gate: published pass result, the
	 * attendance minimum, a usable enrolment ID. A reissue of a certificate
	 * that already exists does not — the document is already out, so a gate
	 * that flips later (a result unpublished for a correction, attendance
	 * edited) must not strand the record while the form keeps offering
	 * "Reissue". The enrolment and profile themselves still have to exist:
	 * the push to Certificate Manager needs both.
	 */
	if ( ! $state['can_generate'] && ! $existing ) {
		return new WP_Error( 'sp_cannot_generate', $state['reason'] );
	}

	$enrollment = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'enrollments' ) . ' WHERE id = %d', (int) $enrollment_id )
	);

	if ( ! $enrollment ) {
		return new WP_Error( 'sp_no_enrollment', 'That enrollment no longer exists.' );
	}

	$result  = sp_get_result_for_enrollment( (int) $enrollment_id );
	$auto    = sp_certificate_auto_values( $enrollment, $result );
	$fields  = sp_certificate_fields();
	$values  = array();
	$missing = array();
	$grade_from_form = '';

	foreach ( $fields as $field ) {
		$key  = isset( $field['key'] ) ? (string) $field['key'] : '';
		$want = isset( $submitted[ $key ] ) ? trim( (string) $submitted[ $key ] ) : '';

		if ( '' === $want && ! empty( $field['source'] ) && isset( $auto[ $field['source'] ] ) ) {
			$want = trim( (string) $auto[ $field['source'] ] );
		}

		if ( ! empty( $field['required'] ) && '' === $want ) {
			$missing[] = $field['label'];
		}

		if ( 'grade' === ( isset( $field['source'] ) ? (string) $field['source'] : '' ) && '' !== $want ) {
			$grade_from_form = $want;
		}

		$values[ $key ] = $want;
	}

	if ( $missing ) {
		return new WP_Error(
			'sp_missing_fields',
			'Fill in: ' . implode( ', ', $missing ) . '.'
		);
	}

	$row = array(
		'enrollment_id' => (int) $enrollment_id,
		'profile_id'    => (int) $enrollment->profile_id,
		'class_id'      => (int) $enrollment->class_id,
		/*
		 * The grade the form actually issued: a hand-corrected reissue wins over
		 * the result's grade, which is only the fallback for legacy rows.
		 */
		'grade'         => '' !== $grade_from_form
			? $grade_from_form
			: ( $result && $result->grade ? (string) $result->grade : null ),
		'issued_date'   => ! empty( $auto['issued'] ) ? $auto['issued'] : current_time( 'Y-m-d' ),
		'values'        => wp_json_encode( $values ),
		'status'        => 'issued',
	);

	if ( $existing ) {
		$wpdb->update(
			sp_table( 'certificates' ),
			$row,
			array( 'id' => (int) $existing->id ),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$certificate_id = (int) $existing->id;
	} else {
		$row['issued_by'] = get_current_user_id();

		$certificate_id = 0;
		$last_error     = '';

		// Serial can race another issue: retry with the next free serial.
		for ( $attempt = 0; $attempt < 3 && ! $certificate_id; $attempt++ ) {
			$row['serial'] = sp_certificate_next_serial();

			$wpdb->insert(
				sp_table( 'certificates' ),
				$row,
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
			);

			$last_error = (string) $wpdb->last_error;
			$candidate  = (int) $wpdb->insert_id;

			if ( $candidate && '' === $last_error ) {
				$certificate_id = $candidate;
				break;
			}

			if ( false === stripos( $last_error, 'duplicate' ) ) {
				break;
			}
		}

		if ( ! $certificate_id ) {
			if ( false !== stripos( $last_error, 'duplicate' ) ) {
				return new WP_Error(
					'sp_certificate_duplicate',
					'That certificate serial has already been issued to another student. Please try again.'
				);
			}

			return new WP_Error(
				'sp_certificate_failed',
				'The certificate could not be saved' . ( $last_error ? ': ' . $last_error : '.' )
			);
		}
	}

	$certificate = sp_get_certificate( $certificate_id );

	if ( ! $certificate ) {
		return new WP_Error( 'sp_certificate_missing', 'The certificate could not be read back.' );
	}

	/*
	 * The Certificate Manager generates the real certificate (QR, PDF,
	 * verification) through the single adapter in cert-push.php. Its failure
	 * means no certificate exists, so a fresh issue is rolled back and the
	 * error is surfaced; a reissue keeps the stored record -- the previous
	 * vendor certificate may still be valid -- and also surfaces the error.
	 */
	$pushed = sp_certificate_push_to_vendor( $certificate, $enrollment, $result );

	if ( is_wp_error( $pushed ) ) {
		if ( ! $existing ) {
			$wpdb->delete( sp_table( 'certificates' ), array( 'id' => (int) $certificate_id ), array( '%d' ) );
		}

		sp_log_activity(
			'certificate.push_failed',
			'certificate',
			$existing ? (int) $certificate_id : 0,
			$pushed->get_error_message()
		);

		return new WP_Error(
			'sp_vendor_generation_failed',
			'Certificate Manager could not generate the certificate: ' . $pushed->get_error_message()
		);
	}

	sp_log_activity(
		$existing ? 'certificate.reissued' : 'certificate.issued',
		'certificate',
		$certificate_id,
		sprintf( '%s — %s (%s)', $certificate->serial, $auto['student_name'], $auto['enroll_id'] )
	);

	return $certificate;
}

/**
 * One portal certificate row.
 *
 * @param int $certificate_id sp_certificates.id.
 * @return object|null
 */
function sp_get_certificate( $certificate_id ) {
	global $wpdb;

	if ( (int) $certificate_id <= 0 ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'certificates' ) . ' WHERE id = %d', (int) $certificate_id )
	);
}

/**
 * The decoded field values of a certificate.
 *
 * @param object $certificate sp_certificates row.
 * @return array key => value.
 */
function sp_certificate_values( $certificate ) {
	if ( ! $certificate || empty( $certificate->values ) ) {
		return array();
	}

	$decoded = json_decode( (string) $certificate->values, true );

	return is_array( $decoded ) ? $decoded : array();
}

/**
 * The grade a certificate actually shows when opened.
 *
 * A reissue can correct the grade by hand on the form; those stored values
 * are what the printed certificate and the Certificate Manager receive, so
 * they win over the `grade` column (kept as the fallback for rows issued
 * before stored values carried one).
 *
 * @param object $certificate sp_certificates row.
 * @return string Empty when no grade was recorded.
 */
function sp_certificate_display_grade( $certificate ) {
	if ( ! $certificate ) {
		return '';
	}

	$values = sp_certificate_values( $certificate );

	foreach ( sp_certificate_fields() as $field ) {
		$source = is_array( $field ) && isset( $field['source'] ) ? (string) $field['source'] : '';

		if ( 'grade' !== $source ) {
			continue;
		}

		$key = isset( $field['key'] ) ? (string) $field['key'] : '';

		if ( $key && isset( $values[ $key ] ) && '' !== trim( (string) $values[ $key ] ) ) {
			return trim( (string) $values[ $key ] );
		}
	}

	return $certificate->grade ? trim( (string) $certificate->grade ) : '';
}

/**
 * Push a finished certificate into the Certificate Manager, when it is present.
 *
 * Strictly one-way: nothing is read back and nothing here can fail the issue.
 * The vendor keys its student rows on the enrolment ID, which every issued
 * certificate has by construction.
 *
 * @param object  $certificate sp_certificates row.
 * @param object  $enrollment  sp_enrollments row.
 * @param object  $result      sp_results row, or null.
 * @return int|WP_Error Vendor cert_students.id.
 */
function sp_certificate_push_to_vendor( $certificate, $enrollment, $result = null ) {
	global $wpdb;

	if ( ! sp_certificate_manager_active() || ! function_exists( 'cert_mgr_generate_certificate' ) ) {
		return new WP_Error( 'sp_no_cert_manager', 'Certificate Manager is not active.' );
	}

	if ( ! $certificate || ! $enrollment ) {
		return new WP_Error( 'sp_push_no_input', 'There is no certificate to push.' );
	}

	$enroll_id = trim( (string) $enrollment->enroll_id );

	if ( '' === $enroll_id ) {
		return new WP_Error( 'sp_no_enroll_id', 'This enrolment has no ID to key the vendor record on.' );
	}

	$profile = sp_get_profile_by_id( (int) $enrollment->profile_id );
	$class   = sp_get_class( (int) $enrollment->class_id );

	if ( ! $profile || ! $class ) {
		return new WP_Error( 'sp_push_missing_rows', 'The student or class no longer exists.' );
	}

	$course        = ! empty( $class->course_id ) ? sp_get_course( (int) $class->course_id ) : null;
	$vendor_course = function_exists( 'sp_cert_mirror_course' ) ? sp_cert_mirror_course( $course ) : 0;
	$students      = sp_cert_table( 'students' );
	$grade         = $certificate->grade ? (string) $certificate->grade : '';

	/*
	 * A reissue may have corrected the grade, admission or completion dates by
	 * hand on the certificate form. Prefer those stored values over what the
	 * enrolment and result hold, so the Certificate Manager record ends up
	 * showing exactly what the printed certificate shows.
	 */
	$cert_grade      = '';
	$cert_admission  = '';
	$cert_completion = '';
	$cert_values     = sp_certificate_values( $certificate );

	foreach ( sp_certificate_fields() as $sp_cfield ) {
		$sp_ckey  = isset( $sp_cfield['key'] ) ? (string) $sp_cfield['key'] : '';
		$sp_cval  = isset( $cert_values[ $sp_ckey ] ) ? trim( (string) $cert_values[ $sp_ckey ] ) : '';

		if ( '' === $sp_cval ) {
			continue;
		}

		if ( 'grade' === ( isset( $sp_cfield['source'] ) ? (string) $sp_cfield['source'] : '' ) ) {
			$cert_grade = $sp_cval;
		} elseif ( 'admission' === ( isset( $sp_cfield['source'] ) ? (string) $sp_cfield['source'] : '' ) ) {
			$cert_admission = $sp_cval;
		} elseif ( 'completion' === ( isset( $sp_cfield['source'] ) ? (string) $sp_cfield['source'] : '' ) ) {
			$cert_completion = $sp_cval;
		}
	}

	$data = array(
		'student_name'    => (string) $profile->full_name,
		'course_name'     => (string) $class->title,
		'course_id'       => $vendor_course > 0 ? $vendor_course : null,
		'year'            => function_exists( 'sp_cert_year_for_class' ) ? sp_cert_year_for_class( $class ) : (string) current_time( 'Y' ),
		'grade'           => '' !== $cert_grade ? $cert_grade : ( '' !== $grade ? $grade : null ),
		'admission_date'  => '' !== $cert_admission ? $cert_admission : ( ! empty( $enrollment->admission_date ) ? (string) $enrollment->admission_date : null ),
		'completion_date' => '' !== $cert_completion ? $cert_completion : ( ! empty( $class->end_date ) ? (string) $class->end_date : null ),
	);

	$formats = array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' );

	/*
	 * Look the vendor row up by the id already linked to this certificate
	 * first. Keying only on the enrolment ID meant a reissue could miss the
	 * record it was meant to update — and insert a second one beside it —
	 * whenever the enrolment's ID had been edited since the first issue.
	 * The ID lookup stays as the fallback for legacy rows pushed before
	 * vendor_id existed.
	 */
	$vendor = null;

	if ( ! empty( $certificate->vendor_id ) ) {
		$vendor = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$students} WHERE id = %d", (int) $certificate->vendor_id )
		);
	}

	if ( ! $vendor ) {
		$vendor = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$students} WHERE enroll_id = %s", $enroll_id )
		);
	}

	if ( $vendor ) {
		$vendor_id   = (int) $vendor->id;
		$vendor_data = $data;

		// Keep the vendor's key in step with the portal's enrolment ID, so the
		// plugin's list shows the same ID as the portal's roster.
		$vendor_data['enroll_id'] = $enroll_id;
		$vendor_formats           = array_merge( $formats, array( '%s' ) );

		$updated = $wpdb->update( $students, $vendor_data, array( 'id' => $vendor_id ), $vendor_formats, array( '%d' ) );

		if ( false === $updated ) {
			return new WP_Error(
				'sp_push_failed',
				'The certificate record could not be updated' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' )
			);
		}
	} else {
		$data['enroll_id'] = $enroll_id;
		$data['status']    = 'not_generated';
		$formats[]         = '%s';
		$formats[]         = '%s';

		$wpdb->insert( $students, $data, $formats );

		$vendor_id = (int) $wpdb->insert_id;

		if ( ! $vendor_id ) {
			return new WP_Error(
				'sp_push_failed',
				'The certificate record could not be created' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' )
			);
		}
	}

	$generated = cert_mgr_generate_certificate( $vendor_id, false );

	if ( is_wp_error( $generated ) ) {
		return $generated;
	}

	$wpdb->update(
		sp_table( 'certificates' ),
		array( 'vendor_id' => $vendor_id ),
		array( 'id' => (int) $certificate->id ),
		array( '%d' ),
		array( '%d' )
	);

	sp_log_activity(
		'certificate.pushed',
		'certificate',
		(int) $certificate->id,
		$profile->full_name . ' — ' . $class->title . ' (' . $enroll_id . ')'
	);

	return $vendor_id;
}

/**
 * Public URL of the Certificate Manager's page for an issued certificate.
 *
 * The portal never draws a certificate itself: every "open and print" link
 * resolves to the vendor's verify page, which renders the generated template
 * with its QR code and PDF download, no login required.
 *
 * Issuances made before the hand-off (or while Certificate Manager was down)
 * are repaired on demand: the idempotent push runs when the vendor has no row
 * for this certificate's enrolment, and a vendor row that was never generated
 * is generated now. Both paths are idempotent, so repeated calls are cheap
 * after the first success.
 *
 * @param object|int $certificate sp_certificates row, or its id.
 * @return string|WP_Error Vendor verify-page URL.
 */
function sp_certificate_vendor_url( $certificate ) {
	global $wpdb;

	if ( ! is_object( $certificate ) ) {
		$certificate = sp_get_certificate( (int) $certificate );
	}

	if ( ! $certificate ) {
		return new WP_Error( 'sp_certificate_missing', 'That certificate no longer exists.' );
	}

	if ( ! sp_certificate_manager_active() || ! function_exists( 'cert_mgr_generate_certificate' ) ) {
		return new WP_Error(
			'sp_no_cert_manager',
			'Certificate Manager is not active, so this certificate cannot be opened.'
		);
	}

	$students = sp_cert_table( 'students' );
	$vendor   = null;

	if ( ! empty( $certificate->vendor_id ) ) {
		$vendor = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$students} WHERE id = %d", (int) $certificate->vendor_id )
		);
	}

	if ( ! $vendor ) {
		// Legacy or failed issuance: run the idempotent push to backfill.
		$enrollment = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . sp_table( 'enrollments' ) . ' WHERE id = %d',
				(int) $certificate->enrollment_id
			)
		);

		if ( $enrollment ) {
			$result     = sp_get_result_for_enrollment( (int) $enrollment->id );
			$vendor_id  = sp_certificate_push_to_vendor( $certificate, $enrollment, $result );

			if ( ! is_wp_error( $vendor_id ) ) {
				$vendor = $wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM {$students} WHERE id = %d", (int) $vendor_id )
				);
			}
		}
	}

	if ( ! $vendor ) {
		return new WP_Error( 'sp_vendor_missing', 'Certificate Manager has no record for this certificate yet.' );
	}

	// A vendor row that was pushed but never generated (legacy issues).
	if ( 'generated' !== (string) $vendor->status || empty( $vendor->certificate_id ) ) {
		$generated = cert_mgr_generate_certificate( (int) $vendor->id, false );

		if ( is_wp_error( $generated ) ) {
			return $generated;
		}

		$vendor = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$students} WHERE id = %d", (int) $vendor->id )
		);
	}

	if ( ! $vendor ) {
		return new WP_Error( 'sp_vendor_missing', 'Certificate Manager has no record for this certificate yet.' );
	}

	$verify_page = get_page_by_path( 'verify-certificate' );
	$base        = $verify_page ? get_permalink( $verify_page->ID ) : home_url( '/verify-certificate/' );

	if ( ! empty( $vendor->certificate_id ) ) {
		return add_query_arg( 'cert_id', rawurlencode( (string) $vendor->certificate_id ), $base );
	}

	return add_query_arg( 'enroll_id', rawurlencode( (string) $vendor->enroll_id ), $base );
}

/**
 * Render the dynamic certificate form on the admin Results screen.
 *
 * Fields come from `sp_certificate_fields()`. Anything with an auto-fill source
 * arrives pre-filled and can still be overwritten; anything without arrives
 * blank. Already-issued certificates pre-fill from the stored values — empty
 * slots fall back to auto-fill — and reissue overwrites them.
 *
 * @param int $enrollment_id Enrollment id.
 * @param int $class_id      Class id, for the redirect.
 */
function sp_admin_render_certificate_form( $enrollment_id, $class_id = 0 ) {
	global $wpdb;

	$enrollment = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'enrollments' ) . ' WHERE id = %d', (int) $enrollment_id )
	);

	if ( ! $enrollment ) {
		return;
	}

	$state = sp_certificate_state_for_enrollment( (int) $enrollment_id );

	if ( ! $state['can_generate'] && ! $state['generated'] ) {
		printf(
			'<div class="sp-admin-panel"><h2>Certificate</h2><p>%s</p></div>',
			esc_html( $state['reason'] )
		);
		return;
	}

	$profile    = sp_get_profile_by_id( (int) $enrollment->profile_id );
	$result     = sp_get_result_for_enrollment( (int) $enrollment_id );
	$auto       = sp_certificate_auto_values( $enrollment, $result );
	$fields     = sp_certificate_fields();
	$existing   = sp_get_certificate_for_enrollment( (int) $enrollment_id );
	$saved      = sp_certificate_values( $existing );
	?>
	<div class="sp-admin-panel sp-cert-form">
		<h2>
			<?php echo $existing ? 'Certificate ' . esc_html( $existing->serial ) : 'Issue certificate'; ?>
			<span class="sp-admin-muted">&mdash; <?php echo esc_html( $profile ? $profile->full_name : '' ); ?></span>
		</h2>

		<?php if ( ! sp_certificate_manager_active() ) : ?>
			<div class="notice notice-warning"><p>
				<strong>Certificate Manager is inactive.</strong>
				Certificates cannot be issued or opened until the plugin is activated again.
			</p></div>
		<?php endif; ?>

		<?php if ( $existing ) : ?>
			<p class="sp-admin-muted">
				Issued <?php echo esc_html( $existing->issued_date ? date_i18n( get_option( 'date_format' ), strtotime( $existing->issued_date ) ) : '' ); ?>.
				All fields stay editable — change what you need, then reissue to save.
			</p>
			<p>
				<?php $sp_open_url = sp_certificate_vendor_url( $existing ); ?>
				<?php if ( ! is_wp_error( $sp_open_url ) ) : ?>
					<a class="button" target="_blank" rel="noopener"
						href="<?php echo esc_url( $sp_open_url ); ?>">Open and print (Certificate Manager)</a>
				<?php else : ?>
					<span class="sp-admin-muted"><?php echo esc_html( $sp_open_url->get_error_message() ); ?></span>
				<?php endif; ?>
			</p>
		<?php else : ?>
			<p class="sp-admin-muted">
				The fields below come from Settings &rarr; Certificates. Anything the portal already knows arrives
				filled in; anything it does not is yours to type.
			</p>
		<?php endif; ?>

		<form method="post">
			<?php sp_admin_nonce_field( 'save_certificate' ); ?>
			<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( (int) $enrollment_id ); ?>" />
			<?php if ( $class_id > 0 ) : ?>
				<input type="hidden" name="class_id" value="<?php echo esc_attr( (int) $class_id ); ?>" />
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $sp_field ) : ?>
					<?php
					$sp_key  = isset( $sp_field['key'] ) ? (string) $sp_field['key'] : '';
					$sp_type = isset( $sp_field['type'] ) ? (string) $sp_field['type'] : 'text';
					$sp_source = isset( $sp_field['source'] ) ? (string) $sp_field['source'] : '';
					$sp_options = sp_certificate_field_options( $sp_field );

					$sp_value = '';

					if ( $existing && array_key_exists( $sp_key, $saved ) && '' !== trim( (string) $saved[ $sp_key ] ) ) {
						$sp_value = (string) $saved[ $sp_key ];
					} elseif ( '' !== $sp_source && isset( $auto[ $sp_source ] ) ) {
						$sp_value = (string) $auto[ $sp_source ];
					}

					$sp_input_name = 'cert_values[' . $sp_key . ']';
					?>
					<tr>
						<th scope="row">
							<label for="sp_cert_<?php echo esc_attr( $sp_key ); ?>">
								<?php echo esc_html( isset( $sp_field['label'] ) ? $sp_field['label'] : $sp_key ); ?>
								<?php if ( ! empty( $sp_field['required'] ) ) : ?>
									<span class="sp-required">*</span>
								<?php endif; ?>
							</label>
						</th>
						<td>
							<?php if ( $sp_options ) : ?>
								<select id="sp_cert_<?php echo esc_attr( $sp_key ); ?>"
									name="<?php echo esc_attr( $sp_input_name ); ?>">
									<?php if ( '' === $sp_value ) : ?>
										<option value=""><?php echo esc_html( ! empty( $sp_field['required'] ) ? 'Select…' : '— none —' ); ?></option>
									<?php endif; ?>
									<?php if ( '' !== $sp_value && ! in_array( $sp_value, $sp_options, true ) ) : ?>
										<option value="<?php echo esc_attr( $sp_value ); ?>" selected><?php echo esc_html( $sp_value ); ?></option>
									<?php endif; ?>
									<?php foreach ( $sp_options as $sp_option ) : ?>
										<option value="<?php echo esc_attr( $sp_option ); ?>"
											<?php selected( $sp_value, $sp_option ); ?>><?php echo esc_html( $sp_option ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( 'textarea' === $sp_type ) : ?>
								<textarea id="sp_cert_<?php echo esc_attr( $sp_key ); ?>"
									name="<?php echo esc_attr( $sp_input_name ); ?>"
									class="large-text" rows="3"><?php echo esc_textarea( $sp_value ); ?></textarea>
							<?php elseif ( 'date' === $sp_type ) : ?>
								<input type="date" id="sp_cert_<?php echo esc_attr( $sp_key ); ?>"
									name="<?php echo esc_attr( $sp_input_name ); ?>"
									value="<?php echo esc_attr( $sp_value ); ?>" />
							<?php else : ?>
								<input type="text" id="sp_cert_<?php echo esc_attr( $sp_key ); ?>"
									name="<?php echo esc_attr( $sp_input_name ); ?>"
									class="regular-text" value="<?php echo esc_attr( $sp_value ); ?>" />
							<?php endif; ?>

							<?php if ( '' !== $sp_source ) : ?>
								<p class="description">
									Auto-filled from
									<em><?php echo esc_html( isset( sp_certificate_field_sources()[ $sp_source ] ) ? sp_certificate_field_sources()[ $sp_source ] : $sp_source ); ?></em>.
								</p>
							<?php else : ?>
								<p class="description">Typed in by hand.</p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<p class="description">
				Attendance at issue: <strong><?php echo esc_html( sp_format_number( $state['attendance'] ) ); ?>%</strong>
				(<?php echo esc_html( sp_format_number( sp_certificate_min_attendance() ) ); ?>% required).
			</p>

			<?php submit_button( $existing ? 'Reissue certificate' : 'Issue certificate' ); ?>
		</form>

		<p>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'class', (int) $class_id, admin_url( 'admin.php?page=sp-results' ) ) ); ?>">Back to results</a>
		</p>
	</div>
	<?php
}

/**
 * 404 for a certificate link that resolves to nothing.
 *
 * @param string $message Optional explanation shown instead of the default text.
 */
function sp_render_certificate_missing( $message = '' ) {
	status_header( 404 );
	nocache_headers();
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title><?php echo esc_html( 'Certificate not found — ' . get_bloginfo( 'name' ) ); ?></title>
		<style>
			body {
				margin: 0;
				padding: 64px 16px;
				background: #f3f4f6;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
				color: #1f2937;
				text-align: center;
			}
			h1 { margin: 0 0 8px; font-size: 24px; }
			p { margin: 0 0 16px; color: #6b7280; }
			a { color: #1f2937; }
		</style>
	</head>
	<body>
		<h1>Certificate not found</h1>
		<p><?php echo esc_html( '' !== $message ? $message : 'That certificate link does not match any issued certificate.' ); ?></p>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to the site</a></p>
	</body>
	</html>
	<?php
	exit;
}
