<?php
/**
 * Teacher portal login screen.
 *
 * A clean, centred sign-in card on a plain panel — matching the student
 * screen: brand logo banner, a welcome heading, boxed inputs with leading
 * icons and a full-width submit. `sp_portal_login_error()` keeps the wording
 * identical on both, and the markup stays parallel so the disabled and
 * throttled messages cannot drift.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_error = sp_portal_login_error();
?>
<div class="sp-auth">
    <main class="sp-auth-panel">
        <div class="sp-auth-card">
            <div class="sp-auth-brand">
                <img class="sp-auth-logo" src="<?php echo esc_url( SP_URL . 'assets/images/logo.png' ); ?>"
                    alt="Swavlamban Kendra" width="2360" height="292" decoding="async" />
            </div>

            <!-- <h1 class="sp-auth-title">Welcome back</h1> -->
            <p class="sp-auth-sub">Take attendance, publish notes and record results.</p>

            <?php if ( $sp_error ) : ?>
            <div class="sp-notice sp-notice-error"><?php echo esc_html( $sp_error ); ?></div>
            <?php endif; ?>

            <form class="sp-auth-form" method="post" action="<?php echo esc_url( sp_portal_home_url( 'teacher' ) ); ?>">
                <input type="hidden" name="sp_teacher_login" value="1" />
                <?php wp_nonce_field( 'sp_teacher_login', 'sp_teacher_nonce' ); ?>

                <div class="sp-field">
                    <label class="sp-field-label" for="sp_email">Email address</label>
                    <div class="sp-input">
                        <span class="sp-input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="m3 7 9 6 9-6" />
                            </svg>
                        </span>
                        <input type="email" id="sp_email" name="sp_email" placeholder="you@company.com"
                            autocomplete="username" required
                            value="<?php echo esc_attr( isset( $_POST['sp_email'] ) ? sanitize_email( wp_unslash( $_POST['sp_email'] ) ) : '' ); ?>" />
                    </div>
                </div>

                <div class="sp-field">
                    <div class="sp-field-head">
                        <label class="sp-field-label" for="sp_password">Password</label>
                        <a class="sp-auth-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Forgot
                            password?</a>
                    </div>
                    <div class="sp-input sp-input-pw">
                        <span class="sp-input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <rect x="4" y="10" width="16" height="11" rx="2" />
                                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                                <circle cx="12" cy="15.6" r="1.1" fill="currentColor" stroke="none" />
                            </svg>
                        </span>
                        <input type="password" id="sp_password" name="sp_password" placeholder="••••••••"
                            autocomplete="current-password" required />
                        <?php sp_password_toggle( 'sp_password' ); ?>
                    </div>
                </div>

                <label class="sp-check">
                    <input type="checkbox" name="sp_remember" value="1" checked />
                    Keep me signed in
                </label>

                <button type="submit" class="sp-auth-submit">
                    Sign in
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M5 12h14" />
                        <path d="m13 6 6 6-6 6" />
                    </svg>
                </button>
            </form>

            <p class="sp-auth-alt">
                Are you a student?
                <a href="<?php echo esc_url( sp_portal_home_url( 'student' ) ); ?>">Open the student portal</a>
            </p>

            <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <p class="sp-auth-alt sp-auth-admin">
                Also managing the portal?
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-dashboard' ) ); ?>">Open the admin
                    screens</a>.
            </p>
            <?php endif; ?>
        </div>
    </main>
</div>