<?php
/**
 * Integration tests for filtering post URLs during cache preloading.
 *
 * @package automattic/wp-super-cache
 */
class PreloadUrlTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		remove_all_filters( 'wpsc_preload_post_url' );
	}

	public function tear_down() {
		remove_all_filters( 'wpsc_preload_post_url' );
		parent::tear_down();
	}

	/**
	 * With no compatibility filter registered, the preload URL is unchanged.
	 */
	public function test_preload_url_defaults_to_the_post_permalink() {
		$post_id   = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$permalink = get_permalink( $post_id );

		$this->assertSame( $permalink, wpsc_get_preload_post_url( $post_id ) );
	}

	/**
	 * Compatibility plugins can provide a language-specific preload URL.
	 */
	public function test_preload_url_can_be_filtered_for_the_post() {
		$post_id   = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$permalink = get_permalink( $post_id );
		$arguments = null;

		add_filter(
			'wpsc_preload_post_url',
			static function ( $url, $filtered_post_id ) use ( &$arguments ) {
				$arguments = array( $url, $filtered_post_id );

				return add_query_arg( 'lang', 'fr', $url );
			},
			10,
			2
		);

		$this->assertSame(
			add_query_arg( 'lang', 'fr', $permalink ),
			wpsc_get_preload_post_url( $post_id )
		);
		$this->assertSame( array( $permalink, $post_id ), $arguments );
	}
}
