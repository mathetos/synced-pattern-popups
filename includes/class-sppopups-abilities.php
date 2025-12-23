<?php
/**
 * Abilities API Registration
 * Registers Synced Pattern Popups abilities for WordPress 6.9+ Abilities API
 * Gracefully degrades on older WordPress versions
 *
 * @package SPPopups
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPPopups_Abilities {

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
	 * Asset collector instance
	 *
	 * @var SPPopups_Asset_Collector
	 */
	private $style_collector;

	/**
	 * Constructor
	 *
	 * @param SPPopups_Pattern         $pattern_service Pattern service instance
	 * @param SPPopups_Cache           $cache_service   Cache service instance
	 * @param SPPopups_Asset_Collector $style_collector Asset collector instance
	 */
	public function __construct( SPPopups_Pattern $pattern_service, SPPopups_Cache $cache_service, SPPopups_Asset_Collector $style_collector ) {
		$this->pattern_service = $pattern_service;
		$this->cache_service = $cache_service;
		$this->style_collector = $style_collector;
	}

	/**
	 * Initialize abilities registration
	 * Only registers if Abilities API is available (WP 6.9+)
	 */
	public function init() {
		// Check if Abilities API is available
		if ( ! function_exists( 'wp_register_ability' ) ) {
			// Abilities API not available - gracefully skip registration
			return;
		}

		// Register category before abilities (required by Abilities API)
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		// Register abilities on the proper hook (required by Abilities API)
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register ability category
	 * Called on wp_abilities_api_categories_init action
	 */
	public function register_category() {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'sppopups',
				array(
					'label'       => __( 'Synced Pattern Popups', 'sppopups' ),
					'description' => __( 'Abilities for managing popup content from Synced Patterns.', 'sppopups' ),
				)
			);
		}
	}

	/**
	 * Register all abilities
	 * Called on wp_abilities_api_init action
	 */
	public function register_abilities() {
		// Register all abilities
		$this->register_render_ability();
		$this->register_list_patterns_ability();
		$this->register_cache_clear_ability();
		$this->register_cache_clear_all_ability();
		$this->register_scan_triggers_ability();
	}

	/**
	 * Check if user has permission for an ability
	 * Signature matches Abilities API: callable( mixed $input= ): (bool|WP_Error)
	 *
	 * @param mixed $input Optional input data (unused for permission check)
	 * @return bool|WP_Error True if user has permission, false or WP_Error otherwise
	 */
	public function check_permission( $input = null ) {
		// Default permission: Editors+ (edit_others_posts)
		$allowed = current_user_can( 'edit_others_posts' );

		// Allow filter for custom permission logic
		// Note: We can't pass ability_name here since we don't have context, but filter can check current ability
		$allowed = apply_filters( 'sppopups_ability_permission', $allowed, null, array() );

		return $allowed;
	}

	/**
	 * Register render popup ability
	 */
	private function register_render_ability() {
		wp_register_ability(
			'sppopups/render',
			array(
				'label'       => __( 'Render Popup Content', 'sppopups' ),
				'description' => __( 'Renders a synced pattern as popup content with all required styles and assets.', 'sppopups' ),
				'category'    => 'sppopups',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'pattern_id' => array(
							'type'        => 'integer',
							'description' => __( 'The ID of the synced pattern to render.', 'sppopups' ),
							'required'    => true,
						),
					),
					'required'   => array( 'pattern_id' ),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'html'                      => array(
							'type'        => 'string',
							'description' => __( 'Rendered HTML content.', 'sppopups' ),
						),
						'title'                     => array(
							'type'        => 'string',
							'description' => __( 'Pattern title.', 'sppopups' ),
						),
						'styles'                    => array(
							'type'        => 'array',
							'description' => __( 'Array of style handles required for this pattern.', 'sppopups' ),
							'items'       => array( 'type' => 'string' ),
						),
						'block_supports_css'        => array(
							'type'        => 'string',
							'description' => __( 'Block supports CSS from Style Engine.', 'sppopups' ),
						),
						'block_style_variation_css' => array(
							'type'        => 'string',
							'description' => __( 'Block style variation CSS (is-style-* classes).', 'sppopups' ),
						),
						'global_stylesheet'         => array(
							'type'        => 'string',
							'description' => __( 'Global stylesheet for CSS variables.', 'sppopups' ),
						),
						'asset_data'                => array(
							'type'        => 'object',
							'description' => __( 'Detailed asset data (styles and scripts with URLs and inline content).', 'sppopups' ),
						),
					),
				),
				'meta'                => array(
					'mcp' => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
				'permission_callback' => function( $input = null ) {
					return $this->check_permission( $input );
				},
				'execute_callback'    => function( $params ) {
					return $this->execute_render( $params );
				},
			)
		);
	}

	/**
	 * Execute render ability
	 *
	 * @param array $params Ability parameters
	 * @return array|WP_Error Rendered content data or error
	 */
	public function execute_render( $params ) {
		// Validate pattern_id
		if ( ! isset( $params['pattern_id'] ) || ! is_numeric( $params['pattern_id'] ) ) {
			return new WP_Error( 'invalid_pattern_id', __( 'Invalid pattern ID.', 'sppopups' ), array( 'status' => 400 ) );
		}

		$pattern_id = (int) $params['pattern_id'];

		// Validate range
		if ( $pattern_id <= 0 || $pattern_id > 2147483647 ) {
			return new WP_Error( 'invalid_pattern_id', __( 'Pattern ID out of valid range.', 'sppopups' ), array( 'status' => 400 ) );
		}

		// Get pattern post object for title
		$pattern = get_post( $pattern_id );
		if ( ! $pattern || $pattern->post_type !== 'wp_block' ) {
			return new WP_Error( 'pattern_not_found', __( 'Pattern not found.', 'sppopups' ), array( 'status' => 404 ) );
		}

		// Get rendered content with asset collection
		$rendered = $this->pattern_service->get_rendered_content( $pattern_id, $this->style_collector );

		if ( false === $rendered ) {
			return new WP_Error( 'render_failed', __( 'Failed to render pattern content.', 'sppopups' ), array( 'status' => 500 ) );
		}

		// Normalize response (handle both string and array returns)
		if ( is_string( $rendered ) ) {
			$rendered = array(
				'html'                      => $rendered,
				'styles'                    => array(),
				'block_supports_css'        => '',
				'block_style_variation_css' => '',
				'global_stylesheet'         => '',
				'asset_data'                => SPPopups_Cache::get_default_asset_data(),
			);
		}

		// Add title
		$rendered['title'] = $pattern->post_title ? $pattern->post_title : __( '(no title)', 'sppopups' );

		return $rendered;
	}

	/**
	 * Register list synced patterns ability
	 */
	private function register_list_patterns_ability() {
		wp_register_ability(
			'sppopups/list-synced-patterns',
			array(
				'label'       => __( 'List Synced Patterns', 'sppopups' ),
				'description' => __( 'Returns a list of all synced patterns available for popups.', 'sppopups' ),
				'category'    => 'sppopups',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array(
							'type'        => 'string',
							'description' => __( 'Filter by post status (e.g., "publish", "draft"). Optional.', 'sppopups' ),
							'enum'        => array( 'publish', 'draft', 'private', 'pending', 'any' ),
						),
					),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'patterns' => array(
							'type'        => 'array',
							'description' => __( 'Array of synced pattern objects.', 'sppopups' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'integer' ),
									'title'       => array( 'type' => 'string' ),
									'status'      => array( 'type' => 'string' ),
									'sync_status' => array( 'type' => 'string' ),
									'edit_url'    => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'meta'                => array(
					'mcp' => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_list_patterns' ),
			)
		);
	}

	/**
	 * Execute list patterns ability
	 *
	 * @param array $params Ability parameters
	 * @return array|WP_Error List of patterns or error
	 */
	public function execute_list_patterns( $params ) {
		// Get status filter (default: any)
		$status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'any';

		// Query synced patterns using shared method
		$posts = $this->pattern_service->get_synced_patterns( $status );

		// Format response
		$patterns = array();
		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
				continue;
			}

			$sync_status = get_post_meta( $post->ID, 'wp_pattern_sync_status', true );
			$is_synced = ( 'unsynced' !== $sync_status );

			$patterns[] = array(
				'id'          => (int) $post->ID,
				'title'       => $post->post_title ? $post->post_title : __( '(no title)', 'sppopups' ),
				'status'      => $post->post_status,
				'sync_status' => $is_synced ? 'synced' : 'unsynced',
				'edit_url'    => get_edit_post_link( $post->ID, 'raw' ),
			);
		}

		return array( 'patterns' => $patterns );
	}

	/**
	 * Register cache clear ability
	 */
	private function register_cache_clear_ability() {
		wp_register_ability(
			'sppopups/cache-clear',
			array(
				'label'       => __( 'Clear Pattern Cache', 'sppopups' ),
				'description' => __( 'Clears the cached content for a specific synced pattern.', 'sppopups' ),
				'category'    => 'sppopups',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'pattern_id' => array(
							'type'        => 'integer',
							'description' => __( 'The ID of the pattern to clear cache for.', 'sppopups' ),
							'required'    => true,
						),
					),
					'required'   => array( 'pattern_id' ),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'cleared' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether the cache was successfully cleared.', 'sppopups' ),
						),
					),
				),
				'meta'                => array(
					'mcp' => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_cache_clear' ),
			)
		);
	}

	/**
	 * Execute cache clear ability
	 *
	 * @param array $params Ability parameters
	 * @return array|WP_Error Success status or error
	 */
	public function execute_cache_clear( $params ) {
		// Validate pattern_id
		if ( ! isset( $params['pattern_id'] ) || ! is_numeric( $params['pattern_id'] ) ) {
			return new WP_Error( 'invalid_pattern_id', __( 'Invalid pattern ID.', 'sppopups' ), array( 'status' => 400 ) );
		}

		$pattern_id = (int) $params['pattern_id'];

		// Validate range
		if ( $pattern_id <= 0 || $pattern_id > 2147483647 ) {
			return new WP_Error( 'invalid_pattern_id', __( 'Pattern ID out of valid range.', 'sppopups' ), array( 'status' => 400 ) );
		}

		// Clear cache
		$cleared = $this->cache_service->delete( $pattern_id );

		// Also clear pattern object cache
		$pattern_cache_key = 'sppopups_pattern_' . $pattern_id;
		wp_cache_delete( $pattern_cache_key, 'sppopups_patterns' );

		return array( 'cleared' => $cleared );
	}

	/**
	 * Register cache clear all ability
	 */
	private function register_cache_clear_all_ability() {
		wp_register_ability(
			'sppopups/cache-clear-all',
			array(
				'label'       => __( 'Clear All Popup Cache', 'sppopups' ),
				'description' => __( 'Clears all cached popup content for all synced patterns.', 'sppopups' ),
				'category'    => 'sppopups',
				'input_schema' => array(
					'type' => 'object',
					'properties' => array(),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'deleted_count' => array(
							'type'        => 'integer',
							'description' => __( 'Number of cache entries deleted.', 'sppopups' ),
						),
					),
				),
				'meta'                => array(
					'mcp' => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_cache_clear_all' ),
			)
		);
	}

	/**
	 * Execute cache clear all ability
	 *
	 * @param array $params Ability parameters (unused)
	 * @return array Result with deleted count
	 */
	public function execute_cache_clear_all( $params ) {
		$deleted = $this->cache_service->clear_all();

		return array( 'deleted_count' => $deleted );
	}

	/**
	 * Register scan triggers ability
	 */
	private function register_scan_triggers_ability() {
		wp_register_ability(
			'sppopups/scan-triggers',
			array(
				'label'       => __( 'Scan for Popup Triggers', 'sppopups' ),
				'description' => __( 'Scans HTML content or a post/page for popup trigger links and classes.', 'sppopups' ),
				'category'    => 'sppopups',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'Post ID to scan. Mutually exclusive with html parameter.', 'sppopups' ),
						),
						'html'    => array(
							'type'        => 'string',
							'description' => __( 'Raw HTML content to scan. Mutually exclusive with post_id parameter.', 'sppopups' ),
						),
					),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'triggers' => array(
							'type'        => 'array',
							'description' => __( 'Array of discovered trigger objects.', 'sppopups' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'type'      => array(
										'type'        => 'string',
										'description' => __( 'Trigger type: "class" or "href".', 'sppopups' ),
										'enum'        => array( 'class', 'href' ),
									),
									'id'        => array( 'type' => 'integer' ),
									'max_width' => array(
										'type'        => 'integer',
										'description' => __( 'Optional max-width in pixels.', 'sppopups' ),
									),
								),
							),
						),
					),
				),
				'meta'                => array(
					'mcp' => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
				'permission_callback' => array( $this, 'check_permission' ),
				'execute_callback'    => array( $this, 'execute_scan_triggers' ),
			)
		);
	}

	/**
	 * Execute scan triggers ability
	 *
	 * @param array $params Ability parameters
	 * @return array|WP_Error List of triggers or error
	 */
	public function execute_scan_triggers( $params ) {
		$html = '';

		// Get HTML from post_id or html parameter
		if ( isset( $params['post_id'] ) && is_numeric( $params['post_id'] ) ) {
			$post_id = (int) $params['post_id'];
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error( 'post_not_found', __( 'Post not found.', 'sppopups' ), array( 'status' => 404 ) );
			}
			$html = $post->post_content;
		} elseif ( isset( $params['html'] ) && is_string( $params['html'] ) ) {
			$html = $params['html'];
		} else {
			return new WP_Error( 'missing_parameter', __( 'Either post_id or html parameter is required.', 'sppopups' ), array( 'status' => 400 ) );
		}

		if ( empty( $html ) ) {
			return array( 'triggers' => array() );
		}

		// Use trigger parser to scan HTML
		$parser = new SPPopups_Trigger_Parser();
		$triggers = $parser->scan_html( $html );

		return array( 'triggers' => $triggers );
	}
}

