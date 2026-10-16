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
?>
<div class="sp-page-head">
	<p class="sp-page-sub">Every certificate your institute has issued to you, ready to open and print.</p>
</div>

<?php if ( ! $sp_certs ) : ?>
	<div class="sp-empty">
		No certificate has been issued to you yet. Your institute will issues
		once your marks and attendance qualify for a course .
	</div>
<?php else : ?>
	<div class="sp-cert-list">
		<?php foreach ( $sp_certs as $sp_cert ) : ?>
			<div class="sp-card sp-cert-item">
				<h2 class="sp-card-title">
					<?php echo esc_html( $sp_cert->serial ); ?>
					<?php if ( $sp_cert->grade ) : ?>
						<span class="sp-cert-grade"><?php echo esc_html( $sp_cert->grade ); ?></span>
					<?php endif; ?>
				</h2>

				<dl class="sp-kv">
					<dt>Issued on</dt>
					<dd><?php echo esc_html( $sp_cert->issued_date ? date_i18n( get_option( 'date_format' ), strtotime( $sp_cert->issued_date ) ) : '—' ); ?></dd>
				</dl>

				<?php
				$sp_open_url = sp_certificate_vendor_url( $sp_cert );

				if ( is_wp_error( $sp_open_url ) ) :
					?>
					<p class="sp-card-actions">
						<span class="sp-muted"><?php echo esc_html( $sp_open_url->get_error_message() ); ?></span>
					</p>
				<?php else : ?>
					<p class="sp-card-actions">
						<a class="sp-btn sp-btn-primary" target="_blank" rel="noopener"
							href="<?php echo esc_url( $sp_open_url ); ?>">Open and print</a>
					</p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
