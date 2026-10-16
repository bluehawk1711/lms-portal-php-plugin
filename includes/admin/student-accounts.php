<?php
/**
 * Student account management.
 *
 * One screen owns the whole lifecycle now: the add/edit form (rendered from
 * add-student.php), the account list, and per-row actions — edit, resend,
 * enable/disable, delete and a direct Enroll that opens under the row.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SP_PATH . 'includes/admin/add-student.php';

/**
 * Student Accounts screen.
 */
function sp_admin_page_sp_student_accounts() {
	$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$per    = 25;
	$total  = sp_count_profiles( $search );
	$rows   = sp_get_profiles(
		array(
			'search'   => $search,
			'per_page' => $per,
			'offset'   => ( $page - 1 ) * $per,
		)
	);

	// Classes offered by the inline Enroll form. Pending (teacher-created,
	// unapproved) classes stay out until an admin approves them.
	$sp_classes = array_values(
		array_filter(
			sp_get_classes( array( 'per_page' => 200 ) ),
			function ( $sp_row ) {
				return 'pending' !== (string) $sp_row->status;
			}
		)
	);
	?>
	<?php sp_admin_cert_notice(); ?>

	<?php sp_admin_render_student_form(); ?>

	<div class="sp-admin-panel">
		<h2>All students</h2>

		<form method="get" class="sp-admin-search">
			<input type="hidden" name="page" value="sp-student-accounts" />
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name, email or enrolment ID" />
			<button type="submit" class="button">Search</button>
			<?php if ( $search ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sp-student-accounts' ) ); ?>">Clear</a>
			<?php endif; ?>
		</form>
		<p class="sp-admin-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> student account(s).</p>

		<div class="sp-table-wrap">
		<table class="widefat striped">
			<thead>
				<tr>
					<th>Name</th>
					<th>Email</th>
					<th>Status</th>
					<th>Last sign-in</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5">No students found.</td></tr>
				<?php endif; ?>

				<?php foreach ( $rows as $sp_row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $sp_row->full_name ); ?></strong></td>
						<td><a href="mailto:<?php echo esc_attr( $sp_row->email ); ?>"><?php echo esc_html( $sp_row->email ); ?></a></td>
						<td><?php echo wp_kses_post( sp_admin_status_badge( $sp_row->status ) ); ?></td>
						<td>
							<?php
							echo $sp_row->last_login_at
								? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $sp_row->last_login_at ) ) )
								: '<span class="sp-admin-muted">Never</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary.
							?>
						</td>
						<td>
							<span class="sp-admin-actions">
								<button type="button" class="button button-small"
									data-sp-enroll-toggle="<?php echo esc_attr( (int) $sp_row->id ); ?>"
									aria-expanded="false">Enroll</button>

								<span class="sp-kebab">
									<button type="button" class="button button-small sp-kebab-btn"
										data-sp-kebab aria-haspopup="true" aria-expanded="false"
										aria-label="More actions for <?php echo esc_attr( $sp_row->full_name ); ?>">&#8942;</button>
									<span class="sp-kebab-menu" hidden>
										<a class="sp-kebab-item"
											href="<?php echo esc_url( add_query_arg( array( 'page' => 'sp-student-accounts', 'edit' => (int) $sp_row->id ), admin_url( 'admin.php' ) ) ); ?>">Edit</a>

										<form method="post" class="sp-kebab-item-form">
											<?php sp_admin_nonce_field( 'resend_credentials' ); ?>
											<input type="hidden" name="user_id" value="<?php echo esc_attr( (int) $sp_row->user_id ); ?>" />
											<button type="submit" class="sp-kebab-item">Resend credentials</button>
										</form>

										<form method="post" class="sp-kebab-item-form">
											<?php sp_admin_nonce_field( 'toggle_status' ); ?>
											<input type="hidden" name="record" value="student" />
											<input type="hidden" name="record_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
											<input type="hidden" name="status" value="<?php echo esc_attr( 'active' === $sp_row->status ? 'disabled' : 'active' ); ?>" />
											<button type="submit" class="sp-kebab-item">
												<?php echo 'active' === $sp_row->status ? 'Disable' : 'Enable'; ?>
											</button>
										</form>

										<form method="post" class="sp-kebab-item-form" onsubmit="return confirm('Delete this student completely — profile, enrolments, results, attendance marks, exam attempts and their login? This cannot be undone.');">
											<?php sp_admin_nonce_field( 'delete_student' ); ?>
											<input type="hidden" name="profile_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />
											<button type="submit" class="sp-kebab-item sp-kebab-danger">Delete</button>
										</form>
									</span>
								</span>
							</span>
						</td>
					</tr>
					<tr data-sp-enroll-row="<?php echo esc_attr( (int) $sp_row->id ); ?>" hidden>
						<td colspan="5">
							<?php if ( $sp_classes ) : ?>
								<form method="post" class="sp-inline-enroll">
									<?php sp_admin_nonce_field( 'enroll_student' ); ?>
									<input type="hidden" name="profile_id" value="<?php echo esc_attr( (int) $sp_row->id ); ?>" />

									<label>
										<span>Class</span>
										<select name="class_id" required>
											<option value="">Choose a class…</option>
											<?php foreach ( $sp_classes as $sp_pick ) : ?>
												<option value="<?php echo esc_attr( (int) $sp_pick->id ); ?>">
													<?php echo esc_html( $sp_pick->title . ( $sp_pick->code ? ' (' . $sp_pick->code . ')' : '' ) ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</label>

									<label>
										<span>Enrolment ID</span>
										<input type="text" name="enroll_id[<?php echo esc_attr( (int) $sp_row->id ); ?>]" placeholder="Blank = auto" />
									</label>

									<label>
										<span>Admission date</span>
										<input type="date" name="admission_date[<?php echo esc_attr( (int) $sp_row->id ); ?>]" />
									</label>

									<button type="submit" class="button button-primary">Enroll</button>
								</form>
								<p class="description">Leave the enrolment ID blank and the next one for that class is generated automatically.</p>
							<?php else : ?>
								<p class="sp-admin-muted">No classes to enrol into yet — create one on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-classes' ) ); ?>">Classes</a> screen first.</p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>

		<?php
		$pages = (int) ceil( $total / $per );

		if ( $pages > 1 ) :
			?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
					<a
						class="button<?php echo $i === $page ? ' button-primary' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( array( 'page' => 'sp-student-accounts', 'paged' => $i, 's' => $search ), admin_url( 'admin.php' ) ) ); ?>"
					><?php echo esc_html( $i ); ?></a>
				<?php endfor; ?>
			</div></div>
		<?php endif; ?>
	</div>

	<script>
	( function () {
		function closeMenus( except ) {
			Array.prototype.forEach.call( document.querySelectorAll( '[data-sp-kebab]' ), function ( btn ) {
				if ( btn === except ) {
					return;
				}
				btn.setAttribute( 'aria-expanded', 'false' );
				if ( btn.nextElementSibling ) {
					btn.nextElementSibling.hidden = true;
				}
			} );
		}

		document.addEventListener( 'click', function ( event ) {
			var target = event.target && event.target.closest ? event.target : null;
			if ( ! target ) {
				return;
			}

			var kebab = target.closest( '[data-sp-kebab]' );

			if ( kebab ) {
				var menu = kebab.nextElementSibling;
				var open = 'true' === kebab.getAttribute( 'aria-expanded' );
				closeMenus( kebab );
				kebab.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				if ( menu ) {
					menu.hidden = open;
				}
				return;
			}

			if ( ! target.closest( '.sp-kebab-menu' ) ) {
				closeMenus( null );
			}

			var enrollToggle = target.closest( '[data-sp-enroll-toggle]' );

			if ( enrollToggle ) {
				var row = document.querySelector(
					'[data-sp-enroll-row="' + enrollToggle.getAttribute( 'data-sp-enroll-toggle' ) + '"]'
				);

				if ( row ) {
					row.hidden = ! row.hidden;
					enrollToggle.setAttribute( 'aria-expanded', row.hidden ? 'false' : 'true' );

					if ( ! row.hidden ) {
						var select = row.querySelector( 'select' );
						if ( select ) {
							select.focus();
						}
					}
				}
			}
		} );
	}() );
	</script>
	<?php
}
