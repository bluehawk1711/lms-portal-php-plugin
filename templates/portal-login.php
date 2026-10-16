<?php
/**
 * Student portal login screen.
 *
 * Full-bleed split layout rendered without any site chrome: the form sits on a
 * light panel, the accent panel carries the welcome message. Shared helpers
 * (error text, password toggle, illustration) live in portal-render.php so the
 * teacher screen cannot drift away from this one.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_error = sp_portal_login_error();
?>
<div class="sp-auth">
	<section class="sp-auth-panel">
		<div class="sp-auth-card">
			<h1 class="sp-auth-title">Login</h1>
			<p class="sp-auth-sub">Sign in with the email address your institute registered for you.</p>

			<?php if ( $sp_error ) : ?>
				<div class="sp-notice sp-notice-error"><?php echo esc_html( $sp_error ); ?></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['sent'] ) && 'reset' === sanitize_key( wp_unslash( $_GET['sent'] ) ) ) : ?>
				<div class="sp-notice sp-notice-good">Check your inbox for a password reset link.</div>
			<?php endif; ?>

			<form class="sp-auth-form" method="post" action="<?php echo esc_url( sp_portal_home_url( 'student' ) ); ?>">
				<input type="hidden" name="sp_portal_login" value="1" />
				<?php wp_nonce_field( 'sp_portal_login', 'sp_portal_nonce' ); ?>

				<div class="sp-float">
					<input
						type="email"
						id="sp_email"
						name="sp_email"
						placeholder=" "
						autocomplete="username"
						required
						value="<?php echo esc_attr( isset( $_POST['sp_email'] ) ? sanitize_email( wp_unslash( $_POST['sp_email'] ) ) : '' ); ?>"
					/>
					<label for="sp_email">Email address</label>
				</div>

				<div class="sp-float sp-float-pw">
					<input
						type="password"
						id="sp_password"
						name="sp_password"
						placeholder=" "
						autocomplete="current-password"
						required
					/>
					<label for="sp_password">Password</label>
					<?php sp_password_toggle( 'sp_password' ); ?>
				</div>

				<div class="sp-auth-row">
					<label class="sp-check">
						<input type="checkbox" name="sp_remember" value="1" checked />
						Keep me signed in
					</label>
					<a class="sp-auth-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Forgot your password?</a>
				</div>

				<button type="submit" class="sp-auth-submit">Sign in</button>
			</form>

			<p class="sp-auth-alt">
				Are you a teacher?
				<a href="<?php echo esc_url( sp_portal_home_url( 'teacher' ) ); ?>">Open the teacher portal</a>
			</p>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<div class="sp-auth-admin">
					<?php echo sp_certificate_manager_notice(); ?>
					<p class="sp-auth-alt">
						Also managing the portal?
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-dashboard' ) ); ?>">Open the admin screens</a>.
					</p>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<aside class="sp-auth-hero">
		<div class="sp-auth-hero-inner">
			<h2 class="sp-auth-hero-title">Welcome to <span>student portal</span></h2>
			<p class="sp-auth-hero-sub">Login to access your account</p>
			<div class="sp-auth-art-wrap">
				<?php sp_auth_illustration(); ?>
			</div>
		</div>
	</aside>
</div>
