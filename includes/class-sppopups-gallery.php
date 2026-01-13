<?php
/**
 * Gallery Block Integration
 * Extends WordPress Core Gallery block with SPPopups modal support
 *
 * @package SPPopups
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	const PATTERN_FIGCAPTION = '/<figcaption[^>]*>(.*?)<\/figcaption>/is';
	const PATTERN_P_CAPTION = '/<p[^>]*class="[^"]*caption[^"]*"[^>]*>(.*?)<\/p>/is';

	/**
	 * Initialize gallery integration
	 */
	public function init() {
		// Enqueue editor assets
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );

		// Filter gallery block render to add data attributes
		add_filter( 'render_block', array( $this, 'filter_gallery_block_render' ), 10, 2 );
	}

	/**
	 * Enqueue editor assets for Gallery block extension
	 */
	public function enqueue_editor_assets() {
		// Only enqueue in block editor
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
		// Only process Gallery blocks
		if ( 'core/gallery' !== $block['blockName'] ) {
			return $block_content;
		}

		// Only process if linkTo is 'sppopup'
		if ( empty( $block['attrs']['linkTo'] ) || 'sppopup' !== $block['attrs']['linkTo'] ) {
			return $block_content;
		}

		// Extract gallery data from block attributes
		$gallery_data = $this->extract_gallery_data( $block );

		if ( empty( $gallery_data['images'] ) ) {
			return $block_content;
		}

		// Generate unique gallery ID
		$gallery_id = wp_unique_id( 'sppopup-gallery-' );

		// Extract and validate settings
		$settings = $this->extract_gallery_settings( $block );
		$gallery_data['modalSize'] = $settings['modalSize'];
		$gallery_data['closeButtons'] = $settings['closeButtons'];
		$gallery_data['imageNavigation'] = $settings['imageNavigation'];

		// Extract captions from HTML and update gallery data
		$html_content = $this->extract_captions_from_html( $block_content, $gallery_data );

		// Find gallery container and add data attributes
		$processed_content = $this->find_gallery_container( $html_content );
		
		// Add data attributes to gallery container
		if ( $processed_content ) {
			$processed_content->set_attribute( 'data-sppopup-gallery', 'true' );
			$processed_content->set_attribute( 'data-gallery-id', $gallery_id );
			$processed_content->set_attribute( 'data-gallery-data', wp_json_encode( $gallery_data ) );
			$processed_content->set_attribute( 'data-modal-size', $settings['modalSize'] );
			
			$html_content = $processed_content->get_updated_html();
		}
		
		// Build a map of image IDs to image data for efficient lookup (handles random order)
		$image_id_map = array();
		foreach ( $gallery_data['images'] as $index => $image_data ) {
			if ( ! empty( $image_data['id'] ) && $image_data['id'] > 0 ) {
				$image_id_map[ $image_data['id'] ] = $image_data;
			}
		}

		// Use regex to find and modify image links/figures
		// Pattern matches figure elements containing images
		$html_content = preg_replace_callback(
			self::PATTERN_FIGURE_IMAGE,
			function ( $matches ) use ( $gallery_data, $image_id_map ) {
				$figure_attrs = $matches[1];
				$figure_content = $matches[2];

				// Extract image ID from figure's img element (Core adds data-id attribute)
				// This works regardless of whether Core has randomized the order
				$figure_image_id = 0;
				if ( preg_match( '/<img[^>]*\bdata-id=["\']?(\d+)["\']?/i', $figure_content, $img_matches ) ) {
					$figure_image_id = intval( $img_matches[1] );
				}

				// Match figure to gallery data by image ID (most reliable, handles random order)
				$image_data = null;
				if ( $figure_image_id > 0 && isset( $image_id_map[ $figure_image_id ] ) ) {
					$image_data = $image_id_map[ $figure_image_id ];
				} else {
					// Fallback: Try to match by image src URL if ID not found
					if ( preg_match( '/<img[^>]*\bsrc=["\']([^"\']+)["\']/i', $figure_content, $src_matches ) ) {
						$figure_src = esc_url_raw( $src_matches[1] );
						foreach ( $gallery_data['images'] as $img_data ) {
							$gallery_url = isset( $img_data['fullUrl'] ) ? $img_data['fullUrl'] : ( isset( $img_data['url'] ) ? $img_data['url'] : '' );
							if ( $gallery_url && $figure_src === $gallery_url ) {
								$image_data = $img_data;
								break;
							}
						}
					}
				}

				if ( ! $image_data ) {
					// Could not match figure to gallery data, return unchanged
					return $matches[0];
				}

				// Try to extract caption from figure content if not already in image_data
				if ( empty( $image_data['caption'] ) ) {
					$image_data['caption'] = $this->extract_caption_from_content( $figure_content );
				}

				// Add data attributes to figure (invisible - no structural changes)
				// JavaScript will handle clicks on figure, image, or any links within
				// Note: We use image ID instead of index to handle randomized order correctly
				if ( ! empty( $image_data['id'] ) && $image_data['id'] > 0 ) {
					$figure_attrs .= ' data-image-id="' . esc_attr( $image_data['id'] ) . '"';
				}
				// Find index in gallery data for backward compatibility (find by ID)
				$image_index = false;
				if ( ! empty( $image_data['id'] ) ) {
					foreach ( $gallery_data['images'] as $idx => $img ) {
						if ( isset( $img['id'] ) && intval( $img['id'] ) === intval( $image_data['id'] ) ) {
							$image_index = $idx;
							break;
						}
					}
				}
				if ( $image_index !== false ) {
					$figure_attrs .= ' data-image-index="' . esc_attr( $image_index ) . '"'; // Keep for backward compatibility
				}
				$figure_attrs .= ' data-image-data="' . esc_attr( wp_json_encode( $image_data ) ) . '"';
				
				// Don't modify links or add links - preserve Core's exact HTML structure
				// JavaScript will intercept clicks via event delegation and match by image ID/URL

				return '<figure' . $figure_attrs . '>' . $figure_content . '</figure>';
			},
			$html_content
		);

		return $html_content;
	}

	/**
	 * Extract gallery data from block attributes
	 *
	 * @param array $block Block data
	 * @return array Gallery data structure
	 */
	private function extract_gallery_data( $block ) {
		$gallery_data = array(
			'images' => array(),
			'galleryId' => '',
		);

		// Get images from block attributes
		$images = isset( $block['attrs']['images'] ) ? $block['attrs']['images'] : array();

		if ( empty( $images ) ) {
			// Try to get from innerBlocks (newer Gallery block format)
			if ( ! empty( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as $index => $inner_block ) {
					if ( 'core/image' === $inner_block['blockName'] ) {
						$image_data = $this->extract_image_data_from_block( $inner_block );
						if ( $image_data ) {
							$image_data['index'] = $index;
							$gallery_data['images'][] = $image_data;
						}
					}
				}
			}

			// Fallback: try to get from ids attribute
			if ( empty( $gallery_data['images'] ) && ! empty( $block['attrs']['ids'] ) && is_array( $block['attrs']['ids'] ) ) {
				foreach ( $block['attrs']['ids'] as $index => $image_id ) {
					$image_id = intval( $image_id );
					$attachment_data = $this->get_attachment_data( $image_id );
					if ( $attachment_data ) {
						$attachment_data['index'] = $index;
						$gallery_data['images'][] = $attachment_data;
					}
				}
			}
		} else {
			// Process images array
			foreach ( $images as $index => $image ) {
				// Extract ID - handle both string and numeric formats from block attributes
				$image_id = 0;
				if ( isset( $image['id'] ) ) {
					// ID can come as string from data-id attribute or as number
					$image_id = is_numeric( $image['id'] ) ? intval( $image['id'] ) : 0;
				}
				
				$image_data = array(
					'id' => $image_id,
					'fullUrl' => isset( $image['fullUrl'] ) ? esc_url_raw( $image['fullUrl'] ) : ( isset( $image['url'] ) ? esc_url_raw( $image['url'] ) : '' ),
					'caption' => isset( $image['caption'] ) ? wp_kses_post( $image['caption'] ) : '',
					'alt' => isset( $image['alt'] ) ? esc_attr( $image['alt'] ) : '',
					'index' => $index,
				);

				// If caption or alt is empty, try to get from attachment using cached method
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
	 * @param array $image_block Image block data
	 * @return array|null Image data or null
	 */
	private function extract_image_data_from_block( $image_block ) {
		$attrs = isset( $image_block['attrs'] ) ? $image_block['attrs'] : array();

		$image_id = isset( $attrs['id'] ) ? intval( $attrs['id'] ) : 0;
		if ( ! $image_id ) {
			return null;
		}

		// Get attachment data using cached method
		$attachment_data = $this->get_attachment_data( $image_id );
		if ( ! $attachment_data ) {
			return null;
		}

		// Get caption - try multiple sources (prioritize block attributes)
		$caption = '';
		
		// First try from block attributes
		if ( isset( $attrs['caption'] ) && ! empty( $attrs['caption'] ) ) {
			$caption = $attrs['caption'];
		}
		
		// If not in attrs, try to extract from innerHTML (for Gallery blocks)
		if ( empty( $caption ) && isset( $image_block['innerHTML'] ) ) {
			$caption = $this->extract_caption_from_content( $image_block['innerHTML'] );
		}
		
		// Fallback to cached attachment caption
		if ( empty( $caption ) ) {
			$caption = $attachment_data['caption'];
		}

		// Get alt text (prioritize block attributes)
		$alt = isset( $attrs['alt'] ) ? $attrs['alt'] : '';
		if ( empty( $alt ) ) {
			$alt = $attachment_data['alt'];
		}

		return array(
			'id' => $image_id,
			'fullUrl' => $attachment_data['fullUrl'],
			'caption' => wp_kses_post( $caption ),
			'alt' => esc_attr( $alt ),
			'index' => 0, // Will be set correctly during processing
		);
	}

	/**
	 * Extract and validate gallery settings from block attributes
	 *
	 * @param array $block Block data
	 * @return array Settings array
	 */
	private function extract_gallery_settings( $block ) {
		$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();

		// Modal size (default 600px, minimum 100px)
		$modal_size = isset( $attrs['sppopupModalSize'] ) ? intval( $attrs['sppopupModalSize'] ) : 600;
		$modal_size = max( 100, $modal_size );

		// Close buttons (default 'both')
		$close_buttons = isset( $attrs['sppopupCloseButtons'] ) ? $attrs['sppopupCloseButtons'] : 'both';
		if ( ! in_array( $close_buttons, array( 'icon', 'button', 'both' ), true ) ) {
			$close_buttons = 'both';
		}

		// Image navigation (default 'both')
		$image_navigation = isset( $attrs['sppopupImageNavigation'] ) ? $attrs['sppopupImageNavigation'] : 'both';
		if ( ! in_array( $image_navigation, array( 'image', 'footer', 'both' ), true ) ) {
			$image_navigation = 'both';
		}

		return array(
			'modalSize' => $modal_size,
			'closeButtons' => $close_buttons,
			'imageNavigation' => $image_navigation,
		);
	}

	/**
	 * Find gallery container in HTML content
	 *
	 * @param string $html_content HTML content
	 * @return WP_HTML_Tag_Processor Processor positioned at gallery container
	 */
	private function find_gallery_container( $html_content ) {
		$processed_content = new WP_HTML_Tag_Processor( $html_content );

		// Try wp-block-gallery class first
		if ( $processed_content->next_tag( array( 'class_name' => 'wp-block-gallery' ) ) ) {
			return $processed_content;
		}

		// Try figure tag with gallery classes
		$processed_content = new WP_HTML_Tag_Processor( $html_content );
		while ( $processed_content->next_tag( 'figure' ) ) {
			$class = $processed_content->get_attribute( 'class' );
			if ( $class && ( strpos( $class, 'wp-block-gallery' ) !== false || strpos( $class, 'blocks-gallery-grid' ) !== false ) ) {
				return $processed_content;
			}
		}

		// Fallback: first tag
		$processed_content = new WP_HTML_Tag_Processor( $html_content );
		$processed_content->next_tag();
		return $processed_content;
	}

	/**
	 * Extract captions from HTML content and update gallery data
	 *
	 * @param string $html_content HTML content
	 * @param array  $gallery_data Gallery data (passed by reference)
	 * @return string Updated HTML content
	 */
	private function extract_captions_from_html( $html_content, &$gallery_data ) {
		$image_index = 0;

		return preg_replace_callback(
			self::PATTERN_FIGURE_IMAGE,
			function ( $matches ) use ( &$image_index, &$gallery_data ) {
				$figure_content = $matches[2];

				// Update caption if missing and found in HTML
				if ( isset( $gallery_data['images'][ $image_index ] ) && empty( $gallery_data['images'][ $image_index ]['caption'] ) ) {
					$caption = $this->extract_caption_from_content( $figure_content );
					if ( ! empty( $caption ) ) {
						$gallery_data['images'][ $image_index ]['caption'] = $caption;
					}
				}

				$image_index++;
				return $matches[0]; // Return unchanged
			},
			$html_content
		);
	}

	/**
	 * Extract caption from HTML content
	 *
	 * @param string $content HTML content
	 * @return string Caption text (empty if not found)
	 */
	private function extract_caption_from_content( $content ) {
		// Try figcaption first
		if ( preg_match( self::PATTERN_FIGCAPTION, $content, $matches ) ) {
			$caption = wp_kses_post( trim( $matches[1] ) );
			if ( ! empty( $caption ) ) {
				return $caption;
			}
		}

		// Try p.caption
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
	 * @param int $attachment_id Attachment ID
	 * @return array|false Attachment data or false if not found
	 */
	private function get_attachment_data( $attachment_id ) {
		if ( ! $attachment_id || $attachment_id <= 0 ) {
			return false;
		}

		// Check cache first
		if ( isset( self::$attachment_cache[ $attachment_id ] ) ) {
			return self::$attachment_cache[ $attachment_id ];
		}

		// Fetch data
		$full_url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! $full_url ) {
			self::$attachment_cache[ $attachment_id ] = false;
			return false;
		}

		$caption = wp_get_attachment_caption( $attachment_id );
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		$data = array(
			'id' => $attachment_id,
			'fullUrl' => esc_url_raw( $full_url ),
			'caption' => wp_kses_post( $caption ),
			'alt' => esc_attr( $alt ),
		);

		// Cache it
		self::$attachment_cache[ $attachment_id ] = $data;

		return $data;
	}
}
