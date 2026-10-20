<?php
/**
 * Portal settings screen.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen.
 */
function sp_admin_page_sp_settings() {
	$settings = sp_settings();

	// Grades the Certificate Manager will accept, when it is available.
	$available = array();

	if ( function_exists( 'cert_mgr_grade_options' ) ) {
		foreach ( cert_mgr_grade_options() as $sp_option ) {
			$available[] = trim( (string) $sp_option );
		}
	}
	?>
	<div class="sp-settings-ui">
		<header class="sp-settings-page-header">
			<div class="sp-settings-page-heading">
				<span class="sp-settings-eyebrow">STUDENT PORTAL <span aria-hidden="true">/</span> CONFIGURATION</span>
				<h1>Settings</h1>
				<p>Portal-wide rules for grading, attendance, log retention and automatic certificates. Changes apply across every class, teacher and student.</p>
			</div>
			<div class="sp-settings-header-note">
				<span class="sp-settings-header-mark" aria-hidden="true">⚙</span>
				<span>
					<strong>Portal configuration</strong>
					<small>Grading, attendance &amp; certificates</small>
				</span>
			</div>
		</header>

		<section class="sp-settings-stats" aria-label="Current settings at a glance">
			<article class="sp-settings-stat-card">
				<span class="sp-settings-stat-icon sp-settings-stat-icon-pass" aria-hidden="true">✓</span>
				<div>
					<span class="sp-settings-stat-label">Pass mark</span>
					<strong><?php echo esc_html( rtrim( rtrim( (string) $settings['pass_mark'], '0' ), '.' ) ); ?>%</strong>
					<small>Results below fail</small>
				</div>
			</article>
			<article class="sp-settings-stat-card">
				<span class="sp-settings-stat-icon sp-settings-stat-icon-edit" aria-hidden="true">◷</span>
				<div>
					<span class="sp-settings-stat-label">Edit window</span>
					<strong><?php echo esc_html( number_format_i18n( (int) $settings['attendance_edit_days'] ) ); ?>d</strong>
					<small>Teacher reopen window</small>
				</div>
			</article>
			<article class="sp-settings-stat-card">
				<span class="sp-settings-stat-icon sp-settings-stat-icon-logs" aria-hidden="true">▤</span>
				<div>
					<span class="sp-settings-stat-label">Log retention</span>
					<strong><?php echo (int) $settings['log_retention_days'] ? esc_html( number_format_i18n( (int) $settings['log_retention_days'] ) ) . 'd' : '∞'; ?></strong>
					<small>Email &amp; activity logs</small>
				</div>
			</article>
			<article class="sp-settings-stat-card">
				<span class="sp-settings-stat-icon sp-settings-stat-icon-upload" aria-hidden="true">↑</span>
				<div>
					<span class="sp-settings-stat-label">Upload limit</span>
					<strong><?php echo esc_html( number_format_i18n( (int) $settings['max_upload_mb'] ) ); ?>MB</strong>
					<small>Course materials</small>
				</div>
			</article>
		</section>

	<?php if ( ! $available ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong>Certificate Manager is not active.</strong> Grades are validated against A+/A/B/C
				instead of the plugin's own list.
			</p>
		</div>
	<?php endif; ?>

	<div class="sp-settings-panel">
	<form method="post">
		<?php settings_fields( 'sp_settings_group' ); ?>
		<?php sp_admin_nonce_field( 'save_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sp_pass_mark">Pass mark (%)</label></th>
				<td>
					<input type="number" id="sp_pass_mark" name="sp_settings[pass_mark]"
						min="0" max="100" step="0.01"
						value="<?php echo esc_attr( $settings['pass_mark'] ); ?>" class="small-text" />
					<p class="description">Below this percentage a result is marked as a fail and no grade is written to the certificate record.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sp_edit_days">Attendance edit window (days)</label></th>
				<td>
					<input type="number" id="sp_edit_days" name="sp_settings[attendance_edit_days]"
						min="0" max="365" step="1"
						value="<?php echo esc_attr( $settings['attendance_edit_days'] ); ?>" class="small-text" />
					<p class="description">
						How long a teacher can reopen a submitted register. After this, only an administrator can.
						Set to 0 to lock immediately.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sp_log_days">Keep logs for (days)</label></th>
				<td>
					<input type="number" id="sp_log_days" name="sp_settings[log_retention_days]"
						min="0" max="3650" step="1"
						value="<?php echo esc_attr( (int) $settings['log_retention_days'] ); ?>" class="small-text" />
					<p class="description">
						Old rows in the email log, grade-sync log and activity log are deleted
						once a day. Set to 0 to keep them forever. Attendance is
						<strong>never</strong> deleted, whatever this is set to.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label>Off days</label></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">Off days</legend>
						<p class="description" style="margin-top:0;">
							No class runs on these days, so a teacher cannot open or mark an attendance register for them.
						</p>
						<?php
						$sp_off_selected = sp_off_days();
						$sp_off_names    = sp_weekday_names();
						?>
						<?php foreach ( $sp_off_names as $sp_day_num => $sp_day_name ) : ?>
							<label style="display:inline-block;min-width:110px;margin:2px 0;">
								<input type="checkbox" name="sp_settings[off_days][]"
									value="<?php echo esc_attr( $sp_day_num ); ?>"
									<?php checked( in_array( (int) $sp_day_num, $sp_off_selected, true ) ); ?> />
								<?php echo esc_html( $sp_day_name ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sp_auto_absent">Auto-absent after (days)</label></th>
				<td>
					<input type="number" id="sp_auto_absent" name="sp_settings[auto_absent_days]"
						min="0" max="90" step="1"
						value="<?php echo esc_attr( (int) $settings['auto_absent_days'] ); ?>" class="small-text" />
					<p class="description">
						Daily run that tidies up old registers: for a scheduled class day this far in the
						past it creates the register, marks any enrolled student with no record as absent
						(kept distinct in the audit trail as <code>auto_absent</code>) and submits it.
						Skips off days, calendar holidays and tests, and dates where every assigned
						teacher is absent or on leave. Set to 0 to disable.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sp_max_upload">Maximum upload size (MB)</label></th>
				<td>
					<input type="number" id="sp_max_upload" name="sp_settings[max_upload_mb]"
						min="1" max="200" step="1"
						value="<?php echo esc_attr( $settings['max_upload_mb'] ); ?>" class="small-text" />
					<p class="description">
						Applies to course material only. Also bounded by the server's upload_max_filesize.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sp_institute_phone">Institute contact</label></th>
				<td>
					<input type="text" id="sp_institute_phone" name="sp_settings[institute_phone]"
						value="<?php echo esc_attr( $settings['institute_phone'] ); ?>" class="regular-text" placeholder="Phone number" maxlength="10" />
					<br />
					<input type="email" id="sp_institute_email" name="sp_settings[institute_email]"
						value="<?php echo esc_attr( $settings['institute_email'] ); ?>" class="regular-text" placeholder="Email address" />
					<p class="description">
						Shown in the student portal's sidebar so students can reach the office.
						Leave blank to hide the card; the email falls back to the site admin address.
					</p>
				</td>
			</tr>
		</table>

		<h2>Grade scale</h2>
		<p class="description">
			The highest matching threshold wins. Only grades the Certificate Manager recognizes are saved;
			<?php echo $available ? 'available: <code>' . esc_html( implode( '</code>, <code>', $available ) ) . '</code>' : 'the fallback list A+, A, B, C, D is used'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped branches. ?>.
		</p>

		<div class="sp-table-wrap">
		<table class="widefat striped" id="sp-grade-scale">
			<thead><tr><th>Minimum %</th><th>Grade</th></tr></thead>
			<tbody>
				<?php foreach ( $settings['grade_scale'] as $sp_row ) : ?>
					<tr>
						<td><input type="number" name="sp_settings[grade_min][]" min="0" max="100" step="0.01"
							value="<?php echo esc_attr( $sp_row['min'] ); ?>" class="small-text" /></td>
						<td><input type="text" name="sp_settings[grade_grade][]" list="sp-grade-options"
							value="<?php echo esc_attr( $sp_row['grade'] ); ?>" class="small-text" /></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>

		<?php if ( $available ) : ?>
			<datalist id="sp-grade-options">
				<?php foreach ( $available as $sp_option ) : ?>
					<option value="<?php echo esc_attr( $sp_option ); ?>"></option>
				<?php endforeach; ?>
			</datalist>
		<?php endif; ?>

		<p>
			<button type="button" class="button" id="sp-add-grade-row">Add another grade</button>
		</p>

		<h2>Notifications</h2>
		<fieldset>
			<legend class="screen-reader-text">Notifications</legend>
			<?php
			$toggles = array(
				'notify_on_result'     => 'Email a student when their result is published',
				'notify_on_material'   => 'Email enrolled students when new material is published',
				'notify_on_assignment' => 'Email a teacher when they are assigned to a class',
				'notify_on_enrollment' => 'Email a student when they are enrolled',
			);
			?>
			<?php foreach ( $toggles as $sp_key => $sp_label ) : ?>
				<label style="display:block;margin-bottom:6px;">
					<input type="checkbox" name="sp_settings[<?php echo esc_attr( $sp_key ); ?>]" value="1"
						<?php checked( ! empty( $settings[ $sp_key ] ) ); ?> />
					<?php echo esc_html( $sp_label ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<h2>Certificates</h2>
		<p class="description">
			These control what happens automatically when a teacher finalizes a passing result.
			Certificates are only produced through the Certificate Manager plugin — this
			plugin never writes to its tables unless you enable this.
		</p>

		<fieldset>
			<legend class="screen-reader-text">Automatic certificates</legend>

			<label style="display:block;margin-bottom:6px;">
				<input type="checkbox" name="sp_settings[auto_certificate]" value="1"
					<?php checked( ! empty( $settings['auto_certificate'] ) ); ?> />
				<strong>Generate a certificate automatically when a passing result is finalized</strong>
			</label>

			<p class="description" style="margin-left:24px;">
				Off by default. When on, the final grade is written to the student's
				certificate record and the Certificate Manager generates the PDF, QR code and
				verification link immediately. Only a passing result with enough class
				attendance (75% by default) is certificated; everyone else is skipped silently.
			</p>

			<p style="margin-left:24px;">
				<label for="sp_auto_min_grade">Only for grades of</label>
				<select id="sp_auto_min_grade" name="sp_settings[auto_certificate_min_grade]">
					<option value="" <?php selected( '' === $settings['auto_certificate_min_grade'] ); ?>>
						any passing result
					</option>
					<?php foreach ( sp_normalized_grade_scale() as $sp_row ) : ?>
						<option value="<?php echo esc_attr( $sp_row['grade'] ); ?>"
							<?php selected( $settings['auto_certificate_min_grade'] === $sp_row['grade'] ); ?>>
							<?php echo esc_html( $sp_row['grade'] ); ?> and above (<?php echo esc_html( $sp_row['min'] ); ?>%+)
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<?php if ( ! sp_certificate_manager_active() ) : ?>
				<p class="description" style="margin-left:24px;color:#b32d2e;">
					Certificate Manager is not active, so issued certificates will not be pushed to the vendor.
					The portal still generates and stores them itself.
				</p>
			<?php endif; ?>
		</fieldset>

		<h2>Email delivery</h2>
		<p>
			<label for="sp_test_email">Send a test email to</label>
			<input type="email" id="sp_test_email" name="test_email" class="regular-text"
				value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
		</p>

		<?php submit_button( 'Save settings' ); ?>
	</form>

	<form method="post">
		<?php sp_admin_nonce_field( 'send_test_email' ); ?>
		<input type="hidden" name="test_email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
		<?php submit_button( 'Send test email', 'secondary' ); ?>
	</form>
	</div>

		<footer class="sp-settings-page-footer">
			<span><span class="sp-settings-footer-dot" aria-hidden="true"></span> Portal settings</span>
			<span>Changes apply site-wide — test with a sample result before relying on them.</span>
		</footer>
	</div>

	<script>
	( function () {
		var add = document.getElementById( 'sp-add-grade-row' );
		var body = document.querySelector( '#sp-grade-scale tbody' );

		if ( ! add || ! body ) {
			return;
		}

		add.addEventListener( 'click', function () {
			var row = document.createElement( 'tr' );
			row.innerHTML = '<td><input type="number" name="sp_settings[grade_min][]" min="0" max="100" step="0.01" class="small-text" /></td>'
				+ '<td><input type="text" name="sp_settings[grade_grade][]" class="small-text" /></td>';
			body.appendChild( row );
		} );
	} )();
	</script>
	<?php
}