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
	 * Option key for latest connection check.
	 *
	 * @var string
	 */
	const CONNECTION_STATUS_OPTION_KEY = 'ai_alt_text_connection_status';


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
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'processNowNonce'    => wp_create_nonce( 'ai_alt_process_now_ajax' ),
				'queueProcessNonce'  => wp_create_nonce( 'ai_alt_queue_process_ajax' ),
				'queueLoadMoreNonce' => wp_create_nonce( 'ai_alt_queue_load_more_ajax' ),
				'queueAddNoAltNonce' => wp_create_nonce( 'ai_alt_queue_add_no_alt_ajax' ),
				'uploadActionNonce'  => wp_create_nonce( 'ai_alt_upload_action_ajax' ),
				'i18n'               => array(
					'processing'         => __( 'Processing queue...', 'dynamic-alt-tags' ),
					'success'            => __( 'Manual processing finished. %d items processed.', 'dynamic-alt-tags' ),
					'error'              => __( 'Queue processing failed. Please try again.', 'dynamic-alt-tags' ),
					'partial'            => __( 'Processing stopped early after %d items. You can run it again to continue.', 'dynamic-alt-tags' ),
					'rowProcessing'      => __( 'Processing image...', 'dynamic-alt-tags' ),
					'rowSuccess'         => __( 'Image successfully processed', 'dynamic-alt-tags' ),
					'rowError'           => __( 'Image processing failed. Please try again.', 'dynamic-alt-tags' ),
					'loadingMore'        => __( 'Loading more...', 'dynamic-alt-tags' ),
					'loadMoreError'      => __( 'Unable to load more items. Please try again.', 'dynamic-alt-tags' ),
					'queueAddSuccess'    => __( 'Added to queue', 'dynamic-alt-tags' ),
					'queueAddError'      => __( 'Unable to add image to queue.', 'dynamic-alt-tags' ),
					'selectUploadAction' => __( 'Please choose an action first.', 'dynamic-alt-tags' ),
					'customAltRequired'  => __( 'Enter custom alt text before applying.', 'dynamic-alt-tags' ),
					'uploadActionFailed' => __( 'Unable to apply upload action. Please try again.', 'dynamic-alt-tags' ),
					'confirmSkip'        => __( 'Skip this image and move it to History?', 'dynamic-alt-tags' ),
					'confirmReject'      => __( 'Reject this generated alt text?', 'dynamic-alt-tags' ),
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

		$connection_status = $this->get_connection_status();

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

		$status       = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$view         = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'active';
		$view         = in_array( $view, array( 'active', 'history', 'no_alt' ), true ) ? $view : 'active';
		$page         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page     = 20;
		$data         = 'no_alt' === $view ? $this->queue_repo->get_no_alt_paginated( $page, $per_page ) : $this->queue_repo->get_paginated( $page, $per_page, $status, $view );
		$total_images = $this->queue_repo->get_total_no_alt_images();

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
				'page'     => 'ai-alt-text-settings',
				'notice'   => 'backfill_done',
				'enqueued' => $count,
			),
			admin_url( 'upload.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Run backfill from Queue page.
	 *
	 * @return void
	 */
	public function handle_run_backfill_queue() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$count = $this->queue_repo->enqueue_missing_alts( 500 );

		$redirect = add_query_arg(
			array(
				'page'     => 'ai-alt-text-queue',
				'notice'   => 'queue_backfill_done',
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
		$before    = $this->queue_repo->get_active_status_counts();
		$processed = $this->processor->process_batch( isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 10 );
		$after     = $this->queue_repo->get_active_status_counts();

		if ( $processed > 0 ) {
			$redirect = add_query_arg(
				array(
					'page'      => 'ai-alt-text-settings',
					'notice'    => 'process_done',
					'processed' => $processed,
				),
				admin_url( 'upload.php' )
			);
		} else {
			$message  = $this->get_zero_processed_message( $before, $after );
			$redirect = add_query_arg(
				array(
					'page'        => 'ai-alt-text-settings',
					'notice'      => 'process_error',
					'process_msg' => rawurlencode( $message ),
				),
				admin_url( 'upload.php' )
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process now from Queue page.
	 *
	 * @return void
	 */
	public function handle_process_now_queue() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$options   = $this->settings->get_options();
		$before    = $this->queue_repo->get_active_status_counts();
		$processed = $this->processor->process_batch( isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 10 );
		$after     = $this->queue_repo->get_active_status_counts();

		if ( $processed > 0 ) {
			$redirect = add_query_arg(
				array(
					'page'      => 'ai-alt-text-queue',
					'notice'    => 'queue_batch_done',
					'processed' => $processed,
				),
				admin_url( 'upload.php' )
			);
		} else {
			$message  = $this->get_zero_processed_message( $before, $after );
			$redirect = add_query_arg(
				array(
					'page'      => 'ai-alt-text-queue',
					'notice'    => 'queue_error',
					'queue_msg' => rawurlencode( $message ),
				),
				admin_url( 'upload.php' )
			);
		}

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

		$options             = $this->settings->get_options();
		$before              = $this->queue_repo->get_active_status_counts();
		$processed           = $this->processor->process_batch( isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 10 );
		$after               = $this->queue_repo->get_active_status_counts();
		$message             = '';
		$remaining_claimable = ( isset( $after['queued'] ) ? absint( $after['queued'] ) : 0 ) + ( isset( $after['failed'] ) ? absint( $after['failed'] ) : 0 );
		$has_more            = $remaining_claimable > 0;

		if ( $processed <= 0 ) {
			$message = $this->get_zero_processed_message( $before, $after );
		}

		wp_send_json_success(
			array(
				'processed'           => $processed,
				'message'             => $message,
				'remaining_claimable' => $remaining_claimable,
				'has_more'            => $has_more,
			)
		);
	}

	/**
	 * Process one queue row via AJAX.
	 *
	 * @return void
	 */
	public function handle_queue_process_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ),
				),
				403
			);
		}

		check_ajax_referer( 'ai_alt_queue_process_ajax' );

		$row_id = isset( $_POST['row_id'] ) ? absint( wp_unslash( $_POST['row_id'] ) ) : 0;
		if ( ! $row_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid queue row.', 'dynamic-alt-tags' ),
				),
				400
			);
		}

		$message = '';
		$ok      = $this->process_queue_row( $row_id, $message );
		$row     = $this->queue_repo->get_row( $row_id );

		if ( ! $ok ) {
			wp_send_json_error(
				array(
					'message' => '' !== $message ? $message : __( 'Image processing failed. Please try again.', 'dynamic-alt-tags' ),
				),
				200
			);
		}

		wp_send_json_success(
			array(
				'message'       => __( 'Image successfully processed', 'dynamic-alt-tags' ),
				'status'        => is_array( $row ) && isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '',
				'confidence'    => is_array( $row ) && isset( $row['confidence'] ) ? (float) $row['confidence'] : 0.0,
				'suggested_alt' => is_array( $row ) && isset( $row['suggested_alt'] ) ? sanitize_text_field( (string) $row['suggested_alt'] ) : '',
			)
		);
	}

	/**
	 * Load more queue rows for current tab view.
	 *
	 * @return void
	 */
	public function handle_queue_load_more_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) ), 403 );
		}

		check_ajax_referer( 'ai_alt_queue_load_more_ajax' );

		$view     = isset( $_POST['view'] ) ? sanitize_key( wp_unslash( $_POST['view'] ) ) : 'active';
		$view     = in_array( $view, array( 'active', 'history', 'no_alt' ), true ) ? $view : 'active';
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? max( 1, absint( wp_unslash( $_POST['page'] ) ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? max( 1, min( 100, absint( wp_unslash( $_POST['per_page'] ) ) ) ) : 20;

		$data     = 'no_alt' === $view ? $this->queue_repo->get_no_alt_paginated( $page, $per_page ) : $this->queue_repo->get_paginated( $page, $per_page, $status, $view );
		$rows     = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : array();
		$total    = isset( $data['total'] ) ? absint( $data['total'] ) : 0;
		$max_page = max( 1, (int) ceil( $total / $per_page ) );

		$html = 'no_alt' === $view ? $this->render_no_alt_rows_html( $rows ) : $this->render_queue_rows_html( $rows, 'history' === $view );

		wp_send_json_success(
			array(
				'html'      => $html,
				'has_more'  => $page < $max_page,
				'next_page' => $page + 1,
			)
		);
	}

	/**
	 * Add one no-alt image to queue.
	 *
	 * @return void
	 */
	public function handle_queue_add_no_alt_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) ), 403 );
		}

		check_ajax_referer( 'ai_alt_queue_add_no_alt_ajax' );

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'dynamic-alt-tags' ) ), 400 );
		}

		$attachment = get_post( $attachment_id );
		if ( ! ( $attachment instanceof WP_Post ) || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Only image attachments can be queued.', 'dynamic-alt-tags' ) ), 400 );
		}

		$ok = $this->queue_repo->enqueue_or_requeue( $attachment_id, 0 );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Unable to add image to queue.', 'dynamic-alt-tags' ) ), 200 );
		}

		wp_send_json_success( array( 'message' => __( 'Added to queue', 'dynamic-alt-tags' ) ) );
	}

	/**
	 * Render active/history queue rows.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @param bool                           $is_history History tab.
	 * @return string
	 */
	private function render_queue_rows_html( $rows, $is_history = false ) {
		ob_start();
		foreach ( $rows as $row ) {
			$row_id        = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			$status        = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
			$confidence    = isset( $row['confidence'] ) ? (float) $row['confidence'] : 0.0;
			$suggested     = isset( $row['suggested_alt'] ) ? (string) $row['suggested_alt'] : '';
			$final_alt     = isset( $row['final_alt'] ) ? (string) $row['final_alt'] : '';
			$display_alt   = $is_history && '' !== trim( $final_alt ) ? $final_alt : $suggested;
			$thumb         = $attachment_id ? wp_get_attachment_image( $attachment_id, array( 80, 80 ), false, array( 'style' => 'max-width:80px;height:auto;' ) ) : '';
			$image_url     = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
			$existing_alt  = $attachment_id ? get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) : '';
			$existing_alt  = is_string( $existing_alt ) ? trim( $existing_alt ) : '';
			?>
			<tr>
				<?php if ( ! $is_history ) : ?>
					<th scope="row" class="check-column">
						<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( (string) $row_id ); ?>"><?php esc_html_e( 'Select item', 'dynamic-alt-tags' ); ?></label>
						<input id="cb-select-<?php echo esc_attr( (string) $row_id ); ?>" type="checkbox" class="ai-alt-row-checkbox" name="selected_row_ids[]" value="<?php echo esc_attr( (string) $row_id ); ?>" />
					</th>
				<?php endif; ?>
				<td>
					<?php echo $thumb ? wp_kses_post( $thumb ) : esc_html__( 'N/A', 'dynamic-alt-tags' ); ?>
					<div>#<?php echo esc_html( (string) $attachment_id ); ?></div>
				</td>
				<td><code class="ai-alt-row-status"><?php echo esc_html( $status ); ?></code></td>
				<td class="ai-alt-row-confidence"><?php echo esc_html( number_format_i18n( $confidence, 2 ) ); ?></td>
				<td><?php echo '' !== $existing_alt ? esc_html( $existing_alt ) : esc_html__( 'None', 'dynamic-alt-tags' ); ?></td>
				<td>
					<?php if ( $is_history ) : ?>
						<?php echo esc_html( $display_alt ); ?>
					<?php else : ?>
						<input type="text" class="regular-text ai-alt-row-suggested" name="bulk_final_alt[<?php echo esc_attr( (string) $row_id ); ?>]" value="<?php echo esc_attr( $display_alt ); ?>" />
					<?php endif; ?>
				</td>
				<td>
					<?php if ( ! $is_history ) : ?>
						<button class="button button-primary" type="submit" name="single_action" value="<?php echo esc_attr( 'approve|' . $row_id ); ?>"><?php esc_html_e( 'Approve', 'dynamic-alt-tags' ); ?></button>
						<button class="button" type="submit" name="single_action" value="<?php echo esc_attr( 'reject|' . $row_id ); ?>"><?php esc_html_e( 'Reject', 'dynamic-alt-tags' ); ?></button>
						<?php if ( in_array( $status, array( 'queued', 'failed', 'generated' ), true ) ) : ?>
							<button class="button ai-alt-row-process" type="button" data-row-id="<?php echo esc_attr( (string) $row_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ai_alt_queue_process_ajax' ) ); ?>"><?php esc_html_e( 'Process', 'dynamic-alt-tags' ); ?></button>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( ! empty( $image_url ) ) : ?>
						<a class="button" href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Image', 'dynamic-alt-tags' ); ?></a>
					<?php endif; ?>
					<?php if ( ! $is_history ) : ?>
						<button class="button" type="submit" name="single_action" value="<?php echo esc_attr( 'skip|' . $row_id ); ?>"><?php esc_html_e( 'Skip Image', 'dynamic-alt-tags' ); ?></button>
						<div class="ai-alt-progress-wrap ai-alt-row-progress-wrap" hidden>
							<div class="ai-alt-progress-bar ai-alt-row-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
						</div>
						<p class="description ai-alt-row-process-message" aria-live="polite"></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		}
		return (string) ob_get_clean();
	}

	/**
	 * Render no-alt rows.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @return string
	 */
	private function render_no_alt_rows_html( $rows ) {
		ob_start();
		foreach ( $rows as $row ) {
			$attachment_id    = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			$queue_status     = isset( $row['queue_status'] ) ? sanitize_key( (string) $row['queue_status'] ) : '';
			$is_active_status = in_array( $queue_status, array( 'queued', 'processing', 'generated' ), true );
			$button_label     = __( 'Add to Queue', 'dynamic-alt-tags' );
			if ( '' !== $queue_status && ! $is_active_status ) {
				$button_label = __( 'Requeue', 'dynamic-alt-tags' );
			} elseif ( $is_active_status ) {
				$button_label = __( 'Queued', 'dynamic-alt-tags' );
			}
			$thumb     = $attachment_id ? wp_get_attachment_image( $attachment_id, array( 80, 80 ), false, array( 'style' => 'max-width:80px;height:auto;' ) ) : '';
			$image_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
			?>
			<tr>
				<td>
					<?php echo $thumb ? wp_kses_post( $thumb ) : esc_html__( 'N/A', 'dynamic-alt-tags' ); ?>
					<div>#<?php echo esc_html( (string) $attachment_id ); ?></div>
				</td>
				<td><?php esc_html_e( 'None', 'dynamic-alt-tags' ); ?></td>
				<td><code class="ai-alt-no-alt-queue-status"><?php echo '' !== $queue_status ? esc_html( $queue_status ) : esc_html__( 'not_queued', 'dynamic-alt-tags' ); ?></code></td>
					<td>
						<button class="button ai-alt-add-no-alt" type="button" data-attachment-id="<?php echo esc_attr( (string) $attachment_id ); ?>" <?php echo $is_active_status ? 'disabled' : ''; ?>><?php echo esc_html( $button_label ); ?></button>
					<?php if ( ! empty( $image_url ) ) : ?>
						<a class="button" href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Image', 'dynamic-alt-tags' ); ?></a>
					<?php endif; ?>
					<p class="description ai-alt-no-alt-message" aria-live="polite"></p>
				</td>
			</tr>
			<?php
		}
		return (string) ob_get_clean();
	}

	/**
	 * Build diagnostic message when processing returns zero.
	 *
	 * @param array<string,int> $before Status counts before run.
	 * @param array<string,int> $after Status counts after run.
	 * @return string
	 */
	private function get_zero_processed_message( $before, $after ) {
		$queued_before     = isset( $before['queued'] ) ? absint( $before['queued'] ) : 0;
		$failed_before     = isset( $before['failed'] ) ? absint( $before['failed'] ) : 0;
		$generated_before  = isset( $before['generated'] ) ? absint( $before['generated'] ) : 0;
		$processing_before = isset( $before['processing'] ) ? absint( $before['processing'] ) : 0;

		$queued_after     = isset( $after['queued'] ) ? absint( $after['queued'] ) : 0;
		$failed_after     = isset( $after['failed'] ) ? absint( $after['failed'] ) : 0;
		$generated_after  = isset( $after['generated'] ) ? absint( $after['generated'] ) : 0;
		$processing_after = isset( $after['processing'] ) ? absint( $after['processing'] ) : 0;

		if ( 0 === $queued_before && 0 === $failed_before && 0 === $generated_before && 0 === $processing_before ) {
			return __( 'No queue items were available to process.', 'dynamic-alt-tags' );
		}

		if ( 0 === $queued_before && 0 === $failed_before && $generated_before > 0 ) {
			return __( 'No items were processed because active items are already generated and waiting for review.', 'dynamic-alt-tags' );
		}

		if ( 0 === $queued_before && 0 === $failed_before && $processing_before > 0 ) {
			return __( 'No items were processed because queue jobs are currently locked in processing. Try again shortly.', 'dynamic-alt-tags' );
		}

		$latest_failed = $this->queue_repo->get_latest_failed_row();
		if ( is_array( $latest_failed ) ) {
			$error_message = isset( $latest_failed['error_message'] ) ? sanitize_text_field( (string) $latest_failed['error_message'] ) : '';
			if ( '' !== $error_message ) {
				return sprintf(
					/* translators: %s provider error detail */
					__( 'No images were processed. Latest provider error: %s', 'dynamic-alt-tags' ),
					$error_message
				);
			}
		}

		if ( $queued_after < $queued_before && $failed_after === $failed_before ) {
			return __( 'No items were processed because claimed queue items were skipped (for example, existing alt text already present).', 'dynamic-alt-tags' );
		}

		return __( 'No items were processed. Check queue item status and provider connectivity details.', 'dynamic-alt-tags' );
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

		$status   = 'success';
		$messages = array();
		$provider = new WPAI_Alt_Text_Provider_Cloudflare( $this->settings );
		$result   = $provider->generate_caption(
			'https://s.w.org/style/images/about/WordPress-logotype-wmark.png',
			array(
				'attachment_title' => 'WordPress logo',
				'post_title'       => 'Provider test',
			)
		);

		if ( is_wp_error( $result ) ) {
			$status     = 'error';
			$messages[] = sprintf(
				/* translators: %s error message */
				__( 'Baseline test failed: %s', 'dynamic-alt-tags' ),
				$result->get_error_message()
			);
		} elseif ( ! is_array( $result ) || empty( $result['caption'] ) ) {
			$status     = 'error';
			$messages[] = __( 'Baseline test failed: provider responded without a usable caption.', 'dynamic-alt-tags' );
		} else {
			$messages[] = __( 'Baseline test succeeded.', 'dynamic-alt-tags' );
		}

		$row = $this->queue_repo->get_latest_active_row();
		if ( ! is_array( $row ) || empty( $row['attachment_id'] ) ) {
			$messages[] = __( 'Latest queued image test skipped: no active queue item found.', 'dynamic-alt-tags' );
		} else {
			$attachment_id = absint( $row['attachment_id'] );
			$image_url     = wp_get_attachment_url( $attachment_id );

			if ( ! $image_url ) {
				$status     = 'error';
				$messages[] = __( 'Latest queued image test failed: attachment URL not found.', 'dynamic-alt-tags' );
			} else {
				$latest_result = $provider->generate_caption(
					$image_url,
					array(
						'attachment_id'    => $attachment_id,
						'attachment_title' => get_the_title( $attachment_id ),
						'post_title'       => 'Provider latest-image test',
					)
				);

				if ( is_wp_error( $latest_result ) ) {
					$status     = 'error';
					$messages[] = sprintf(
						/* translators: 1: attachment id, 2: error message */
						__( 'Latest queued image test failed (attachment #%1$d): %2$s', 'dynamic-alt-tags' ),
						$attachment_id,
						$latest_result->get_error_message()
					);
				} elseif ( ! is_array( $latest_result ) || empty( $latest_result['caption'] ) ) {
					$status     = 'error';
					$messages[] = sprintf(
						/* translators: %d attachment id */
						__( 'Latest queued image test failed (attachment #%d): no usable caption returned.', 'dynamic-alt-tags' ),
						$attachment_id
					);
				} else {
					$messages[] = sprintf(
						/* translators: %d attachment id */
						__( 'Latest queued image test succeeded (attachment #%d).', 'dynamic-alt-tags' ),
						$attachment_id
					);
				}
			}
		}

		$message = implode( ' ', $messages );
		$this->record_connection_check( $status, $message );

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
	 * Save latest provider connection check result.
	 *
	 * @param string $status Test status.
	 * @param string $message Test message.
	 * @return void
	 */
	private function record_connection_check( $status, $message ) {
		$status  = 'success' === $status ? 'success' : 'error';
		$message = sanitize_text_field( (string) $message );

		update_option(
			self::CONNECTION_STATUS_OPTION_KEY,
			array(
				'status'     => $status,
				'message'    => $message,
				'checked_at' => current_time( 'mysql' ),
			),
			false
		);
	}

	/**
	 * Build settings connection status payload for UI.
	 *
	 * @return array<string,mixed>
	 */
	private function get_connection_status() {
		$options = $this->settings->get_options();
		$state   = 'unknown';
		$title   = __( 'Not Checked', 'dynamic-alt-tags' );
		$message = __( 'Run "Test Provider Connection" to verify connectivity.', 'dynamic-alt-tags' );

		if ( empty( $options['worker_url'] ) ) {
			$state   = 'error';
			$title   = __( 'Not Configured', 'dynamic-alt-tags' );
			$message = __( 'Cloudflare Worker URL is required.', 'dynamic-alt-tags' );
		}

		$saved = get_option( self::CONNECTION_STATUS_OPTION_KEY, array() );
		if ( is_array( $saved ) && ! empty( $saved['status'] ) ) {
			$saved_status  = 'success' === sanitize_key( (string) $saved['status'] ) ? 'success' : 'error';
			$saved_message = isset( $saved['message'] ) ? sanitize_text_field( (string) $saved['message'] ) : '';

			if ( 'success' === $saved_status ) {
				$state = 'ok';
				$title = __( 'Connected', 'dynamic-alt-tags' );
				if ( '' !== $saved_message ) {
					$message = $saved_message;
				}
			} else {
				$state   = 'error';
				$title   = __( 'Connection Error', 'dynamic-alt-tags' );
				$message = '' !== $saved_message ? $saved_message : __( 'Provider check failed.', 'dynamic-alt-tags' );
			}
		}

		$latest_failed = $this->queue_repo->get_latest_failed_row();
		$queue_error   = '';
		if ( is_array( $latest_failed ) ) {
			$queue_error = isset( $latest_failed['error_message'] ) ? sanitize_text_field( (string) $latest_failed['error_message'] ) : '';
			if ( '' === $queue_error && ! empty( $latest_failed['error_code'] ) ) {
				$queue_error = sprintf(
					/* translators: %s error code */
					__( 'Latest queue failure code: %s', 'dynamic-alt-tags' ),
					sanitize_key( (string) $latest_failed['error_code'] )
				);
			}
			if ( '' !== $queue_error && 'error' !== $state ) {
				$state = 'warning';
			}
		}

		$checked_at = '';
		if ( is_array( $saved ) && ! empty( $saved['checked_at'] ) ) {
			$checked_at = sanitize_text_field( (string) $saved['checked_at'] );
		}

		return array(
			'state'       => $state,
			'title'       => $title,
			'message'     => $message,
			'checked_at'  => $checked_at,
			'queue_error' => $queue_error,
		);
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

		$allowed_actions = array( 'approve', 'reject', 'skip', 'process' );
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

			$bulk_final_alt_raw = isset( $_POST['bulk_final_alt'] ) ? wp_unslash( $_POST['bulk_final_alt'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$alts               = is_array( $bulk_final_alt_raw ) ? $bulk_final_alt_raw : array();
			$alt  = isset( $alts[ $row_id ] ) ? sanitize_text_field( (string) $alts[ $row_id ] ) : '';
			$this->apply_queue_action( $row_id, $action, $alt );
			$updated_count = 1;

			$notice = 'queue_updated';
			if ( 'process' === $action ) {
				$notice = 'queue_process_done';
			}

			$redirect = add_query_arg(
				array(
					'page'    => 'ai-alt-text-queue',
					'notice'  => $notice,
					'updated' => $updated_count,
				),
				admin_url( 'upload.php' )
			);

			wp_safe_redirect( $redirect );
			exit;
		} else {
			$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
			if ( '' === $bulk_action || '-1' === $bulk_action ) {
				$bulk_action = isset( $_POST['bulk_action2'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action2'] ) ) : '';
			}

			if ( ! in_array( $bulk_action, $allowed_actions, true ) ) {
				$redirect = add_query_arg(
					array(
						'page'      => 'ai-alt-text-queue',
						'notice'    => 'queue_error',
						'queue_msg' => rawurlencode( __( 'Please select a bulk action before clicking Apply.', 'dynamic-alt-tags' ) ),
					),
					admin_url( 'upload.php' )
				);

				wp_safe_redirect( $redirect );
				exit;
			}

			$selected_ids_raw = isset( $_POST['selected_row_ids'] ) ? wp_unslash( $_POST['selected_row_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$selected_ids     = is_array( $selected_ids_raw ) ? $selected_ids_raw : array();
			$selected_ids     = array_values( array_filter( array_map( 'absint', $selected_ids ) ) );
			if ( empty( $selected_ids ) ) {
				wp_die( esc_html__( 'No queue items selected.', 'dynamic-alt-tags' ) );
			}

			$bulk_final_alt_raw = isset( $_POST['bulk_final_alt'] ) ? wp_unslash( $_POST['bulk_final_alt'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$alts               = is_array( $bulk_final_alt_raw ) ? $bulk_final_alt_raw : array();
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
				update_post_meta( absint( $row['attachment_id'] ), '_ai_alt_review_required', 0 );
			}
			$this->queue_repo->mark_final( $row_id, 'rejected', '' );
		} elseif ( 'skip' === $action ) {
			$row = $this->queue_repo->get_row( $row_id );
			if ( is_array( $row ) && ! empty( $row['attachment_id'] ) ) {
				update_post_meta( absint( $row['attachment_id'] ), '_wp_attachment_image_alt', '' );
				update_post_meta( absint( $row['attachment_id'] ), '_ai_alt_review_required', 0 );
			}
			$this->queue_repo->mark_final( $row_id, 'skipped', '' );
		} elseif ( 'process' === $action ) {
			$message = '';
			$this->process_queue_row( $row_id, $message );
		}
	}

	/**
	 * Process one queue row by attachment id.
	 *
	 * @param int         $row_id Queue row ID.
	 * @param string|null $message Error message output.
	 * @return bool
	 */
	private function process_queue_row( $row_id, &$message = null ) {
		$message = '';
		$row     = $this->queue_repo->get_row( $row_id );
		if ( ! is_array( $row ) || empty( $row['attachment_id'] ) ) {
			$message = __( 'Queue row was not found.', 'dynamic-alt-tags' );
			return false;
		}

		$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
		if ( 'generated' === $status ) {
			$this->queue_repo->mark_failed( $row_id, 'manual_reprocess', 'Manual reprocess requested.' );
		}

		$attachment_id = absint( $row['attachment_id'] );
		$processed     = $this->processor->process_attachment_for_review( $attachment_id );
		if ( ! $processed ) {
			$latest_row = $this->queue_repo->get_row( $row_id );
			if ( is_array( $latest_row ) && ! empty( $latest_row['error_message'] ) ) {
				$message = sanitize_text_field( (string) $latest_row['error_message'] );
			} else {
				$message = __( 'Image processing failed. Please try again.', 'dynamic-alt-tags' );
			}
			return false;
		}

		return true;
	}
}
