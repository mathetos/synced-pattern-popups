<?php
/**
 * Style Collector Service
 * Collects style handles required for blocks during rendering
 *
 * @package Simplest_Popup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simplest_Popup_Style_Collector {

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
				}
			}
		}

		// Collect view style handles
		if ( ! empty( $block_type->view_style_handles ) && is_array( $block_type->view_style_handles ) ) {
			foreach ( $block_type->view_style_handles as $handle ) {
				if ( ! empty( $handle ) && is_string( $handle ) ) {
					$this->collected_styles[] = $handle;
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
		$always_loaded = array(
			'wp-block-library',
			'global-styles',
		);

		$styles = array_diff( $styles, $always_loaded );

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

		// Get unique style handles (only those newly enqueued during rendering)
		$style_handles = $this->get_style_handles();

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
				$src = $style_obj->src;

				// If relative URL, make it absolute
				if ( ! preg_match( '|^(https?:)?//|', $src ) ) {
					if ( $wp_styles->content_url && str_starts_with( $src, $wp_styles->content_url ) ) {
						// Already has content URL
					} else {
						// Use base URL
						$src = $wp_styles->base_url . $src;
					}
				}

				// Add version if available
				if ( ! empty( $style_obj->ver ) ) {
					$src = add_query_arg( 'ver', $style_obj->ver, $src );
				}

				// Apply filter (same as WordPress does)
				$src = apply_filters( 'style_loader_src', $src, $handle );

				$asset['src'] = esc_url( $src );
			}

			// Get inline CSS (before)
			$inline_before = $wp_styles->get_data( $handle, 'before' );
			if ( $inline_before && is_array( $inline_before ) ) {
				$asset['inline_before'] = implode( "\n", $inline_before );
			} elseif ( $inline_before && is_string( $inline_before ) ) {
				$asset['inline_before'] = $inline_before;
			}

			// Get inline CSS (after)
			$inline_after = $wp_styles->get_data( $handle, 'after' );
			if ( $inline_after && is_array( $inline_after ) ) {
				$asset['inline_after'] = implode( "\n", $inline_after );
			} elseif ( $inline_after && is_string( $inline_after ) ) {
				$asset['inline_after'] = $inline_after;
			}

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
				$src = $script_obj->src;

				// If relative URL, make it absolute
				if ( ! preg_match( '|^(https?:)?//|', $src ) ) {
					if ( $wp_scripts->content_url && str_starts_with( $src, $wp_scripts->content_url ) ) {
						// Already has content URL
					} else {
						// Use base URL
						$src = $wp_scripts->base_url . $src;
					}
				}

				// Add version if available
				if ( ! empty( $script_obj->ver ) ) {
					$src = add_query_arg( 'ver', $script_obj->ver, $src );
				}

				// Apply filter (same as WordPress does)
				$src = apply_filters( 'script_loader_src', $src, $handle );

				$asset['src'] = esc_url( $src );
			}

			// Get inline JS (before)
			$inline_before = $wp_scripts->get_data( $handle, 'before' );
			if ( $inline_before && is_array( $inline_before ) ) {
				$asset['inline_before'] = implode( "\n", $inline_before );
			} elseif ( $inline_before && is_string( $inline_before ) ) {
				$asset['inline_before'] = $inline_before;
			}

			// Get inline JS (after)
			$inline_after = $wp_scripts->get_data( $handle, 'after' );
			if ( $inline_after && is_array( $inline_after ) ) {
				$asset['inline_after'] = implode( "\n", $inline_after );
			} elseif ( $inline_after && is_string( $inline_after ) ) {
				$asset['inline_after'] = $inline_after;
			}

			// Only add if there's at least a src or inline JS
			if ( ! empty( $asset['src'] ) || ! empty( $asset['inline_before'] ) || ! empty( $asset['inline_after'] ) ) {
				$asset_data['scripts'][] = $asset;
			}
		}

		return $asset_data;
	}
}

