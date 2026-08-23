<?php
/**
 * M5-A private CPT chrome admission and host-independent resolve tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Extension;

use AIMultilingual\Extension\ExtensionDiagnostics;
use AIMultilingual\Extension\ExtensionServices;
use AIMultilingual\Extension\LanguageReference;
use AIMultilingual\Extension\SourceSegmentReference;
use AIMultilingual\Extension\VisitorLanguageContext;
use AIMultilingual\Extension\VisitorTranslationResolver;
use AIMultilingual\Integration\ChromeOwnedSurfaceDeclaration;
use AIMultilingual\Integration\DeclaresChromeOwnedSurfaces;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationAdmission;
use AIMultilingual\Integration\IntegrationAdmissionRegistry;
use AIMultilingual\Integration\IntegrationDiagnostics;
use AIMultilingual\Integration\IntegrationRegistry;
use AIMultilingual\Integration\CompatibilityStatus;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\PluginIntegrationInterface;
use AIMultilingual\Integration\TranslationUnitDescriptor;
use AIMultilingual\Tests\Fixtures\ReferenceIntegration\ChromeReferenceIntegration;
use AIMultilingual\Tests\Fixtures\ReferenceIntegration\ReferenceIntegration;
use AIMultilingual\Tests\Integration\AimlTestCase;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * @coversNothing
 */
final class M5aChromeAdmissionTest extends AimlTestCase {

	private IntegrationDiagnostics $diagnostics;

	private PluginIdentity $identity;

	private IntegrationRegistry $registry;

	private IntegrationAdmissionRegistry $admission;

	private VisitorTranslationResolver $visitor_resolver;

	protected function setUp(): void {
		parent::setUp();

		ChromeReferenceIntegration::register_post_type();

		$this->diagnostics = new IntegrationDiagnostics();
		$this->identity    = new PluginIdentity( $this->diagnostics );
		$this->registry    = new IntegrationRegistry( $this->diagnostics );
		$this->registry->register( new ChromeReferenceIntegration( $this->identity ) );
		$this->registry->register( new ReferenceIntegration( $this->identity ) );

		$this->admission = new IntegrationAdmissionRegistry( $this->diagnostics, $this->identity );
		$this->admission->collect_from_registry( $this->registry );
		$this->admission->validate_and_activate();
		IntegrationAdmission::bind( $this->admission );

		$this->extractor = new Extractor( null, null, null, $this->registry, null, $this->admission );

		$this->visitor_resolver = new VisitorTranslationResolver(
			$this->store,
			$this->languages,
			$this->context,
			new \AIMultilingual\Surface\Meta\RegisteredMetaRegistry(),
			$this->registry,
			$this->identity,
			new ExtensionDiagnostics(),
			$this->admission
		);
	}

	protected function tearDown(): void {
		IntegrationAdmission::reset_for_tests();
		parent::tearDown();
	}

	public function test_existing_integration_without_companion_remains_compatible(): void {
		$ref = $this->registry->get( ReferenceIntegration::ID );
		$this->assertNotNull( $ref );
		$this->assertNotInstanceOf( DeclaresChromeOwnedSurfaces::class, $ref );
		$this->assertTrue( $ref->get_compatibility()->allows_overlay() );
	}

	public function test_valid_chrome_field_extracts_without_natives(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => ChromeReferenceIntegration::CPT,
				'post_status'  => 'publish',
				'post_title'   => 'Admin Label',
				'post_content' => 'Should not extract',
			)
		);
		update_post_meta( $post_id, ChromeReferenceIntegration::META_BODY, 'Hello {{token}}' );

		$post     = get_post( $post_id );
		$segments = $this->extractor->extract( $post );
		$this->assertArrayNotHasKey( 'post_title', $segments );
		$this->assertArrayNotHasKey( 'post_content', $segments );

		$key = $this->identity->build(
			ChromeReferenceIntegration::ID,
			ChromeReferenceIntegration::OWNER_TYPE,
			(string) $post_id,
			ChromeReferenceIntegration::FIELD_BODY
		);
		$this->assertArrayHasKey( $key, $segments );
		$this->assertSame( 'Hello {{token}}', $segments[ $key ]['source_text'] );
	}

	public function test_host_independent_resolve_on_unrelated_page(): void {
		$default = $this->languages->default();
		$target  = $this->add_language( 'sv' );
		$this->context->set_default( $default );
		$this->context->set_current( $target );

		$chrome_id = self::factory()->post->create(
			array(
				'post_type'   => ChromeReferenceIntegration::CPT,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $chrome_id, ChromeReferenceIntegration::META_BODY, 'Source body' );
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertNotSame( $chrome_id, $page_id );

		$key = $this->identity->build(
			ChromeReferenceIntegration::ID,
			ChromeReferenceIntegration::OWNER_TYPE,
			(string) $chrome_id,
			ChromeReferenceIntegration::FIELD_BODY
		);
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $chrome_id,
				'source_subtype'  => ChromeReferenceIntegration::CPT,
				'language_id'     => (int) $target->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'source_text'     => 'Source body',
				'text_format'     => Store::FORMAT_HTML,
				'translated_text' => 'Källtext',
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'publish_status'  => Store::PUBLISH_PUBLISHED,
			)
		);

		$result = $this->visitor_resolver->resolve(
			new SourceSegmentReference( Store::SOURCE_POST, $chrome_id, $key ),
			new LanguageReference( 'sv' )
		);
		$this->assertNotNull( $result );
		$this->assertSame( 'Källtext', $result->text );
	}

	public function test_undeclared_field_and_mismatched_owner_return_null(): void {
		$default = $this->languages->default();
		$target  = $this->add_language( 'sv' );
		$this->context->set_default( $default );
		$this->context->set_current( $target );

		$chrome_id = self::factory()->post->create(
			array(
				'post_type'   => ChromeReferenceIntegration::CPT,
				'post_status' => 'publish',
			)
		);
		$other_id  = self::factory()->post->create(
			array(
				'post_type'   => ChromeReferenceIntegration::CPT,
				'post_status' => 'publish',
			)
		);

		$bad_field = $this->identity->build(
			ChromeReferenceIntegration::ID,
			ChromeReferenceIntegration::OWNER_TYPE,
			(string) $chrome_id,
			'title'
		);
		$bad_owner = $this->identity->build(
			ChromeReferenceIntegration::ID,
			ChromeReferenceIntegration::OWNER_TYPE,
			(string) $other_id,
			ChromeReferenceIntegration::FIELD_BODY
		);

		foreach ( array( $bad_field, $bad_owner ) as $key ) {
			$this->store->save_translation(
				array(
					'source_type'     => Store::SOURCE_POST,
					'source_id'       => $chrome_id,
					'source_subtype'  => ChromeReferenceIntegration::CPT,
					'language_id'     => (int) $target->language_id,
					'field_key'       => Contract::FIELD_KEY,
					'segment_key'     => $key,
					'source_text'     => 'x',
					'text_format'     => Store::FORMAT_HTML,
					'translated_text' => 'y',
					'status'          => Store::STATUS_MANUALLY_EDITED,
					'publish_status'  => Store::PUBLISH_PUBLISHED,
				)
			);
			$this->assertNull(
				$this->visitor_resolver->resolve(
					new SourceSegmentReference( Store::SOURCE_POST, $chrome_id, $key ),
					new LanguageReference( 'sv' )
				)
			);
		}
	}

	public function test_draft_source_returns_null_even_with_published_translation(): void {
		$default = $this->languages->default();
		$target  = $this->add_language( 'sv' );
		$this->context->set_default( $default );
		$this->context->set_current( $target );

		$chrome_id = self::factory()->post->create(
			array(
				'post_type'   => ChromeReferenceIntegration::CPT,
				'post_status' => 'draft',
			)
		);
		$key       = $this->identity->build(
			ChromeReferenceIntegration::ID,
			ChromeReferenceIntegration::OWNER_TYPE,
			(string) $chrome_id,
			ChromeReferenceIntegration::FIELD_BODY
		);
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $chrome_id,
				'source_subtype'  => ChromeReferenceIntegration::CPT,
				'language_id'     => (int) $target->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'source_text'     => 'Source',
				'text_format'     => Store::FORMAT_HTML,
				'translated_text' => 'Translated',
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'publish_status'  => Store::PUBLISH_PUBLISHED,
			)
		);

		$this->assertNull(
			$this->visitor_resolver->resolve(
				new SourceSegmentReference( Store::SOURCE_POST, $chrome_id, $key ),
				new LanguageReference( 'sv' )
			)
		);
	}

	public function test_stale_chrome_translation_returns_null(): void {
		$default = $this->languages->default();
		$target  = $this->add_language( 'sv' );
		$this->context->set_default( $default );
		$this->context->set_current( $target );

		$chrome_id = self::factory()->post->create(
			array(
				'post_type'   => ChromeReferenceIntegration::CPT,
				'post_status' => 'publish',
			)
		);
		$key       = $this->identity->build(
			ChromeReferenceIntegration::ID,
			ChromeReferenceIntegration::OWNER_TYPE,
			(string) $chrome_id,
			ChromeReferenceIntegration::FIELD_BODY
		);
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $chrome_id,
				'source_subtype'  => ChromeReferenceIntegration::CPT,
				'language_id'     => (int) $target->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'source_text'     => 'Source',
				'text_format'     => Store::FORMAT_HTML,
				'translated_text' => 'Translated',
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'publish_status'  => Store::PUBLISH_PUBLISHED,
			)
		);
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			\AIMultilingual\Database\Schema::translations(),
			array( 'is_stale' => 1 ),
			array(
				'source_type' => Store::SOURCE_POST,
				'source_id'   => $chrome_id,
				'language_id' => (int) $target->language_id,
				'segment_key' => $key,
			)
		);
		$this->cache->flush_all();

		$this->assertNull(
			$this->visitor_resolver->resolve(
				new SourceSegmentReference( Store::SOURCE_POST, $chrome_id, $key ),
				new LanguageReference( 'sv' )
			)
		);
	}

	public function test_invalid_declaration_disables_only_that_surface(): void {
		$diagnostics = new IntegrationDiagnostics();
		$identity    = new PluginIdentity( $diagnostics );
		$registry    = new IntegrationRegistry( $diagnostics );

		$registry->register( new ChromeReferenceIntegration( $identity ) );
		$registry->register(
			new class( $identity ) implements PluginIntegrationInterface, DeclaresChromeOwnedSurfaces {
				public function __construct( private PluginIdentity $identity ) {
				}
				public function get_id(): string {
					return 'aiml_chrome_bad';
				}
				public function get_api_version(): string {
					return Contract::API_VERSION;
				}
				public function get_compatibility(): CompatibilityStatus {
					return new CompatibilityStatus( Contract::STATE_COMPATIBLE, 'ok' );
				}
				public function get_chrome_owned_surfaces(): array {
					return array(
						new ChromeOwnedSurfaceDeclaration(
							'missing_cpt_xyz',
							array( 'item' ),
							array( 'body' )
						),
					);
				}
				public function extract_for_post( WP_Post $post ): array {
					return array();
				}
				public function register_output_hooks( callable $resolve ): void {
				}
			}
		);

		$admission = new IntegrationAdmissionRegistry( $diagnostics, $identity );
		$admission->collect_from_registry( $registry );
		$admission->validate_and_activate();

		$this->assertTrue( $admission->admits_post_type( ChromeReferenceIntegration::CPT ) );
		$this->assertFalse( $admission->admits_post_type( 'missing_cpt_xyz' ) );
		$this->assertGreaterThan( 0, $diagnostics->snapshot()[ IntegrationDiagnostics::COUNTER_CHROME_DECLARATION_DISABLED ] ?? 0 );
		$this->assertNotNull( $registry->get( 'aiml_chrome_bad' ) );
		$this->assertNotNull( $registry->get( ChromeReferenceIntegration::ID ) );
	}

	public function test_chrome_cpt_visibility_unchanged(): void {
		$obj = get_post_type_object( ChromeReferenceIntegration::CPT );
		$this->assertNotNull( $obj );
		$this->assertFalse( (bool) $obj->public );
		$this->assertFalse( (bool) $obj->show_in_rest );
	}

	private function bind_extension_services(): void {
		$meta      = new \AIMultilingual\Surface\Meta\RegisteredMetaRegistry();
		$resolver  = new VisitorTranslationResolver(
			$this->store,
			$this->languages,
			$this->context,
			$meta,
			$this->registry,
			$this->identity,
			new ExtensionDiagnostics(),
			$this->admission
		);
		$registrar = new \AIMultilingual\Extension\ExtensionRegistrar(
			$meta,
			new \AIMultilingual\Block\AdapterRegistry(),
			new \AIMultilingual\Extension\ExtensionRegistry(),
			new ExtensionDiagnostics()
		);
		ExtensionServices::bind(
			new \AIMultilingual\Surface\RequestLocalInvalidationCoordinator(
				$this->store,
				new \AIMultilingual\Surface\SurfaceRegistry(),
				$meta
			),
			$resolver,
			$registrar,
			new ExtensionDiagnostics(),
			$this->context
		);
	}

	public function test_aiml_visitor_language_contract(): void {
		ExtensionServices::reset_for_tests();
		$this->assertNull( aiml_visitor_language() );

		$default = $this->languages->default();
		$target  = $this->add_language( 'sv' );
		$this->context->set_default( $default );
		$this->context->set_current( $target );

		$this->bind_extension_services();

		$lang = aiml_visitor_language();
		$this->assertInstanceOf( VisitorLanguageContext::class, $lang );
		$this->assertSame( 'sv', $lang->code );
		$this->assertFalse( $lang->is_default );

		$this->context->set_current( $default );
		$lang_default = aiml_visitor_language();
		$this->assertNotNull( $lang_default );
		$this->assertTrue( $lang_default->is_default );

		ExtensionServices::reset_for_tests();
	}

	public function test_dirty_helper_admits_chrome_source(): void {
		$chrome_id = self::factory()->post->create(
			array(
				'post_type'   => ChromeReferenceIntegration::CPT,
				'post_status' => 'publish',
			)
		);

		$this->bind_extension_services();

		$this->assertTrue( aiml_mark_source_dirty( Store::SOURCE_POST, $chrome_id ) );
		$this->assertFalse( aiml_mark_source_dirty( Store::SOURCE_POST, 999999 ) );

		ExtensionServices::reset_for_tests();
	}
}
