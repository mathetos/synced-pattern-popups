<?php
/**
 * Plugin Name: Synced Pattern Popups
 * Description: A lightweight modal popup system that loads WordPress Synced Pattern content on demand. Trigger with class "spp-trigger-{id}".
 * Version: 1.0.0
 * Author: WP Product Talk
 * Author URI: https://www.mattcromwell.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sppopups
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'SPPOPUPS_VERSION', '1.0.0' );
define( 'SPPOPUPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPPOPUPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPPOPUPS_CACHE_TTL', 12 * HOUR_IN_SECONDS ); // 12 hours default

// Load required classes
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-pattern.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-cache.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-asset-collector.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-trigger-parser.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-ajax.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-admin.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-abilities.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-settings.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-tldr.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-plugin.php';

// Initialize plugin
add_action( 'plugins_loaded', 'sppopups_init' );

/**
 * Initialize the plugin
 */
function sppopups_init() {
	// Initialize settings
	$settings = new SPPopups_Settings();
	$settings->init();

	// Initialize main plugin
	$plugin = new SPPopups_Plugin();
	$plugin->init();
}

