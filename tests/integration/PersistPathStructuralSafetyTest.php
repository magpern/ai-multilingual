<?php
/**
 * TI.1 persist-path structural safety integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Glossary\GlossaryMatcher;
use AIMultilingual\Glossary\GlossaryNormalizer;
use AIMultilingual\Glossary\GlossaryRepository;
use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Jobs\BackgroundTranslationItemProcessor;
use AIMultilingual\Jobs\BackgroundTranslationRetryPolicy;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\AI\ResponseValidator;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\TranslationService;
use WP_Error;

/**
 * Sync + Jobs share persist_provider_result structural gate.
 */
final class PersistPathStructuralSafetyTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	private Store $job_store;

	private SegmentAssembler $assembler;

	private GlossaryService $glossary;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();

		$settings        = new Settings();
		$adapters        = new AdapterRegistry();
		$blocks          = new BlockRegistry( $adapters );
		$extractor       = new Extractor(
			$settings,
			new BlockExtractor(
				$adapters,
				$blocks,
				new BlockExtractionLogger()
			)
		);
		$this->job_store = $this->store;
		$this->assembler = new SegmentAssembler( $extractor, $this->job_store, $blocks );
		$this->glossary  = new GlossaryService(
			new GlossaryRepository(),
			new GlossaryNormalizer(),
			new GlossaryMatcher( new GlossaryNormalizer() )
		);
	}

	/**
	 * @param ScriptedAIProvider $provider Scripted provider.
	 */
	private function make_translation_service( ScriptedAIProvider $provider ): TranslationService {
		return new TranslationService(
			$this->job_store,
			$this->assembler,
			$this->languages,
			$provider,
			null,
			null,
			$this->glossary
		);
	}

	/**
	 * @param TranslationService $translation Service under test.
	 */
	private function make_processor( TranslationService $translation ): BackgroundTranslationItemProcessor {
		return new BackgroundTranslationItemProcessor(
			$this->job_store,
			$translation,
			$this->glossary,
			$this->assembler
		);
	}

	public function test_valid_translation_persists(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();
		$source   = (string) ( $this->assembler->assemble_one( $post, (int) $language->language_id, $key )['source_text'] ?? '' );

		$provider = new ScriptedAIProvider(
			array(
				new ProviderResult(
					array(
						array(
							'segment_key'     => $key,
							'translated_text' => 'SV: ' . $source,
						),
					),
					1,
					1,
					'scripted-1'
				),
			)
		);

		$result = $this->make_translation_service( $provider )->translate_segment(
			$post,
			(int) $language->language_id,
			$key
		);

		$this->assertIsArray( $result );
		$row = $this->job_store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( 'SV: ' . $source, (string) $row->translated_text );
	}

	public function test_empty_target_rejects_without_store_write(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$provider = new ScriptedAIProvider(
			array(
				new ProviderResult(
					array(
						array(
							'segment_key'     => $key,
							'translated_text' => '',
						),
					)
				),
			)
		);

		$result = $this->make_translation_service( $provider )->translate_segment(
			$post,
			(int) $language->language_id,
			$key
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( ResponseValidator::CODE_EMPTY_TARGET, $result->get_error_code() );
		$this->assertSame( 422, (int) ( $result->get_error_data()['status'] ?? 0 ) );
		$this->assertNull( $this->job_store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key ) );
	}

	public function test_missing_segment_key_is_response_contract_error(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$provider = new ScriptedAIProvider(
			array(
				new ProviderResult(
					array(
						array(
							'segment_key'     => 'other:key',
							'translated_text' => 'Hej',
						),
					)
				),
			)
		);

		$result = $this->make_translation_service( $provider )->translate_segment(
			$post,
			(int) $language->language_id,
			$key
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aiml_ai_invalid_response', $result->get_error_code() );
		$this->assertNull( $this->job_store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key ) );
	}

	public function test_placeholder_loss_rejects_and_preserves_prior_machine_row(): void {
		$language = $this->add_language();
		$post     = $this->factory()->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph {"aimlBlockId":"550e8400-e29b-41d4-a716-446655440000"} -->'
					. '<p>Hello {order_number}</p>'
					. '<!-- /wp:paragraph -->',
			)
		);
		$key      = $this->default_segment_key();
		$prior    = 'Tidigare {order_number}';

		$this->seed_row(
			$post,
			(int) $language->language_id,
			$key,
			$prior,
			Store::STATUS_MACHINE_TRANSLATED
		);

		$provider = new ScriptedAIProvider(
			array(
				new ProviderResult(
					array(
						array(
							'segment_key'     => $key,
							'translated_text' => 'Hej utan placeholder',
						),
					)
				),
			)
		);

		$result = $this->make_translation_service( $provider )->translate_segment(
			$post,
			(int) $language->language_id,
			$key
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( ResponseValidator::CODE_PLACEHOLDER_MISMATCH, $result->get_error_code() );

		$row = $this->job_store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( $prior, (string) $row->translated_text );
		$this->assertSame( Store::STATUS_MACHINE_TRANSLATED, (string) $row->status );
	}

	public function test_forbidden_markup_and_url_loss_reject(): void {
		$language = $this->add_language();
		$post     = $this->factory()->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph {"aimlBlockId":"550e8400-e29b-41d4-a716-446655440000"} -->'
					. '<p>Read https://example.com/a</p>'
					. '<!-- /wp:paragraph -->',
			)
		);
		$key      = $this->default_segment_key();

		$provider = new ScriptedAIProvider(
			array(
				new ProviderResult(
					array(
						array(
							'segment_key'     => $key,
							'translated_text' => '<p>Läs</p><script>x</script>',
						),
					)
				),
			)
		);

		$result = $this->make_translation_service( $provider )->translate_segment(
			$post,
			(int) $language->language_id,
			$key
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains(
			$result->get_error_code(),
			array(
				ResponseValidator::CODE_FORBIDDEN_MARKUP,
				ResponseValidator::CODE_URL_MISMATCH,
			)
		);
		$this->assertNull( $this->job_store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key ) );
	}

	public function test_swedish_decimal_localization_persists_after_ts7_narrow(): void {
		$language = $this->add_language();
		$post     = $this->factory()->post->create_and_get(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph {"aimlBlockId":"550e8400-e29b-41d4-a716-446655440000"} -->'
					. '<p>Dose is 1.5 ml</p>'
					. '<!-- /wp:paragraph -->',
			)
		);
		$key      = $this->default_segment_key();

		$provider = new ScriptedAIProvider(
			array(
				new ProviderResult(
					array(
						array(
							'segment_key'     => $key,
							'translated_text' => '<p>Dosen är 1,5 ml</p>',
						),
					)
				),
			)
		);

		$result = $this->make_translation_service( $provider )->translate_segment(
			$post,
			(int) $language->language_id,
			$key
		);

		$this->assertIsArray( $result );
		$row = $this->job_store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertSame( '<p>Dosen är 1,5 ml</p>', (string) $row->translated_text );
	}

	public function test_jobs_structural_failure_is_terminal_failed(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$provider    = new ScriptedAIProvider(
			array(
				new ProviderResult(
					array(
						array(
							'segment_key'     => $key,
							'translated_text' => '',
						),
					)
				),
			)
		);
		$translation = $this->make_translation_service( $provider );
		$processor   = $this->make_processor( $translation );

		$result = $processor->process(
			(object) array(
				'job_type'    => JobTypes::TRANSLATE_SELECTED,
				'language_id' => (int) $language->language_id,
			),
			(object) array(
				'segment_key'               => $key,
				'source_hash_captured'      => '',
				'translation_hash_captured' => '',
			),
			$post
		);

		$this->assertSame( ItemStatuses::FAILED, $result->status );
		$this->assertSame( ResponseValidator::CODE_EMPTY_TARGET, $result->error_code );
		$this->assertSame( ProviderResult::ERROR_PERMANENT, $result->error_class );
		$this->assertNull( $this->job_store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key ) );

		$policy = new BackgroundTranslationRetryPolicy();
		$this->assertSame(
			BackgroundTranslationRetryPolicy::DISPOSITION_TERMINAL,
			$policy->classify( ResponseValidator::CODE_EMPTY_TARGET )
		);
	}

	public function test_jobs_preserves_approved_conflict_gate(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$this->seed_row(
			$post,
			(int) $language->language_id,
			$key,
			'Approved text',
			Store::STATUS_MACHINE_TRANSLATED,
			Store::REVIEW_APPROVED
		);

		$provider    = new ScriptedAIProvider(); // Unused — conflict before translate.
		$translation = $this->make_translation_service( $provider );
		$processor   = $this->make_processor( $translation );

		$result = $processor->process(
			(object) array(
				'job_type'    => JobTypes::TRANSLATE_SELECTED,
				'language_id' => (int) $language->language_id,
			),
			(object) array(
				'segment_key'               => $key,
				'source_hash_captured'      => '',
				'translation_hash_captured' => '',
			),
			$post
		);

		$this->assertSame( ItemStatuses::SKIPPED_CONFLICT, $result->status );
		$row = $this->job_store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertSame( 'Approved text', (string) $row->translated_text );
	}

	public function test_human_row_preserved_on_structural_reject(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();
		$prior    = 'Human text';

		$this->seed_row(
			$post,
			(int) $language->language_id,
			$key,
			$prior,
			Store::STATUS_MANUALLY_EDITED
		);

		// Sync path has no Jobs conflict gate — but reject still must not overwrite.
		$provider = new ScriptedAIProvider(
			array(
				new ProviderResult(
					array(
						array(
							'segment_key'     => $key,
							'translated_text' => '',
						),
					)
				),
			)
		);

		$result = $this->make_translation_service( $provider )->translate_segment(
			$post,
			(int) $language->language_id,
			$key
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$row = $this->job_store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertSame( $prior, (string) $row->translated_text );
		$this->assertSame( Store::STATUS_MANUALLY_EDITED, (string) $row->status );
	}

	/**
	 * @param \WP_Post $post        Post.
	 * @param int      $language_id Language.
	 * @param string   $key         Segment key.
	 * @param string   $text        Translated text.
	 * @param string   $status      Store status.
	 * @param string   $review      Review status.
	 */
	private function seed_row(
		\WP_Post $post,
		int $language_id,
		string $key,
		string $text,
		string $status,
		string $review = Store::REVIEW_NOT_SUBMITTED
	): void {
		$assembled = $this->assembler->assemble_one( $post, $language_id, $key );
		$this->assertNotNull( $assembled );

		$save = $this->job_store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => $language_id,
				'field_key'       => (string) ( $assembled['field_key'] ?? '' ),
				'segment_key'     => $key,
				'segment_kind'    => (string) ( $assembled['segment_kind'] ?? Store::KIND_BLOCK ),
				'segment_order'   => (int) ( $assembled['segment_order'] ?? 0 ),
				'text_format'     => (string) ( $assembled['text_format'] ?? Store::FORMAT_PLAIN ),
				'source_text'     => (string) ( $assembled['source_text'] ?? '' ),
				'translated_text' => $text,
				'status'          => $status,
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $save );

		if ( Store::REVIEW_NOT_SUBMITTED !== $review ) {
			$this->job_store->update_review_metadata(
				Store::SOURCE_POST,
				(int) $post->ID,
				$language_id,
				$key,
				array(
					'review_status' => $review,
				)
			);
		}
	}
}
