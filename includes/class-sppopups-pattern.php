<?php
/**
 * Synced Pattern Service
 * Handles retrieval of WordPress Synced Pattern content
 *
 * @package SPPopups
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPPopups_Pattern {

	/**
	 * Internal cache for sync status checks (request-scoped)
	 *
	 * @var array
	 */
	private static $sync_status_cache = array();

	/**
	 * Check if a pattern is a synced pattern
	 * Synced patterns have wp_pattern_sync_status meta that is NOT 'unsynced' (or doesn't exist)
	 *
	 * @param int $pattern_id Pattern post ID
	 * @param string|null $sync_status Optional pre-fetched sync status to avoid duplicate query
	 * @return bool True if synced pattern, false otherwise
	 */
	public function is_synced_pattern( $pattern_id, $sync_status = null ) {
		if ( ! is_numeric( $pattern_id ) || $pattern_id <= 0 ) {
			return false;
		}

		$pattern_id = (int) $pattern_id;

		// Check internal cache first
		if ( isset( self::$sync_status_cache[ $pattern_id ] ) ) {
			return self::$sync_status_cache[ $pattern_id ];
		}

		// Use provided sync status or fetch it
		if ( null === $sync_status ) {
			$sync_status = get_post_meta( $pattern_id, 'wp_pattern_sync_status', true );
		}

		// If meta doesn't exist or is empty, it's synced (default)
		// If meta is 'unsynced', it's not synced
		$is_synced = 'unsynced' !== $sync_status;

		// Cache the result
		self::$sync_status_cache[ $pattern_id ] = $is_synced;

		return $is_synced;
	}

	/**
	 * Get cache key for pattern post object
	 *
	 * @param int $pattern_id Pattern post ID
	 * @return string Cache key
	 */
	private function get_pattern_cache_key( $pattern_id ) {
		return 'sppopups_pattern_' . (int) $pattern_id;
	}

	/**
	 * Get synced pattern content by ID
	 * Only accepts numeric IDs for security
	 * Only returns content for synced patterns
	 *
	 * @param int $pattern_id Synced pattern post ID
	 * @param WP_Post|null $pattern Optional pre-fetched post object to avoid duplicate query
	 * @return string|false Pattern content or false if not found or not synced
	 */
	public function get_content( $pattern_id, $pattern = null ) {
		if ( ! is_numeric( $pattern_id ) || $pattern_id <= 0 ) {
			return false;
		}

		$pattern_id = (int) $pattern_id;

		// Use provided post object or fetch it (with object cache support)
		if ( null === $pattern ) {
			$cache_key = $this->get_pattern_cache_key( $pattern_id );
			$pattern = wp_cache_get( $cache_key, 'sppopups_patterns' );
			
			if ( false === $pattern ) {
				$pattern = get_post( $pattern_id );
				// Cache for 5 minutes (short cache for post objects)
				if ( $pattern ) {
					wp_cache_set( $cache_key, $pattern, 'sppopups_patterns', 300 );
				}
			}
		}

		// Verify it's actually a pattern (wp_block post type)
		if ( ! $pattern || $pattern->post_type !== 'wp_block' ) {
			return false;
		}

		// Check if pattern is published
		if ( $pattern->post_status !== 'publish' ) {
			return false;
		}

		// Check if pattern is password-protected (should not be accessible via popup)
		if ( ! empty( $pattern->post_password ) ) {
			return false;
		}

		// Verify pattern visibility - allow filter for plugins to restrict access
		$can_access = apply_filters( 'sppopups_can_access_pattern', true, $pattern_id, $pattern );
		if ( ! $can_access ) {
			return false;
		}

		// Get sync status once and pass to is_synced_pattern to avoid duplicate query
		$sync_status = get_post_meta( $pattern_id, 'wp_pattern_sync_status', true );

		// Verify it's a synced pattern (pass sync_status to avoid duplicate query)
		if ( ! $this->is_synced_pattern( $pattern_id, $sync_status ) ) {
			return false;
		}

		if ( isset( $pattern->post_content ) ) {
			return $pattern->post_content;
		}

		return false;
	}

	/**
	 * Get rendered synced pattern HTML
	 *
	 * @param int                                    $pattern_id Synced pattern post ID
	 * @param SPPopups_Asset_Collector|null $style_collector Optional asset collector instance
	 * @return string|array|false Rendered HTML (string), array with HTML and styles, or false if not found
	 */
	public function get_rendered_content( $pattern_id, $style_collector = null ) {
		$content = $this->get_content( $pattern_id );

		if ( ! $content ) {
			return false;
		}

		// Start style collection if collector provided
		if ( $style_collector instanceof SPPopups_Asset_Collector ) {
			$style_collector->start_collection( $pattern_id );
		}

		// Render blocks using custom content filter to avoid conflicts with other plugins
		// This filter duplicates 'the_content' functionality but only includes core WordPress functions
		// and block rendering, avoiding unwanted side effects from plugins that hook into 'the_content'
		// The filter includes do_blocks at priority 9, which properly renders blocks and triggers
		// all necessary hooks for asset enqueuing (including third-party blocks like Kadence)
		$html = apply_filters( 'sppopups_the_content', $content );

		// Get block support CSS from Style Engine store
		$block_supports_css = '';
		if ( function_exists( 'wp_style_engine_get_stylesheet_from_context' ) ) {
			$block_supports_css = wp_style_engine_get_stylesheet_from_context( 'block-supports' );
		}

		// Get inline CSS from block-style-variation-styles (WordPress 6.6+)
		// This contains CSS for block style variations like is-style-section-3
		$block_style_variation_css = '';
		global $wp_styles;
		if ( $wp_styles && isset( $wp_styles->registered['block-style-variation-styles'] ) ) {
			$style_obj = $wp_styles->registered['block-style-variation-styles'];
			if ( isset( $style_obj->extra['after'] ) && is_array( $style_obj->extra['after'] ) ) {
				$block_style_variation_css = implode( "\n", $style_obj->extra['after'] );
			}
		}

		// Get global stylesheet for CSS variables (preset colors, spacing, etc.)
		$global_stylesheet = '';
		if ( function_exists( 'wp_get_global_stylesheet' ) ) {
			$global_stylesheet = wp_get_global_stylesheet();
		}

		// Finish style collection and get styles
		if ( $style_collector instanceof SPPopups_Asset_Collector ) {
			// Get asset data BEFORE finish_collection() resets the collected arrays
			$asset_data = $style_collector->get_asset_data();
			// Now finish collection (this resets the arrays)
			$styles = $style_collector->finish_collection();
			// Return both HTML, styles, block support CSS, block style variation CSS, global stylesheet, and asset data
			return array(
				'html'                      => $html,
				'styles'                    => $styles,
				'block_supports_css'        => $block_supports_css,
				'block_style_variation_css' => $block_style_variation_css,
				'global_stylesheet'         => $global_stylesheet,
				'asset_data'                => $asset_data,
			);
		}

		// If no collector, still return block support CSS, block style variation CSS, and global stylesheet for backward compatibility
		if ( ! empty( $block_supports_css ) || ! empty( $block_style_variation_css ) || ! empty( $global_stylesheet ) ) {
			return array(
				'html'                      => $html,
				'styles'                    => array(),
				'block_supports_css'        => $block_supports_css,
				'block_style_variation_css' => $block_style_variation_css,
				'global_stylesheet'         => $global_stylesheet,
				'asset_data'                => SPPopups_Cache::get_default_asset_data(),
			);
		}

		// Backward compatibility: return HTML string if no collector
		return $html;
	}

	/**
	 * Get all synced patterns
	 * Shared query method for admin and abilities
	 *
	 * @param string $status Post status filter (default: 'any')
	 * @return array Array of pattern post objects
	 */
	public function get_synced_patterns( $status = 'any' ) {
		$args = array(
			'post_type'      => 'wp_block',
			'post_status'    => $status,
			'posts_per_page' => -1,
			// meta_query is necessary here to filter synced vs unsynced patterns
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'wp_pattern_sync_status',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'wp_pattern_sync_status',
					'value'   => 'unsynced',
					'compare' => '!=',
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$query = new WP_Query( $args );
		$posts = $query->posts;

		// Prime meta cache for all patterns to eliminate N+1 queries
		if ( ! empty( $posts ) && is_array( $posts ) ) {
			$post_ids = wp_list_pluck( $posts, 'ID' );
			if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
				// Correct WP core meta-cache priming (update_post_meta_cache() is not a core function)
				if ( function_exists( 'update_postmeta_cache' ) ) {
					update_postmeta_cache( $post_ids );
				} else {
					update_meta_cache( 'post', $post_ids );
				}
			}
		}

		return is_array( $posts ) ? $posts : array();
	}
}

