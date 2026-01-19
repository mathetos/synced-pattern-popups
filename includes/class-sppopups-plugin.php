<?php
/**
 * Main Plugin Class
 * Handles front-end enqueuing and modal output
 *
 * @package SPPopups
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * SPPopups_Plugin class.
 *
 * @package SPPopups
 */
class SPPopups_Plugin {

	/**
	 * AJAX handler instance
	 *
	 * @var SPPopups_Ajax
	 */
	private $ajax_handler;

	/**
	 * Cache service instance
	 *
	 * @var SPPopups_Cache
	 */
	private $cache_service;

	/**
	 * Admin interface instance
	 *
	 * @var SPPopups_Admin
	 */
	private $admin;

	/**
	 * Pattern service instance (shared)
	 *
	 * @var SPPopups_Pattern
	 */
	private $pattern_service;

	/**
	 * Asset collector instance (shared)
	 *
	 * @var SPPopups_Asset_Collector
	 */
	private $style_collector;

	/**
	 * Abilities instance (WP 6.9+ only)
	 *
	 * @var SPPopups_Abilities|null
	 */
	private $abilities;

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Create shared service instances.
		$this->pattern_service = new SPPopups_Pattern();
		$this->cache_service   = new SPPopups_Cache();
		$this->style_collector = new SPPopups_Asset_Collector();

		// Initialize AJAX handler with shared services.
		$this->ajax_handler = new SPPopups_Ajax( $this->pattern_service, $this->cache_service, $this->style_collector );
		$this->ajax_handler->init();

		// Initialize admin interface with shared services.
		if ( is_admin() ) {
			$this->admin = new SPPopups_Admin( $this->pattern_service, $this->cache_service );
			$this->admin->init();
		}

		// Initialize Abilities API registration (WP 6.9+ only)
		// This will gracefully skip if Abilities API is not available.
		if ( function_exists( 'wp_register_ability' ) ) {
			$this->abilities = new SPPopups_Abilities( $this->pattern_service, $this->cache_service, $this->style_collector );
			$this->abilities->init();
		}

		// Initialize Command Palette integration (WP 6.3+ only)
		// This will gracefully skip if Command Palette API is not available.
		if ( version_compare( get_bloginfo( 'version' ), '6.3', '>=' ) && is_admin() ) {
			$command_palette = new SPPopups_Command_Palette( $this->pattern_service, $this->cache_service );
			$command_palette->init();
		}

		// Set up custom content filter to avoid conflicts with other plugins.
		$this->setup_content_filter();

		// Hook into front-end.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		// Always output modal HTML (lightweight, needed for JavaScript to work).
		add_action( 'wp_footer', array( $this, 'output_modal' ) );

		// Invalidate cache when synced patterns are updated.
		add_action( 'save_post_wp_block', array( $this, 'invalidate_cache' ), 10, 1 );

		// Invalidate cache when post status changes (publish -> draft, etc.).
		add_action( 'transition_post_status', array( $this, 'invalidate_cache_on_status_change' ), 10, 3 );

		// Invalidate cache when post password is added/removed.
		add_action( 'post_updated', array( $this, 'invalidate_cache_on_update' ), 10, 3 );

		// Invalidate TLDR cache when posts are updated.
		add_action( 'save_post', array( $this, 'invalidate_tldr_cache' ), 10, 1 );

		// Register post meta for popup support toggle.
		add_action( 'init', array( $this, 'register_post_meta' ) );
	}

	/**
	 * Set up custom content filter
	 * Creates a custom filter that duplicates 'the_content' functionality
	 * but avoids conflicts with plugins that hook into 'the_content'
	 * Based on Bill Erickson's approach: https://www.billerickson.net/code/duplicate-the_content-filters/
	 *
	 * @return void
	 */
	private function setup_content_filter() {
		global $wp_embed;

		// Apply block hooks before do_blocks (priority 8).
		if ( function_exists( 'apply_block_hooks_to_content_from_post_object' ) ) {
			add_filter( 'sppopups_the_content', 'apply_block_hooks_to_content_from_post_object', 8 );
		}

		// Render blocks (priority 9) - CRITICAL for block rendering and asset enqueuing.
		if ( function_exists( 'do_blocks' ) ) {
			add_filter( 'sppopups_the_content', 'do_blocks', 9 );
		}

		// Core WordPress content formatting functions.
		add_filter( 'sppopups_the_content', 'wptexturize' );
		add_filter( 'sppopups_the_content', 'convert_smilies', 20 );
		add_filter( 'sppopups_the_content', 'wpautop' );
		add_filter( 'sppopups_the_content', 'shortcode_unautop' );
		add_filter( 'sppopups_the_content', 'do_shortcode', 11 );
		add_filter( 'sppopups_the_content', 'wp_filter_content_tags', 12 );

		// oEmbed support (if wp_embed is available).
		if ( $wp_embed ) {
			add_filter( 'sppopups_the_content', array( $wp_embed, 'run_shortcode' ), 8 );
			add_filter( 'sppopups_the_content', array( $wp_embed, 'autoembed' ), 8 );
		}
	}

	/**
	 * Check if page contains popup triggers
	 *
	 * @return bool True if triggers found, false otherwise
	 */
	private function has_popup_triggers() {
		// Check if we're in admin (always load in admin for preview purposes).
		if ( is_admin() ) {
			return true;
		}

		// Check if post has forced modal assets loading enabled.
		if ( is_singular() ) {
			global $post;
			if ( $post ) {
				$modal_assets = get_post_meta( $post->ID, '_sppopups_modal_assets', true );
				if ( 'loaded' === $modal_assets ) {
					return true;
				}
			}
		}

		// Allow filter to force loading.
		if ( apply_filters( 'sppopups_force_load_assets', false ) ) {
			return true;
		}

		// Get page content to scan for triggers.
		global $post;

		// Check current post content.
		if ( $post && isset( $post->post_content ) ) {
			if ( $this->content_has_triggers( $post->post_content ) ) {
				return true;
			}

			// Check for Gallery blocks with sppopup linkTo option.
			if ( has_block( 'core/gallery', $post->post_content ) ) {
				$blocks = parse_blocks( $post->post_content );
				foreach ( $blocks as $block ) {
					if ( 'core/gallery' === $block['blockName'] &&
						isset( $block['attrs']['linkTo'] ) &&
						'sppopup' === $block['attrs']['linkTo'] ) {
						return true;
					}
				}
			}
		}

		// Check menu items for href triggers (lightweight - only check URLs).
		$locations = get_nav_menu_locations();
		if ( ! empty( $locations ) ) {
			foreach ( $locations as $location => $menu_id ) {
				if ( $menu_id ) {
					$menu_items = wp_get_nav_menu_items( $menu_id );
					if ( $menu_items ) {
						foreach ( $menu_items as $item ) {
							if ( isset( $item->url ) && ( preg_match( '/#spp-trigger-tldr/', $item->url ) || preg_match( '/#spp-trigger-\d+(?:-\d+)?/', $item->url ) ) ) {
								return true;
							}
						}
					}
				}
			}
		}

		// Check widget output if widgets are active (cache result to avoid repeated checks).
		static $widget_check_done   = false;
		static $widget_has_triggers = false;

		if ( ! $widget_check_done ) {
			$widget_check_done = true;
			// Only check if there are active widgets.
			if ( is_active_widget( false, false, 'text' ) || is_active_widget( false, false, 'html' ) || is_active_widget( false, false, 'custom_html' ) ) {
				// Widgets might contain triggers, use filter to allow theme/plugin to indicate.
				$widget_has_triggers = apply_filters( 'sppopups_widgets_have_triggers', false );
			}
		}

		if ( $widget_has_triggers ) {
			return true;
		}

		// Allow filter to override detection.
		return apply_filters( 'sppopups_has_triggers', false );
	}

	/**
	 * Check if content string contains popup triggers
	 *
	 * @param string $content Content to check.
	 * @return bool True if triggers found
	 */
	private function content_has_triggers( $content ) {
		if ( empty( $content ) ) {
			return false;
		}

		// Check for TLDR trigger.
		if ( preg_match( '/\bspp-trigger-tldr\b/', $content ) || preg_match( '/#spp-trigger-tldr/', $content ) ) {
			return true;
		}

		// Check for class-based triggers: spp-trigger-{id} or spp-trigger-{id}-{width}.
		if ( preg_match( '/\bspp-trigger-\d+(?:-\d+)?\b/', $content ) ) {
			return true;
		}

		// Check for href-based triggers: #spp-trigger-{id} or #spp-trigger-{id}-{width}.
		if ( preg_match( '/#spp-trigger-\d+(?:-\d+)?/', $content ) ) {
			return true;
		}

		// Check for gallery with sppopup link option.
		if ( preg_match( '/data-sppopup-gallery/', $content ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if page contains galleries with sppopup link option
	 *
	 * @return bool True if galleries are present
	 */
	private function has_galleries() {
		// Get page content.
		global $post;
		if ( ! $post || ! isset( $post->post_content ) ) {
			return false;
		}

		// Check for Gallery blocks with sppopup linkTo option (same logic as has_popup_triggers).
		if ( has_block( 'core/gallery', $post->post_content ) ) {
			$blocks = parse_blocks( $post->post_content );
			foreach ( $blocks as $block ) {
				if ( 'core/gallery' === $block['blockName'] &&
					isset( $block['attrs']['linkTo'] ) &&
					'sppopup' === $block['attrs']['linkTo'] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Enqueue CSS and JavaScript assets
	 */
	public function enqueue_assets() {
		// Check post meta for forced asset loading.
		$force_modal_assets   = false;
		$force_gallery_assets = false;

		if ( is_singular() ) {
			global $post;
			if ( $post ) {
				$modal_assets   = get_post_meta( $post->ID, '_sppopups_modal_assets', true );
				$gallery_assets = get_post_meta( $post->ID, '_sppopups_gallery_assets', true );

				if ( 'loaded' === $modal_assets ) {
					$force_modal_assets = true;
				}
				if ( 'loaded' === $gallery_assets ) {
					$force_gallery_assets = true;
					// Gallery assets depend on modal assets, so force modal assets too.
					$force_modal_assets = true;
				}
			}
		}

		// Check if page contains popup triggers (only if not forced).
		if ( ! $force_modal_assets && ! $this->has_popup_triggers() ) {
			return;
		}

		// Enqueue CSS (always enqueue if modal assets are forced or triggers found).
		if ( $force_modal_assets || $this->has_popup_triggers() ) {
			wp_enqueue_style(
				'simplest-popup-modal',
				SPPOPUPS_PLUGIN_URL . 'assets/css/modal.css',
				array(),
				SPPOPUPS_VERSION
			);
		}

		// Check if galleries are present.
		$has_galleries = $this->has_galleries();

		// Enqueue gallery JavaScript if galleries are present OR forced to load.
		if ( $has_galleries || $force_gallery_assets ) {
			wp_enqueue_script(
				'sppopups-gallery',
				SPPOPUPS_PLUGIN_URL . 'assets/js/gallery.js',
				array(),
				SPPOPUPS_VERSION,
				true
			);
		}

		// Enqueue JavaScript (always enqueue if modal assets are forced or triggers found).
		if ( $force_modal_assets || $this->has_popup_triggers() ) {
			wp_enqueue_script(
				'simplest-popup-modal',
				SPPOPUPS_PLUGIN_URL . 'assets/js/modal.js',
				( $has_galleries || $force_gallery_assets ) ? array( 'sppopups-gallery' ) : array(),
				SPPOPUPS_VERSION,
				true
			);
		}

		// Get style URLs for JavaScript injection.
		$style_urls = $this->get_style_urls();

		// Get script URLs for JavaScript injection.
		$script_urls = $this->get_script_urls();

		// Get current post ID for TLDR feature.
		$post_id = 0;
		if ( is_singular() ) {
			global $post;
			if ( $post && isset( $post->ID ) ) {
				$post_id = $post->ID;
			}
		}

		// Localize script with AJAX data.
		wp_localize_script(
			'simplest-popup-modal',
			'sppopups',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'sppopups_ajax' ),
				'postId'     => $post_id,
				'styleUrls'  => $style_urls,
				'scriptUrls' => $script_urls,
				'defaults'   => array(
					'pattern' => SPPopups_Settings::get_pattern_defaults(),
					'tldr'    => SPPopups_Settings::get_tldr_defaults(),
					'gallery' => SPPopups_Settings::get_gallery_defaults(),
				),
				'strings'    => array(
					'loading'  => __( 'Loading content...', 'synced-pattern-popups' ),
					'error'    => __( 'Error loading content. Please try again.', 'synced-pattern-popups' ),
					'notFound' => __( 'Content not found.', 'synced-pattern-popups' ),
				),
			)
		);
	}

	/**
	 * Output modal HTML structure
	 */
	public function output_modal() {
		?>
		<!-- Synced Pattern Popups Modal -->
		<div id="sppopups-modal" class="sppopups-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="sppopups-title" aria-describedby="sppopups-desc" tabindex="-1" style="display: none;">
			<div class="sppopups-overlay"></div>
			<div class="sppopups-container">
				<div class="sppopups-card">
					<h2 id="sppopups-title" class="sppopups-sr-only"></h2>
					<p id="sppopups-desc" class="sppopups-sr-only"><?php esc_html_e( 'Press Escape to close. Tab stays within the popup.', 'synced-pattern-popups' ); ?></p>
					<button class="sppopups-close" aria-label="<?php esc_attr_e( 'Close modal', 'synced-pattern-popups' ); ?>" type="button">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
					<div class="sppopups-content">
						<div class="sppopups-loading">
							<div class="sppopups-spinner"></div>
							<p><?php esc_html_e( 'Loading content...', 'synced-pattern-popups' ); ?></p>
						</div>
					</div>
					<div class="sppopups-footer">
						<button class="sppopups-close-footer" type="button" aria-label="<?php esc_attr_e( 'Close modal', 'synced-pattern-popups' ); ?>">
							<?php esc_html_e( 'Close', 'synced-pattern-popups' ); ?> →
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Invalidate cache when a synced pattern is saved
	 *
	 * @param int $post_id Post ID.
	 */
	public function invalidate_cache( $post_id ) {
		if ( get_post_type( $post_id ) === 'wp_block' ) {
			// Only invalidate cache for synced patterns.
			if ( $this->pattern_service->is_synced_pattern( $post_id ) ) {
				$this->clear_pattern_cache( $post_id );
			}
		}
	}

	/**
	 * Invalidate cache when post status changes
	 * Handles visibility changes (publish -> draft, etc.)
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function invalidate_cache_on_status_change( $new_status, $old_status, $post ) {
		// Only process wp_block post type.
		if ( ! $post || 'wp_block' !== $post->post_type ) {
			return;
		}

		// If status changed from or to publish, invalidate cache.
		// This handles cases where pattern visibility changes.
		if ( $old_status !== $new_status && ( 'publish' === $old_status || 'publish' === $new_status ) ) {
			if ( $this->pattern_service->is_synced_pattern( $post->ID ) ) {
				$this->clear_pattern_cache( $post->ID );
			}
		}
	}

	/**
	 * Invalidate cache when post is updated
	 * Handles password protection changes and other updates
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 */
	public function invalidate_cache_on_update( $post_id, $post_after, $post_before ) {
		// Only process wp_block post type.
		if ( ! $post_after || 'wp_block' !== $post_after->post_type ) {
			return;
		}

		// Check if password protection changed.
		$password_changed = ( $post_before->post_password !== $post_after->post_password );

		// Check if post status changed.
		$status_changed = ( $post_before->post_status !== $post_after->post_status );

		// If password or status changed, invalidate cache.
		if ( $password_changed || $status_changed ) {
			if ( $this->pattern_service->is_synced_pattern( $post_id ) ) {
				$this->clear_pattern_cache( $post_id );
			}
		}
	}

	/**
	 * Clear pattern cache (helper method)
	 *
	 * @param int $post_id Post ID.
	 */
	private function clear_pattern_cache( $post_id ) {
		// Clear rendered HTML cache.
		$this->cache_service->delete( $post_id );

		// Clear pattern object cache.
		$pattern_cache_key = 'sppopups_pattern_' . $post_id;
		wp_cache_delete( $pattern_cache_key, 'sppopups_patterns' );
	}

	/**
	 * Invalidate TLDR cache when post is updated
	 *
	 * @param int $post_id Post ID.
	 */
	public function invalidate_tldr_cache( $post_id ) {
		// Only clear TLDR cache for published posts (not wp_block).
		$post = get_post( $post_id );
		if ( ! $post || 'wp_block' === $post->post_type ) {
			return;
		}

		// Only clear if post is published.
		if ( 'publish' === $post->post_status ) {
			$tldr_service = new SPPopups_TLDR( $this->cache_service );
			$tldr_service->clear_tldr_cache( $post_id );
		}
	}

	/**
	 * Register post meta for popup support toggle
	 */
	public function register_post_meta() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			// Skip attachment post type.
			if ( 'attachment' === $post_type ) {
				continue;
			}

			// Register modal assets meta.
			register_post_meta(
				$post_type,
				'_sppopups_modal_assets',
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => array( $this, 'sanitize_assets_setting' ),
					'show_in_rest'      => true,
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
					'default'           => 'auto-detect',
				)
			);

			// Register gallery assets meta.
			register_post_meta(
				$post_type,
				'_sppopups_gallery_assets',
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => array( $this, 'sanitize_assets_setting' ),
					'show_in_rest'      => true,
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
					'default'           => 'auto-detect',
				)
			);
		}
	}

	/**
	 * Sanitize assets setting meta value
	 *
	 * @param string $value Meta value.
	 * @return string Sanitized value
	 */
	public function sanitize_assets_setting( $value ) {
		$allowed = array( 'auto-detect', 'loaded' );
		return in_array( $value, $allowed, true ) ? $value : 'auto-detect';
	}

	/**
	 * Sanitize popup support meta value (deprecated, kept for backward compatibility)
	 *
	 * @param string $value Meta value.
	 * @return string Sanitized value
	 * @deprecated Use sanitize_assets_setting instead
	 */
	public function sanitize_popup_support( $value ) {
		$allowed = array( 'default', 'forced' );
		return in_array( $value, $allowed, true ) ? $value : 'default';
	}

	/**
	 * Normalize asset URL (make relative URLs absolute, add version, apply filters)
	 * Shared helper to eliminate duplication across codebase
	 *
	 * @param string          $src        Source URL (may be relative).
	 * @param string          $handle     Asset handle.
	 * @param string          $type       Asset type: 'style' or 'script'.
	 * @param string          $version    Optional version string.
	 * @param WP_Dependencies $deps WordPress dependencies object (wp_styles or wp_scripts).
	 * @return string Normalized absolute URL
	 */
	public static function normalize_asset_url( $src, $handle, $type, $version = '', $deps = null ) {
		// If relative URL, make it absolute.
		if ( ! preg_match( '|^(https?:)?//|', $src ) ) {
			// Check if URL already has content URL prefix - if so, no change needed.
			if ( ! ( $deps && $deps->content_url && str_starts_with( $src, $deps->content_url ) ) ) {
				if ( $deps && $deps->base_url ) {
					// Use base URL.
					$src = $deps->base_url . $src;
				}
			}
		}

		// Add version if available.
		if ( ! empty( $version ) ) {
			$src = add_query_arg( 'ver', $version, $src );
		}

		// Apply filter (same as WordPress does).
		// Using WordPress core filter names (style_loader_src, script_loader_src).
		if ( 'style' === $type ) {
			// Using WordPress core filter, not a plugin-specific hook.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$src = apply_filters( 'style_loader_src', $src, $handle );
		} else {
			// Using WordPress core filter, not a plugin-specific hook.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$src = apply_filters( 'script_loader_src', $src, $handle );
		}

		return esc_url( $src );
	}

	/**
	 * Get style URLs for all registered styles
	 * Used to provide JavaScript with style URLs for dynamic injection
	 *
	 * @return array Associative array of style handle => URL
	 */
	private function get_style_urls() {
		global $wp_styles;

		$style_urls = array();

		if ( ! $wp_styles || ! isset( $wp_styles->registered ) ) {
			return $style_urls;
		}

		// Get all registered styles.
		foreach ( $wp_styles->registered as $handle => $style ) {
			if ( ! empty( $style->src ) ) {
				$style_urls[ $handle ] = self::normalize_asset_url(
					$style->src,
					$handle,
					'style',
					isset( $style->ver ) ? $style->ver : '',
					$wp_styles
				);
			}
		}

		return $style_urls;
	}

	/**
	 * Get script URLs for all registered scripts
	 * Used to provide JavaScript with script URLs for dynamic injection
	 *
	 * @return array Associative array of script handle => URL
	 */
	private function get_script_urls() {
		global $wp_scripts;

		$script_urls = array();

		if ( ! $wp_scripts || ! isset( $wp_scripts->registered ) ) {
			return $script_urls;
		}

		// Get all registered scripts.
		foreach ( $wp_scripts->registered as $handle => $script ) {
			if ( ! empty( $script->src ) ) {
				$script_urls[ $handle ] = self::normalize_asset_url(
					$script->src,
					$handle,
					'script',
					isset( $script->ver ) ? $script->ver : '',
					$wp_scripts
				);
			}
		}

		return $script_urls;
	}
}

