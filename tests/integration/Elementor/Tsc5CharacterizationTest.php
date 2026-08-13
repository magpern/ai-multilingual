<?php
/**
 * TSC.5 Elementor characterization baseline proofs.
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

/**
 * TSC5.0 characterization tests — identity, registry, body ownership.
 */
final class Tsc5CharacterizationTest extends AimlTestCase {

	public function test_registry_admits_exactly_eight_widget_families(): void {
		$registry = new ElementorControlRegistry();
		$widgets  = array(
			'heading',
			'text-editor',
			'button',
			'accordion',
			'toggle',
			'image',
			'icon-list',
			'call-to-action',
		);

		foreach ( $widgets as $widget ) {
			$this->assertTrue( $registry->is_supported_widget( $widget ), $widget );
		}

		$this->assertFalse( $registry->is_supported_widget( 'testimonial' ) );
		$this->assertFalse( $registry->is_supported_widget( 'icon-box' ) );
		$this->assertCount( 8, array_unique( array_map( static fn( $e ) => $e['widget_type'], $registry->all() ) ) );
	}

	public function test_elementor_body_excludes_gutenberg_block_pipeline(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Elementor body',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
			)
		);
		update_post_meta( $post_id, Contract::META_EDIT_MODE, 'builder' );
		update_post_meta( $post_id, Contract::META_DATA, '[]' );

		$extractor = new Extractor(
			new Settings(
				array(
					'block_extraction_enabled'     => true,
					'elementor_extraction_enabled' => true,
				)
			)
		);

		$post = get_post( $post_id );
		$this->assertSame( Extractor::BODY_ELEMENTOR, $extractor->body_status( $post ) );
		$this->assertNotSame( Extractor::BODY_BLOCKS, $extractor->body_status( $post ) );
	}

	public function test_repeater_missing_id_skips_extraction(): void {
		$extractor = new ElementorExtractor(
			new ElementorDocumentDetector(),
			new ElementorControlRegistry(),
			new ElementorIdentity()
		);
		$units     = $extractor->extract_from_elements(
			1,
			array(
				array(
					'id'         => 'acc1',
					'widgetType' => 'accordion',
					'settings'   => array(
						'tabs' => array(
							array( 'tab_title' => 'No id row' ),
						),
					),
				),
			)
		);

		$this->assertSame( array(), $units );
	}

	public function test_elementor_flags_default_off(): void {
		$defaults = ( new Settings() )->get();
		$this->assertFalse( $defaults['elementor_extraction_enabled'] );
		$this->assertFalse( $defaults['elementor_frontend_rendering_enabled'] );
	}
}
