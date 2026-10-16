<?php
/**
 * Course material: authoring, upload handling and the gated download route.
 *
 * Files are stored in the normal uploads tree but named with 32 random hex
 * characters, so the on-disk path is not guessable or enumerable. Access is
 * still enforced in PHP: every read goes through sp_stream_material(), which
 * checks the requester's relationship to the class before touching disk.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the admin file/media helpers on demand.
 *
 * They are deliberately not required at file scope: this plugin is loaded on
 * every frontend request, and pulling wp-admin includes in that early would be
 * both wasteful and unsafe.
 */
function sp_load_upload_helpers() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
}

/**
 * Extensions the portal will accept, mapped to the MIME types allowed for each.
 *
 * @return array
 */
function sp_allowed_upload_types() {
	return array(
		'pdf'  => array( 'application/pdf' ),
		'doc'  => array( 'application/msword', 'application/octet-stream' ),
		'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' ),
		'ppt'  => array( 'application/vnd.ms-powerpoint', 'application/octet-stream' ),
		'pptx' => array( 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip' ),
		'xls'  => array( 'application/vnd.ms-excel', 'application/octet-stream' ),
		'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip' ),
		'txt'  => array( 'text/plain' ),
		'csv'  => array( 'text/plain', 'text/csv', 'application/csv' ),
		'png'  => array( 'image/png' ),
		'jpg'  => array( 'image/jpeg' ),
		'jpeg' => array( 'image/jpeg' ),
		'webp' => array( 'image/webp' ),
	);
}

/**
 * Extensions that are never accepted, whatever the MIME type says.
 *
 * @return string[]
 */
function sp_forbidden_upload_extensions() {
	return array( 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phps', 'pht', 'html', 'htm', 'shtml', 'svg', 'js', 'json', 'htaccess', 'user.ini' );
}

/**
 * Whether an extension/MIME pair passes the allowlist.
 *
 * @param string $extension Lowercase extension, no dot.
 * @param string $mime_type Reported MIME type.
 * @return true|WP_Error
 */
function sp_validate_upload_type( $extension, $mime_type ) {
	$extension = strtolower( trim( (string) $extension ) );
	$mime_type = strtolower( trim( (string) $mime_type ) );

	if ( in_array( $extension, sp_forbidden_upload_extensions(), true ) ) {
		return new WP_Error(
			'sp_forbidden_type',
			sprintf( 'Files of type ".%s" cannot be uploaded.', $extension )
		);
	}

	$allowed = sp_allowed_upload_types();

	if ( ! isset( $allowed[ $extension ] ) ) {
		return new WP_Error(
			'sp_bad_extension',
			sprintf( '".%s" files are not supported. Allowed: %s.', $extension, implode( ', ', array_keys( $allowed ) ) )
		);
	}

	// Office files are frequently re-labelled as zip or octet-stream by the
	// browser, so the MIME list for each extension is deliberately permissive —
	// but never open-ended.
	$permitted_mimes = array_merge( $allowed[ $extension ], array( 'application/octet-stream', 'application/zip' ) );

	if ( ! in_array( $mime_type, $permitted_mimes, true ) ) {
		return new WP_Error(
			'sp_bad_mime',
			sprintf( 'The file reports MIME type "%s", which is not accepted for .%s files.', $mime_type, $extension )
		);
	}

	return true;
}

/**
 * Human label for the filename used in Content-Disposition.
 *
 * @param string $filename Raw filename.
 * @return string
 */
function sp_safe_content_filename( $filename ) {
	$filename = basename( (string) $filename );
	$filename = preg_replace( '/[^A-Za-z0-9._ -]/', '_', $filename );
	$filename = trim( $filename );

	if ( '' === $filename ) {
		$filename = 'download';
	}

	return substr( $filename, 0, 180 );
}

/**
 * MIME types the gated route may serve with Content-Disposition: inline.
 *
 * @return string[]
 */
function sp_inline_viewable_mimes() {
	return array( 'application/pdf', 'text/plain', 'text/csv' );
}

/**
 * Whether a stored MIME type can be rendered inside the page rather than
 * downloaded: PDFs, images and plain text are displayed by the browser
 * itself. Office formats are not on that list — no browser renders them,
 * so they keep the attachment disposition for the rare direct open.
 *
 * @param string $mime Stored MIME type.
 * @return bool
 */
function sp_mime_is_inline_viewable( $mime ) {
	$mime = strtolower( trim( (string) $mime ) );

	if ( in_array( $mime, sp_inline_viewable_mimes(), true ) ) {
		return true;
	}

	return 0 === strpos( $mime, 'image/' );
}

/**
 * Extension of a file material, read from the stored filename and falling
 * back to the on-disk path (rows imported before a filename existed).
 *
 * @param object $material sp_materials row.
 * @return string Lowercase extension, no dot.
 */
function sp_material_extension( $material ) {
	$source = '';

	if ( ! empty( $material->original_filename ) ) {
		$source = (string) $material->original_filename;
	} elseif ( ! empty( $material->file_path ) ) {
		$source = (string) $material->file_path;
	}

	return strtolower( (string) pathinfo( $source, PATHINFO_EXTENSION ) );
}

/**
 * How a file material should be shown on the student portal.
 *
 * The portal no longer offers downloads, so every file is embedded:
 * 'pdf' and 'text' through an inline iframe, 'image' through an <img>,
 * 'docx' converted to HTML in the browser, and 'none' for formats no
 * browser can display (those get an explanatory card instead).
 *
 * @param object|null $material sp_materials row.
 * @return string One of: pdf, image, text, docx, none.
 */
function sp_material_embed_kind( $material ) {
	if ( ! $material || 'file' !== $material->kind ) {
		return 'none';
	}

	$ext  = sp_material_extension( $material );
	$mime = strtolower( trim( (string) $material->mime_type ) );

	if ( 'pdf' === $ext || 'application/pdf' === $mime ) {
		return 'pdf';
	}

	if ( in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif', 'webp' ), true ) || 0 === strpos( $mime, 'image/' ) ) {
		return 'image';
	}

	if ( in_array( $ext, array( 'txt', 'csv' ), true ) || in_array( $mime, sp_inline_viewable_mimes(), true ) ) {
		return 'text';
	}

	if ( 'docx' === $ext ) {
		return 'docx';
	}

	return 'none';
}

/**
 * Whether the browser-side .docx preview can run.
 *
 * The preview reuses the converter bundle shipped by the Mammoth .docx
 * converter plugin. When that plugin is missing the portal falls back to
 * the "preview not available" card instead of breaking.
 *
 * @return bool
 */
function sp_docx_preview_available() {
	return file_exists( WP_PLUGIN_DIR . '/mammoth-docx-converter/mammoth-editor.js' );
}

/**
 * Absolute path to the private materials directory, created with a deny rule.
 *
 * Course material must NOT live in the shared uploads tree as attachment posts:
 * attachments are enumerable through /wp-json/wp/v2/media, which publishes the
 * filenames and defeats the point of the random 32-hex names. Files are kept in
 * uploads/sp-private/ instead, protected by its own .htaccess and served only
 * through the gated streaming route.
 *
 * @return string|WP_Error Absolute path with a trailing separator.
 */
function sp_material_private_dir() {
	$base = WP_CONTENT_DIR . '/uploads/sp-private';

	if ( ! wp_mkdir_p( $base ) ) {
		return new WP_Error( 'sp_no_private_dir', 'The private materials folder could not be created.' );
	}

	/*
	 * Deny web access on the servers that honour it (Apache, LiteSpeed). The
	 * streaming route is the only supported way to read these files. nginx needs
	 * the matching rule added to its server config at deploy time.
	 */
	$htaccess = $base . '/.htaccess';

	if ( ! file_exists( $htaccess ) ) {
		file_put_contents( $htaccess, "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	$index = $base . '/index.html';

	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	return trailingslashit( $base );
}

/**
 * Remove the private materials directory and everything inside it.
 *
 * Only called from uninstall.php, and only when the site owner opted in.
 */
function sp_delete_private_material_dir() {
	$base = WP_CONTENT_DIR . '/uploads/sp-private';

	if ( ! is_dir( $base ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged
		} else {
			@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged
		}
	}

	@rmdir( $base ); // phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged
}

/**
 * Resolve a material to an absolute path inside the private folder.
 *
 * Prefers sp_materials.file_path. Falls back to the old attachment-based
 * location so anything stored before schema 1.4 keeps working.
 *
 * @param object|int $material Material row, or an attachment id for legacy rows.
 * @return string|WP_Error Absolute path.
 */
function sp_material_file_path( $material ) {
	$relative = '';

	if ( is_object( $material ) ) {
		if ( ! empty( $material->file_path ) ) {
			$relative = (string) $material->file_path;
		} elseif ( ! empty( $material->attachment_id ) ) {
			// Legacy row stored before 1.4: resolve via the attachment meta.
			return sp_material_legacy_file_path( (int) $material->attachment_id );
		}
	} else {
		return sp_material_legacy_file_path( (int) $material );
	}

	if ( ! $relative ) {
		return new WP_Error( 'sp_no_file', 'That material has no stored file.' );
	}

	$base = sp_material_private_dir();

	if ( is_wp_error( $base ) ) {
		return $base;
	}

	$uploads_base = realpath( WP_CONTENT_DIR . '/uploads' );

	if ( ! $uploads_base ) {
		return new WP_Error( 'sp_no_uploads_dir', 'The uploads directory is missing.' );
	}

	$absolute = realpath( $uploads_base . '/' . ltrim( $relative, '/' ) );

	// Belt and braces: never stream anything outside the uploads tree.
	if ( ! $absolute || 0 !== strpos( $absolute, $uploads_base . DIRECTORY_SEPARATOR ) ) {
		return new WP_Error( 'sp_path_escape', 'That file could not be resolved safely.' );
	}

	if ( ! is_file( $absolute ) || ! is_readable( $absolute ) ) {
		return new WP_Error( 'sp_file_missing', 'That file is no longer on disk.' );
	}

	return $absolute;
}

/**
 * Resolve a legacy attachment id to an absolute path.
 *
 * @param int $attachment_id Attachment post id.
 * @return string|WP_Error Absolute path.
 */
function sp_material_legacy_file_path( $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	$attachment    = get_post( $attachment_id );

	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return new WP_Error( 'sp_not_attachment', 'That file could not be found.' );
	}

	$relative = get_post_meta( $attachment_id, '_wp_attached_file', true );

	if ( ! $relative ) {
		return new WP_Error( 'sp_no_file_meta', 'That file has no stored location.' );
	}

	$uploads_base = realpath( WP_CONTENT_DIR . '/uploads' );

	if ( ! $uploads_base ) {
		return new WP_Error( 'sp_no_uploads_dir', 'The uploads directory is missing.' );
	}

	$absolute = realpath( $uploads_base . '/' . ltrim( $relative, '/' ) );

	// Belt and braces: never stream anything outside the uploads tree.
	if ( ! $absolute || 0 !== strpos( $absolute, $uploads_base . DIRECTORY_SEPARATOR ) ) {
		return new WP_Error( 'sp_path_escape', 'That file could not be resolved safely.' );
	}

	if ( ! is_file( $absolute ) || ! is_readable( $absolute ) ) {
		return new WP_Error( 'sp_file_missing', 'That file is no longer on disk.' );
	}

	return $absolute;
}

/**
 * Store an uploaded file with a random name and register it as an attachment.
 *
 * @param array  $file        One entry from $_FILES.
 * @param string $description Optional description used for the attachment title.
 * @return array|WP_Error {
 *     @type string $file_path
 *     @type string $original_filename
 *     @type string $mime_type
 *     @type int    $size
 * }
 */
function sp_store_uploaded_file( $file, $description = '' ) {
	sp_load_upload_helpers();

	if ( ! isset( $file['tmp_name'], $file['name'], $file['error'] ) ) {
		return new WP_Error( 'sp_bad_upload', 'No file was received.' );
	}

	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return new WP_Error( 'sp_upload_error', 'The upload failed (error code ' . (int) $file['error'] . ').' );
	}

	if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'sp_not_uploaded_file', 'The upload could not be verified.' );
	}

	$original = sanitize_file_name( $file['name'] );
	$extension = strtolower( (string) pathinfo( $original, PATHINFO_EXTENSION ) );

	$type_check = sp_validate_upload_type( $extension, (string) $file['type'] );

	if ( is_wp_error( $type_check ) ) {
		return $type_check;
	}

	$max = sp_max_upload_bytes();

	if ( $max > 0 && (int) $file['size'] > $max ) {
		return new WP_Error(
			'sp_file_too_large',
			sprintf( 'That file is %s MB. The limit is %d MB.', size_format( (int) $file['size'] / MB_IN_BYTES, 1 ), (int) round( $max / MB_IN_BYTES ) )
		);
	}

	$stored_name = bin2hex( random_bytes( 16 ) ) . '.' . $extension;

	$base = sp_material_private_dir();

	if ( is_wp_error( $base ) ) {
		return $base;
	}

	// Year/month subfolders keep any single directory small.
	$subdir = current_time( 'Y/m' );
	$dir    = $base . $subdir;

	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'sp_no_private_dir', 'The private materials folder could not be created.' );
	}

	$target = $dir . '/' . $stored_name;

	/*
	 * Move the upload ourselves rather than through wp_handle_upload(). That
	 * function writes into the shared uploads tree and registers an attachment
	 * post, which is exactly what we are avoiding.
	 */
	if ( is_uploaded_file( $file['tmp_name'] ) ) {
		$moved = move_uploaded_file( $file['tmp_name'], $target );
	} else {
		// Not a genuine HTTP upload (WP-CLI, tests, internal calls).
		$moved = @rename( $file['tmp_name'], $target );
	}

	if ( ! $moved ) {
		return new WP_Error( 'sp_move_failed', 'The file could not be moved into the private folder.' );
	}

	// Assert the random-name guarantee held. If a future refactor drops the
	// naming, a predictable filename must never be accepted.
	if ( ! preg_match( '/^[a-f0-9]{32}\.[a-z0-9]+$/', basename( $target ) ) ) {
		@unlink( $target ); // phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged
		return new WP_Error( 'sp_unexpected_filename', 'The file could not be stored with a safe name and was discarded.' );
	}

	$filetype = wp_check_filetype( $target, null );

	// Relative to wp-content/uploads, which is what sp_material_file_path() expects.
	$relative = 'sp-private/' . $subdir . '/' . $stored_name;

	@chmod( $target, 0640 ); // phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged

	return array(
		'file_path'          => $relative,
		'original_filename'  => $original,
		'mime_type'          => $filetype['type'],
		'size'               => (int) $file['size'],
	);
}

/**
 * Create or update a material record.
 *
 * @param array $args id, class_id, title, kind, content, status, attachment info.
 * @return int|WP_Error Material id.
 */
function sp_save_material( $args ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'id'                 => 0,
			'class_id'        => 0,
			'title'              => '',
			'kind'               => 'note',
			'content'            => '',
			'status'             => 'draft',
			'file_path'          => '',
			'original_filename'  => '',
			'mime_type'          => '',
			'file_size'          => 0,
		)
	);

	$class_id = (int) $args['class_id'];
	$title       = sanitize_text_field( $args['title'] );
	$kind        = in_array( $args['kind'], array( 'note', 'file' ), true ) ? $args['kind'] : 'note';
	$status      = in_array( $args['status'], array( 'draft', 'published' ), true ) ? $args['status'] : 'draft';

	if ( ! sp_get_class( $class_id ) ) {
		return new WP_Error( 'sp_no_class', 'That class no longer exists.' );
	}

	if ( '' === $title ) {
		return new WP_Error( 'sp_no_title', 'Enter a title.' );
	}

	if ( 'note' === $kind ) {
		$content = wp_kses_post( $args['content'] );

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return new WP_Error( 'sp_empty_note', 'Write some content before saving.' );
		}

		$file_path         = '';
		$original_filename = '';
		$mime_type         = '';
		$file_size         = 0;
	} else {
		$file_path = sanitize_text_field( $args['file_path'] );

		if ( '' === $file_path ) {
			return new WP_Error( 'sp_no_file', 'Attach a file before saving.' );
		}

		$content           = null;
		$original_filename = sanitize_text_field( $args['original_filename'] );
		$mime_type         = sanitize_text_field( $args['mime_type'] );
		$file_size         = (int) $args['file_size'];
	}

	$data = array(
		'class_id'       => $class_id,
		'title'             => $title,
		'kind'              => $kind,
		'content'           => $content,
		'file_path'         => $file_path ? $file_path : null,
		'original_filename' => $original_filename ? $original_filename : null,
		'mime_type'         => $mime_type ? $mime_type : null,
		'file_size'         => $file_size > 0 ? $file_size : null,
		'status'            => $status,
	);

	$formats          = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );
	$material_id      = (int) $args['id'];
	$just_published   = false;

	if ( $material_id > 0 ) {
		$existing = sp_get_material( $material_id );

		if ( ! $existing ) {
			return new WP_Error( 'sp_no_material', 'That material no longer exists.' );
		}

		// Notify only on the draft -> published transition, so re-saving a
		// published note never re-spams the class.
		$just_published = ( 'published' === $status && 'published' !== $existing->status );

		if ( $just_published ) {
			$data['published_at'] = current_time( 'mysql' );
		}

		$wpdb->update( sp_table( 'materials' ), $data, array( 'id' => $material_id ), $formats, array( '%d' ) );
	} else {
		$data['author_user_id'] = get_current_user_id();

		if ( 'published' === $status ) {
			$data['published_at'] = current_time( 'mysql' );
			$just_published       = true;
		}

		$wpdb->insert(
			sp_table( 'materials' ),
			$data,
			array_merge( $formats, array( '%d', '%s' ) )
		);

		$material_id = (int) $wpdb->insert_id;
	}

	if ( $just_published ) {
		$material = sp_get_material( $material_id );

		if ( $material ) {
			sp_log_activity( 'material.published', 'material', $material_id, $title );

			sp_notify_material_published( $material );
		}
	} else {
		sp_log_activity( 'material.saved', 'material', $material_id, $title );
	}

	return $material_id;
}

/**
 * Email every active student in the class about a newly published material.
 *
 * @param object $material sp_materials row.
 * @return int Number of students notified.
 */
function sp_notify_material_published( $material ) {
	if ( ! sp_setting( 'notify_on_material' ) ) {
		return 0;
	}

	$profiles = sp_get_enrollments_for_class( $material->class_id, array( 'active' ), 0 );
	$sent     = 0;

	foreach ( $profiles as $row ) {
		$profile = sp_get_profile_by_id( $row->profile_id );

		if ( ! $profile ) {
			continue;
		}

		if ( sp_email_material_notice( $profile->id, $material ) ) {
			++$sent;
		}
	}

	return $sent;
}

/**
 * Delete a material and, for files, the underlying attachment.
 *
 * @param int $material_id Material id.
 * @return true|WP_Error
 */
function sp_delete_material( $material_id ) {
	global $wpdb;

	sp_load_upload_helpers();

	$material = sp_get_material( $material_id );

	if ( ! $material ) {
		return new WP_Error( 'sp_no_material', 'That material no longer exists.' );
	}

	if ( $material->file_path ) {
		$path = sp_material_file_path( $material );

		// A missing or unresolvable file must not stop the row being deleted.
		if ( ! is_wp_error( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged
		}
	} elseif ( $material->attachment_id ) {
		// Legacy row created before schema 1.4.
		sp_load_upload_helpers();
		wp_delete_attachment( (int) $material->attachment_id, true );
	}

	$wpdb->delete( sp_table( 'materials' ), array( 'id' => (int) $material_id ), array( '%d' ) );

	sp_log_activity( 'material.deleted', 'material', (int) $material_id, $material->title );

	return true;
}

/**
 * Whether the given user may read a material.
 *
 * @param object   $material sp_materials row.
 * @param WP_User  $user     Viewer.
 * @param string   $mode     'read' for the page, 'download' for the file.
 * @return bool
 */
function sp_user_can_view_material( $material, $user = null, $mode = 'read' ) {
	if ( ! $material ) {
		return false;
	}

	if ( ! $user instanceof WP_User ) {
		$user = wp_get_current_user();
	}

	if ( ! $user->exists() ) {
		return false;
	}

	if ( user_can( $user, 'manage_certificates' ) ) {
		return true;
	}

	if ( (int) $material->author_user_id === (int) $user->ID ) {
		return true;
	}

	$teacher = sp_get_teacher_by_user_id( $user->ID );

	if ( $teacher && sp_teacher_can_access_class( $teacher->id, $material->class_id ) ) {
		return true;
	}

	// Students only ever see published material.
	if ( 'published' !== $material->status ) {
		return false;
	}

	$profile = sp_get_profile_by_user_id( $user->ID );

	if ( ! $profile ) {
		return false;
	}

	return sp_student_can_access_class( $profile->id, $material->class_id );
}

/**
 * Entry point for ?sp_material=<id>. Streams the file and exits.
 */
function sp_stream_material() {
	$material_id = isset( $_GET['sp_material'] ) ? (int) $_GET['sp_material'] : 0;

	if ( $material_id <= 0 ) {
		wp_die( esc_html__( 'That file could not be found.', 'student-portal' ), '', array( 'response' => 404 ) );
	}

	$material = sp_get_material( $material_id );

	if ( ! $material ) {
		wp_die( esc_html__( 'That file could not be found.', 'student-portal' ), '', array( 'response' => 404 ) );
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( sp_portal_home_url( 'student' ) );
		exit;
	}

	if ( 'file' !== $material->kind || ( ! $material->file_path && ! $material->attachment_id ) ) {
		wp_die( esc_html__( 'That file could not be found.', 'student-portal' ), '', array( 'response' => 404 ) );
	}

	if ( ! sp_user_can_view_material( $material, wp_get_current_user(), 'download' ) ) {
		wp_die( esc_html__( 'You do not have access to that file.', 'student-portal' ), '', array( 'response' => 403 ) );
	}

	$path = sp_material_file_path( $material );

	if ( is_wp_error( $path ) ) {
		wp_die( esc_html( $path->get_error_message() ), '', array( 'response' => 404 ) );
	}

	$mime      = $material->mime_type ? $material->mime_type : 'application/octet-stream';
	$filename  = sp_safe_content_filename( $material->original_filename ? $material->original_filename : basename( $path ) );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display mode.
	$inline      = isset( $_GET['inline'] ) && sp_mime_is_inline_viewable( $mime );
	$disposition = $inline ? 'inline' : 'attachment';

	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		// Only this site's own pages may frame a preview of the file.
		header( "Content-Security-Policy: frame-ancestors 'self'" );
	}

	// Stream in chunks so a large PDF does not exhaust PHP's memory limit.
	$handle = fopen( $path, 'rb' );

	if ( ! $handle ) {
		wp_die( esc_html__( 'That file could not be read.', 'student-portal' ), '', array( 'response' => 500 ) );
	}

	while ( ! feof( $handle ) ) {
		echo fread( $handle, 8192 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary file stream.
		flush();
	}

	fclose( $handle );
	exit;
}
add_action( 'template_redirect', 'sp_maybe_stream_material', 5 );

/**
 * Redirect ?sp_material requests to the streaming handler.
 */
function sp_maybe_stream_material() {
	if ( ! isset( $_GET['sp_material'] ) ) {
		return;
	}

	sp_stream_material();
}

/**
 * Formats the stored byte size for display.
 *
 * @param mixed $size Bytes.
 * @return string
 */
function sp_format_file_size( $size ) {
	if ( ! $size ) {
		return '';
	}

	return size_format( (int) $size );
}