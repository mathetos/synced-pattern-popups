<?php
/**
 * Unit tests for SPPopups_Settings class
 *
 * Tests sanitization functions and inheritance logic.
 *
 * @package SPPopups
 */

/**
 * Test class for settings unit tests
 */
class SPPopups_Settings_Test extends WP_UnitTestCase {

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		parent::tearDown();
		// Clean up options
		delete_option( 'sppopups_defaults_pattern' );
		delete_option( 'sppopups_defaults_tldr' );
		delete_option( 'sppopups_defaults_gallery' );
	}

	/**
	 * Test sanitize_pattern_defaults with valid values
	 */
	public function test_sanitize_pattern_defaults_valid_values() {
		$settings = new SPPopups_Settings();

		$valid = array(
			'maxWidth'        => 1200,
			'borderRadius'    => 12,
			'maxHeight'       => 95,
			'overlayColor'    => 'rgba(255, 0, 0, 0.5)',
			'backdropBlur'    => 10,
			'showIconClose'   => false,
			'showFooterClose' => false,
			'footerCloseText' => 'Close',
		);

		$sanitized = $settings->sanitize_pattern_defaults( $valid );

		$this->assertEquals( 1200, $sanitized['maxWidth'] );
		$this->assertEquals( 12, $sanitized['borderRadius'] );
		$this->assertEquals( 95, $sanitized['maxHeight'] );
		$this->assertEquals( 'rgba(255, 0, 0, 0.5)', $sanitized['overlayColor'] );
		$this->assertEquals( 10, $sanitized['backdropBlur'] );
		$this->assertFalse( $sanitized['showIconClose'] );
		$this->assertFalse( $sanitized['showFooterClose'] );
		$this->assertEquals( 'Close', $sanitized['footerCloseText'] );
	}

	/**
	 * Test sanitize_pattern_defaults with invalid maxWidth
	 */
	public function test_sanitize_pattern_defaults_invalid_maxwidth() {
		$settings = new SPPopups_Settings();

		// Test out of range high value
		$invalid_high = array( 'maxWidth' => 99999 );
		$sanitized     = $settings->sanitize_pattern_defaults( $invalid_high );
		$this->assertEquals( 5000, $sanitized['maxWidth'], 'maxWidth should clamp to 5000' );

		// Test out of range low value
		$invalid_low = array( 'maxWidth' => -5 );
		$sanitized   = $settings->sanitize_pattern_defaults( $invalid_low );
		$this->assertEquals( 600, $sanitized['maxWidth'], 'maxWidth should default to 600 when invalid' );
	}

	/**
	 * Test sanitize_pattern_defaults with invalid borderRadius
	 */
	public function test_sanitize_pattern_defaults_invalid_borderradius() {
		$settings = new SPPopups_Settings();

		// Test out of range high value
		$invalid_high = array( 'borderRadius' => 100 );
		$sanitized     = $settings->sanitize_pattern_defaults( $invalid_high );
		$this->assertEquals( 6, $sanitized['borderRadius'], 'borderRadius should default when out of range' );

		// Test negative value
		$invalid_low = array( 'borderRadius' => -1 );
		$sanitized   = $settings->sanitize_pattern_defaults( $invalid_low );
		$this->assertEquals( 6, $sanitized['borderRadius'], 'borderRadius should default when negative' );
	}

	/**
	 * Test sanitize_pattern_defaults with invalid maxHeight
	 */
	public function test_sanitize_pattern_defaults_invalid_maxheight() {
		$settings = new SPPopups_Settings();

		// Test out of range high value
		$invalid_high = array( 'maxHeight' => 200 );
		$sanitized     = $settings->sanitize_pattern_defaults( $invalid_high );
		$this->assertEquals( 100, $sanitized['maxHeight'], 'maxHeight should clamp to 100' );

		// Test out of range low value
		$invalid_low = array( 'maxHeight' => 10 );
		$sanitized   = $settings->sanitize_pattern_defaults( $invalid_low );
		$this->assertEquals( 90, $sanitized['maxHeight'], 'maxHeight should default when below 50' );
	}

	/**
	 * Test sanitize_pattern_defaults with invalid overlayColor
	 */
	public function test_sanitize_pattern_defaults_invalid_overlaycolor() {
		$settings = new SPPopups_Settings();

		// Test invalid format
		$invalid = array( 'overlayColor' => 'not-a-color' );
		$sanitized = $settings->sanitize_pattern_defaults( $invalid );
		$this->assertEquals( 'rgba(0, 0, 0, 0.1)', $sanitized['overlayColor'], 'overlayColor should default when invalid' );

		// Test invalid rgba format
		$invalid_rgba = array( 'overlayColor' => 'rgba(999, 999, 999, 2)' );
		$sanitized    = $settings->sanitize_pattern_defaults( $invalid_rgba );
		$this->assertEquals( 'rgba(0, 0, 0, 0.1)', $sanitized['overlayColor'], 'overlayColor should default when rgba values invalid' );
	}

	/**
	 * Test sanitize_pattern_defaults with invalid backdropBlur
	 */
	public function test_sanitize_pattern_defaults_invalid_backdropblur() {
		$settings = new SPPopups_Settings();

		// Test out of range high value
		$invalid_high = array( 'backdropBlur' => 50 );
		$sanitized     = $settings->sanitize_pattern_defaults( $invalid_high );
		$this->assertEquals( 8, $sanitized['backdropBlur'], 'backdropBlur should default when out of range' );
	}

	/**
	 * Test sanitize_tldr_defaults inheritance flags
	 */
	public function test_sanitize_tldr_defaults_inheritance_flags() {
		$settings = new SPPopups_Settings();

		$with_flags = array(
			'inheritModalAppearance' => false,
			'inheritOverlay'         => false,
			'inheritCloseButtons'    => false,
		);

		$sanitized = $settings->sanitize_tldr_defaults( $with_flags );

		$this->assertFalse( $sanitized['inheritModalAppearance'] );
		$this->assertFalse( $sanitized['inheritOverlay'] );
		$this->assertFalse( $sanitized['inheritCloseButtons'] );
	}

	/**
	 * Test sanitize_gallery_defaults image navigation
	 */
	public function test_sanitize_gallery_defaults_image_navigation() {
		$settings = new SPPopups_Settings();

		// Test valid values
		$valid = array( 'imageNavigation' => 'image' );
		$sanitized = $settings->sanitize_gallery_defaults( $valid );
		$this->assertEquals( 'image', $sanitized['imageNavigation'] );

		$valid_footer = array( 'imageNavigation' => 'footer' );
		$sanitized_footer = $settings->sanitize_gallery_defaults( $valid_footer );
		$this->assertEquals( 'footer', $sanitized_footer['imageNavigation'] );

		$valid_both = array( 'imageNavigation' => 'both' );
		$sanitized_both = $settings->sanitize_gallery_defaults( $valid_both );
		$this->assertEquals( 'both', $sanitized_both['imageNavigation'] );

		// Test invalid value should default
		$invalid = array( 'imageNavigation' => 'invalid' );
		$sanitized_invalid = $settings->sanitize_gallery_defaults( $invalid );
		$this->assertEquals( 'both', $sanitized_invalid['imageNavigation'], 'Invalid imageNavigation should default to both' );
	}

	/**
	 * Test get_pattern_defaults returns defaults when option is empty
	 */
	public function test_get_pattern_defaults_returns_defaults_when_empty() {
		delete_option( 'sppopups_defaults_pattern' );

		$defaults = SPPopups_Settings::get_pattern_defaults();

		$this->assertNotEmpty( $defaults );
		$this->assertEquals( 600, $defaults['maxWidth'] );
		$this->assertEquals( 6, $defaults['borderRadius'] );
	}

	/**
	 * Test get_pattern_defaults merges saved values correctly
	 */
	public function test_get_pattern_defaults_merges_saved_values() {
		update_option( 'sppopups_defaults_pattern', array(
			'maxWidth' => 1200,
			'borderRadius' => 12,
		) );

		$defaults = SPPopups_Settings::get_pattern_defaults();

		$this->assertEquals( 1200, $defaults['maxWidth'] );
		$this->assertEquals( 12, $defaults['borderRadius'] );
		// Other values should still be defaults
		$this->assertEquals( 90, $defaults['maxHeight'] );
		$this->assertEquals( 'rgba(0, 0, 0, 0.1)', $defaults['overlayColor'] );
	}

	/**
	 * Test get_tldr_defaults inherits from pattern when inheritance enabled
	 */
	public function test_get_tldr_defaults_inherits_from_pattern() {
		// Set custom pattern defaults
		update_option( 'sppopups_defaults_pattern', array(
			'maxWidth' => 1200,
			'borderRadius' => 12,
			'maxHeight' => 95,
		) );

		// Set TLDR to inherit
		update_option( 'sppopups_defaults_tldr', array(
			'inheritModalAppearance' => true,
		) );

		$tldr_defaults = SPPopups_Settings::get_tldr_defaults();

		// Should inherit from pattern
		$this->assertEquals( 1200, $tldr_defaults['maxWidth'] );
		$this->assertEquals( 12, $tldr_defaults['borderRadius'] );
		$this->assertEquals( 95, $tldr_defaults['maxHeight'] );
	}

	/**
	 * Test get_tldr_defaults overrides when not inheriting
	 */
	public function test_get_tldr_defaults_overrides_when_not_inheriting() {
		// Set custom pattern defaults
		update_option( 'sppopups_defaults_pattern', array(
			'maxWidth' => 1200,
		) );

		// Set TLDR to NOT inherit and use custom values
		update_option( 'sppopups_defaults_tldr', array(
			'inheritModalAppearance' => false,
			'maxWidth' => 800,
		) );

		$tldr_defaults = SPPopups_Settings::get_tldr_defaults();

		// Should use custom value, not pattern value
		$this->assertEquals( 800, $tldr_defaults['maxWidth'] );
	}

	/**
	 * Test get_gallery_defaults inheritance for all three groups
	 */
	public function test_get_gallery_defaults_inheritance_all_three_groups() {
		// Set custom pattern defaults
		update_option( 'sppopups_defaults_pattern', array(
			'maxWidth' => 1200,
			'overlayColor' => 'rgba(255, 0, 0, 0.5)',
			'showIconClose' => false,
		) );

		// Set gallery to inherit all
		update_option( 'sppopups_defaults_gallery', array(
			'inheritModalAppearance' => true,
			'inheritOverlay' => true,
			'inheritCloseButtons' => true,
		) );

		$gallery_defaults = SPPopups_Settings::get_gallery_defaults();

		// Should inherit modal appearance
		$this->assertEquals( 1200, $gallery_defaults['maxWidth'] );
		// Should inherit overlay
		$this->assertEquals( 'rgba(255, 0, 0, 0.5)', $gallery_defaults['overlayColor'] );
		// Should inherit close buttons
		$this->assertFalse( $gallery_defaults['showIconClose'] );
	}

	/**
	 * Test get_tldr_defaults inherits overlay from pattern
	 */
	public function test_get_tldr_defaults_inherits_overlay_from_pattern() {
		update_option( 'sppopups_defaults_pattern', array(
			'overlayColor' => 'rgba(255, 0, 0, 0.5)',
			'backdropBlur' => 15,
		) );

		update_option( 'sppopups_defaults_tldr', array(
			'inheritOverlay' => true,
		) );

		$tldr_defaults = SPPopups_Settings::get_tldr_defaults();

		$this->assertEquals( 'rgba(255, 0, 0, 0.5)', $tldr_defaults['overlayColor'] );
		$this->assertEquals( 15, $tldr_defaults['backdropBlur'] );
	}

	/**
	 * Test get_tldr_defaults inherits close buttons from pattern
	 */
	public function test_get_tldr_defaults_inherits_close_buttons_from_pattern() {
		update_option( 'sppopups_defaults_pattern', array(
			'showIconClose' => false,
			'showFooterClose' => false,
			'footerCloseText' => 'Custom Close',
		) );

		update_option( 'sppopups_defaults_tldr', array(
			'inheritCloseButtons' => true,
		) );

		$tldr_defaults = SPPopups_Settings::get_tldr_defaults();

		$this->assertFalse( $tldr_defaults['showIconClose'] );
		$this->assertFalse( $tldr_defaults['showFooterClose'] );
		$this->assertEquals( 'Custom Close', $tldr_defaults['footerCloseText'] );
	}
}
