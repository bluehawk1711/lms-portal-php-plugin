<?php
/**
 * Create or edit one assignment: details, question bank, sets, students.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sp_assignment_id = (int) get_query_var( 'sp_assignment_id' );
$sp_assignment    = $sp_assignment_id ? sp_get_assignment( $sp_assignment_id ) : null;

$sp_class_id = $sp_assignment ? (int) $sp_assignment->class_id : 0;

if ( ! $sp_class_id && isset( $_GET['class'] ) ) {
	$sp_class_id = (int) $_GET['class']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection.
}

if ( $sp_assignment_id && ! $sp_assignment ) {
	?>
	<div class="sp-notice sp-notice-error">That assignment no longer exists.</div>
	<?php
	return;
}

/*
 * Only a class that is already known gets checked here: an existing
 * assignment's class, or a ?class= picked from the list screen. A brand-new
 * assignment has no class yet — the form below asks for one and the save
 * handler validates the posted class against the signed-in teacher, so the
 * editor must not bail before the form renders.
 */
$sp_access = $sp_class_id ? sp_require_assigned_teacher( $sp_class_id ) : null;

if ( is_wp_error( $sp_access ) ) {
	?>
	<div class="sp-notice sp-notice-error"><?php echo esc_html( $sp_access->get_error_message() ); ?></div>
	<?php
	return;
}

$sp_teacher = ! empty( $teacher ) ? $teacher : sp_get_teacher_by_user_id( get_current_user_id() );
$sp_classes = $sp_teacher ? sp_get_classes( array( 'teacher_id' => $sp_teacher->id, 'status' => 'active' ) ) : array();

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

$sp_base_url = $sp_assignment
	? sp_teacher_url( 'assignments/' . (int) $sp_assignment->id )
	: sp_teacher_url( 'assignments/new' );

$sp_list_url = sp_teacher_url( 'assignments' );

/*
 * A brand-new assignment has no tabs: there is nothing to build until the
 * details are saved once. Existing ones expose whichever tabs their type uses.
 */
$sp_tabs = array();

if ( $sp_assignment ) {
	$sp_tabs['details'] = 'Details';

	if ( 'exam' === $sp_assignment->type ) {
		$sp_tabs['bank']     = 'Question bank';
		$sp_tabs['sets']     = 'Sets';
		$sp_tabs['students'] = 'Students';
	}
}

$sp_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'details'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection.

if ( ! isset( $sp_tabs[ $sp_tab ] ) ) {
	$sp_tab = 'details';
}

$sp_type   = $sp_assignment ? $sp_assignment->type : 'paper';
$sp_status = $sp_assignment ? $sp_assignment->status : 'draft';
$sp_due    = '';

if ( $sp_assignment && $sp_assignment->due_date ) {
	// Inputs want the date part; the stored value carries a time too.
	$sp_due = substr( $sp_assignment->due_date, 0, 10 );
}

$sp_class_obj   = $sp_class_id ? sp_get_class( $sp_class_id ) : null;
$sp_class_title = $sp_class_obj ? $sp_class_obj->title : '';
$sp_is_exam     = 'exam' === $sp_type;

// Shortens long question prompts for lists.
$sp_snip = static function ( $text, $limit ) {
	$text = (string) $text;

	return mb_strlen( $text ) > $limit ? mb_substr( $text, 0, $limit ) . '…' : $text;
};

// Shown wherever a value is missing; static markup, nothing to escape.
$sp_dash = '<span class="sp-muted" aria-hidden="true">&mdash;</span><span class="sp-ae-sr">Not available</span>';
?>
<div class="sp-ae">

	<header class="sp-ae-head">
		<a class="sp-ae-back" href="<?php echo esc_url( $sp_list_url ); ?>">All assignments</a>

		<div class="sp-page-head">
			<h1><?php echo $sp_assignment ? esc_html( $sp_assignment->title ) : 'New assignment'; ?></h1>

			<?php if ( $sp_assignment ) : ?>
				<div class="sp-ae-meta">
					<span class="sp-ae-type<?php echo $sp_is_exam ? ' is-exam' : ''; ?>"><?php echo esc_html( sp_assignment_type_label( $sp_assignment->type ) ); ?></span>
					<?php if ( $sp_class_title ) : ?>
						<span class="sp-ae-meta-item"><?php echo esc_html( $sp_class_title ); ?></span>
					<?php endif; ?>
					<?php echo wp_kses_post( sp_assignment_status_badge( $sp_assignment->status ) ); ?>
				</div>
			<?php else : ?>
				<p class="sp-page-sub">A paper is a file your students read. An exam is a timed test built from the class question bank.</p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( count( $sp_tabs ) > 1 ) : ?>
		<nav class="sp-tabs" aria-label="Assignment sections">
			<?php foreach ( $sp_tabs as $sp_key => $sp_label ) : ?>
				<a
					class="sp-tab<?php echo $sp_key === $sp_tab ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( 'tab', $sp_key, $sp_base_url ) ); ?>"
					<?php echo $sp_key === $sp_tab ? ' aria-current="page"' : ''; ?>
				><?php echo esc_html( $sp_label ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

<?php if ( 'details' === $sp_tab ) : ?>

	<form class="sp-ae-card sp-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( $sp_base_url ); ?>">
		<?php sp_teacher_nonce_field( 'save_assignment' ); ?>

		<?php if ( $sp_assignment ) : ?>
			<input type="hidden" name="assignment_id" value="<?php echo esc_attr( (int) $sp_assignment->id ); ?>" />
		<?php endif; ?>

		<div class="sp-ae-section">
			<div class="sp-ae-section-copy">
				<h2 class="sp-ae-section-title">Basics</h2>
				<p class="sp-ae-section-text">Which class this is for and what it is called.</p>
			</div>
			<div class="sp-ae-fields">
				<div class="sp-field">
					<label for="sp_a_class">Class <span class="sp-req">*</span></label>
					<select id="sp_a_class" name="class_id" required <?php disabled( (bool) $sp_assignment ); ?>>
						<?php if ( $sp_assignment ) : ?>
							<option value="<?php echo esc_attr( $sp_class_id ); ?>" selected>
								<?php echo esc_html( $sp_class_title ? $sp_class_title : __( 'Unknown class', 'student-portal' ) ); ?>
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
					<?php if ( ! $sp_assignment && ! $sp_allowed ) : ?>
						<p class="sp-help">You are not assigned to any class yet — ask an administrator to assign you first.</p>
					<?php endif; ?>
				</div>

				<div class="sp-field">
					<label for="sp_a_title">Title <span class="sp-req">*</span></label>
					<input type="text" id="sp_a_title" name="title" required
						value="<?php echo esc_attr( $sp_assignment ? $sp_assignment->title : '' ); ?>" />
				</div>
			</div>
		</div>

		<div class="sp-ae-section">
			<div class="sp-ae-section-copy">
				<h2 class="sp-ae-section-title">Type</h2>
				<p class="sp-ae-section-text">A paper is read; an exam is sat.</p>
			</div>
			<div class="sp-ae-fields">
				<fieldset class="sp-fieldset">
					<legend class="sp-ae-sr">Type</legend>
					<div class="sp-ae-choices">
						<label class="sp-radio sp-ae-choice">
							<input type="radio" name="type" value="paper" <?php checked( $sp_type, 'paper' ); ?> <?php disabled( (bool) $sp_assignment ); ?> />
							<span class="sp-ae-choice-body">
								<span class="sp-ae-choice-title">Paper</span>
								<span class="sp-ae-choice-text">Upload a file for the class to read.</span>
							</span>
						</label>
						<label class="sp-radio sp-ae-choice">
							<input type="radio" name="type" value="exam" <?php checked( $sp_type, 'exam' ); ?> <?php disabled( (bool) $sp_assignment ); ?> />
							<span class="sp-ae-choice-body">
								<span class="sp-ae-choice-title">Exam</span>
								<span class="sp-ae-choice-text">Timed test built from the question bank.</span>
							</span>
						</label>
					</div>
					<?php if ( $sp_assignment ) : ?>
						<p class="sp-help">The type cannot be changed after creation.</p>
					<?php endif; ?>
				</fieldset>
			</div>
		</div>

		<div class="sp-ae-section">
			<div class="sp-ae-section-copy">
				<h2 class="sp-ae-section-title">Schedule</h2>
				<p class="sp-ae-section-text">When students need to finish.</p>
			</div>
			<div class="sp-ae-fields">
				<div class="sp-ae-grid">
					<div class="sp-field">
						<label for="sp_a_due">Due date / exam day <span class="sp-req">*</span></label>
						<input type="date" id="sp_a_due" name="due_date" value="<?php echo esc_attr( $sp_due ); ?>" required />
						<p class="sp-help">
							Papers are due at the end of that day. An exam opens for the whole day it is scheduled on.
						</p>
					</div>

					<div class="sp-field sp-atype-exam" <?php echo 'exam' !== $sp_type ? 'hidden' : ''; ?>>
						<label for="sp_a_limit">Time limit (minutes) <span class="sp-req">*</span></label>
						<input type="number" id="sp_a_limit" name="time_limit_minutes" min="1" max="600"
							value="<?php echo $sp_assignment && $sp_assignment->time_limit_minutes ? esc_attr( (int) $sp_assignment->time_limit_minutes ) : ''; ?>" />
						<p class="sp-help">Between 1 and 600 minutes. Leaving the page midway marks the attempt failed.</p>
					</div>
				</div>
			</div>
		</div>

		<div class="sp-ae-section">
			<div class="sp-ae-section-copy">
				<h2 class="sp-ae-section-title">Instructions</h2>
				<p class="sp-ae-section-text">Optional notes for your students.</p>
			</div>
			<div class="sp-ae-fields">
				<div class="sp-field">
					<label for="sp_a_desc">Instructions for students</label>
					<textarea id="sp_a_desc" name="description" rows="5"><?php echo $sp_assignment && $sp_assignment->description ? esc_textarea( $sp_assignment->description ) : ''; ?></textarea>
				</div>
			</div>
		</div>

		<div class="sp-ae-section sp-atype-paper" <?php echo 'paper' !== $sp_type ? 'hidden' : ''; ?>>
			<div class="sp-ae-section-copy">
				<h2 class="sp-ae-section-title">File</h2>
				<p class="sp-ae-section-text">The paper students will read.</p>
			</div>
			<div class="sp-ae-fields">
				<div class="sp-field">
					<label for="sp_a_file">
						<?php if ( $sp_assignment && $sp_assignment->attachment_path ) : ?>
							Replace file (current: <?php echo esc_html( $sp_assignment->original_filename ); ?>)
						<?php else : ?>
							Upload the paper <span class="sp-req">*</span>
						<?php endif; ?>
					</label>
					<input type="file" id="sp_a_file" name="paper_file"
						accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.csv,.png,.jpg,.jpeg,.webp" />
					<p class="sp-help">
						Allowed: PDF, Word, PowerPoint, Excel, TXT, CSV and images. Only enrolled students can download it.
					</p>
				</div>
			</div>
		</div>

		<div class="sp-ae-actions">
			<button type="submit" class="sp-btn sp-btn-primary"><?php echo $sp_assignment ? 'Save changes' : 'Create assignment'; ?></button>
			<a class="sp-btn sp-btn-ghost" href="<?php echo esc_url( $sp_list_url ); ?>">Back to assignments</a>
		</div>
	</form>

	<?php if ( $sp_assignment ) : ?>

		<?php if ( $sp_is_exam && 'draft' === $sp_status ) : ?>
			<aside class="sp-ae-steps" aria-label="Next steps">
				<h2 class="sp-ae-steps-title">Next steps</h2>
				<ol>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'tab', 'bank', $sp_base_url ) ); ?>">Build the question bank</a>
						<span>Add the questions your class shares.</span>
					</li>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'tab', 'sets', $sp_base_url ) ); ?>">Create the sets</a>
						<span>Choose which questions go in each version.</span>
					</li>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'tab', 'students', $sp_base_url ) ); ?>">Publish from Students</a>
						<span>Decide who gets which set, then publish.</span>
					</li>
				</ol>
			</aside>
		<?php elseif ( 'paper' === $sp_assignment->type && 'draft' === $sp_status ) : ?>
			<form class="sp-ae-zone is-publish" method="post" action="<?php echo esc_url( $sp_base_url ); ?>">
				<?php sp_teacher_nonce_field( 'publish_assignment' ); ?>
				<input type="hidden" name="assignment_id" value="<?php echo esc_attr( (int) $sp_assignment->id ); ?>" />
				<div class="sp-ae-zone-copy">
					<h2 class="sp-ae-zone-title">Publish</h2>
					<p class="sp-ae-zone-text">Publishing makes the paper visible to every student enrolled in the class.</p>
				</div>
				<button type="submit" class="sp-btn sp-btn-primary">Publish paper</button>
			</form>
		<?php endif; ?>

		<?php if ( 'draft' === $sp_status ) : ?>
			<form class="sp-ae-zone is-danger" method="post" action="<?php echo esc_url( $sp_base_url ); ?>"
				onsubmit="return confirm('Delete this assignment? Its questions stay in the class bank.');">
				<?php sp_teacher_nonce_field( 'delete_assignment' ); ?>
				<input type="hidden" name="assignment_id" value="<?php echo esc_attr( (int) $sp_assignment->id ); ?>" />
				<div class="sp-ae-zone-copy">
					<h2 class="sp-ae-zone-title">Delete assignment</h2>
					<p class="sp-ae-zone-text">Removes this draft. Its questions stay in the class bank.</p>
				</div>
				<button type="submit" class="sp-btn sp-btn-danger">Delete assignment</button>
			</form>
		<?php endif; ?>

	<?php endif; ?>

<?php elseif ( 'bank' === $sp_tab ) :
	$sp_questions = sp_get_class_questions( $sp_class_id );
	?>

	<section class="sp-ae-card" aria-labelledby="sp-ae-add-q">
		<div class="sp-ae-card-head">
			<h2 class="sp-ae-card-title" id="sp-ae-add-q">Add a question</h2>
			<p class="sp-ae-card-sub">One at a time. The bank is shared by every exam in this class.</p>
		</div>

		<form class="sp-ae-card-body sp-form" method="post" action="<?php echo esc_url( $sp_base_url ); ?>">
			<?php sp_teacher_nonce_field( 'save_question' ); ?>
			<input type="hidden" name="class_id" value="<?php echo esc_attr( $sp_class_id ); ?>" />

			<div class="sp-field">
				<label for="sp_q_type">Format</label>
				<select id="sp_q_type" name="question_type" class="sp-qtype-toggle">
					<?php foreach ( sp_question_types() as $sp_qtype ) : ?>
						<option value="<?php echo esc_attr( $sp_qtype ); ?>"><?php echo esc_html( sp_question_type_label( $sp_qtype ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="sp-field">
				<label for="sp_q_prompt">Question <span class="sp-req">*</span></label>
				<textarea id="sp_q_prompt" name="prompt" rows="3" required></textarea>
			</div>

			<div class="sp-field sp-qtype-mcq">
				<label for="sp_q_o1">Options <span class="sp-req">*</span></label>
				<div class="sp-ae-options">
					<span class="sp-ae-option">
						<span class="sp-ae-option-letter" aria-hidden="true">A</span>
						<input type="text" id="sp_q_o1" name="options[]" placeholder="Option A" aria-label="Option A" />
					</span>
					<span class="sp-ae-option">
						<span class="sp-ae-option-letter" aria-hidden="true">B</span>
						<input type="text" name="options[]" placeholder="Option B" aria-label="Option B" />
					</span>
					<span class="sp-ae-option">
						<span class="sp-ae-option-letter" aria-hidden="true">C</span>
						<input type="text" name="options[]" placeholder="Option C (optional)" aria-label="Option C" />
					</span>
					<span class="sp-ae-option">
						<span class="sp-ae-option-letter" aria-hidden="true">D</span>
						<input type="text" name="options[]" placeholder="Option D (optional)" aria-label="Option D" />
					</span>
				</div>
				<p class="sp-help">Leave the last ones blank if the question has fewer options.</p>
			</div>

			<div class="sp-field">
				<label for="sp_q_answer">Correct answer <span class="sp-req">*</span></label>
				<input type="text" id="sp_q_answer" name="answer" required
					placeholder="The correct answer" />
				<p class="sp-help sp-qtype-mcq" hidden>The letter of the right option, e.g. B.</p>
				<p class="sp-help sp-qtype-truefalse" hidden>Type TRUE or FALSE.</p>
				<p class="sp-help sp-qtype-fill" hidden>The expected wording. Separate alternatives with a pipe, e.g. color|colour.</p>
			</div>

			<div class="sp-ae-inline-actions">
				<button type="submit" class="sp-btn sp-btn-primary">Add question</button>
			</div>
		</form>
	</section>

	<details class="sp-ae-card sp-ae-import">
		<summary>
			<span class="sp-ae-import-title">Import from CSV</span>
			<span class="sp-ae-import-sub">Add many questions at once</span>
		</summary>

		<div class="sp-ae-card-body">
			<p class="sp-ae-note">
				Columns: <code>type, prompt, option_a, option_b, option_c, option_d, answer</code>.
				A header row is optional. Rows that fail validation are reported and skipped.
			</p>

			<form class="sp-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( $sp_base_url ); ?>">
				<?php sp_teacher_nonce_field( 'import_questions' ); ?>
				<input type="hidden" name="class_id" value="<?php echo esc_attr( $sp_class_id ); ?>" />

				<div class="sp-field">
					<label for="sp_q_csv_file">CSV file</label>
					<input type="file" id="sp_q_csv_file" name="questions_file" accept=".csv,.txt" />
				</div>

				<div class="sp-field">
					<label for="sp_q_csv_text">…or paste the CSV</label>
					<textarea id="sp_q_csv_text" name="questions_csv" rows="6" placeholder="mcq,Which planet is red?,Venus,Earth,Mars,Jupiter,C"></textarea>
				</div>

				<div class="sp-ae-inline-actions">
					<button type="submit" style=" padding: 0px 10px ;" class="sp-btn sp-btn-primary">Import</button>
				</div>
			</form>
		</div>
	</details>

	<section class="sp-ae-card" aria-labelledby="sp-ae-bank">
		<div class="sp-ae-card-head is-row">
			<div>
				<h2 class="sp-ae-card-title" id="sp-ae-bank">
					Class bank
					<span class="sp-ae-count"><?php echo esc_html( number_format_i18n( count( $sp_questions ) ) ); ?></span>
				</h2>
				<p class="sp-ae-card-sub">Shared by every exam in this class.</p>
			</div>
		</div>

		<?php if ( ! $sp_questions ) : ?>
			<div class="sp-empty sp-ae-empty">
				<p class="sp-ae-empty-title">No questions yet</p>
				<p class="sp-ae-empty-text">Add them by hand above or import a CSV — the bank is shared by every exam in this class.</p>
			</div>
		<?php else : ?>
			<div class="sp-ae-tablewrap">
				<table class="sp-ae-table">
					<caption class="sp-ae-sr">Questions in the class bank</caption>
					<thead>
						<tr>
							<th scope="col">#</th>
							<th scope="col">Format</th>
							<th scope="col">Question</th>
							<th scope="col">Answer</th>
							<th scope="col"><span class="sp-ae-sr">Actions</span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sp_questions as $sp_index => $sp_question ) : ?>
							<tr>
								<td class="sp-ae-cell-num"><?php echo esc_html( (string) ( $sp_index + 1 ) ); ?></td>
								<td data-label="Format"><span class="sp-ae-pill"><?php echo esc_html( sp_question_type_label( $sp_question->type ) ); ?></span></td>
								<td class="sp-ae-cell-q" title="<?php echo esc_attr( $sp_question->prompt ); ?>"><?php echo esc_html( $sp_snip( $sp_question->prompt, 120 ) ); ?></td>
								<td data-label="Answer"><span class="sp-ae-answer"><?php echo esc_html( $sp_question->answer ); ?></span></td>
								<td class="sp-ae-cell-action">
									<form method="post" action="<?php echo esc_url( $sp_base_url ); ?>"
										onsubmit="return confirm('Remove this question from the bank?');">
										<?php sp_teacher_nonce_field( 'delete_question' ); ?>
										<input type="hidden" name="question_id" value="<?php echo esc_attr( (int) $sp_question->id ); ?>" />
										<button type="submit" class="sp-btn sp-btn-danger sp-btn-sm" aria-label="<?php echo esc_attr( 'Delete question ' . ( $sp_index + 1 ) ); ?>">Delete</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>

<?php elseif ( 'sets' === $sp_tab ) :
	$sp_bank      = sp_get_class_questions( $sp_class_id );
	$sp_sets      = sp_get_assignment_sets( (int) $sp_assignment->id );
	$sp_sets_open = ! sp_assignment_started( (int) $sp_assignment->id );
	$sp_bank_ids  = array_map( 'intval', wp_list_pluck( $sp_bank, 'id' ) );
	?>
	<form class="sp-form" method="post" action="<?php echo esc_url( $sp_base_url ); ?>">
		<?php sp_teacher_nonce_field( 'save_sets' ); ?>
		<input type="hidden" name="assignment_id" value="<?php echo esc_attr( (int) $sp_assignment->id ); ?>" />

		<?php if ( ! $sp_bank ) : ?>
			<div class="sp-empty sp-ae-empty">
				<p class="sp-ae-empty-title">The question bank is empty</p>
				<p class="sp-ae-empty-text">Add questions to the class bank first, then come back to build your sets.</p>
				<a class="sp-btn sp-btn-primary" href="<?php echo esc_url( add_query_arg( 'tab', 'bank', $sp_base_url ) ); ?>">Go to the question bank</a>
			</div>
		<?php else : ?>
			<p class="sp-ae-note">
				Each set is one version of the paper. Deal the same set to everyone, deal them at random,
				or pick a set per student when you publish.
			</p>

			<?php if ( ! $sp_sets_open ) : ?>
				<div class="sp-ae-banner is-warn" role="status">Students have already started — the sets are locked.</div>
			<?php endif; ?>

			<div class="sp-set-list">
				<?php
				$sp_set_blocks = $sp_sets ? $sp_sets : array( (object) array( 'label' => 'A' ) );

				foreach ( $sp_set_blocks as $sp_i => $sp_set ) :
					$sp_existing_q = $sp_sets ? wp_list_pluck( sp_get_set_questions( (int) $sp_set->id ), 'id' ) : array();
					$sp_existing_q = array_map( 'intval', $sp_existing_q );
					$sp_set_label  = isset( $sp_set->label ) ? $sp_set->label : chr( 65 + $sp_i );
					$sp_picked     = count( array_intersect( $sp_existing_q, $sp_bank_ids ) );
					?>
					<fieldset class="sp-set-card sp-ae-set" data-set-index="<?php echo esc_attr( (int) $sp_i ); ?>">
						<legend class="sp-ae-sr">Set <?php echo esc_html( $sp_set_label ); ?></legend>

						<div class="sp-ae-set-head" aria-hidden="true">
							<span class="sp-ae-set-badge"><?php echo esc_html( mb_strtoupper( mb_substr( (string) $sp_set_label, 0, 1 ) ) ); ?></span>
							<span class="sp-ae-set-name">Set <?php echo esc_html( $sp_set_label ); ?></span>
						</div>

						<?php if ( $sp_sets ) : ?>
							<div class="sp-field sp-ae-set-field">
								<label for="sp_set_label_<?php echo esc_attr( (int) $sp_i ); ?>">Set name</label>
								<input type="text" id="sp_set_label_<?php echo esc_attr( (int) $sp_i ); ?>"
									name="sets[<?php echo esc_attr( (int) $sp_i ); ?>][label]"
									value="<?php echo esc_attr( isset( $sp_set->label ) ? $sp_set->label : '' ); ?>"
									<?php disabled( ! $sp_sets_open ); ?> />
							</div>
						<?php else : ?>
							<input type="hidden" name="sets[<?php echo esc_attr( (int) $sp_i ); ?>][label]"
								value="<?php echo esc_attr( chr( 65 + $sp_i ) ); ?>" />
						<?php endif; ?>

						<div class="sp-ae-set-bar">
							<span class="sp-ae-set-count"><?php echo esc_html( $sp_picked . ' of ' . count( $sp_bank ) . ' selected' ); ?></span>
							<span class="sp-ae-set-tools"></span>
						</div>

						<div class="sp-set-questions">
							<?php foreach ( $sp_bank as $sp_qi => $sp_question ) : ?>
								<label class="sp-set-option">
									<input type="checkbox"
										name="sets[<?php echo esc_attr( (int) $sp_i ); ?>][question_ids][]"
										value="<?php echo esc_attr( (int) $sp_question->id ); ?>"
										<?php checked( in_array( (int) $sp_question->id, $sp_existing_q, true ) ); ?>
										<?php disabled( ! $sp_sets_open ); ?> />
									<span class="sp-ae-qn"><?php echo esc_html( (string) ( $sp_qi + 1 ) ); ?></span>
									<span class="sp-ae-qtext"><?php echo esc_html( $sp_snip( $sp_question->prompt, 90 ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>
			</div>

			<?php if ( ! $sp_sets ) : ?>
				<p class="sp-help sp-ae-note">Tick the questions that belong to set A, then save. Use "Add another set" to build more versions of the paper.</p>
			<?php endif; ?>

			<div class="sp-ae-actions is-floating">
				<?php if ( $sp_sets_open ) : ?>
					<button type="submit" class="sp-btn sp-btn-primary">Save sets</button>
					<button type="submit" name="add_set" value="1" class="sp-btn sp-btn-ghost">Add another set</button>
				<?php endif; ?>
				<a class="sp-btn sp-btn-ghost" href="<?php echo esc_url( add_query_arg( 'tab', 'bank', $sp_base_url ) ); ?>">Edit question bank</a>
			</div>
		<?php endif; ?>
	</form>

	<script>
	// Live "n of m selected" counters and Select all / Clear shortcuts. The form works without it.
	(function () {
		document.querySelectorAll('.sp-ae-set').forEach(function (card) {
			var boxes = card.querySelectorAll('input[type="checkbox"]');
			var count = card.querySelector('.sp-ae-set-count');
			var tools = card.querySelector('.sp-ae-set-tools');

			if (!boxes.length || !count) {
				return;
			}

			function update() {
				var n = 0;
				boxes.forEach(function (box) {
					if (box.checked) {
						n++;
					}
				});
				count.textContent = n + ' of ' + boxes.length + ' selected';
			}

			if (tools && !boxes[0].disabled) {
				[['Select all', true], ['Clear', false]].forEach(function (action) {
					var button = document.createElement('button');
					button.type = 'button';
					button.className = 'sp-ae-link';
					button.textContent = action[0];
					button.addEventListener('click', function () {
						boxes.forEach(function (box) {
							box.checked = action[1];
						});
						update();
					});
					tools.appendChild(button);
				});
			}

			card.addEventListener('change', update);
			update();
		});
	})();
	</script>

<?php elseif ( 'students' === $sp_tab ) :
	$sp_sets   = sp_get_assignment_sets( (int) $sp_assignment->id );
	$sp_counts = sp_assignment_progress( (int) $sp_assignment->id );
	$sp_roster = sp_get_enrollments_for_class( $sp_class_id, array( 'active' ), 0 );
	?>

	<?php if ( 'draft' === $sp_status ) : ?>
		<section class="sp-ae-card" aria-labelledby="sp-ae-publish">
			<div class="sp-ae-card-head is-row">
				<div>
					<h2 class="sp-ae-card-title" id="sp-ae-publish">Publish</h2>
					<p class="sp-ae-card-sub">Choose how students receive this assignment, then publish.</p>
				</div>
				<?php if ( $sp_roster ) : ?>
					<span class="sp-ae-pill"><?php echo esc_html( number_format_i18n( count( $sp_roster ) ) . ( 1 === count( $sp_roster ) ? ' active student' : ' active students' ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( ! $sp_roster ) : ?>
				<div class="sp-empty sp-ae-empty">
					<p class="sp-ae-empty-title">No students yet</p>
					<p class="sp-ae-empty-text">No active students are enrolled in that class.</p>
				</div>
			<?php else : ?>
				<form class="sp-ae-card-body sp-form" method="post" action="<?php echo esc_url( $sp_base_url ); ?>">
					<?php sp_teacher_nonce_field( 'publish_assignment' ); ?>
					<input type="hidden" name="assignment_id" value="<?php echo esc_attr( (int) $sp_assignment->id ); ?>" />

					<?php if ( 'exam' === $sp_assignment->type ) : ?>
						<?php if ( ! $sp_sets ) : ?>
							<div class="sp-empty sp-ae-empty">
								<p class="sp-ae-empty-title">No question sets yet</p>
								<p class="sp-ae-empty-text">Build at least one set before you publish the exam.</p>
								<a class="sp-btn sp-btn-primary" href="<?php echo esc_url( add_query_arg( 'tab', 'sets', $sp_base_url ) ); ?>">Go to Sets</a>
							</div>
						<?php else : ?>
							<fieldset class="sp-fieldset">
								<legend class="sp-ae-label">Which set does each student get?</legend>

								<div class="sp-ae-choices">
									<label class="sp-radio sp-ae-choice">
										<input type="radio" name="mode" value="single" checked class="sp-dist-toggle" />
										<span class="sp-ae-choice-body">
											<span class="sp-ae-choice-title">Same set for everyone</span>
											<span class="sp-ae-choice-text">Everyone sits the version you pick.</span>
										</span>
									</label>

									<label class="sp-radio sp-ae-choice">
										<input type="radio" name="mode" value="random" class="sp-dist-toggle" />
										<span class="sp-ae-choice-body">
											<span class="sp-ae-choice-title">Deal sets at random</span>
											<span class="sp-ae-choice-text">Keeps the counts balanced.</span>
										</span>
									</label>

									<label class="sp-radio sp-ae-choice">
										<input type="radio" name="mode" value="manual" class="sp-dist-toggle" />
										<span class="sp-ae-choice-body">
											<span class="sp-ae-choice-title">Choose a set per student</span>
											<span class="sp-ae-choice-text">Assign each student yourself.</span>
										</span>
									</label>
								</div>
							</fieldset>

							<div class="sp-field sp-dist-single">
								<label for="sp_pub_set">Set for everyone</label>
								<select id="sp_pub_set" name="set_id">
									<?php foreach ( $sp_sets as $sp_set ) : ?>
										<option value="<?php echo esc_attr( (int) $sp_set->id ); ?>"><?php echo esc_html( $sp_set->label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="sp-field sp-dist-manual" hidden>
								<div class="sp-ae-tablewrap is-inset">
									<table class="sp-ae-table sp-table-form">
										<caption class="sp-ae-sr">Set for each student</caption>
										<thead>
											<tr>
												<th scope="col">Student</th>
												<th scope="col">Set</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $sp_roster as $sp_student ) : ?>
												<tr>
													<td class="sp-ae-cell-name">
														<strong><?php echo esc_html( $sp_student->full_name ); ?></strong>
													</td>
													<td data-label="Set">
														<select name="map[<?php echo esc_attr( (int) $sp_student->profile_id ); ?>]" aria-label="<?php echo esc_attr( 'Set for ' . $sp_student->full_name ); ?>">
															<?php foreach ( $sp_sets as $sp_set ) : ?>
																<option value="<?php echo esc_attr( (int) $sp_set->id ); ?>"><?php echo esc_html( $sp_set->label ); ?></option>
															<?php endforeach; ?>
														</select>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</div>

							<div class="sp-ae-inline-actions">
								<button type="submit" class="sp-btn sp-btn-primary">Publish exam</button>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<p class="sp-ae-note">Publishing makes the paper visible to every student enrolled in the class.</p>
						<div class="sp-ae-inline-actions">
							<button type="submit" class="sp-btn sp-btn-primary">Publish paper</button>
						</div>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		</section>

	<?php else : ?>
		<?php
		$sp_assigned = (int) ( $sp_counts['total'] ?? 0 );
		$sp_done     = (int) ( $sp_counts['submitted'] ?? 0 );
		$sp_running  = (int) ( $sp_counts['in_progress'] ?? 0 );
		$sp_failed   = (int) ( $sp_counts['failed'] ?? 0 );
		$sp_done_pct = $sp_assigned ? min( 100, (int) floor( $sp_done / $sp_assigned * 100 ) ) : 0;
		?>
		<section class="sp-ae-card sp-ae-overview" aria-label="Progress overview">
			<dl class="sp-ae-stats">
				<div class="sp-ae-stat">
					<dt>Assigned</dt>
					<dd><?php echo esc_html( number_format_i18n( $sp_assigned ) ); ?></dd>
				</div>
				<div class="sp-ae-stat<?php echo $sp_done ? ' is-good' : ''; ?>">
					<dt>Submitted</dt>
					<dd><?php echo esc_html( number_format_i18n( $sp_done ) ); ?></dd>
				</div>
				<div class="sp-ae-stat<?php echo $sp_running ? ' is-warn' : ''; ?>">
					<dt>In progress</dt>
					<dd><?php echo esc_html( number_format_i18n( $sp_running ) ); ?></dd>
				</div>
				<div class="sp-ae-stat<?php echo $sp_failed ? ' is-danger' : ''; ?>">
					<dt>Failed</dt>
					<dd><?php echo esc_html( number_format_i18n( $sp_failed ) ); ?></dd>
				</div>
			</dl>

			<?php if ( $sp_assigned ) : ?>
				<div class="sp-ae-overview-bar">
					<div class="sp-ae-prog-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $sp_done_pct; ?>" aria-label="Students who have submitted">
						<span style="width: <?php echo (int) $sp_done_pct; ?>%"></span>
					</div>
					<p class="sp-ae-prog-text"><?php echo esc_html( $sp_done . ' of ' . $sp_assigned . ' submitted' ); ?></p>
				</div>
			<?php endif; ?>
		</section>

		<?php
		$sp_attempts = sp_get_assignment_attempts( (int) $sp_assignment->id );

		if ( ! $sp_attempts ) :
			?>
			<div class="sp-empty sp-ae-empty">
				<?php if ( 'exam' === $sp_assignment->type ) : ?>
					<p class="sp-ae-empty-title">No students on the list yet</p>
					<p class="sp-ae-empty-text">Students who join the class later are added automatically.</p>
				<?php else : ?>
					<p class="sp-ae-empty-title">Nothing to track</p>
					<p class="sp-ae-empty-text">Papers do not track individual attempts.</p>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<section class="sp-ae-card" aria-labelledby="sp-ae-attempts">
				<div class="sp-ae-card-head is-row">
					<h2 class="sp-ae-card-title" id="sp-ae-attempts">
						Attempts
						<span class="sp-ae-count"><?php echo esc_html( number_format_i18n( count( $sp_attempts ) ) ); ?></span>
					</h2>
				</div>

				<div class="sp-ae-tablewrap">
					<table class="sp-ae-table">
						<caption class="sp-ae-sr">Student attempts</caption>
						<thead>
							<tr>
								<th scope="col">Student</th>
								<th scope="col">Set</th>
								<th scope="col">Status</th>
								<th scope="col">Score</th>
								<th scope="col">Started</th>
								<th scope="col">Submitted</th>
								<th scope="col"><span class="sp-ae-sr">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $sp_attempts as $sp_attempt ) :
								$sp_has_score = null !== $sp_attempt->score && '' !== $sp_attempt->score;
								$sp_score_pct = ( $sp_has_score && (float) $sp_attempt->total > 0 )
									? (int) round( (float) $sp_attempt->score / (float) $sp_attempt->total * 100 )
									: null;
								$sp_started   = $sp_attempt->started_at ? strtotime( $sp_attempt->started_at ) : 0;
								$sp_finished  = $sp_attempt->submitted_at ? strtotime( $sp_attempt->submitted_at ) : 0;
								?>
								<tr>
									<td class="sp-ae-cell-name">
										<strong><?php echo esc_html( $sp_attempt->full_name ); ?></strong>
										<span class="sp-kv-note"><?php echo esc_html( $sp_attempt->email ); ?></span>
									</td>
									<td data-label="Set">
										<?php
										if ( $sp_attempt->set_label ) {
											echo '<span class="sp-ae-pill">' . esc_html( $sp_attempt->set_label ) . '</span>';
										} else {
											echo $sp_dash; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
										}
										?>
									</td>
									<td data-label="Status"><?php echo wp_kses_post( sp_attempt_status_badge( $sp_attempt->status ) ); ?></td>
									<td data-label="Score">
										<?php if ( $sp_has_score ) : ?>
											<span class="sp-ae-score"><?php echo esc_html( $sp_attempt->score . '/' . $sp_attempt->total ); ?></span>
											<?php if ( null !== $sp_score_pct ) : ?>
												<span class="sp-ae-score-pct"><?php echo esc_html( $sp_score_pct . '%' ); ?></span>
											<?php endif; ?>
										<?php else : ?>
											<?php echo $sp_dash; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
										<?php endif; ?>
									</td>
									<td data-label="Started">
										<?php if ( $sp_started ) : ?>
											<span class="sp-ae-dt"><?php echo esc_html( date_i18n( get_option( 'date_format' ), $sp_started ) ); ?><span class="sp-ae-dt-time"><?php echo esc_html( date_i18n( 'H:i', $sp_started ) ); ?></span></span>
										<?php else : ?>
											<?php echo $sp_dash; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
										<?php endif; ?>
									</td>
									<td data-label="Submitted">
										<?php if ( $sp_finished ) : ?>
											<span class="sp-ae-dt"><?php echo esc_html( date_i18n( get_option( 'date_format' ), $sp_finished ) ); ?><span class="sp-ae-dt-time"><?php echo esc_html( date_i18n( 'H:i', $sp_finished ) ); ?></span></span>
										<?php else : ?>
											<?php echo $sp_dash; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
										<?php endif; ?>
									</td>
									<td class="sp-ae-cell-action">
										<?php if ( in_array( $sp_attempt->status, array( 'submitted', 'failed' ), true ) ) : ?>
											<form method="post" action="<?php echo esc_url( $sp_base_url ); ?>"
												onsubmit="return confirm('Allow this student to sit the exam again? Their current attempt is cleared.');">
												<?php sp_teacher_nonce_field( 'allow_retake' ); ?>
												<input type="hidden" name="student_row_id" value="<?php echo esc_attr( (int) $sp_attempt->id ); ?>" />
												<button type="submit" class="sp-btn sp-btn-ghost sp-btn-sm" aria-label="<?php echo esc_attr( 'Allow retake: ' . $sp_attempt->full_name ); ?>">Allow retake</button>
											</form>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( 'published' === $sp_status ) : ?>
			<form class="sp-ae-zone" method="post" action="<?php echo esc_url( $sp_base_url ); ?>"
				onsubmit="return confirm('Close this assignment? No new attempts can start.');">
				<?php sp_teacher_nonce_field( 'close_assignment' ); ?>
				<input type="hidden" name="assignment_id" value="<?php echo esc_attr( (int) $sp_assignment->id ); ?>" />
				<div class="sp-ae-zone-copy">
					<h2 class="sp-ae-zone-title">Close assignment</h2>
					<p class="sp-ae-zone-text">No new attempts can start once it is closed.</p>
				</div>
				<button type="submit" class="sp-btn sp-btn-ghost">Close assignment</button>
			</form>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( 'closed' === $sp_status ) : ?>
		<form class="sp-ae-zone is-danger" method="post" action="<?php echo esc_url( $sp_base_url ); ?>"
			onsubmit="return confirm('Delete this assignment? Its questions stay in the class bank.');">
			<?php sp_teacher_nonce_field( 'delete_assignment' ); ?>
			<input type="hidden" name="assignment_id" value="<?php echo esc_attr( (int) $sp_assignment->id ); ?>" />
			<div class="sp-ae-zone-copy">
				<h2 class="sp-ae-zone-title">Delete assignment</h2>
				<p class="sp-ae-zone-text">Removes this assignment. Its questions stay in the class bank.</p>
			</div>
			<button type="submit" class="sp-btn sp-btn-danger">Delete assignment</button>
		</form>
	<?php endif; ?>

<?php endif; ?>

</div>