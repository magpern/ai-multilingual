<?php
/**
 * Reference integration end-to-end (integration suite).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationDiagnostics;
use AIMultilingual\Integration\IntegrationRegistry;
use AIMultilingual\Tests\Fixtures\ReferenceIntegration\ReferenceIntegration;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

/**
 * @covers \AIMultilingual\Tests\Fixtures\ReferenceIntegration\ReferenceIntegration
 */
final class ReferenceIntegrationTest extends AimlTestCase {

	public function test_extract_store_and_overlay_source_fallback(): void {
		$post = $this->create_page( 'Ref Host', 'classic body' );
		update_post_meta( (int) $post->ID, ReferenceIntegration::META_TITLE, 'Hello EN' );
		update_post_meta( (int) $post->ID, ReferenceIntegration::META_NESTED, 'Nested EN' );

		$diag     = new IntegrationDiagnostics();
		$identity = new PluginIdentity( $diag );
		$ref      = new ReferenceIntegration( $identity );
		$registry = new IntegrationRegistry( $diag );
		$registry->register( $ref );

		$extractor = new Extractor( null, null, null, $registry );
		$segments  = $extractor->extract( $post );

		$title_key = $identity->build( ReferenceIntegration::ID, 'record', (string) $post->ID, 'title' );
		$this->assertArrayHasKey( $title_key, $segments );
		$this->assertSame( 'plugin_integration', $segments[ $title_key ]['surface'] );
		$this->assertSame( ReferenceIntegration::ID, $segments[ $title_key ]['integration_id'] );
		$this->assertSame( 'Hello EN', $segments[ $title_key ]['source_text'] );
		$this->assertSame( Contract::FIELD_KEY, $segments[ $title_key ]['field_key'] );

		$language = $this->add_language();
		$result   = $this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => (int) $language->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $title_key,
				'segment_order'   => 2000,
				'source_text'     => 'Hello EN',
				'translated_text' => 'Hej SV',
				'text_format'     => Store::FORMAT_PLAIN,
				'segment_kind'    => Store::KIND_FIELD,
			)
		);
		$this->assertTrue( $result );

		$row = $this->store->get(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$title_key
		);
		$this->assertNotNull( $row );
		$this->assertSame( 'Hej SV', (string) $row->translated_text );

		$ref->register_output_hooks(
			static function () {
				return null;
			}
		);
		$this->assertSame( 'Hello EN', apply_filters( 'aiml_reference_integration_title', 'Hello EN' ) );
		$this->assertSame( 'Hello EN', get_post_meta( (int) $post->ID, ReferenceIntegration::META_TITLE, true ) );
	}

	public function test_lifecycle_states(): void {
		$identity = new PluginIdentity();
		$ref      = new ReferenceIntegration( $identity, true, true, '1.0.0', '1.0.0', false );
		$this->assertTrue( $ref->get_compatibility()->allows_overlay() );

		$ref->configure( false, null, null, null );
		$this->assertSame( Contract::STATE_UNAVAILABLE, $ref->get_compatibility()->state() );

		$ref->configure( true, false, null, null );
		$this->assertSame( Contract::STATE_UNAVAILABLE, $ref->get_compatibility()->state() );

		$ref->configure( null, true, '0.9.0', null );
		$this->assertSame( Contract::STATE_UNSUPPORTED_VERSION, $ref->get_compatibility()->state() );

		$ref->configure( null, null, '1.0.0', true );
		$this->assertSame( Contract::STATE_DISABLED, $ref->get_compatibility()->state() );

		$ref->configure( null, null, null, false );
		$this->assertTrue( $ref->get_compatibility()->allows_overlay() );
	}

	public function test_production_plugin_does_not_autoload_fixture(): void {
		$src = file_get_contents( dirname( __DIR__, 2 ) . '/src/Plugin.php' );
		$this->assertIsString( $src );
		$this->assertStringNotContainsString( 'ReferenceIntegration', $src );
		$this->assertStringContainsString( 'aiml_register_integrations', $src );
	}
}
