<?php
/**
 * Tests for wpsc_parse_accept_header().
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wpsc_parse_accept_header
 */
class WpscParseAcceptHeaderTest extends TestCase {

	/**
	 * Default JSON types mirroring the wpsc_accept_headers default.
	 *
	 * @var string[]
	 */
	private array $json_types = array(
		'application/json',
		'application/activity+json',
		'application/ld+json',
	);

	// ── RFC 7231 acceptance criteria from issue #1045 ─────────────────────────

	/**
	 * New Relic Synthetics header: text/html has implicit q=1.0, JSON is
	 * deprioritised at q=0.9 — must classify as text/html.
	 */
	public function test_nr_synthetics_header_classifies_as_html(): void {
		$accept = 'text/html,application/xhtml+xml,application/json;q=0.9,application/javascript;q=0.9,text/javascript;q=0.9,application/xml;q=0.9,text/plain;q=0.8,*/*;q=0.7';
		$this->assertSame( 'text/html', wpsc_parse_accept_header( $accept, $this->json_types ) );
	}

	/** Bare JSON-only Accept: application/json should classify as JSON. */
	public function test_bare_json_classifies_as_json(): void {
		$this->assertSame( 'application/json', wpsc_parse_accept_header( 'application/json', $this->json_types ) );
	}

	/** Tie (both implicit q=1.0) resolves to text/html (safe default). */
	public function test_tie_resolves_to_html(): void {
		$this->assertSame( 'text/html', wpsc_parse_accept_header( 'text/html,application/json', $this->json_types ) );
	}

	/** JSON strictly higher q than text/html must classify as JSON. */
	public function test_json_higher_q_classifies_as_json(): void {
		$this->assertSame( 'application/json', wpsc_parse_accept_header( 'application/json,text/html;q=0.9', $this->json_types ) );
	}

	/** Extended JSON type (application/ld+json) via filter participates in comparison. */
	public function test_extended_json_type_participates(): void {
		$this->assertSame( 'application/json', wpsc_parse_accept_header( 'application/ld+json;q=1.0,text/html;q=0.8', $this->json_types ) );
	}

	/** Malformed q-value (non-numeric) treated as q=1.0, no warning/fatal. */
	public function test_malformed_q_value_treated_as_default(): void {
		// application/json;q=bad → treated as q=1.0; text/html absent → effective_html_q = 0.0; 1.0 > 0.0 → application/json.
		$this->assertSame( 'application/json', wpsc_parse_accept_header( 'application/json;q=bad,*/*;q=0.7', $this->json_types ) );
	}

	/** Out-of-range q-value clamped — q=2 treated as 1.0. */
	public function test_out_of_range_q_clamped(): void {
		$this->assertSame( 'text/html', wpsc_parse_accept_header( 'text/html;q=2,application/json;q=0.9', $this->json_types ) );
	}

	// ── Wildcard behaviour ────────────────────────────────────────────────────

	/**
	 * The *\/* wildcard does NOT cover text/html when text/html is not explicit.
	 * A JSON client sending "*\/*,application/json;q=0.9" must classify as JSON,
	 * not as text/html, to avoid serving cached HTML to non-browser clients.
	 */
	public function test_wildcard_does_not_cover_html_when_not_explicit(): void {
		// html absent → effective_html_q = 0.0; json q=0.9 > 0.0 → application/json.
		$this->assertSame( 'application/json', wpsc_parse_accept_header( '*/*,application/json;q=0.9', $this->json_types ) );
	}

	/** Fediverse regression: "application/json, *\/*" must NOT serve cached HTML. */
	public function test_fediverse_json_with_wildcard_classifies_as_json(): void {
		// html absent → effective_html_q = 0.0; json q=1.0 > 0.0 → application/json.
		$this->assertSame( 'application/json', wpsc_parse_accept_header( 'application/json, */*', $this->json_types ) );
	}

	/** Explicit text/html;q=0.5 wins over *\/*;q=1.0 — wildcard does not boost an explicit html q. */
	public function test_explicit_html_takes_precedence_over_wildcard(): void {
		// html explicit q=0.5; wildcard q=1.0; json q=0.8 → 0.8 > 0.5 → application/json.
		$this->assertSame( 'application/json', wpsc_parse_accept_header( 'text/html;q=0.5,*/*;q=1.0,application/json;q=0.8', $this->json_types ) );
	}

	/** No explicit text/html and no wildcard, but JSON present — effective html_q = 0.0 → application/json. */
	public function test_no_html_no_wildcard_json_present(): void {
		$this->assertSame( 'application/json', wpsc_parse_accept_header( 'application/json;q=0.5', $this->json_types ) );
	}

	// ── Edge cases ────────────────────────────────────────────────────────────

	/** Whitespace around media types is handled. */
	public function test_whitespace_around_media_types(): void {
		$this->assertSame( 'text/html', wpsc_parse_accept_header( ' text/html , application/json;q=0.9 ', $this->json_types ) );
	}

	/** Classifies application/activity+json as JSON. */
	public function test_activity_json_classified_as_json(): void {
		$this->assertSame( 'application/json', wpsc_parse_accept_header( 'application/activity+json', $this->json_types ) );
	}

	/** Custom type added via wpsc_accept_headers filter participates. */
	public function test_custom_json_type_via_filter_participates(): void {
		$extended = array_merge( $this->json_types, array( 'application/vnd.api+json' ) );
		$this->assertSame( 'application/json', wpsc_parse_accept_header( 'application/vnd.api+json;q=1.0,text/html;q=0.8', $extended ) );
	}

	/** Typical browser Accept header classifies as text/html. */
	public function test_typical_browser_accept_header(): void {
		$accept = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8';
		$this->assertSame( 'text/html', wpsc_parse_accept_header( $accept, $this->json_types ) );
	}
}
