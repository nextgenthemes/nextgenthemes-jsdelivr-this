<?php

declare(strict_types = 1);

namespace Nextgenthemes\jsDelivrThis;

use WP_HTML_Tag_Processor;
use WP_UnitTestCase;

/**
 * Unit tests for the style_loader_tag filter with SupportCandy loaded, emulating frontend.
 */
class Test_Style_Loader_Tag_With_SupportCandy extends WP_UnitTestCase {

	/**
	 * Creates a directory at the specified path.
	 *
	 * @param string $path The directory path to create.
	 * @param int $permissions File permissions (optional, default 0755).
	 * @return bool True on success, false on failure.
	 */
	private function create_directory( string $path, int $permissions = 0755 ): bool {
		if ( file_exists( $path ) ) {
			if ( is_dir( $path ) ) {
				return true; // Directory already exists.
			}
			return false; // Path exists but isn’t a directory.
		}

		// Create the directory with specified permissions.
		$result = mkdir( $path, $permissions, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir

		if ( $result ) {
			return true;
		} else {
			fwrite( STDOUT, "Failed to create directory: $path" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false;
		}
	}

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$arve_php = WP_PLUGIN_DIR . '/advanced-responsive-video-embedder/advanced-responsive-video-embedder.php';

		$this->create_directory( WP_PLUGIN_DIR . '/advanced-responsive-video-embedder/build' );

		copy( __DIR__ . '/arve/advanced-responsive-video-embedder.php', $arve_php );
		copy( __DIR__ . '/arve/build/main.js', WP_PLUGIN_DIR . '/advanced-responsive-video-embedder/build/main.js' );

		wp_register_script(
			'arve',
			plugins_url( 'build/main.js', $arve_php ),
			array(),
			'10.6.12',
			array(
				'strategy' => 'async',
			)
		);
	}

	// Test wp_script_attributes filter
	public function test_version_of_fake_plugin(): void {
		$this->assertEquals( '10.6.12', get_plugin_version( 'advanced-responsive-video-embedder/advanced-responsive-video-embedder.php' ) );
	}

	public function test_jsdelivr_asset_in_head(): void {

		ob_start();
		wp_head();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'https://cdn.jsdelivr.net', $html );
	}

	public function todo_test_by_plugin(): void {
		$this->assertStringContainsString( 'jsdelivr.net', $this->get_script_src( 'arve' ) );
	}

	private function get_script_src( string $handle ): string {

		ob_start();
		wp_scripts()->do_item( $handle );
		$html = ob_get_clean();

		// Use WP_HTML_Tag_Processor to parse the HTML output.
		$p = new WP_HTML_Tag_Processor( $html );

		// Target the <link> tag with the specific id attribute (e.g., 'test-style-css').
		while ( $p->next_tag( [ 'tag_name' => 'script' ] ) ) {

			$id  = $p->get_attribute( 'id' );
			$src = $p->get_attribute( 'src' );

			if ( "$handle-js" === $id && $src ) {
				return $src;
			}
		}

		// Return the full tag if found, with a newline for consistency with original behavior.
		return '';
	}

	private function list_filters_for_hook( $hook_name ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook_name ] ) ) {
			return "No filters registered for the hook: $hook_name";
		}

		$output = [];
		foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
			$output[] = "Priority: $priority";
			foreach ( $callbacks as $callback ) {
				$callback_name = '';
				if ( is_string( $callback['function'] ) ) {
					$callback_name = $callback['function'];
				} elseif ( is_array( $callback['function'] ) ) {
					if ( is_object( $callback['function'][0] ) ) {
						$callback_name = get_class( $callback['function'][0] ) . '->' . $callback['function'][1];
					} else {
						$callback_name = $callback['function'][0] . '::' . $callback['function'][1];
					}
				} elseif ( $callback['function'] instanceof Closure ) {
					$callback_name = 'Closure';
				}
				$output[] = "  Callback: $callback_name (Accepted Args: {$callback['accepted_args']})";
			}
		}

		return implode( "\n", $output );
	}
}
