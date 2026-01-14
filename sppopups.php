<?php
/**
 * Plugin Name: Synced Pattern Popups
 * Description: A lightweight modal popup system that loads WordPress Synced Pattern content on demand. Trigger with class "spp-trigger-{id}".
 * Version: 1.2.0
 * Author: Matt Cromwell
 * Author URI: https://www.mattcromwell.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: synced-pattern-popups
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'SPPOPUPS_VERSION', '1.2.0' );
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
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-review-notice.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-shipped-patterns.php';
require_once SPPOPUPS_PLUGIN_DIR . 'includes/class-sppopups-gallery.php';

// Register activation hook to set review notice trigger date and ensure shipped patterns
register_activation_hook( __FILE__, 'sppopups_activate' );

// Register uninstall hook for cleanup
register_uninstall_hook( __FILE__, 'sppopups_uninstall' );

// Initialize plugin
add_action( 'plugins_loaded', 'sppopups_init' );

/**
 * Plugin activation handler
 */
function sppopups_activate() {
	// Set review notice trigger date
	SPPopups_Review_Notice::set_trigger_date();
	
	// Ensure shipped patterns exist on activation
	SPPopups_Shipped_Patterns::activate();
}

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

	// Initialize gallery integration
	$gallery = new SPPopups_Gallery();
	$gallery->init();

	// Initialize review notice (admin only)
	if ( is_admin() ) {
		$review_notice = new SPPopups_Review_Notice();
		$review_notice->init();

		// Ensure shipped patterns exist (runs on install and when version changes)
		$shipped_patterns = new SPPopups_Shipped_Patterns();
		add_action( 'admin_init', array( $shipped_patterns, 'maybe_ensure_patterns' ) );
	}
}

/**
 * Cleanup on plugin uninstall
 */
function sppopups_uninstall() {
	SPPopups_Review_Notice::cleanup();
}

