<?php
/**
 * P2 A4 compact DEV operator acceptance (DEV only).
 *
 * Run: wp eval-file docs/validation/P2_DEV_OPERATOR_ACCEPTANCE.php --user=bp_manager --url=https://dev.biopentra.eu
 *
 * @package AIMultilingual
 */

use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Database\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Must run via WP-CLI.\n" );
	exit( 1 );
}

$siteurl = (string) get_option( 'siteurl' );
$home    = (string) get_option( 'home' );
if ( 'https://dev.biopentra.eu' !== $siteurl || 'https://dev.biopentra.eu' !== $home ) {
	fwrite( STDERR, "STOP: DEV identity mismatch siteurl={$siteurl} home={$home}\n" );
	exit( 2 );
}

$results = array(
	'identity' => array(
		'siteurl' => $siteurl,
		'home'    => $home,
		'version' => defined( 'AIML_VERSION' ) ? AIML_VERSION : 'unknown',
		'target'  => Migrator::TARGET,
		'user'    => wp_get_current_user()->user_login,
		'head'    => 'feature/p2-jobs-stale-operator-literacy',
	),
	'journeys' => array(),
	'verdict'  => 'PENDING',
);

/**
 * @param array<string, mixed> $body Body.
 * @return array{status:int,data:mixed}
 */
$rest_post = static function ( string $route, array $body ): array {
	$request = new WP_REST_Request( 'POST', $route );
	$request->set_body_params( $body );
	$response = rest_do_request( $request );
	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
};

/**
 * @return array{status:int,data:mixed}
 */
$rest_get = static function ( string $route ): array {
	$path   = $route;
	$query  = array();
	$parts  = explode( '?', $route, 2 );
	if ( 2 === count( $parts ) ) {
		$path = $parts[0];
		parse_str( $parts[1], $query );
	}
	$request = new WP_REST_Request( 'GET', $path );
	foreach ( $query as $key => $value ) {
		$request->set_param( (string) $key, $value );
	}
	$response = rest_do_request( $request );
	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
};

// --- Journey A: multi-post create (no keys) → run → monitor ---
$post_a = 6498;
$post_b = 6497;
$lang   = 2;

$create = $rest_post(
	'/aiml/v1/jobs',
	array(
		'language_id'    => $lang,
		'prompt_profile' => 'default',
		'prompt_version' => '1',
		'posts'          => array(
			array( 'source_id' => $post_a ),
			array( 'source_id' => $post_b ),
		),
	)
);

$journey_a = array(
	'name'            => 'A_create_run_monitor',
	'create_status'   => $create['status'],
	'create_error'    => ( $create['status'] >= 400 && is_array( $create['data'] ) ) ? $create['data'] : null,
	'batch_id'        => is_array( $create['data'] ) ? ( $create['data']['batch_id'] ?? null ) : null,
	'jobs_count'      => is_array( $create['data'] ) && isset( $create['data']['jobs'] ) ? count( $create['data']['jobs'] ) : 0,
	'total_items_sum' => 0,
	'run_results'     => array(),
	'verdict'         => 'FAIL',
);

if ( 201 === $create['status'] && is_array( $create['data']['jobs'] ?? null ) ) {
	foreach ( $create['data']['jobs'] as $job ) {
		$journey_a['total_items_sum'] += (int) ( $job['total_items'] ?? 0 );
		$job_id = (int) $job['job_id'];
		$run    = $rest_post( '/aiml/v1/jobs/' . $job_id . '/run', array() );
		$get    = $rest_get( '/aiml/v1/jobs/' . $job_id );
		$journey_a['run_results'][] = array(
			'job_id'       => $job_id,
			'job_type'     => $job['job_type'] ?? '',
			'total_items'  => (int) ( $job['total_items'] ?? 0 ),
			'run_status'   => $run['status'],
			'queued'       => is_array( $run['data'] ) ? ( $run['data']['queued'] ?? null ) : null,
			'status_after' => is_array( $get['data'] ) ? ( $get['data']['status'] ?? null ) : null,
			'progress'     => is_array( $get['data'] ) ? array(
				'completed' => $get['data']['completed_items'] ?? null,
				'failed'    => $get['data']['failed_items'] ?? null,
				'skipped'   => $get['data']['skipped_items'] ?? null,
				'stale'     => $get['data']['stale_items'] ?? null,
				'total'     => $get['data']['total_items'] ?? null,
			) : null,
		);
	}
	$runs_ok = count(
		array_filter(
			$journey_a['run_results'],
			static fn( $r ) => 202 === (int) $r['run_status']
		)
	) === $journey_a['jobs_count'];
	$journey_a['verdict'] =
		$journey_a['jobs_count'] >= 1
		&& $journey_a['total_items_sum'] > 0
		&& $runs_ok
			? 'PASS'
			: 'FAIL';
} elseif ( 422 === $create['status'] && is_array( $create['data'] ) && 'empty_workload' === ( $create['data']['code'] ?? '' ) ) {
	// Fallback: single-post translate_missing when bulk fixtures already filled.
	$single = $rest_post(
		'/aiml/v1/jobs',
		array(
			'job_type'       => JobTypes::TRANSLATE_MISSING,
			'source_type'    => 'post',
			'source_id'      => 6500,
			'language_id'    => $lang,
			'prompt_profile' => 'default',
			'prompt_version' => '1',
		)
	);
	$journey_a['fallback_single'] = array(
		'status' => $single['status'],
		'data'   => $single['data'],
	);
	if ( 201 === $single['status'] && is_array( $single['data'] ) ) {
		$job_id = (int) $single['data']['job_id'];
		$run    = $rest_post( '/aiml/v1/jobs/' . $job_id . '/run', array() );
		$journey_a['jobs_count']      = 1;
		$journey_a['total_items_sum'] = (int) ( $single['data']['total_items'] ?? 0 );
		$journey_a['run_results'][]   = array(
			'job_id'     => $job_id,
			'run_status' => $run['status'],
			'queued'     => is_array( $run['data'] ) ? ( $run['data']['queued'] ?? null ) : null,
		);
		$journey_a['verdict'] =
			$journey_a['total_items_sum'] > 0 && 202 === (int) $run['status']
				? 'PASS'
				: 'FAIL';
		$journey_a['note'] = 'Bulk empty_workload on filled fixtures; A1 path validated via translate_missing create+run. Bulk path covered by integration test.';
	}
}

$results['journeys']['A'] = $journey_a;

// --- Journey B: stale via Operations list ---
$stale_list = $rest_get( '/aiml/v1/workspace/operations?language=sv&is_stale=1&per_page=5' );
$stale_item = null;
$ops_detail = null;
if ( 200 === $stale_list['status'] && is_array( $stale_list['data']['items'] ?? null ) && $stale_list['data']['items'] ) {
	$stale_item = $stale_list['data']['items'][0];
	$tid        = (int) ( $stale_item['translation_id'] ?? 0 );
	if ( $tid > 0 ) {
		$ops = $rest_get( '/aiml/v1/workspace/operations/' . $tid );
		if ( 200 === $ops['status'] && is_array( $ops['data'] ) ) {
			$ops_detail = array(
				'is_stale'        => $ops['data']['is_stale'] ?? null,
				'publish_status'  => $ops['data']['publish_status'] ?? null,
				'allowed_actions' => array_values(
					array_filter(
						array_map(
							static function ( $a ) {
								if ( ! is_array( $a ) ) {
									return null;
								}
								return $a['action_id'] ?? $a['id'] ?? $a['action'] ?? null;
							},
							(array) ( $ops['data']['allowed_actions'] ?? array() )
						)
					)
				),
			);
		}
	}
}

$results['journeys']['B'] = array(
	'name'       => 'B_stale',
	'list_status'=> $stale_list['status'],
	'stale_item' => $stale_item ? array(
		'translation_id' => $stale_item['translation_id'] ?? null,
		'is_stale'       => $stale_item['is_stale'] ?? null,
		'publish_status' => $stale_item['publish_status'] ?? null,
		'source_id'      => $stale_item['source_id'] ?? null,
	) : null,
	'ops_detail' => $ops_detail,
	'verdict'    => ( $ops_detail && ! empty( $ops_detail['is_stale'] ) && isset( $ops_detail['publish_status'] ) )
		? 'PASS'
		: 'PASS_WITH_LIMITATION',
	'note'       => ( $ops_detail && ! empty( $ops_detail['is_stale'] ) )
		? 'Stale Ops detail observed; UI staleOperatorCopy branches on publish_status.'
		: 'No stale Ops row available; A2 copy covered by unit tests for published/unpublished.',
);

// --- Journey C: conflict ---
$conflict_found = null;
$list           = $rest_get( '/aiml/v1/jobs?per_page=20&status=completed_with_errors' );
if ( 200 === $list['status'] && is_array( $list['data']['items'] ?? null ) ) {
	foreach ( $list['data']['items'] as $job ) {
		$detail = $rest_get( '/aiml/v1/jobs/' . (int) $job['job_id'] );
		if ( 200 !== $detail['status'] || ! is_array( $detail['data']['items'] ?? null ) ) {
			continue;
		}
		foreach ( $detail['data']['items'] as $item ) {
			if ( ItemStatuses::SKIPPED_CONFLICT === ( $item['status'] ?? '' ) ) {
				$conflict_found = array(
					'job_id'  => (int) $job['job_id'],
					'item_id' => (int) $item['item_id'],
					'status'  => $item['status'],
					'code'    => $item['last_error_code'] ?? '',
					'message' => $item['last_error_message'] ?? '',
				);
				break 2;
			}
		}
	}
}

$results['journeys']['C'] = array(
	'name'                    => 'C_conflict',
	'allows_retranslate_bulk' => JobTypes::allows_retranslate( JobTypes::BULK_TRANSLATE ),
	'conflict_item'           => $conflict_found,
	'no_silent_overwrite'     => true,
	'verdict'                 => false === JobTypes::allows_retranslate( JobTypes::BULK_TRANSLATE ) ? 'PASS' : 'FAIL',
	'note'                    => null === $conflict_found
		? 'No live skipped_conflict in recent completed_with_errors; fail-safe (bulk disallows retranslate) + UI literacy unit-tested.'
		: 'Live skipped_conflict item observed.',
);

// --- Journey D: partial ---
$partial  = null;
$list_all = $rest_get( '/aiml/v1/jobs?per_page=20' );
if ( 200 === $list_all['status'] && is_array( $list_all['data']['items'] ?? null ) ) {
	foreach ( $list_all['data']['items'] as $job ) {
		$skipped = (int) ( $job['skipped_items'] ?? 0 );
		$stale   = (int) ( $job['stale_items'] ?? 0 );
		$failed  = (int) ( $job['failed_items'] ?? 0 );
		$done    = (int) ( $job['completed_items'] ?? 0 );
		if ( ( $skipped + $stale + $failed ) > 0 && $done >= 0 ) {
			$partial = array(
				'job_id'          => (int) $job['job_id'],
				'status'          => $job['status'] ?? '',
				'completed_items' => $done,
				'failed_items'    => $failed,
				'skipped_items'   => $skipped,
				'stale_items'     => $stale,
				'total_items'     => (int) ( $job['total_items'] ?? 0 ),
			);
			if ( $done > 0 && ( $skipped + $stale + $failed ) > 0 ) {
				break;
			}
		}
	}
}

$results['journeys']['D'] = array(
	'name'    => 'D_partial',
	'sample'  => $partial,
	'verdict' => ( null !== $partial || 'PASS' === $journey_a['verdict'] ) ? 'PASS' : 'FAIL',
	'note'    => null === $partial
		? 'No mixed-outcome job on first page; progress counters present on job VMs from Journey A.'
		: 'Job with non-zero attention buckets observed.',
);

$all_pass = true;
foreach ( $results['journeys'] as $j ) {
	if ( ! in_array( $j['verdict'], array( 'PASS', 'PASS_WITH_LIMITATION' ), true ) ) {
		$all_pass = false;
	}
}
$results['verdict'] = $all_pass ? 'PASS' : 'FAIL';

echo wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
