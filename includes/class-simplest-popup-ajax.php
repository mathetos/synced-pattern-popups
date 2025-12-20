<?php
/**
 * AJAX Handler
 * Handles AJAX requests for synced pattern content with nonce verification
 *
 * @package Simplest_Popup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simplest_Popup_Ajax {

	/**
	 * Pattern service
	 *
	 * @var Simplest_Popup_Pattern
	 */
	private $pattern_service;

	/**
	 * Cache service
	 *
	 * @var Simplest_Popup_Cache
	 */
	private $cache_service;

	/**
	 * Style collector instance
	 *
	 * @var Simplest_Popup_Style_Collector|null
	 */
	private $style_collector;

	/**
	 * Constructor
	 *
	 * @param Simplest_Popup_Pattern              $pattern_service Pattern service instance
	 * @param Simplest_Popup_Cache                $cache_service   Cache service instance
	 * @param Simplest_Popup_Style_Collector|null $style_collector Optional style collector instance
	 */
	public function __construct( Simplest_Popup_Pattern $pattern_service, Simplest_Popup_Cache $cache_service, $style_collector = null ) {
		$this->pattern_service = $pattern_service;
		$this->cache_service = $cache_service;
		$this->style_collector = $style_collector;
	}

	/**
	 * Initialize AJAX hooks
	 */
	public function init() {
		add_action( 'wp_ajax_simplest_popup_get_block', array( $this, 'handle_request' ) );
		add_action( 'wp_ajax_nopriv_simplest_popup_get_block', array( $this, 'handle_request' ) );
	}

	/**
	 * Handle AJAX request
	 */
	public function handle_request() {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'simplest_popup_ajax' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token. Please refresh the page and try again.' ) );
			return;
		}

		// Get and validate pattern ID
		$pattern_id = isset( $_POST['block_id'] ) ? sanitize_text_field( $_POST['block_id'] ) : '';

		if ( empty( $pattern_id ) ) {
			wp_send_json_error( array( 'message' => 'Pattern ID is required.' ) );
			return;
		}

		// Validate numeric ID only
		if ( ! is_numeric( $pattern_id ) || $pattern_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid pattern ID. Only numeric IDs are allowed.' ) );
			return;
		}

		$pattern_id = (int) $pattern_id;

		// Verify pattern is synced
		if ( ! $this->pattern_service->is_synced_pattern( $pattern_id ) ) {
			wp_send_json_error( array( 'message' => 'Only synced patterns can be used for popups.' ) );
			return;
		}

		// Get pattern title for accessibility
		$pattern_title = get_the_title( $pattern_id );
		if ( empty( $pattern_title ) ) {
			$pattern_title = __( 'Popup', 'simplest-popup' );
		}

		// Check cache first
		$cached_data = $this->cache_service->get( $pattern_id );
		if ( $cached_data !== false ) {
			// Handle both old (string) and new (array) cache formats
			if ( is_array( $cached_data ) ) {
				$cached_html = isset( $cached_data['html'] ) ? $cached_data['html'] : '';
				$cached_styles = isset( $cached_data['styles'] ) ? $cached_data['styles'] : array();
				$cached_block_supports_css = isset( $cached_data['block_supports_css'] ) ? $cached_data['block_supports_css'] : '';
				$cached_block_style_variation_css = isset( $cached_data['block_style_variation_css'] ) ? $cached_data['block_style_variation_css'] : '';
				$cached_global_stylesheet = isset( $cached_data['global_stylesheet'] ) ? $cached_data['global_stylesheet'] : '';
			} else {
				// Backward compatibility: old cache format (string only)
				$cached_html = $cached_data;
				$cached_styles = array();
				$cached_block_supports_css = '';
				$cached_block_style_variation_css = '';
				$cached_global_stylesheet = '';
			}

			if ( ! empty( $cached_html ) ) {
				wp_send_json_success( array(
					'html'                      => $cached_html,
					'title'                     => $pattern_title,
					'styles'                    => $cached_styles,
					'block_supports_css'        => $cached_block_supports_css,
					'block_style_variation_css' => $cached_block_style_variation_css,
					'global_stylesheet'          => $cached_global_stylesheet,
					'cached'                    => true,
				) );
				return;
			}
		}

		// Create style collector if not provided
		$style_collector = $this->style_collector;
		if ( ! $style_collector instanceof Simplest_Popup_Style_Collector ) {
			$style_collector = new Simplest_Popup_Style_Collector();
		}

		// Get and render pattern content with style collection
		$rendered_data = $this->pattern_service->get_rendered_content( $pattern_id, $style_collector );

		if ( $rendered_data === false ) {
			wp_send_json_error( array( 'message' => 'Synced pattern not found or is empty.' ) );
			return;
		}

		// Handle both old (string) and new (array) return formats
		if ( is_array( $rendered_data ) ) {
			$rendered_html = isset( $rendered_data['html'] ) ? $rendered_data['html'] : '';
			$rendered_styles = isset( $rendered_data['styles'] ) ? $rendered_data['styles'] : array();
			$rendered_block_supports_css = isset( $rendered_data['block_supports_css'] ) ? $rendered_data['block_supports_css'] : '';
			$rendered_block_style_variation_css = isset( $rendered_data['block_style_variation_css'] ) ? $rendered_data['block_style_variation_css'] : '';
			$rendered_global_stylesheet = isset( $rendered_data['global_stylesheet'] ) ? $rendered_data['global_stylesheet'] : '';
		} else {
			// Backward compatibility: old return format (string only)
			$rendered_html = $rendered_data;
			$rendered_styles = array();
			$rendered_block_supports_css = '';
			$rendered_block_style_variation_css = '';
			$rendered_global_stylesheet = '';
		}

		if ( empty( $rendered_html ) ) {
			wp_send_json_error( array( 'message' => 'Synced pattern not found or is empty.' ) );
			return;
		}

		// Cache both HTML, styles, block support CSS, block style variation CSS, and global stylesheet
		$this->cache_service->set( $pattern_id, array(
			'html'                      => $rendered_html,
			'styles'                    => $rendered_styles,
			'block_supports_css'        => $rendered_block_supports_css,
			'block_style_variation_css' => $rendered_block_style_variation_css,
			'global_stylesheet'          => $rendered_global_stylesheet,
		) );

		// Return success with HTML, title, styles, block support CSS, block style variation CSS, and global stylesheet
		wp_send_json_success( array(
			'html'                      => $rendered_html,
			'title'                     => $pattern_title,
			'styles'                    => $rendered_styles,
			'block_supports_css'        => $rendered_block_supports_css,
			'block_style_variation_css' => $rendered_block_style_variation_css,
			'global_stylesheet'          => $rendered_global_stylesheet,
			'cached'                    => false,
		) );
	}
}

