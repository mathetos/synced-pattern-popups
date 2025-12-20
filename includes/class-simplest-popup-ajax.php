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
	 * Constructor
	 *
	 * @param Simplest_Popup_Pattern $pattern_service Pattern service instance
	 * @param Simplest_Popup_Cache   $cache_service   Cache service instance
	 */
	public function __construct( Simplest_Popup_Pattern $pattern_service, Simplest_Popup_Cache $cache_service ) {
		$this->pattern_service = $pattern_service;
		$this->cache_service = $cache_service;
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
		$cached_html = $this->cache_service->get( $pattern_id );
		if ( $cached_html !== false ) {
			wp_send_json_success( array( 'html' => $cached_html, 'title' => $pattern_title, 'cached' => true ) );
			return;
		}

		// Get and render pattern content
		$rendered_html = $this->pattern_service->get_rendered_content( $pattern_id );

		if ( $rendered_html === false || empty( $rendered_html ) ) {
			wp_send_json_error( array( 'message' => 'Synced pattern not found or is empty.' ) );
			return;
		}

		// Cache the rendered HTML
		$this->cache_service->set( $pattern_id, $rendered_html );

		// Return success with HTML and title
		wp_send_json_success( array( 'html' => $rendered_html, 'title' => $pattern_title, 'cached' => false ) );
	}
}

