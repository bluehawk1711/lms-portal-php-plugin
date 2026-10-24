<?php
/**
 * Teacher profile.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The teacher route passes the record as $teacher.
$profile = isset( $teacher ) ? $teacher : null;

if ( empty( $profile ) ) {
	?>
<div class="sp-notice sp-notice-warn" role="alert">
    This WordPress account is not linked to a teacher profile yet. Ask your administrator to create your portal account.
</div>
<?php
	return;
}

$sp_date_fmt = get_option( 'date_format' );

// Assumed helper: returns the teacher's active classes (objects with class_title, class_code,
// student_count, schedule_label). Adjust the name to match your plugin.
$sp_classes = function_exists( 'sp_get_classes_for_teacher' )
	? (array) sp_get_classes_for_teacher( $profile->id, array( 'active' ) )
	: array();

$sp_active_classes = count( $sp_classes );
$sp_students       = 0;
foreach ( $sp_classes as $sp_class ) {
	$sp_students += (int) ( isset( $sp_class->student_count ) ? $sp_class->student_count : 0 );
}

$sp_initial   = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( trim( (string) $profile->full_name ), 0, 1 ) ) : strtoupper( substr( trim( (string) $profile->full_name ), 0, 1 ) );
$sp_initial   = '' !== $sp_initial ? $sp_initial : '?';
$sp_photo_url = ! empty( $profile->photo_id )
	? (string) wp_get_attachment_image_url( (int) $profile->photo_id, 'thumbnail' )
	: '';
$sp_bio       = ! empty( $profile->bio ) ? (string) $profile->bio : '';

$sp_status       = strtolower( (string) $profile->status );
$sp_status_class = 'sp-pill-' . sanitize_html_class( $sp_status ? $sp_status : 'unknown' );
?>

<div class="sp-page-head">
    <h1>Profile</h1>
    <p class="sp-page-sub">Your account details, contact information and teaching load.</p>
</div>

<div class="sp-t-grid">

    <div class="sp-card sp-t-edit" id="sp-edit">
        <form class="sp-form" method="post" enctype="multipart/form-data"
            action="<?php echo esc_url( sp_teacher_url( 'profile' ) ); ?>" data-sp-async data-sp-async-toast>
            <?php sp_teacher_nonce_field( 'save_profile' ); ?>
            <input type="hidden" name="sp_action" value="save_profile" />
            <input type="hidden" name="sp_redirect" value="<?php echo esc_url( sp_teacher_url( 'profile' ) ); ?>" />

            <div class="sp-t-identity">
                <div class="sp-t-avatar<?php echo $sp_photo_url ? ' has-photo' : ''; ?>" data-sp-avatar>
                    <?php if ( $sp_photo_url ) : ?>
                    <img src="<?php echo esc_url( $sp_photo_url ); ?>" alt="" />
                    <?php else : ?>
                    <span><?php echo esc_html( $sp_initial ); ?></span>
                    <?php endif; ?>
                </div>

                <div class="sp-t-identity-body">
                    <div class="sp-t-name-row">
                        <h2><?php echo esc_html( $profile->full_name ); ?></h2>
                        <span
                            class="sp-t-pill <?php echo esc_attr( $sp_status_class ); ?>"><?php echo esc_html( ucfirst( $profile->status ) ); ?></span>
                    </div>
                    <p class="sp-muted"><?php echo esc_html( $profile->email ); ?></p>
                </div>

                <label class="sp-btn sp-btn-ghost sp-t-photo-btn" for="sp_photo">Change photo</label>
                <input class="sp-t-visually-hidden" type="file" id="sp_photo" name="photo" accept="image/*"
                    data-sp-photo-input />
            </div>

            <div class="sp-field-row">
                <div class="sp-field">
                    <label for="sp_full_name">Full name <span class="sp-req" title="Required">*</span></label>
                    <input type="text" id="sp_full_name" name="full_name" required autocomplete="name"
                        value="<?php echo esc_attr( $profile->full_name ); ?>" />
                </div>

                <div class="sp-field">
                    <label for="sp_phone">Phone</label>
                    <input type="tel" id="sp_phone" name="phone" autocomplete="tel" inputmode="tel" maxlength="16"
                        placeholder="e.g. +91 98765 43210" value="<?php echo esc_attr( $profile->phone ); ?>" />
                </div>
            </div>

            <div class="sp-field">
                <div class="sp-t-label-row">
                    <label for="sp_bio">Bio</label>
                    <span class="sp-t-hint">Public within academy</span>
                </div>
                <textarea id="sp_bio" name="bio" rows="4"
                    maxlength="400"><?php echo esc_textarea( $sp_bio ); ?></textarea>
            </div>

            <div class="sp-field">
                <label for="sp_email">Email (login)</label>
                <div class="sp-t-locked-input">
                    <input type="email" id="sp_email" value="<?php echo esc_attr( $profile->email ); ?>" disabled />
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="11" width="16" height="10" rx="2" />
                        <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                    </svg>
                </div>
                <p class="sp-help sp-t-info">
                    <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 8h.01M11 12h1v4h1" />
                    </svg>
                    The office changes login emails &mdash; contact an administrator to request updates.
                </p>
            </div>

            <div class="sp-form-actions sp-t-actions">
                <button type="submit" class="sp-btn sp-btn-primary" data-sp-save>
                    Save profile
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </button>
            </div>
        </form>
    </div>

    <aside class="sp-col-side">

        <div class="sp-card">
            <p class="sp-t-eyebrow">Teaching load</p>
            <h2 class="sp-card-title">Your classes</h2>

            <dl class="sp-t-stats">
                <div class="sp-t-stat">
                    <dt>Active classes</dt>
                    <dd><?php echo esc_html( (string) $sp_active_classes ); ?></dd>
                </div>
                <div class="sp-t-stat">
                    <dt>Students taught</dt>
                    <dd><?php echo esc_html( (string) $sp_students ); ?></dd>
                </div>
                <div class="sp-t-stat">
                    <dt>Last sign-in</dt>
                    <dd class="sp-t-stat-small">
                        <?php
						echo $profile->last_login_at
							? esc_html( sp_portal_time( $sp_date_fmt, strtotime( $profile->last_login_at ) ) )
							: 'First visit'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static string.
						?>
                    </dd>
                </div>
            </dl>

            <p class="sp-t-eyebrow">Current cohorts</p>
            <?php if ( ! $sp_classes ) : ?>
            <p class="sp-muted">You are not assigned to a class yet.</p>
            <?php else : ?>
            <ul class="sp-t-cohorts">
                <?php foreach ( $sp_classes as $sp_class ) : ?>
                <li class="sp-t-cohort">
                    <div class="sp-t-cohort-top">
                        <span class="sp-t-dot" aria-hidden="true"></span>
                        <strong><?php echo esc_html( $sp_class->class_title ); ?></strong>
                        <?php if ( ! empty( $sp_class->class_code ) ) : ?>
                        <code><?php echo esc_html( $sp_class->class_code ); ?></code>
                        <?php endif; ?>
                    </div>
                    <div class="sp-t-cohort-meta sp-muted">
                        <span>
                            <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="3.5" />
                                <path d="M5 20a7 7 0 0 1 14 0" />
                            </svg>
                            <?php
									$sp_count = (int) ( isset( $sp_class->student_count ) ? $sp_class->student_count : 0 );
									echo esc_html( sprintf( _n( '%d Enrolled Student', '%d Enrolled Students', $sp_count, 'student-portal' ), $sp_count ) );
									?>
                        </span>
                        <?php if ( ! empty( $sp_class->schedule_label ) ) : ?>
                        <span><?php echo esc_html( $sp_class->schedule_label ); ?></span>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <p class="sp-t-center">
                <a class="sp-link sp-t-timetable" href="<?php echo esc_url( sp_portal_url( 'calendar' ) ); ?>">View
                    detailed teaching timetable &rsaquo;</a>
            </p>
        </div>

        <div class="sp-card sp-t-security">
            <span class="sp-t-shield" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.500 7-10V6z" />
                    <path d="M9.500 12l2 2 3.500-4" />
                </svg>
            </span>
            <div>
                <h2 class="sp-t-sec-title">Account Security</h2>
                <p class="sp-muted">Two-factor authentication is enforced by your Kendra coordinator.</p>
                <a class="sp-t-reset" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Request password reset</a>
            </div>
        </div>

    </aside>
</div>

<script>
(function() {
    var input = document.querySelector('[data-sp-photo-input]');
    var avatar = document.querySelector('[data-sp-avatar]');

    if (!input || !avatar) {
        return;
    }

    // Show the newly chosen photo in the avatar before saving.
    input.addEventListener('change', function() {
        var file = input.files && input.files[0];

        if (!file || 0 !== file.type.indexOf('image/')) {
            return;
        }

        var img = document.createElement('img');
        img.alt = '';
        img.src = URL.createObjectURL(file);
        avatar.innerHTML = '';
        avatar.classList.add('has-photo');
        avatar.appendChild(img);
    });
})();
</script>