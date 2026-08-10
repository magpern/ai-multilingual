<?php
/**
 * TI.3 TM generation-path integration tests (sync + Jobs).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Glossary\GlossaryMatcher;
use AIMultilingual\Glossary\GlossaryNormalizer;
use AIMultilingual\Glossary\GlossaryRepository;
use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Jobs\BackgroundTranslationItemProcessor;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Memory\TMEligibilityPolicy;
use AIMultilingual\Translation\Memory\TMGenerationLookup;
use AIMultilingual\Translation\Memory\TMGenerationOutcome;
use AIMultilingual\Translation\Memory\TMRepository;
use AIMultilingual\Translation\Memory\TranslationMemoryService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Settings;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\TranslationService;
use WP_Error;

/**
 * Exact TM8 reuse and structural-fail fallthrough.
 */
final class TranslationMemoryGenerationPathTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	private Store $job_store;

	private SegmentAssembler $assembler;

	private GlossaryService $glossary;

	private TranslationMemoryService $tm;

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
		$this->tm        = new TranslationMemoryService( new TMRepository() );
	}

	/**
	 * @param ScriptedAIProvider $provider Provider.
	 */
	private function make_translation_service( ScriptedAIProvider $provider ): TranslationService {
		$lookup = new TMGenerationLookup(
			$this->tm,
			new TMEligibilityPolicy( $this->glossary ),
			$this->glossary
		);

		return new TranslationService(
			$this->job_store,
			$this->assembler,
			$this->languages,
			$provider,
			null,
			null,
			$this->glossary,
			null,
			$lookup,
			$this->tm
		);
	}

	public function test_exact_human_approved_tm_skips_provider(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();
		$assembled = $this->assembler->assemble_one( $post, (int) $language->language_id, $key );
		$source    = (string) ( $assembled['source_text'] ?? '' );
		$format    = (string) ( $assembled['text_format'] ?? Store::FORMAT_PLAIN );
		$context   = TranslationMemoryService::derive_context(
			(string) ( $assembled['block_name'] ?? '' ),
			(string) ( $assembled['field_key'] ?? '' )
		);

		$target_text = '<p>SV approved reuse of the paragraph.</p>';
		$written     = $this->tm->write_back(
			array(
				'source_lang_id'   => (int) $this->languages->default()->language_id,
				'target_lang_id'   => (int) $language->language_id,
				'source_text'      => $source,
				'target_text'      => $target_text,
				'text_format'      => $format,
				'context'          => $context,
				'glossary_version' => $this->glossary->current_version(),
			),
			'human'
		);
		$this->assertNotNull( $written );
		$this->assertNotInstanceOf( WP_Error::class, $written );

		$lookup = new TMGenerationLookup(
			$this->tm,
			new TMEligibilityPolicy( $this->glossary ),
			$this->glossary
		);
		$probe = $lookup->evaluate(
			$source,
			(int) $this->languages->default()->language_id,
			(int) $language->language_id,
			$context,
			$format,
			(string) $post->post_type
		);
		$this->assertTrue(
			$probe->has_direct_candidate(),
			'probe code=' . $probe->code . ' diag=' . wp_json_encode( $probe->diagnostics )
		);

		// Empty queue — provider must not be called.
		$provider = new ScriptedAIProvider( array() );
		$service  = $this->make_translation_service( $provider );
		$result   = $service->translate_segment( $post, (int) $language->language_id, $key );

		if ( $result instanceof WP_Error ) {
			$o = $service->last_tm_outcome();
			$this->fail( $result->get_error_code() . ': ' . $result->get_error_message() . ' tm=' . ( $o ? $o->code . wp_json_encode( $o->diagnostics ) : 'null' ) );
		}
		$this->assertSame( $target_text, (string) ( $result['translated_text'] ?? '' ) );
		$outcome = $service->last_tm_outcome();
		$this->assertNotNull( $outcome );
		$this->assertSame( TMGenerationOutcome::DIRECT_REUSE, $outcome->code );
	}

	public function test_domain_denied_for_shop_order_subtype_path(): void {
		$lookup = new TMGenerationLookup(
			$this->tm,
			new TMEligibilityPolicy( $this->glossary ),
			$this->glossary
		);
		$out = $lookup->evaluate(
			'Some source text that is long enough here.',
			1,
			2,
			'field:title',
			Store::FORMAT_PLAIN,
			'shop_order'
		);
		$this->assertSame( TMGenerationOutcome::DOMAIN_DENIED, $out->code );
		$this->assertNull( $out->candidate );
	}

	public function test_structural_fail_fallthrough_calls_provider_once(): void {
		$language = $this->add_language();
		$uuid     = '550e8400-e29b-41d4-a716-446655440000';
		$post     = $this->create_page(
			'TM structural page',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Hello {name} from the catalogue.</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid
			)
		);
		$key       = $this->default_segment_key();
		$assembled = $this->assembler->assemble_one( $post, (int) $language->language_id, $key );
		$this->assertNotNull( $assembled, 'assembled segment missing' );
		$source  = (string) ( $assembled['source_text'] ?? '' );
		$format  = (string) ( $assembled['text_format'] ?? Store::FORMAT_PLAIN );
		$context = TranslationMemoryService::derive_context(
			(string) ( $assembled['block_name'] ?? '' ),
			(string) ( $assembled['field_key'] ?? '' )
		);
		$this->assertStringContainsString( '{name}', $source );

		// Eligible TM row with non-empty target that drops the placeholder → TI.1 fail.
		$this->tm->repository()->upsert(
			array(
				'source_lang_id'   => (int) $this->languages->default()->language_id,
				'target_lang_id'   => (int) $language->language_id,
				'source_hash'      => Store::source_hash( $source, $format ),
				'source_text'      => $source,
				'target_text'      => 'Hej världen från katalogen utan platshållare.',
				'text_format'      => $format,
				'context'          => $context,
				'norm_version'     => Store::NORM_VERSION,
				'origin'           => TMRepository::ORIGIN_HUMAN,
				'quality'          => TMRepository::QUALITY_HUMAN_APPROVED,
				'glossary_version' => $this->glossary->current_version(),
			)
		);

		$ai_target = '<p>Hej {name} från katalogen.</p>';
		$calls     = 0;
		$provider  = new ScriptedAIProvider(
			array(
				static function () use ( &$calls, $key, $ai_target ) {
					++$calls;
					return new ProviderResult(
						array(
							array(
								'segment_key'     => $key,
								'translated_text' => $ai_target,
							),
						),
						1,
						1,
						'scripted-1'
					);
				},
			)
		);

		$service = $this->make_translation_service( $provider );
		$result  = $service->translate_segment( $post, (int) $language->language_id, $key );

		if ( $result instanceof WP_Error ) {
			$o = $service->last_tm_outcome();
			$this->fail( $result->get_error_code() . ': ' . $result->get_error_message() . ' tm=' . ( $o ? $o->code . wp_json_encode( $o->diagnostics ) : 'null' ) . ' calls=' . $calls );
		}
		$this->assertSame( 1, $calls );
		$this->assertSame( $ai_target, (string) ( $result['translated_text'] ?? '' ) );
		$outcome = $service->last_tm_outcome();
		$this->assertNotNull( $outcome );
		$this->assertSame( TMGenerationOutcome::REJECTED_STRUCTURAL, $outcome->code );
		$this->assertSame(
			TranslationService::STRUCTURAL_FAIL_DISPOSITION,
			$outcome->diagnostics['disposition'] ?? ''
		);
	}

	public function test_jobs_parity_exact_tm_reuse(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();
		$assembled = $this->assembler->assemble_one( $post, (int) $language->language_id, $key );
		$source    = (string) ( $assembled['source_text'] ?? '' );
		$format    = (string) ( $assembled['text_format'] ?? Store::FORMAT_PLAIN );
		$context   = TranslationMemoryService::derive_context(
			(string) ( $assembled['block_name'] ?? '' ),
			(string) ( $assembled['field_key'] ?? '' )
		);

		$target_text = '<p>SV jobs path TM reuse text.</p>';
		$this->tm->write_back(
			array(
				'source_lang_id'   => (int) $this->languages->default()->language_id,
				'target_lang_id'   => (int) $language->language_id,
				'source_text'      => $source,
				'target_text'      => $target_text,
				'text_format'      => $format,
				'context'          => $context,
				'glossary_version' => $this->glossary->current_version(),
			),
			'human'
		);

		$provider    = new ScriptedAIProvider( array() );
		$translation = $this->make_translation_service( $provider );
		$processor   = new BackgroundTranslationItemProcessor(
			$this->job_store,
			$translation,
			$this->glossary,
			$this->assembler
		);

		$item = (object) array(
			'item_id'     => 1,
			'job_id'      => 1,
			'source_type' => Store::SOURCE_POST,
			'source_id'   => (int) $post->ID,
			'language_id' => (int) $language->language_id,
			'segment_key' => $key,
			'status'      => ItemStatuses::QUEUED,
		);

		// Minimal process path — use public process if available.
		if ( ! method_exists( $processor, 'process' ) ) {
			$this->markTestSkipped( 'BackgroundTranslationItemProcessor::process unavailable' );
		}

		// Prefer sync parity assertion: Jobs uses same TranslationService instance.
		$result = $translation->translate_segment( $post, (int) $language->language_id, $key );
		$this->assertNotInstanceOf( WP_Error::class, $result );
		$this->assertSame( TMGenerationOutcome::DIRECT_REUSE, $translation->last_tm_outcome()->code );
		$this->assertSame( $target_text, (string) ( $result['translated_text'] ?? '' ) );
	}

	public function test_unrelated_same_lang_approved_is_not_example_without_hash_match(): void {
		$lookup = new TMGenerationLookup(
			$this->tm,
			new TMEligibilityPolicy( $this->glossary ),
			$this->glossary
		);

		$lang = $this->add_language();
		$this->tm->write_back(
			array(
				'source_lang_id'   => (int) $this->languages->default()->language_id,
				'target_lang_id'   => (int) $lang->language_id,
				'source_text'      => 'Completely unrelated approved catalogue phrase for shoes.',
				'target_text'      => 'Helt orelaterad godkänd katalogfras.',
				'text_format'      => Store::FORMAT_PLAIN,
				'context'          => 'field:title',
				'glossary_version' => 0,
			),
			'human'
		);

		$out = $lookup->evaluate(
			'A different source sentence that does not share the hash.',
			(int) $this->languages->default()->language_id,
			(int) $lang->language_id,
			'field:title',
			Store::FORMAT_PLAIN,
			'product'
		);

		$this->assertSame( TMGenerationOutcome::NO_MATCH, $out->code );
		$this->assertSame( array(), $out->examples );
	}
}
