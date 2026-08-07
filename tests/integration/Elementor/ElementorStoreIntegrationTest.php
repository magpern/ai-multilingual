<?php
/**
 * Elementor Foundation integration — Store + extraction wiring.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Integration\Elementor;

use AIMultilingual\Elementor\Contract;
use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDocumentDetector;
use AIMultilingual\Elementor\ElementorExtractor;
use AIMultilingual\Elementor\ElementorIdentity;
use AIMultilingual\Elementor\ElementorOverlayApplier;
use AIMultilingual\Settings;
use AIMultilingual\Tests\Integration\AimlTestCase;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Block\BlockRegistry;

/**
 * Elementor units coexist with Store without schema changes.
 */
final class ElementorStoreIntegrationTest extends AimlTestCase {

	/**
	 * @return array{0: int, 1: string}
	 */
	private function create_elementor_page(): array {
		$payload = wp_json_encode(
			array(
				array(
					'id'         => 'hd1',
					'elType'     => 'widget',
					'widgetType' => 'heading',
					'settings'   => array( 'title' => 'Hello Elementor' ),
					'elements'   => array(),
				),
				array(
					'id'         => 'btn1',
					'elType'     => 'widget',
					'widgetType' => 'button',
					'settings'   => array( 'text' => 'Buy' ),
					'elements'   => array(),
				),
				array(
					'id'         => 'acc1',
					'elType'     => 'widget',
					'widgetType' => 'accordion',
					'settings'   => array(),
					'elements'   => array(),
				),
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'A2 Elementor Fixture',
				'post_status'  => 'publish',
				'post_content' => '<!-- elementor artifact -->',
				'post_type'    => 'page',
			)
		);
		update_post_meta( $post_id, Contract::META_DATA, $payload );
		update_post_meta( $post_id, Contract::META_EDIT_MODE, 'builder' );

		return array( (int) $post_id, (string) $payload );
	}

	private function enabled_extractor(): Extractor {
		$settings = new Settings(
			array(
				'elementor_extraction_enabled' => true,
			)
		);

		return new Extractor(
			$settings,
			null,
			new ElementorExtractor(
				new ElementorDocumentDetector(),
				new ElementorControlRegistry(),
				new ElementorIdentity()
			)
		);
	}

	public function test_extraction_and_store_roundtrip_without_schema_bump(): void {
		[ $post_id ] = $this->create_elementor_page();
		$sv          = $this->add_language( 'sv', 'sv_SE', 'published' );
		$extractor   = $this->enabled_extractor();
		$post        = get_post( $post_id );

		$segments = $extractor->extract( $post );
		$this->assertArrayHasKey( 'e:d:' . $post_id . ':hd1:title', $segments );
		$this->assertArrayHasKey( 'e:d:' . $post_id . ':btn1:text', $segments );
		$this->assertArrayNotHasKey( 'e:d:' . $post_id . ':acc1:tabs', $segments );

		$key    = 'e:d:' . $post_id . ':hd1:title';
		$result = $this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_FIELD,
				'source_text'     => 'Hello Elementor',
				'translated_text' => 'Hej Elementor',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);
		$this->assertTrue( $result );

		$row = $this->store->get( Store::SOURCE_POST, $post_id, (int) $sv->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( 'Hej Elementor', $row->translated_text );

		// Gutenberg b: keys remain disjoint.
		$this->assertFalse( str_starts_with( $key, 'b:' ) );
	}

	public function test_stale_on_source_change_via_sync(): void {
		[ $post_id ] = $this->create_elementor_page();
		$sv          = $this->add_language( 'sv', 'sv_SE', 'published' );
		$key         = 'e:d:' . $post_id . ':hd1:title';

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_FIELD,
				'source_text'     => 'Hello Elementor',
				'translated_text' => 'Hej Elementor',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$assembler = new SegmentAssembler( $this->enabled_extractor(), $this->store, new BlockRegistry() );
		$post      = get_post( $post_id );

		// Change source meta.
		$raw                          = get_post_meta( $post_id, Contract::META_DATA, true );
		$data                         = json_decode( (string) $raw, true );
		$data[0]['settings']['title'] = 'Hello Elementor Changed';
		update_post_meta( $post_id, Contract::META_DATA, wp_json_encode( $data ) );

		$dtos = $assembler->assemble_for_post( $post, (int) $sv->language_id );
		$hit  = null;
		foreach ( $dtos as $dto ) {
			if ( ( $dto['segment_key'] ?? '' ) === $key ) {
				$hit = $dto;
				break;
			}
		}
		$this->assertNotNull( $hit );
		$this->assertTrue( (bool) $hit['is_stale'] );
		$this->assertSame( 'elementor', $hit['meta']['surface'] ?? '' );
	}

	public function test_overlay_applier_does_not_fail_whole_document(): void {
		$applier = new ElementorOverlayApplier( new ElementorControlRegistry() );
		$units   = ( new ElementorExtractor(
			new ElementorDocumentDetector(),
			new ElementorControlRegistry(),
			new ElementorIdentity()
		) )->extract_from_elements(
			10,
			array(
				array(
					'id'         => 'hd1',
					'widgetType' => 'heading',
					'settings'   => array( 'title' => 'A' ),
				),
				array(
					'id'         => 'bad',
					'widgetType' => 'heading',
					'settings'   => array( 'title' => 'B' ),
				),
			)
		);

		$out = $applier->apply(
			array(
				array(
					'id'         => 'hd1',
					'widgetType' => 'heading',
					'settings'   => array( 'title' => 'A' ),
				),
				array(
					'id'         => 'bad',
					'widgetType' => 'heading',
					'settings'   => array( 'title' => 'B' ),
				),
			),
			array( 'e:d:10:hd1:title' => 'AA' ),
			$units
		);

		$this->assertSame( 'AA', $out[0]['settings']['title'] );
		$this->assertSame( 'B', $out[1]['settings']['title'] );
	}

	public function test_gutenberg_page_unaffected_by_elementor_extractor(): void {
		$post_id  = self::factory()->post->create(
			array(
				'post_title'   => 'Gutenberg only',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
				'post_type'    => 'page',
			)
		);
		$post     = get_post( $post_id );
		$segments = $this->enabled_extractor()->extract( $post );
		$e_keys   = array_filter( array_keys( $segments ), static fn( $k ) => str_starts_with( (string) $k, 'e:' ) );
		$this->assertSame( array(), $e_keys );
	}
}
