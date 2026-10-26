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
 * The curriculum now supports headings: a heading row has a `group_title` set
 * and `parent_id` null, while topic rows have `parent_id` pointing at their
 * heading. Marking a heading complete marks every topic beneath it.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The ordered outline of a course, grouped by headings.
 *
 * @param int $course_id Course id.
 * @return array[] List of items, each with 'is_heading', 'title', 'detail',
 *                 'id', 'parent_id', 'group_title', 'topics' (for headings),
 *                 'position' keys.
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
 * The ordered outline of a course, structured with headings and their topics.
 *
 * @param int $course_id Course id.
 * @return array[] List of heading groups with 'id', 'title', 'group_title',
 *                 'position', 'topics' (array of topic items).
 */
function sp_get_curriculum_grouped( $course_id ) {
	$items = sp_get_curriculum( $course_id );

	$headings = array();
	$topics  = array();

	foreach ( $items as $item ) {
		if ( ! empty( $item->group_title ) ) {
			// This is a heading row.
			$headings[] = array(
				'id'         => (int) $item->id,
				'title'      => $item->title,
				'group_title' => $item->group_title,
				'position'   => (int) $item->position,
				'topics'     => array(),
			);
		} else {
			// This is a topic row.
			$topics[] = $item;
		}
	}

	// Assign topics to their headings based on parent_id.
	$heading_map = array();

	foreach ( $headings as &$heading ) {
		$heading_map[ $heading['id'] ] = &$heading;
	}

	foreach ( $topics as $topic ) {
		$parent_id = (int) $topic->parent_id;

		if ( isset( $heading_map[ $parent_id ] ) ) {
			$heading_map[ $parent_id ]['topics'][] = array(
				'id'      => (int) $topic->id,
				'title'   => $topic->title,
				'detail'  => $topic->detail,
				'position' => (int) $topic->position,
			);
		} else {
			// Topic without a heading - add to first heading or create orphan group.
			if ( empty( $headings ) ) {
				// No headings exist - treat all topics as under one implicit group.
				$headings[] = array(
					'id'         => 0,
					'title'      => '',
					'group_title' => '',
					'position'   => 0,
					'topics'     => array(),
				);
				$heading_map[ 0 ] = &$headings[ count( $headings ) - 1 ];
			} elseif ( ! isset( $heading_map[ 0 ] ) ) {
				// Headings exist but this topic has no parent heading - create an orphan group.
				$headings[] = array(
					'id'         => 0,
					'title'      => '',
					'group_title' => '',
					'position'   => PHP_INT_MAX,
					'topics'     => array(),
				);
				$heading_map[ 0 ] = &$headings[ count( $headings ) - 1 ];
			}
			$heading_map[ 0 ]['topics'][] = array(
				'id'      => (int) $topic->id,
				'title'   => $topic->title,
				'detail'  => $topic->detail,
				'position' => (int) $topic->position,
			);
		}
	}

	// Sort headings by position and topics within each heading by position.
	usort( $headings, function ( $a, $b ) {
		return $a['position'] - $b['position'];
	} );

	foreach ( $headings as &$heading ) {
		usort( $heading['topics'], function ( $a, $b ) {
			return $a['position'] - $b['position'];
		} );
	}

	return $headings;
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
 * Create or update an outline item (heading or topic).
 *
 * @param array $args id, course_id, title, detail, is_heading, parent_id, group_title.
 * @return int|WP_Error Item id on success.
 */
function sp_save_curriculum( $args ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'id'         => 0,
			'course_id'  => 0,
			'title'      => '',
			'detail'     => '',
			'is_heading' => false,
			'parent_id'  => 0,
			'group_title' => '',
		)
	);

	$course_id  = (int) $args['course_id'];
	$title      = sanitize_text_field( $args['title'] );
	$detail     = sanitize_textarea_field( $args['detail'] );
	$is_heading = ! empty( $args['is_heading'] );
	$parent_id  = (int) $args['parent_id'];
	$group_title = $is_heading ? sanitize_text_field( $args['group_title'] ) : '';

	if ( '' === $title ) {
		return new WP_Error( 'sp_no_title', 'Enter a topic title.' );
	}

	if ( ! sp_get_course( $course_id ) ) {
		return new WP_Error( 'sp_no_course', 'That course no longer exists.' );
	}

	// If this is a heading, require a group_title.
	if ( $is_heading && '' === $group_title ) {
		return new WP_Error( 'sp_no_group_title', 'Enter a heading title.' );
	}

	// If this is a topic with a parent, validate the parent exists and belongs to this course.
	if ( ! $is_heading && $parent_id > 0 ) {
		$parent = sp_get_curriculum_item( $parent_id );

		if ( ! $parent ) {
			return new WP_Error( 'sp_no_parent', 'The selected heading no longer exists.' );
		}

		if ( (int) $parent->course_id !== $course_id ) {
			return new WP_Error( 'sp_wrong_course', 'That heading belongs to a different course.' );
		}
	}

	$data   = array(
		'course_id'  => $course_id,
		'title'      => $title,
		'detail'     => $detail ? $detail : null,
		'parent_id'  => $is_heading ? null : ( $parent_id > 0 ? $parent_id : null ),
		'group_title' => $is_heading ? $group_title : null,
	);
	$format = array( '%d', '%s', '%s', '%d', '%s' );
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

	$data['position']   = $position;
	$data['created_by'] = get_current_user_id();

	$wpdb->insert( sp_table( 'curriculum' ), $data, array_merge( $format, array( '%d', '%d' ) ) );

	$item_id = (int) $wpdb->insert_id;

	sp_log_activity( 'curriculum.created', 'course', $course_id, $title );

	return $item_id;
}

/**
 * Delete an outline item and every coverage row recorded against it.
 *
 * A heading's child topics are detached (parent_id -> null) rather than
 * deleted, so removing a section never silently destroys the lessons under it.
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

	// Detach any children first so they fall back to top level, not an orphan id.
	$wpdb->update(
		sp_table( 'curriculum' ),
		array( 'parent_id' => null ),
		array( 'parent_id' => (int) $item->id ),
		array( '%d' ),
		array( '%d' )
	);

	$wpdb->delete( sp_table( 'curriculum_progress' ), array( 'item_id' => (int) $item->id ), array( '%d' ) );
	$wpdb->delete( sp_table( 'curriculum' ), array( 'id' => (int) $item->id ), array( '%d' ) );

	sp_log_activity( 'curriculum.deleted', 'course', (int) $item->course_id, $item->title );

	return true;
}

/**
 * The heading title a topic sits under, resolved from its parent_id.
 *
 * Used by the wp-admin outline list to label nested topics. Returns an empty
 * string when the item has no parent or the parent no longer exists.
 *
 * @param int $parent_id Parent heading item id.
 * @return string
 */
function sp_curriculum_item_heading_title( $parent_id ) {
	$parent = $parent_id ? sp_get_curriculum_item( (int) $parent_id ) : null;

	if ( ! $parent ) {
		return '';
	}

	return ! empty( $parent->group_title ) ? (string) $parent->group_title : (string) $parent->title;
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
 * Tick a topic or heading as covered, or untick it.
 *
 * When a heading is marked, all topics beneath it are also marked (cascade).
 * When a heading is unmarked, all topics beneath it are also unmarked.
 * When a topic is marked/unmarked, any parent heading is updated to reflect
 * partial or full completion.
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

	$is_heading = ! empty( $item->group_title );

	if ( $is_heading ) {
		// Cascade: get all topic ids under this heading.
		$topic_ids = sp_get_topic_ids_for_heading( (int) $item_id );
		$all_ids   = array_merge( array( (int) $item_id ), $topic_ids );

		$existing = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . sp_table( 'curriculum_progress' ) . ' WHERE class_id = %d AND item_id IN (' . implode( ',', array_fill( 0, count( $all_ids ), '%d' ) ) . ')',
				(int) $class_id,
				...$all_ids
			)
		);

		if ( $existing ) {
			// Unmark the heading and all its topics.
			foreach ( $all_ids as $id ) {
				$wpdb->delete( sp_table( 'curriculum_progress' ), array( 'class_id' => (int) $class_id, 'item_id' => $id ), array( '%d', '%d' ) );
			}

			sp_log_activity( 'curriculum.unmarked', 'class', (int) $class_id, $item->title );

			return 'unmarked';
		}

		// Mark the heading and all its topics.
		$now = current_time( 'mysql' );
		foreach ( $all_ids as $id ) {
			$wpdb->insert(
				sp_table( 'curriculum_progress' ),
				array(
					'class_id'     => (int) $class_id,
					'item_id'      => $id,
					'completed_at' => $now,
					'completed_by' => (int) $user_id,
				),
				array( '%d', '%d', '%s', '%d' )
			);
		}

		sp_log_activity( 'curriculum.marked', 'class', (int) $class_id, $item->title );

		return 'marked';
	}

	// Regular topic toggle.
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
 * Get all topic ids that belong to a heading.
 *
 * @param int $heading_id Heading item id.
 * @return int[] List of topic ids.
 */
function sp_get_topic_ids_for_heading( $heading_id ) {
	global $wpdb;

	$table = sp_table( 'curriculum' );

	return $wpdb->get_col(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE parent_id = %d ORDER BY position ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
			(int) $heading_id
		)
	);
}

/**
 * Coverage totals for one class against its course's outline.
 * Counts topics only (not headings) towards the total.
 *
 * @param int $course_id Course id.
 * @param int $class_id  Class id.
 * @return array {total:int, done:int, percent:int}
 */
function sp_curriculum_summary( $course_id, $class_id ) {
	$items    = sp_get_curriculum( $course_id );
	$progress = sp_get_class_progress( $class_id );

	// Count only topics (those without group_title), not headings.
	$topics   = array_filter( $items, function ( $item ) {
		return empty( $item->group_title );
	} );
	$total    = count( $topics );
	$done     = 0;

	foreach ( $topics as $item ) {
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
