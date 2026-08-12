<?php
/**
 * Unit tests for Fluent Forms host-local embed discovery integration.
 *
 * Fixture form id 5 is test-local only (not a production FORM_ID constant).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Elementor\ElementorDocumentDetector;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\FluentForms\FluentFormDefinitionReader;
use AIMultilingual\Integration\FluentForms\FluentFormsEmbedDetector;
use AIMultilingual\Integration\FluentForms\FluentFormsIntegration;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationSecurity;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * @covers \AIMultilingual\Integration\FluentForms\FluentFormsIntegration
 */
final class FluentFormsIntegrationTest extends TestCase {

	/**
	 * Fixture form id used by sample fields / identity keys in this suite.
	 */
	private const FIXTURE_FORM_ID = 5;

	public function test_identity_keys_match_frozen_contract(): void {
		$identity = new PluginIdentity();
		$this->assertSame(
			'p:fluentform:form:5:full_name:label',
			$identity->build( 'fluentform', 'form', '5', 'full_name', 'label' )
		);
		$this->assertSame(
			'p:fluentform:form:5:email:label',
			$identity->build( 'fluentform', 'form', '5', 'email', 'label' )
		);
		$this->assertSame(
			'p:fluentform:form:5:submit_text',
			$identity->build( 'fluentform', 'form', '5', 'submit_text' )
		);
		foreach (
			array(
				'p:fluentform:form:5:full_name:label',
				'p:fluentform:form:5:email:label',
				'p:fluentform:form:5:submit_text',
			) as $key
		) {
			$this->assertLessThanOrEqual( Contract::MAX_SEGMENT_KEY_LENGTH, strlen( $key ) );
		}
	}

	public function test_compatibility_matrix(): void {
		$integration = $this->make_integration( $this->sample_fields() );
		$integration->configure( true, true, '6.2.9', false, true );
		$this->assertSame( Contract::STATE_COMPATIBLE, $integration->get_compatibility()->state() );

		$integration->configure( false, null, null, null, null );
		$this->assertSame( Contract::STATE_UNAVAILABLE, $integration->get_compatibility()->state() );

		$integration->configure( true, false, null, null, null );
		$this->assertSame( Contract::STATE_UNAVAILABLE, $integration->get_compatibility()->state() );

		$integration->configure( null, true, '6.1.0', null, null );
		$this->assertSame( Contract::STATE_UNSUPPORTED_VERSION, $integration->get_compatibility()->state() );

		$integration->configure( null, null, '6.2.9', true, null );
		$this->assertSame( Contract::STATE_DISABLED, $integration->get_compatibility()->state() );

		$integration->configure( null, null, null, false, false );
		$this->assertSame( Contract::STATE_MISSING_REQUIRED_HOOK, $integration->get_compatibility()->state() );

		$integration->configure( null, null, null, null, true );
		$this->assertTrue( $integration->get_compatibility()->allows_overlay() );
	}

	public function test_extract_emits_exactly_three_units_for_embedded_form(): void {
		$integration = $this->make_integration( $this->sample_fields(), true );
		$integration->configure( true, true, '6.2.9', false, true );
		$post  = $this->fake_post( 3410 );
		$units = $integration->extract_for_post( $post );
		$this->assertCount( 3, $units );
		$keys = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertSame(
			array(
				'p:fluentform:form:5:full_name:label',
				'p:fluentform:form:5:email:label',
				'p:fluentform:form:5:submit_text',
			),
			$keys
		);
		$this->assertSame( 'Name', $units[0]->source_text );
		$this->assertSame( 'Email', $units[1]->source_text );
		$this->assertSame( 'Send message', $units[2]->source_text );
		$this->assertSame( Contract::OWNERSHIP_RECORD, $units[0]->ownership_class );
	}

	public function test_extract_empty_when_form_not_embedded(): void {
		$integration = $this->make_integration( $this->sample_fields(), false );
		$integration->configure( true, true, '6.2.9', false, true );
		$this->assertSame( array(), $integration->extract_for_post( $this->fake_post( 99 ) ) );
	}

	public function test_extract_skips_missing_field_without_fuzzy_rematch(): void {
		$fields = $this->sample_fields();
		// Rename full_name → contact_name (must not rematch).
		$fields['fields'][0]['attributes']['name'] = 'contact_name';
		$integration                               = $this->make_integration( $fields, true );
		$integration->configure( true, true, '6.2.9', false, true );
		$units = $integration->extract_for_post( $this->fake_post( 3410 ) );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertNotContains( 'p:fluentform:form:5:full_name:label', $keys );
		$this->assertContains( 'p:fluentform:form:5:email:label', $keys );
		$this->assertContains( 'p:fluentform:form:5:submit_text', $keys );
	}

	public function test_overlay_applies_plain_text_without_double_escape(): void {
		$integration = $this->make_integration( $this->sample_fields(), true );
		$integration->configure( true, true, '6.2.9', false, true );
		$map = array(
			'p:fluentform:form:5:full_name:label' => 'Namn & Co <test>',
			'p:fluentform:form:5:email:label'     => 'E-post "x"',
			'p:fluentform:form:5:submit_text'     => "Skicka 'nu'",
		);
		$integration->register_output_hooks(
			static function ( string $key ) use ( $map ): ?string {
				return $map[ $key ] ?? null;
			}
		);

		$form     = (object) array( 'id' => 5 );
		$name     = array(
			'element'    => 'input_text',
			'attributes' => array( 'name' => 'full_name' ),
			'settings'   => array( 'label' => 'Name' ),
		);
		$out_name = apply_filters( FluentFormsIntegration::HOOK_INPUT_TEXT, $name, $form );
		$this->assertSame( 'Namn & Co', $out_name['settings']['label'] );

		$email     = array(
			'element'    => 'input_email',
			'attributes' => array( 'name' => 'email' ),
			'settings'   => array( 'label' => 'Email' ),
		);
		$out_email = apply_filters( FluentFormsIntegration::HOOK_INPUT_EMAIL, $email, $form );
		$this->assertSame( 'E-post "x"', $out_email['settings']['label'] );

		$button     = array(
			'element'  => 'button',
			'settings' => array(
				'button_ui' => array(
					'type' => 'default',
					'text' => 'Send message',
				),
			),
		);
		$out_button = apply_filters( FluentFormsIntegration::HOOK_BUTTON, $button, $form );
		$this->assertSame( "Skicka 'nu'", $out_button['settings']['button_ui']['text'] );
	}

	public function test_overlay_source_fallback_and_wrong_form_untouched(): void {
		$integration = $this->make_integration( $this->sample_fields(), true );
		$integration->configure( true, true, '6.2.9', false, true );
		$integration->register_output_hooks(
			static function (): ?string {
				return null;
			}
		);
		$form = (object) array( 'id' => 5 );
		$data = array(
			'attributes' => array( 'name' => 'full_name' ),
			'settings'   => array( 'label' => 'Name' ),
		);
		$out  = apply_filters( FluentFormsIntegration::HOOK_INPUT_TEXT, $data, $form );
		$this->assertSame( 'Name', $out['settings']['label'] );

		$other = (object) array( 'id' => 1 );
		$out2  = apply_filters( FluentFormsIntegration::HOOK_INPUT_TEXT, $data, $other );
		$this->assertSame( 'Name', $out2['settings']['label'] );
	}

	public function test_wrong_field_name_untouched_on_input_text_hook(): void {
		$integration = $this->make_integration( $this->sample_fields(), true );
		$integration->configure( true, true, '6.2.9', false, true );
		$integration->register_output_hooks(
			static function (): ?string {
				return 'Translated';
			}
		);
		$form = (object) array( 'id' => 5 );
		$data = array(
			'attributes' => array( 'name' => 'requested_product' ),
			'settings'   => array( 'label' => 'Requested product' ),
		);
		$out  = apply_filters( FluentFormsIntegration::HOOK_INPUT_TEXT, $data, $form );
		$this->assertSame( 'Requested product', $out['settings']['label'] );
	}

	public function test_sanitize_plain_strips_markup(): void {
		$this->assertSame( 'Hello', IntegrationSecurity::sanitize_plain( '<b>Hello</b>' ) );
	}

	/**
	 * @param array<string, mixed> $fields   Form fields.
	 * @param bool                 $embedded Whether host-local discovery yields the fixture form.
	 */
	private function make_integration( array $fields, bool $embedded = true ): FluentFormsIntegration {
		$fixture_id = self::FIXTURE_FORM_ID;
		$reader     = new class( $fields, $fixture_id ) implements FluentFormDefinitionReader {
			/**
			 * @param array<string, mixed> $fields     Fields.
			 * @param int                  $fixture_id Fixture form id.
			 */
			public function __construct(
				private array $fields,
				private int $fixture_id
			) {
			}

			public function get_decoded_fields( int $form_id ): ?array {
				return $this->fixture_id === $form_id ? $this->fields : null;
			}
		};

		return new FluentFormsIntegration(
			new PluginIdentity(),
			new FluentFormsEmbedDetector( new ElementorDocumentDetector() ),
			$reader,
			true,
			true,
			'6.2.9',
			false,
			true,
			$embedded ? array( self::FIXTURE_FORM_ID ) : array()
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function sample_fields(): array {
		return array(
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
				array(
					'element'    => 'input_text',
					'attributes' => array( 'name' => 'requested_product' ),
					'settings'   => array( 'label' => 'Requested product' ),
				),
			),
			'submitButton' => array(
				'element'  => 'button',
				'settings' => array(
					'button_ui' => array(
						'type' => 'default',
						'text' => 'Send message',
					),
				),
			),
		);
	}

	private function fake_post( int $id ): WP_Post {
		$post               = new WP_Post();
		$post->ID           = $id;
		$post->post_content = '';
		$post->post_type    = 'page';
		$post->post_status  = 'publish';
		return $post;
	}

	protected function setUp(): void {
		parent::setUp();
		remove_all_filters( FluentFormsIntegration::HOOK_INPUT_TEXT );
		remove_all_filters( FluentFormsIntegration::HOOK_INPUT_EMAIL );
		remove_all_filters( FluentFormsIntegration::HOOK_BUTTON );
	}
}
