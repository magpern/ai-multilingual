<?php
/**
 * TQ.0 baseline vs candidate comparer.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

/**
 * Compares two evidence packs for regression analysis.
 */
final class QualityComparer {

	/**
	 * Compares baseline and candidate packs.
	 *
	 * @param EvidencePack $baseline Baseline pack.
	 * @param EvidencePack $candidate Candidate pack.
	 * @return array<string,mixed>
	 */
	public function compare( EvidencePack $baseline, EvidencePack $candidate ): array {
		$base_manifest = $baseline->load_manifest();
		$cand_manifest = $candidate->load_manifest();

		$this->assert_compatible( $base_manifest, $cand_manifest );

		$base_scores = $baseline->load_scores();
		$cand_scores = $candidate->load_scores();

		$base_cases = (array) ( $base_scores['case_results'] ?? array() );
		$cand_cases = (array) ( $cand_scores['case_results'] ?? array() );

		$improved     = array();
		$regressed    = array();
		$unchanged    = array();
		$new_critical = array();

		$all_ids = array_unique( array_merge( array_keys( $base_cases ), array_keys( $cand_cases ) ) );
		sort( $all_ids );

		foreach ( $all_ids as $case_id ) {
			$base = (array) ( $base_cases[ $case_id ] ?? array() );
			$cand = (array) ( $cand_cases[ $case_id ] ?? array() );

			$base_crit = (int) ( $base['critical_count'] ?? 0 );
			$cand_crit = (int) ( $cand['critical_count'] ?? 0 );
			$base_pass = (bool) ( $base['pass'] ?? false );
			$cand_pass = (bool) ( $cand['pass'] ?? false );

			if ( $cand_crit > $base_crit || ( $base_pass && ! $cand_pass ) ) {
				$regressed[] = $case_id;
				if ( $cand_crit > $base_crit && 0 === $base_crit ) {
					$new_critical[] = $case_id;
				}
			} elseif ( $cand_crit < $base_crit || ( ! $base_pass && $cand_pass ) ) {
				$improved[] = $case_id;
			} else {
				$unchanged[] = $case_id;
			}
		}

		$category_rollups = $this->category_rollups( $base_cases, $cand_cases, $candidate );
		$dimension_deltas = $this->dimension_deltas( $baseline, $candidate );

		return array(
			'corpus_version'           => (string) ( $base_manifest['corpus_version'] ?? '' ),
			'methodology_version'      => (string) ( $base_manifest['methodology_version'] ?? '' ),
			'scorer_version'           => DeterministicScorer::VERSION,
			'baseline_label'           => (string) ( $base_manifest['generation_label'] ?? '' ),
			'candidate_label'          => (string) ( $cand_manifest['generation_label'] ?? '' ),
			'improved'                 => $improved,
			'regressed'                => $regressed,
			'unchanged'                => $unchanged,
			'new_critical_regressions' => $new_critical,
			'category_rollups'         => $category_rollups,
			'dimension_deltas'         => $dimension_deltas,
			'gate'                     => array(
				'zero_new_critical_regressions' => array() === $new_critical,
				'compatible_versions'           => true,
			),
		);
	}

	/**
	 * Returns true when candidate introduces no new Class A critical regressions.
	 *
	 * @param array<string,mixed> $comparison Result from compare().
	 */
	public function passes_zero_new_critical_gate( array $comparison ): bool {
		return (bool) ( $comparison['gate']['zero_new_critical_regressions'] ?? false );
	}

	/**
	 * Ensures corpus and methodology versions match between packs.
	 *
	 * @param array<string,mixed> $baseline Baseline manifest.
	 * @param array<string,mixed> $candidate Candidate manifest.
	 */
	public function assert_compatible( array $baseline, array $candidate ): void {
		foreach ( array( 'corpus_version', 'methodology_version' ) as $key ) {
			$base_val = (string) ( $baseline[ $key ] ?? '' );
			$cand_val = (string) ( $candidate[ $key ] ?? '' );
			if ( '' === $base_val || $base_val !== $cand_val ) {
				throw new \RuntimeException(
					sprintf( 'Incompatible %s: baseline=%s candidate=%s', $key, $base_val, $cand_val )
				);
			}
		}
		$base_scorer = (string) ( $baseline['scorer_version'] ?? DeterministicScorer::VERSION );
		$cand_scorer = (string) ( $candidate['scorer_version'] ?? DeterministicScorer::VERSION );
		if ( $base_scorer !== $cand_scorer ) {
			throw new \RuntimeException(
				sprintf( 'Incompatible scorer_version: baseline=%s candidate=%s', $base_scorer, $cand_scorer )
			);
		}
	}

	/**
	 * Computes mean human dimension deltas when B1.0 reviews exist on both packs.
	 *
	 * @param EvidencePack $baseline  Baseline pack.
	 * @param EvidencePack $candidate Candidate pack.
	 * @return array<string,array{baseline_mean: float|null, candidate_mean: float|null, delta: float|null}>
	 */
	private function dimension_deltas( EvidencePack $baseline, EvidencePack $candidate ): array {
		$base_human = $baseline->load_human( 'B1.0' );
		$cand_human = $candidate->load_human( 'B1.0' );
		if ( null === $base_human || null === $cand_human ) {
			return array();
		}

		$base_reviews = (array) ( $base_human['reviews'] ?? array() );
		$cand_reviews = (array) ( $cand_human['reviews'] ?? array() );
		$dim_keys     = array();
		foreach ( array_merge( $base_reviews, $cand_reviews ) as $review ) {
			$review = (array) $review;
			foreach ( array_keys( (array) ( $review['dimensions'] ?? array() ) ) as $key ) {
				$dim_keys[ (string) $key ] = true;
			}
		}

		$out = array();
		foreach ( array_keys( $dim_keys ) as $dim ) {
			$base_vals = array();
			$cand_vals = array();
			foreach ( $base_reviews as $review ) {
				$review = (array) $review;
				$dims   = (array) ( $review['dimensions'] ?? array() );
				if ( isset( $dims[ $dim ] ) ) {
					$base_vals[] = (float) $dims[ $dim ];
				}
			}
			foreach ( $cand_reviews as $review ) {
				$review = (array) $review;
				$dims   = (array) ( $review['dimensions'] ?? array() );
				if ( isset( $dims[ $dim ] ) ) {
					$cand_vals[] = (float) $dims[ $dim ];
				}
			}
			$base_mean = array() === $base_vals ? null : array_sum( $base_vals ) / count( $base_vals );
			$cand_mean = array() === $cand_vals ? null : array_sum( $cand_vals ) / count( $cand_vals );
			$out[ $dim ] = array(
				'baseline_mean'  => null === $base_mean ? null : round( $base_mean, 3 ),
				'candidate_mean' => null === $cand_mean ? null : round( $cand_mean, 3 ),
				'delta'          => ( null === $base_mean || null === $cand_mean ) ? null : round( $cand_mean - $base_mean, 3 ),
			);
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $base_cases Baseline case results keyed by id.
	 * @param array<string,mixed> $cand_cases Candidate case results keyed by id.
	 * @param EvidencePack        $candidate  Candidate pack (for generation categories).
	 * @return array<string,array{improved: int, regressed: int, unchanged: int}>
	 */
	private function category_rollups( array $base_cases, array $cand_cases, EvidencePack $candidate ): array {
		$categories = array();
		try {
			$generations = $candidate->load_generations();
		} catch ( \RuntimeException $e ) {
			$generations = array();
		}
		$case_category = array();
		foreach ( $generations as $row ) {
			$id = (string) ( $row['case_id'] ?? '' );
			if ( '' !== $id ) {
				$case_category[ $id ] = (string) ( $row['category'] ?? 'unknown' );
			}
		}

		$all_ids = array_unique( array_merge( array_keys( $base_cases ), array_keys( $cand_cases ) ) );
		foreach ( $all_ids as $case_id ) {
			$cat = $case_category[ $case_id ] ?? 'unknown';
			if ( ! isset( $categories[ $cat ] ) ) {
				$categories[ $cat ] = array(
					'improved'  => 0,
					'regressed' => 0,
					'unchanged' => 0,
				);
			}
			$base      = (array) ( $base_cases[ $case_id ] ?? array() );
			$cand      = (array) ( $cand_cases[ $case_id ] ?? array() );
			$base_crit = (int) ( $base['critical_count'] ?? 0 );
			$cand_crit = (int) ( $cand['critical_count'] ?? 0 );
			$base_pass = (bool) ( $base['pass'] ?? false );
			$cand_pass = (bool) ( $cand['pass'] ?? false );

			if ( $cand_crit > $base_crit || ( $base_pass && ! $cand_pass ) ) {
				++$categories[ $cat ]['regressed'];
			} elseif ( $cand_crit < $base_crit || ( ! $base_pass && $cand_pass ) ) {
				++$categories[ $cat ]['improved'];
			} else {
				++$categories[ $cat ]['unchanged'];
			}
		}

		ksort( $categories );
		return $categories;
	}
}
