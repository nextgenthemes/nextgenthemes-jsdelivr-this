<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Free jsDelivr CDN
 * Plugin URI:        https://nextgenthemes.com
 * Description:       Serves all available assets from free jsDelivr CDN
 * Version:           1.2.3
 * Requres PHP:       7.4
 * Author:            Nicolas Jonas
 * Author URI:        https://nextgenthemes.com/donate
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 */

declare(strict_types = 1);

namespace Nextgenthemes\jsDelivrThis;

const VERSION = '1.2.3';

add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

function init(): void {

	add_filter( 'wp_script_attributes', __NAMESPACE__ . '\filter_script_attributes', 10, 1 );
	add_filter( 'style_loader_tag', __NAMESPACE__ . '\filter_style_loader_tag', 10, 1 );

	add_action( 'admin_bar_menu', __NAMESPACE__ . '\add_item_to_admin_bar', 33 );

	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );

	add_action( 'admin_footer', __NAMESPACE__ . '\admin_bar_html' );
	add_action( 'wp_footer', __NAMESPACE__ . '\admin_bar_html' );

	add_action(
		'init',
		function (): void {
			wp_register_script_module(
				'ngt-jsdelivr-dialog',
				plugins_url( 'dialog.js', __FILE__ ),
				array(),
				VERSION
			);
		}
	);

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

function enqueue_assets(): void {

	if ( is_admin_bar_showing() ) {
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

	wp_enqueue_style( 'media-views' );

	?>
<dialog class="ngt-jsdelivr-dialog">
	<div class="ngt-jsdelivr-dialog__header">
		<button type="button" class="media-modal-close">
			<span class="media-modal-icon">
				<span class="screen-reader-text">Close dialog</span>
			</span>
		</button>
	</div>
	<h3><?= esc_html__( 'jsDelivr CDN plugin by Nextgenthemes', 'nextgenthemes-jsdelivr-this' ); ?></h3>
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
<style>
#wp-admin-bar-ngt-jsdelivr a {
	cursor: pointer;

	&:hover {
		background-color: darkred !important;
	}
}
.ngt-jsdelivr-dialog {
	--dialog-padding: 1.2rem;

	border: none;
	border-radius: 2px;
	box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
	padding: 0 var(--dialog-padding);
	width: 100dvw;
	max-width: 50rem;
	font-size: 1rem;
	&::backdrop {
		/* Style the backdrop */
		background-color: rgba(0, 0, 0, .9);
	}
	pre {
		font-size: 14px;
		overflow-x: auto;
	}
}

.ngt-jsdelivr-dialog__header {
	position: relative;

	> button {
		--btn-bg: oklch(0.91 0.01 281.07);

		position: absolute;
		top: 0;
		right: calc(var(--dialog-padding) * -1);
		border-radius: 0;
		background: var(--btn-bg);
		border-width: 0;

		&:hover {
			background-color: oklch(from var(--btn-bg) calc(l - 0.1) c h);
		}
	}
}
</style>
	<?php
}

function filter_script_attributes( array $attributes ): array {

	$by_hash = detect_by_hash( $attributes['src'] );

	if ( $by_hash ) {
		$attributes['src']         = $by_hash['src'];
		$attributes['integrity']   = $by_hash['integrity'];
		$attributes['crossorigin'] = 'anonymous';

		// we already got what we wanted, so exit early
		return $attributes;
	}

	$by_plugin = detect_plugin_asset( $attributes['src'], 'js' );

	if ( $by_plugin ) {
		$attributes['src']         = $by_plugin['src'];
		$attributes['integrity']   = $by_plugin['integrity'];
		$attributes['crossorigin'] = 'anonymous';
	}

	return $attributes;
}

function filter_style_loader_tag( string $html ): string {

	$p = new \WP_HTML_Tag_Processor( $html );

	// we may have multiple links here, like with rel="preload" and regular
	while ( $p->next_tag( 'link' ) ) {

		$href = $p->get_attribute( 'href' );

		if ( ! $href ) {
			continue;
		}

		$by_hash = detect_by_hash( $href );

		if ( $by_hash ) {
			$p->set_attribute( 'href', $by_hash['src'] );
			$p->set_attribute( 'integrity', $by_hash['integrity'] );
			$p->set_attribute( 'crossorigin', 'anonymous' );

			// we already got what we wanted, so exit early
			return $p->get_updated_html();
		}

		$by_plugin = detect_plugin_asset( $href, 'css' );

		if ( $by_plugin ) {
			$p->set_attribute( 'href', $by_plugin['src'] );
			$p->set_attribute( 'integrity', $by_plugin['integrity'] );
			$p->set_attribute( 'crossorigin', 'anonymous' );

			return $p->get_updated_html();
		}
	}

	return $html;
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
 * #1 For wp.org assets the src URL most have `/plugins/plugin-slug/` in them and end with `.js` or `.css` (excluding cash busting `?ver=1.2.3`).
 * #2 wp.org assets need to have its current version published as a tag on the wp.org plugins SVN, `trunk` will not work.
 *
 * @param string $src     The src to detect.
 * @param string $extension The extension of the file (css or js).
 *
 * @return array|null The array contains 'src' and 'integrity' if file and hash can be detected on the server and the file exists on the CDN.
 */
function detect_plugin_asset( string $src, string $extension ): ?array {

	if ( str_starts_with( $src, 'https://cdn.jsdelivr.net' ) ) {
		return $src;
	}

	preg_match( "#/plugins/(?<plugin_slug>[^/]+)/(?<path>.*\.$extension)#", $src, $matches );

	if ( ! empty( $matches['plugin_slug'] ) ) {
		$plugin_dir_file = get_plugin_dir_file( $matches['plugin_slug'] );
	}

	if ( empty( $plugin_dir_file ) ) {
		return null;
	}

	$plugin_ver     = get_plugin_version( $plugin_dir_file );
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

	return null;
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

function get_jsdelivr_hash_api_data( string $file_path, string $src ): ?object {

	$transient_name = shorten_transient_name( 'ngt-jsd_' . $src );
	$result         = get_transient( $transient_name );

	if ( false === $result && ! call_limit() ) {

		$result       = new \stdClass();
		$file_content = file_get_contents( $file_path );

		if ( $file_content ) {
			$sha256 = hash( 'sha256', $file_content );
			$data   = wp_safe_remote_get(
				'https://data.jsdelivr.com/v1/lookup/hash/' . $sha256,
				array(
					'user-agent' => 'https://nextgenthemes.com/plugins/jsdelivr-this',
					'timeout'    => 2,
				)
			);

			if ( ! is_wp_error( $data ) ) {

				$body = wp_remote_retrieve_body( $data );

				if ( '' === $body ) {
					wp_trigger_error( __FUNCTION__, 'Empty body' );
				} else {

					try {
						$result = (object) json_decode( $body, false, 5, JSON_THROW_ON_ERROR );
					} catch ( \Exception $e ) {
						wp_trigger_error( __FUNCTION__, $e->getMessage() );
					}
				}

				$result->integrity = gen_integrity( $file_content );
			}
		}

		// Random time between 24 and 48h to avoid calls getting made every pageload (if only one lonely visitor)
		set_transient( $transient_name, $result, wp_rand( DAY_IN_SECONDS, DAY_IN_SECONDS * 2 ) );
	}

	// So we can used nulled return type on php 7.4. Union types require 8.0
	if ( false === $result ) {
		$result = null;
	}

	return $result;
}

function detect_by_hash( string $src ): ?array {

	if ( str_starts_with( $src, 'https://cdn.jsdelivr.net' ) ) {
		return $src;
	}

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

		return [
			'src'        => $src,
			'integrity'  => $data->integrity,
		];
	}

	return null;
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

/**
 * Retrieves the file path for a given URL, relative to the WordPress root directory.
 *
 * First checks if the file exists in the WordPress root directory, and if not, then
 * checks the parent directory of the WordPress root directory.
 *
 * @param string $url The URL to retrieve the file path for.
 * @return string|null The file path if it exists, or null otherwise.
 */
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

/**
 * @return mixed|WP_Error
 */
function remote_get_head( string $url, array $args = array() ) {

	$response = wp_safe_remote_head( $url, $args );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$response_code = wp_remote_retrieve_response_code( $response );

	if ( 200 !== $response_code ) {

		return new \WP_Error(
			$response_code,
			sprintf(
				// Translators: 1 URL 2 HTTP response code.
				__( 'url: %1$s Status code 200 expected but was %2$s.', 'advanced-responsive-video-embedder' ),
				$url,
				$response_code
			)
		);
	}

	return $response;
}

function shorten_transient_name( string $transient_name ): string {

	$transient_name = str_replace( 'https://', '', $transient_name );

	if ( strlen( $transient_name ) > 172 ) {
		$transient_name = preg_replace( '/[^a-zA-Z0-9_]/', '', $transient_name );
	}

	if ( strlen( $transient_name ) > 172 ) {
		$transient_name = substr( $transient_name, 0, 107 ) . '_' . hash( 'sha256', $transient_name ); // 107 + 1 + 64
	}

	return $transient_name;
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

	static $limit = 2;

	if ( 0 === $limit ) {
		return true;
	}

	--$limit;

	return false;
}
