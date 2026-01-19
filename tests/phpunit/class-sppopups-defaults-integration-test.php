<?php
/**
 * Integration tests for defaults flow
 *
 * Tests that defaults flow correctly from PHP → JavaScript → DOM.
 *
 * @package SPPopups
 */

/**
 * Test class for defaults integration tests
 */
class SPPopups_Defaults_Integration_Test extends WP_UnitTestCase {

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		parent::tearDown();
		// Clean up options
		delete_option( 'sppopups_defaults_pattern' );
		delete_option( 'sppopups_defaults_tldr' );
		delete_option( 'sppopups_defaults_gallery' );
		// Clean up scripts
		wp_deregister_script( 'simplest-popup-modal' );
		wp_dequeue_script( 'simplest-popup-modal' );
	}

	/**
	 * Test that defaults are localized to JavaScript
	 */
	public function test_defaults_localized_to_javascript() {
		// Create plugin instance and enqueue assets
		$plugin = new SPPopups_Plugin();
		$plugin->enqueue_assets();

		// Verify script is enqueued
		$this->assertTrue( wp_script_is( 'simplest-popup-modal', 'enqueued' ), 'Script should be enqueued' );

		// Get localized data
		global $wp_scripts;
		$script_data = $wp_scripts->get_data( 'simplest-popup-modal', 'data' );

		// Verify localized data exists
		$this->assertNotEmpty( $script_data, 'Script should have localized data' );
		$this->assertStringContainsString( 'sppopups', $script_data, 'Localized data should contain sppopups object' );
		$this->assertStringContainsString( 'defaults', $script_data, 'Localized data should contain defaults' );
	}

	/**
	 * Test that defaults structure in localized data is correct
	 */
	public function test_defaults_structure_in_localized_data() {
		$plugin = new SPPopups_Plugin();
		$plugin->enqueue_assets();

		// Get the actual localized data by parsing the script output
		// We'll check that the getter methods return the expected structure
		$pattern_defaults = SPPopups_Settings::get_pattern_defaults();
		$tldr_defaults    = SPPopups_Settings::get_tldr_defaults();
		$gallery_defaults = SPPopups_Settings::get_gallery_defaults();

		// Verify all three types have defaults
		$this->assertIsArray( $pattern_defaults, 'Pattern defaults should be an array' );
		$this->assertIsArray( $tldr_defaults, 'TLDR defaults should be an array' );
		$this->assertIsArray( $gallery_defaults, 'Gallery defaults should be an array' );

		// Verify structure includes required keys
		$this->assertArrayHasKey( 'maxWidth', $pattern_defaults, 'Pattern defaults should have maxWidth' );
		$this->assertArrayHasKey( 'maxWidth', $tldr_defaults, 'TLDR defaults should have maxWidth' );
		$this->assertArrayHasKey( 'maxWidth', $gallery_defaults, 'Gallery defaults should have maxWidth' );
	}

	/**
	 * Test that TLDR defaults inherit in localized data
	 */
	public function test_tldr_defaults_inherit_in_localized_data() {
		// Set custom pattern defaults
		update_option( 'sppopups_defaults_pattern', array(
			'maxWidth' => 1200,
			'borderRadius' => 12,
		) );

		// Set TLDR to inherit
		update_option( 'sppopups_defaults_tldr', array(
			'inheritModalAppearance' => true,
		) );

		// Get TLDR defaults (this is what would be localized)
		$tldr_defaults = SPPopups_Settings::get_tldr_defaults();

		// Verify inheritance is resolved before localization
		$this->assertEquals( 1200, $tldr_defaults['maxWidth'], 'TLDR should inherit maxWidth from pattern' );
		$this->assertEquals( 12, $tldr_defaults['borderRadius'], 'TLDR should inherit borderRadius from pattern' );
	}

	/**
	 * Test that gallery defaults inherit in localized data
	 */
	public function test_gallery_defaults_inherit_in_localized_data() {
		// Set custom pattern defaults
		update_option( 'sppopups_defaults_pattern', array(
			'maxWidth' => 1200,
			'overlayColor' => 'rgba(255, 0, 0, 0.5)',
		) );

		// Set gallery to inherit
		update_option( 'sppopups_defaults_gallery', array(
			'inheritModalAppearance' => true,
			'inheritOverlay' => true,
		) );

		// Get gallery defaults (this is what would be localized)
		$gallery_defaults = SPPopups_Settings::get_gallery_defaults();

		// Verify inheritance is resolved
		$this->assertEquals( 1200, $gallery_defaults['maxWidth'], 'Gallery should inherit maxWidth from pattern' );
		$this->assertEquals( 'rgba(255, 0, 0, 0.5)', $gallery_defaults['overlayColor'], 'Gallery should inherit overlayColor from pattern' );
	}

	/**
	 * Test that defaults are available on frontend
	 */
	public function test_defaults_available_on_frontend() {
		// Simulate frontend context
		$this->go_to( home_url() );

		// Create plugin instance and enqueue assets
		$plugin = new SPPopups_Plugin();
		$plugin->enqueue_assets();

		// Verify script is registered and enqueued
		$this->assertTrue( wp_script_is( 'simplest-popup-modal', 'registered' ), 'Script should be registered' );
		$this->assertTrue( wp_script_is( 'simplest-popup-modal', 'enqueued' ), 'Script should be enqueued on frontend' );

		// Verify defaults getter methods work (these are what get localized)
		$pattern_defaults = SPPopups_Settings::get_pattern_defaults();
		$tldr_defaults    = SPPopups_Settings::get_tldr_defaults();
		$gallery_defaults = SPPopups_Settings::get_gallery_defaults();

		// All should return arrays with data
		$this->assertNotEmpty( $pattern_defaults, 'Pattern defaults should not be empty' );
		$this->assertNotEmpty( $tldr_defaults, 'TLDR defaults should not be empty' );
		$this->assertNotEmpty( $gallery_defaults, 'Gallery defaults should not be empty' );
	}
}
