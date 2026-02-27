<?php
/**
 * Alt text normalization.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Generator {

	/**
	 * Convert caption to clean alt text.
	 *
	 * @param string $caption Caption.
	 * @return string
	 */
	public function to_alt_text( $caption ) {
		$text = wp_strip_all_tags( (string) $caption );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( (string) $text );
		$text = preg_replace( '/^(an?\s+)?(image|photo|picture)\s+of\s+/i', '', $text );

		if ( ! is_string( $text ) ) {
			return '';
		}

		$text = trim( $text, " \t\n\r\0\x0B.,;:-" );

		if ( strlen( $text ) > 140 ) {
			$text = substr( $text, 0, 140 );
			$pos  = strrpos( $text, ' ' );
			if ( false !== $pos ) {
				$text = substr( $text, 0, $pos );
			}

			$sentence_pos = max(
				(int) strrpos( $text, '.' ),
				(int) strrpos( $text, '!' ),
				(int) strrpos( $text, '?' )
			);
			if ( $sentence_pos > 0 ) {
				$text = substr( $text, 0, $sentence_pos + 1 );
			}
		}

		if ( '' !== $text ) {
			$text = ucfirst( $text );
		}

		return sanitize_text_field( $text );
	}

	/**
	 * Basic quality check for generated alt text.
	 *
	 * @param string $alt Alt text.
	 * @return bool
	 */
	public function is_usable_alt( $alt ) {
		$alt = trim( (string) $alt );
		if ( strlen( $alt ) < 5 ) {
			return false;
		}

		if ( preg_match( '/\.(jpg|jpeg|png|gif|webp|svg)$/i', $alt ) ) {
			return false;
		}

		return true;
	}
}
