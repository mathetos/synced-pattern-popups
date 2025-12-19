<?php
/**
 * Synced Pattern Service
 * Handles retrieval of WordPress Synced Pattern content
 *
 * @package Simplest_Popup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simplest_Popup_Pattern {

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
		return 'simplest_popup_pattern_' . (int) $pattern_id;
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
			$pattern = wp_cache_get( $cache_key, 'simplest_popup_patterns' );
			
			if ( false === $pattern ) {
				$pattern = get_post( $pattern_id );
				// Cache for 5 minutes (short cache for post objects)
				if ( $pattern ) {
					wp_cache_set( $cache_key, $pattern, 'simplest_popup_patterns', 300 );
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
	 * @param int $pattern_id Synced pattern post ID
	 * @return string|false Rendered HTML or false if not found or not synced
	 */
	public function get_rendered_content( $pattern_id ) {
		$content = $this->get_content( $pattern_id );

		if ( ! $content ) {
			return false;
		}

		return do_blocks( $content );
	}
}

