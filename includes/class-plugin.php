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
		add_action( 'delete_attachment', array( $this, 'cleanup_attachment_data' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_review_fields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_review_fields' ), 10, 2 );
		add_action( 'edit_attachment', array( $this, 'apply_pending_upload_review_action' ), 20 );
		add_action( 'wp_ajax_ai_alt_upload_action_ajax', array( $this, 'handle_upload_action_ajax' ) );
		add_action( 'admin_notices', array( $this, 'render_upload_review_notice' ) );

		add_action( 'admin_menu', array( $this->admin, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
		add_action( 'admin_post_ai_alt_run_backfill', array( $this->admin, 'handle_run_backfill' ) );
		add_action( 'admin_post_ai_alt_run_backfill_queue', array( $this->admin, 'handle_run_backfill_queue' ) );
		add_action( 'admin_post_ai_alt_process_now', array( $this->admin, 'handle_process_now' ) );
		add_action( 'admin_post_ai_alt_process_now_queue', array( $this->admin, 'handle_process_now_queue' ) );
		add_action( 'admin_post_ai_alt_test_connection', array( $this->admin, 'handle_test_connection' ) );
		add_action( 'admin_post_ai_alt_queue_action', array( $this->admin, 'handle_queue_action' ) );
		add_action( 'wp_ajax_ai_alt_process_now_ajax', array( $this->admin, 'handle_process_now_ajax' ) );
		add_action( 'wp_ajax_ai_alt_queue_process_ajax', array( $this->admin, 'handle_queue_process_ajax' ) );
		add_action( 'wp_ajax_ai_alt_queue_load_more_ajax', array( $this->admin, 'handle_queue_load_more_ajax' ) );
		add_action( 'wp_ajax_ai_alt_queue_add_no_alt_ajax', array( $this->admin, 'handle_queue_add_no_alt_ajax' ) );

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

		$queued = $this->queue_repo->enqueue( $attachment_id, 0 );
		if ( $queued ) {
			$this->processor->process_attachment_for_review( $attachment_id );
		}
	}

	/**
	 * Remove plugin data when an attachment is deleted.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function cleanup_attachment_data( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		$this->queue_repo->delete_by_attachment_id( $attachment_id );

		delete_post_meta( $attachment_id, '_ai_alt_last_generated_at' );
		delete_post_meta( $attachment_id, '_ai_alt_source_provider' );
		delete_post_meta( $attachment_id, '_ai_alt_review_required' );
	}

	/**
	 * Add upload/edit review controls for generated suggestions.
	 *
	 * @param array<string,mixed> $form_fields Form fields.
	 * @param WP_Post             $post Attachment post.
	 * @return array<string,mixed>
	 */
	public function add_attachment_review_fields( $form_fields, $post ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $form_fields;
		}

		$attachment_id = isset( $post->ID ) ? absint( $post->ID ) : 0;
		if ( ! $attachment_id ) {
			return $form_fields;
		}

		if ( 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			return $form_fields;
		}

		$row           = $this->queue_repo->get_row_by_attachment( $attachment_id );
		$has_row       = is_array( $row );
		$status        = $has_row && isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'not_queued';
		$suggested_alt = $has_row && isset( $row['suggested_alt'] ) ? sanitize_text_field( (string) $row['suggested_alt'] ) : '';
		$final_alt     = $has_row && isset( $row['final_alt'] ) ? sanitize_text_field( (string) $row['final_alt'] ) : '';
		$error_message = $has_row && isset( $row['error_message'] ) ? sanitize_text_field( (string) $row['error_message'] ) : '';

		if ( $has_row ) {
			$action_options = array(
				''        => __( 'Choose action', 'dynamic-alt-tags' ),
				'approve' => __( 'Approve', 'dynamic-alt-tags' ),
				'reject'  => __( 'Reject', 'dynamic-alt-tags' ),
				'skip'    => __( 'Skip Image', 'dynamic-alt-tags' ),
				'custom'  => __( 'Use Custom Alt Text', 'dynamic-alt-tags' ),
			);
		} else {
			$action_options = array(
				''         => __( 'Choose action', 'dynamic-alt-tags' ),
				'generate' => __( 'Generate Suggestion', 'dynamic-alt-tags' ),
				'custom'   => __( 'Use Custom Alt Text', 'dynamic-alt-tags' ),
			);
		}

		$options_html = '';
		foreach ( $action_options as $value => $label ) {
			$options_html .= sprintf(
				'<option value="%1$s">%2$s</option>',
				esc_attr( $value ),
				esc_html( $label )
			);
		}

		$review_html = '';
		if ( '' !== $suggested_alt ) {
			$review_html .= '<p><strong>' . esc_html__( 'Suggested Alt:', 'dynamic-alt-tags' ) . '</strong> ' . esc_html( $suggested_alt ) . '</p>';
		}
		if ( '' !== $error_message ) {
			$review_html .= '<p><strong>' . esc_html__( 'Generation Error:', 'dynamic-alt-tags' ) . '</strong> ' . esc_html( $error_message ) . '</p>';
		}
		$review_html     .= '<p><label>' . esc_html__( 'Action', 'dynamic-alt-tags' ) . '<br />';
		$review_html     .= '<select class="ai-alt-upload-action" data-attachment-id="' . esc_attr( (string) $attachment_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'ai_alt_upload_action_ajax' ) ) . '" name="attachments[' . esc_attr( (string) $attachment_id ) . '][ai_alt_action]">' . $options_html . '</select>';
		$review_html     .= '</label></p>';
			$review_html .= '<p class="ai-alt-upload-custom-wrap" style="display:none;"><label>' . esc_html__( 'Custom Alt Text', 'dynamic-alt-tags' ) . '<br />';
		$review_html     .= '<input type="text" class="widefat ai-alt-upload-custom-alt" data-attachment-id="' . esc_attr( (string) $attachment_id ) . '" name="attachments[' . esc_attr( (string) $attachment_id ) . '][ai_alt_custom_alt]" value="" />';
		$review_html     .= '</label></p>';
		$review_html     .= '<p class="ai-alt-upload-apply-row"><input type="button" class="button ai-alt-upload-apply" style="display:none;" data-attachment-id="' . esc_attr( (string) $attachment_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'ai_alt_upload_action_ajax' ) ) . '" value="' . esc_attr__( 'Apply', 'dynamic-alt-tags' ) . '" /></p>';
		$review_html     .= '<p class="description ai-alt-upload-action-result" aria-live="polite"></p>';
		$review_html     .= '<p class="description">' . esc_html__( 'Choose an action to finalize this uploaded image suggestion.', 'dynamic-alt-tags' ) . '</p>';
		if ( ! $has_row ) {
			$review_html .= '<p class="description">' . esc_html__( 'This image does not have a queue item yet. Use Generate Suggestion to create one.', 'dynamic-alt-tags' ) . '</p>';
		}

		$form_fields['ai_alt_review'] = array(
			'label' => __( 'Dynamic Alt Tags Review', 'dynamic-alt-tags' ),
			'input' => 'html',
			'html'  => $review_html,
		);

		return $form_fields;
	}

	/**
	 * Save upload/edit review actions from attachment fields.
	 *
	 * @param array<string,mixed> $post Attachment post array.
	 * @param array<string,mixed> $attachment Attachment form data.
	 * @return array<string,mixed>
	 */
	public function save_attachment_review_fields( $post, $attachment ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $post;
		}

		$attachment_id = isset( $post['ID'] ) ? absint( $post['ID'] ) : 0;
		if ( ! $attachment_id ) {
			return $post;
		}

		$action = isset( $attachment['ai_alt_action'] ) ? sanitize_key( (string) $attachment['ai_alt_action'] ) : '';
		if ( '' === $action ) {
			return $post;
		}

		if ( ! in_array( $action, array( 'approve', 'reject', 'skip', 'custom', 'generate' ), true ) ) {
			return $post;
		}

		$custom_alt = isset( $attachment['ai_alt_custom_alt'] ) ? sanitize_text_field( (string) $attachment['ai_alt_custom_alt'] ) : '';

		update_post_meta( $attachment_id, '_ai_alt_pending_action', $action );
		update_post_meta( $attachment_id, '_ai_alt_pending_custom_alt', $custom_alt );

		return $post;
	}

	/**
	 * Apply pending upload review action after attachment save is complete.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function apply_pending_upload_review_action( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		$action     = sanitize_key( (string) get_post_meta( $attachment_id, '_ai_alt_pending_action', true ) );
		$custom_alt = sanitize_text_field( (string) get_post_meta( $attachment_id, '_ai_alt_pending_custom_alt', true ) );
		if ( '' === $action ) {
			return;
		}

		delete_post_meta( $attachment_id, '_ai_alt_pending_action' );
		delete_post_meta( $attachment_id, '_ai_alt_pending_custom_alt' );

		$result         = $this->apply_review_action( $attachment_id, $action, $custom_alt );
		$notice_message = isset( $result['message'] ) ? (string) $result['message'] : '';

		if ( '' !== $notice_message ) {
			update_user_meta( get_current_user_id(), '_ai_alt_upload_review_notice', $notice_message );
		}
	}

	/**
	 * Handle upload action via AJAX.
	 *
	 * @return void
	 */
	public function handle_upload_action_ajax() {
		$is_debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$debug            = array(
			'timestamp' => current_time( 'mysql' ),
			'user_id'   => get_current_user_id(),
		);

		if ( ! current_user_can( 'manage_options' ) ) {
			$debug['error'] = 'forbidden';
			if ( $is_debug_enabled ) {
				error_log( '[dynamic-alt-tags] upload_action_ajax ' . wp_json_encode( $debug ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ),
				),
				403
			);
		}

		$nonce_ok = check_ajax_referer( 'ai_alt_upload_action_ajax', '_ajax_nonce', false );
		if ( ! $nonce_ok ) {
			$debug['error'] = 'invalid_nonce';
			if ( $is_debug_enabled ) {
				error_log( '[dynamic-alt-tags] upload_action_ajax ' . wp_json_encode( $debug ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			wp_send_json_error(
				array(
					'message' => __( 'Invalid security nonce.', 'dynamic-alt-tags' ),
				),
				403
			);
		}

		$debug['post_keys'] = array_keys( $_POST );

		$attachment_id    = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		$action           = isset( $_POST['review_action'] ) ? sanitize_key( wp_unslash( $_POST['review_action'] ) ) : '';
		$custom_alt       = isset( $_POST['custom_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_alt'] ) ) : '';
		$before_row       = $this->queue_repo->get_row_by_attachment( $attachment_id );
		$debug['request'] = array(
			'attachment_id' => $attachment_id,
			'action'        => $action,
			'custom_alt'    => $custom_alt,
		);
		$debug['before']  = array(
			'alt_text'  => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'queue_row' => is_array( $before_row ) ? array(
				'id'            => isset( $before_row['id'] ) ? absint( $before_row['id'] ) : 0,
				'status'        => isset( $before_row['status'] ) ? sanitize_key( (string) $before_row['status'] ) : '',
				'suggested_alt' => isset( $before_row['suggested_alt'] ) ? sanitize_text_field( (string) $before_row['suggested_alt'] ) : '',
				'final_alt'     => isset( $before_row['final_alt'] ) ? sanitize_text_field( (string) $before_row['final_alt'] ) : '',
			) : null,
		);
		$result           = $this->apply_review_action( $attachment_id, $action, $custom_alt );
		$after_row        = $this->queue_repo->get_row_by_attachment( $attachment_id );
		$debug['result']  = $result;
		$debug['after']   = array(
			'alt_text'  => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'queue_row' => is_array( $after_row ) ? array(
				'id'            => isset( $after_row['id'] ) ? absint( $after_row['id'] ) : 0,
				'status'        => isset( $after_row['status'] ) ? sanitize_key( (string) $after_row['status'] ) : '',
				'suggested_alt' => isset( $after_row['suggested_alt'] ) ? sanitize_text_field( (string) $after_row['suggested_alt'] ) : '',
				'final_alt'     => isset( $after_row['final_alt'] ) ? sanitize_text_field( (string) $after_row['final_alt'] ) : '',
			) : null,
		);
		if ( $is_debug_enabled ) {
			error_log( '[dynamic-alt-tags] upload_action_ajax ' . wp_json_encode( $debug ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		if ( empty( $result['ok'] ) ) {
			wp_send_json_error(
				array(
					'message' => isset( $result['message'] ) ? (string) $result['message'] : __( 'Unable to apply action.', 'dynamic-alt-tags' ),
				),
				400
			);
		}

		if ( ! empty( $result['message'] ) ) {
			update_user_meta( get_current_user_id(), '_ai_alt_upload_review_notice', (string) $result['message'] );
		}

		wp_send_json_success(
			array(
				'message'  => isset( $result['message'] ) ? (string) $result['message'] : '',
				'alt_text' => isset( $result['alt_text'] ) ? (string) $result['alt_text'] : '',
			)
		);
	}

	/**
	 * Apply a review action to a single attachment queue record.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $action Action key.
	 * @param string $custom_alt Custom alt.
	 * @return array<string,mixed>
	 */
	private function apply_review_action( $attachment_id, $action, $custom_alt = '' ) {
		$attachment_id = absint( $attachment_id );
		$action        = sanitize_key( (string) $action );
		$custom_alt    = sanitize_text_field( (string) $custom_alt );

		if ( ! $attachment_id || ! in_array( $action, array( 'approve', 'reject', 'skip', 'custom', 'generate' ), true ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Invalid upload action request.', 'dynamic-alt-tags' ),
			);
		}

		if ( 'generate' === $action ) {
			$row_before = $this->queue_repo->get_row_by_attachment( $attachment_id );
			if ( ! is_array( $row_before ) ) {
				$queued = $this->queue_repo->enqueue( $attachment_id, 0 );
				if ( ! $queued ) {
					return array(
						'ok'      => false,
						'message' => __( 'Unable to queue this image for generation.', 'dynamic-alt-tags' ),
					);
				}
			}

			$generated = $this->processor->process_attachment_for_review( $attachment_id );
			$row_after = $this->queue_repo->get_row_by_attachment( $attachment_id );
			$suggested = is_array( $row_after ) && isset( $row_after['suggested_alt'] ) ? sanitize_text_field( (string) $row_after['suggested_alt'] ) : '';
			$status    = is_array( $row_after ) && isset( $row_after['status'] ) ? sanitize_key( (string) $row_after['status'] ) : '';

			return array(
				'ok'            => (bool) $generated,
				'alt_text'      => '',
				'message'       => $generated
					? __( 'Dynamic Alt Tags: suggestion generated. You can now approve, reject, skip, or set custom alt text.', 'dynamic-alt-tags' )
					: __( 'Unable to generate suggestion for this image. Check provider settings/logs.', 'dynamic-alt-tags' ),
				'status'        => $status,
				'suggested_alt' => $suggested,
			);
		}

		$row = $this->queue_repo->get_row_by_attachment( $attachment_id );
		if ( ! is_array( $row ) || empty( $row['id'] ) ) {
			if ( 'custom' === $action ) {
				if ( '' === trim( $custom_alt ) ) {
					return array(
						'ok'      => false,
						'message' => __( 'Please enter custom alt text first.', 'dynamic-alt-tags' ),
					);
				}
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $custom_alt );
				update_post_meta( $attachment_id, '_ai_alt_last_generated_at', current_time( 'mysql' ) );
				update_post_meta( $attachment_id, '_ai_alt_source_provider', 'custom' );
				update_post_meta( $attachment_id, '_ai_alt_review_required', 0 );
				return array(
					'ok'       => true,
					'alt_text' => $custom_alt,
					'message'  => __( 'Dynamic Alt Tags: custom alt text saved and applied.', 'dynamic-alt-tags' ),
				);
			}

			return array(
				'ok'      => false,
				'message' => __( 'No queue item found for this image. Choose Generate Suggestion first.', 'dynamic-alt-tags' ),
			);
		}

		$row_id        = absint( $row['id'] );
		$suggested_alt = isset( $row['suggested_alt'] ) ? sanitize_text_field( (string) $row['suggested_alt'] ) : '';

		if ( 'approve' === $action ) {
			if ( '' === $suggested_alt ) {
				return array(
					'ok'      => false,
					'message' => __( 'No suggested alt text available to approve.', 'dynamic-alt-tags' ),
				);
			}
			$ok = $this->processor->approve_row( $row_id, $suggested_alt );
			return array(
				'ok'       => (bool) $ok,
				'alt_text' => $suggested_alt,
				'message'  => $ok ? __( 'Dynamic Alt Tags: suggested alt text approved and applied.', 'dynamic-alt-tags' ) : __( 'Unable to approve suggested alt text.', 'dynamic-alt-tags' ),
			);
		}

		if ( 'reject' === $action ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', '' );
			update_post_meta( $attachment_id, '_ai_alt_review_required', 0 );
			$ok = $this->queue_repo->mark_final( $row_id, 'rejected', '' );
			return array(
				'ok'       => (bool) $ok,
				'alt_text' => '',
				'message'  => __( 'Dynamic Alt Tags: image suggestion rejected and alt text cleared.', 'dynamic-alt-tags' ),
			);
		}

		if ( 'skip' === $action ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', '' );
			update_post_meta( $attachment_id, '_ai_alt_review_required', 0 );
			$ok = $this->queue_repo->mark_final( $row_id, 'skipped', '' );
			return array(
				'ok'       => (bool) $ok,
				'alt_text' => '',
				'message'  => __( 'Dynamic Alt Tags: image skipped and moved to History.', 'dynamic-alt-tags' ),
			);
		}

		if ( '' === trim( $custom_alt ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Please enter custom alt text first.', 'dynamic-alt-tags' ),
			);
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $custom_alt );
		update_post_meta( $attachment_id, '_ai_alt_last_generated_at', current_time( 'mysql' ) );
		update_post_meta( $attachment_id, '_ai_alt_source_provider', 'custom' );
		update_post_meta( $attachment_id, '_ai_alt_review_required', 0 );
		$ok = $this->queue_repo->mark_final( $row_id, 'approved', $custom_alt );

		return array(
			'ok'       => (bool) $ok,
			'alt_text' => $custom_alt,
			'message'  => __( 'Dynamic Alt Tags: custom alt text saved and applied.', 'dynamic-alt-tags' ),
		);
	}

	/**
	 * Render one-time upload review notice.
	 *
	 * @return void
	 */
	public function render_upload_review_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = get_user_meta( get_current_user_id(), '_ai_alt_upload_review_notice', true );
		if ( ! is_string( $notice ) || '' === trim( $notice ) ) {
			return;
		}

		delete_user_meta( get_current_user_id(), '_ai_alt_upload_review_notice' );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
		<?php
	}
}
