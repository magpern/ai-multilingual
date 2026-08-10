<?php
/**
 * Optional Class C LLM judge stub (manual, not CI).
 *
 * Records judge_version and advisory scores. Cannot clear Class A failures.
 *
 * Usage: wp eval-file acceptance/quality/llm-judge.php -- [pack-dir]
 *
 * @package AIMultilingual
 */

use AIMultilingual\Quality\DeterministicScorer;
use AIMultilingual\Quality\EvidencePack;
use AIMultilingual\Quality\QualityScorer;

defined( 'ABSPATH' ) || exit;

const JUDGE_VERSION = 'C1.0-stub';

$plugin_root = dirname( __DIR__, 2 );
$pack_dir    = isset( $args[0] ) ? (string) $args[0] : $plugin_root . '/tests/quality/baselines/_staging-v1.1.0';
$pack        = new EvidencePack( $pack_dir );

$scorer = new QualityScorer();
$scores = $scorer->score_pack( $pack );
$generations = $pack->load_generations();

$advisory = array(
	'judge_version'   => JUDGE_VERSION,
	'advisory_only'   => true,
	'cannot_clear_class_a' => true,
	'model'           => 'stub-no-network',
	'case_scores'     => array(),
);

foreach ( $generations as $row ) {
	$case_id = (string) ( $row['case_id'] ?? '' );
	$class_a = (array) ( $scores['case_results'][ $case_id ] ?? array() );
	$pass_a  = (bool) ( $class_a['pass'] ?? false );

	$advisory['case_scores'][ $case_id ] = array(
		'semantic_quality' => $pass_a ? 4 : 2,
		'fluency'          => 3,
		'notes'            => $pass_a ? 'stub advisory pass' : 'Class A failure present — advisory capped',
		'class_a_pass'     => $pass_a,
		'advisory_blocked_by_class_a' => ! $pass_a,
	);
}

$out_path = $pack->path() . '/judge.' . JUDGE_VERSION . '.json';
$encoded  = wp_json_encode( $advisory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
if ( false === $encoded ) {
	echo "FAIL\tllm-judge encode\n";
	exit( 1 );
}
file_put_contents( $out_path, $encoded . "\n" );

echo "PASS\tllm-judge stub\tversion=" . JUDGE_VERSION . " path=" . $out_path . "\n";
exit( 0 );
