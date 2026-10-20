<?php
/**
 * Student attendance.
 *
 * Every enrolled class renders as a pane behind a class selection filter.
 * With JavaScript the select swaps panes in place (URL kept in sync); without
 * it the same form falls back to a plain ?class= reload.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $profile ) ) {
	?>
<div class="sp-notice sp-notice-warn">
    This WordPress account is not linked to a student profile yet. Ask the institute to create your portal account.
</div>
<?php
	return;
}

$sp_enrollments = sp_get_enrollments_for_profile( $profile->id, array( 'active', 'completed' ) );
$sp_view_id     = isset( $_GET['class'] ) ? (int) $_GET['class'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter selection.

// Resolve the visible class: the requested one, else the first enrolment.
$sp_active_id = 0;

foreach ( $sp_enrollments as $sp_candidate ) {
	if ( (int) $sp_candidate->class_id === $sp_view_id ) {
		$sp_active_id = (int) $sp_candidate->class_id;
		break;
	}
}

if ( ! $sp_active_id && $sp_enrollments ) {
	$sp_active_id = (int) $sp_enrollments[0]->class_id;
}

// Stats + history for every class, so switching never needs a round trip.
$sp_panes = array();

foreach ( $sp_enrollments as $sp_pane_enrol ) {
	$sp_panes[] = array(
		'class_id' => (int) $sp_pane_enrol->class_id,
		'title'    => (string) $sp_pane_enrol->class_title,
		'stats'    => sp_get_attendance_stats( $profile->id, (int) $sp_pane_enrol->class_id ),
		'history'  => sp_get_attendance_history( $profile->id, (int) $sp_pane_enrol->class_id, 90 ),
	);
}
?>
<!-- <div class="sp-page-head">

	<p class="sp-page-sub">Your record for each course.</p>
</div> -->

<?php if ( ! $sp_enrollments ) : ?>
<div class="sp-empty">You are not enrolled in any course yet.</div>
<?php else : ?>

<form class="sp-class-filter" method="get" data-sp-class-filter
    style="display: flex; align-items: flex-end; gap: 12px; padding: 12px 0;">
    <div style="flex: 1; min-width: 0;">
        <label class="sp-class-filter-label" for="sp-att-class-filter"
            style="display: block; margin-bottom: 6px; font-weight: 700; font-size: 18px;">
            Class
        </label>

        <select id="sp-att-class-filter" name="class" data-sp-class-select
            style="width: 100%; height: 40px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 10px; padding: 0 12px; font-size: 16px;">
            <?php foreach ( $sp_panes as $sp_option ) : ?>
            <option value="<?php echo esc_attr( $sp_option['class_id'] ); ?>"
                <?php selected( $sp_active_id, $sp_option['class_id'] ); ?>>
                <?php echo esc_html( $sp_option['title'] ); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="sp-btn sp-btn-quiet sp-class-filter-btn"
        style="height: 40px; min-width: 80px; flex-shrink: 0; margin: 0; display: inline-flex; align-items: center; justify-content: center;">
        Switch
    </button>
</form>


<?php foreach ( $sp_panes as $sp_pane ) : ?>
<?php $sp_is_active = (int) $sp_pane['class_id'] === (int) $sp_active_id; ?>
<div style="padding-top: 16px;" class="sp-class-pane<?php echo $sp_is_active ? ' is-active' : ''; ?>"
    data-sp-class-pane="<?php echo esc_attr( $sp_pane['class_id'] ); ?>"
    <?php echo $sp_is_active ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- boolean attribute. ?>>
    <?php if ( $sp_pane['stats'] ) : ?>
    <div class="sp-card">
        <h2 class="sp-card-title"><?php echo esc_html( $sp_pane['title'] ); ?></h2>

        <div class="sp-meter">
            <div class="sp-meter-bar">
                <span
                    style="width: <?php echo esc_attr( min( 100, max( 0, $sp_pane['stats']['percentage'] ) ) ); ?>%"></span>
            </div>
            <span
                class="sp-meter-value"><?php echo wp_kses_post( sp_attendance_badge( $sp_pane['stats']['percentage'] ) ); ?></span>
        </div>

        <ul class="sp-chips">
            <li class="sp-chip sp-chip-good">Present
                <strong><?php echo esc_html( $sp_pane['stats']['present'] ); ?></strong></li>
            <li class="sp-chip sp-chip-bad">Absent
                <strong><?php echo esc_html( $sp_pane['stats']['absent'] ); ?></strong></li>
            <li class="sp-chip sp-chip-warn">Late <strong><?php echo esc_html( $sp_pane['stats']['late'] ); ?></strong>
            </li>
            <li class="sp-chip">Excused <strong><?php echo esc_html( $sp_pane['stats']['excused'] ); ?></strong></li>
            <li class="sp-chip">Sessions <strong><?php echo esc_html( $sp_pane['stats']['total'] ); ?></strong></li>
        </ul>
    </div>
    <?php else : ?>
    <div class="sp-card">
        <h2 class="sp-card-title"><?php echo esc_html( $sp_pane['title'] ); ?></h2>
        <div class="sp-empty">No attendance recorded yet.</div>
    </div>
    <?php endif; ?>

    <h2 class="sp-section-title">Class History</h2>

    <?php if ( ! $sp_pane['history'] ) : ?>
    <div class="sp-empty">No sessions recorded yet.</div>
    <?php else : ?>
    <div class="sp-table-wrap">
        <table class="sp-table">
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Topic</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
							foreach ( $sp_pane['history'] as $sp_row ) :
								$sp_badge_class = sp_attendance_row_class( $sp_row->status );
								?>
                <tr>
                    <td data-label="Date">
                        <?php echo esc_html( sp_portal_time( get_option( 'date_format' ), strtotime( $sp_row->session_date ) ) ); ?>
                    </td>
                    <td data-label="Topic">
                        <?php echo $sp_row->topic ? esc_html( $sp_row->topic ) : '<span class="sp-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ternary with escaped branches. ?>
                    </td>
                    <td data-label="Status"><span
                            class="sp-badge <?php echo esc_attr( $sp_badge_class ); ?>"><?php echo esc_html( ucfirst( $sp_row->status ) ); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>