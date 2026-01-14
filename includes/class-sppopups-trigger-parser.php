<?php
/**
 * Trigger Parser Helper
 * Parses HTML content to extract popup trigger information
 * Shared utility for both frontend JavaScript and backend PHP (abilities)
 *
 * @package SPPopups
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * SPPopups_Trigger_Parser class.
 *
 * @package SPPopups
 */
class SPPopups_Trigger_Parser {

	/**
	 * Scan HTML content for popup triggers
	 * Extracts both class-based and href-based triggers
	 *
	 * @param string $html HTML content to scan.
	 * @return array Array of trigger objects with type, id, and optional max_width
	 */
	public function scan_html( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return array();
		}

		$triggers  = array();
		$found_ids = array(); // Track unique triggers to avoid duplicates.

		// Scan for TLDR trigger first.
		if ( preg_match( '/\bspp-trigger-tldr\b/', $html ) || preg_match( '/#spp-trigger-tldr/', $html ) ) {
			$triggers[] = array(
				'type' => 'tldr',
				'id'   => 'tldr',
			);
		}

		// Scan for class-based triggers: spp-trigger-{id} or spp-trigger-{id}-{width}.
		$class_pattern = '/\bspp-trigger-(\d+)(?:-(\d+))?\b/';
		if ( preg_match_all( $class_pattern, $html, $class_matches, PREG_SET_ORDER ) ) {
			foreach ( $class_matches as $match ) {
				$id        = isset( $match[1] ) ? (int) $match[1] : 0;
				$max_width = isset( $match[2] ) ? (int) $match[2] : null;

				// Validate ID range.
				if ( $id > 0 && $id <= 2147483647 ) {
					// Validate max-width range.
					if ( null !== $max_width ) {
						if ( $max_width < 100 || $max_width > 5000 ) {
							$max_width = null; // Ignore invalid max-width.
						}
					}

					// Create unique key to avoid duplicates.
					$key = 'class-' . $id . '-' . ( null !== $max_width ? $max_width : 'default' );
					if ( ! isset( $found_ids[ $key ] ) ) {
						$found_ids[ $key ] = true;
						$trigger           = array(
							'type' => 'class',
							'id'   => $id,
						);
						if ( null !== $max_width ) {
							$trigger['max_width'] = $max_width;
						}
						$triggers[] = $trigger;
					}
				}
			}
		}

		// Scan for href-based triggers: #spp-trigger-{id} or #spp-trigger-{id}-{width}.
		$href_pattern = '/#spp-trigger-(\d+)(?:-(\d+))?/';
		if ( preg_match_all( $href_pattern, $html, $href_matches, PREG_SET_ORDER ) ) {
			foreach ( $href_matches as $match ) {
				$id        = isset( $match[1] ) ? (int) $match[1] : 0;
				$max_width = isset( $match[2] ) ? (int) $match[2] : null;

				// Validate ID range.
				if ( $id > 0 && $id <= 2147483647 ) {
					// Validate max-width range.
					if ( null !== $max_width ) {
						if ( $max_width < 100 || $max_width > 5000 ) {
							$max_width = null; // Ignore invalid max-width.
						}
					}

					// Create unique key to avoid duplicates.
					$key = 'href-' . $id . '-' . ( null !== $max_width ? $max_width : 'default' );
					if ( ! isset( $found_ids[ $key ] ) ) {
						$found_ids[ $key ] = true;
						$trigger           = array(
							'type' => 'href',
							'id'   => $id,
						);
						if ( null !== $max_width ) {
							$trigger['max_width'] = $max_width;
						}
						$triggers[] = $trigger;
					}
				}
			}
		}

		return $triggers;
	}
}
