<?php
/**
 * Regenerate a single TQ.0 case into an existing staging pack (manual TI.2 repair).
 *
 * Usage: wp eval-file .../acceptance/ti2/regenerate-case.php -- CASE_ID PACK_DIR
 *
 * @package AIMultilingual
 */

use AIMultilingual\Quality\CorpusLoader;
use AIMultilingual\Quality\DeterministicScorer;
use AIMultilingual\Quality\EvidencePack;
use AIMultilingual\Quality\FixtureGlossaryFragmentBuilder;
use AIMultilingual\Quality\PersistPathBatchBuilder;
use AIMultilingual\Quality\QualityScorer;
use AIMultilingual\Quality\ReportWriter;
use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\CredentialVault;
use AIMultilingual\Translation\AI\Providers\OpenAIProvider;
use AIMultilingual\Translation\AI\PromptProfileRegistry;

defined( 'ABSPATH' ) || exit;

$plugin_root = dirname( __DIR__, 2 );
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

$case_id = ( isset( $args[0] ) && is_string( $args[0] ) ) ? $args[0] : '';
$out_dir = ( isset( $args[1] ) && is_string( $args[1] ) ) ? $args[1] : ( $plugin_root . '/tests/quality/baselines/_staging-ti2' );
if ( '' === $case_id ) {
	echo "STOP\tmissing case id\n";
	exit( 2 );
}

$settings = get_option( Settings::OPTION, array() );
$settings = is_array( $settings ) ? $settings : array();
$model    = (string) ( $settings['ai_model'] ?? '' );
$enc      = (string) ( $settings['ai_api_key_encrypted'] ?? '' );
$vault    = new CredentialVault();
$key      = $vault->decrypt( $enc );
$openai   = new OpenAIProvider( is_string( $key ) ? $key : '', $model );

$loader = new CorpusLoader( $plugin_root . '/tests/quality' );
$corpus = $loader->load( 'C1.0' );
$case   = $corpus['cases'][ $case_id ] ?? null;
if ( null === $case ) {
	echo "STOP\tunknown case\n";
	exit( 2 );
}

$fragment = ( new FixtureGlossaryFragmentBuilder() )->build( (string) $case['source_text'], $corpus['glossary'] );
$batch    = ( new PersistPathBatchBuilder() )->build_for_case(
	$case,
	(string) $corpus['manifest']['source_locale'],
	(string) $corpus['manifest']['target_locale'],
	$fragment
);
$result   = $openai->translate_batch( $batch );
if ( $result instanceof WP_Error ) {
	echo 'FAIL\t' . $result->get_error_message() . "\n";
	exit( 1 );
}

$translated = '';
foreach ( $result->segments as $segment ) {
	if ( (string) ( $segment['segment_key'] ?? '' ) === $case_id ) {
		$translated = (string) ( $segment['translated_text'] ?? '' );
		break;
	}
}
$context = $batch->context;
$row     = array(
	'case_id'                => $case_id,
	'category'               => (string) ( $case['category'] ?? '' ),
	'case_class'             => (string) ( $case['case_class'] ?? '' ),
	'text_format'            => (string) ( $case['text_format'] ?? 'plain' ),
	'field_semantics'        => (string) ( $case['field_semantics'] ?? '' ),
	'source_text'            => (string) ( $case['source_text'] ?? '' ),
	'translated_text'        => $translated,
	'glossary_fragment'      => $fragment,
	'prompt_version'         => PromptProfileRegistry::VERSION,
	'context_schema_version' => null !== $context ? $context->schema_version : '',
	'context_field_semantic' => null !== $context ? $context->field_semantic : '',
	'context_truncated'      => null !== $context ? (bool) ( $context->provenance['truncated'] ?? false ) : false,
	'context_char_count'     => null !== $context ? (int) ( $context->provenance['char_count'] ?? 0 ) : 0,
	'model'                  => (string) ( $result->model ?? $model ),
	'input_tokens'           => (int) ( $result->input_tokens ?? 0 ),
	'output_tokens'          => (int) ( $result->output_tokens ?? 0 ),
);

$pack        = new EvidencePack( $out_dir );
$manifest    = $pack->load_manifest();
$generations = $pack->load_generations();
$replaced    = false;
foreach ( $generations as $i => $existing ) {
	if ( (string) ( $existing['case_id'] ?? '' ) === $case_id ) {
		$generations[ $i ] = $row;
		$replaced          = true;
		break;
	}
}
if ( ! $replaced ) {
	$generations[] = $row;
}

$manifest['prompt_version'] = PromptProfileRegistry::VERSION;
$pack->save( $manifest, $generations, array() );
$scores = ( new QualityScorer( null, $loader ) )->score_pack( $pack );
$pack->save( $manifest, $generations, $scores );
file_put_contents( $out_dir . '/REPORT.md', ( new ReportWriter() )->write_score_report( $scores, $manifest ) );

printf( "PASS\tregenerated\t%s\tout=%s\nSRC\t%s\nOUT\t%s\n", $case_id, $out_dir, $row['source_text'], $row['translated_text'] );
exit( 0 );
