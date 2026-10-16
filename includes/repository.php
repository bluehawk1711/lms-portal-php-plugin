<?php
/**
 * Data access layer.
 *
 * All SQL lives here so the rest of the plugin never builds a query. Every
 * query against a Certificate Manager table is funnelled through the helpers
 * below, which return empty results when that plugin is inactive instead of
 * throwing a database error.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Student profiles
 * ---------------------------------------------------------------------- */

/**
 * Fetch a student profile by its WordPress user id.
 *
 * @param int $user_id WP user id.
 * @return object|null
 */
function sp_get_profile_by_user_id( $user_id ) {
	global $wpdb;

	$user_id = (int) $user_id;

	if ( $user_id <= 0 ) {
		return null;
	}

	$table = sp_table( 'student_profiles' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) );
}

/**
 * Fetch a student profile by its own row id.
 *
 * @param int $profile_id sp_student_profiles.id.
 * @return object|null
 */
function sp_get_profile_by_id( $profile_id ) {
	global $wpdb;

	$profile_id = (int) $profile_id;

	if ( $profile_id <= 0 ) {
		return null;
	}

	$table = sp_table( 'student_profiles' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $profile_id ) );
}

/**
 * Fetch a student profile by email address.
 *
 * @param string $email Email address.
 * @return object|null
 */
function sp_get_profile_by_email( $email ) {
	global $wpdb;

	$email = sanitize_email( $email );

	if ( '' === $email ) {
		return null;
	}

	$table = sp_table( 'student_profiles' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) );
}

/**
 * Count student profiles for admin pagination.
 *
 * @param string $search Optional name/email/enrolment-id search.
 * @return int
 */
function sp_count_profiles( $search = '' ) {
	global $wpdb;

	$table = sp_table( 'student_profiles' );

	if ( '' !== $search ) {
		$like        = '%' . $wpdb->esc_like( $search ) . '%';
		$enrollments = sp_table( 'enrollments' );
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} p
				 WHERE p.full_name LIKE %s OR p.email LIKE %s
				   OR EXISTS ( SELECT 1 FROM {$enrollments} e
							   WHERE e.profile_id = p.id AND e.enroll_id LIKE %s )",
				$like,
				$like,
				$like
			)
		);
	} else {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	return (int) $count;
}

/**
 * Paginated list of student profiles.
 *
 * @param array $args search, orderby, order, per_page, offset.
 * @return object[]
 */
function sp_get_profiles( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'search'  => '',
			'orderby' => 'full_name',
			'order'   => 'ASC',
			'per_page' => 25,
			'offset'  => 0,
		)
	);

	$allowed_orderby = array( 'full_name', 'email', 'created_at', 'status' );
	$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'full_name';
	$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

	$table  = sp_table( 'student_profiles' );
	$enrollments = sp_table( 'enrollments' );
	$per_page = max( 1, (int) $args['per_page'] );
	$offset   = max( 0, (int) $args['offset'] );

	$where  = '1=1';
	$params = array();

	if ( '' !== $args['search'] ) {
		$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where   .= ' AND (p.full_name LIKE %s OR p.email LIKE %s
			OR EXISTS ( SELECT 1 FROM ' . $enrollments . ' e WHERE e.profile_id = p.id AND e.enroll_id LIKE %s ))';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	$sql = "SELECT p.* FROM {$table} p WHERE {$where} ORDER BY p.{$orderby} {$order} LIMIT %d OFFSET %d";
	$sql = $wpdb->prepare( $sql, array_merge( $params, array( $per_page, $offset ) ) );

	return $wpdb->get_results( $sql );
}

/* -------------------------------------------------------------------------
 * Teachers
 * ---------------------------------------------------------------------- */

/**
 * Fetch a teacher row by WordPress user id.
 *
 * @param int $user_id WP user id.
 * @return object|null
 */
function sp_get_teacher_by_user_id( $user_id ) {
	global $wpdb;

	$user_id = (int) $user_id;

	if ( $user_id <= 0 ) {
		return null;
	}

	$table = sp_table( 'teachers' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) );
}

/**
 * Fetch a teacher row by email address.
 *
 * @param string $email Email address.
 * @return object|null
 */
/**
 * One teacher row by id.
 *
 * @param int $teacher_id Teacher id.
 * @return object|null
 */
function sp_get_teacher( $teacher_id ) {
	global $wpdb;

	$teacher_id = (int) $teacher_id;

	if ( $teacher_id <= 0 ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'teachers' ) . ' WHERE id = %d', $teacher_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
	);
}

function sp_get_teacher_by_email( $email ) {
	global $wpdb;

	$email = sanitize_email( $email );

	if ( '' === $email ) {
		return null;
	}

	$table = sp_table( 'teachers' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) );
}

/**
 * List every teacher, ordered by name.
 *
 * @return object[]
 */
function sp_get_teachers() {
	global $wpdb;

	$table = sp_table( 'teachers' );

	return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY full_name ASC" );
}

/**
 * Count teachers.
 *
 * @return int
 */
function sp_count_teachers() {
	global $wpdb;

	$table = sp_table( 'teachers' );

	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
}

/* -------------------------------------------------------------------------
 * Classes
 * ---------------------------------------------------------------------- */

/**
 * Fetch one class.
 *
 * @param int $class_id Class id.
 * @return object|null
 */
function sp_get_class( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return null;
	}

	$table = sp_table( 'classes' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $class_id ) );
}

/**
 * List classes.
 *
 * @param array $args status, teacher_id, search, orderby, order, per_page, offset.
 * @return object[]
 */
function sp_get_classes( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'status'     => '',
			'teacher_id' => 0,
			'search'     => '',
			'orderby'    => 'start_date',
			'order'      => 'DESC',
			'per_page'   => 50,
			'offset'     => 0,
		)
	);

	$allowed_orderby = array( 'title', 'code', 'start_date', 'end_date', 'created_at', 'status' );
	$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'start_date';
	$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

	$table    = sp_table( 'classes' );
	$assign   = sp_table( 'teacher_classes' );
	$per_page = max( 1, (int) $args['per_page'] );
	$offset   = max( 0, (int) $args['offset'] );

	$joins   = '';
	$where   = '1=1';
	$params  = array();
	$distinct = '';

	if ( (int) $args['teacher_id'] > 0 ) {
		$joins   .= " INNER JOIN {$assign} ON {$assign}.class_id = o.id";
		$where   .= ' AND ' . $assign . '.teacher_id = %d';
		$params[] = (int) $args['teacher_id'];
		$distinct = 'DISTINCT';
	}

	if ( '' !== $args['status'] ) {
		$where   .= ' AND o.status = %s';
		$params[] = $args['status'];
	}

	if ( '' !== $args['search'] ) {
		$where   .= ' AND (o.title LIKE %s OR o.code LIKE %s)';
		$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$params[] = $like;
		$params[] = $like;
	}

	$params[] = $per_page;
	$params[] = $offset;

	$sql = "SELECT {$distinct} o.* FROM {$table} o {$joins} WHERE {$where} ORDER BY o.{$orderby} {$order} LIMIT %d OFFSET %d";

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Count classes matching the same filters sp_get_classes() accepts.
 *
 * @param array $args status, teacher_id, search.
 * @return int
 */
function sp_count_classes( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'status'     => '',
			'teacher_id' => 0,
			'search'     => '',
		)
	);

	$table   = sp_table( 'classes' );
	$assign  = sp_table( 'teacher_classes' );
	$joins   = '';
	$where   = '1=1';
	$params  = array();
	$distinct = '';

	if ( (int) $args['teacher_id'] > 0 ) {
		$joins    .= " INNER JOIN {$assign} ON {$assign}.class_id = o.id";
		$where    .= ' AND ' . $assign . '.teacher_id = %d';
		$params[]  = (int) $args['teacher_id'];
		$distinct  = 'DISTINCT';
	}

	if ( '' !== $args['status'] ) {
		$where   .= ' AND o.status = %s';
		$params[] = $args['status'];
	}

	if ( '' !== $args['search'] ) {
		$where   .= ' AND (o.title LIKE %s OR o.code LIKE %s)';
		$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$params[] = $like;
		$params[] = $like;
	}

	$sql = "SELECT COUNT({$distinct} o.id) FROM {$table} o {$joins} WHERE {$where}";

	if ( $params ) {
		$sql = $wpdb->prepare( $sql, $params );
	}

	return (int) $wpdb->get_var( $sql );
}

/**
 * Whether a teacher is assigned to a class.
 *
 * @param int $teacher_id  Teacher row id.
 * @param int $class_id Class id.
 * @return bool
 */
function sp_teacher_can_access_class( $teacher_id, $class_id ) {
	global $wpdb;

	$teacher_id  = (int) $teacher_id;
	$class_id = (int) $class_id;

	if ( $teacher_id <= 0 || $class_id <= 0 ) {
		return false;
	}

	$table = sp_table( 'teacher_classes' );

	$found = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE teacher_id = %d AND class_id = %d",
			$teacher_id,
			$class_id
		)
	);

	return ! empty( $found );
}

/**
 * Whether a student has an active enrollment in a class.
 *
 * @param int $profile_id  Student profile id.
 * @param int $class_id Class id.
 * @return bool
 */
function sp_student_can_access_class( $profile_id, $class_id ) {
	global $wpdb;

	$profile_id  = (int) $profile_id;
	$class_id = (int) $class_id;

	if ( $profile_id <= 0 || $class_id <= 0 ) {
		return false;
	}

	$table = sp_table( 'enrollments' );

	$found = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE profile_id = %d AND class_id = %d AND status IN ( 'active', 'completed' )",
			$profile_id,
			$class_id
		)
	);

	return ! empty( $found );
}

/* -------------------------------------------------------------------------
 * Enrollments
 * ---------------------------------------------------------------------- */

/**
 * Ids of every class a student profile is actively enrolled in.
 *
 * @param int $profile_id Student profile id.
 * @return int[]
 */
function sp_get_enrolled_class_ids( $profile_id ) {
	global $wpdb;

	$profile_id = (int) $profile_id;

	if ( $profile_id <= 0 ) {
		return array();
	}

	$table  = sp_table( 'enrollments' );
	$classes = sp_table( 'classes' );

	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT e.class_id
			 FROM {$table} e
			 INNER JOIN {$classes} o ON o.id = e.class_id
			 WHERE e.profile_id = %d AND e.status = 'active'
			 ORDER BY o.start_date DESC",
			$profile_id
		)
	);

	return array_map( 'intval', (array) $ids );
}

/**
 * Enrollments of one student, joined to the class for display.
 *
 * @param int   $profile_id Student profile id.
 * @param array $statuses   Enrollment statuses to include.
 * @return object[]
 */
function sp_get_enrollments_for_profile( $profile_id, $statuses = array( 'active' ) ) {
	global $wpdb;

	$profile_id = (int) $profile_id;

	if ( $profile_id <= 0 ) {
		return array();
	}

	return sp_query_enrollments(
		array(
			'profile_id' => $profile_id,
			'statuses'   => $statuses,
		)
	);
}

/**
 * Enrollments in one class, joined to the student profile for display.
 *
 * @param int   $class_id Class id.
 * @param array $statuses    Enrollment statuses to include.
 * @param int   $limit       Row cap, 0 for unlimited.
 * @return object[]
 */
function sp_get_enrollments_for_class( $class_id, $statuses = array( 'active' ), $limit = 0 ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return array();
	}

	$profiles = sp_table( 'student_profiles' );
	$enroll   = sp_table( 'enrollments' );

	$statuses = array_values( array_filter( array_map( 'strval', (array) $statuses ) ) );

	if ( ! $statuses ) {
		return array();
	}

	$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

	$sql = "SELECT e.id AS enrollment_id, e.enroll_id, e.admission_date, e.status, e.created_at,
					p.id AS profile_id, p.full_name, p.email, p.phone
			FROM {$enroll} e
			INNER JOIN {$profiles} p ON p.id = e.profile_id
			WHERE e.class_id = %d AND e.status IN ( {$placeholders} )
			ORDER BY p.full_name ASC";

	$params = array_merge( array( $class_id ), $statuses );

	if ( $limit > 0 ) {
		$sql     .= ' LIMIT %d';
		$params[] = (int) $limit;
	}

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Shared enrollment query.
 *
 * @param array $args profile_id, class_id, statuses.
 * @return object[]
 */
function sp_query_enrollments( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'profile_id'  => 0,
			'class_id' => 0,
			'statuses'    => array( 'active' ),
		)
	);

	$enroll    = sp_table( 'enrollments' );
	$profiles  = sp_table( 'student_profiles' );
	$classes = sp_table( 'classes' );

	$statuses = array_values( array_filter( array_map( 'strval', (array) $args['statuses'] ) ) );

	if ( ! $statuses ) {
		return array();
	}

	$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
	$where        = 'e.status IN ( ' . $placeholders . ' )';
	$params       = $statuses;

	if ( (int) $args['profile_id'] > 0 ) {
		$where   .= ' AND e.profile_id = %d';
		$params[] = (int) $args['profile_id'];
	}

	if ( (int) $args['class_id'] > 0 ) {
		$where   .= ' AND e.class_id = %d';
		$params[] = (int) $args['class_id'];
	}

	$sql = "SELECT e.*, o.title AS class_title, o.code AS class_code,
					o.start_date AS class_start, o.end_date AS class_end,
					p.full_name, p.email
			FROM {$enroll} e
			INNER JOIN {$classes} o ON o.id = e.class_id
			INNER JOIN {$profiles} p ON p.id = e.profile_id
			WHERE {$where}
			ORDER BY o.start_date DESC, p.full_name ASC";

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Count students in a class.
 *
 * @param int $class_id Class id.
 * @return int
 */
function sp_count_class_students( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return 0;
	}

	$table = sp_table( 'enrollments' );

	return (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE class_id = %d AND status = 'active'", $class_id )
	);
}

/* -------------------------------------------------------------------------
 * Attendance
 * ---------------------------------------------------------------------- */

/**
 * Attendance tallies for one student in one class.
 *
 * @param int $profile_id  Student profile id.
 * @param int $class_id Class id.
 * @return array present, absent, late, excused, total, percentage.
 */
function sp_get_attendance_stats( $profile_id, $class_id ) {
	global $wpdb;

	$profile_id  = (int) $profile_id;
	$class_id = (int) $class_id;

	$empty = array(
		'present'    => 0,
		'absent'     => 0,
		'late'       => 0,
		'excused'    => 0,
		'total'      => 0,
		'percentage' => 0.0,
	);

	if ( $profile_id <= 0 || $class_id <= 0 ) {
		return $empty;
	}

	$records  = sp_table( 'attendance_records' );
	$sessions = sp_table( 'attendance_sessions' );

	/*
	 * Cancelled dates — a calendar holiday/test, or every assigned teacher
	 * absent — are excluded outright: no class ran, so those sessions must
	 * neither help nor hurt anyone's percentage.
	 */
	$cancelled = sp_cancelled_class_dates( $class_id );

	$sql    = "SELECT r.status, COUNT(*) AS tally
			 FROM {$records} r
			 INNER JOIN {$sessions} s ON s.id = r.session_id
			 WHERE r.profile_id = %d AND s.class_id = %d";
	$params = array( $profile_id, $class_id );

	if ( $cancelled ) {
		$placeholders = implode( ', ', array_fill( 0, count( $cancelled ), '%s' ) );
		$sql         .= " AND s.session_date NOT IN ( {$placeholders} )";
		$params       = array_merge( $params, $cancelled );
	}

	$sql .= ' GROUP BY r.status';

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

	foreach ( $rows as $row ) {
		$status = (string) $row->status;

		if ( isset( $empty[ $status ] ) ) {
			$empty[ $status ] = (int) $row->tally;
		}
	}

	$empty['total'] = $empty['present'] + $empty['absent'] + $empty['late'] + $empty['excused'];

	/*
	 * Attendance percentage, per the institute rule:
	 *
	 *     ( present + late ) / ( total - excused ) * 100
	 *
	 * "late" counts as attended. "excused" is removed from the denominator
	 * entirely, so an authorised absence neither helps nor hurts the score.
	 * When every session is excused the denominator is zero, which we report
	 * as 0.0 rather than dividing by zero.
	 */
	$attended = $empty['present'] + $empty['late'];
	$denominator = $empty['total'] - $empty['excused'];

	if ( $denominator > 0 ) {
		$empty['percentage'] = round( ( $attended / $denominator ) * 100, 1 );
		$empty['percentage'] = min( 100.0, max( 0.0, $empty['percentage'] ) );
	} else {
		$empty['percentage'] = 0.0;
	}

	return $empty;
}

/**
 * Per-date attendance history for one student in one class.
 *
 * @param int $profile_id  Student profile id.
 * @param int $class_id Class id.
 * @param int $limit       Row cap.
 * @return object[]
 */
function sp_get_attendance_history( $profile_id, $class_id, $limit = 60 ) {
	global $wpdb;

	$profile_id  = (int) $profile_id;
	$class_id = (int) $class_id;
	$limit       = max( 1, (int) $limit );

	if ( $profile_id <= 0 || $class_id <= 0 ) {
		return array();
	}

	$records  = sp_table( 'attendance_records' );
	$sessions = sp_table( 'attendance_sessions' );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT s.session_date, s.topic, r.status
			 FROM {$records} r
			 INNER JOIN {$sessions} s ON s.id = r.session_id
			 WHERE r.profile_id = %d AND s.class_id = %d
			 ORDER BY s.session_date DESC
			 LIMIT %d",
			$profile_id,
			$class_id,
			$limit
		)
	);
}

/* -------------------------------------------------------------------------
 * Results
 * ---------------------------------------------------------------------- */

/**
 * Fetch the result row for an enrollment.
 *
 * @param int $enrollment_id Enrollment id.
 * @return object|null
 */
function sp_get_result_for_enrollment( $enrollment_id ) {
	global $wpdb;

	$enrollment_id = (int) $enrollment_id;

	if ( $enrollment_id <= 0 ) {
		return null;
	}

	$table = sp_table( 'results' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE enrollment_id = %d", $enrollment_id ) );
}

/* -------------------------------------------------------------------------
 * Materials
 * ---------------------------------------------------------------------- */

/**
 * Materials for one class.
 *
 * @param int   $class_id Class id.
 * @param array $statuses    Material statuses to include.
 * @return object[]
 */
function sp_get_materials( $class_id, $statuses = array( 'published' ) ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return array();
	}

	$statuses = array_values( array_filter( array_map( 'strval', (array) $statuses ) ) );

	if ( ! $statuses ) {
		return array();
	}

	$table         = sp_table( 'materials' );
	$placeholders  = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

	$params = array_merge( array( $class_id ), $statuses );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE class_id = %d AND status IN ( {$placeholders} )
			 ORDER BY published_at DESC, created_at DESC",
			$params
		)
	);
}

/**
 * Fetch a material row.
 *
 * @param int $material_id Material id.
 * @return object|null
 */
function sp_get_material( $material_id ) {
	global $wpdb;

	$material_id = (int) $material_id;

	if ( $material_id <= 0 ) {
		return null;
	}

	$table = sp_table( 'materials' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $material_id ) );
}

/**
 * Count draft materials in a class.
 *
 * @param int $class_id Class id.
 * @return int
 */
function sp_count_unpublished_materials( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return 0;
	}

	$table = sp_table( 'materials' );

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE class_id = %d AND status = 'draft'",
			$class_id
		)
	);
}

/**
 * Materials a student may read: published, in classes they are enrolled in.
 *
 * @param int $profile_id Student profile id.
 * @param int $limit      Row cap.
 * @return object[]
 */
function sp_get_student_materials( $profile_id, $limit = 50 ) {
	global $wpdb;

	$profile_id = (int) $profile_id;
	$limit      = max( 1, (int) $limit );

	if ( $profile_id <= 0 ) {
		return array();
	}

	$materials  = sp_table( 'materials' );
	$enrollments = sp_table( 'enrollments' );
	$classes   = sp_table( 'classes' );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT m.*, o.title AS class_title, o.code AS class_code
			 FROM {$materials} m
			 INNER JOIN {$enrollments} e ON e.class_id = m.class_id AND e.profile_id = %d AND e.status IN ( 'active', 'completed' )
			 INNER JOIN {$classes} o ON o.id = m.class_id
			 WHERE m.status = 'published'
			 ORDER BY m.published_at DESC, m.created_at DESC
			 LIMIT %d",
			$profile_id,
			$limit
		)
	);
}

/**
 * Courses owned by the portal, for course pickers and management screens.
 *
 * @param array $args Optional: status (default 'all'), per_page.
 * @return object[]
 */
function sp_get_courses( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'status'   => 'all',
			'per_page' => 0,
		)
	);

	$table  = sp_table( 'courses' );
	$wheres = array();
	$values = array();

	if ( 'all' !== $args['status'] ) {
		$wheres[] = 'status = %s';
		$values[] = $args['status'];
	}

	$sql = 'SELECT * FROM ' . $table;

	if ( $wheres ) {
		$sql .= ' WHERE ' . implode( ' AND ', $wheres );
	}

	$sql .= ' ORDER BY name ASC';

	if ( (int) $args['per_page'] > 0 ) {
		$values[] = (int) $args['per_page'];
		$sql     .= ' LIMIT %d';
	}

	return $values
		? $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		: $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * One portal course.
 *
 * @param int $course_id Course id.
 * @return object|null
 */
function sp_get_course( $course_id ) {
	global $wpdb;

	if ( ! $course_id ) {
		return null;
	}

	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . sp_table( 'courses' ) . ' WHERE id = %d', (int) $course_id ) );
}