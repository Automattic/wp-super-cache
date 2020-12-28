<?php
/**
 * File containing the class Wp_Super_Cache_Debug
 *
 * @package wp-super-cache
 *
 * @since   2.0.0
 */

/**
 * Handles the debug log
 *
 * @since 2.0.0
 */
class Wp_Super_Cache_Debug {

	/**
	 * Configuration variables
	 *
	 * @since 1.0.1
	 * @var   object
	 */
	public $config;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct() {
		$this->config = Wp_Super_Cache_Config::instance();
	}

	/**
	 * Add a log message to the file, if debugging is turned on
	 *
	 * @param string $message The message that should be added to the log.
	 */
	public function log( $message ) {
		static $last_message = '';

		if ( $last_message === $message ) {
			return false;
		}
		$last_message = $message;

		// If either of the debug or log globals aren't set, then we can stop.
		if ( ! isset( $this->config->config['wp_super_cache_debug'] ) || ! isset( $this->config->config['wp_cache_debug_log'] ) ) {
			return false;
		}

		// If either the debug or log globals are false or empty, we can stop.
		if ( false === $this->config->config['wp_super_cache_debug'] || '' === $this->config->config['wp_cache_debug_log'] ) {
			return false;
		}

		// If the debug_ip has been set, but it doesn't match the ip of the requester
		// then we can stop.
		if (
			isset( $this->config->config['wp_cache_debug_ip'] )
			&& '' !== $this->config->config['wp_cache_debug_ip']
			&& $this->config->config['wp_cache_debug_ip'] !== $_SERVER['REMOTE_ADDR'] // phpcs:ignore
		) {
			return false;
		}

		// Log message: Date URI Message.
		$log_message = gmdate( 'H:i:s' ) . ' ' . getmypid() . ' ' . wp_unslash( $_SERVER['REQUEST_URI'] ) . ' ' . $message . PHP_EOL; // phpcs:ignore
		// path to the log file in the cache folder.
		$log_file = $this->config->config['cache_path'] . str_replace( '/', '', str_replace( '..', '', $this->config->config['wp_cache_debug_log'] ) );

		if ( ! file_exists( $log_file ) ) {
			if ( ! isset( $this->config->config['wp_cache_debug_username'] ) ) {
				$this->config->config['wp_cache_debug_username'] = '';
			}

			$this->create_debug_log( $this->config->config['wp_cache_debug_log'], $this->config->config['wp_cache_debug_username'] );
		}

		error_log( $log_message, 3, $log_file ); // phpcs:ignore
	}

	/**
	 * Get a username to use for the debug log.
	 */
	private function get_debug_username() {

		if ( ! isset( $this->config->config['wp_cache_debug_username'] ) || '' === $this->config->config['wp_cache_debug_username'] ) {
			$this->config->update_setting( 'wp_cache_debug_username', md5( time() + wp_rand() ) );
		}
		return $wp_cache_debug_username;
	}

	/**
	 * Create a new debug log
	 *
	 * @param string $filename The name of the log file.
	 * @param string $username username and password used to protect the log file.
	 */
	private function create_debug_log( $filename = '', $username = '' ) {
		global $wp_super_cache_config;

		if ( '' !== $filename ) {
			$this->config->update_setting( 'wp_cache_debug_log', $filename );
		} else {
			$this->config->update_setting( 'wp_cache_debug_log', md5( time() + wp_rand() ) . '.php' );
		}
		if ( '' !== $username ) {
			$this->config->update_setting( 'wp_cache_debug_username', $username );
		} else {
			$this->get_debug_username();
		}
		// phpcs:disable
		$msg = 'die( "Please use the viewer" );' . PHP_EOL;
		$fp = fopen( $this->config->config['cache_path'] . $this->config->config['wp_cache_debug_log'], 'w' );
		if ( $fp ) {
			fwrite( $fp, '<' . "?php\n" );
			fwrite( $fp, $msg );
			fwrite( $fp, '?' . '><pre>' . PHP_EOL );
			fwrite( $fp, '<' . '?php // END HEADER ?' . '>' . PHP_EOL );
			fclose( $fp );
			wp_cache_setting( 'wp_cache_debug_log', $this->config->config['wp_cache_debug_log'] );
			wp_cache_setting( 'wp_cache_debug_username', $this->config->config['wp_cache_debug_username'] );
		}

		$msg = '
if ( !isset( $_SERVER[ "PHP_AUTH_USER" ] ) || ( $_SERVER[ "PHP_AUTH_USER" ] != "' . $this->config->config['wp_cache_debug_username'] . '" && $_SERVER[ "PHP_AUTH_PW" ] != "' . $this->config->config['wp_cache_debug_username'] . '" ) ) {
	header( "WWW-Authenticate: Basic realm=\"WP-Super-Cache Debug Log\"" );
	header( $_SERVER[ "SERVER_PROTOCOL" ] . " 401 Unauthorized" );
	echo "You must login to view the debug log";
	exit;
}' . PHP_EOL;

		$fp = fopen( $this->config->config['cache_path'] . 'view_' . $this->config->config['wp_cache_debug_log'], 'w' );
		if ( $fp ) {
			fwrite( $fp, '<' . '?php' . PHP_EOL );
			$msg .= '$debug_log = file( "./' . $this->config->config['wp_cache_debug_log'] . '" );
$start_log = 1 + array_search( "<" . "?php // END HEADER ?" . ">" . PHP_EOL, $debug_log );
if ( $start_log > 1 ) {
	$debug_log = array_slice( $debug_log, $start_log );
}
?' . '><form action="" method="GET"><' . '?php

$checks = array( "wp-admin", "exclude_filter", "wp-content", "wp-json" );
foreach( $checks as $check ) {
	if ( isset( $_GET[ $check ] ) ) {
		$$check = 1;
	} else {
		$$check = 0;
	}
}

if ( isset( $_GET[ "filter" ] ) ) {
	$filter = htmlspecialchars( $_GET[ "filter" ] );
} else {
	$filter = "";
}

unset( $checks[1] ); // exclude_filter
?' . '>
<h2>WP Super Cache Log Viewer</h2>
<h3>Warning! Do not copy and paste this log file to a public website!</h3>
<p>This log file contains sensitive information about your website such as cookies and directories.</p>
<p>If you must share it please remove any cookies and remove any directories such as ' . ABSPATH . '.</p>
Exclude requests: <br />
<' . '?php foreach ( $checks as $check ) { ?>
	<label><input type="checkbox" name="<' . '?php echo $check; ?' . '>" value="1" <' . '?php if ( $$check ) { echo "checked"; } ?' . '> /> <' . '?php echo $check; ?' . '></label><br />
<' . '?php } ?' . '>
<br />
Text to filter by:

<input type="text" name="filter" value="<' . '?php echo $filter; ?' . '>" /><br />
<input type="checkbox" name="exclude_filter" value="1" <' . '?php if ( $exclude_filter ) { echo "checked"; } ?' . '> /> Exclude by filter instead of include.<br />
<input type="submit" value="Submit" />
</form>
<' . '?php
$path_to_site = "' . ABSPATH . '";
foreach ( $debug_log as $t => $line ) {
	$line = str_replace( $path_to_site, "ABSPATH/", $line );
	$debug_log[ $t ] = $line;
	foreach( $checks as $check ) {
		if ( $$check && false !== strpos( $line, " /$check/" ) ) {
			unset( $debug_log[ $t ] );
		}
	}
	if ( $filter ) {
		if ( false !== strpos( $line, $filter ) && $exclude_filter ) {
			unset( $debug_log[ $t ] );
		} elseif ( false === strpos( $line, $filter ) && ! $exclude_filter ) {
			unset( $debug_log[ $t ] );
		}
	}
}
foreach( $debug_log as $line ) {
	echo htmlspecialchars( $line ) . "<br />";
}';
			fwrite( $fp, $msg );
			fclose( $fp );
		}
		// phpcs:enable

		return array(
			'wp_cache_debug_log'      => $this->config->config['wp_cache_debug_log'],
			'wp_cache_debug_username' => $this->config->config['wp_cache_debug_username'],
		);
	}


	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @since  2.0
	 * @return Wp_Super_Cache_Config
	 */
	public static function instance() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}

/**
 * Add a log message to the file, if debugging is turned on
 *
 * @param string $message The message that should be added to the log.
 * @param int    $level deprecated.
 */
function wp_cache_debug( $message, $level = false ) {
	return Wp_Super_Cache_Debug::instance()->log( $message );
}
