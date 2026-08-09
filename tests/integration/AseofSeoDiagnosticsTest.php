<?php
/**
 * A.SEOf SEO diagnostics integration coverage.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsCheck;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsOptions;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsService;
use AIMultilingual\Seo\LanguageRelationshipService;
use AIMultilingual\Translation\Store;

/**
 * Contract-primary SEO health checks (read-only).
 */
final class AseofSeoDiagnosticsTest extends AimlTestCase {

	public function test_scan_returns_sf13_model_with_contract_checks(): void {
		update_option( 'blog_public', '0' );
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$this->add_language( 'de', 'de_DE', Languages::STATUS_PREVIEW );

		$service  = $this->make_service();
		$snapshot = $service->scan(
			new SeoDiagnosticsOptions(
				path: '/',
				include_http: false,
			)
		);

		$data = $snapshot->to_array();
		$this->assertSame( 'aiml.seo_diagnostics.v1', $data['model'] );
		$this->assertContains( 'blog_public_zero', $data['limitations'] );

		$by_id = $this->index_checks( $snapshot->checks );
		$this->assertSame( SeoDiagnosticsCheck::STATUS_PASS, $by_id['sf4_language_graph']->status );
		$this->assertSame( SeoDiagnosticsCheck::STATUS_PASS, $by_id['sf3_hreflang']->status );
		$this->assertSame( SeoDiagnosticsCheck::STATUS_PASS, $by_id['sf8_preview_leakage']->status );
		$this->assertSame( SeoDiagnosticsCheck::STATUS_PASS, $by_id['sf6_sitemap']->status );
		$this->assertSame( 'blog_public_honesty', $by_id['sf6_sitemap']->code );
		$this->assertSame( SeoDiagnosticsCheck::STATUS_PASS, $by_id['sf7_robots_indexability']->status );
		$this->assertSame( SeoDiagnosticsCheck::STATUS_WARNING, $by_id['sf15_external_readiness']->status );
		$this->assertArrayHasKey( 'sf1_health_summary', $by_id );
		$this->assertSame( SeoDiagnosticsCheck::STATUS_SKIPPED, $by_id['sf9_redirect_loop']->status );
	}

	public function test_preview_language_excluded_from_public_graph(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$this->add_language( 'de', 'de_DE', Languages::STATUS_PREVIEW );

		$rels  = ( new LanguageRelationshipService( $this->languages, $this->context ) )->for_path( '/', false );
		$codes = array_map( static fn( $r ) => $r->language_code, $rels );
		$this->assertNotContains( 'de', $codes );

		$by_id = $this->index_checks(
			$this->make_service()->scan( new SeoDiagnosticsOptions( include_http: false ) )->checks
		);
		$this->assertSame( SeoDiagnosticsCheck::STATUS_PASS, $by_id['sf8_preview_leakage']->status );
	}

	public function test_woocommerce_path_is_in_scope(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$by_id = $this->index_checks(
			$this->make_service()->scan(
				new SeoDiagnosticsOptions(
					path: '/product/sample-tea/',
					include_http: false,
				)
			)->checks
		);
		$this->assertSame( 'ok', $by_id['sf11_woocommerce']->code );
	}

	public function test_inactive_rank_math_is_non_fatal(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$integration = new RankMathIntegration(
			new PluginIdentity(),
			new Store( new Cache() ),
			new LanguageContext(),
			new LanguageRelationshipService( $this->languages, $this->context ),
			true,
			false,
			'1.0.275',
			false,
			true
		);
		$service     = new SeoDiagnosticsService(
			new LanguageRelationshipService( $this->languages, $this->context ),
			$this->languages,
			$integration
		);
		$by_id       = $this->index_checks(
			$service->scan( new SeoDiagnosticsOptions( include_http: false ) )->checks
		);
		$this->assertContains(
			$by_id['sf12_rankmath_compat']->status,
			array(
				SeoDiagnosticsCheck::STATUS_WARNING,
				SeoDiagnosticsCheck::STATUS_PASS,
				SeoDiagnosticsCheck::STATUS_UNAVAILABLE,
			)
		);
		$this->assertSame( SeoDiagnosticsCheck::STATUS_SKIPPED, $by_id['sf5_social']->status );
	}

	public function test_service_source_is_read_only(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/src/Seo/Diagnostics/SeoDiagnosticsService.php'
		);
		foreach ( array(
			'update_option(',
			'update_post_meta(',
			'wp_update_post(',
			'wpdb->update',
			'wpdb->insert',
			'rank_math_update',
		) as $needle ) {
			$this->assertStringNotContainsString( $needle, $source, $needle );
		}
	}

	private function make_service(): SeoDiagnosticsService {
		return new SeoDiagnosticsService(
			new LanguageRelationshipService( $this->languages, $this->context ),
			$this->languages,
			null
		);
	}

	/**
	 * Indexes checks by id.
	 *
	 * @param array $checks Checks.
	 * @return array<string, \AIMultilingual\Seo\Diagnostics\SeoDiagnosticsCheck>
	 */
	private function index_checks( array $checks ): array {
		$out = array();
		foreach ( $checks as $check ) {
			$out[ $check->id ] = $check;
		}
		return $out;
	}
}
