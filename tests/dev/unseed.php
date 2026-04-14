<?php
/**
 * Remove content created by tests/dev/seed.php. Safe to run repeatedly —
 * deletes only posts/pages that carry the `_wpsc_seed=1` meta key.
 *
 * @package WP_Super_Cache
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$ids = get_posts(
	array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_wpsc_seed',
		'meta_value'     => 1,
	)
);

$deleted = 0;
foreach ( $ids as $post_to_delete_id ) {
	if ( wp_delete_post( $post_to_delete_id, true ) ) {
		++$deleted;
	}
}

WP_CLI::success( sprintf( 'Deleted %d seeded posts/pages.', $deleted ) );
