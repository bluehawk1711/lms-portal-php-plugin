<?php
/**
 * Announcement composition and management.
 *
 * Layout mirrors the Classes screen: header + stats, a compose panel on the
 * left and the running list on the right, so it shares one design language
 * with the rest of the plugin admin.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Announcements screen.
 */
function sp_admin_page_sp_announcements() {
	global $wpdb;

	$sp_students = sp_get_profiles( array( 'per_page' => 500 ) );
	$sp_rows     = $wpdb->get_results(
		'SELECT * FROM ' . sp_table( 'announcements' ) . ' ORDER BY created_at DESC, id DESC LIMIT 200' // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table().
	);

	$sp_total    = count( $sp_rows );
	$sp_active   = 0;
	$sp_archived = 0;

	foreach ( $sp_rows as $sp_count_row ) {
		if ( 'active' === (string) $sp_count_row->status ) {
			++$sp_active;
		} else {
			++$sp_archived;
		}
	}
	?>
	<div class="sp-annc-ui">
		<header class="sp-annc-page-header">
			<div class="sp-annc-page-heading">
				<span class="sp-annc-eyebrow">STUDENT PORTAL <span aria-hidden="true">/</span> COMMUNICATION</span>
				<h1>Announcements</h1>
				<p>Publish a full banner or a thin announcement bar to students and teachers — right inside their portal.</p>
			</div>
			<div class="sp-annc-header-note">
				<span class="sp-annc-header-mark" aria-hidden="true">✦</span>
				<span><strong>Direct channel</strong><small>Visible in both portals</small></span>
			</div>
		</header>

		<section class="sp-annc-stats" aria-label="Announcement overview">
			<article class="sp-annc-stat-card">
				<span class="sp-annc-stat-icon sp-annc-stat-icon-total" aria-hidden="true">✉</span>
				<div><span class="sp-annc-stat-label">Total</span><strong><?php echo esc_html( number_format_i18n( $sp_total ) ); ?></strong><small>Published so far</small></div>
			</article>
			<article class="sp-annc-stat-card">
				<span class="sp-annc-stat-icon sp-annc-stat-icon-active" aria-hidden="true">✓</span>
				<div><span class="sp-annc-stat-label">Live</span><strong><?php echo esc_html( number_format_i18n( $sp_active ) ); ?></strong><small>Showing in portals</small></div>
			</article>
			<article class="sp-annc-stat-card">
				<span class="sp-annc-stat-icon sp-annc-stat-icon-archived" aria-hidden="true">◱</span>
				<div><span class="sp-annc-stat-label">Archived</span><strong><?php echo esc_html( number_format_i18n( $sp_archived ) ); ?></strong><small>Hidden right now</small></div>
			</article>
		</section>

		<div class="sp-annc-layout">
			<section class="sp-annc-panel sp-annc-form-panel" aria-labelledby="sp-annc-form-title">
				<div class="sp-annc-panel-heading">
					<div>
						<span class="sp-annc-section-kicker">GET STARTED</span>
						<h2 id="sp-annc-form-title">Publish an announcement</h2>
						<p>Write the message, choose who sees it and how it appears.</p>
					</div>
					<span class="sp-annc-heading-icon" aria-hidden="true">＋</span>
				</div>

				<form method="post" class="sp-annc-form">
					<?php sp_admin_nonce_field( 'save_announcement' ); ?>

					<div class="sp-annc-field">
						<label for="sp_ann_title">Title <span class="sp-annc-muted">(optional)</span></label>
						<input type="text" id="sp_ann_title" name="title" maxlength="255" placeholder="e.g. Diwali break schedule" />
						<p class="description">A short heading shown above the message.</p>
					</div>

					<div class="sp-annc-field">
						<label for="sp_ann_body">Message <span class="sp-required">*</span></label>
						<textarea id="sp_ann_body" name="body" rows="4" required placeholder="What should students or teachers know?"></textarea>
					</div>

					<div class="sp-annc-divider"></div>
					<div class="sp-annc-form-section-title"><span>01</span><div><strong>How it appears</strong><small>Pick the display style.</small></div></div>

					<div class="sp-annc-radio-row">
						<label class="sp-annc-radio-card">
							<input type="radio" name="display" value="banner" checked />
							<span><strong>Full banner</strong><small>A large card at the top of the page.</small></span>
						</label>
						<label class="sp-annc-radio-card">
							<input type="radio" name="display" value="bar" />
							<span><strong>Thin bar</strong><small>A compact strip above the content.</small></span>
						</label>
					</div>

					<div class="sp-annc-divider"></div>
					<div class="sp-annc-form-section-title"><span>02</span><div><strong>Who sees it</strong><small>Target everyone or one student.</small></div></div>

					<div class="sp-annc-field">
						<label for="sp_ann_audience">Audience</label>
						<select id="sp_ann_audience" name="audience">
							<option value="all_students">All students</option>
							<option value="one_student">One student</option>
							<option value="teachers">Teachers</option>
						</select>
					</div>

					<div class="sp-annc-field">
						<label for="sp_ann_profile">Student</label>
						<select id="sp_ann_profile" name="profile_id">
							<option value="0">— choose a student —</option>
							<?php foreach ( $sp_students as $sp_student ) : ?>
								<option value="<?php echo esc_attr( (int) $sp_student->id ); ?>">
									<?php echo esc_html( $sp_student->full_name . ' — ' . $sp_student->email ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">Only used when the audience is “One student”.</p>
					</div>

					<button type="submit" class="button button-primary sp-annc-submit">Publish announcement <span aria-hidden="true">→</span></button>
				</form>
			</section>

			<section class="sp-annc-panel sp-annc-list-panel" aria-labelledby="sp-annc-list-title">
				<div class="sp-annc-panel-heading">
					<div>
						<span class="sp-annc-section-kicker">INBOX</span>
						<h2 id="sp-annc-list-title">Announcements</h2>
						<p>Archive hides a message again; students who dismissed it never see it twice.</p>
					</div>
					<span class="sp-annc-heading-icon" aria-hidden="true">▤</span>
				</div>

				<div class="sp-annc-table-wrap">
					<table class="widefat sp-annc-table">
						<thead>
							<tr>
								<th scope="col">Message</th>
								<th scope="col">Audience</th>
								<th scope="col">Shown as</th>
								<th scope="col">Status</th>
								<th scope="col">Published</th>
								<th scope="col"><span class="screen-reader-text">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! $sp_rows ) : ?>
								<tr><td colspan="6"><div class="sp-annc-empty"><span aria-hidden="true">✉</span><strong>No announcements yet</strong><p>Compose your first message using the form.</p></div></td></tr>
							<?php endif; ?>

							<?php foreach ( $sp_rows as $sp_row ) : ?>
								<?php
								$sp_target = '';

								if ( 'one_student' === $sp_row->audience ) {
									$sp_profile = sp_get_profile_by_id( (int) $sp_row->audience_profile_id );
									$sp_target  = $sp_profile ? $sp_profile->full_name : 'Student #' . (int) $sp_row->audience_profile_id;
								}
								?>
								<tr>
									<td class="sp-annc-title-cell">
										<span class="sp-annc-avatar" aria-hidden="true"><?php echo esc_html( strtoupper( function_exists( 'mb_substr' ) ? mb_substr( (string) ( $sp_row->title ? $sp_row->title : $sp_row->body ), 0, 1 ) : substr( (string) ( $sp_row->title ? $sp_row->title : $sp_row->body ), 0, 1 ) ) ); ?></span>
										<span class="sp-annc-details"><strong><?php echo esc_html( $sp_row->title ? $sp_row->title : wp_trim_words( wp_strip_all_tags( $sp_row->body ), 10 ) ); ?></strong><small><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $sp_row->body ), 18 ) ); ?></small></span>
									</td>
									<td>
										<?php
										switch ( $sp_row->audience ) {
											case 'teachers':
												echo '<span class="sp-annc-course-pill">Teachers</span>';
												break;
											case 'one_student':
												echo '<span class="sp-annc-course-pill">' . esc_html( $sp_target ) . '</span>';
												break;
											default:
												echo '<span class="sp-annc-course-pill">All students</span>';
										}
										?>
									</td>
									<td><span class="sp-annc-number-pill"><?php echo 'banner' === $sp_row->display ? 'Banner' : 'Bar'; ?></span></td>
									<td><span class="sp-annc-status sp-annc-status-<?php echo esc_attr( sanitize_html_class( (string) $sp_row->status ) ); ?>"><?php echo esc_html( ucfirst( (string) $sp_row->status ) ); ?></span></td>
									<td><span class="sp-annc-muted"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $sp_row->created_at ) ) ); ?></span></td>
									<td class="sp-annc-actions-cell">
										<div class="sp-annc-row-actions">
											<form method="post" class="sp-annc-inline-form">
												<?php sp_admin_nonce_field( 'announcement_status' ); ?>
												<input type="hidden" name="announcement_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
												<button type="submit" class="button button-small sp-annc-approve">
													<?php echo 'active' === $sp_row->status ? 'Archive' : 'Restore'; ?>
												</button>
											</form>
											<form method="post" class="sp-annc-inline-form sp-annc-delete-form" onsubmit="return confirm('Delete this announcement?');">
												<?php sp_admin_nonce_field( 'delete_announcement' ); ?>
												<input type="hidden" name="announcement_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
												<button type="submit" class="button button-small sp-annc-delete">Delete</button>
											</form>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="sp-annc-table-footer">
					<span>Showing <strong><?php echo esc_html( number_format_i18n( $sp_total ) ); ?></strong> announcement<?php echo 1 === $sp_total ? '' : 's'; ?></span>
					<span class="sp-annc-muted">Dismissals are stored per user</span>
				</div>
			</section>
		</div>

		<footer class="sp-annc-page-footer">
			<span><span class="sp-annc-footer-dot" aria-hidden="true"></span> Announcements</span>
			<span>Banners and bars appear above the page content in both portals.</span>
		</footer>
	</div>
	<?php
}
