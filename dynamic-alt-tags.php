<?php
/**
 * Plugin Name:       Dynamic Alt Tags
 * Description:       Generate and manage AI-suggested alt text for WordPress images.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Eric Satzman
 * Text Domain:       dynamic-alt-tags
 * Domain Path:       /languages
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAI_ALT_TEXT_VERSION', '0.1.0' );
define( 'WPAI_ALT_TEXT_FILE', __FILE__ );
define( 'WPAI_ALT_TEXT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAI_ALT_TEXT_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAI_ALT_TEXT_CRON_HOOK', 'ai_alt_text_process_queue' );

require_once WPAI_ALT_TEXT_DIR . 'includes/class-activator.php';
require_once WPAI_ALT_TEXT_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WPAI_Alt_Text_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPAI_Alt_Text_Activator', 'deactivate' ) );

WPAI_Alt_Text_Plugin::instance();
