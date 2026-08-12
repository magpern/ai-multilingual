<?php
/**
 * TSC.0 surface foundation integration proofs.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Glossary\GlossaryMatcher;
use AIMultilingual\Glossary\GlossaryNormalizer;
use AIMultilingual\Glossary\GlossaryRepository;
use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Jobs\BackgroundTranslationItemProcessor;
use AIMultilingual\Jobs\BackgroundTranslationItemRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobService;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobLeaseService;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Settings;
use AIMultilingual\Surface\PostSurfaceAdapter;
use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\TranslationService;
use WP_Error;

/**
 * Rank Math coalesce, Jobs registry gate, orphan short-circuit, Fluent neutrality.
 */
final class Tsc0SurfaceFoundationTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	public function test_rank_math_n_meta_updates_coalesce_to_one_sync(): void {
		$post = $this->create_page( 'Rank Math Coalesce', '<p>Body</p>' );

		$coordinator = new RequestLocalInvalidationCoordinator( $this->store, $this->extractor );
		$adapter     = new PostSurfaceAdapter( new Settings( array() ) );
		$adapter->register_invalidation_events( $coordinator );

		foreach ( PostSurfaceAdapter::RANK_MATH_SEO_META_KEYS as $index => $meta_key ) {
			update_post_meta( (int) $post->ID, $meta_key, 'value-' . $index );
		}

		$this->assertSame( 1, $coordinator->dirty_count() );
		$coordinator->flush();
		$this->assertSame( 1, $coordinator->sync_count() );
		$this->assertSame( 0, $coordinator->dirty_count() );

		$coordinator->flush();
		$this->assertSame( 1, $coordinator->sync_count(), 'Second flush must not sync again.' );
	}

	public function test_jobs_create_rejects_unregistered_source_type_when_registry_wired(): void {
		$registry = new SurfaceRegistry();
		$registry->register( new PostSurfaceAdapter() );

		$jobs    = new BackgroundTranslationJobRepository();
		$items   = new BackgroundTranslationItemRepository();
		$leases  = new JobLeaseService( $jobs, $items );
		$service = new BackgroundTranslationJobService(
			$jobs,
			$items,
			$leases,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$registry
		);

		$result = $service->create_job(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => 'term',
				'source_id'      => 1,
				'language_id'    => 2,
				'segment_keys'   => array( 'title' ),
				'provider_id'    => 'openai',
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => 1,
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'unregistered_source_type', $result->get_error_code() );
	}

	public function test_item_processor_short_circuits_ignored_orphaned_rows(): void {
		$this->enable_strategy_f_flags();

		$settings  = new Settings();
		$adapters  = new AdapterRegistry();
		$blocks    = new BlockRegistry( $adapters );
		$extractor = new Extractor(
			$settings,
			new BlockExtractor(
				$adapters,
				$blocks,
				new BlockExtractionLogger()
			)
		);
		$assembler = new SegmentAssembler( $extractor, $this->store, $blocks );
		$glossary  = new GlossaryService(
			new GlossaryRepository(),
			new GlossaryNormalizer(),
			new GlossaryMatcher( new GlossaryNormalizer() )
		);
		$translation = new TranslationService(
			$this->store,
			$assembler,
			$this->languages,
			new EchoAIProvider(),
			null,
			null,
			$glossary
		);

		$registry = new SurfaceRegistry();
		$registry->register( new PostSurfaceAdapter() );

		$processor = new BackgroundTranslationItemProcessor(
			$this->store,
			$translation,
			$glossary,
			$assembler,
			null,
			$registry
		);

		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'content',
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'segment_order'   => 0,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => 'Hello workspace',
				'translated_text' => 'Hej',
				'status'          => Store::STATUS_IGNORED,
			)
		);

		$result = $processor->process(
			(object) array(
				'job_type'    => JobTypes::TRANSLATE_SELECTED,
				'language_id' => (int) $language->language_id,
				'source_type' => Store::SOURCE_POST,
				'source_id'   => (int) $post->ID,
			),
			(object) array(
				'segment_key'          => $key,
				'source_hash_captured' => '',
			),
			$post
		);

		$this->assertSame( ItemStatuses::SKIPPED_CONFLICT, $result->status );
	}

	public function test_fluent_forms_integration_has_no_hardcoded_form_or_contact_constants(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/src/Integration/FluentForms/FluentFormsIntegration.php'
		);

		$this->assertStringNotContainsString( 'FORM_ID', $src );
		$this->assertStringNotContainsString( 'CONTACT_PAGE_ID', $src );
		$this->assertStringNotContainsString( '3410', $src );
		$this->assertStringContainsString( 'discover_form_ids', $src );
		$this->assertStringContainsString( 'embed_ids_override', $src );

		// Rank Math allowlist remains a separate surface concern.
		$this->assertNotEmpty( RankMathIntegration::META_TITLE );
	}
}
