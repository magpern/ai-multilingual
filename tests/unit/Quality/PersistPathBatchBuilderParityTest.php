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
use AIMultilingual\Translation\AI\PromptProfileRegistry;
use AIMultilingual\Translation\AI\TranslationBatch;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \AIMultilingual\Quality\PersistPathBatchBuilder
 */
final class PersistPathBatchBuilderParityTest extends TestCase {

	public function test_batch_matches_translate_segment_contract(): void {
		$case   = ( new CorpusLoader() )->load( 'C1.0' )['cases']['html_01'];
		$builder = new PersistPathBatchBuilder();
		$batch   = $builder->build_for_case( $case, 'en_US', 'sv_SE', "peptide => peptid\n" );

		$this->assertSame( 'en_US', $batch->source_locale );
		$this->assertSame( 'sv_SE', $batch->target_locale );
		$this->assertSame( PromptProfileRegistry::TRANSLATE, $batch->prompt_profile );
		$this->assertSame( PromptProfileRegistry::VERSION, $batch->prompt_version );
		$this->assertSame( TranslationBatch::OPERATION_TRANSLATE, $batch->operation );
		$this->assertSame( array(), $batch->constraints );
		$this->assertSame( "peptide => peptid\n", $batch->glossary_fragment );
		$this->assertCount( 1, $batch->segments );
		$this->assertSame( 'html_01', $batch->segments[0]->segment_key );
		$this->assertSame( $case['source_text'], $batch->segments[0]->source_text );
		$this->assertSame( 'html', $batch->segments[0]->text_format );
		$this->assertSame( '', $batch->segments[0]->existing_target );
	}

	public function test_field_semantics_not_in_batch(): void {
		$case = array(
			'id'               => 'test_01',
			'source_text'      => 'Hello',
			'text_format'      => 'plain',
			'field_semantics'  => 'seo_title',
		);
		$batch = ( new PersistPathBatchBuilder() )->build_for_case( $case, 'en_US', 'sv_SE', '' );

		$props = ( new ReflectionClass( $batch ) )->getProperties();
		$names = array_map( static fn( $p ) => $p->getName(), $props );
		$this->assertNotContains( 'field_semantics', $names );

		$segment_props = ( new ReflectionClass( $batch->segments[0] ) )->getProperties();
		$segment_names = array_map( static fn( $p ) => $p->getName(), $segment_props );
		$this->assertNotContains( 'field_semantics', $segment_names );
	}
}
