<?php
/**
 * @wordpress-plugin
 * Plugin Name:       NGT jsDelivr This
 * Plugin URI:        https://nextgenthemes.com
 * Description:       Makes your site load all WP Core and plugin assets from jsDelivr CDN
 * Version:           0.9.0
 * Author:            Nicolas Jonas
 * Author URI:        https://nextgenthemes.com/donate
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace nextgenthemes\jsdelivr_this;

add_filter( 'script_loader_src', __NAMESPACE__ . '\\script_src', 10, 2 );
add_filter( 'style_loader_src', __NAMESPACE__ . '\\style_src', 10, 2 );

function script_src( $src, $handle ) {
	return src( 'script', $src, $handle );
};
function style_src( $src, $handle ) {
	return src( 'style', $src, $handle );
};

function src( $type, $src, $handle ) {
	#$src = replace_core_asset( $type, $src, $handle );
	$src = detect_by_hash( $type, $src, $handle );
	$src = detect_plugin_asset( $type, $src, $handle );
	return $src;
}

function replace_core_asset( $type, $src, $handle ) {

	if ( starts_with( $src, 'https://cdn.jsdelivr.net' ) ) {
		return $src;
	}

	$ext = ( 'script' === $type ) ? 'js' : 'css';

	if ( contains( $src, '/wp-includes/' ) || contains( $src, '/wp-admin/' ) ) {
		global $wp_version;
		preg_match( "#(?<path>(wp-includes|wp-admin)/.*\.$ext)#", $src, $matches );
		$src = "https://cdn.jsdelivr.net/gh/WordPress/WordPress@$wp_version/{$matches['path']}";
	}

	return $src;
}

function get_plugin_dir_file( $plugin_slug ) {

	$active_plugins = get_option( 'active_plugins' );

	if ( in_array( "$plugin_slug/$plugin_slug.php", $active_plugins, true ) ) {
		return "$plugin_slug/$plugin_slug.php";
	}

	foreach ( $active_plugins as $key => $value ) {

		if ( starts_with( $value, $plugin_slug ) ) {
			return $value;
		}
	}

	return false;
}

function detect_plugin_asset( $type, $src, $handle ) {

	if ( starts_with( $src, 'https://cdn.jsdelivr.net' ) ) {
		return $src;
	}

	$ext = ( 'script' === $type ) ? 'js' : 'css';

	preg_match( "#/plugins/(?<plugin_slug>[^/]+)/(?<path>.*\.$ext)#", $src, $matches );

	if ( ! empty( $matches['plugin_slug'] ) ) {
		$plugin_dir_file = get_plugin_dir_file( $matches['plugin_slug'] );
	}

	if ( empty( $plugin_dir_file ) ) {
		return $src;
	}

	$plugin_ver     = get_plugin_version( $plugin_dir_file );
	$cdn_file       = "https://cdn.jsdelivr.net/wp/{$matches['plugin_slug']}/tags/$plugin_ver/{$matches['path']}";
	$transient_name = "jsdelivr_this_{$cdn_file}_exists";

	if ( false === ( $file_exists = get_transient( $transient_name ) ) ) {

		$file_headers = @get_headers( $cdn_file );

		if ( 'HTTP/1.1 404 Not Found' === $file_headers[0] ) {
			$file_exists = 'no';
		} else {
			$file_exists = 'yes';
		}

		set_transient( $transient_name, $file_exists, rand( DAY_IN_SECONDS, DAY_IN_SECONDS * 2 ) );
	}

	if ( 'yes' === $file_exists ) {
		$src = $cdn_file;
	}

	return $src;
}

function detect_by_hash( $type, $src, $handle ) {

	if ( starts_with( $src, 'https://cdn.jsdelivr.net' ) ) {
		return $src;
	}

	#dd(get_home_path());
	$parsed_url = parse_url( $src );
	$file       = rtrim( ABSPATH, '/' ) . $parsed_url['path'];
	$file_alt   = rtrim( dirname( ABSPATH ), '/' ) . $parsed_url['path'];

	if ( is_file( $file ) ) {
		$data = get_jsdeliver_hash_api_data( $file );
	};
	if ( is_file( $file_alt ) ) {
		$data = get_jsdeliver_hash_api_data( $file_alt );
	};

	if ( isset( $data['type'] ) && 'gh' === $data['type'] ) {
		$src = "https://cdn.jsdelivr.net/{$data['type']}/{$data['name']}@{$data['version']}{$data['file']}";
	}

	return $src;
}

function get_jsdeliver_hash_api_data( $file_path ) {

	$transient_name = "jsdelivr_this_api_call_$file_path";
	$result         = get_transient( $transient_name );

	if ( false === $result ) {

		$result       = array();
		$file_content = file_get_contents( $file_path );

		if( $file_content ) {
			$sha256 = hash( 'sha256', $file_content );
			$data   = wp_remote_get( "https://data.jsdelivr.com/v1/lookup/hash/$sha256", array() );

			if ( ! is_wp_error( $data ) ) {
				$result = (array) json_decode( wp_remote_retrieve_body( $data ), true );
			}
		}

		set_transient( $transient_name, $result, rand( DAY_IN_SECONDS, DAY_IN_SECONDS * 2 ) );
	}

	return $result;
}

// https://stackoverflow.com/a/7168986.
function starts_with( $haystack, $needle ) {

	return $haystack[0] === $needle[0]
		? strncmp( $haystack, $needle, strlen( $needle ) ) === 0
		: false;
}

function contains( $haystack, $needle ) {
	return strpos( $haystack, $needle ) !== false;
}

function get_plugin_version( $plugin_file ) {
	$plugin_data = get_plugin_data( WP_PLUGIN_DIR . "/$plugin_file", false, false );
	return $plugin_data['Version'];
}

/**
 * Parses the plugin contents to retrieve plugin's metadata.
 *
 * The metadata of the plugin's data searches for the following in the plugin's
 * header. All plugin data must be on its own line. For plugin description, it
 * must not have any newlines or only parts of the description will be displayed
 * and the same goes for the plugin data. The below is formatted for printing.
 *
 *     /*
 *     Plugin Name: Name of Plugin
 *     Plugin URI: Link to plugin information
 *     Description: Plugin Description
 *     Author: Plugin author's name
 *     Author URI: Link to the author's web site
 *     Version: Must be set in the plugin for WordPress 2.3+
 *     Text Domain: Optional. Unique identifier, should be same as the one used in
 *    		load_plugin_textdomain()
 *     Domain Path: Optional. Only useful if the translations are located in a
 *    		folder above the plugin's base path. For example, if .mo files are
 *    		located in the locale folder then Domain Path will be "/locale/" and
 *    		must have the first slash. Defaults to the base folder the plugin is
 *    		located in.
 *     Network: Optional. Specify "Network: true" to require that a plugin is activated
 *    		across all sites in an installation. This will prevent a plugin from being
 *    		activated on a single site when Multisite is enabled.
 *      * / # Remove the space to close comment
 *
 * Some users have issues with opening large files and manipulating the contents
 * for want is usually the first 1kiB or 2kiB. This function stops pulling in
 * the plugin contents when it has all of the required plugin data.
 *
 * The first 8kiB of the file will be pulled in and if the plugin data is not
 * within that first 8kiB, then the plugin author should correct their plugin
 * and move the plugin data headers to the top.
 *
 * The plugin file is assumed to have permissions to allow for scripts to read
 * the file. This is not checked however and the file is only opened for
 * reading.
 *
 * @since 1.5.0
 *
 * @param string $plugin_file Path to the main plugin file.
 * @param bool   $markup      Optional. If the returned data should have HTML markup applied.
 *                            Default true.
 * @param bool   $translate   Optional. If the returned data should be translated. Default true.
 * @return array {
 *     Plugin data. Values will be empty if not supplied by the plugin.
 *
 *     @type string $Name        Name of the plugin. Should be unique.
 *     @type string $Title       Title of the plugin and link to the plugin's site (if set).
 *     @type string $Description Plugin description.
 *     @type string $Author      Author's name.
 *     @type string $AuthorURI   Author's website address (if set).
 *     @type string $Version     Plugin version.
 *     @type string $TextDomain  Plugin textdomain.
 *     @type string $DomainPath  Plugins relative directory path to .mo files.
 *     @type bool   $Network     Whether the plugin can only be activated network-wide.
 * }
 */
function get_plugin_data( $plugin_file, $markup = true, $translate = true ) {

	$default_headers = array(
		'Name' => 'Plugin Name',
		'PluginURI' => 'Plugin URI',
		'Version' => 'Version',
		'Description' => 'Description',
		'Author' => 'Author',
		'AuthorURI' => 'Author URI',
		'TextDomain' => 'Text Domain',
		'DomainPath' => 'Domain Path',
		'Network' => 'Network',
		// Site Wide Only is deprecated in favor of Network.
		'_sitewide' => 'Site Wide Only',
	);

	$plugin_data = get_file_data( $plugin_file, $default_headers, 'plugin' );

	// Site Wide Only is the old header for Network
	if ( ! $plugin_data['Network'] && $plugin_data['_sitewide'] ) {
		/* translators: 1: Site Wide Only: true, 2: Network: true */
		_deprecated_argument( __FUNCTION__, '3.0.0', sprintf( __( 'The %1$s plugin header is deprecated. Use %2$s instead.' ), '<code>Site Wide Only: true</code>', '<code>Network: true</code>' ) );
		$plugin_data['Network'] = $plugin_data['_sitewide'];
	}
	$plugin_data['Network'] = ( 'true' == strtolower( $plugin_data['Network'] ) );
	unset( $plugin_data['_sitewide'] );

	// If no text domain is defined fall back to the plugin slug.
	if ( ! $plugin_data['TextDomain'] ) {
		$plugin_slug = dirname( plugin_basename( $plugin_file ) );
		if ( '.' !== $plugin_slug && false === strpos( $plugin_slug, '/' ) ) {
			$plugin_data['TextDomain'] = $plugin_slug;
		}
	}

	if ( $markup || $translate ) {
		$plugin_data = _get_plugin_data_markup_translate( $plugin_file, $plugin_data, $markup, $translate );
	} else {
		$plugin_data['Title']      = $plugin_data['Name'];
		$plugin_data['AuthorName'] = $plugin_data['Author'];
	}

	return $plugin_data;
}
