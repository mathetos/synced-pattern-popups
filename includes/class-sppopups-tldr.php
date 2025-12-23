<?php
/**
 * TLDR Service
 * Handles AI-powered TLDR generation and caching
 *
 * @package SPPopups
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPPopups_TLDR {

	/**
	 * Cache service instance
	 *
	 * @var SPPopups_Cache
	 */
	private $cache_service;

	/**
	 * Constructor
	 *
	 * @param SPPopups_Cache $cache_service Cache service instance
	 */
	public function __construct( SPPopups_Cache $cache_service = null ) {
		$this->cache_service = $cache_service ? $cache_service : new SPPopups_Cache();
	}

	/**
	 * Get TLDR for a post
	 * Checks cache first, generates if needed
	 *
	 * @param int $post_id Post ID
	 * @return string|WP_Error TLDR content or error
	 */
	public function get_tldr( $post_id ) {
		// Validate post ID
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post_id', __( 'Invalid post ID.', 'sppopups' ) );
		}

		// Check if TLDR is enabled
		if ( ! SPPopups_Settings::is_tldr_enabled() ) {
			return new WP_Error( 'tldr_disabled', __( 'TLDR feature is disabled.', 'sppopups' ) );
		}

		// Check cache first
		$cache_key = $this->get_cache_key( $post_id );
		$cached = $this->get_cached_tldr( $post_id );
		if ( false !== $cached ) {
			return $cached;
		}

		// Check if AI is available
		if ( ! $this->is_ai_available() ) {
			return new WP_Error( 'ai_unavailable', __( 'AI service is not available. Please check AI Experiments plugin configuration.', 'sppopups' ) );
		}

		// Extract content
		$content = $this->extract_primary_content( $post_id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		if ( empty( $content ) ) {
			return new WP_Error( 'no_content', __( 'No content found to generate TLDR.', 'sppopups' ) );
		}

		// Generate TLDR
		$tldr = $this->generate_tldr( $content );
		if ( is_wp_error( $tldr ) ) {
			return $tldr;
		}

		// Cache the result
		$this->cache_tldr( $post_id, $tldr );

		return $tldr;
	}

	/**
	 * Extract primary content from post
	 *
	 * @param int $post_id Post ID
	 * @return string|WP_Error Extracted content or error
	 */
	public function extract_primary_content( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'sppopups' ) );
		}

		// Check if post is published
		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'post_not_published', __( 'Post is not published.', 'sppopups' ) );
		}

		$content = '';

		// Try to use AI Experiments get_post_context if available
		if ( function_exists( 'WordPress\AI\get_post_context' ) ) {
			try {
				$context = \WordPress\AI\get_post_context( $post_id );
				if ( isset( $context['content'] ) && ! empty( $context['content'] ) ) {
					$content = $context['content'];
				}
			} catch ( Exception $e ) {
				// Fall back to standard extraction
			}
		}

		// Fallback: use post content directly
		if ( empty( $content ) ) {
			// Apply the_content filter to process blocks/shortcodes
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$content = apply_filters( 'the_content', $post->post_content );
		}

		// Normalize content using AI Experiments helper if available
		if ( function_exists( 'WordPress\AI\normalize_content' ) ) {
			$content = \WordPress\AI\normalize_content( $content );
		} else {
			// Basic normalization fallback
			$content = wp_strip_all_tags( $content );
			$content = preg_replace( '/\s+/', ' ', $content );
			$content = trim( $content );
		}

		// Limit content length to avoid token limits (10000 characters)
		if ( strlen( $content ) > 10000 ) {
			$content = substr( $content, 0, 10000 ) . '...';
		}

		return $content;
	}

	/**
	 * Generate TLDR using AI
	 *
	 * @param string $content Content to summarize
	 * @return string|WP_Error Generated TLDR or error
	 */
	public function generate_tldr( $content ) {
		if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			return new WP_Error( 'ai_client_unavailable', __( 'AI Client is not available.', 'sppopups' ) );
		}

		// Get prompt template
		$prompt_template = SPPopups_Settings::get_tldr_prompt();
		
		// Replace {content} placeholder
		$full_prompt = str_replace( '{content}', $content, $prompt_template );

		try {
			// Use AI_Client to generate TLDR
			$result = \WordPress\AI_Client\AI_Client::prompt_with_wp_error( $full_prompt )
				->using_temperature( 0.7 )
				->using_model_preference( ...$this->get_preferred_models() )
				->generate_text();

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// generate_text() returns a string directly
			if ( is_string( $result ) ) {
				return trim( $result );
			}

			return new WP_Error( 'invalid_response', __( 'Invalid response from AI service.', 'sppopups' ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'ai_generation_failed', __( 'Failed to generate TLDR: ', 'sppopups' ) . $e->getMessage() );
		}
	}

	/**
	 * Get preferred AI models
	 *
	 * @return array Preferred models
	 */
	private function get_preferred_models() {
		if ( function_exists( 'WordPress\AI\get_preferred_models' ) ) {
			return \WordPress\AI\get_preferred_models();
		}

		// Default fallback
		return array(
			array( 'anthropic', 'claude-haiku-4-5' ),
			array( 'google', 'gemini-2.5-flash' ),
			array( 'openai', 'gpt-4o-mini' ),
		);
	}

	/**
	 * Get cache key for post
	 *
	 * @param int $post_id Post ID
	 * @return string Cache key
	 */
	private function get_cache_key( $post_id ) {
		return 'sppopups_tldr_' . (int) $post_id;
	}

	/**
	 * Get cached TLDR
	 *
	 * @param int $post_id Post ID
	 * @return string|false Cached TLDR or false
	 */
	public function get_cached_tldr( $post_id ) {
		$cache_key = $this->get_cache_key( $post_id );
		$group = 'sppopups';
		$ttl = SPPopups_Settings::get_tldr_cache_ttl();

		// Try object cache first
		$cached = wp_cache_get( $cache_key, $group );
		if ( false !== $cached ) {
			return $cached;
		}

		// Fallback to transient
		$transient = get_transient( $cache_key );
		if ( false !== $transient ) {
			// Prime object cache
			wp_cache_set( $cache_key, $transient, $group, $ttl );
			return $transient;
		}

		return false;
	}

	/**
	 * Cache TLDR result
	 *
	 * @param int    $post_id Post ID
	 * @param string $tldr    TLDR content
	 * @return bool True on success
	 */
	private function cache_tldr( $post_id, $tldr ) {
		$cache_key = $this->get_cache_key( $post_id );
		$group = 'sppopups';
		$ttl = SPPopups_Settings::get_tldr_cache_ttl();

		// Store in object cache
		wp_cache_set( $cache_key, $tldr, $group, $ttl );

		// Store in transient
		return set_transient( $cache_key, $tldr, $ttl );
	}

	/**
	 * Clear TLDR cache for a post
	 *
	 * @param int $post_id Post ID
	 * @return bool True on success
	 */
	public function clear_tldr_cache( $post_id ) {
		$cache_key = $this->get_cache_key( $post_id );
		$group = 'sppopups';

		// Delete from object cache
		wp_cache_delete( $cache_key, $group );

		// Delete from transient
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all TLDR caches
	 *
	 * @return int Number of items deleted
	 */
	public function clear_all_tldr_cache() {
		global $wpdb;

		$pattern = '_transient_sppopups_tldr_%';
		$group = 'sppopups';

		// Clear object cache group
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( $group );
		}

		// Clear transients
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$pattern,
				'_transient_timeout_' . substr( $pattern, 12 )
			)
		);

		return $deleted;
	}

	/**
	 * Check if AI is available
	 *
	 * @return bool True if available
	 */
	private function is_ai_available() {
		if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			return false;
		}

		if ( ! function_exists( 'WordPress\AI\has_valid_ai_credentials' ) ) {
			return false;
		}

		return \WordPress\AI\has_valid_ai_credentials();
	}
}

