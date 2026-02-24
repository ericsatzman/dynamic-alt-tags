<?php
/**
 * Plugin bootstrap.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WPAI_ALT_TEXT_DIR . 'includes/class-settings.php';
require_once WPAI_ALT_TEXT_DIR . 'includes/class-queue-repo.php';
require_once WPAI_ALT_TEXT_DIR . 'includes/class-provider-interface.php';
require_once WPAI_ALT_TEXT_DIR . 'includes/class-provider-cloudflare.php';
require_once WPAI_ALT_TEXT_DIR . 'includes/class-alt-generator.php';
require_once WPAI_ALT_TEXT_DIR . 'includes/class-logger.php';
require_once WPAI_ALT_TEXT_DIR . 'includes/class-processor.php';
require_once WPAI_ALT_TEXT_DIR . 'includes/class-admin.php';
require_once WPAI_ALT_TEXT_DIR . 'includes/class-rest.php';

class WPAI_Alt_Text_Plugin {

	/**
	 * Instance.
	 *
	 * @var WPAI_Alt_Text_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings.
	 *
	 * @var WPAI_Alt_Text_Settings
	 */
	private $settings;

	/**
	 * Queue.
	 *
	 * @var WPAI_Alt_Text_Queue_Repo
	 */
	private $queue_repo;

	/**
	 * Processor.
	 *
	 * @var WPAI_Alt_Text_Processor
	 */
	private $processor;

	/**
	 * Admin.
	 *
	 * @var WPAI_Alt_Text_Admin
	 */
	private $admin;

	/**
	 * REST.
	 *
	 * @var WPAI_Alt_Text_REST
	 */
	private $rest;

	/**
	 * Get singleton.
	 *
	 * @return WPAI_Alt_Text_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings   = new WPAI_Alt_Text_Settings();
		$this->queue_repo = new WPAI_Alt_Text_Queue_Repo();
		$logger           = new WPAI_Alt_Text_Logger();
		$generator        = new WPAI_Alt_Text_Generator();
		$provider         = new WPAI_Alt_Text_Provider_Cloudflare( $this->settings );

		$this->processor = new WPAI_Alt_Text_Processor( $this->settings, $this->queue_repo, $provider, $generator, $logger );
		$this->admin     = new WPAI_Alt_Text_Admin( $this->settings, $this->queue_repo, $this->processor );
		$this->rest      = new WPAI_Alt_Text_REST( $this->queue_repo, $this->processor );

		$this->register_hooks();
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'init', array( $this, 'ensure_cron_scheduled' ), 20 );
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		add_action( WPAI_ALT_TEXT_CRON_HOOK, array( $this, 'run_cron' ) );
		add_action( 'add_attachment', array( $this, 'maybe_queue_attachment' ) );

		add_action( 'admin_menu', array( $this->admin, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
		add_action( 'admin_post_ai_alt_run_backfill', array( $this->admin, 'handle_run_backfill' ) );
		add_action( 'admin_post_ai_alt_process_now', array( $this->admin, 'handle_process_now' ) );
		add_action( 'admin_post_ai_alt_queue_action', array( $this->admin, 'handle_queue_action' ) );

		add_action( 'rest_api_init', array( $this->rest, 'register_routes' ) );
	}

	/**
	 * i18n.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'dynamic-alt-tags', false, dirname( plugin_basename( WPAI_ALT_TEXT_FILE ) ) . '/languages' );
	}

	/**
	 * Add 5 minute cron.
	 *
	 * @param array<string,array<string,mixed>> $schedules Schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['five_minutes'] ) ) {
			$schedules['five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 Minutes', 'dynamic-alt-tags' ),
			);
		}

		return $schedules;
	}

	/**
	 * Cron callback.
	 *
	 * @return void
	 */
	public function run_cron() {
		$options = $this->settings->get_options();
		$limit   = isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 10;
		$this->processor->process_batch( $limit );
	}

	/**
	 * Ensure recurring event exists after custom schedule registration.
	 *
	 * @return void
	 */
	public function ensure_cron_scheduled() {
		if ( ! wp_next_scheduled( WPAI_ALT_TEXT_CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'five_minutes', WPAI_ALT_TEXT_CRON_HOOK );
		}
	}

	/**
	 * Queue new upload.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function maybe_queue_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		if ( 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			return;
		}

		$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$options      = $this->settings->get_options();
		$overwrite    = ! empty( $options['overwrite_existing'] );

		if ( ! $overwrite && is_string( $existing_alt ) && '' !== trim( $existing_alt ) ) {
			return;
		}

		$this->queue_repo->enqueue( $attachment_id, 0 );
	}
}
