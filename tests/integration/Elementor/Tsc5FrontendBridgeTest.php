<?php
/**
 * TSC.5 Elementor frontend context matrix integration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Elementor;

use AIMultilingual\Elementor\Contract;
use AIMultilingual\Elementor\ElementorCompatibility;
use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDocumentDetector;
use AIMultilingual\Elementor\ElementorExtractor;
use AIMultilingual\Elementor\ElementorFrontendBridge;
use AIMultilingual\Elementor\ElementorIdentity;
use AIMultilingual\Elementor\ElementorOverlayApplier;
use AIMultilingual\Elementor\ElementorOverlayResolver;
use AIMultilingual\Elementor\ElementorRenderContextGate;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Settings;
use AIMultilingual\Tests\Integration\AimlTestCase;
use AIMultilingual\Translation\Store;

/**
 * @covers \AIMultilingual\Elementor\ElementorFrontendBridge
 */
final class Tsc5FrontendBridgeTest extends AimlTestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['aiml_test_elementor_overlays_allowed'] );
		unset( $GLOBALS['aiml_test_elementor_edit_mode'], $GLOBALS['aiml_test_elementor_preview_mode'] );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_is_json_request' );
		parent::tearDown();
	}

	private function bridge( Settings $settings, LanguageContext $context ): ElementorFrontendBridge {
		$registry                                        = new ElementorControlRegistry();
		$identity                                        = new ElementorIdentity();
		$GLOBALS['aiml_test_elementor_overlays_allowed'] = true;

		return new ElementorFrontendBridge(
			$settings,
			$context,
			new ElementorCompatibility(),
			new ElementorDocumentDetector(),
			new ElementorExtractor( new ElementorDocumentDetector(), $registry, $identity ),
			new ElementorOverlayResolver( $this->store ),
			new ElementorOverlayApplier( $registry ),
			null,
			new ElementorRenderContextGate()
		);
	}

	/**
	 * @return array{0: int, 1: array<int, mixed>}
	 */
	private function fixture(): array {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Bridge',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '<!-- elementor -->',
			)
		);
		$data    = array(
			array(
				'id'         => 'hd1',
				'elType'     => 'widget',
				'widgetType' => 'heading',
				'settings'   => array( 'title' => 'Source Title' ),
				'elements'   => array(),
			),
		);
		update_post_meta( $post_id, Contract::META_DATA, wp_json_encode( $data ) );
		update_post_meta( $post_id, Contract::META_EDIT_MODE, 'builder' );

		return array( (int) $post_id, $data );
	}

	public function test_visitor_frontend_applies_overlay(): void {
		$sv                 = $this->add_language();
		[ $post_id, $data ] = $this->fixture();
		$key                = 'e:d:' . $post_id . ':hd1:title';

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'page',
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_FIELD,
				'source_text'     => 'Source Title',
				'translated_text' => 'Besökare SV',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$settings = new Settings(
			array(
				'elementor_extraction_enabled'         => true,
				'elementor_frontend_rendering_enabled' => true,
			)
		);
		$this->context->set_current( $sv );
		$bridge = $this->bridge( $settings, $this->context );

		$out = $bridge->filter_builder_content_data( $data, $post_id );
		$this->assertSame( 'Besökare SV', $out[0]['settings']['title'] );
	}

	public function test_source_language_returns_canonical(): void {
		$sv                 = $this->add_language();
		[ $post_id, $data ] = $this->fixture();
		$key                = 'e:d:' . $post_id . ':hd1:title';

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_FIELD,
				'source_text'     => 'Source Title',
				'translated_text' => 'Besökare SV',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$settings = new Settings(
			array(
				'elementor_extraction_enabled'         => true,
				'elementor_frontend_rendering_enabled' => true,
			)
		);
		$this->context->set_current( $this->languages->default() );
		$bridge = $this->bridge( $settings, $this->context );

		$out = $bridge->filter_builder_content_data( $data, $post_id );
		$this->assertSame( 'Source Title', $out[0]['settings']['title'] );
	}

	public function test_elementor_editor_mode_returns_canonical(): void {
		$sv                 = $this->add_language();
		[ $post_id, $data ] = $this->fixture();
		$key                = 'e:d:' . $post_id . ':hd1:title';

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_FIELD,
				'source_text'     => 'Source Title',
				'translated_text' => 'Besökare SV',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$GLOBALS['aiml_test_elementor_edit_mode'] = true;

		$settings = new Settings(
			array(
				'elementor_extraction_enabled'         => true,
				'elementor_frontend_rendering_enabled' => true,
			)
		);
		$this->context->set_current( $sv );
		$bridge = $this->bridge( $settings, $this->context );

		$out = $bridge->filter_builder_content_data( $data, $post_id );
		$this->assertSame( 'Source Title', $out[0]['settings']['title'] );

		unset( $GLOBALS['aiml_test_elementor_edit_mode'] );
	}

	public function test_elementor_preview_mode_returns_canonical(): void {
		$sv                 = $this->add_language();
		[ $post_id, $data ] = $this->fixture();

		$GLOBALS['aiml_test_elementor_preview_mode'] = true;

		$settings = new Settings(
			array(
				'elementor_extraction_enabled'         => true,
				'elementor_frontend_rendering_enabled' => true,
			)
		);
		$this->context->set_current( $sv );
		$bridge = $this->bridge( $settings, $this->context );

		$out = $bridge->filter_builder_content_data( $data, $post_id );
		$this->assertSame( 'Source Title', $out[0]['settings']['title'] );

		unset( $GLOBALS['aiml_test_elementor_preview_mode'] );
	}

	public function test_editor_ajax_is_canonical_visitor_ajax_may_overlay(): void {
		$gate = new ElementorRenderContextGate();

		$GLOBALS['aiml_test_elementor_edit_mode'] = true;
		add_filter( 'wp_doing_ajax', static fn() => true );
		$this->assertTrue( $gate->is_elementor_internal_ajax() );
		$this->assertFalse( $gate->overlay_allowed() );
		remove_all_filters( 'wp_doing_ajax' );
		unset( $GLOBALS['aiml_test_elementor_edit_mode'] );

		$GLOBALS['aiml_test_elementor_edit_mode']    = false;
		$GLOBALS['aiml_test_elementor_preview_mode'] = false;
		add_filter( 'wp_doing_ajax', static fn() => true );
		$this->assertFalse( $gate->is_elementor_internal_ajax() );
		$this->assertTrue( $gate->overlay_allowed() );
		remove_all_filters( 'wp_doing_ajax' );
		unset( $GLOBALS['aiml_test_elementor_edit_mode'], $GLOBALS['aiml_test_elementor_preview_mode'] );
	}

	public function test_rest_request_denies_overlay(): void {
		add_filter( 'wp_is_json_request', static fn() => true );
		$sv                 = $this->add_language();
		[ $post_id, $data ] = $this->fixture();
		$settings           = new Settings(
			array(
				'elementor_extraction_enabled'         => true,
				'elementor_frontend_rendering_enabled' => true,
			)
		);
		$this->context->set_current( $sv );
		$bridge = $this->bridge( $settings, $this->context );

		$out = $bridge->filter_builder_content_data( $data, $post_id );
		$this->assertSame( 'Source Title', $out[0]['settings']['title'] );
		remove_all_filters( 'wp_is_json_request' );
	}
}
