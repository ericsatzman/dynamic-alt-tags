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

		if ( '' === $worker_url ) {
			return new WP_Error( 'ai_alt_missing_worker_url', __( 'Cloudflare Worker URL is not configured.', 'dynamic-alt-tags' ) );
		}

		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$payload = array(
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

		$response = wp_remote_post(
			$worker_url,
			array(
				'timeout' => 30,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$detail = '';
			$fetch_url = esc_url_raw( $image_url );
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
}
