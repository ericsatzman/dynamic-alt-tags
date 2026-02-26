<?php
/**
 * Queue page template.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rows      = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : array();
$total     = isset( $data['total'] ) ? absint( $data['total'] ) : 0;
$page_num  = isset( $data['page'] ) ? absint( $data['page'] ) : 1;
$per_page  = isset( $data['per_page'] ) ? absint( $data['per_page'] ) : 20;
$max_pages = max( 1, (int) ceil( $total / $per_page ) );
$view      = isset( $view ) && in_array( $view, array( 'active', 'history' ), true ) ? $view : 'active';
$is_history = 'history' === $view;
?>
<div class="wrap ai-alt-wrap">
	<h1><?php esc_html_e( 'Dynamic Alt Tags Queue', 'dynamic-alt-tags' ); ?></h1>

	<h2 class="nav-tab-wrapper">
		<a class="nav-tab <?php echo $is_history ? '' : 'nav-tab-active'; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ai-alt-text-queue', 'view' => 'active' ), admin_url( 'upload.php' ) ) ); ?>"><?php esc_html_e( 'Active Queue', 'dynamic-alt-tags' ); ?></a>
		<a class="nav-tab <?php echo $is_history ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ai-alt-text-queue', 'view' => 'history' ), admin_url( 'upload.php' ) ) ); ?>"><?php esc_html_e( 'History', 'dynamic-alt-tags' ); ?></a>
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

	<?php if ( isset( $_GET['notice'] ) && 'queue_error' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<?php $queue_error_msg = isset( $_GET['queue_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['queue_msg'] ) ) ) : __( 'Unable to apply bulk action.', 'dynamic-alt-tags' ); ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $queue_error_msg ); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php
		printf(
			$is_history ? esc_html__( 'Total history items: %d', 'dynamic-alt-tags' ) : esc_html__( 'Total queue items: %d', 'dynamic-alt-tags' ),
			$total
		);
		?>
	</p>

	<?php if ( ! $is_history ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
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

	<table class="widefat striped ai-alt-table">
		<thead>
			<tr>
				<?php if ( ! $is_history ) : ?>
					<td class="manage-column check-column">
						<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All', 'dynamic-alt-tags' ); ?></label>
						<input id="cb-select-all-1" type="checkbox" class="ai-alt-select-all" />
					</td>
					<?php endif; ?>
					<th><?php esc_html_e( 'Image', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Status', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Confidence', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Existing Alt', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Suggested Alt', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="<?php echo $is_history ? '6' : '7'; ?>"><?php esc_html_e( 'No queue items found.', 'dynamic-alt-tags' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
					<?php
					$row_id        = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
					$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
					$status        = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
					$confidence    = isset( $row['confidence'] ) ? (float) $row['confidence'] : 0.0;
					$suggested     = isset( $row['suggested_alt'] ) ? (string) $row['suggested_alt'] : '';
					$final_alt     = isset( $row['final_alt'] ) ? (string) $row['final_alt'] : '';
						$display_alt   = $is_history && '' !== trim( $final_alt ) ? $final_alt : $suggested;
						$thumb         = $attachment_id ? wp_get_attachment_image( $attachment_id, array( 80, 80 ), false, array( 'style' => 'max-width:80px;height:auto;' ) ) : '';
						$image_url     = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
						$existing_alt  = $attachment_id ? get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) : '';
						$existing_alt  = is_string( $existing_alt ) ? trim( $existing_alt ) : '';
						?>
						<tr>
						<?php if ( ! $is_history ) : ?>
							<th scope="row" class="check-column">
								<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( (string) $row_id ); ?>"><?php esc_html_e( 'Select item', 'dynamic-alt-tags' ); ?></label>
								<input id="cb-select-<?php echo esc_attr( (string) $row_id ); ?>" type="checkbox" class="ai-alt-row-checkbox" name="selected_row_ids[]" value="<?php echo esc_attr( (string) $row_id ); ?>" />
							</th>
						<?php endif; ?>
						<td>
							<?php echo $thumb ? wp_kses_post( $thumb ) : esc_html__( 'N/A', 'dynamic-alt-tags' ); ?>
							<div>#<?php echo esc_html( (string) $attachment_id ); ?></div>
							</td>
							<td><code><?php echo esc_html( $status ); ?></code></td>
							<td><?php echo esc_html( number_format_i18n( $confidence, 2 ) ); ?></td>
							<td><?php echo '' !== $existing_alt ? esc_html( $existing_alt ) : esc_html__( 'None', 'dynamic-alt-tags' ); ?></td>
							<td>
								<?php if ( $is_history ) : ?>
									<?php echo esc_html( $display_alt ); ?>
							<?php else : ?>
								<input type="text" class="regular-text" name="bulk_final_alt[<?php echo esc_attr( (string) $row_id ); ?>]" value="<?php echo esc_attr( $display_alt ); ?>" />
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! $is_history ) : ?>
								<button class="button button-primary" type="submit" name="single_action" value="<?php echo esc_attr( 'approve|' . $row_id ); ?>"><?php esc_html_e( 'Approve', 'dynamic-alt-tags' ); ?></button>
								<button class="button" type="submit" name="single_action" value="<?php echo esc_attr( 'reject|' . $row_id ); ?>"><?php esc_html_e( 'Reject', 'dynamic-alt-tags' ); ?></button>
							<?php endif; ?>
							<?php if ( ! empty( $image_url ) ) : ?>
								<a class="button" href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Image', 'dynamic-alt-tags' ); ?></a>
							<?php endif; ?>
							<?php if ( ! $is_history ) : ?>
									<button class="button" type="submit" name="single_action" value="<?php echo esc_attr( 'skip|' . $row_id ); ?>"><?php esc_html_e( 'Skip Image', 'dynamic-alt-tags' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<?php if ( ! $is_history ) : ?>
					<td class="manage-column check-column">
						<label class="screen-reader-text" for="cb-select-all-2"><?php esc_html_e( 'Select All', 'dynamic-alt-tags' ); ?></label>
						<input id="cb-select-all-2" type="checkbox" class="ai-alt-select-all" />
					</td>
					<?php endif; ?>
					<th><?php esc_html_e( 'Image', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Status', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Confidence', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Existing Alt', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Suggested Alt', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
				</tr>
		</tfoot>
	</table>

	<?php if ( ! $is_history ) : ?>
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

		<?php if ( $max_pages > 1 ) : ?>
			<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
								'base'      => add_query_arg(
									array(
										'page'  => 'ai-alt-text-queue',
										'view'  => $view,
										'paged' => '%#%',
									),
									admin_url( 'upload.php' )
								),
							'format'    => '',
							'current'   => $page_num,
							'total'     => $max_pages,
							'prev_text' => __( '&laquo;', 'dynamic-alt-tags' ),
							'next_text' => __( '&raquo;', 'dynamic-alt-tags' ),
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
