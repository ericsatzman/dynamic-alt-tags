<?php
/**
 * Cloudflare Worker provider.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Provider_Cloudflare implements WPAI_Alt_Text_Provider_Interface {
	/**
	 * Max bytes to inline in direct upload mode.
	 */
	const MAX_INLINE_IMAGE_BYTES = 10485760; // 10MB.

	/**
	 * Settings.
	 *
	 * @var WPAI_Alt_Text_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param WPAI_Alt_Text_Settings $settings Settings object.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Generate caption.
	 *
	 * @param string $image_url Image URL.
	 * @param array  $context Context.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate_caption( $image_url, $context = array() ) {
		$options    = $this->settings->get_options();
		$worker_url = isset( $options['worker_url'] ) ? trim( (string) $options['worker_url'] ) : '';
		$token      = isset( $options['cloudflare_token'] ) ? trim( (string) $options['cloudflare_token'] ) : '';
		$use_direct = ! empty( $options['direct_upload_mode'] );

		if ( '' === $worker_url ) {
			return new WP_Error( 'ai_alt_missing_worker_url', __( 'Cloudflare Worker URL is not configured.', 'dynamic-alt-tags' ) );
		}

		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$payload      = array(
			'image_url' => esc_url_raw( $image_url ),
			'context'   => array(
				'attachment_title' => isset( $context['attachment_title'] ) ? sanitize_text_field( (string) $context['attachment_title'] ) : '',
				'post_title'       => isset( $context['post_title'] ) ? sanitize_text_field( (string) $context['post_title'] ) : '',
			),
			'rules'     => array(
				'concise'       => true,
				'no_guessing'   => true,
				'max_words'     => 18,
				'no_image_of'   => true,
				'alt_text_mode' => true,
			),
		);
		$request_mode = 'url';

		if ( $use_direct ) {
			$attachment_id = isset( $context['attachment_id'] ) ? absint( $context['attachment_id'] ) : 0;
			if ( $attachment_id > 0 ) {
				$direct_payload = $this->build_direct_image_payload( $attachment_id );
				if ( ! is_wp_error( $direct_payload ) ) {
					$payload      = array_merge( $payload, $direct_payload );
					$request_mode = 'bytes';
				}
			}
		}

		$response = wp_remote_post(
			$worker_url,
			array(
				'timeout' => 90,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				$response->get_error_code(),
				sprintf(
					/* translators: 1: error message, 2: request mode */
					__( '%1$s; request mode: %2$s', 'dynamic-alt-tags' ),
					$response->get_error_message(),
					$request_mode
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$detail       = '';
			$fetch_url    = esc_url_raw( $image_url );
			$fetch_status = 0;

			if ( is_array( $data ) ) {
				if ( isset( $data['error'] ) ) {
					$detail = (string) $data['error'];
				} elseif ( isset( $data['message'] ) ) {
					$detail = (string) $data['message'];
				}

				$url_keys = array( 'fetch_url', 'image_url', 'url' );
				foreach ( $url_keys as $url_key ) {
					if ( isset( $data[ $url_key ] ) && is_string( $data[ $url_key ] ) && '' !== trim( $data[ $url_key ] ) ) {
						$fetch_url = esc_url_raw( (string) $data[ $url_key ] );
						break;
					}
				}

				$status_keys = array( 'fetch_status', 'upstream_status', 'status', 'status_code', 'http_status' );
				foreach ( $status_keys as $status_key ) {
					if ( isset( $data[ $status_key ] ) ) {
						$candidate = absint( $data[ $status_key ] );
						if ( $candidate > 0 ) {
							$fetch_status = $candidate;
							break;
						}
					}
				}
			}

			if ( '' === trim( $detail ) && is_string( $body ) && '' !== trim( $body ) ) {
				$detail = wp_strip_all_tags( $body );
			}

			$detail = trim( preg_replace( '/\s+/', ' ', (string) $detail ) );
			$parts  = array();
			if ( '' !== $detail ) {
				$parts[] = sanitize_text_field( substr( $detail, 0, 220 ) );
			}
			if ( $fetch_status > 0 ) {
				$parts[] = sprintf(
					/* translators: %d upstream status code */
					__( 'upstream status %d', 'dynamic-alt-tags' ),
					$fetch_status
				);
			}
			if ( '' !== $fetch_url ) {
				$parts[] = sprintf(
					/* translators: %s image fetch URL */
					__( 'image URL: %s', 'dynamic-alt-tags' ),
					$fetch_url
				);
			}
			$parts[] = sprintf(
				/* translators: %s request mode */
				__( 'request mode: %s', 'dynamic-alt-tags' ),
				$request_mode
			);

			if ( ! empty( $parts ) ) {
				return new WP_Error(
					'ai_alt_provider_http_error',
					sprintf(
						/* translators: 1: HTTP status code, 2: provider error detail */
						__( 'Provider returned HTTP %1$d: %2$s', 'dynamic-alt-tags' ),
						(int) $code,
						implode( '; ', $parts )
					)
				);
			}

			return new WP_Error(
				'ai_alt_provider_http_error',
				sprintf(
					/* translators: %d HTTP status code */
					__( 'Provider returned HTTP %d', 'dynamic-alt-tags' ),
					(int) $code
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'ai_alt_provider_parse_error', __( 'Provider returned invalid JSON.', 'dynamic-alt-tags' ) );
		}

		$caption = '';
		if ( isset( $data['alt_text'] ) ) {
			$caption = (string) $data['alt_text'];
		} elseif ( isset( $data['caption'] ) ) {
			$caption = (string) $data['caption'];
		}

		$confidence = isset( $data['confidence'] ) ? (float) $data['confidence'] : 0.0;

		return array(
			'caption'    => $caption,
			'confidence' => max( 0.0, min( 1.0, $confidence ) ),
			'raw'        => $data,
		);
	}

	/**
	 * Build direct-upload payload fields for one attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,string>|WP_Error
	 */
	private function build_direct_image_payload( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return new WP_Error( 'ai_alt_invalid_attachment', __( 'Invalid attachment for direct upload mode.', 'dynamic-alt-tags' ) );
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! is_string( $file_path ) || '' === trim( $file_path ) ) {
			return new WP_Error( 'ai_alt_missing_attachment_file', __( 'Attachment file path was not found for direct upload mode.', 'dynamic-alt-tags' ) );
		}

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error( 'ai_alt_unreadable_attachment_file', __( 'Attachment file is not readable for direct upload mode.', 'dynamic-alt-tags' ) );
		}

		$file_size = filesize( $file_path );
		if ( false !== $file_size && $file_size > self::MAX_INLINE_IMAGE_BYTES ) {
			return new WP_Error(
				'ai_alt_attachment_too_large',
				sprintf(
					/* translators: %d max size in MB */
					__( 'Attachment is too large for direct upload mode (max %d MB).', 'dynamic-alt-tags' ),
					10
				)
			);
		}

		$binary = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $binary ) || '' === $binary ) {
			return new WP_Error( 'ai_alt_attachment_read_failed', __( 'Attachment file could not be read for direct upload mode.', 'dynamic-alt-tags' ) );
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! is_string( $mime_type ) || 0 !== strpos( $mime_type, 'image/' ) ) {
			$mime_type = 'application/octet-stream';
		}

		return array(
			'image_source'      => 'bytes',
			'image_data_base64' => base64_encode( $binary ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'image_mime_type'   => sanitize_text_field( $mime_type ),
			'image_filename'    => sanitize_file_name( basename( $file_path ) ),
		);
	}
}
