<?php
/**
 * A.R1 EXPERIMENTAL — ER4 rendering hooks inventory + Candidate B probe on disposable fixture.
 *
 * Usage: wp eval-file research/ar1-elementor-identity/scripts/er4-hooks-and-candidate-b.php
 *
 * @package AIMultilingual\Research\AR1
 */


require __DIR__ . '/lib-ar1.php';

$out_dir = dirname( __DIR__ ) . '/evidence';
global $wp_filter;

$needle = array( 'elementor', 'frontend', 'widget', 'render', 'document', 'dynamic' );
$matches = array();

if ( is_array( $wp_filter ) ) {
	foreach ( $wp_filter as $tag => $callbacks ) {
		$tag_l = strtolower( (string) $tag );
		$hit   = false;
		foreach ( $needle as $n ) {
			if ( str_contains( $tag_l, $n ) ) {
				$hit = true;
				break;
			}
		}
		if ( ! $hit ) {
			continue;
		}
		$count = 0;
		if ( is_object( $callbacks ) && isset( $callbacks->callbacks ) && is_array( $callbacks->callbacks ) ) {
			foreach ( $callbacks->callbacks as $prio => $group ) {
				$count += is_array( $group ) ? count( $group ) : 0;
			}
		}
		$matches[ (string) $tag ] = $count;
	}
}
ksort( $matches );

// Highlight known-safe research candidates (presence only — not production registration).
$of_interest = array(
	'elementor/frontend/builder_content_data',
	'elementor/frontend/the_content',
	'elementor/element/parse_css',
	'elementor/widget/render_content',
	'elementor/frontend/widget/before_render',
	'elementor/frontend/widget/after_render',
	'elementor/element/before_parse_css',
	'elementor/document/before_save',
	'elementor/document/after_save',
	'elementor/editor/after_save',
	'elementor/element/after_add_attributes',
);

$presence = array();
foreach ( $of_interest as $tag ) {
	$presence[ $tag ] = array(
		'registered_listener_count' => $matches[ $tag ] ?? 0,
		'exists_in_wp_filter'        => isset( $matches[ $tag ] ),
	);
}

// Candidate B: write disposable AIML meta key into Elementor settings on a disposable page, resave via API, verify.
$b = array(
	'attempted' => false,
	'note'      => 'Disposable fixture only; cleaned up.',
);

$pid = wp_insert_post(
	array(
		'post_title'   => 'AR1 Candidate B Probe ' . gmdate( 'Ymd-His' ),
		'post_status'  => 'private',
		'post_type'    => 'page',
		'post_content' => '<!-- AR1 disposable -->',
	),
	true
);
if ( ! is_wp_error( $pid ) ) {
	$pid = (int) $pid;
	update_post_meta( $pid, '_ar1_disposable', '1' );
	$elements = array(
		array(
			'id'       => 'bprobe1',
			'elType'   => 'container',
			'settings' => array(),
			'elements' => array(
				array(
					'id'         => 'bprobe2',
					'elType'     => 'widget',
					'widgetType' => 'heading',
					'settings'   => array(
						'title'          => 'Candidate B probe',
						'_aiml_identity' => 'aiml-test-identity-001',
					),
					'elements'   => array(),
				),
			),
		),
	);
	update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
	update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
	update_post_meta( $pid, '_elementor_template_type', 'wp-page' );

	$b['attempted'] = true;
	$b['post_id']   = $pid;

	$loaded = ar1_load_document( $pid );
	$found  = false;
	$walk   = ar1_walk_elements( $loaded['elements'] );
	// Check raw settings for _aiml_identity.
	$raw = $loaded['elements'];
	$check = static function ( array $els ) use ( &$check, &$found ): void {
		foreach ( $els as $el ) {
			if ( isset( $el['settings']['_aiml_identity'] ) && $el['settings']['_aiml_identity'] === 'aiml-test-identity-001' ) {
				$found = true;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$check( $el['elements'] );
			}
		}
	};
	$check( $raw );
	$b['survived_meta_roundtrip'] = $found;

	$api_ok = false;
	if ( class_exists( '\Elementor\Plugin' ) ) {
		try {
			$document = \Elementor\Plugin::$instance->documents->get( $pid );
			if ( $document ) {
				$document->save( array( 'elements' => $raw ) );
				$reloaded = ar1_load_document( $pid );
				$found2   = false;
				$check2   = static function ( array $els ) use ( &$check2, &$found2 ): void {
					foreach ( $els as $el ) {
						if ( isset( $el['settings']['_aiml_identity'] ) ) {
							$found2 = true;
						}
						if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
							$check2( $el['elements'] );
						}
					}
				};
				$check2( $reloaded['elements'] );
				$api_ok = $found2;
				$b['survived_elementor_document_save'] = $found2;
			}
		} catch ( Throwable $e ) {
			$b['api_error'] = $e->getMessage();
		}
	}

	// Frontend render smoke: does heading text still appear (visitor-visible semantics).
	$content = (string) apply_filters( 'the_content', get_post_field( 'post_content', $pid ) );
	// Elementor renders via its own path; try get_builder_content if available.
	if ( class_exists( '\Elementor\Plugin' ) ) {
		try {
			$html = \Elementor\Plugin::$instance->frontend->get_builder_content( $pid, true );
			$b['builder_content_contains_title'] = is_string( $html ) && str_contains( $html, 'Candidate B probe' );
			$b['builder_content_bytes'] = is_string( $html ) ? strlen( $html ) : 0;
		} catch ( Throwable $e ) {
			$b['builder_content_error'] = $e->getMessage();
		}
	}

	wp_trash_post( $pid );
	$b['trashed'] = true;
	$b['governance_note'] = 'Preservation on disposable fixture is necessary but not sufficient for Candidate B GO; copy/import/update compatibility and ADR ownership exception still required.';
}

$out = array(
	'captured_at'           => gmdate( 'c' ),
	'hook_match_count'      => count( $matches ),
	'hooks_of_interest'     => $presence,
	'sample_elementor_hooks'=> array_slice( $matches, 0, 80, true ),
	'candidate_b_probe'     => $b,
	'html_scrape_verdict'   => 'rejected_as_primary_architecture',
);

file_put_contents( $out_dir . '/er4-hooks-candidate-b.json', wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
WP_CLI::success( 'ER4 hooks + Candidate B probe written.' );
echo wp_json_encode(
	array(
		'hooks_of_interest' => $presence,
		'candidate_b'       => array(
			'survived_meta' => $b['survived_meta_roundtrip'] ?? null,
			'survived_api'  => $b['survived_elementor_document_save'] ?? null,
			'render_ok'     => $b['builder_content_contains_title'] ?? null,
		),
	),
	JSON_PRETTY_PRINT
) . "\n";
