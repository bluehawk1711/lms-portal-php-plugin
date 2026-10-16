<?php
/**
 * Portal email log.
 *
 * @package StudentPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Log screen.
 */
function sp_admin_page_sp_email_log() {
	global $wpdb;

	$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$per    = 30;

	$table = sp_table( 'email_log' );
	$where = '1=1';
	$args  = array();

	if ( in_array( $filter, array( 'sent', 'failed' ), true ) ) {
		$where .= ' AND status = %s';
		$args[] = $filter;
	}

	$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
	$total     = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql ) );

	$list_sql    = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
	$list_args   = array_merge( $args, array( $per, ( $page - 1 ) * $per ) );
	$rows        = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ) );
	$failed_only = ( 'failed' === $filter );
	?>
	<form method="get" class="sp-admin-search">
		<input type="hidden" name="page" value="sp-email-log" />
		<select name="status">
			<option value="" <?php selected( $filter, '' ); ?>>All</option>
			<option value="sent" <?php selected( $filter, 'sent' ); ?>>Accepted</option>
			<option value="failed" <?php selected( $filter, 'failed' ); ?>>Failed</option>
		</select>
		<button type="submit" class="button">Filter</button>
	</form>

	<?php if ( $failed_only ) : ?>
		<div class="notice notice-error">
			<p>
				<strong>Emails are failing.</strong> WordPress is not delivering mail on this server. Install and
				configure an SMTP plugin (WP Mail SMTP, Post SMTP or similar), then use the test email on the
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sp-settings' ) ); ?>">Settings</a> screen.
			</p>
		</div>
	<?php endif; ?>

	<p class="sp-admin-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> message(s).</p>

	<div class="sp-table-wrap">
	<table class="widefat striped">
		<thead>
			<tr><th>When</th><th>Recipient</th><th>Subject</th><th>Context</th><th>Status</th><th>Error</th></tr>
		</thead>
		<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6">Nothing logged yet.</td></tr>
			<?php endif; ?>

			<?php foreach ( $rows as $sp_row ) : ?>
				<tr>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sp_row->created_at ) ) ); ?></td>
					<td><?php echo esc_html( $sp_row->recipient ); ?></td>
					<td><?php echo esc_html( $sp_row->subject ); ?></td>
					<td><code><?php echo esc_html( $sp_row->context ); ?></code></td>
					<td><?php echo wp_kses_post( sp_admin_status_badge( $sp_row->status ) ); ?></td>
					<td>
						<?php
						echo $sp_row->error
							? esc_html( $sp_row->error )
							: '<span class="sp-admin-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped ternary.
						?>
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
					href="<?php echo esc_url( add_query_arg( array( 'page' => 'sp-email-log', 'paged' => $i, 'status' => $filter ), admin_url( 'admin.php' ) ) ); ?>"
				><?php echo esc_html( $i ); ?></a>
			<?php endfor; ?>
		</div></div>
	<?php endif; ?>
	<?php
}