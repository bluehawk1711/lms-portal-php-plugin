<?php
/**
 * Teacher portal rendering, asset loading and POST action dispatch.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the teacher portal stylesheets: the shared base plus the current
 * route's own file — one stylesheet per template/page.
 */
function sp_teacher_enqueue_assets() {
	$route = (string) get_query_var( 'sp_teacher_route' );

	if ( '' === $route ) {
		return;
	}

	wp_enqueue_style(
		'student-portal-tokens',
		SP_URL . 'assets/css/tokens.css',
		array(),
		SP_VERSION
	);

	wp_enqueue_style(
		'student-portal-teacher-base',
		SP_URL . 'assets/css/teacher-base.css',
		array( 'student-portal-tokens' ),
		SP_VERSION
	);

	if ( ! is_user_logged_in() ) {
		wp_enqueue_style(
			'student-portal-teacher-login',
			SP_URL . 'assets/css/teacher-login.css',
			array( 'student-portal-teacher-base' ),
			SP_VERSION
		);
	} else {
		$page = 'teacher-' . $route . '.css';

		if ( file_exists( SP_PATH . 'assets/css/' . $page ) ) {
			wp_enqueue_style(
				'student-portal-teacher-' . $route,
				SP_URL . 'assets/css/' . $page,
				array( 'student-portal-teacher-base' ),
				SP_VERSION
			);
		}

		// Announcement banner / bar styles, shared with the student portal.
		wp_enqueue_style(
			'sp-announcements',
			SP_URL . 'assets/css/announcements.css',
			array( 'student-portal-teacher-base' ),
			SP_VERSION
		);
	}

	if ( 'calendar' === $route ) {
		wp_enqueue_style(
			'student-portal-teacher-schedule',
			SP_URL . 'assets/css/schedule.css',
			array( 'student-portal-teacher-base' ),
			SP_VERSION
		);
	}

	if ( 'class' === $route ) {
		// Enqueue curriculum styles for the class screen.
		wp_enqueue_style(
			'student-portal-teacher-curriculum',
			SP_URL . 'assets/css/teacher-curriculum.css',
			array( 'student-portal-teacher-base' ),
			SP_VERSION
		);
	}

	// The attempt detail page reuses the question-bank components.
	if ( 'attempts' === $route ) {
		wp_enqueue_style(
			'student-portal-teacher-attempt-edit',
			SP_URL . 'assets/css/teacher-assignment-edit.css',
			array( 'student-portal-teacher-base' ),
			SP_VERSION
		);

		wp_enqueue_style(
			'student-portal-teacher-attempts',
			SP_URL . 'assets/css/teacher-attempts.css',
			array( 'student-portal-teacher-attempt-edit' ),
			SP_VERSION
		);
	}

	wp_enqueue_script(
		'student-portal-teacher',
		SP_URL . 'assets/js/teacher.js',
		array(),
		SP_VERSION,
		true
	);

	// The reusable date & time component on the routes with date/time inputs
	// (attendance date, class create form, assignment due date).
	if ( in_array( $route, array( 'class', 'classes', 'assignment-edit' ), true ) ) {
		sp_datetime_enqueue();
	}

	// Register live counter + submit confirmation on the class attendance tab.
	if ( 'class' === $route ) {
		wp_enqueue_script(
			'student-portal-attendance',
			SP_URL . 'assets/js/attendance.js',
			array(),
			SP_VERSION,
			true
		);
	}

	// Live search on the class list; other routes never need it.
	if ( 'classes' === $route ) {
		wp_enqueue_script(
			'student-portal-teacher-classes',
			SP_URL . 'assets/js/teacher-classes.js',
			array(),
			SP_VERSION,
			true
		);
	}

	// Async filters & small submits on the routes wired with data-sp-async:
	// tab switches, register opens and small saves swap the region in place.
	if ( in_array( $route, array( 'class', 'classes', 'profile' ), true ) ) {
		wp_enqueue_style(
			'student-portal-async',
			SP_URL . 'assets/css/sp-async.css',
			array(),
			SP_VERSION
		);

		wp_enqueue_script(
			'student-portal-async',
			SP_URL . 'assets/js/sp-async.js',
			array(),
			SP_VERSION,
			true
		);
	}

	// Live search on the assignment question bank and set picker; other routes never need it.
	if ( 'assignment-edit' === $route ) {
		wp_enqueue_script(
			'student-portal-teacher-assignment',
			SP_URL . 'assets/js/teacher-assignment.js',
			array(),
			SP_VERSION,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'sp_teacher_enqueue_assets', 21 );

/**
 * Enqueue assets for the teacher curriculum tab.
 */
function sp_teacher_enqueue_curriculum_assets() {
	wp_enqueue_style(
		'student-portal-teacher-curriculum',
		SP_URL . 'assets/css/teacher-curriculum.css',
		array( 'student-portal-teacher-base' ),
		SP_VERSION
	);
}

/**
 * Render a teacher route and end the request.
 *
 * Shares the standalone document shell with the student portal: the theme's
 * header, footer and admin bar never render around portal content.
 *
 * @param string      $slug    Route slug.
 * @param object|null $teacher Teacher row, or null on the login screen.
 */
function sp_render_teacher_portal( $slug, $teacher ) {
	$routes = sp_teacher_routes();

	if ( ! isset( $routes[ $slug ] ) ) {
		return;
	}

	$route = $routes[ $slug ];

	// Form submissions are handled before any output so each action can act
	// and then redirect, which keeps a browser refresh from re-posting.
	sp_teacher_handle_post();

	sp_portal_document_start();

	if ( ! is_user_logged_in() ) {
		require SP_PATH . 'templates/teacher-login.php';
	} else {
		require SP_PATH . 'templates/teacher-layout-start.php';
		require SP_PATH . 'templates/' . $route['template'];
		require SP_PATH . 'templates/teacher-layout-end.php';
	}

	sp_portal_document_end();
}

/**
 * Handle every teacher portal form submission.
 */
function sp_teacher_handle_post() {
	if ( empty( $_POST['sp_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['sp_action'] ) );

	$nonce = isset( $_POST['sp_teacher_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_teacher_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'sp_teacher_action_' . $action ) ) {
		wp_die( esc_html__( 'Your session expired. Please go back and try again.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	if ( ! sp_is_teacher() && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	$redirect = isset( $_POST['sp_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['sp_redirect'] ) ) : sp_teacher_url();

	// Only ever bounce back inside the teacher portal.
	$redirect = wp_validate_redirect( $redirect, sp_teacher_url() );

	if ( false === strpos( $redirect, '/teacher/' ) ) {
		$redirect = sp_teacher_url();
	}

	switch ( $action ) {
		case 'create_class':
			sp_teacher_action_create_class( $redirect );
			break;
		case 'update_class':
			sp_teacher_action_update_class( $redirect );
			break;
	case 'save_session':
		sp_teacher_action_save_session( $redirect );
		break;
		case 'mark_all':
			sp_teacher_action_mark_all( $redirect );
			break;
		case 'submit_session':
			sp_teacher_action_submit_session( $redirect );
			break;
		case 'reopen_session':
			sp_teacher_action_reopen_session( $redirect );
			break;
		case 'save_material':
			sp_teacher_action_save_material( $redirect );
			break;
		case 'delete_material':
			sp_teacher_action_delete_material( $redirect );
			break;
		case 'save_results':
			sp_teacher_action_save_results( $redirect );
			break;
		case 'finalise_result':
			sp_teacher_action_finalise_result( $redirect );
			break;
	case 'generate_certificate':
			sp_teacher_action_generate_certificate( $redirect );

			break;
		case 'save_certificate':
			sp_teacher_action_save_certificate( $redirect );

			break;
		case 'toggle_curriculum_item':
			sp_teacher_action_toggle_curriculum_item( $redirect );
			break;
		case 'save_assignment':
			sp_teacher_action_save_assignment( $redirect );
			break;
		case 'delete_assignment':
			sp_teacher_action_delete_assignment( $redirect );
			break;
		case 'save_question':
			sp_teacher_action_save_question( $redirect );
			break;
		case 'delete_question':
			sp_teacher_action_delete_question( $redirect );
			break;
		case 'import_questions':
			sp_teacher_action_import_questions( $redirect );
			break;
		case 'save_sets':
			sp_teacher_action_save_sets( $redirect );
			break;
		case 'publish_assignment':
			sp_teacher_action_publish_assignment( $redirect );
			break;
		case 'close_assignment':
			sp_teacher_action_close_assignment( $redirect );
			break;
		case 'allow_retake':
			sp_teacher_action_allow_retake( $redirect );
			break;
		case 'save_profile':
			sp_teacher_action_save_profile( $redirect );
			break;
		case 'dismiss_announcement':
			sp_teacher_action_dismiss_announcement( $redirect );
			break;
		default:
			wp_die( esc_html__( 'Unknown action.', 'student-portal' ), '', array( 'response' => 400 ) );
	}
}

/**
 * Update the signed-in teacher's own profile details.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_save_profile( $redirect ) {
	global $wpdb;

	$teacher = sp_get_teacher_by_user_id( get_current_user_id() );

	if ( ! $teacher ) {
		sp_teacher_redirect( $redirect, '', 'This account has no teacher profile.' );
	}

	$name  = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
	$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$bio   = isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '';

	if ( '' === $name ) {
		sp_teacher_redirect( $redirect, '', 'Enter your full name.' );
	}

	$wpdb->update(
		sp_table( 'teachers' ),
		array(
			'full_name' => $name,
			'phone'     => '' !== $phone ? $phone : null,
			'bio'       => '' !== $bio ? $bio : null,
		),
		array( 'id' => (int) $teacher->id ),
		array( '%s', '%s', '%s' ),
		array( '%d' )
	);

	// Keep the WordPress display name in step with the portal name.
	if ( $name !== (string) $teacher->full_name ) {
		wp_update_user(
			array(
				'ID'           => (int) $teacher->user_id,
				'display_name' => $name,
				'first_name'   => $name,
			)
		);
	}

	sp_teacher_redirect( $redirect, 'profile_saved' );
}

/**
 * Hide an announcement for the current teacher and return to the page.
 *
 * @param string $redirect URL to bounce back to.
 */
function sp_teacher_action_dismiss_announcement( $redirect ) {
	$announcement_id = isset( $_POST['announcement_id'] ) ? (int) $_POST['announcement_id'] : 0;

	if ( $announcement_id ) {
		sp_announcement_dismiss( $announcement_id );
	}

	sp_teacher_redirect( $redirect );
}

/**
 * Redirect back to the teacher portal carrying a notice code.
 *
 * @param string $url     Destination.
 * @param string $code    Notice code, or '' for none.
 * @param string $message Optional explicit message that replaces the code text.
 */
function sp_teacher_redirect( $url, $code = '', $message = '' ) {
	if ( '' === $message && '' !== $code ) {
		$message = sp_teacher_notice_message( $code );
	}

	if ( '' !== $message ) {
		$url = add_query_arg( 'sp_notice', rawurlencode( $message ), $url );
	}

	wp_safe_redirect( $url );
	exit;
}

/**
 * Turn a notice code into readable text.
 *
 * @param string $code Notice code.
 * @return string
 */
function sp_teacher_notice_message( $code ) {
	switch ( $code ) {
		case 'class_created':
			return 'Class created. It is waiting for admin approval — an administrator will approve it and add students.';
		case 'class_updated':
			return 'Class details saved.';
		case 'session_saved':
			return 'Attendance saved.';
		case 'session_submitted':
			return 'Attendance submitted. The register is now locked.';
		case 'session_reopened':
			return 'Register reopened for editing.';
		case 'marked_all':
			return 'Every student on the roster was marked present.';
		case 'material_saved':
			return 'Material saved.';
		case 'material_published':
			return 'Material published. Enrolled students can see it now.';
		case 'material_deleted':
			return 'Material deleted.';
		case 'result_saved':
			return 'Result saved.';
		case 'result_published':
			return 'Result published and the student has been notified.';
		case 'certificate_generated':
			return 'Certificate issued — the PDF, QR code and verification link are ready.';
		case 'curriculum_marked':
			return 'Topic marked as covered.';
		case 'curriculum_unmarked':
			return 'Topic marked as not covered.';
		case 'assignment_saved':
			return 'Assignment saved.';
		case 'assignment_deleted':
			return 'Assignment deleted.';
		case 'question_saved':
			return 'Question saved to the class bank.';
		case 'question_deleted':
			return 'Question removed from the class bank.';
		case 'sets_saved':
			return 'Question sets saved.';
		case 'set_added':
			return 'Set added. Tick the questions that belong to it, then save.';
		case 'assignment_published':
			return 'Assignment published. Students can see it now.';
		case 'assignment_closed':
			return 'Assignment closed. No new attempts can start.';
		case 'retake_granted':
			return 'Retake allowed. The student can sit the exam again.';
		case 'profile_saved':
			return 'Profile updated.';
		default:
			return '';
	}
}

/**
 * Read the notice for the current request.
 *
 * @return string
 */
function sp_teacher_notice() {
	if ( empty( $_GET['sp_notice'] ) ) {
		return '';
	}

	return sanitize_text_field( wp_unslash( $_GET['sp_notice'] ) );
}

/**
 * Read the error for the current request.
 *
 * @return string
 */
function sp_teacher_error() {
	if ( empty( $_GET['sp_error'] ) ) {
		return '';
	}

	return sanitize_text_field( wp_unslash( $_GET['sp_error'] ) );
}

/**
 * Create a class owned by the signed-in teacher.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_create_class( $redirect ) {
	// Validate the daily timing before anything is written, so a half-filled
	// pair cannot leave a class behind without its schedule.
	$timings = sp_normalize_timing_rows( sp_timing_rows_from_request() );

	if ( is_wp_error( $timings ) ) {
		sp_teacher_redirect( $redirect, '', $timings->get_error_message() );
	}

	$class_id = sp_save_class(
		array(
			'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'code'        => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '',
			'course_id'   => isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0,
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'start_date'  => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'end_date'    => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
			// A teacher-created class goes live only once an admin approves it.
			'status'      => 'pending',
		)
	);

	if ( is_wp_error( $class_id ) ) {
		sp_teacher_redirect( $redirect, '', $class_id->get_error_message() );
	}

	if ( $timings ) {
		$saved = sp_save_class_timings( $class_id, $timings );

		if ( is_wp_error( $saved ) ) {
			sp_teacher_redirect(
				$redirect,
				'',
				'Class created, but the daily timing could not be saved: ' . $saved->get_error_message()
			);
		}
	}

	$teacher = sp_get_teacher_by_user_id( get_current_user_id() );

	if ( $teacher ) {
		sp_assign_teacher( $teacher->id, (int) $class_id, true, false );
	}

	sp_teacher_redirect(
		sp_teacher_url( 'classes/' . (int) $class_id ),
		'class_created'
	);
}

/**
 * Update a class's details and timetable from the class page.
 *
 * The approval status is carried over untouched — only an administrator
 * approves, archives or resets a class.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_update_class( $redirect ) {
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

	$teacher = sp_require_assigned_teacher( $class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	// Validate the timing before anything is written, so a half-filled pair
	// cannot leave the class without its schedule.
	$timings = sp_normalize_timing_rows( sp_timing_rows_from_request() );

	if ( is_wp_error( $timings ) ) {
		sp_teacher_redirect( $redirect, '', $timings->get_error_message() );
	}

	$existing = sp_get_class( $class_id );

	$saved = sp_save_class(
		array(
			'id'          => $class_id,
			'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'code'        => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '',
			'course_id'   => isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0,
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'start_date'  => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'end_date'    => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
			'status'      => $existing ? (string) $existing->status : 'active',
		)
	);

	if ( is_wp_error( $saved ) ) {
		sp_teacher_redirect( $redirect, '', $saved->get_error_message() );
	}

	// Blank timings mean "leave the timetable alone"; anything else replaces it.
	if ( $timings ) {
		$updated = sp_save_class_timings( $class_id, $timings );

		if ( is_wp_error( $updated ) ) {
			sp_teacher_redirect(
				$redirect,
				'',
				'Class saved, but the daily timing could not be saved: ' . $updated->get_error_message()
			);
		}
	}

	sp_teacher_redirect( $redirect, 'class_updated' );
}

/**
 * Save a register, creating the session if needed.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_save_session( $redirect ) {
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$date        = isset( $_POST['session_date'] ) ? sanitize_text_field( wp_unslash( $_POST['session_date'] ) ) : '';
	$topic       = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';

	$teacher = sp_require_assigned_teacher( $class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$session_id = sp_create_session( $class_id, $date, $topic );

	if ( is_wp_error( $session_id ) ) {
		sp_teacher_redirect( $redirect, '', $session_id->get_error_message() );
	}

	$marks = array();

	if ( isset( $_POST['marks'] ) && is_array( $_POST['marks'] ) ) {
		foreach ( wp_unslash( $_POST['marks'] ) as $profile_id => $status ) {
			$marks[ (int) $profile_id ] = sanitize_key( $status );
		}
	}

	$saved = sp_save_attendance_records( $session_id, $marks );

	if ( is_wp_error( $saved ) ) {
		sp_teacher_redirect( $redirect, '', $saved->get_error_message() );
	}

	$submit = ! empty( $_POST['submit_after'] );

	if ( $submit ) {
		$submitted = sp_submit_session( $session_id );

		if ( is_wp_error( $submitted ) ) {
			sp_teacher_redirect( $redirect, '', $submitted->get_error_message() );
		}

		sp_teacher_redirect( $redirect, 'session_submitted' );
	}

	sp_teacher_redirect( $redirect, 'session_saved' );
}

/**
 * Mark the whole roster present.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_mark_all( $redirect ) {
	$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;
	$session    = sp_get_session( $session_id );

	if ( ! $session ) {
		sp_teacher_redirect( $redirect, '', 'That session no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $session->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$marked = sp_mark_all( $session_id, 'present' );

	if ( is_wp_error( $marked ) ) {
		sp_teacher_redirect( $redirect, '', $marked->get_error_message() );
	}

	sp_teacher_redirect( $redirect, 'marked_all' );
}

/**
 * Submit (lock) a register.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_submit_session( $redirect ) {
	$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;
	$session    = sp_get_session( $session_id );

	if ( ! $session ) {
		sp_teacher_redirect( $redirect, '', 'That session no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $session->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$submitted = sp_submit_session( $session_id );

	if ( is_wp_error( $submitted ) ) {
		sp_teacher_redirect( $redirect, '', $submitted->get_error_message() );
	}

	sp_teacher_redirect( $redirect, 'session_submitted' );
}

/**
 * Reopen a submitted register.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_reopen_session( $redirect ) {
	$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;
	$session    = sp_get_session( $session_id );

	if ( ! $session ) {
		sp_teacher_redirect( $redirect, '', 'That session no longer exists.' );
	}

	// Reopening a submitted register is the administrator's audited route;
	// a teacher with a crafted POST must not bypass the lock.
	if ( ! sp_can_reopen_session( $session ) ) {
		sp_teacher_redirect( $redirect, '', 'Only an administrator can reopen a submitted register.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $session->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

	$reopened = sp_reopen_session( $session_id, $reason );

	if ( is_wp_error( $reopened ) ) {
		sp_teacher_redirect( $redirect, '', $reopened->get_error_message() );
	}

	sp_teacher_redirect( $redirect, 'session_reopened' );
}

/**
 * Create or update a material, uploading the file when one is supplied.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_save_material( $redirect ) {
	$material_id  = isset( $_POST['material_id'] ) ? (int) $_POST['material_id'] : 0;
	$class_id  = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$kind         = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'note';
	$title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$status       = isset( $_POST['status'] ) && 'published' === sanitize_key( wp_unslash( $_POST['status'] ) ) ? 'published' : 'draft';
	$existing     = $material_id ? sp_get_material( $material_id ) : null;

	if ( $existing ) {
		$class_id = (int) $existing->class_id;
	}

	$teacher = sp_require_assigned_teacher( $class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$args = array(
		'id'          => $material_id,
		'class_id' => $class_id,
		'title'       => $title,
		'kind'        => $kind,
		'content'     => isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- sanitised by wp_kses_post in sp_save_material().
		'status'      => $status,
	);

	if ( 'file' === $kind ) {
		$has_new_file = ! empty( $_FILES['material_file']['name'] );

		if ( $has_new_file ) {
			$stored = sp_store_uploaded_file(
				$_FILES['material_file'],
				$title
			);

			if ( is_wp_error( $stored ) ) {
				sp_teacher_redirect( $redirect, '', $stored->get_error_message() );
			}

			$args['file_path']         = $stored['file_path'];
			$args['original_filename'] = $stored['original_filename'];
			$args['mime_type']         = $stored['mime_type'];
			$args['file_size']         = $stored['size'];
		} elseif ( ! $existing || 'file' !== $existing->kind ) {
			sp_teacher_redirect( $redirect, '', 'Attach a file, or switch the material to a written note.' );
		}
	}

	$saved = sp_save_material( $args );

	if ( is_wp_error( $saved ) ) {
		sp_teacher_redirect( $redirect, '', $saved->get_error_message() );
	}

	/*
	 * Replacing the file on an existing material discards the old one. Done after
	 * the save so a failed save never destroys the file still in use.
	 */
	if ( $existing && 'file' === $kind && ! empty( $args['file_path'] ) && $existing->file_path !== $args['file_path'] ) {
		$old_path = sp_material_file_path( $existing );

		if ( ! is_wp_error( $old_path ) ) {
			@unlink( $old_path ); // phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged
		}
	}

	sp_teacher_redirect(
		add_query_arg( 'tab', 'materials', sp_teacher_url( 'classes/' . $class_id ) ),
		'published' === $status ? 'material_published' : 'material_saved'
	);
}

/**
 * Delete a material the teacher authored.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_delete_material( $redirect ) {
	$material_id = isset( $_POST['material_id'] ) ? (int) $_POST['material_id'] : 0;
	$material    = sp_get_material( $material_id );

	if ( ! $material ) {
		sp_teacher_redirect( $redirect, '', 'That material no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $material->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$is_author = ( (int) $material->author_user_id === get_current_user_id() );

	if ( ! $is_author && ! current_user_can( 'manage_certificates' ) ) {
		sp_teacher_redirect( $redirect, '', 'You can only delete material you created.' );
	}

	$deleted = sp_delete_material( $material_id );

	if ( is_wp_error( $deleted ) ) {
		sp_teacher_redirect( $redirect, '', $deleted->get_error_message() );
	}

	sp_teacher_redirect( $redirect, 'material_deleted' );
}

/**
 * Save a batch of results entered on the class results tab.
 *
 * Each row is validated independently: one student's bad marks must not discard
 * the rest of the class's work.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_save_results( $redirect ) {
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

	$teacher = sp_require_assigned_teacher( $class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	if ( empty( $_POST['sp_result'] ) || ! is_array( $_POST['sp_result'] ) ) {
		sp_teacher_redirect( $redirect, '', 'Nothing was submitted.' );
	}

	$saved     = 0;
	$published = 0;
	$problems  = array();

	foreach ( wp_unslash( $_POST['sp_result'] ) as $enrollment_id => $marks ) {
		$enrollment_id = (int) $enrollment_id;

		if ( $enrollment_id <= 0 || ! is_array( $marks ) ) {
			continue;
		}

		$enrollment = sp_get_enrollment_with_class( $enrollment_id );

		// Ignore rows for students who are not in this class.
		if ( ! $enrollment || (int) $enrollment->class_id !== $class_id ) {
			continue;
		}

		$finalised = ! empty( $marks['finalised'] );

		$result = sp_save_result(
			$enrollment_id,
			array(
				'theory_max'         => isset( $marks['theory_max'] ) ? (float) $marks['theory_max'] : 0,
				'theory_obtained'    => isset( $marks['theory_obtained'] ) ? (float) $marks['theory_obtained'] : 0,
				'practical_max'      => isset( $marks['practical_max'] ) ? (float) $marks['practical_max'] : 0,
				'practical_obtained' => isset( $marks['practical_obtained'] ) ? (float) $marks['practical_obtained'] : 0,
			),
			array( 'finalised' => $finalised )
		);

		if ( is_wp_error( $result ) ) {
			$problems[] = sprintf( '%s: %s', $enrollment->full_name, $result->get_error_message() );
			continue;
		}

		++$saved;

		if ( $finalised ) {
			++$published;
		}
	}

	if ( ! $saved ) {
		sp_teacher_redirect( $redirect, '', $problems ? implode( ' ', $problems ) : 'Nothing was saved.' );
	}

	if ( $problems ) {
		sp_teacher_redirect( add_query_arg( 'tab', 'results', $redirect ), '', sprintf( '%d saved, but %s', $saved, implode( '; ', $problems ) ) );
	}

	sp_teacher_redirect(
		add_query_arg( 'tab', 'results', $redirect ),
		$published ? 'result_published' : 'result_saved'
	);
}

/**
 * Toggle the published flag on a single result.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_finalise_result( $redirect ) {
	$result_id = isset( $_POST['result_id'] ) ? (int) $_POST['result_id'] : 0;
	$enrollment_id = isset( $_POST['enrollment_id'] ) ? (int) $_POST['enrollment_id'] : 0;

	$result = sp_get_result_for_enrollment( $enrollment_id );

	if ( ! $result || (int) $result->id !== $result_id ) {
		sp_teacher_redirect( $redirect, '', 'That result no longer exists.' );
	}

	$enrollment = sp_get_enrollment_with_class( $enrollment_id );

	if ( ! $enrollment ) {
		sp_teacher_redirect( $redirect, '', 'That student enrollment no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $enrollment->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$will_publish = empty( $result->finalised );

	$updated = sp_set_result_finalised( $result_id, $will_publish );

	if ( is_wp_error( $updated ) ) {
		sp_teacher_redirect( $redirect, '', $updated->get_error_message() );
	}

	sp_teacher_redirect(
		add_query_arg( 'tab', 'results', $redirect ),
		$will_publish ? 'result_published' : 'result_saved'
	);
}

/**
 * Issue a certificate for one student in the teacher's class.
 *
 * Eligibility lives in sp_certificate_state_for_enrollment(): a passing result,
 * at least the required class attendance, and an enrolment ID. The state
 * helper's reason is surfaced to the teacher when it cannot proceed.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_generate_certificate( $redirect ) {
	$enrollment_id = isset( $_POST['enrollment_id'] ) ? (int) $_POST['enrollment_id'] : 0;

	$enrollment = sp_get_enrollment_with_class( $enrollment_id );

	if ( ! $enrollment ) {
		sp_teacher_redirect( $redirect, '', 'That student enrollment no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $enrollment->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$result = sp_issue_certificate( $enrollment_id );

	if ( is_wp_error( $result ) ) {
		sp_teacher_redirect(
			add_query_arg( array( 'tab' => 'certificates', 'cert_form' => $enrollment_id ), $redirect ),
			'',
			$result->get_error_message()
		);
	}

	sp_teacher_redirect( add_query_arg( 'tab', 'certificates', $redirect ), 'certificate_generated' );
}

/**
 * Save a certificate from the dynamic form on the teacher's class page.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_save_certificate( $redirect ) {
	$enrollment_id = isset( $_POST['enrollment_id'] ) ? (int) $_POST['enrollment_id'] : 0;
	$values        = isset( $_POST['cert_values'] ) && is_array( $_POST['cert_values'] )
		? wp_unslash( $_POST['cert_values'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- sanitised per field below.
		: array();

	$enrollment = sp_get_enrollment_with_class( $enrollment_id );

	if ( ! $enrollment ) {
		sp_teacher_redirect( $redirect, '', 'That student enrollment no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $enrollment->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$sanitised = array();

	foreach ( $values as $sp_key => $sp_value ) {
		$sp_key = sanitize_key( $sp_key );

		$sanitised[ $sp_key ] = is_array( $sp_value )
			? implode( ' ', array_map( 'sanitize_textarea_field', $sp_value ) )
			: sanitize_textarea_field( $sp_value );
	}

	$certificate = sp_issue_certificate( $enrollment_id, $sanitised );

	if ( is_wp_error( $certificate ) ) {
		sp_teacher_redirect(
			add_query_arg( array( 'tab' => 'certificates', 'cert_form' => $enrollment_id ), $redirect ),
			'',
			$certificate->get_error_message()
		);
	}

	sp_teacher_redirect(
		add_query_arg( 'tab', 'certificates', $redirect ),
		'certificate_generated'
	);
}

/**
 * Render the dynamic certificate form on the teacher's class page.
 *
 * Same field list and auto-fill behaviour as the admin Results screen.
 *
 * @param int $enrollment_id Enrollment id.
 * @param int $class_id      Class id.
 */
function sp_teacher_render_certificate_form( $enrollment_id, $class_id ) {
	global $wpdb;

	$enrollment = $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . sp_table( 'enrollments' ) . ' WHERE id = %d', (int) $enrollment_id )
	);

	if ( ! $enrollment ) {
		return;
	}

	$state = sp_certificate_state_for_enrollment( (int) $enrollment_id );

	if ( ! $state['can_generate'] && ! $state['generated'] ) {
		?>
		<div class="sp-notice sp-notice-warn"><?php echo esc_html( $state['reason'] ); ?></div>
		<?php
		return;
	}

	$profile  = sp_get_profile_by_id( (int) $enrollment->profile_id );
	$result   = sp_get_result_for_enrollment( (int) $enrollment_id );
	$auto     = sp_certificate_auto_values( $enrollment, $result );
	$fields   = sp_certificate_fields();
	$existing = sp_get_certificate_for_enrollment( (int) $enrollment_id );
	$saved    = sp_certificate_values( $existing );
	?>
	<div class="sp-card sp-cert-form">
		<h2 class="sp-card-title">
			<?php echo $existing ? 'Certificate ' . esc_html( $existing->serial ) : 'Issue certificate'; ?>
			<span class="sp-muted">&mdash; <?php echo esc_html( $profile ? $profile->full_name : '' ); ?></span>
		</h2>

		<?php if ( ! sp_certificate_manager_active() ) : ?>
			<div class="sp-notice sp-notice-warn">
				Certificate Manager is inactive. Certificates cannot be issued or opened until it is
				activated again.
			</div>
		<?php endif; ?>

		<?php if ( $existing ) : ?>
			<p class="sp-muted">
				All fields stay editable — change what you need, then reissue to save.
			</p>
		<?php endif; ?>

		<form class="sp-form" method="post">
			<?php sp_teacher_nonce_field( 'save_certificate' ); ?>
			<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( (int) $enrollment_id ); ?>" />

			<?php foreach ( $fields as $sp_field ) : ?>
				<?php
				$sp_key    = isset( $sp_field['key'] ) ? (string) $sp_field['key'] : '';
				$sp_type   = isset( $sp_field['type'] ) ? (string) $sp_field['type'] : 'text';
				$sp_source = isset( $sp_field['source'] ) ? (string) $sp_field['source'] : '';

				$sp_value = '';

				if ( $existing && array_key_exists( $sp_key, $saved ) && '' !== trim( (string) $saved[ $sp_key ] ) ) {
					$sp_value = (string) $saved[ $sp_key ];
				} elseif ( '' !== $sp_source && isset( $auto[ $sp_source ] ) ) {
					$sp_value = (string) $auto[ $sp_source ];
				}

				$sp_input_name = 'cert_values[' . $sp_key . ']';
				$sp_label_id   = 'sp_tcert_' . $sp_key;
				?>
				<div class="sp-field">
					<label for="<?php echo esc_attr( $sp_label_id ); ?>">
						<?php echo esc_html( isset( $sp_field['label'] ) ? $sp_field['label'] : $sp_key ); ?>
						<?php if ( ! empty( $sp_field['required'] ) ) : ?>
							<span class="sp-req">*</span>
						<?php endif; ?>
					</label>

					<?php if ( 'textarea' === $sp_type ) : ?>
						<textarea id="<?php echo esc_attr( $sp_label_id ); ?>"
							name="<?php echo esc_attr( $sp_input_name ); ?>" rows="3"><?php echo esc_textarea( $sp_value ); ?></textarea>
					<?php elseif ( 'date' === $sp_type ) : ?>
						<input type="date" id="<?php echo esc_attr( $sp_label_id ); ?>"
							name="<?php echo esc_attr( $sp_input_name ); ?>"
							value="<?php echo esc_attr( $sp_value ); ?>" />
					<?php else : ?>
						<input type="text" id="<?php echo esc_attr( $sp_label_id ); ?>"
							name="<?php echo esc_attr( $sp_input_name ); ?>"
							value="<?php echo esc_attr( $sp_value ); ?>" />
					<?php endif; ?>

					<?php if ( '' !== $sp_source ) : ?>
						<p class="sp-help">
							Auto-filled from
							<em><?php echo esc_html( isset( sp_certificate_field_sources()[ $sp_source ] ) ? sp_certificate_field_sources()[ $sp_source ] : $sp_source ); ?></em>.
						</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<p class="sp-help">
				Attendance at issue: <strong><?php echo esc_html( sp_format_number( $state['attendance'] ) ); ?>%</strong>
				(<?php echo esc_html( sp_format_number( sp_certificate_min_attendance() ) ); ?>% required).
			</p>

			<button type="submit" class="sp-btn sp-btn-primary">
				<?php echo $existing ? 'Reissue certificate' : 'Issue certificate'; ?>
			</button>
		</form>
	</div>
	<?php
}

/**
 * Tick or untick one curriculum topic for the class.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_toggle_curriculum_item( $redirect ) {
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
	$item_id  = isset( $_POST['item_id'] ) ? (int) $_POST['item_id'] : 0;

	$teacher = sp_require_assigned_teacher( $class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$result = sp_toggle_curriculum_item( $class_id, $item_id, get_current_user_id() );

	if ( is_wp_error( $result ) ) {
		sp_teacher_redirect( $redirect, '', $result->get_error_message() );
	}

	sp_teacher_redirect(
		add_query_arg( 'tab', 'curriculum', $redirect ),
		'marked' === $result ? 'curriculum_marked' : 'curriculum_unmarked'
	);
}

/**
 * Create or update an assignment, uploading the paper when one is attached.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_save_assignment( $redirect ) {
	$assignment_id = isset( $_POST['assignment_id'] ) ? (int) $_POST['assignment_id'] : 0;
	$existing      = $assignment_id ? sp_get_assignment( $assignment_id ) : null;
	$class_id      = $existing ? (int) $existing->class_id : ( isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0 );
	$type          = $existing ? $existing->type : ( isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'paper' );

	$teacher = sp_require_assigned_teacher( $class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$saved = sp_save_assignment(
		array(
			'id'                 => $assignment_id,
			'class_id'           => $class_id,
			'type'               => $type,
			'title'              => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'        => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'due_date'           => isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '',
			'time_limit_minutes' => isset( $_POST['time_limit_minutes'] ) ? (int) $_POST['time_limit_minutes'] : 0,
		)
	);

	if ( is_wp_error( $saved ) ) {
		sp_teacher_redirect( $redirect, '', $saved->get_error_message() );
	}

	$saved_id = (int) $saved;

	// A paper is only useful with its file: store it against the assignment.
	if ( 'paper' === $type && ! empty( $_FILES['paper_file']['name'] ) ) {
		$stored = sp_store_uploaded_file( $_FILES['paper_file'], isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '' );

		if ( is_wp_error( $stored ) ) {
			sp_teacher_redirect( $redirect, '', $stored->get_error_message() );
		}

		global $wpdb;

		$wpdb->update(
			sp_table( 'assignments' ),
			array(
				'attachment_path'   => $stored['file_path'],
				'original_filename' => $stored['original_filename'],
			),
			array( 'id' => $saved_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// The replaced paper, if any, is no longer needed.
		if ( $existing && $existing->attachment_path && $existing->attachment_path !== $stored['file_path'] ) {
			$old = sp_assignment_file_path( $existing );

			if ( $old && file_exists( $old ) ) {
				@unlink( $old ); // phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged -- best-effort cleanup.
			}
		}
	}

	sp_teacher_redirect(
		add_query_arg( 'tab', 'details', sp_teacher_url( 'assignments/' . $saved_id ) ),
		'assignment_saved'
	);
}

/**
 * Delete an assignment and everything hanging off it.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_delete_assignment( $redirect ) {
	$assignment_id = isset( $_POST['assignment_id'] ) ? (int) $_POST['assignment_id'] : 0;
	$assignment    = sp_get_assignment( $assignment_id );

	if ( ! $assignment ) {
		sp_teacher_redirect( $redirect, '', 'That assignment no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $assignment->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$deleted = sp_delete_assignment( $assignment_id );

	if ( is_wp_error( $deleted ) ) {
		sp_teacher_redirect( $redirect, '', $deleted->get_error_message() );
	}

	sp_teacher_redirect( sp_teacher_url( 'assignments' ), 'assignment_deleted' );
}

/**
 * Add a question to the class bank, or edit one already there.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_save_question( $redirect ) {
	$question_id = isset( $_POST['question_id'] ) ? (int) $_POST['question_id'] : 0;
	$question    = $question_id ? sp_get_question( $question_id ) : null;
	$class_id    = $question ? (int) $question->class_id : ( isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0 );

	$teacher = sp_require_assigned_teacher( $class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$options = array();

	if ( isset( $_POST['options'] ) && is_array( $_POST['options'] ) ) {
		foreach ( wp_unslash( $_POST['options'] ) as $option ) {
			$options[] = sanitize_text_field( $option );
		}
	}

	$saved = sp_save_question(
		array(
			'id'          => $question_id,
			'class_id'    => $class_id,
			'type'        => isset( $_POST['question_type'] ) ? sanitize_key( wp_unslash( $_POST['question_type'] ) ) : '',
			'prompt'      => isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '',
			'options'     => $options,
			'answer'      => isset( $_POST['answer'] ) ? sanitize_text_field( wp_unslash( $_POST['answer'] ) ) : '',
			'explanation' => isset( $_POST['explanation'] ) ? sanitize_textarea_field( wp_unslash( $_POST['explanation'] ) ) : '',
		)
	);

	if ( is_wp_error( $saved ) ) {
		sp_teacher_redirect( $redirect, '', $saved->get_error_message() );
	}

	sp_teacher_redirect( add_query_arg( 'tab', 'bank', $redirect ), 'question_saved' );
}

/**
 * Remove a question from the class bank.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_delete_question( $redirect ) {
	$question_id = isset( $_POST['question_id'] ) ? (int) $_POST['question_id'] : 0;
	$question    = sp_get_question( $question_id );

	if ( ! $question ) {
		sp_teacher_redirect( $redirect, '', 'That question no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $question->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$deleted = sp_delete_question( $question_id );

	if ( is_wp_error( $deleted ) ) {
		sp_teacher_redirect( $redirect, '', $deleted->get_error_message() );
	}

	sp_teacher_redirect( add_query_arg( 'tab', 'bank', $redirect ), 'question_deleted' );
}

/**
 * Import questions from an uploaded CSV into the class bank.
 *
 * The file wins over pasted text so a teacher can use either path.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_import_questions( $redirect ) {
	$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

	$teacher = sp_require_assigned_teacher( $class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$csv = '';

	if ( ! empty( $_FILES['questions_file']['name'] ) ) {
		if ( ! isset( $_FILES['questions_file']['error'] ) || is_array( $_FILES['questions_file']['error'] ) ) {
			sp_teacher_redirect( $redirect, '', 'The CSV upload was malformed.' );
		}

		if ( UPLOAD_ERR_OK !== (int) $_FILES['questions_file']['error'] ) {
			sp_teacher_redirect( $redirect, '', 'The CSV upload failed (error code ' . (int) $_FILES['questions_file']['error'] . ').' );
		}

		if ( ! is_uploaded_file( $_FILES['questions_file']['tmp_name'] ) ) {
			sp_teacher_redirect( $redirect, '', 'The CSV upload could not be verified.' );
		}

		$csv = (string) file_get_contents( $_FILES['questions_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- read from the PHP upload temp file.
	} elseif ( isset( $_POST['questions_csv'] ) ) {
		$csv = wp_unslash( $_POST['questions_csv'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- parsed and sanitised row by row in sp_import_questions_csv().
	}

	$result = sp_import_questions_csv( $class_id, $csv );

	$imported = (int) $result['imported'];
	$failed   = count( $result['errors'] );

	if ( ! $imported && ! $failed ) {
		sp_teacher_redirect( add_query_arg( 'tab', 'bank', $redirect ), '', 'Nothing was imported.' );
	}

	if ( ! $imported ) {
		sp_teacher_redirect( add_query_arg( 'tab', 'bank', $redirect ), '', 'Nothing was imported: ' . implode( ' ', $result['errors'] ) );
	}

	$message = sprintf( '%d question%s imported.', $imported, 1 === $imported ? '' : 's' );

	if ( $failed ) {
		$message .= ' ' . $failed . ' line' . ( 1 === $failed ? '' : 's' ) . ' skipped: ' . implode( ' ', $result['errors'] );
	}

	sp_teacher_redirect( add_query_arg( 'tab', 'bank', $redirect ), '', $message );
}

/**
 * Synchronise an exam's question sets from the set editor.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_save_sets( $redirect ) {
	$assignment_id = isset( $_POST['assignment_id'] ) ? (int) $_POST['assignment_id'] : 0;
	$assignment    = sp_get_assignment( $assignment_id );

	if ( ! $assignment ) {
		sp_teacher_redirect( $redirect, '', 'That assignment no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $assignment->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$sets = array();

	if ( isset( $_POST['sets'] ) && is_array( $_POST['sets'] ) ) {
		foreach ( wp_unslash( $_POST['sets'] ) as $set ) {
			if ( ! is_array( $set ) ) {
				continue;
			}

			$question_ids = array();

			if ( isset( $set['question_ids'] ) && is_array( $set['question_ids'] ) ) {
				foreach ( $set['question_ids'] as $qid ) {
					$question_ids[] = (int) $qid;
				}
			}

			$sets[] = array(
				'label'        => isset( $set['label'] ) ? sanitize_text_field( $set['label'] ) : '',
				'question_ids' => $question_ids,
			);
		}
	}

	// "Add another set" saves what is on screen and appends one empty set,
	// so a draft can grow from one set to as many as the teacher needs.
	if ( isset( $_POST['add_set'] ) ) {
		$sets[] = array(
			'label'        => sp_next_set_label( $sets ),
			'question_ids' => array(),
		);
	}

	$saved = sp_save_assignment_sets( $assignment_id, $sets );

	if ( is_wp_error( $saved ) ) {
		sp_teacher_redirect( $redirect, '', $saved->get_error_message() );
	}

	if ( isset( $_POST['add_set'] ) ) {
		sp_teacher_redirect( add_query_arg( 'tab', 'sets', $redirect ), 'set_added' );
	}

	sp_teacher_redirect( add_query_arg( 'tab', 'sets', $redirect ), 'sets_saved' );
}

/**
 * Publish an assignment, dealing sets to the class when it is an exam.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_publish_assignment( $redirect ) {
	$assignment_id = isset( $_POST['assignment_id'] ) ? (int) $_POST['assignment_id'] : 0;
	$assignment    = sp_get_assignment( $assignment_id );

	if ( ! $assignment ) {
		sp_teacher_redirect( $redirect, '', 'That assignment no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $assignment->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'single';
	$set_id = isset( $_POST['set_id'] ) ? (int) $_POST['set_id'] : 0;
	$map    = array();

	if ( isset( $_POST['map'] ) && is_array( $_POST['map'] ) ) {
		foreach ( wp_unslash( $_POST['map'] ) as $pid => $sid ) {
			$map[ (int) $pid ] = (int) $sid;
		}
	}

	$dealt = sp_publish_assignment(
		$assignment_id,
		array(
			'mode'   => $mode,
			'set_id' => $set_id,
			'map'    => $map,
		)
	);

	if ( is_wp_error( $dealt ) ) {
		sp_teacher_redirect( $redirect, '', $dealt->get_error_message() );
	}

	sp_teacher_redirect( add_query_arg( 'tab', 'students', $redirect ), 'assignment_published' );
}

/**
 * Close a published assignment so no new attempts can start.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_close_assignment( $redirect ) {
	$assignment_id = isset( $_POST['assignment_id'] ) ? (int) $_POST['assignment_id'] : 0;
	$assignment    = sp_get_assignment( $assignment_id );

	if ( ! $assignment ) {
		sp_teacher_redirect( $redirect, '', 'That assignment no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $assignment->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$closed = sp_close_assignment( $assignment_id );

	if ( is_wp_error( $closed ) ) {
		sp_teacher_redirect( $redirect, '', $closed->get_error_message() );
	}

	sp_teacher_redirect( add_query_arg( 'tab', 'students', $redirect ), 'assignment_closed' );
}

/**
 * Let one student sit an exam again after a fail or a submit.
 *
 * @param string $redirect Destination after the action.
 */
function sp_teacher_action_allow_retake( $redirect ) {
	$student_row_id = isset( $_POST['student_row_id'] ) ? (int) $_POST['student_row_id'] : 0;
	$row            = sp_get_assignment_attempt( $student_row_id );

	if ( ! $row ) {
		sp_teacher_redirect( $redirect, '', 'That attempt no longer exists.' );
	}

	$assignment = sp_get_assignment( (int) $row->assignment_id );

	if ( ! $assignment ) {
		sp_teacher_redirect( $redirect, '', 'That assignment no longer exists.' );
	}

	$teacher = sp_require_assigned_teacher( (int) $assignment->class_id );

	if ( is_wp_error( $teacher ) ) {
		sp_teacher_redirect( $redirect, '', $teacher->get_error_message() );
	}

	$allowed = sp_allow_assignment_retake( $student_row_id );

	if ( is_wp_error( $allowed ) ) {
		sp_teacher_redirect( $redirect, '', $allowed->get_error_message() );
	}

	sp_teacher_redirect( add_query_arg( 'tab', 'students', $redirect ), 'retake_granted' );
}

/**
 * Nonce field for a teacher action.
 *
 * @param string $action Action slug.
 */
function sp_teacher_nonce_field( $action ) {
	wp_nonce_field( 'sp_teacher_action_' . $action, 'sp_teacher_nonce' );
	?>
	<input type="hidden" name="sp_action" value="<?php echo esc_attr( $action ); ?>" />
	<input type="hidden" name="sp_redirect" value="<?php echo esc_url( sp_teacher_current_url() ); ?>" />
	<?php
}

/**
 * Current teacher portal URL, including any query arguments.
 *
 * @return string
 */
function sp_teacher_current_url() {
	$route = sanitize_key( (string) get_query_var( 'sp_teacher_route' ) );

	$base = ( '' === $route || 'home' === $route )
		? sp_teacher_url()
		: sp_teacher_url( $route );

	$class_id = (int) get_query_var( 'sp_teacher_id' );

	if ( $class_id > 0 ) {
		$base = sp_teacher_url( 'classes/' . $class_id );
	}

	// The edit screens keep their own human URLs, not their route slugs.
	$assignment_id = (int) get_query_var( 'sp_assignment_id' );

	if ( 'assignment-edit' === $route ) {
		$base = $assignment_id > 0
			? sp_teacher_url( 'assignments/' . $assignment_id )
			: sp_teacher_url( 'assignments/new' );
	}

	return $base;
}