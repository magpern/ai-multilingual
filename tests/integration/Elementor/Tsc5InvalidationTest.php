<?php
/**
 * TSC.5 Elementor invalidation integration (A2 event contract).
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
use AIMultilingual\Surface\PostSurfaceAdapter;
use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Tests\Integration\AimlTestCase;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

/**
 * @covers \AIMultilingual\Surface\PostSurfaceAdapter
 */
final class Tsc5InvalidationTest extends AimlTestCase {

	/**
	 * @return array{0: int, 1: RequestLocalInvalidationCoordinator}
	 */
	private function wire_coordinator(): array {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Elementor invalidation',
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
						'settings'   => array( 'title' => 'Original' ),
						'elements'   => array(),
					),
				)
			)
		);
		update_post_meta( $post_id, Contract::META_EDIT_MODE, 'builder' );

		$settings  = new Settings( array( 'elementor_extraction_enabled' => true ) );
		$extractor = new Extractor(
			$settings,
			null,
			new ElementorExtractor(
				new ElementorDocumentDetector(),
				new ElementorControlRegistry(),
				new ElementorIdentity()
			)
		);
		$registry  = new SurfaceRegistry();
		$adapter   = new PostSurfaceAdapter( $settings, $extractor );
		$registry->register( $adapter );
		$coordinator = new RequestLocalInvalidationCoordinator( $this->store, $registry );
		$adapter->register_invalidation_events( $coordinator );

		return array( (int) $post_id, $coordinator );
	}

	public function test_after_save_and_shutdown_read_final_elementor_data(): void {
		[ $post_id, $coordinator ] = $this->wire_coordinator();
		$sv                         = $this->add_language();
		$key                        = 'e:d:' . $post_id . ':hd1:title';

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'page',
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_FIELD,
				'source_text'     => 'Original',
				'translated_text' => 'Original SV',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$post = get_post( $post_id );
		do_action( 'save_post', $post_id, $post, true );

		update_post_meta(
			$post_id,
			Contract::META_DATA,
			wp_json_encode(
				array(
					array(
						'id'         => 'hd1',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array( 'title' => 'Final After Elementor Save' ),
						'elements'   => array(),
					),
				)
			)
		);

		$document = new class( $post_id ) {
			public function __construct( private int $id ) {}
			public function get_main_id(): int {
				return $this->id;
			}
		};
		do_action( 'elementor/document/after_save', $document );

		$this->assertSame( 1, $coordinator->dirty_count() );
		$coordinator->flush();
		$this->assertSame( 1, $coordinator->sync_count() );

		$row = $this->store->get( Store::SOURCE_POST, $post_id, (int) $sv->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( 'Final After Elementor Save', $row->source_text );
		$this->assertTrue( (bool) $row->is_stale );
	}

	public function test_duplicate_dirty_marks_coalesce_to_one_sync(): void {
		[ $post_id, $coordinator ] = $this->wire_coordinator();
		$post                       = get_post( $post_id );

		do_action( 'save_post', $post_id, $post, true );
		$document = new class( $post_id ) {
			public function __construct( private int $id ) {}
			public function get_main_id(): int {
				return $this->id;
			}
		};
		do_action( 'elementor/document/after_save', $document );

		$this->assertSame( 1, $coordinator->dirty_count() );
		$coordinator->flush();
		$this->assertSame( 1, $coordinator->sync_count() );
	}

	public function test_direct_elementor_meta_write_does_not_mark_dirty(): void {
		[ $post_id, $coordinator ] = $this->wire_coordinator();

		update_post_meta( $post_id, Contract::META_DATA, wp_json_encode( array() ) );

		$this->assertSame( 0, $coordinator->dirty_count() );
	}
}
