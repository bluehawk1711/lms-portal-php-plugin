<?php
/**
 * Teacher dashboard.
 *
 * A three-zone home screen in the shape of the agreed mockup: a greeting bar
 * with the pending-results bell, a gradient status banner, then a main column
 * (recent registers, top students, my classes) beside a right rail (profile,
 * month calendar, today's timeline). Every block runs on real portal data -
 * sessions, results and the class timetable - so nothing is decorative only
 * where an action can be offered.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_teacher = $teacher ? $teacher : sp_get_teacher_by_user_id( get_current_user_id() );

if ( ! $sp_teacher ) {
	?>
	<div class="sp-notice sp-notice-warn">
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			This WordPress account has no teacher profile, so there is no dashboard to draw. The sidebar still lets you
			browse every class, material, result and assignment, and you can create a teacher profile from
			Student Portal &rarr; Teachers.
		<?php else : ?>
			This WordPress account is not linked to a teacher profile yet. Ask an administrator to create your teacher account.
		<?php endif; ?>
	</div>
	<?php
	return;
}

$sp_classes = sp_get_classes(
	array(
		'teacher_id' => $sp_teacher->id,
		'status'     => 'active',
		'orderby'    => 'start_date',
		'order'      => 'DESC',
	)
);

$sp_today     = current_time( 'Y-m-d' );
$sp_off_today = sp_is_off_day( $sp_today ) || sp_date_cancels_class( $sp_today );
$sp_dow       = (int) date( 'w', current_time( 'timestamp' ) );
$sp_hour      = (int) current_time( 'G' );

// My own attendance for today — drives the "marked absent" banner. Only an
// administrator (or the auto-absent run) writes this record; teachers read it.
$sp_self        = sp_get_teacher_attendance( $sp_teacher->id, $sp_today );
$sp_self_status = $sp_self ? $sp_self->status : '';

$sp_total_students   = 0;
$sp_pending_results  = 0;
$sp_drafts           = 0;
$sp_today_sessions   = array();
$sp_submitted_today  = 0;
$sp_recent_registers = array();
$sp_top_students     = array();
$sp_periods          = array();

foreach ( $sp_classes as $sp_class ) {
	$sp_total_students  += sp_count_class_students( $sp_class->id );
	$sp_pending_results += sp_count_pending_results( $sp_class->id );
	$sp_drafts          += count( sp_get_materials( $sp_class->id, array( 'draft' ) ) );

	foreach ( sp_get_recent_sessions( $sp_class->id, 6 ) as $sp_session ) {
		$sp_recent_registers[] = array(
			'class'   => $sp_class,
			'session' => $sp_session,
		);

		if ( $sp_session->session_date === $sp_today ) {
			$sp_today_sessions[] = $sp_session;

			if ( 'submitted' === $sp_session->status ) {
				++$sp_submitted_today;
			}
		}
	}

	foreach ( sp_get_class_timings( $sp_class->id ) as $sp_timing ) {
		if ( (int) $sp_timing['day_of_week'] === $sp_dow ) {
			$sp_periods[] = array(
				'class' => $sp_class,
				'start' => $sp_timing['start_time'],
				'end'   => $sp_timing['end_time'],
			);
		}
	}

	foreach ( sp_get_class_results( $sp_class->id ) as $sp_row ) {
		if ( null === $sp_row->percentage || '' === $sp_row->percentage ) {
			continue;
		}

		$sp_top_students[] = array(
			'class'      => $sp_class,
			'full_name'  => $sp_row->full_name,
			'percentage' => (float) $sp_row->percentage,
			'grade'      => $sp_row->grade,
		);
	}
}

usort(
	$sp_recent_registers,
	function ( $a, $b ) {
		return strcmp( $b['session']->session_date, $a['session']->session_date );
	}
);
$sp_recent_registers = array_slice( $sp_recent_registers, 0, 6 );

usort(
	$sp_top_students,
	function ( $a, $b ) {
		return $b['percentage'] <=> $a['percentage'];
	}
);
$sp_top_students = array_slice( $sp_top_students, 0, 5 );

usort(
	$sp_periods,
	function ( $a, $b ) {
		return strcmp( $a['start'], $b['start'] );
	}
);

$sp_now_ts = current_time( 'timestamp' );

if ( ! function_exists( 'sp_dash_initials' ) ) {
	/**
	 * First letters of a display name, for the avatar tiles.
	 *
	 * @param string $name Display name.
	 * @return string One or two uppercase letters.
	 */
	function sp_dash_initials( $name ) {
		$parts = preg_split( '/\s+/', trim( (string) $name ) );
		$init  = '';

		if ( $parts ) {
			$init .= mb_strtoupper( mb_substr( $parts[0], 0, 1 ) );
		}

		if ( count( $parts ) > 1 ) {
			$init .= mb_strtoupper( mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) );
		}

		return $init ? $init : '?';
	}
}

$sp_name = ( isset( $sp_teacher->full_name ) && $sp_teacher->full_name )
	? $sp_teacher->full_name
	: wp_get_current_user()->display_name;
$sp_first     = preg_split( '/\s+/', trim( (string) $sp_name ) );
$sp_first     = $sp_first ? $sp_first[0] : $sp_name;
$sp_greeting  = $sp_hour < 12 ? 'Good morning' : ( $sp_hour < 17 ? 'Good afternoon' : 'Good evening' );
$sp_class_n   = count( $sp_classes );
$sp_pct       = $sp_class_n ? (int) round( ( $sp_submitted_today / $sp_class_n ) * 100 ) : 0;

// CTA: first class still waiting for today's register, else the first class.
$sp_cta_class = null;

$sp_session_class_ids = array();
foreach ( $sp_recent_registers as $sp_reg ) {
	if ( $sp_reg['session']->session_date === $sp_today ) {
		$sp_session_class_ids[ (int) $sp_reg['class']->id ] = 'submitted' === $sp_reg['session']->status;
	}
}

foreach ( $sp_classes as $sp_class ) {
	$sp_has_period = false;

	foreach ( $sp_periods as $sp_period ) {
		if ( (int) $sp_period['class']->id === (int) $sp_class->id ) {
			$sp_has_period = true;
			break;
		}
	}

	$sp_submitted = isset( $sp_session_class_ids[ (int) $sp_class->id ] ) && $sp_session_class_ids[ (int) $sp_class->id ];

	if ( ! $sp_submitted && ( $sp_has_period || ! isset( $sp_session_class_ids[ (int) $sp_class->id ] ) ) ) {
		$sp_cta_class = $sp_class;
		break;
	}
}
?>
<div class="sp-dash-top">
	<h1 class="sp-dash-greeting"><?php echo esc_html( $sp_greeting . ', ' . $sp_first ); ?> <span aria-hidden="true">&#128075;</span></h1>
	<a class="sp-dash-bell<?php echo $sp_pending_results ? ' has-pending' : ''; ?>"
		href="<?php echo esc_url( sp_teacher_url( 'results' ) ); ?>"
		aria-label="<?php echo esc_attr( $sp_pending_results ? number_format_i18n( $sp_pending_results ) . ' results waiting to be published' : 'Results' ); ?>">
		<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
			<path d="M18 9a6 6 0 1 0-12 0c0 5-2 6-2 6h16s-2-1-2-6z" />
			<path d="M10.5 19a2 2 0 0 0 3 0" />
		</svg>
		<?php if ( $sp_pending_results ) : ?>
			<span class="sp-dash-bell-badge"><?php echo esc_html( number_format_i18n( $sp_pending_results ) ); ?></span>
		<?php endif; ?>
	</a>
</div>

<section class="sp-dash-banner" aria-label="Today at a glance">
	<div class="sp-dash-banner-body">
		<?php if ( ! $sp_classes ) : ?>
			<p class="sp-dash-banner-kicker">Welcome aboard</p>
			<h2>Create your first class to get going</h2>
			<p class="sp-dash-banner-text">Once a class exists you can add students, take attendance and publish results.</p>
			<a class="sp-dash-banner-btn" href="<?php echo esc_url( sp_teacher_url( 'classes' ) ); ?>#sp-create-class">Create a class</a>
		<?php elseif ( $sp_off_today ) : ?>
			<p class="sp-dash-banner-kicker">Today at a glance</p>
			<h2>Today is an off day</h2>
			<p class="sp-dash-banner-text"><?php echo esc_html( sp_off_days_label() ); ?> are marked as off days, so no register needs taking.</p>
		<?php elseif ( in_array( $sp_self_status, array( 'absent', 'leave' ), true ) ) : ?>
			<p class="sp-dash-banner-kicker">Today at a glance</p>
			<h2>You are marked <?php echo 'leave' === $sp_self_status ? 'on leave' : 'absent'; ?> today</h2>
			<p class="sp-dash-banner-text">Your classes are cancelled for this date &mdash; students are not marked against it.</p>
		<?php else : ?>
			<p class="sp-dash-banner-kicker">Today at a glance</p>
			<h2>
				<?php if ( $sp_submitted_today ) : ?>
					<?php echo esc_html( number_format_i18n( $sp_submitted_today ) . ' of ' . number_format_i18n( $sp_class_n ) ); ?> registers submitted for today
				<?php else : ?>
					No registers submitted yet today
				<?php endif; ?>
			</h2>
			<p class="sp-dash-banner-text">
				<?php echo esc_html( number_format_i18n( $sp_class_n ) . ' classes · ' . number_format_i18n( $sp_total_students ) . ' students · ' . number_format_i18n( $sp_pending_results ) . ' results to publish' ); ?>
			</p>
			<?php if ( $sp_cta_class ) : ?>
				<a class="sp-dash-banner-btn" href="<?php echo esc_url( add_query_arg( 'tab', 'attendance', sp_teacher_url( 'classes/' . (int) $sp_cta_class->id ) ) ); ?>">
					<?php echo $sp_submitted_today ? 'Next register' : 'Take attendance'; ?>
				</a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<div class="sp-dash-banner-art" aria-hidden="true">
		<span class="sp-dash-banner-ring">
			<strong><?php echo esc_html( $sp_pct ); ?>%</strong>
			<span>submitted</span>
		</span>
	</div>
</section>

<div class="sp-dash">
	<div class="sp-dash-main">
		<section class="sp-dash-card">
			<header class="sp-dash-card-head">
				<h2>Recent registers</h2>
				<?php if ( $sp_classes ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'attendance', sp_teacher_url( 'classes/' . (int) $sp_classes[0]->id ) ) ); ?>">Open attendance</a>
				<?php endif; ?>
			</header>

			<?php if ( ! $sp_recent_registers ) : ?>
				<div class="sp-empty">Registers you submit will appear here with their date, topic and status.</div>
			<?php else : ?>
				<div class="sp-table-wrap">
					<table class="sp-table">
						<thead>
							<tr>
								<th scope="col">Class</th>
								<th scope="col">Date</th>
								<th scope="col">Topic</th>
								<th scope="col">Marked</th>
								<th scope="col">Status</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $sp_recent_registers as $sp_reg ) : ?>
								<?php $sp_reg_class = $sp_reg['class']; $sp_reg_s = $sp_reg['session']; ?>
								<tr>
									<th scope="row">
										<a href="<?php echo esc_url( add_query_arg( 'tab', 'attendance', sp_teacher_url( 'classes/' . (int) $sp_reg_class->id ) ) ); ?>">
											<?php echo esc_html( $sp_reg_class->title ); ?>
										</a>
									</th>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_reg_s->session_date ) ) ); ?></td>
									<td><?php echo $sp_reg_s->topic ? esc_html( $sp_reg_s->topic ) : '<span class="sp-muted">&mdash;</span>'; ?></td>
									<td><?php echo esc_html( number_format_i18n( (int) $sp_reg_s->marked_count ) ); ?></td>
									<td>
										<?php if ( 'submitted' === $sp_reg_s->status ) : ?>
											<span class="sp-badge sp-badge-good">Submitted</span>
										<?php else : ?>
											<span class="sp-badge sp-badge-warn">Open</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>

		<div class="sp-dash-row">
			<section class="sp-dash-card">
				<header class="sp-dash-card-head">
					<h2>Top students</h2>
				</header>

				<?php if ( ! $sp_top_students ) : ?>
					<div class="sp-empty">Enter and publish some results and your strongest students show up here.</div>
				<?php else : ?>
					<ul class="sp-dash-people">
						<?php foreach ( $sp_top_students as $sp_top ) : ?>
							<li class="sp-dash-person">
								<span class="sp-dash-avatar" aria-hidden="true"><?php echo esc_html( sp_dash_initials( $sp_top['full_name'] ) ); ?></span>
								<span class="sp-dash-person-text">
									<strong><?php echo esc_html( $sp_top['full_name'] ); ?></strong>
									<span><?php echo esc_html( $sp_top['class']->title ); ?></span>
								</span>
								<span class="sp-dash-score<?php echo $sp_top['percentage'] >= 90 ? ' is-top' : ''; ?>">
									<?php echo esc_html( number_format_i18n( $sp_top['percentage'], 0 ) ); ?>%
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>

			<section class="sp-dash-card">
				<header class="sp-dash-card-head">
					<h2>My classes</h2>
					<a href="<?php echo esc_url( sp_teacher_url( 'classes' ) ); ?>">View all</a>
				</header>

				<?php if ( ! $sp_classes ) : ?>
					<div class="sp-empty">
						You are not assigned to any class yet. <a href="<?php echo esc_url( sp_teacher_url( 'classes' ) ); ?>#sp-create-class">Create one</a>, then ask an administrator to add students.
					</div>
				<?php else : ?>
					<ul class="sp-dash-groups">
						<?php foreach (  array_slice( $sp_classes, 0, 5 ) as $sp_class ) : ?>
							<?php
							$sp_g_pending = sp_count_pending_results( $sp_class->id );
							$sp_g_drafts  = sp_count_unpublished_materials( $sp_class->id );
							?>
							<li class="sp-dash-group">
								<a class="sp-dash-group-link" href="<?php echo esc_url( sp_teacher_url( 'classes/' . (int) $sp_class->id ) ); ?>">
									<span class="sp-dash-tile" aria-hidden="true"><?php echo esc_html( sp_dash_initials( $sp_class->title ) ); ?></span>
									<span class="sp-dash-group-text">
										<strong><?php echo esc_html( $sp_class->title ); ?></strong>
										<span>
											<?php echo esc_html( number_format_i18n( sp_count_class_students( $sp_class->id ) ) ); ?> students
											<?php if ( $sp_class->code ) : ?>&middot; <?php echo esc_html( $sp_class->code ); ?><?php endif; ?>
										</span>
									</span>
									<?php if ( $sp_g_pending ) : ?>
										<span class="sp-dash-chip sp-dash-chip-warn"><?php echo esc_html( number_format_i18n( $sp_g_pending ) ); ?> to publish</span>
									<?php elseif ( $sp_g_drafts ) : ?>
										<span class="sp-dash-chip"><?php echo esc_html( number_format_i18n( $sp_g_drafts ) ); ?> draft(s)</span>
									<?php endif; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>
	</div>

	<aside class="sp-dash-rail" aria-label="Profile, calendar and today">
		<section class="sp-dash-card sp-dash-profile">
			<span class="sp-dash-avatar sp-dash-avatar-lg" aria-hidden="true"><?php echo esc_html( sp_dash_initials( $sp_name ) ); ?></span>
			<strong class="sp-dash-profile-name"><?php echo esc_html( $sp_name ); ?></strong>
			<span class="sp-dash-profile-role">Teacher &middot; <?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<span class="sp-dash-online"><span class="sp-dash-online-dot" aria-hidden="true"></span> Online</span>
		</section>

		<section class="sp-dash-card">
			<header class="sp-dash-card-head">
				<h2><?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, (int) current_time( 'n' ), 1, (int) current_time( 'Y' ) ) ) ); ?></h2>
				<span class="sp-dash-card-note"><?php echo esc_html( (string) current_time( 'Y' ) ); ?></span>
			</header>

			<?php
			$sp_cal_y     = (int) current_time( 'Y' );
			$sp_cal_m     = (int) current_time( 'n' );
			$sp_cal_first = (int) date( 'w', mktime( 0, 0, 0, $sp_cal_m, 1, $sp_cal_y ) );
			$sp_cal_off   = ( $sp_cal_first + 6 ) % 7; // Week starts Monday.
			$sp_cal_days  = (int) date( 't', mktime( 0, 0, 0, $sp_cal_m, 1, $sp_cal_y ) );
			$sp_cal_today = (int) current_time( 'j' );

			// Events shown on the grid: global entries plus my own classes'
			// (an empty class list still needs the global ones, hence the 0).
			$sp_cal_class_ids   = $sp_classes ? array_map( 'intval', wp_list_pluck( $sp_classes, 'id' ) ) : array( 0 );
			$sp_events_by_date  = array();

			foreach (
				sp_get_calendar_events(
					array(
						'date_from' => sprintf( '%04d-%02d-01', $sp_cal_y, $sp_cal_m ),
						'date_to'   => date( 'Y-m-d', mktime( 0, 0, 0, $sp_cal_m, $sp_cal_days, $sp_cal_y ) ),
						'class_ids' => $sp_cal_class_ids,
					)
				) as $sp_event
			) {
				$sp_events_by_date[ $sp_event->event_date ][] = $sp_event;
			}
			?>
			<div class="sp-cal" role="table" aria-label="<?php echo esc_attr( date_i18n( 'F Y' ) ); ?>">
				<div class="sp-cal-row sp-cal-head" role="row">
					<?php foreach ( array( 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su' ) as $sp_day_label ) : ?>
						<span role="columnheader"><?php echo esc_html( $sp_day_label ); ?></span>
					<?php endforeach; ?>
				</div>
				<?php
				$sp_cell = 0;
				for ( $sp_i = 0; $sp_i < $sp_cal_off; $sp_i++ ) :
					++$sp_cell;
					?>
					<span class="sp-cal-cell is-empty" role="cell"></span>
				<?php endfor; ?>
				<?php for ( $sp_day = 1; $sp_day <= $sp_cal_days; $sp_day++ ) : ?>
					<?php
					++$sp_cell;
					$sp_cell_date   = sprintf( '%04d-%02d-%02d', $sp_cal_y, $sp_cal_m, $sp_day );
					$sp_cell_events = isset( $sp_events_by_date[ $sp_cell_date ] ) ? $sp_events_by_date[ $sp_cell_date ] : array();
					?>
					<span class="sp-cal-cell<?php echo $sp_day === $sp_cal_today ? ' is-today' : ''; ?>" role="cell">
						<?php echo esc_html( (string) $sp_day ); ?>
						<?php if ( $sp_cell_events ) : ?>
							<?php $sp_cell_titles = wp_list_pluck( $sp_cell_events, 'title' ); ?>
							<span class="sp-cal-dot" role="img"
								aria-label="<?php echo esc_attr( implode( ', ', $sp_cell_titles ) ); ?>"
								title="<?php echo esc_attr( implode( ' · ', $sp_cell_titles ) ); ?>"></span>
						<?php endif; ?>
					</span>
				<?php endfor; ?>
			</div>
		</section>

		<section class="sp-dash-card">
			<header class="sp-dash-card-head">
				<h2>Timeline</h2>
				<span class="sp-dash-card-note">Today</span>
			</header>

			<?php if ( $sp_off_today ) : ?>
				<div class="sp-empty">Off day &mdash; nothing scheduled for today.</div>
			<?php elseif ( $sp_periods ) : ?>
				<ul class="sp-dash-timeline">
					<?php foreach ( $sp_periods as $sp_period ) : ?>
						<?php
						$sp_start_ts = strtotime( $sp_today . ' ' . $sp_period['start'] );
						$sp_end_ts   = strtotime( $sp_today . ' ' . $sp_period['end'] );
						$sp_is_now   = $sp_now_ts >= $sp_start_ts && $sp_now_ts < $sp_end_ts;
						?>
						<li class="sp-dash-timeline-item<?php echo $sp_is_now ? ' is-now' : ''; ?>">
							<span class="sp-dash-timeline-time">
								<?php echo esc_html( date_i18n( get_option( 'time_format' ), $sp_start_ts ) ); ?>
							</span>
							<span class="sp-dash-timeline-body">
								<a href="<?php echo esc_url( add_query_arg( 'tab', 'attendance', sp_teacher_url( 'classes/' . (int) $sp_period['class']->id ) ) ); ?>">
									<strong><?php echo esc_html( $sp_period['class']->title ); ?></strong>
								</a>
								<span class="sp-dash-timeline-meta">
									<?php if ( $sp_period['class']->code ) : ?>
										<span class="sp-dash-chip"><?php echo esc_html( $sp_period['class']->code ); ?></span>
									<?php endif; ?>
									<?php echo esc_html( date_i18n( get_option( 'time_format' ), $sp_start_ts ) ); ?>
									&ndash;
									<?php echo esc_html( date_i18n( get_option( 'time_format' ), $sp_end_ts ) ); ?>
									<?php if ( $sp_is_now ) : ?>
										<span class="sp-dash-now">In progress</span>
									<?php endif; ?>
								</span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php elseif ( $sp_classes ) : ?>
				<div class="sp-empty">No periods scheduled for today. Your classes still show in Recent registers above.</div>
			<?php else : ?>
				<div class="sp-empty">Create a class to start building a timetable.</div>
			<?php endif; ?>
		</section>
	</aside>
</div>
