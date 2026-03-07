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
	 * Metrics option key.
	 *
	 * @var string
	 */
	const METRICS_OPTION_KEY = 'ai_alt_text_metrics';

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
			'use_url_mode'        => 0,
			'batch_size'          => 10,
			'min_confidence'      => 0.70,
			'auto_apply_new_uploads' => 0,
			'sync_title_from_alt' => 1,
			'allowed_roles'       => array( 'administrator' ),
			'overwrite_existing'  => 0,
			'require_review'      => 1,
			'keep_data_on_delete' => 0,
		);

		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$options = wp_parse_args( $raw, $defaults );

		// Backward compatibility: older versions used direct_upload_mode.
		if ( ! array_key_exists( 'use_url_mode', $raw ) && array_key_exists( 'direct_upload_mode', $raw ) ) {
			$options['use_url_mode'] = ! empty( $raw['direct_upload_mode'] ) ? 0 : 1;
		}

		return $options;
	}

	/**
	 * Get processing metrics with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get_metrics() {
		$defaults = array(
			'total_images_processed'   => 0,
			'success_count'            => 0,
			'failure_count'            => 0,
			'provider_call_count'      => 0,
			'total_processing_time_ms' => 0.0,
			'total_provider_latency_ms' => 0.0,
			'last_processing_time_ms'  => 0.0,
			'last_provider_latency_ms' => 0.0,
			'last_processed_at'        => '',
		);

		$raw = get_option( self::METRICS_OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$metrics = wp_parse_args( $raw, $defaults );

		$metrics['total_images_processed']    = max( 0, absint( $metrics['total_images_processed'] ) );
		$metrics['success_count']             = max( 0, absint( $metrics['success_count'] ) );
		$metrics['failure_count']             = max( 0, absint( $metrics['failure_count'] ) );
		$metrics['provider_call_count']       = max( 0, absint( $metrics['provider_call_count'] ) );
		$metrics['total_processing_time_ms']  = max( 0.0, (float) $metrics['total_processing_time_ms'] );
		$metrics['total_provider_latency_ms'] = max( 0.0, (float) $metrics['total_provider_latency_ms'] );
		$metrics['last_processing_time_ms']   = max( 0.0, (float) $metrics['last_processing_time_ms'] );
		$metrics['last_provider_latency_ms']  = max( 0.0, (float) $metrics['last_provider_latency_ms'] );
		$metrics['last_processed_at']         = is_string( $metrics['last_processed_at'] ) ? sanitize_text_field( $metrics['last_processed_at'] ) : '';

		return $metrics;
	}

	/**
	 * Persist processing metrics.
	 *
	 * @param array<string,mixed> $event Metric event values.
	 * @return void
	 */
	public function record_processing_metrics( $event ) {
		$metrics = $this->get_metrics();

		$is_success          = ! empty( $event['success'] );
		$provider_call_count = ! empty( $event['provider_called'] ) ? 1 : 0;
		$processing_time_ms  = isset( $event['processing_time_ms'] ) ? max( 0.0, (float) $event['processing_time_ms'] ) : 0.0;
		$provider_latency_ms = isset( $event['provider_latency_ms'] ) ? max( 0.0, (float) $event['provider_latency_ms'] ) : 0.0;

		$metrics['total_images_processed'] += 1;
		if ( $is_success ) {
			$metrics['success_count'] += 1;
		} else {
			$metrics['failure_count'] += 1;
		}
		$metrics['provider_call_count']       += $provider_call_count;
		$metrics['total_processing_time_ms']  += $processing_time_ms;
		$metrics['total_provider_latency_ms'] += $provider_latency_ms;
		$metrics['last_processing_time_ms']    = $processing_time_ms;
		$metrics['last_provider_latency_ms']   = $provider_latency_ms;
		$metrics['last_processed_at']          = current_time( 'mysql' );

		update_option( self::METRICS_OPTION_KEY, $metrics, false );
	}

	/**
	 * Reset processing metrics to defaults.
	 *
	 * @return void
	 */
	public function reset_metrics() {
		delete_option( self::METRICS_OPTION_KEY );
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

		add_settings_section(
			'ai_alt_text_access_section',
			__( 'Access Control', 'dynamic-alt-tags' ),
			'__return_false',
			'ai-alt-text-settings'
		);

		$fields = array(
			'worker_url'          => __( 'Cloudflare Worker URL', 'dynamic-alt-tags' ),
			'cloudflare_token'    => __( 'Cloudflare API Token', 'dynamic-alt-tags' ),
			'batch_size'          => __( 'Batch Size', 'dynamic-alt-tags' ),
			'min_confidence'      => __( 'Min Confidence (0-1)', 'dynamic-alt-tags' ),
					'use_url_mode'        => __( 'Use URL Mode - Send Image URL', 'dynamic-alt-tags' ),
					'auto_apply_new_uploads' => __( 'Auto-Apply Alt Text for New Uploads', 'dynamic-alt-tags' ),
					'sync_title_from_alt' => __( 'Sync Alt Text to Attachment Title', 'dynamic-alt-tags' ),
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

		add_settings_field(
			'allowed_roles',
			__( 'Roles Allowed To Access Dynamic Alt Tags', 'dynamic-alt-tags' ),
			array( $this, 'render_field' ),
			'ai-alt-text-settings',
			'ai_alt_text_access_section',
			array( 'id' => 'allowed_roles' )
		);

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
			$current['cloudflare_token'] = '' === $token ? '' : sanitize_text_field( $token );
		}

		$current['batch_size'] = isset( $input['batch_size'] ) ? max( 1, min( 50, absint( $input['batch_size'] ) ) ) : 10;

		$current['min_confidence'] = isset( $input['min_confidence'] ) ? (float) $input['min_confidence'] : 0.70;
		$current['min_confidence'] = max( 0.00, min( 1.00, $current['min_confidence'] ) );

		$current['use_url_mode']        = ! empty( $input['use_url_mode'] ) ? 1 : 0;
		$current['auto_apply_new_uploads'] = ! empty( $input['auto_apply_new_uploads'] ) ? 1 : 0;
		$current['sync_title_from_alt'] = ! empty( $input['sync_title_from_alt'] ) ? 1 : 0;
		$current['overwrite_existing']  = ! empty( $input['overwrite_existing'] ) ? 1 : 0;
		$current['require_review']      = ! empty( $input['require_review'] ) ? 1 : 0;
		$current['keep_data_on_delete'] = ! empty( $input['keep_data_on_delete'] ) ? 1 : 0;
		$current['allowed_roles']       = array( 'administrator' );
		if ( isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ) {
			$roles = array_filter(
				array_map(
					'sanitize_key',
					array_map( 'strval', wp_unslash( $input['allowed_roles'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				)
			);
			if ( ! empty( $roles ) ) {
				$current['allowed_roles'] = array_values( array_unique( $roles ) );
			}
		}

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

		if ( 'allowed_roles' === $id ) {
			$selected_roles = isset( $options['allowed_roles'] ) && is_array( $options['allowed_roles'] ) ? array_map( 'strval', $options['allowed_roles'] ) : array( 'administrator' );
			$wp_roles       = wp_roles();
			$roles          = $wp_roles instanceof WP_Roles ? $wp_roles->roles : array();
			$sortable_roles = array();
			foreach ( $roles as $role_key => $role_data ) {
				$role_label = isset( $role_data['name'] ) ? (string) $role_data['name'] : (string) $role_key;
				$sortable_roles[] = array(
					'key'   => (string) $role_key,
					'label' => $role_label,
				);
			}
			usort(
				$sortable_roles,
				static function ( $a, $b ) {
					return strcasecmp( (string) $a['label'], (string) $b['label'] );
				}
			);
			foreach ( $sortable_roles as $role_item ) {
				printf(
					'<label style="display:block; margin-bottom:6px;"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
					esc_attr( $name ),
					esc_attr( (string) $role_item['key'] ),
					checked( true, in_array( (string) $role_item['key'], $selected_roles, true ), false ),
					esc_html( (string) $role_item['label'] )
				);
			}
			echo '<p class="description">' . esc_html__( 'Administrator always has full access. Selected roles can access only the Dynamic Alt Tags Queue page under Media.', 'dynamic-alt-tags' ) . '</p>';
			return;
		}

		if ( in_array( $id, array( 'use_url_mode', 'auto_apply_new_uploads', 'sync_title_from_alt', 'overwrite_existing', 'require_review', 'keep_data_on_delete' ), true ) ) {
			printf(
				'<label><input type="checkbox" name="%1$s" value="1" %2$s /></label>',
				esc_attr( $name ),
				checked( 1, (int) $options[ $id ], false )
			);

			if ( 'use_url_mode' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, the plugin sends image URLs and the Worker fetches images remotely. Leave unchecked to use Direct Upload Mode (default, recommended).', 'dynamic-alt-tags' ) . '</p>';
			} elseif ( 'auto_apply_new_uploads' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, newly uploaded images get AI alt text applied automatically after generation.', 'dynamic-alt-tags' ) . '</p>';
			} elseif ( 'sync_title_from_alt' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, applying alt text will also set the attachment title to the same value.', 'dynamic-alt-tags' ) . '</p>';
			}
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
		if ( 'cloudflare_token' === $id ) {
			$type = 'password';
		}

		$field_id = 'ai-alt-field-' . sanitize_html_class( $id );

		if ( 'cloudflare_token' === $id ) {
			printf(
				'<input id="%1$s" class="regular-text" type="%2$s" name="%3$s" value="%4$s" autocomplete="off" />',
				esc_attr( $field_id ),
				esc_attr( $type ),
				esc_attr( $name ),
				esc_attr( (string) $options[ $id ] )
			);
			printf(
				' <button type="button" class="button ai-alt-toggle-token" data-target="%1$s" data-show-label="%2$s" data-hide-label="%3$s" aria-pressed="false">%2$s</button>',
				esc_attr( $field_id ),
				esc_attr__( 'Show', 'dynamic-alt-tags' ),
				esc_attr__( 'Hide', 'dynamic-alt-tags' )
			);
			return;
		}

		printf(
			'<input id="%1$s" class="regular-text" type="%2$s" name="%3$s" value="%4$s"%5$s />',
			esc_attr( $field_id ),
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( (string) $options[ $id ] ),
			$step // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

	}

	/**
	 * Check whether a user is allowed to access plugin settings/queue pages.
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return bool
	 */
	public function current_user_has_access( $user_id = 0 ) {
		return $this->current_user_can_access_queue( $user_id );
	}

	/**
	 * Check whether a user can access plugin queue/media controls.
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return bool
	 */
	public function current_user_can_access_queue( $user_id = 0 ) {
		$user = $user_id > 0 ? get_user_by( 'id', absint( $user_id ) ) : wp_get_current_user();
		if ( ! ( $user instanceof WP_User ) || empty( $user->roles ) ) {
			return false;
		}

		if ( $this->current_user_is_administrator( $user_id ) ) {
			return true;
		}

		$options       = $this->get_options();
		$allowed_roles = isset( $options['allowed_roles'] ) && is_array( $options['allowed_roles'] )
			? array_filter( array_map( 'sanitize_key', array_map( 'strval', $options['allowed_roles'] ) ) )
			: array();

		if ( empty( $allowed_roles ) ) {
			$allowed_roles = array( 'administrator' );
		}

		return ! empty( array_intersect( $allowed_roles, array_map( 'strval', $user->roles ) ) );
	}

	/**
	 * Check whether a user can access plugin settings page.
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return bool
	 */
	public function current_user_can_access_settings( $user_id = 0 ) {
		return $this->current_user_is_administrator( $user_id );
	}

	/**
	 * Check whether user has administrator role.
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return bool
	 */
	public function current_user_is_administrator( $user_id = 0 ) {
		$user = $user_id > 0 ? get_user_by( 'id', absint( $user_id ) ) : wp_get_current_user();
		if ( ! ( $user instanceof WP_User ) || empty( $user->roles ) ) {
			return false;
		}

		return in_array( 'administrator', array_map( 'strval', $user->roles ), true );
	}
}
