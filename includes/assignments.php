<?php
/**
 * Assignments and exams — one entity with a type.
 *
 * `paper`   : the teacher uploads a file; students read and download it.
 * `exam`    : a timed test built from the class question bank, split into one
 *             or more sets (A, B, ...) which can be dealt to students the same,
 *             randomly, or manually. Leaving midway marks the attempt failed;
 *              only the teacher can grant a retake.
 *
 * Questions live in a per-class bank (`sp_questions`) imported from CSV or
 * added by hand, and are reused across every exam of that class. Exams are
 * graded the moment the student submits: MCQ and true/false match exactly,
 * fill-in-the-blank compares case-insensitively and accepts `|`-separated
 * alternatives.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Labels and enums.
 * ---------------------------------------------------------------------- */

/**
 * The assignment types this plugin understands.
 *
 * @return string[]
 */
function sp_assignment_types() {
	return array( 'paper', 'exam' );
}

/**
 * Human label for one assignment type.
 *
 * @param string $type Type key.
 * @return string
 */
function sp_assignment_type_label( $type ) {
	$labels = array(
		'paper' => 'Paper',
		'exam'  => 'Exam',
	);

	return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( (string) $type );
}

/**
 * The assignment lifecycle statuses.
 *
 * @return string[]
 */
function sp_assignment_statuses() {
	return array( 'draft', 'published', 'closed' );
}

/**
 * Human label for one status.
 *
 * @param string $status Status key.
 * @return string
 */
function sp_assignment_status_label( $status ) {
	$labels = array(
		'draft'     => 'Draft',
		'published' => 'Published',
		'closed'    => 'Closed',
	);

	return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( (string) $status );
}

/**
 * Status as a styled badge, for tables.
 *
 * @param string $status Status key.
 * @return string Safe HTML.
 */
function sp_assignment_status_badge( $status ) {
	$classes = array(
		'draft'     => 'sp-badge-muted',
		'published' => 'sp-badge-good',
		'closed'    => 'sp-badge-warn',
	);

	$class = isset( $classes[ $status ] ) ? $classes[ $status ] : 'sp-badge-muted';

	return '<span class="sp-badge ' . $class . '">' . esc_html( sp_assignment_status_label( $status ) ) . '</span>';
}

/**
 * A student's attempt status as a styled badge, for tables.
 *
 * @param string $status Attempt status key.
 * @return string Safe HTML.
 */
function sp_attempt_status_badge( $status ) {
	$classes = array(
		'pending'    => 'sp-badge-muted',
		'in_progress' => 'sp-badge-warn',
		'submitted'  => 'sp-badge-good',
		'failed'     => 'sp-badge-bad',
	);

	$labels = array(
		'pending'     => 'Not started',
		'in_progress' => 'In progress',
		'submitted'   => 'Submitted',
		'failed'      => 'Failed',
	);

	$class  = isset( $classes[ $status ] ) ? $classes[ $status ] : 'sp-badge-muted';
	$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( (string) $status );

	return '<span class="sp-badge ' . $class . '">' . esc_html( $label ) . '</span>';
}

/**
 * A student's feed-state as a styled badge, for tables.
 *
 * @param string $state State key from sp_assignment_student_state().
 * @return string Safe HTML.
 */
function sp_assignment_state_badge( $state ) {
	$classes = array(
		'unavailable' => 'sp-badge-muted',
		'upcoming'    => 'sp-badge-info',
		'available'   => 'sp-badge-good',
		'missed'      => 'sp-badge-bad',
		'in_progress' => 'sp-badge-warn',
		'failed'      => 'sp-badge-bad',
		'submitted'   => 'sp-badge-good',
		'closed'      => 'sp-badge-muted',
		'open'        => 'sp-badge-good',
	);

	$labels = array(
		'unavailable' => 'Unavailable',
		'upcoming'    => 'Upcoming',
		'available'   => 'Available now',
		'missed'      => 'Missed',
		'in_progress' => 'In progress',
		'failed'      => 'Failed',
		'submitted'   => 'Submitted',
		'closed'      => 'Closed',
		'open'        => 'Open',
	);

	$class = isset( $classes[ $state ] ) ? $classes[ $state ] : 'sp-badge-muted';
	$label = isset( $labels[ $state ] ) ? $labels[ $state ] : ucfirst( (string) $state );

	return '<span class="sp-badge ' . $class . '">' . esc_html( $label ) . '</span>';
}

/**
 * The question formats the bank accepts.
 *
 * @return string[]
 */
function sp_question_types() {
	return array( 'mcq', 'truefalse', 'fill' );
}

/**
 * Human label for one question type.
 *
 * @param string $type Question type key.
 * @return string
 */
function sp_question_type_label( $type ) {
	$labels = array(
		'mcq'       => 'MCQ',
		'truefalse' => 'True / False',
		'fill'      => 'Fill in the blank',
	);

	return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( (string) $type );
}

/**
 * Per-student attempt statuses.
 *
 * @return string[]
 */
function sp_assignment_attempt_statuses() {
	return array( 'pending', 'in_progress', 'submitted', 'failed' );
}

/* -------------------------------------------------------------------------
 * Assignment CRUD.
 * ---------------------------------------------------------------------- */

/**
 * A single assignment row.
 *
 * @param int $assignment_id Assignment id.
 * @return object|null
 */
function sp_get_assignment( $assignment_id ) {
	global $wpdb;

	$table = sp_table( 'assignments' );

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $assignment_id
		)
	);
}

/**
 * Every assignment of one class, newest first.
 *
 * @param int   $class_id Class id.
 * @param array $args     Optional: status (any single status) or statuses (list).
 * @return object[]
 */
function sp_get_class_assignments( $class_id, $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'status'    => '',
			'statuses'  => array(),
		)
	);

	$table  = sp_table( 'assignments' );
	$where  = 'class_id = %d';
	$params = array( (int) $class_id );

	if ( $args['status'] ) {
		$where   .= ' AND status = %s';
		$params[] = $args['status'];
	} elseif ( $args['statuses'] ) {
		$in      = implode( ',', array_fill( 0, count( $args['statuses'] ), '%s' ) );
		$where   .= " AND status IN ( {$in} )";
		$params  = array_merge( $params, $args['statuses'] );
	}

	$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built above from placeholders.
}

/**
 * Every visible assignment across a student's enrolments, with that student's
 * attempt row joined on.
 *
 * @param int $profile_id Student profile id.
 * @return object[]
 */
function sp_get_assignments_for_profile( $profile_id ) {
	global $wpdb;

	$assign  = sp_table( 'assignments' );
	$classes = sp_table( 'classes' );
	$enroll  = sp_table( 'enrollments' );
	$students = sp_table( 'assignment_students' );

	$sql = "SELECT a.*, o.title AS class_title, o.code AS class_code,
	               s.id AS student_row_id, s.status AS student_status,
	               s.score AS student_score, s.total AS student_total,
	               s.set_id AS student_set_id, s.attempts AS student_attempts,
	               s.started_at AS student_started_at, s.submitted_at AS student_submitted_at,
	               s.retake_granted AS student_retake_granted
	        FROM {$assign} a
	        INNER JOIN {$classes} o ON o.id = a.class_id
	        INNER JOIN {$enroll} e ON e.class_id = a.class_id
	             AND e.profile_id = %d AND e.status IN ( 'active', 'completed' )
	        LEFT JOIN {$students} s ON s.assignment_id = a.id AND s.profile_id = %d
	        WHERE a.status IN ( 'published', 'closed' )
	        GROUP BY a.id
	        ORDER BY a.due_date IS NULL, a.due_date ASC, a.id ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from sp_table().

	return $wpdb->get_results( $wpdb->prepare( $sql, (int) $profile_id, (int) $profile_id ) );
}

/**
 * Create or update an assignment.
 *
 * @param array $args id, class_id, type, title, description, due_date,
 *                    time_limit_minutes, status.
 * @return int|WP_Error Assignment id on success.
 */
function sp_save_assignment( $args ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'id'                 => 0,
			'class_id'           => 0,
			'type'               => 'paper',
			'title'              => '',
			'description'        => '',
			'due_date'           => '',
			'time_limit_minutes' => 0,
			'status'             => '',
		)
	);

	$id        = (int) $args['id'];
	$class_id  = (int) $args['class_id'];
	$type      = in_array( $args['type'], sp_assignment_types(), true ) ? $args['type'] : '';
	$title     = sanitize_text_field( $args['title'] );
	$desc      = sanitize_textarea_field( $args['description'] );
	$due_raw   = sanitize_text_field( $args['due_date'] );
	$limit     = (int) $args['time_limit_minutes'];
	$status    = sanitize_key( $args['status'] );
	$existing  = $id ? sp_get_assignment( $id ) : null;

	if ( '' === $title ) {
		return new WP_Error( 'sp_no_title', 'Enter a title.' );
	}

	if ( '' === $type ) {
		return new WP_Error( 'sp_bad_type', 'Choose whether this is a paper or an exam.' );
	}

	if ( ! sp_get_class( $class_id ) ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	if ( $id && ! $existing ) {
		return new WP_Error( 'sp_no_assignment', 'That assignment no longer exists.' );
	}

	// The type cannot change once the assignment exists: the schema behind it
	// (sets, rows, attachment) is already shaped around it.
	if ( $existing && $existing->type !== $type ) {
		return new WP_Error( 'sp_type_locked', 'The type cannot be changed after creation.' );
	}

	// Normalise the date: papers are due at the end of the day, exams open for
	// the whole day they are scheduled on.
	$due = '';

	if ( '' !== $due_raw ) {
		$ts = strtotime( $due_raw );

		if ( false === $ts ) {
			return new WP_Error( 'sp_bad_date', 'Enter a valid date.' );
		}

		$due = (10 === strlen( $due_raw ))
			? date( 'Y-m-d', $ts ) . ( 'exam' === $type ? ' 00:00:00' : ' 23:59:59' )
			: date( 'Y-m-d H:i:s', $ts );
	}

	if ( 'exam' === $type ) {
		if ( '' === $due ) {
			return new WP_Error( 'sp_no_date', 'Choose the day of the exam.' );
		}

		if ( $limit < 1 || $limit > 600 ) {
			return new WP_Error( 'sp_bad_limit', 'Set a time limit between 1 and 600 minutes.' );
		}
	} else {
		$limit = 0;
	}

	// Nothing structural may change once a student has touched the exam.
	if ( $existing && 'published' === $existing->status ) {
		$taken = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . sp_table( 'assignment_students' ) . ' WHERE assignment_id = %d AND ( started_at IS NOT NULL OR status <> %s )', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
				$id,
				'pending'
			)
		);

		if ( $taken > 0 ) {
			$structural = ( $existing->due_date !== $due ) || ( (int) $existing->time_limit_minutes !== $limit );

			if ( $structural ) {
				return new WP_Error( 'sp_taken', sprintf( 'Already taken or started by %d student — the schedule is locked.', $taken ) );
			}
		}
	}

	$data = array(
		'class_id'           => $class_id,
		'type'               => $type,
		'title'              => $title,
		'description'        => $desc ? $desc : null,
		'due_date'           => '' !== $due ? $due : null,
		'time_limit_minutes' => 'exam' === $type ? $limit : null,
	);

	if ( '' !== $status && in_array( $status, sp_assignment_statuses(), true ) ) {
		$data['status'] = $status;
	}

	if ( $id ) {
		$wpdb->update( sp_table( 'assignments' ), $data, array( 'id' => $id ), array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' ), array( '%d' ) );

		sp_log_activity( 'assignment.updated', 'class', $class_id, $title );

		return $id;
	}

	$data['status']     = isset( $data['status'] ) ? $data['status'] : 'draft';
	$data['created_by'] = get_current_user_id();

	$wpdb->insert( sp_table( 'assignments' ), $data, array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d' ) );

	$id = (int) $wpdb->insert_id;

	sp_log_activity( 'assignment.created', 'class', $class_id, $title );

	return $id;
}

/**
 * Delete an assignment and everything hanging off it.
 *
 * @param int $assignment_id Assignment id.
 * @return true|WP_Error
 */
function sp_delete_assignment( $assignment_id, $force = false ) {
	global $wpdb;

	$assignment = sp_get_assignment( $assignment_id );

	if ( $assignment ) {
		// $force is for sp_delete_class() only: an administrator removing a
		// whole class takes its assignments with them, taken or not.
		if ( ! $force && 'draft' !== $assignment->status ) {
			$started = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . sp_table( 'assignment_students' ) . ' WHERE assignment_id = %d AND started_at IS NOT NULL', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
					(int) $assignment->id
				)
			);

			if ( $started > 0 ) {
				return new WP_Error(
					'sp_taken',
					'published' === $assignment->status
						? 'Close the assignment first — students have already started it.'
						: 'Students have already taken it — it can no longer be deleted.'
				);
			}
		}
	}

	if ( ! $assignment ) {
		return new WP_Error( 'sp_no_assignment', 'That assignment no longer exists.' );
	}

	$student_ids = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT id FROM ' . sp_table( 'assignment_students' ) . ' WHERE assignment_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $assignment->id
		)
	);

	if ( $student_ids ) {
		$in = implode( ',', array_map( 'intval', $student_ids ) );

		$wpdb->query( 'DELETE FROM ' . sp_table( 'assignment_answers' ) . " WHERE student_row_id IN ( {$in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
	}

	$set_ids = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT id FROM ' . sp_table( 'assignment_sets' ) . ' WHERE assignment_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $assignment->id
		)
	);

	if ( $set_ids ) {
		$in = implode( ',', array_map( 'intval', $set_ids ) );

		$wpdb->query( 'DELETE FROM ' . sp_table( 'assignment_set_questions' ) . " WHERE set_id IN ( {$in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
		$wpdb->query( 'DELETE FROM ' . sp_table( 'assignment_sets' ) . " WHERE id IN ( {$in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
	}

	$wpdb->query( 'DELETE FROM ' . sp_table( 'assignment_students' ) . ' WHERE assignment_id = ' . (int) $assignment->id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- intval'd.

	// The uploaded paper goes with it.
	if ( $assignment->attachment_path ) {
		$abs = sp_assignment_file_path( $assignment );

		if ( $abs && file_exists( $abs ) ) {
			@unlink( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort cleanup.
		}
	}

	$wpdb->delete( sp_table( 'assignments' ), array( 'id' => (int) $assignment->id ), array( '%d' ) );

	sp_log_activity( 'assignment.deleted', 'class', (int) $assignment->class_id, $assignment->title );

	return true;
}

/**
 * Absolute path of an assignment's uploaded paper, or false.
 *
 * @param object $assignment Assignment row.
 * @return string|false
 */
function sp_assignment_file_path( $assignment ) {
	if ( empty( $assignment->attachment_path ) ) {
		return false;
	}

	$relative = ltrim( (string) $assignment->attachment_path, '/\\' );

	// Never allow the stored value to climb out of the uploads tree.
	if ( false !== strpos( $relative, '..' ) ) {
		return false;
	}

	return WP_CONTENT_DIR . '/uploads/' . str_replace( array( '..', '\\' ), '', $relative );
}

/**
 * Gated download URL for an assignment's paper.
 *
 * @param int $assignment_id Assignment id.
 * @return string
 */
function sp_assignment_file_url( $assignment_id ) {
	return add_query_arg( 'sp_assignment_file', (int) $assignment_id, home_url( '/' ) );
}

/* -------------------------------------------------------------------------
 * The per-class question bank.
 * ---------------------------------------------------------------------- */

/**
 * Every question in a class's bank, oldest first.
 *
 * @param int $class_id Class id.
 * @return object[]
 */
function sp_get_class_questions( $class_id ) {
	global $wpdb;

	$table = sp_table( 'questions' );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE class_id = %d ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $class_id
		)
	);
}

/**
 * One question.
 *
 * @param int $question_id Question id.
 * @return object|null
 */
function sp_get_question( $question_id ) {
	global $wpdb;

	$table = sp_table( 'questions' );

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $question_id
		)
	);
}

/**
 * Create or update a question, normalising its answer to a canonical form.
 *
 * @param array $args id, class_id, type, prompt, options (array), answer.
 * @return int|WP_Error Question id on success.
 */
function sp_save_question( $args ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'id'       => 0,
			'class_id' => 0,
			'type'     => '',
			'prompt'   => '',
			'options'  => array(),
			'answer'   => '',
		)
	);

	$id       = (int) $args['id'];
	$class_id = (int) $args['class_id'];
	$type     = in_array( $args['type'], sp_question_types(), true ) ? $args['type'] : '';
	$prompt   = trim( (string) $args['prompt'] );
	$answer   = trim( (string) $args['answer'] );

	if ( '' === $type ) {
		return new WP_Error( 'sp_bad_qtype', 'Choose MCQ, True/False or Fill in the blank.' );
	}

	if ( '' === $prompt ) {
		return new WP_Error( 'sp_no_prompt', 'Enter the question.' );
	}

	if ( '' === $answer ) {
		return new WP_Error( 'sp_no_answer', 'Enter the correct answer.' );
	}

	if ( ! sp_get_class( $class_id ) ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	$options_json = null;

	if ( 'mcq' === $type ) {
		$options = array_values(
			array_filter(
				array_map(
					function ( $o ) {
						return trim( (string) $o );
					},
					(array) $args['options']
				)
			)
		);

		if ( count( $options ) < 2 ) {
			return new WP_Error( 'sp_few_options', 'An MCQ needs at least two options.' );
		}

		if ( count( $options ) > 6 ) {
			return new WP_Error( 'sp_many_options', 'An MCQ can have at most six options.' );
		}

		// The stored answer is a letter: accept A-D (or the option's own text).
		$letter = strtoupper( substr( $answer, 0, 1 ) );

		if ( preg_match( '/^[A-F]$/', $letter ) && ( ord( $letter ) - 64 ) <= count( $options ) ) {
			$answer = $letter;
		} else {
			$found = '';

			foreach ( $options as $i => $option ) {
				if ( 0 === strcasecmp( $option, $answer ) ) {
					$found = chr( 65 + $i );
					break;
				}
			}

			if ( '' === $found ) {
				return new WP_Error( 'sp_bad_answer', 'The answer must be a letter matching one of the options.' );
			}

			$answer = $found;
		}

		$options_json = wp_json_encode( $options );
	} elseif ( 'truefalse' === $type ) {
		$norm = strtoupper( $answer );

		if ( in_array( $norm, array( 'T', '1', 'YES', 'Y' ), true ) ) {
			$answer = 'TRUE';
		} elseif ( in_array( $norm, array( 'F', '0', 'NO', 'N' ), true ) ) {
			$answer = 'FALSE';
		} elseif ( in_array( $norm, array( 'TRUE', 'FALSE' ), true ) ) {
			$answer = $norm;
		} else {
			return new WP_Error( 'sp_bad_answer', 'The answer must be TRUE or FALSE.' );
		}
	} else {
		// Fill in the blank: keep the alternatives (a | b) exactly as typed;
		// comparison happens case-insensitively at grading time.
		$answer = mb_substr( $answer, 0, 250 );
	}

	$data = array(
		'class_id' => $class_id,
		'type'     => $type,
		'prompt'   => $prompt,
		'options'  => $options_json,
		'answer'   => $answer,
	);

	if ( $id ) {
		if ( ! sp_get_question( $id ) ) {
			return new WP_Error( 'sp_no_question', 'That question no longer exists.' );
		}

		$wpdb->update( sp_table( 'questions' ), $data, array( 'id' => $id ), array( '%d', '%s', '%s', '%s', '%s' ), array( '%d' ) );

		return $id;
	}

	$wpdb->insert( sp_table( 'questions' ), $data, array( '%d', '%s', '%s', '%s', '%s' ) );

	return (int) $wpdb->insert_id;
}

/**
 * Delete a question, including every set that still references it.
 *
 * @param int $question_id Question id.
 * @return true|WP_Error
 */
function sp_delete_question( $question_id ) {
	global $wpdb;

	$question = sp_get_question( $question_id );

	if ( ! $question ) {
		return new WP_Error( 'sp_no_question', 'That question no longer exists.' );
	}

	$wpdb->delete( sp_table( 'assignment_set_questions' ), array( 'question_id' => (int) $question_id ), array( '%d' ) );
	$wpdb->delete( sp_table( 'questions' ), array( 'id' => (int) $question_id ), array( '%d' ) );

	return true;
}

/**
 * Import questions from CSV into a class's bank.
 *
 * Columns: type, prompt, option_a..option_d, answer — a header row is
 * optional. Malformed lines are collected as errors instead of aborting.
 *
 * @param int    $class_id Class id.
 * @param string $csv      Raw CSV text.
 * @return array {imported:int, errors:string[]}
 */
function sp_import_questions_csv( $class_id, $csv ) {
	$result = array(
		'imported' => 0,
		'errors'   => array(),
	);

	$csv = trim( (string) $csv );

	if ( '' === $csv ) {
		$result['errors'][] = 'The file was empty.';

		return $result;
	}

	$lines = preg_split( '/\r\n|\r|\n/', $csv );
	$first = true;

	foreach ( $lines as $i => $line ) {
		$line_no = $i + 1;

		if ( '' === trim( $line ) ) {
			continue;
		}

		$cells = array_map( 'trim', str_getcsv( $line ) );

		// A header row names the columns; recognise it and skip.
		if ( $first ) {
			$first = false;
			$lower = array_map( 'strtolower', $cells );

			if ( in_array( 'type', $lower, true ) && in_array( 'prompt', $lower, true ) ) {
				continue;
			}
		}

		$type    = isset( $cells[0] ) ? strtolower( $cells[0] ) : '';
		$prompt  = isset( $cells[1] ) ? $cells[1] : '';
		$opt_a   = isset( $cells[2] ) ? $cells[2] : '';
		$opt_b   = isset( $cells[3] ) ? $cells[3] : '';
		$opt_c   = isset( $cells[4] ) ? $cells[4] : '';
		$opt_d   = isset( $cells[5] ) ? $cells[5] : '';
		$answer  = isset( $cells[6] ) ? $cells[6] : '';

		// Tolerate the friendly spellings a spreadsheet user would type.
		$aliases = array(
			'mcq'          => 'mcq',
			'multiple'     => 'mcq',
			'multiplechoice' => 'mcq',
			'true'         => 'truefalse',
			'truefalse'    => 'truefalse',
			'true/false'   => 'truefalse',
			'true - false' => 'truefalse',
			'boolean'      => 'truefalse',
			'fill'         => 'fill',
			'blank'        => 'fill',
			'fillin'       => 'fill',
			'fill in the blank' => 'fill',
			'fill-in-the-blank' => 'fill',
		);

		$type = isset( $aliases[ $type ] ) ? $aliases[ $type ] : $type;

		$saved = sp_save_question(
			array(
				'class_id' => $class_id,
				'type'     => $type,
				'prompt'   => $prompt,
				'options'  => array_filter( array( $opt_a, $opt_b, $opt_c, $opt_d ) ),
				'answer'   => $answer,
			)
		);

		if ( is_wp_error( $saved ) ) {
			$result['errors'][] = sprintf( 'Line %d: %s', $line_no, $saved->get_error_message() );
		} else {
			++$result['imported'];
		}
	}

	return $result;
}

/* -------------------------------------------------------------------------
 * Exam sets.
 * ---------------------------------------------------------------------- */

/**
 * The sets of one exam, in order.
 *
 * @param int $assignment_id Assignment id.
 * @return object[]
 */
function sp_get_assignment_sets( $assignment_id ) {
	global $wpdb;

	$table = sp_table( 'assignment_sets' );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE assignment_id = %d ORDER BY position ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $assignment_id
		)
	);
}

/**
 * The questions of one set, in order.
 *
 * @param int $set_id Set id.
 * @return object[]
 */
function sp_get_set_questions( $set_id ) {
	global $wpdb;

	$pivot = sp_table( 'assignment_set_questions' );
	$quest = sp_table( 'questions' );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT q.* FROM {$pivot} sq INNER JOIN {$quest} q ON q.id = sq.question_id
			 WHERE sq.set_id = %d ORDER BY sq.position ASC, sq.id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from sp_table().
			(int) $set_id
		)
	);
}

/**
 * The next free single-letter set label (A, B, C ...) for a set list.
 *
 * Used when "Add another set" appends a set: the label must not clash with
 * any set already on the form, whatever the teacher has renamed them to.
 *
 * @param array $sets List of arrays carrying a 'label' key.
 * @return string A label no set in the list uses.
 */
function sp_next_set_label( $sets ) {
	$sets = is_array( $sets ) ? $sets : array();
	$used = array();

	foreach ( $sets as $set ) {
		$label = is_array( $set ) && isset( $set['label'] ) ? (string) $set['label'] : '';
		$used[ strtolower( $label ) ] = true;
	}

	for ( $i = 0; $i < 26; $i++ ) {
		$try = chr( 65 + $i );

		if ( ! isset( $used[ strtolower( $try ) ] ) ) {
			return $try;
		}
	}

	$n = count( $sets ) + 1;

	while ( isset( $used[ strtolower( 'set ' . $n ) ] ) ) {
		++$n;
	}

	return 'Set ' . $n;
}

/**
 * Whether any student has started this assignment.
 *
 * The sets may be edited until the first attempt begins; the save path and
 * the sets editor both gate on this, so the rules can never drift apart.
 *
 * @param int $assignment_id Assignment id.
 * @return bool True once at least one attempt has started.
 */
function sp_assignment_started( $assignment_id ) {
	global $wpdb;

	$started = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . sp_table( 'assignment_students' ) . ' WHERE assignment_id = %d AND started_at IS NOT NULL', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $assignment_id
		)
	);

	return $started > 0;
}

/**
 * Synchronise an exam's sets and their questions from the editor form.
 *
 * @param int   $assignment_id Assignment id.
 * @param array $sets          List of {label, question_ids:[]} in order.
 * @return true|WP_Error
 */
function sp_save_assignment_sets( $assignment_id, $sets ) {
	global $wpdb;

	$assignment = sp_get_assignment( $assignment_id );

	if ( ! $assignment ) {
		return new WP_Error( 'sp_no_assignment', 'That assignment no longer exists.' );
	}

	if ( 'exam' !== $assignment->type ) {
		return new WP_Error( 'sp_not_exam', 'Only exams have sets.' );
	}

	if ( ! is_array( $sets ) ) {
		$sets = array();
	}

	$seen   = array();
	$clean  = array();

	foreach ( $sets as $i => $set ) {
		$label = sanitize_text_field( isset( $set['label'] ) ? $set['label'] : '' );
		$label = mb_substr( $label ? $label : chr( 65 + $i ), 0, 50 );

		if ( '' === $label || isset( $seen[ strtolower( $label ) ] ) ) {
			return new WP_Error( 'sp_bad_label', 'Set names must be unique and cannot be empty.' );
		}

		$seen[ strtolower( $label ) ] = true;

		$question_ids = array_map( 'intval', (array) isset( $set['question_ids'] ) ? $set['question_ids'] : array() );
		$question_ids = array_values( array_unique( array_filter( $question_ids ) ) );

		// Every chosen question must belong to this class's bank.
		foreach ( $question_ids as $qid ) {
			$question = sp_get_question( $qid );

			if ( ! $question || (int) $question->class_id !== (int) $assignment->class_id ) {
				return new WP_Error( 'sp_bad_question', 'A chosen question does not belong to this class.' );
			}
		}

		$clean[] = array(
			'label'       => $label,
			'question_ids' => $question_ids,
		);
	}

	// No structural change once a student has started.
	if ( 'published' === $assignment->status ) {
		if ( sp_assignment_started( $assignment_id ) ) {
			return new WP_Error( 'sp_taken', 'Students have already started — the sets are locked.' );
		}

		if ( ! $clean ) {
			return new WP_Error( 'sp_sets_empty', 'A published exam keeps at least one set.' );
		}
	}

	$existing = sp_get_assignment_sets( $assignment_id );
	$keep     = array();

	foreach ( $clean as $i => $set ) {
		$position = $i + 1;

		if ( isset( $existing[ $i ] ) && strtolower( $existing[ $i ]->label ) === strtolower( $set['label'] ) ) {
			$set_id = (int) $existing[ $i ]->id;
		} else {
			// Match by label anywhere in the old list so renames within the
			// same slot keep their id.
			$set_id = 0;

			foreach ( $existing as $old ) {
				if ( strtolower( $old->label ) === strtolower( $set['label'] ) ) {
					$set_id = (int) $old->id;
					break;
				}
			}
		}

		if ( ! $set_id ) {
			$wpdb->insert(
				sp_table( 'assignment_sets' ),
				array(
					'assignment_id' => (int) $assignment_id,
					'label'         => $set['label'],
					'position'      => $position,
				),
				array( '%d', '%s', '%d' )
			);
			$set_id = (int) $wpdb->insert_id;
		} else {
			$wpdb->update(
				sp_table( 'assignment_sets' ),
				array( 'position' => $position ),
				array( 'id' => $set_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		$keep[] = $set_id;

		// Replace the set's questions wholesale: cheap and always canonical.
		$wpdb->delete( sp_table( 'assignment_set_questions' ), array( 'set_id' => $set_id ), array( '%d' ) );

		foreach ( $set['question_ids'] as $j => $qid ) {
			$wpdb->insert(
				sp_table( 'assignment_set_questions' ),
				array(
					'set_id'      => $set_id,
					'question_id' => $qid,
					'position'    => $j + 1,
				),
				array( '%d', '%d', '%d' )
			);
		}
	}

	// Drop the sets that disappeared from the form.
	foreach ( $existing as $old ) {
		if ( ! in_array( (int) $old->id, $keep, true ) ) {
			$wpdb->delete( sp_table( 'assignment_set_questions' ), array( 'set_id' => (int) $old->id ), array( '%d' ) );
			$wpdb->delete( sp_table( 'assignment_sets' ), array( 'id' => (int) $old->id ), array( '%d' ) );
		}
	}

	// A dealt row must never point at a set that no longer exists: any
	// student whose set was removed simply moves to the first surviving set.
	if ( $keep ) {
		$kept_in  = implode( ',', array_map( 'intval', $keep ) );
		$sp_first = (int) $keep[0];
		$sp_dangling = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id FROM ' . sp_table( 'assignment_students' ) . " WHERE assignment_id = %d AND set_id IS NOT NULL AND set_id NOT IN ( {$kept_in} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table(), ids from intval.
				(int) $assignment_id
			)
		);

		foreach ( $sp_dangling as $sp_drow ) {
			$wpdb->update(
				sp_table( 'assignment_students' ),
				array( 'set_id' => $sp_first ),
				array( 'id' => (int) $sp_drow->id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	sp_log_activity( 'assignment.sets_saved', 'class', (int) $assignment->class_id, $assignment->title );

	return true;
}

/* -------------------------------------------------------------------------
 * Publishing and distribution.
 * ---------------------------------------------------------------------- */

/**
 * Publish an exam: validate it, then deal a set to every enrolled student.
 *
 * @param int   $assignment_id Assignment id.
 * @param array $args mode ('single'|'random'|'manual'), set_id for single,
 *                    map (profile_id => set_id) for manual.
 * @return int|WP_Error The number of students dealt, or an error.
 */
function sp_publish_assignment( $assignment_id, $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'mode'   => 'single',
			'set_id' => 0,
			'map'    => array(),
		)
	);

	$assignment = sp_get_assignment( $assignment_id );

	if ( ! $assignment ) {
		return new WP_Error( 'sp_no_assignment', 'That assignment no longer exists.' );
	}

	if ( 'published' === $assignment->status ) {
		return new WP_Error( 'sp_already_published', 'That assignment is already published.' );
	}

	if ( 'closed' === $assignment->status ) {
		return new WP_Error( 'sp_closed', 'That assignment is closed.' );
	}

	if ( 'paper' === $assignment->type ) {
		$wpdb->update( sp_table( 'assignments' ), array( 'status' => 'published' ), array( 'id' => (int) $assignment_id ), array( '%s' ), array( '%d' ) );

		sp_log_activity( 'assignment.published', 'class', (int) $assignment->class_id, $assignment->title );

		return 0;
	}

	$sets = sp_get_assignment_sets( $assignment_id );

	if ( ! $sets ) {
		return new WP_Error( 'sp_no_sets', 'Create at least one question set before publishing.' );
	}

	foreach ( $sets as $set ) {
		if ( ! sp_get_set_questions( (int) $set->id ) ) {
			return new WP_Error( 'sp_empty_set', sprintf( 'Set %s has no questions.', $set->label ) );
		}
	}

	// Every active enrolment gets a row.
	$enrollments = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT profile_id FROM ' . sp_table( 'enrollments' ) . " WHERE class_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $assignment->class_id
		)
	);

	if ( ! $enrollments ) {
		return new WP_Error( 'sp_no_students', 'No active students are enrolled in that class.' );
	}

	$profiles = array_map(
		function ( $e ) {
			return (int) $e->profile_id;
		},
		$enrollments
	);

	$mode = in_array( $args['mode'], array( 'single', 'random', 'manual' ), true ) ? $args['mode'] : 'single';
	$deal = array();

	if ( 'single' === $mode ) {
		$set_id = (int) $args['set_id'] ? (int) $args['set_id'] : (int) $sets[0]->id;

		foreach ( $profiles as $pid ) {
			$deal[ $pid ] = $set_id;
		}
	} elseif ( 'random' === $mode ) {
		// Deal round-robin from a shuffled deck so the sets stay balanced.
		$ids = array_map(
			function ( $s ) {
				return (int) $s->id;
			},
			$sets
		);
		shuffle( $ids );

		foreach ( $profiles as $i => $pid ) {
			$deal[ $pid ] = $ids[ $i % count( $ids ) ];
		}
	} else {
		$map = array();

		foreach ( (array) $args['map'] as $pid => $sid ) {
			$map[ (int) $pid ] = (int) $sid;
		}

		$valid = array();

		foreach ( $sets as $set ) {
			$valid[] = (int) $set->id;
		}

		foreach ( $profiles as $pid ) {
			if ( ! isset( $map[ $pid ] ) || ! in_array( $map[ $pid ], $valid, true ) ) {
				$missing = sp_get_profile_by_id( $pid );

				return new WP_Error(
					'sp_missing_set',
					sprintf( 'Choose a set for every student — %s is missing one.', $missing ? $missing->full_name : 'student #' . $pid )
				);
			}

			$deal[ $pid ] = $map[ $pid ];
		}
	}

	foreach ( $deal as $pid => $sid ) {
		$wpdb->insert(
			sp_table( 'assignment_students' ),
			array(
				'assignment_id' => (int) $assignment_id,
				'profile_id'    => $pid,
				'set_id'        => $sid,
				'status'        => 'pending',
			),
			array( '%d', '%d', '%d', '%s' )
		);
	}

	$wpdb->update( sp_table( 'assignments' ), array( 'status' => 'published' ), array( 'id' => (int) $assignment_id ), array( '%s' ), array( '%d' ) );

	sp_log_activity( 'assignment.published', 'class', (int) $assignment->class_id, $assignment->title . ' (' . count( $deal ) . ' students, ' . $mode . ')' );

	return count( $deal );
}

/**
 * Close a published assignment: no new attempts may start.
 *
 * @param int $assignment_id Assignment id.
 * @return true|WP_Error
 */
function sp_close_assignment( $assignment_id ) {
	global $wpdb;

	$assignment = sp_get_assignment( $assignment_id );

	if ( ! $assignment ) {
		return new WP_Error( 'sp_no_assignment', 'That assignment no longer exists.' );
	}

	if ( 'published' !== $assignment->status ) {
		return new WP_Error( 'sp_not_published', 'Only a published assignment can be closed.' );
	}

	$wpdb->update( sp_table( 'assignments' ), array( 'status' => 'closed' ), array( 'id' => (int) $assignment_id ), array( '%s' ), array( '%d' ) );

	sp_log_activity( 'assignment.closed', 'class', (int) $assignment->class_id, $assignment->title );

	return true;
}

/**
 * Make sure every active enrolment has an attempt row (students who joined
 * after publishing, or a paper-free exam that was re-published).
 *
 * @param int $assignment_id Assignment id.
 * @return int Rows created.
 */
function sp_ensure_assignment_rows( $assignment_id ) {
	global $wpdb;

	$assignment = sp_get_assignment( $assignment_id );

	if ( ! $assignment || 'exam' !== $assignment->type || 'published' !== $assignment->status ) {
		return 0;
	}

	$profiles = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT profile_id FROM ' . sp_table( 'enrollments' ) . " WHERE class_id = %d AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $assignment->class_id
		)
	);

	if ( ! $profiles ) {
		return 0;
	}

	$have = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT profile_id FROM ' . sp_table( 'assignment_students' ) . ' WHERE assignment_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $assignment_id
		)
	);
	$have = array_map( 'intval', $have );

	$sets  = sp_get_assignment_sets( $assignment_id );
	$default = $sets ? (int) $sets[0]->id : 0;
	$created = 0;

	foreach ( $profiles as $pid ) {
		if ( in_array( (int) $pid, $have, true ) ) {
			continue;
		}

		$wpdb->insert(
			sp_table( 'assignment_students' ),
			array(
				'assignment_id' => (int) $assignment_id,
				'profile_id'    => (int) $pid,
				'set_id'        => $default,
				'status'        => 'pending',
			),
			array( '%d', '%d', '%d', '%s' )
		);
		++$created;
	}

	return $created;
}

/* -------------------------------------------------------------------------
 * Attempts: state, start, submit, fail, retake.
 * ---------------------------------------------------------------------- */

/**
 * A student's attempt row for one assignment.
 *
 * @param int $assignment_id Assignment id.
 * @param int $profile_id    Student profile id.
 * @return object|null
 */
function sp_get_student_assignment( $assignment_id, $profile_id ) {
	global $wpdb;

	$table = sp_table( 'assignment_students' );

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE assignment_id = %d AND profile_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $assignment_id,
			(int) $profile_id
		)
	);
}

/**
 * The attempt row by its own id (teacher-side lookups).
 *
 * @param int $student_row_id Attempt row id.
 * @return object|null
 */
function sp_get_assignment_attempt( $student_row_id ) {
	global $wpdb;

	$table = sp_table( 'assignment_students' );

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $student_row_id
		)
	);
}

/**
 * Seconds left on a running attempt, 0 once expired.
 *
 * @param object $row        Attempt row.
 * @param object $assignment Assignment row.
 * @return int
 */
function sp_assignment_time_left( $row, $assignment ) {
	if ( ! $row || ! $row->started_at || ! $assignment->time_limit_minutes ) {
		return 0;
	}

	$deadline = strtotime( $row->started_at ) + ( (int) $assignment->time_limit_minutes * 60 );

	return max( 0, $deadline - time() );
}

/**
 * Can this student start (or resume) the exam today?
 *
 * An exam is available on its scheduled day; a granted retake is available
 * any time until the assignment is closed.
 *
 * @param object      $assignment Assignment row.
 * @param object|null $row        Attempt row, if any.
 * @return bool
 */
function sp_assignment_open_today( $assignment, $row = null ) {
	if ( ! $assignment || 'published' !== $assignment->status || 'exam' !== $assignment->type ) {
		return false;
	}

	if ( $row && $row->retake_granted ) {
		return true;
	}

	if ( ! $assignment->due_date ) {
		return false;
	}

	return current_time( 'Y-m-d' ) === substr( $assignment->due_date, 0, 10 );
}

/**
 * The feed-state of one assignment for one student.
 *
 * Returns: unavailable | upcoming | available | missed | in_progress |
 * failed | submitted | closed | open (papers).
 *
 * @param object      $assignment Assignment row (with student_* joined or not).
 * @param object|null $row        Attempt row.
 * @return string
 */
function sp_assignment_student_state( $assignment, $row = null ) {
	if ( 'closed' === $assignment->status ) {
		if ( $row && 'submitted' === $row->status ) {
			return 'submitted';
		}

		if ( $row && 'in_progress' === $row->status ) {
			return 'failed';
		}

		return 'closed';
	}

	if ( 'draft' === $assignment->status ) {
		return 'unavailable';
	}

	if ( 'paper' === $assignment->type ) {
		return 'open';
	}

	if ( ! $row ) {
		return 'unavailable';
	}

	if ( 'submitted' === $row->status ) {
		return 'submitted';
	}

	if ( 'failed' === $row->status ) {
		return 'failed';
	}

	if ( 'in_progress' === $row->status ) {
		return 'in_progress';
	}

	// pending
	if ( $row->retake_granted ) {
		return 'available';
	}

	if ( ! $assignment->due_date ) {
		return 'unavailable';
	}

	$day = substr( $assignment->due_date, 0, 10 );
	$now = current_time( 'Y-m-d' );

	if ( $now === $day ) {
		return 'available';
	}

	return $now < $day ? 'upcoming' : 'missed';
}

/**
 * Start (or resume) an exam attempt.
 *
 * @param int $assignment_id Assignment id.
 * @param int $profile_id    Student profile id.
 * @return object|WP_Error The up-to-date attempt row.
 */
function sp_start_assignment_attempt( $assignment_id, $profile_id ) {
	global $wpdb;

	$assignment = sp_get_assignment( $assignment_id );

	if ( ! $assignment || 'exam' !== $assignment->type ) {
		return new WP_Error( 'sp_no_assignment', 'That exam no longer exists.' );
	}

	if ( 'closed' === $assignment->status ) {
		return new WP_Error( 'sp_exam_closed', 'This exam is closed.' );
	}

	if ( 'draft' === $assignment->status ) {
		return new WP_Error( 'sp_not_published', 'This exam has not been published yet.' );
	}

	sp_ensure_assignment_rows( $assignment_id );

	$row = sp_get_student_assignment( $assignment_id, $profile_id );

	if ( ! $row ) {
		return new WP_Error( 'sp_not_assigned', 'You are not on the list for this exam.' );
	}

	if ( 'submitted' === $row->status ) {
		return new WP_Error( 'sp_exam_done', 'You have already submitted this exam.' );
	}

	if ( 'failed' === $row->status ) {
		return new WP_Error( 'sp_exam_failed', 'This attempt was marked failed. Your teacher can allow you to retake it.' );
	}

	// Resume a running attempt as long as the clock has not run out.
	if ( 'in_progress' === $row->status ) {
		if ( sp_assignment_time_left( $row, $assignment ) > 0 ) {
			return $row;
		}

		// Expired without a submit: close it out with nothing answered.
		return sp_submit_assignment_attempt( $assignment_id, $profile_id, array() );
	}

	if ( ! sp_assignment_open_today( $assignment, $row ) ) {
		return new WP_Error( 'sp_not_open', 'This exam is not open today. Ask your teacher if you missed it.' );
	}

	// A set with questions is required to sit the paper.
	if ( ! $row->set_id ) {
		$sets = sp_get_assignment_sets( $assignment_id );

		if ( ! $sets ) {
			return new WP_Error( 'sp_no_sets', 'This exam has no question set yet.' );
		}

		$set_id = (int) $sets[0]->id;

		$wpdb->update( sp_table( 'assignment_students' ), array( 'set_id' => $set_id ), array( 'id' => (int) $row->id ), array( '%d' ), array( '%d' ) );
	} else {
		$set_id = (int) $row->set_id;
	}

	if ( ! sp_get_set_questions( $set_id ) ) {
		return new WP_Error( 'sp_empty_set', 'Your question set is empty — ask your teacher.' );
	}

	$now = current_time( 'mysql' );

	$wpdb->update(
		sp_table( 'assignment_students' ),
		array(
			'status'     => 'in_progress',
			'started_at' => $now,
			'attempts'   => (int) $row->attempts + 1,
		),
		array( 'id' => (int) $row->id ),
		array( '%s', '%s', '%d' ),
		array( '%d' )
	);

	sp_log_activity( 'assignment.started', 'class', (int) $assignment->class_id, $assignment->title );

	return sp_get_student_assignment( $assignment_id, $profile_id );
}

/**
 * Grade one answer against one question.
 *
 * @param object $question Question row.
 * @param string $given    The student's answer.
 * @return bool
 */
function sp_grade_answer( $question, $given ) {
	$given  = trim( (string) $given );
	$answer = trim( (string) $question->answer );

	if ( '' === $given ) {
		return false;
	}

	if ( 'mcq' === $question->type ) {
		return 0 === strcasecmp( $given, $answer );
	}

	if ( 'truefalse' === $question->type ) {
		$norm = strtoupper( $given );
		$norm = in_array( $norm, array( 'T', '1', 'YES', 'Y' ), true ) ? 'TRUE' : ( in_array( $norm, array( 'F', '0', 'NO', 'N' ), true ) ? 'FALSE' : $norm );

		return $norm === $answer;
	}

	// Fill in the blank: case-insensitive, any `|`-separated alternative.
	$given_l = mb_strtolower( $given );

	foreach ( explode( '|', $answer ) as $alternative ) {
		if ( mb_strtolower( trim( $alternative ) ) === $given_l ) {
			return true;
		}
	}

	return false;
}

/**
 * Submit and instantly grade an in-progress attempt.
 *
 * @param int   $assignment_id Assignment id.
 * @param int   $profile_id    Student profile id.
 * @param array $answers       question_id => given answer.
 * @return object|WP_Error The graded attempt row.
 */
function sp_submit_assignment_attempt( $assignment_id, $profile_id, $answers ) {
	global $wpdb;

	$assignment = sp_get_assignment( $assignment_id );
	$row        = $assignment ? sp_get_student_assignment( $assignment_id, $profile_id ) : null;

	if ( ! $assignment || ! $row ) {
		return new WP_Error( 'sp_no_assignment', 'That exam no longer exists.' );
	}

	if ( 'in_progress' !== $row->status ) {
		return new WP_Error( 'sp_not_started', 'That exam is not running.' );
	}

	$questions = $row->set_id ? sp_get_set_questions( (int) $row->set_id ) : array();

	if ( ! $questions ) {
		return new WP_Error( 'sp_empty_set', 'Your question set is empty.' );
	}

	$answers = (array) $answers;
	$score   = 0;
	$total   = count( $questions );

	// Fresh attempt: sweep any answers left from a previous retake.
	$wpdb->delete( sp_table( 'assignment_answers' ), array( 'student_row_id' => (int) $row->id ), array( '%d' ) );

	foreach ( $questions as $question ) {
		$given = isset( $answers[ (int) $question->id ] ) ? sanitize_text_field( wp_unslash( $answers[ (int) $question->id ] ) ) : '';
		$correct = sp_grade_answer( $question, $given );

		if ( $correct ) {
			++$score;
		}

		$wpdb->insert(
			sp_table( 'assignment_answers' ),
			array(
				'student_row_id' => (int) $row->id,
				'question_id'    => (int) $question->id,
				'given'          => $given,
				'correct'        => $correct ? 1 : 0,
			),
			array( '%d', '%d', '%s', '%d' )
		);
	}

	$wpdb->update(
		sp_table( 'assignment_students' ),
		array(
			'status'       => 'submitted',
			'score'        => $score,
			'total'        => $total,
			'submitted_at' => current_time( 'mysql' ),
		),
		array( 'id' => (int) $row->id ),
		array( '%s', '%d', '%d', '%s' ),
		array( '%d' )
	);

	sp_log_activity( 'assignment.submitted', 'class', (int) $assignment->class_id, $assignment->title . sprintf( ' — %d/%d', $score, $total ) );

	return sp_get_student_assignment( $assignment_id, $profile_id );
}

/**
 * Mark a running attempt failed — the student left midway.
 *
 * @param int $assignment_id Assignment id.
 * @param int $profile_id    Student profile id.
 * @return object|WP_Error
 */
function sp_fail_assignment_attempt( $assignment_id, $profile_id ) {
	global $wpdb;

	$row = sp_get_student_assignment( $assignment_id, $profile_id );

	if ( ! $row ) {
		return new WP_Error( 'sp_not_assigned', 'You are not on the list for that exam.' );
	}

	if ( 'in_progress' !== $row->status ) {
		// Already finished or failed: leave it exactly as it is.
		return $row;
	}

	$wpdb->update(
		sp_table( 'assignment_students' ),
		array( 'status' => 'failed' ),
		array( 'id' => (int) $row->id ),
		array( '%s' ),
		array( '%d' )
	);

	sp_log_activity( 'assignment.failed', 'class', (int) $row->assignment_id, 'Student left midway' );

	return sp_get_student_assignment( $assignment_id, $profile_id );
}

/**
 * The teacher grants a retake: the attempt resets to pending and the exam
 * day restriction is lifted for this student.
 *
 * @param int $student_row_id Attempt row id.
 * @return true|WP_Error
 */
function sp_allow_assignment_retake( $student_row_id ) {
	global $wpdb;

	$row = sp_get_assignment_attempt( $student_row_id );

	if ( ! $row ) {
		return new WP_Error( 'sp_no_attempt', 'That attempt no longer exists.' );
	}

	if ( 'in_progress' === $row->status ) {
		return new WP_Error( 'sp_in_progress', 'That student is still sitting the exam.' );
	}

	$assignment = sp_get_assignment( (int) $row->assignment_id );

	$wpdb->delete( sp_table( 'assignment_answers' ), array( 'student_row_id' => (int) $row->id ), array( '%d' ) );

	$wpdb->update(
		sp_table( 'assignment_students' ),
		array(
			'status'         => 'pending',
			'score'          => null,
			'total'          => null,
			'started_at'     => null,
			'submitted_at'   => null,
			'retake_granted' => 1,
		),
		array( 'id' => (int) $row->id ),
		array( '%s', '%d', '%d', '%s', '%s', '%d' ),
		array( '%d' )
	);

	sp_log_activity( 'assignment.retake_granted', 'class', $assignment ? (int) $assignment->class_id : 0, $assignment ? $assignment->title : '' );

	return true;
}

/**
 * A student's answers for a finished attempt, keyed by question id.
 *
 * @param int $student_row_id Attempt row id.
 * @return array<int, object>
 */
function sp_get_attempt_answers( $student_row_id ) {
	global $wpdb;

	$table = sp_table( 'assignment_answers' );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE student_row_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $student_row_id
		)
	);

	$map = array();

	foreach ( $rows as $row ) {
		$map[ (int) $row->question_id ] = $row;
	}

	return $map;
}

/**
 * Publish-time counts for the teacher's status table.
 *
 * @param int $assignment_id Assignment id.
 * @return array {total:int, submitted:int, failed:int, in_progress:int, pending:int}
 */
function sp_assignment_progress( $assignment_id ) {
	global $wpdb;

	$table  = sp_table( 'assignment_students' );
	$counts = array(
		'total'       => 0,
		'submitted'   => 0,
		'failed'      => 0,
		'in_progress' => 0,
		'pending'     => 0,
	);

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT status, COUNT(*) AS n FROM {$table} WHERE assignment_id = %d GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $assignment_id
		)
	);

	foreach ( $rows as $row ) {
		$counts['total'] += (int) $row->n;

		if ( isset( $counts[ $row->status ] ) ) {
			$counts[ $row->status ] = (int) $row->n;
		}
	}

	return $counts;
}

/**
 * The student attempt rows of an assignment with names attached, for the
 * teacher's status table.
 *
 * @param int $assignment_id Assignment id.
 * @return object[]
 */
function sp_get_assignment_attempts( $assignment_id ) {
	global $wpdb;

	$students = sp_table( 'assignment_students' );
	$profiles = sp_table( 'student_profiles' );
	$sets     = sp_table( 'assignment_sets' );

	$sql = "SELECT s.*, p.full_name, p.email, sl.label AS set_label
	        FROM {$students} s
	        INNER JOIN {$profiles} p ON p.id = s.profile_id
	        LEFT JOIN {$sets} sl ON sl.id = s.set_id
	        WHERE s.assignment_id = %d
	        ORDER BY p.full_name ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from sp_table().

	return $wpdb->get_results( $wpdb->prepare( $sql, (int) $assignment_id ) );
}

/* -------------------------------------------------------------------------
 * The gated paper download.
 * ---------------------------------------------------------------------- */

/**
 * Whether the given user may read an assignment (and its attached paper).
 *
 * @param object  $assignment Assignments row.
 * @param WP_User $user       Viewer.
 * @return bool
 */
function sp_user_can_view_assignment( $assignment, $user = null ) {
	if ( ! $assignment ) {
		return false;
	}

	if ( ! $user instanceof WP_User ) {
		$user = wp_get_current_user();
	}

	if ( ! $user->exists() ) {
		return false;
	}

	if ( user_can( $user, 'manage_certificates' ) ) {
		return true;
	}

	$teacher = sp_get_teacher_by_user_id( $user->ID );

	if ( $teacher && sp_teacher_can_access_class( $teacher->id, (int) $assignment->class_id ) ) {
		return true;
	}

	// Students only ever see published or already-closed work.
	if ( ! in_array( $assignment->status, array( 'published', 'closed' ), true ) ) {
		return false;
	}

	$profile = sp_get_profile_by_user_id( $user->ID );

	if ( ! $profile ) {
		return false;
	}

	return sp_student_can_access_class( $profile->id, (int) $assignment->class_id );
}

/**
 * Entry point for ?sp_assignment_file=<id>. Streams the paper and exits.
 */
function sp_stream_assignment_file() {
	$assignment_id = isset( $_GET['sp_assignment_file'] ) ? (int) $_GET['sp_assignment_file'] : 0;

	if ( $assignment_id <= 0 ) {
		wp_die( esc_html__( 'That file could not be found.', 'student-portal' ), '', array( 'response' => 404 ) );
	}

	$assignment = sp_get_assignment( $assignment_id );

	if ( ! $assignment ) {
		wp_die( esc_html__( 'That file could not be found.', 'student-portal' ), '', array( 'response' => 404 ) );
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( sp_portal_home_url( 'student' ) );
		exit;
	}

	if ( 'paper' !== $assignment->type || ! $assignment->attachment_path ) {
		wp_die( esc_html__( 'That file could not be found.', 'student-portal' ), '', array( 'response' => 404 ) );
	}

	if ( ! sp_user_can_view_assignment( $assignment ) ) {
		wp_die( esc_html__( 'You do not have access to that file.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	$path = sp_assignment_file_path( $assignment );

	if ( ! $path || ! is_file( $path ) ) {
		wp_die( esc_html__( 'That file is no longer on disk.', 'student-portal' ), '', array( 'response' => 404 ) );
	}

	// Assignments carry no mime column, so the extension decides the type.
	$filetype    = wp_check_filetype( $path );
	$mime        = ! empty( $filetype['type'] ) ? $filetype['type'] : 'application/octet-stream';
	$filename    = sp_safe_content_filename( $assignment->original_filename ? $assignment->original_filename : basename( $path ) );
	$disposition = ( isset( $_GET['inline'] ) && 'application/pdf' === $mime ) ? 'inline' : 'attachment';

	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
	}

	$handle = fopen( $path, 'rb' );

	if ( ! $handle ) {
		wp_die( esc_html__( 'That file could not be read.', 'student-portal' ), '', array( 'response' => 500 ) );
	}

	while ( ! feof( $handle ) ) {
		echo fread( $handle, 8192 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary file stream.
		flush();
	}

	fclose( $handle );
	exit;
}
add_action( 'template_redirect', 'sp_maybe_stream_assignment_file', 5 );

/**
 * Redirect ?sp_assignment_file requests to the streaming handler.
 */
function sp_maybe_stream_assignment_file() {
	if ( ! isset( $_GET['sp_assignment_file'] ) ) {
		return;
	}

	sp_stream_assignment_file();
}

/* -------------------------------------------------------------------------
 * Exam rendering helpers shared by the student task page.
 * ---------------------------------------------------------------------- */

/**
 * Seconds past the deadline a submit still keeps its answers.
 *
 * The countdown posts at zero, so every honest submission arrives a moment
 * after the deadline; without a window here it would be graded as empty.
 *
 * @return int
 */
function sp_assignment_submit_grace() {
	return 60;
}

/**
 * Whether an in-progress attempt is past its deadline plus the grace window.
 *
 * @param object $row        Attempt row.
 * @param object $assignment Assignment row.
 * @return bool
 */
function sp_assignment_submit_is_late( $row, $assignment ) {
	if ( ! $row || ! $row->started_at || empty( $assignment->time_limit_minutes ) ) {
		return false;
	}

	$deadline = strtotime( $row->started_at ) + ( (int) $assignment->time_limit_minutes * 60 ) + sp_assignment_submit_grace();

	return time() > $deadline;
}

/**
 * The MCQ options of a question as a plain array.
 *
 * @param object $question Question row.
 * @return string[]
 */
function sp_question_options( $question ) {
	if ( empty( $question->options ) ) {
		return array();
	}

	$decoded = json_decode( $question->options, true );

	return is_array( $decoded ) ? array_values( $decoded ) : array();
}

/**
 * Answer inputs for one question inside the exam form.
 *
 * Renders choice or text inputs named `answers[<question id>]` and never
 * carries the correct answer into the page.
 *
 * @param object $question Question row.
 */
function sp_exam_question_inputs( $question ) {
	$name = 'answers[' . (int) $question->id . ']';

	if ( 'mcq' === $question->type ) {
		foreach ( sp_question_options( $question ) as $sp_index => $sp_option ) {
			$sp_letter = chr( 65 + (int) $sp_index );

			printf(
				'<label class="sp-radio"><input type="radio" name="%1$s" value="%2$s" /> %2$s. %3$s</label>',
				esc_attr( $name ),
				esc_attr( $sp_letter ),
				esc_html( $sp_option )
			);
		}

		return;
	}

	if ( 'truefalse' === $question->type ) {
		foreach ( array( 'TRUE' => 'True', 'FALSE' => 'False' ) as $sp_value => $sp_label ) {
			printf(
				'<label class="sp-radio"><input type="radio" name="%1$s" value="%2$s" /> %3$s</label>',
				esc_attr( $name ),
				esc_attr( $sp_value ),
				esc_html( $sp_label )
			);
		}

		return;
	}

	printf(
		'<input class="sp-field" type="text" name="%1$s" value="" autocomplete="off" placeholder="Type your answer" />',
		esc_attr( $name )
	);
}

/**
 * A stored answer as readable text for the result review.
 *
 * @param object $question Question row.
 * @param string $given    The student's answer.
 * @return string
 */
function sp_attempt_answer_label( $question, $given ) {
	$given = trim( (string) $given );

	if ( '' === $given ) {
		return 'Not answered';
	}

	if ( 'mcq' === $question->type ) {
		$index  = strpos( 'ABCDEF', strtoupper( $given ) );
		$option = ( false !== $index ) ? sp_question_options( $question ) : array();

		if ( isset( $option[ $index ] ) ) {
			return strtoupper( $given ) . '. ' . $option[ $index ];
		}

		return $given;
	}

	if ( 'truefalse' === $question->type ) {
		$norm = strtoupper( $given );

		if ( in_array( $norm, array( 'T', '1', 'YES', 'Y', 'TRUE' ), true ) ) {
			return 'True';
		}

		if ( in_array( $norm, array( 'F', '0', 'NO', 'N', 'FALSE' ), true ) ) {
			return 'False';
		}
	}

	return $given;
}
