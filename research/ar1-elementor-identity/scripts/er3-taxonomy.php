<?php
/**
 * A.R1 EXPERIMENTAL — ER3 field taxonomy from fixtures + live inventory.
 *
 * Usage: wp eval-file research/ar1-elementor-identity/scripts/er3-taxonomy.php
 *
 * @package AIMultilingual\Research\AR1
 */


require __DIR__ . '/lib-ar1.php';

$out_dir = dirname( __DIR__ ) . '/evidence';
$fixture_dir = dirname( __DIR__ ) . '/fixtures';

$translatable_control_hints = array(
	'title', 'editor', 'text', 'description', 'caption', 'alt', 'html',
	'tab_title', 'tab_content', 'item_title', 'item_description',
	'shortcode', 'testimonial_content', 'testimonial_name',
);

$categories = array();
$files = glob( $fixture_dir . '/fixture-post-*-sanitized.json' ) ?: array();

foreach ( $files as $file ) {
	$payload = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $payload ) || ! isset( $payload['elements'] ) ) {
		continue;
	}
	foreach ( ar1_walk_elements( $payload['elements'] ) as $row ) {
		$wt = $row['widgetType'] !== '' ? $row['widgetType'] : ( $row['elType'] ?: 'unknown' );
		if ( ! isset( $categories[ $wt ] ) ) {
			$categories[ $wt ] = array(
				'widget_or_el'     => $wt,
				'samples'          => 0,
				'setting_key_union'=> array(),
				'responsive_keys'  => array(),
				'has_dynamic'      => false,
				'has_repeater'     => false,
				'template_refs'    => 0,
			);
		}
		$categories[ $wt ]['samples']++;
		foreach ( $row['setting_keys'] as $k ) {
			$categories[ $wt ]['setting_key_union'][ $k ] = true;
		}
		foreach ( $row['responsive'] as $k ) {
			$categories[ $wt ]['responsive_keys'][ $k ] = true;
		}
		if ( $row['has_dynamic'] ) {
			$categories[ $wt ]['has_dynamic'] = true;
		}
		if ( $row['repeater_keys'] !== array() ) {
			$categories[ $wt ]['has_repeater'] = true;
		}
		if ( $row['template_ref'] ) {
			$categories[ $wt ]['template_refs']++;
		}
	}
}

$taxonomy = array();
foreach ( $categories as $wt => $info ) {
	$keys = array_keys( $info['setting_key_union'] );
	sort( $keys );
	$likely = array_values( array_intersect( $keys, $translatable_control_hints ) );
	// Also catch title_mobile etc.
	foreach ( $keys as $k ) {
		foreach ( $translatable_control_hints as $hint ) {
			if ( str_starts_with( $k, $hint ) && ! in_array( $k, $likely, true ) ) {
				$likely[] = $k;
			}
		}
	}

	$classification = 'directly supportable';
	$reason         = null;
	if ( $info['has_dynamic'] ) {
		$classification = 'unsupported';
		$reason         = 'dynamic runtime value';
	} elseif ( in_array( $wt, array( 'html', 'shortcode', 'template', 'global' ), true ) ) {
		$classification = 'unsupported';
		$reason         = $wt === 'html' || $wt === 'shortcode'
			? 'unsupported Elementor behavior'
			: 'ownership ambiguity';
	} elseif ( str_starts_with( $wt, 'woocommerce' ) || str_contains( $wt, 'wc-' ) ) {
		$classification = 'unsupported';
		$reason         = 'third-party opaque persistence';
	} elseif ( $info['has_repeater'] ) {
		$classification = 'supportable through adapter';
		$reason         = null;
	} elseif ( $likely === array() && $wt !== 'container' && $wt !== 'section' && $wt !== 'column' ) {
		$classification = 'supportable through adapter';
	}

	$taxonomy[ $wt ] = array(
		'samples'                 => $info['samples'],
		'likely_text_controls'    => $likely,
		'responsive_keys'         => array_keys( $info['responsive_keys'] ),
		'has_dynamic'             => $info['has_dynamic'],
		'has_repeater'            => $info['has_repeater'],
		'template_refs_observed'  => $info['template_refs'],
		'classification'          => $classification,
		'unsupported_reason'      => $reason,
		'confidence'              => 'supported by evidence',
		'notes'                   => 'Preliminary from sanitized fixtures; ER7 may refine.',
	);
}

ksort( $taxonomy );
file_put_contents( $out_dir . '/er3-taxonomy.json', wp_json_encode( $taxonomy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

$deny = array();
$adapter = array();
$direct = array();
foreach ( $taxonomy as $wt => $row ) {
	if ( $row['classification'] === 'unsupported' ) {
		$deny[ $wt ] = $row['unsupported_reason'];
	} elseif ( $row['classification'] === 'supportable through adapter' ) {
		$adapter[] = $wt;
	} else {
		$direct[] = $wt;
	}
}

file_put_contents(
	$out_dir . '/er3-deny-list.json',
	wp_json_encode(
		array(
			'deny_list' => $deny,
			'adapter_required' => $adapter,
			'directly_supportable_candidates' => $direct,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . "\n"
);

WP_CLI::success( 'ER3 taxonomy written.' );
echo wp_json_encode(
	array(
		'widgets_classified' => count( $taxonomy ),
		'direct' => count( $direct ),
		'adapter' => count( $adapter ),
		'deny' => count( $deny ),
	),
	JSON_PRETTY_PRINT
) . "\n";
