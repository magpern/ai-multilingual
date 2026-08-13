<?php
/**
 * TSC.4 sync_source per-segment-key stale granularity (A1).
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
 * @covers \AIMultilingual\Translation\Store::sync_source
 */
final class Tsc4SyncSourceGranularityTest extends AimlTestCase {

	private const UUID_Q = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
	private const UUID_C = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1';

	public function test_only_changed_field_row_becomes_stale(): void {
		$language = $this->add_language();
		$content  = sprintf(
			'<!-- wp:quote {"%1$s":"%2$s"} --><blockquote class="wp-block-quote"><p>Body original</p><cite>Citation original</cite></blockquote><!-- /wp:quote -->',
			Contract::ATTR_NAME,
			self::UUID_Q
		);
		$post     = $this->create_page( 'Multi field', $content );

		$citation_key = SegmentKey::build( self::UUID_Q, Contract::FIELD_CITATION );
		$content_key  = SegmentKey::build( self::UUID_C, Contract::FIELD_CONTENT );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => $citation_key,
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => 'Citation original',
				'translated_text' => 'Citat SV',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$updated = sprintf(
			'<!-- wp:quote {"%1$s":"%2$s"} --><blockquote class="wp-block-quote"><p>Body original</p><cite>Citation changed</cite></blockquote><!-- /wp:quote -->',
			Contract::ATTR_NAME,
			self::UUID_Q
		);
		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => $updated,
			)
		);

		$extractor = $this->enabled_extractor();
		$this->store->sync_source(
			Store::SOURCE_POST,
			(int) $post->ID,
			'page',
			$extractor->extract( get_post( $post->ID ) )
		);

		$citation_row = $this->store->get(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$citation_key
		);
		$this->assertNotNull( $citation_row );
		$this->assertSame( 1, (int) $citation_row->is_stale );
		$this->assertSame( 'Citat SV', $citation_row->translated_text );

		$this->assertNull(
			$this->store->get(
				Store::SOURCE_POST,
				(int) $post->ID,
				(int) $language->language_id,
				$content_key
			)
		);
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
