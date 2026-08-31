<?php
/**
 * Smoke-tier bootstrap (CI: lint + this suite, no database, no WordPress runtime).
 *
 * Loads the procedural caching files so their WordPress-free functions can be
 * exercised directly. WordPress core is NOT loaded here, so the handful of core
 * functions those files call at runtime are provided as minimal, pure-PHP test
 * doubles below. Do NOT add WordPress-runtime behaviour here — tests that need a
 * real WordPress runtime (options, hooks, transients, a database) belong in the
 * integration tier (tests/php/integration, run via `make test-integration`).
 *
 * @package automattic/wp-super-cache
 */

require_once __DIR__ . '/../../vendor/autoload.php';

/*
 * Minimal filter registry.
 *
 * The procedural files call apply_filters()/add_filter() at runtime. Under the
 * smoke tier there is no WordPress, so we provide a tiny callback registry that
 * matches WordPress's calling convention closely enough for these functions:
 * apply_filters() returns $value unchanged when no filters are registered, and
 * runs registered callbacks (in priority order) otherwise. This lets a test
 * register a hostile filter and assert the function under test sanitises its
 * output — e.g. the supercache_filename() #1050 regression guard.
 *
 * Guarded by function_exists() so a real WordPress runtime always wins; this
 * registry only exists in the no-WordPress smoke process.
 */
if ( ! function_exists( 'apply_filters' ) ) {
	$GLOBALS['wpsc_test_filters'] = array();

	/**
	 * Register a filter callback (test double for WordPress add_filter()).
	 *
	 * @param string   $hook_name     Filter hook name.
	 * @param callable $callback      Callback to run.
	 * @param int      $priority      Lower runs earlier. Default 10.
	 * @param int      $accepted_args Number of args passed to the callback. Default 1.
	 */
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wpsc_test_filters'][ $hook_name ][ $priority ][] = array(
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		);
		return true;
	}

	/**
	 * Apply registered filters to a value (test double for WordPress apply_filters()).
	 *
	 * @param string $hook_name Filter hook name.
	 * @param mixed  $value     Value to filter.
	 * @param mixed  ...$args   Additional arguments passed to callbacks.
	 * @return mixed Filtered value, or $value unchanged when no filters are registered.
	 */
	function apply_filters( $hook_name, $value, ...$args ) {
		if ( empty( $GLOBALS['wpsc_test_filters'][ $hook_name ] ) ) {
			return $value;
		}

		$buckets = $GLOBALS['wpsc_test_filters'][ $hook_name ];
		ksort( $buckets );
		foreach ( $buckets as $callbacks ) {
			foreach ( $callbacks as $registered ) {
				$call_args = array_merge( array( $value ), $args );
				$call_args = array_slice( $call_args, 0, $registered['accepted_args'] );
				$value     = call_user_func_array( $registered['callback'], $call_args );
			}
		}

		return $value;
	}

	/**
	 * Remove every callback registered for a hook (test cleanup helper).
	 *
	 * @param string $hook_name Filter hook name.
	 */
	function remove_all_filters( $hook_name ) {
		unset( $GLOBALS['wpsc_test_filters'][ $hook_name ] );
		return true;
	}

	/*
	 * Actions are filters in WordPress, so the action doubles delegate to the same
	 * registry. These let a test assert that a function under test wired a hook —
	 * e.g. the #1007 guard that wp_cache_postload() registers the cache-invalidation
	 * hooks even when there is no request URI (WP-CLI).
	 */

	/**
	 * Register an action callback (test double for WordPress add_action()).
	 *
	 * @param string   $hook_name     Action hook name.
	 * @param callable $callback      Callback to run.
	 * @param int      $priority      Lower runs earlier. Default 10.
	 * @param int      $accepted_args Number of args passed to the callback. Default 1.
	 */
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook_name, $callback, $priority, $accepted_args );
	}

	/**
	 * Whether a callback is registered for an action (test double for has_action()).
	 *
	 * @param string         $hook_name Action hook name.
	 * @param callable|false $callback  Specific callback to look for, or false for any.
	 * @return int|bool Registered priority for a specific callback, true for "any", false if none.
	 */
	function has_action( $hook_name, $callback = false ) {
		if ( empty( $GLOBALS['wpsc_test_filters'][ $hook_name ] ) ) {
			return false;
		}
		if ( false === $callback ) {
			return true;
		}
		foreach ( $GLOBALS['wpsc_test_filters'][ $hook_name ] as $priority => $callbacks ) {
			foreach ( $callbacks as $registered ) {
				if ( $registered['callback'] === $callback ) {
					return $priority;
				}
			}
		}
		return false;
	}

	/**
	 * Remove every callback registered for an action (test cleanup helper).
	 *
	 * @param string $hook_name Action hook name.
	 */
	function remove_all_actions( $hook_name ) {
		return remove_all_filters( $hook_name );
	}
}

/*
 * wp-cache-phase2.php calls wp_rand() in several places, the first a smoke test
 * reaches being the temporary file name in wp_cache_replace_line(). Nothing in the
 * smoke tier depends on the randomness, only on the function existing.
 */
if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * Random integer (test double for WordPress wp_rand()).
	 *
	 * @param int $min Lower bound.
	 * @param int $max Upper bound.
	 * @return int
	 */
	function wp_rand( $min = 0, $max = 4294967295 ) {
		return random_int( $min, $max );
	}
}

/*
 * Load the procedural caching engine. Its functions become callable from tests.
 * require_once keeps test files that also require it (for self-documentation)
 * idempotent.
 */
require_once dirname( __DIR__, 2 ) . '/wp-cache-phase2.php';
