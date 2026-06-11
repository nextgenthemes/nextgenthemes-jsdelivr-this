<?php

declare(strict_types = 1);

namespace Nextgenthemes\ARVE\phpUnit;

require_once __DIR__ . '/phpunit-debug-helpers.php';#

$kint = getenv( 'KINT_DEBUG_PLUGIN_FILE' );

if ( $kint ) {
	require_once $kint;
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\manually_load_plugins' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

function manually_load_plugins(): void {

	require_once dirname( __DIR__ ) . '/nextgenthemes-jsdelivr-this.php';
}
