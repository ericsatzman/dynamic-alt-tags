<?php
/**
 * Provider contract.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface WPAI_Alt_Text_Provider_Interface {

	/**
	 * Generate image caption.
	 *
	 * @param string $image_url Public image URL.
	 * @param array  $context   Context.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate_caption( $image_url, $context = array() );
}
