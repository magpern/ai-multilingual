<?php
/**
 * Unit tests for OTL.1 OperationalAttention.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Workspace\Operator;

use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Operator\OperationalAttention;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \AIMultilingual\Workspace\Operator\OperationalAttention
 */
final class OperationalAttentionTest extends TestCase {

	public function test_reason_ids_exclude_needs_review_and_all(): void {
		$ids = OperationalAttention::reason_ids();
		$this->assertSame(
			array(
				'stale',
				'review_pending',
				'review_rejected',
				'unpublished',
				'translation_failed',
			),
			$ids
		);
		$this->assertNotContains( 'needs_review', $ids );
		$this->assertNotContains( 'all', $ids );
	}

	public function test_needs_review_preset_is_invalid(): void {
		$result = OperationalAttention::preset_to_store_filters( 'needs_review' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 422, (int) ( $result->get_error_data()['status'] ?? 0 ) );
	}

	public function test_all_and_empty_map_to_no_filters(): void {
		$this->assertSame( array(), OperationalAttention::preset_to_store_filters( 'all' ) );
		$this->assertSame( array(), OperationalAttention::preset_to_store_filters( '' ) );
	}

	public function test_presets_map_to_store_axes(): void {
		$this->assertSame( array( 'is_stale' => true ), OperationalAttention::preset_to_store_filters( 'stale' ) );
		$this->assertSame(
			array( 'review_status' => Store::REVIEW_PENDING ),
			OperationalAttention::preset_to_store_filters( 'review_pending' )
		);
		$this->assertSame(
			array( 'review_status' => Store::REVIEW_REJECTED ),
			OperationalAttention::preset_to_store_filters( 'review_rejected' )
		);
		$this->assertSame(
			array( 'publish_status' => Store::PUBLISH_UNPUBLISHED ),
			OperationalAttention::preset_to_store_filters( 'unpublished' )
		);
		$this->assertSame(
			array( 'status' => Store::STATUS_FAILED ),
			OperationalAttention::preset_to_store_filters( 'translation_failed' )
		);
	}

	public function test_reasons_for_row_are_multi_label(): void {
		$row     = (object) array(
			'is_stale'       => 1,
			'review_status'  => Store::REVIEW_PENDING,
			'publish_status' => Store::PUBLISH_UNPUBLISHED,
			'status'         => Store::STATUS_MACHINE_TRANSLATED,
		);
		$reasons = OperationalAttention::reasons_for_row( $row );
		$this->assertSame(
			array( 'stale', 'review_pending', 'unpublished' ),
			$reasons
		);
		$this->assertNotContains( 'needs_review', $reasons );
	}

	public function test_failed_adds_translation_failed(): void {
		$row = (object) array(
			'is_stale'       => 0,
			'review_status'  => Store::REVIEW_REJECTED,
			'publish_status' => Store::PUBLISH_PUBLISHED,
			'status'         => Store::STATUS_FAILED,
		);
		$this->assertSame(
			array( 'review_rejected', 'translation_failed' ),
			OperationalAttention::reasons_for_row( $row )
		);
	}
}
