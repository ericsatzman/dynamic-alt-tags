<?php
/**
 * Uninstall cleanup.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = get_option( 'ai_alt_text_options', array() );
$keep    = is_array( $options ) && ! empty( $options['keep_data_on_delete'] );

if ( $keep ) {
	return;
}

delete_option( 'ai_alt_text_options' );

delete_metadata( 'post', 0, '_ai_alt_last_generated_at', '', true );
delete_metadata( 'post', 0, '_ai_alt_source_provider', '', true );
delete_metadata( 'post', 0, '_ai_alt_hash', '', true );
delete_metadata( 'post', 0, '_ai_alt_review_required', '', true );

global $wpdb;
$table_name = $wpdb->prefix . 'ai_alt_queue';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
