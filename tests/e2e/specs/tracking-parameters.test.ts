import { beforeAll, beforeEach, describe, expect, test } from '@jest/globals';
import { load as parseDom } from 'cheerio';
import { updateSettings } from '../lib/plugin-settings';
import { deleteCacheDirectory, getAuthCookie } from '../lib/plugin-tools';
import { loadPage } from '../lib/test-tools';
import { resetEnvironmnt, wpcli } from '../lib/wordpress-tools';

describe( 'ignored tracking parameters', () => {
	beforeAll( async () => {
		await resetEnvironmnt();
		await wpcli( 'plugin', 'activate', 'wp-super-cache' );
		await updateSettings( await getAuthCookie(), {
			wp_cache_enabled: true,
		} );
		await wpcli(
			'eval',
			"wp_cache_setting( 'wpsc_tracking_parameters', array( 'gclid', 'fbclid', 'utm_source' ) ); wp_cache_setting( 'wpsc_ignore_tracking_parameters', 1 );"
		);
	} );

	beforeEach( async () => {
		await deleteCacheDirectory();
	} );

	test( 'does not cache visitor A click ID in visitor B form action', async () => {
		const visitorA = await loadPage( '/', { gclid: 'A' } );
		const visitorB = await loadPage();

		expect( visitorA ).toBe( visitorB );
		expect( parseDom( visitorA )( '#wpsc-request-uri-form' ).attr( 'action' ) ).toBe( '/' );
		expect( parseDom( visitorB )( '#wpsc-request-uri-form' ).attr( 'action' ) ).toBe( '/' );
	} );
} );
