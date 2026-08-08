<?php
/**
 * WorkspaceService unit tests.
 *
 * Final collaborators cannot be doubled in PHPUnit, so conflict and serializer
 * contracts are asserted here; save/load behaviour lives in integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Workspace;

use AIMultilingual\Rest\ViewModel\WorkspaceSegmentSerializer;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\WorkspaceConflictException;
use AIMultilingual\Workspace\WorkspaceService;
use PHPUnit\Framework\TestCase;

/**
 * Workspace application-layer unit coverage.
 */
final class WorkspaceServiceTest extends TestCase {

	public function test_supported_post_types_include_nav_menu_item_for_a6(): void {
		$this->assertSame(
			array( 'post', 'page', 'product', 'nav_menu_item' ),
			WorkspaceService::SUPPORTED_POST_TYPES
		);
	}

	public function test_conflict_exception_carries_refreshed_segments_and_status_code(): void {
		$segments = array(
			array(
				'segment_key' => 'b:550e8400-e29b-41d4-a716-446655440000:content',
				'source_hash' => 'fresh',
			),
		);

		$exception = new WorkspaceConflictException( $segments );

		$this->assertSame( 409, $exception->getCode() );
		$this->assertSame( $segments, $exception->segments() );
	}

	public function test_segment_serializer_exposes_view_model_fields_only(): void {
		$serializer = new WorkspaceSegmentSerializer();
		$row        = $serializer->from_dto(
			array(
				'segment_key'     => 'b:550e8400-e29b-41d4-a716-446655440000:content',
				'field_key'       => 'content',
				'block_name'      => 'core/paragraph',
				'uuid'            => '550e8400-e29b-41d4-a716-446655440000',
				'segment_order'   => 1,
				'source_text'     => 'Hello',
				'source_hash'     => 'abc',
				'translated_text' => 'Hej',
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'is_stale'        => false,
				'text_format'     => Store::FORMAT_PLAIN,
				'can_edit'        => true,
				'meta'            => array(),
				'translation_id'  => 999,
			)
		)->to_array();

		$this->assertArrayHasKey( 'segment_key', $row );
		$this->assertArrayNotHasKey( 'translation_id', $row );
		$this->assertSame( 'Hej', $row['translated_text'] );
	}
}
