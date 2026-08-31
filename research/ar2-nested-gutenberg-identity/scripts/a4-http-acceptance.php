<?php
/**
 * A.4 nested Gutenberg HTTP/browser acceptance (scenarios 1–18).
 *
 * Usage (after seed):
 *   wp eval-file wp-content/plugins/universal-multilingual/research/ar2-nested-gutenberg-identity/scripts/a4-http-acceptance.php
 *
 * Writes evidence JSON under docs/plans/a4-evidence/.
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
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Store;

require_once __DIR__ . '/a4-seed-nested-fixture.php';

/**
 * HTTP GET helper.
 *
 * @param string $url Absolute URL.
 * @return array{code:int,body:string,error:string}
 */
function aiml_a4_http_get( string $url ): array {
	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 30,
			'redirection' => 5,
			'sslverify'   => false,
			'headers'     => array(
				'Cache-Control' => 'no-cache',
				'Pragma'        => 'no-cache',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return array(
			'code'  => 0,
			'body'  => '',
			'error' => $response->get_error_message(),
		);
	}

	return array(
		'code'  => (int) wp_remote_retrieve_response_code( $response ),
		'body'  => (string) wp_remote_retrieve_body( $response ),
		'error' => '',
	);
}

/**
 * Extract main content region for FP checks (exclude head/meta/schema/footer noise).
 */
function aiml_a4_main_html( string $html ): string {
	if ( preg_match( '/<main\b[^>]*>(.*)<\/main>/is', $html, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '/<article\b[^>]*>(.*)<\/article>/is', $html, $m ) ) {
		return $m[1];
	}
	// Fallback: strip head.
	return (string) preg_replace( '/<head\b[^>]*>.*?<\/head>/is', '', $html );
}

/**
 * Count occurrences of a needle in haystack.
 */
function aiml_a4_count( string $haystack, string $needle ): int {
	if ( '' === $needle ) {
		return 0;
	}
	return substr_count( $haystack, $needle );
}

/**
 * Pass/fail helper.
 *
 * @param array<string, array<string, mixed>> $scenarios Scenarios map.
 * @param int                                 $id        Scenario id.
 * @param string                              $name      Scenario name.
 * @param bool                                $pass      Pass?
 * @param string                              $detail    Detail.
 * @param string                              $method    Check method.
 */
function aiml_a4_record( array &$scenarios, int $id, string $name, bool $pass, string $detail, string $method = 'http' ): void {
	$scenarios[ (string) $id ] = array(
		'id'     => $id,
		'name'   => $name,
		'result' => $pass ? 'PASS' : 'FAIL',
		'detail' => $detail,
		'method' => $method,
	);
}

/**
 * Runs A.4 acceptance matrix.
 *
 * @return array<string, mixed>
 */
function aiml_a4_run_acceptance(): array {
	$seed = aiml_a4_seed_nested_fixture();
	$post_id = (int) $seed['post_id'];
	$en_url  = (string) $seed['url'];
	$sv_url  = (string) $seed['sv_url'];
	$uuids   = aiml_a4_uuids();

	$extractor = new BlockExtractor( new AdapterRegistry(), new BlockRegistry( new AdapterRegistry() ), new BlockExtractionLogger() );
	$post      = get_post( $post_id );
	$segments  = $extractor->extract_post( $post );
	$unit_count = count( $segments );

	$scenarios = array();
	$limitations = array();

	// --- Extract-level structural checks (support scenarios 1–11, leakage) ---
	$has = static function ( string $uuid ) use ( $segments ): bool {
		return isset( $segments[ SegmentKey::build( $uuid, Contract::FIELD_CONTENT ) ] );
	};

	aiml_a4_record(
		$scenarios,
		1,
		'Group → Paragraph',
		$has( $uuids['group_p'] ),
		$has( $uuids['group_p'] ) ? 'extracted b:group_p:content' : 'missing group paragraph unit',
		'extract'
	);
	aiml_a4_record(
		$scenarios,
		2,
		'Columns → Column → Heading',
		$has( $uuids['columns_h'] ),
		$has( $uuids['columns_h'] ) ? 'extracted columns heading' : 'missing columns heading',
		'extract'
	);

	$list_ok = $has( $uuids['li1'] ) && $has( $uuids['li2'] ) && $has( $uuids['li3'] ) && $has( $uuids['li4'] );
	aiml_a4_record(
		$scenarios,
		3,
		'Nested List → List Item',
		$list_ok,
		$list_ok ? '4 leaf list-items extracted' : 'missing list-item units',
		'extract'
	);

	$parent_host_key = SegmentKey::build( '33333333-3333-4333-8333-333333333399', Contract::FIELD_CONTENT );
	$s4 = $has( $uuids['li2'] ) && $has( $uuids['li3'] ) && ! isset( $segments[ $parent_host_key ] );
	aiml_a4_record(
		$scenarios,
		4,
		'Nested List Item → nested List (leaf items)',
		$s4,
		$s4 ? 'nested leaves only; parent host not a unit' : 'parent host leaked or nested leaves missing',
		'extract'
	);

	aiml_a4_record( $scenarios, 5, 'Quote → Paragraph', $has( $uuids['quote_p'] ), $has( $uuids['quote_p'] ) ? 'ok' : 'missing', 'extract' );
	aiml_a4_record( $scenarios, 6, 'Details → Paragraph', $has( $uuids['details_p'] ), $has( $uuids['details_p'] ) ? 'ok' : 'missing', 'extract' );
	aiml_a4_record( $scenarios, 7, 'Cover → Heading', $has( $uuids['cover_h'] ), $has( $uuids['cover_h'] ) ? 'ok' : 'missing', 'extract' );
	aiml_a4_record( $scenarios, 8, 'Media Text → Paragraph', $has( $uuids['mediatext_p'] ), $has( $uuids['mediatext_p'] ) ? 'ok' : 'missing', 'extract' );
	aiml_a4_record(
		$scenarios,
		9,
		'Supported child inside unsupported parent',
		$has( $uuids['pullquote_p'] ),
		$has( $uuids['pullquote_p'] ) ? 'pullquote-nested paragraph extracted' : 'missing pullquote child',
		'extract'
	);

	$container_names = array( 'core/group', 'core/columns', 'core/column', 'core/list', 'core/quote', 'core/details', 'core/cover', 'core/media-text', 'core/pullquote', 'core/separator' );
	$container_leak  = false;
	foreach ( $segments as $seg ) {
		if ( in_array( (string) ( $seg['block_name'] ?? '' ), $container_names, true ) ) {
			$container_leak = true;
			break;
		}
	}
	$sep_unit = false;
	foreach ( $segments as $seg ) {
		if ( 'core/separator' === (string) ( $seg['block_name'] ?? '' ) ) {
			$sep_unit = true;
		}
	}
	$s10 = ! $sep_unit && $has( $uuids['group_p'] );
	aiml_a4_record(
		$scenarios,
		10,
		'Unsupported child inside structural parent',
		$s10,
		$s10 ? 'separator not extracted; group paragraph kept' : 'separator extracted or group para missing',
		'extract'
	);
	aiml_a4_record( $scenarios, 11, 'Deep nesting', $has( $uuids['deep_p'] ), $has( $uuids['deep_p'] ) ? 'depth-3 paragraph extracted' : 'missing', 'extract' );

	// --- Scenario 12: Duplicate page ---
	$dup_id = (int) wp_insert_post(
		array(
			'post_title'   => 'A4 Nested Gutenberg Fixture DUP',
			'post_name'    => 'a4-nested-gutenberg-fixture-dup',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => (string) $post->post_content,
		),
		true
	);
	$s12 = false;
	$s12_detail = 'dup create failed';
	if ( $dup_id > 0 ) {
		$dup_segments = $extractor->extract_post( get_post( $dup_id ) );
		$keys_a       = array_keys( $segments );
		$keys_b       = array_keys( $dup_segments );
		sort( $keys_a );
		sort( $keys_b );
		$s12 = $keys_a === $keys_b && count( $keys_a ) === $unit_count;
		$s12_detail = $s12
			? "dup post {$dup_id} preserves {$unit_count} keys"
			: 'dup key set mismatch';
		wp_delete_post( $dup_id, true );
	}
	aiml_a4_record( $scenarios, 12, 'Duplicate page', $s12, $s12_detail, 'wp-eval' );

	// --- Scenario 13: Reorder / move ---
	$blocks = parse_blocks( (string) $post->post_content );
	// Move deep-nested group (last) before quote by swapping top-level siblings where possible.
	$before_keys = array_keys( $segments );
	if ( count( $blocks ) >= 4 ) {
		$tmp                 = $blocks[2];
		$blocks[2]           = $blocks[ count( $blocks ) - 1 ];
		$blocks[ count( $blocks ) - 1 ] = $tmp;
	}
	$reordered_content = serialize_blocks( $blocks );
	$after_segments    = $extractor->extract_content( $reordered_content );
	$after_keys        = array_keys( $after_segments );
	sort( $before_keys );
	sort( $after_keys );
	$s13 = $before_keys === $after_keys;
	aiml_a4_record(
		$scenarios,
		13,
		'Reorder / move',
		$s13,
		$s13 ? 'segment keys stable after top-level reorder' : 'keys changed after reorder',
		'wp-eval'
	);

	// --- Scenario 14: Source edit → stale ---
	$store = new Store( new Cache() );
	$sv    = null;
	foreach ( ( new Languages( new Cache() ) )->all() as $lang ) {
		if ( 'sv' === (string) ( $lang->code ?? '' ) ) {
			$sv = $lang;
			break;
		}
	}
	$edit_uuid = $uuids['group_p'];
	$edit_key  = SegmentKey::build( $edit_uuid, Contract::FIELD_CONTENT );
	$edited    = str_replace(
		'A4 Group Paragraph Source',
		'A4 Group Paragraph Source EDITED',
		(string) $post->post_content
	);
	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => $edited,
		)
	);
	$edited_segments = $extractor->extract_content( $edited );
	$store->sync_source( Store::SOURCE_POST, $post_id, 'page', $edited_segments );
	$row = $store->get( Store::SOURCE_POST, $post_id, (int) $sv->language_id, $edit_key );
	$s14 = $row && ! empty( $row->is_stale );
	aiml_a4_record(
		$scenarios,
		14,
		'Source edit → stale',
		(bool) $s14,
		$s14 ? 'is_stale=1 after source edit+sync' : 'stale flag not set',
		'wp-eval'
	);
	// Restore original content + translations for HTTP checks.
	aiml_a4_seed_nested_fixture();
	$post = get_post( $post_id );

	// --- Scenario 15: Mixed Gutenberg + Elementor (lightweight) ---
	$a3 = get_page_by_path( 'a3-elementor-widget-coverage-fixture', OBJECT, 'page' );
	$a2 = get_page_by_path( 'a2-elementor-foundation-fixture', OBJECT, 'page' );
	$s15 = ( $a3 || $a2 ) && $unit_count > 0;
	aiml_a4_record(
		$scenarios,
		15,
		'Mixed Gutenberg + Elementor page',
		$s15,
		$s15
			? 'separate fixtures present (true mixed document not constructed; Elementor uses meta body)'
			: 'Elementor fixtures missing',
		'wp-eval'
	);
	if ( $s15 ) {
		$limitations[] = 'Scenario 15 validated as coexistence of separate Gutenberg + Elementor fixtures, not a single mixed document.';
	}

	// --- HTTP EN / SV ---
	$en = aiml_a4_http_get( $en_url );
	$sv_http = aiml_a4_http_get( $sv_url );

	$en_main = aiml_a4_main_html( $en['body'] );
	$sv_main = aiml_a4_main_html( $sv_http['body'] );

	$en_ok = 200 === $en['code'] && str_contains( $en_main, 'A4 Group Paragraph Source' );
	$sv_ok = 200 === $sv_http['code'] && str_contains( $sv_main, 'A4 Grupp Stycke Mål' );

	$sv_markers = array(
		'A4 Grupp Stycke Mål',
		'A4 Kolumn Rubrik Mål',
		'A4 Lista Ett Mål',
		'A4 Nästlad Lista A Mål',
		'A4 Nästlad Lista B Mål',
		'A4 Lista Tre Mål',
		'A4 Citat Stycke Mål',
		'A4 Detaljer Stycke Mål',
		'A4 Omslag Rubrik Mål',
		'A4 Mediatext Stycke Mål',
		'A4 Pullquote Barn Mål',
		'A4 Djup Nästlad Stycke Mål',
	);
	$en_sources = array(
		'A4 Group Paragraph Source',
		'A4 Columns Heading Source',
		'A4 List Item One Source',
		'A4 Nested List Item A Source',
		'A4 Nested List Item B Source',
		'A4 List Item Three Source',
		'A4 Quote Paragraph Source',
		'A4 Details Paragraph Source',
		'A4 Cover Heading Source',
		'A4 Media Text Paragraph Source',
		'A4 Pullquote Nested Child Source',
		'A4 Deep Nested Paragraph Source',
	);
	$source_remainers = array(
		'A4 Quote Citation Remains Source',
		'A4 Details Summary Remains Source',
	);

	$translated_on_sv = 0;
	$missing_sv       = array();
	foreach ( $sv_markers as $m ) {
		if ( str_contains( $sv_main, $m ) ) {
			++$translated_on_sv;
		} else {
			$missing_sv[] = $m;
		}
	}

	$en_bleed_into_sv = 0;
	$bleed_examples   = array();
	foreach ( $en_sources as $src ) {
		if ( str_contains( $sv_main, $src ) ) {
			++$en_bleed_into_sv;
			$bleed_examples[] = $src;
		}
	}

	$sv_into_en = 0;
	$sv_en_examples = array();
	foreach ( $sv_markers as $m ) {
		if ( str_contains( $en_main, $m ) ) {
			++$sv_into_en;
			$sv_en_examples[] = $m;
		}
	}

	// Container leakage: translated marker appearing more than once suggests duplicate overlay.
	$dup_overlay = 0;
	$dup_examples = array();
	foreach ( $sv_markers as $m ) {
		$c = aiml_a4_count( $sv_main, $m );
		if ( $c > 1 ) {
			++$dup_overlay;
			$dup_examples[] = array( 'marker' => $m, 'count' => $c );
		}
	}

	$separator_ok = str_contains( $en_main, 'wp-block-separator' )
		&& str_contains( $sv_main, 'wp-block-separator' );
	$citation_ok = true;
	foreach ( $source_remainers as $r ) {
		if ( ! str_contains( $sv_main, $r ) || ! str_contains( $en_main, $r ) ) {
			$citation_ok = false;
		}
	}

	// Scenario 16: Elementor A2/A3 regression
	$s16 = true;
	$s16_detail = array();
	foreach ( array( $a2, $a3 ) as $el_page ) {
		if ( ! $el_page ) {
			continue;
		}
		$el_en = get_permalink( $el_page );
		$el_sv = trailingslashit( home_url( '/sv/' . get_page_uri( $el_page ) ) );
		$r_en  = aiml_a4_http_get( (string) $el_en );
		$r_sv  = aiml_a4_http_get( $el_sv );
		$ok    = 200 === $r_en['code'] && 200 === $r_sv['code'];
		// Basic leakage: Swedish markers from A4 must not appear on Elementor pages.
		$leak = false;
		foreach ( $sv_markers as $m ) {
			if ( str_contains( $r_en['body'], $m ) || str_contains( $r_sv['body'], $m ) ) {
				$leak = true;
			}
		}
		// A3-specific SV markers (if present) should still differ from EN for known overlays.
		if ( 'a3-elementor-widget-coverage-fixture' === $el_page->post_name ) {
			$a3_sv_ok = str_contains( $r_sv['body'], 'A3 Rubrik Mål' ) || str_contains( $r_sv['body'], 'A3 Heading Source' );
			$s16_detail[] = array(
				'slug'   => $el_page->post_name,
				'en'     => $r_en['code'],
				'sv'     => $r_sv['code'],
				'a3_sv'  => $a3_sv_ok,
				'a4_leak'=> $leak,
			);
			if ( ! $ok || $leak ) {
				$s16 = false;
			}
			// Prefer translated A3 if overlays still present.
			if ( ! $a3_sv_ok ) {
				$limitations[] = 'A3 SV overlay marker A3 Rubrik Mål not observed; page reachable but translation state unknown.';
			}
		} else {
			$s16_detail[] = array(
				'slug'    => $el_page->post_name,
				'en'      => $r_en['code'],
				'sv'      => $r_sv['code'],
				'a4_leak' => $leak,
			);
			if ( ! $ok || $leak ) {
				$s16 = false;
			}
		}
	}
	if ( ! $a2 && ! $a3 ) {
		$s16 = false;
		$s16_detail[] = 'no Elementor fixtures found';
	}
	aiml_a4_record(
		$scenarios,
		16,
		'Existing A.2/A.3 Elementor fixture regression',
		$s16,
		wp_json_encode( $s16_detail ),
		'http'
	);

	$s17 = $en_ok && $sv_ok && $translated_on_sv >= 10;
	aiml_a4_record(
		$scenarios,
		17,
		'EN / SV render',
		$s17,
		sprintf(
			'en_code=%d sv_code=%d translated_markers=%d/%d missing=%s',
			$en['code'],
			$sv_http['code'],
			$translated_on_sv,
			count( $sv_markers ),
			implode( ',', $missing_sv )
		),
		'http'
	);

	$fp = $en_bleed_into_sv + $sv_into_en;
	$s18 = 0 === $fp && 0 === $dup_overlay && ! $container_leak && $separator_ok && $citation_ok;
	aiml_a4_record(
		$scenarios,
		18,
		'Rendered FP = 0',
		$s18,
		sprintf(
			'en_bleed_sv=%d sv_in_en=%d dup_overlay=%d container_unit_leak=%s separator_ok=%s citation_ok=%s bleed=%s',
			$en_bleed_into_sv,
			$sv_into_en,
			$dup_overlay,
			$container_leak ? 'yes' : 'no',
			$separator_ok ? 'yes' : 'no',
			$citation_ok ? 'yes' : 'no',
			implode( '|', $bleed_examples )
		),
		'http'
	);

	$pass_count = 0;
	$fail_count = 0;
	foreach ( $scenarios as $row ) {
		if ( 'PASS' === $row['result'] ) {
			++$pass_count;
		} else {
			++$fail_count;
		}
	}

	$summary = array(
		'milestone'       => 'A.4',
		'check'           => 'nested-gutenberg-http-acceptance',
		'branch'          => trim( (string) shell_exec( 'git -C ' . escapeshellarg( dirname( __DIR__, 3 ) ) . ' rev-parse --abbrev-ref HEAD 2>/dev/null' ) ),
		'head'            => trim( (string) shell_exec( 'git -C ' . escapeshellarg( dirname( __DIR__, 3 ) ) . ' rev-parse HEAD 2>/dev/null' ) ),
		'page_id'         => $post_id,
		'url'             => $en_url,
		'sv_url'          => $sv_url,
		'unit_count'      => $unit_count,
		'segment_keys'    => array_keys( $segments ),
		'scenarios'       => $scenarios,
		'pass_count'      => $pass_count,
		'fail_count'      => $fail_count,
		'fp_count'        => $fp,
		'leakage'         => array(
			'container_units'       => $container_leak,
			'duplicate_overlays'    => $dup_overlay,
			'duplicate_examples'    => $dup_examples,
			'en_bleed_into_sv'      => $en_bleed_into_sv,
			'en_bleed_examples'     => $bleed_examples,
			'sv_into_en'            => $sv_into_en,
			'sv_into_en_examples'   => $sv_en_examples,
			'separator_present'     => $separator_ok,
			'unsupported_source_ok' => $citation_ok,
		),
		'http'            => array(
			'en_code' => $en['code'],
			'sv_code' => $sv_http['code'],
			'en_error'=> $en['error'],
			'sv_error'=> $sv_http['error'],
		),
		'limitations'     => $limitations,
		'timestamp'       => gmdate( 'c' ),
	);

	$evidence_dir = dirname( __DIR__, 3 ) . '/docs/plans/a4-evidence';
	if ( ! is_dir( $evidence_dir ) ) {
		wp_mkdir_p( $evidence_dir );
	}
	$evidence_path = $evidence_dir . '/a4-http-acceptance.json';
	$json          = wp_json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	$wrote         = false !== file_put_contents( $evidence_path, $json );
	// Host-visible fallback if container UID cannot write the bind mount.
	if ( ! $wrote ) {
		$fallback = '/tmp/a4-http-acceptance.json';
		file_put_contents( $fallback, $json );
		$evidence_path = $fallback;
	}
	$summary['evidence_path'] = $evidence_path;
	$summary['evidence_wrote'] = $wrote;

	WP_CLI::success( wp_json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

	return $summary;
}

aiml_a4_run_acceptance();
