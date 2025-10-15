<?php

declare(strict_types = 1);

class Tests_Enqueued_Scripts_Output extends WP_UnitTestCase {

	protected static function capture_hook_output( string $hook = 'wp_head' ): string {
		ob_start();
		do_action( $hook );
		return ob_get_clean();
	}

	private function get_attr( string $html, string $tag, string $id ): ?array {

		// Use WP_HTML_Tag_Processor to parse the HTML output.
		$p = new WP_HTML_Tag_Processor( $html );

		// Target the <link> tag with the specific id attribute (e.g., 'test-style-css').
		while ( $p->next_tag( [ 'tag_name' => $tag ] ) ) {

			$id = $p->get_attribute( 'id' );

			if ( $id === $p->get_attribute( 'id' ) ) {

				$attr_names = $p->get_attribute_names_with_prefix( '' );

				foreach ( $attr_names as $attr_name ) {
					$attr[ $attr_name ] = $p->get_attribute( $attr_name );
				}

				return $attr;
			}
		}

		// Return the full tag if found, with a newline for consistency with original behavior.
		return null;
	}

	public function test_jquery_in_head(): void {
		wp_enqueue_script( 'jquery' );

		$this->assertTrue( wp_script_is( 'jquery', 'enqueued' ) );

		// Capture footer output (jQuery is typically printed in footer when registered with $in_footer = true)
		$head_html   = $this->capture_hook_output( 'wp_head' );
		$jquery_attr = $this->get_attr( $head_html, 'script', 'jquery-core' );

		$this->assertArrayHasKey( 'src', $jquery_attr );
		$this->assertArrayHasKey( 'integrity', $jquery_attr );
		$this->assertArrayHasKey( 'crossorigin', $jquery_attr );

		$this->assertEquals( 'anonymous', $jquery_attr['crossorigin'] );
		$this->assertStringStartsWith( 'https://cdn.jsdelivr.net', $jquery_attr['src'] );
		$this->assertStringStartsWith( 'sha384-', $jquery_attr['integrity'] );
	}

	public function test_interactivity_api_module(): void {

		wp_enqueue_script_module( '@wordpress/block-library/navigation/view' );

		$head_html   = $this->capture_hook_output( 'wp_head' );
		$footer_html = $this->capture_hook_output( 'wp_footer' );

		d( $head_html );
		d( $footer_html );

		// $this->assertTrue( wp_script_is( 'jquery', 'enqueued' ) );

		// // Capture footer output (jQuery is typically printed in footer when registered with $in_footer = true)
		// $head_html   = $this->capture_hook_output( 'wp_head' );
		// $jquery_attr = $this->get_attr( $head_html, 'script', 'jquery-core' );

		// $this->assertArrayHasKey( $jquery_attr, 'src' );
		// $this->assertArrayHasKey( $jquery_attr, 'integrity' );
		// $this->assertArrayHasKey( $jquery_attr, 'crossorigin' );

		// $this->assertEquals( $jquery_attr['crossorigin'], 'anonymous' );
		// $this->assertStringStartsWith( $jquery_attr['src'], 'https://cdn.jsdelivr.net' );
		// $this->assertStringStartsWith( $jquery_attr['integrity'], 'sha384-' );
	}
}
