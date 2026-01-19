<?php
/**
 * PHPUnit bootstrap file
 *
 * @package SPPopups
 */

// Forward custom PHPUnit Polyfills requirement to PHPUnit bootstrap file.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

// Detect where to load the WordPress tests environment from.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

// Try multiple locations for WordPress test suite.
if ( ! $_tests_dir ) {
	// Check if we're in a WordPress installation with tests.
	$possible_locations = array(
		dirname( __DIR__, 5 ) . '/tests/phpunit', // WordPress root/tests/phpunit
		dirname( __DIR__, 4 ) . '/tests/phpunit', // wp-content/tests/phpunit
		rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib',
	);

	foreach ( $possible_locations as $location ) {
		if ( file_exists( $location . '/includes/functions.php' ) ) {
			$_tests_dir = $location;
			break;
		}
	}
}

// If still not found, provide helpful error message.
if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "\n";
	echo "ERROR: WordPress test suite not found.\n";
	echo "\n";
	echo "The WordPress test suite is required to run PHPUnit tests.\n";
	echo "\n";
	echo "Options:\n";
	echo "1. Install WordPress test suite:\n";
	echo "   Download from: https://github.com/WordPress/wordpress-develop\n";
	echo "   Or use wp-env: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/\n";
	echo "\n";
	echo "2. Set WP_TESTS_DIR environment variable:\n";
	echo "   export WP_TESTS_DIR=/path/to/wordpress-develop/tests/phpunit\n";
	echo "\n";
	echo "3. Place test suite in one of these locations:\n";
	foreach ( $possible_locations as $location ) {
		echo "   - {$location}\n";
	}
	echo "\n";
	die( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/sppopups.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
