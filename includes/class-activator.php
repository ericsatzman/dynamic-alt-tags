<?php
/**
 * Activation/deactivation handlers.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Activator {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'ai_alt_text_options';

	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_queue_table();
		self::set_default_options();
		add_filter( 'cron_schedules', array( __CLASS__, 'register_activation_schedules' ) );

		if ( ! wp_next_scheduled( WPAI_ALT_TEXT_CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'five_minutes', WPAI_ALT_TEXT_CRON_HOOK );
		}

		remove_filter( 'cron_schedules', array( __CLASS__, 'register_activation_schedules' ) );
	}

	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( WPAI_ALT_TEXT_CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, WPAI_ALT_TEXT_CRON_HOOK );
			$timestamp = wp_next_scheduled( WPAI_ALT_TEXT_CRON_HOOK );
		}
	}

	/**
	 * Register custom schedule needed during activation.
	 *
	 * @param array<string,array<string,mixed>> $schedules Existing schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_activation_schedules( $schedules ) {
		if ( ! isset( $schedules['five_minutes'] ) ) {
			$schedules['five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 Minutes', 'dynamic-alt-tags' ),
			);
		}

		return $schedules;
	}

	/**
	 * Create queue table.
	 *
	 * @return void
	 */
	private static function create_queue_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ai_alt_queue';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT(20) UNSIGNED NOT NULL,
			post_id BIGINT(20) UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			provider VARCHAR(50) NOT NULL DEFAULT 'cloudflare',
			raw_caption LONGTEXT NULL,
			suggested_alt TEXT NULL,
			final_alt TEXT NULL,
			confidence DECIMAL(5,2) NULL,
			error_code VARCHAR(100) NULL,
			error_message TEXT NULL,
			attempts INT UNSIGNED NOT NULL DEFAULT 0,
			locked_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attachment_provider (attachment_id, provider),
			KEY status_locked (status, locked_at),
			KEY attachment_id (attachment_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Set defaults.
	 *
	 * @return void
	 */
	private static function set_default_options() {
			$defaults = array(
				'provider'            => 'cloudflare',
				'cloudflare_account'  => '',
				'cloudflare_token'    => '',
					'worker_url'          => '',
					'use_url_mode'        => 0,
					'batch_size'          => 10,
					'min_confidence'      => 0.70,
					'auto_apply_new_uploads' => 0,
					'sync_title_from_alt' => 1,
					'allowed_roles'       => array( 'administrator' ),
					'overwrite_existing'  => 0,
					'require_review'      => 1,
				'keep_data_on_delete' => 0,
			);

		$current = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		update_option( self::OPTION_KEY, wp_parse_args( $current, $defaults ) );
	}
}
