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
 * The newest announcement id the current user has already been shown.
 *
 * Stored per user so the "you have a new announcement" signal survives across
 * devices and browser sessions, unlike a cookie or localStorage flag.
 *
 * @return int Announcement id, 0 when the user has never seen one.
 */
function sp_announcement_last_seen_id() {
	if ( ! is_user_logged_in() ) {
		return 0;
	}

	return (int) get_user_meta( get_current_user_id(), 'sp_ann_last_seen', true );
}

/**
 * Record that the user has seen the announcements up to one id.
 *
 * Only ever moves forward: an older announcement arriving late must not reset
 * the marker and make the bell fire again.
 *
 * @param int $announcement_id Announcement row id.
 */
function sp_announcement_mark_seen( $announcement_id ) {
	$announcement_id = (int) $announcement_id;

	if ( ! $announcement_id || ! is_user_logged_in() ) {
		return;
	}

	if ( $announcement_id <= sp_announcement_last_seen_id() ) {
		return;
	}

	update_user_meta( get_current_user_id(), 'sp_ann_last_seen', $announcement_id );
}

/**
 * Whether the current user has an announcement they have not seen yet.
 *
 * @param object[] $rows Announcement rows, newest first.
 * @return bool
 */
function sp_announcements_have_new( $rows ) {
	if ( ! $rows ) {
		return false;
	}

	$last_seen = sp_announcement_last_seen_id();

	foreach ( $rows as $row ) {
		if ( (int) $row->id > $last_seen ) {
			return true;
		}
	}

	return false;
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
 * Clean an end date coming from a form into a storable value.
 *
 * Accepts a Y-m-d date (what the date input posts) or a full datetime. An
 * empty or unparseable value means "no end date", which is stored as NULL so
 * the announcement never expires.
 *
 * @param mixed $value Raw submitted value.
 * @return string|null Y-m-d H:i:s, or null when there is no end date.
 */
function sp_announcement_sanitise_end_date( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return null;
	}

	$timestamp = strtotime( $value );

	if ( ! $timestamp ) {
		return null;
	}

	return gmdate( 'Y-m-d H:i:s', $timestamp );
}

/**
 * Whether an announcement has passed its end date and should be hidden.
 *
 * An announcement with no end date never expires. The end date is stored as a
 * plain date, so it is compared against today's date: the announcement stays
 * visible for the whole of its final day and disappears the next morning.
 *
 * @param object $announcement Announcement row.
 * @return bool
 */
function sp_announcement_is_expired( $announcement ) {
	if ( empty( $announcement->ends_at ) ) {
		return false;
	}

	$ends_at = strtotime( (string) $announcement->ends_at );

	if ( ! $ends_at ) {
		return false;
	}

	// Midnight at the start of the day after the end date.
	$expiry = strtotime( date( 'Y-m-d', $ends_at ) . ' +1 day' );

	return $expiry <= time();
}

/**
 * The end date of an announcement as a Y-m-d string, or an empty string.
 *
 * @param object $announcement Announcement row.
 * @return string
 */
function sp_announcement_end_date( $announcement ) {
	if ( empty( $announcement->ends_at ) ) {
		return '';
	}

	$timestamp = strtotime( (string) $announcement->ends_at );

	return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
}

/**
 * Active announcements the current user should see, dismissals filtered out.
 *
 * Announcements whose end date has passed are left out, so they disappear
 * from both portals on their own without anyone touching them.
 *
 * @param string $portal 'student' or 'teacher'.
 * @param int    $limit  Maximum number of rows to return.
 * @return object[] Announcement rows.
 */
function sp_announcements_visible( $portal, $limit = 6 ) {
	global $wpdb;

	if ( ! is_user_logged_in() ) {
		return array();
	}

	$table  = sp_table( 'announcements' );
	$params = array();
	$limit  = max( 1, (int) $limit );

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

	/*
	 * The expiry is filtered in SQL so an expired announcement never counts
	 * towards the limit: a row that is about to disappear must not push a
	 * live one out of the list.
	 */
	$where .= ' AND ( ends_at IS NULL OR ends_at >= %s )';
	$params[] = gmdate( 'Y-m-d' );

	$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from sp_table(), clause built above.

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $limit ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders come from $where.

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
    <!-- <span class="sp-ann-bar-label" aria-hidden="true">Announcement</span> -->
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