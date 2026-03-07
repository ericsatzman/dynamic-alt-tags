<?php
/**
 * Settings page template.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap ai-alt-wrap">
	<h1><?php esc_html_e( 'Dynamic Alt Tags Settings', 'dynamic-alt-tags' ); ?></h1>
	<?php settings_errors( 'ai_alt_text_options_group' ); ?>

	<?php if ( isset( $_GET['notice'] ) && 'backfill_done' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
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

	<?php if ( isset( $_GET['notice'] ) && 'process_done' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
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

	<?php if ( isset( $_GET['notice'] ) && 'process_partial' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					esc_html__( 'Processing stopped early after %d items. Run Process Queue Now again to continue.', 'dynamic-alt-tags' ),
					isset( $_GET['processed'] ) ? absint( $_GET['processed'] ) : 0
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['notice'] ) && 'process_error' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<?php
		$process_msg_raw   = isset( $_GET['process_msg'] ) ? wp_unslash( $_GET['process_msg'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$process_error_msg = '' !== $process_msg_raw ? sanitize_text_field( rawurldecode( (string) $process_msg_raw ) ) : __( 'No items were processed.', 'dynamic-alt-tags' );
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $process_error_msg ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['notice'] ) && 'metrics_reset' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Metrics were reset successfully.', 'dynamic-alt-tags' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'ai_alt_text_options_group' );
		do_settings_sections( 'ai-alt-text-settings' );
		submit_button( __( 'Save Settings', 'dynamic-alt-tags' ) );
		?>
	</form>

	<hr />

	<?php
	$metrics = isset( $metrics ) && is_array( $metrics ) ? $metrics : array();
	$coverage = isset( $coverage ) && is_array( $coverage ) ? $coverage : array();

	$total_images            = isset( $coverage['total_images'] ) ? absint( $coverage['total_images'] ) : 0;
	$images_with_alt         = isset( $coverage['with_alt'] ) ? absint( $coverage['with_alt'] ) : 0;
	$images_without_alt      = isset( $coverage['without_alt'] ) ? absint( $coverage['without_alt'] ) : 0;
	$total_processed         = isset( $metrics['total_images_processed'] ) ? absint( $metrics['total_images_processed'] ) : 0;
	$success_count           = isset( $metrics['success_count'] ) ? absint( $metrics['success_count'] ) : 0;
	$failure_count           = isset( $metrics['failure_count'] ) ? absint( $metrics['failure_count'] ) : 0;
	$provider_call_count     = isset( $metrics['provider_call_count'] ) ? absint( $metrics['provider_call_count'] ) : 0;
	$total_processing_ms     = isset( $metrics['total_processing_time_ms'] ) ? (float) $metrics['total_processing_time_ms'] : 0.0;
	$total_provider_ms       = isset( $metrics['total_provider_latency_ms'] ) ? (float) $metrics['total_provider_latency_ms'] : 0.0;
	$last_processing_ms      = isset( $metrics['last_processing_time_ms'] ) ? (float) $metrics['last_processing_time_ms'] : 0.0;
	$last_provider_latency   = isset( $metrics['last_provider_latency_ms'] ) ? (float) $metrics['last_provider_latency_ms'] : 0.0;
	$average_processing_ms   = $total_processed > 0 ? $total_processing_ms / $total_processed : 0.0;
	$average_provider_ms     = $provider_call_count > 0 ? $total_provider_ms / $provider_call_count : 0.0;
	$last_processed_at       = isset( $metrics['last_processed_at'] ) ? sanitize_text_field( (string) $metrics['last_processed_at'] ) : '';
	$last_processed_display  = '';
	if ( '' !== $last_processed_at ) {
		$last_processed_display = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_processed_at );
		if ( ! is_string( $last_processed_display ) || '' === $last_processed_display ) {
			$last_processed_display = $last_processed_at;
		}
	}
	?>

	<h2><?php esc_html_e( 'Metrics', 'dynamic-alt-tags' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Live attachment coverage and cumulative processing metrics.', 'dynamic-alt-tags' ); ?></p>

	<div class="ai-alt-metrics-grid">
		<div class="ai-alt-metric-card">
			<strong><?php esc_html_e( 'Images on site', 'dynamic-alt-tags' ); ?></strong>
			<span><?php echo esc_html( number_format_i18n( $total_images ) ); ?></span>
		</div>
		<div class="ai-alt-metric-card">
			<strong><?php esc_html_e( 'Images with alt tags', 'dynamic-alt-tags' ); ?></strong>
			<span><?php echo esc_html( number_format_i18n( $images_with_alt ) ); ?></span>
		</div>
		<div class="ai-alt-metric-card">
			<strong><?php esc_html_e( 'Images without alt tags', 'dynamic-alt-tags' ); ?></strong>
			<span><?php echo esc_html( number_format_i18n( $images_without_alt ) ); ?></span>
		</div>
		<div class="ai-alt-metric-card">
			<strong><?php esc_html_e( 'Total images processed', 'dynamic-alt-tags' ); ?></strong>
			<span><?php echo esc_html( number_format_i18n( $total_processed ) ); ?></span>
		</div>
	</div>

	<table class="widefat striped ai-alt-metrics-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Success count', 'dynamic-alt-tags' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $success_count ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Failure count', 'dynamic-alt-tags' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $failure_count ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Average processing time', 'dynamic-alt-tags' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $average_processing_ms, 2 ) ); ?> ms</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Average provider latency', 'dynamic-alt-tags' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $average_provider_ms, 2 ) ); ?> ms</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last processing time', 'dynamic-alt-tags' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $last_processing_ms, 2 ) ); ?> ms</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last provider latency', 'dynamic-alt-tags' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $last_provider_latency, 2 ) ); ?> ms</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last processed at', 'dynamic-alt-tags' ); ?></th>
				<td><?php echo '' !== $last_processed_display ? esc_html( $last_processed_display ) : esc_html__( 'Not yet recorded', 'dynamic-alt-tags' ); ?></td>
			</tr>
		</tbody>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-alt-metrics-reset-form">
		<input type="hidden" name="action" value="ai_alt_reset_metrics" />
		<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
		<?php submit_button( __( 'Reset Metrics', 'dynamic-alt-tags' ), 'secondary', 'submit', false ); ?>
	</form>

	<h2><?php esc_html_e( 'Tools', 'dynamic-alt-tags' ); ?></h2>
	<p><?php esc_html_e( 'Backfill scans existing images with empty alt text and adds them to the queue.', 'dynamic-alt-tags' ); ?></p>
	<p class="description"><?php esc_html_e( 'Backfill now skips images that were already processed earlier.', 'dynamic-alt-tags' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right:8px;">
		<input type="hidden" name="action" value="ai_alt_run_backfill" />
		<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
		<?php submit_button( __( 'Run Backfill', 'dynamic-alt-tags' ), 'secondary', 'submit', false ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;" id="ai-alt-process-form">
		<input type="hidden" name="action" value="ai_alt_process_now" />
		<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
		<div class="ai-alt-progress-wrap" id="ai-alt-progress-wrap" hidden>
			<div class="ai-alt-progress-bar" id="ai-alt-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
		</div>
		<p class="description" id="ai-alt-progress-message" aria-live="polite"></p>
		<?php submit_button( __( 'Process Queue Now', 'dynamic-alt-tags' ), 'secondary', 'ai_alt_process_submit', false ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-left:8px;">
		<input type="hidden" name="action" value="ai_alt_test_connection" />
		<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
		<?php submit_button( __( 'Test Provider Connection', 'dynamic-alt-tags' ), 'secondary', 'submit', false ); ?>
	</form>
	<p class="description"><?php esc_html_e( 'Test Provider Connection runs both a baseline provider test and a latest queued image URL test (if a queued image exists).', 'dynamic-alt-tags' ); ?></p>

	<?php
	$connection_status = isset( $connection_status ) && is_array( $connection_status ) ? $connection_status : array();
	$connection_state  = isset( $connection_status['state'] ) ? sanitize_key( (string) $connection_status['state'] ) : 'unknown';
	$connection_title  = isset( $connection_status['title'] ) ? sanitize_text_field( (string) $connection_status['title'] ) : __( 'Not Checked', 'dynamic-alt-tags' );
	$connection_msg    = isset( $connection_status['message'] ) ? sanitize_text_field( (string) $connection_status['message'] ) : __( 'Run "Test Provider Connection" to verify connectivity.', 'dynamic-alt-tags' );
	$checked_at        = isset( $connection_status['checked_at'] ) ? sanitize_text_field( (string) $connection_status['checked_at'] ) : '';
	$queue_error       = isset( $connection_status['queue_error'] ) ? sanitize_text_field( (string) $connection_status['queue_error'] ) : '';
	$show_status       = isset( $_GET['notice'] ) && 'provider_test' === sanitize_key( wp_unslash( $_GET['notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$notice_cls        = 'notice-info';
	if ( 'ok' === $connection_state ) {
		$notice_cls = 'notice-success';
	} elseif ( 'error' === $connection_state ) {
		$notice_cls = 'notice-error';
	} elseif ( 'warning' === $connection_state ) {
		$notice_cls = 'notice-warning';
	}
	?>
	<?php if ( $show_status ) : ?>
		<div class="ai-alt-connection-status ai-alt-connection-status-<?php echo esc_attr( $connection_state ); ?> notice <?php echo esc_attr( $notice_cls ); ?> is-dismissible">
			<h2><?php esc_html_e( 'Connection Status', 'dynamic-alt-tags' ); ?></h2>
			<p><strong><?php echo esc_html( $connection_title ); ?></strong></p>
			<p><?php echo esc_html( $connection_msg ); ?></p>
			<p class="description">
				<?php
				printf(
					/* translators: %s date time */
					esc_html__( 'Last checked: %s', 'dynamic-alt-tags' ),
					esc_html( $checked_at )
				);
				?>
			</p>
			<?php if ( '' !== $queue_error ) : ?>
				<p class="ai-alt-connection-detail"><strong><?php esc_html_e( 'Latest queue failure:', 'dynamic-alt-tags' ); ?></strong> <?php echo esc_html( $queue_error ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
