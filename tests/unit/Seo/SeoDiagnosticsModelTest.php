<?php
/**
 * Unit tests for A.SEOf SF13 result model.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Seo;

use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsAdmissions;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsCheck;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsOptions;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Seo\Diagnostics\SeoDiagnosticsCheck
 * @covers \AIMultilingual\Seo\Diagnostics\SeoDiagnosticsSnapshot
 * @covers \AIMultilingual\Seo\Diagnostics\SeoDiagnosticsOptions
 * @covers \AIMultilingual\Seo\Diagnostics\SeoDiagnosticsAdmissions
 */
final class SeoDiagnosticsModelTest extends TestCase {

	public function test_status_vocabulary_is_frozen(): void {
		$this->assertSame(
			array( 'pass', 'warning', 'error', 'unavailable', 'skipped' ),
			SeoDiagnosticsCheck::statuses()
		);
	}

	public function test_check_rejects_unknown_status(): void {
		$this->expectException( \InvalidArgumentException::class );
		new SeoDiagnosticsCheck( 'sf2_canonical', 'fail', 'aiml', 'x', 'msg' );
	}

	public function test_snapshot_model_token(): void {
		$check = new SeoDiagnosticsCheck(
			'sf4_language_graph',
			SeoDiagnosticsCheck::STATUS_PASS,
			'aiml_sb11',
			'ok',
			'ok'
		);
		$snap  = new SeoDiagnosticsSnapshot(
			'2026-08-09T00:00:00Z',
			'/',
			'https://example.com/',
			array( $check ),
			array( 'pass' => 1 ),
			array( 'blog_public_zero' ),
			12,
			0
		);

		$data = $snap->to_array();
		$this->assertSame( 'aiml.seo_diagnostics.v1', $data['model'] );
		$this->assertSame( '/', $data['scope_path'] );
		$this->assertCount( 1, $data['checks'] );
		$this->assertSame( 'sf4_language_graph', $data['checks'][0]['id'] );
	}

	public function test_options_cap_redirect_depth(): void {
		$opts = new SeoDiagnosticsOptions( redirect_depth: 99 );
		$this->assertSame( SeoDiagnosticsOptions::MAX_REDIRECT_DEPTH, $opts->capped_redirect_depth() );
		$this->assertSame( 1, ( new SeoDiagnosticsOptions( redirect_depth: 0 ) )->capped_redirect_depth() );
	}

	public function test_admissions_lock(): void {
		$this->assertCount( 14, SeoDiagnosticsAdmissions::supported() );
		$this->assertSame( array( 'SF15' ), SeoDiagnosticsAdmissions::partially_supported() );
		$this->assertContains( 'SE11', SeoDiagnosticsAdmissions::deferred_upstream() );
		$this->assertContains( 'SD12', SeoDiagnosticsAdmissions::deferred_upstream() );
		$this->assertTrue( SeoDiagnosticsAdmissions::is_admitted( 'SF9' ) );
		$this->assertTrue( SeoDiagnosticsAdmissions::is_admitted( 'sf15' ) );
		$this->assertFalse( SeoDiagnosticsAdmissions::is_admitted( 'SE11' ) );
	}

	public function test_no_persistence_or_discovery_classes(): void {
		$this->assertFalse( class_exists( 'AIMultilingual\\Seo\\SitemapDiscovery', false ) );
		$this->assertFalse( class_exists( 'AIMultilingual\\Seo\\SocialMeta', false ) );
		$this->assertFalse( class_exists( 'AIMultilingual\\Seo\\Diagnostics\\SeoDiagnosticsStore', false ) );
		$this->assertFalse( class_exists( 'AIMultilingual\\Seo\\Diagnostics\\SearchConsoleClient', false ) );
	}
}
