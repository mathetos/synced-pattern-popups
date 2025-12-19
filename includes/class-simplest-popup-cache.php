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
	 * @return string|false Cached HTML or false if not cached
	 */
	public function get( $pattern_id ) {
		$cache_key = $this->get_cache_key( $pattern_id );
		$group = $this->get_cache_group();

		// Try object cache first (faster if available)
		$cached = wp_cache_get( $cache_key, $group );
		if ( false !== $cached ) {
			return $cached;
		}

		// Fallback to transient
		$transient = get_transient( $cache_key );
		if ( false !== $transient ) {
			// Prime object cache for next time
			wp_cache_set( $cache_key, $transient, $group, $this->get_cache_ttl() );
			return $transient;
		}

		return false;
	}

	/**
	 * Set cached rendered content for a pattern
	 * Uses object cache if available, falls back to transients
	 *
	 * @param int    $pattern_id Synced pattern ID
	 * @param string $html       Rendered HTML to cache
	 * @return bool True on success, false on failure
	 */
	public function set( $pattern_id, $html ) {
		$cache_key = $this->get_cache_key( $pattern_id );
		$group = $this->get_cache_group();
		$ttl = $this->get_cache_ttl();

		// Store in object cache (if available)
		wp_cache_set( $cache_key, $html, $group, $ttl );

		// Also store in transient as fallback
		return set_transient( $cache_key, $html, $ttl );
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

