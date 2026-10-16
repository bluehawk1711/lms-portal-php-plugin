<?php
/**
 * Course curriculum: the ordered outline of a course and the per-class record
 * of which topics a class has covered.
 *
 * The outline belongs to a course (`sp_curriculum`), so every class teaching
 * that course shows the same list. Coverage is tracked per class
 * (`sp_curriculum_progress`), so two classes of the same course advance
 * independently. An administrator maintains the outline from the Courses
 * screen; only a teacher assigned to the class may tick its topics, and
 * students read the result.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The ordered outline of a course.
 *
 * @param int $course_id Course id.
 * @return object[] Items ordered by position.
 */
function sp_get_curriculum( $course_id ) {
	global $wpdb;

	$table = sp_table( 'curriculum' );

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE course_id = %d ORDER BY position ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $course_id
		)
	);
}

/**
 * A single outline item.
 *
 * @param int $item_id Item id.
 * @return object|null
 */
function sp_get_curriculum_item( $item_id ) {
	global $wpdb;

	$table = sp_table( 'curriculum' );

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $item_id
		)
	);
}

/**
 * How many topics a course's outline has.
 *
 * @param int $course_id Course id.
 * @return int
 */
function sp_count_curriculum( $course_id ) {
	global $wpdb;

	$table = sp_table( 'curriculum' );

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE course_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $course_id
		)
	);
}

/**
 * Create or update an outline item.
 *
 * @param array $args id, course_id, title, detail.
 * @return int|WP_Error Item id on success.
 */
function sp_save_curriculum( $args ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'id'        => 0,
			'course_id' => 0,
			'title'     => '',
			'detail'    => '',
		)
	);

	$course_id = (int) $args['course_id'];
	$title     = sanitize_text_field( $args['title'] );
	$detail    = sanitize_textarea_field( $args['detail'] );

	if ( '' === $title ) {
		return new WP_Error( 'sp_no_title', 'Enter a topic title.' );
	}

	if ( ! sp_get_course( $course_id ) ) {
		return new WP_Error( 'sp_no_course', 'That course no longer exists.' );
	}

	$data   = array(
		'course_id' => $course_id,
		'title'     => $title,
		'detail'    => $detail ? $detail : null,
	);
	$format = array( '%d', '%s', '%s' );
	$item_id = (int) $args['id'];

	if ( $item_id > 0 ) {
		$existing = sp_get_curriculum_item( $item_id );

		if ( ! $existing ) {
			return new WP_Error( 'sp_no_item', 'That outline item no longer exists.' );
		}

		$wpdb->update( sp_table( 'curriculum' ), $data, array( 'id' => $item_id ), $format, array( '%d' ) );

		sp_log_activity( 'curriculum.updated', 'course', $course_id, $title );

		return $item_id;
	}

	// New items append to the end of the outline.
	$position = 1 + (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COALESCE( MAX( position ), 0 ) FROM ' . sp_table( 'curriculum' ) . ' WHERE course_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			$course_id
		)
	);

	$data['position']  = $position;
	$data['created_by'] = get_current_user_id();

	$wpdb->insert( sp_table( 'curriculum' ), $data, array_merge( $format, array( '%d', '%d' ) ) );

	$item_id = (int) $wpdb->insert_id;

	sp_log_activity( 'curriculum.created', 'course', $course_id, $title );

	return $item_id;
}

/**
 * Delete an outline item and every coverage row recorded against it.
 *
 * @param int $item_id Item id.
 * @return true|WP_Error
 */
function sp_delete_curriculum_item( $item_id ) {
	global $wpdb;

	$item = sp_get_curriculum_item( $item_id );

	if ( ! $item ) {
		return new WP_Error( 'sp_no_item', 'That outline item no longer exists.' );
	}

	$wpdb->delete( sp_table( 'curriculum_progress' ), array( 'item_id' => (int) $item->id ), array( '%d' ) );
	$wpdb->delete( sp_table( 'curriculum' ), array( 'id' => (int) $item->id ), array( '%d' ) );

	sp_log_activity( 'curriculum.deleted', 'course', (int) $item->course_id, $item->title );

	return true;
}

/**
 * Move an outline item one step up or down, resequencing the course.
 *
 * @param int    $item_id   Item id.
 * @param string $direction 'up' or 'down'.
 * @return true|WP_Error
 */
function sp_move_curriculum_item( $item_id, $direction ) {
	$item = sp_get_curriculum_item( $item_id );

	if ( ! $item ) {
		return new WP_Error( 'sp_no_item', 'That outline item no longer exists.' );
	}

	if ( ! in_array( $direction, array( 'up', 'down' ), true ) ) {
		return new WP_Error( 'sp_bad_direction', 'Move direction must be up or down.' );
	}

	$items = sp_get_curriculum( (int) $item->course_id );
	$index = -1;

	foreach ( $items as $i => $row ) {
		if ( (int) $row->id === (int) $item->id ) {
			$index = $i;
			break;
		}
	}

	if ( $index < 0 ) {
		return new WP_Error( 'sp_no_item', 'That outline item no longer exists.' );
	}

	$swap = 'up' === $direction ? $index - 1 : $index + 1;

	if ( $swap < 0 || $swap >= count( $items ) ) {
		return new WP_Error( 'sp_at_edge', 'up' === $direction ? 'That topic is already at the top.' : 'That topic is already at the bottom.' );
	}

	$tmp               = $items[ $index ];
	$items[ $index ]   = $items[ $swap ];
	$items[ $swap ]    = $tmp;

	// Positions are dense 1..N; rewriting them all keeps the order canonical
	// even if the stored values had drifted apart.
	foreach ( $items as $i => $row ) {
		sp_set_curriculum_position( (int) $row->id, $i + 1 );
	}

	sp_log_activity( 'curriculum.moved', 'course', (int) $item->course_id, $item->title );

	return true;
}

/**
 * Write one item's position.
 *
 * @param int $item_id  Item id.
 * @param int $position New position.
 */
function sp_set_curriculum_position( $item_id, $position ) {
	global $wpdb;

	$wpdb->update(
		sp_table( 'curriculum' ),
		array( 'position' => (int) $position ),
		array( 'id' => (int) $item_id ),
		array( '%d' ),
		array( '%d' )
	);
}

/**
 * Every coverage row recorded for a class, keyed by item id.
 *
 * @param int $class_id Class id.
 * @return array<int, object>
 */
function sp_get_class_progress( $class_id ) {
	global $wpdb;

	$table = sp_table( 'curriculum_progress' );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE class_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $class_id
		)
	);

	$map = array();

	foreach ( $rows as $row ) {
		$map[ (int) $row->item_id ] = $row;
	}

	return $map;
}

/**
 * Tick a topic as covered, or untick it.
 *
 * The item must belong to the same course the class teaches — a stale form
 * posting an id from another course is rejected rather than recorded.
 *
 * @param int $class_id Class id.
 * @param int $item_id  Outline item id.
 * @param int $user_id  WP user doing the toggling.
 * @return string|WP_Error 'marked' or 'unmarked'.
 */
function sp_toggle_curriculum_item( $class_id, $item_id, $user_id ) {
	global $wpdb;

	$class = sp_get_class( $class_id );
	$item  = sp_get_curriculum_item( $item_id );

	if ( ! $class ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	if ( ! $item ) {
		return new WP_Error( 'sp_no_item', 'That outline item no longer exists.' );
	}

	if ( (int) $class->course_id !== (int) $item->course_id ) {
		return new WP_Error( 'sp_wrong_course', 'That topic belongs to a different course.' );
	}

	$existing = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT id FROM ' . sp_table( 'curriculum_progress' ) . ' WHERE class_id = %d AND item_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $class_id,
			(int) $item_id
		)
	);

	if ( $existing ) {
		$wpdb->delete( sp_table( 'curriculum_progress' ), array( 'id' => (int) $existing ), array( '%d' ) );

		sp_log_activity( 'curriculum.unmarked', 'class', (int) $class_id, $item->title );

		return 'unmarked';
	}

	$wpdb->insert(
		sp_table( 'curriculum_progress' ),
		array(
			'class_id'     => (int) $class_id,
			'item_id'      => (int) $item_id,
			'completed_at' => current_time( 'mysql' ),
			'completed_by' => (int) $user_id,
		),
		array( '%d', '%d', '%s', '%d' )
	);

	sp_log_activity( 'curriculum.marked', 'class', (int) $class_id, $item->title );

	return 'marked';
}

/**
 * Coverage totals for one class against its course's outline.
 *
 * @param int $course_id Course id.
 * @param int $class_id  Class id.
 * @return array {total:int, done:int, percent:int}
 */
function sp_curriculum_summary( $course_id, $class_id ) {
	$items    = sp_get_curriculum( $course_id );
	$progress = sp_get_class_progress( $class_id );
	$total    = count( $items );
	$done     = 0;

	foreach ( $items as $item ) {
		if ( isset( $progress[ (int) $item->id ] ) ) {
			++$done;
		}
	}

	return array(
		'total'   => $total,
		'done'    => $done,
		'percent' => $total ? (int) round( ( $done / $total ) * 100 ) : 0,
	);
}

/**
 * Coverage totals for a class, resolving its course from the class row.
 *
 * Convenience wrapper for screens that hold a class id (dashboard tiles,
 * enrolment tables) and should show course completion measured by topics.
 *
 * @param int $class_id Class id.
 * @return array {total:int, done:int, percent:int}
 */
function sp_class_curriculum_summary( $class_id ) {
	$class = sp_get_class( (int) $class_id );

	if ( ! $class || ! (int) $class->course_id ) {
		return array(
			'total'   => 0,
			'done'    => 0,
			'percent' => 0,
		);
	}

	return sp_curriculum_summary( (int) $class->course_id, (int) $class_id );
}
