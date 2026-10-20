<?php
/**
 * Teacher profile: account details, editable name/phone/bio and the load.
 *
 * Expects $teacher from sp_render_teacher_portal().
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_teacher = ! empty( $teacher ) ? $teacher : sp_get_teacher_by_user_id( get_current_user_id() );

if ( ! $sp_teacher ) {
	?>
	<div class="sp-empty">
		This account has no teacher profile yet. Ask an administrator to add one.
	</div>
	<?php
	return;
}

$sp_classes = sp_get_classes( array( 'teacher_id' => $sp_teacher->id, 'status' => 'active' ) );
$sp_classes = is_array( $sp_classes ) ? $sp_classes : array();

$sp_students = 0;

foreach ( $sp_classes as $sp_load_class ) {
	$sp_students += (int) sp_count_class_students( $sp_load_class->id );
}

$sp_initials = function_exists( 'mb_strtoupper' ) && function_exists( 'mb_substr' )
	? mb_strtoupper( mb_substr( $sp_teacher->full_name, 0, 1 ) )
	: strtoupper( substr( $sp_teacher->full_name, 0, 1 ) );
?>
<div class="sp-page-head">
	<h1>Profile</h1>
	<p class="sp-page-sub">Your account details, contact information and teaching load.</p>
</div>

<div class="sp-pf">
	<section class="sp-pf-card sp-pf-account" aria-labelledby="sp-pf-account-title">
		<header class="sp-pf-head">
			<span class="sp-pf-avatar" aria-hidden="true"><?php echo esc_html( $sp_initials ); ?></span>
			<div class="sp-pf-identity">
				<h2 id="sp-pf-account-title"><?php echo esc_html( $sp_teacher->full_name ); ?></h2>
				<p><?php echo esc_html( $sp_teacher->email ); ?></p>
			</div>
			<span class="sp-badge <?php echo 'active' === (string) $sp_teacher->status ? 'sp-badge-good' : 'sp-badge-muted'; ?>">
				<?php echo esc_html( ucfirst( (string) $sp_teacher->status ) ); ?>
			</span>
		</header>

		<form method="post" class="sp-pf-form" data-sp-async data-sp-async-toast>
			<?php sp_teacher_nonce_field( 'save_profile' ); ?>

			<div class="sp-pf-grid">
				<label class="sp-pf-field">
					<span>Full name <em>*</em></span>
					<input type="text" name="full_name" required value="<?php echo esc_attr( $sp_teacher->full_name ); ?>" />
				</label>

				<label class="sp-pf-field">
					<span>Phone</span>
					<input type="text" name="phone" value="<?php echo esc_attr( $sp_teacher->phone ? $sp_teacher->phone : '' ); ?>" placeholder="Contact number" />
				</label>
			</div>

			<label class="sp-pf-field">
				<span>Bio</span>
				<textarea name="bio" rows="3" placeholder="A line about what you teach."><?php echo esc_textarea( $sp_teacher->bio ? $sp_teacher->bio : '' ); ?></textarea>
			</label>

			<label class="sp-pf-field">
				<span>Email (login)</span>
				<input type="email" value="<?php echo esc_attr( $sp_teacher->email ); ?>" readonly disabled />
				<small>The office changes login emails — contact an administrator.</small>
			</label>

			<div class="sp-pf-actions">
				<button type="submit" class="sp-btn sp-btn-primary">Save profile <span aria-hidden="true">&#8594;</span></button>
			</div>
		</form>
	</section>

	<section class="sp-pf-card" aria-labelledby="sp-pf-load-title">
		<div class="sp-pf-section-head">
			<span class="sp-pf-kicker">TEACHING LOAD</span>
			<h2 id="sp-pf-load-title">Your classes</h2>
		</div>

		<dl class="sp-pf-stats">
			<div class="sp-pf-stat">
				<dt>Active classes</dt>
				<dd><?php echo esc_html( number_format_i18n( count( $sp_classes ) ) ); ?></dd>
			</div>
			<div class="sp-pf-stat">
				<dt>Students taught</dt>
				<dd><?php echo esc_html( number_format_i18n( $sp_students ) ); ?></dd>
			</div>
			<div class="sp-pf-stat">
				<dt>Last sign-in</dt>
				<dd class="sp-pf-stat-small">
					<?php
					echo $sp_teacher->last_login_at
						? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_teacher->last_login_at ) ) )
						: 'Never';
					?>
				</dd>
			</div>
		</dl>

		<?php if ( $sp_classes ) : ?>
			<ul class="sp-pf-classes">
				<?php foreach ( $sp_classes as $sp_load ) : ?>
					<li>
						<a href="<?php echo esc_url( sp_teacher_url( 'classes/' . (int) $sp_load->id ) ); ?>">
							<span class="sp-pf-class-dot" aria-hidden="true"></span>
							<strong><?php echo esc_html( $sp_load->title ); ?></strong>
							<?php if ( ! empty( $sp_load->code ) ) : ?>
								<span class="sp-pf-class-code"><?php echo esc_html( $sp_load->code ); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="sp-pf-empty">No active classes assigned yet.</p>
		<?php endif; ?>
	</section>
</div>
