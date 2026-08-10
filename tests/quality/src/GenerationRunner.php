<?php
/**
 * Corpus generation runner (one batch per case).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

use AIMultilingual\Translation\AI\AIProviderInterface;
use AIMultilingual\Translation\AI\ProviderResult;
use WP_Error;

/**
 * Runs persist-path-parity generation for all corpus cases without Store writes.
 */
final class GenerationRunner {

	/**
	 * @var PersistPathBatchBuilder
	 */
	private PersistPathBatchBuilder $batch_builder;

	/**
	 * @var FixtureGlossaryFragmentBuilder
	 */
	private FixtureGlossaryFragmentBuilder $glossary_builder;

	/**
	 * @param PersistPathBatchBuilder|null        $batch_builder    Batch builder.
	 * @param FixtureGlossaryFragmentBuilder|null $glossary_builder Glossary builder.
	 */
	public function __construct(
		?PersistPathBatchBuilder $batch_builder = null,
		?FixtureGlossaryFragmentBuilder $glossary_builder = null
	) {
		$this->batch_builder    = $batch_builder ?? new PersistPathBatchBuilder();
		$this->glossary_builder = $glossary_builder ?? new FixtureGlossaryFragmentBuilder();
	}

	/**
	 * Runs generation for all corpus cases.
	 *
	 * One TranslationBatch per case (OpenAI per-segment HTTP parity).
	 *
	 * @param array{manifest: array<string,mixed>, cases: array<string,array<string,mixed>>, glossary: array<string,mixed>} $corpus Loaded corpus.
	 * @param callable|AIProviderInterface                                                                                  $provider           translate_batch callable or provider.
	 * @param callable|null                                                                                                 $http_counter       Optional HTTP request counter callback.
	 * @return array{generations: list<array<string,mixed>>, accounting: array<string,int>, errors: list<string>}
	 */
	public function run( array $corpus, $provider, ?callable $http_counter = null ): array {
		$manifest      = $corpus['manifest'];
		$cases         = $corpus['cases'];
		$glossary      = $corpus['glossary'];
		$source_locale = (string) ( $manifest['source_locale'] ?? 'en_US' );
		$target_locale = (string) ( $manifest['target_locale'] ?? 'sv_SE' );

		$generations = array();
		$errors      = array();
		$batches     = 0;
		$http        = 0;

		foreach ( $cases as $case_id => $case ) {
			$fragment = $this->glossary_builder->build(
				(string) ( $case['source_text'] ?? '' ),
				$glossary
			);
			$batch    = $this->batch_builder->build_for_case(
				$case,
				$source_locale,
				$target_locale,
				$fragment
			);
			++$batches;

			$result = $this->invoke_provider( $provider, $batch );
			if ( $http_counter ) {
				$http_counter();
				++$http;
			} elseif ( is_callable( array( $provider, 'translate_batch' ) ) ) {
				// Default: one HTTP request per batch for OpenAI-style providers.
				++$http;
			}

			if ( $result instanceof WP_Error ) {
				$errors[] = sprintf(
					'%s: %s',
					$case_id,
					$result->get_error_message()
				);
				continue;
			}

			$translated    = $this->extract_translation( $result, $case_id );
			$generations[] = array(
				'case_id'           => $case_id,
				'category'          => (string) ( $case['category'] ?? '' ),
				'case_class'        => (string) ( $case['case_class'] ?? '' ),
				'text_format'       => (string) ( $case['text_format'] ?? 'plain' ),
				'field_semantics'   => (string) ( $case['field_semantics'] ?? '' ),
				'source_text'       => (string) ( $case['source_text'] ?? '' ),
				'translated_text'   => $translated,
				'glossary_fragment' => $fragment,
				'model'             => (string) ( $result->model ?? '' ),
				'input_tokens'      => (int) ( $result->input_tokens ?? 0 ),
				'output_tokens'     => (int) ( $result->output_tokens ?? 0 ),
			);
		}

		return array(
			'generations' => $generations,
			'accounting'  => array(
				'cases'         => count( $cases ),
				'segments'      => count( $cases ),
				'batches'       => $batches,
				'http_requests' => $http,
			),
			'errors'      => $errors,
		);
	}

	/**
	 * Writes generations to JSONL file.
	 *
	 * @param list<array<string,mixed>> $generations Generation rows.
	 * @param string                    $path        Output path.
	 */
	public function write_generations_jsonl( array $generations, string $path ): void {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			throw new \RuntimeException( 'Cannot create directory: ' . $dir );
		}
		$lines = array();
		foreach ( $generations as $row ) {
			$encoded = wp_json_encode( $row, JSON_UNESCAPED_UNICODE );
			if ( false === $encoded ) {
				$encoded = json_encode( $row, JSON_UNESCAPED_UNICODE );
			}
			if ( false === $encoded ) {
				throw new \RuntimeException( 'Failed to encode generation row.' );
			}
			$lines[] = $encoded;
		}
		file_put_contents( $path, implode( "\n", $lines ) . ( array() !== $lines ? "\n" : '' ) );
	}

	/**
	 * @param callable|AIProviderInterface $provider Provider or callable.
	 * @return ProviderResult|WP_Error
	 */
	private function invoke_provider( $provider, \AIMultilingual\Translation\AI\TranslationBatch $batch ) {
		if ( is_callable( $provider ) ) {
			return $provider( $batch );
		}
		if ( $provider instanceof AIProviderInterface ) {
			return $provider->translate_batch( $batch );
		}
		throw new \InvalidArgumentException( 'Provider must be callable or AIProviderInterface.' );
	}

	private function extract_translation( ProviderResult $result, string $case_id ): string {
		foreach ( $result->segments as $segment ) {
			if ( (string) ( $segment['segment_key'] ?? '' ) === $case_id ) {
				return (string) ( $segment['translated_text'] ?? '' );
			}
		}
		if ( isset( $result->segments[0]['translated_text'] ) ) {
			return (string) $result->segments[0]['translated_text'];
		}
		return '';
	}
}
