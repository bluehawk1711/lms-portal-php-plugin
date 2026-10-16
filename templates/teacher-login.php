<?php
/**
 * Teacher portal login screen.
 *
 * Same split layout as the student screen, deliberately: `sp_portal_login_error()`
 * keeps the wording identical on both, and the markup is kept parallel so the
 * disabled and throttled messages cannot drift.
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
			<p class="sp-auth-sub">Take attendance, publish notes and record results.</p>

			<?php if ( $sp_error ) : ?>
				<div class="sp-notice sp-notice-error"><?php echo esc_html( $sp_error ); ?></div>
			<?php endif; ?>

			<form class="sp-auth-form" method="post" action="<?php echo esc_url( sp_portal_home_url( 'teacher' ) ); ?>">
				<input type="hidden" name="sp_teacher_login" value="1" />
				<?php wp_nonce_field( 'sp_teacher_login', 'sp_teacher_nonce' ); ?>

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
				Are you a student?
				<a href="<?php echo esc_url( sp_portal_home_url( 'student' ) ); ?>">Open the student portal</a>
			</p>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<p class="sp-auth-alt sp-auth-admin">
					Also managing the portal?
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-dashboard' ) ); ?>">Open the admin screens</a>.
				</p>
			<?php endif; ?>
		</div>
	</section>

	<aside class="sp-auth-hero">
		<div class="sp-auth-hero-inner">
			<h2 class="sp-auth-hero-title">Welcome to <span>teacher portal</span></h2>
			<p class="sp-auth-hero-sub">Login to access your account</p>
			<div class="sp-auth-art-wrap">
				<?php sp_auth_illustration(); ?>
			</div>
		</div>
	</aside>
</div>
