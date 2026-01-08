<?php
/**
 * Asset Collector Service
 * Collects style and script handles required for blocks during rendering
 *
 * @package SPPopups
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SPPopups_Asset_Collector {

	/**
	 * Current pattern ID being rendered
	 *
	 * @var int|null
	 */
	private $current_pattern_id = null;

	/**
	 * Collected style handles during rendering
	 *
	 * @var array
	 */
	private $collected_styles = array();

	/**
	 * Collected script handles during rendering
	 *
	 * @var array
	 */
	private $collected_scripts = array();

	/**
	 * Snapshot of wp_styles queue before rendering
	 *
	 * @var array
	 */
	private $wp_styles_snapshot = array();

	/**
	 * Snapshot of wp_scripts queue before rendering
	 *
	 * @var array
	 */
	private $wp_scripts_snapshot = array();

	/**
	 * Whether collection is active
	 *
	 * @var bool
	 */
	private $is_collecting = false;

	/**
	 * Start collecting styles for a pattern
	 *
	 * @param int $pattern_id Pattern ID
	 */
	public function start_collection( $pattern_id ) {
		$this->current_pattern_id = (int) $pattern_id;
		$this->collected_styles = array();
		$this->collected_scripts = array();
		$this->is_collecting = true;

		// Take snapshot of current wp_styles queue
		global $wp_styles;
		if ( $wp_styles && isset( $wp_styles->queue ) ) {
			$this->wp_styles_snapshot = array_merge( array(), $wp_styles->queue );
		} else {
			$this->wp_styles_snapshot = array();
		}

		// Take snapshot of current wp_scripts queue
		global $wp_scripts;
		if ( $wp_scripts && isset( $wp_scripts->queue ) ) {
			$this->wp_scripts_snapshot = array_merge( array(), $wp_scripts->queue );
		} else {
			$this->wp_scripts_snapshot = array();
		}

		// Hook into render_block filter to collect styles and scripts
		add_filter( 'render_block', array( $this, 'collect_from_render_block' ), 5, 3 );
	}

	/**
	 * Collect styles from render_block filter
	 *
	 * @param string   $html      Block HTML
	 * @param array    $block     Block array
	 * @param WP_Block $instance  Block instance
	 * @return string Unmodified block HTML
	 */
	public function collect_from_render_block( $html, $block, $instance ) {
		if ( ! $this->is_collecting || ! $instance ) {
			return $html;
		}

		// Get block type
		$block_type = $instance->block_type;
		if ( ! $block_type ) {
			return $html;
		}

		// Collect style handles from block type
		if ( ! empty( $block_type->style_handles ) && is_array( $block_type->style_handles ) ) {
			foreach ( $block_type->style_handles as $handle ) {
				if ( ! empty( $handle ) && is_string( $handle ) ) {
					$this->collected_styles[] = $handle;
					// CRITICAL: Actually enqueue the style during rendering
					// Some blocks expect their styles to be enqueued, not just registered
					// This ensures styles are properly loaded and dependencies are resolved
					if ( function_exists( 'wp_enqueue_style' ) ) {
						wp_enqueue_style( $handle );
					}
				}
			}
		}

		// Collect view style handles (frontend styles)
		if ( ! empty( $block_type->view_style_handles ) && is_array( $block_type->view_style_handles ) ) {
			foreach ( $block_type->view_style_handles as $handle ) {
				if ( ! empty( $handle ) && is_string( $handle ) ) {
					$this->collected_styles[] = $handle;
					// CRITICAL: Actually enqueue the style during rendering
					// View style handles are frontend styles that MUST be enqueued
					if ( function_exists( 'wp_enqueue_style' ) ) {
						wp_enqueue_style( $handle );
					}
				}
			}
		}

		// Check for block style variations
		// WordPress 6.6+ uses a single 'block-style-variation-styles' handle for all variations
		// Check both className attribute and rendered HTML for style variation classes
		$class_name = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
		
		// Also check the rendered HTML for style variation classes (they might be applied during rendering)
		if ( ! empty( $html ) ) {
			preg_match_all('/class=["\']([^"\']*)["\']/', $html, $class_matches);
			if ( ! empty( $class_matches[1] ) ) {
				$html_classes = implode( ' ', $class_matches[1] );
				if ( ! empty( $html_classes ) ) {
					$class_name = $class_name ? $class_name . ' ' . $html_classes : $html_classes;
				}
			}
		}
		
		// Check for block style variation classes (is-style-*)
		if ( ! empty( $class_name ) && preg_match( '/\bis-style-([a-z0-9-]+)/i', $class_name, $style_variation_match ) ) {
			// WordPress 6.6+ uses a single handle for all block style variations
			$variation_handle = 'block-style-variation-styles';
			if ( ! in_array( $variation_handle, $this->collected_styles, true ) ) {
				$this->collected_styles[] = $variation_handle;
			}
		}
		
		// Also check for legacy individual style handles (pre-6.6)
		if ( ! empty( $class_name ) ) {
			$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
			if ( $block_name ) {
				$block_styles = WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( $block_name );
				if ( ! empty( $block_styles ) ) {
					foreach ( $block_styles as $style ) {
						if ( isset( $style['style_handle'] ) && ! empty( $style['style_handle'] ) ) {
							// Check if this style is applied via className
							$style_class = isset( $style['name'] ) ? 'is-style-' . $style['name'] : '';
							if ( $style_class && strpos( $class_name, $style_class ) !== false ) {
								$this->collected_styles[] = $style['style_handle'];
							}
						}
					}
				}
			}
		}

		// Track styles enqueued during rendering
		global $wp_styles;
		if ( $wp_styles && isset( $wp_styles->queue ) ) {
			$new_styles = array_diff( $wp_styles->queue, $this->wp_styles_snapshot );
			if ( ! empty( $new_styles ) ) {
				foreach ( $new_styles as $handle ) {
					if ( ! empty( $handle ) && is_string( $handle ) ) {
						$this->collected_styles[] = $handle;
					}
				}
				// Update snapshot to current state
				$this->wp_styles_snapshot = array_merge( array(), $wp_styles->queue );
			}
		}

		// CRITICAL: Check for style-blocks-*.css files for Kadence blocks
		// WordPress may generate handles like "kadence-column-style" but Kadence also has
		// separate "style-blocks-*.css" files that need to be loaded on the frontend.
		// These are different from "blocks-*.css" files and are loaded when patterns are embedded.
		if ( strpos( $block_name, 'kadence/' ) === 0 ) {
			$block_slug = str_replace( 'kadence/', '', $block_name );
			// Try to find style-blocks-{block_slug} handle or file
			$style_blocks_handles = array(
				'style-blocks-' . $block_slug,
				'kadence-style-blocks-' . $block_slug,
				'kadence-blocks-style-' . $block_slug,
			);
			
			// Also check for generated handle from block.json style field
			// WordPress generates: {block_name}-{field_name} = kadence-column-style
			$generated_handle = str_replace( '/', '-', $block_name ) . '-style';
			$style_blocks_handles[] = $generated_handle;
			
			foreach ( $style_blocks_handles as $style_blocks_handle ) {
				if ( $wp_styles && isset( $wp_styles->registered[ $style_blocks_handle ] ) ) {
					if ( ! in_array( $style_blocks_handle, $this->collected_styles, true ) ) {
						$this->collected_styles[] = $style_blocks_handle;
						wp_enqueue_style( $style_blocks_handle );
					}
				} else {
					// If handle not registered, try to register and enqueue style-blocks-*.css file directly
					$style_blocks_file = 'style-blocks-' . $block_slug . '.css';
					$kadence_blocks_path = WP_PLUGIN_DIR . '/kadence-blocks/dist/' . $style_blocks_file;
					if ( file_exists( $kadence_blocks_path ) ) {
						$style_blocks_url = plugins_url( 'dist/' . $style_blocks_file, WP_PLUGIN_DIR . '/kadence-blocks/kadence-blocks.php' );
						$style_blocks_handle_registered = 'kadence-style-blocks-' . $block_slug;
						$kadence_version = defined( 'KADENCE_BLOCKS_VERSION' ) ? KADENCE_BLOCKS_VERSION : ( file_exists( $kadence_blocks_path ) ? filemtime( $kadence_blocks_path ) : '1.0.0' );
						if ( ! wp_style_is( $style_blocks_handle_registered, 'registered' ) ) {
							wp_register_style( $style_blocks_handle_registered, $style_blocks_url, array(), $kadence_version );
						}
						if ( ! in_array( $style_blocks_handle_registered, $this->collected_styles, true ) ) {
							$this->collected_styles[] = $style_blocks_handle_registered;
							wp_enqueue_style( $style_blocks_handle_registered );
						}
					}
				}
			}
		}

		// Track scripts enqueued during rendering
		global $wp_scripts;
		if ( $wp_scripts && isset( $wp_scripts->queue ) ) {
			$new_scripts = array_diff( $wp_scripts->queue, $this->wp_scripts_snapshot );
			if ( ! empty( $new_scripts ) ) {
				foreach ( $new_scripts as $handle ) {
					if ( ! empty( $handle ) && is_string( $handle ) ) {
						$this->collected_scripts[] = $handle;
					}
				}
				// Update snapshot to current state
				$this->wp_scripts_snapshot = array_merge( array(), $wp_scripts->queue );
			}
		}

		return $html;
	}

	/**
	 * Finish collection and return style handles
	 *
	 * @return array Array of unique style handles
	 */
	public function finish_collection() {
		// Remove filter
		remove_filter( 'render_block', array( $this, 'collect_from_render_block' ), 5 );

		$this->is_collecting = false;

		// In AJAX context, ensure wp-block-library is included
		// Core blocks (core/*) don't have style_handles and rely on wp-block-library
		// In AJAX requests, wp-block-library is not automatically enqueued, so we need to include it
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			global $wp_styles;
			// If wp-block-library is registered but not already collected, add it
			// This ensures core blocks have their styles in AJAX context
			if ( $wp_styles && isset( $wp_styles->registered['wp-block-library'] ) ) {
				$wp_block_library_collected = in_array( 'wp-block-library', $this->collected_styles, true );
				if ( ! $wp_block_library_collected ) {
					$this->collected_styles[] = 'wp-block-library';
				}
			}
		}

		// Get unique style handles
		$styles = $this->get_style_handles();

		// Reset state
		$this->current_pattern_id = null;
		$this->collected_styles = array();
		$this->collected_scripts = array();
		$this->wp_styles_snapshot = array();
		$this->wp_scripts_snapshot = array();

		return $styles;
	}

	/**
	 * Get unique array of style handles
	 *
	 * @return array Array of unique style handles
	 */
	public function get_style_handles() {
		// Remove duplicates and empty values
		$styles = array_filter( array_unique( $this->collected_styles ), 'is_string' );

		// Filter out common WordPress styles that are always loaded
		// BUT: In AJAX context, wp-block-library may not be loaded, so we need to include it
		$always_loaded = array(
			'global-styles',
		);

		// Only filter out wp-block-library if it's actually loaded in the current context
		global $wp_styles;
		$wp_block_library_loaded = ( $wp_styles && isset( $wp_styles->registered['wp-block-library'] ) && in_array( 'wp-block-library', $wp_styles->queue, true ) );

		// If wp-block-library is actually loaded, filter it out; otherwise keep it so it gets included
		if ( $wp_block_library_loaded ) {
			$styles = array_diff( $styles, array( 'wp-block-library' ) );
		} else {
			// In AJAX context, wp-block-library is not loaded, so we need to include it
			// Check if any core blocks are present (they need wp-block-library)
			// We'll add it to the collected styles if it's registered but not queued
			if ( $wp_styles && isset( $wp_styles->registered['wp-block-library'] ) && ! in_array( 'wp-block-library', $styles, true ) ) {
				// Don't add it here - let it be handled by the asset data collection
				// But don't filter it out either
			}
		}

		$styles = array_diff( $styles, $always_loaded );

		// Filter out editor-specific styles
		// These should not be loaded on the frontend
		$editor_styles = array(
			'wp-edit-blocks',
			'wp-block-editor',
			'wp-editor',
			'wp-edit-post',
			'wp-block-editor-content',
			'wp-editor-classic-layout-styles',
			'wp-format-library',
			'wp-components',
			'wp-commands',
			'wp-preferences',
			'wp-nux',
			'wp-widgets',
			'wp-edit-widgets',
			'wp-customize-widgets',
			'wp-edit-site',
			'wp-list-reusable-blocks',
			'wp-reusable-blocks',
			'wp-patterns',
		);

		// Allow filtering of editor styles list
		$editor_styles = apply_filters( 'sppopups_editor_styles', $editor_styles );

		$styles = array_diff( $styles, $editor_styles );

		// Also filter by pattern: any handle starting with known editor prefixes
		$editor_prefixes = array(
			'wp-edit-',
			'wp-block-editor',
			'wp-editor',
		);

		// Allow filtering of editor prefixes
		$editor_prefixes = apply_filters( 'sppopups_editor_style_prefixes', $editor_prefixes );

		$styles = array_filter( $styles, function( $handle ) use ( $editor_prefixes ) {
			foreach ( $editor_prefixes as $prefix ) {
				if ( strpos( $handle, $prefix ) === 0 ) {
					return false; // Exclude this style
				}
			}
			return true; // Keep this style
		} );

		// Return as indexed array
		return array_values( $styles );
	}

	/**
	 * Check if a style is already loaded in DOM (for JavaScript use)
	 * This is a PHP helper, actual checking happens in JavaScript
	 *
	 * @param string $handle Style handle
	 * @return bool True if style should be considered loaded
	 */
	public function is_style_loaded( $handle ) {
		// In PHP context, we can't reliably check DOM
		// This method is here for potential future use or consistency
		// Actual checking happens in JavaScript
		return false;
	}

	/**
	 * Extract inline assets (before/after) from WordPress dependencies
	 * Helper method to eliminate duplication between CSS and JS extraction
	 *
	 * @param WP_Dependencies $deps WordPress dependencies object (wp_styles or wp_scripts)
	 * @param string          $handle Asset handle
	 * @return array Array with 'inline_before' and 'inline_after' keys
	 */
	private function extract_inline_assets( $deps, $handle ) {
		$result = array(
			'inline_before' => '',
			'inline_after'  => '',
		);

		// Get inline before
		$inline_before = $deps->get_data( $handle, 'before' );
		if ( $inline_before && is_array( $inline_before ) ) {
			$result['inline_before'] = implode( "\n", $inline_before );
		} elseif ( $inline_before && is_string( $inline_before ) ) {
			$result['inline_before'] = $inline_before;
		}

		// Get inline after
		$inline_after = $deps->get_data( $handle, 'after' );
		if ( $inline_after && is_array( $inline_after ) ) {
			$result['inline_after'] = implode( "\n", $inline_after );
		} elseif ( $inline_after && is_string( $inline_after ) ) {
			$result['inline_after'] = $inline_after;
		}

		return $result;
	}

	/**
	 * Get asset data (styles and scripts with inline CSS/JS)
	 * This should be called after finish_collection() to get full asset information
	 *
	 * @return array Array with 'styles' and 'scripts' keys, each containing arrays of asset data
	 */
	public function get_asset_data() {
		global $wp_styles, $wp_scripts;

		$asset_data = array(
			'styles'  => array(),
			'scripts' => array(),
		);

		// In AJAX context, ensure wp-block-library is included before processing
		// Core blocks (core/*) don't have style_handles and rely on wp-block-library
		// This needs to happen BEFORE get_style_handles() is called
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( $wp_styles && isset( $wp_styles->registered['wp-block-library'] ) ) {
				$wp_block_library_collected = in_array( 'wp-block-library', $this->collected_styles, true );
				if ( ! $wp_block_library_collected ) {
					$this->collected_styles[] = 'wp-block-library';
				}
			}
		}

		// Get unique style handles (only those newly enqueued during rendering)
		$style_handles = $this->get_style_handles();

		// Collect all style dependencies recursively
		// Third-party blocks often have dependencies that need to be loaded first
		$all_style_handles = array_values( $style_handles );
		$processed_handles = array();
		
		// Get list of editor styles to filter out (same as in get_style_handles)
		$editor_styles = array(
			'wp-edit-blocks',
			'wp-block-editor',
			'wp-editor',
			'wp-edit-post',
			'wp-block-editor-content',
			'wp-editor-classic-layout-styles',
			'wp-format-library',
			'wp-components',
			'wp-commands',
			'wp-preferences',
			'wp-nux',
			'wp-widgets',
			'wp-edit-widgets',
			'wp-customize-widgets',
			'wp-edit-site',
			'wp-list-reusable-blocks',
			'wp-reusable-blocks',
			'wp-patterns',
			// Third-party editor styles
			'kadence-editor-global',
			'kadence-blocks-global-editor-styles',
		);
		$editor_styles = apply_filters( 'sppopups_editor_styles', $editor_styles );
		$editor_prefixes = array( 'wp-edit-', 'wp-block-editor', 'wp-editor', 'kadence-editor', 'kadence-blocks-global-editor' );
		$editor_prefixes = apply_filters( 'sppopups_editor_style_prefixes', $editor_prefixes );

		// Helper function to check if a style is an editor style
		$is_editor_style = function( $handle ) use ( $editor_styles, $editor_prefixes ) {
			if ( in_array( $handle, $editor_styles, true ) ) {
				return true;
			}
			foreach ( $editor_prefixes as $prefix ) {
				if ( strpos( $handle, $prefix ) === 0 ) {
					return true;
				}
			}
			return false;
		};

		// Recursive function to collect dependencies
		$collect_deps = function( $handle ) use ( &$collect_deps, &$all_style_handles, &$processed_handles, $wp_styles, $is_editor_style ) {
			if ( in_array( $handle, $processed_handles, true ) ) {
				return; // Already processed
			}
			
			$processed_handles[] = $handle;
			
			if ( ! $wp_styles || ! isset( $wp_styles->registered[ $handle ] ) ) {
				return;
			}
			
			$style_obj = $wp_styles->registered[ $handle ];
			if ( ! empty( $style_obj->deps ) && is_array( $style_obj->deps ) ) {
				foreach ( $style_obj->deps as $dep_handle ) {
					if ( ! empty( $dep_handle ) && is_string( $dep_handle ) && ! in_array( $dep_handle, $all_style_handles, true ) ) {
						// Skip editor-only styles - they shouldn't be loaded on frontend
						if ( $is_editor_style( $dep_handle ) ) {
							continue;
						}
						
						if ( isset( $wp_styles->registered[ $dep_handle ] ) ) {
							// Add dependency to beginning so it loads first
							array_unshift( $all_style_handles, $dep_handle );
							// Recursively collect dependencies of this dependency
							$collect_deps( $dep_handle );
						}
					}
				}
			}
		};
		
		// Collect dependencies for all style handles
		foreach ( $all_style_handles as $handle ) {
			$collect_deps( $handle );
		}
		
		// Use the expanded list with dependencies
		$style_handles = array_values( array_unique( $all_style_handles ) );

		// Filter out editor-only styles from the final list
		$style_handles = array_filter( $style_handles, function( $handle ) use ( $is_editor_style ) {
			return ! $is_editor_style( $handle );
		} );

		// Process each style handle
		foreach ( $style_handles as $handle ) {
			if ( ! $wp_styles || ! isset( $wp_styles->registered[ $handle ] ) ) {
				continue;
			}

			$style_obj = $wp_styles->registered[ $handle ];

			$asset = array(
				'handle'       => $handle,
				'src'          => '',
				'inline_before' => '',
				'inline_after' => '',
			);

			// Get src URL
			if ( ! empty( $style_obj->src ) ) {
				$asset['src'] = SPPopups_Plugin::normalize_asset_url(
					$style_obj->src,
					$handle,
					'style',
					isset( $style_obj->ver ) ? $style_obj->ver : '',
					$wp_styles
				);
			}

			// Get inline CSS (before/after)
			$inline_assets = $this->extract_inline_assets( $wp_styles, $handle );
			$asset['inline_before'] = $inline_assets['inline_before'];
			$asset['inline_after'] = $inline_assets['inline_after'];

			// Only add if there's at least a src or inline CSS
			if ( ! empty( $asset['src'] ) || ! empty( $asset['inline_before'] ) || ! empty( $asset['inline_after'] ) ) {
				$asset_data['styles'][] = $asset;
			}
		}

		// Get unique script handles (only those newly enqueued during rendering)
		$script_handles = array_filter( array_unique( $this->collected_scripts ), 'is_string' );
		$script_handles = array_values( $script_handles );

		// Process each script handle
		foreach ( $script_handles as $handle ) {
			if ( ! $wp_scripts || ! isset( $wp_scripts->registered[ $handle ] ) ) {
				continue;
			}

			$script_obj = $wp_scripts->registered[ $handle ];
			$asset = array(
				'handle'       => $handle,
				'src'          => '',
				'inline_before' => '',
				'inline_after' => '',
			);

			// Get src URL
			if ( ! empty( $script_obj->src ) ) {
				$asset['src'] = SPPopups_Plugin::normalize_asset_url(
					$script_obj->src,
					$handle,
					'script',
					isset( $script_obj->ver ) ? $script_obj->ver : '',
					$wp_scripts
				);
			}

			// Get inline JS (before/after)
			$inline_assets = $this->extract_inline_assets( $wp_scripts, $handle );
			$asset['inline_before'] = $inline_assets['inline_before'];
			$asset['inline_after'] = $inline_assets['inline_after'];

			// Only add if there's at least a src or inline JS
			if ( ! empty( $asset['src'] ) || ! empty( $asset['inline_before'] ) || ! empty( $asset['inline_after'] ) ) {
				$asset_data['scripts'][] = $asset;
			}
		}

		return $asset_data;
	}
}

