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
?>
<div class="wrap ai-alt-wrap">
	<h1><?php esc_html_e( 'Dynamic Alt Tags Queue', 'dynamic-alt-tags' ); ?></h1>

	<?php if ( isset( $_GET['notice'] ) && 'queue_updated' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Queue item updated.', 'dynamic-alt-tags' ); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php
		printf(
			esc_html__( 'Total queue items: %d', 'dynamic-alt-tags' ),
			$total
		);
		?>
	</p>

	<table class="widefat striped ai-alt-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Image', 'dynamic-alt-tags' ); ?></th>
				<th><?php esc_html_e( 'Status', 'dynamic-alt-tags' ); ?></th>
				<th><?php esc_html_e( 'Confidence', 'dynamic-alt-tags' ); ?></th>
				<th><?php esc_html_e( 'Suggested Alt', 'dynamic-alt-tags' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No queue items found.', 'dynamic-alt-tags' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$row_id        = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
					$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
					$status        = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
					$confidence    = isset( $row['confidence'] ) ? (float) $row['confidence'] : 0.0;
					$suggested     = isset( $row['suggested_alt'] ) ? (string) $row['suggested_alt'] : '';
					$thumb         = $attachment_id ? wp_get_attachment_image( $attachment_id, array( 80, 80 ), false, array( 'style' => 'max-width:80px;height:auto;' ) ) : '';
					?>
					<tr>
						<td>
							<?php echo $thumb ? wp_kses_post( $thumb ) : esc_html__( 'N/A', 'dynamic-alt-tags' ); ?>
							<div>#<?php echo esc_html( (string) $attachment_id ); ?></div>
						</td>
						<td><code><?php echo esc_html( $status ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( $confidence, 2 ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="ai_alt_queue_action" />
								<input type="hidden" name="row_id" value="<?php echo esc_attr( (string) $row_id ); ?>" />
								<?php wp_nonce_field( 'ai_alt_queue_action_' . $row_id, 'ai_alt_queue_nonce' ); ?>
								<input type="text" class="regular-text" name="final_alt" value="<?php echo esc_attr( $suggested ); ?>" />
						</td>
						<td>
								<button class="button button-primary" type="submit" name="queue_action" value="approve"><?php esc_html_e( 'Approve', 'dynamic-alt-tags' ); ?></button>
								<button class="button" type="submit" name="queue_action" value="reject"><?php esc_html_e( 'Reject', 'dynamic-alt-tags' ); ?></button>
								<button class="button" type="submit" name="queue_action" value="skip"><?php esc_html_e( 'Skip decorative', 'dynamic-alt-tags' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

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
