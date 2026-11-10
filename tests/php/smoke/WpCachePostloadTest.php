<?php
/**
 * Smoke tests for wp_cache_postload() hook registration.
 *
 * Regression guard for #1007: when $wp_cache_request_uri is empty — which is the
 * case under WP-CLI and some cron runners — wp_cache_postload() must still register
 * the cache-invalidation hooks, or publishing a scheduled post from the CLI leaves
 * the stale page in the cache. The serving path is correctly skipped in that case;
 * only the content-change hooks need to run.
 *
 * Runs in the WordPress-free smoke tier; add_action()/has_action() are provided as
 * test doubles by tests/php/bootstrap-smoke.php.
 *
 * @package automattic/wp-super-cache
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wp_cache_postload
 */
class WpCachePostloadTest extends TestCase {

	/**
	 * With no request URI and caching enabled, postload still wires the
	 * content-change hooks. Runs in a separate process so the static guard inside
	 * wpsc_register_post_hooks() and the hook registry start clean.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_registers_invalidation_hooks_when_request_uri_empty(): void {
		$GLOBALS['wp_cache_request_uri']     = '';
		$GLOBALS['cache_enabled']            = true;
		$GLOBALS['wp_super_cache_late_init'] = false;

		// Precondition: nothing is hooked yet, so a pass below is the fix's doing.
		$this->assertFalse(
			has_action( 'transition_post_status', 'wpsc_post_transition' ),
			'transition_post_status must not be hooked before postload runs'
		);

		wp_cache_postload();

		$this->assertNotFalse(
			has_action( 'transition_post_status', 'wpsc_post_transition' ),
			'postload must register the post-transition invalidation hook without a request URI (WP-CLI)'
		);
		$this->assertNotFalse(
			has_action( 'publish_post', 'wp_cache_post_edit' ),
			'postload must register the publish_post invalidation hook without a request URI (WP-CLI)'
		);
	}

	/**
	 * Boundary: with caching disabled there is nothing to invalidate, so no hooks
	 * register even without a request URI.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_registers_no_hooks_when_cache_disabled(): void {
		$GLOBALS['wp_cache_request_uri'] = '';
		$GLOBALS['cache_enabled']        = false;

		wp_cache_postload();

		$this->assertFalse(
			has_action( 'transition_post_status', 'wpsc_post_transition' ),
			'no invalidation hooks should register while caching is disabled'
		);
	}
}
