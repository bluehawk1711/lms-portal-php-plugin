<?php
/**
 * Shared schedule UI: the timetable-style calendar.
 *
 * One component serves three surfaces - the admin Calendar screen (editable,
 * with the per-date sidebar), and the student and teacher portal Calendar
 * pages (read-only). The markup and behaviour live here; the styles live in
 * assets/css/schedule.css, enqueued alongside whichever page includes it.
 *
 * Two kinds of data sit on the grid:
 *
 * - Calendar events are all-day (holiday, test, exam, event) and carry no
 *   clock times, so they render as coloured chips in each day's all-day strip.
 * - The timed blocks come from each class's weekly timetable
 *   (sp_class_timings), positioned on the hour rail exactly like the schedule
 *   mockup. A date on which class is cancelled still shows its blocks, dimmed,
 *   beside the reason.
 *
 * Day, Week, Month and Year are server-rendered links - no JavaScript is
 * required to browse. The only script (assets/js/schedule.js, admin only)
 * upgrades the edit icons and the sidebar to fetch-on-click.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The views the toolbar offers, in display order.
 *
 * @return string[]
 */
function sp_schedule_views() {
	return array( 'day', 'week', 'month', 'year' );
}

/**
 * Event type labels keyed by stored type.
 *
 * @return array
 */
function sp_schedule_type_labels() {
	return array(
		'holiday' => 'Holiday',
		'test'    => 'Test',
		'exam'    => 'Exam',
		'event'   => 'Event',
	);
}

/**
 * Read the view and focus date from the request, with safe defaults.
 *
 * @return array{view: string, date: string} View slug and Y-m-d focus date.
 */
function sp_schedule_request() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'week';
	$date = isset( $_GET['date'] ) ? sp_sanitize_date( wp_unslash( $_GET['date'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( ! in_array( $view, sp_schedule_views(), true ) ) {
		$view = 'week';
	}

	if ( '' === $date ) {
		$date = current_time( 'Y-m-d' );
	}

	return array(
		'view' => $view,
		'date' => $date,
	 );
}

/**
 * A schedule URL on a given base, carrying view and date.
 *
 * @param string $base Base URL (admin screen or portal route).
 * @param array  $args Query arguments; view and date override the base's own.
 * @return string
 */
function sp_schedule_url( $base, $args = array() ) {
	return add_query_arg( $args, $base );
}

/**
 * The date range a view covers, and the dates its grid walks.
 *
 * The week runs Sunday to Saturday, matching the schedule mockup. The month
 * range covers the whole grid: leading days from the previous month and
 * trailing days from the next one, so every row is a full week.
 *
 * @param string $view View slug.
 * @param string $date Focus date (Y-m-d).
 * @return array{from: string, to: string, days: string[], month: int, year: int}
 */
function sp_schedule_range( $view, $date ) {
	$ts    = (int) strtotime( $date );
	$days  = array();
	$month = (int) date( 'n', $ts );
	$year  = (int) date( 'Y', $ts );

	if ( 'day' === $view ) {
		$from = $to = date( 'Y-m-d', $ts );
		$days = array( $from );
	} elseif ( 'week' === $view ) {
		$start = $ts - ( (int) date( 'w', $ts ) * DAY_IN_SECONDS );
		$start = (int) strtotime( date( 'Y-m-d', $start ) );

		for ( $i = 0; $i < 7; $i++ ) {
			$days[] = date( 'Y-m-d', strtotime( "+{$i} days", $start ) );
		}

		$from = $days[0];
		$to   = $days[6];
	} elseif ( 'month' === $view ) {
		$first = (int) mktime( 0, 0, 0, $month, 1, $year );
		$lead  = (int) date( 'w', $first );
		$total = (int) date( 't', $first );
		$cells = (int) ceil( ( $lead + $total ) / 7 ) * 7;
		$start = $first - ( $lead * DAY_IN_SECONDS );
		$start = (int) strtotime( date( 'Y-m-d', $start ) );

		for ( $i = 0; $i < $cells; $i++ ) {
			$days[] = date( 'Y-m-d', strtotime( "+{$i} days", $start ) );
		}

		$from = $days[0];
		$to   = $days[ count( $days ) - 1 ];
	} else {
		$first = (int) mktime( 0, 0, 0, 1, 1, $year );
		$last  = (int) mktime( 0, 0, 0, 12, 31, $year );
		$from  = date( 'Y-m-d', $first );
		$to    = date( 'Y-m-d', $last );
		$days  = array( $from );
	}

	return array(
		'from'  => $from,
		'to'    => $to,
		'days'  => $days,
		'month' => $month,
		'year'  => $year,
	);
}

/**
 * The heading for a view's toolbar.
 *
 * @param string $view View slug.
 * @param string $date Focus date (Y-m-d).
 * @return string
 */
function sp_schedule_title( $view, $date ) {
	$ts = (int) strtotime( $date );

	if ( 'day' === $view ) {
		return date_i18n( 'l, j F Y', $ts );
	}

	if ( 'month' === $view ) {
		return date_i18n( 'F Y', $ts );
	}

	if ( 'year' === $view ) {
		return date_i18n( 'Y', $ts );
	}

	// Week: "13 - 19 April 2025", "30 Apr - 6 May 2025", "28 Dec 2025 - 3 Jan 2026".
	$range = sp_schedule_range( 'week', $date );
	$start = (int) strtotime( $range['from'] );
	$end   = (int) strtotime( $range['to'] );

	if ( date( 'Y', $start ) === date( 'Y', $end ) && date( 'n', $start ) === date( 'n', $end ) ) {
		return date_i18n( 'j', $start ) . ' - ' . date_i18n( 'j F Y', $end );
	}

	if ( date( 'Y', $start ) === date( 'Y', $end ) ) {
		return date_i18n( 'j M', $start ) . ' - ' . date_i18n( 'j F Y', $end );
	}

	return date_i18n( 'j M Y', $start ) . ' - ' . date_i18n( 'j F Y', $end );
}

/**
 * The focus date one step before or after the current one.
 *
 * @param string $view View slug.
 * @param string $date Focus date (Y-m-d).
 * @param int    $dir  +1 forward, -1 back.
 * @return string Y-m-d.
 */
function sp_schedule_shift( $view, $date, $dir ) {
	$dir = $dir < 0 ? -1 : 1;
	$ts  = (int) strtotime( $date );

	if ( 'day' === $view ) {
		return date( 'Y-m-d', strtotime( $dir > 0 ? '+1 day' : '-1 day', $ts ) );
	}

	if ( 'week' === $view ) {
		return date( 'Y-m-d', strtotime( ( $dir > 0 ? '+' : '-' ) . '7 days', $ts ) );
	}

	if ( 'month' === $view ) {
		return date(
			'Y-m-d',
			mktime( 0, 0, 0, (int) date( 'n', $ts ) + $dir, 1, (int) date( 'Y', $ts ) )
		);
	}

	return date(
		'Y-m-d',
		mktime(
			0,
			0,
			0,
			(int) date( 'n', $ts ),
			min( (int) date( 'j', $ts ), (int) date( 't', mktime( 0, 0, 0, (int) date( 'n', $ts ), 1, (int) date( 'Y', $ts ) + $dir ) ) ),
			(int) date( 'Y', $ts ) + $dir
		)
	);
}

/**
 * Which classes a schedule context may see.
 *
 * @param string $context 'admin', 'student' or 'teacher'.
 * @return array{all: bool, class_ids: int[]} `all` means no SQL filter (the
 *     admin sees every event); otherwise class_ids filters to global plus
 *     those classes, with array(0) meaning "global events only".
 */
function sp_schedule_scope( $context ) {
	if ( 'student' === $context ) {
		$profile      = sp_get_profile_by_user_id( get_current_user_id() );
		$enrollments  = $profile ? sp_get_enrollments_for_profile( $profile->id, array( 'active' ) ) : array();
		$class_ids    = $enrollments ? array_map( 'intval', wp_list_pluck( $enrollments, 'class_id' ) ) : array();

		return array(
			'all'       => false,
			'class_ids' => $class_ids ? $class_ids : array( 0 ),
		);
	}

	if ( 'teacher' === $context ) {
		$teacher  = sp_get_teacher_by_user_id( get_current_user_id() );
		$classes  = $teacher
			? sp_get_classes(
				array(
					'teacher_id' => (int) $teacher->id,
					'status'     => 'active',
					'per_page'   => 1000,
				)
			)
			: array();
		$class_ids = $classes ? array_map( 'intval', wp_list_pluck( $classes, 'id' ) ) : array();

		return array(
			'all'       => false,
			'class_ids' => $class_ids ? $class_ids : array( 0 ),
		);
	}

	return array(
		'all'       => true,
		'class_ids' => array(),
	);
}

/**
 * Every active class id, for the admin's all-classes schedule.
 *
 * @return int[]
 */
function sp_schedule_active_class_ids() {
	$classes = sp_get_classes(
		array(
			'status'   => 'active',
			'per_page' => 1000,
		)
	);

	return $classes ? array_map( 'intval', wp_list_pluck( $classes, 'id' ) ) : array();
}

/**
 * Weekly timetable rows for a set of classes, grouped by weekday.
 *
 * @param int[] $class_ids Class ids.
 * @return array<int, object[]> Keyed by day_of_week (0 = Sunday).
 */
function sp_schedule_blocks( $class_ids ) {
	global $wpdb;

	$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $class_ids ) ) ) );

	if ( ! $ids ) {
		return array();
	}

	$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

	$sql = 'SELECT t.class_id, t.day_of_week,
				LEFT( t.start_time, 5 ) AS start_time,
				LEFT( t.end_time, 5 )   AS end_time,
				c.title, c.code
			FROM ' . sp_table( 'class_timings' ) . ' t
			INNER JOIN ' . sp_table( 'classes' ) . " c ON c.id = t.class_id
			WHERE t.class_id IN ( {$placeholders} )
			ORDER BY t.day_of_week, t.start_time, t.end_time";

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.

	$by_day = array();

	foreach ( (array) $rows as $row ) {
		$by_day[ (int) $row->day_of_week ][] = $row;
	}

	return $by_day;
}

/**
 * "HH:MM" as minutes past midnight.
 *
 * @param string $time Clock time.
 * @return int
 */
function sp_schedule_minutes( $time ) {
	$parts = explode( ':', (string) $time );

	if ( count( $parts ) < 2 ) {
		return 0;
	}

	return ( (int) $parts[0] ) * 60 + (int) $parts[1];
}

/**
 * Column colours for class blocks, keyed by class id.
 *
 * Pastel fills with a darker edge, the palette the schedule mockup uses.
 *
 * @param int $class_id Class id.
 * @return array{0: string, 1: string} Background and accent.
 */
function sp_schedule_block_colors( $class_id ) {
	$palette = array(
		array( '#d9e8fd', '#2563eb' ),
		array( '#ffe6cc', '#ea580c' ),
		array( '#d7f5dd', '#16a34a' ),
		array( '#ece0fb', '#7c3aed' ),
		array( '#fde2ec', '#db2777' ),
		array( '#cffafe', '#0891b2' ),
	);

	return $palette[ absint( $class_id ) % count( $palette ) ];
}

/**
 * Class titles for the scope selector, cached per request.
 *
 * @param int $class_id Class id.
 * @return string
 */
function sp_schedule_class_title( $class_id ) {
	static $cache = array();

	$class_id = (int) $class_id;

	if ( $class_id <= 0 ) {
		return '';
	}

	if ( ! isset( $cache[ $class_id ] ) ) {
		$class     = sp_get_class( $class_id );
		$cache[ $class_id ] = $class ? (string) $class->title : '';
	}

	return $cache[ $class_id ];
}

/**
 * Group a date range's calendar events by date.
 *
 * @param string $from      Range start (Y-m-d).
 * @param string $to        Range end (Y-m-d).
 * @param array  $scope     From sp_schedule_scope().
 * @return array<string, object[]> Keyed by event_date.
 */
function sp_schedule_events_by_date( $from, $to, $scope ) {
	$args = array(
		'date_from' => $from,
		'date_to'   => $to,
		'order'     => 'ASC',
	);

	if ( empty( $scope['all'] ) ) {
		$args['class_ids'] = $scope['class_ids'];
	}

	$by_date = array();

	foreach ( sp_get_calendar_events( $args ) as $event ) {
		$by_date[ $event->event_date ][] = $event;
	}

	return $by_date;
}

/**
 * Assign overlapping blocks to side-by-side lanes.
 *
 * @param object[] $rows Timetable rows for one day, start-time ordered.
 * @return array{lane: int[], total: int} Lane index per row and lane count.
 */
function sp_schedule_lanes( $rows ) {
	$lane_of  = array();
	$lane_end = array();

	foreach ( $rows as $i => $row ) {
		$start = sp_schedule_minutes( $row->start_time );
		$end   = sp_schedule_minutes( $row->end_time );
		$place = -1;

		foreach ( $lane_end as $li => $busy_until ) {
			if ( $busy_until <= $start ) {
				$lane_end[ $li ] = $end;
				$place           = $li;
				break;
			}
		}

		if ( $place < 0 ) {
			$place               = count( $lane_end );
			$lane_end[ $place ]  = $end;
		}

		$lane_of[ $i ] = $place;
	}

	return array(
		'lane'  => $lane_of,
		'total' => max( 1, count( $lane_end ) ),
	);
}

/**
 * Render the whole schedule: toolbar, grid, legend and (admin) the sidebar.
 *
 * @param array $args {
 *     @type bool   $editable  Show edit icons and the sidebar (admin).
 *     @type string $context   'admin', 'student' or 'teacher'.
 *     @type string $base_url  URL the toolbar and edit links build on.
 *     @type string $view      View slug; empty reads the request.
 *     @type string $date      Focus date; empty reads the request.
 *     @type string $open_date Date whose sidebar starts open (admin).
 *     @type int    $event_id  Event the sidebar starts editing (admin).
 * }
 */
function sp_render_schedule( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'editable'  => false,
			'context'   => 'admin',
			'base_url'  => admin_url( 'admin.php?page=sp-calendar' ),
			'view'      => '',
			'date'      => '',
			'open_date' => '',
			'event_id'  => 0,
		)
	);

	$request = sp_schedule_request();
	$view    = '' !== $args['view'] ? $args['view'] : $request['view'];
	$date    = '' !== $args['date'] ? sp_sanitize_date( $args['date'] ) : $request['date'];

	if ( ! in_array( $view, sp_schedule_views(), true ) ) {
		$view = 'week';
	}

	if ( '' === $date ) {
		$date = current_time( 'Y-m-d' );
	}

	$editable   = (bool) $args['editable'];
	$context    = $args['context'];
	$base       = $args['base_url'];
	$scope      = sp_schedule_scope( $context );
	$range      = sp_schedule_range( $view, $date );
	$events     = sp_schedule_events_by_date( $range['from'], $range['to'], $scope );
	$labels     = sp_schedule_type_labels();
	$today      = current_time( 'Y-m-d' );
	$timed_view = in_array( $view, array( 'day', 'week' ), true );

	$open_date = $editable ? sp_sanitize_date( $args['open_date'] ) : '';
	$event_id  = $editable ? (int) $args['event_id'] : 0;
	$side_open = ( '' !== $open_date || $event_id > 0 );

	$blocks = array();

	if ( $timed_view ) {
		$block_ids = empty( $scope['all'] ) ? $scope['class_ids'] : sp_schedule_active_class_ids();
		$blocks    = sp_schedule_blocks( $block_ids );
	}

	// The hour rail spans the earliest start to the latest end of what is
	// shown, floored and ceilinged to whole hours; 08:00-22:00 when empty.
	$axis_min = 8 * 60;
	$axis_max = 22 * 60;
	$found    = false;

	if ( $timed_view ) {
		foreach ( $range['days'] as $day ) {
			$dow = (int) date( 'w', (int) strtotime( $day ) );

			foreach ( isset( $blocks[ $dow ] ) ? $blocks[ $dow ] : array() as $row ) {
				$axis_min = $found ? min( $axis_min, sp_schedule_minutes( $row->start_time ) ) : sp_schedule_minutes( $row->start_time );
				$axis_max = $found ? max( $axis_max, sp_schedule_minutes( $row->end_time ) ) : sp_schedule_minutes( $row->end_time );
				$found    = true;
			}
		}

		if ( $found ) {
			$axis_min = (int) floor( $axis_min / 60 ) * 60;
			$axis_max = (int) ceil( $axis_max / 60 ) * 60;

			if ( $axis_max <= $axis_min ) {
				$axis_max = $axis_min + 60;
			}
		}
	}

	$axis_hours = max( 1, (int) ( ( $axis_max - $axis_min ) / 60 ) );
	$prev_date  = sp_schedule_shift( $view, $date, -1 );
	$next_date  = sp_schedule_shift( $view, $date, 1 );
	?>
	<div class="sp-sch" data-view="<?php echo esc_attr( $view ); ?>"
		data-date="<?php echo esc_attr( $date ); ?>" data-base="<?php echo esc_url( $base ); ?>">

		<div class="sp-sch-toolbar">
			<div class="sp-sch-nav">
				<a class="sp-sch-arrow" href="<?php echo esc_url( sp_schedule_url( $base, array( 'view' => $view, 'date' => $prev_date ) ) ); ?>"
					aria-label="<?php echo esc_attr__( 'Previous period', 'student-portal' ); ?>" title="<?php echo esc_attr__( 'Previous', 'student-portal' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m14 6-6 6 6 6" /></svg>
				</a>
				<span class="sp-sch-title"><?php echo esc_html( sp_schedule_title( $view, $date ) ); ?></span>
				<a class="sp-sch-arrow" href="<?php echo esc_url( sp_schedule_url( $base, array( 'view' => $view, 'date' => $next_date ) ) ); ?>"
					aria-label="<?php echo esc_attr__( 'Next period', 'student-portal' ); ?>" title="<?php echo esc_attr__( 'Next', 'student-portal' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m10 6 6 6-6 6" /></svg>
				</a>
				<a class="sp-sch-today" href="<?php echo esc_url( sp_schedule_url( $base, array( 'view' => $view, 'date' => $today ) ) ); ?>">Today</a>
			</div>

			<nav class="sp-sch-views" aria-label="<?php echo esc_attr__( 'Calendar view', 'student-portal' ); ?>">
				<?php foreach ( sp_schedule_views() as $sp_sch_view ) : ?>
					<a class="sp-sch-tab<?php echo $sp_sch_view === $view ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( sp_schedule_url( $base, array( 'view' => $sp_sch_view, 'date' => $date ) ) ); ?>"
						<?php echo $sp_sch_view === $view ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html( ucfirst( $sp_sch_view ) ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php echo sp_schedule_timezone_label(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static timezone text. ?>
		</div>

		<?php if ( 'month' === $view ) : ?>
			<?php sp_schedule_render_month( $range, $events, $labels, $editable, $base, $view, $date, $today ); ?>
		<?php elseif ( 'year' === $view ) : ?>
			<?php sp_schedule_render_year( $range, $events, $base, $date ); ?>
		<?php else : ?>
			<?php sp_schedule_render_grid( $view, $range, $events, $blocks, $labels, $editable, $base, $date, $today, $context, $axis_min, $axis_hours ); ?>
		<?php endif; ?>

		<div class="sp-sch-legend">
			<?php foreach ( $labels as $sp_sch_type => $sp_sch_text ) : ?>
				<span class="sp-sch-legend-item">
					<span class="sp-sch-legend-dot is-<?php echo esc_attr( $sp_sch_type ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $sp_sch_text ); ?>
				</span>
			<?php endforeach; ?>
			<?php if ( $timed_view ) : ?>
				<span class="sp-sch-legend-item">
					<span class="sp-sch-legend-dot is-class" aria-hidden="true"></span>
					Class timetable
				</span>
			<?php endif; ?>
		</div>

		<?php if ( $editable ) : ?>
			<div class="sp-sch-side-backdrop" hidden></div>
			<aside class="sp-sch-side<?php echo $side_open ? ' is-open' : ''; ?>"
				aria-label="<?php echo esc_attr__( 'Edit calendar date', 'student-portal' ); ?>">
				<div class="sp-sch-side-head">
					<h2 class="sp-sch-side-title"><?php echo esc_html__( 'Calendar entry', 'student-portal' ); ?></h2>
					<button type="button" class="sp-sch-close" aria-label="<?php echo esc_attr__( 'Close sidebar', 'student-portal' ); ?>">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" /></svg>
					</button>
				</div>
				<div class="sp-sch-side-body">
					<?php
					if ( $side_open ) {
						echo sp_schedule_side_html( '' !== $open_date ? $open_date : $date, $event_id, $view, $date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fragment builds its own escaped markup.
					} else {
						echo '<p class="sp-sch-side-hint">' . esc_html__( 'Pick the pencil on any date to add or edit its entries there.', 'student-portal' ) . '</p>';
					}
					?>
				</div>
			</aside>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The site timezone chip on the toolbar's right.
 *
 * @return string HTML.
 */
function sp_schedule_timezone_label() {
	$zone   = wp_timezone();
	$name   = $zone->getName();
	$offset = (int) $zone->getOffset( new DateTime( 'now', $zone ) );
	$hours  = (int) floor( abs( $offset ) / 3600 );
	$mins   = (int) ( ( abs( $offset ) % 3600 ) / 60 );
	$gmt    = sprintf( 'GMT%s%02d:%02d', $offset < 0 ? '-' : '+', $hours, $mins );

	return sprintf(
		'<span class="sp-sch-tz"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>%s</span>',
		esc_html( $name ? $name . ' (' . $gmt . ')' : $gmt )
	);
}

/**
 * The Day and Week views: hour rail, day columns, chips and timed blocks.
 *
 * @param string $view      'day' or 'week'.
 * @param array  $range     From sp_schedule_range().
 * @param array  $events    Events grouped by date.
 * @param array  $blocks    Timetable rows grouped by weekday.
 * @param array  $labels    Type labels.
 * @param bool   $editable  Edit icons on.
 * @param string $base      Base URL.
 * @param string $date      Focus date.
 * @param string $today     Today (Y-m-d).
 * @param string $context   Schedule context.
 * @param int    $axis_min  Rail start, minutes past midnight.
 * @param int    $axis_hours Rail length in hours.
 */
function sp_schedule_render_grid( $view, $range, $events, $blocks, $labels, $editable, $base, $date, $today, $context, $axis_min, $axis_hours ) {
	$days          = 'day' === $view ? array( $range['days'][0] ) : $range['days'];
	$columns       = count( $days );
	$grid_template = 'day' === $view
		? 'grid-template-columns: var(--sp-sch-gutter) minmax(0, 1fr);'
		: 'grid-template-columns: var(--sp-sch-gutter) repeat(' . $columns . ', minmax(112px, 1fr));';
	?>
	<div class="sp-sch-scroll">
		<div class="sp-sch-grid" style="<?php echo esc_attr( $grid_template ); ?>">

			<div class="sp-sch-corner"></div>

			<?php foreach ( $days as $day ) : ?>
				<?php
				$day_ts   = (int) strtotime( $day );
				$is_today = $day === $today;
				$off      = sp_is_off_day( $day );
				$long     = date_i18n( 'l, j F Y', $day_ts );
				?>
				<div class="sp-sch-dayhead<?php echo $is_today ? ' is-today' : ''; ?>">
					<span class="sp-sch-dow"><?php echo esc_html( date_i18n( 'D', $day_ts ) ); ?></span>
					<span class="sp-sch-num"><?php echo esc_html( date_i18n( 'j', $day_ts ) ); ?></span>
					<?php if ( $off ) : ?>
						<span class="sp-sch-off">Off</span>
					<?php endif; ?>
					<?php if ( $editable ) : ?>
						<a class="sp-sch-editbtn" data-date="<?php echo esc_attr( $day ); ?>"
							href="<?php echo esc_url( sp_schedule_url( $base, array( 'view' => $view, 'date' => $date, 'sp_edit_date' => $day ) ) ); ?>"
							title="<?php echo esc_attr__( 'Edit this date', 'student-portal' ); ?>"
							aria-label="<?php echo esc_attr( sprintf( 'Edit entries for %s', $long ) ); ?>">
							<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 20h4l10-10-4-4L4 16z" /><path d="m13.5 6.5 4 4" /></svg>
						</a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<div class="sp-sch-gutter-label"></div>

			<?php foreach ( $days as $day ) : ?>
				<?php
				$day_events = isset( $events[ $day ] ) ? $events[ $day ] : array();
				$cancelled  = sp_schedule_day_cancelled( $day_events, $context );
				?>
				<div class="sp-sch-allday">
					<?php if ( $cancelled ) : ?>
						<div class="sp-sch-noclass">No class &middot; <?php echo esc_html( $cancelled ); ?></div>
					<?php endif; ?>
					<?php foreach ( $day_events as $event ) : ?>
						<?php echo sp_schedule_chip( $event, $labels, $editable, $base, $view, $date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chip builds its own escaped markup. ?>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>

			<div class="sp-sch-hours">
				<?php for ( $h = 0; $h <= $axis_hours; $h++ ) : ?>
					<?php
					$minutes = $axis_min + ( $h * 60 );
					$clock   = sprintf( '%02d:%02d', (int) floor( $minutes / 60 ) % 24, $minutes % 60 );
					?>
					<div class="sp-sch-hour" style="--i: <?php echo esc_attr( $h ); ?>">
						<span><?php echo esc_html( $clock ); ?></span>
					</div>
				<?php endfor; ?>
			</div>

			<?php foreach ( $days as $day ) : ?>
				<?php
				$day_ts    = (int) strtotime( $day );
				$dow       = (int) date( 'w', $day_ts );
				$is_today  = $day === $today;
				$weekend   = ( 0 === $dow || 6 === $dow );
				$day_rows  = isset( $blocks[ $dow ] ) ? $blocks[ $dow ] : array();
				$day_events = isset( $events[ $day ] ) ? $events[ $day ] : array();
				$cancelled = sp_schedule_day_cancelled( $day_events, $context );
				$lanes     = sp_schedule_lanes( $day_rows );
				?>
				<div class="sp-sch-day<?php echo $is_today ? ' is-today' : ''; ?><?php echo $weekend ? ' is-weekend' : ''; ?><?php echo $cancelled ? ' is-cancelled' : ''; ?>"
					style="--sp-sch-hours: <?php echo esc_attr( $axis_hours ); ?>; --sp-sch-axis: <?php echo esc_attr( $axis_min ); ?>;">

					<?php
					// A recurring weekday row is only drawn on days that actually run.
					// The header above already marks off days with an "Off" badge, but
					// without this guard the recurring blocks were drawn anyway, which
					// is why Sunday (the default off day) always showed its classes.
					//
					// Cancelled days are deliberately left alone: their blocks are still
					// drawn and merely styled as cancelled further down.
					$sp_day_rows = sp_is_off_day( $day ) ? array() : $day_rows;
					?>
					<?php foreach ( $sp_day_rows as $ri => $row ) : ?>
						<?php
						$colors  = sp_schedule_block_colors( (int) $row->class_id );
						$mins    = sp_schedule_minutes( $row->start_time );
						$end     = sp_schedule_minutes( $row->end_time );
						$top     = $mins - $axis_min;
						$height  = max( 16, $end - $mins );
						$lane    = isset( $lanes['lane'][ $ri ] ) ? (int) $lanes['lane'][ $ri ] : 0;
						$is_dead = sp_schedule_block_cancelled( $row, $day_events );
						$span    = $row->start_time . ' - ' . $row->end_time;
						?>
						<div class="sp-sch-block<?php echo $is_dead ? ' is-cancelled' : ''; ?>"
							style="--mt: <?php echo esc_attr( $top ); ?>; --mh: <?php echo esc_attr( $height ); ?>; --li: <?php echo esc_attr( $lane ); ?>; --ln: <?php echo esc_attr( $lanes['total'] ); ?>; --sp-sch-bg: <?php echo esc_attr( $colors[0] ); ?>; --sp-sch-fg: <?php echo esc_attr( $colors[1] ); ?>;"
							title="<?php echo esc_attr( $row->title . ' - ' . $span ); ?>">
							<span class="sp-sch-block-title"><?php echo esc_html( $row->title ); ?></span>
							<span class="sp-sch-block-time"><?php echo esc_html( $span ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Does a day carry a reason to cancel class for this context?
 *
 * Returns the joined reasons ("Holiday - Diwali") or '' when class runs.
 * On the admin's all-classes view only a global entry banners the day; a
 * class-scoped holiday belongs to that class's column alone.
 *
 * @param object[] $day_events Events on the date.
 * @param string   $context    Schedule context.
 * @return string
 */
function sp_schedule_day_cancelled( $day_events, $context ) {
	$reasons = array();

	foreach ( $day_events as $event ) {
		if ( ! in_array( $event->type, sp_calendar_cancel_types(), true ) ) {
			continue;
		}

		if ( 'global' !== $event->scope && 'admin' === $context ) {
			continue;
		}

		$reasons[] = $event->type . ': ' . $event->title;
	}

	return $reasons ? implode( ' / ', $reasons ) : '';
}

/**
 * Is one timetable block cancelled on this date?
 *
 * @param object   $row         Timetable row.
 * @param object[] $day_events  Events on the date.
 * @return bool
 */
function sp_schedule_block_cancelled( $row, $day_events ) {
	foreach ( $day_events as $event ) {
		if ( ! in_array( $event->type, sp_calendar_cancel_types(), true ) ) {
			continue;
		}

		if ( 'global' === $event->scope || (int) $event->class_id === (int) $row->class_id ) {
			return true;
		}
	}

	return false;
}

/**
 * One all-day event chip.
 *
 * @param object   $event    Calendar event.
 * @param array    $labels   Type labels.
 * @param bool     $editable Link to the sidebar editor.
 * @param string   $base     Base URL.
 * @param string   $view     Current view.
 * @param string   $date     Focus date.
 * @return string HTML.
 */
function sp_schedule_chip( $event, $labels, $editable, $base, $view, $date ) {
	$type  = isset( $labels[ $event->type ] ) ? $labels[ $event->type ] : ucfirst( $event->type );
	$scope = 'global' === $event->scope ? __( 'Everyone', 'student-portal' ) : sp_schedule_class_title( (int) $event->class_id );
	$tip   = $type . ' - ' . $event->title . ( $scope ? ' (' . $scope . ')' : '' );

	$inner = '<span class="sp-sch-chip-title">' . esc_html( $event->title ) . '</span>'
		. '<span class="sp-sch-chip-type">' . esc_html( $type ) . '</span>';

	if ( ! $editable ) {
		return '<span class="sp-sch-chip is-' . esc_attr( $event->type ) . '" title="' . esc_attr( $tip ) . '">' . $inner . '</span>';
	}

	return '<a class="sp-sch-chip is-' . esc_attr( $event->type ) . '" data-event="' . esc_attr( (int) $event->id ) . '"'
		. ' data-date="' . esc_attr( $event->event_date ) . '"'
		. ' href="' . esc_url( sp_schedule_url( $base, array( 'view' => $view, 'date' => $date, 'edit' => (int) $event->id ) ) ) . '"'
		. ' title="' . esc_attr( $tip ) . '">' . $inner . '</a>';
}

/**
 * The Month view: week rows of cells with chips and a per-date edit icon.
 *
 * @param array  $range   From sp_schedule_range().
 * @param array  $events  Events grouped by date.
 * @param array  $labels  Type labels.
 * @param bool   $editable Edit icons on.
 * @param string $base    Base URL.
 * @param string $view    Current view.
 * @param string $date    Focus date.
 * @param string $today   Today (Y-m-d).
 */
function sp_schedule_render_month( $range, $events, $labels, $editable, $base, $view, $date, $today ) {
	?>
	<div class="sp-sch-scroll">
		<div class="sp-sch-month">
			<?php foreach ( array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ) as $sp_sch_head ) : ?>
				<div class="sp-sch-mheadday"><?php echo esc_html( $sp_sch_head ); ?></div>
			<?php endforeach; ?>

			<?php foreach ( $range['days'] as $day ) : ?>
				<?php
				$day_ts     = (int) strtotime( $day );
				$in_month   = (int) date( 'n', $day_ts ) === (int) $range['month'] && (int) date( 'Y', $day_ts ) === (int) $range['year'];
				$is_today   = $day === $today;
				$day_events = isset( $events[ $day ] ) ? $events[ $day ] : array();
				$shown      = array_slice( $day_events, 0, 3 );
				$extra      = count( $day_events ) - count( $shown );
				$long       = date_i18n( 'l, j F Y', $day_ts );
				?>
				<div class="sp-sch-mcell<?php echo $in_month ? '' : ' is-out'; ?><?php echo $is_today ? ' is-today' : ''; ?>">
					<div class="sp-sch-mcellhead">
						<span class="sp-sch-mnum"><?php echo esc_html( date_i18n( 'j', $day_ts ) ); ?></span>
						<?php if ( $editable ) : ?>
							<a class="sp-sch-editbtn" data-date="<?php echo esc_attr( $day ); ?>"
								href="<?php echo esc_url( sp_schedule_url( $base, array( 'view' => $view, 'date' => $date, 'sp_edit_date' => $day ) ) ); ?>"
								title="<?php echo esc_attr__( 'Edit this date', 'student-portal' ); ?>"
								aria-label="<?php echo esc_attr( sprintf( 'Edit entries for %s', $long ) ); ?>">
								<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 20h4l10-10-4-4L4 16z" /><path d="m13.5 6.5 4 4" /></svg>
							</a>
						<?php endif; ?>
					</div>

					<?php foreach ( $shown as $event ) : ?>
						<?php echo sp_schedule_chip( $event, $labels, $editable, $base, $view, $date ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chip builds its own escaped markup. ?>
					<?php endforeach; ?>

					<?php if ( $extra > 0 ) : ?>
						<span class="sp-sch-more">+<?php echo esc_html( (string) $extra ); ?> more</span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * The Year view: twelve mini months with an event dot per date.
 *
 * @param array  $range From sp_schedule_range().
 * @param array  $events Events grouped by date.
 * @param string $base  Base URL.
 * @param string $date  Focus date.
 */
function sp_schedule_render_year( $range, $events, $base, $date ) {
	$labels = sp_schedule_type_labels();
	?>
	<div class="sp-sch-scroll">
		<div class="sp-sch-year">
			<?php for ( $m = 1; $m <= 12; $m++ ) : ?>
				<?php
				$month_start = (int) mktime( 0, 0, 0, $m, 1, $range['year'] );
				$lead        = (int) date( 'w', $month_start );
				$days_total  = (int) date( 't', $month_start );
				$cells       = (int) ceil( ( $lead + $days_total ) / 7 ) * 7;
				?>
				<div class="sp-sch-mini">
					<a class="sp-sch-mini-title"
						href="<?php echo esc_url( sp_schedule_url( $base, array( 'view' => 'month', 'date' => date( 'Y-m-d', $month_start ) ) ) ); ?>">
						<?php echo esc_html( date_i18n( 'F', $month_start ) ); ?>
					</a>
					<div class="sp-sch-mini-grid">
						<?php foreach ( array( 'S', 'M', 'T', 'W', 'T', 'F', 'S' ) as $sp_sch_mini_head ) : ?>
							<span class="sp-sch-mini-day"><?php echo esc_html( $sp_sch_mini_head ); ?></span>
						<?php endforeach; ?>

						<?php for ( $i = 0; $i < $cells; $i++ ) : ?>
							<?php
							if ( $i < $lead || ( $i - $lead ) >= $days_total ) {
								echo '<span class="sp-sch-mini-cell is-out"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static empty cell.
								continue;
							}

							$day    = $i - $lead + 1;
							$day_ds = sprintf( '%04d-%02d-%02d', $range['year'], $m, $day );
							$day_ev = isset( $events[ $day_ds ] ) ? $events[ $day_ds ] : array();
							?>
							<a class="sp-sch-mini-cell<?php echo $day_ev ? ' has-event' : ''; ?>"
								href="<?php echo esc_url( sp_schedule_url( $base, array( 'view' => 'day', 'date' => $day_ds ) ) ); ?>"
								title="<?php echo esc_attr( $day_ev ? wp_trim_words( implode( ', ', wp_list_pluck( $day_ev, 'title' ) ), 6 ) : date_i18n( 'l, j F Y', (int) strtotime( $day_ds ) ) ); ?>">
								<span><?php echo esc_html( (string) $day ); ?></span>
								<?php if ( $day_ev ) : ?>
									<?php $sp_sch_first = $day_ev[0]; ?>
									<span class="sp-sch-dot is-<?php echo esc_attr( isset( $labels[ $sp_sch_first->type ] ) ? $sp_sch_first->type : 'event' ); ?>" aria-hidden="true"></span>
								<?php endif; ?>
							</a>
						<?php endfor; ?>
					</div>
				</div>
			<?php endfor; ?>
		</div>
	</div>
	<?php
}

/**
 * The sidebar fragment: the add/edit form plus every entry on a date.
 *
 * Printed inline when the URL asks for it, and fetched by
 * assets/js/schedule.js when an edit icon is clicked with scripting on.
 *
 * @param string $date     Date the sidebar is for (Y-m-d).
 * @param int    $event_id Event being edited, or 0 for a blank form.
 * @param string $view     Current view (carried through the save redirect).
 * @param string $focus    Current focus date (carried through the redirect).
 * @return string HTML.
 */
function sp_schedule_side_html( $date, $event_id = 0, $view = 'week', $focus = '' ) {
	$date = sp_sanitize_date( $date );

	if ( '' === $date ) {
		$date = current_time( 'Y-m-d' );
	}

	$focus = sp_sanitize_date( $focus );
	$focus = '' !== $focus ? $focus : $date;

	$event  = $event_id > 0 ? sp_get_calendar_event( (int) $event_id ) : null;
	$labels = sp_schedule_type_labels();
	$classes = sp_get_classes(
		array(
			'status'   => 'active',
			'per_page' => 1000,
		)
	);

	$day_events = sp_get_calendar_events(
		array(
			'date_from' => $date,
			'date_to'   => $date,
			'order'     => 'ASC',
		)
	);

	$editing_date = $event ? (string) $event->event_date : $date;
	ob_start();
	?>
	<div class="sp-sch-side-date"><?php echo esc_html( date_i18n( 'l, j F Y', (int) strtotime( $editing_date ) ) ); ?></div>

	<form class="sp-sch-side-form" method="post">
		<?php sp_admin_nonce_field( 'save_calendar_event' ); ?>
		<?php if ( $event ) : ?>
			<input type="hidden" name="event_id" value="<?php echo esc_attr( (int) $event->id ); ?>" />
		<?php endif; ?>
		<input type="hidden" name="sp_view" value="<?php echo esc_attr( $view ); ?>" />
		<input type="hidden" name="sp_date" value="<?php echo esc_attr( $focus ); ?>" />
		<input type="hidden" name="sp_edit_date" value="<?php echo esc_attr( $editing_date ); ?>" />

		<div class="sp-sch-field">
			<label for="sp_sch_date">Date <span class="sp-required">*</span></label>
			<input type="date" id="sp_sch_date" name="event_date" required data-sp-date
				value="<?php echo esc_attr( $editing_date ); ?>" />
		</div>

		<div class="sp-sch-field">
			<label for="sp_sch_type">Type</label>
			<select id="sp_sch_type" name="type">
				<?php foreach ( $labels as $sp_sch_value => $sp_sch_text ) : ?>
					<option value="<?php echo esc_attr( $sp_sch_value ); ?>"
						<?php selected( $event ? $event->type : 'holiday', $sp_sch_value ); ?>>
						<?php echo esc_html( $sp_sch_text ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="sp-sch-hint">
				<strong>Holiday</strong> and <strong>Test</strong> cancel class: registers cannot be opened
				and attendance ignores those dates. Exam and event are informational.
			</p>
		</div>

		<div class="sp-sch-field">
			<label for="sp_sch_scope">Applies to</label>
			<select id="sp_sch_scope" name="scope">
				<option value="global" <?php selected( $event ? $event->scope : 'global', 'global' ); ?>>Everyone (all classes)</option>
				<option value="class" <?php selected( $event ? $event->scope : '', 'class' ); ?>>One class</option>
			</select>
		</div>

		<div class="sp-sch-field">
			<label for="sp_sch_class">Class</label>
			<select id="sp_sch_class" name="class_id">
				<option value="0">&mdash; not applicable &mdash;</option>
				<?php foreach ( $classes as $sp_sch_class ) : ?>
					<option value="<?php echo esc_attr( (int) $sp_sch_class->id ); ?>"
						<?php selected( $event ? (int) $event->class_id : 0, (int) $sp_sch_class->id ); ?>>
						<?php echo esc_html( $sp_sch_class->title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="sp-sch-hint">Only needed when the entry applies to one class.</p>
		</div>

		<div class="sp-sch-field">
			<label for="sp_sch_title">Title <span class="sp-required">*</span></label>
			<input type="text" id="sp_sch_title" name="title" required
				placeholder="e.g. Diwali holiday"
				value="<?php echo esc_attr( $event ? $event->title : '' ); ?>" />
		</div>

		<div class="sp-sch-field">
			<label for="sp_sch_note">Note</label>
			<textarea id="sp_sch_note" name="note" rows="3"><?php echo esc_textarea( $event && $event->note ? $event->note : '' ); ?></textarea>
		</div>

		<p class="sp-sch-side-submit">
			<?php submit_button( $event ? 'Save entry' : 'Add entry', 'primary', 'submit', false ); ?>
			<?php if ( $event ) : ?>
				<a class="button"
					href="<?php echo esc_url( sp_schedule_url( admin_url( 'admin.php?page=sp-calendar' ), array( 'view' => $view, 'date' => $focus, 'sp_edit_date' => $editing_date ) ) ); ?>">Cancel</a>
			<?php endif; ?>
		</p>
	</form>

	<div class="sp-sch-side-list">
		<h3 class="sp-sch-side-listhead">Entries on this date (<?php echo esc_html( (string) count( $day_events ) ); ?>)</h3>

		<?php if ( ! $day_events ) : ?>
			<p class="sp-sch-side-empty">Nothing on this date yet.</p>
		<?php else : ?>
			<?php foreach ( $day_events as $sp_sch_entry ) : ?>
				<?php $sp_sch_is_current = ( $event && (int) $event->id === (int) $sp_sch_entry->id ); ?>
				<div class="sp-sch-side-item<?php echo $sp_sch_is_current ? ' is-current' : ''; ?>">
					<span class="sp-sch-chip is-<?php echo esc_attr( $sp_sch_entry->type ); ?>">
						<span class="sp-sch-chip-type"><?php echo esc_html( isset( $labels[ $sp_sch_entry->type ] ) ? $labels[ $sp_sch_entry->type ] : ucfirst( $sp_sch_entry->type ) ); ?></span>
					</span>
					<span class="sp-sch-side-itemmeta">
						<strong><?php echo esc_html( $sp_sch_entry->title ); ?></strong>
						<span class="sp-admin-muted">
							<?php
							echo 'global' === $sp_sch_entry->scope
								? esc_html__( 'Everyone', 'student-portal' )
								: esc_html( sp_schedule_class_title( (int) $sp_sch_entry->class_id ) );
							?>
						</span>
					</span>

					<div class="sp-sch-side-itemactions">
						<a class="sp-sch-side-edit" data-date="<?php echo esc_attr( $sp_sch_entry->event_date ); ?>"
							data-event="<?php echo esc_attr( (int) $sp_sch_entry->id ); ?>"
							href="<?php echo esc_url( sp_schedule_url( admin_url( 'admin.php?page=sp-calendar' ), array( 'view' => $view, 'date' => $focus, 'edit' => (int) $sp_sch_entry->id ) ) ); ?>">Edit</a>

						<form method="post" class="sp-admin-inline">
							<?php sp_admin_nonce_field( 'delete_calendar_event' ); ?>
							<input type="hidden" name="event_id" value="<?php echo esc_attr( (int) $sp_sch_entry->id ); ?>" />
							<input type="hidden" name="sp_view" value="<?php echo esc_attr( $view ); ?>" />
							<input type="hidden" name="sp_date" value="<?php echo esc_attr( $focus ); ?>" />
							<input type="hidden" name="sp_edit_date" value="<?php echo esc_attr( $sp_sch_entry->event_date ); ?>" />
							<button type="submit" class="button button-small">Delete</button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * The sidebar fragment over admin-ajax, for fetch-on-click.
 */
function sp_schedule_side_ajax() {
	check_ajax_referer( 'sp_schedule_side', 'nonce' );

	if ( ! current_user_can( 'manage_certificates' ) ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to do that.' ), 403 );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing, nonce checked above.
	$date     = isset( $_GET['date'] ) ? sp_sanitize_date( wp_unslash( $_GET['date'] ) ) : '';
	$event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;
	$view     = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'week';
	$focus    = isset( $_GET['focus'] ) ? sp_sanitize_date( wp_unslash( $_GET['focus'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( $event_id > 0 ) {
		$event = sp_get_calendar_event( $event_id );

		if ( $event ) {
			$date = (string) $event->event_date;
		}
	}

	if ( '' === $date ) {
		$date = current_time( 'Y-m-d' );
	}

	if ( ! in_array( $view, sp_schedule_views(), true ) ) {
		$view = 'week';
	}

	wp_send_json_success(
		array(
			'date' => $date,
			'html' => sp_schedule_side_html( $date, $event_id, $view, $focus ),
		)
	);
}
add_action( 'wp_ajax_sp_schedule_side', 'sp_schedule_side_ajax' );
