<?php
/**
 * Calendar events: the shared school calendar that cancels class.
 *
 * A holiday or test entry — global or scoped to one class — means no class
 * runs on that date: registers cannot be opened, the auto-absent run skips
 * it, and existing sessions drop out of attendance percentages. Exam and
 * event entries are informational only; they are shown but cancel nothing.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All event types the calendar accepts.
 *
 * @return string[]
 */
function sp_calendar_event_types() {
	return array( 'holiday', 'test', 'exam', 'event' );
}

/**
 * The event types that cancel class (plan rule: holiday or test).
 *
 * @return string[]
 */
function sp_calendar_cancel_types() {
	return array( 'holiday', 'test' );
}

/**
 * Scopes an event may carry.
 *
 * @return string[]
 */
function sp_calendar_scopes() {
	return array( 'global', 'class' );
}

/**
 * Fetch calendar events.
 *
 * @param array $args date_from, date_to (Y-m-d), scope, type, class_ids
 *                    (array — global events plus events of these classes),
 *                    limit, order (ASC|DESC).
 * @return object[]
 */
function sp_get_calendar_events( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'date_from' => '',
			'date_to'   => '',
			'scope'     => '',
			'type'      => '',
			'class_ids' => array(),
			'limit'     => 0,
			'order'     => 'ASC',
		)
	);

	$table  = sp_table( 'calendar_events' );
	$where  = array( '1=1' );
	$params = array();

	$date_from = sp_sanitize_date( $args['date_from'] );
	$date_to   = sp_sanitize_date( $args['date_to'] );

	if ( '' !== $date_from ) {
		$where[] = 'event_date >= %s';
		$params[] = $date_from;
	}

	if ( '' !== $date_to ) {
		$where[] = 'event_date <= %s';
		$params[] = $date_to;
	}

	$scope = sanitize_key( $args['scope'] );

	if ( in_array( $scope, sp_calendar_scopes(), true ) ) {
		$where[]  = 'scope = %s';
		$params[] = $scope;
	}

	$type = sanitize_key( $args['type'] );

	if ( in_array( $type, sp_calendar_event_types(), true ) ) {
		$where[]  = 'type = %s';
		$params[] = $type;
	}

	$class_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $args['class_ids'] ) ) ) );

	if ( $class_ids ) {
		$placeholders = implode( ', ', array_fill( 0, count( $class_ids ), '%d' ) );
		$where[]      = "( scope = 'global' OR ( scope = 'class' AND class_id IN ( {$placeholders} ) ) )";
		$params       = array_merge( $params, $class_ids );
	}

	$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
	$sql   = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY event_date {$order}, id {$order}";

	if ( (int) $args['limit'] > 0 ) {
		$sql     .= ' LIMIT %d';
		$params[] = (int) $args['limit'];
	}

	if ( ! $params ) {
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no placeholders in the static case.
	}

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Fetch one calendar event.
 *
 * @param int $event_id Event id.
 * @return object|null
 */
function sp_get_calendar_event( $event_id ) {
	global $wpdb;

	$event_id = (int) $event_id;

	if ( $event_id <= 0 ) {
		return null;
	}

	$table = sp_table( 'calendar_events' );

	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $event_id ) );
}

/**
 * Save a calendar event (create or update).
 *
 * @param array $args id (0 to create), event_date, type, scope, class_id,
 *                    title, note, created_by.
 * @return int|WP_Error Event id.
 */
function sp_save_calendar_event( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'id'         => 0,
			'event_date' => '',
			'type'       => 'event',
			'scope'      => 'global',
			'class_id'   => 0,
			'title'      => '',
			'note'       => '',
			'created_by' => 0,
		)
	);

	$event_date = sp_sanitize_date( $args['event_date'] );

	if ( '' === $event_date ) {
		return new WP_Error( 'sp_bad_date', 'Pick a valid date for the calendar entry.' );
	}

	$type = sanitize_key( $args['type'] );

	if ( ! in_array( $type, sp_calendar_event_types(), true ) ) {
		return new WP_Error( 'sp_bad_type', 'Choose holiday, test, exam or event.' );
	}

	$scope = sanitize_key( $args['scope'] );

	if ( ! in_array( $scope, sp_calendar_scopes(), true ) ) {
		return new WP_Error( 'sp_bad_scope', 'Choose whether the entry applies to the whole portal or one class.' );
	}

	$title = sanitize_text_field( $args['title'] );

	if ( '' === $title ) {
		return new WP_Error( 'sp_missing_title', 'Give the calendar entry a title.' );
	}

	$class_id = (int) $args['class_id'];

	if ( 'class' === $scope ) {
		if ( $class_id <= 0 || ! sp_get_class( $class_id ) ) {
			return new WP_Error( 'sp_bad_class', 'Pick a real class for a class-scoped entry.' );
		}
	} else {
		$class_id = 0;
	}

	$note       = sanitize_textarea_field( $args['note'] );
	$created_by = (int) $args['created_by'] ? (int) $args['created_by'] : get_current_user_id();
	$event_id   = (int) $args['id'];

	$row = array(
		'scope'      => $scope,
		'class_id'   => $class_id,
		'event_date' => $event_date,
		'type'       => $type,
		'title'      => $title,
		'note'       => '' !== $note ? $note : null,
	);

	$formats = array( '%s', '%d', '%s', '%s', '%s', '%s' );

	if ( $event_id > 0 ) {
		$updated = $wpdb->update(
			sp_table( 'calendar_events' ),
			$row,
			array( 'id' => $event_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'sp_save_failed', 'That calendar entry could not be saved.' );
		}

		sp_log_activity( 'calendar_event.updated', 'calendar_event', $event_id, sprintf( '%s — %s (%s)', $event_date, $title, $type ) );

		return $event_id;
	}

	$row['created_by'] = $created_by;
	$formats[]         = '%d';

	$inserted = $wpdb->insert( sp_table( 'calendar_events' ), $row, $formats );

	if ( ! $inserted ) {
		return new WP_Error( 'sp_save_failed', 'That calendar entry could not be saved.' );
	}

	$event_id = (int) $wpdb->insert_id;

	sp_log_activity( 'calendar_event.created', 'calendar_event', $event_id, sprintf( '%s — %s (%s)', $event_date, $title, $type ) );

	return $event_id;
}

/**
 * Delete a calendar event.
 *
 * @param int $event_id Event id.
 * @return bool True when a row was removed.
 */
function sp_delete_calendar_event( $event_id ) {
	global $wpdb;

	$event_id = (int) $event_id;

	if ( $event_id <= 0 ) {
		return false;
	}

	$table = sp_table( 'calendar_events' );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT event_date, title FROM {$table} WHERE id = %d", $event_id ) );

	if ( ! $row ) {
		return false;
	}

	$wpdb->delete( $table, array( 'id' => $event_id ), array( '%d' ) );

	sp_log_activity( 'calendar_event.deleted', 'calendar_event', $event_id, sprintf( '%s — %s', $row->event_date, $row->title ) );

	return true;
}

/**
 * Does a date cancel class?
 *
 * True when the calendar carries a holiday or test for it (global, or
 * scoped to this class) or when every teacher assigned to the class is
 * marked absent or on leave that day. Off days are handled separately by
 * sp_is_off_day() — weekly rhythm, not calendar data.
 *
 * @param string $date     Y-m-d.
 * @param int    $class_id Class id; 0 checks global entries only.
 * @return bool
 */
function sp_date_cancels_class( $date, $class_id = 0 ) {
	global $wpdb;

	$date = sp_sanitize_date( $date );

	if ( '' === $date ) {
		return false;
	}

	$class_id = (int) $class_id;

	$types        = sp_calendar_cancel_types();
	$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
	$table        = sp_table( 'calendar_events' );

	$params = array_merge( array( $date ), $types, array( $class_id ) );

	$hit = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table}
			 WHERE event_date = %s AND type IN ( {$placeholders} )
			   AND ( scope = 'global' OR ( scope = 'class' AND class_id = %d ) )
			 LIMIT 1",
			$params
		)
	);

	if ( (bool) $hit ) {
		return true;
	}

	return $class_id > 0 && sp_class_teachers_unavailable( $date, $class_id );
}

/**
 * Every date on which a class is cancelled, for stats exclusion.
 *
 * @param int $class_id Class id.
 * @return string[] Y-m-d list.
 */
function sp_cancelled_class_dates( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return array();
	}

	$types        = sp_calendar_cancel_types();
	$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
	$table        = sp_table( 'calendar_events' );

	$dates = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT event_date FROM {$table}
			 WHERE type IN ( {$placeholders} )
			   AND ( scope = 'global' OR ( scope = 'class' AND class_id = %d ) )",
			array_merge( $types, array( $class_id ) )
		)
	);

	$dates = array_map( 'strval', (array) $dates );

	$unavailable = sp_teacher_unavailable_dates( $class_id );

	if ( $unavailable ) {
		$dates = array_unique( array_merge( $dates, $unavailable ) );
	}

	$dates = array_values( $dates );
	sort( $dates );

	return $dates;
}
