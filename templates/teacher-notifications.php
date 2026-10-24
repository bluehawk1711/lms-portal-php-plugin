<?php
/**
 * Teacher portal: Announcements / Notifications page.
 *
 * Lists the active announcements aimed at the teachers, with the same
 * dismiss support the student portal has.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_announcements = sp_announcements_visible( 'teacher', 100 );
$sp_dismissed     = sp_announcement_dismissed_ids();
$sp_ajax_nonce    = wp_create_nonce( 'sp_portal_action_dismiss_announcement' );
$sp_ajax_url      = esc_url_raw( admin_url( 'admin-ajax.php' ) );
?>
<div class="sp-portal-notifications">
	<header class="sp-portal-notifications-header">
		<h1>Announcements</h1>
		<p>Messages from the office. Dismiss any you no longer need to see.</p>
	</header>

	<?php if ( ! $sp_announcements ) : ?>
		<div class="sp-portal-notifications-empty">
			<span class="sp-portal-notifications-empty-icon" aria-hidden="true">✉</span>
			<h2>No announcements</h2>
			<p>There are no active announcements for you right now.</p>
		</div>
	<?php else : ?>
		<div class="sp-portal-notifications-list">
			<?php foreach ( $sp_announcements as $sp_ann ) : ?>
				<?php
				$is_dismissed = in_array( (int) $sp_ann->id, $sp_dismissed, true );
				$is_banner    = 'banner' === (string) $sp_ann->display;
				?>
				<article class="sp-portal-notification-item<?php echo $is_dismissed ? ' is-dismissed' : ''; ?>">
					<div class="sp-portal-notification-content">
						<div class="sp-portal-notification-header">
							<h3><?php echo esc_html( $sp_ann->title ?: 'Announcement' ); ?></h3>
							<span class="sp-portal-notification-type"><?php echo $is_banner ? 'Banner' : 'Bar'; ?></span>
						</div>
						<div class="sp-portal-notification-body">
							<?php echo wp_kses_post( wpautop( $sp_ann->body ) ); ?>
						</div>
						<div class="sp-portal-notification-meta">
							<span class="sp-portal-notification-date">
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $sp_ann->created_at ) ) ); ?>
							</span>
							<button type="button" class="sp-portal-notification-dismiss"
								data-id="<?php echo esc_attr( (int) $sp_ann->id ); ?>"
								aria-label="Dismiss announcement">
								<?php echo $is_dismissed ? 'Restored' : 'Dismiss'; ?>
							</button>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<script>
			document.addEventListener( 'DOMContentLoaded', function () {
				var buttons = document.querySelectorAll( '.sp-portal-notification-dismiss' );
				buttons.forEach( function ( btn ) {
					btn.addEventListener( 'click', function () {
						var id = btn.getAttribute( 'data-id' );
						fetch( '<?php echo $sp_ajax_url; ?>', {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: 'action=sp_dismiss_announcement&announcement_id=' + id + '&nonce=' + '<?php echo $sp_ajax_nonce; ?>'
						} ).then( function ( res ) {
							return res.json();
						} ).then( function ( data ) {
							if ( data.success ) {
								var item = btn.closest( '.sp-portal-notification-item' );
								if ( item ) {
									item.classList.add( 'is-dismissed' );
									btn.textContent = 'Restored';
								}
							}
						} );
					} );
				} );
			} );
		</script>
	<?php endif; ?>
</div>