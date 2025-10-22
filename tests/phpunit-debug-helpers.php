<?php

declare(strict_types = 1);

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_dump
// phpcs:disable WordPress.WP.AlternativeFunctions
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_print_r
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export

/**
 * Prints the given variables to STDOUT.
 *
 * @param mixed ...$args The variables to print.
 */
function debug( ...$args ): void {

	// if ( is_string( $value )  ) {
	//  ob_start();
	//  var_dump( $value );
	//  $value = ob_get_clean();
	// }

	foreach ( $args as $value ) {
		ob_start();
		var_dump( $value );
		fwrite( STDOUT, ob_get_clean() );
	}
}

/**
 * Writes a log message to a specified file.
 *
 * @param mixed $name The name of the log message.
 * @param mixed $variable The variable to be logged.
 * @param string $file The file path for the log.
 */
function logfile( $name, $variable, string $file ): void {
	// if ( ! is_string( $msg ) ) {
	//  ob_start();
	//  var_dump( $msg );
	//  $msg  = ob_get_clean();
	//  $msg .= PHP_EOL;
	// }
	$msg = "$name " . var_export( $variable, true ) . PHP_EOL;

	error_log( $msg . PHP_EOL, 3, "$file.log" );
}

/**
 * Removes a log file if it exists.
 *
 * @param string $file The name of the log file to be removed
 */
function rm_logfile( string $file ): void {

	$file = "$file.log";

	if ( is_file( $file ) ) {
		unlink( $file );
	}
}

/**
 * Get all callbacks hooked to a WordPress hook.
 *
 * @param string $hook_name Hook name, e.g. 'wp_head'.
 * @return array<int, array{
 *     priority: int,
 *     callable: string,
 *     accepted_args: int|null,
 *     raw: mixed
 * }> Array of callbacks grouped by numeric index.
 */
function get_hooked_callbacks( string $hook_name = 'wp_head' ): array {
	global $wp_filter;

	if ( ! isset( $wp_filter[ $hook_name ] ) ) {
		return [];
	}

	$hook_obj = $wp_filter[ $hook_name ];

	// WP_Hook since WP 4.7
	if ( $hook_obj instanceof WP_Hook ) {
		$callbacks = $hook_obj->callbacks;
	} else {
		$callbacks = (array) $hook_obj;
	}

	$result = [];

	foreach ( $callbacks as $priority => $items ) {
		foreach ( $items as $id => $data ) {
			$function      = $data['function'] ?? $data;
			$accepted_args = $data['accepted_args'] ?? null;

			if ( is_array( $function ) ) {
				if ( is_object( $function[0] ) ) {
					$callable = get_class( $function[0] ) . '->' . $function[1];
				} else {
					$callable = $function[0] . '::' . $function[1];
				}
			} elseif ( $function instanceof \Closure ) {
				$callable = 'Closure';
			} elseif ( is_string( $function ) ) {
				$callable = $function;
			} else {
				$callable = var_export( $function, true );
			}

			$result[] = [
				'priority'      => (int) $priority,
				'callable'      => $callable,
				'accepted_args' => $accepted_args,
				#'raw'           => $function,
			];
		}
	}

	return $result;
}
