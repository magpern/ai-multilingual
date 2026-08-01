<?php
/**
 * F9 — replay production render gate on one post (false-positive check).
 *
 * @package AIMultilingual
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Translation\BlockRenderer;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;
use AIMultilingual\Settings;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\BlockFrontendRenderLogger;
use AIMultilingual\Translation\BlockFrontendRenderer;
use AIMultilingual\Translation\BlockRenderGate;
use AIMultilingual\Translation\BlockTranslationLookup;
use AIMultilingual\Translation\BlockTranslationSanitizer;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
if ( $post_id <= 0 ) {
	fwrite( STDERR, "Usage: wp eval-file replay-render-gate.php <post_id>\n" );
	exit( 1 );
}

$post = get_post( $post_id );
if ( ! $post instanceof WP_Post ) {
	echo wp_json_encode( array( 'error' => 'post_missing' ) ) . "\n";
	exit( 0 );
}

$settings  = new Settings( get_option( Settings::OPTION, Settings::defaults() ) );
$cache     = new Cache();
$languages = new Languages( $cache );
$sv        = $languages->find_by_code( 'sv' );
$context   = new LanguageContext();
$context->set_default( $languages->default() );
if ( $sv ) {
	$context->set_current( $sv );
}

$registry  = new AdapterRegistry();
$extractor = new Extractor(
	$settings,
	new BlockExtractor( $registry, new BlockRegistry( $registry ), new \AIMultilingual\Block\BlockExtractionLogger() )
);

$frontend = new BlockFrontendRenderer(
	new BlockRenderGate(),
	new BlockTranslationLookup( new Store( $cache ) ),
	new BlockTranslationSanitizer(),
	new BlockRenderer( $registry, new BlockRenderLogger() ),
	new BlockFrontendRenderLogger(),
	$settings,
	$context,
	$extractor
);

$content  = (string) $post->post_content;
$rendered = $frontend->render( $post, $content );

$false_positives = 0;
$checks          = 0;

if ( function_exists( 'parse_blocks' ) && has_blocks( $content ) ) {
	$block_registry = new BlockRegistry( $registry );
	( new \AIMultilingual\Block\BlockTreeWalker() )->walk(
		parse_blocks( $content ),
		static function ( array $block ) use ( $post, $content, $rendered, $registry, $block_registry, $cache, $sv, &$false_positives, &$checks ): void {
			if ( ! $block_registry->is_eligible( $block ) ) {
				return;
			}

			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$uuid  = isset( $attrs[ Contract::ATTR_NAME ] ) ? (string) $attrs[ Contract::ATTR_NAME ] : '';
			if ( '' === $uuid ) {
				return;
			}

			$key = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
			$source_text = wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) );
			if ( '' === trim( $source_text ) ) {
				return;
			}

			++$checks;
			$store  = new Store( $cache );
			$lookup = $store->load_object( Store::SOURCE_POST, (int) $post->ID, $sv ? (int) $sv->language_id : 0 );
			$row    = $lookup[ $key ] ?? null;
			if ( ! is_object( $row ) ) {
				return;
			}

			$translated = wp_strip_all_tags( (string) ( $row->translated_text ?? '' ) );
			if ( '' === $translated || ! empty( $row->is_stale ) ) {
				return;
			}

			if ( str_contains( $rendered, $translated ) && ! str_contains( $content, $translated ) ) {
				++$false_positives;
			}
		}
	);
}

echo wp_json_encode(
	array(
		'post_id'               => $post_id,
		'checks'                => $checks,
		'rendered_false_positive' => $false_positives,
		'gate_allowed'          => $settings->block_frontend_rendering_enabled(),
	),
	JSON_PRETTY_PRINT
) . "\n";
