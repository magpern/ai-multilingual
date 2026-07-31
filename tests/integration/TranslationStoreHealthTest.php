<?php
/**
 * Strategy F translation store health count integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

/**
 * Read-only Store health aggregate queries.
 */
final class TranslationStoreHealthTest extends AimlTestCase {

	private const UUID = '550e8400-e29b-41d4-a716-446655440000';

	public function test_count_block_segments_scopes_by_source_id(): void {
		$language = $this->add_language();
		$post_a   = $this->create_block_page( self::UUID, 'Alpha' );
		$post_b   = $this->create_block_page( '6ba7b810-9dad-41d1-80b4-00c04fd430c8', 'Beta' );

		$this->save_block_translation( $post_a, $language, self::UUID, '<p>Alpha</p>', 'Alpha SV' );
		$this->save_block_translation( $post_b, $language, '6ba7b810-9dad-41d1-80b4-00c04fd430c8', '<p>Beta</p>', 'Beta SV' );

		$this->assertSame( 1, $this->store->count_block_segments( Store::SOURCE_POST, (int) $post_a->ID ) );
		$this->assertSame( 2, $this->store->count_block_segments( Store::SOURCE_POST ) );
	}

	public function test_count_translated_and_renderable_block_segments(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page( self::UUID, 'Renderable' );

		$this->save_block_translation(
			$post,
			$language,
			self::UUID,
			'<p>Renderable</p>',
			'Renderable SV',
			Store::STATUS_REVIEWED
		);

		$this->assertSame( 1, $this->store->count_translated_block_segments( Store::SOURCE_POST, (int) $post->ID ) );
		$this->assertSame( 1, $this->store->count_renderable_block_segments( Store::SOURCE_POST, (int) $post->ID ) );
		$this->assertSame(
			1,
			$this->store->count_renderable_block_segments( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id )
		);
	}

	public function test_count_stale_block_segments(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page( self::UUID, 'Stale body' );

		$key = SegmentKey::build( self::UUID, Contract::FIELD_CONTENT );
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
				'source_text'     => '<p>Old body</p>',
				'translated_text' => 'Old SV',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$this->store->sync_source(
			Store::SOURCE_POST,
			(int) $post->ID,
			'page',
			array(
				$key => array(
					'source_text'  => '<p>Stale body</p>',
					'text_format'  => Store::FORMAT_HTML,
					'segment_kind' => Store::KIND_BLOCK,
				),
			)
		);

		$this->assertSame( 1, $this->store->count_stale_block_segments( Store::SOURCE_POST, (int) $post->ID ) );
		$this->assertSame( 0, $this->store->count_renderable_block_segments( Store::SOURCE_POST, (int) $post->ID ) );
	}

	public function test_count_orphaned_block_segments(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page( self::UUID, 'Orphan' );

		$key = SegmentKey::build( self::UUID, Contract::FIELD_CONTENT );
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
				'source_text'     => '<p>Orphan</p>',
				'translated_text' => 'Orphan SV',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$this->store->sync_source(
			Store::SOURCE_POST,
			(int) $post->ID,
			'page',
			array()
		);

		$this->assertSame( 1, $this->store->count_orphaned_block_segments( Store::SOURCE_POST, (int) $post->ID ) );
	}

	public function test_duplicate_segment_rows_are_not_detectable(): void {
		$this->assertFalse( $this->store->duplicate_segment_rows_detectable() );
		$this->assertSame( 0, $this->store->count_duplicate_segment_rows( Store::SOURCE_POST ) );
	}

	public function test_health_count_methods_do_not_write(): void {
		global $wpdb;

		$language = $this->add_language();
		$post     = $this->create_block_page( self::UUID, 'Read only' );
		$this->save_block_translation( $post, $language, self::UUID, '<p>Read only</p>', 'Read only SV' );

		$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . \AIMultilingual\Database\Schema::translations() ); // phpcs:ignore WordPress.DB

		$this->store->count_block_segments( Store::SOURCE_POST, (int) $post->ID );
		$this->store->count_translated_block_segments( Store::SOURCE_POST, (int) $post->ID );
		$this->store->count_renderable_block_segments( Store::SOURCE_POST, (int) $post->ID );
		$this->store->count_stale_block_segments( Store::SOURCE_POST, (int) $post->ID );
		$this->store->count_orphaned_block_segments( Store::SOURCE_POST, (int) $post->ID );

		$after = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . \AIMultilingual\Database\Schema::translations() ); // phpcs:ignore WordPress.DB

		$this->assertSame( $before, $after );
	}

	public function test_translations_table_exists_in_integration_suite(): void {
		$this->assertTrue( $this->store->translations_table_exists() );
	}

	/**
	 * @param string $uuid Block UUID.
	 * @param string $text Paragraph text.
	 */
	private function create_block_page( string $uuid, string $text ): \WP_Post {
		return $this->create_page(
			'Health ' . $text,
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid,
				$text
			)
		);
	}

	/**
	 * @param \WP_Post $post        Post.
	 * @param object   $language    Language row.
	 * @param string   $uuid        Block UUID.
	 * @param string   $source_text Source HTML.
	 * @param string   $translation Translated text.
	 * @param string   $status      Segment status.
	 */
	private function save_block_translation(
		\WP_Post $post,
		object $language,
		string $uuid,
		string $source_text,
		string $translation,
		string $status = Store::STATUS_REVIEWED
	): void {
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
				'source_text'     => $source_text,
				'translated_text' => $translation,
				'status'          => $status,
			)
		);
	}
}
