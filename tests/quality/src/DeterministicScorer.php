<?php
/**
 * TQ.0 Class A deterministic quality scorer (H1.0).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

use AIMultilingual\Translation\AI\SegmentConstraintAnalyzer;
use AIMultilingual\Translation\Store;

/**
 * Network-free structural/terminology checks for quality fixtures.
 *
 * Does not wire into production persist path (TI.1).
 */
final class DeterministicScorer {

	public const VERSION = 'H1.0';

	/**
	 * @var SegmentConstraintAnalyzer
	 */
	private SegmentConstraintAnalyzer $analyzer;

	/**
	 * @param SegmentConstraintAnalyzer|null $analyzer Analyzer.
	 */
	public function __construct( ?SegmentConstraintAnalyzer $analyzer = null ) {
		$this->analyzer = $analyzer ?? new SegmentConstraintAnalyzer();
	}

	/**
	 * Scores one translation against a corpus case.
	 *
	 * @param array<string,mixed>      $case       Corpus case.
	 * @param string                   $translated Hypothesis translation.
	 * @param array<string,mixed>|null $glossary   Optional glossary fixture.
	 * @return array{findings: list<array<string,mixed>>, critical_count: int, error_count: int, warning_count: int, pass: bool}
	 */
	public function score_case( array $case, string $translated, ?array $glossary = null ): array {
		$source   = (string) ( $case['source_text'] ?? '' );
		$format   = (string) ( $case['text_format'] ?? Store::FORMAT_PLAIN );
		$class    = (string) ( $case['case_class'] ?? 'free' );
		$inv      = is_array( $case['expected_invariants'] ?? null ) ? $case['expected_invariants'] : array();
		$findings = array();

		if ( '' !== trim( $source ) && '' === trim( $translated ) ) {
			$findings[] = $this->finding( 'empty_translation', 'critical', 'Empty translation for non-empty source.' );
		}

		if ( in_array( $class, array( 'free', 'terminology' ), true ) && '' !== trim( $source ) && $source === $translated ) {
			$findings[] = $this->finding( 'unexpected_source_equals_target', 'warning', 'Translation equals source unexpectedly.' );
		}

		$analysis     = $this->analyzer->analyze( $source, $format );
		$placeholders = isset( $inv['placeholders'] ) && is_array( $inv['placeholders'] )
			? array_map( 'strval', $inv['placeholders'] )
			: $analysis['placeholders'];
		foreach ( $placeholders as $token ) {
			if ( ! str_contains( $translated, (string) $token ) ) {
				$findings[] = $this->finding( 'placeholder_loss', 'critical', 'Missing placeholder.', array( 'token' => $token ) );
			}
		}
		// Added placeholders that look like {word} and were not in source.
		if ( preg_match_all( '/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $translated, $m ) ) {
			foreach ( $m[0] as $tok ) {
				if ( ! str_contains( $source, $tok ) && ! in_array( $tok, $placeholders, true ) ) {
					$findings[] = $this->finding( 'placeholder_addition', 'critical', 'Unexpected placeholder added.', array( 'token' => $tok ) );
				}
			}
		}

		if ( Store::FORMAT_HTML === $format ) {
			$src_tags = $this->analyzer->extract_html_tags( $source );
			$tgt_tags = $this->analyzer->extract_html_tags( $translated );
			$missing  = array_values( array_diff( $src_tags, $tgt_tags ) );
			if ( array() !== $missing ) {
				$findings[] = $this->finding( 'html_tag_loss', 'critical', 'HTML tags missing from translation.', array( 'missing' => $missing ) );
			}
			if ( preg_match( '/<(script|iframe|object|embed)\b/i', $translated ) && ! preg_match( '/<(script|iframe|object|embed)\b/i', $source ) ) {
				$findings[] = $this->finding( 'forbidden_markup', 'critical', 'Dangerous markup invented in translation.' );
			}
			if ( false !== strpos( $translated, '<' ) && ! preg_match( '/<[^>]+>/', $translated ) && preg_match( '/<[^>]+>/', $source ) ) {
				$findings[] = $this->finding( 'broken_html', 'error', 'Likely broken HTML markup.' );
			}
		}

		$numbers = isset( $inv['numbers'] ) && is_array( $inv['numbers'] )
			? array_map( 'strval', $inv['numbers'] )
			: $analysis['numbers'];
		foreach ( $numbers as $number ) {
			if ( ! str_contains( $translated, (string) $number ) ) {
				$findings[] = $this->finding( 'number_corruption', 'error', 'Required number/unit token missing.', array( 'number' => $number ) );
			}
		}

		foreach ( (array) ( $inv['urls'] ?? array() ) as $url ) {
			$url = (string) $url;
			if ( '' !== $url && ! str_contains( $translated, $url ) ) {
				$findings[] = $this->finding( 'url_corruption', 'critical', 'Protected URL missing.', array( 'url' => $url ) );
			}
		}
		foreach ( (array) ( $inv['skus'] ?? array() ) as $sku ) {
			$sku = (string) $sku;
			if ( '' !== $sku && ! str_contains( $translated, $sku ) ) {
				$findings[] = $this->finding( 'sku_corruption', 'critical', 'Protected SKU/token missing.', array( 'sku' => $sku ) );
			}
		}

		if ( preg_match( '/&(?:amp|lt|gt|quot|#\d+);/', $source ) && preg_match( '/&(?:amp|lt|gt|quot|#\d+);/', $translated ) === 0 && str_contains( $source, '&' ) ) {
			// Soft entity check when source had entities.
			if ( str_contains( $source, '&amp;' ) && ! str_contains( $translated, '&' ) && ! str_contains( $translated, '&amp;' ) ) {
				$findings[] = $this->finding( 'entity_damage', 'error', 'HTML entities appear damaged.' );
			}
		}

		$src_trim = $source;
		$tgt_trim = $translated;
		if ( ( '' !== $src_trim && preg_match( '/^\s/', $tgt_trim ) ) || ( '' !== $src_trim && preg_match( '/\s$/', $tgt_trim ) && ! preg_match( '/\s$/', $src_trim ) ) ) {
			$findings[] = $this->finding( 'whitespace_anomaly', 'warning', 'Suspicious leading/trailing whitespace.' );
		}

		$slen = mb_strlen( trim( $source ) );
		$tlen = mb_strlen( trim( $translated ) );
		if ( $slen > 20 && $tlen > 0 ) {
			$ratio = $tlen / $slen;
			if ( $ratio < 0.35 || $ratio > 2.8 ) {
				$findings[] = $this->finding( 'length_ratio', 'warning', 'Gross length ratio anomaly.', array( 'ratio' => $ratio ) );
			}
		}

		// Mechanical glossary: preferred source terms listed must appear as preferred targets when source contains them.
		if ( null !== $glossary && isset( $inv['glossary_term_ids'] ) && is_array( $inv['glossary_term_ids'] ) ) {
			$terms = array();
			foreach ( (array) ( $glossary['terms'] ?? array() ) as $term ) {
				if ( is_array( $term ) && isset( $term['id'] ) ) {
					$terms[ (string) $term['id'] ] = $term;
				}
			}
			foreach ( $inv['glossary_term_ids'] as $tid ) {
				$tid = (string) $tid;
				if ( ! isset( $terms[ $tid ] ) ) {
					continue;
				}
				$src_term = (string) ( $terms[ $tid ]['source'] ?? '' );
				$tgt_term = (string) ( $terms[ $tid ]['target'] ?? '' );
				if ( '' === $src_term || '' === $tgt_term ) {
					continue;
				}
				if ( $this->contains_term( $source, $src_term ) && ! $this->contains_term( $translated, $tgt_term ) ) {
					$findings[] = $this->finding(
						'glossary_compliance',
						'error',
						'Preferred glossary target missing.',
						array(
							'term_id' => $tid,
							'source'  => $src_term,
							'target'  => $tgt_term,
						)
					);
				}
			}
		}

		// Unicode replacement character damage.
		if ( str_contains( $translated, "\u{FFFD}" ) && ! str_contains( $source, "\u{FFFD}" ) ) {
			$findings[] = $this->finding( 'unicode_damage', 'error', 'Unicode replacement character introduced.' );
		}

		$critical = 0;
		$error    = 0;
		$warning  = 0;
		foreach ( $findings as $f ) {
			if ( 'critical' === $f['severity'] ) {
				++$critical;
			} elseif ( 'error' === $f['severity'] ) {
				++$error;
			} else {
				++$warning;
			}
		}

		return array(
			'findings'       => $findings,
			'critical_count' => $critical,
			'error_count'    => $error,
			'warning_count'  => $warning,
			'pass'           => 0 === $critical,
		);
	}

	/**
	 * @param string              $code     Finding code.
	 * @param string              $severity Severity level.
	 * @param string              $message  Human message.
	 * @param array<string,mixed> $data     Extra data.
	 * @return array<string,mixed>
	 */
	private function finding( string $code, string $severity, string $message, array $data = array() ): array {
		return array(
			'code'     => $code,
			'severity' => $severity,
			'message'  => $message,
			'data'     => $data,
		);
	}

	/**
	 * Case-insensitive containment for glossary terms.
	 */
	private function contains_term( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return false;
		}
		return false !== mb_stripos( $haystack, $needle );
	}
}
