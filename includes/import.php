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
		'address'         => 'address',
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

	// A spreadsheet export often carries a UTF-8 byte order mark, which would
	// otherwise glue itself onto the first column name and stop it matching.
	if ( isset( $headers[0] ) && 0 === strpos( (string) $headers[0], "\xEF\xBB\xBF" ) ) {
		$headers[0] = substr( (string) $headers[0], 3 );
	}

	$mapped = array();

	foreach ( $headers as $index => $header ) {
		$key = strtolower( trim( (string) $header ) );
		$key = preg_replace( '/[^a-z0-9_]/', '_', $key );
		// "Password:" and "Password *" both normalise to "password_" and must
		// still land on the map instead of arriving as an unknown column.
		$key = trim( (string) $key, '_' );
		$mapped[ $index ] = isset( $map[ $key ] ) ? $map[ $key ] : $key;
	}

	/*
	 * Every mapped field exists on every row, even the ones whose column the
	 * file does not contain. The planners read them unconditionally, so
	 * without this a file missing an optional column raised an undefined-key
	 * warning on each row.
	 */
	$known = array_unique( array_values( $map ) );

	$rows = array();
	$line = 1;

	while ( false !== ( $data = fgetcsv( $handle ) ) ) {
		++$line;

		if ( null === $data || array( null ) === $data ) {
			continue;
		}

		$row = array_fill_keys( $known, '' );

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

		// The card prints one short address line, so the import matches the
		// portal's 100-character field rather than the wider column.
		$address = isset( $row['address'] ) ? trim( sanitize_textarea_field( (string) $row['address'] ) ) : '';
		$address = function_exists( 'mb_substr' ) ? mb_substr( $address, 0, 100 ) : substr( $address, 0, 100 );

		$entry = array(
			'line'    => $line,
			'action'  => 'create',
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'enroll'  => $enroll,
			'class'   => $code,
			'detail'  => '',
			'admission_date' => $admit,
			/*
			 * The commit step works only from this plan, so these must be
			 * carried through: dropping them meant a password column was
			 * replaced by a generated one and the dob column was ignored
			 * without a word.
			 */
			'dob'      => isset( $row['dob'] ) ? sp_sanitize_date( $row['dob'] ) : '',
			'password' => isset( $row['password'] ) ? trim( (string) $row['password'] ) : '',
			'address'  => $address,
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
				'address'         => isset( $row['address'] ) ? $row['address'] : '',
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
			/*
			 * The commit step works only from this plan, so the password has to
			 * be carried through here. It used to be dropped, which made every
			 * row fail at commit with "no password supplied" even when the CSV
			 * column was filled in.
			 */
			'password' => isset( $row['password'] ) ? trim( (string) $row['password'] ) : '',
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
			} elseif ( '' === $entry['password'] ) {
				// Required by the single-add form too. The dry run is the place
				// to say so: the commit step refuses the row outright, and a
				// failure discovered after confirming cannot be corrected here.
				$entry['action'] = 'error';
				$entry['detail'] = 'Missing password. Teachers need one to sign in.';
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
		array( 'name', 'email', 'phone', 'enroll_id', 'class_code', 'admission_date', 'dob', 'password', 'address' ),
		array( 'Asha Verma', 'asha@example.com', '9876543210', 'WEB2025001', 'WEB-2025-A', '2025-01-15', '2001-04-12', '', '12 MG Road, Jaipur 302001' ),
		array( 'Ravi Kumar', 'ravi@example.com', '9876543211', '', '', '', '2000-09-30', 'Ravi@2025', '' ),
	);

	return sp_render_csv( $rows );
}

/**
 * Curriculum CSV column map.
 *
 * @return array
 */
function sp_import_curriculum_column_map() {
	return array(
		'title'       => 'title',
		'description' => 'detail',
		'heading'     => 'is_heading',
		'group_title' => 'group_title',
		'parent'      => 'parent_id',
		'order'       => 'position',
		'course_code' => 'course_code',
	);
}

/**
 * Sample course-outline CSV for the Courses screen importer.
 *
 * Mirrors the exact columns `sp_admin_action_import_curriculum()` reads:
 * `title` (required), `detail` (optional), `type` (`heading` or `topic`)
 * and `parent` (the heading title a topic sits under). A `parent` that does
 * not exist yet is created as a heading on the fly, so the last row below
 * shows both patterns working from one file.
 *
 * @return string
 */
function sp_sample_curriculum_csv() {
	$rows = array(
		array( 'title', 'detail', 'type', 'parent' ),
		array(
			'Computer Fundamentals & Digital Systems',
			'Foundational computing, hardware components, number logic & storage primitives',
			'heading',
			'',
		),
		array(
			'Computer Hardware & Central Processing Unit',
			'Which component is often called the brain of a computer? (ALU, registers, microarchitecture)',
			'topic',
			'Computer Fundamentals & Digital Systems',
		),
		array(
			'Number Systems, Binary & Logic Fundamentals',
			'Which number system uses only 0 and 1? Binary conversion and hexadecimal colors',
			'topic',
			'Computer Fundamentals & Digital Systems',
		),
		array(
			'Web Fundamentals & Visual Structuring',
			'Document structure, CSS styling, URL protocols & web clients',
			'heading',
			'',
		),
		array(
			'Semantic Web Structure & Content Hierarchy',
			'Which language is primarily used to structure content on web pages? (HTML5 syntax & elements)',
			'topic',
			'Web Fundamentals & Visual Structuring',
		),
		array(
			'CSS Styling & Visual Presentation Engine',
			'Which technology is mainly used to style web pages? Cascade rules, flexbox, box models',
			'topic',
			'Web Fundamentals & Visual Structuring',
		),
		array(
			'Orientation & Course Induction',
			'A top-level topic with no heading — leave the parent cell blank',
			'topic',
			'',
		),
	);

	return sp_render_csv( $rows );
}

/**
 * Dry run a curriculum CSV import.
 *
 * @param array $rows Parsed CSV rows.
 * @return array[] One entry per row: line, action, title, detail, error.
 */
function sp_plan_curriculum_import( $rows ) {
	global $wpdb;

	$plan = array();
	$course_map = array();

	// First pass: collect all course codes and build a map.
	foreach ( $rows as $row ) {
		$course_code = isset( $row['course_code'] ) ? trim( $row['course_code'] ) : '';

		if ( '' !== $course_code && ! isset( $course_map[ $course_code ] ) ) {
			$class = sp_find_class_by_code( $course_code );

			if ( $class && $class->course_id ) {
				$course_map[ $course_code ] = (int) $class->course_id;
			} else {
				$course = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id FROM " . sp_table( 'courses' ) . " WHERE code = %s",
						$course_code
					)
				);

				if ( $course ) {
					$course_map[ $course_code ] = (int) $course->id;
				}
			}
		}
	}

	// Track heading ids by course for parent references.
	$heading_ids = array();

	foreach ( $rows as $row ) {
		$line         = (int) $row['__line'];
		$title        = sanitize_text_field( $row['title'] );
		$detail       = isset( $row['detail'] ) ? sanitize_textarea_field( $row['detail'] ) : '';
		$is_heading   = ! empty( $row['is_heading'] );
		$group_title  = $is_heading ? sanitize_text_field( $row['group_title'] ) : '';
		$parent_ref   = isset( $row['parent_id'] ) ? trim( $row['parent_id'] ) : '';
		$position     = isset( $row['position'] ) ? trim( $row['position'] ) : '';
		$course_code  = isset( $row['course_code'] ) ? trim( $row['course_code'] ) : '';

		$entry = array(
			'line'  => $line,
			'action' => 'create',
			'title' => $title,
			'detail' => $detail,
			'error' => '',
		);

		// Validate course.
		if ( '' === $course_code ) {
			$entry['action'] = 'error';
			$entry['error']  = 'Missing course_code.';
			$plan[]          = $entry;
			continue;
		}

		$course_id = isset( $course_map[ $course_code ] ) ? $course_map[ $course_code ] : 0;

		if ( ! $course_id ) {
			$entry['action'] = 'error';
			$entry['error']  = sprintf( 'No course found with code "%s".', $course_code );
			$plan[]          = $entry;
			continue;
		}

		$entry['course_id'] = $course_id;

		// Validate heading.
		if ( $is_heading && '' === $group_title ) {
			$entry['action'] = 'error';
			$entry['error']  = 'Heading rows require a group_title.';
			$plan[]          = $entry;
			continue;
		}

		// Validate topic has parent or is under implicit group.
		if ( ! $is_heading && '' !== $parent_ref ) {
			// Parent reference can be "course_code:heading_title" or a heading index.
			// For simplicity, we track headings by their position in the import.
		}

		if ( '' === $position || ! ctype_digit( $position ) ) {
			$entry['action'] = 'error';
			$entry['error']  = 'Invalid position (must be a number).';
			$plan[]          = $entry;
			continue;
		}

		$entry['is_heading']  = $is_heading;
		$entry['group_title'] = $group_title;
		$entry['position']    = (int) $position;
		$entry['parent_ref']  = $parent_ref;

		$plan[] = $entry;
	}

	return $plan;
}

/**
 * Execute a curriculum import plan.
 *
 * @param array $plan Output of sp_plan_curriculum_import().
 * @return array Counts and errors.
 */
function sp_execute_curriculum_import( $plan ) {
	$created = 0;
	$errors  = array();
	$heading_map = array();

	foreach ( $plan as $row ) {
		if ( 'create' !== $row['action'] ) {
			if ( ! empty( $row['error'] ) ) {
				$errors[] = sprintf( 'Line %d: %s', $row['line'], $row['error'] );
			}
			continue;
		}

		$args = array(
			'course_id'   => $row['course_id'],
			'title'       => $row['title'],
			'detail'      => $row['detail'],
			'is_heading'  => $row['is_heading'],
			'group_title' => $row['group_title'],
			'position'    => $row['position'],
		);

		// Handle parent reference.
		if ( ! $row['is_heading'] && ! empty( $row['parent_ref'] ) ) {
			// Try to find the heading by course_code:heading_title format.
			$parts = explode( ':', $row['parent_ref'] );

			if ( count( $parts ) >= 2 ) {
				$parent_course_code = trim( $parts[0] );
				$parent_heading_title = trim( $parts[1] );

				// Look for the heading in our created headings.
				if ( isset( $heading_map[ $parent_course_code . ':' . $parent_heading_title ] ) ) {
					$args['parent_id'] = $heading_map[ $parent_course_code . ':' . $parent_heading_title ];
				}
			}
		}

		$result = sp_save_curriculum( $args );

		if ( is_wp_error( $result ) ) {
			$errors[] = sprintf( 'Line %d (%s): %s', $row['line'], $row['title'], $result->get_error_message() );
			continue;
		}

		// Track heading for parent references.
		if ( $row['is_heading'] ) {
			$heading_map[ $row['course_id'] . ':' . $row['group_title'] ] = (int) $result;
		}

		++$created;
	}

	return array(
		'created' => $created,
		'errors'  => $errors,
	);
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