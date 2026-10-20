<?php
/**
 * Student profile.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $profile ) ) {
	?>
	<div class="sp-notice sp-notice-warn" role="alert">
		This WordPress account is not linked to a student profile yet. Ask your institute to create your portal account.
	</div>
	<?php
	return;
}

$sp_user        = wp_get_current_user();
$sp_enrollments = sp_get_enrollments_for_profile( $profile->id, array( 'active' ) );
$sp_dob         = ( ! empty( $profile->dob ) && '0000-00-00' !== $profile->dob ) ? (string) $profile->dob : '';

$sp_date_fmt     = get_option( 'date_format' );
$sp_datetime_fmt = $sp_date_fmt . ' ' . get_option( 'time_format' );

// Initials for the avatar (max two letters).
$sp_words    = preg_split( '/\s+/', trim( (string) $profile->full_name ) );
$sp_initials = '';
foreach ( array_slice( array_filter( $sp_words ), 0, 2 ) as $sp_word ) {
	$sp_initials .= function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $sp_word, 0, 1 ) ) : strtoupper( substr( $sp_word, 0, 1 ) );
}
if ( '' === $sp_initials ) {
	$sp_initials = '?';
}

// Profile completeness, based on the fields the student can see.
$sp_checks = array(
	'Full name'     => ! empty( $profile->full_name ),
	'Email address' => ! empty( $profile->email ),
	'Phone number'  => ! empty( $profile->phone ),
	'Date of birth' => '' !== $sp_dob,
);
$sp_done    = count( array_filter( $sp_checks ) );
$sp_percent = (int) round( ( $sp_done / count( $sp_checks ) ) * 100 );
$sp_missing = array_keys( array_filter( $sp_checks, static function ( $ok ) {
	return ! $ok;
} ) );

$sp_status       = strtolower( (string) $profile->status );
$sp_status_class = 'sp-pill-' . sanitize_html_class( $sp_status ? $sp_status : 'unknown' );
?>

<div class="sp-page-head">
	<h1>My profile</h1>
	<p class="sp-page-sub">Keep your contact details current and review what the portal holds about you.</p>
</div>

<section class="sp-hero" aria-label="Profile summary">
	<div class="sp-avatar" aria-hidden="true"><?php echo esc_html( $sp_initials ); ?></div>

	<div class="sp-hero-body">
		<div  class="sp-hero-name">
			<h2 style="color: #fff;"><?php echo esc_html( $profile->full_name ); ?></h2>
			<span class="sp-pill <?php echo esc_attr( $sp_status_class ); ?>"><?php echo esc_html( ucfirst( $profile->status ) ); ?></span>
		</div>
		<p class="sp-hero-meta">
			<span><?php echo esc_html( $profile->email ); ?></span>
			<span class="sp-dot" aria-hidden="true">&middot;</span>
			<span>@<?php echo esc_html( $sp_user->user_login ); ?></span>
		</p>
	</div>

	<dl class="sp-stats">
		<div class="sp-stat">
			<dt>Active courses</dt>
			<dd><?php echo esc_html( (string) count( (array) $sp_enrollments ) ); ?></dd>
		</div>
		<div class="sp-stat">
			<dt>Last sign-in</dt>
			<dd class="sp-stat-small">
				<?php
				echo $profile->last_login_at
					? esc_html( sp_portal_time( $sp_date_fmt, strtotime( $profile->last_login_at ) ) )
					: 'First visit'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string.
				?>
			</dd>
		</div>
	</dl>
</section>

<div class="sp-profile-grid">

	<div class="sp-col-main">

		<div class="sp-card" id="sp-edit">
			<div class="sp-card-head">
				<h2 class="sp-card-title">Edit your details</h2>
				<p class="sp-card-sub">Changes are saved to your student record straight away.</p>
			</div>

			<form class="sp-form" method="post" action="<?php echo esc_url( sp_portal_url( 'profile' ) ); ?>" data-sp-async data-sp-async-toast data-sp-dirty-form>
				<?php sp_portal_nonce_field( 'save_profile' ); ?>

				<div class="sp-field">
					<label for="sp_full_name">Full name <span class="sp-req" title="Required">*</span></label>
					<input type="text" id="sp_full_name" name="full_name" required autocomplete="name"
						value="<?php echo esc_attr( $profile->full_name ); ?>" />
				</div>

				<div class="sp-field-row">
					<div class="sp-field">
						<label for="sp_phone">Phone</label>
						<input maxlength="12" type="tel" id="sp_phone" name="phone" autocomplete="tel" inputmode="tel"
							placeholder="e.g. 98765 43210"
							value="<?php echo esc_attr( $profile->phone ); ?>" />
					</div>

					<div class="sp-field">
						<label for="sp_dob">Date of birth</label>
						<input type="date" id="sp_dob" name="dob" autocomplete="bday"
							max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
							value="<?php echo esc_attr( $sp_dob ); ?>" />
					</div>
				</div>

				<div class="sp-locked">
					<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
					<p>Your email address, username, enrolment ID and status are held by your institute and cannot be changed here.</p>
				</div>

				<div class="sp-form-actions">
					<button type="submit" class="sp-btn sp-btn-primary" data-sp-save disabled>Save changes</button>
					<button type="reset" class="sp-btn sp-btn-ghost" data-sp-reset hidden>Discard</button>
					<span class="sp-form-state" data-sp-state aria-live="polite">No changes yet</span>
				</div>
			</form>
		</div>

		<div class="sp-card">
			<div class="sp-card-head">
				<h2 class="sp-card-title">Your enrolments</h2>
				<p class="sp-card-sub">Courses you are currently active in.</p>
			</div>

			<?php if ( ! $sp_enrollments ) : ?>
				<div class="sp-empty">
					<svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16"/></svg>
					<p>You are not enrolled in a course yet.</p>
				</div>
			<?php else : ?>
				<ul class="sp-enrol-list">
					<?php foreach ( $sp_enrollments as $sp_enrol ) : ?>
						<li class="sp-enrol">
							<span class="sp-enrol-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>
							</span>
							<div class="sp-enrol-body">
								<strong class="sp-enrol-title"><?php echo esc_html( $sp_enrol->class_title ); ?></strong>
								<?php if ( ! empty( $sp_enrol->admission_date ) ) : ?>
									<span class="sp-muted">Admitted <?php echo esc_html( sp_portal_time( $sp_date_fmt, strtotime( $sp_enrol->admission_date ) ) ); ?></span>
								<?php endif; ?>
							</div>
							<div class="sp-enrol-id">
								<?php if ( ! empty( $sp_enrol->enroll_id ) ) : ?>
									<code><?php echo esc_html( $sp_enrol->enroll_id ); ?></code>
									<button type="button" class="sp-copy" data-sp-copy="<?php echo esc_attr( $sp_enrol->enroll_id ); ?>" aria-label="Copy enrolment ID">
										<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
									</button>
								<?php else : ?>
									<span class="sp-muted">No enrolment ID yet</span>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

	</div>

	<aside class="sp-col-side">

		<div class="sp-card sp-complete">
			<h2 class="sp-card-title">Profile completeness</h2>
			<div class="sp-meter" role="progressbar" aria-valuenow="<?php echo esc_attr( $sp_percent ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completeness">
				<span style="width: <?php echo esc_attr( $sp_percent ); ?>%"></span>
			</div>
			<p class="sp-complete-line"><strong><?php echo esc_html( $sp_percent ); ?>%</strong> complete</p>
			<?php if ( $sp_missing ) : ?>
				<p class="sp-muted">Still to add: <?php echo esc_html( implode( ', ', $sp_missing ) ); ?>.</p>
				<a class="sp-link" href="#sp-edit">Update details</a>
			<?php else : ?>
				<p class="sp-muted">Everything is filled in. Thank you!</p>
			<?php endif; ?>
		</div>

		<div class="sp-card">
			<h2 class="sp-card-title">Account</h2>
			<dl class="sp-kv">
				<dt>Email</dt>
				<dd><?php echo esc_html( $profile->email ); ?></dd>

				<dt>Username</dt>
				<dd><?php echo esc_html( $sp_user->user_login ); ?></dd>

				<dt>Phone</dt>
				<dd><?php echo $profile->phone ? esc_html( $profile->phone ) : '<span class="sp-muted">Not recorded</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?></dd>

				<dt>Date of birth</dt>
				<dd><?php echo '' !== $sp_dob ? esc_html( sp_portal_time( $sp_date_fmt, strtotime( $sp_dob ) ) ) : '<span class="sp-muted">Not recorded</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary. ?></dd>

				<dt>Last sign-in</dt>
				<dd>
					<?php
					echo $profile->last_login_at
						? esc_html( sp_portal_time( $sp_datetime_fmt, strtotime( $profile->last_login_at ) ) )
						: '<span class="sp-muted">This is your first sign-in</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary.
					?>
				</dd>
			</dl>
		</div>

		<div class="sp-card">
			<h2 class="sp-card-title">Security</h2>
			<p class="sp-muted sp-security-text">Use a strong password you do not use anywhere else.</p>
			<p class="sp-card-actions">
				<a class="sp-btn sp-btn-ghost" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Change password</a>
			</p>
		</div>

	</aside>
</div>

<script>
( function () {
	var form = document.querySelector( '[data-sp-dirty-form]' );
	if ( form ) {
		var save  = form.querySelector( '[data-sp-save]' );
		var reset = form.querySelector( '[data-sp-reset]' );
		var state = form.querySelector( '[data-sp-state]' );
		var inputs = form.querySelectorAll( 'input[name]:not([type="hidden"])' );
		var initial = {};
		inputs.forEach( function ( el ) { initial[ el.name ] = el.value; } );

		var check = function () {
			var dirty = false;
			inputs.forEach( function ( el ) { if ( el.value !== initial[ el.name ] ) { dirty = true; } } );
			save.disabled = ! dirty;
			reset.hidden  = ! dirty;
			state.textContent = dirty ? 'You have unsaved changes' : 'No changes yet';
			form.classList.toggle( 'is-dirty', dirty );
		};
		form.addEventListener( 'input', check );
		form.addEventListener( 'reset', function () { setTimeout( check, 0 ); } );
		form.addEventListener( 'submit', function () { save.classList.add( 'is-loading' ); save.textContent = 'Saving\u2026'; } );
	}

	document.querySelectorAll( '[data-sp-copy]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var text = btn.getAttribute( 'data-sp-copy' );
			var done = function () {
				btn.classList.add( 'is-copied' );
				setTimeout( function () { btn.classList.remove( 'is-copied' ); }, 1400 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done );
			}
		} );
	} );
} )();
</script>