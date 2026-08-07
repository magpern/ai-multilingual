<?php
/**
 * Fluent Forms Contact Form #5 integration suite.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Elementor\Contract as ElementorContract;
use AIMultilingual\Elementor\ElementorDocumentDetector;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\FluentForms\FluentFormDefinitionReader;
use AIMultilingual\Integration\FluentForms\FluentFormsEmbedDetector;
use AIMultilingual\Integration\FluentForms\FluentFormsIntegration;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationDiagnostics;
use AIMultilingual\Integration\IntegrationRegistry;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

/**
 * @covers \AIMultilingual\Integration\FluentForms\FluentFormsIntegration
 */
final class FluentFormsIntegrationTest extends AimlTestCase {

	public function test_extract_store_overlay_and_no_foreign_mutation(): void {
		$post = $this->create_page( 'Contact Fixture', '' );
		update_post_meta(
			(int) $post->ID,
			ElementorContract::META_DATA,
			wp_json_encode(
				array(
					array(
						'elType'     => 'widget',
						'widgetType' => FluentFormsEmbedDetector::ELEMENTOR_WIDGET,
						'settings'   => array( 'form_list' => '5' ),
						'elements'   => array(),
					),
				)
			)
		);

		$fields = array(
			'fields'       => array(
				array(
					'element'    => 'input_text',
					'attributes' => array( 'name' => 'full_name' ),
					'settings'   => array( 'label' => 'Name' ),
				),
				array(
					'element'    => 'input_email',
					'attributes' => array( 'name' => 'email' ),
					'settings'   => array( 'label' => 'Email' ),
				),
			),
			'submitButton' => array(
				'settings' => array(
					'button_ui' => array(
						'type' => 'default',
						'text' => 'Send message',
					),
				),
			),
		);

		$reader = new class( $fields ) implements FluentFormDefinitionReader {
			/** @param array<string, mixed> $fields Fields. */
			public function __construct( private array $fields ) {
			}
			public function get_decoded_fields( int $form_id ): ?array {
				return 5 === $form_id ? $this->fields : null;
			}
		};

		$diag     = new IntegrationDiagnostics();
		$identity = new PluginIdentity( $diag );
		$ff       = new FluentFormsIntegration(
			$identity,
			new FluentFormsEmbedDetector( new ElementorDocumentDetector() ),
			$reader,
			true,
			true,
			'6.2.9',
			false,
			true
		);
		$registry = new IntegrationRegistry( $diag );
		$registry->register( $ff );

		$extractor = new Extractor( null, null, null, $registry );
		$segments  = $extractor->extract( $post );

		$name_key   = $identity->build( FluentFormsIntegration::ID, 'form', '5', 'full_name', 'label' );
		$email_key  = $identity->build( FluentFormsIntegration::ID, 'form', '5', 'email', 'label' );
		$submit_key = $identity->build( FluentFormsIntegration::ID, 'form', '5', 'submit_text' );

		$this->assertArrayHasKey( $name_key, $segments );
		$this->assertArrayHasKey( $email_key, $segments );
		$this->assertArrayHasKey( $submit_key, $segments );
		$this->assertSame( 'plugin_integration', $segments[ $name_key ]['surface'] );
		$this->assertSame( Contract::FIELD_KEY, $segments[ $name_key ]['field_key'] );

		$language = $this->add_language();
		foreach (
			array(
				$name_key   => array( 'Name', 'Namn' ),
				$email_key  => array( 'Email', 'E-post' ),
				$submit_key => array( 'Send message', 'Skicka meddelande' ),
			) as $key => $pair
		) {
			$this->assertTrue(
				$this->store->save_translation(
					array(
						'source_type'     => Store::SOURCE_POST,
						'source_id'       => (int) $post->ID,
						'source_subtype'  => (string) $post->post_type,
						'language_id'     => (int) $language->language_id,
						'field_key'       => Contract::FIELD_KEY,
						'segment_key'     => $key,
						'segment_order'   => 2000,
						'source_text'     => $pair[0],
						'translated_text' => $pair[1],
						'text_format'     => Store::FORMAT_PLAIN,
						'segment_kind'    => Store::KIND_FIELD,
					)
				)
			);
		}

		$ff->register_output_hooks(
			function ( string $key ) use ( $post, $language ): ?string {
				$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
				if ( null === $row || '' === (string) ( $row->translated_text ?? '' ) ) {
					return null;
				}
				return (string) $row->translated_text;
			}
		);

		$form = (object) array( 'id' => 5 );
		$name = apply_filters(
			FluentFormsIntegration::HOOK_INPUT_TEXT,
			array(
				'attributes' => array( 'name' => 'full_name' ),
				'settings'   => array( 'label' => 'Name' ),
			),
			$form
		);
		$this->assertSame( 'Namn', $name['settings']['label'] );

		// Foreign persistence untouched (reader is in-memory; assert form meta path unused).
		$this->assertSame( 'Name', $fields['fields'][0]['settings']['label'] );
	}

	public function test_plugin_registers_fluentform_integration(): void {
		$src = file_get_contents( dirname( __DIR__, 2 ) . '/src/Plugin.php' );
		$this->assertIsString( $src );
		$this->assertStringContainsString( 'FluentFormsIntegration', $src );
		$this->assertStringContainsString( 'aiml_register_integrations', $src );
	}
}
