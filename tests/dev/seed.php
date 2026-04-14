<?php
/**
 * Seed a local development site with randomly-named posts and pages so that
 * caching behavior (preload, garbage collection, mod_rewrite) can be exercised
 * against a realistic volume of content.
 *
 * Intended to be executed via `wp eval-file` inside the `make up` environment.
 * Every item is tagged with post_meta `_wpsc_seed=1` so `tests/dev/unseed.php`
 * can remove the seeded content without touching anything else.
 *
 * @package WP_Super_Cache
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
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

foreach ( array( 'post', 'page' ) as $content_type ) {
	for ( $i = 0; $i < $count; $i++ ) {
		$inserted_post_id = wp_insert_post(
			array(
				'post_title'   => wpsc_seed_random_title( ucfirst( $content_type ) ),
				'post_content' => wpsc_seed_random_body(),
				'post_status'  => 'publish',
				'post_type'    => $content_type,
			),
			true
		);

		if ( is_wp_error( $inserted_post_id ) ) {
			WP_CLI::warning( sprintf( 'Failed to insert %s: %s', $content_type, $inserted_post_id->get_error_message() ) );
			continue;
		}

		update_post_meta( $inserted_post_id, '_wpsc_seed', 1 );
		++$created[ $content_type ];
	}
}

WP_CLI::success( sprintf( 'Seeded %d posts and %d pages.', $created['post'], $created['page'] ) );
