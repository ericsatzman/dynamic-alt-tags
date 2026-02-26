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

	<?php if ( isset( $_GET['notice'] ) && 'provider_test' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<?php
		$test_status = isset( $_GET['test_status'] ) ? sanitize_key( rawurldecode( wp_unslash( $_GET['test_status'] ) ) ) : 'success';
		$test_msg    = isset( $_GET['test_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['test_msg'] ) ) ) : '';
		$notice_cls  = 'success' === $test_status ? 'notice-success' : 'notice-error';
		?>
		<div class="notice <?php echo esc_attr( $notice_cls ); ?> is-dismissible">
			<p><?php echo esc_html( $test_msg ); ?></p>
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
</div>
