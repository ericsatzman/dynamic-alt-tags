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
	<?php if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'dynamic-alt-tags' ); ?></p>
		</div>
	<?php endif; ?>

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

	<form method="post" action="options.php">
		<?php
		settings_fields( 'ai_alt_text_options_group' );
		do_settings_sections( 'ai-alt-text-settings' );
		submit_button( __( 'Save Settings', 'dynamic-alt-tags' ) );
		?>
	</form>

	<hr />

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
