<?php
/**
 * Classes, teacher assignment and enrollment management.
 *
 * Enrolment writes come from two places: the admin Enrollments screen, and a
 * teacher adding or removing students in a class they are assigned to. Every
 * write is capability-checked at the point of use as well as in the route.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create or update a class.
 *
 * @param array $args id, title, code, course_id, description, start_date, end_date, status.
 * @return int|WP_Error Class id on success.
 */
function sp_save_class( $args ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'id'          => 0,
			'title'       => '',
			'code'        => '',
			'course_id'   => 0,
			'description' => '',
			'start_date'  => '',
			'end_date'    => '',
			'status'      => 'active',
		)
	);

	$title       = sanitize_text_field( $args['title'] );
	$code        = sanitize_text_field( $args['code'] );
	$description = sanitize_textarea_field( $args['description'] );
	$course_id   = (int) $args['course_id'];
	$status      = in_array( $args['status'], array( 'active', 'archived', 'pending' ), true ) ? $args['status'] : 'active';

	if ( '' === $title ) {
		return new WP_Error( 'sp_no_title', 'Enter a class title.' );
	}

	if ( $course_id > 0 && ! sp_get_course( $course_id ) ) {
		return new WP_Error( 'sp_bad_course', 'That course no longer exists. Pick one from the Courses screen.' );
	}

	$start_date = sp_sanitize_date( $args['start_date'] );
	$end_date   = sp_sanitize_date( $args['end_date'] );

	if ( $start_date && $end_date && $end_date < $start_date ) {
		return new WP_Error( 'sp_bad_dates', 'The end date cannot be before the start date.' );
	}

	$data = array(
		'title'       => $title,
		'code'        => $code ? $code : null,
		'course_id'   => $course_id > 0 ? $course_id : null,
		'description' => $description ? $description : null,
		'start_date'  => $start_date ? $start_date : null,
		'end_date'    => $end_date ? $end_date : null,
		'status'      => $status,
	);

	$format = array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' );

	$class_id = (int) $args['id'];

	if ( $class_id > 0 ) {
		$existing = sp_get_class( $class_id );

		if ( ! $existing ) {
			return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
		}

		$wpdb->update( sp_table( 'classes' ), $data, array( 'id' => $class_id ), $format, array( '%d' ) );

		sp_log_activity( 'class.updated', 'class', $class_id, $title );

		return $class_id;
	}

	$data['created_by'] = get_current_user_id();

	$wpdb->insert( sp_table( 'classes' ), $data, array_merge( $format, array( '%d' ) ) );

	$class_id = (int) $wpdb->insert_id;

	sp_log_activity( 'class.created', 'class', $class_id, $title );

	return $class_id;
}

/**
 * Assign a teacher to a class.
 *
 * @param int  $teacher_id  Teacher row id.
 * @param int  $class_id Class id.
 * @param bool $is_primary  Whether this is the lead teacher.
 * @param bool $notify      Whether to email the teacher.
 * @return true|WP_Error
 */
function sp_assign_teacher( $teacher_id, $class_id, $is_primary = false, $notify = false ) {
	global $wpdb;

	$teacher_id  = (int) $teacher_id;
	$class_id = (int) $class_id;

	$teacher  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . sp_table( 'teachers' ) . ' WHERE id = %d', $teacher_id ) );
	$class = sp_get_class( $class_id );

	if ( ! $teacher ) {
		return new WP_Error( 'sp_no_teacher', 'That teacher no longer exists.' );
	}

	if ( ! $class ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	$existing_id = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT id FROM ' . sp_table( 'teacher_classes' ) . ' WHERE teacher_id = %d AND class_id = %d',
			$teacher_id,
			$class_id
		)
	);

	if ( $existing_id ) {
		if ( $is_primary ) {
			$wpdb->update(
				sp_table( 'teacher_classes' ),
				array( 'is_primary' => 1 ),
				array( 'id' => (int) $existing_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return true;
	}

	$wpdb->insert(
		sp_table( 'teacher_classes' ),
		array(
			'teacher_id'  => $teacher_id,
			'class_id' => $class_id,
			'is_primary'  => $is_primary ? 1 : 0,
			'assigned_by' => get_current_user_id(),
		),
		array( '%d', '%d', '%d', '%d' )
	);

	if ( $notify ) {
		sp_email_assignment_notice( $teacher, $class );
	}

	return true;
}

/**
 * Make a teacher the class's single lead, assigning them if needed.
 *
 * Used by the class edit form's teacher dropdown: the chosen teacher is
 * assigned when new to the class, and any other teacher keeps their
 * assignment but loses the lead flag — a class always has at most one lead.
 *
 * @param int $class_id   Class id.
 * @param int $teacher_id Teacher row id.
 * @return true|WP_Error
 */
function sp_assign_class_lead_teacher( $class_id, $teacher_id ) {
	global $wpdb;

	$class_id   = (int) $class_id;
	$teacher_id = (int) $teacher_id;

	$class   = sp_get_class( $class_id );
	$teacher = $teacher_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . sp_table( 'teachers' ) . ' WHERE id = %d', $teacher_id ) ) : null;

	if ( ! $class ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	if ( ! $teacher ) {
		return new WP_Error( 'sp_no_teacher', 'That teacher no longer exists.' );
	}

	// One lead per class: demote everyone first, then promote the pick.
	$wpdb->update(
		sp_table( 'teacher_classes' ),
		array( 'is_primary' => 0 ),
		array( 'class_id' => $class_id ),
		array( '%d' ),
		array( '%d' )
	);

	$result = sp_assign_teacher( $teacher_id, $class_id, true, false );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	sp_log_activity(
		'teacher.lead_assigned',
		'class',
		$class_id,
		'Teacher ' . $teacher_id . ' set as lead of class ' . $class_id
	);

	return true;
}

/**
 * The lead (or first assigned) teacher of a class, for prefilling forms.
 *
 * @param int $class_id Class id.
 * @return object|null Teachers row with an is_primary flag, or null.
 */
function sp_get_class_lead_teacher( $class_id ) {
	$teachers = sp_get_class_teachers( $class_id );

	if ( ! is_array( $teachers ) || ! $teachers ) {
		return null;
	}

	foreach ( $teachers as $teacher ) {
		if ( ! empty( $teacher->is_primary ) ) {
			return $teacher;
		}
	}

	return $teachers[0];
}

/**
 * Remove a teacher's assignment.
 *
 * @param int $teacher_id  Teacher row id.
 * @param int $class_id Class id.
 * @return true|WP_Error
 */
function sp_unassign_teacher( $teacher_id, $class_id ) {
	global $wpdb;

	$teacher_id  = (int) $teacher_id;
	$class_id = (int) $class_id;

	if ( ! sp_get_class( $class_id ) ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	$wpdb->delete(
		sp_table( 'teacher_classes' ),
		array(
			'teacher_id'  => $teacher_id,
			'class_id' => $class_id,
		),
		array( '%d', '%d' )
	);

	return true;
}

/**
 * Teachers assigned to a class.
 *
 * @param int $class_id Class id.
 * @return object[] Teacher rows.
 */
function sp_get_class_teachers( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return array();
	}

	$teachers = sp_table( 'teachers' );
	$assign   = sp_table( 'teacher_classes' );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT t.*, a.is_primary
			 FROM {$assign} a
			 INNER JOIN {$teachers} t ON t.id = a.teacher_id
			 WHERE a.class_id = %d
			 ORDER BY a.is_primary DESC, t.full_name ASC",
			$class_id
		)
	);
}

/**
 * The next enrolment ID for a class, auto-generated when none was supplied.
 *
 * Uses the class code (or a short class reference) plus a zero-padded sequence
 * so every enrolment always carries an ID and certificates can be issued
 * without a manual step. An explicit ID always wins.
 *
 * @param int    $class_id  Class id.
 * @param string $enroll_id Optional admin-supplied ID; used verbatim when non-empty.
 * @return string
 */
function sp_next_enroll_id( $class_id, $enroll_id = '' ) {
	$enroll_id = sanitize_text_field( $enroll_id );

	if ( '' !== $enroll_id ) {
		return $enroll_id;
	}

	global $wpdb;

	$class    = sp_get_class( (int) $class_id );
	$base     = $class && ! empty( $class->code ) ? strtoupper( (string) $class->code ) : 'C' . (int) $class_id;
	$enroll   = sp_table( 'enrollments' );
	$like     = $wpdb->esc_like( $base ) . '-%';
	$existing = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$enroll} WHERE enroll_id LIKE %s",
			$like
		)
	);

	/*
	 * count + 1 alone hands back an ID that is already taken whenever the
	 * sequence has gaps — a manually typed ID, a deleted row — which put two
	 * students under the same number and made every certificate keyed on it
	 * ambiguous. Walk up from the count until the candidate is genuinely free.
	 */
	$sequence = $existing + 1;

	for ( $attempt = 0; $attempt < 200; $attempt++, $sequence++ ) {
		$candidate = sprintf( '%s-%03d', $base, $sequence );
		$taken     = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$enroll} WHERE enroll_id = %s", $candidate )
		);

		if ( ! $taken ) {
			return $candidate;
		}
	}

	return sprintf( '%s-%03d', $base, $sequence );
}

/**
 * Whether an enrolment ID is already used by a different enrollment.
 *
 * Enrolment IDs key the Certificate Manager's student rows, so two students
 * sharing one — however it happened — makes every certificate for either
 * ambiguous. Checked globally, because that is the scope the vendor's unique
 * key operates at.
 *
 * @param string $enroll_id        Candidate ID.
 * @param int    $ignore_enrollment_id Enrollment id to exempt (an edit keeping its own ID).
 * @return bool
 */
function sp_enroll_id_is_taken( $enroll_id, $ignore_enrollment_id = 0 ) {
	global $wpdb;

	$enroll_id = trim( (string) $enroll_id );

	if ( '' === $enroll_id ) {
		return false;
	}

	$sql   = 'SELECT id FROM ' . sp_table( 'enrollments' ) . ' WHERE enroll_id = %s';
	 $args = array( $enroll_id );

	if ( (int) $ignore_enrollment_id > 0 ) {
		$sql    .= ' AND id != %d';
		$args[]  = (int) $ignore_enrollment_id;
	}

	return (bool) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
}

/**
 * Enroll a student profile into a class.
 *
 * @param int    $profile_id     Student profile id.
 * @param int    $class_id       Class id.
 * @param string $admission_date Optional Y-m-d admission date.
 * @param bool   $notify         Whether to email the student.
 * @param string $enroll_id      Optional per-class enrolment ID; auto-generated when blank.
 * @return true|WP_Error
 */
function sp_enroll_student( $profile_id, $class_id, $admission_date = '', $notify = false, $enroll_id = '' ) {
	global $wpdb;

	$profile_id  = (int) $profile_id;
	$class_id = (int) $class_id;
	$enroll_id = sp_next_enroll_id( $class_id, $enroll_id );

	$profile  = sp_get_profile_by_id( $profile_id );
	$class = sp_get_class( $class_id );

	if ( ! $profile ) {
		return new WP_Error( 'sp_no_profile', 'That student no longer exists.' );
	}

	if ( ! $class ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	if ( 'active' !== $profile->status ) {
		return new WP_Error( 'sp_profile_disabled', 'That student account is disabled.' );
	}

	$existing = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . sp_table( 'enrollments' ) . ' WHERE profile_id = %d AND class_id = %d',
			$profile_id,
			$class_id
		)
	);

	if ( $existing ) {
		if ( 'dropped' === $existing->status ) {
			$wpdb->update(
				sp_table( 'enrollments' ),
				array(
					'status'         => 'active',
					'enroll_id'      => '' !== $enroll_id ? $enroll_id : ( $existing->enroll_id ? $existing->enroll_id : sp_next_enroll_id( $class_id ) ),
					'admission_date' => sp_sanitize_date( $admission_date ) ? sp_sanitize_date( $admission_date ) : $existing->admission_date,
				),
				array( 'id' => (int) $existing->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			sp_log_activity(
				'enrollment.reactivated',
				'enrollment',
				(int) $existing->id,
				'Profile ' . $profile_id . ' returned to class ' . $class_id
			);

			return true;
		}

		return new WP_Error( 'sp_already_enrolled', 'That student is already enrolled in this class.' );
	}

	$date = sp_sanitize_date( $admission_date );

	// The generated candidate walks past taken IDs, but a typed one is passed
	// through verbatim — refuse it here so a duplicate never reaches the table.
	if ( sp_enroll_id_is_taken( $enroll_id ) ) {
		return new WP_Error(
			'sp_enroll_id_taken',
			sprintf( 'The enrolment ID %s is already used by another student — pick a different one.', $enroll_id )
		);
	}

	$inserted = $wpdb->insert(
		sp_table( 'enrollments' ),
		array(
			'profile_id'     => $profile_id,
			'class_id'    => $class_id,
			'enroll_id'      => $enroll_id ? $enroll_id : null,
			'admission_date' => $date ? $date : null,
			'status'         => 'active',
			'created_by'     => get_current_user_id(),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%d' )
	);

	// A silent false here used to be reported as a successful enrolment,
	// which is how "enrolled but not in the class" happened. Never claim a
	// row exists when the insert did not land.
	if ( false === $inserted ) {
		return new WP_Error(
			'sp_enroll_failed',
			'The enrolment could not be saved' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' )
		);
	}

	if ( $notify && sp_setting( 'notify_on_enrollment' ) ) {
		sp_email_enrollment_notice( $profile, $class );
	}

	sp_log_activity(
		'enrollment.created',
		'enrollment',
		(int) $wpdb->insert_id,
		'Profile ' . $profile_id . ' enrolled in class ' . $class_id
	);

	return true;
}

/**
 * Notify a student they have been enrolled.
 *
 * @param object $profile  Student profile row.
 * @param object $class Class row.
 * @return bool
 */
function sp_email_enrollment_notice( $profile, $class ) {
	ob_start();
	?>
<p>Dear <?php echo esc_html( $profile->full_name ); ?>,</p>
<p>You have been enrolled in <strong><?php echo esc_html( $class->title ); ?></strong>.</p>
<p><a href="<?php echo esc_url( sp_portal_url() ); ?>">Open your portal</a></p>
	<?php
	$body = (string) ob_get_clean();

	return sp_send_mail(
		$profile->email,
		'You have been enrolled in a new course',
		sp_email_shell( 'Course enrollment', $body ),
		array(
			'user_id' => (int) $profile->user_id,
			'context' => 'enrollment',
		)
	);
}

/**
 * Change an enrollment status.
 *
 * @param int    $enrollment_id Enrollment id.
 * @param string $status        active, completed or dropped.
 * @return true|WP_Error
 */
function sp_set_enrollment_status( $enrollment_id, $status ) {
	global $wpdb;

	$status = in_array( $status, array( 'active', 'completed', 'dropped' ), true ) ? $status : 'active';

	$row = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'enrollments' ) . ' WHERE id = %d', (int) $enrollment_id )
	);

	if ( ! $row ) {
		return new WP_Error( 'sp_no_enrollment', 'That enrollment no longer exists.' );
	}

	$wpdb->update(
		sp_table( 'enrollments' ),
		array( 'status' => $status ),
		array( 'id' => (int) $enrollment_id ),
		array( '%s' ),
		array( '%d' )
	);

	sp_log_activity(
		'enrollment.status_changed',
		'enrollment',
		(int) $enrollment_id,
		sprintf( 'Profile %d in class %d: %s -> %s', (int) $row->profile_id, (int) $row->class_id, $row->status, $status )
	);

	return true;
}

/**
 * Normalise a submitted date to Y-m-d, or '' when unusable.
 *
 * @param string $value Raw value.
 * @return string
 */
function sp_sanitize_date( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	$timestamp = strtotime( $value );

	if ( ! $timestamp ) {
		return '';
	}	// strtotime() and date() share PHP's default timezone, so a plain Y-m-d
	// round-trips exactly whatever was typed. gmdate() here would shift the
	// date by the offset on servers whose default zone is behind UTC.
	return date( 'Y-m-d', $timestamp );
}

/**
 * Delete a class and everything the class owns.
 *
 * The portal's tables carry no InnoDB foreign keys, so the cascade is spelled
 * out here: attendance sessions take their records and audit rows, enrolments
 * take results / grade history / portal certificate rows, assignments go
 * through sp_delete_assignment() in force mode, and materials are removed
 * through sp_delete_material() so their files leave the disk too.
 *
 * Deliberately kept: the shared timing *set* (other classes may reuse the same
 * pattern), the course, and every Certificate Manager table — the portal never
 * writes to the vendor's schema.
 *
 * Reached only from the nonce- and capability-checked `delete_class` action.
 *
 * @param int $class_id Class id.
 * @return true|WP_Error
 */
function sp_delete_class( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;
	$class    = sp_get_class( $class_id );

	if ( ! $class ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	// Attendance: sessions first, then everything keyed on them.
	$sp_session_ids = $wpdb->get_col(
		$wpdb->prepare( 'SELECT id FROM ' . sp_table( 'attendance_sessions' ) . ' WHERE class_id = %d', $class_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
	);

	if ( $sp_session_ids ) {
		$sp_in = implode( ',', array_map( 'intval', $sp_session_ids ) );

		$wpdb->query( 'DELETE FROM ' . sp_table( 'attendance_records' ) . " WHERE session_id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
		$wpdb->query( 'DELETE FROM ' . sp_table( 'attendance_audit' ) . " WHERE session_id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
		$wpdb->query( 'DELETE FROM ' . sp_table( 'attendance_sessions' ) . " WHERE id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
	}

	// Enrolments: results, grade history and this portal's certificate rows.
	$sp_enrollment_ids = $wpdb->get_col(
		$wpdb->prepare( 'SELECT id FROM ' . sp_table( 'enrollments' ) . ' WHERE class_id = %d', $class_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
	);

	if ( $sp_enrollment_ids ) {
		$sp_in = implode( ',', array_map( 'intval', $sp_enrollment_ids ) );

		$wpdb->query( 'DELETE FROM ' . sp_table( 'results' ) . " WHERE enrollment_id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
		$wpdb->query( 'DELETE FROM ' . sp_table( 'grade_sync_log' ) . " WHERE enrollment_id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
		$wpdb->query( 'DELETE FROM ' . sp_table( 'certificates' ) . " WHERE enrollment_id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
		$wpdb->query( 'DELETE FROM ' . sp_table( 'enrollments' ) . " WHERE id IN ( {$sp_in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from intval'd ids.
	}

	// Assignments: force, because a class delete is an explicit admin act
	// even when students have already taken one of them.
	$sp_assignment_ids = $wpdb->get_col(
		$wpdb->prepare( 'SELECT id FROM ' . sp_table( 'assignments' ) . ' WHERE class_id = %d', $class_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
	);

	foreach ( (array) $sp_assignment_ids as $sp_aid ) {
		$sp_result = sp_delete_assignment( (int) $sp_aid, true );

		// "No longer exists" is fine mid-cascade; anything else stops the run
		// so the screen reports a real problem instead of a half-delete.
		if ( is_wp_error( $sp_result ) && 'sp_no_assignment' !== $sp_result->get_error_code() ) {
			return $sp_result;
		}
	}

	// The class question bank.
	$wpdb->delete( sp_table( 'questions' ), array( 'class_id' => $class_id ), array( '%d' ) );

	// Progress, calendar entries, timetable rows and teacher links.
	$wpdb->delete( sp_table( 'curriculum_progress' ), array( 'class_id' => $class_id ), array( '%d' ) );
	$wpdb->delete( sp_table( 'calendar_events' ), array( 'class_id' => $class_id ), array( '%d' ) );
	$wpdb->delete( sp_table( 'class_timings' ), array( 'class_id' => $class_id ), array( '%d' ) );
	$wpdb->delete( sp_table( 'teacher_classes' ), array( 'class_id' => $class_id ), array( '%d' ) );

	// Materials row by row so each stored file is unlinked as well.
	foreach ( sp_get_materials( $class_id, array( 'draft', 'published' ) ) as $sp_material ) {
		sp_delete_material( $sp_material->id );
	}

	// The class row itself. The shared timing set survives on purpose.
	$wpdb->delete( sp_table( 'classes' ), array( 'id' => $class_id ), array( '%d' ) );

	sp_log_activity( 'class.deleted', 'class', $class_id, (string) $class->title );

	return true;
}
