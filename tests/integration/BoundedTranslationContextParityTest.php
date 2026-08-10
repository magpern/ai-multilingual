<?php
/**
 * TI.2 sync/Jobs translation context parity.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Glossary\GlossaryMatcher;
use AIMultilingual\Glossary\GlossaryNormalizer;
use AIMultilingual\Glossary\GlossaryRepository;
use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Jobs\BackgroundTranslationItemProcessor;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\AI\TranslationBatch;
use AIMultilingual\Translation\AI\TranslationContext;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\TranslationService;

/**
 * Sync and Jobs share one TranslationContextBuilder path.
 */
final class BoundedTranslationContextParityTest extends AimlTestCase {

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

	public function test_sync_and_jobs_receive_context_on_shared_path(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();
		$source   = (string) ( $this->assembler->assemble_one( $post, (int) $language->language_id, $key )['source_text'] ?? '' );

		$captured = array();
		$provider = new ScriptedAIProvider(
			array(
				static function ( TranslationBatch $batch ) use ( &$captured, $key, $source ) {
					$captured[] = $batch;
					return new ProviderResult(
						array(
							array(
								'segment_key'     => $key,
								'translated_text' => 'SV: ' . $source,
							),
						),
						1,
						1,
						'scripted-1'
					);
				},
			)
		);

		$sync = $this->make_translation_service( $provider )->translate_segment(
			$post,
			(int) $language->language_id,
			$key
		);
		$this->assertIsArray( $sync );
		$this->assertCount( 1, $captured );
		$this->assertInstanceOf( TranslationContext::class, $captured[0]->context );
		$this->assertSame( '2', $captured[0]->prompt_version );
		$sync_semantic = $captured[0]->context->field_semantic;
		$sync_schema   = $captured[0]->context->schema_version;

		// Second post for Jobs so prior Store row does not conflict.
		$uuid2   = '550e8400-e29b-41d4-a716-446655440099';
		$post2   = $this->create_block_page( $uuid2 );
		$key2    = SegmentKey::build( $uuid2, Contract::FIELD_CONTENT );
		$source2 = (string) ( $this->assembler->assemble_one( $post2, (int) $language->language_id, $key2 )['source_text'] ?? '' );

		$captured  = array();
		$provider  = new ScriptedAIProvider(
			array(
				static function ( TranslationBatch $batch ) use ( &$captured, $key2, $source2 ) {
					$captured[] = $batch;
					return new ProviderResult(
						array(
							array(
								'segment_key'     => $key2,
								'translated_text' => 'SV: ' . $source2,
							),
						),
						1,
						1,
						'scripted-1'
					);
				},
			)
		);
		$processor = new BackgroundTranslationItemProcessor(
			$this->job_store,
			$this->make_translation_service( $provider ),
			$this->glossary,
			$this->assembler
		);

		$result = $processor->process(
			(object) array(
				'job_type'    => JobTypes::TRANSLATE_SELECTED,
				'language_id' => (int) $language->language_id,
			),
			(object) array(
				'segment_key'               => $key2,
				'source_hash_captured'      => '',
				'translation_hash_captured' => '',
			),
			$post2
		);

		$this->assertSame( ItemStatuses::COMPLETED, $result->status );
		$this->assertCount( 1, $captured );
		$this->assertInstanceOf( TranslationContext::class, $captured[0]->context );
		$this->assertSame( '2', $captured[0]->prompt_version );
		$this->assertSame( $sync_schema, $captured[0]->context->schema_version );
		$this->assertSame( $sync_semantic, $captured[0]->context->field_semantic );
	}
}
