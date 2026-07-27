<?php
/**
 * Strategy F block extraction integration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Settings;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

/**
 * Block extraction through Extractor and sync_source reconciliation.
 */
final class BlockExtractionTest extends AimlTestCase {

	public function test_extraction_disabled_leaves_block_body_unextracted(): void {
		$post = $this->create_page(
			'Blocks',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"550e8400-e29b-41d4-a716-446655440000"} --><p>Block body.</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME
			)
		);

		$segments = $this->extractor->extract( $post );

		$this->assertArrayNotHasKey( Extractor::FIELD_CONTENT, $segments );
		$this->assertSame(
			array(),
			array_filter(
				array_keys( $segments ),
				static fn( string $key ): bool => str_starts_with( $key, 'b:' )
			)
		);
	}

	public function test_extraction_enabled_produces_block_segments(): void {
		$uuid    = '550e8400-e29b-41d4-a716-446655440000';
		$content = sprintf(
			'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Hello blocks</p><!-- /wp:paragraph -->',
			Contract::ATTR_NAME,
			$uuid
		);
		$post    = $this->create_page( 'Blocks', $content );

		$extractor = $this->enabled_extractor();
		$segments  = $extractor->extract( $post );

		$key = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$this->assertArrayHasKey( $key, $segments );
		$this->assertSame( Store::KIND_BLOCK, $segments[ $key ]['segment_kind'] );
		$this->assertSame( '<p>Hello blocks</p>', $segments[ $key ]['source_text'] );
		$this->assertArrayNotHasKey( Extractor::FIELD_CONTENT, $segments );
	}

	public function test_sync_source_receives_expected_block_extraction(): void {
		$language = $this->add_language();
		$uuid     = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';
		$post     = $this->create_page(
			'Sync',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Original</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid
			)
		);

		$key = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<p>Original</p>',
				'translated_text' => 'Original SV',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$extractor = $this->enabled_extractor();
		$segments  = $extractor->extract( $post );
		$this->store->sync_source(
			Store::SOURCE_POST,
			(int) $post->ID,
			'page',
			$segments
		);

		$row = $this->store->get(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key
		);

		$this->assertNotNull( $row );
		$this->assertSame( 0, (int) $row->is_stale );
		$this->assertSame( 'Original SV', $row->translated_text );
	}

	public function test_sync_source_marks_stale_when_block_text_changes(): void {
		$language = $this->add_language();
		$uuid     = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
		$post     = $this->create_page(
			'Stale',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Changed text</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid
			)
		);

		$key = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<p>Original text</p>',
				'translated_text' => 'Original SV',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$extractor = $this->enabled_extractor();
		$this->store->sync_source(
			Store::SOURCE_POST,
			(int) $post->ID,
			'page',
			$extractor->extract( $post )
		);

		$row = $this->store->get(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key
		);

		$this->assertNotNull( $row );
		$this->assertSame( 1, (int) $row->is_stale );
		$this->assertSame( 'Original SV', $row->translated_text );
	}

	private function enabled_extractor(): Extractor {
		$settings = new Settings(
			array(
				'block_attr_registration_enabled' => true,
				'block_uuid_injection_enabled'    => true,
				'block_extraction_enabled'        => true,
			)
		);
		$registry = new AdapterRegistry();

		return new Extractor(
			$settings,
			new BlockExtractor(
				$registry,
				new BlockRegistry( $registry ),
				new BlockExtractionLogger()
			)
		);
	}
}
