<?php
/**
 * Queue processor.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Processor {

	/**
	 * Settings.
	 *
	 * @var WPAI_Alt_Text_Settings
	 */
	private $settings;

	/**
	 * Queue repo.
	 *
	 * @var WPAI_Alt_Text_Queue_Repo
	 */
	private $queue_repo;

	/**
	 * Provider.
	 *
	 * @var WPAI_Alt_Text_Provider_Interface
	 */
	private $provider;

	/**
	 * Generator.
	 *
	 * @var WPAI_Alt_Text_Generator
	 */
	private $generator;

	/**
	 * Logger.
	 *
	 * @var WPAI_Alt_Text_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param WPAI_Alt_Text_Settings           $settings Settings.
	 * @param WPAI_Alt_Text_Queue_Repo         $queue_repo Queue.
	 * @param WPAI_Alt_Text_Provider_Interface $provider Provider.
	 * @param WPAI_Alt_Text_Generator          $generator Generator.
	 * @param WPAI_Alt_Text_Logger             $logger Logger.
	 */
	public function __construct( $settings, $queue_repo, $provider, $generator, $logger ) {
		$this->settings   = $settings;
		$this->queue_repo = $queue_repo;
		$this->provider   = $provider;
		$this->generator  = $generator;
		$this->logger     = $logger;
	}

	/**
	 * Process a batch.
	 *
	 * @param int $limit Limit.
	 * @return int Processed count.
	 */
	public function process_batch( $limit = 10 ) {
		$jobs       = $this->queue_repo->claim_jobs( $limit );
		$processed  = 0;
		$options    = $this->settings->get_options();
		$overwrite  = ! empty( $options['overwrite_existing'] );
		$min_conf   = isset( $options['min_confidence'] ) ? (float) $options['min_confidence'] : 0.7;
		$need_review = ! empty( $options['require_review'] );

		foreach ( $jobs as $job ) {
			$row_id        = isset( $job['id'] ) ? absint( $job['id'] ) : 0;
			$attachment_id = isset( $job['attachment_id'] ) ? absint( $job['attachment_id'] ) : 0;

			if ( ! $row_id || ! $attachment_id ) {
				continue;
			}

			$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( ! $overwrite && is_string( $existing_alt ) && '' !== trim( $existing_alt ) ) {
				$this->queue_repo->mark_final( $row_id, 'skipped', '' );
				continue;
			}

			$image_url = wp_get_attachment_url( $attachment_id );
			if ( ! $image_url ) {
				$this->queue_repo->mark_failed( $row_id, 'missing_image_url', 'Attachment URL not found.' );
				continue;
			}

			$post_id = isset( $job['post_id'] ) ? absint( $job['post_id'] ) : 0;
			$context = array(
				'attachment_title' => get_the_title( $attachment_id ),
				'post_title'       => $post_id ? get_the_title( $post_id ) : '',
			);

			$result = $this->provider->generate_caption( $image_url, $context );
			if ( is_wp_error( $result ) ) {
				$this->queue_repo->mark_failed( $row_id, $result->get_error_code(), $result->get_error_message() );
				$this->logger->log(
					'Caption generation failed',
					array(
						'row_id'        => $row_id,
						'attachment_id' => $attachment_id,
						'error'         => $result->get_error_code(),
					)
				);
				continue;
			}

			$caption    = isset( $result['caption'] ) ? (string) $result['caption'] : '';
			$confidence = isset( $result['confidence'] ) ? (float) $result['confidence'] : 0.0;
			$alt_text   = $this->generator->to_alt_text( $caption );

			if ( ! $this->generator->is_usable_alt( $alt_text ) ) {
				$this->queue_repo->mark_failed( $row_id, 'bad_alt_output', 'Generated alt text did not pass quality checks.' );
				continue;
			}

			$this->queue_repo->mark_generated( $row_id, wp_json_encode( $result ), $alt_text, $confidence );

			if ( ! $need_review && $confidence >= $min_conf ) {
				$this->approve_row( $row_id, $alt_text );
			}

			++$processed;
		}

		return $processed;
	}

	/**
	 * Process one attachment into a generated suggestion for review.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function process_attachment_for_review( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}

		$row = $this->queue_repo->get_row_by_attachment( $attachment_id );
		if ( ! is_array( $row ) ) {
			return false;
		}

		$row_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		if ( ! $row_id ) {
			return false;
		}

		$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
		if ( ! in_array( $status, array( 'queued', 'failed' ), true ) ) {
			return false;
		}

		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			$this->queue_repo->mark_failed( $row_id, 'missing_image_url', 'Attachment URL not found.' );
			return false;
		}

		$post_id = isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0;
		$context = array(
			'attachment_title' => get_the_title( $attachment_id ),
			'post_title'       => $post_id ? get_the_title( $post_id ) : '',
		);

		$result = $this->provider->generate_caption( $image_url, $context );
		if ( is_wp_error( $result ) ) {
			$this->queue_repo->mark_failed( $row_id, $result->get_error_code(), $result->get_error_message() );
			return false;
		}

		$caption    = isset( $result['caption'] ) ? (string) $result['caption'] : '';
		$confidence = isset( $result['confidence'] ) ? (float) $result['confidence'] : 0.0;
		$alt_text   = $this->generator->to_alt_text( $caption );
		if ( ! $this->generator->is_usable_alt( $alt_text ) ) {
			$this->queue_repo->mark_failed( $row_id, 'bad_alt_output', 'Generated alt text did not pass quality checks.' );
			return false;
		}

		$this->queue_repo->mark_generated( $row_id, wp_json_encode( $result ), $alt_text, $confidence );
		update_post_meta( $attachment_id, '_ai_alt_review_required', 1 );

		return true;
	}

	/**
	 * Approve row and persist alt text.
	 *
	 * @param int    $row_id Row ID.
	 * @param string $alt_text Alt text.
	 * @return bool
	 */
	public function approve_row( $row_id, $alt_text ) {
		$row = $this->queue_repo->get_row( $row_id );
		if ( ! is_array( $row ) ) {
			return false;
		}

		$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			return false;
		}

		$alt_text = $this->generator->to_alt_text( $alt_text );
		if ( '' === $alt_text ) {
			return false;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		update_post_meta( $attachment_id, '_ai_alt_last_generated_at', current_time( 'mysql' ) );
		update_post_meta( $attachment_id, '_ai_alt_source_provider', 'cloudflare' );
		update_post_meta( $attachment_id, '_ai_alt_review_required', 0 );

		return $this->queue_repo->mark_final( $row_id, 'approved', $alt_text );
	}
}
