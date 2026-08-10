<?php
/**
 * H1.1 deterministic scoring orchestrator for evidence packs (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

/**
 * Scores generation fixtures against corpus using DeterministicScorerH11.
 *
 * Historical rows without marker provenance score leakage as not_applicable.
 */
final class QualityScorerH11 {

	/**
	 * @var DeterministicScorerH11
	 */
	private DeterministicScorerH11 $scorer;

	/**
	 * @var CorpusLoader
	 */
	private CorpusLoader $loader;

	/**
	 * @param DeterministicScorerH11|null $scorer Scorer instance.
	 * @param CorpusLoader|null           $loader Corpus loader.
	 */
	public function __construct( ?DeterministicScorerH11 $scorer = null, ?CorpusLoader $loader = null ) {
		$this->scorer = $scorer ?? new DeterministicScorerH11();
		$this->loader = $loader ?? new CorpusLoader();
	}

	/**
	 * Scores an evidence pack's generations against its corpus version (H1.1).
	 *
	 * @param EvidencePack $pack Evidence pack with manifest + generations.
	 * @return array<string,mixed> Scores payload suitable for scores.H1.1.json.
	 */
	public function score_pack( EvidencePack $pack ): array {
		$manifest    = $pack->load_manifest();
		$generations = $pack->load_generations();
		$version     = (string) ( $manifest['corpus_version'] ?? 'C1.0' );
		$corpus      = $this->loader->load( $version );
		$glossary    = $corpus['glossary'];
		$cases       = $corpus['cases'];

		$source_locale = (string) ( $manifest['source_locale'] ?? '' );
		$target_locale = (string) ( $manifest['target_locale'] ?? '' );

		$case_results = array();
		$pass_count   = 0;
		$critical     = 0;
		$na_total     = 0;

		foreach ( $generations as $row ) {
			$case_id = (string) ( $row['case_id'] ?? '' );
			if ( '' === $case_id || ! isset( $cases[ $case_id ] ) ) {
				continue;
			}

			$options                  = $this->options_for_row( $row, $source_locale, $target_locale );
			$translated               = (string) ( $row['translated_text'] ?? '' );
			$result                   = $this->scorer->score_case( $cases[ $case_id ], $translated, $glossary, $options );
			$case_results[ $case_id ] = $result;
			if ( $result['pass'] ) {
				++$pass_count;
			}
			if ( $result['critical_count'] > 0 ) {
				++$critical;
			}
			$na_total += (int) ( $result['not_applicable_count'] ?? 0 );
		}

		return array(
			'scorer_version' => DeterministicScorerH11::VERSION,
			'corpus_version' => $version,
			'case_results'   => $case_results,
			'summary'        => array(
				'total_cases'          => count( $case_results ),
				'pass_count'           => $pass_count,
				'critical_failures'    => $critical,
				'not_applicable_count' => $na_total,
				'scored_at'            => gmdate( 'c' ),
			),
		);
	}

	/**
	 * Builds H1.1 score_case options from a generation row.
	 *
	 * @param array<string,mixed> $row           Generation row.
	 * @param string              $source_locale Pack source locale.
	 * @param string              $target_locale Pack target locale.
	 * @return array<string,mixed>
	 */
	private function options_for_row( array $row, string $source_locale, string $target_locale ): array {
		$options = array(
			'source_locale' => $source_locale,
			'target_locale' => $target_locale,
		);

		if ( isset( $row['scaffolding_markers'] ) && is_array( $row['scaffolding_markers'] ) ) {
			$options['scaffolding_markers'] = array_values(
				array_filter(
					array_map( 'strval', $row['scaffolding_markers'] ),
					static fn( string $m ): bool => '' !== $m
				)
			);
			$options['markers_applicable']  = true;
			return $options;
		}

		if ( array_key_exists( 'markers_applicable', $row ) ) {
			$options['markers_applicable'] = (bool) $row['markers_applicable'];
			return $options;
		}

		// Historical Outcome C: no marker provenance → leakage not_applicable.
		$options['markers_applicable'] = false;

		return $options;
	}
}
