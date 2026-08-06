<?php
/**
 * BackgroundTranslationItemProcessor integration conflict matrix.
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
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\TranslationService;

/**
 * J3 ItemProcessor conflict policy (integration — final collaborators).
 */
final class JobsItemProcessorTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	private BackgroundTranslationItemProcessor $processor;

	private Store $job_store;

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
		$assembler       = new SegmentAssembler( $extractor, $this->job_store, $blocks );
		$glossary        = new GlossaryService(
			new GlossaryRepository(),
			new GlossaryNormalizer(),
			new GlossaryMatcher( new GlossaryNormalizer() )
		);
		$translation     = new TranslationService(
			$this->job_store,
			$assembler,
			$this->languages,
			new EchoAIProvider(),
			null,
			null,
			$glossary
		);

		$this->processor = new BackgroundTranslationItemProcessor(
			$this->job_store,
			$translation,
			$glossary,
			$assembler
		);
	}

	public function test_approved_review_skips_conflict(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$this->seed_segment(
			$post,
			(int) $language->language_id,
			$key,
			'Approved text',
			Store::STATUS_MACHINE_TRANSLATED,
			Store::REVIEW_APPROVED
		);

		$result = $this->processor->process(
			(object) array(
				'job_type'    => JobTypes::TRANSLATE_SELECTED,
				'language_id' => (int) $language->language_id,
			),
			(object) array(
				'segment_key'          => $key,
				'source_hash_captured' => '',
			),
			$post
		);

		$this->assertSame( ItemStatuses::SKIPPED_CONFLICT, $result->status );
	}

	public function test_manually_edited_skips_conflict(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();

		$this->seed_segment(
			$post,
			(int) $language->language_id,
			$key,
			'Human edit',
			Store::STATUS_MANUALLY_EDITED
		);

		$result = $this->processor->process(
			(object) array(
				'job_type'    => JobTypes::TRANSLATE_SELECTED,
				'language_id' => (int) $language->language_id,
			),
			(object) array(
				'segment_key'          => $key,
				'source_hash_captured' => '',
			),
			$post
		);

		$this->assertSame( ItemStatuses::SKIPPED_CONFLICT, $result->status );
	}

	public function test_retranslate_stale_allows_matching_machine_output(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$key      = $this->default_segment_key();
		$text     = 'Old machine';

		$this->seed_segment(
			$post,
			(int) $language->language_id,
			$key,
			$text,
			Store::STATUS_MACHINE_TRANSLATED
		);

		$hash = Store::translation_hash( $text );

		$result = $this->processor->process(
			(object) array(
				'job_type'    => JobTypes::RETRANSLATE_STALE,
				'language_id' => (int) $language->language_id,
			),
			(object) array(
				'segment_key'               => $key,
				'source_hash_captured'      => '',
				'translation_hash_captured' => $hash,
			),
			$post
		);

		$this->assertSame( ItemStatuses::COMPLETED, $result->status );
	}

	/**
	 * @param \WP_Post $post        Post.
	 * @param int      $language_id Language id.
	 * @param string   $key         Segment key.
	 * @param string   $translated  Translated text.
	 * @param string   $status      Provenance status.
	 * @param string   $review      Review status.
	 */
	private function seed_segment(
		\WP_Post $post,
		int $language_id,
		string $key,
		string $translated,
		string $status,
		string $review = Store::REVIEW_NOT_SUBMITTED
	): void {
		$this->job_store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => $language_id,
				'field_key'       => 'content',
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'segment_order'   => 0,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => 'Hello workspace',
				'translated_text' => $translated,
				'status'          => $status,
			)
		);

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
