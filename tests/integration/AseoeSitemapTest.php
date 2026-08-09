<?php
/**
 * A.SEOe Rank Math sitemap language discovery.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Integration\RankMath\RankMathSitemapOverlay;
use AIMultilingual\Language\Languages;
use AIMultilingual\Seo\LanguageRelationshipService;

/**
 * Supported SE1–SE9 / SE12 characterization (Rank Math official filters).
 */
final class AseoeSitemapTest extends AimlTestCase {

	public function test_urlset_adds_xhtml_namespace_when_multilingual(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		update_option( 'blog_public', '1' );

		$overlay = $this->make_overlay();
		$overlay->register();

		$urlset = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		$out    = (string) apply_filters( 'rank_math/sitemap/page_urlset', $urlset );

		$this->assertStringContainsString( 'xmlns:xhtml="' . RankMathSitemapOverlay::XHTML_NS . '"', $out );
		$this->assertSame( 1, substr_count( $out, 'xmlns:xhtml=' ) );
		$this->assertSame( 1, substr_count( $out, 'xmlns:image=' ) );
	}

	public function test_sitemap_url_emits_sb11_reciprocal_xhtml_links(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		update_option( 'blog_public', '1' );

		$overlay = $this->make_overlay();
		$overlay->register();

		$home = trailingslashit( (string) get_option( 'home' ) );
		$loc  = $home . 'about/';
		$xml  = "\t<url>\n\t\t<loc>" . esc_html( $loc ) . "</loc>\n\t</url>\n";

		$out = (string) apply_filters(
			RankMathSitemapOverlay::HOOK_URL,
			$xml,
			array( 'loc' => $loc )
		);

		$this->assertStringContainsString( 'xhtml:link rel="alternate" hreflang="', $out );
		$this->assertStringContainsString( 'hreflang="x-default"', $out );
		$this->assertStringContainsString( $home . 'sv/about/', $out );
		$this->assertStringContainsString( $loc, $out );
		$this->assertSame( 1, substr_count( $out, '<loc>' ) );
		$this->assertSame( 1, substr_count( $out, 'hreflang="sv-SE"' ) );
		$this->assertSame( 1, substr_count( $out, 'hreflang="en-US"' ) );
	}

	public function test_preview_language_excluded_from_xhtml_links(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$this->add_language( 'de', 'de_DE', Languages::STATUS_PREVIEW );
		update_option( 'blog_public', '1' );

		$overlay = $this->make_overlay();
		$overlay->register();

		$home = trailingslashit( (string) get_option( 'home' ) );
		$loc  = $home . 'about/';
		$xml  = "\t<url>\n\t\t<loc>" . esc_html( $loc ) . "</loc>\n\t</url>\n";
		$out  = (string) apply_filters(
			RankMathSitemapOverlay::HOOK_URL,
			$xml,
			array( 'loc' => $loc )
		);

		$this->assertStringContainsString( '/sv/about/', $out );
		$this->assertStringNotContainsString( '/de/', $out );
		$this->assertStringNotContainsString( 'hreflang="de-DE"', $out );
	}

	public function test_product_urlset_and_url_hooks_work_for_woo_surfaces(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		update_option( 'blog_public', '1' );

		$overlay = $this->make_overlay();
		$overlay->register();

		$urlset = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		$this->assertStringContainsString(
			'xmlns:xhtml=',
			(string) apply_filters( 'rank_math/sitemap/product_urlset', $urlset )
		);
		$this->assertStringContainsString(
			'xmlns:xhtml=',
			(string) apply_filters( 'rank_math/sitemap/product_cat_urlset', $urlset )
		);

		$home = trailingslashit( (string) get_option( 'home' ) );
		$loc  = $home . 'product/bpc-157/';
		$out  = (string) apply_filters(
			RankMathSitemapOverlay::HOOK_URL,
			"\t<url>\n\t\t<loc>{$loc}</loc>\n\t</url>\n",
			array( 'loc' => $loc )
		);
		$this->assertStringContainsString( $home . 'sv/product/bpc-157/', $out );
	}

	public function test_blog_public_zero_does_not_enrich_discovery(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		update_option( 'blog_public', '0' );

		$overlay = $this->make_overlay();
		$overlay->register();

		$urlset = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		$this->assertSame( $urlset, apply_filters( 'rank_math/sitemap/page_urlset', $urlset ) );

		$home = trailingslashit( (string) get_option( 'home' ) );
		$loc  = $home . 'about/';
		$xml  = "\t<url>\n\t\t<loc>{$loc}</loc>\n\t</url>\n";
		$this->assertSame(
			$xml,
			apply_filters( RankMathSitemapOverlay::HOOK_URL, $xml, array( 'loc' => $loc ) )
		);
	}

	public function test_include_noindex_not_forced_on(): void {
		$overlay = $this->make_overlay();
		$overlay->register();
		$this->assertFalse( (bool) apply_filters( RankMathSitemapOverlay::HOOK_INCLUDE_NOINDEX, false, 'page' ) );
	}

	public function test_inactive_integration_skips_sitemap_registration(): void {
		$integration = new RankMathIntegration(
			new PluginIdentity(),
			$this->store,
			$this->context,
			new LanguageRelationshipService( $this->languages, $this->context ),
			true,
			false,
			'1.0.275',
			false,
			true
		);
		$integration->register_sitemap_hooks();
		$this->assertFalse( has_filter( RankMathSitemapOverlay::HOOK_URL ) );
	}

	public function test_xhtml_injection_is_idempotent_on_existing_links(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		update_option( 'blog_public', '1' );
		$overlay = $this->make_overlay();
		$overlay->register();

		$home  = trailingslashit( (string) get_option( 'home' ) );
		$loc   = $home . 'about/';
		$xml   = "\t<url>\n\t\t<loc>{$loc}</loc>\n\t</url>\n";
		$once  = (string) apply_filters( RankMathSitemapOverlay::HOOK_URL, $xml, array( 'loc' => $loc ) );
		$twice = (string) apply_filters( RankMathSitemapOverlay::HOOK_URL, $once, array( 'loc' => $loc ) );
		$this->assertSame( substr_count( $once, 'xhtml:link' ), substr_count( $twice, 'xhtml:link' ) );
	}

	private function make_overlay(): RankMathSitemapOverlay {
		return new RankMathSitemapOverlay(
			new LanguageRelationshipService( $this->languages, $this->context )
		);
	}
}
