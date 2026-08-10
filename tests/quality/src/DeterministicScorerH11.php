<?php
/**
 * TQ.0 Class A deterministic quality scorer (H1.1) — TI.4 shared detectors + MeasurementH11Policy.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

use AIMultilingual\Translation\QA\DetectionInput;
use AIMultilingual\Translation\QA\DeterministicQA;
use AIMultilingual\Translation\QA\MeasurementH11Policy;
use AIMultilingual\Translation\Store;

/**
 * Network-free H1.1 measurement scorer with leakage applicability states.
 *
 * Does not mutate H1.0; historical packs without markers score leakage as not_applicable.
 */
final class DeterministicScorerH11 {

	public const VERSION = 'H1.1';

	/**
	 * Shared detector orchestrator.
	 *
	 * @var DeterministicQA
	 */
	private DeterministicQA $qa;

	/**
	 * H1.1 severity / applicability policy.
	 *
	 * @var MeasurementH11Policy
	 */
	private MeasurementH11Policy $policy;

	/**
	 * @param DeterministicQA|null      $qa     Optional orchestrator.
	 * @param MeasurementH11Policy|null $policy Optional policy.
	 */
	public function __construct( ?DeterministicQA $qa = null, ?MeasurementH11Policy $policy = null ) {
		$this->qa     = $qa ?? new DeterministicQA();
		$this->policy = $policy ?? new MeasurementH11Policy();
	}

	/**
	 * Scores one translation against a corpus case (H1.1).
	 *
	 * Options:
	 * - scaffolding_markers: list<string>
	 * - markers_applicable: bool (default false — historical Outcome C safety)
	 * - source_locale / target_locale: override case / empty defaults
	 *
	 * @param array<string,mixed>      $case       Corpus case.
	 * @param string                   $translated Hypothesis translation.
	 * @param array<string,mixed>|null $glossary   Optional glossary fixture.
	 * @param array<string,mixed>      $options    Scorer options.
	 * @return array{
	 *     findings: list<array<string,mixed>>,
	 *     critical_count: int,
	 *     error_count: int,
	 *     warning_count: int,
	 *     not_applicable_count: int,
	 *     pass: bool
	 * }
	 */
	public function score_case( array $case, string $translated, ?array $glossary = null, array $options = array() ): array {
		$source = (string) ( $case['source_text'] ?? '' );
		$format = (string) ( $case['text_format'] ?? Store::FORMAT_PLAIN );
		$inv    = is_array( $case['expected_invariants'] ?? null ) ? $case['expected_invariants'] : array();

		$markers_applicable = (bool) ( $options['markers_applicable'] ?? false );
		$markers            = array();
		if ( isset( $options['scaffolding_markers'] ) && is_array( $options['scaffolding_markers'] ) ) {
			foreach ( $options['scaffolding_markers'] as $marker ) {
				if ( is_string( $marker ) && '' !== $marker ) {
					$markers[] = $marker;
				}
			}
		}

		$source_locale = (string) ( $options['source_locale'] ?? $case['source_locale'] ?? '' );
		$target_locale = (string) ( $options['target_locale'] ?? $case['target_locale'] ?? '' );

		$glossary_terms = $this->glossary_terms_for_case( $case, $glossary );

		$input = new DetectionInput(
			$source,
			$translated,
			$format,
			$markers,
			$markers_applicable,
			$glossary_terms,
			$source_locale,
			$target_locale,
			$inv
		);

		$raw = $this->qa->detect( $input );

		return $this->policy->score( $raw, $input );
	}

	/**
	 * Builds preferred-term list from glossary fixture when term ids are declared.
	 *
	 * @param array<string,mixed>      $case     Corpus case.
	 * @param array<string,mixed>|null $glossary Glossary fixture.
	 * @return list<array<string,mixed>>|null
	 */
	private function glossary_terms_for_case( array $case, ?array $glossary ): ?array {
		if ( null === $glossary ) {
			return null;
		}

		$inv = is_array( $case['expected_invariants'] ?? null ) ? $case['expected_invariants'] : array();
		$ids = $inv['glossary_term_ids'] ?? null;
		if ( ! is_array( $ids ) || array() === $ids ) {
			return null;
		}

		$by_id = array();
		foreach ( (array) ( $glossary['terms'] ?? array() ) as $term ) {
			if ( is_array( $term ) && isset( $term['id'] ) ) {
				$by_id[ (string) $term['id'] ] = $term;
			}
		}

		$out = array();
		foreach ( $ids as $tid ) {
			$tid = (string) $tid;
			if ( isset( $by_id[ $tid ] ) ) {
				$out[] = $by_id[ $tid ];
			}
		}

		return array() === $out ? null : $out;
	}
}
