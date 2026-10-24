<?php
/**
 * Create or edit a material.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_material_id = (int) get_query_var( 'sp_material_id' );
$sp_material    = $sp_material_id ? sp_get_material( $sp_material_id ) : null;
$sp_class_id    = $sp_material ? (int) $sp_material->class_id : 0;

if ( ! $sp_class_id && isset( $_GET['class'] ) ) {
	$sp_class_id = (int) $_GET['class']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection.
}

if ( $sp_material_id && ! $sp_material ) {
	?>
	<div class="sp-notice sp-notice-error">That material no longer exists.</div>
	<?php
	return;
}

/*
 * Only a class that is already known gets checked here: an existing
 * material's class, or a ?class= picked from the list screen. A brand-new
 * material has no class yet — the form below asks for one and the save
 * handler validates the posted class against the signed-in teacher.
 */
$sp_access = $sp_class_id ? sp_require_assigned_teacher( $sp_class_id ) : null;

if ( is_wp_error( $sp_access ) ) {
	?>
	<div class="sp-notice sp-notice-error"><?php echo esc_html( $sp_access->get_error_message() ); ?></div>
	<?php
	return;
}

$sp_teacher = $teacher ? $teacher : sp_get_teacher_by_user_id( get_current_user_id() );
$sp_classes = $sp_teacher ? sp_get_classes( array( 'teacher_id' => $sp_teacher->id, 'status' => 'active' ) ) : array();

// Only classes this teacher is actually assigned to may be chosen.
$sp_allowed = array();

foreach ( $sp_classes as $sp_candidate ) {
	if ( sp_teacher_can_access_class( $sp_teacher->id, (int) $sp_candidate->id ) ) {
		$sp_allowed[] = $sp_candidate;
	}
}

// An administrator signed in without a teacher row picks from every class.
if ( ! $sp_allowed && current_user_can( 'manage_options' ) ) {
	$sp_allowed = sp_get_classes( array( 'status' => 'active' ) );
}

$sp_is_edit   = (bool) $sp_material;
$sp_kind      = ( $sp_material && 'file' === $sp_material->kind ) ? 'file' : 'note';
$sp_status    = ( $sp_material && 'published' === $sp_material->status ) ? 'published' : 'draft';
$sp_max_mb    = (int) sp_setting( 'max_upload_mb', 20 );
$sp_list_url  = sp_teacher_url( 'materials' );
$sp_class_obj = ( $sp_is_edit && $sp_class_id ) ? sp_get_class( $sp_class_id ) : null;

$sp_svg_open = '<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
$sp_icon     = static function ( $paths, $size = 20 ) use ( $sp_svg_open ) {
	return sprintf( $sp_svg_open, $size ) . $paths . '</svg>';
};

$sp_ico_back   = $sp_icon( '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>', 16 );
$sp_ico_note   = $sp_icon( '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>' );
$sp_ico_file   = $sp_icon( '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>' );
$sp_ico_lock   = $sp_icon( '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>' );
$sp_ico_eye    = $sp_icon( '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>' );
$sp_ico_upload = $sp_icon( '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>', 24 );
$sp_ico_ext    = $sp_icon( '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="m15 3 6 0 0 6"/><path d="M10 14 21 3"/>', 16 );
?>
<div class="sp-me" data-sp-material-form-root>

	<div class="sp-page-head sp-me-head">
		<a class="sp-me-back" href="<?php echo esc_url( $sp_list_url ); ?>">
			<?php echo $sp_ico_back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span>All materials</span>
		</a>

		<div class="sp-me-titlerow">
			<h1><?php echo $sp_is_edit ? 'Edit material' : 'Add material'; ?></h1>
			<?php if ( $sp_is_edit ) : ?>
				<span class="sp-badge sp-badge-muted sp-me-badge"><?php echo 'file' === $sp_kind ? 'File' : 'Note'; ?></span>
				<?php if ( 'published' === $sp_status ) : ?>
					<span class="sp-badge sp-badge-good sp-me-badge"><span class="sp-me-badge__dot"></span>Published</span>
				<?php else : ?>
					<span class="sp-badge sp-badge-muted sp-me-badge"><span class="sp-me-badge__dot"></span>Draft</span>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<p class="sp-page-sub">
			Notes are written in the editor. Files are uploaded and shared with every student enrolled in the class.
		</p>
	</div>

	<form class="sp-me-form" method="post" enctype="multipart/form-data"
		action="<?php echo esc_url( $sp_is_edit ? sp_teacher_url( 'materials/' . (int) $sp_material->id ) : sp_teacher_url( 'materials/new' ) ); ?>" data-sp-material-form>
		<?php sp_teacher_nonce_field( 'save_material' ); ?>
		<input type="hidden" name="sp_redirect"
			value="<?php echo esc_url( $sp_is_edit ? add_query_arg( 'tab', 'materials', sp_teacher_url( 'classes/' . (int) $sp_class_id ) ) : $sp_list_url ); ?>" />

		<?php if ( $sp_is_edit ) : ?>
			<input type="hidden" name="material_id" value="<?php echo esc_attr( (int) $sp_material->id ); ?>" />
		<?php endif; ?>

		<div class="sp-me-layout">

			<div class="sp-me-main">

				<section class="sp-me-card">
					<header class="sp-me-card__head">
						<h2>Details</h2>
						<p>Choose the class and give this material a clear title.</p>
					</header>

					<div class="sp-me-card__body">
						<div class="sp-field">
							<label for="sp_m_class">Class <span class="sp-req">*</span></label>
							<select id="sp_m_class" name="class_id" required <?php disabled( $sp_is_edit ); ?>>
								<?php if ( $sp_is_edit ) : ?>
									<option value="<?php echo esc_attr( $sp_class_id ); ?>" selected>
										<?php echo esc_html( $sp_class_obj ? $sp_class_obj->title : __( 'Unknown class', 'student-portal' ) ); ?>
									</option>
								<?php else : ?>
									<option value=""><?php esc_html_e( 'Choose a class', 'student-portal' ); ?></option>
									<?php foreach ( $sp_allowed as $sp_option ) : ?>
										<option value="<?php echo esc_attr( (int) $sp_option->id ); ?>" <?php selected( $sp_class_id, (int) $sp_option->id ); ?>>
											<?php echo esc_html( $sp_option->title ); ?>
										</option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
							<?php if ( $sp_is_edit ) : ?>
								<p class="sp-help">The class can't be changed once a material has been created.</p>
							<?php elseif ( ! $sp_allowed ) : ?>
								<p class="sp-help">You are not assigned to any class yet — ask an administrator to assign you first.</p>
							<?php endif; ?>
						</div>

						<div class="sp-field">
							<label for="sp_m_title">Title <span class="sp-req">*</span></label>
							<input type="text" id="sp_m_title" name="title" required autocomplete="off"
								value="<?php echo esc_attr( $sp_is_edit ? $sp_material->title : '' ); ?>" />
						</div>
					</div>
				</section>

				<section class="sp-me-card">
					<header class="sp-me-card__head">
						<h2>Content</h2>
						<p>Write a note for students, or upload a document they can download.</p>
					</header>

					<div class="sp-me-card__body">

						<fieldset class="sp-fieldset sp-me-fieldset">
							<legend>Type</legend>
							<div class="sp-me-choices sp-kind-picker">
								<label class="sp-me-choice">
									<input type="radio" name="kind" value="note" class="sp-kind-toggle"
										<?php checked( $sp_kind, 'note' ); ?> />
									<span class="sp-me-choice__body">
										<span class="sp-me-choice__icon"><?php echo $sp_ico_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
										<span class="sp-me-choice__text">
											<span class="sp-me-choice__title">Written note</span>
											<span class="sp-me-choice__hint">Type or paste content in the editor</span>
										</span>
										<span class="sp-me-choice__check" aria-hidden="true"></span>
									</span>
								</label>

								<label class="sp-me-choice">
									<input type="radio" name="kind" value="file" class="sp-kind-toggle"
										<?php checked( $sp_kind, 'file' ); ?> />
									<span class="sp-me-choice__body">
										<span class="sp-me-choice__icon"><?php echo $sp_ico_file; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
										<span class="sp-me-choice__text">
											<span class="sp-me-choice__title">File upload</span>
											<span class="sp-me-choice__hint">PDF, slides, sheets or images</span>
										</span>
										<span class="sp-me-choice__check" aria-hidden="true"></span>
									</span>
								</label>
							</div>
						</fieldset>

						<div class="sp-field sp-kind-note">
							<label for="sp_m_content">Note content</label>
							<?php
							wp_editor(
								$sp_is_edit ? $sp_material->content : '',
								'sp_m_content',
								array(
									'textarea_name' => 'content',
									'textarea_rows' => 12,
									'media_buttons' => true,
									'teeny'         => false,
								)
							);
							?>
						</div>

						<div class="sp-field sp-kind-file">
							<label for="sp_m_file">
								<?php echo ( $sp_is_edit && 'file' === $sp_material->kind ) ? 'Replace file' : 'Choose a file'; ?>
							</label>

							<?php if ( $sp_is_edit && 'file' === $sp_material->kind ) : ?>
								<p class="sp-me-current">
									<span class="sp-me-current__icon"><?php echo $sp_ico_file; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
									<span class="sp-me-current__text">
										<span class="sp-me-current__label">Current file</span>
										<span class="sp-me-current__name"><?php echo esc_html( $sp_material->original_filename ); ?></span>
									</span>
								</p>
							<?php endif; ?>

							<div class="sp-me-drop" data-sp-drop data-max-mb="<?php echo esc_attr( $sp_max_mb ); ?>">
								<span class="sp-me-drop__icon"><?php echo $sp_ico_upload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
								<span class="sp-me-drop__title"><strong>Click to upload</strong> or drag and drop</span>
								<span class="sp-me-drop__hint">
									PDF, Word, PowerPoint, Excel, TXT, CSV or images &middot; up to <?php echo esc_html( $sp_max_mb ); ?> MB
								</span>
								<input type="file" id="sp_m_file" name="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.csv,.png,.jpg,.jpeg,.webp" />
							</div>

							<div class="sp-me-picked" data-sp-picked hidden>
								<span class="sp-me-picked__icon"><?php echo $sp_ico_file; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
								<span class="sp-me-picked__text">
									<span class="sp-me-picked__name" data-sp-name></span>
									<span class="sp-me-picked__size" data-sp-size></span>
								</span>
								<button type="button" class="sp-me-picked__clear" data-sp-clear>Remove</button>
							</div>

							<p class="sp-me-error" data-sp-file-error role="alert" hidden></p>
						</div>

					</div>
				</section>
			</div>

			<aside class="sp-me-side">

				<section class="sp-me-card">
					<header class="sp-me-card__head">
						<h2>Publishing</h2>
						<p>Control who can see this material.</p>
					</header>

					<div class="sp-me-card__body">
						<fieldset class="sp-fieldset sp-me-fieldset">
							<legend class="screen-reader-text">Visibility</legend>
							<div class="sp-me-choices sp-me-choices--stack">
								<label class="sp-me-choice">
									<input type="radio" name="status" value="draft" <?php checked( $sp_status, 'draft' ); ?> />
									<span class="sp-me-choice__body">
										<span class="sp-me-choice__icon"><?php echo $sp_ico_lock; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
										<span class="sp-me-choice__text">
											<span class="sp-me-choice__title">Draft</span>
											<span class="sp-me-choice__hint">Only you and administrators can see it</span>
										</span>
										<span class="sp-me-choice__check" aria-hidden="true"></span>
									</span>
								</label>

								<label class="sp-me-choice sp-me-choice--good">
									<input type="radio" name="status" value="published" <?php checked( $sp_status, 'published' ); ?> />
									<span class="sp-me-choice__body">
										<span class="sp-me-choice__icon"><?php echo $sp_ico_eye; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
										<span class="sp-me-choice__text">
											<span class="sp-me-choice__title">Published</span>
											<span class="sp-me-choice__hint">Visible to enrolled students</span>
										</span>
										<span class="sp-me-choice__check" aria-hidden="true"></span>
									</span>
								</label>
							</div>
						</fieldset>

						<div class="sp-me-actions">
							<button type="submit" class="sp-btn sp-btn-primary sp-me-save" data-sp-save data-label="<?php echo esc_attr( $sp_is_edit ? 'Save changes' : 'Save material' ); ?>">
								<span><?php echo $sp_is_edit ? 'Save changes' : 'Save material'; ?></span>
							</button>
							<a class="sp-btn sp-btn-ghost sp-me-cancel" href="<?php echo esc_url( $sp_list_url ); ?>">Cancel</a>
						</div>
					</div>
				</section>

				<?php if ( $sp_is_edit && 'file' === $sp_material->kind ) : ?>
					<section class="sp-me-card sp-me-card--aside">
						<div class="sp-me-card__body">
							<h3 class="sp-me-aside__title">Check the upload</h3>
							<p class="sp-me-aside__text">
								Open the stored file through the gated download link to confirm it opens correctly.
							</p>
							<a class="sp-btn sp-btn-ghost sp-me-preview" target="_blank" rel="noopener"
								href="<?php echo esc_url( add_query_arg( 'inline', '1', sp_material_url( $sp_material->id ) ) ); ?>">
								<span>Preview file</span>
								<?php echo $sp_ico_ext; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
							</a>
						</div>
					</section>
				<?php endif; ?>

			</aside>
		</div>
	</form>
</div>

<script>
( function () {
	var form = document.querySelector( '[data-sp-material-form]' );
	if ( ! form ) { return; }

	/* ---- Note / file sections follow the Type radios ---- */
	var noteBox = form.querySelector( '.sp-kind-note' );
	var fileBox = form.querySelector( '.sp-kind-file' );
	var kinds   = form.querySelectorAll( 'input[name="kind"]' );

	function syncKind() {
		var checked = form.querySelector( 'input[name="kind"]:checked' );
		var kind    = checked ? checked.value : 'note';
		if ( noteBox ) { noteBox.classList.toggle( 'is-visible', 'note' === kind ); }
		if ( fileBox ) { fileBox.classList.toggle( 'is-visible', 'file' === kind ); }
	}

	Array.prototype.forEach.call( kinds, function ( radio ) {
		radio.addEventListener( 'change', syncKind );
	} );
	syncKind();

	/* ---- File picker: drag state, size check, selected-file chip ---- */
	var input  = form.querySelector( '#sp_m_file' );
	var zone   = form.querySelector( '[data-sp-drop]' );
	var picked = form.querySelector( '[data-sp-picked]' );
	var error  = form.querySelector( '[data-sp-file-error]' );

	function formatSize( bytes ) {
		if ( bytes < 1024 ) { return bytes + ' B'; }
		if ( bytes < 1048576 ) { return Math.round( bytes / 1024 ) + ' KB'; }
		return ( bytes / 1048576 ).toFixed( 1 ) + ' MB';
	}

	function clearFile() {
		input.value = '';
		picked.hidden = true;
		zone.classList.remove( 'has-file' );
	}

	if ( input && zone && picked && error ) {
		[ 'dragenter', 'dragover' ].forEach( function ( name ) {
			zone.addEventListener( name, function () { zone.classList.add( 'is-dragover' ); } );
		} );
		[ 'dragleave', 'dragend', 'drop' ].forEach( function ( name ) {
			zone.addEventListener( name, function () { zone.classList.remove( 'is-dragover' ); } );
		} );

		input.addEventListener( 'change', function () {
			error.hidden = true;
			var file = input.files && input.files[ 0 ];

			if ( ! file ) { clearFile(); return; }

			var maxMb = parseInt( zone.getAttribute( 'data-max-mb' ), 10 ) || 0;

			if ( maxMb && file.size > maxMb * 1048576 ) {
				clearFile();
				error.textContent = file.name + ' is ' + formatSize( file.size ) + ', which is over the ' + maxMb + ' MB limit.';
				error.hidden = false;
				return;
			}

			picked.querySelector( '[data-sp-name]' ).textContent = file.name;
			picked.querySelector( '[data-sp-size]' ).textContent = formatSize( file.size );
			picked.hidden = false;
			zone.classList.add( 'has-file' );
		} );

		picked.querySelector( '[data-sp-clear]' ).addEventListener( 'click', function () {
			clearFile();
			error.hidden = true;
		} );
	}

	/* ---- Saving state (skipped when something cancels the submit) ---- */
	var save = form.querySelector( '[data-sp-save]' );

	function resetSave() {
		if ( ! save ) { return; }
		save.disabled = false;
		save.classList.remove( 'is-loading' );
		save.querySelector( 'span' ).textContent = save.getAttribute( 'data-label' );
	}

	form.addEventListener( 'submit', function ( event ) {
		setTimeout( function () {
			if ( event.defaultPrevented || ! save ) { return; }
			save.disabled = true;
			save.classList.add( 'is-loading' );
			save.querySelector( 'span' ).textContent = 'Saving…';
		}, 0 );
	} );

	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted ) { resetSave(); }
	} );
} )();
</script>