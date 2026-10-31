<?php
/**
 * Student certificates.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $profile ) ) {
	?>
	<div class="sp-notice sp-notice-warn">
		This WordPress account is not linked to a student profile yet. Ask your institute to create your portal account.
	</div>
	<?php
	return;
}

$sp_certs = sp_get_certificates_for_profile( (int) $profile->id );

/*
 * Resolve each certificate's class once so the card can name the course it
 * belongs to, and collect the small stats for the summary strip.
 */
$sp_cert_cards   = array();
$sp_course_names = array();
$sp_latest       = '';

foreach ( (array) $sp_certs as $sp_cert ) {
	$sp_class = sp_get_class( (int) $sp_cert->class_id );
	$sp_title = $sp_class ? trim( (string) $sp_class->title ) : '';
	$sp_code  = $sp_class && $sp_class->code ? trim( (string) $sp_class->code ) : '';

	if ( '' !== $sp_title && ! isset( $sp_course_names[ $sp_title ] ) ) {
		$sp_course_names[ $sp_title ] = $sp_title;
	}

	if ( $sp_cert->issued_date && ( '' === $sp_latest || $sp_cert->issued_date > $sp_latest ) ) {
		$sp_latest = (string) $sp_cert->issued_date;
	}

	$sp_cert_cards[] = array(
		'cert'  => $sp_cert,
		'title' => '' !== $sp_title ? $sp_title : 'Certificate',
		'code'  => $sp_code,
	);
}

$sp_cert_total   = count( $sp_cert_cards );
$sp_course_total = count( $sp_course_names );
?>
<div class="sp-page-head">
	<h1>Certificates</h1>
	<p class="sp-page-sub">Every certificate your institute has issued to you, ready to open and print.</p>
</div>

<?php if ( ! $sp_cert_cards ) : ?>
	<div class="sp-empty sp-cert-empty">
		<span class="sp-cert-empty-mark" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.6"
				stroke-linecap="round" stroke-linejoin="round">
				<circle cx="12" cy="9" r="6" />
				<path d="M8.5 14.3 7 22l5-2.6L17 22l-1.5-7.7" />
			</svg>
		</span>
		<strong>No certificates yet</strong>
		<p>Your institute will issue one here as soon as your marks and attendance qualify for a course.</p>
	</div>
<?php else : ?>
	<section class="sp-cert-summary" aria-label="Certificate summary">
		<div class="sp-cert-stat">
			<strong><?php echo esc_html( number_format_i18n( $sp_cert_total ) ); ?></strong>
			<span>Certificate<?php echo 1 === $sp_cert_total ? '' : 's'; ?> issued</span>
		</div>
		<div class="sp-cert-stat">
			<strong><?php echo esc_html( number_format_i18n( $sp_course_total ) ); ?></strong>
			<span>Course<?php echo 1 === $sp_course_total ? '' : 's'; ?> completed</span>
		</div>
		<?php if ( $sp_latest ) : ?>
			<div class="sp-cert-stat">
				<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_latest ) ) ); ?></strong>
				<span>Most recent issue</span>
			</div>
		<?php endif; ?>
	</section>

	<div class="sp-cert-list">
		<?php foreach ( $sp_cert_cards as $sp_card ) :
			$sp_cert      = $sp_card['cert'];
			$sp_open_url  = sp_certificate_vendor_url( $sp_cert );
			$sp_grade     = sp_certificate_display_grade( $sp_cert );
			$sp_void      = $sp_cert->status && 'issued' !== (string) $sp_cert->status;
			$sp_issued_on = $sp_cert->issued_date
				? date_i18n( get_option( 'date_format' ), strtotime( $sp_cert->issued_date ) )
				: '';
			?>
			<article class="sp-card sp-cert-item<?php echo $sp_void ? ' is-void' : ''; ?>">
				<div class="sp-cert-seal<?php echo '' !== $sp_grade ? ' has-grade' : ''; ?>">
					<?php if ( '' !== $sp_grade ) : ?>
						<span class="sp-cert-seal-grade"><?php echo esc_html( $sp_grade ); ?></span>
					<?php else : ?>
						<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
							stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<circle cx="12" cy="9" r="6" />
							<path d="M8.5 14.3 7 22l5-2.6L17 22l-1.5-7.7" />
						</svg>
					<?php endif; ?>
				</div>

				<div class="sp-cert-body">
					<p class="sp-cert-course">
						<strong><?php echo esc_html( $sp_card['title'] ); ?></strong>
						<?php if ( $sp_card['code'] ) : ?>
							<code class="sp-cert-code"><?php echo esc_html( $sp_card['code'] ); ?></code>
						<?php endif; ?>
					</p>
					<div class="sp-cert-meta">
						<!-- WE DONT NEED TO SHOW SERIAL -->
						<!-- <span class="sp-cert-serial"><?php echo esc_html( $sp_cert->serial ); ?></span> -->
						<?php if ( $sp_issued_on ) : ?>
							<span class="sp-cert-date">
								<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor"
									stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
									<rect x="3" y="4" width="18" height="17" rx="2" />
									<path d="M16 2v4M8 2v4M3 10h18" />
								</svg>
								Issued <?php echo esc_html( $sp_issued_on ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $sp_void ) : ?>
							<span class="sp-cert-status"><?php echo esc_html( ucwords( (string) $sp_cert->status ) ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<div class="sp-cert-actions">
					<?php if ( is_wp_error( $sp_open_url ) ) : ?>
						<span class="sp-muted sp-cert-unavailable"><?php echo esc_html( $sp_open_url->get_error_message() ); ?></span>
					<?php else : ?>
						<a class="sp-btn sp-btn-primary" target="_blank" rel="noopener"
							href="<?php echo esc_url( $sp_open_url ); ?>">Open and print</a>
						<span class="sp-cert-hint">PDF &middot; opens in a new tab</span>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
