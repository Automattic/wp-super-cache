<?php
/**
 * Remove content created by tests/dev/seed.php. Safe to run repeatedly —
 * deletes only posts/pages that carry the `_wpsc_seed=1` meta key.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This file must be run via wp-cli (wp eval-file).\n" );
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
foreach ( $ids as $id ) {
	if ( wp_delete_post( $id, true ) ) {
		++$deleted;
	}
}

printf( "Deleted %d seeded posts/pages.\n", $deleted );
