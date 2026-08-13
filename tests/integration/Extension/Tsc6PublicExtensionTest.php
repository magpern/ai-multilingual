<?php
/**
 * Integration tests for TSC.6 public extension API.
 *
 * @package AIMultilingual
 */

declare( strict_types=1);

namespace AIMultilingual\Tests\Integration\Extension;

use AIMultilingual\Extension\ExtensionServices;
use AIMultilingual\Extension\LanguageReference;
use AIMultilingual\Extension\SourceSegmentReference;
use AIMultilingual\Extension\VisitorTranslationResolver;
use AIMultilingual\Surface\Meta\RegisteredMetaDefinition;
use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Tests\Fixtures\ReferenceExtension\ReferenceExtensionBootstrap;
use AIMultilingual\Tests\Integration\AimlTestCase;
use AIMultilingual\Translation\Store;

/**
 * @coversNothing
 */
final class Tsc6PublicExtensionTest extends AimlTestCase {

	public function test_reference_extension_registers_via_public_api(): void {
		$registrar = ExtensionServices::registrar();
		$this->assertNotNull( $registrar );
		$this->assertTrue( $registrar->is_sealed() );

		$record = $registrar->internal_registry()->get( ReferenceExtensionBootstrap::EXTENSION_ID );
		$this->assertNotNull( $record );
		$this->assertSame( 1, $record->meta_count );
		$this->assertSame( 1, $record->block_count );
	}

	public function test_aiml_mark_source_dirty_coalesces_without_immediate_sync(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$this->assertTrue( aiml_mark_source_dirty( Store::SOURCE_POST, $post_id ) );
		$this->assertFalse( aiml_mark_source_dirty( Store::SOURCE_POST, 0 ) );
		$this->assertFalse( aiml_mark_source_dirty( 'invalid', $post_id ) );
	}

	public function test_public_resolver_black_box_path(): void {
		$default = $this->languages->default();
		$this->assertNotNull( $default );
		$target = $this->add_language( 'sv' );

		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		update_post_meta( $post_id, ReferenceExtensionBootstrap::META_KEY, 'Source subtitle' );

		$key = 'm:reference_ext:' . ReferenceExtensionBootstrap::META_KEY;
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'post',
				'language_id'     => (int) $target->language_id,
				'field_key'       => RegisteredMetaDefinition::FIELD_KEY,
				'segment_key'     => $key,
				'source_text'     => 'Source subtitle',
				'text_format'     => Store::FORMAT_PLAIN,
				'translated_text' => 'Källa',
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'publish_status'  => Store::PUBLISH_PUBLISHED,
			)
		);

		$resolver = ExtensionServices::resolver();
		$this->assertNotNull( $resolver );

		$context = $this->plugin_language_context( $resolver );
		$context->set_default( $default );
		$context->set_current( $target );

		$result = $resolver->resolve(
			new SourceSegmentReference( Store::SOURCE_POST, $post_id, $key ),
			new LanguageReference( 'sv' )
		);
		$this->assertNotNull( $result );
		$this->assertSame( 'Källa', $result->text );
	}

	/**
	 * Returns the LanguageContext bound to the plugin-resolved visitor resolver.
	 *
	 * @param VisitorTranslationResolver $resolver Plugin-bound resolver.
	 */
	private function plugin_language_context( VisitorTranslationResolver $resolver ): \AIMultilingual\Language\LanguageContext {
		$property = new \ReflectionProperty( VisitorTranslationResolver::class, 'context' );
		$property->setAccessible( true );

		/** @var \AIMultilingual\Language\LanguageContext $context */
		$context = $property->getValue( $resolver );

		return $context;
	}
}
