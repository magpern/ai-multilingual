<?php
/**
 * A.SEOb SB11 language relationship contract + canonical/hreflang.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Language\Languages;
use AIMultilingual\Routing\Router;
use AIMultilingual\Seo\DocumentSeoHead;
use AIMultilingual\Seo\LanguageRelationshipService;

/**
 * Characterizes Supported SB1–SB11 surfaces.
 */
final class AseobCanonicalHreflangTest extends AimlTestCase {

	public function test_sb11_public_relationships_exclude_preview_and_use_sa7_urls(): void {
		$sv = $this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$this->add_language( 'de', 'de_DE', Languages::STATUS_PREVIEW );

		$this->route( '/sv/about/' );
		$svc   = new LanguageRelationshipService( $this->languages, $this->context );
		$rels  = $svc->for_public_request();
		$codes = array_map(
			static function ( $r ) {
				return $r->language_code;
			},
			$rels
		);

		$this->assertContains( 'en', $codes );
		$this->assertContains( 'sv', $codes );
		$this->assertNotContains( 'de', $codes );

		$by = array();
		foreach ( $rels as $rel ) {
			$by[ $rel->language_code ] = $rel;
		}

		$this->assertTrue( $by['en']->is_default );
		$this->assertTrue( $by['sv']->is_current );
		$this->assertStringEndsWith( '/about/', $by['en']->url );
		$this->assertStringNotContainsString( '/sv/', $by['en']->url );
		$this->assertStringContainsString( '/sv/about/', $by['sv']->url );
		$this->assertSame( 'sv-SE', $by['sv']->hreflang );
		$this->assertSame( $by['sv']->url, $svc->current_public()->url );
		$this->assertSame( $by['en']->url, $svc->default_public()->url );
		unset( $sv );
	}

	public function test_canonical_filter_uses_current_sb11_url(): void {
		$this->add_language();
		$this->route( '/sv/hello/' );

		$svc  = new LanguageRelationshipService( $this->languages, $this->context );
		$head = new DocumentSeoHead( $svc );
		$head->register();

		$filtered = apply_filters( 'get_canonical_url', home_url( '/hello/' ), null );
		$this->assertSame( $svc->current_public()->url, $filtered );
	}

	public function test_canonical_preserves_external_override(): void {
		$this->add_language();
		$this->route( '/sv/hello/' );

		$svc      = new LanguageRelationshipService( $this->languages, $this->context );
		$head     = new DocumentSeoHead( $svc );
		$external = 'https://example.com/other-canonical/';

		$this->assertSame( $external, $head->filter_canonical_url( $external, null ) );
	}

	public function test_hreflang_emission_includes_reciprocal_set_and_x_default(): void {
		$this->add_language();
		$this->route( '/sv/page/' );

		$svc  = new LanguageRelationshipService( $this->languages, $this->context );
		$head = new DocumentSeoHead( $svc );

		ob_start();
		$head->emit_hreflang();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'hreflang="en-US"', $html );
		$this->assertStringContainsString( 'hreflang="sv-SE"', $html );
		$this->assertStringContainsString( 'hreflang="x-default"', $html );
		$this->assertSame( 1, substr_count( $html, 'hreflang="x-default"' ) );
		$this->assertSame( 1, substr_count( $html, 'hreflang="sv-SE"' ) );
		$this->assertStringContainsString( '/sv/page/', $html );
		$default = $svc->default_public();
		$this->assertNotNull( $default );
		$this->assertStringContainsString( esc_url( $default->url ), $html );
	}

	public function test_redirect_canonical_blocks_language_strip_but_allows_same_prefix(): void {
		$this->add_language();
		$router = new Router( $this->languages, $this->resolver, $this->context );
		$router->register();
		$this->route( '/sv/about/' );

		$ref  = new \ReflectionClass( $router );
		$prop = $ref->getProperty( 'prefixed' );
		$prop->setAccessible( true );
		$prop->setValue( $router, true );

		$stripped = home_url( '/about/' );
		$this->assertFalse( $router->filter_redirect_canonical( $stripped ) );

		$same = home_url( '/sv/about-us/' );
		// home_url may already be prefixed by filter; build raw.
		$raw_home = trailingslashit( (string) get_option( 'home' ) );
		$allowed  = $raw_home . 'sv/about-us/';
		$this->assertSame( $allowed, $router->filter_redirect_canonical( $allowed ) );
	}

	public function test_rank_math_canonical_filter_hook_registered(): void {
		$svc  = new LanguageRelationshipService( $this->languages, $this->context );
		$head = new DocumentSeoHead( $svc );
		$head->register();

		$this->assertNotFalse( has_filter( 'rank_math/frontend/canonical', array( $head, 'filter_rank_math_canonical' ) ) );
		$this->assertNotFalse( has_filter( 'get_canonical_url', array( $head, 'filter_canonical_url' ) ) );
		$this->assertNotFalse( has_action( 'wp_head', array( $head, 'emit_hreflang' ) ) );
	}
}
