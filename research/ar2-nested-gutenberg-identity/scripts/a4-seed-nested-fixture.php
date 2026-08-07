<?php
/**
 * A.4 nested Gutenberg browser/HTTP fixture seed.
 *
 * Creates/updates published page slug `a4-nested-gutenberg-fixture` with
 * deterministic aimlBlockId UUIDs and SV Store overlays.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/ai-multilingual/research/ar2-nested-gutenberg-identity/scripts/a4-seed-nested-fixture.php
 *
 * @package AIMultilingual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Language\Languages;
use AIMultilingual\Rollout\RolloutAuditLogger;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Rollout\RolloutConfigurationService;
use AIMultilingual\Settings;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

/**
 * Deterministic A.4 fixture UUIDs.
 *
 * @return array<string, string>
 */
function aiml_a4_uuids(): array {
	return array(
		'group_p'    => '11111111-1111-4111-8111-111111111101',
		'columns_h'  => '22222222-2222-4222-8222-222222222201',
		'li1'        => '33333333-3333-4333-8333-333333333301',
		'li2'        => '33333333-3333-4333-8333-333333333302',
		'li3'        => '33333333-3333-4333-8333-333333333303',
		'li4'        => '33333333-3333-4333-8333-333333333304',
		'quote_p'    => '44444444-4444-4444-8444-444444444401',
		'details_p'  => '11111111-1111-4111-8111-111111111103',
		'cover_h'    => '22222222-2222-4222-8222-222222222202',
		'mediatext_p'=> '11111111-1111-4111-8111-111111111104',
		'pullquote_p'=> '11111111-1111-4111-8111-111111111105',
		'deep_p'     => '11111111-1111-4111-8111-111111111106',
	);
}

/**
 * Serialized nested Gutenberg content for A.4 acceptance.
 */
function aiml_a4_fixture_content(): string {
	$u = aiml_a4_uuids();

	return <<<HTML
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">A4 Nested Gutenberg Fixture</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"aimlBlockId":"{$u['group_p']}"} -->
<p>A4 Group Paragraph Source</p>
<!-- /wp:paragraph -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator --></div>
<!-- /wp:group -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"aimlBlockId":"{$u['columns_h']}"} -->
<h3 class="wp-block-heading">A4 Columns Heading Source</h3>
<!-- /wp:heading --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item {"aimlBlockId":"{$u['li1']}"} -->
<li>A4 List Item One Source</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>A4 Parent List Item Nest Host<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item {"aimlBlockId":"{$u['li2']}"} -->
<li>A4 Nested List Item A Source</li>
<!-- /wp:list-item -->

<!-- wp:list-item {"aimlBlockId":"{$u['li3']}"} -->
<li>A4 Nested List Item B Source</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></li>
<!-- /wp:list-item -->

<!-- wp:list-item {"aimlBlockId":"{$u['li4']}"} -->
<li>A4 List Item Three Source</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph {"aimlBlockId":"{$u['quote_p']}"} -->
<p>A4 Quote Paragraph Source</p>
<!-- /wp:paragraph --><cite>A4 Quote Citation Remains Source</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:details -->
<details class="wp-block-details"><summary>A4 Details Summary Remains Source</summary><!-- wp:paragraph {"aimlBlockId":"{$u['details_p']}"} -->
<p>A4 Details Paragraph Source</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:cover {"overlayColor":"black","isUserOverlayColor":true,"minHeight":160,"dimRatio":50} -->
<div class="wp-block-cover" style="min-height:160px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":3,"aimlBlockId":"{$u['cover_h']}"} -->
<h3 class="wp-block-heading has-text-align-center">A4 Cover Heading Source</h3>
<!-- /wp:heading --></div></div>
<!-- /wp:cover -->

<!-- wp:media-text {"mediaType":"image"} -->
<div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="https://dev.biopentra.eu/wp-content/uploads/2024/01/placeholder.png" alt=""/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph {"aimlBlockId":"{$u['mediatext_p']}"} -->
<p>A4 Media Text Paragraph Source</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->

<!-- wp:pullquote -->
<figure class="wp-block-pullquote"><!-- wp:paragraph {"aimlBlockId":"{$u['pullquote_p']}"} -->
<p>A4 Pullquote Nested Child Source</p>
<!-- /wp:paragraph --></figure>
<!-- /wp:pullquote -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"aimlBlockId":"{$u['deep_p']}"} -->
<p>A4 Deep Nested Paragraph Source</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
HTML;
}

/**
 * Source HTML + SV overlay pairs keyed by UUID alias.
 *
 * @return list<array{0:string,1:string,2:string,3:string}>
 */
function aiml_a4_translation_rows(): array {
	$u = aiml_a4_uuids();

	return array(
		array( $u['group_p'], '<p>A4 Group Paragraph Source</p>', 'A4 Grupp Stycke Mål', 'p' ),
		array( $u['columns_h'], '<h3 class="wp-block-heading">A4 Columns Heading Source</h3>', 'A4 Kolumn Rubrik Mål', 'h3' ),
		array( $u['li1'], '<li>A4 List Item One Source</li>', 'A4 Lista Ett Mål', 'li' ),
		array( $u['li2'], '<li>A4 Nested List Item A Source</li>', 'A4 Nästlad Lista A Mål', 'li' ),
		array( $u['li3'], '<li>A4 Nested List Item B Source</li>', 'A4 Nästlad Lista B Mål', 'li' ),
		array( $u['li4'], '<li>A4 List Item Three Source</li>', 'A4 Lista Tre Mål', 'li' ),
		array( $u['quote_p'], '<p>A4 Quote Paragraph Source</p>', 'A4 Citat Stycke Mål', 'p' ),
		array( $u['details_p'], '<p>A4 Details Paragraph Source</p>', 'A4 Detaljer Stycke Mål', 'p' ),
		array( $u['cover_h'], '<h3 class="wp-block-heading has-text-align-center">A4 Cover Heading Source</h3>', 'A4 Omslag Rubrik Mål', 'h3' ),
		array( $u['mediatext_p'], '<p>A4 Media Text Paragraph Source</p>', 'A4 Mediatext Stycke Mål', 'p' ),
		array( $u['pullquote_p'], '<p>A4 Pullquote Nested Child Source</p>', 'A4 Pullquote Barn Mål', 'p' ),
		array( $u['deep_p'], '<p>A4 Deep Nested Paragraph Source</p>', 'A4 Djup Nästlad Stycke Mål', 'p' ),
	);
}

/**
 * Seeds the A.4 nested Gutenberg fixture page + SV translations.
 *
 * @return array<string, mixed>
 */
function aiml_a4_seed_nested_fixture(): array {
	$slug    = 'a4-nested-gutenberg-fixture';
	$title   = 'A4 Nested Gutenberg Fixture';
	$content = aiml_a4_fixture_content();

	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	$post_id  = $existing ? (int) $existing->ID : 0;

	if ( $post_id <= 0 ) {
		$post_id = (int) wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => $content,
			),
			true
		);
		if ( $post_id <= 0 || is_wp_error( $post_id ) ) {
			WP_CLI::error( 'Failed to create A4 fixture page.' );
		}
	} else {
		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_content' => $content,
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			WP_CLI::error( 'Failed to update A4 fixture page: ' . $updated->get_error_message() );
		}
	}

	// Ensure block feature flags remain on for acceptance.
	$settings = new Settings();
	$current  = $settings->get();
	$current['block_attr_registration_enabled'] = true;
	$current['block_uuid_injection_enabled']    = true;
	$current['block_extraction_enabled']        = true;
	$current['block_frontend_rendering_enabled'] = true;
	$settings->save( $current );

	$sv = null;
	foreach ( ( new Languages( new Cache() ) )->all() as $lang ) {
		if ( 'sv' === (string) ( $lang->code ?? '' ) ) {
			$sv = $lang;
			break;
		}
	}
	if ( null === $sv ) {
		WP_CLI::error( 'Swedish language missing.' );
	}

	$store     = new Store( new Cache() );
	$extractor = new BlockExtractor( new AdapterRegistry(), new BlockRegistry( new AdapterRegistry() ), new BlockExtractionLogger() );
	$segments  = $extractor->extract_content( $content );

	$saved = 0;
	foreach ( aiml_a4_translation_rows() as [ $uuid, $source_html, $translated, $tag ] ) {
		$key = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		if ( ! isset( $segments[ $key ] ) ) {
			WP_CLI::warning( "Extracted segments missing expected key {$key}" );
		}
		// Prefer extracted canonical source when present (hash alignment).
		$source = isset( $segments[ $key ]['source_text'] )
			? (string) $segments[ $key ]['source_text']
			: $source_html;

		$result = $store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'page',
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => $source,
				'translated_text' => $translated,
				'status'          => Store::STATUS_REVIEWED,
			)
		);
		if ( is_wp_error( $result ) ) {
			WP_CLI::warning( "Save failed for {$key}: " . $result->get_error_message() );
			continue;
		}
		++$saved;
	}

	$store->sync_source( Store::SOURCE_POST, $post_id, 'page', $segments );

	// Limited rollout requires an explicit post allowlist for block frontend overlays.
	$rollout_repo = new RolloutConfigurationRepository();
	$current      = $rollout_repo->get()->to_array();
	$ids          = array_values(
		array_unique(
			array_map(
				'intval',
				array_merge( (array) ( $current['allowed_post_ids'] ?? array() ), array( $post_id ) )
			)
		)
	);
	$proposed                           = $current;
	$proposed['allowed_post_ids']       = $ids;
	$proposed['rollout_render_enabled'] = true;
	$proposed['rollout_stage']          = max( 2, (int) ( $current['rollout_stage'] ?? 0 ) );
	$proposed['allowed_language_codes'] = array_values(
		array_unique(
			array_merge( (array) ( $current['allowed_language_codes'] ?? array() ), array( 'sv' ) )
		)
	);
	unset( $proposed['schema_version'], $proposed['policy_version'], $proposed['updated_at'], $proposed['updated_by'] );
	$rollout = ( new RolloutConfigurationService( $rollout_repo, new RolloutAuditLogger() ) )
		->apply( $proposed, 1, 'a4-nested-fixture-seed' );

	wp_cache_flush();
	clean_post_cache( $post_id );

	$out = array(
		'post_id'          => $post_id,
		'slug'             => $slug,
		'url'              => get_permalink( $post_id ),
		'sv_url'           => trailingslashit( home_url( '/sv/' . get_page_uri( $post_id ) ) ),
		'unit_count'       => count( $segments ),
		'segment_keys'     => array_keys( $segments ),
		'saved_sv'         => $saved,
		'uuids'            => aiml_a4_uuids(),
		'rollout_allowlist'=> $ids,
		'rollout_ok'       => (bool) $rollout->valid,
	);

	WP_CLI::success( wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

	return $out;
}

// Only auto-run when this file is the eval-file entrypoint (not when required).
if ( ! function_exists( 'aiml_a4_run_acceptance' ) ) {
	aiml_a4_seed_nested_fixture();
}
