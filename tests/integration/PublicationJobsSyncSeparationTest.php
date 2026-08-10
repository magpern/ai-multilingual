<?php
/**
 * TI.7 — publication failure must not become translation failure.
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
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Publication\PublicationAuditLogger;
use AIMultilingual\Translation\Publication\PublicationMode;
use AIMultilingual\Translation\Publication\PublicationPolicy;
use AIMultilingual\Translation\Publication\PublicationReasonCodes;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\TranslationService;

/**
 * Sync + Jobs share PublicationService; translation success stays independent.
 */
final class PublicationJobsSyncSeparationTest extends AimlTestCase {

	use WorkspaceTestHelpers;

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
		$this->assembler = new SegmentAssembler( $extractor, $this->store, $blocks );
		$this->glossary  = new GlossaryService(
			new GlossaryRepository(),
			new GlossaryNormalizer(),
			new GlossaryMatcher( new GlossaryNormalizer() )
		);
	}

	private function publication_service(): PublicationService {
		return new PublicationService(
			$this->store,
			new AssessmentAssembler(),
			new PublicationPolicy(),
			new PublicationAuditLogger(),
			new Settings()
		);
	}

	/**
	 * @param ScriptedAIProvider $provider Provider.
	 */
	private function make_translation_service( ScriptedAIProvider $provider ): TranslationService {
		return new TranslationService(
			$this->store,
			$this->assembler,
			$this->languages,
			$provider,
			null,
			null,
			$this->glossary,
			null,
			null,
			null,
			$this->publication_service()
		);
	}

	private function set_publication_mode( string $mode ): void {
		$current = get_option( Settings::OPTION, Settings::defaults() );
		if ( ! is_array( $current ) ) {
			$current = Settings::defaults();
		}
		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array_merge(
					$current,
					array( 'auto_publication_mode' => $mode )
				)
			)
		);
		\AIMultilingual\Plugin::instance()->reload_settings();
	}

	public function test_sync_succeeds_when_auto_publication_skipped(): void {
		$this->set_publication_mode( PublicationMode::MANUAL );

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
		$this->assertArrayHasKey( 'publication_result', $result );
		$this->assertSame( 'skipped', $result['publication_result']['status'] );
		$this->assertContains(
			PublicationReasonCodes::AUTOMATION_DISABLED,
			$result['publication_result']['reason_codes']
		);

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertNotSame( '', (string) $row->translated_text );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );
	}

	public function test_jobs_item_succeeds_when_publication_skipped(): void {
		$this->set_publication_mode( PublicationMode::MANUAL );

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
					'scripted-jobs'
				),
			)
		);

		$translation = $this->make_translation_service( $provider );
		$processor   = new BackgroundTranslationItemProcessor(
			$this->store,
			$translation,
			$this->glossary,
			$this->assembler
		);

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

		$this->assertSame( ItemStatuses::COMPLETED, $result->status );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );
	}
}
