<?php
/**
 * F13 performance baseline capture (policy + GA path) — staging evidence only.
 *
 * Run: wp --user=1 eval-file wp-content/plugins/ai-multilingual/acceptance/f13-staging/f13-performance.php
 *
 * @package AIMultilingual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

use AIMultilingual\Rollout\GeneralAvailabilityCohortProvider;
use AIMultilingual\Rollout\RolloutConfiguration;
use AIMultilingual\Rollout\RolloutPolicyRequest;
use AIMultilingual\Rollout\RolloutPolicyService;

/**
 * Median of float samples.
 *
 * @param array<int, float> $samples Samples.
 */
function aiml_f13_median_ms( array $samples ): float {
	sort( $samples );
	$n = count( $samples );
	if ( 0 === $n ) {
		return 0.0;
	}
	$mid = (int) floor( $n / 2 );
	return ( 0 === $n % 2 ) ? ( ( $samples[ $mid - 1 ] + $samples[ $mid ] ) / 2.0 ) : $samples[ $mid ];
}

/**
 * Measures callable N times.
 *
 * @param callable $fn Callable.
 * @param int      $n  Iterations.
 * @return array{median_ms: float, p95_ms: float, samples: int}
 */
function aiml_f13_measure( callable $fn, int $n = 50 ): array {
	$samples = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$t0 = hrtime( true );
		$fn();
		$samples[] = ( hrtime( true ) - $t0 ) / 1e6;
	}
	sort( $samples );
	$idx = (int) max( 0, (int) ceil( 0.95 * count( $samples ) ) - 1 );
	return array(
		'median_ms' => aiml_f13_median_ms( $samples ),
		'p95_ms'    => $samples[ $idx ],
		'samples'   => count( $samples ),
	);
}

$policy = new RolloutPolicyService( new GeneralAvailabilityCohortProvider() );

$limited = RolloutConfiguration::defaults()->with(
	array(
		'rollout_stage'           => 2,
		'rollout_render_enabled'  => true,
		'allowed_post_ids'        => array( 6321 ),
		'allowed_language_codes'  => array( 'sv' ),
		'general_rollout_enabled' => false,
	)
);

$ga = RolloutConfiguration::defaults()->with(
	array(
		'rollout_stage'           => 6,
		'rollout_render_enabled'  => true,
		'allowed_post_ids'        => array(),
		'allowed_language_codes'  => array( 'sv' ),
		'general_rollout_enabled' => true,
	)
);

$request = new RolloutPolicyRequest( 9999, 'page', 'sv' );

$result = array(
	'timestamp'           => gmdate( 'c' ),
	'limited_allowlist'   => aiml_f13_measure(
		static function () use ( $policy, $limited ): void {
			$policy->evaluate( new RolloutPolicyRequest( 6321, 'page', 'sv' ), $limited );
		}
	),
	'ga_non_allowlisted'  => aiml_f13_measure(
		static function () use ( $policy, $ga, $request ): void {
			$policy->evaluate( $request, $ga );
		}
	),
	'ga_deny_language'    => aiml_f13_measure(
		static function () use ( $policy, $ga ): void {
			$policy->evaluate( new RolloutPolicyRequest( 9999, 'page', 'de' ), $ga );
		}
	),
	'note'                => 'No invented SLOs — evidence only.',
);

echo wp_json_encode( $result, JSON_PRETTY_PRINT ) . "\n";
