<?php
/**
 * Main Plugin Class
 * Handles front-end enqueuing and modal output
 *
 * @package Simplest_Popup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simplest_Popup_Plugin {

	/**
	 * AJAX handler instance
	 *
	 * @var Simplest_Popup_Ajax
	 */
	private $ajax_handler;

	/**
	 * Cache service instance
	 *
	 * @var Simplest_Popup_Cache
	 */
	private $cache_service;

	/**
	 * Admin interface instance
	 *
	 * @var Simplest_Popup_Admin
	 */
	private $admin;

	/**
	 * Pattern service instance (shared)
	 *
	 * @var Simplest_Popup_Pattern
	 */
	private $pattern_service;

	/**
	 * Style collector instance (shared)
	 *
	 * @var Simplest_Popup_Style_Collector
	 */
	private $style_collector;

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Create shared service instances
		$this->pattern_service = new Simplest_Popup_Pattern();
		$this->cache_service = new Simplest_Popup_Cache();
		$this->style_collector = new Simplest_Popup_Style_Collector();

		// Initialize AJAX handler with shared services
		$this->ajax_handler = new Simplest_Popup_Ajax( $this->pattern_service, $this->cache_service, $this->style_collector );
		$this->ajax_handler->init();

		// Initialize admin interface with shared services
		if ( is_admin() ) {
			$this->admin = new Simplest_Popup_Admin( $this->pattern_service, $this->cache_service );
			$this->admin->init();
		}

		// Hook into front-end
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		// Always output modal HTML (lightweight, needed for JavaScript to work)
		add_action( 'wp_footer', array( $this, 'output_modal' ) );

		// Invalidate cache when synced patterns are updated
		add_action( 'save_post_wp_block', array( $this, 'invalidate_cache' ), 10, 1 );

		// Register post meta for popup support toggle
		add_action( 'init', array( $this, 'register_post_meta' ) );
	}

	/**
	 * Check if page contains popup triggers
	 *
	 * @return bool True if triggers found, false otherwise
	 */
	private function has_popup_triggers() {
		// Check if we're in admin (always load in admin for preview purposes)
		if ( is_admin() ) {
			return true;
		}

		// Check if post has forced popup support enabled
		if ( is_singular() ) {
			global $post;
			if ( $post && get_post_meta( $post->ID, '_simplest_popup_support', true ) === 'forced' ) {
				return true;
			}
		}

		// Allow filter to force loading
		if ( apply_filters( 'simplest_popup_force_load_assets', false ) ) {
			return true;
		}

		// Get page content to scan for triggers
		global $post;

		// Check current post content
		if ( $post && isset( $post->post_content ) ) {
			if ( $this->content_has_triggers( $post->post_content ) ) {
				return true;
			}
		}

		// Check menu items for href triggers (lightweight - only check URLs)
		$locations = get_nav_menu_locations();
		if ( ! empty( $locations ) ) {
			foreach ( $locations as $location => $menu_id ) {
				if ( $menu_id ) {
					$menu_items = wp_get_nav_menu_items( $menu_id );
					if ( $menu_items ) {
						foreach ( $menu_items as $item ) {
							if ( isset( $item->url ) && preg_match( '/#wppt-popup-\d+(?:-\d+)?/', $item->url ) ) {
								return true;
							}
						}
					}
				}
			}
		}

		// Check widget output if widgets are active (cache result to avoid repeated checks)
		static $widget_check_done = false;
		static $widget_has_triggers = false;
		
		if ( ! $widget_check_done ) {
			$widget_check_done = true;
			// Only check if there are active widgets
			if ( is_active_widget( false, false, 'text' ) || is_active_widget( false, false, 'html' ) || is_active_widget( false, false, 'custom_html' ) ) {
				// Widgets might contain triggers, use filter to allow theme/plugin to indicate
				$widget_has_triggers = apply_filters( 'simplest_popup_widgets_have_triggers', false );
			}
		}

		if ( $widget_has_triggers ) {
			return true;
		}

		// Allow filter to override detection
		return apply_filters( 'simplest_popup_has_triggers', false );
	}

	/**
	 * Check if content string contains popup triggers
	 *
	 * @param string $content Content to check
	 * @return bool True if triggers found
	 */
	private function content_has_triggers( $content ) {
		if ( empty( $content ) ) {
			return false;
		}

		// Check for class-based triggers: wppt-popup-{id} or wppt-popup-{id}-{width}
		if ( preg_match( '/\bwppt-popup-\d+(?:-\d+)?\b/', $content ) ) {
			return true;
		}

		// Check for href-based triggers: #wppt-popup-{id} or #wppt-popup-{id}-{width}
		if ( preg_match( '/#wppt-popup-\d+(?:-\d+)?/', $content ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue CSS and JavaScript assets
	 */
	public function enqueue_assets() {
		// Only enqueue if page contains popup triggers
		if ( ! $this->has_popup_triggers() ) {
			return;
		}

		// Enqueue CSS
		wp_enqueue_style(
			'simplest-popup-modal',
			SIMPLEST_POPUP_PLUGIN_URL . 'assets/css/modal.css',
			array(),
			SIMPLEST_POPUP_VERSION
		);

		// Enqueue JavaScript
		wp_enqueue_script(
			'simplest-popup-modal',
			SIMPLEST_POPUP_PLUGIN_URL . 'assets/js/modal.js',
			array(),
			SIMPLEST_POPUP_VERSION,
			true
		);

		// Get style URLs for JavaScript injection
		$style_urls = $this->get_style_urls();

		// Get script URLs for JavaScript injection
		$script_urls = $this->get_script_urls();

		// Localize script with AJAX data
		wp_localize_script(
			'simplest-popup-modal',
			'simplestPopup',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'simplest_popup_ajax' ),
				'styleUrls' => $style_urls,
				'scriptUrls' => $script_urls,
				'strings'   => array(
					'loading'  => __( 'Loading content...', 'simplest-popup' ),
					'error'    => __( 'Error loading content. Please try again.', 'simplest-popup' ),
					'notFound' => __( 'Content not found.', 'simplest-popup' ),
				),
			)
		);
	}

	/**
	 * Output modal HTML structure
	 */
	public function output_modal() {
		?>
		<!-- Simplest Popup Modal -->
		<div id="simplest-popup-modal" class="simplest-popup-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="simplest-popup-title" aria-describedby="simplest-popup-desc" tabindex="-1" style="display: none;">
			<div class="simplest-popup-overlay"></div>
			<div class="simplest-popup-container">
				<div class="simplest-popup-card">
					<h2 id="simplest-popup-title" class="simplest-popup-sr-only"></h2>
					<p id="simplest-popup-desc" class="simplest-popup-sr-only"><?php esc_html_e( 'Press Escape to close. Tab stays within the popup.', 'simplest-popup' ); ?></p>
					<button class="simplest-popup-close" aria-label="<?php esc_attr_e( 'Close modal', 'simplest-popup' ); ?>" type="button">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						</svg>
					</button>
					<div class="simplest-popup-content">
						<div class="simplest-popup-loading">
							<div class="simplest-popup-spinner"></div>
							<p><?php esc_html_e( 'Loading content...', 'simplest-popup' ); ?></p>
						</div>
					</div>
					<div class="simplest-popup-footer">
						<button class="simplest-popup-close-footer" type="button" aria-label="<?php esc_attr_e( 'Close modal', 'simplest-popup' ); ?>">
							<?php esc_html_e( 'Close', 'simplest-popup' ); ?> →
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
	 * @param int $post_id Post ID
	 */
	public function invalidate_cache( $post_id ) {
		if ( get_post_type( $post_id ) === 'wp_block' ) {
			// Only invalidate cache for synced patterns
			if ( $this->pattern_service->is_synced_pattern( $post_id ) ) {
				// Clear rendered HTML cache
				$this->cache_service->delete( $post_id );
				
				// Clear pattern object cache
				$pattern_cache_key = 'simplest_popup_pattern_' . $post_id;
				wp_cache_delete( $pattern_cache_key, 'simplest_popup_patterns' );
			}
		}
	}

	/**
	 * Register post meta for popup support toggle
	 */
	public function register_post_meta() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		
		foreach ( $post_types as $post_type ) {
			// Skip attachment post type
			if ( 'attachment' === $post_type ) {
				continue;
			}

			register_post_meta(
				$post_type,
				'_simplest_popup_support',
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => array( $this, 'sanitize_popup_support' ),
					'show_in_rest'      => true,
					'auth_callback'     => function() {
						return current_user_can( 'edit_posts' );
					},
					'default'           => 'default',
				)
			);
		}
	}

	/**
	 * Sanitize popup support meta value
	 *
	 * @param string $value Meta value
	 * @return string Sanitized value
	 */
	public function sanitize_popup_support( $value ) {
		$allowed = array( 'default', 'forced' );
		return in_array( $value, $allowed, true ) ? $value : 'default';
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

		// Get all registered styles
		foreach ( $wp_styles->registered as $handle => $style ) {
			if ( ! empty( $style->src ) ) {
				// Get the full URL
				$src = $style->src;

				// If relative URL, make it absolute
				if ( ! preg_match( '|^(https?:)?//|', $src ) ) {
					// Check if it's relative to content directory
					if ( $wp_styles->content_url && str_starts_with( $src, $wp_styles->content_url ) ) {
						// Already has content URL
					} else {
						// Use base URL
						$src = $wp_styles->base_url . $src;
					}
				}

				// Add version if available
				if ( ! empty( $style->ver ) ) {
					$src = add_query_arg( 'ver', $style->ver, $src );
				}

				// Apply filter (same as WordPress does)
				$src = apply_filters( 'style_loader_src', $src, $handle );

				$style_urls[ $handle ] = esc_url( $src );
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

		// Get all registered scripts
		foreach ( $wp_scripts->registered as $handle => $script ) {
			if ( ! empty( $script->src ) ) {
				// Get the full URL
				$src = $script->src;

				// If relative URL, make it absolute
				if ( ! preg_match( '|^(https?:)?//|', $src ) ) {
					// Check if it's relative to content directory
					if ( $wp_scripts->content_url && str_starts_with( $src, $wp_scripts->content_url ) ) {
						// Already has content URL
					} else {
						// Use base URL
						$src = $wp_scripts->base_url . $src;
					}
				}

				// Add version if available
				if ( ! empty( $script->ver ) ) {
					$src = add_query_arg( 'ver', $script->ver, $src );
				}

				// Apply filter (same as WordPress does)
				$src = apply_filters( 'script_loader_src', $src, $handle );

				$script_urls[ $handle ] = esc_url( $src );
			}
		}

		return $script_urls;
	}
}

