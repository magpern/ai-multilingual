<?php
/**
 * Site Translate coverage unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\SiteTranslate;

use AIMultilingual\SiteTranslate\SiteTranslateAdmissionService;
use AIMultilingual\SiteTranslate\SiteTranslateCoverageService;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Workspace\SegmentAssembler;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Coverage read-model unit tests.
 */
final class SiteTranslateCoverageServiceTest extends TestCase {

	public function test_zero_eligible_marks_no_extractable_work(): void {
		$extractor = $this->createMock( Extractor::class );
		$extractor->method( 'body_status' )->willReturn( Extractor::BODY_OK );

		$assembler = $this->createMock( SegmentAssembler::class );
		$assembler->method( 'assemble_for_post' )->willReturn( array() );

		$admission = $this->createMock( SiteTranslateAdmissionService::class );
		$admission->method( 'body_surface' )->willReturn( Extractor::BODY_OK );
		$admission->method( 'is_strategy_f_fully_valid' )->willReturn( true );

		$service = new SiteTranslateCoverageService(
			$assembler,
			$extractor,
			$admission,
			null
		);

		$post = new WP_Post( (object) array( 'ID' => 1, 'post_type' => 'page' ) );
		$coverage = $service->coverage_for_post( $post, 2 );

		$this->assertSame( 0, $coverage['eligible_total'] );
		$this->assertTrue( $coverage['no_extractable_work'] );
		$this->assertContains( 'zero_eligible', $coverage['blocked_or_unsupported'] );
		$this->assertFalse( $coverage['translation_complete'] );
	}
}
