<?php
/**
 * Outbound email.
 *
 * Every send is recorded in sp_email_log so a misconfigured mailer is visible
 * to an administrator instead of silently swallowing the message.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send an HTML email and log the outcome.
 *
 * @param string   $to      Recipient address.
 * @param string   $subject Subject line.
 * @param string   $body    HTML body (already escaped/escaped by caller).
 * @param array    $args    user_id, context.
 * @return bool True when wp_mail() accepted the message.
 */
function sp_send_mail( $to, $subject, $body, $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'user_id' => 0,
			'context' => 'generic',
		)
	);

	$to = sanitize_email( $to );

	if ( '' === $to ) {
		return false;
	}

	$subject = sanitize_text_field( $subject );

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	$from_email = apply_filters( 'portal_email_from_email', get_option( 'admin_email' ) );
	$from_name  = apply_filters( 'portal_email_from_name', get_bloginfo( 'name' ) );

	if ( $from_email && is_email( $from_email ) ) {
		$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
	}

	$sent = wp_mail( $to, $subject, $body, $headers );

	$wpdb->insert(
		sp_table( 'email_log' ),
		array(
			'recipient' => $to,
			'user_id'   => (int) $args['user_id'] > 0 ? (int) $args['user_id'] : null,
			'subject'   => $subject,
			'context'   => sanitize_key( $args['context'] ),
			'status'    => $sent ? 'sent' : 'failed',
			'error'     => $sent ? null : 'wp_mail() returned false. The site mailer is most likely not configured.',
		),
		array( '%s', '%d', '%s', '%s', '%s', '%s' )
	);

	return (bool) $sent;
}

/**
 * Wrap body content in the standard portal email shell.
 *
 * @param string $heading Heading text.
 * @param string $body    Inner HTML.
 * @return string
 */
function sp_email_shell( $heading, $body ) {
	ob_start();
	?>
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2933;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e4e7eb;">
					<tr>
						<td style="background:#1f3c88;padding:20px 28px;color:#ffffff;font-size:18px;font-weight:600;">
							<?php echo esc_html( $heading ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding:28px;font-size:15px;line-height:1.6;">
							<?php echo wp_kses_post( $body ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding:18px 28px;background:#f8fafc;color:#64748b;font-size:12px;">
							<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
	<?php
	return (string) ob_get_clean();
}

/**
 * Send the welcome email containing the generated password.
 *
 * @param int    $user_id WP user id.
 * @param string $role    Portal role slug.
 * @param string $name    Display name.
 * @return bool
 */
/**
 * Send the welcome email containing the generated password.
 *
 * The plaintext password MUST be passed in. WordPress only stores a hash, and a
 * hash is not a usable credential -- $user->user_pass is the bcrypt digest
 * (e.g. $wp$2y$10$...), so emailing it produces a password nobody can type.
 * Callers hold the real password at the moment the account is created, so we
 * send that and discard it; if it is ever lost, "Resend credentials" issues a
 * brand new one rather than trying to recover it.
 *
 * @param int    $user_id  WP user id.
 * @param string $role     Portal role slug.
 * @param string $name     Display name.
 * @param string $password The plaintext password to disclose.
 * @return bool
 */
function sp_email_credentials( $user_id, $role, $name, $password ) {
	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return false;
	}

	$which    = ( SP_ROLE_TEACHER === $role ) ? 'teacher' : 'student';
	$portal   = sp_portal_home_url( $which );
	$lostpass = wp_lostpassword_url();

	$password = (string) $password;

	ob_start();
	?>
<p>Dear <?php echo esc_html( $name ); ?>,</p>
<p>
	An account has been created for you on the <?php echo esc_html( get_bloginfo( 'name' ) ); ?> portal.
	Please sign in using the details below.
</p>
<table role="presentation" cellpadding="6" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
	<tr>
		<td style="color:#52606d;">Portal</td>
		<td style="color:#1f2933;font-weight:600;"><a href="<?php echo esc_url( $portal ); ?>"><?php echo esc_html( $portal ); ?></a></td>
	</tr>
	<tr>
		<td style="color:#52606d;">Username</td>
		<td style="color:#1f2933;font-weight:600;"><?php echo esc_html( $user->user_login ); ?></td>
	</tr>
	<tr>
		<td style="color:#52606d;">Password</td>
		<td style="color:#1f2933;font-weight:600;">
			<?php
			if ( '' !== $password ) {
				// Monospace so the exact character set is unambiguous.
				echo '<code style="background:#f1f3f5;padding:2px 6px;border-radius:4px;">'
					. esc_html( $password ) . '</code>';
			} else {
				echo '<em>Not shown here.</em>';
			}
			?>
		</td>
	</tr>
</table>
<?php if ( '' !== $password ) : ?>
	<p>
		If you would rather set your own password, sign in and use
		<a href="<?php echo esc_url( $lostpass ); ?>">this link</a> to change it.
	</p>
<?php else : ?>
	<p>
		Please use the
		<a href="<?php echo esc_url( $lostpass ); ?>">set a new password</a>
		link to choose your own before signing in.
	</p>
<?php endif; ?>
<p>Please do not forward this email.</p>
	<?php
	$body = (string) ob_get_clean();

	$subject = sprintf( 'Your %s portal login', ( 'teacher' === $which ) ? 'teacher' : 'student' );

	return sp_send_mail(
		$user->user_email,
		$subject,
		sp_email_shell( 'Your portal account is ready', $body ),
		array(
			'user_id' => $user_id,
			'context' => 'credentials',
		)
	);
}

/**
 * Notify a student that new course material was published.
 *
 * @param int    $profile_id Student profile id.
 * @param object $material   sp_materials row.
 * @return bool
 */
function sp_email_material_notice( $profile_id, $material ) {
	if ( ! sp_setting( 'notify_on_material' ) ) {
		return true;
	}

	$profile = sp_get_profile_by_id( $profile_id );

	if ( ! $profile ) {
		return false;
	}

	$link = sp_portal_url( 'materials' );

	ob_start();
	?>
<p>Dear <?php echo esc_html( $profile->full_name ); ?>,</p>
<p>New study material has been published for one of your courses:</p>
<p style="margin:16px 0;"><strong><?php echo esc_html( $material->title ); ?></strong></p>
<p><a href="<?php echo esc_url( $link ); ?>">Open your materials</a></p>
	<?php
	$body = (string) ob_get_clean();

	return sp_send_mail(
		$profile->email,
		'New course material available',
		sp_email_shell( 'New course material', $body ),
		array(
			'user_id' => (int) $profile->user_id,
			'context' => 'material',
		)
	);
}

/**
 * Notify a student that a result has been finalised.
 *
 * @param object $profile    Student profile row.
 * @param object $enrollment Enrollment row (with class title).
 * @param object $result     sp_results row.
 * @return bool
 */
function sp_email_result_notice( $profile, $enrollment, $result ) {
	if ( ! sp_setting( 'notify_on_result' ) ) {
		return true;
	}

	ob_start();
	?>
<p>Dear <?php echo esc_html( $profile->full_name ); ?>,</p>
<p>Your result has been published for <strong><?php echo esc_html( $enrollment->class_title ); ?></strong>.</p>
<table role="presentation" cellpadding="6" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">
	<tr>
		<td style="color:#52606d;">Percentage</td>
		<td style="font-weight:600;"><?php echo esc_html( sp_format_number( $result->percentage ) ); ?>%</td>
	</tr>
	<tr>
		<td style="color:#52606d;">Grade</td>
		<td style="font-weight:600;"><?php echo esc_html( $result->grade ? $result->grade : '—' ); ?></td>
	</tr>
	<tr>
		<td style="color:#52606d;">Result</td>
		<td style="font-weight:600;"><?php echo esc_html( sp_result_label( $result->result ) ); ?></td>
	</tr>
</table>
<p><a href="<?php echo esc_url( sp_portal_url( 'results' ) ); ?>">View all results</a></p>
	<?php
	$body = (string) ob_get_clean();

	return sp_send_mail(
		$profile->email,
		'Your result has been published',
		sp_email_shell( 'Result published', $body ),
		array(
			'user_id' => (int) $profile->user_id,
			'context' => 'result',
		)
	);
}

/**
 * Notify a teacher they have been assigned to a class.
 *
 * @param object $teacher  sp_teachers row.
 * @param object $class sp_classes row.
 * @return bool
 */
function sp_email_assignment_notice( $teacher, $class ) {
	if ( ! sp_setting( 'notify_on_assignment' ) ) {
		return true;
	}

	ob_start();
	?>
<p>Dear <?php echo esc_html( $teacher->full_name ); ?>,</p>
<p>You have been assigned to teach:</p>
<p style="margin:16px 0;"><strong><?php echo esc_html( $class->title ); ?></strong></p>
<p><a href="<?php echo esc_url( sp_teacher_url( 'classes/' . (int) $class->id ) ); ?>">Open the class</a></p>
	<?php
	$body = (string) ob_get_clean();

	return sp_send_mail(
		$teacher->email,
		'New teaching assignment',
		sp_email_shell( 'New teaching assignment', $body ),
		array(
			'user_id' => (int) $teacher->user_id,
			'context' => 'assignment',
		)
	);
}

/**
 * Send an announcement email to all targeted recipients.
 *
 * @param int   $announcement_id Announcement row id.
 * @param string $audience    'all_students', 'one_student' or 'teachers'.
 * @param int   $profile_id   Student profile id (for one_student audience).
 * @param string $title    Announcement title.
 * @param string $body    Announcement body.
 * @return int Number of emails sent.
 */
function sp_send_announcement_email( $announcement_id, $audience, $profile_id, $title, $body ) {
	$student_link = sp_portal_url( 'notifications' );
	$teacher_link = sp_teacher_url( 'notifications' );

	$recipients = array();

	if ( 'teachers' === $audience ) {
		$teachers = sp_get_teachers();
		foreach ( $teachers as $teacher ) {
			if ( ! empty( $teacher->email ) ) {
				$recipients[] = array(
					'to'      => $teacher->email,
					'user_id' => (int) $teacher->user_id,
					'name'    => $teacher->full_name,
					'link'    => $teacher_link,
				);
			}
		}
	} elseif ( 'one_student' === $audience ) {
		$profile = sp_get_profile_by_id( $profile_id );
		if ( $profile && ! empty( $profile->email ) ) {
			$recipients[] = array(
				'to'      => $profile->email,
				'user_id' => (int) $profile->user_id,
				'name'    => $profile->full_name,
				'link'    => $student_link,
			);
		}
	} else {
		$students = sp_get_profiles( array( 'per_page' => 1000 ) );
		foreach ( $students as $profile ) {
			if ( ! empty( $profile->email ) ) {
				$recipients[] = array(
					'to'      => $profile->email,
					'user_id' => (int) $profile->user_id,
					'name'    => $profile->full_name,
					'link'    => $student_link,
				);
			}
		}
	}

	$sent = 0;

	foreach ( $recipients as $recipient ) {
		ob_start();
		?>
		<p>Dear <?php echo esc_html( $recipient['name'] ); ?>,</p>
		<?php if ( '' !== trim( (string) $title ) ) : ?>
			<p><strong><?php echo esc_html( $title ); ?></strong></p>
		<?php endif; ?>
		<p><?php echo wp_kses_post( wpautop( $body ) ); ?></p>
		<p>
			<a href="<?php echo esc_url( $recipient['link'] ); ?>">View all announcements</a>
		</p>
		<?php
		$email_body = (string) ob_get_clean();

		$subject = '' !== trim( (string) $title ) ? sanitize_text_field( $title ) : 'New announcement';

		if ( sp_send_mail(
			$recipient['to'],
			$subject,
			sp_email_shell( 'New announcement', $email_body ),
			array(
				'user_id' => $recipient['user_id'],
				'context' => 'announcement',
			)
		) ) {
			++$sent;
		}
	}

	return $sent;
}

/**
 * Format a number for display without trailing noise.
 *
 * @param mixed $value Numeric value.
 * @return string
 */
function sp_format_number( $value ) {
	if ( null === $value || '' === $value ) {
		return '—';
	}

	return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' );
}