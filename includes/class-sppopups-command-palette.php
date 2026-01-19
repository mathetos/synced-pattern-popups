<?php
/**
 * Command Palette Integration
 * Provides WordPress Command Palette integration for Synced Pattern Popups
 *
 * @package SPPopups
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * SPPopups_Command_Palette class.
 *
 * Handles all Command Palette functionality for the plugin.
 * This class is isolated from other plugin code to prevent interference.
 *
 * @package SPPopups
 * @since 1.2.2
 */
class SPPopups_Command_Palette {

	/**
	 * Pattern service instance
	 *
	 * @var SPPopups_Pattern
	 */
	private $pattern_service;

	/**
	 * Cache service instance
	 *
	 * @var SPPopups_Cache
	 */
	private $cache_service;

	/**
	 * Constructor
	 *
	 * @param SPPopups_Pattern $pattern_service Pattern service instance.
	 * @param SPPopups_Cache   $cache_service   Cache service instance.
	 */
	public function __construct( SPPopups_Pattern $pattern_service, SPPopups_Cache $cache_service ) {
		$this->pattern_service = $pattern_service;
		$this->cache_service   = $cache_service;
	}

	/**
	 * Initialize command palette integration
	 *
	 * Only initializes if WordPress 6.3+ (Command Palette API requirement).
	 */
	public function init() {
		// Command Palette API requires WordPress 6.3+.
		if ( version_compare( get_bloginfo( 'version' ), '6.3', '<' ) ) {
			return;
		}

		// Only initialize in admin area.
		if ( ! is_admin() ) {
			return;
		}

		// Hook into admin enqueue scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue command palette assets
	 *
	 * Enqueues the JavaScript file that registers all command palette commands.
	 * Only enqueues for users with edit_posts capability.
	 *
	 * @param string $hook Current admin page hook. Unused but required by hook signature.
	 */
	public function enqueue_assets( $hook ) {
		// phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UnusedVariable -- Required by hook signature.
		// Only enqueue for users with required capability.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Enqueue command palette script with required dependencies.
		wp_enqueue_script(
			'sppopups-command-palette',
			SPPOPUPS_PLUGIN_URL . 'assets/js/command-palette.js',
			array(
				'wp-commands',
				'wp-element',
				'wp-i18n',
				'wp-url',
				'wp-primitives',
			),
			SPPOPUPS_VERSION,
			true
		);

		// Localize script with data needed by JavaScript.
		wp_localize_script(
			'sppopups-command-palette',
			'sppopupsCommandPalette',
			array(
				'adminUrl'         => admin_url(),
				'clearCacheNonce'  => wp_create_nonce( 'clear_popup_cache' ),
				'canManageOptions' => current_user_can( 'manage_options' ),
				'strings'          => array(
					'goToSettings' => __( 'Go to: Appearance > Synced Patterns', 'synced-pattern-popups' ),
					'clearCache'   => __( 'Clear Popup Cache', 'synced-pattern-popups' ),
				),
			)
		);
	}
}
