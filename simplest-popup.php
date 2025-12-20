<?php
/**
 * Plugin Name: The Simplest of Popups
 * Plugin URI: https://wpproducttalk.com
 * Description: A lightweight modal popup system that loads WordPress Synced Pattern content on demand. Trigger with class "wppt-popup-{id}".
 * Version: 1.0.0
 * Author: WP Product Talk
 * Author URI: https://wpproducttalk.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simplest-popup
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'SIMPLEST_POPUP_VERSION', '1.0.0' );
define( 'SIMPLEST_POPUP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLEST_POPUP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLEST_POPUP_CACHE_TTL', 12 * HOUR_IN_SECONDS ); // 12 hours default

// Load required classes
require_once SIMPLEST_POPUP_PLUGIN_DIR . 'includes/class-simplest-popup-pattern.php';
require_once SIMPLEST_POPUP_PLUGIN_DIR . 'includes/class-simplest-popup-cache.php';
require_once SIMPLEST_POPUP_PLUGIN_DIR . 'includes/class-simplest-popup-style-collector.php';
require_once SIMPLEST_POPUP_PLUGIN_DIR . 'includes/class-simplest-popup-ajax.php';
require_once SIMPLEST_POPUP_PLUGIN_DIR . 'includes/class-simplest-popup-admin.php';
require_once SIMPLEST_POPUP_PLUGIN_DIR . 'includes/class-simplest-popup-plugin.php';

// Initialize plugin
add_action( 'plugins_loaded', 'simplest_popup_init' );

/**
 * Initialize the plugin
 */
function simplest_popup_init() {
	$plugin = new Simplest_Popup_Plugin();
	$plugin->init();
}

