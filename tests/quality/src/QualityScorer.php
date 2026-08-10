<?php
/**
 * Deterministic scoring orchestrator for evidence packs.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

/**
 * Scores generation fixtures against corpus using DeterministicScorer.
 */
final class QualityScorer {

	/**
	 * @var DeterministicScorer
	 */
	private DeterministicScorer $scorer;

	/**
	 * @var CorpusLoader
	 */
	private CorpusLoader $loader;

	/**
	 * @param DeterministicScorer|null $scorer Scorer instance.
	 * @param CorpusLoader|null        $loader Corpus loader.
	 */
	public function __construct( ?DeterministicScorer $scorer = null, ?CorpusLoader $loader = null ) {
		$this->scorer = $scorer ?? new DeterministicScorer();
		$this->loader = $loader ?? new CorpusLoader();
	}

	/**
	 * Scores an evidence pack's generations against its corpus version.
	 *
	 * @param EvidencePack $pack Evidence pack with manifest + generations.
	 * @return array<string,mixed> Scores payload suitable for scores.H1.0.json.
	 */
	public function score_pack( EvidencePack $pack ): array {
		$manifest    = $pack->load_manifest();
		$generations = $pack->load_generations();
		$version     = (string) ( $manifest['corpus_version'] ?? 'C1.0' );
		$corpus      = $this->loader->load( $version );
		$glossary    = $corpus['glossary'];
		$cases       = $corpus['cases'];

		$case_results = array();
		$pass_count   = 0;
		$critical     = 0;

		foreach ( $generations as $row ) {
			$case_id = (string) ( $row['case_id'] ?? '' );
			if ( '' === $case_id || ! isset( $cases[ $case_id ] ) ) {
				continue;
			}
			$translated               = (string) ( $row['translated_text'] ?? '' );
			$result                   = $this->scorer->score_case( $cases[ $case_id ], $translated, $glossary );
			$case_results[ $case_id ] = $result;
			if ( $result['pass'] ) {
				++$pass_count;
			}
			if ( $result['critical_count'] > 0 ) {
				++$critical;
			}
		}

		return array(
			'scorer_version' => DeterministicScorer::VERSION,
			'corpus_version' => $version,
			'case_results'   => $case_results,
			'summary'        => array(
				'total_cases'       => count( $case_results ),
				'pass_count'        => $pass_count,
				'critical_failures' => $critical,
				'scored_at'         => gmdate( 'c' ),
			),
		);
	}
}
