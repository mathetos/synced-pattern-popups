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
	 * Extract rendered data from cache or render result
	 * Handles both old (string) and new (array) formats
	 *
	 * @param mixed $data Cached data or rendered data (string or array)
	 * @return array Extracted data with all components
	 */
	private function extract_rendered_data( $data ) {
		$result = array(
			'html'                      => '',
			'styles'                    => array(),
			'block_supports_css'        => '',
			'block_style_variation_css' => '',
			'global_stylesheet'          => '',
			'asset_data'                => Simplest_Popup_Cache::get_default_asset_data(),
		);

		if ( is_array( $data ) ) {
			$result['html']                      = isset( $data['html'] ) ? $data['html'] : '';
			$result['styles']                    = isset( $data['styles'] ) ? $data['styles'] : array();
			$result['block_supports_css']        = isset( $data['block_supports_css'] ) ? $data['block_supports_css'] : '';
			$result['block_style_variation_css'] = isset( $data['block_style_variation_css'] ) ? $data['block_style_variation_css'] : '';
			$result['global_stylesheet']          = isset( $data['global_stylesheet'] ) ? $data['global_stylesheet'] : '';
			$result['asset_data']                = isset( $data['asset_data'] ) && is_array( $data['asset_data'] ) ? $data['asset_data'] : Simplest_Popup_Cache::get_default_asset_data();
		} else {
			// Backward compatibility: old format (string only)
			$result['html'] = $data;
		}

		return $result;
	}

	/**
	 * Check rate limit for AJAX requests
	 * Prevents DoS attacks and brute force enumeration
	 *
	 * @return bool True if within rate limit, false if exceeded
	 */
	private function check_rate_limit() {
		// Get client IP address
		$ip = $this->get_client_ip();
		if ( ! $ip ) {
			// If we can't determine IP, allow request but log warning
			return true;
		}

		// Rate limit: 60 requests per minute per IP (configurable via filter)
		$max_requests = apply_filters( 'simplest_popup_rate_limit_requests', 60 );
		$time_window = apply_filters( 'simplest_popup_rate_limit_window', 60 ); // seconds

		$transient_key = 'simplest_popup_rate_limit_' . md5( $ip );
		$requests = get_transient( $transient_key );

		if ( false === $requests ) {
			// First request in this time window
			set_transient( $transient_key, 1, $time_window );
			return true;
		}

		if ( $requests >= $max_requests ) {
			// Rate limit exceeded
			return false;
		}

		// Increment request count
		set_transient( $transient_key, $requests + 1, $time_window );
		return true;
	}

	/**
	 * Get client IP address
	 * Respects proxy headers but prioritizes security
	 *
	 * @return string|false Client IP address or false if unable to determine
	 */
	private function get_client_ip() {
		// Check for IP in various headers (in order of preference)
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Standard proxy header
			'REMOTE_ADDR',           // Direct connection
		);

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated IPs (X-Forwarded-For can contain multiple)
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip = trim( $ips[0] );
				}
				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				} elseif ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					// Allow private/reserved ranges for local development
					return $ip;
				}
			}
		}

		return false;
	}

	/**
	 * Handle AJAX request
	 */
	public function handle_request() {
		// Check rate limit first (before any processing)
		if ( ! $this->check_rate_limit() ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'simplest-popup' ) ) );
			return;
		}

		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'simplest_popup_ajax' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token. Please refresh the page and try again.', 'simplest-popup' ) ) );
			return;
		}

		// Get and validate pattern ID
		$pattern_id = isset( $_POST['block_id'] ) ? sanitize_text_field( wp_unslash( $_POST['block_id'] ) ) : '';

		if ( empty( $pattern_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'simplest-popup' ) ) );
			return;
		}

		// Validate numeric ID only
		if ( ! is_numeric( $pattern_id ) || $pattern_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'simplest-popup' ) ) );
			return;
		}

		$pattern_id = (int) $pattern_id;

		// Validate pattern ID range (prevent extremely large IDs that could cause memory issues)
		// WordPress post IDs are typically well below this, but set a reasonable upper bound
		$max_pattern_id = apply_filters( 'simplest_popup_max_pattern_id', 2147483647 ); // Max 32-bit integer
		if ( $pattern_id > $max_pattern_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'simplest-popup' ) ) );
			return;
		}

		// Verify pattern is synced
		if ( ! $this->pattern_service->is_synced_pattern( $pattern_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'simplest-popup' ) ) );
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
			$extracted = $this->extract_rendered_data( $cached_data );

			if ( ! empty( $extracted['html'] ) ) {
				wp_send_json_success( array(
					'html'                      => $extracted['html'],
					'title'                     => $pattern_title,
					'styles'                    => $extracted['styles'],
					'block_supports_css'        => $extracted['block_supports_css'],
					'block_style_variation_css' => $extracted['block_style_variation_css'],
					'global_stylesheet'          => $extracted['global_stylesheet'],
					'asset_data'                => $extracted['asset_data'],
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
			wp_send_json_error( array( 'message' => __( 'Content not available.', 'simplest-popup' ) ) );
			return;
		}

		// Extract rendered data
		$extracted = $this->extract_rendered_data( $rendered_data );

		if ( empty( $extracted['html'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Content not available.', 'simplest-popup' ) ) );
			return;
		}

		// Cache both HTML, styles, block support CSS, block style variation CSS, global stylesheet, and asset data
		$this->cache_service->set( $pattern_id, array(
			'html'                      => $extracted['html'],
			'styles'                    => $extracted['styles'],
			'block_supports_css'        => $extracted['block_supports_css'],
			'block_style_variation_css' => $extracted['block_style_variation_css'],
			'global_stylesheet'          => $extracted['global_stylesheet'],
			'asset_data'                => $extracted['asset_data'],
		) );

		// Return success with HTML, title, styles, block support CSS, block style variation CSS, global stylesheet, and asset data
		wp_send_json_success( array(
			'html'                      => $extracted['html'],
			'title'                     => $pattern_title,
			'styles'                    => $extracted['styles'],
			'block_supports_css'        => $extracted['block_supports_css'],
			'block_style_variation_css' => $extracted['block_style_variation_css'],
			'global_stylesheet'          => $extracted['global_stylesheet'],
			'asset_data'                => $extracted['asset_data'],
			'cached'                    => false,
		) );
	}
}

