<?php
/**
 * CSV import for students and teachers.
 *
 * Two-phase by design: a dry run reports what each row would do and why, and
 * only a confirmed second step writes anything. A bad row never aborts the run.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map CSV header names to internal field keys.
 *
 * @return array
 */
function sp_import_student_column_map() {
	return array(
		'name'            => 'full_name',
		'full_name'       => 'full_name',
		'student_name'    => 'full_name',
		'email'           => 'email',
		'email_address'   => 'email',
		'phone'           => 'phone',
		'mobile'          => 'phone',
		'enroll_id'       => 'enroll_id',
		'enrollment_id'   => 'enroll_id',
		'class_code'   => 'class_code',
		'course_code'     => 'class_code',
		'admission_date'  => 'admission_date',
		'dob'             => 'dob',
		'date_of_birth'   => 'dob',
		'birth_date'      => 'dob',
		'birthdate'       => 'dob',
		'password'        => 'password',
		'pass'            => 'password',
	);
}

/**
 * Map CSV header names to internal field keys for teachers.
 *
 * @return array
 */
function sp_import_teacher_column_map() {
	return array(
		'name'       => 'full_name',
		'full_name'  => 'full_name',
		'teacher_name' => 'full_name',
		'email'      => 'email',
		'phone'      => 'phone',
		'mobile'     => 'phone',
		'password'   => 'password',
		'pass'       => 'password',
	);
}

/**
 * Read an uploaded CSV into an associative array keyed by mapped field names.
 *
 * @param array $file One entry from $_FILES.
 * @param array $map  Header name => field key.
 * @return array|WP_Error {headers, rows}
 */
function sp_read_import_csv( $file, $map ) {
	if ( ! isset( $file['tmp_name'], $file['error'] ) ) {
		return new WP_Error( 'sp_no_file', 'No file was received.' );
	}

	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return new WP_Error( 'sp_upload_error', 'The upload failed (error code ' . (int) $file['error'] . ').' );
	}

	if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'sp_not_uploaded_file', 'The upload could not be verified.' );
	}

	$extension = strtolower( (string) pathinfo( $file['name'], PATHINFO_EXTENSION ) );

	if ( ! in_array( $extension, array( 'csv', 'txt' ), true ) ) {
		return new WP_Error( 'sp_bad_file_type', 'Upload a .csv file exported as comma-separated values.' );
	}

	$handle = fopen( $file['tmp_name'], 'r' );

	if ( ! $handle ) {
		return new WP_Error( 'sp_unreadable', 'The uploaded file could not be read.' );
	}

	$headers = fgetcsv( $handle );

	if ( ! is_array( $headers ) || ! $headers ) {
		fclose( $handle );
		return new WP_Error( 'sp_empty_csv', 'That file appears to be empty.' );
	}

	$mapped = array();

	foreach ( $headers as $index => $header ) {
		$key = strtolower( trim( (string) $header ) );
		$key = preg_replace( '/[^a-z0-9_]/', '_', $key );
		$mapped[ $index ] = isset( $map[ $key ] ) ? $map[ $key ] : $key;
	}

	$rows = array();
	$line = 1;

	while ( false !== ( $data = fgetcsv( $handle ) ) ) {
		++$line;

		if ( null === $data || array( null ) === $data ) {
			continue;
		}

		$row = array();

		foreach ( $mapped as $index => $field ) {
			$row[ $field ] = isset( $data[ $index ] ) ? trim( (string) $data[ $index ] ) : '';
		}

		$row['__line'] = $line;
		$rows[]        = $row;
	}

	fclose( $handle );

	return array(
		'headers' => $headers,
		'rows'    => $rows,
	);
}

/**
 * Find a class by its code, falling back to an exact title match.
 *
 * @param string $code Class code or title.
 * @return object|null
 */
function sp_find_class_by_code( $code ) {
	global $wpdb;

	$code = trim( (string) $code );

	if ( '' === $code ) {
		return null;
	}

	$table = sp_table( 'classes' );

	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s ORDER BY id ASC LIMIT 1", $code )
	);

	if ( $row ) {
		return $row;
	}

	return $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE title = %s ORDER BY id ASC LIMIT 1", $code )
	);
}

/**
 * Dry run a student CSV: decide what would happen to every row.
 *
 * @param array $rows Parsed CSV rows.
 * @return array[] One entry per row: line, action, name, email, detail, payload.
 */
function sp_plan_student_import( $rows ) {
	global $wpdb;

	$plan = array();

	foreach ( $rows as $row ) {
		$line   = (int) $row['__line'];
		$name   = sanitize_text_field( $row['full_name'] );
		$email  = sanitize_email( $row['email'] );
		$phone  = sanitize_text_field( $row['phone'] );
		$enroll = sanitize_text_field( $row['enroll_id'] );
		$code   = sanitize_text_field( $row['class_code'] );
		$admit  = sp_sanitize_date( $row['admission_date'] );

		$entry = array(
			'line'    => $line,
			'action'  => 'create',
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'enroll'  => $enroll,
			'class' => $code,
			'detail'  => '',
			'admission_date' => $admit,
		);

		if ( '' === $name ) {
			$entry['action'] = 'error';
			$entry['detail'] = 'Missing name.';
			$plan[]          = $entry;
			continue;
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$entry['action'] = 'error';
			$entry['detail'] = 'Missing or invalid email address.';
			$plan[]          = $entry;
			continue;
		}

		$portal = sp_email_portal( $email );

		if ( $portal ) {
			$entry['action'] = 'skip';
			$entry['detail'] = sprintf( 'Already has a %s portal account.', $portal );
			$plan[]          = $entry;
			continue;
		}

		if ( email_exists( $email ) ) {
			$entry['action'] = 'skip';
			$entry['detail'] = 'Already has a WordPress account.';
			$plan[]          = $entry;
			continue;
		}

		if ( '' !== $code ) {
			$class = sp_find_class_by_code( $code );

			if ( ! $class ) {
				$entry['action'] = 'error';
				$entry['detail'] = sprintf( 'No class matches the code "%s".', $code );
				$plan[]          = $entry;
				continue;
			}

			$entry['class_id'] = (int) $class->id;
			$entry['detail']   = sprintf( 'Will enroll in %s.', $class->title );

			// Enrolment IDs are per-class; a second active enrolment in the same
			// class with the same ID would be ambiguous.
			if ( '' !== $enroll ) {
				$taken = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT id FROM ' . sp_table( 'enrollments' )
						. ' WHERE class_id = %d AND enroll_id = %s',
						(int) $class->id,
						$enroll
					)
				);

				if ( $taken ) {
					$entry['action'] = 'error';
					$entry['detail'] = sprintf( 'Enrolment ID "%s" is already used in this class.', $enroll );
					$plan[]          = $entry;
					continue;
				}
			}
		} else {
			$entry['detail'] = 'No class given; account only.';
		}

		$plan[] = $entry;
	}

	return $plan;
}

/**
 * Execute a previously reviewed student import plan.
 *
 * @param array $plan Output of sp_plan_student_import().
 * @return array Counts and per-row outcomes.
 */
function sp_execute_student_import( $plan ) {
	$created = 0;
	$errors  = array();

	foreach ( $plan as $row ) {
		if ( 'create' !== $row['action'] ) {
			continue;
		}

		$result = sp_create_account(
			array(
				'role'            => SP_ROLE_STUDENT,
				'email'           => $row['email'],
				'full_name'       => $row['name'],
				'phone'           => $row['phone'],
				// Optional: blank generates a random password instead.
				'dob'             => isset( $row['dob'] ) ? $row['dob'] : '',
				'password'        => isset( $row['password'] ) ? trim( $row['password'] ) : '',
				'send_email'      => true,
			)
		);

		if ( is_wp_error( $result ) ) {
			$errors[] = sprintf( 'Line %d (%s): %s', $row['line'], $row['email'], $result->get_error_message() );
			continue;
		}

		if ( ! empty( $row['class_id'] ) ) {
			$enrolled = sp_enroll_student(
				$result['record_id'],
				(int) $row['class_id'],
				$row['admission_date'],
				true,
				isset( $row['enroll'] ) ? $row['enroll'] : ''
			);

			if ( is_wp_error( $enrolled ) ) {
				$errors[] = sprintf(
					'Line %d (%s): account created but enrollment failed — %s',
					$row['line'],
					$row['email'],
					$enrolled->get_error_message()
				);
			}
		}

		++$created;
	}

	return array(
		'created' => $created,
		'errors'  => $errors,
	);
}

/**
 * Dry run a teacher CSV.
 *
 * @param array $rows Parsed CSV rows.
 * @return array[]
 */
function sp_plan_teacher_import( $rows ) {
	$plan = array();

	foreach ( $rows as $row ) {
		$line  = (int) $row['__line'];
		$name  = sanitize_text_field( $row['full_name'] );
		$email = sanitize_email( $row['email'] );
		$phone = sanitize_text_field( $row['phone'] );

		$entry = array(
			'line'   => $line,
			'action' => 'create',
			'name'   => $name,
			'email'  => $email,
			'phone'  => $phone,
			'detail' => '',
		);

		if ( '' === $name ) {
			$entry['action'] = 'error';
			$entry['detail'] = 'Missing name.';
		} elseif ( '' === $email || ! is_email( $email ) ) {
			$entry['action'] = 'error';
			$entry['detail'] = 'Missing or invalid email address.';
		} else {
			$portal = sp_email_portal( $email );

			if ( $portal ) {
				$entry['action'] = 'skip';
				$entry['detail'] = sprintf( 'Already has a %s portal account.', $portal );
			} elseif ( email_exists( $email ) ) {
				$entry['action'] = 'skip';
				$entry['detail'] = 'Already has a WordPress account.';
			} else {
				$entry['detail'] = 'Will create a teacher account and email credentials.';
			}
		}

		$plan[] = $entry;
	}

	return $plan;
}

/**
 * Execute a previously reviewed teacher import plan.
 *
 * @param array $plan Output of sp_plan_teacher_import().
 * @return array
 */
function sp_execute_teacher_import( $plan ) {
	$created = 0;
	$errors  = array();

	foreach ( $plan as $row ) {
		if ( 'create' !== $row['action'] ) {
			continue;
		}

		/*
		 * A teacher password is required in bulk too, matching the single-add form.
		 * Rows without one are reported as errors in the dry run rather than
		 * quietly getting a generated password nobody agreed to.
		 */
		$teacher_password = isset( $row['password'] ) ? trim( $row['password'] ) : '';

		if ( '' === $teacher_password ) {
			$errors[] = sprintf( 'Line %d (%s): no password supplied. Teachers require one.', $row['line'], $row['email'] );
			continue;
		}

		$result = sp_create_account(
			array(
				'role'       => SP_ROLE_TEACHER,
				'email'      => $row['email'],
				'full_name'  => $row['name'],
				'phone'      => $row['phone'],
				'password'   => $teacher_password,
				'send_email' => true,
			)
		);

		if ( is_wp_error( $result ) ) {
			$errors[] = sprintf( 'Line %d (%s): %s', $row['line'], $row['email'], $result->get_error_message() );
			continue;
		}

		++$created;
	}

	return array(
		'created' => $created,
		'errors'  => $errors,
	);
}

/**
 * A ready-to-fill sample CSV for students.
 *
 * @return string
 */
function sp_sample_student_csv() {
	$rows = array(
		array( 'name', 'email', 'phone', 'enroll_id', 'class_code', 'admission_date', 'dob', 'password' ),
		array( 'Asha Verma', 'asha@example.com', '9876543210', 'WEB2025001', 'WEB-2025-A', '2025-01-15', '2001-04-12', '' ),
		array( 'Ravi Kumar', 'ravi@example.com', '9876543211', '', '', '', '2000-09-30', 'Ravi@2025' ),
	);

	return sp_render_csv( $rows );
}

/**
 * A ready-to-fill sample CSV for teachers.
 *
 * @return string
 */
function sp_sample_teacher_csv() {
	$rows = array(
		array( 'name', 'email', 'phone', 'password' ),
		array( 'Sunita Sharma', 'sunita@example.com', '9876543212', 'Sunita@2025' ),
	);

	return sp_render_csv( $rows );
}

/**
 * Render rows as CSV text.
 *
 * @param array $rows Array of arrays.
 * @return string
 */
function sp_render_csv( $rows ) {
	$handle = fopen( 'php://temp', 'r+' );

	foreach ( $rows as $row ) {
		fputcsv( $handle, $row );
	}

	rewind( $handle );
	$csv = stream_get_contents( $handle );
	fclose( $handle );

	return $csv;
}