<?php
/**
 * Unit tests for TI.3 tm_example context items and budgets.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\AI;

use AIMultilingual\Translation\AI\ContextItem;
use AIMultilingual\Translation\AI\FieldSemantic;
use AIMultilingual\Translation\AI\TranslationContext;
use AIMultilingual\Translation\AI\TranslationContextBuilder;
use PHPUnit\Framework\TestCase;

/**
 * TM example carrier bounds.
 */
final class TMExampleContextTest extends TestCase {

	public function test_tm_example_type_is_allowlisted(): void {
		$item = new ContextItem( ContextItem::TYPE_TM_EXAMPLE, 'src => tgt', 'approved_example' );
		$this->assertSame( ContextItem::TYPE_TM_EXAMPLE, $item->type );
	}

	public function test_with_tm_examples_caps_and_drops_before_essentials(): void {
		$builder = new TranslationContextBuilder();
		$base    = new TranslationContext(
			FieldSemantic::PRODUCT_TITLE,
			'product',
			'Title',
			array(
				new ContextItem( ContextItem::TYPE_CONTENT_PURPOSE, 'search_snippet' ),
				new ContextItem( ContextItem::TYPE_ATTRIBUTE_NAME, 'Color' ),
			),
			array(
				'item_types' => array( 'content_purpose', 'attribute_name' ),
				'truncated'  => false,
				'char_count' => 10,
			)
		);

		$examples = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$examples[] = array(
				'source_text' => 'Source example text number ' . $i,
				'target_text' => 'Target example text number ' . $i,
			);
		}

		$merged = $builder->with_tm_examples( $base, $examples );
		$tm     = 0;
		$has_purpose = false;
		foreach ( $merged->items as $item ) {
			if ( ContextItem::TYPE_TM_EXAMPLE === $item->type ) {
				++$tm;
				$this->assertLessThanOrEqual( TranslationContext::MAX_TM_EXAMPLE_VALUE, strlen( $item->value ) );
			}
			if ( ContextItem::TYPE_CONTENT_PURPOSE === $item->type ) {
				$has_purpose = true;
			}
		}

		$this->assertTrue( $has_purpose );
		$this->assertLessThanOrEqual( TranslationContext::MAX_TM_EXAMPLES, $tm );
		$this->assertSame( '2', $merged->schema_version );
	}

	public function test_same_lang_alone_is_not_a_context_item_type(): void {
		$this->assertNotContains( 'same_lang_approved', ContextItem::allowed_types() );
	}
}
