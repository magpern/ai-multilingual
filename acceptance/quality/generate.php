<?php
/**
 * Manual TQ.0 baseline generation (wp eval-file).
 *
 * Persist-path parity with TranslationService::translate_segment.
 * Never prints secrets, prompts, or Authorization headers. No Store writes.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/ai-multilingual/acceptance/quality/generate.php
 * Optional first positional arg: output pack directory.
 *
 * @package AIMultilingual
 */

use AIMultilingual\Quality\CorpusLoader;
use AIMultilingual\Quality\DeterministicScorer;
use AIMultilingual\Quality\EvidencePack;
use AIMultilingual\Quality\GenerationRunner;
use AIMultilingual\Quality\QualityScorer;
use AIMultilingual\Quality\ReportWriter;
use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\CredentialVault;
use AIMultilingual\Translation\AI\Providers\OpenAIProvider;
use AIMultilingual\Translation\AI\PromptProfileRegistry;

defined( 'ABSPATH' ) || exit;

$plugin_root = dirname( __DIR__, 2 );

// Quality harness is autoload-dev only — register for acceptance runs.
$quality_src = $plugin_root . '/tests/quality/src';
spl_autoload_register(
	static function ( string $class ) use ( $quality_src ): void {
		$prefix = 'AIMultilingual\\Quality\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$rel  = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file = $quality_src . '/' . $rel . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

$redact = static function ( $text ) {
	$text = (string) $text;
	$text = preg_match( '/sk-[A-Za-z0-9_\-]{8,}/', $text ) ? preg_replace( '/sk-[A-Za-z0-9_\-]{8,}/', '[REDACTED]', $text ) : $text;
	$text = preg_replace( '/Bearer\s+\S+/i', 'Bearer [REDACTED]', (string) $text );
	return $text;
};

$settings = get_option( Settings::OPTION, array() );
$settings = is_array( $settings ) ? $settings : array();
$provider = (string) ( $settings['ai_provider'] ?? '' );
$model    = (string) ( $settings['ai_model'] ?? '' );
$enc      = (string) ( $settings['ai_api_key_encrypted'] ?? '' );
$enabled  = ! empty( $settings['ai_enabled'] );

echo sprintf(
	"INFO\tprereq\tenabled=%d provider=%s model=%s key_present=%d\n",
	$enabled ? 1 : 0,
	$provider,
	$model,
	'' !== $enc ? 1 : 0
);

if ( ! $enabled || 'openai' !== $provider || '' === $model || '' === $enc ) {
	echo "STOP\tprerequisites incomplete (ai_enabled/openai/model/key)\n";
	exit( 2 );
}

$default_out = $plugin_root . '/tests/quality/baselines/_staging-ti2';
$out_dir     = $default_out;
if ( isset( $args ) && is_array( $args ) ) {
	foreach ( $args as $arg ) {
		if ( is_string( $arg ) && '' !== $arg && '--' !== $arg ) {
			$out_dir = $arg;
			break;
		}
	}
}
if ( ! is_dir( $out_dir ) && ! @mkdir( $out_dir, 0777, true ) && ! is_dir( $out_dir ) ) {
	$out_dir = rtrim( sys_get_temp_dir(), '/' ) . '/aiml-tq0-staging-v1.1.0';
	if ( ! is_dir( $out_dir ) && ! mkdir( $out_dir, 0777, true ) && ! is_dir( $out_dir ) ) {
		echo "STOP\tcannot create output directory\n";
		exit( 2 );
	}
	echo "WARN\tfallback_output\t" . $out_dir . "\n";
}
if ( ! is_writable( $out_dir ) ) {
	$out_dir = rtrim( sys_get_temp_dir(), '/' ) . '/aiml-tq0-staging-v1.1.0';
	if ( ! is_dir( $out_dir ) ) {
		mkdir( $out_dir, 0777, true );
	}
	echo "WARN\tfallback_output\t" . $out_dir . "\n";
}

$subject_sha = '';
$git_head    = @shell_exec( 'git -C ' . escapeshellarg( $plugin_root ) . ' rev-parse HEAD 2>/dev/null' );
if ( is_string( $git_head ) ) {
	$subject_sha = trim( $git_head );
}

$http_calls = 0;
add_filter(
	'http_response',
	static function ( $response, $req_args, $url ) use ( &$http_calls ) {
		if ( is_string( $url ) && false !== strpos( $url, 'api.openai.com' ) ) {
			++$http_calls;
		}
		return $response;
	},
	10,
	3
);

$vault  = new CredentialVault();
$key    = $vault->decrypt( $enc );
$openai = new OpenAIProvider( is_string( $key ) ? $key : '', $model );
$loader = new CorpusLoader( $plugin_root . '/tests/quality' );
$corpus = $loader->load( 'C1.0' );
$runner = new GenerationRunner();

echo "INFO\tgenerate_start\tcases=" . count( $corpus['cases'] ) . "\n";

$result = $runner->run( $corpus, $openai );

if ( array() !== $result['errors'] ) {
	foreach ( $result['errors'] as $err ) {
		echo 'FAIL\t' . $redact( $err ) . "\n";
	}
	exit( 1 );
}

$accounting                   = $result['accounting'];
$accounting['http_requests']  = $http_calls > 0 ? $http_calls : (int) ( $accounting['http_requests'] ?? 0 );
$token_in                     = 0;
$token_out                    = 0;
foreach ( $result['generations'] as $row ) {
	$token_in  += (int) ( $row['input_tokens'] ?? 0 );
	$token_out += (int) ( $row['output_tokens'] ?? 0 );
}

$manifest = array(
	'pack_label'                => 'staging-ti2',
	'corpus_version'            => 'C1.0',
	'methodology_version'       => 'M1.0',
	'glossary_fixture_version'  => (string) ( $corpus['glossary']['glossary_fixture_version'] ?? 'G1.0' ),
	'scorer_version'            => DeterministicScorer::VERSION,
	'source_locale'             => (string) ( $corpus['manifest']['source_locale'] ?? 'en_US' ),
	'target_locale'             => (string) ( $corpus['manifest']['target_locale'] ?? 'sv_SE' ),
	'subject_kind'              => 'candidate',
	'subject_ref'               => 'ti2-bounded-translation-context',
	'subject_sha'               => $subject_sha,
	'behavior_reference_sha'    => 'd9c2336182fa2e0ae0582ead78cc0a346670c92a',
	'baseline_pack'             => 'baseline-v1.1.0',
	'equivalence_evidence'      => array(
		'method'  => 'tq0_compare_vs_baseline',
		'command' => 'php tests/quality/bin/quality-compare.php tests/quality/baselines/baseline-v1.1.0 tests/quality/baselines/_staging-ti2',
		'result'  => 'pending_post_generate',
		'note'    => 'TI.2 candidate uses bounded TranslationContext + glossary/source isolation (prompt_version 2).',
	),
	'generation_mode'           => 'live',
	'generation_label'          => 'gen-ti2-' . gmdate( 'Ymd\THis\Z' ),
	'provider_id'               => 'openai',
	'model'                     => $model,
	'prompt_profile'            => PromptProfileRegistry::TRANSLATE,
	'prompt_version'            => PromptProfileRegistry::VERSION,
	'context_schema_version'    => \AIMultilingual\Translation\AI\TranslationContext::SCHEMA_VERSION,
	'tm_observed'               => false,
	'timestamp'                 => gmdate( 'c' ),
	'token_usage'               => array(
		'input_tokens'  => $token_in,
		'output_tokens' => $token_out,
	),
	'cases_evaluated'           => (int) ( $accounting['cases'] ?? 0 ),
	'segments'                  => (int) ( $accounting['segments'] ?? 0 ),
	'batches'                   => (int) ( $accounting['batches'] ?? 0 ),
	'http_requests'             => (int) ( $accounting['http_requests'] ?? 0 ),
	'accounting'                => $accounting,
	'field_semantics_in_prompt' => true,
	'store_writes'              => false,
	'response_validator_gate'   => false,
);

$pack = new EvidencePack( $out_dir );
$pack->save( $manifest, $result['generations'], array() );

$scorer = new QualityScorer( null, $loader );
$scores = $scorer->score_pack( $pack );
$pack->save( $manifest, $result['generations'], $scores );

$report = ( new ReportWriter() )->write_score_report( $scores, $manifest );
file_put_contents( $out_dir . '/REPORT.md', $report );

echo sprintf(
	"PASS\tgenerate\tcases=%d segments=%d batches=%d http=%d tokens_in=%d tokens_out=%d path=%s\n",
	(int) ( $accounting['cases'] ?? 0 ),
	(int) ( $accounting['segments'] ?? 0 ),
	(int) ( $accounting['batches'] ?? 0 ),
	(int) ( $accounting['http_requests'] ?? 0 ),
	$token_in,
	$token_out,
	$out_dir
);
exit( 0 );
