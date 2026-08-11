<?php
/**
 * OTL.3 retranslate target concurrency + provider-failure preservation.
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
use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\AI\TranslationBatch;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Publication\PublicationAuditLogger;
use AIMultilingual\Translation\Publication\PublicationPolicy;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\TranslationService;
use WP_Error;
use WP_REST_Request;

/**
 * Interactive sync retranslate lost-update protection.
 */
final class Otl3RetranslateConcurrencyTest extends AimlTestCase {

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
			new PublicationService(
				$this->store,
				new AssessmentAssembler(),
				new PublicationPolicy(),
				new PublicationAuditLogger(),
				new Settings()
			)
		);
	}

	/**
	 * Seed a published machine translation and return segment + hash H1.
	 *
	 * @return array{post:\WP_Post,language_id:int,key:string,hash:string,source:string,text:string}
	 */
	private function seed_published_translation(): array {
		$language  = $this->add_language();
		$post      = $this->create_block_page();
		$key       = $this->default_segment_key();
		$assembled = $this->assembler->assemble_one( $post, (int) $language->language_id, $key );
		$source    = (string) ( $assembled['source_text'] ?? '' );
		$text      = 'SV seed T1: ' . $source;

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => (int) $language->language_id,
				'field_key'       => (string) ( $assembled['field_key'] ?? '' ),
				'segment_key'     => $key,
				'segment_kind'    => (string) ( $assembled['segment_kind'] ?? Store::KIND_BLOCK ),
				'segment_order'   => (int) ( $assembled['segment_order'] ?? 0 ),
				'text_format'     => (string) ( $assembled['text_format'] ?? Store::FORMAT_PLAIN ),
				'source_text'     => $source,
				'translated_text' => $text,
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
			)
		);

		$this->store->update_publish_metadata(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			array(
				'publish_status' => Store::PUBLISH_PUBLISHED,
				'published_at'   => current_time( 'mysql', true ),
				'published_by'   => 1,
			)
		);

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );

		return array(
			'post'        => $post,
			'language_id' => (int) $language->language_id,
			'key'         => $key,
			'hash'        => (string) ( $row->translation_hash ?? '' ),
			'source'      => $source,
			'text'        => $text,
		);
	}

	public function test_provider_race_preserves_newer_target(): void {
		$seed = $this->seed_published_translation();
		$h1   = $seed['hash'];
		$t2   = 'SV concurrent T2: ' . $seed['source'];
		$t3   = 'SV provider T3: ' . $seed['source'];

		$store = $this->store;
		$post  = $seed['post'];
		$lang  = $seed['language_id'];
		$key   = $seed['key'];

		$provider = new ScriptedAIProvider(
			array(
				static function ( TranslationBatch $batch ) use ( $store, $post, $lang, $key, $seed, $t2, $t3 ) {
					unset( $batch );
					// Simulate operator B saving T2 while provider is in flight.
					$existing = $store->get( Store::SOURCE_POST, (int) $post->ID, $lang, $key );
					$store->save_translation(
						array(
							'source_type'     => Store::SOURCE_POST,
							'source_id'       => (int) $post->ID,
							'source_subtype'  => (string) $post->post_type,
							'language_id'     => $lang,
							'field_key'       => (string) ( $existing->field_key ?? '' ),
							'segment_key'     => $key,
							'segment_kind'    => (string) ( $existing->segment_kind ?? Store::KIND_BLOCK ),
							'segment_order'   => (int) ( $existing->segment_order ?? 0 ),
							'text_format'     => (string) ( $existing->text_format ?? Store::FORMAT_PLAIN ),
							'source_text'     => $seed['source'],
							'translated_text' => $t2,
							'status'          => Store::STATUS_MANUALLY_EDITED,
						)
					);

					return new ProviderResult(
						array(
							array(
								'segment_key'     => $key,
								'translated_text' => $t3,
							),
						),
						1,
						1,
						'scripted-1'
					);
				},
			)
		);

		$result = $this->make_translation_service( $provider )->translate_segment(
			$post,
			$lang,
			$key,
			true,
			$h1
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aiml_translation_hash_mismatch', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertIsArray( $data );
		$this->assertSame( 409, (int) ( $data['status'] ?? 0 ) );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, $lang, $key );
		$this->assertNotNull( $row );
		$this->assertSame( $t2, (string) $row->translated_text );
		$this->assertNotSame( $t3, (string) $row->translated_text );
		$this->assertNotSame( $h1, (string) $row->translation_hash );
	}

	public function test_provider_failure_preserves_prior_state(): void {
		$seed = $this->seed_published_translation();

		$provider = new ScriptedAIProvider(
			array(
				new WP_Error( 'aiml_provider_failed', 'Simulated provider failure.' ),
			)
		);

		$result = $this->make_translation_service( $provider )->translate_segment(
			$seed['post'],
			$seed['language_id'],
			$seed['key'],
			true,
			$seed['hash']
		);

		$this->assertInstanceOf( WP_Error::class, $result );

		$row = $this->store->get(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key']
		);
		$this->assertNotNull( $row );
		$this->assertSame( $seed['text'], (string) $row->translated_text );
		$this->assertSame( $seed['hash'], (string) $row->translation_hash );
		$this->assertSame( Store::PUBLISH_PUBLISHED, (string) $row->publish_status );
	}

	public function test_null_hash_jobs_path_still_overwrites(): void {
		$seed = $this->seed_published_translation();
		$t3   = 'SV jobs overwrite: ' . $seed['source'];

		$provider = new ScriptedAIProvider(
			array(
				new ProviderResult(
					array(
						array(
							'segment_key'     => $seed['key'],
							'translated_text' => $t3,
						),
					),
					1,
					1,
					'scripted-1'
				),
			)
		);

		$result = $this->make_translation_service( $provider )->translate_segment(
			$seed['post'],
			$seed['language_id'],
			$seed['key']
		);

		$this->assertIsArray( $result );
		$row = $this->store->get(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key']
		);
		$this->assertNotNull( $row );
		$this->assertSame( $t3, (string) $row->translated_text );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );
		$this->assertSame( Store::REVIEW_NOT_SUBMITTED, (string) $row->review_status );
	}

	public function test_expected_hash_mismatch_before_provider_persists_nothing(): void {
		$seed = $this->seed_published_translation();
		wp_set_current_user( $this->create_translator() );

		$existing = $this->store->get(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key']
		);
		$this->assertNotNull( $existing );
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $seed['post']->ID,
				'source_subtype'  => (string) $seed['post']->post_type,
				'language_id'     => $seed['language_id'],
				'field_key'       => (string) ( $existing->field_key ?? '' ),
				'segment_key'     => $seed['key'],
				'segment_kind'    => (string) ( $existing->segment_kind ?? Store::KIND_BLOCK ),
				'segment_order'   => (int) ( $existing->segment_order ?? 0 ),
				'text_format'     => (string) ( $existing->text_format ?? Store::FORMAT_PLAIN ),
				'source_text'     => $seed['source'],
				'translated_text' => 'SV concurrent before provider',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$provider = new ScriptedAIProvider(
			array(
				new ProviderResult(
					array(
						array(
							'segment_key'     => $seed['key'],
							'translated_text' => 'should not persist',
						),
					),
					1,
					1,
					'scripted-1'
				),
			)
		);
		$result   = $this->make_translation_service( $provider )->translate_segment(
			$seed['post'],
			$seed['language_id'],
			$seed['key'],
			true,
			$seed['hash']
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aiml_translation_hash_mismatch', $result->get_error_code() );

		$row = $this->store->get(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key']
		);
		$this->assertSame( 'SV concurrent before provider', (string) $row->translated_text );
	}
}
