<?php
/**
 * Queue page template.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rows         = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : array();
$total        = isset( $data['total'] ) ? absint( $data['total'] ) : 0;
$page_num     = isset( $data['page'] ) ? absint( $data['page'] ) : 1;
$per_page     = isset( $data['per_page'] ) ? absint( $data['per_page'] ) : 20;
$max_pages    = max( 1, (int) ceil( $total / $per_page ) );
$has_more     = $page_num < $max_pages;
$total_images = isset( $total_images ) ? absint( $total_images ) : 0;
$status       = isset( $status ) ? sanitize_key( (string) $status ) : '';
$view         = isset( $view ) && in_array( $view, array( 'active', 'history', 'no_alt' ), true ) ? $view : 'active';
$is_history   = 'history' === $view;
$is_no_alt    = 'no_alt' === $view;
?>
<div class="wrap ai-alt-wrap">
	<h1><?php esc_html_e( 'Dynamic Alt Tags Queue', 'dynamic-alt-tags' ); ?></h1>
	<?php if ( ! $is_history && ! $is_no_alt ) : ?>
		<div class="ai-alt-queue-process-top">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ai_alt_run_backfill_queue" />
				<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Run Backfill', 'dynamic-alt-tags' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ai_alt_process_now_queue" />
				<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Process Queue Now', 'dynamic-alt-tags' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper">
		<a class="nav-tab <?php echo $is_history || $is_no_alt ? '' : 'nav-tab-active'; ?>" href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page' => 'ai-alt-text-queue',
					'view' => 'active',
				),
				admin_url( 'upload.php' )
			)
		);
		?>
		"><?php esc_html_e( 'Active Queue', 'dynamic-alt-tags' ); ?></a>
		<a class="nav-tab <?php echo $is_history ? 'nav-tab-active' : ''; ?>" href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page' => 'ai-alt-text-queue',
					'view' => 'history',
				),
				admin_url( 'upload.php' )
			)
		);
		?>
		"><?php esc_html_e( 'History', 'dynamic-alt-tags' ); ?></a>
		<a class="nav-tab <?php echo $is_no_alt ? 'nav-tab-active' : ''; ?>" href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page' => 'ai-alt-text-queue',
					'view' => 'no_alt',
				),
				admin_url( 'upload.php' )
			)
		);
		?>
		"><?php esc_html_e( 'No Alt Images', 'dynamic-alt-tags' ); ?></a>
	</h2>

	<?php if ( isset( $_GET['notice'] ) && 'queue_updated' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					esc_html__( 'Queue items updated: %d', 'dynamic-alt-tags' ),
					isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['notice'] ) && 'queue_process_done' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Image successfully processed', 'dynamic-alt-tags' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['notice'] ) && 'queue_batch_done' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					esc_html__( 'Manual processing finished. %d items processed.', 'dynamic-alt-tags' ),
					isset( $_GET['processed'] ) ? absint( $_GET['processed'] ) : 0
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['notice'] ) && 'queue_backfill_done' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					esc_html__( 'Backfill complete. %d images were queued. Previously processed images were skipped.', 'dynamic-alt-tags' ),
					isset( $_GET['enqueued'] ) ? absint( $_GET['enqueued'] ) : 0
				);
				?>
			</p>
		</div>
	<?php endif; ?>

<?php if ( isset( $_GET['notice'] ) && 'queue_error' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
	<?php
		$queue_msg_raw   = isset( $_GET['queue_msg'] ) ? wp_unslash( $_GET['queue_msg'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$queue_error_msg = '' !== $queue_msg_raw ? sanitize_text_field( rawurldecode( (string) $queue_msg_raw ) ) : __( 'Unable to apply queue action.', 'dynamic-alt-tags' );
	?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $queue_error_msg ); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php
		if ( $is_history ) {
			echo esc_html( sprintf( __( 'Total history items: %d', 'dynamic-alt-tags' ), $total ) );
		} elseif ( $is_no_alt ) {
			echo esc_html( sprintf( __( 'Total images with no alt text: %d', 'dynamic-alt-tags' ), $total ) );
		} else {
			echo esc_html( sprintf( __( 'Total queue items: %1$d out of %2$d images', 'dynamic-alt-tags' ), $total, $total_images ) );
		}
		?>
	</p>

	<?php if ( ! $is_history && ! $is_no_alt ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-alt-queue-form">
			<input type="hidden" name="action" value="ai_alt_queue_action" />
			<?php wp_nonce_field( 'ai_alt_queue_action', 'ai_alt_queue_nonce' ); ?>
			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<label class="screen-reader-text" for="bulk-action-selector-top"><?php esc_html_e( 'Select bulk action', 'dynamic-alt-tags' ); ?></label>
					<select name="bulk_action" id="bulk-action-selector-top">
						<option value="-1"><?php esc_html_e( 'Bulk actions', 'dynamic-alt-tags' ); ?></option>
						<option value="approve"><?php esc_html_e( 'Approve', 'dynamic-alt-tags' ); ?></option>
						<option value="reject"><?php esc_html_e( 'Reject', 'dynamic-alt-tags' ); ?></option>
						<option value="skip"><?php esc_html_e( 'Skip Image', 'dynamic-alt-tags' ); ?></option>
					</select>
					<button type="submit" class="button action"><?php esc_html_e( 'Apply', 'dynamic-alt-tags' ); ?></button>
				</div>
				<br class="clear" />
			</div>
	<?php endif; ?>

	<table class="widefat striped ai-alt-table" data-view="<?php echo esc_attr( $view ); ?>" data-status="<?php echo esc_attr( $status ); ?>" data-per-page="<?php echo esc_attr( (string) $per_page ); ?>">
		<thead>
			<tr>
				<?php if ( ! $is_history && ! $is_no_alt ) : ?>
					<td class="manage-column check-column">
						<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All', 'dynamic-alt-tags' ); ?></label>
						<input id="cb-select-all-1" type="checkbox" class="ai-alt-select-all" />
					</td>
				<?php endif; ?>
				<th><?php esc_html_e( 'Image', 'dynamic-alt-tags' ); ?></th>
				<?php if ( $is_no_alt ) : ?>
					<th><?php esc_html_e( 'Alt Text', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Queue Status', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
				<?php else : ?>
					<th><?php esc_html_e( 'Status', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Confidence', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Existing Alt', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Suggested Alt', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody id="ai-alt-queue-tbody">
			<?php if ( empty( $rows ) ) : ?>
				<tr>
					<td colspan="<?php echo $is_no_alt ? '4' : ( $is_history ? '6' : '7' ); ?>"><?php esc_html_e( 'No queue items found.', 'dynamic-alt-tags' ); ?></td>
				</tr>
			<?php else : ?>
				<?php
				if ( $is_no_alt ) {
					echo $this->render_no_alt_rows_html( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo $this->render_queue_rows_html( $rows, $is_history ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! $is_history && ! $is_no_alt ) : ?>
		<div class="tablenav bottom">
			<div class="alignleft actions bulkactions">
				<label class="screen-reader-text" for="bulk-action-selector-bottom"><?php esc_html_e( 'Select bulk action', 'dynamic-alt-tags' ); ?></label>
				<select name="bulk_action2" id="bulk-action-selector-bottom">
					<option value="-1"><?php esc_html_e( 'Bulk actions', 'dynamic-alt-tags' ); ?></option>
					<option value="approve"><?php esc_html_e( 'Approve', 'dynamic-alt-tags' ); ?></option>
					<option value="reject"><?php esc_html_e( 'Reject', 'dynamic-alt-tags' ); ?></option>
					<option value="skip"><?php esc_html_e( 'Skip Image', 'dynamic-alt-tags' ); ?></option>
				</select>
				<button type="submit" class="button action"><?php esc_html_e( 'Apply', 'dynamic-alt-tags' ); ?></button>
			</div>
			<br class="clear" />
		</div>
		</form>
	<?php endif; ?>

	<?php if ( $has_more ) : ?>
		<div class="tablenav ai-alt-load-more-wrap">
			<button type="button" class="button button-primary ai-alt-load-more" data-view="<?php echo esc_attr( $view ); ?>" data-status="<?php echo esc_attr( $status ); ?>" data-next-page="<?php echo esc_attr( (string) ( $page_num + 1 ) ); ?>" data-per-page="<?php echo esc_attr( (string) $per_page ); ?>"><?php esc_html_e( 'View more images', 'dynamic-alt-tags' ); ?></button>
		</div>
	<?php endif; ?>
</div>
