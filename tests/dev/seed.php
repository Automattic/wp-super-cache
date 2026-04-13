<?php
/**
 * Seed a local development site with randomly-named posts and pages so that
 * caching behavior (preload, garbage collection, mod_rewrite) can be exercised
 * against a realistic volume of content.
 *
 * Intended to be executed via `wp eval-file` inside the `make up` environment.
 * Every item is tagged with post_meta `_wpsc_seed=1` so `tests/dev/unseed.php`
 * can remove the seeded content without touching anything else.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This file must be run via wp-cli (wp eval-file).\n" );
	exit( 1 );
}

$count = 100;

function wpsc_seed_random_title( $prefix ) {
	$words = array( 'alpha', 'bravo', 'charlie', 'delta', 'echo', 'foxtrot', 'golf', 'hotel', 'india', 'juliet', 'kilo', 'lima', 'mike', 'november', 'oscar', 'papa', 'quebec', 'romeo', 'sierra', 'tango', 'uniform', 'victor', 'whiskey', 'xray', 'yankee', 'zulu' );
	shuffle( $words );
	return $prefix . ': ' . ucfirst( $words[0] ) . ' ' . ucfirst( $words[1] ) . ' ' . substr( md5( (string) wp_rand() ), 0, 6 );
}

function wpsc_seed_random_body() {
	$body = '';
	for ( $p = 0; $p < 5; $p++ ) {
		$body .= '<p>' . str_repeat( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', wp_rand( 3, 8 ) ) . "</p>\n\n";
	}
	return $body;
}

$created = array(
	'post' => 0,
	'page' => 0,
);

foreach ( array( 'post', 'page' ) as $type ) {
	for ( $i = 0; $i < $count; $i++ ) {
		$post_id = wp_insert_post(
			array(
				'post_title'   => wpsc_seed_random_title( ucfirst( $type ) ),
				'post_content' => wpsc_seed_random_body(),
				'post_status'  => 'publish',
				'post_type'    => $type,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			fwrite( STDERR, "Failed to insert {$type}: " . $post_id->get_error_message() . "\n" );
			continue;
		}

		update_post_meta( $post_id, '_wpsc_seed', 1 );
		++$created[ $type ];
	}
}

printf( "Seeded %d posts and %d pages.\n", $created['post'], $created['page'] );
