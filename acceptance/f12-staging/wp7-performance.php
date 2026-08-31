<?php
/**
 * F12 WP7 performance baseline capture for dev.biopentra.eu
 * Run: wp eval-file wp-content/plugins/universal-multilingual/acceptance/f12-staging/wp7-performance.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Rollout\RolloutPolicyRequest;
use AIMultilingual\Rollout\RolloutPolicyService;
use AIMultilingual\Rollout\Metrics\RolloutHotMetricsStore;
use AIMultilingual\Rollout\Metrics\RolloutMetricsCollector;
use AIMultilingual\Settings;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\BlockFrontendRenderer;
use AIMultilingual\Translation\BlockRenderGate;
use AIMultilingual\Translation\BlockRenderer;
use AIMultilingual\Translation\BlockTranslationLookup;
use AIMultilingual\Translation\BlockTranslationSanitizer;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Rollout\RolloutRenderGateBridge;

$samples = 50;

function median_ms( array $values ): float {
	sort( $values );
	$n = count( $values );
	if ( 0 === $n ) {
		return 0.0;
	}
	$mid = (int) floor( ( $n - 1 ) / 2 );
	return (float) $values[ $mid ];
}

function p95_ms( array $values ): float {
	sort( $values );
	$n = count( $values );
	if ( 0 === $n ) {
		return 0.0;
	}
	$idx = (int) ceil( 0.95 * $n ) - 1;
	return (float) $values[ max( 0, $idx ) ];
}

function measure( callable $fn, int $samples ): array {
	$times = array();
	$mem   = array();
	for ( $i = 0; $i < $samples; $i++ ) {
		$before = memory_get_usage( true );
		$start  = hrtime( true );
		$fn();
		$times[] = ( hrtime( true ) - $start ) / 1_000_000;
		$mem[]   = memory_get_usage( true ) - $before;
	}

	return array(
		'cold_median_ms' => median_ms( array_slice( $times, 0, 1 ) ),
		'warm_median_ms' => median_ms( array_slice( $times, 1 ) ?: $times ),
		'p95_ms'         => p95_ms( $times ),
		'sample_size'    => $samples,
		'memory_delta_b' => median_ms( $mem ),
	);
}

$repo   = new RolloutConfigurationRepository();
$config = $repo->get();
$policy = new RolloutPolicyService();

$policy_ms = measure(
	static function () use ( $policy, $config ): void {
		$policy->evaluate(
			new RolloutPolicyRequest( 6338, 'page', 'sv', true ),
			$config
		);
	},
	$samples
);

$deny_config = $repo->validate_proposed(
	array_merge(
		$config->to_array(),
		array(
			'rollout_render_enabled' => true,
			'rollout_stage'          => 2,
			'allowed_post_ids'       => array( 999999 ),
			'allowed_language_codes' => array( 'sv' ),
		)
	)
)->config;

$deny_ms = measure(
	static function () use ( $policy, $deny_config ): void {
		if ( null === $deny_config ) {
			return;
		}
		$policy->evaluate(
			new RolloutPolicyRequest( 6338, 'page', 'sv', true ),
			$deny_config
		);
	},
	$samples
);

$collector = new RolloutMetricsCollector();
$flush_ms  = measure(
	static function () use ( $collector ): void {
		$collector->flush();
	},
	10
);

echo wp_json_encode(
	array(
		'environment'     => 'dev.biopentra.eu',
		'samples'         => $samples,
		'policy_eval_ms'  => $policy_ms,
		'deny_path_ms'    => $deny_ms,
		'metrics_flush_ms' => $flush_ms,
		'approval_status' => 'pending_po_thresholds',
	),
	JSON_PRETTY_PRINT
);
