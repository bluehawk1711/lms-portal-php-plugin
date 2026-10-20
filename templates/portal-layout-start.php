<?php
/**
 * Portal chrome: sidebar shell, top bar and page opening.
 *
 * Three columns around every screen, the layout the mockup asks for: a left
 * sidebar with the brand, section nav and the office contact card; the content
 * column with the screen's own title; and a right rail (closed and filled by
 * portal-layout-end.php) carrying the profile card, month calendar and
 * attendance progress. Below 768px the sidebar becomes a drawer driven by the
 * existing initMobileNav() hooks (.sp-nav-toggle / .sp-portal-nav).
 *
 * Expects $slug, $route and $profile from sp_render_portal().
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_current         = isset( $slug ) ? $slug : 'home';
$sp_has_rail        = ( 'home' === $sp_current );
$sp_routes          = sp_portal_routes();
$sp_nav_current     = isset( $sp_routes[ $sp_current ]['parent'] ) ? $sp_routes[ $sp_current ]['parent'] : $sp_current;
$sp_user            = wp_get_current_user();
$sp_portal_notice   = sp_portal_notice();
$sp_portal_error    = sp_portal_notice( 'sp_notice_error' );
$sp_title           = isset( $sp_routes[ $sp_nav_current ]['label'] ) ? $sp_routes[ $sp_nav_current ]['label'] : '';
$sp_display_name    = ( $profile && ! empty( $profile->full_name ) ) ? $profile->full_name : $sp_user->display_name;
$sp_contact_phone   = trim( (string) sp_setting( 'institute_phone', '' ) );
$sp_contact_email   = trim( (string) sp_setting( 'institute_email', '' ) );
$sp_contact_visible = ( '' !== $sp_contact_phone || '' !== $sp_contact_email );
?>
<div class="sp-portal sp-student-portal">
	<div class="sp-portal-shell<?php echo $sp_has_rail ? ' has-rail' : ' no-rail'; ?>">
		<aside class="sp-portal-side" id="sp-portal-side">
			<div class="sp-side-brand">
				<a class="sp-side-brand-link" href="<?php echo esc_url( sp_portal_url() ); ?>">
					<span class="sp-side-mark" aria-hidden="true">
						<?php $sp_logo = get_theme_mod( 'custom_logo' ); ?>
						<?php if ( $sp_logo ) : ?>
							<span class="sp-side-mark-img"><?php echo wp_kses_post( $sp_logo ); ?></span>
						<?php else : ?>
							<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5 12 4l9 4.5-9 4.5z" /><path d="M7 11v4.5c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5V11" /><path d="M21 8.5V14" /></svg>
						<?php endif; ?>
					</span>
					<span class="sp-side-brand-text">
						<strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
						<span>Student portal</span>
					</span>
				</a>
			</div>

			<nav id="sp-portal-nav" class="sp-portal-nav" aria-label="Portal sections">
				<?php foreach ( $sp_routes as $sp_key => $sp_def ) : ?>
					<?php if ( empty( $sp_def['hidden'] ) ) : ?>
						<?php $sp_side_active = $sp_key === $sp_nav_current; ?>
						<a
							class="sp-portal-nav-link<?php echo $sp_side_active ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( sp_portal_url( 'home' === $sp_key ? '' : $sp_key ) ); ?>"
							<?php echo $sp_side_active ? ' aria-current="page"' : ''; ?>
						>
							<?php echo sp_portal_nav_icon( $sp_key ); // phpcs:ignore WordPress.Security.EscapeOutputOutputNotEscaped -- inline SVG. ?>
							<span><?php echo esc_html( $sp_def['label'] ); ?></span>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</nav>

			<?php if ( $sp_contact_visible ) : ?>
				<div class="sp-side-contact">
					<p class="sp-side-contact-title">Need help?</p>
					<p class="sp-side-contact-text">Contact the office &mdash; we are happy to help.</p>
					<?php if ( '' !== $sp_contact_phone ) : ?>
						<a class="sp-side-contact-link" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $sp_contact_phone ) ); ?>">
							<?php echo esc_html( $sp_contact_phone ); ?>
						</a>
					<?php endif; ?>
					<?php
					$sp_contact_mail = '' !== $sp_contact_email ? $sp_contact_email : get_option( 'admin_email' );
					?>
					<a class="sp-side-contact-link" href="<?php echo esc_url( 'mailto:' . antispambot( $sp_contact_mail ) ); ?>">
						<?php echo esc_html( antispambot( $sp_contact_mail ) ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="sp-side-user">
				<span class="sp-side-avatar" aria-hidden="true"><?php echo esc_html( sp_portal_initials( $sp_display_name ) ); ?></span>
				<span class="sp-side-user-text">
					<strong><?php echo esc_html( $sp_display_name ); ?></strong>
					<a href="<?php echo esc_url( sp_portal_logout_url( 'student' ) ); ?>">Log out</a>
				</span>
			</div>
		</aside>

		<div class="sp-nav-backdrop" hidden></div>

		<div class="sp-portal-col">
			<header class="sp-portal-top">
				<button type="button" class="sp-nav-toggle" aria-controls="sp-portal-nav"
					aria-expanded="false" aria-label="Open navigation menu">
					<span class="sp-nav-toggle-bar" aria-hidden="true"></span>
					<span class="sp-nav-toggle-bar" aria-hidden="true"></span>
					<span class="sp-nav-toggle-bar" aria-hidden="true"></span>
				</button>

				<h1 class="sp-portal-title"><?php echo esc_html( $sp_title ); ?></h1>
			</header>

			<main class="sp-portal-main">
				<?php if ( $sp_portal_error ) : ?>
					<div class="sp-notice sp-notice-error"><?php echo esc_html( $sp_portal_error ); ?></div>
				<?php endif; ?>

				<?php if ( $sp_portal_notice ) : ?>
					<div class="sp-notice sp-notice-good"><?php echo esc_html( $sp_portal_notice ); ?></div>
				<?php endif; ?>

				<?php sp_render_announcements( 'student' ); ?>
