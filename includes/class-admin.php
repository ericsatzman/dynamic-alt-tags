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
			'manage_options',
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
		$allowed_hooks = array( 'upload.php', 'post.php', 'post-new.php' );
		if ( false === strpos( $hook_suffix, 'ai-alt-text' ) && ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'dynamic-alt-tags-admin',
			WPAI_ALT_TEXT_URL . 'assets/admin.css',
			array(),
			file_exists( WPAI_ALT_TEXT_DIR . 'assets/admin.css' ) ? (string) filemtime( WPAI_ALT_TEXT_DIR . 'assets/admin.css' ) : WPAI_ALT_TEXT_VERSION
		);

		wp_enqueue_script(
			'dynamic-alt-tags-admin',
			WPAI_ALT_TEXT_URL . 'assets/admin.js',
			array(),
			file_exists( WPAI_ALT_TEXT_DIR . 'assets/admin.js' ) ? (string) filemtime( WPAI_ALT_TEXT_DIR . 'assets/admin.js' ) : WPAI_ALT_TEXT_VERSION,
			true
		);

		wp_localize_script(
			'dynamic-alt-tags-admin',
			'aiAltAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'processNowNonce' => wp_create_nonce( 'ai_alt_process_now_ajax' ),
				'uploadActionNonce' => wp_create_nonce( 'ai_alt_upload_action_ajax' ),
				'i18n' => array(
					'processing' => __( 'Processing queue...', 'dynamic-alt-tags' ),
					'success'    => __( 'Manual processing finished. %d items processed.', 'dynamic-alt-tags' ),
					'error'      => __( 'Queue processing failed. Please try again.', 'dynamic-alt-tags' ),
					'selectUploadAction' => __( 'Please choose an action first.', 'dynamic-alt-tags' ),
					'customAltRequired'  => __( 'Enter custom alt text before applying.', 'dynamic-alt-tags' ),
					'uploadActionFailed' => __( 'Unable to apply upload action. Please try again.', 'dynamic-alt-tags' ),
				),
			)
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dynamic-alt-tags' ) );
		}

		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$view     = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'active';
		$view     = in_array( $view, array( 'active', 'history' ), true ) ? $view : 'active';
		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 20;
		$data     = $this->queue_repo->get_paginated( $page, $per_page, $status, $view );

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
	 * Process now via AJAX.
	 *
	 * @return void
	 */
	public function handle_process_now_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ),
				),
				403
			);
		}

		check_ajax_referer( 'ai_alt_process_now_ajax' );

		$options   = $this->settings->get_options();
		$processed = $this->processor->process_batch( isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 10 );

		wp_send_json_success(
			array(
				'processed' => $processed,
			)
		);
	}

	/**
	 * Test provider connection.
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$provider = new WPAI_Alt_Text_Provider_Cloudflare( $this->settings );
		$result   = $provider->generate_caption(
			'https://s.w.org/style/images/about/WordPress-logotype-wmark.png',
			array(
				'attachment_title' => 'WordPress logo',
				'post_title'       => 'Provider test',
			)
		);

		$status  = 'success';
		$message = __( 'Provider connection succeeded.', 'dynamic-alt-tags' );

		if ( is_wp_error( $result ) ) {
			$status  = 'error';
			$message = sprintf(
				/* translators: %s error message */
				__( 'Provider connection failed: %s', 'dynamic-alt-tags' ),
				$result->get_error_message()
			);
		} elseif ( ! is_array( $result ) || empty( $result['caption'] ) ) {
			$status  = 'error';
			$message = __( 'Provider responded, but did not return a usable caption.', 'dynamic-alt-tags' );
		}

		$redirect = add_query_arg(
			array(
				'page'        => 'ai-alt-text-settings',
				'notice'      => 'provider_test',
				'test_status' => rawurlencode( $status ),
				'test_msg'    => rawurlencode( $message ),
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_queue_action', 'ai_alt_queue_nonce' );

		$allowed_actions = array( 'approve', 'reject', 'skip' );
		$updated_count   = 0;

		$single_action = isset( $_POST['single_action'] ) ? sanitize_text_field( wp_unslash( $_POST['single_action'] ) ) : '';
		if ( '' !== $single_action ) {
			$parts = explode( '|', $single_action );
			if ( 2 !== count( $parts ) ) {
				wp_die( esc_html__( 'Invalid request.', 'dynamic-alt-tags' ) );
			}

			$action = sanitize_key( $parts[0] );
			$row_id = absint( $parts[1] );
			if ( ! $row_id || ! in_array( $action, $allowed_actions, true ) ) {
				wp_die( esc_html__( 'Invalid request.', 'dynamic-alt-tags' ) );
			}

			$alts = isset( $_POST['bulk_final_alt'] ) && is_array( $_POST['bulk_final_alt'] ) ? wp_unslash( $_POST['bulk_final_alt'] ) : array();
			$alt  = isset( $alts[ $row_id ] ) ? sanitize_text_field( (string) $alts[ $row_id ] ) : '';
			$this->apply_queue_action( $row_id, $action, $alt );
			$updated_count = 1;
		} else {
			$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
			if ( '' === $bulk_action || '-1' === $bulk_action ) {
				$bulk_action = isset( $_POST['bulk_action2'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action2'] ) ) : '';
			}

			if ( ! in_array( $bulk_action, $allowed_actions, true ) ) {
				wp_die( esc_html__( 'Invalid request.', 'dynamic-alt-tags' ) );
			}

			$selected_ids = isset( $_POST['selected_row_ids'] ) && is_array( $_POST['selected_row_ids'] ) ? $_POST['selected_row_ids'] : array();
			$selected_ids = array_values( array_filter( array_map( 'absint', $selected_ids ) ) );
			if ( empty( $selected_ids ) ) {
				wp_die( esc_html__( 'No queue items selected.', 'dynamic-alt-tags' ) );
			}

			$alts = isset( $_POST['bulk_final_alt'] ) && is_array( $_POST['bulk_final_alt'] ) ? wp_unslash( $_POST['bulk_final_alt'] ) : array();
			foreach ( $selected_ids as $row_id ) {
				$alt = isset( $alts[ $row_id ] ) ? sanitize_text_field( (string) $alts[ $row_id ] ) : '';
				$this->apply_queue_action( $row_id, $bulk_action, $alt );
				++$updated_count;
			}
		}

		$redirect = add_query_arg(
			array(
				'page'    => 'ai-alt-text-queue',
				'notice'  => 'queue_updated',
				'updated' => $updated_count,
			),
			admin_url( 'upload.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Apply one queue action.
	 *
	 * @param int    $row_id Queue row ID.
	 * @param string $action Action key.
	 * @param string $alt Alt text.
	 * @return void
	 */
	private function apply_queue_action( $row_id, $action, $alt ) {
		$row_id = absint( $row_id );
		if ( ! $row_id ) {
			return;
		}

		if ( 'approve' === $action ) {
			if ( '' === trim( (string) $alt ) ) {
				$row = $this->queue_repo->get_row( $row_id );
				$alt = is_array( $row ) && isset( $row['suggested_alt'] ) ? (string) $row['suggested_alt'] : '';
			}
			$this->processor->approve_row( $row_id, $alt );
		} elseif ( 'reject' === $action ) {
			$row = $this->queue_repo->get_row( $row_id );
			if ( is_array( $row ) && ! empty( $row['attachment_id'] ) ) {
				update_post_meta( absint( $row['attachment_id'] ), '_wp_attachment_image_alt', '' );
			}
			$this->queue_repo->mark_final( $row_id, 'rejected', '' );
		} elseif ( 'skip' === $action ) {
			$row = $this->queue_repo->get_row( $row_id );
			if ( is_array( $row ) && ! empty( $row['attachment_id'] ) ) {
				update_post_meta( absint( $row['attachment_id'] ), '_wp_attachment_image_alt', '' );
				update_post_meta( absint( $row['attachment_id'] ), '_ai_alt_review_required', 0 );
			}
			$this->queue_repo->mark_final( $row_id, 'skipped', '' );
		}
	}
}
