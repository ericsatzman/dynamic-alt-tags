<?php
/**
 * REST endpoints for integrations.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_REST {

	/**
	 * Queue repo.
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
	 * @param WPAI_Alt_Text_Queue_Repo $queue_repo Queue.
	 * @param WPAI_Alt_Text_Processor  $processor Processor.
	 */
	public function __construct( $queue_repo, $processor ) {
		$this->queue_repo = $queue_repo;
		$this->processor  = $processor;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'ai-alt/v1',
			'/queue/(?P<id>\d+)/approve',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => array( $this, 'can_manage' ),
				'callback'            => array( $this, 'approve' ),
				'args'                => array(
					'alt_text' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Approve queue item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve( WP_REST_Request $request ) {
		$row_id   = absint( $request->get_param( 'id' ) );
		$alt_text = (string) $request->get_param( 'alt_text' );

		if ( ! $row_id || '' === trim( $alt_text ) ) {
			return new WP_Error( 'ai_alt_invalid_request', __( 'Invalid request.', 'dynamic-alt-tags' ), array( 'status' => 400 ) );
		}

		$ok = $this->processor->approve_row( $row_id, $alt_text );
		if ( ! $ok ) {
			return new WP_Error( 'ai_alt_approve_failed', __( 'Unable to approve queue item.', 'dynamic-alt-tags' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'success' => true ) );
	}
}
