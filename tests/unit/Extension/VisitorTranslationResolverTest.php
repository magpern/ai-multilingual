<?php
/**
 * Unit tests for VisitorTranslationResolver (TSC.6).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Extension;

use AIMultilingual\Extension\ExtensionDiagnostics;
use AIMultilingual\Extension\LanguageReference;
use AIMultilingual\Extension\SourceSegmentReference;
use AIMultilingual\Extension\VisitorTranslationResolver;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationRegistry;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;
use AIMultilingual\Surface\Meta\RegisteredMetaDefinition;
use AIMultilingual\Surface\Meta\RegisteredMetaRegistry;
use AIMultilingual\Tests\Integration\AimlTestCase;
use AIMultilingual\Translation\Store;

/**
 * @covers \AIMultilingual\Extension\VisitorTranslationResolver
 */
final class VisitorTranslationResolverTest extends AimlTestCase {

	private VisitorTranslationResolver $resolver;

	private RegisteredMetaRegistry $meta_registry;

	protected function setUp(): void {
		parent::setUp();

		$this->meta_registry = new RegisteredMetaRegistry();
		$this->meta_registry->register(
			new RegisteredMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: '_demo_field',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'Demo',
			)
		);

		$this->resolver = new VisitorTranslationResolver(
			$this->store,
			$this->languages,
			$this->context,
			$this->meta_registry,
			new IntegrationRegistry(),
			new PluginIdentity(),
			new ExtensionDiagnostics()
		);
	}

	public function test_resolver_requires_complete_source_identity(): void {
		$default = $this->languages->default();
		$this->assertNotNull( $default );
		$this->context->set_default( $default );
		$this->context->set_current( $default );

		$result = $this->resolver->resolve(
			new SourceSegmentReference( Store::SOURCE_POST, 0, 'm:demo:_demo_field' ),
			new LanguageReference( 'sv' )
		);
		$this->assertNull( $result );
	}

	public function test_source_language_returns_null(): void {
		$default = $this->languages->default();
		$this->assertNotNull( $default );
		$this->context->set_default( $default );
		$this->context->set_current( $default );

		$post_id = self::factory()->post->create();
		$result  = $this->resolver->resolve(
			new SourceSegmentReference( Store::SOURCE_POST, $post_id, 'm:demo:_demo_field' ),
			new LanguageReference( (string) $default->code )
		);
		$this->assertNull( $result );
	}

	public function test_resolver_returns_eligible_translation(): void {
		$default = $this->languages->default();
		$this->assertNotNull( $default );
		$target = $this->add_language( 'sv' );

		$this->context->set_default( $default );
		$this->context->set_current( $target );

		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$key     = 'm:demo:_demo_field';

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'post',
				'language_id'     => (int) $target->language_id,
				'field_key'       => RegisteredMetaDefinition::FIELD_KEY,
				'segment_key'     => $key,
				'source_text'     => 'Hello',
				'text_format'     => Store::FORMAT_PLAIN,
				'translated_text' => 'Hej',
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'publish_status'  => Store::PUBLISH_PUBLISHED,
			)
		);

		$result = $this->resolver->resolve(
			new SourceSegmentReference( Store::SOURCE_POST, $post_id, $key ),
			new LanguageReference( 'sv' )
		);

		$this->assertNotNull( $result );
		$this->assertSame( 'Hej', $result->text );
		$this->assertTrue( $result->available );
		$this->assertSame( Store::FORMAT_PLAIN, $result->format );
	}

	public function test_resolver_accepts_language_code_not_db_id(): void {
		$this->assertTrue( Languages::is_valid_code( 'sv' ) );
		$this->assertTrue( Languages::is_valid_code( 'pt-br' ) );
	}
}
