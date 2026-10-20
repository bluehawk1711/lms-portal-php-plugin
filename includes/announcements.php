<?php
/**
 * Announcements shown as a full banner or a thin announcement bar.
 *
 * Admin composes a message aimed at one student, all students or the
 * teachers, and picks the display mode. Each signed-in portal user sees the
 * active rows aimed at them until they dismiss it; a dismissal is stored per
 * user so it never hides the message for anyone else.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Announcement ids the current user has dismissed.
 *
 * @return int[]
 */
function sp_announcement_dismissed_ids() {
	$raw = get_user_meta( get_current_user_id(), 'sp_ann_dismissed', true );

	if ( ! is_array( $raw ) ) {
		return array();
	}

	return array_map( 'intval', $raw );
}

/**
 * Hide one announcement from the current user only.
 *
 * @param int $announcement_id Announcement row id.
 */
function sp_announcement_dismiss( $announcement_id ) {
	$announcement_id = (int) $announcement_id;

	if ( ! $announcement_id || ! is_user_logged_in() ) {
		return;
	}

	$ids = sp_announcement_dismissed_ids();

	if ( in_array( $announcement_id, $ids, true ) ) {
		return;
	}

	$ids[] = $announcement_id;

	// The list only ever grows with dismissed ids: keep it bounded.
	$ids = array_slice( $ids, -200 );

	update_user_meta( get_current_user_id(), 'sp_ann_dismissed', $ids );
}

/**
 * Active announcements the current user should see, dismissals filtered out.
 *
 * @param string $portal 'student' or 'teacher'.
 * @return object[] Announcement rows.
 */
function sp_announcements_visible( $portal ) {
	global $wpdb;

	if ( ! is_user_logged_in() ) {
		return array();
	}

	$table  = sp_table( 'announcements' );
	$params = array();

	if ( 'teacher' === $portal ) {
		if ( ! sp_is_teacher() && ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		$where = "status = 'active' AND audience = 'teachers'";
	} else {
		$profile = sp_get_profile_by_user_id( get_current_user_id() );

		if ( ! $profile ) {
			return array();
		}

		$where   = "status = 'active' AND ( audience = 'all_students' OR ( audience = 'one_student' AND audience_profile_id = %d ) )";
		$params[] = (int) $profile->id;
	}

	$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT 6"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table(), clause built above.

	$rows = $params
		? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders come from $where.
		: $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no placeholders to bind.

	if ( ! $rows ) {
		return array();
	}

	$dismissed = sp_announcement_dismissed_ids();
	$visible   = array();

	foreach ( $rows as $row ) {
		if ( ! in_array( (int) $row->id, $dismissed, true ) ) {
			$visible[] = $row;
		}
	}

	return $visible;
}

/**
 * Print the active announcements for the current portal page.
 *
 * Called from inside <main> of each portal layout, after the page notices.
 *
 * @param string $portal 'student' or 'teacher'.
 */
function sp_render_announcements( $portal ) {
	$rows = sp_announcements_visible( $portal );

	if ( ! $rows ) {
		return;
	}

	foreach ( $rows as $row ) {
		$is_banner = 'banner' === (string) $row->display;
		$title     = trim( (string) $row->title );
		$body      = trim( (string) $row->body );
		?>
		<section class="sp-ann sp-ann-<?php echo $is_banner ? 'banner' : 'bar'; ?>" role="region" aria-label="Announcement">
			<?php if ( $is_banner ) : ?>
				<div class="sp-ann-banner-inner">
					<p class="sp-ann-eyebrow">ANNOUNCEMENT</p>
					<?php if ( '' !== $title ) : ?>
						<h3 class="sp-ann-title"><?php echo esc_html( $title ); ?></h3>
					<?php endif; ?>
					<div class="sp-ann-text"><?php echo wp_kses_post( wpautop( $body ) ); ?></div>
				</div>
			<?php else : ?>
				<span class="sp-ann-bar-label" aria-hidden="true">Announcement</span>
				<div class="sp-ann-bar-text">
					<?php if ( '' !== $title ) : ?>
						<strong><?php echo esc_html( $title ); ?></strong>
					<?php endif; ?>
					<span><?php echo esc_html( wp_strip_all_tags( $body ) ); ?></span>
				</div>
			<?php endif; ?>

			<form method="post" class="sp-ann-dismiss">
				<?php
				if ( 'teacher' === $portal ) {
					sp_teacher_nonce_field( 'dismiss_announcement' );
				} else {
					sp_portal_nonce_field( 'dismiss_announcement' );
				}
				?>
				<input type="hidden" name="announcement_id" value="<?php echo esc_attr( (int) $row->id ); ?>" />
				<button type="submit" class="sp-ann-dismiss-btn" aria-label="Dismiss announcement" title="Dismiss">
					<span aria-hidden="true">&times;</span>
					<span class="sp-ann-dismiss-text">Dismiss</span>
				</button>
			</form>
		</section>
		<?php
	}
}
