<?php
/**
 * A.R2 EXPERIMENTAL — Nested Gutenberg research helpers.
 *
 * Research-only. Load via: wp eval-file research/ar2-nested-gutenberg-identity/scripts/<script>.php
 * Do NOT register via Plugin.php.
 *
 * @package AIMultilingual\Research\AR2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "A.R2 research script must run via WP-CLI eval-file.\n" );
}

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockIdentityLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\BlockTreeWalker;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Block\UuidInjector;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\BlockRenderer;

/**
 * Absolute path to research root.
 */
function ar2_root(): string {
	return dirname( __DIR__ );
}

/**
 * Write JSON evidence file.
 *
 * @param string               $name Evidence basename without extension.
 * @param array<string, mixed> $data Payload.
 */
function ar2_write_evidence( string $name, array $data ): string {
	$dir = ar2_root() . '/evidence';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$path = $dir . '/' . $name . '.json';
	$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( false === $json ) {
		WP_CLI::error( 'Failed to encode evidence JSON for ' . $name );
	}
	file_put_contents( $path, $json . "\n" );
	return $path;
}

/**
 * Load a sanitized fixture file.
 *
 * @param string $relative Relative path under fixtures/.
 */
function ar2_fixture( string $relative ): string {
	$path = ar2_root() . '/fixtures/' . ltrim( $relative, '/' );
	if ( ! is_readable( $path ) ) {
		WP_CLI::error( 'Missing fixture: ' . $path );
	}
	return (string) file_get_contents( $path );
}

/**
 * Also load authored test fixtures (read-only, not modified).
 *
 * @param string $name Filename under tests/fixtures/blocks/authored/.
 */
function ar2_authored_fixture( string $name ): string {
	$path = dirname( ar2_root(), 2 ) . '/tests/fixtures/blocks/authored/' . $name;
	if ( ! is_readable( $path ) ) {
		WP_CLI::error( 'Missing authored fixture: ' . $path );
	}
	return (string) file_get_contents( $path );
}

/**
 * @return BlockExtractor
 */
function ar2_extractor(): BlockExtractor {
	$adapters = new AdapterRegistry();
	return new BlockExtractor( $adapters, new BlockRegistry( $adapters ), new BlockExtractionLogger() );
}

/**
 * @return BlockRenderer
 */
function ar2_renderer(): BlockRenderer {
	return new BlockRenderer( new AdapterRegistry(), new BlockRenderLogger() );
}

/**
 * @return UuidInjector
 */
function ar2_injector(): UuidInjector {
	return new UuidInjector( new BlockRegistry( new AdapterRegistry() ), new BlockIdentityLogger() );
}

/**
 * Inventory every real block with nesting context.
 *
 * @param array<int, array<string, mixed>> $blocks Parsed tree.
 * @param string                           $path   Structural path (context only).
 * @param string|null                      $parent Parent UUID if any.
 * @return list<array<string, mixed>>
 */
function ar2_inventory_blocks( array $blocks, string $path = '', ?string $parent = null ): array {
	$rows = array();
	$i    = 0;
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) || null === ( $block['blockName'] ?? null ) ) {
			++$i;
			continue;
		}
		$name   = (string) $block['blockName'];
		$attrs  = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$uuid   = isset( $attrs[ Contract::ATTR_NAME ] ) ? (string) $attrs[ Contract::ATTR_NAME ] : '';
		$inner  = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
		$node   = $path === '' ? (string) $i : $path . '/' . $i;
		$reg    = new BlockRegistry( new AdapterRegistry() );
		$adap   = ( new AdapterRegistry() )->get( $name );
		$rows[] = array(
			'path'                => $node,
			'block_name'          => $name,
			'uuid'                => $uuid,
			'parent_uuid'         => $parent,
			'inner_block_count'   => count( $inner ),
			'attr_keys'           => array_keys( $attrs ),
			'has_aiml_block_id'   => '' !== $uuid,
			'is_supported'        => $reg->is_supported( $name ),
			'is_dynamic'          => $reg->is_dynamic( $name ),
			'is_eligible'         => $reg->is_eligible( $block ),
			'has_adapter'         => null !== $adap,
			'adapter_translatable'=> null !== $adap ? $adap->is_translatable_instance( $block ) : false,
			'inner_html_len'      => strlen( (string) ( $block['innerHTML'] ?? '' ) ),
			'inner_html_preview'  => mb_substr( trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) ), 0, 80 ),
		);
		$rows   = array_merge( $rows, ar2_inventory_blocks( $inner, $node, '' !== $uuid ? $uuid : $parent ) );
		++$i;
	}
	return $rows;
}

/**
 * Collect UUID → segment key map from extractor.
 *
 * @param array<int, array<string, mixed>> $blocks Parsed tree.
 * @return array{keys: list<string>, by_uuid: array<string, string>, segments: array<string, array<string, mixed>>, count: int}
 */
function ar2_extract_summary( array $blocks ): array {
	$segments = ar2_extractor()->extract_blocks( $blocks );
	$by_uuid  = array();
	foreach ( $segments as $key => $seg ) {
		$by_uuid[ (string) $seg['block_uuid'] ] = (string) $key;
	}
	return array(
		'keys'     => array_keys( $segments ),
		'by_uuid'  => $by_uuid,
		'segments' => $segments,
		'count'    => count( $segments ),
	);
}

/**
 * Walk and collect uuid map keyed by path for stability diffs.
 *
 * @param array<int, array<string, mixed>> $blocks Parsed tree.
 * @return array<string, array{uuid: string, block_name: string}>
 */
function ar2_uuid_path_map( array $blocks, string $path = '' ): array {
	$map = array();
	$i   = 0;
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) || null === ( $block['blockName'] ?? null ) ) {
			++$i;
			continue;
		}
		$node  = $path === '' ? (string) $i : $path . '/' . $i;
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$uuid  = isset( $attrs[ Contract::ATTR_NAME ] ) ? (string) $attrs[ Contract::ATTR_NAME ] : '';
		$map[ $node ] = array(
			'uuid'       => $uuid,
			'block_name' => (string) $block['blockName'],
		);
		$inner = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
		$map   = array_merge( $map, ar2_uuid_path_map( $inner, $node ) );
		++$i;
	}
	return $map;
}

/**
 * Collect UUID set for leaves (independent of path).
 *
 * @param array<int, array<string, mixed>> $blocks Parsed tree.
 * @return array<string, string> uuid => block_name
 */
function ar2_uuid_set( array $blocks ): array {
	$set = array();
	( new BlockTreeWalker() )->walk(
		$blocks,
		static function ( array $block ) use ( &$set ): void {
			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$uuid  = isset( $attrs[ Contract::ATTR_NAME ] ) ? (string) $attrs[ Contract::ATTR_NAME ] : '';
			if ( '' !== $uuid ) {
				$set[ $uuid ] = (string) ( $block['blockName'] ?? '' );
			}
		}
	);
	return $set;
}

/**
 * Deep-copy a parsed block tree.
 *
 * @param array<int, array<string, mixed>> $blocks Tree.
 * @return array<int, array<string, mixed>>
 */
function ar2_copy( array $blocks ): array {
	return unserialize( serialize( $blocks ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- research deep copy
}

/**
 * Fixed research UUIDs (deterministic).
 *
 * @return array<string, string>
 */
function ar2_uuids(): array {
	return array(
		'p1'  => '11111111-1111-4111-8111-111111111101',
		'p2'  => '11111111-1111-4111-8111-111111111102',
		'p3'  => '11111111-1111-4111-8111-111111111103',
		'h1'  => '22222222-2222-4222-8222-222222222201',
		'li1' => '33333333-3333-4333-8333-333333333301',
		'li2' => '33333333-3333-4333-8333-333333333302',
		'li3' => '33333333-3333-4333-8333-333333333303',
		'li4' => '33333333-3333-4333-8333-333333333304',
		'li5' => '33333333-3333-4333-8333-333333333305',
		'qp'  => '44444444-4444-4444-8444-444444444401',
		'b1'  => '55555555-5555-4555-8555-555555555501',
	);
}
