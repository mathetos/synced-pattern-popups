<?php
/**
 * Shipped Patterns Manager
 * Handles creation and maintenance of synced patterns shipped with the plugin
 *
 * @package SPPopups
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPPopups_Shipped_Patterns
 */
class SPPopups_Shipped_Patterns {

	/**
	 * Option name for tracking shipped patterns version
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'sppopups_shipped_patterns_version';

	/**
	 * Meta key for identifying shipped patterns
	 *
	 * @var string
	 */
	const SHIPPED_PATTERN_META_KEY = '_sppopups_shipped_pattern_key';

	/**
	 * Pattern category slug
	 *
	 * @var string
	 */
	const CATEGORY_SLUG = 'synced-pattern-popups';

	/**
	 * Pattern category name
	 *
	 * @var string
	 */
	const CATEGORY_NAME = 'Synced Pattern Popups';

	/**
	 * Get all shipped pattern definitions from files
	 *
	 * @return array Array of pattern definitions keyed by pattern key
	 */
	private function get_pattern_definitions() {
		$patterns_dir = SPPOPUPS_PLUGIN_DIR . 'assets/patterns/';

		if ( ! is_dir( $patterns_dir ) ) {
			return array();
		}

		// Initialize WP_Filesystem for reading local files.
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$definitions = array();
		$files       = glob( $patterns_dir . 'pattern-*.html' );

		if ( ! $files ) {
			return array();
		}

		foreach ( $files as $file ) {
			// Extract pattern key from filename: pattern-more-details.html -> more-details.
			$basename    = basename( $file, '.html' );
			$pattern_key = str_replace( 'pattern-', '', $basename );

			if ( empty( $pattern_key ) ) {
				continue;
			}

			// Read file content using WP_Filesystem API.
			$content = $wp_filesystem->get_contents( $file );
			if ( false === $content ) {
				continue;
			}

			// Parse metadata from comment header.
			$metadata      = $this->parse_pattern_metadata( $content );
			$block_content = $this->parse_pattern_content( $content );

			// Skip if no content.
			if ( empty( $block_content ) ) {
				continue;
			}

			// Derive title from metadata or pattern key.
			$title = ! empty( $metadata['title'] )
				? $metadata['title']
				: ucwords( str_replace( '-', ' ', $pattern_key ) );

			$definitions[ $pattern_key ] = array(
				'title'       => $title,
				'description' => isset( $metadata['description'] ) ? $metadata['description'] : '',
				'content'     => $block_content,
			);
		}

		return $definitions;
	}

	/**
	 * Parse pattern metadata from comment header
	 *
	 * @param string $content File content.
	 * @return array Associative array with 'title' and 'description' keys
	 */
	private function parse_pattern_metadata( $content ) {
		$metadata = array();

		// Try to match multiline comment block first (more specific).
		// Matches: <!--\nTitle: ...\nDescription: ...\n-->.
		if ( preg_match( '/<!--\s*\n\s*Title:\s*(.+?)\n\s*Description:\s*(.+?)\n\s*-->/is', $content, $matches ) ) {
			$metadata['title']       = trim( $matches[1] );
			$metadata['description'] = trim( $matches[2] );
			return $metadata;
		}

		// Try multiline with just Title.
		if ( preg_match( '/<!--\s*\n\s*Title:\s*(.+?)\n\s*-->/is', $content, $matches ) ) {
			$metadata['title'] = trim( $matches[1] );
		}

		// Try multiline with just Description.
		if ( preg_match( '/<!--\s*\n\s*Description:\s*(.+?)\n\s*-->/is', $content, $matches ) ) {
			$metadata['description'] = trim( $matches[1] );
		}

		// Fallback to single-line patterns.
		if ( empty( $metadata['title'] ) && preg_match( '/<!--\s*Title:\s*(.+?)\s*-->/i', $content, $matches ) ) {
			$metadata['title'] = trim( $matches[1] );
		}

		if ( empty( $metadata['description'] ) && preg_match( '/<!--\s*Description:\s*(.+?)\s*-->/i', $content, $matches ) ) {
			$metadata['description'] = trim( $matches[1] );
		}

		return $metadata;
	}

	/**
	 * Parse pattern content, removing metadata comment header
	 *
	 * @param string $content File content.
	 * @return string Block markup content
	 */
	private function parse_pattern_content( $content ) {
		// Remove multiline comment block (most specific first).
		$content = preg_replace( '/<!--\s*\n\s*Title:\s*.+?\n\s*Description:\s*.+?\n\s*-->\s*/is', '', $content );
		$content = preg_replace( '/<!--\s*\n\s*Title:\s*.+?\n\s*-->\s*/is', '', $content );
		$content = preg_replace( '/<!--\s*\n\s*Description:\s*.+?\n\s*-->\s*/is', '', $content );

		// Remove single-line comment patterns.
		$content = preg_replace( '/<!--\s*Title:\s*.+?\s*-->\s*/i', '', $content );
		$content = preg_replace( '/<!--\s*Description:\s*.+?\s*-->\s*/i', '', $content );

		// Trim whitespace.
		return trim( $content );
	}

	/**
	 * Ensure pattern category exists
	 *
	 * @return int|false Term ID on success, false on failure
	 */
	private function ensure_category() {
		// Check if term already exists.
		$term = get_term_by( 'slug', self::CATEGORY_SLUG, 'wp_pattern_category' );

		if ( $term ) {
			return $term->term_id;
		}

		// Create the term.
		$result = wp_insert_term(
			self::CATEGORY_NAME,
			'wp_pattern_category',
			array(
				'slug' => self::CATEGORY_SLUG,
			)
		);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return isset( $result['term_id'] ) ? $result['term_id'] : false;
	}

	/**
	 * Get category term ID
	 *
	 * @return int|false Term ID on success, false on failure
	 */
	private function get_category_term_id() {
		$term = get_term_by( 'slug', self::CATEGORY_SLUG, 'wp_pattern_category' );
		return $term ? $term->term_id : false;
	}

	/**
	 * Check if a shipped pattern already exists
	 *
	 * @param string $pattern_key Pattern key (e.g., 'more-details').
	 * @return int|false Post ID if found, false otherwise
	 */
	private function pattern_exists( $pattern_key ) {
		$args = array(
			'post_type'      => 'wp_block',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => self::SHIPPED_PATTERN_META_KEY,
					'value' => $pattern_key,
				),
			),
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );
		if ( ! empty( $query->posts ) ) {
			return (int) $query->posts[0];
		}

		return false;
	}

	/**
	 * Create a shipped pattern
	 *
	 * @param string $pattern_key Pattern key (e.g., 'more-details').
	 * @param array  $definition   Pattern definition array with 'title' and 'content'.
	 * @return int|false Post ID on success, false on failure
	 */
	private function create_pattern( $pattern_key, $definition ) {
		// Ensure category exists first.
		$category_term_id = $this->ensure_category();
		if ( ! $category_term_id ) {
			return false;
		}

		// Prepare post data.
		$post_data = array(
			'post_type'    => 'wp_block',
			'post_status'  => 'publish',
			'post_title'   => $definition['title'],
			'post_content' => wp_slash( $definition['content'] ),
		);

		// Insert the pattern.
		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// Set identifying meta.
		update_post_meta( $post_id, self::SHIPPED_PATTERN_META_KEY, $pattern_key );

		// Ensure pattern is synced (don't set wp_pattern_sync_status to 'unsynced').
		// WordPress uses 'fully' for fully synced patterns.
		update_post_meta( $post_id, 'wp_pattern_sync_status', 'fully' );

		// Assign category term.
		wp_set_post_terms( $post_id, array( $category_term_id ), 'wp_pattern_category' );

		return $post_id;
	}

	/**
	 * Ensure all shipped patterns exist
	 * Only creates patterns that don't already exist (never overwrites)
	 *
	 * @return void
	 */
	public function ensure_patterns() {
		$definitions = $this->get_pattern_definitions();

		foreach ( $definitions as $pattern_key => $definition ) {
			// Check if pattern already exists.
			if ( $this->pattern_exists( $pattern_key ) ) {
				// Pattern exists, skip (never overwrite user content).
				continue;
			}

			// Create the pattern.
			$this->create_pattern( $pattern_key, $definition );
		}
	}

	/**
	 * Check if patterns need to be ensured (version check)
	 *
	 * @return bool True if patterns should be ensured, false otherwise
	 */
	public function should_ensure_patterns() {
		$stored_version  = get_option( self::VERSION_OPTION, '' );
		$current_version = SPPOPUPS_VERSION;

		// If versions don't match, we need to ensure patterns.
		return $stored_version !== $current_version;
	}

	/**
	 * Ensure patterns and update version
	 * This is the main entry point called on admin_init
	 *
	 * @return void
	 */
	public function maybe_ensure_patterns() {
		// Only run in admin.
		if ( ! is_admin() ) {
			return;
		}

		// Check if we need to ensure patterns.
		if ( ! $this->should_ensure_patterns() ) {
			return;
		}

		// Ensure category and patterns.
		$this->ensure_category();
		$this->ensure_patterns();

		// Update stored version.
		update_option( self::VERSION_OPTION, SPPOPUPS_VERSION );
	}

	/**
	 * Static method called on plugin activation
	 * Ensures shipped patterns are created immediately
	 *
	 * @return void
	 */
	public static function activate() {
		$instance = new self();
		$instance->ensure_category();
		$instance->ensure_patterns();
		// Update version to current.
		update_option( self::VERSION_OPTION, SPPOPUPS_VERSION );
	}
}
