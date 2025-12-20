<?php
/**
 * Cache Service
 * Handles transient caching for rendered synced pattern content
 *
 * @package Simplest_Popup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simplest_Popup_Cache {

	/**
	 * Cached TTL value (request-scoped)
	 *
	 * @var int|null
	 */
	private static $cached_ttl = null;

	/**
	 * Get cache TTL (time to live)
	 * Can be filtered via 'simplest_popup_cache_ttl'
	 * Result is cached per request to avoid repeated filter execution
	 *
	 * @return int Cache TTL in seconds
	 */
	private function get_cache_ttl() {
		// Return cached value if available
		if ( null !== self::$cached_ttl ) {
			return self::$cached_ttl;
		}

		// Call filter once and cache the result
		self::$cached_ttl = (int) apply_filters( 'simplest_popup_cache_ttl', SIMPLEST_POPUP_CACHE_TTL );

		return self::$cached_ttl;
	}

	/**
	 * Get cache key for a pattern ID
	 *
	 * @param int $pattern_id Synced pattern ID
	 * @return string Cache key
	 */
	private function get_cache_key( $pattern_id ) {
		return 'simplest_popup_block_' . (int) $pattern_id;
	}

	/**
	 * Get cache group name
	 *
	 * @return string Cache group
	 */
	private function get_cache_group() {
		return 'simplest_popup';
	}

	/**
	 * Get cached rendered content for a pattern
	 * Uses object cache if available, falls back to transients
	 *
	 * @param int $pattern_id Synced pattern ID
	 * @return string|array|false Cached HTML (string), array with HTML and styles, or false if not cached
	 */
	public function get( $pattern_id ) {
		$cache_key = $this->get_cache_key( $pattern_id );
		$group = $this->get_cache_group();

		// Try object cache first (faster if available)
		$cached = wp_cache_get( $cache_key, $group );
		if ( false !== $cached ) {
			return $this->normalize_cached_data( $cached );
		}

		// Fallback to transient
		$transient = get_transient( $cache_key );
		if ( false !== $transient ) {
			// Prime object cache for next time
			$normalized = $this->normalize_cached_data( $transient );
			wp_cache_set( $cache_key, $transient, $group, $this->get_cache_ttl() );
			return $normalized;
		}

		return false;
	}

	/**
	 * Normalize cached data to handle both old (string) and new (array) formats
	 *
	 * @param mixed $data Cached data (string or array)
	 * @return string|array Normalized data
	 */
	private function normalize_cached_data( $data ) {
		// If it's already an array, return as-is
		if ( is_array( $data ) ) {
			return $data;
		}

		// If it's a JSON string, decode it
		if ( is_string( $data ) ) {
			$decoded = json_decode( $data, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
			// Backward compatibility: return string as HTML-only
			return $data;
		}

		// Fallback: return as-is
		return $data;
	}

	/**
	 * Set cached rendered content for a pattern
	 * Uses object cache if available, falls back to transients
	 *
	 * @param int         $pattern_id Synced pattern ID
	 * @param string|array $data      Rendered HTML (string) or array with HTML and styles
	 * @return bool True on success, false on failure
	 */
	public function set( $pattern_id, $data ) {
		$cache_key = $this->get_cache_key( $pattern_id );
		$group = $this->get_cache_group();
		$ttl = $this->get_cache_ttl();

		// Normalize data for storage
		$storage_data = $this->prepare_data_for_storage( $data );

		// Store in object cache (if available)
		wp_cache_set( $cache_key, $storage_data, $group, $ttl );

		// Also store in transient as fallback
		return set_transient( $cache_key, $storage_data, $ttl );
	}

	/**
	 * Prepare data for storage (encode arrays as JSON for transients)
	 *
	 * @param string|array $data Data to prepare
	 * @return string|array Prepared data
	 */
	private function prepare_data_for_storage( $data ) {
		// If it's an array, JSON encode it for storage
		if ( is_array( $data ) ) {
			return wp_json_encode( $data );
		}

		// String data can be stored as-is
		return $data;
	}

	/**
	 * Delete cached content for a pattern
	 * Clears both object cache and transient
	 *
	 * @param int $pattern_id Synced pattern ID
	 * @return bool True on success, false on failure
	 */
	public function delete( $pattern_id ) {
		$cache_key = $this->get_cache_key( $pattern_id );
		$group = $this->get_cache_group();

		// Delete from object cache
		wp_cache_delete( $cache_key, $group );

		// Delete from transient
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all cached popup content
	 * Useful for debugging or bulk operations
	 * Clears both object cache and transients
	 *
	 * @return int Number of items deleted
	 */
	public function clear_all() {
		global $wpdb;

		$pattern = '_transient_simplest_popup_block_%';
		$group = $this->get_cache_group();
		
		// Clear object cache group (if function exists - WordPress 6.1+)
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( $group );
		}

		// Clear transients
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$pattern,
				'_transient_timeout_' . substr( $pattern, 12 )
			)
		);

		return $deleted;
	}
}

