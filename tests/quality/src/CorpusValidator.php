<?php
/**
 * Corpus structure and hygiene validator.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

/**
 * Validates TQ.0 corpus integrity, IDs, fields, and basic secrets/PII heuristics.
 */
final class CorpusValidator {

	/**
	 * Expected approximate category counts from TQ.0 plan.
	 *
	 * @var array<string,int>
	 */
	private const CATEGORY_TARGETS = array(
		'woo_product_title'      => 6,
		'woo_short_description'  => 6,
		'woo_long_description'   => 6,
		'scientific_terminology' => 6,
		'marketing'              => 4,
		'navigation_ui'          => 4,
		'taxonomy'               => 4,
		'seo_title'              => 4,
		'seo_description'        => 4,
		'gutenberg_plain'        => 4,
		'html_rich'              => 4,
		'placeholder_token'      => 4,
		'protected'              => 4,
	);

	private const REQUIRED_CASE_FIELDS = array(
		'id',
		'category',
		'case_class',
		'text_format',
		'field_semantics',
		'source_text',
		'expected_invariants',
		'difficulty',
	);

	/**
	 * @var CorpusLoader
	 */
	private CorpusLoader $loader;

	/**
	 * @param CorpusLoader|null $loader Corpus loader.
	 */
	public function __construct( ?CorpusLoader $loader = null ) {
		$this->loader = $loader ?? new CorpusLoader();
	}

	/**
	 * Validates a corpus version.
	 *
	 * @param string $version Corpus version (e.g. C1.0).
	 * @return array{ok: bool, errors: list<string>, warnings: list<string>, category_counts: array<string,int>}
	 */
	public function validate( string $version = 'C1.0' ): array {
		$errors   = array();
		$warnings = array();

		try {
			$corpus = $this->loader->load( $version );
		} catch ( \Throwable $e ) {
			return array(
				'ok'              => false,
				'errors'          => array( $e->getMessage() ),
				'warnings'        => array(),
				'category_counts' => array(),
			);
		}

		$manifest = $corpus['manifest'];
		$cases    = $corpus['cases'];
		$glossary = $corpus['glossary'];

		foreach ( array( 'corpus_version', 'methodology_version', 'source_locale', 'target_locale', 'case_count', 'case_ids' ) as $field ) {
			if ( ! isset( $manifest[ $field ] ) ) {
				$errors[] = 'Manifest missing field: ' . $field;
			}
		}

		if ( (string) ( $manifest['corpus_version'] ?? '' ) !== $version ) {
			$errors[] = 'Manifest corpus_version mismatch.';
		}

		$seen_ids = array();
		$counts   = array();

		foreach ( $cases as $id => $case ) {
			if ( isset( $seen_ids[ $id ] ) ) {
				$errors[] = 'Duplicate case id: ' . $id;
			}
			$seen_ids[ $id ] = true;

			foreach ( self::REQUIRED_CASE_FIELDS as $field ) {
				if ( ! array_key_exists( $field, $case ) ) {
					$errors[] = sprintf( 'Case %s missing field: %s', $id, $field );
				}
			}

			$source = (string) ( $case['source_text'] ?? '' );
			if ( '' === trim( $source ) ) {
				$errors[] = 'Case ' . $id . ' has empty source_text.';
			}

			foreach ( $this->detect_secrets( $source ) as $hit ) {
				$errors[] = sprintf( 'Case %s possible secret: %s', $id, $hit );
			}
			foreach ( $this->detect_pii( $source ) as $hit ) {
				$warnings[] = sprintf( 'Case %s possible PII: %s', $id, $hit );
			}

			$cat            = (string) ( $case['category'] ?? 'unknown' );
			$counts[ $cat ] = ( $counts[ $cat ] ?? 0 ) + 1;
		}

		$manifest_ids = array_map( 'strval', (array) ( $manifest['case_ids'] ?? array() ) );
		if ( count( $manifest_ids ) !== count( array_unique( $manifest_ids ) ) ) {
			$errors[] = 'Manifest case_ids contains duplicates.';
		}
		$missing = array_diff( $manifest_ids, array_keys( $cases ) );
		if ( array() !== $missing ) {
			$errors[] = 'Manifest lists missing case files: ' . implode( ', ', $missing );
		}
		$extra = array_diff( array_keys( $cases ), $manifest_ids );
		if ( array() !== $extra ) {
			$errors[] = 'Cases not listed in manifest: ' . implode( ', ', $extra );
		}

		if ( ! isset( $glossary['glossary_fixture_version'] ) || ! isset( $glossary['terms'] ) ) {
			$errors[] = 'Glossary missing glossary_fixture_version or terms.';
		}

		foreach ( self::CATEGORY_TARGETS as $cat => $target ) {
			$actual = $counts[ $cat ] ?? 0;
			if ( $actual !== $target ) {
				$warnings[] = sprintf(
					'Category %s count %d (expected ~%d).',
					$cat,
					$actual,
					$target
				);
			}
		}

		return array(
			'ok'              => array() === $errors,
			'errors'          => $errors,
			'warnings'        => $warnings,
			'category_counts' => $counts,
		);
	}

	/**
	 * @return list<string>
	 */
	private function detect_secrets( string $text ): array {
		$hits = array();
		if ( preg_match( '/sk-[A-Za-z0-9_\-]{8,}/', $text ) ) {
			$hits[] = 'openai_api_key_pattern';
		}
		if ( preg_match( '/password\s*=\s*\S+/i', $text ) ) {
			$hits[] = 'password_assignment';
		}
		if ( preg_match( '/Bearer\s+[A-Za-z0-9._\-]+/i', $text ) ) {
			$hits[] = 'bearer_token';
		}
		return $hits;
	}

	/**
	 * @return list<string>
	 */
	private function detect_pii( string $text ): array {
		$hits = array();
		if ( preg_match( '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text ) ) {
			$hits[] = 'email_address';
		}
		if ( preg_match( '/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/', $text ) ) {
			$hits[] = 'phone_number';
		}
		return $hits;
	}
}
