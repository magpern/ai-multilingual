<?php
/**
 * Integration tests for TSC.2 registered meta retain/orphan lifecycle.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Surface\Meta\RegisteredMetaDefinition;
use AIMultilingual\Surface\Meta\RegisteredMetaExtractor;
use AIMultilingual\Surface\Meta\RegisteredMetaReader;
use AIMultilingual\Surface\Meta\RegisteredMetaRegistry;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermExtractor;

/**
 * @covers \AIMultilingual\Translation\Store::sync_source
 * @covers \AIMultilingual\Surface\Meta\RegisteredMetaExtractor
 */
final class Tsc2RegisteredMetaLifecycleTest extends AimlTestCase {

	public function test_case_a_active_empty_orphans(): void {
		$language = $this->add_language();
		$post_id  = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$lang     = (int) $language->language_id;

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'post',
				'language_id'     => $lang,
				'field_key'       => RegisteredMetaDefinition::FIELD_KEY,
				'segment_key'     => 'm:demo:subtitle',
				'source_text'     => 'Hello',
				'text_format'     => Store::FORMAT_PLAIN,
				'translated_text' => 'Hej',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$this->store->sync_source( Store::SOURCE_POST, $post_id, 'post', array() );

		$after = $this->store->get( Store::SOURCE_POST, $post_id, $lang, 'm:demo:subtitle' );
		$this->assertNotNull( $after );
		$this->assertSame( Store::STATUS_IGNORED, (string) $after->status );
		$this->assertSame( 'orphaned', (string) $after->error_code );
	}

	public function test_case_b_retain_keys_leave_row_genuinely_untouched(): void {
		$language = $this->add_language();
		$post_id  = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$lang     = (int) $language->language_id;

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'post',
				'language_id'     => $lang,
				'field_key'       => RegisteredMetaDefinition::FIELD_KEY,
				'segment_key'     => 'm:demo:subtitle',
				'source_text'     => 'Hello',
				'text_format'     => Store::FORMAT_PLAIN,
				'translated_text' => 'Hej',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$before = $this->store->get( Store::SOURCE_POST, $post_id, $lang, 'm:demo:subtitle' );
		$this->assertNotNull( $before );
		$snapshot = array(
			'status'          => (string) $before->status,
			'error_code'      => (string) $before->error_code,
			'updated_at'      => (string) $before->updated_at,
			'source_hash'     => (string) $before->source_hash,
			'source_text'     => (string) $before->source_text,
			'translated_text' => (string) $before->translated_text,
			'is_stale'        => (int) $before->is_stale,
		);

		$this->store->sync_source(
			Store::SOURCE_POST,
			$post_id,
			'post',
			array(),
			array( 'm:demo:subtitle' )
		);

		$after = $this->store->get( Store::SOURCE_POST, $post_id, $lang, 'm:demo:subtitle' );
		$this->assertNotNull( $after );
		$this->assertSame( $snapshot['status'], (string) $after->status );
		$this->assertSame( $snapshot['error_code'], (string) $after->error_code );
		$this->assertSame( $snapshot['updated_at'], (string) $after->updated_at );
		$this->assertSame( $snapshot['source_hash'], (string) $after->source_hash );
		$this->assertSame( $snapshot['source_text'], (string) $after->source_text );
		$this->assertSame( $snapshot['translated_text'], (string) $after->translated_text );
		$this->assertSame( $snapshot['is_stale'], (int) $after->is_stale );
	}

	public function test_case_c_removed_definition_orphans_without_retain(): void {
		$language = $this->add_language();
		$post_id  = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$lang     = (int) $language->language_id;

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'post',
				'language_id'     => $lang,
				'field_key'       => RegisteredMetaDefinition::FIELD_KEY,
				'segment_key'     => 'm:retired:old',
				'source_text'     => 'Old',
				'text_format'     => Store::FORMAT_PLAIN,
				'translated_text' => 'Gammalt',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$this->store->sync_source( Store::SOURCE_POST, $post_id, 'post', array(), array() );

		$row = $this->store->get( Store::SOURCE_POST, $post_id, $lang, 'm:retired:old' );
		$this->assertNotNull( $row );
		$this->assertSame( Store::STATUS_IGNORED, (string) $row->status );
		$this->assertSame( 'orphaned', (string) $row->error_code );
	}

	public function test_native_extract_for_admitted_post_only(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		update_post_meta( $post_id, '_aiml_tsc2_subtitle', 'Visible subtitle' );

		$registry = new RegisteredMetaRegistry();
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'aiml_tsc2',
				source_type: Store::SOURCE_POST,
				meta_key: '_aiml_tsc2_subtitle',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'Subtitle',
				provider_allowed: true,
				overlay_capable: true,
				overlay_resolver_ownership: RegisteredMetaDefinition::OVERLAY_REFERENCE_PREFIX . 'aiml_tsc2',
			)
		);

		$extractor = new RegisteredMetaExtractor( $registry, new RegisteredMetaReader() );
		$units     = $extractor->extract_for_post( $post_id );
		$this->assertArrayHasKey( 'm:aiml_tsc2:_aiml_tsc2_subtitle', $units );
		$this->assertSame( RegisteredMetaDefinition::FIELD_KEY, $units['m:aiml_tsc2:_aiml_tsc2_subtitle']['field_key'] );
		$this->assertSame( 'Visible subtitle', $units['m:aiml_tsc2:_aiml_tsc2_subtitle']['source_text'] );
	}

	public function test_term_native_meta_extract_additional_to_name_description(): void {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'News',
			)
		);
		update_term_meta( $term_id, '_aiml_tsc2_blurb', 'Category blurb' );

		$registry = new RegisteredMetaRegistry();
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'aiml_tsc2',
				source_type: Store::SOURCE_TERM,
				meta_key: '_aiml_tsc2_blurb',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'Blurb',
				provider_allowed: true,
			)
		);

		$extractor = new TermExtractor(
			new RegisteredMetaExtractor( $registry, new RegisteredMetaReader() )
		);
		$segments  = $extractor->extract( $term_id );
		$this->assertArrayHasKey( 'name', $segments );
		$this->assertArrayHasKey( 'm:aiml_tsc2:_aiml_tsc2_blurb', $segments );
		$this->assertSame( 'News', (string) ( $segments['name']['source_text'] ?? '' ) );
		$this->assertSame( 'Category blurb', (string) ( $segments['m:aiml_tsc2:_aiml_tsc2_blurb']['source_text'] ?? '' ) );
		// Native name/description plus optional registered m: — no duplicate segment keys.
		$this->assertSame( count( $segments ), count( array_unique( array_keys( $segments ) ) ) );
		foreach ( array_keys( $segments ) as $key ) {
			$this->assertStringStartsNotWith( 'p:rankmath', (string) $key );
		}
	}

	public function test_rank_math_inactive_retain_keys_preserve_external_p_rows(): void {
		$language = $this->add_language();
		$post_id  = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$lang     = (int) $language->language_id;

		$identity = new \AIMultilingual\Integration\Identity\PluginIdentity();
		$seg_key  = $identity->build( 'rankmath', 'post', (string) $post_id, 'title' );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'post',
				'language_id'     => $lang,
				'field_key'       => 'title',
				'segment_key'     => $seg_key,
				'source_text'     => 'SEO Title',
				'text_format'     => Store::FORMAT_PLAIN,
				'translated_text' => 'SEO Titel',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$registry = new RegisteredMetaRegistry( $identity );
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'rankmath',
				source_type: Store::SOURCE_POST,
				meta_key: 'rank_math_title',
				segment_key_mode: RegisteredMetaDefinition::MODE_EXTERNAL_P,
				label: 'Rank Math title',
				provider_allowed: true,
				activation: static fn (): bool => false,
				external_field_token: 'title',
			)
		);

		$retain = $registry->retain_segment_keys( Store::SOURCE_POST, $post_id );
		$this->assertContains( $seg_key, $retain );

		$before = $this->store->get( Store::SOURCE_POST, $post_id, $lang, $seg_key );
		$this->assertNotNull( $before );
		$updated_at = (string) $before->updated_at;

		$this->store->sync_source( Store::SOURCE_POST, $post_id, 'post', array(), $retain );

		$after = $this->store->get( Store::SOURCE_POST, $post_id, $lang, $seg_key );
		$this->assertNotNull( $after );
		$this->assertSame( Store::STATUS_MANUALLY_EDITED, (string) $after->status );
		$this->assertNotSame( 'orphaned', (string) $after->error_code );
		$this->assertSame( $updated_at, (string) $after->updated_at );
	}

	public function test_rank_math_active_empty_extract_orphans_without_retain(): void {
		$language = $this->add_language();
		$post_id  = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$lang     = (int) $language->language_id;

		$identity = new \AIMultilingual\Integration\Identity\PluginIdentity();
		$seg_key  = $identity->build( 'rankmath', 'post', (string) $post_id, 'title' );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'post',
				'language_id'     => $lang,
				'field_key'       => 'title',
				'segment_key'     => $seg_key,
				'source_text'     => 'SEO Title',
				'text_format'     => Store::FORMAT_PLAIN,
				'translated_text' => 'SEO Titel',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		// Active definition → not retained → CASE A orphan when missing from extract.
		$this->store->sync_source( Store::SOURCE_POST, $post_id, 'post', array(), array() );

		$row = $this->store->get( Store::SOURCE_POST, $post_id, $lang, $seg_key );
		$this->assertNotNull( $row );
		$this->assertSame( Store::STATUS_IGNORED, (string) $row->status );
		$this->assertSame( 'orphaned', (string) $row->error_code );
	}

	public function test_rank_math_disabled_filter_retains_external_p_rows(): void {
		$language = $this->add_language();
		$post_id  = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$lang     = (int) $language->language_id;

		$identity = new \AIMultilingual\Integration\Identity\PluginIdentity();
		$seg_key  = $identity->build( 'rankmath', 'post', (string) $post_id, 'title' );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'post',
				'language_id'     => $lang,
				'field_key'       => 'title',
				'segment_key'     => $seg_key,
				'source_text'     => 'SEO Title',
				'text_format'     => Store::FORMAT_PLAIN,
				'translated_text' => 'SEO Titel',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		add_filter( 'aiml_rankmath_integration_disabled', '__return_true' );
		$registry = new RegisteredMetaRegistry( $identity, $this->store );
		\AIMultilingual\Surface\Meta\RankMathMetaDefinitions::register_into( $registry );
		$retain = $registry->retain_segment_keys( Store::SOURCE_POST, $post_id );
		$this->assertContains( $seg_key, $retain );

		$before = $this->store->get( Store::SOURCE_POST, $post_id, $lang, $seg_key );
		$this->assertNotNull( $before );
		$updated_at = (string) $before->updated_at;
		$this->store->sync_source( Store::SOURCE_POST, $post_id, 'post', array(), $retain );
		$after = $this->store->get( Store::SOURCE_POST, $post_id, $lang, $seg_key );
		$this->assertNotNull( $after );
		$this->assertSame( Store::STATUS_MANUALLY_EDITED, (string) $after->status );
		$this->assertSame( $updated_at, (string) $after->updated_at );
		remove_filter( 'aiml_rankmath_integration_disabled', '__return_true' );
	}

	public function test_provider_disallowed_segment_skips_without_calling_provider(): void {
		$registry = new RegisteredMetaRegistry();
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: 'note',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'Note',
				provider_allowed: false,
			)
		);

		$settings  = new \AIMultilingual\Settings();
		$adapters  = new \AIMultilingual\Block\AdapterRegistry();
		$blocks    = new \AIMultilingual\Block\BlockRegistry( $adapters );
		$extractor = new \AIMultilingual\Translation\Extractor(
			$settings,
			new \AIMultilingual\Translation\BlockExtractor(
				$adapters,
				$blocks,
				new \AIMultilingual\Block\BlockExtractionLogger()
			)
		);
		$assembler = new \AIMultilingual\Workspace\SegmentAssembler( $extractor, $this->store, $blocks, null, null, $registry );
		$glossary  = new \AIMultilingual\Glossary\GlossaryService(
			new \AIMultilingual\Glossary\GlossaryRepository(),
			new \AIMultilingual\Glossary\GlossaryNormalizer(),
			new \AIMultilingual\Glossary\GlossaryMatcher( new \AIMultilingual\Glossary\GlossaryNormalizer() )
		);

		$provider_calls = 0;
		$provider       = new ScriptedAIProvider(
			array(
				static function () use ( &$provider_calls ) {
					++$provider_calls;
					return new \WP_Error( 'should_not_run', 'provider must not be called' );
				},
			)
		);
		$translation    = new \AIMultilingual\Workspace\TranslationService(
			$this->store,
			$assembler,
			$this->languages,
			$provider,
			null,
			null,
			$glossary
		);
		$processor      = new \AIMultilingual\Jobs\BackgroundTranslationItemProcessor(
			$this->store,
			$translation,
			$glossary,
			$assembler,
			null,
			null,
			$registry
		);

		$job  = (object) array(
			'language_id' => 1,
			'job_type'    => 'translate',
			'source_type' => Store::SOURCE_POST,
			'source_id'   => 1,
		);
		$item = (object) array(
			'segment_key'               => 'm:demo:note',
			'source_hash_captured'      => '',
			'translation_hash_captured' => '',
		);

		$result = $processor->process( $job, $item, null, true );
		$this->assertSame( \AIMultilingual\Jobs\ItemStatuses::SKIPPED_CONFLICT, $result->status );
		$this->assertSame( 0, $provider_calls );
		$this->assertStringContainsString( 'not provider-admitted', $result->skip_reason );
	}
}
