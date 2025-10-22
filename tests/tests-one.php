<?php

declare(strict_types = 1);

use function Nextgenthemes\jsDelivrThis\filter_script_attributes;
use function Nextgenthemes\jsDelivrThis\detect_wp_asset;

class Test_WP_Enqueue_Scripts extends WP_UnitTestCase {

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Reset global wp_scripts to ensure clean state
		global $wp_scripts;
		$wp_scripts = null;
		wp_scripts(); // Reinitialize WP_Scripts
		// Remove all actions on wp_enqueue_scripts to prevent interference
		remove_all_actions( 'wp_enqueue_scripts' );
		// Explicitly deregister test scripts
		wp_dequeue_script( 'admin-bar' );
		wp_deregister_script( 'admin-bar' );
		// Ensure site_url() returns a valid URL for tests
		$this->set_permalink_structure( '/%postname%/' );
		#update_option( 'siteurl', 'http://example.com' );
	}

	/**
	 * Tear down the test environment.
	 */
	public function tearDown(): void {
		// Clean up enqueued scripts and reset wp_scripts
		global $wp_scripts;
		wp_dequeue_script( 'admin-bar' );
		wp_deregister_script( 'admin-bar' );
		remove_all_actions( 'wp_enqueue_scripts' );
		$wp_scripts = null;
		parent::tearDown();
	}

	public function test_plugin_script(): void {

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		$arve_dir       = WP_PLUGIN_DIR . '/advanced-responsive-video-embedder';
		$arve_build_dir = WP_PLUGIN_DIR . '/advanced-responsive-video-embedder/build';
		$arve_php       = WP_PLUGIN_DIR . '/advanced-responsive-video-embedder/advanced-responsive-video-embedder.php';

		$this->assertTrue( wp_mkdir_p( $arve_build_dir ), 'failed to create arve build dir' );
		$this->assertDirectoryExists( $arve_build_dir );

		$this->assertTrue(
			$wp_filesystem->copy(
				__DIR__ . '/arve/advanced-responsive-video-embedder.php',
				WP_PLUGIN_DIR . '/advanced-responsive-video-embedder/advanced-responsive-video-embedder.php',
				true
			)
		);

		$this->assertTrue(
			$wp_filesystem->copy(
				__DIR__ . '/arve/build/main.js',
				WP_PLUGIN_DIR . '/advanced-responsive-video-embedder/build/main.js',
				true
			)
		);

		add_action(
			'wp_enqueue_scripts',
			function () use ( $arve_php ): void {
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
		);

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_script_is( 'arve', 'registered' ) );

		$this->assertEquals(
			'http://example.org/wp-content/plugins/advanced-responsive-video-embedder/build/main.js',
			wp_scripts()->registered['arve']->src,
			'Script source should match expected URL.'
		);

		$plugin_basename = 'advanced-responsive-video-embedder/advanced-responsive-video-embedder.php';

		// This stupid shit returns NULL on SUCCESS
		$this->assertNull( activate_plugin( $plugin_basename ), 'plugin activation failed' );
		$this->assertTrue( is_plugin_active( $plugin_basename ), 'plugin is not active' );

		$attr['src'] = wp_scripts()->registered['arve']->src;
		$attr        = filter_script_attributes( $attr );

		$this->verify_script_attr( $attr );

		$this->assertTrue( $wp_filesystem->rmdir( $arve_dir, true ), 'failed to remove arve dir' );
		$this->assertDirectoryDoesNotExist( $arve_dir, 'The dir that should be gone' );
	}

	/**
	 * Test that a script is properly enqueued with correct URL and version.
	 */
	public function test_core_scripts(): void {
		// Arrange: Register and enqueue a test script
		add_action(
			'wp_enqueue_scripts',
			function (): void {

				wp_enqueue_script(
					'hoverintent-js',
					'http://example.org/wp-includes/js/hoverintent-js.min.js',
					[],
					'2.2.1',
					true
				);

				wp_enqueue_script(
					'admin-bar',
					'http://example.org/wp-includes/js/admin-bar.js',
					[ 'hoverintent-js' ],
					false, // phpcs:ignore
					true
				);
			}
		);

		// Act: Trigger the wp_enqueue_scripts action
		do_action( 'wp_enqueue_scripts' );

		// Assert: Check if the script is enqueued with correct URL and version
		$this->assertTrue( wp_script_is( 'admin-bar', 'enqueued' ), 'Script should be enqueued.' );
		$this->assertTrue( wp_script_is( 'hoverintent-js', 'enqueued' ), 'Script should be enqueued.' );

		$this->assertEquals(
			'http://example.org/wp-includes/js/admin-bar.js',
			wp_scripts()->registered['admin-bar']->src,
			'Script source should match expected URL.'
		);

		$ab_attr['src']    = wp_scripts()->registered['admin-bar']->src;
		$hover_attr['src'] = wp_scripts()->registered['hoverintent-js']->src;
		$ab_attr           = filter_script_attributes( $ab_attr );
		$hover_attr        = filter_script_attributes( $hover_attr );

		$this->verify_script_attr( $ab_attr );
		$this->verify_script_attr( $hover_attr );
	}

	/**
	 * Verify that a script has the expected attributes.
	 *
	 * @param array<string, string> $attr Script attributes.
	 */
	private function verify_script_attr( array $attr ): void {

		$this->assertArrayHasKey( 'src', $attr, 'Script should have src attribute.' );
		$this->assertArrayHasKey( 'integrity', $attr, 'Script should have integrity attribute.' );
		$this->assertArrayHasKey( 'crossorigin', $attr, 'Script should have crossorigin attribute.' );

		$this->assertStringStartsWith( 'https://cdn.jsdelivr.net', $attr['src'], 'Script src should start with CDN URL.' );
		$this->assertStringStartsWith( 'sha384-', $attr['integrity'], 'Script integrity should start with sha384-.' );
		$this->assertEquals( 'anonymous', $attr['crossorigin'], 'Script crossorigin should be anonymous.' );
	}

	/**
	 * Test that a script is properly enqueued with correct URL and version.
	 */
	public function test_core_script_module(): void {

		remove_action( 'wp_footer', 'the_block_template_skip_link' );
		remove_action( 'wp_head', 'print_emoji_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		// Arrange: Register and enqueue a test script
		add_action(
			'wp_enqueue_scripts',
			function (): void {

				wp_enqueue_script_module(
					'block/navigation/view',
					'http://example.org/wp-includes/js/dist/script-modules/block-library/navigation/view.min.js',
					[ '@wordpress/interactivity' ]
				);
			}
		);

		// // Act: Trigger the wp_enqueue_scripts action
		ob_start();
		do_action( 'wp_head' );
		$head_html = ob_get_clean();

		ob_start();
		do_action( 'wp_footer' );
		$footer_html = ob_get_clean();

		// Initialise the processor with the HTML string.
		$p          = new WP_HTML_Tag_Processor( $footer_html );
		$nav_attr   = array();
		$import_map = array();

		// Loop through every tag until we find the desired <script>.
		while ( $p->next_tag() ) {

			if ( 'SCRIPT' === $p->get_tag() &&
				'block/navigation/view-js-module' === $p->get_attribute( 'id' )
			) {
				$attr_names = $p->get_attribute_names_with_prefix( '' ); // all names

				foreach ( $attr_names as $name ) {
					$nav_attr[ $name ] = $p->get_attribute( $name );
				}

				continue;
			}

			if ( 'SCRIPT' === $p->get_tag() &&
				'wp-importmap' === $p->get_attribute( 'id' )
			) {
				$import_map = $p->get_modifiable_text();
				continue;
			}
		}

		$this->assertArrayHasKey( 'src', $nav_attr, 'Script should have src attribute.' );
		$this->assertArrayHasKey( 'integrity', $nav_attr, 'Script should have integrity attribute.' );
		$this->assertArrayHasKey( 'crossorigin', $nav_attr, 'Script should have crossorigin attribute.' );

		$this->assertStringStartsWith( 'https://cdn.jsdelivr.net', $nav_attr['src'], 'Script src should start with CDN URL.' );
		$this->assertStringStartsWith( 'sha384-', $nav_attr['integrity'], 'Script integrity should start with sha384-.' );
		$this->assertEquals( 'anonymous', $nav_attr['crossorigin'], 'Script crossorigin should be anonymous.' );

		try {
			$decoded = json_decode( $import_map, true, 512, JSON_THROW_ON_ERROR );
			$this->assertIsArray( $decoded ); // sanity check
		} catch ( JsonException $e ) {
			$this->fail( $e->getMessage() );
		}

		$this->assertArrayHasKey( 'imports', $decoded, 'Import map should have imports array.' );
		$this->assertArrayHasKey( 'integrity', $decoded, 'Import map should have integrity array.' );
		$this->assertEquals(
			'https://cdn.jsdelivr.net/gh/WordPress/WordPress@6.8.2/wp-includes/js/dist/script-modules/interactivity/index.min.js',
			$decoded['imports']['@wordpress/interactivity'],
			'interactivity src is CDN.'
		);
		$this->assertArrayHasKey(
			'https://cdn.jsdelivr.net/gh/WordPress/WordPress@6.8.2/wp-includes/js/dist/script-modules/interactivity/index.min.js',
			$decoded['integrity'],
			'interactivity src not in integrity.'
		);
		$this->assertMatchesRegularExpression(
			'~<link crossorigin="anonymous" integrity="sha384-[^"]*" rel="modulepreload" href="https://cdn\.jsdelivr\.net/gh/WordPress/WordPress@[^/]+/wp-includes/js/dist/script-modules/interactivity/index\.min\.js" id="@wordpress/interactivity-js-modulepreload">~',
			$footer_html,
			'Footer HTML should contain the modulepreload link for interactivity script.'
		);
	}
}
