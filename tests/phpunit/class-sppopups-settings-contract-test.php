<?php
/**
 * Contract tests for SPPopups_Settings defaults
 *
 * These tests lock in the current default values to prevent accidental changes.
 * If defaults need to change, these tests must be updated intentionally.
 *
 * @package SPPopups
 */

/**
 * Test class for settings contract tests
 */
class SPPopups_Settings_Contract_Test extends WP_UnitTestCase {

	/**
	 * Test that pattern defaults match expected contract
	 *
	 * This test acts as a contract - if defaults change, this test will fail
	 * and force explicit review of the change.
	 */
	public function test_pattern_defaults_contract() {
		$defaults = SPPopups_Settings::get_pattern_defaults();

		// Expected contract - update this if defaults intentionally change
		$expected_contract = array(
			'maxWidth'        => 600,
			'borderRadius'    => 6,
			'maxHeight'       => 90,
			'overlayColor'    => 'rgba(0, 0, 0, 0.1)',
			'backdropBlur'    => 8,
			'showIconClose'   => true,
			'showFooterClose' => true,
			'footerCloseText' => 'Close →',
		);

		foreach ( $expected_contract as $key => $expected_value ) {
			$this->assertEquals(
				$expected_value,
				$defaults[ $key ],
				sprintf( 'Default value for %s changed from contract. Review if intentional.', $key )
			);
		}
	}

	/**
	 * Test that TLDR defaults match expected contract
	 */
	public function test_tldr_defaults_contract() {
		$defaults = SPPopups_Settings::get_tldr_defaults();

		// Expected contract for TLDR defaults
		$expected_contract = array(
			'inheritModalAppearance' => true,
			'inheritOverlay'         => true,
			'inheritCloseButtons'    => true,
			'loadingText'            => 'Generating TLDR',
			'titleText'              => 'TLDR',
		);

		foreach ( $expected_contract as $key => $expected_value ) {
			$this->assertEquals(
				$expected_value,
				$defaults[ $key ],
				sprintf( 'TLDR default value for %s changed from contract. Review if intentional.', $key )
			);
		}

		// When inheriting, these should match pattern defaults
		$pattern_defaults = SPPopups_Settings::get_pattern_defaults();
		$this->assertEquals( $pattern_defaults['maxWidth'], $defaults['maxWidth'], 'TLDR should inherit maxWidth from pattern when inheriting' );
		$this->assertEquals( $pattern_defaults['borderRadius'], $defaults['borderRadius'], 'TLDR should inherit borderRadius from pattern when inheriting' );
		$this->assertEquals( $pattern_defaults['maxHeight'], $defaults['maxHeight'], 'TLDR should inherit maxHeight from pattern when inheriting' );
	}

	/**
	 * Test that gallery defaults match expected contract
	 */
	public function test_gallery_defaults_contract() {
		$defaults = SPPopups_Settings::get_gallery_defaults();

		// Expected contract for gallery defaults
		$expected_contract = array(
			'inheritModalAppearance' => true,
			'inheritOverlay'         => true,
			'inheritCloseButtons'    => true,
			'imageNavigation'        => 'both',
			'showCaptions'           => true,
			'crossfadeTransition'    => true,
			'transitionDuration'     => 500,
			'preloadAdjacentImages'  => true,
			'showNavOnHover'         => true,
		);

		foreach ( $expected_contract as $key => $expected_value ) {
			$this->assertEquals(
				$expected_value,
				$defaults[ $key ],
				sprintf( 'Gallery default value for %s changed from contract. Review if intentional.', $key )
			);
		}

		// When inheriting, these should match pattern defaults
		$pattern_defaults = SPPopups_Settings::get_pattern_defaults();
		$this->assertEquals( $pattern_defaults['maxWidth'], $defaults['maxWidth'], 'Gallery should inherit maxWidth from pattern when inheriting' );
		$this->assertEquals( $pattern_defaults['borderRadius'], $defaults['borderRadius'], 'Gallery should inherit borderRadius from pattern when inheriting' );
		$this->assertEquals( $pattern_defaults['maxHeight'], $defaults['maxHeight'], 'Gallery should inherit maxHeight from pattern when inheriting' );
	}

	/**
	 * Test that all required keys exist in pattern defaults
	 */
	public function test_pattern_defaults_completeness() {
		$defaults = SPPopups_Settings::get_pattern_defaults();

		$required_keys = array(
			'maxWidth',
			'borderRadius',
			'maxHeight',
			'overlayColor',
			'backdropBlur',
			'showIconClose',
			'showFooterClose',
			'footerCloseText',
		);

		foreach ( $required_keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$defaults,
				sprintf( 'Required default key %s is missing from pattern defaults', $key )
			);
		}
	}

	/**
	 * Test that all three popup types have consistent base structure
	 */
	public function test_defaults_structure_consistency() {
		$pattern_defaults = SPPopups_Settings::get_pattern_defaults();
		$tldr_defaults    = SPPopups_Settings::get_tldr_defaults();
		$gallery_defaults = SPPopups_Settings::get_gallery_defaults();

		// All should have these base modal appearance keys (when not inheriting)
		$base_keys = array( 'maxWidth', 'borderRadius', 'maxHeight', 'overlayColor', 'backdropBlur', 'showIconClose', 'showFooterClose', 'footerCloseText' );

		foreach ( $base_keys as $key ) {
			$this->assertArrayHasKey( $key, $pattern_defaults, "Pattern defaults missing base key: {$key}" );
			$this->assertArrayHasKey( $key, $tldr_defaults, "TLDR defaults missing base key: {$key}" );
			$this->assertArrayHasKey( $key, $gallery_defaults, "Gallery defaults missing base key: {$key}" );
		}
	}
}
