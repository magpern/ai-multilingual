<?php
/**
 * TSC.4 lookup grammar widening integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\BlockTranslationLookup;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

/**
 * @covers \AIMultilingual\Translation\BlockTranslationLookup
 */
final class Tsc4BlockTranslationLookupTest extends AimlTestCase {

	private const UUID = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';

	public function test_lookup_loads_all_supported_field_types(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Lookup', '<p>Body</p>' );
		$fields   = array(
			Contract::FIELD_CONTENT              => array(
				'source'     => '<p>Body</p>',
				'translated' => 'Kropp SV',
				'format'     => Store::FORMAT_HTML,
			),
			Contract::FIELD_CITATION             => array(
				'source'     => 'Cite EN',
				'translated' => 'Citat SV',
				'format'     => Store::FORMAT_HTML,
			),
			Contract::FIELD_SUMMARY              => array(
				'source'     => 'Summary EN',
				'translated' => 'Sammanfattning SV',
				'format'     => Store::FORMAT_HTML,
			),
			Contract::FIELD_CAPTION              => array(
				'source'     => 'Caption EN',
				'translated' => 'Bildtext SV',
				'format'     => Store::FORMAT_HTML,
			),
			Contract::FIELD_FILE_NAME            => array(
				'source'     => 'File EN',
				'translated' => 'Fil SV',
				'format'     => Store::FORMAT_PLAIN,
			),
			Contract::FIELD_DOWNLOAD_BUTTON_TEXT => array(
				'source'     => 'Download EN',
				'translated' => 'Ladda ner SV',
				'format'     => Store::FORMAT_PLAIN,
			),
		);

		foreach ( $fields as $field => $payload ) {
			$this->store->save_translation(
				array(
					'source_type'     => Store::SOURCE_POST,
					'source_id'       => (int) $post->ID,
					'source_subtype'  => 'page',
					'language_id'     => (int) $language->language_id,
					'field_key'       => Extractor::FIELD_CONTENT,
					'segment_key'     => SegmentKey::build( self::UUID, $field ),
					'segment_kind'    => Store::KIND_BLOCK,
					'text_format'     => (string) $payload['format'],
					'source_text'     => (string) $payload['source'],
					'translated_text' => (string) $payload['translated'],
					'status'          => Store::STATUS_REVIEWED,
				)
			);
		}

		$result = ( new BlockTranslationLookup( $this->store ) )->for_post(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id
		);

		$this->assertTrue( $result->successful );
		$this->assertSame( 6, $result->translated_count );
		foreach ( $fields as $field => $payload ) {
			$key = SegmentKey::build( self::UUID, $field );
			$this->assertSame( (string) $payload['translated'], $result->translations[ $key ] ?? '' );
		}
	}

	public function test_lookup_rejects_unknown_field_grammar(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Reject', '<p>Body</p>' );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => 'b:' . self::UUID . ':forgedField',
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<p>Body</p>',
				'translated_text' => 'Forged SV',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$result = ( new BlockTranslationLookup( $this->store ) )->for_post(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id
		);

		$this->assertTrue( $result->successful );
		$this->assertSame( 0, $result->translated_count );
		$this->assertSame( 1, $result->rejected_count );
	}
}
