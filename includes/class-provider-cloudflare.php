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
			return new WP_Error( 'ai_alt_provider_http_error', sprintf( 'Provider returned HTTP %d', (int) $code ) );
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
