<?php
/**
 * @wordpress-plugin
 * Plugin Name:       NextGenThemes jsDelivr CDN
 * Plugin URI:        https://nextgenthemes.com
 * Description:       Serves all available assets from free jsDelivr CDN
 * Version:           1.3.5
 * Requres PHP:       7.4
 * Author:            Nicolas Jonas
 * Author URI:        https://nextgenthemes.com/donate
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types = 1);

namespace Nextgenthemes\jsDelivrThis;

use WP_HTML_Tag_Processor;

const VERSION = '1.3.5';

add_action( 'after_setup_theme', __NAMESPACE__ . '\after_setup_theme' );

function after_setup_theme(): void {

	$position = wp_is_block_theme() ? 'wp_head' : 'wp_footer';
	remove_action( $position, array( wp_script_modules(), 'print_import_map' ) );
	remove_action( $position, array( wp_script_modules(), 'print_enqueued_script_modules' ) );
	remove_action( $position, array( wp_script_modules(), 'print_script_module_preloads' ) );
	add_action( $position, __NAMESPACE__ . '\print_import_map' );
	add_action( $position, array( wp_script_modules(), 'print_enqueued_script_modules' ) );
	add_action( $position, __NAMESPACE__ . '\print_script_module_preloads' );
}

add_action( 'init', __NAMESPACE__ . '\init', 9 );

function init(): void {

	require_once __DIR__ . '/fn-remote-get.php';

	add_filter( 'wp_script_attributes', __NAMESPACE__ . '\filter_script_attributes', 10, 1 );
	add_filter( 'style_loader_tag', __NAMESPACE__ . '\filter_link_tags', 10, 1 );

	add_action( 'admin_bar_menu', __NAMESPACE__ . '\add_item_to_admin_bar', 33 );

	add_action( 'init', __NAMESPACE__ . '\register_assets' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );

	add_action( 'admin_footer', __NAMESPACE__ . '\admin_bar_html' );
	add_action( 'wp_footer', __NAMESPACE__ . '\admin_bar_html' );

	add_filter(
		'plugin_row_meta',
		function ( array $links, string $file ): array {

			if ( 'nextgenthemes-jsdelivr-this/nextgenthemes-jsdelivr-this.php' !== $file ) {
				return $links;
			}
			$links[] = '<strong>' . arve_links() . '</strong>';

			return $links;
		},
		10,
		2
	);

	add_filter(
		'plugin_action_links_' . plugin_basename( __FILE__ ),
		function ( array $links ) {

			$links['donate'] = sprintf(
				'<a href="https://nextgenthemes.com/donate/">%s</a>',
				esc_html__( 'Donate', 'nextgenthemes-jsdelivr-this' )
			);

			return $links;
		}
	);
}

function print_script_module_preloads(): void {

	ob_start();

	wp_script_modules()->print_script_module_preloads();
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo filter_link_tags( ob_get_clean() );
}

function print_import_map(): void {

	ob_start();
	wp_script_modules()->print_import_map();
	$html = ob_get_clean();

	// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	$import_map = json_decode( strip_tags( $html ), true );

	if ( empty( $import_map['imports'] ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
		return;
	}

	foreach ( $import_map['imports'] as $id => $old_src ) {

		$attr['src'] = $old_src;
		$attr        = filter_script_attributes( $attr );

		if ( ! empty( $attr['integrity'] ) ) {
			$import_map['imports'][ $id ]            = $attr['src'];
			$import_map['integrity'][ $attr['src'] ] = $attr['integrity'];
		}
	}

	wp_print_inline_script_tag(
		wp_json_encode( $import_map, JSON_HEX_TAG | JSON_HEX_AMP ),
		array(
			'type' => 'importmap',
			'id'   => 'wp-importmap',
		)
	);
}

function register_assets(): void {

	wp_register_style(
		'ngt-jsdelivr-dialog',
		plugins_url( 'dialog.css', __FILE__ ),
		array( 'media-views' ),
		VERSION
	);

	wp_register_script_module(
		'ngt-jsdelivr-dialog',
		plugins_url( 'dialog.js', __FILE__ ),
		array(),
		VERSION
	);
}

function enqueue_assets(): void {

	if ( is_admin_bar_showing() ) {
		wp_enqueue_style( 'ngt-jsdelivr-dialog' );
		wp_enqueue_script_module( 'ngt-jsdelivr-dialog' );
	}
}

function add_item_to_admin_bar( object $admin_bar ): void {
	// Add a new item to the admin bar
	$admin_bar->add_node(
		array(
			'id'     => 'ngt-jsdelivr',
			'title'  => '&nbsp;',
			'href'   => '#',
			'parent' => 'top-secondary',
		)
	);
}

function arve_links(): string {
	return wp_kses(
		sprintf(
				// translators: %1$s: link, %2$s: link
			__( 'Level up your video embeds with <a href="%1$s">ARVE</a> or <a href="%2$s">ARVE Pro</a>', 'nextgenthemes-jsdelivr-this' ),
			esc_url( 'https://wordpress.org/plugins/advanced-responsive-video-embedder/' ),
			esc_url( 'https://nextgenthemes.com/plugins/arve-pro/' )
		),
		array( 'a' => array( 'href' => array() ) )
	);
}

function admin_bar_html(): void {

	if ( ! is_admin_bar_showing() ) {
		return;
	}

	?>
<dialog class="ngt-jsdelivr-dialog">
	<div class="ngt-jsdelivr-dialog__header">
		<button type="button" class="media-modal-close">
			<span class="media-modal-icon">
				<span class="screen-reader-text">Close dialog</span>
			</span>
		</button>
	</div>
	<h3><?php esc_html_e( 'jsDelivr CDN plugin by Nextgenthemes', 'nextgenthemes-jsdelivr-this' ); ?></h3>
	<p>
		<?php
		esc_html_e(
			'These are the assets loaded from jsDelivr CDN. Do not worry about old WP versions in the URLs, this is simply because the files were not modified. A sha384 hash check is used so you can be 100% sure the files loaded from jsDelivr are the exact same files that would be served from your server.',
			'nextgenthemes-jsdelivr-this'
		);
		?>
	</p>
	<pre></pre>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				// translators: %1$s: link, %2$s: link
				__( 'Level up your video embeds with <a href="%1$s">ARVE</a> or <a href="%2$s">ARVE Pro</a>', 'nextgenthemes-jsdelivr-this' ),
				esc_url( 'https://wordpress.org/plugins/advanced-responsive-video-embedder/' ),
				esc_url( 'https://nextgenthemes.com/plugins/arve-pro/' )
			),
			array( 'a' => array( 'href' => array() ) )
		);
		?>
	</p>
</dialog>
	<?php
}

/**
 * Filters the attributes of a script tag.
 *
 * @param array<string, string> $attributes The attributes of the script tag.
 * @return array<string, string>            The filtered attributes.
 */
function filter_script_attributes( array $attributes ): array {

	$detection_methods = [
		fn() => detect_wp_asset( $attributes['src'] ),
		fn() => detect_by_hash( $attributes['src'] ),
		fn() => detect_plugin_asset( $attributes['src'], 'js' ),
	];

	foreach ( $detection_methods as $detect ) {

		$new_attr = $detect();

		if ( $new_attr ) {
			$attributes['src']         = $new_attr['src'];
			$attributes['integrity']   = $new_attr['integrity'];
			$attributes['crossorigin'] = 'anonymous';
			break;
		}
	}

	return $attributes;
}

function filter_link_tags( string $html ): string {

	$p = new WP_HTML_Tag_Processor( $html );

	// we may have multiple links here, like with rel="preload" and regular
	while ( $p->next_tag( 'link' ) ) {

		$href = $p->get_attribute( 'href' );

		if ( ! $href ) {
			continue;
		}

		$detection_methods = [
			fn() => detect_wp_asset( $href ),
			fn() => detect_by_hash( $href ),
			fn() => detect_plugin_asset( $href, 'css' ),
		];

		foreach ( $detection_methods as $detect ) {

			$new_attr = $detect();

			if ( $new_attr ) {
				$p->set_attribute( 'href', $new_attr['src'] );
				$p->set_attribute( 'integrity', $new_attr['integrity'] );
				$p->set_attribute( 'crossorigin', 'anonymous' );
				break;
			}
		}
	}

	return $p->get_updated_html();
}

/**
 * Checks for a active plugin file based on a slug.
 *
 * @param string $plugin_slug The plugin slug to search for.
 *
 * @return string|null The path to the main plugin file if found, null otherwise.
 */
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

/**
 * Detects if file can be served from CDN
 *
 * Given a <link href="..."> or <script src="..."> it detects CDN files
 *
 * Plugins hosted on wp.org need some trickery by this plugin as the jsDelivr API does not detect them by hash.
 * #1 For wp.org assets the src URL must have `/plugins/plugin-slug/` in them and end with `.js` or `.css` (excluding cash busting `?ver=1.2.3`).
 * #2 wp.org assets need to have its current version published as a tag on the wp.org plugins SVN, `trunk` will not work.
 *
 * @param string $src            The src to detect.
 * @param string $extension      The extension of the file (css or js).
 *
 * @return array<string, string> The array contains 'src' and 'integrity' if file and hash can be detected on the server and the file exists on the CDN. Empty array otherwise
 */
function detect_plugin_asset( string $src, string $extension ): array {

	if ( str_starts_with( $src, 'https://cdn.jsdelivr.net' ) ) {
		return array();
	}

	preg_match( "#/plugins/(?<plugin_slug>[^/]+)/(?<path>.*\.$extension)#", $src, $matches );

	if ( ! empty( $matches['plugin_slug'] ) ) {
		$plugin_dir_file = get_plugin_dir_file( $matches['plugin_slug'] );
	}

	if ( empty( $plugin_dir_file ) ) {
		return array();
	}

	$plugin_ver = get_plugin_version( $plugin_dir_file );

	if ( ! $plugin_ver ) {
		return array();
	}

	$cdn_file       = 'https://cdn.jsdelivr.net/wp/' . $matches['plugin_slug'] . '/tags/' . $plugin_ver . '/' . $matches['path'];
	$transient_name = shorten_transient_name( 'ngt-jsd_' . $cdn_file );

	$data = get_transient( $transient_name );

	if ( false === $data && ! call_limit() ) {

		$data         = new \stdClass();
		$file_headers = remote_get_head( $cdn_file, [ 'timeout' => 2 ] );

		if ( ! is_wp_error( $file_headers ) ) {
			$data->file_exists = true;
			$data->integrity   = integrity_for_src( $src );
		}

		// Random time between 24 and 48h to avoid calls getting made every pageload (if only one lonely visitor)
		set_transient( $transient_name, $data, wp_rand( DAY_IN_SECONDS, DAY_IN_SECONDS * 2 ) );
	}

	if ( ! empty( $data->file_exists ) && ! empty( $data->integrity ) ) {
		return [
			'src'        => $cdn_file,
			'integrity'  => $data->integrity,
		];
	}

	return array();
}

function integrity_for_src( string $src ): ?string {
	$path = path_from_url( $src );

	if ( $path ) {
		$file_content = file_get_contents( $path );

		if ( ! $file_content ) {
			wp_trigger_error( __FUNCTION__, 'Could not read file: ' . $path );
		} else {
			return gen_integrity( $file_content );
		}
	}

	return null;
}

/**
 * Assert that the current WordPress installation is a stable release.
 *
 * @return string|null Stable WordPress version. Null for beta... versions.
 */
function get_stable_wp_version(): ?string {

	if ( str_contains_any(
		$GLOBALS['wp_version'],
		[
			'beta',
			'alpha',
			'rc',
			'dev',
		]
	) ) {
		return null;
	}

	// No pre‑release strings found – the version is stable.
	return $GLOBALS['wp_version'];
}

/**
 * Return the part of a URL that comes after the “/wp‑includes/” segment.
 *
 * @param string $url  Full URL (may contain query string or fragment).
 *
 * @return string|null Empty string if the segment is not present,
 *                otherwise the path that follows the segment.
 */
function get_wp_asset_path( string $url ): ?string {
	// Remove query string and fragment – we only care about the path.
	$clean = strtok( $url, '?#' );

	$pos_includes = strpos( $clean, '/wp-includes/' );

	if ( false !== $pos_includes ) {
		return substr( $clean, $pos_includes );
	}

	$pos_admin = strpos( $clean, '/wp-admin/' );

	if ( false !== $pos_admin ) {
		return substr( $clean, $pos_admin );
	}

	return null;
}

/**
 * Detects if file can be served from CDN
 *
 * Given a <link href="..."> or <script src="..."> it detects CDN files
 *
 * @param string $src The src to detect.
 *
 * @return array{src: string, integrity: string}|array{}
 */
function detect_wp_asset( string $src ): array {

	if ( str_starts_with( $src, 'https://cdn.jsdelivr.net' ) ||
		! str_contains_any( $src, [ '/wp-includes/', '/wp-admin/' ] )
	) {
		return array();
	}

	$wp_version = get_stable_wp_version();

	if ( ! $wp_version ) {
		return array();
	}

	$wp_asset_path = get_wp_asset_path( $src );

	if ( ! $wp_asset_path ) {
		return array();
	}

	# https://cdn.jsdelivr.net/gh/WordPress/WordPress@6.8.2/wp-includes/js/dist/script-modules/interactivity/debug.js

	$cdn_file       = 'https://cdn.jsdelivr.net/gh/WordPress/WordPress@' . $wp_version . $wp_asset_path;
	$transient_name = shorten_transient_name( 'ngt-jsd_' . $cdn_file );
	$data           = get_transient( $transient_name );

	if ( false === $data ) {

		$data            = new \stdClass();
		$data->integrity = integrity_for_src( $src );

		set_transient( $transient_name, $data, YEAR_IN_SECONDS );
	}

	if ( ! empty( $data->integrity ) ) {
		return [
			'src'        => $cdn_file,
			'integrity'  => $data->integrity,
		];
	}

	return array();
}

function get_jsdelivr_hash_api_data( string $file_path, string $src ): ?object {
	$transient_name = shorten_transient_name( 'ngt-jsd_' . $src );
	$result         = get_transient( $transient_name );

	if ( false !== $result ) {
		return $result;
	}

	if ( call_limit() ) {
		return null;
	}

	return fetch_and_cache_jsdelivr_data( $file_path, $transient_name );
}

function fetch_and_cache_jsdelivr_data( string $file_path, string $transient_name ): object {

	$result       = new \stdClass();
	$file_content = file_get_contents( $file_path );

	if ( ! $file_content ) {
		cache_jsdelivr_api_result( $transient_name, $result );
		return $result;
	}

	$sha256   = hash( 'sha256', $file_content );
	$api_data = wp_safe_remote_get(
		'https://data.jsdelivr.com/v1/lookup/hash/' . $sha256,
		[
			'user-agent' => 'https://wordpress.org/plugins/nextgenthemes-jsdelivr-this/',
			'timeout'    => 2,
		]
	);

	process_jsdelivr_api_response( $api_data, $result );
	if ( property_exists( $result, 'file' ) && property_exists( $result, 'type' ) ) {
		$result->integrity = gen_integrity( $file_content );
	}

	cache_jsdelivr_api_result( $transient_name, $result );
	return $result;
}

/**
 * @param array<string, mixed>|\WP_Error $api_data
 */
function process_jsdelivr_api_response( $api_data, object &$result ): void {
	if ( is_wp_error( $api_data ) ) {
		wp_trigger_error( __FUNCTION__, $api_data->get_error_message() );
		return;
	}

	$response_code = wp_remote_retrieve_response_code( $api_data );
	if ( 404 === $response_code ) {
		return;
	}

	$body = trim( wp_remote_retrieve_body( $api_data ) );
	if ( empty( $body ) ) {
		wp_trigger_error( __FUNCTION__, 'Empty body' );
		return;
	}

	if ( 200 !== $response_code ) {
		wp_trigger_error( __FUNCTION__, 'Response code: ' . $response_code );
		return;
	}

	try {
		$result = json_decode( $body, false, 5, JSON_THROW_ON_ERROR );
	} catch ( \Exception $e ) {
		wp_trigger_error( __FUNCTION__, $e->getMessage() );
	}
}

function cache_jsdelivr_api_result( string $transient_name, object $result ): void {
	// Random time between 24 and 48h to avoid calls getting made every pageload (if only one lonely visitor)
	set_transient( $transient_name, $result, wp_rand( DAY_IN_SECONDS, DAY_IN_SECONDS * 2 ) );
}

/**
 * Detects if file can be served from CDN
 *
 * Given a <link href="..."> or <script src="..."> it detects CDN files
 *
 * @param string $src The src to detect.
 * @return array{src: string, integrity: string}|array{} The array contains 'src' and 'integrity' if file and hash can be detected on the server and the file exists on the CDN. Empty array otherwise
 */
function detect_by_hash( string $src ): array {

	if ( str_starts_with( $src, 'https://cdn.jsdelivr.net' ) ) {
		return array();
	}

	$data = new \stdClass();
	$path = path_from_url( $src );

	if ( $path ) {
		$data = get_jsdelivr_hash_api_data( $path, $src );
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

		$file_headers = remote_get_head_cached( $src, [ 'timeout' => 2 ] );

		if ( is_wp_error( $file_headers ) ) {
			return array();
		}

		return [
			'src'        => $src,
			'integrity'  => $data->integrity,
		];
	}

	return array();
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

	if ( empty( $query_string ) ) {
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

/**
 * Convert a registered/enqueued script URL to a filesystem path.
 *
 * @param string $script_url The script URL (absolute).
 * @return string|null       Filesystem path on success, null if not resolvable.
 */
function path_from_url( string $script_url ): ?string {

	// Extract the URL path (no normalization)
	$script_path = parse_url( $script_url, PHP_URL_PATH );
	if ( ! $script_path ) {
		return null;
	}

	// Static cache for mappings
	static $mappings = null;
	if ( null === $mappings ) {
		// Define raw mappings
		$raw_mappings = [
			content_url()        => WP_CONTENT_DIR,
			plugins_url()        => WP_PLUGIN_DIR,
			WPMU_PLUGIN_URL      => WPMU_PLUGIN_DIR, // @phpstan-ignore-line
			get_theme_file_uri() => get_theme_root() . '/' . wp_get_theme()->get_stylesheet(),
			site_url()           => ABSPATH,
		];

		// Prepare mappings: overwrite keys with parsed URL paths and values with normalized filesystem paths
		$mappings = [];
		foreach ( $raw_mappings as $url => $fs_path ) {
			$url_path = parse_url( $url, PHP_URL_PATH );

			if ( null === $url_path ) {
				$url_path = '';
			} elseif ( false === $url_path ) {
				wp_trigger_error( __FUNCTION__, 'parse_url error' );
				continue;
			}

			$mappings[ $url_path ] = wp_normalize_path( $fs_path );
		}
	}

	// Find matching base path
	foreach ( $mappings as $url_base => $fs_base ) {
		if ( str_starts_with( $script_path, $url_base ) ) {
			$relative = ltrim( substr( $script_path, strlen( $url_base ) ), '/' );
			$fs_path  = wp_normalize_path( $fs_base . '/' . $relative );

			// Verify the file exists
			if ( file_exists( $fs_path ) ) {
				return $fs_path;
			}
		}
	}

	return null;
}

function get_plugin_version( string $plugin_file ): string {
	$plugin_data = get_file_data( WP_PLUGIN_DIR . "/$plugin_file", array( 'Version' => 'Version' ), 'plugin' );
	return $plugin_data['Version'] ?? '';
}

/**
 * Limit the number of remote requests to jsDelivr in a short timespan.
 * In THEORY after the plugin is activated having this unlimited could
 * take the first php page generation of a page a long time, so we limit
 * it to 2 (this) x 2 (wp_safe_remote_get timeout) seconds at the time
 * of writing.
 *
 * @return bool True if the limit is reached, false otherwise.
 */
function call_limit(): bool {

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return false;
	}

	static $limit = 2;

	if ( 0 === $limit ) {
		return true;
	}

	--$limit;

	return false;
}

/**
 * Return true if any needle is present in the haystack.
 *
 * @param string[] $needles
 */
function str_contains_any( string $haystack, array $needles ): bool {
	foreach ( $needles as $needle ) {
		if ( '' !== $needle && str_contains( $haystack, $needle ) ) {
			return true;
		}
	}
	return false;
}
