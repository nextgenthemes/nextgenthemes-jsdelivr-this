<?php
/**
 * @wordpress-plugin
 * Plugin Name:       NGT jsDelivr CDN
 * Plugin URI:        https://nextgenthemes.com
 * Description:       Makes your site load all WP Core and plugin assets from jsDelivr CDN
 * Version:           1.1.0
 * Requres PHP:       7.4
 * Author:            Nicolas Jonas
 * Author URI:        https://nextgenthemes.com/donate
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace Nextgenthemes\jsDelivrThis;

const VERSION = '1.1.0';

add_filter( 'script_loader_src', __NAMESPACE__ . '\filter_script_loader_src', 10, 2 );
add_filter( 'style_loader_src', __NAMESPACE__ . '\filter_style_loader_src', 10, 2 );

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( array $links ) {

		$links['donate'] = sprintf(
			'<a href="https://nextgenthemes.com/donate/"><strong style="display: inline;">%s</strong></a>',
			esc_html__( 'Donate', 'jsdelivr-this' )
		);

		return $links;
	}
);

function filter_script_loader_src( string $src, string $handle ): string {
	return maybe_replace_src( 'script', $src, $handle );
}
function filter_style_loader_src( string $src, string $handle ): string {
	return maybe_replace_src( 'style', $src, $handle );
}

function maybe_replace_src( string $type, string $src, string $handle ): string {
	$src = detect_by_hash( $type, $src, $handle );
	$src = detect_plugin_asset( $type, $src, $handle );
	return $src;
}

function get_plugin_dir_file( string $plugin_slug ): ?string {

	$active_plugins = get_option( 'active_plugins' );

	if ( in_array( "$plugin_slug/$plugin_slug.php", $active_plugins, true ) ) {
		return "$plugin_slug/$plugin_slug.php";
	}

	foreach ( $active_plugins as $key => $value ) {

		if ( str_starts_with( $value, $plugin_slug ) ) {
			return $value;
		}
	}

	return null;
}

function detect_plugin_asset( string $type, string $src, string $handle ): string {

	if ( str_starts_with( $src, 'https://cdn.jsdelivr.net' ) ) {
		return $src;
	}
	$ext = ( 'style' === $type ) ? 'css' : 'js';

	preg_match( "#/plugins/(?<plugin_slug>[^/]+)/(?<path>.*\.$ext)#", $src, $matches );

	if ( ! empty( $matches['plugin_slug'] ) ) {
		$plugin_dir_file = get_plugin_dir_file( $matches['plugin_slug'] );
	}

	if ( empty( $plugin_dir_file ) ) {
		return $src;
	}

	static $ran_already = false;
	$plugin_ver         = get_plugin_version( $plugin_dir_file );
	$cdn_file           = "https://cdn.jsdelivr.net/wp/{$matches['plugin_slug']}/tags/$plugin_ver/{$matches['path']}";
	$transient_name     = 'ngt_jsdelivr_this_' . $cdn_file;
	$data               = get_transient( $transient_name );

	if ( false === $data && ! $ran_already ) {

		$ran_already  = true;
		$data         = new \stdClass();
		$file_headers = ngt_headers( $cdn_file );

		if ( ! empty( $file_headers[0] ) && 'HTTP/1.1 200 OK' === $file_headers[0] ) {
			$data->file_exists = true;
			$path              = path_from_url( $src );

			if ( $path ) {
				$data->integrity = gen_integrity( file_get_contents( $path ) );
			}
		}

		// Random time between 24 and 48h to avoid calls getting made every pageload (if only one lonely visitor)
		set_transient( $transient_name, $data, wp_rand( DAY_IN_SECONDS, DAY_IN_SECONDS * 2 ) );
	}

	if ( ! empty( $data->file_exists ) && ! empty( $data->integrity ) ) {
		$src = $cdn_file;
		add_integrity_to_asset( $type, $handle, $data->integrity );
	}

	return $src;
}

/**
 * Retrieves headers for the given URL.
 *
 * @param string $url The URL for which to retrieve headers.
 * @return array|false Returns an array of headers on success or FALSE on failure.
 */
function ngt_headers( string $url ) {

	$opts['http']['timeout'] = 2;

	$context = stream_context_create( $opts );
	return @get_headers( $url, 0, $context ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

/**
 * Adds integrity and crossorigin attributes to assets based on type.
 *
 * @param string $type The type of the asset ('script' or 'style').
 * @param string $handle The handle of the asset.
 * @param string $integrity The integrity value to be added.
 */
function add_integrity_to_asset( string $type, string $handle, string $integrity ): void {

	if ( 'script' === $type ) {
		add_filter(
			'wp_script_attributes',
			function ( array $attr ) use ( $handle, $integrity ) {

				if ( ! empty( $attr['src'] ) &&
					! empty( $attr['id'] ) &&
					$handle . '-js' === $attr['id']
				) {
					$attr['integrity']   = $integrity;
					$attr['crossorigin'] = 'anonymous';
				}

				return $attr;
			}
		);
	} else {
		add_filter(
			'style_loader_tag',
			function ( $html, $fn_handle ) use ( $handle, $integrity ) {

				if ( $fn_handle === $handle ) {

					$p = new \WP_HTML_Tag_Processor( $html );

					if ( $p->next_tag( 'link' ) && $p->get_attribute( 'href' ) ) {

						$p->set_attribute( 'integrity', $integrity );
						$p->set_attribute( 'crossorigin', 'anonymous' );
						$html = $p->get_updated_html();
					}
				}

				return $html;
			},
			10,
			2
		);
	}
}

function get_jsdelivr_hash_api_data( string $file_path, string $handle, string $src ): ?object {

	static $ran_already = false;
	$transient_name     = "ngt_jsdelivr_this_{$handle}_{$src}_wp{$GLOBALS['wp_version']}";
	$result             = get_transient( $transient_name );

	if ( false === $result && ! $ran_already ) {

		$ran_already  = true;
		$result       = new \stdClass();
		$file_content = file_get_contents( $file_path );

		if ( $file_content ) {
			$sha256 = hash( 'sha256', $file_content );
			$data   = wp_safe_remote_get(
				"https://data.jsdelivr.com/v1/lookup/hash/$sha256",
				array(
					'user-agent' => 'https://nextgenthemes.com/plugins/jsdelivr-this',
					'timeout'    => 2,
				)
			);

			if ( ! is_wp_error( $data ) ) {
				$result            = (object) json_decode( wp_remote_retrieve_body( $data ) );
				$result->integrity = gen_integrity( $file_content );
			}
		}

		// Random time between 24 and 48h to avoid calls getting made every pageload (if only one lonely visitor)
		set_transient( $transient_name, $result, wp_rand( DAY_IN_SECONDS, DAY_IN_SECONDS * 2 ) );
	}

	if ( false === $result ) {
		$result = null;
	}

	return $result;
}

function detect_by_hash( string $type, string $src, string $handle ): string {

	if ( str_starts_with( $src, 'https://cdn.jsdelivr.net' ) ) {
		return $src;
	}

	$path = path_from_url( $src );

	if ( $path ) {
		$data = get_jsdelivr_hash_api_data( $path, $handle, $src );
	}

	$ver         = get_url_arg( $src, 'ver' );
	$wp_gh_asset = ( ! empty( $data->type ) && 'gh' === $data->type && 'WordPress/WordPress' === $data->name );
	$ver_not_wp  = ( ! empty( $data->type ) && $ver && $GLOBALS['wp_version'] !== $ver );

	if ( $wp_gh_asset || $ver_not_wp ) {
		$src = sprintf(
			'https://cdn.jsdelivr.net/%s/%s@%s',
			$data->type,
			$data->name,
			$data->version . $data->file
		);
		add_integrity_to_asset( $type, $handle, $data->integrity );
	}

	return $src;
}

/**
 * Retrieves the value of a specific query argument from the given URL.
 *
 * @param string $url The URL containing the query parameters.
 * @param string $arg The name of the query argument to retrieve.
 * @return string|null The value of the specified query argument, or null if it is not found.
 */
function get_url_arg( string $url, string $arg ): ?string {

	$query_string = parse_url( $url, PHP_URL_QUERY );

	if ( empty( $query_string ) || ! is_string( $query_string ) ) {
		return null;
	}

	parse_str( $query_string, $query_args );

	return $query_args[ $arg ] ?? null;
}

function gen_integrity( string $input ): string {
	$hash        = hash( 'sha384', $input, true );
	$hash_base64 = base64_encode( $hash ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	return "sha384-$hash_base64";
}

function path_from_url( string $url ): ?string {
	$parsed_url = wp_parse_url( $url );
	$file       = rtrim( ABSPATH, '/' ) . $parsed_url['path'];
	$file_alt   = rtrim( dirname( ABSPATH ), '/' ) . $parsed_url['path'];

	if ( is_file( $file ) ) {
		return $file;
	} elseif ( is_file( $file_alt ) ) {
		return $file_alt;
	}

	return null;
}

function get_plugin_version( string $plugin_file ): string {
	$plugin_data = get_file_data( WP_PLUGIN_DIR . "/$plugin_file", array( 'Version' => 'Version' ), 'plugin' );
	return $plugin_data['Version'];
}
