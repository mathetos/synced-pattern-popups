<?php
/**
 * Gallery Block Integration
 * Extends WordPress Core Gallery block with SPPopups modal support
 *
 * @package SPPopups
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * SPPopups_Gallery class.
 *
 * @package SPPopups
 */
class SPPopups_Gallery {

	/**
	 * Cached attachment data per request (memoization)
	 *
	 * @var array
	 */
	private static $attachment_cache = array();

	/**
	 * Regex patterns (cached to avoid recompilation)
	 */
	const PATTERN_FIGURE_IMAGE = '/<figure([^>]*class="[^"]*wp-block-image[^"]*"[^>]*)>(.*?)<\/figure>/s';
	const PATTERN_FIGCAPTION   = '/<figcaption[^>]*>(.*?)<\/figcaption>/is';
	const PATTERN_P_CAPTION    = '/<p[^>]*class="[^"]*caption[^"]*"[^>]*>(.*?)<\/p>/is';

	/**
	 * Initialize gallery integration
	 */
	public function init() {
		// Enqueue editor assets.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );

		// Filter gallery block render to add data attributes.
		// Priority 10 (default) runs after WordPress Core's render callback, which includes random order shuffle.
		// This ensures we receive the already-shuffled HTML and can properly reorder gallery_data to match.
		add_filter( 'render_block', array( $this, 'filter_gallery_block_render' ), 10, 2 );
	}

	/**
	 * Enqueue editor assets for Gallery block extension
	 */
	public function enqueue_editor_assets() {
		// Only enqueue in block editor.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}

		wp_enqueue_script(
			'sppopups-gallery-editor',
			SPPOPUPS_PLUGIN_URL . 'assets/js/gallery-editor.js',
			array( 'wp-blocks', 'wp-hooks', 'wp-i18n', 'wp-element', 'wp-components' ),
			SPPOPUPS_VERSION,
			true
		);
	}

	/**
	 * Filter Gallery block render to add data attributes when linkTo is 'sppopup'
	 *
	 * @param string $block_content The block content about to be appended.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string Modified block content
	 */
	public function filter_gallery_block_render( $block_content, $block ) {
		// Only process Gallery blocks.
		if ( 'core/gallery' !== $block['blockName'] ) {
			return $block_content;
		}

		// Only process if linkTo is 'sppopup'.
		if ( empty( $block['attrs']['linkTo'] ) || 'sppopup' !== $block['attrs']['linkTo'] ) {
			return $block_content;
		}

		// Extract gallery data from block attributes.
		$gallery_data = $this->extract_gallery_data( $block );

		if ( empty( $gallery_data['images'] ) ) {
			return $block_content;
		}

		// Generate unique gallery ID.
		$gallery_id = wp_unique_id( 'sppopup-gallery-' );

		// Extract and validate settings.
		$settings                        = $this->extract_gallery_settings( $block );
		$gallery_data['modalSize']       = $settings['modalSize'];
		$gallery_data['closeButtons']    = $settings['closeButtons'];
		$gallery_data['imageNavigation'] = $settings['imageNavigation'];

		// Extract captions from HTML and update gallery data.
		$html_content = $this->extract_captions_from_html( $block_content, $gallery_data );

		// Find gallery container and add data attributes.
		$processed_content = $this->find_gallery_container( $html_content );

		// Add data attributes to gallery container.
		if ( $processed_content ) {
			$processed_content->set_attribute( 'data-sppopup-gallery', 'true' );
			$processed_content->set_attribute( 'data-gallery-id', $gallery_id );
			$processed_content->set_attribute( 'data-gallery-data', wp_json_encode( $gallery_data ) );
			$processed_content->set_attribute( 'data-modal-size', $settings['modalSize'] );

			$html_content = $processed_content->get_updated_html();
		}

		// CRITICAL: Check if randomOrder is enabled BEFORE processing.
		// When randomOrder is enabled, WordPress Core shuffles the HTML, so we can't use index-based matching.
		$is_random_order = ! empty( $block['attrs']['randomOrder'] ) && true === $block['attrs']['randomOrder'];

		// Create image lookup map by ID for fast access (needed for random order).
		$image_map_by_id = array();
		foreach ( $gallery_data['images'] as $idx => $img ) {
			if ( ! empty( $img['id'] ) && $img['id'] > 0 ) {
				$image_map_by_id[ $img['id'] ] = array(
					'data'  => $img,
					'index' => $idx,
				);
			}
		}

		// Reset index for the actual modification pass (used for non-random order).
		$image_index = 0;

		// Use regex to find and modify image links/figures.
		// Pattern matches figure elements containing images.
		$html_content = preg_replace_callback(
			self::PATTERN_FIGURE_IMAGE,
			function ( $matches ) use ( &$image_index, $gallery_data, $is_random_order, $image_map_by_id ) {
				$figure_attrs   = $matches[1];
				$figure_content = $matches[2];

				$image_data = null;
				$actual_index = -1;

				if ( $is_random_order ) {
					// CRITICAL FOR RANDOM ORDER: Extract image ID from img tag's data-id attribute.
					// Core adds data-id to img tags, so we can match the correct image data.
					// Pattern: <img ... data-id="123" ...>.
					if ( preg_match( '/<img[^>]*\bdata-id="(\d+)"[^>]*>/', $figure_content, $img_matches ) ) {
						$img_id = intval( $img_matches[1] );
						if ( $img_id > 0 && isset( $image_map_by_id[ $img_id ] ) ) {
							$image_data   = $image_map_by_id[ $img_id ]['data'];
							$actual_index = $image_map_by_id[ $img_id ]['index'];
						}
					}

					// Fallback: Try to extract from img src URL if data-id not found.
					if ( ! $image_data && preg_match( '/<img[^>]*\bsrc="([^"]+)"[^>]*>/', $figure_content, $src_matches ) ) {
						$img_src = $src_matches[1];
						// Try to match by URL in gallery_data.
						foreach ( $gallery_data['images'] as $idx => $img ) {
							$img_url = ! empty( $img['fullUrl'] ) ? $img['fullUrl'] : ( ! empty( $img['url'] ) ? $img['url'] : '' );
							if ( $img_url && ( $img_src === $img_url || strpos( $img_src, basename( $img_url ) ) !== false ) ) {
								$image_data   = $img;
								$actual_index = $idx;
								break;
							}
						}
					}
				} else {
					// Normal order: Use index-based matching.
					$image_data   = isset( $gallery_data['images'][ $image_index ] ) ? $gallery_data['images'][ $image_index ] : null;
					$actual_index = $image_index;
					$image_index++;
				}

				if ( ! $image_data ) {
					return $matches[0];
				}

				// Try to extract caption from figure content if not already in image_data.
				if ( empty( $image_data['caption'] ) ) {
					$image_data['caption'] = $this->extract_caption_from_content( $figure_content );
				}

				// Add data attributes to figure (invisible - no structural changes).
				// JavaScript will handle clicks on figure, image, or any links within.
				// CRITICAL: We use image ID instead of index to handle randomized order correctly.
				if ( ! empty( $image_data['id'] ) && $image_data['id'] > 0 ) {
					$figure_attrs .= ' data-image-id="' . esc_attr( $image_data['id'] ) . '"';
				}
				if ( $actual_index >= 0 ) {
					$figure_attrs .= ' data-image-index="' . esc_attr( $actual_index ) . '"'; // Keep for backward compatibility.
				}
				$figure_attrs .= ' data-image-data="' . esc_attr( wp_json_encode( $image_data ) ) . '"';

				// Don't modify links or add links - preserve Core's exact HTML structure.
				// JavaScript will intercept clicks via event delegation and match by image ID/URL.

				return '<figure' . $figure_attrs . '>' . $figure_content . '</figure>';
			},
			$html_content
		);

		// CRITICAL FIX FOR RANDOM ORDER (Part 2):
		// Now that we've correctly matched image data to each figure element by ID (above),
		// we need to reorder the gallery_data array to match the HTML display order.
		// This ensures the modal opens with images in the correct sequence when navigating.
		if ( $is_random_order ) {
			$html_content = $this->fix_random_order_gallery_data( $html_content, $gallery_data );
		}

		return $html_content;
	}

	/**
	 * Extract gallery data from block attributes
	 *
	 * @param array $block Block data.
	 * @return array Gallery data structure
	 */
	private function extract_gallery_data( $block ) {
		$gallery_data = array(
			'images'    => array(),
			'galleryId' => '',
		);

		// Get images from block attributes.
		$images = isset( $block['attrs']['images'] ) ? $block['attrs']['images'] : array();

		if ( empty( $images ) ) {
			// Try to get from innerBlocks (newer Gallery block format).
			if ( ! empty( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as $index => $inner_block ) {
					if ( 'core/image' === $inner_block['blockName'] ) {
						$image_data = $this->extract_image_data_from_block( $inner_block );
						if ( $image_data ) {
							$image_data['index']      = $index;
							$gallery_data['images'][] = $image_data;
						}
					}
				}
			}

			// Fallback: try to get from ids attribute.
			if ( empty( $gallery_data['images'] ) && ! empty( $block['attrs']['ids'] ) && is_array( $block['attrs']['ids'] ) ) {
				foreach ( $block['attrs']['ids'] as $index => $image_id ) {
					$image_id        = intval( $image_id );
					$attachment_data = $this->get_attachment_data( $image_id );
					if ( $attachment_data ) {
						$attachment_data['index'] = $index;
						$gallery_data['images'][] = $attachment_data;
					}
				}
			}
		} else {
			// Process images array.
			foreach ( $images as $index => $image ) {
				$image_data = array(
					'id'      => isset( $image['id'] ) ? intval( $image['id'] ) : 0,
					'fullUrl' => isset( $image['fullUrl'] ) ? esc_url_raw( $image['fullUrl'] ) : ( isset( $image['url'] ) ? esc_url_raw( $image['url'] ) : '' ),
					'caption' => isset( $image['caption'] ) ? wp_kses_post( $image['caption'] ) : '',
					'alt'     => isset( $image['alt'] ) ? esc_attr( $image['alt'] ) : '',
					'index'   => $index,
				);

				// If caption or alt is empty, try to get from attachment using cached method.
				if ( ( empty( $image_data['caption'] ) || empty( $image_data['alt'] ) ) && ! empty( $image_data['id'] ) ) {
					$attachment_data = $this->get_attachment_data( $image_data['id'] );
					if ( $attachment_data ) {
						if ( empty( $image_data['caption'] ) && ! empty( $attachment_data['caption'] ) ) {
							$image_data['caption'] = $attachment_data['caption'];
						}
						if ( empty( $image_data['alt'] ) && ! empty( $attachment_data['alt'] ) ) {
							$image_data['alt'] = $attachment_data['alt'];
						}
					}
				}

				$gallery_data['images'][] = $image_data;
			}
		}

		return $gallery_data;
	}

	/**
	 * Extract image data from inner Image block
	 *
	 * @param array $image_block Image block data.
	 * @return array|null Image data or null
	 */
	private function extract_image_data_from_block( $image_block ) {
		$attrs = isset( $image_block['attrs'] ) ? $image_block['attrs'] : array();

		$image_id = isset( $attrs['id'] ) ? intval( $attrs['id'] ) : 0;
		if ( ! $image_id ) {
			return null;
		}

		// Get attachment data using cached method.
		$attachment_data = $this->get_attachment_data( $image_id );
		if ( ! $attachment_data ) {
			return null;
		}

		// Get caption - try multiple sources (prioritize block attributes).
		$caption = '';

		// First try from block attributes.
		if ( isset( $attrs['caption'] ) && ! empty( $attrs['caption'] ) ) {
			$caption = $attrs['caption'];
		}

		// If not in attrs, try to extract from innerHTML (for Gallery blocks).
		if ( empty( $caption ) && isset( $image_block['innerHTML'] ) ) {
			$caption = $this->extract_caption_from_content( $image_block['innerHTML'] );
		}

		// Fallback to cached attachment caption.
		if ( empty( $caption ) ) {
			$caption = $attachment_data['caption'];
		}

		// Get alt text (prioritize block attributes).
		$alt = isset( $attrs['alt'] ) ? $attrs['alt'] : '';
		if ( empty( $alt ) ) {
			$alt = $attachment_data['alt'];
		}

		return array(
			'id'      => $image_id,
			'fullUrl' => $attachment_data['fullUrl'],
			'caption' => wp_kses_post( $caption ),
			'alt'     => esc_attr( $alt ),
			'index'   => 0, // Will be set correctly during processing.
		);
	}

	/**
	 * Extract and validate gallery settings from block attributes
	 *
	 * @param array $block Block data.
	 * @return array Settings array
	 */
	private function extract_gallery_settings( $block ) {
		$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();

		// Modal size (default 600px, minimum 100px).
		$modal_size = isset( $attrs['sppopupModalSize'] ) ? intval( $attrs['sppopupModalSize'] ) : 600;
		$modal_size = max( 100, $modal_size );

		// Close buttons (default 'both').
		$close_buttons = isset( $attrs['sppopupCloseButtons'] ) ? $attrs['sppopupCloseButtons'] : 'both';
		if ( ! in_array( $close_buttons, array( 'icon', 'button', 'both' ), true ) ) {
			$close_buttons = 'both';
		}

		// Image navigation (default 'both').
		$image_navigation = isset( $attrs['sppopupImageNavigation'] ) ? $attrs['sppopupImageNavigation'] : 'both';
		if ( ! in_array( $image_navigation, array( 'image', 'footer', 'both' ), true ) ) {
			$image_navigation = 'both';
		}

		return array(
			'modalSize'       => $modal_size,
			'closeButtons'    => $close_buttons,
			'imageNavigation' => $image_navigation,
		);
	}

	/**
	 * Find gallery container in HTML content
	 *
	 * @param string $html_content HTML content.
	 * @return WP_HTML_Tag_Processor Processor positioned at gallery container
	 */
	private function find_gallery_container( $html_content ) {
		$processed_content = new WP_HTML_Tag_Processor( $html_content );

		// Try wp-block-gallery class first.
		if ( $processed_content->next_tag( array( 'class_name' => 'wp-block-gallery' ) ) ) {
			return $processed_content;
		}

		// Try figure tag with gallery classes.
		$processed_content = new WP_HTML_Tag_Processor( $html_content );
		while ( $processed_content->next_tag( 'figure' ) ) {
			$class = $processed_content->get_attribute( 'class' );
			if ( $class && ( strpos( $class, 'wp-block-gallery' ) !== false || strpos( $class, 'blocks-gallery-grid' ) !== false ) ) {
				return $processed_content;
			}
		}

		// Fallback: first tag.
		$processed_content = new WP_HTML_Tag_Processor( $html_content );
		$processed_content->next_tag();
		return $processed_content;
	}

	/**
	 * Extract captions from HTML content and update gallery data
	 *
	 * @param string $html_content HTML content.
	 * @param array  $gallery_data Gallery data (passed by reference).
	 * @return string Updated HTML content
	 */
	private function extract_captions_from_html( $html_content, &$gallery_data ) {
		$image_index = 0;

		return preg_replace_callback(
			self::PATTERN_FIGURE_IMAGE,
			function ( $matches ) use ( &$image_index, &$gallery_data ) {
				$figure_content = $matches[2];

				// Update caption if missing and found in HTML.
				if ( isset( $gallery_data['images'][ $image_index ] ) && empty( $gallery_data['images'][ $image_index ]['caption'] ) ) {
					$caption = $this->extract_caption_from_content( $figure_content );
					if ( ! empty( $caption ) ) {
						$gallery_data['images'][ $image_index ]['caption'] = $caption;
					}
				}

				$image_index++;
				return $matches[0]; // Return unchanged.
			},
			$html_content
		);
	}

	/**
	 * Extract caption from HTML content
	 *
	 * @param string $content HTML content.
	 * @return string Caption text (empty if not found)
	 */
	private function extract_caption_from_content( $content ) {
		// Try figcaption first.
		if ( preg_match( self::PATTERN_FIGCAPTION, $content, $matches ) ) {
			$caption = wp_kses_post( trim( $matches[1] ) );
			if ( ! empty( $caption ) ) {
				return $caption;
			}
		}

		// Try p.caption.
		if ( preg_match( self::PATTERN_P_CAPTION, $content, $matches ) ) {
			$caption = wp_kses_post( trim( $matches[1] ) );
			if ( ! empty( $caption ) ) {
				return $caption;
			}
		}

		return '';
	}

	/**
	 * Get attachment data with per-request memoization
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|false Attachment data or false if not found
	 */
	private function get_attachment_data( $attachment_id ) {
		if ( ! $attachment_id || $attachment_id <= 0 ) {
			return false;
		}

		// Check cache first.
		if ( isset( self::$attachment_cache[ $attachment_id ] ) ) {
			return self::$attachment_cache[ $attachment_id ];
		}

		// Fetch data.
		$full_url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! $full_url ) {
			self::$attachment_cache[ $attachment_id ] = false;
			return false;
		}

		$caption = wp_get_attachment_caption( $attachment_id );
		$alt     = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		$data = array(
			'id'      => $attachment_id,
			'fullUrl' => esc_url_raw( $full_url ),
			'caption' => wp_kses_post( $caption ),
			'alt'     => esc_attr( $alt ),
		);

		// Cache it.
		self::$attachment_cache[ $attachment_id ] = $data;

		return $data;
	}

	/**
	 * Fix gallery data order to match HTML display order when randomOrder is enabled
	 *
	 * CRITICAL: WordPress Core shuffles gallery HTML AFTER our filter runs.
	 * When randomOrder is enabled, the HTML display order doesn't match the
	 * gallery_data array order. This causes clicking an image to open the wrong
	 * image in the modal.
	 *
	 * This method:
	 * 1. Extracts the actual display order of image IDs from the shuffled HTML
	 * 2. Reorders the gallery_data['images'] array to match the HTML display order
	 * 3. Updates the data-gallery-data attribute with the correctly ordered array
	 *
	 * @param string $html_content The gallery HTML content (already shuffled by Core).
	 * @param array  $gallery_data  The gallery data array (in original order).
	 * @return string Updated HTML with correctly ordered gallery data attribute.
	 */
	private function fix_random_order_gallery_data( $html_content, $gallery_data ) {
		// Extract image IDs in their actual display order from HTML.
		// Pattern matches figure elements with data-image-id attributes we added.
		$display_order = array();
		if ( preg_match_all( '/<figure[^>]*\bdata-image-id="(\d+)"[^>]*>/', $html_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$image_id = intval( $match[1] );
				if ( $image_id > 0 ) {
					$display_order[] = $image_id;
				}
			}
		}

		// If we couldn't extract order from HTML, return original (shouldn't happen).
		if ( empty( $display_order ) || count( $display_order ) !== count( $gallery_data['images'] ) ) {
			return $html_content;
		}

		// Create a lookup map: image_id => image_data for fast access.
		$image_map = array();
		foreach ( $gallery_data['images'] as $image ) {
			if ( ! empty( $image['id'] ) && $image['id'] > 0 ) {
				$image_map[ $image['id'] ] = $image;
			}
		}

		// Reorder gallery_data['images'] array to match HTML display order.
		$reordered_images = array();
		foreach ( $display_order as $image_id ) {
			if ( isset( $image_map[ $image_id ] ) ) {
				$reordered_images[] = $image_map[ $image_id ];
			}
		}

		// If reordering failed (missing images), return original to prevent breakage.
		if ( count( $reordered_images ) !== count( $gallery_data['images'] ) ) {
			return $html_content;
		}

		// Update gallery_data with reordered images.
		$gallery_data['images'] = $reordered_images;

		// Update the data-gallery-data attribute in HTML with reordered array.
		$processed_content = $this->find_gallery_container( $html_content );
		if ( $processed_content ) {
			$processed_content->set_attribute( 'data-gallery-data', wp_json_encode( $gallery_data ) );
			$html_content = $processed_content->get_updated_html();
		}

		return $html_content;
	}
}
