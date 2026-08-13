<?php
/**
 * TSC.5 Elementor sync_source per-segment-key stale granularity.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Elementor;

use AIMultilingual\Elementor\Contract;
use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDocumentDetector;
use AIMultilingual\Elementor\ElementorExtractor;
use AIMultilingual\Elementor\ElementorIdentity;
use AIMultilingual\Settings;
use AIMultilingual\Tests\Integration\AimlTestCase;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

/**
 * @covers \AIMultilingual\Translation\Store::sync_source
 */
final class Tsc5SyncSourceGranularityTest extends AimlTestCase {

	public function test_only_changed_elementor_field_row_becomes_stale(): void {
		$language = $this->add_language();
		$post_id  = self::factory()->post->create(
			array(
				'post_title'   => 'Granularity',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '<!-- elementor -->',
			)
		);
		update_post_meta(
			$post_id,
			Contract::META_DATA,
			wp_json_encode(
				array(
					array(
						'id'         => 'hd1',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array( 'title' => 'Heading EN' ),
						'elements'   => array(),
					),
					array(
						'id'         => 'btn1',
						'elType'     => 'widget',
						'widgetType' => 'button',
						'settings'   => array( 'text' => 'Buy EN' ),
						'elements'   => array(),
					),
				)
			)
		);
		update_post_meta( $post_id, Contract::META_EDIT_MODE, 'builder' );

		$heading_key = 'e:d:' . $post_id . ':hd1:title';
		$button_key  = 'e:d:' . $post_id . ':btn1:text';

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $heading_key,
				'segment_kind'    => Store::KIND_FIELD,
				'source_text'     => 'Heading EN',
				'translated_text' => 'Rubrik SV',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		update_post_meta(
			$post_id,
			Contract::META_DATA,
			wp_json_encode(
				array(
					array(
						'id'         => 'hd1',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array( 'title' => 'Heading EN Changed' ),
						'elements'   => array(),
					),
					array(
						'id'         => 'btn1',
						'elType'     => 'widget',
						'widgetType' => 'button',
						'settings'   => array( 'text' => 'Buy EN' ),
						'elements'   => array(),
					),
				)
			)
		);

		$extractor = $this->enabled_extractor();
		$this->store->sync_source(
			Store::SOURCE_POST,
			$post_id,
			'page',
			$extractor->extract( get_post( $post_id ) )
		);

		$heading_row = $this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, $heading_key );
		$this->assertNotNull( $heading_row );
		$this->assertSame( 1, (int) $heading_row->is_stale );

		$this->assertNull(
			$this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, $button_key )
		);
	}

	private function enabled_extractor(): Extractor {
		return new Extractor(
			new Settings( array( 'elementor_extraction_enabled' => true ) ),
			null,
			new ElementorExtractor(
				new ElementorDocumentDetector(),
				new ElementorControlRegistry(),
				new ElementorIdentity()
			)
		);
	}
}
