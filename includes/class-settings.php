<?php
/**
 * Settings manager.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Settings {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'ai_alt_text_options';

	/**
	 * Get options with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get_options() {
		$defaults = array(
			'provider'            => 'cloudflare',
			'cloudflare_account'  => '',
			'cloudflare_token'    => '',
			'worker_url'          => '',
			'batch_size'          => 10,
			'min_confidence'      => 0.70,
			'overwrite_existing'  => 0,
			'require_review'      => 1,
			'keep_data_on_delete' => 0,
		);

		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		return wp_parse_args( $raw, $defaults );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! function_exists( 'register_setting' ) || ! function_exists( 'add_settings_section' ) || ! function_exists( 'add_settings_field' ) ) {
			return;
		}

		register_setting(
			'ai_alt_text_options_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);

		add_settings_section(
			'ai_alt_text_provider_section',
			__( 'Provider Settings', 'dynamic-alt-tags' ),
			'__return_false',
			'ai-alt-text-settings'
		);

		$fields = array(
			'worker_url'          => __( 'Cloudflare Worker URL', 'dynamic-alt-tags' ),
			'cloudflare_account'  => __( 'Cloudflare Account ID', 'dynamic-alt-tags' ),
			'cloudflare_token'    => __( 'Cloudflare API Token', 'dynamic-alt-tags' ),
			'batch_size'          => __( 'Batch Size', 'dynamic-alt-tags' ),
			'min_confidence'      => __( 'Min Confidence (0-1)', 'dynamic-alt-tags' ),
			'overwrite_existing'  => __( 'Overwrite Existing Alt Text', 'dynamic-alt-tags' ),
			'require_review'      => __( 'Require Manual Review', 'dynamic-alt-tags' ),
			'keep_data_on_delete' => __( 'Keep Data On Delete', 'dynamic-alt-tags' ),
		);

		foreach ( $fields as $field_id => $label ) {
			add_settings_field(
				$field_id,
				$label,
				array( $this, 'render_field' ),
				'ai-alt-text-settings',
				'ai_alt_text_provider_section',
				array( 'id' => $field_id )
			);
		}
	}

	/**
	 * Sanitize options.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize_options( $input ) {
		$current = $this->get_options();
		$input   = is_array( $input ) ? $input : array();

		$current['provider'] = 'cloudflare';

		if ( isset( $input['worker_url'] ) ) {
			$worker_url_raw = trim( (string) $input['worker_url'] );

			if ( '' === $worker_url_raw ) {
				$current['worker_url'] = '';
			} else {
				$worker_url = esc_url_raw( $worker_url_raw );

				// If user omitted scheme, try https:// before treating it as invalid.
				if ( '' === $worker_url && false === strpos( $worker_url_raw, '://' ) ) {
					$worker_url = esc_url_raw( 'https://' . $worker_url_raw );
				}

				// Keep last valid value instead of clearing the field on invalid input.
				if ( '' !== $worker_url ) {
					$current['worker_url'] = $worker_url;
				}
			}
		}

		$current['cloudflare_account'] = isset( $input['cloudflare_account'] ) ? sanitize_text_field( (string) $input['cloudflare_account'] ) : '';

		if ( isset( $input['cloudflare_token'] ) ) {
			$token = trim( (string) $input['cloudflare_token'] );
			if ( '' !== $token ) {
				$current['cloudflare_token'] = sanitize_text_field( $token );
			}
		}

		$current['batch_size'] = isset( $input['batch_size'] ) ? max( 1, min( 50, absint( $input['batch_size'] ) ) ) : 10;

		$current['min_confidence'] = isset( $input['min_confidence'] ) ? (float) $input['min_confidence'] : 0.70;
		$current['min_confidence'] = max( 0.00, min( 1.00, $current['min_confidence'] ) );

		$current['overwrite_existing']  = ! empty( $input['overwrite_existing'] ) ? 1 : 0;
		$current['require_review']      = ! empty( $input['require_review'] ) ? 1 : 0;
		$current['keep_data_on_delete'] = ! empty( $input['keep_data_on_delete'] ) ? 1 : 0;

		return $current;
	}

	/**
	 * Render settings field.
	 *
	 * @param array<string,string> $args Field args.
	 * @return void
	 */
	public function render_field( $args ) {
		$options = $this->get_options();
		$id      = isset( $args['id'] ) ? $args['id'] : '';

		if ( '' === $id ) {
			return;
		}

		$name = self::OPTION_KEY . '[' . $id . ']';

		if ( in_array( $id, array( 'overwrite_existing', 'require_review', 'keep_data_on_delete' ), true ) ) {
			printf(
				'<label><input type="checkbox" name="%1$s" value="1" %2$s /></label>',
				esc_attr( $name ),
				checked( 1, (int) $options[ $id ], false )
			);
			return;
		}

		$type = 'text';
		$step = '';
		if ( 'batch_size' === $id ) {
			$type = 'number';
		}
		if ( 'min_confidence' === $id ) {
			$type = 'number';
			$step = ' step="0.01" min="0" max="1"';
		}

		printf(
			'<input class="regular-text" type="%1$s" name="%2$s" value="%3$s"%4$s />',
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( (string) $options[ $id ] ),
			$step // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
}
