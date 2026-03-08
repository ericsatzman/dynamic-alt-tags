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
$view         = isset( $view ) && in_array( $view, array( 'dashboard', 'active', 'history', 'no_alt', 'search' ), true ) ? $view : 'dashboard';
$is_dashboard = 'dashboard' === $view;
$is_history   = 'history' === $view;
$is_no_alt    = 'no_alt' === $view;
$is_search    = 'search' === $view;
$is_active    = ! $is_dashboard && ! $is_history && ! $is_no_alt && ! $is_search;
$refresh_args = array(
	'page' => 'ai-alt-text-queue',
	'view' => $view,
);
if ( '' !== $status ) {
	$refresh_args['status'] = $status;
}
if ( $page_num > 1 ) {
	$refresh_args['paged'] = $page_num;
}

$metrics = isset( $metrics ) && is_array( $metrics ) ? $metrics : array();
$coverage = isset( $coverage ) && is_array( $coverage ) ? $coverage : array();

$total_images_dashboard   = isset( $coverage['total_images'] ) ? absint( $coverage['total_images'] ) : 0;
$images_with_alt          = isset( $coverage['with_alt'] ) ? absint( $coverage['with_alt'] ) : 0;
$images_without_alt       = isset( $coverage['without_alt'] ) ? absint( $coverage['without_alt'] ) : 0;
$total_processed          = isset( $metrics['total_images_processed'] ) ? absint( $metrics['total_images_processed'] ) : 0;
$success_count            = isset( $metrics['success_count'] ) ? absint( $metrics['success_count'] ) : 0;
$failure_count            = isset( $metrics['failure_count'] ) ? absint( $metrics['failure_count'] ) : 0;
$provider_call_count      = isset( $metrics['provider_call_count'] ) ? absint( $metrics['provider_call_count'] ) : 0;
$total_processing_ms      = isset( $metrics['total_processing_time_ms'] ) ? (float) $metrics['total_processing_time_ms'] : 0.0;
$total_provider_ms        = isset( $metrics['total_provider_latency_ms'] ) ? (float) $metrics['total_provider_latency_ms'] : 0.0;
$last_processing_ms       = isset( $metrics['last_processing_time_ms'] ) ? (float) $metrics['last_processing_time_ms'] : 0.0;
$last_provider_latency    = isset( $metrics['last_provider_latency_ms'] ) ? (float) $metrics['last_provider_latency_ms'] : 0.0;
$average_processing_ms    = $total_processed > 0 ? $total_processing_ms / $total_processed : 0.0;
$average_provider_ms      = $provider_call_count > 0 ? $total_provider_ms / $provider_call_count : 0.0;
$last_processed_at        = isset( $metrics['last_processed_at'] ) ? sanitize_text_field( (string) $metrics['last_processed_at'] ) : '';
$last_processed_display   = '';
if ( '' !== $last_processed_at ) {
	$last_processed_display = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_processed_at );
	if ( ! is_string( $last_processed_display ) || '' === $last_processed_display ) {
		$last_processed_display = $last_processed_at;
	}
}
?>
<div class="wrap ai-alt-wrap ai-alt-queue-page ai-alt-queue-view-<?php echo esc_attr( $view ); ?>">
	<h1><?php esc_html_e( 'Dynamic Alt Tags', 'dynamic-alt-tags' ); ?></h1>
	<div class="ai-alt-queue-shell">
	<div class="ai-alt-queue-header-bar">
		<h2 class="nav-tab-wrapper ai-alt-queue-tabs">
			<a class="nav-tab <?php echo $is_dashboard ? 'nav-tab-active' : ''; ?>" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'ai-alt-text-queue',
						'view' => 'dashboard',
					),
					admin_url( 'upload.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Dashboard', 'dynamic-alt-tags' ); ?></a>
			<a class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>" href="
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
			<a class="nav-tab <?php echo $is_search ? 'nav-tab-active' : ''; ?>" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'ai-alt-text-queue',
						'view' => 'search',
					),
					admin_url( 'upload.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Search', 'dynamic-alt-tags' ); ?></a>
		</h2>
		<?php if ( $is_active ) : ?>
			<div class="ai-alt-queue-process-top">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ai_alt_run_backfill_queue" />
					<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Run Backfill', 'dynamic-alt-tags' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ai-alt-queue-process-form">
					<input type="hidden" name="action" value="ai_alt_process_now_queue" />
					<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate Alt Text', 'dynamic-alt-tags' ); ?></button>
				</form>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( $refresh_args, admin_url( 'upload.php' ) ) ); ?>"><?php esc_html_e( 'Refresh', 'dynamic-alt-tags' ); ?></a>
			</div>
		<?php endif; ?>
	</div>

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

	<?php if ( ! $is_dashboard ) : ?>
		<p>
			<?php
			if ( $is_history ) {
				echo esc_html( sprintf( __( 'Total history items: %d', 'dynamic-alt-tags' ), $total ) );
			} elseif ( $is_no_alt ) {
				echo esc_html( sprintf( __( 'Total images with no alt text: %d', 'dynamic-alt-tags' ), $total ) );
			} elseif ( $is_search ) {
				esc_html_e( 'Search images by title, filename, alt text, or attachment ID.', 'dynamic-alt-tags' );
			} else {
				echo esc_html( sprintf( __( 'Total queue items: %1$d out of %2$d images', 'dynamic-alt-tags' ), $total, $total_images ) );
			}
			?>
		</p>
	<?php endif; ?>

	<?php if ( $is_dashboard ) : ?>
		<div id="ai-alt-settings-panel-metrics">
			<h2><?php esc_html_e( 'Dashboard', 'dynamic-alt-tags' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Live attachment coverage and cumulative processing metrics.', 'dynamic-alt-tags' ); ?></p>

			<div class="ai-alt-metrics-grid">
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Images on site', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-total-images"><?php echo esc_html( number_format_i18n( $total_images_dashboard ) ); ?></span>
				</div>
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Images with alt tags', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-images-with-alt"><?php echo esc_html( number_format_i18n( $images_with_alt ) ); ?></span>
				</div>
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Images without alt tags', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-images-without-alt"><?php echo esc_html( number_format_i18n( $images_without_alt ) ); ?></span>
				</div>
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Total images processed', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-total-processed"><?php echo esc_html( number_format_i18n( $total_processed ) ); ?></span>
				</div>
			</div>

			<table class="widefat striped ai-alt-metrics-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Success count', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-success-count"><?php echo esc_html( number_format_i18n( $success_count ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Failure count', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-failure-count"><?php echo esc_html( number_format_i18n( $failure_count ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Average processing time', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-average-processing"><?php echo esc_html( number_format_i18n( $average_processing_ms, 2 ) ); ?> ms</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Average provider latency', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-average-provider-latency"><?php echo esc_html( number_format_i18n( $average_provider_ms, 2 ) ); ?> ms</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last processing time', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-last-processing"><?php echo esc_html( number_format_i18n( $last_processing_ms, 2 ) ); ?> ms</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last provider latency', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-last-provider-latency"><?php echo esc_html( number_format_i18n( $last_provider_latency, 2 ) ); ?> ms</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last processed at', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-last-processed-at"><?php echo '' !== $last_processed_display ? esc_html( $last_processed_display ) : esc_html__( 'Not yet recorded', 'dynamic-alt-tags' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

	<?php elseif ( $is_search ) : ?>
		<div class="ai-alt-search-controls">
			<label class="screen-reader-text" for="ai-alt-media-search-input"><?php esc_html_e( 'Search media library', 'dynamic-alt-tags' ); ?></label>
			<input type="search" id="ai-alt-media-search-input" class="regular-text ai-alt-search-input" placeholder="<?php echo esc_attr__( 'Search by name, filename, alt text, or ID...', 'dynamic-alt-tags' ); ?>" autocomplete="off" />
		</div>
		<p class="description" id="ai-alt-media-search-summary"><?php esc_html_e( 'Type at least 2 characters to search.', 'dynamic-alt-tags' ); ?></p>
		<table class="widefat striped ai-alt-table ai-alt-search-table" data-view="search">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Image', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Name', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'File', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Alt Text', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Queue Status', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Last Updated', 'dynamic-alt-tags' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
				</tr>
			</thead>
			<tbody id="ai-alt-search-results">
				<tr><td colspan="7"><?php esc_html_e( 'No matching images found.', 'dynamic-alt-tags' ); ?></td></tr>
			</tbody>
		</table>

	<?php else : ?>
		<?php if ( ! $is_history && ! $is_no_alt ) : ?>
			<div class="ai-alt-progress-wrap" id="ai-alt-queue-progress-wrap" hidden>
				<div class="ai-alt-progress-bar" id="ai-alt-queue-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
			</div>
			<p class="description" id="ai-alt-queue-progress-message" aria-live="polite"></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-alt-queue-form">
				<input type="hidden" name="action" value="ai_alt_queue_action" />
				<input type="hidden" name="return_view" value="active" />
				<?php wp_nonce_field( 'ai_alt_queue_action', 'ai_alt_queue_nonce' ); ?>
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<label class="screen-reader-text" for="bulk-action-selector-top"><?php esc_html_e( 'Select bulk action', 'dynamic-alt-tags' ); ?></label>
						<select name="bulk_action" id="bulk-action-selector-top">
							<option value="-1"><?php esc_html_e( 'Bulk actions', 'dynamic-alt-tags' ); ?></option>
							<option value="approve"><?php esc_html_e( 'Approve', 'dynamic-alt-tags' ); ?></option>
							<option value="skip"><?php esc_html_e( 'Skip Image', 'dynamic-alt-tags' ); ?></option>
							<option value="process"><?php esc_html_e( 'Generate Alt Text', 'dynamic-alt-tags' ); ?></option>
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
					<?php elseif ( $is_history ) : ?>
						<th><?php esc_html_e( 'Status', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Alt Text', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Processed On', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
					<?php else : ?>
						<th><?php esc_html_e( 'Status', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Confidence', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Suggested Alt Text', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody id="ai-alt-queue-tbody">
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="<?php echo $is_no_alt ? '4' : ( $is_history ? '5' : '6' ); ?>"><?php esc_html_e( 'No queue items found.', 'dynamic-alt-tags' ); ?></td>
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
						<option value="skip"><?php esc_html_e( 'Skip Image', 'dynamic-alt-tags' ); ?></option>
						<option value="process"><?php esc_html_e( 'Generate Alt Text', 'dynamic-alt-tags' ); ?></option>
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
	<?php endif; ?>
	</div>
</div>
