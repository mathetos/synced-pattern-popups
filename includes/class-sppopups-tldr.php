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
			return new WP_Error( 'invalid_post_id', __( 'Invalid post ID.', 'synced-pattern-popups' ) );
		}

		// Check if TLDR is enabled
		if ( ! SPPopups_Settings::is_tldr_enabled() ) {
			return new WP_Error( 'tldr_disabled', __( 'TLDR feature is disabled.', 'synced-pattern-popups' ) );
		}

		// Check cache first
		$cache_key = $this->get_cache_key( $post_id );
		$cached = $this->get_cached_tldr( $post_id );
		if ( false !== $cached ) {
			return $cached;
		}

		// Check if AI is available
		if ( ! $this->is_ai_available() ) {
			return new WP_Error( 'ai_unavailable', __( 'AI service is not available. Please check AI Experiments plugin configuration.', 'synced-pattern-popups' ) );
		}

		// Extract content
		$content = $this->extract_primary_content( $post_id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		if ( empty( $content ) ) {
			return new WP_Error( 'no_content', __( 'No content found to generate TLDR.', 'synced-pattern-popups' ) );
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
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'synced-pattern-popups' ) );
		}

		// Check if post is published
		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'post_not_published', __( 'Post is not published.', 'synced-pattern-popups' ) );
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
			return new WP_Error( 'ai_client_unavailable', __( 'AI Client is not available.', 'synced-pattern-popups' ) );
		}

		// Get prompt template
		$prompt_template = SPPopups_Settings::get_tldr_prompt();
		
		// Always append the content to the prompt, regardless of user's custom prompt
		$full_prompt = trim( $prompt_template ) . "\n\n" . $content;

		// System instruction to always enforce markdown formatting
		$system_instruction = __( 'Format your response using Markdown syntax (use **bold** for emphasis, * for lists, ## for headings, etc.).', 'synced-pattern-popups' );

		try {
			// Use AI_Client to generate TLDR
			$result = \WordPress\AI_Client\AI_Client::prompt_with_wp_error( $full_prompt )
				->using_system_instruction( $system_instruction )
				->using_temperature( 0.7 )
				->using_model_preference( ...$this->get_preferred_models() )
				->generate_text();

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// generate_text() returns a string directly
			if ( is_string( $result ) ) {
				$markdown = trim( $result );
				// Convert markdown to HTML
				$html = $this->markdown_to_html( $markdown );
				return $html;
			}

			return new WP_Error( 'invalid_response', __( 'Invalid response from AI service.', 'synced-pattern-popups' ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'ai_generation_failed', __( 'Failed to generate TLDR: ', 'synced-pattern-popups' ) . $e->getMessage() );
		}
	}

	/**
	 * Convert markdown to HTML
	 * Handles common markdown syntax: headers, bold, italic, lists, paragraphs
	 *
	 * @param string $markdown Markdown text
	 * @return string HTML (sanitized)
	 */
	private function markdown_to_html( $markdown ) {
		if ( empty( $markdown ) ) {
			return '';
		}

		$html = $markdown;

		// Split into lines for processing
		$lines = explode( "\n", $html );
		$processed_lines = array();
		$in_list = false;
		$list_type = null; // 'ul' or 'ol'
		$list_items = array();

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			
			// Check for headers
			if ( preg_match( '/^###\s+(.+)$/', $trimmed, $matches ) ) {
				$this->flush_list( $processed_lines, $in_list, $list_type, $list_items );
				$processed_lines[] = '<h3>' . $this->process_inline_markdown( $matches[1] ) . '</h3>';
				continue;
			} elseif ( preg_match( '/^##\s+(.+)$/', $trimmed, $matches ) ) {
				$this->flush_list( $processed_lines, $in_list, $list_type, $list_items );
				$processed_lines[] = '<h2>' . $this->process_inline_markdown( $matches[1] ) . '</h2>';
				continue;
			} elseif ( preg_match( '/^#\s+(.+)$/', $trimmed, $matches ) ) {
				$this->flush_list( $processed_lines, $in_list, $list_type, $list_items );
				$processed_lines[] = '<h1>' . $this->process_inline_markdown( $matches[1] ) . '</h1>';
				continue;
			}
			
			// Check for unordered list
			if ( preg_match( '/^[\-\*]\s+(.+)$/', $trimmed, $matches ) ) {
				if ( ! $in_list || $list_type !== 'ul' ) {
					$this->flush_list( $processed_lines, $in_list, $list_type, $list_items );
					$in_list = true;
					$list_type = 'ul';
				}
				$list_items[] = '<li>' . $this->process_inline_markdown( $matches[1] ) . '</li>';
				continue;
			}
			
			// Check for ordered list
			if ( preg_match( '/^\d+\.\s+(.+)$/', $trimmed, $matches ) ) {
				if ( ! $in_list || $list_type !== 'ol' ) {
					$this->flush_list( $processed_lines, $in_list, $list_type, $list_items );
					$in_list = true;
					$list_type = 'ol';
				}
				$list_items[] = '<li>' . $this->process_inline_markdown( $matches[1] ) . '</li>';
				continue;
			}
			
			// Empty line - flush list if active
			if ( empty( $trimmed ) ) {
				$this->flush_list( $processed_lines, $in_list, $list_type, $list_items );
				$processed_lines[] = '';
				continue;
			}
			
			// Regular paragraph line
			$this->flush_list( $processed_lines, $in_list, $list_type, $list_items );
			$processed_lines[] = $this->process_inline_markdown( $trimmed );
		}
		
		// Flush any remaining list
		$this->flush_list( $processed_lines, $in_list, $list_type, $list_items );
		
		// Join lines and wrap paragraphs
		$html = implode( "\n", $processed_lines );
		
		// Wrap consecutive non-empty, non-tag lines in paragraphs
		// Split by double newlines first
		$blocks = preg_split( '/\n\s*\n/', $html );
		$wrapped_blocks = array();
		foreach ( $blocks as $block ) {
			$block = trim( $block );
			if ( empty( $block ) ) {
				continue;
			}
			// Don't wrap if it's already a tag (heading, list, etc.)
			if ( preg_match( '/^<(ul|ol|h[1-6]|li)/', $block ) ) {
				$wrapped_blocks[] = $block;
			} else {
				// Convert single newlines to <br> and wrap in <p>
				$block = nl2br( $block, false );
				$wrapped_blocks[] = '<p>' . $block . '</p>';
			}
		}
		$html = implode( "\n\n", $wrapped_blocks );
		
		// Clean up any empty tags
		$html = preg_replace( '/<p>\s*<\/p>/', '', $html );

		// Sanitize HTML to ensure security
		$html = wp_kses_post( $html );

		return trim( $html );
	}

	/**
	 * Process inline markdown (bold, italic) within a line
	 *
	 * @param string $text Text to process
	 * @return string Processed text
	 */
	private function process_inline_markdown( $text ) {
		// Convert bold (**text** or __text__)
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/__(.+?)__/', '<strong>$1</strong>', $text );
		
		// Convert italic (*text* or _text_) - but avoid conflicts with bold
		// Match single * or _ that's not part of ** or __
		$text = preg_replace( '/(?<!\*)\*(?!\*)([^*]+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text );
		$text = preg_replace( '/(?<!_)_(?!_)([^_]+?)(?<!_)_(?!_)/', '<em>$1</em>', $text );
		
		return $text;
	}

	/**
	 * Flush accumulated list items to processed lines
	 *
	 * @param array  $processed_lines Reference to processed lines array
	 * @param bool   $in_list Reference to in_list flag
	 * @param string $list_type Reference to list type
	 * @param array  $list_items Reference to list items array
	 */
	private function flush_list( &$processed_lines, &$in_list, &$list_type, &$list_items ) {
		if ( $in_list && ! empty( $list_items ) ) {
			$tag = ( $list_type === 'ol' ) ? 'ol' : 'ul';
			$processed_lines[] = '<' . $tag . '>' . "\n" . implode( "\n", $list_items ) . "\n" . '</' . $tag . '>';
			$list_items = array();
			$in_list = false;
			$list_type = null;
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

