<?php
/**
 * F9 — analyze aimlBlockId UUID state using production Strategy F classes.
 *
 * @package AIMultilingual
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockTreeWalker;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\UuidValidator;

$post_id      = isset( $args[0] ) ? (int) $args[0] : 0;
$baseline_arg = isset( $args[1] ) ? (string) $args[1] : '';

if ( $post_id <= 0 ) {
	fwrite( STDERR, "Usage: wp eval-file analyze-aiml-content.php <post_id> [baseline_content_file]\n" );
	exit( 1 );
}

$post = get_post( $post_id );
if ( ! $post instanceof WP_Post ) {
	fwrite( STDERR, "Post not found: {$post_id}\n" );
	exit( 1 );
}

$content = (string) $post->post_content;
$blocks  = array();

if ( function_exists( 'parse_blocks' ) && function_exists( 'has_blocks' ) && has_blocks( $content ) ) {
	$registry = new BlockRegistry();
	$index    = 0;

	( new BlockTreeWalker() )->walk(
		parse_blocks( $content ),
		static function ( array $block ) use ( &$blocks, &$index, $registry ): void {
			if ( ! $registry->is_eligible( $block ) ) {
				return;
			}

			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$uuid  = isset( $attrs[ Contract::ATTR_NAME ] ) ? (string) $attrs[ Contract::ATTR_NAME ] : '';
			$text  = wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) );

			$blocks[] = array(
				'index'      => $index,
				'uuid'       => $uuid,
				'valid_uuid' => UuidValidator::is_valid_non_empty( $uuid ),
				'block_name' => (string) ( $block['blockName'] ?? '' ),
				'text_sha1'  => sha1( $text ),
			);
			++$index;
		}
	);
}

$uuid_counts = array();
foreach ( $blocks as $block ) {
	if ( '' === $block['uuid'] ) {
		continue;
	}
	$uuid_counts[ $block['uuid'] ] = ( $uuid_counts[ $block['uuid'] ] ?? 0 ) + 1;
}

$dupes = array();
foreach ( $uuid_counts as $uuid => $count ) {
	if ( $count > 1 ) {
		$dupes[ $uuid ] = $count;
	}
}

$analysis = array(
	'post_id'           => $post_id,
	'post_status'       => $post->post_status,
	'post_modified_gmt' => $post->post_modified_gmt,
	'content_bytes'     => strlen( $content ),
	'content_sha1'      => sha1( $content ),
	'eligible_blocks'   => count( $blocks ),
	'blocks'            => $blocks,
	'uuid_counts'       => $uuid_counts,
	'duplicate_uuids'   => $dupes,
	'has_aimlBlockId'   => str_contains( $content, '"' . Contract::ATTR_NAME . '"' ),
);

if ( '' !== $baseline_arg && file_exists( $baseline_arg ) ) {
	$before        = (string) file_get_contents( $baseline_arg );
	$before_blocks = array();
	$registry      = new BlockRegistry();
	$index         = 0;

	if ( function_exists( 'parse_blocks' ) && has_blocks( $before ) ) {
		( new BlockTreeWalker() )->walk(
			parse_blocks( $before ),
			static function ( array $block ) use ( &$before_blocks, &$index, $registry ): void {
				if ( ! $registry->is_eligible( $block ) ) {
					return;
				}

				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				$uuid  = isset( $attrs[ Contract::ATTR_NAME ] ) ? (string) $attrs[ Contract::ATTR_NAME ] : '';
				$text  = wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) );

				$before_blocks[] = array(
					'index'      => $index,
					'uuid'       => $uuid,
					'block_name' => (string) ( $block['blockName'] ?? '' ),
					'text_sha1'  => sha1( $text ),
				);
				++$index;
			}
		);
	}

	$analysis['baseline_source']     = 'file:' . $baseline_arg;
	$analysis['content_byte_diff']   = strlen( $content ) - strlen( $before );
	$analysis['content_identical']   = $content === $before;
	$analysis['baseline_blocks']     = $before_blocks;
	$analysis['uuid_preservation']   = array();

	foreach ( $blocks as $i => $block ) {
		$prev = $before_blocks[ $i ] ?? null;
		if ( null === $prev ) {
			$analysis['uuid_preservation'][ $i ] = 'new_block';
			continue;
		}
		if ( $prev['uuid'] === $block['uuid'] && '' !== $block['uuid'] ) {
			$analysis['uuid_preservation'][ $i ] = 'preserved';
		} elseif ( '' === $block['uuid'] ) {
			$analysis['uuid_preservation'][ $i ] = 'removed';
		} else {
			$analysis['uuid_preservation'][ $i ] = 'mutated';
		}
	}
}

echo wp_json_encode( $analysis, JSON_PRETTY_PRINT ) . "\n";
