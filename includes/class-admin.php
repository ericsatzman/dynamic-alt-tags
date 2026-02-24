<?php
/**
 * Admin UI and actions.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Admin {

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
	 * Constructor.
	 *
	 * @param WPAI_Alt_Text_Settings   $settings Settings.
	 * @param WPAI_Alt_Text_Queue_Repo $queue_repo Queue.
	 * @param WPAI_Alt_Text_Processor  $processor Processor.
	 */
	public function __construct( $settings, $queue_repo, $processor ) {
		$this->settings   = $settings;
		$this->queue_repo = $queue_repo;
		$this->processor  = $processor;
	}

	/**
	 * Register menus.
	 *
	 * @return void
	 */
	public function register_menus() {
		add_submenu_page(
			'upload.php',
			__( 'Dynamic Alt Tags Settings', 'dynamic-alt-tags' ),
			__( 'Dynamic Alt Tags Settings', 'dynamic-alt-tags' ),
			'manage_options',
			'ai-alt-text-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'upload.php',
			__( 'Dynamic Alt Tags Queue', 'dynamic-alt-tags' ),
			__( 'Dynamic Alt Tags Queue', 'dynamic-alt-tags' ),
			'upload_files',
			'ai-alt-text-queue',
			array( $this, 'render_queue_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'ai-alt-text' ) ) {
			return;
		}

		wp_enqueue_style(
			'dynamic-alt-tags-admin',
			WPAI_ALT_TEXT_URL . 'assets/admin.css',
			array(),
			WPAI_ALT_TEXT_VERSION
		);

		wp_enqueue_script(
			'dynamic-alt-tags-admin',
			WPAI_ALT_TEXT_URL . 'assets/admin.js',
			array(),
			WPAI_ALT_TEXT_VERSION,
			true
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dynamic-alt-tags' ) );
		}

		include WPAI_ALT_TEXT_DIR . 'admin/views-page-settings.php';
	}

	/**
	 * Render queue page.
	 *
	 * @return void
	 */
	public function render_queue_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dynamic-alt-tags' ) );
		}

		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 20;
		$data     = $this->queue_repo->get_paginated( $page, $per_page, $status );

		include WPAI_ALT_TEXT_DIR . 'admin/views-page-queue.php';
	}

	/**
	 * Run backfill.
	 *
	 * @return void
	 */
	public function handle_run_backfill() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$count = $this->queue_repo->enqueue_missing_alts( 500 );

		$redirect = add_query_arg(
			array(
				'page'    => 'ai-alt-text-settings',
				'notice'  => 'backfill_done',
				'enqueued' => $count,
			),
			admin_url( 'upload.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process now.
	 *
	 * @return void
	 */
	public function handle_process_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$options   = $this->settings->get_options();
		$processed = $this->processor->process_batch( isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 10 );

		$redirect = add_query_arg(
			array(
				'page'      => 'ai-alt-text-settings',
				'notice'    => 'process_done',
				'processed' => $processed,
			),
			admin_url( 'upload.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle queue actions.
	 *
	 * @return void
	 */
	public function handle_queue_action() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		$row_id = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$action = isset( $_POST['queue_action'] ) ? sanitize_key( wp_unslash( $_POST['queue_action'] ) ) : '';

		if ( ! $row_id || ! in_array( $action, array( 'approve', 'reject', 'skip' ), true ) ) {
			wp_die( esc_html__( 'Invalid request.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_queue_action_' . $row_id, 'ai_alt_queue_nonce' );

		if ( 'approve' === $action ) {
			$alt = isset( $_POST['final_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['final_alt'] ) ) : '';
			$this->processor->approve_row( $row_id, $alt );
		} elseif ( 'reject' === $action ) {
			$this->queue_repo->mark_final( $row_id, 'rejected', '' );
		} elseif ( 'skip' === $action ) {
			$row = $this->queue_repo->get_row( $row_id );
			if ( is_array( $row ) && ! empty( $row['attachment_id'] ) ) {
				update_post_meta( absint( $row['attachment_id'] ), '_wp_attachment_image_alt', '' );
				update_post_meta( absint( $row['attachment_id'] ), '_ai_alt_review_required', 0 );
			}
			$this->queue_repo->mark_final( $row_id, 'skipped', '' );
		}

		$redirect = add_query_arg(
			array(
				'page'   => 'ai-alt-text-queue',
				'notice' => 'queue_updated',
			),
			admin_url( 'upload.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
