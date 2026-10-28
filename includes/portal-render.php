<?php
/**
 * Student portal rendering: asset loading, layout and shared display helpers.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the portal stylesheets only on portal pages: the shared base plus
 * the current route's own file — one stylesheet per template/page.
 */
function sp_portal_enqueue_assets() {
	$route = (string) get_query_var( 'sp_route' );

	if ( '' === $route ) {
		return; // the teacher portal enqueues its own stack.
	}

	sp_portal_enqueue_css( $route );

	wp_enqueue_script(
		'student-portal',
		SP_URL . 'assets/js/portal.js',
		array(),
		SP_VERSION,
		true
	);

	// Materials (list and detail) embed documents inline. The .docx preview
	// needs jQuery (the Mammoth bundle uses it) and the script that drives the
	// conversion; both are printed for those routes only.
	if ( in_array( $route, array( 'materials', 'material' ), true ) && is_user_logged_in() ) {
		wp_enqueue_script( 'jquery' );

		wp_enqueue_script(
			'sp-material-embed',
			SP_URL . 'assets/js/material-embed.js',
			array( 'jquery' ),
			SP_VERSION,
			true
		);
	}

	// The profile save posts without a page reload (toast + URL sync only).
	if ( 'profile' === $route && is_user_logged_in() ) {
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
}
add_action( 'wp_enqueue_scripts', 'sp_portal_enqueue_assets' );

/**
 * Enqueue the base plus the current route's stylesheet for the student portal.
 *
 * The login screen replaces the dashboard on `home` when nobody is signed in,
 * so it gets its own file instead of the dashboard's.
 *
 * @param string $route Route slug from sp_portal_routes().
 */
function sp_portal_enqueue_css( $route ) {
	wp_enqueue_style(
		'student-portal-tokens',
		SP_URL . 'assets/css/tokens.css',
		array(),
		SP_VERSION
	);

	wp_enqueue_style(
		'student-portal-base',
		SP_URL . 'assets/css/portal-base.css',
		array( 'student-portal-tokens' ),
		SP_VERSION
	);

	if ( ! is_user_logged_in() ) {
		wp_enqueue_style(
			'student-portal-login',
			SP_URL . 'assets/css/portal-login.css',
			array( 'student-portal-base' ),
			SP_VERSION
		);
	} else {
	$page = 'portal-' . $route . '.css';

	if ( 'material' === $route ) {
		$page = 'portal-materials.css';
	}		if ( file_exists( SP_PATH . 'assets/css/' . $page ) ) {
			wp_enqueue_style(
				'student-portal-' . $route,
				SP_URL . 'assets/css/' . $page,
				array( 'student-portal-base' ),
				SP_VERSION
			);
		}

		// Announcement banner / bar styles, shared with the teacher portal.
		wp_enqueue_style(
			'sp-announcements',
			SP_URL . 'assets/css/announcements.css',
			array( 'student-portal-base' ),
			SP_VERSION
		);
	}

	if ( 'calendar' === $route ) {
		wp_enqueue_style(
			'student-portal-schedule',
			SP_URL . 'assets/css/schedule.css',
			array( 'student-portal-base' ),
			SP_VERSION
		);
	}
}

/**
 * Render a portal route and end the request.
 *
 * The portal owns the whole document: `get_header()` / `get_footer()` are
 * deliberately not called, so the active theme's header, footer, sidebar and
 * admin bar never appear around portal content. Only `wp_head()` / `wp_footer()`
 * run, which is what still delivers the enqueued assets and the title tag.
 *
 * @param string      $slug    Route slug.
 * @param object|null $profile Student profile row, or null on the login screen.
 */
function sp_render_portal( $slug, $profile ) {
	$routes = sp_portal_routes();

	if ( ! isset( $routes[ $slug ] ) ) {
		return;
	}

	$route = $routes[ $slug ];

	// Form submissions are handled before any output so an action can run and
	// then redirect, which keeps a browser refresh from re-posting.
	sp_handle_portal_post();

	sp_portal_document_start();

	if ( ! is_user_logged_in() ) {
		require SP_PATH . 'templates/portal-login.php';
	} elseif ( ! empty( $route['fullscreen'] ) ) {
		// Exams and other focus views: a minimal shell with no sidebar or rail.
		require SP_PATH . 'templates/portal-fullscreen-start.php';
		require SP_PATH . 'templates/' . $route['template'];
		require SP_PATH . 'templates/portal-fullscreen-end.php';
	} else {
		require SP_PATH . 'templates/portal-layout-start.php';
		require SP_PATH . 'templates/' . $route['template'];
		require SP_PATH . 'templates/portal-layout-end.php';
	}

	sp_portal_document_end();
}

/**
 * Stop full-page caches from storing portal screens.
 *
 * The student and teacher portals render login forms whose nonces are minted
 * per request. A full-page cache (for example LiteSpeed Cache) that stores one
 * of these screens and later serves it back will hand the browser a stale
 * nonce, which then fails wp_verify_nonce() and falsely reports that the
 * student's session expired. These screens are inherently personalised and must
 * never be cached.
 *
 * Defines DONOTCACHEPAGE, which LiteSpeed Cache (and most other caches) honour,
 * and fires LiteSpeed's own no-cache action when available.
 */
function sp_disable_portal_page_cache() {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	// LiteSpeed Cache reads this action to mark the response uncacheable.
	do_action( 'litespeed_control_set_nocache', 'Student/teacher portal screen' );
}

/**
 * Client-side no-cache guard for attendance screens.
 *
 * Attendance is per-user data, so it must never be served from a cache. The
 * server side is already covered — the portal dispatchers define
 * DONOTCACHEPAGE and sp_portal_document_start() calls nocache_headers() — but
 * those run before the attendance template is included, once the response
 * headers have been sent. This emits the equivalent meta hint so a browser or
 * intermediary that respects meta-based cache control still refuses to store
 * or reuse the rendered page. Harmless where it is ignored.
 */
function sp_attendance_no_cache_guard() {
	?>
	<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, private" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Expires" content="0" />
	<?php
}

/**
 * Open the portal's own HTML document.
 *
 * No theme markup at all: a bare document with our stylesheet and script,
 * without the site chrome a front-end page normally inherits. The admin bar is
 * switched off too, since it is site UI and would sit on top of the portal.
 */
function sp_portal_document_start() {
	status_header( 200 );
	nocache_headers();
	show_admin_bar( false );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<?php wp_head(); ?>
	</head>
	<body class="sp-standalone">
	<?php
	if ( function_exists( 'wp_body_open' ) ) {
		wp_body_open();
	}
}

/**
 * Close the portal's own HTML document.
 *
 * `wp_footer()` still runs: it is what prints the enqueued portal script and
 * lets other plugins clean up. Only the theme's own footer markup is skipped.
 */
function sp_portal_document_end() {
	wp_footer();
	?>
	</body>
</html>
	<?php
	exit;
}

/**
 * Line icon for a sidebar nav item. Decorative only - the label is text.
 *
 * @param string $name Route slug: home, certificates, attendance, curriculum,
 *                     results, materials, tasks, profile.
 * @return string SVG markup, empty string for unknown keys.
 */
function sp_portal_nav_icon( $name ) {
	$paths = array(
		'home'        => '<path d="M3 10.5 12 3l9 7.5" /><path d="M5.5 9.5V21h13V9.5" /><path d="M10 21v-6h4v6" />',
		'certificates'=> '<circle cx="12" cy="9" r="5.5" /><path d="M8.5 13.5 7 21l5-2.5L17 21l-1.5-7.5" />',
		'attendance'  => '<rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /><path d="m9 15 2 2 4-4" />',
		'curriculum'  => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z" /><path d="M4 5.5v15" /><path d="M9 8h7M9 12h5" />',
		'results'     => '<path d="M4 20V10" /><path d="M10 20V4" /><path d="M16 20v-7" /><path d="M22 20H2" />',
		'materials'   => '<path d="M6 3h8l5 5v13a 1 1 0 0 1-1 1H6a 1 1 0 0 1-1-1V4a 1 1 0 0 1 1-1z" /><path d="M14 3v5h5" /><path d="M8.5 13h7M8.5 17h5" />',
		'tasks'       => '<path d="M7 3h10a1 1 0 0 1 1 1v16l-6-3.5L6 20V4a1 1 0 0 1 1-1z" /><path d="M9.5 8.5h5M9.5 12h3" />',
		'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" />',
		'profile'     => '<circle cx="12" cy="8" r="4" /><path d="M4.5 21c1.5-4 4.5-6 7.5-6s6 2 7.5 6" />',
		'notifications' => '<path d=\"M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9\" /><path d=\"M13.73 21a2 2 0 0 1-3.46 0\" />',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return '<svg class="sp-nav-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Initials for the avatar circles (sidebar user, rail profile card).
 *
 * @param string $name Display name.
 * @return string One or two uppercase letters, '?' when empty.
 */
function sp_portal_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$init  = '';

	if ( $parts ) {
		$init .= mb_strtoupper( mb_substr( $parts[0], 0, 1 ) );
	}

	if ( count( $parts ) > 1 ) {
		$init .= mb_strtoupper( mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) );
	}

	return $init ? $init : '?';
}

/**
 * Password reveal button, eye icon included.
 *
 * Shared by both login forms so they cannot drift apart. The icon is drawn
 * here rather than in CSS so it inherits the button's current colour, and the
 * accessible name is carried by aria-label because the visible text is only an
 * icon.
 *
 * @param string $input_id Id of the input the button toggles.
 */
function sp_password_toggle( $input_id ) {
	?>
	<button
		type="button"
		class="sp-pw-toggle"
		data-target="#<?php echo esc_attr( $input_id ); ?>"
		aria-pressed="false"
		aria-label="<?php echo esc_attr__( 'Show password', 'student-portal' ); ?>"
	>
		<svg class="sp-pw-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
			<path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
			<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8" />
			<line class="sp-pw-icon-slash" x1="4" y1="20" x2="20" y2="4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
		</svg>
	</button>
	<?php
}

/**
 * Line-art illustration shown beside both login forms.
 *
 * Ships as a webp in assets/images - white line art on a transparent
 * background, sized for the accent panel. Decorative, so the alt text stays
 * empty; the intrinsic dimensions keep the layout steady while it loads.
 */
function sp_auth_illustration() {
	?>
	<img class="sp-auth-art"
		src="<?php echo esc_url( SP_URL . 'assets/images/login.png' ); ?>"
		alt=""
		width="2192"
		height="1820"
		decoding="async" />
	<?php
}

/**
 * The error message for the current login attempt, if any.
 *
 * Read by both login screens, so wording here is what a student and a teacher
 * each see for the same condition.
 *
 * @return string
 */
function sp_portal_login_error() {
	if ( empty( $_GET['sp_error'] ) ) {
		return '';
	}

	switch ( sanitize_key( wp_unslash( $_GET['sp_error'] ) ) ) {
		case 'login':
			return 'That email address and password combination was not recognised.';
		case 'expired':
			// The sign-in form was submitted with a stale nonce. The form is
			// re-rendered with a fresh one, so the student can just sign in.
			return 'Your sign-in form had expired. Please try again.';
		case 'disabled':
			// Only reachable after the password verified, so it can be specific.
			return 'That account has been disabled. Contact your institute to have it reactivated.';
		case 'not_portal':
			return 'That account is not set up for portal access.';
		case 'throttled':
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display.
			$wait = isset( $_GET['sp_wait'] ) ? max( 1, (int) $_GET['sp_wait'] ) : 0;

			return sprintf(
				'Too many failed sign-in attempts. Try again in %d minute(s).',
				(int) ceil( $wait / 60 )
			);
		default:
			return '';
	}
}

/**
 * Read a redirected message for the current request, if any.
 *
 * Mirrors sp_teacher_notice(): the portal redirects with ready-to-read prose
 * rather than a code, so whatever the layout prints is already a sentence.
 *
 * @param string $key Query argument to read. 'sp_notice' renders as a success
 *                    notice and 'sp_notice_error' as a failure notice.
 * @return string
 */
function sp_portal_notice( $key = 'sp_notice' ) {
	if ( empty( $_GET[ $key ] ) ) {
		return '';
	}

	return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
}

/**
 * Format a moment in Indian Standard Time (Asia/Kolkata).
 *
 * Portal clocks and submitted/issued timestamps are shown to students in IST
 * regardless of the site's configured timezone.
 *
 * @param string $format PHP date format.
 * @param int|null $timestamp Unix timestamp; now when omitted.
 * @return string
 */
function sp_portal_time( $format, $timestamp = null ) {
	return wp_date( $format, null === $timestamp ? time() : (int) $timestamp, new \DateTimeZone( 'Asia/Kolkata' ) );
}

/**
 * The message shown when a signed-in user is sent to the portal they belong to.
 *
 * Both dispatchers and both login handlers reach for it so the wording cannot
 * drift between them.
 *
 * @param string $from The portal they tried to open: 'student' or 'teacher'.
 * @return string
 */
function sp_wrong_portal_notice( $from ) {
	if ( 'student' === $from ) {
		return 'This is the student portal. Your account belongs to the teacher portal, so we have taken you there.';
	}

	return 'This is the teacher portal. Your account belongs to the student portal, so we have taken you there.';
}

/**
 * A portal home URL carrying a ready-to-read notice.
 *
 * @param string $portal  'student' or 'teacher'.
 * @param string $message Notice text.
 * @return string
 */
function sp_portal_url_with_notice( $portal, $message ) {
	return add_query_arg( 'sp_notice', rawurlencode( $message ), sp_portal_home_url( $portal ) );
}

/**
 * Logout URL that returns the visitor to the right portal.
 *
 * @param string $which 'student' or 'teacher'.
 * @return string
 */
function sp_portal_logout_url( $which = 'student' ) {
	return wp_logout_url( sp_portal_home_url( $which ) );
}

/**
 * Badge markup for an attendance percentage.
 *
 * @param float $percentage Attendance percentage.
 * @return string
 */
function sp_attendance_badge( $percentage ) {
	$percentage = (float) $percentage;

	if ( $percentage >= 75 ) {
		$class = 'sp-badge sp-badge-good';
	} elseif ( $percentage >= 50 ) {
		$class = 'sp-badge sp-badge-warn';
	} else {
		$class = 'sp-badge sp-badge-bad';
	}

	return sprintf( '<span class="%s">%s%%</span>', esc_attr( $class ), esc_html( sp_format_number( $percentage ) ) );
}

/**
 * Badge markup for a pass/fail result.
 *
 * @param string $result pass, fail or pending.
 * @return string
 */
function sp_result_badge( $result ) {
	switch ( $result ) {
		case 'pass':
			$class = 'sp-badge sp-badge-good';
			break;
		case 'fail':
			$class = 'sp-badge sp-badge-bad';
			break;
		default:
			$class = 'sp-badge sp-badge-muted';
			break;
	}

	return sprintf( '<span class="%s">%s</span>', esc_attr( $class ), esc_html( sp_result_label( $result ) ) );
}

/**
 * Badge class for a single attendance status.
 *
 * @param string $status present, absent, late or excused.
 * @return string
 */
function sp_attendance_row_class( $status ) {
	switch ( $status ) {
		case 'present':
			return 'sp-badge-good';
		case 'absent':
			return 'sp-badge-bad';
		case 'late':
			return 'sp-badge-warn';
		default:
			return 'sp-badge-muted';
	}
}

/**
 * Inline viewer markup for a file material.
 *
 * The student portal has no download links any more: every document is
 * embedded on the page.
 *
 *   pdf / text  an iframe on the gated route with `inline=1`, so the stream
 *               answers Content-Disposition: inline and the browser renders it
 *   image       a plain <img> on the same gated URL
 *   docx        converted to HTML in the browser by material-embed.js through
 *               the Mammoth bundle (sp_docx_preview_host() prints that contract)
 *   none        a card explaining that this file type cannot be previewed
 *
 * Nothing here falls back to a download link — that is the point of the
 * feature. The "File: name" line the template prints under the embed keeps
 * the filename visible for the formats that show no preview.
 *
 * @param object $material sp_materials row for a `file` material.
 */
function sp_material_embed( $material ) {
	$kind = sp_material_embed_kind( $material );
	$name = $material->original_filename ? $material->original_filename : '';
	$alt  = $material->title ? $material->title : 'Course document';

	if ( 'none' === $kind || ( 'docx' === $kind && ! sp_docx_preview_available() ) ) {
		$extension = sp_material_extension( $material );
		?>
		<div class="sp-embed sp-embed-unavailable">
			<strong>Preview not available</strong>
			<span class="sp-muted"><?php
				/* translators: %s: file name or extension of the document. */
				echo esc_html( $name ? sprintf( '“%s” cannot be displayed in the browser.', $name ) : sprintf( 'Files of type “.%s” cannot be displayed in the browser.', $extension ) );
			?></span>
		</div>
		<?php
		return;
	}

	switch ( $kind ) {
		case 'image':
			?>
			<img class="sp-embed sp-embed-image"
				src="<?php echo esc_url( sp_material_inline_url( $material->id ) ); ?>"
				alt="<?php echo esc_attr( $alt ); ?>"
				loading="lazy"
				decoding="async" />
			<?php
			break;

		case 'docx':
			?>
			<div class="sp-embed sp-embed-docx"
				data-src="<?php echo esc_url( sp_material_inline_url( $material->id ) ); ?>"
				data-name="<?php echo esc_attr( $name ); ?>">
				<p class="sp-embed-status">Preparing the document preview&hellip;</p>
			</div>
			<?php
			break;

		default: // pdf, text.
			$sp_src = sp_material_inline_url( $material->id );

			// The browser's PDF viewer ships download and print buttons on its
			// toolbar; the fragment asks the viewer not to draw them at all.
			// Anything the viewer still offers internally (its own shortcuts)
			// cannot be removed from the page — see material-embed.js.
			if ( 'pdf' === $kind ) {
				$sp_src .= '#toolbar=0&navpanes=0';
			}
			?>
			<iframe class="sp-embed sp-embed-frame"
				src="<?php echo esc_url( $sp_src ); ?>"
				title="<?php echo esc_attr( $alt ); ?>"
				loading="lazy"></iframe>
			<?php
	}
}

/**
 * The hidden DOM contract the Mammoth bundle initialises itself against,
 * plus the bundle itself, for pages that preview a .docx material.
 *
 * The Mammoth .docx converter plugin wires its converter up on script load
 * to #mammoth-docx-uploader and never exposes the library globally, so the
 * only way to reuse it is to give it exactly the elements it looks for and
 * feed a File into #mammoth-docx-upload. material-embed.js does that and
 * reads the converted HTML back out of #mammoth-docx-visual-preview.
 *
 * Printed from the template (not enqueued) because the script must run after
 * this markup exists — and only on pages that actually have a .docx to show.
 */
function sp_docx_preview_host() {
	$bundle = WP_PLUGIN_DIR . '/mammoth-docx-converter/mammoth-editor.js';

	if ( ! file_exists( $bundle ) ) {
		return;
	}
	?>
	<div id="mammoth-docx-uploader" class="sp-mammoth-host" aria-hidden="true">
		<input type="file" id="mammoth-docx-upload" tabindex="-1" />
		<iframe id="mammoth-docx-visual-preview" src="about:blank" data-stylesheets="<?php echo esc_url( SP_URL . 'assets/css/docx-frame.css' ); ?>"></iframe>
		<input type="button" id="mammoth-docx-insert" value="Insert into editor" />
		<pre id="mammoth-docx-raw-preview"></pre>
		<div id="mammoth-docx-messages"></div>
		<p class="mammoth-docx-error"><span id="mammoth-docx-error-message"></span></p>
	</div>
	<script src="<?php echo esc_url( plugins_url( 'mammoth-docx-converter/mammoth-editor.js' ) . '?v=1.22.0' ); ?>"></script>
	<?php
}

/**
 * Notice shown when the Certificate Manager plugin cannot be reached.
 *
 * Certificates are issued by the portal itself; the vendor is only a one-way
 * push at generation, so this is informational.
 *
 * @return string
 */
function sp_certificate_manager_notice() {
	if ( sp_certificate_manager_active() ) {
		return '';
	}

	return '<div class="sp-notice sp-notice-warn"><strong>Certificate Manager is not active.</strong> '
		. 'Issued certificates will not be pushed to the vendor. '
		. 'Your classes, attendance, results and materials keep working.</div>';
}

/**
 * Handle every student portal form submission.
 *
 * The student portal previously had no post handler at all, so any form added
 * to it would have silently done nothing.
 */
function sp_handle_portal_post() {
	if ( empty( $_POST['sp_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['sp_action'] ) );

	$nonce = isset( $_POST['sp_portal_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_portal_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'sp_portal_action_' . $action ) ) {
		wp_die( esc_html__( 'Your session expired. Please go back and try again.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	if ( ! sp_is_student() ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	switch ( $action ) {
		case 'save_profile':
			sp_portal_action_save_profile();
			break;
		case 'start_assignment':
			sp_portal_action_start_assignment();
			break;
		case 'submit_assignment':
			sp_portal_action_submit_assignment();
			break;
		case 'leave_beacon':
			sp_portal_action_leave_beacon();
			break;
		case 'dismiss_announcement':
			sp_portal_action_dismiss_announcement();
			break;
		default:
			wp_die( esc_html__( 'Unknown action.', 'student-portal' ), '', array( 'response' => 400 ) );
	}
}

/**
 * Nonce field plus action marker for a student portal form.
 *
 * @param string $action Action slug.
 */
function sp_portal_nonce_field( $action ) {
	wp_nonce_field( 'sp_portal_action_' . $action, 'sp_portal_nonce' );
	echo '<input type="hidden" name="sp_action" value="' . esc_attr( $action ) . '" />';
}

/**
 * Hide an announcement for the current user and return to the page they were on.
 */
function sp_portal_action_dismiss_announcement() {
	$announcement_id = isset( $_POST['announcement_id'] ) ? (int) $_POST['announcement_id'] : 0;

	if ( $announcement_id ) {
		sp_announcement_dismiss( $announcement_id );
	}

	$redirect = wp_get_referer();
	$redirect = $redirect ? wp_validate_redirect( $redirect, false ) : false;

	// Dismissals must bounce back inside the student portal only.
	if ( ! $redirect || false === strpos( wp_parse_url( (string) $redirect, PHP_URL_PATH ), '/portal/' ) ) {
		$redirect = sp_portal_home_url( 'student' );
	}

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * AJAX handler: dismiss an announcement for the current portal user.
 * Returns JSON so the notification list can update without a reload.
 */
function sp_ajax_dismiss_announcement() {
	check_ajax_referer( 'sp_portal_action_dismiss_announcement', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( esc_html__( 'You do not have permission to do that.', 'student-portal' ), 403 );
	}

	$announcement_id = isset( $_POST['announcement_id'] ) ? (int) $_POST['announcement_id'] : 0;

	if ( $announcement_id ) {
		sp_announcement_dismiss( $announcement_id );
	}

	wp_send_json_success( array( 'id' => $announcement_id ) );
}
add_action( 'wp_ajax_sp_dismiss_announcement', 'sp_ajax_dismiss_announcement' );

/**
 * AJAX handler: record that the current portal user has seen the announcements.
 *
 * The bell uses this marker to know whether an announcement is new, so opening
 * the dropdown has to clear it on the server, not only in the browser.
 */
function sp_ajax_mark_announcements_seen() {
	check_ajax_referer( 'sp_portal_action_dismiss_announcement', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( esc_html__( 'You do not have permission to do that.', 'student-portal' ), 403 );
	}

	$announcement_id = isset( $_POST['announcement_id'] ) ? (int) $_POST['announcement_id'] : 0;

	sp_announcement_mark_seen( $announcement_id );

	wp_send_json_success( array( 'id' => $announcement_id ) );
}
add_action( 'wp_ajax_sp_mark_announcements_seen', 'sp_ajax_mark_announcements_seen' );

/**
 * Store a photo a student picked on their own profile.
 *
 * Stricter than the wp-admin version: every student can reach this form, so the
 * file is size-capped first and then rejected outright unless WordPress can read
 * it back as a real image, which throws away anything that merely claims an
 * image mime type.
 *
 * @return int|WP_Error Attachment ID, 0 when no file was submitted.
 */
function sp_portal_upload_profile_photo() {
	if ( empty( $_FILES['photo']['name'] ) || '' === (string) $_FILES['photo']['name'] ) {
		return 0;
	}

	if ( ! empty( $_FILES['photo']['error'] ) ) {
		return new WP_Error(
			'sp_photo_upload',
			esc_html__( 'That photo could not be uploaded. Please try a smaller file.', 'student-portal' )
		);
	}

	if ( ! empty( $_FILES['photo']['size'] ) && (int) $_FILES['photo']['size'] > 3 * MB_IN_BYTES ) {
		return new WP_Error(
			'sp_photo_too_large',
			esc_html__( 'Please choose a photo under 3 MB.', 'student-portal' )
		);
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$attachment_id = media_handle_upload( 'photo', 0 );

	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	$meta = wp_get_attachment_metadata( $attachment_id );

	if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		wp_delete_attachment( $attachment_id, true );

		return new WP_Error(
			'sp_photo_not_image',
			esc_html__( 'That file is not an image. Please choose a JPG or PNG photo.', 'student-portal' )
		);
	}

	return (int) $attachment_id;
}

/**
 * Save the contact details a student may edit themselves.
 *
 * Email, username, enrolment ID and status stay read-only: they key the
 * certificate record and the institute roster, so a student fixing a typo must
 * not be able to re-point them.
 */
function sp_portal_action_save_profile() {
	global $wpdb;

	$profile = sp_get_profile_by_user_id( get_current_user_id() );

	if ( ! $profile ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	$full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
	$phone     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$dob_raw   = isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '';

	// A cleared name would break the roster, so an empty submit keeps the old one.
	$full_name = '' !== $full_name ? $full_name : (string) $profile->full_name;

	$phone = substr( trim( (string) preg_replace( '/[^0-9+()\-\s]/', '', $phone ) ), 0, 20 );

	// Unlike the name, a cleared father's name or address is a legitimate
	// edit, so an empty value clears the column instead of keeping the old one.
	$address     = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
	$father      = isset( $_POST['father_name'] ) ? sanitize_text_field( wp_unslash( $_POST['father_name'] ) ) : '';
	$clear_photo = ! empty( $_POST['clear_photo'] );
	$sp_upload   = sp_portal_upload_profile_photo();
	$sp_errors   = array();

	// The field is capped at 100 characters, so pasted overflow is trimmed
	// rather than stored as a line the card cannot print.
	$address = trim( $address );
	$address = function_exists( 'mb_substr' ) ? mb_substr( $address, 0, 100 ) : substr( $address, 0, 100 );

	if ( is_wp_error( $sp_upload ) ) {
		$sp_errors[] = $sp_upload->get_error_message();
	}

	$dob = ( ! empty( $profile->dob ) && '0000-00-00' !== $profile->dob ) ? (string) $profile->dob : '';

	// A <input type="date"> posts Y-m-d, but older browsers post a local format
	// and createFromFormat silently rolls impossible dates over (2024-02-31
	// becomes 2024-03-02), so the round trip has to be checked explicitly.
	$dob_error = false;

	if ( '' !== $dob_raw ) {
		$parsed = date_create_immutable_from_format( '!Y-m-d', $dob_raw );

		if ( $parsed && $dob_raw === $parsed->format( 'Y-m-d' ) && $parsed->getTimestamp() <= time() ) {
			$dob = $parsed->format( 'Y-m-d' );
		} else {
			$dob_error = true;
		}
	}

	$sp_data   = array(
		'full_name'   => $full_name,
		'phone'       => $phone,
		'dob'         => '' !== $dob ? $dob : null,
		'father_name' => '' !== $father ? $father : null,
		'address'     => '' !== $address ? $address : null,
	);
	$sp_format = array( '%s', '%s', '%s', '%s', '%s' );

	/*
	 * Three mutually exclusive photo outcomes: a new file replaces the record,
	 * an explicit removal clears it, and a failed upload leaves the stored
	 * photo alone so the rest of the form still saves.
	 */
	if ( ! is_wp_error( $sp_upload ) && $sp_upload ) {
		$sp_data['photo_id'] = (int) $sp_upload;
		$sp_format[]         = '%d';
	} elseif ( $clear_photo ) {
		$sp_data['photo_id'] = null;
		$sp_format[]         = '%d';
	}

	$wpdb->update(
		sp_table( 'student_profiles' ),
		$sp_data,
		array( 'id' => (int) $profile->id ),
		$sp_format,
		array( '%d' )
	);

	sp_log_activity(
		'profile.updated',
		'student_profile',
		(int) $profile->id,
		'Student updated their own contact details.'
	);

	if ( $dob_error ) {
		$sp_errors[] = 'That date of birth could not be read, so the stored value was kept.';
	}

	if ( $sp_errors ) {
		// The other fields did save, so say so instead of implying it all failed.
		$url = add_query_arg(
			'sp_notice_error',
			rawurlencode( implode( ' ', $sp_errors ) . ' Your other details were saved.' ),
			sp_portal_url( 'profile' )
		);
	} else {
		$url = add_query_arg(
			'sp_notice',
			rawurlencode( 'Your profile details have been saved.' ),
			sp_portal_url( 'profile' )
		);
	}

	wp_safe_redirect( $url );
	exit;
}

/**
 * The assignment a portal POST refers to, and the profile posting it.
 *
 * Guards every exam action in one place: the assignment must be visible to
 * students (published or closed) and the poster must be enrolled in its class.
 *
 * @return array{0: object, 1: object} Assignment row and student profile row.
 */
function sp_portal_assignment_context() {
	$assignment_id = isset( $_POST['assignment_id'] ) ? (int) $_POST['assignment_id'] : 0;
	$assignment    = $assignment_id > 0 ? sp_get_assignment( $assignment_id ) : null;
	$profile       = sp_get_profile_by_user_id( get_current_user_id() );

	if ( ! $profile ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	if ( ! $assignment || ! in_array( $assignment->status, array( 'published', 'closed' ), true ) ) {
		wp_die( esc_html__( 'That task is no longer available.', 'student-portal' ), '', array( 'response' => 404 ) );
	}

	if ( ! sp_student_can_access_class( $profile->id, (int) $assignment->class_id ) ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	return array( $assignment, $profile );
}

/**
 * The task page URL, ready for a notice argument.
 *
 * @param int    $assignment_id Assignment id.
 * @param string $notice        Message for sp_notice (success) or nothing.
 * @param string $error         Message for sp_notice_error (failure) or nothing.
 * @return string
 */
function sp_portal_task_url( $assignment_id, $notice = '', $error = '' ) {
	$url = sp_portal_url( 'tasks/' . (int) $assignment_id );

	if ( '' !== $error ) {
		return add_query_arg( 'sp_notice_error', rawurlencode( $error ), $url );
	}

	if ( '' !== $notice ) {
		return add_query_arg( 'sp_notice', rawurlencode( $notice ), $url );
	}

	return $url;
}

/**
 * Start (or resume) an exam attempt from the task page.
 */
function sp_portal_action_start_assignment() {
	list( $assignment, $profile ) = sp_portal_assignment_context();

	$result = sp_start_assignment_attempt( (int) $assignment->id, (int) $profile->id );

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( sp_portal_task_url( $assignment->id, '', $result->get_error_message() ) );
		exit;
	}

	wp_safe_redirect(
		sp_portal_task_url(
			$assignment->id,
			'The clock is running. Stay on this page and submit when you are finished.'
		)
	);
	exit;
}

/**
 * Submit and grade an exam from the task page.
 *
 * The countdown auto-submits at zero, so a legitimate POST always lands
 * slightly after the deadline: answers are accepted for a short grace window
 * and thrown away after that, exactly as an expired attempt would be.
 */
function sp_portal_action_submit_assignment() {
	list( $assignment, $profile ) = sp_portal_assignment_context();

	$answers = array();

	if ( isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ) {
		foreach ( wp_unslash( $_POST['answers'] ) as $sp_qid => $sp_value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- sanitised per value below.
			$answers[ (int) $sp_qid ] = sanitize_text_field( $sp_value );
		}
	}

	$row = sp_get_student_assignment( (int) $assignment->id, (int) $profile->id );

	if ( $row && 'in_progress' === $row->status && sp_assignment_submit_is_late( $row, $assignment ) ) {
		$answers = array();
	}

	$result = sp_submit_assignment_attempt( (int) $assignment->id, (int) $profile->id, $answers );

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( sp_portal_task_url( $assignment->id, '', $result->get_error_message() ) );
		exit;
	}

	wp_safe_redirect(
		sp_portal_task_url(
			$assignment->id,
			sprintf( 'Submitted. You scored %d out of %d.', (int) $result->score, (int) $result->total )
		)
	);
	exit;
}

/**
 * The browser is leaving a running exam — mark the attempt failed.
 *
 * Sent by navigator.sendBeacon() from pagehide. Failing an attempt only acts
 * on `in_progress`, so a beacon that races a real submit is a no-op, and this
 * deliberately produces no page: the sender never reads the response.
 */
function sp_portal_action_leave_beacon() {
	list( $assignment, $profile ) = sp_portal_assignment_context();

	sp_fail_assignment_attempt( (int) $assignment->id, (int) $profile->id );

	status_header( 204 );
	exit;
}
