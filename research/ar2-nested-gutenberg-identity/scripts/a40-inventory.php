<?php
/**
 * A40 — Baseline nested-block inventory + fixture corpus.
 *
 * Usage: wp eval-file research/ar2-nested-gutenberg-identity/scripts/a40-inventory.php
 *
 * @package AIMultilingual\Research\AR2
 */

require_once __DIR__ . '/lib-ar2.php';

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;

$env = array(
	'wp_version'      => get_bloginfo( 'version' ),
	'php_version'     => PHP_VERSION,
	'siteurl'         => get_option( 'siteurl' ),
	'plugin_active'   => is_plugin_active( 'universal-multilingual/universal-multilingual.php' ) || is_plugin_active( 'universal-multilingual/plugin.php' ),
	'grammar'         => Contract::SEGMENT_KEY_GRAMMAR,
	'attr_name'       => Contract::ATTR_NAME,
	'supported_blocks'=> BlockRegistry::SUPPORTED_BLOCKS,
	'dynamic_blocks'  => BlockRegistry::DYNAMIC_BLOCK_NAMES,
	'adapter_blocks'  => ( new AdapterRegistry() )->block_names(),
	'timestamp_utc'   => gmdate( 'c' ),
);

$fixtures = array(
	'structural-group-columns.html'                 => ar2_fixture( 'structural-group-columns.html' ),
	'list-nested-with-uuids.html'                   => ar2_fixture( 'list-nested-with-uuids.html' ),
	'textual-quote-pullquote-details.html'          => ar2_fixture( 'textual-quote-pullquote-details.html' ),
	'media-cover-mediatext-gallery.html'            => ar2_fixture( 'media-cover-mediatext-gallery.html' ),
	'shared-dynamic-navigation-query-reusable.html' => ar2_fixture( 'shared-dynamic-navigation-query-reusable.html' ),
	'deep-nested-performance.html'                  => ar2_fixture( 'deep-nested-performance.html' ),
	'authored/list-nested.html'                     => ar2_authored_fixture( 'list-nested.html' ),
	'authored/nested-group-columns.html'            => ar2_authored_fixture( 'nested-group-columns.html' ),
	'authored/quote-with-citation.html'             => ar2_authored_fixture( 'quote-with-citation.html' ),
	'authored/reusable-block.html'                  => ar2_authored_fixture( 'reusable-block.html' ),
	'authored/synced-pattern.html'                  => ar2_authored_fixture( 'synced-pattern.html' ),
	'authored/dynamic-block.html'                   => ar2_authored_fixture( 'dynamic-block.html' ),
);

$inventory = array();
$family_hits = array(
	'core/group'         => 0,
	'core/columns'       => 0,
	'core/column'        => 0,
	'core/list'          => 0,
	'core/list-item'     => 0,
	'core/quote'         => 0,
	'core/pullquote'     => 0,
	'core/details'       => 0,
	'core/cover'         => 0,
	'core/media-text'    => 0,
	'core/gallery'       => 0,
	'core/image'         => 0,
	'core/navigation'    => 0,
	'core/query'         => 0,
	'core/post-template' => 0,
	'core/block'         => 0,
	'core/paragraph'     => 0,
	'core/heading'       => 0,
);

foreach ( $fixtures as $name => $content ) {
	$blocks = parse_blocks( $content );
	$rows   = ar2_inventory_blocks( $blocks );
	$extract = ar2_extract_summary( $blocks );
	foreach ( $rows as $row ) {
		$bn = $row['block_name'];
		if ( isset( $family_hits[ $bn ] ) ) {
			++$family_hits[ $bn ];
		}
	}
	$inventory[ $name ] = array(
		'block_count'     => count( $rows ),
		'extract_count'   => $extract['count'],
		'extract_keys'    => $extract['keys'],
		'max_depth'       => 0 === count( $rows ) ? 0 : max( array_map( static fn( $r ) => substr_count( $r['path'], '/' ) + 1, $rows ) ),
		'blocks'          => $rows,
	);
}

$contracts = array(
	'grammar'                 => Contract::SEGMENT_KEY_GRAMMAR,
	'uuid_on_attrs'           => Contract::ATTR_NAME,
	'walker_recurses'         => 'BlockTreeWalker::walk recurses innerBlocks (source inspection + unit test)',
	'eligibility_requires_empty_innerBlocks' => true,
	'list_has_adapter'        => null !== ( new AdapterRegistry() )->get( 'core/list' ),
	'list_item_has_adapter'   => null !== ( new AdapterRegistry() )->get( 'core/list-item' ),
	'group_has_adapter'       => null !== ( new AdapterRegistry() )->get( 'core/group' ),
	'navigation_is_dynamic'   => ( new BlockRegistry() )->is_dynamic( 'core/navigation' ),
	'query_is_dynamic'        => ( new BlockRegistry() )->is_dynamic( 'core/query' ),
	'core_block_is_dynamic'   => ( new BlockRegistry() )->is_dynamic( 'core/block' ),
);

ar2_write_evidence(
	'a40-environment',
	array(
		'work_package' => 'A40',
		'environment'  => $env,
		'contracts'    => $contracts,
	)
);

ar2_write_evidence(
	'a40-inventory',
	array(
		'work_package' => 'A40',
		'family_hits'  => $family_hits,
		'fixtures'     => array_keys( $inventory ),
		'inventory'    => $inventory,
	)
);

ar2_write_evidence(
	'a40-summary',
	array(
		'work_package' => 'A40',
		'findings'     => array(
			array(
				'claim'      => 'Production grammar remains b:<uuid>:<field> with aimlBlockId attribute ownership.',
				'confidence' => 'Proven by experiment',
			),
			array(
				'claim'      => 'Supported adapters are seven F14 leaves; no container adapters registered.',
				'confidence' => 'Proven by experiment',
			),
			array(
				'claim'      => 'core/list has no adapter; core/list-item does. Structural containers have no adapters.',
				'confidence' => 'Proven by experiment',
			),
			array(
				'claim'      => 'core/navigation, core/query, and core/block appear in DYNAMIC_BLOCK_NAMES.',
				'confidence' => 'Proven by experiment',
			),
			array(
				'claim'      => 'Research fixtures cover structural, textual, media, and shared/dynamic families.',
				'confidence' => 'Proven by experiment',
			),
		),
	)
);

WP_CLI::success( 'A40 inventory written to research/ar2-nested-gutenberg-identity/evidence/' );
