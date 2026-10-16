<?php
/**
 * Teacher portal chrome: a sidebar shell around each screen.
 *
 * The sidebar carries the brand, the section nav (with the active state the
 * mockup asks for) and a quick CTA; the content column holds the screen's own
 * heading, the main column and the footer. Below 1024px the sidebar simply
 * restacks on top - no drawer JavaScript, so nothing here depends on the
 * student portal's top-bar nav.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_user        = wp_get_current_user();
$sp_notice      = sp_teacher_notice();
$sp_error       = sp_teacher_error();
$sp_nav_current = $slug;

if ( 'class' === $sp_nav_current ) {
	$sp_nav_current = 'classes';
} elseif ( 'material-edit' === $sp_nav_current ) {
	$sp_nav_current = 'materials';
} elseif ( 'assignment-edit' === $sp_nav_current ) {
	$sp_nav_current = 'assignments';
}

if ( ! function_exists( 'sp_teacher_nav_icon' ) ) {
	/**
	 * Inline line icon for a sidebar item. Decorative only - the label is text.
	 *
	 * @param string $name Icon key: home, classes, materials, assignments, results.
	 * @return string SVG markup.
	 */
	function sp_teacher_nav_icon( $name ) {
		$paths = array(
			'home'        => '<path d="M3 10.5 12 3l9 7.5" /><path d="M5.5 9.5V21h13V9.5" /><path d="M10 21v-6h4v6" />',
			'classes'     => '<rect x="3" y="4" width="7" height="7" rx="1.5" /><rect x="14" y="4" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" />',
			'materials'   => '<path d="M6 3h8l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" /><path d="M14 3v5h5" /><path d="M8.5 13h7M8.5 17h5" />',
			'assignments' => '<path d="M7 3h10a1 1 0 0 1 1 1v16l-6-3.5L6 20V4a1 1 0 0 1 1-1z" /><path d="M9.5 8.5h5M9.5 12h3" />',
			'results'     => '<path d="M4 20V10" /><path d="M10 20V4" /><path d="M16 20v-7" /><path d="M22 20H2" />',
			'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" />',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="sp-side-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}
?>
<div class="sp-portal sp-teacher-portal">
	<div class="sp-teacher-shell">
		<aside class="sp-side" id="sp-teacher-side">
			<div class="sp-side-brand">
				<a class="sp-side-brand-link" href="<?php echo esc_url( sp_teacher_url() ); ?>">
					<span class="sp-side-mark" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5 12 4l9 4.5-9 4.5z" /><path d="M7 11v4.5c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5V11" /><path d="M21 8.5V14" /></svg>
					</span>
					<span class="sp-side-brand-text">
						<strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
						<span>Teacher portal</span>
					</span>
				</a>
			</div>

			<?php if ( is_user_logged_in() ) : ?>
				<nav class="sp-side-nav" aria-label="Teacher sections">
					<?php
					$sp_side_items = array(
						'home'        => array( 'label' => 'Dashboard', 'url' => sp_teacher_url() ),
						'classes'     => array( 'label' => 'My classes', 'url' => sp_teacher_url( 'classes' ) ),
						'assignments' => array( 'label' => 'Assignments', 'url' => sp_teacher_url( 'assignments' ) ),
						'materials'   => array( 'label' => 'Materials', 'url' => sp_teacher_url( 'materials' ) ),
						'results'     => array( 'label' => 'Results', 'url' => sp_teacher_url( 'results' ) ),
						'calendar'    => array( 'label' => 'Calendar', 'url' => sp_teacher_url( 'calendar' ) ),
					);
					foreach ( $sp_side_items as $sp_side_key => $sp_side_item ) :
						$sp_side_active = $sp_side_key === $sp_nav_current;
						?>
						<a class="sp-side-nav-link<?php echo $sp_side_active ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( $sp_side_item['url'] ); ?>"
							<?php echo $sp_side_active ? 'aria-current="page"' : ''; ?>>
							<?php echo sp_teacher_nav_icon( $sp_side_key ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span><?php echo esc_html( $sp_side_item['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="sp-side-promo">
					<p class="sp-side-promo-title">Start a new class</p>
					<p class="sp-side-promo-text">Create it once, then add students, attendance and results.</p>
					<a class="sp-side-promo-btn" href="<?php echo esc_url( sp_teacher_url( 'classes' ) ); ?>#sp-create-class">Create class</a>
				</div>

				<div class="sp-side-user">
					<span class="sp-side-avatar" aria-hidden="true">
						<?php echo esc_html( mb_strtoupper( mb_substr( $sp_user->display_name, 0, 1 ) ) ); ?>
					</span>
					<span class="sp-side-user-text">
						<strong><?php echo esc_html( $sp_user->display_name ); ?></strong>
						<a href="<?php echo esc_url( sp_portal_logout_url( 'teacher' ) ); ?>">Log out</a>
					</span>
				</div>
			<?php endif; ?>
		</aside>

		<div class="sp-side-backdrop" hidden></div>

		<div class="sp-teacher-col">
			<header class="sp-side-bar">
				<button type="button" class="sp-side-toggle" aria-expanded="true" aria-controls="sp-teacher-side" aria-label="Toggle navigation menu" title="Toggle menu">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
				</button>
				<a class="sp-side-bar-brand" href="<?php echo esc_url( sp_teacher_url() ); ?>">
					<span class="sp-side-mark" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5 12 4l9 4.5-9 4.5z" /><path d="M7 11v4.5c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5V11" /><path d="M21 8.5V14" /></svg>
					</span>
					<strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
				</a>
			</header>

			<main class="sp-portal-main">
				<?php if ( $sp_error ) : ?>
					<div class="sp-notice sp-notice-error"><?php echo esc_html( $sp_error ); ?></div>
				<?php endif; ?>

				<?php if ( $sp_notice ) : ?>
					<div class="sp-notice sp-notice-good"><?php echo esc_html( $sp_notice ); ?></div>
				<?php endif; ?>
