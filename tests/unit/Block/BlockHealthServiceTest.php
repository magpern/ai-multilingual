<?php
/**
 * Strategy F block health scan options unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\BlockHealthScanOptions;
use AIMultilingual\Block\BlockHealthSnapshot;
use AIMultilingual\Block\BlockIdentityCompliance;
use PHPUnit\Framework\TestCase;

/**
 * Pure value-object and option tests for block health scanning.
 */
final class BlockHealthServiceTest extends TestCase {

	public function test_default_sample_size_is_bounded(): void {
		$options = new BlockHealthScanOptions();

		$this->assertSame( BlockHealthScanOptions::DEFAULT_SAMPLE_SIZE, $options->sample_size );
		$this->assertFalse( $options->full_scan );
		$this->assertSame( 100, $options->normalized_sample_size() );
	}

	public function test_sample_size_is_clamped_to_maximum(): void {
		$options = new BlockHealthScanOptions( sample_size: 5000 );

		$this->assertSame( BlockHealthScanOptions::MAX_SAMPLE_SIZE, $options->normalized_sample_size() );
	}

	public function test_sample_size_minimum_is_one(): void {
		$options = new BlockHealthScanOptions( sample_size: 0 );

		$this->assertSame( 1, $options->normalized_sample_size() );
	}

	public function test_compliance_helper(): void {
		$this->assertTrue( ( new BlockIdentityCompliance() )->is_compliant() );
		$this->assertFalse( ( new BlockIdentityCompliance( 1, 1 ) )->is_compliant() );
	}

	public function test_snapshot_serializes_required_fields(): void {
		$snapshot = new BlockHealthSnapshot(
			'2026-07-27T09:00:00+00:00',
			BlockHealthSnapshot::SCAN_MODE_SAMPLE,
			100,
			0,
			0,
			0,
			0,
			0,
			array(),
			0,
			0,
			0,
			0,
			0,
			0,
			0,
			0,
			null,
			false,
			array(),
			array( 'sample_incomplete' ),
			false,
			true,
			12
		);

		$array = $snapshot->to_array();

		$this->assertSame( 'sample', $array['scan_mode'] );
		$this->assertSame( 100, $array['requested_sample_size'] );
		$this->assertSame( 12, $array['elapsed_ms'] );
		$this->assertArrayNotHasKey( 'post_results', $array );
	}
}
