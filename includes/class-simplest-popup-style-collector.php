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
	 * Snapshot of wp_styles queue before rendering
	 *
	 * @var array
	 */
	private $wp_styles_snapshot = array();

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
		$this->is_collecting = true;

		// Take snapshot of current wp_styles queue
		global $wp_styles;
		if ( $wp_styles && isset( $wp_styles->queue ) ) {
			$this->wp_styles_snapshot = array_merge( array(), $wp_styles->queue );
		} else {
			$this->wp_styles_snapshot = array();
		}

		// Hook into render_block filter to collect styles
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
		$this->wp_styles_snapshot = array();

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
}

