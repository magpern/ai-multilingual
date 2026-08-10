<?php
/**
 * Persist-path batch builder parity tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Quality;

use AIMultilingual\Quality\CorpusLoader;
use AIMultilingual\Quality\PersistPathBatchBuilder;
use AIMultilingual\Translation\AI\FieldSemantic;
use AIMultilingual\Translation\AI\PromptProfileRegistry;
use AIMultilingual\Translation\AI\TranslationBatch;
use AIMultilingual\Translation\AI\TranslationContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \AIMultilingual\Quality\PersistPathBatchBuilder
 */
final class PersistPathBatchBuilderParityTest extends TestCase {

	public function test_batch_matches_translate_segment_contract(): void {
		$case    = ( new CorpusLoader() )->load( 'C1.0' )['cases']['html_01'];
		$builder = new PersistPathBatchBuilder();
		$batch   = $builder->build_for_case( $case, 'en_US', 'sv_SE', "peptide => peptid\n" );

		$this->assertSame( 'en_US', $batch->source_locale );
		$this->assertSame( 'sv_SE', $batch->target_locale );
		$this->assertSame( PromptProfileRegistry::TRANSLATE, $batch->prompt_profile );
		$this->assertSame( PromptProfileRegistry::VERSION, $batch->prompt_version );
		$this->assertSame( '3', $batch->prompt_version );
		$this->assertSame( TranslationBatch::OPERATION_TRANSLATE, $batch->operation );
		$this->assertSame( array(), $batch->constraints );
		$this->assertSame( "peptide => peptid\n", $batch->glossary_fragment );
		$this->assertCount( 1, $batch->segments );
		$this->assertSame( 'html_01', $batch->segments[0]->segment_key );
		$this->assertSame( $case['source_text'], $batch->segments[0]->source_text );
		$this->assertSame( 'html', $batch->segments[0]->text_format );
		$this->assertSame( '', $batch->segments[0]->existing_target );
		$this->assertInstanceOf( TranslationContext::class, $batch->context );
		$this->assertSame( TranslationContext::SCHEMA_VERSION, $batch->context->schema_version );
	}

	public function test_field_semantics_mapped_via_context_not_raw_batch_property(): void {
		$case  = array(
			'id'              => 'test_01',
			'source_text'     => 'Hello',
			'text_format'     => 'plain',
			'field_semantics' => 'seo_title',
		);
		$batch = ( new PersistPathBatchBuilder() )->build_for_case( $case, 'en_US', 'sv_SE', '' );

		$props = ( new ReflectionClass( $batch ) )->getProperties();
		$names = array_map( static fn( $p ) => $p->getName(), $props );
		$this->assertNotContains( 'field_semantics', $names );
		$this->assertContains( 'context', $names );

		$this->assertNotNull( $batch->context );
		$this->assertSame( FieldSemantic::SEO_TITLE, $batch->context->field_semantic );
		$this->assertSame( 'search_snippet', $batch->context->content_purpose() );
	}

	public function test_unknown_semantic_degrades_to_generic(): void {
		$case  = array(
			'id'              => 'test_02',
			'source_text'     => 'Hello',
			'text_format'     => 'plain',
			'field_semantics' => 'paragraph',
		);
		$batch = ( new PersistPathBatchBuilder() )->build_for_case( $case, 'en_US', 'sv_SE', '' );
		$this->assertNotNull( $batch->context );
		$this->assertSame( FieldSemantic::GENERIC, $batch->context->field_semantic );
	}
}
