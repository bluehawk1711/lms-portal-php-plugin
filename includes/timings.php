<?php
/**
 * Weekly class timings and timing sets.
 *
 * Two layers, deliberately:
 *
 * - `sp_class_timings` holds a class's own timetable, one row per day it meets.
 *   This is what the class form edits.
 * - `sp_timing_sets` + `sp_timing_set_days` hold the reusable form of the same
 *   pattern. Two classes that meet identically share one set id, which is what
 *   `sp_classes.timings_id` points at, so "same schedule as that class" is a
 *   single indexed lookup rather than a row-by-row comparison.
 *
 * A set is created only when a class is saved; there is no separate screen for
 * them yet. Day numbers follow MySQL's DAYOFWEEK(): 0 = Sunday.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Full weekday names keyed by day number (0 = Sunday).
 *
 * @return array
 */
function sp_timing_days() {
	return array(
		0 => 'Sunday',
		1 => 'Monday',
		2 => 'Tuesday',
		3 => 'Wednesday',
		4 => 'Thursday',
		5 => 'Friday',
		6 => 'Saturday',
	);
}

/**
 * Three-letter weekday names, for labels and pattern names.
 *
 * @return array
 */
function sp_timing_days_short() {
	$short = array();

	foreach ( sp_timing_days() as $day => $name ) {
		$short[ $day ] = substr( $name, 0, 3 );
	}

	return $short;
}

/**
 * The timetable rows of a class, normalised to `HH:MM`.
 *
 * @param int $class_id Class id.
 * @return array List of day_of_week / start_time / end_time rows.
 */
function sp_get_class_timings( $class_id ) {
	global $wpdb;

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return array();
	}

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT day_of_week, start_time, end_time FROM ' . sp_table( 'class_timings' ) . ' WHERE class_id = %d ORDER BY day_of_week, start_time',
			$class_id
		),
		ARRAY_A
	);

	if ( ! $rows ) {
		return array();
	}

	$timings = array();

	foreach ( $rows as $row ) {
		$timings[] = array(
			'day_of_week' => (int) $row['day_of_week'],
			'start_time'  => substr( (string) $row['start_time'], 0, 5 ),
			'end_time'    => substr( (string) $row['end_time'], 0, 5 ),
		);
	}

	return $timings;
}

/**
 * Read the timing fields posted by a class form.
 *
 * Both shapes are accepted:
 *   - `timing_all[start|end]` — one daily timing, applied to every day of the
 *     week. This is the form both portals now render: the institute runs the
 *     same hours daily, so seven rows of identical input was busywork.
 *   - `timing[day][start|end]` — the legacy seven-row grid, still read so
 *     older markup and bookmarks keep saving the same way.
 *
 * Empty timings come through as no rows (or blank rows); validation happens in
 * sp_normalize_timing_rows() so one place owns the rules.
 *
 * @return array Raw rows: day_of_week, start_time, end_time.
 */
function sp_timing_rows_from_request() {
	$rows = array();

	// One daily timing, mirrored across all seven days.
	if ( isset( $_POST['timing_all'] ) && is_array( $_POST['timing_all'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by the dispatch.
		$all   = wp_unslash( $_POST['timing_all'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$start = isset( $all['start'] ) ? trim( sanitize_text_field( (string) $all['start'] ) ) : '';
		$end   = isset( $all['end'] ) ? trim( sanitize_text_field( (string) $all['end'] ) ) : '';

		if ( '' === $start && '' === $end ) {
			return $rows;
		}

		foreach ( array_keys( sp_timing_days() ) as $sp_day ) {
			$rows[] = array(
				'day_of_week' => (int) $sp_day,
				'start_time'  => $start,
				'end_time'    => $end,
				'daily'       => true,
			);
		}

		return $rows;
	}

	if ( empty( $_POST['timing'] ) || ! is_array( $_POST['timing'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by the dispatch.
		return $rows;
	}

	$timing = wp_unslash( $_POST['timing'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	foreach ( $timing as $day => $pair ) {
		$day = (int) $day;

		if ( $day < 0 || $day > 6 || ! is_array( $pair ) ) {
			continue;
		}

		$rows[] = array(
			'day_of_week' => $day,
			'start_time'  => isset( $pair['start'] ) ? trim( sanitize_text_field( (string) $pair['start'] ) ) : '',
			'end_time'    => isset( $pair['end'] ) ? trim( sanitize_text_field( (string) $pair['end'] ) ) : '',
		);
	}

	return $rows;
}

/**
 * Validate, complete and order timing rows.
 *
 * A blank start *and* end means the class does not meet that day and the row is
 * dropped. A blank half of a pair is a mistake, not an absence.
 *
 * @param array $rows Raw rows as posted or already stored.
 * @return array|WP_Error Cleaned rows on success.
 */
function sp_normalize_timing_rows( $rows ) {
	$clean = array();

	if ( ! is_array( $rows ) ) {
		return new WP_Error( 'sp_bad_timings', 'Weekly timings were not in the expected format.' );
	}

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['day_of_week'] ) ) {
			continue;
		}

		$day = (int) $row['day_of_week'];

		if ( $day < 0 || $day > 6 ) {
			continue;
		}

		$start = isset( $row['start_time'] ) ? trim( (string) $row['start_time'] ) : '';
		$end   = isset( $row['end_time'] ) ? trim( (string) $row['end_time'] ) : '';

		if ( '' === $start && '' === $end ) {
			continue;
		}

		$label = sp_timing_days()[ $day ];

		// Daily rows carry their own wording: one pair stands for the whole week,
		// so naming a weekday would send the admin looking at the wrong field.
		if ( ! empty( $row['daily'] ) ) {
			$label = 'the daily timing';
		}

		if ( '' === $start || '' === $end ) {
			return new WP_Error(
				'sp_timing_half_filled',
				sprintf( 'Fill in both times for %s, or leave both blank.', $label )
			);
		}

		$start = sp_sanitize_time( $start );
		$end   = sp_sanitize_time( $end );

		if ( false === $start || false === $end ) {
			return new WP_Error(
				'sp_timing_bad_time',
				sprintf( '%s does not hold a valid start and end time.', $label )
			);
		}

		if ( $end <= $start ) {
			return new WP_Error(
				'sp_timing_order',
				sprintf( 'The end time for %s must be after the start time.', $label )
			);
		}

		$clean[ $day . ' ' . $start ] = array(
			'day_of_week' => $day,
			'start_time'  => $start,
			'end_time'    => $end,
		);
	}

	$clean = array_values( $clean );

	usort(
		$clean,
		function ( $a, $b ) {
			if ( $a['day_of_week'] === $b['day_of_week'] ) {
				return strcmp( $a['start_time'], $b['start_time'] );
			}

			return $a['day_of_week'] < $b['day_of_week'] ? -1 : 1;
		}
	);

	return $clean;
}

/**
 * Normalise a time string to `H:i`, or false when it is not a clock time.
 *
 * @param string $value Raw time, e.g. "18:00" or "18:00:00".
 * @return string|false
 */
function sp_sanitize_time( $value ) {
	$value = (string) $value;

	if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value ) ) {
		return false;
	}

	return substr( $value, 0, 5 );
}

/**
 * Human clock label for a stored `HH:MM` time, in the site's time format.
 *
 * @param string $value Raw time, e.g. "13:00".
 * @return string '' when the value is not a clock time.
 */
function sp_format_clock_time( $value ) {
	$value = sp_sanitize_time( $value );

	if ( ! $value ) {
		return '';
	}

	return date_i18n( get_option( 'time_format' ), (int) strtotime( '2000-01-01 ' . $value ) );
}

/**
 * The hours a class runs on a given weekday.
 *
 * Returns that weekday's own row when it has one. A class stored with a single
 * daily timing (the same start and end every row) also covers weekdays without
 * their own row; anything else means the class does not meet that day, and
 * null is returned so the caller can say so instead of inventing hours.
 *
 * @param int $class_id Class id.
 * @param int $day      Weekday, 0 (Sunday) to 6.
 * @return array|null { day_of_week, start_time, end_time } or null.
 */
function sp_class_timing_for_weekday( $class_id, $day ) {
	$rows = sp_get_class_timings( $class_id );

	if ( ! $rows ) {
		return null;
	}

	$day = (int) $day;

	foreach ( $rows as $row ) {
		if ( (int) $row['day_of_week'] === $day ) {
			return $row;
		}
	}

	$first = $rows[0];

	foreach ( $rows as $row ) {
		if ( $row['start_time'] !== $first['start_time'] || $row['end_time'] !== $first['end_time'] ) {
			return null;
		}
	}

	return $first;
}

/**
 * Replace a class's timetable and point the class at its timing set.
 *
 * @param int   $class_id Class id.
 * @param array $rows     Raw rows; validated here.
 * @return int|WP_Error Number of rows saved on success.
 */
function sp_save_class_timings( $class_id, $rows ) {
	global $wpdb;

	$class_id = (int) $class_id;
	$class    = $class_id > 0 ? sp_get_class( $class_id ) : null;

	if ( ! $class ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	$rows = sp_normalize_timing_rows( $rows );

	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	$set_id = 0;

	if ( $rows ) {
		$set_id = sp_upsert_timing_set( $rows );

		if ( is_wp_error( $set_id ) ) {
			return $set_id;
		}
	}

	$wpdb->delete( sp_table( 'class_timings' ), array( 'class_id' => $class_id ), array( '%d' ) );

	foreach ( $rows as $row ) {
		$saved = $wpdb->insert(
			sp_table( 'class_timings' ),
			array(
				'class_id'    => $class_id,
				'day_of_week' => (int) $row['day_of_week'],
				'start_time'  => $row['start_time'],
				'end_time'    => $row['end_time'],
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( ! $saved ) {
			return new WP_Error(
				'sp_timing_failed',
				'The weekly timings could not be saved' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' )
			);
		}
	}

	if ( $set_id > 0 ) {
		$wpdb->update(
			sp_table( 'classes' ),
			array( 'timings_id' => $set_id ),
			array( 'id' => $class_id ),
			array( '%d' ),
			array( '%d' )
		);
	} else {
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from identifiers only.
			$wpdb->prepare(
				'UPDATE ' . sp_table( 'classes' ) . ' SET timings_id = NULL WHERE id = %d',
				$class_id
			)
		);
	}

	sp_log_activity(
		$rows ? 'class.timings_saved' : 'class.timings_cleared',
		'class',
		$class_id,
		$rows ? sp_timing_set_label( $rows ) : 'no weekly timings'
	);

	return count( $rows );
}

/**
 * A stable signature for a set of timing rows, used to find an identical set.
 *
 * Works from both stored rows (`18:00:00`) and form values (`18:00`).
 *
 * @param array $rows Timing rows.
 * @return string
 */
function sp_timing_pattern_key( $rows ) {
	$parts = array();

	foreach ( (array) $rows as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['day_of_week'] ) ) {
			continue;
		}

		$parts[] = (int) $row['day_of_week'] . ':' . substr( (string) $row['start_time'], 0, 5 ) . ':' . substr( (string) $row['end_time'], 0, 5 );
	}

	sort( $parts );

	return implode( '|', $parts );
}

/**
 * A human label for a timing pattern, e.g. "Mon, Wed, Fri · 18:00–19:30".
 *
 * @param array $rows Timing rows.
 * @return string
 */
function sp_timing_set_label( $rows ) {
	$rows = (array) $rows;

	if ( ! $rows ) {
		return '';
	}

	$short  = sp_timing_days_short();
	$uniform = true;

	foreach ( $rows as $row ) {
		if ( $row['start_time'] !== $rows[0]['start_time'] || $row['end_time'] !== $rows[0]['end_time'] ) {
			$uniform = false;
			break;
		}
	}

	$label = array();

	foreach ( $rows as $row ) {
		$day  = $short[ (int) $row['day_of_week'] ];
		$span = $row['start_time'] . '–' . $row['end_time'];

		$label[] = $uniform ? $day : $day . ' ' . $span;
	}

	$text = implode( ', ', $label );

	if ( $uniform ) {
		$text .= ' · ' . $rows[0]['start_time'] . '–' . $rows[0]['end_time'];
	}

	return $text;
}

/**
 * The id of an existing timing set with this exact pattern, or a new set.
 *
 * Patterns are compared as signatures rather than per-row SQL so an identical
 * set is reused no matter how many classes already carry it.
 *
 * @param array $rows Timing rows.
 * @return int|WP_Error Set id on success.
 */
function sp_upsert_timing_set( $rows ) {
	global $wpdb;

	$rows = sp_normalize_timing_rows( $rows );

	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	if ( ! $rows ) {
		return 0;
	}

	$key   = sp_timing_pattern_key( $rows );
	$sets  = sp_table( 'timing_sets' );
	$days  = sp_table( 'timing_set_days' );
	$found = $wpdb->get_results(
		'SELECT s.id, GROUP_CONCAT( CONCAT( d.day_of_week, \':\', LEFT( d.start_time, 5 ), \':\', LEFT( d.end_time, 5 ) ) ORDER BY d.day_of_week, d.start_time, d.end_time SEPARATOR \'|\' ) AS pattern
		FROM ' . $sets . ' s
		LEFT JOIN ' . $days . ' d ON d.set_id = s.id
		GROUP BY s.id'
	); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers only.

	foreach ( (array) $found as $set ) {
		if ( (string) $set->pattern === $key ) {
			return (int) $set->id;
		}
	}

	$inserted = $wpdb->insert(
		$sets,
		array(
			'name'       => sp_timing_set_label( $rows ),
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		),
		array( '%s', '%d', '%s' )
	);

	$set_id = (int) $wpdb->insert_id;

	if ( ! $inserted || ! $set_id ) {
		return new WP_Error(
			'sp_timing_set_failed',
			'The timing set could not be created' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' )
		);
	}

	foreach ( $rows as $row ) {
		$saved = $wpdb->insert(
			$days,
			array(
				'set_id'      => $set_id,
				'day_of_week' => (int) $row['day_of_week'],
				'start_time'  => $row['start_time'],
				'end_time'    => $row['end_time'],
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( ! $saved ) {
			// Never leave a half-built set behind for someone else to match.
			$wpdb->delete( $days, array( 'set_id' => $set_id ), array( '%d' ) );
			$wpdb->delete( $sets, array( 'id' => $set_id ), array( '%d' ) );

			return new WP_Error(
				'sp_timing_set_failed',
				'The timing set could not be created' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' )
			);
		}
	}

	return $set_id;
}

/**
 * How many other classes share a timing set.
 *
 * @param int $set_id          Timing set id.
 * @param int $exclude_class_id Class to leave out of the count.
 * @return int
 */
function sp_count_classes_with_timings( $set_id, $exclude_class_id = 0 ) {
	global $wpdb;

	$set_id = (int) $set_id;

	if ( $set_id <= 0 ) {
		return 0;
	}

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . sp_table( 'classes' ) . ' WHERE timings_id = %d AND id <> %d',
			$set_id,
			(int) $exclude_class_id
		)
	);
}
