<?php
/**
 * A.4 Nested Gutenberg admission and duplicate-protection tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\BlockRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Bounded A.4 nested surface: structural transparency, list, hosts, duplicates.
 *
 * @covers \AIMultilingual\Block\BlockRegistry
 * @covers \AIMultilingual\Translation\BlockExtractor
 * @covers \AIMultilingual\Translation\BlockRenderer
 */
final class NestedGutenbergAdmissionTest extends TestCase {

	private const UUID_P1  = '11111111-1111-4111-8111-111111111101';
	private const UUID_P2  = '11111111-1111-4111-8111-111111111102';
	private const UUID_H1  = '22222222-2222-4222-8222-222222222201';
	private const UUID_LI1 = '33333333-3333-4333-8333-333333333301';
	private const UUID_LI2 = '33333333-3333-4333-8333-333333333302';
	private const UUID_LI3 = '33333333-3333-4333-8333-333333333303';
	private const UUID_LI4 = '33333333-3333-4333-8333-333333333304';
	private const UUID_QP  = '44444444-4444-4444-8444-444444444401';

	private BlockExtractor $extractor;
	private BlockRenderer $renderer;
	private BlockRegistry $registry;

	protected function setUp(): void {
		parent::setUp();

		$adapters        = new AdapterRegistry();
		$this->registry  = new BlockRegistry( $adapters );
		$this->extractor = new BlockExtractor( $adapters, $this->registry, new BlockExtractionLogger() );
		$this->renderer  = new BlockRenderer( $adapters, new BlockRenderLogger() );
	}

	public function test_structural_classification_helpers(): void {
		foreach ( BlockRegistry::STRUCTURAL_TRANSPARENT_BLOCKS as $name ) {
			$this->assertTrue( $this->registry->is_structural_transparent( $name ) );
			$this->assertFalse( $this->registry->is_supported( $name ) );
		}
		foreach ( BlockRegistry::CHILD_TRAVERSAL_HOST_BLOCKS as $name ) {
			$this->assertTrue( $this->registry->is_child_traversal_host( $name ) );
			$this->assertFalse( $this->registry->is_supported( $name ) );
		}
		$this->assertTrue( $this->registry->is_dynamic( 'core/navigation' ) );
		$this->assertTrue( $this->registry->is_dynamic( 'core/query' ) );
		$this->assertTrue( $this->registry->is_dynamic( 'core/block' ) );
	}

	public function test_nested_leaf_inside_group_is_eligible_and_extracts(): void {
		$blocks   = array( $this->group( array( $this->paragraph( 'Inside group', self::UUID_P1 ) ) ) );
		$segments = $this->extractor->extract_blocks( $blocks );
		$key      = SegmentKey::build( self::UUID_P1, Contract::FIELD_CONTENT );

		$this->assertCount( 1, $segments );
		$this->assertArrayHasKey( $key, $segments );
		$this->assertSame( 'core/paragraph', $segments[ $key ]['block_name'] );
		$this->assertFalse( $this->registry->is_eligible( $blocks[0] ) );
		$this->assertTrue( $this->registry->is_eligible( $blocks[0]['innerBlocks'][0] ) );
	}

	public function test_columns_column_children_extract_without_parent_units(): void {
		$blocks   = array(
			$this->container(
				'core/columns',
				array(
					$this->container(
						'core/column',
						array( $this->paragraph( 'Left', self::UUID_P1 ) )
					),
					$this->container(
						'core/column',
						array( $this->heading( 'Right', self::UUID_H1 ) )
					),
				)
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertCount( 2, $segments );
		$this->assertSame( array(), $this->units_for_names( $segments, array( 'core/columns', 'core/column' ) ) );
	}

	public function test_unsupported_child_inside_structural_parent_stays_source(): void {
		$blocks   = array(
			$this->group(
				array(
					$this->paragraph( 'Keep', self::UUID_P1 ),
					$this->leaf( 'core/separator', '<hr class="wp-block-separator"/>' ),
				)
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertCount( 1, $segments );
	}

	public function test_list_wrapper_never_extracts_and_nested_list_items_do(): void {
		$blocks   = array( $this->nested_list_fixture() );
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertCount( 4, $segments );
		$this->assertSame( array(), $this->units_for_names( $segments, array( 'core/list' ) ) );
		foreach ( array( self::UUID_LI1, self::UUID_LI2, self::UUID_LI3, self::UUID_LI4 ) as $uuid ) {
			$this->assertArrayHasKey( SegmentKey::build( $uuid, Contract::FIELD_CONTENT ), $segments );
		}
	}

	public function test_parent_list_item_with_inner_blocks_is_not_extracted(): void {
		$parent = array(
			'blockName'    => 'core/list-item',
			'attrs'        => array( Contract::ATTR_NAME => '33333333-3333-4333-8333-333333333399' ),
			'innerBlocks'  => array(
				$this->container(
					'core/list',
					array( $this->list_item( 'Nested leaf', self::UUID_LI2 ) )
				),
			),
			'innerHTML'    => '<li>Parent text',
			'innerContent' => array( '<li>Parent text', null, '</li>' ),
		);

		$segments = $this->extractor->extract_blocks( array( $this->container( 'core/list', array( $parent ) ) ) );

		$this->assertCount( 1, $segments );
		$this->assertArrayHasKey( SegmentKey::build( self::UUID_LI2, Contract::FIELD_CONTENT ), $segments );
		$this->assertArrayNotHasKey(
			SegmentKey::build( '33333333-3333-4333-8333-333333333399', Contract::FIELD_CONTENT ),
			$segments
		);
	}

	public function test_list_reorder_preserves_keys(): void {
		$blocks = array( $this->nested_list_fixture() );
		$before = array_keys( $this->extractor->extract_blocks( $blocks ) );

		$items                       = $blocks[0]['innerBlocks'];
		$tmp                         = $items[0];
		$blocks[0]['innerBlocks'][0] = $items[2];
		$blocks[0]['innerBlocks'][2] = $tmp;
		$after                       = array_keys( $this->extractor->extract_blocks( $blocks ) );

		sort( $before );
		sort( $after );
		$this->assertSame( $before, $after );
	}

	public function test_duplicate_segment_key_prevented(): void {
		$blocks = array(
			$this->paragraph( 'First', self::UUID_P1 ),
			$this->paragraph( 'Second', self::UUID_P1 ),
		);

		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertCount( 1, $segments );
	}

	public function test_list_inside_group_and_column(): void {
		$blocks   = array(
			$this->group(
				array(
					$this->container(
						'core/columns',
						array(
							$this->container(
								'core/column',
								array( $this->nested_list_fixture() )
							),
						)
					),
				)
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertCount( 4, $segments );
		$this->assertSame( array(), $this->units_for_names( $segments, array( 'core/list', 'core/group', 'core/columns', 'core/column' ) ) );
	}

	public function test_quote_child_extracts_without_citation_unit(): void {
		$blocks   = array(
			$this->container(
				'core/quote',
				array( $this->paragraph( 'Quoted body', self::UUID_QP ) ),
				'<blockquote class="wp-block-quote"><cite>Citation stays source</cite></blockquote>'
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertCount( 1, $segments );
		$this->assertArrayHasKey( SegmentKey::build( self::UUID_QP, Contract::FIELD_CONTENT ), $segments );
		$this->assertSame( array(), $this->units_for_names( $segments, array( 'core/quote' ) ) );
	}

	public function test_details_cover_media_text_children(): void {
		$blocks   = array(
			$this->container(
				'core/details',
				array( $this->paragraph( 'Details body', self::UUID_P1 ) )
			),
			$this->container(
				'core/cover',
				array( $this->heading( 'Cover heading', self::UUID_H1 ) )
			),
			$this->container(
				'core/media-text',
				array( $this->paragraph( 'Media text body', self::UUID_P2 ) )
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertCount( 3, $segments );
		$this->assertSame(
			array(),
			$this->units_for_names( $segments, array( 'core/details', 'core/cover', 'core/media-text' ) )
		);
	}

	public function test_dynamic_shared_blocks_extract_nothing(): void {
		$blocks = array(
			$this->leaf( 'core/navigation', '' ),
			$this->leaf( 'core/query', '<div class="wp-block-query"></div>' ),
			$this->leaf( 'core/block', '' ),
		);

		$this->assertSame( array(), $this->extractor->extract_blocks( $blocks ) );
	}

	public function test_render_nested_children_without_duplicate_overlay(): void {
		$blocks = array(
			$this->group(
				array(
					$this->paragraph( 'One', self::UUID_P1 ),
					$this->heading( 'Two', self::UUID_H1 ),
				)
			),
		);
		$map    = array(
			SegmentKey::build( self::UUID_P1, Contract::FIELD_CONTENT ) => 'Ett',
			SegmentKey::build( self::UUID_H1, Contract::FIELD_CONTENT ) => 'Två',
		);

		$this->renderer->render( $blocks, $map );
		$this->assertSame( '<p>Ett</p>', $blocks[0]['innerBlocks'][0]['innerHTML'] );
		$this->assertSame( '<h2>Två</h2>', $blocks[0]['innerBlocks'][1]['innerHTML'] );

		$this->renderer->render( $blocks, $map );
		$this->assertSame( '<p>Ett</p>', $blocks[0]['innerBlocks'][0]['innerHTML'] );
	}

	public function test_move_child_between_containers_preserves_key(): void {
		$blocks                     = array(
			$this->group( array( $this->paragraph( 'Move me', self::UUID_P1 ) ) ),
			$this->group( array() ),
		);
		$before                     = $this->extractor->extract_blocks( $blocks );
		$child                      = $blocks[0]['innerBlocks'][0];
		$blocks[0]['innerBlocks']   = array();
		$blocks[1]['innerBlocks'][] = $child;
		$after                      = $this->extractor->extract_blocks( $blocks );

		$key = SegmentKey::build( self::UUID_P1, Contract::FIELD_CONTENT );
		$this->assertArrayHasKey( $key, $before );
		$this->assertArrayHasKey( $key, $after );
	}

	public function test_no_duplicate_logical_units_across_host_and_child(): void {
		$blocks   = array(
			$this->container(
				'core/quote',
				array( $this->paragraph( 'Only once', self::UUID_QP ) )
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );
		$sources  = array_map(
			static fn( array $seg ): string => (string) $seg['source_text'],
			$segments
		);

		$this->assertCount( 1, $segments );
		$this->assertCount( 1, array_unique( $sources ) );
	}

	/**
	 * Builds the nested list admission fixture.
	 *
	 * @return array<string, mixed>
	 */
	private function nested_list_fixture(): array {
		return $this->container(
			'core/list',
			array(
				$this->list_item( 'Top one', self::UUID_LI1 ),
				array(
					'blockName'    => 'core/list-item',
					'attrs'        => array(),
					'innerBlocks'  => array(
						$this->container(
							'core/list',
							array(
								$this->list_item( 'Nested A', self::UUID_LI2 ),
								$this->list_item( 'Nested B', self::UUID_LI3 ),
							)
						),
					),
					'innerHTML'    => '<li>Parent with nest',
					'innerContent' => array( '<li>Parent with nest', null, '</li>' ),
				),
				$this->list_item( 'Top three', self::UUID_LI4 ),
			)
		);
	}

	/**
	 * @param list<array<string, mixed>> $inner Inner blocks.
	 * @return array<string, mixed>
	 */
	private function group( array $inner ): array {
		return $this->container( 'core/group', $inner );
	}

	/**
	 * @param string                     $name  Block name.
	 * @param list<array<string, mixed>> $inner Inner blocks.
	 * @param string                     $html  Wrapper HTML.
	 * @return array<string, mixed>
	 */
	private function container( string $name, array $inner, string $html = '<div></div>' ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => array(),
			'innerBlocks'  => $inner,
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function paragraph( string $text, string $uuid ): array {
		return $this->leaf( 'core/paragraph', '<p>' . $text . '</p>', $uuid );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function heading( string $text, string $uuid ): array {
		$block                   = $this->leaf( 'core/heading', '<h2>' . $text . '</h2>', $uuid );
		$block['attrs']['level'] = 2;

		return $block;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function list_item( string $text, string $uuid ): array {
		return $this->leaf( 'core/list-item', '<li>' . $text . '</li>', $uuid );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function leaf( string $name, string $html, string $uuid = '' ): array {
		$attrs = array();
		if ( '' !== $uuid ) {
			$attrs[ Contract::ATTR_NAME ] = $uuid;
		}

		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * @param array<string, array<string, mixed>> $segments Segments.
	 * @param string[]                            $names    Block names.
	 * @return string[]
	 */
	private function units_for_names( array $segments, array $names ): array {
		$found = array();
		foreach ( $segments as $key => $seg ) {
			if ( in_array( (string) $seg['block_name'], $names, true ) ) {
				$found[] = (string) $key;
			}
		}

		return $found;
	}
}
