<?php
/**
 * Admin menu and page dispatch.
 *
 * Page renderers live in includes/admin/ and are only loaded when their screen
 * is actually requested.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SP_PATH . 'includes/admin-actions.php';

/**
 * The admin screens this plugin owns.
 *
 * @return array slug => array(label, capability, callback file, menu title).
 */
function sp_admin_pages() {
	return array(
		'sp-dashboard'         => array(
			'label'      => 'Dashboard',
			'capability' => 'manage_certificates',
			'file'       => 'dashboard.php',
			'title'      => 'Portal',
		),
		'sp-add-student'       => array(
			// Merged into Student Accounts; sp_render_admin_page() redirects
			// the old URL there so existing links and notices still land.
			'label'      => 'Add Student',
			'capability' => 'manage_certificates',
			'file'       => 'add-student.php',
			'hidden'     => true,
		),
		'sp-import'           => array(
			'label'      => 'Import',
			'capability' => 'manage_certificates',
			'file'       => 'import.php',
		),
		'sp-student-accounts'  => array(
			'label'      => 'Student Accounts',
			'capability' => 'manage_certificates',
			'file'       => 'student-accounts.php',
		),
		'sp-teachers'          => array(
			'label'      => 'Teachers',
			'capability' => 'manage_certificates',
			'file'       => 'teachers.php',
		),
		'sp-courses'         => array(
			'label'      => 'Courses',
			'capability' => 'manage_certificates',
			'file'       => 'courses.php',
		),
		'sp-classes'         => array(
			'label'      => 'Classes',
			'capability' => 'manage_certificates',
			'file'       => 'classes.php',
		),
		'sp-enrollments'       => array(
			'label'      => 'Enrollments',
			'capability' => 'manage_certificates',
			'file'       => 'enrollments.php',
		),
		'sp-attendance'        => array(
			'label'      => 'Attendance',
			'capability' => 'manage_certificates',
			'file'       => 'attendance.php',
		),
		'sp-calendar'          => array(
			'label'      => 'Calendar',
			'capability' => 'manage_certificates',
			'file'       => 'calendar.php',
		),
		'sp-announcements'     => array(
			'label'      => 'Announcements',
			'capability' => 'manage_certificates',
			'file'       => 'announcements.php',
		),
		'sp-id-cards'          => array(
			'label'      => 'ID cards',
			'capability' => 'manage_certificates',
			'file'       => 'id-cards.php',
		),
		'sp-results'           => array(
			'label'      => 'Results',
			'capability' => 'manage_certificates',
			'file'       => 'results.php',
		),
		'sp-certificate-fields' => array(
			'label'      => 'Certificate fields',
			'capability' => 'manage_certificates',
			'file'       => 'certificate-fields.php',
		),
		'sp-settings'          => array(
			'label'      => 'Settings',
			'capability' => 'manage_options',
			'file'       => 'settings-page.php',
		),
		'sp-email-log'         => array(
			'label'      => 'Email Log',
			'capability' => 'manage_options',
			'file'       => 'email-log.php',
		),
	);
}

/**
 * Register the top-level menu and its subpages.
 */
function sp_register_admin_menu() {
	$pages = sp_admin_pages();

	add_menu_page(
		'Student Portal',
		'Student Portal',
		'manage_certificates',
		'sp-dashboard',
		'sp_render_admin_page',
		'dashicons-welcome-learn-more',
		58
	);

	foreach ( $pages as $slug => $page ) {
		if ( ! empty( $page['hidden'] ) ) {
			continue;
		}

		add_submenu_page(
			'sp-dashboard',
			$page['label'],
			isset( $page['menu'] ) ? $page['menu'] : $page['label'],
			$page['capability'],
			$slug,
			'sp_render_admin_page'
		);
	}
}
add_action( 'admin_menu', 'sp_register_admin_menu' );

/**
 * Send the retired Add Student URL to the merged Student Accounts screen.
 *
 * Runs on admin_init — before any output — because the page callback itself
 * fires after admin-header.php has printed, where a redirect is no longer
 * possible. The edit id is carried across so ?edit=<id> keeps working.
 */
function sp_redirect_legacy_add_student() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$sp_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	if ( 'sp-add-student' !== $sp_page || wp_doing_ajax() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$sp_edit = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;

	wp_safe_redirect(
		add_query_arg(
			$sp_edit ? array( 'page' => 'sp-student-accounts', 'edit' => $sp_edit ) : array( 'page' => 'sp-student-accounts' ),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_init', 'sp_redirect_legacy_add_student' );

/**
 * Render the requested admin screen.
 */
function sp_render_admin_page() {
	$pages = sp_admin_pages();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'sp-dashboard';

	if ( ! isset( $pages[ $slug ] ) ) {
		return;
	}

	$page = $pages[ $slug ];

	if ( ! current_user_can( $page['capability'] ) ) {
		wp_die( esc_html__( 'You do not have permission to view this screen.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	require_once SP_PATH . 'includes/admin/' . $page['file'];

	$notice = sp_admin_notice();

	/*
	 * .sp-scope is what the design tokens in admin-base.css hang off; without it the
	 * colour and radius variables never resolve and every page falls back to
	 * bare wp-admin defaults. .sp-page-head gives the title a consistent block
	 * instead of a lone h1 whose margins differ on every screen.
	 */
	echo '<div class="wrap sp-scope">';

	if ( 'error' === $notice['type'] ) {
		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $notice['message'] ) );
	} elseif ( '' !== $notice['message'] ) {
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notice['message'] ) );
	}

	echo '<div class="sp-page-head">';
	echo '<h1>' . esc_html( isset( $page['title'] ) ? $page['title'] : $page['label'] ) . '</h1>';
	echo '</div>';

	call_user_func( 'sp_admin_page_' . str_replace( '-', '_', $slug ) );

	echo '</div>';
}

/**
 * Enqueue the portal admin stylesheet and the media library on our screens only.
 *
 * @param string $hook Current admin page hook.
 */
function sp_admin_enqueue_assets( $hook ) {
	$pages = sp_admin_pages();
	$slug  = '';

	if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$slug = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
	}

	$ours = ( 0 === strpos( $hook, 'student-portal_page_' ) ) || isset( $pages[ $slug ] );

	if ( ! $ours ) {
		return;
	}

	/*
	 * Deliberately no CDN and no Bootstrap. Bootstrap is a global stylesheet, so
	 * loading it here would restyle every other plugin on the site and the admin
	 * would degrade without network access. The portal ships its own self-contained
	 * stylesheet instead.
	 */
	/*
	 * Theme tokens load first: every other plugin stylesheet resolves its
	 * colours and radii through them (assets/css/tokens.css).
	 */
	wp_enqueue_style(
		'student-portal-tokens',
		SP_URL . 'assets/css/tokens.css',
		array(),
		SP_VERSION
	);

	wp_enqueue_style(
		'student-portal-admin',
		SP_URL . 'assets/css/admin-base.css',
		array( 'student-portal-tokens' ),
		SP_VERSION
	);

	// One stylesheet per admin screen, alongside whichever page includes it.
	$page = 'admin-' . str_replace( 'sp-', '', $slug ) . '.css';

	if ( file_exists( SP_PATH . 'assets/css/' . $page ) ) {
		wp_enqueue_style(
			'student-portal-admin-' . $slug,
			SP_URL . 'assets/css/' . $page,
			array( 'student-portal-admin' ),
			SP_VERSION
		);
	}

	// The merged Student Accounts screen also renders the add/edit form.
	if ( 'sp-student-accounts' === $slug && file_exists( SP_PATH . 'assets/css/admin-add-student.css' ) ) {
		wp_enqueue_style(
			'student-portal-admin-add-student',
			SP_URL . 'assets/css/admin-add-student.css',
			array( 'student-portal-admin' ),
			SP_VERSION
		);
	}

	/*
	 * The compose preview on the Announcements screen renders the real portal
	 * banner and bar, so it needs the same stylesheet the portals use.
	 */
	if ( 'sp-announcements' === $slug && file_exists( SP_PATH . 'assets/css/announcements.css' ) ) {
		wp_enqueue_style(
			'student-portal-announcements',
			SP_URL . 'assets/css/announcements.css',
			array( 'student-portal-admin' ),
			SP_VERSION
		);
	}

	// ID card screen: html2canvas + jsPDF drive the JPG/PDF downloads.
	if ( 'sp-id-cards' === $slug ) {
		wp_enqueue_script(
			'sp-idcard-html2canvas',
			SP_URL . 'assets/js/html2canvas.min.js',
			array(),
			SP_VERSION,
			true
		);

		wp_enqueue_script(
			'sp-idcard-jspdf',
			SP_URL . 'assets/js/jspdf.umd.min.js',
			array(),
			SP_VERSION,
			true
		);

		wp_enqueue_script(
			'sp-idcard-screen',
			SP_URL . 'assets/js/id-card.js',
			array( 'sp-idcard-html2canvas', 'sp-idcard-jspdf' ),
			SP_VERSION,
			true
		);

		wp_localize_script(
			'sp-idcard-screen',
			'SpIdCard',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'sp_idcard' ),
			)
		);
	}

	if ( 'sp-calendar' === $slug ) {
		wp_enqueue_style(
			'student-portal-schedule',
			SP_URL . 'assets/css/schedule.css',
			array(),
			SP_VERSION
		);

		wp_enqueue_script(
			'student-portal-schedule',
			SP_URL . 'assets/js/schedule.js',
			array(),
			SP_VERSION,
			true
		);

		wp_localize_script(
			'student-portal-schedule',
			'SpSchedule',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'sp_schedule_side' ),
			)
		);
	}

	// The reusable date & time component on the screens that render those
	// inputs (attendance date, class start/end, DOB, calendar sidebar date).
	if ( in_array( $slug, array( 'sp-attendance', 'sp-classes', 'sp-student-accounts', 'sp-calendar' ), true ) ) {
		sp_datetime_enqueue();
	}

	// Register live counter + submit confirmation on the attendance screen.
	if ( 'sp-attendance' === $slug ) {
		wp_enqueue_script(
			'student-portal-attendance',
			SP_URL . 'assets/js/attendance.js',
			array(),
			SP_VERSION,
			true
		);
	}

	// Async filters & small submits on the screens wired with data-sp-async:
	// the region swaps in place instead of reloading the whole admin page.
	if ( in_array( $slug, array( 'sp-student-accounts', 'sp-results', 'sp-email-log', 'sp-enrollments', 'sp-attendance' ), true ) ) {
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

	wp_enqueue_media();
}
add_action( 'admin_enqueue_scripts', 'sp_admin_enqueue_assets' );

/**
 * Render a nonce field plus the action marker for an admin form.
 *
 * @param string $action Action slug.
 */
function sp_admin_nonce_field( $action ) {
	wp_nonce_field( 'sp_admin_action_' . $action, 'sp_admin_nonce' );
	echo '<input type="hidden" name="sp_action" value="' . esc_attr( $action ) . '" />';
}

/**
 * Render the "Certificate Manager is inactive" warning, when relevant.
 */
function sp_admin_cert_notice() {
	if ( sp_certificate_manager_active() ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>Certificate Manager is not active.</strong> '
		. 'Issued certificates will not be pushed to the vendor. Classes, enrolments, attendance, results and materials all keep working.</p></div>';
}

/**
 * Status badge markup for the admin tables.
 *
 * @param string $status Stored status.
 * @return string
 */
function sp_admin_status_badge( $status ) {
	switch ( $status ) {
		case 'active':
		case 'published':
		case 'generated':
		case 'pass':
			$class = 'sp-pill sp-pill-good';
			break;
		case 'disabled':
		case 'fail':
			$class = 'sp-pill sp-pill-bad';
			break;
		case 'submitted':
			$class = 'sp-pill sp-pill-good';
			break;
		case 'open':
		case 'pending':
		case 'draft':
		case 'not_generated':
			$class = 'sp-pill sp-pill-muted';
			break;
		case 'completed':
			$class = 'sp-pill sp-pill-info';
			break;
		case 'not_set':
			$class = 'sp-pill sp-pill-muted';
			break;
		case 'needs_email':
			$class = 'sp-pill sp-pill-warn';
			break;
		case 'link':
			$class = 'sp-pill sp-pill-info';
			break;
		case 'linked':
			$class = 'sp-pill sp-pill-good';
			break;
		case 'dropped':
			$class = 'sp-pill sp-pill-muted';
			break;
		default:
			$class = 'sp-pill sp-pill-muted';
			break;
	}

	return sprintf( '<span class="%s">%s</span>', esc_attr( $class ), esc_html( ucfirst( (string) $status ) ) );
}

/**
 * Send a sample CSV download.
 */
function sp_admin_sample_csv() {
	// This runs on every admin request, so it must be a no-op unless the
	// download was explicitly asked for. Without this guard the handler fires
	// on unrelated screens (including the post-activation redirect) and
	// hijacks the response into a CSV download.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only download.
	if ( ! isset( $_GET['sp_sample'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only download.
	$type = sanitize_key( wp_unslash( $_GET['sp_sample'] ) );

	if ( ! in_array( $type, array( 'students', 'teachers' ), true ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_certificates' ) ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	$csv    = ( 'teachers' === $type ) ? sp_sample_teacher_csv() : sp_sample_student_csv();
	$name   = ( 'teachers' === $type ) ? 'teachers-sample.csv' : 'students-sample.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . strlen( $csv ) );

	echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
	exit;
}
add_action( 'admin_init', 'sp_admin_sample_csv' );