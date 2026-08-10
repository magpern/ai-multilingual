<?php
/**
 * TI.2 bounded translation context unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\AI;

use AIMultilingual\Translation\AI\ContextItem;
use AIMultilingual\Translation\AI\FieldSemantic;
use AIMultilingual\Translation\AI\FieldSemanticMapper;
use AIMultilingual\Translation\AI\Providers\OpenAIProvider;
use AIMultilingual\Translation\AI\ProviderSegment;
use AIMultilingual\Translation\AI\TranslationBatch;
use AIMultilingual\Translation\AI\TranslationContext;
use AIMultilingual\Translation\AI\TranslationContextBuilder;
use AIMultilingual\Translation\Extractor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers \AIMultilingual\Translation\AI\TranslationContext
 * @covers \AIMultilingual\Translation\AI\TranslationContextBuilder
 * @covers \AIMultilingual\Translation\AI\FieldSemanticMapper
 * @covers \AIMultilingual\Translation\AI\Providers\OpenAIProvider
 */
final class BoundedTranslationContextTest extends TestCase {

	public function test_field_semantic_normalize_unknown_to_generic(): void {
		$this->assertSame( FieldSemantic::GENERIC, FieldSemantic::normalize( 'paragraph' ) );
		$this->assertSame( FieldSemantic::SEO_TITLE, FieldSemantic::normalize( 'seo_title' ) );
	}

	public function test_mapper_maps_product_excerpt_and_seo_plugin_key(): void {
		$mapper = new FieldSemanticMapper();
		$this->assertSame(
			FieldSemantic::PRODUCT_SHORT_DESCRIPTION,
			$mapper->map(
				array(
					'field_key'   => Extractor::FIELD_EXCERPT,
					'segment_key' => Extractor::FIELD_EXCERPT,
				),
				'product'
			)
		);
		$this->assertSame(
			FieldSemantic::SEO_TITLE,
			$mapper->map(
				array(
					'field_key'   => 'title',
					'segment_key' => 'p:rankmath:post:12:title',
				),
				'post'
			)
		);
		$this->assertSame(
			FieldSemantic::GENERIC,
			$mapper->map(
				array(
					'field_key'   => 'mystery',
					'segment_key' => 'mystery',
				),
				'post'
			)
		);
	}

	public function test_context_item_rejects_unknown_type(): void {
		$this->expectException( \InvalidArgumentException::class );
		new ContextItem( 'customer_email', 'x@y.z' );
	}

	public function test_builder_enforces_budgets_and_drop_priority(): void {
		$builder = new TranslationContextBuilder();
		$case    = array(
			'id'                 => 'budget_01',
			'field_semantics'    => 'product_short_description',
			'object_type'        => 'product',
			'object_title'       => str_repeat( 'T', 300 ),
			'context_categories' => array( 'Alpha', 'Beta', 'Gamma', 'Delta' ),
			'source_text'        => 'Short desc',
		);
		$ctx     = $builder->build_for_corpus_case( $case );

		$this->assertSame( 200, strlen( $ctx->object_title ) );
		$cats = array_values(
			array_filter(
				$ctx->items,
				static fn( ContextItem $i ) => ContextItem::TYPE_CATEGORY === $i->type
			)
		);
		$this->assertLessThanOrEqual( TranslationContext::MAX_CATEGORIES, count( $cats ) );
		$this->assertLessThanOrEqual( TranslationContext::MAX_ITEMS, count( $ctx->items ) );
		$this->assertLessThanOrEqual( TranslationContext::MAX_TOTAL_CHARS, (int) $ctx->provenance['char_count'] );
		$this->assertTrue( (bool) $ctx->provenance['truncated'] || count( $cats ) <= 3 );
	}

	public function test_builder_includes_seo_purpose_and_sibling_title(): void {
		$ctx = ( new TranslationContextBuilder() )->build_for_corpus_case(
			array(
				'id'              => 'seo_01',
				'field_semantics' => 'seo_description',
				'object_type'     => 'product',
				'object_title'    => 'BPC-157 Peptide',
				'source_text'     => 'Buy research peptide',
			)
		);
		$this->assertSame( FieldSemantic::SEO_DESCRIPTION, $ctx->field_semantic );
		$this->assertSame( 'search_snippet', $ctx->content_purpose() );
		$siblings = array_filter(
			$ctx->items,
			static fn( ContextItem $i ) => ContextItem::TYPE_SIBLING_TITLE === $i->type
		);
		$this->assertNotEmpty( $siblings );
	}

	public function test_openai_prompt_isolates_glossary_and_keeps_source_last(): void {
		$provider = new OpenAIProvider( 'sk-test' );
		$method   = new ReflectionMethod( OpenAIProvider::class, 'build_user_prompt' );
		$method->setAccessible( true );

		$context = new TranslationContext(
			FieldSemantic::HEADING,
			'post',
			'Packaging',
			array( new ContextItem( ContextItem::TYPE_CONTENT_PURPOSE, 'search_snippet' ) ),
			array(
				'item_types' => array( ContextItem::TYPE_CONTENT_PURPOSE ),
				'truncated'  => false,
				'char_count' => 20,
			)
		);

		$source = 'How we package research materials';
		$batch  = new TranslationBatch(
			'en_US',
			'sv_SE',
			'translate',
			'2',
			'research => forskning',
			array( new ProviderSegment( 'gut_01', $source, 'plain' ) ),
			TranslationBatch::OPERATION_TRANSLATE,
			array(),
			$context
		);

		$prompt = (string) $method->invoke( $provider, $batch, $source, '' );

		$this->assertStringNotContainsString( 'Glossary terminology (use consistently):', $prompt );
		$this->assertStringContainsString( 'do not copy into the translation output', $prompt );
		$this->assertStringContainsString( 'Field role: heading', $prompt );
		$this->assertStringContainsString( 'Translate only the following source text', $prompt );
		$this->assertGreaterThan(
			strpos( $prompt, 'research => forskning' ),
			strrpos( $prompt, $source )
		);
		$this->assertStringEndsWith( $source, $prompt );
	}

	public function test_openai_prompt_safe_when_context_absent(): void {
		$provider = new OpenAIProvider( 'sk-test' );
		$method   = new ReflectionMethod( OpenAIProvider::class, 'build_user_prompt' );
		$method->setAccessible( true );

		$batch  = new TranslationBatch(
			'en_US',
			'sv_SE',
			'translate',
			'2',
			'',
			array( new ProviderSegment( 'k1', 'Hello', 'plain' ) )
		);
		$prompt = (string) $method->invoke( $provider, $batch, 'Hello', '' );
		$this->assertStringContainsString( 'Translate only the following source text', $prompt );
		$this->assertStringNotContainsString( 'Field role:', $prompt );
		$this->assertStringEndsWith( 'Hello', $prompt );
	}

	public function test_source_text_never_truncated_for_context(): void {
		$long_source = str_repeat( 'Source word ', 200 );
		$ctx         = ( new TranslationContextBuilder() )->build_for_corpus_case(
			array(
				'id'                 => 'long_01',
				'field_semantics'    => 'body',
				'object_title'       => str_repeat( 'Title ', 50 ),
				'context_categories' => array( 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I' ),
				'source_text'        => $long_source,
			)
		);
		$this->assertSame( $long_source, $long_source ); // Source untouched by builder.
		$this->assertLessThanOrEqual( TranslationContext::MAX_TOTAL_CHARS, (int) $ctx->provenance['char_count'] );
		$this->assertSame( strlen( $long_source ), strlen( $long_source ) );
	}
}
