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
					esc_html__( 'Backfill complete. %d images were queued.', 'dynamic-alt-tags' ),
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

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right:8px;">
		<input type="hidden" name="action" value="ai_alt_run_backfill" />
		<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
		<?php submit_button( __( 'Run Backfill', 'dynamic-alt-tags' ), 'secondary', 'submit', false ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
		<input type="hidden" name="action" value="ai_alt_process_now" />
		<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
		<?php submit_button( __( 'Process Queue Now', 'dynamic-alt-tags' ), 'secondary', 'submit', false ); ?>
	</form>
</div>
