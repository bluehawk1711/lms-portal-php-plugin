<?php
/**
 * Portal-owned courses.
 *
 * Courses used to be read straight from the Certificate Manager's table, which
 * meant the certificate plugin was quietly the source of truth for curriculum
 * names. From schema 2.0 the portal owns them: `sp_courses` is created empty or
 * seeded once from the vendor table (see sp_migrate_courses_from_vendor) and is
 * edited only from here.
 *
 * The vendor table is not written to. At issuance time the adapter in
 * cert-push.php resolves the certificate's course code from this table.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create or update a course.
 *
 * @param array $args id, name, code, description, duration, status.
 * @return int|WP_Error Course id on success.
 */
function sp_save_course( $args ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'id'          => 0,
			'name'        => '',
			'code'        => '',
			'description' => '',
			'duration'    => '',
			'status'      => 'active',
		)
	);

	$name        = sanitize_text_field( $args['name'] );
	$code        = sp_normalize_course_code( $args['code'] );
	$description = sanitize_textarea_field( $args['description'] );
	$duration    = sanitize_text_field( $args['duration'] );
	$status      = in_array( $args['status'], array( 'active', 'archived' ), true ) ? $args['status'] : 'active';

	if ( '' === $name ) {
		return new WP_Error( 'sp_no_name', 'Enter a course name.' );
	}

	if ( '' !== $code ) {
		$dupe = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . sp_table( 'courses' ) . ' WHERE code = %s AND id <> %d',
				$code,
				(int) $args['id']
			)
		);

		if ( $dupe ) {
			return new WP_Error( 'sp_code_taken', 'Another course already uses the code "' . $code . '".' );
		}
	}

	$data = array(
		'name'        => $name,
		'code'        => '' !== $code ? $code : null,
		'description' => $description ? $description : null,
		'duration'    => $duration ? $duration : null,
		'status'      => $status,
	);

	$format    = array( '%s', '%s', '%s', '%s', '%s' );
	$course_id = (int) $args['id'];

	if ( $course_id > 0 ) {
		if ( ! sp_get_course( $course_id ) ) {
			return new WP_Error( 'sp_no_course', 'That course no longer exists.' );
		}

		$wpdb->update( sp_table( 'courses' ), $data, array( 'id' => $course_id ), $format, array( '%d' ) );

		sp_log_activity( 'course.updated', 'course', $course_id, $name );

		return $course_id;
	}

	$data['created_by'] = get_current_user_id();

	$wpdb->insert( sp_table( 'courses' ), $data, array_merge( $format, array( '%d' ) ) );

	$course_id = (int) $wpdb->insert_id;

	sp_log_activity( 'course.created', 'course', $course_id, $name );

	return $course_id;
}

/**
 * Uppercase a course code and strip everything the certificate id cannot carry.
 *
 * The Certificate Manager builds ids as `SWJOD/{code}/{year}/{random}`, so the
 * code must be a path-safe slug. An empty result means "no code" rather than
 * an error: the certificate falls back to `GEN`.
 *
 * @param string $code Raw code.
 * @return string
 */
function sp_normalize_course_code( $code ) {
	$code = strtoupper( preg_replace( '/[^A-Za-z0-9]+/', '-', (string) $code ) );

	return trim( trim( (string) $code ), '-' );
}

/**
 * Whether a course code is free to use.
 *
 * @param string $code      Normalised code.
 * @param int    $excluding Course id to ignore.
 * @return bool
 */
function sp_course_code_available( $code, $excluding = 0 ) {
	global $wpdb;

	if ( '' === (string) $code ) {
		return true;
	}

	$found = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT id FROM ' . sp_table( 'courses' ) . ' WHERE code = %s AND id <> %d',
			(string) $code,
			(int) $excluding
		)
	);

	return ! $found;
}

/**
 * How many classes are taught from a course.
 *
 * @param int $course_id Course id.
 * @return int
 */
function sp_count_classes_for_course( $course_id ) {
	global $wpdb;

	if ( ! $course_id ) {
		return 0;
	}

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . sp_table( 'classes' ) . ' WHERE course_id = %d',
			(int) $course_id
		)
	);
}

/**
 * Delete a course that no class still uses.
 *
 * The row is removed rather than archived because it never stored data: the
 * certificate records it once fed keep their own copy of the name.
 *
 * @param int $course_id Course id.
 * @return true|WP_Error
 */
function sp_delete_course( $course_id ) {
	$course_id = (int) $course_id;
	$course    = sp_get_course( $course_id );

	if ( ! $course ) {
		return new WP_Error( 'sp_no_course', 'That course no longer exists.' );
	}

	$in_use = sp_count_classes_for_course( $course_id );

	if ( $in_use > 0 ) {
		return new WP_Error(
			'sp_course_in_use',
			sprintf(
				/* translators: %d: number of classes. */
				_n( '%d class still teaches this course.', '%d classes still teach this course.', $in_use, 'student-portal' ),
				$in_use
			)
		);
	}

	global $wpdb;

	$wpdb->delete( sp_table( 'courses' ), array( 'id' => $course_id ), array( '%d' ) );

	sp_log_activity( 'course.deleted', 'course', $course_id, (string) $course->name );

	return true;
}
