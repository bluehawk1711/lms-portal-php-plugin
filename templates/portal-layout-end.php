<?php
/**
 * Closes the content column and fills the right rail.
 *
 * The rail is part of the shell, so every screen gets the same three cards:
 * the student's profile, this month's calendar (global events plus the events
 * of their own classes) and an attendance progress ring per active course.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_rail_user        = wp_get_current_user();
$sp_rail_name        = ( $profile && ! empty( $profile->full_name ) ) ? $profile->full_name : $sp_rail_user->display_name;
$sp_rail_enrollments = ( $profile ) ? sp_get_enrollments_for_profile( $profile->id, array( 'active' ) ) : array();
$sp_rail_class_ids   = $sp_rail_enrollments ? array_map( 'intval', wp_list_pluck( $sp_rail_enrollments, 'class_id' ) ) : array( 0 );
$sp_rail_role        = '';

if ( $sp_rail_enrollments ) {
	$sp_rail_role = $sp_rail_enrollments[0]->class_title;
} else {
	$sp_rail_role = $profile ? 'Student' : 'Account not linked';
}
?>
			</main>

			<footer class="sp-portal-footer">
				<p>
					<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
					&middot;
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to website</a>
				</p>
			</footer>
		</div>

		<aside class="sp-portal-rail" aria-label="Profile, calendar and progress">
			<section class="sp-rail-card sp-rail-profile">
				<header class="sp-rail-head">
					<h2>Profile</h2>
					<a class="sp-rail-edit" href="<?php echo esc_url( sp_portal_url( 'profile' ) ); ?>"
						aria-label="Open my profile" title="Open my profile">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 20h4l10-10-4-4L4 16z" /><path d="m13.5 6.5 4 4" /></svg>
					</a>
				</header>

				<span class="sp-rail-avatar" aria-hidden="true"><?php echo esc_html( sp_portal_initials( $sp_rail_name ) ); ?></span>
				<strong class="sp-rail-name"><?php echo esc_html( $sp_rail_name ); ?></strong>
				<span class="sp-rail-role"><?php echo esc_html( $sp_rail_role ); ?></span>
				<a class="sp-rail-link" href="<?php echo esc_url( sp_portal_url( 'profile' ) ); ?>">My profile</a>
			</section>

			<section class="sp-rail-card">
				<header class="sp-rail-head">
					<h2><?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, (int) current_time( 'n' ), 1, (int) current_time( 'Y' ) ) ) ); ?></h2>
					<span class="sp-rail-note"><?php echo esc_html( (string) current_time( 'Y' ) ); ?></span>
				</header>

				<?php
				$sp_rail_cal_y     = (int) current_time( 'Y' );
				$sp_rail_cal_m     = (int) current_time( 'n' );
				$sp_rail_cal_first = (int) date( 'w', mktime( 0, 0, 0, $sp_rail_cal_m, 1, $sp_rail_cal_y ) );
				$sp_rail_cal_off   = ( $sp_rail_cal_first + 6 ) % 7; // Week starts Monday.
				$sp_rail_cal_days  = (int) date( 't', mktime( 0, 0, 0, $sp_rail_cal_m, 1, $sp_rail_cal_y ) );
				$sp_rail_cal_today = (int) current_time( 'j' );

				$sp_rail_events = array();

				foreach (
					sp_get_calendar_events(
						array(
							'date_from' => sprintf( '%04d-%02d-01', $sp_rail_cal_y, $sp_rail_cal_m ),
							'date_to'   => date( 'Y-m-d', mktime( 0, 0, 0, $sp_rail_cal_m, $sp_rail_cal_days, $sp_rail_cal_y ) ),
							'class_ids' => $sp_rail_class_ids,
						)
					) as $sp_rail_event
				) {
					$sp_rail_events[ $sp_rail_event->event_date ][] = $sp_rail_event;
				}
				?>
				<div class="sp-cal" role="table" aria-label="<?php echo esc_attr( date_i18n( 'F Y' ) ); ?>">
					<div class="sp-cal-row sp-cal-head" role="row">
						<?php foreach ( array( 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su' ) as $sp_rail_day_label ) : ?>
							<span role="columnheader"><?php echo esc_html( $sp_rail_day_label ); ?></span>
						<?php endforeach; ?>
					</div>
					<?php
					$sp_rail_cell = 0;
					for ( $sp_rail_i = 0; $sp_rail_i < $sp_rail_cal_off; $sp_rail_i++ ) :
						++$sp_rail_cell;
						?>
						<span class="sp-cal-cell is-empty" role="cell"></span>
					<?php endfor; ?>
					<?php for ( $sp_rail_day = 1; $sp_rail_day <= $sp_rail_cal_days; $sp_rail_day++ ) : ?>
						<?php
						++$sp_rail_cell;
						$sp_rail_cell_date   = sprintf( '%04d-%02d-%02d', $sp_rail_cal_y, $sp_rail_cal_m, $sp_rail_day );
						$sp_rail_cell_events = isset( $sp_rail_events[ $sp_rail_cell_date ] ) ? $sp_rail_events[ $sp_rail_cell_date ] : array();
						?>
						<span class="sp-cal-cell<?php echo $sp_rail_day === $sp_rail_cal_today ? ' is-today' : ''; ?>" role="cell">
							<?php echo esc_html( (string) $sp_rail_day ); ?>
							<?php if ( $sp_rail_cell_events ) : ?>
								<?php $sp_rail_titles = wp_list_pluck( $sp_rail_cell_events, 'title' ); ?>
								<span class="sp-cal-dot" role="img"
									aria-label="<?php echo esc_attr( implode( ', ', $sp_rail_titles ) ); ?>"
									title="<?php echo esc_attr( implode( ' · ', $sp_rail_titles ) ); ?>"></span>
							<?php endif; ?>
						</span>
					<?php endfor; ?>
				</div>
			</section>

			<section class="sp-rail-card">
				<header class="sp-rail-head">
					<h2>Attendance progress</h2>
				</header>

				<?php if ( ! $sp_rail_enrollments ) : ?>
					<div class="sp-empty">Your progress shows here once you are enrolled in a course.</div>
				<?php else : ?>
					<ul class="sp-progress-list">
						<?php foreach ( array_slice( $sp_rail_enrollments, 0, 4 ) as $sp_rail_enrollment ) : ?>
							<?php $sp_rail_stats = sp_get_attendance_stats( $profile->id, (int) $sp_rail_enrollment->class_id ); ?>
							<li>
								<a class="sp-progress-card" href="<?php echo esc_url( sp_portal_url( 'attendance' ) ); ?>">
									<span class="sp-progress-ring" style="--sp-pct: <?php echo esc_attr( (int) $sp_rail_stats['percentage'] ); ?>">
										<span class="sp-progress-value"><?php echo esc_html( (int) $sp_rail_stats['percentage'] ); ?>%</span>
									</span>
									<span class="sp-progress-text">
										<strong><?php echo esc_html( $sp_rail_enrollment->class_title ); ?></strong>
										<span class="sp-muted"><?php echo esc_html( $sp_rail_stats['present'] . '/' . $sp_rail_stats['total'] ); ?> sessions</span>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</aside>
	</div>
</div>
