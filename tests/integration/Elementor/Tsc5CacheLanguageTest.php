<?php
/**
 * TSC.5 Elementor cache/language isolation integration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Elementor;

use AIMultilingual\Elementor\Contract;
use AIMultilingual\Elementor\ElementorCacheInvalidation;
use AIMultilingual\Elementor\ElementorCompatibility;
use AIMultilingual\Elementor\ElementorDocumentDetector;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Settings;
use AIMultilingual\Tests\Integration\AimlTestCase;

/**
 * @covers \AIMultilingual\Elementor\ElementorCacheInvalidation
 */
final class Tsc5CacheLanguageTest extends AimlTestCase {

	public function test_language_unique_id_suffix_is_scoped(): void {
		$sv = $this->add_language( 'sv', 'sv_SE' );
		$de = $this->add_language( 'de', 'de_DE' );

		$settings = new Settings(
			array(
				'elementor_extraction_enabled'         => true,
				'elementor_frontend_rendering_enabled' => true,
			)
		);

		$context_sv = new LanguageContext();
		$context_sv->set_default( $this->languages->default() );
		$context_sv->set_current( $sv );

		$invalidation_sv = new ElementorCacheInvalidation(
			new ElementorDocumentDetector(),
			new ElementorCompatibility(),
			$settings,
			$context_sv
		);

		$context_de = new LanguageContext();
		$context_de->set_default( $this->languages->default() );
		$context_de->set_current( $de );

		$invalidation_de = new ElementorCacheInvalidation(
			new ElementorDocumentDetector(),
			new ElementorCompatibility(),
			$settings,
			$context_de
		);

		$this->assertSame( 'widget-1|aiml:sv', $invalidation_sv->language_unique_id( 'widget-1' ) );
		$this->assertSame( 'widget-1|aiml:de', $invalidation_de->language_unique_id( 'widget-1' ) );
		$this->assertNotSame(
			$invalidation_sv->language_unique_id( 'widget-1' ),
			$invalidation_de->language_unique_id( 'widget-1' )
		);
	}

	public function test_element_cache_ttl_disabled_when_overlay_enabled(): void {
		$settings = new Settings(
			array(
				'elementor_extraction_enabled'         => true,
				'elementor_frontend_rendering_enabled' => true,
			)
		);
		$invalidation = new ElementorCacheInvalidation(
			new ElementorDocumentDetector(),
			new ElementorCompatibility(),
			$settings,
			$this->context
		);

		$this->assertSame( 'disable', $invalidation->disable_elementor_element_cache( null ) );
	}

	public function test_translation_save_busts_document_element_cache(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Cache bust',
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		update_post_meta( $post_id, Contract::META_DATA, '[]' );
		update_post_meta( $post_id, Contract::META_EDIT_MODE, 'builder' );
		update_post_meta( $post_id, ElementorCacheInvalidation::DOCUMENT_CACHE_META, 'cached-html' );

		$invalidation = new ElementorCacheInvalidation(
			new ElementorDocumentDetector(),
			new ElementorCompatibility(),
			new Settings(
				array(
					'elementor_extraction_enabled'         => true,
					'elementor_frontend_rendering_enabled' => true,
				)
			),
			$this->context
		);
		$invalidation->bust_document_element_cache(
			new class( $post_id ) {
				public function __construct( private int $id ) {}
				public function get_main_id(): int {
					return $this->id;
				}
			}
		);

		$this->assertSame( '', get_post_meta( $post_id, ElementorCacheInvalidation::DOCUMENT_CACHE_META, true ) );
	}
}
