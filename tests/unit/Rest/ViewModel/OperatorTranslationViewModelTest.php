<?php
/**
 * Unit tests for OTL ViewModel serializers.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Rest\ViewModel;

use AIMultilingual\Rest\ViewModel\OperatorTranslationDetailSerializer;
use AIMultilingual\Rest\ViewModel\OperatorTranslationListItemSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Rest\ViewModel\OperatorTranslationListItemViewModel
 * @covers \AIMultilingual\Rest\ViewModel\OperatorTranslationDetailViewModel
 */
final class OperatorTranslationViewModelTest extends TestCase {

	public function test_list_serializer_omits_heavy_payloads(): void {
		$out = ( new OperatorTranslationListItemSerializer() )->many_to_arrays(
			array(
				array(
					'translation_id'  => 9,
					'source_type'     => 'post',
					'source_id'       => 3,
					'language_code'   => 'sv',
					'status'          => 'machine_translated',
					'review_status'   => 'not_submitted',
					'publish_status'  => 'unpublished',
					'is_stale'        => false,
					'source_preview'  => 'Hello',
					'target_preview'  => 'Hej',
					'allowed_actions' => array(
						array(
							'id'          => 'publish',
							'allowed'     => false,
							'reason_code' => 'detail_only',
						),
					),
					'jobs'            => null,
					'assessment'      => array( 'should' => 'not leak' ),
					'qa'              => array( 'should' => 'not leak' ),
				),
			)
		);
		$this->assertSame( 9, $out[0]['translation_id'] );
		$this->assertArrayNotHasKey( 'assessment', $out[0] );
		$this->assertArrayNotHasKey( 'qa', $out[0] );
		$this->assertNull( $out[0]['jobs'] );
	}

	public function test_detail_serializer_keeps_authoritative_payloads(): void {
		$out = ( new OperatorTranslationDetailSerializer() )->to_array(
			array(
				'translation_id'  => 11,
				'source_text'     => 'Hello',
				'translated_text' => 'Hej',
				'qa'              => array( 'ok' => true ),
				'assessment'      => array( 'overall_category' => 'structurally_clean' ),
				'publication'     => array( 'eligible' => false ),
				'allowed_actions' => array(),
				'jobs'            => null,
			)
		);
		$this->assertSame( 'Hello', $out['source_text'] );
		$this->assertSame( 'structurally_clean', $out['assessment']['overall_category'] );
		$this->assertFalse( $out['publication']['eligible'] );
		$this->assertNull( $out['jobs'] );
	}
}
