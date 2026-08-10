<?php
/**
 * Admitted shared deterministic detectors in one pass (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

use AIMultilingual\Translation\AI\SegmentConstraintAnalyzer;
use AIMultilingual\Translation\Store;

/**
 * Runs structural/soft/terminology checks once per DetectionInput.
 */
final class DeterministicDetectorSuite implements Detector {

	public const VERSION = '1';

	public const ID = 'deterministic_suite';

	public const CHECK_EMPTY_TARGET          = 'qd21_empty_target';
	public const CHECK_PLACEHOLDER_LOSS      = 'qd5_placeholder_loss';
	public const CHECK_PLACEHOLDER_ADDITION  = 'qd5_placeholder_addition';
	public const CHECK_HTML_TAG_LOSS         = 'qd6_html_tag_loss';
	public const CHECK_FORBIDDEN_MARKUP      = 'qd7_forbidden_markup';
	public const CHECK_URL_LOSS              = 'qd8_url_loss';
	public const CHECK_NUMBER_CORRUPTION     = 'qd9_number_corruption';
	public const CHECK_SOURCE_EQUALS_TARGET  = 'qd2_source_equals_target';
	public const CHECK_LENGTH_RATIO          = 'qd13_length_ratio';
	public const CHECK_DUPLICATE_PARAGRAPH   = 'qd14_duplicate_paragraph';
	public const CHECK_UNICODE_DAMAGE        = 'qd12_unicode_damage';
	public const CHECK_ENTITY_DAMAGE         = 'qd12_entity_damage';
	public const CHECK_GLOSSARY_TERM_MISSING = 'qd16_glossary_term_missing';
	public const CHECK_WHITESPACE_ANOMALY    = 'qd_whitespace_anomaly';

	private const LENGTH_RATIO_MIN            = 0.35;
	private const LENGTH_RATIO_MAX            = 2.8;
	private const SOURCE_EQUALS_MIN_LEN       = 12;
	private const LENGTH_RATIO_MIN_SOURCE_LEN = 20;

	/**
	 * Constraint analyzer (shared inventory).
	 *
	 * @var SegmentConstraintAnalyzer
	 */
	private SegmentConstraintAnalyzer $analyzer;

	/**
	 * Builds the suite.
	 *
	 * @param SegmentConstraintAnalyzer|null $analyzer Optional analyzer.
	 */
	public function __construct( ?SegmentConstraintAnalyzer $analyzer = null ) {
		$this->analyzer = $analyzer ?? new SegmentConstraintAnalyzer();
	}

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return self::ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function version(): string {
		return self::VERSION;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param DetectionInput $input Detection input.
	 * @return list<RawFinding>
	 */
	public function detect( DetectionInput $input ): array {
		$source   = $input->source_text;
		$target   = $input->target_text;
		$format   = $input->text_format;
		$findings = array();

		$analysis = $this->analyzer->analyze( $source, $format );

		if ( '' !== trim( $source ) && '' === trim( $target ) ) {
			$findings[] = $this->finding(
				self::CHECK_EMPTY_TARGET,
				RawFinding::DIMENSION_STRUCTURAL,
				'Translation target is empty while source is not.'
			);
		}

		foreach ( $analysis['placeholders'] as $token ) {
			if ( ! str_contains( $target, $token ) ) {
				$findings[] = $this->finding(
					self::CHECK_PLACEHOLDER_LOSS,
					RawFinding::DIMENSION_STRUCTURAL,
					'Required placeholder is missing from the target.',
					array( 'token' => $token )
				);
			}
		}

		if ( preg_match_all( '/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $target, $added ) ) {
			foreach ( array_unique( $added[0] ) as $tok ) {
				if ( ! str_contains( $source, $tok ) && ! in_array( $tok, $analysis['placeholders'], true ) ) {
					$findings[] = $this->finding(
						self::CHECK_PLACEHOLDER_ADDITION,
						RawFinding::DIMENSION_STRUCTURAL,
						'Unexpected placeholder was added to the target.',
						array( 'token' => $tok )
					);
				}
			}
		}

		if ( Store::FORMAT_HTML === $format ) {
			$target_tags = $this->analyzer->extract_html_tags( $target );
			$missing     = array_values( array_diff( $analysis['html_tags'], $target_tags ) );
			if ( array() !== $missing ) {
				$findings[] = $this->finding(
					self::CHECK_HTML_TAG_LOSS,
					RawFinding::DIMENSION_STRUCTURAL,
					'Required HTML tags are missing from the target.',
					array( 'missing_tags' => $missing )
				);
			}
		}

		$forbidden = $this->invented_dangerous_tags( $source, $target );
		if ( array() !== $forbidden ) {
			$findings[] = $this->finding(
				self::CHECK_FORBIDDEN_MARKUP,
				RawFinding::DIMENSION_STRUCTURAL,
				'Dangerous markup was introduced in the target.',
				array( 'tags' => $forbidden )
			);
		}

		foreach ( $analysis['urls'] as $url ) {
			if ( ! str_contains( $target, $url ) ) {
				$findings[] = $this->finding(
					self::CHECK_URL_LOSS,
					RawFinding::DIMENSION_STRUCTURAL,
					'Required absolute URL is missing from the target.',
					array( 'url' => $url )
				);
			}
		}

		$missing_numbers = NumberNormalizer::missing_signatures( $source, $target );
		if ( array() !== $missing_numbers ) {
			$findings[] = $this->finding(
				self::CHECK_NUMBER_CORRUPTION,
				RawFinding::DIMENSION_STRUCTURAL,
				'Normalized number signatures from source are missing in target.',
				array( 'missing_signatures' => $missing_numbers )
			);
		}

		if ( $this->should_flag_source_equals_target( $input ) ) {
			$findings[] = $this->finding(
				self::CHECK_SOURCE_EQUALS_TARGET,
				RawFinding::DIMENSION_SOFT,
				'Target text equals source text unexpectedly.',
				array(
					'length' => mb_strlen( trim( $source ) ),
				)
			);
		}

		$slen = mb_strlen( trim( $source ) );
		$tlen = mb_strlen( trim( $target ) );
		if ( $slen > self::LENGTH_RATIO_MIN_SOURCE_LEN && $tlen > 0 ) {
			$ratio = $tlen / $slen;
			if ( $ratio < self::LENGTH_RATIO_MIN || $ratio > self::LENGTH_RATIO_MAX ) {
				$findings[] = $this->finding(
					self::CHECK_LENGTH_RATIO,
					RawFinding::DIMENSION_SOFT,
					'Gross length ratio anomaly between source and target.',
					array(
						'ratio'      => $ratio,
						'source_len' => $slen,
						'target_len' => $tlen,
					),
					array(
						'threshold_min' => self::LENGTH_RATIO_MIN,
						'threshold_max' => self::LENGTH_RATIO_MAX,
					)
				);
			}
		}

		$dup = $this->consecutive_duplicate_paragraphs( $target );
		if ( null !== $dup ) {
			$findings[] = $this->finding(
				self::CHECK_DUPLICATE_PARAGRAPH,
				RawFinding::DIMENSION_SOFT,
				'Exact consecutive duplicate paragraphs detected in target.',
				array( 'paragraph' => mb_substr( $dup, 0, 120 ) )
			);
		}

		if ( str_contains( $target, "\u{FFFD}" ) && ! str_contains( $source, "\u{FFFD}" ) ) {
			$findings[] = $this->finding(
				self::CHECK_UNICODE_DAMAGE,
				RawFinding::DIMENSION_SOFT,
				'Unicode replacement character was introduced in the target.'
			);
		}

		if ( $this->entity_damage( $source, $target ) ) {
			$findings[] = $this->finding(
				self::CHECK_ENTITY_DAMAGE,
				RawFinding::DIMENSION_SOFT,
				'HTML entities appear damaged in the target.'
			);
		}

		if ( null !== $input->glossary_terms ) {
			foreach ( $input->glossary_terms as $term ) {
				if ( ! is_array( $term ) ) {
					continue;
				}
				$src_term = isset( $term['source'] ) ? (string) $term['source'] : '';
				$tgt_term = isset( $term['target'] ) ? (string) $term['target'] : '';
				if ( '' === $src_term || '' === $tgt_term ) {
					continue;
				}
				if ( $this->contains_term( $source, $src_term ) && ! $this->contains_term( $target, $tgt_term ) ) {
					$findings[] = $this->finding(
						self::CHECK_GLOSSARY_TERM_MISSING,
						RawFinding::DIMENSION_TERMINOLOGY,
						'Preferred glossary target term is missing.',
						array(
							'source' => $src_term,
							'target' => $tgt_term,
						)
					);
				}
			}
		}

		if ( $this->whitespace_anomaly( $source, $target ) ) {
			$findings[] = $this->finding(
				self::CHECK_WHITESPACE_ANOMALY,
				RawFinding::DIMENSION_SOFT,
				'Suspicious leading or trailing whitespace on the target.'
			);
		}

		return $findings;
	}

	/**
	 * Builds a versioned raw finding.
	 *
	 * @param string               $check_id  Stable check id.
	 * @param string               $dimension Dimension.
	 * @param string               $message   Message.
	 * @param array<string, mixed> $evidence  Evidence.
	 * @param array<string, mixed> $meta      Detector meta.
	 */
	private function finding(
		string $check_id,
		string $dimension,
		string $message,
		array $evidence = array(),
		array $meta = array()
	): RawFinding {
		return new RawFinding(
			$check_id,
			self::VERSION,
			$dimension,
			$message,
			$evidence,
			$meta
		);
	}

	/**
	 * Dangerous tags present in target but not source.
	 *
	 * @param string $source Source.
	 * @param string $target Target.
	 * @return array<int, string>
	 */
	private function invented_dangerous_tags( string $source, string $target ): array {
		$source_tags = $this->analyzer->extract_html_tags( $source );
		$target_tags = $this->analyzer->extract_html_tags( $target );
		$invented    = array();

		foreach ( SegmentConstraintAnalyzer::DANGEROUS_TAGS as $tag ) {
			if ( in_array( $tag, $target_tags, true ) && ! in_array( $tag, $source_tags, true ) ) {
				$invented[] = $tag;
			}
		}

		return $invented;
	}

	/**
	 * QD2: source equals target under locale / SKU guards.
	 *
	 * @param DetectionInput $input Detection input.
	 */
	private function should_flag_source_equals_target( DetectionInput $input ): bool {
		$source = trim( $input->source_text );
		$target = trim( $input->target_text );
		if ( '' === $source || $source !== $target ) {
			return false;
		}
		if ( mb_strlen( $source ) < self::SOURCE_EQUALS_MIN_LEN ) {
			return false;
		}

		$src_loc = trim( $input->source_locale );
		$tgt_loc = trim( $input->target_locale );

		if ( '' !== $src_loc && '' !== $tgt_loc ) {
			return 0 !== strcasecmp( $src_loc, $tgt_loc );
		}

		// Locales empty: skip SKU-like identifiers that are intentionally identical.
		return ! $this->is_sku_like( $source );
	}

	/**
	 * Compact alphanumeric identifier (not free prose).
	 *
	 * @param string $text Candidate text.
	 */
	private function is_sku_like( string $text ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._\-]{0,63}$/', $text );
	}

	/**
	 * First exact consecutive duplicate paragraph, or null.
	 *
	 * @param string $target Target text.
	 */
	private function consecutive_duplicate_paragraphs( string $target ): ?string {
		$parts = preg_split( "/\n\n+/", $target );
		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return null;
		}

		$prev = null;
		foreach ( $parts as $part ) {
			$cur = trim( (string) $part );
			if ( '' === $cur ) {
				$prev = $cur;
				continue;
			}
			if ( null !== $prev && $prev === $cur ) {
				return $cur;
			}
			$prev = $cur;
		}

		return null;
	}

	/**
	 * Soft entity damage heuristic (H1.0-aligned).
	 *
	 * @param string $source Source text.
	 * @param string $target Target text.
	 */
	private function entity_damage( string $source, string $target ): bool {
		if ( ! preg_match( '/&(?:amp|lt|gt|quot|#\d+);/', $source ) ) {
			return false;
		}
		if ( ! str_contains( $source, '&' ) ) {
			return false;
		}
		if ( str_contains( $source, '&amp;' ) && ! str_contains( $target, '&' ) && ! str_contains( $target, '&amp;' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Soft leading/trailing whitespace anomaly (H1.0-aligned).
	 *
	 * @param string $source Source text.
	 * @param string $target Target text.
	 */
	private function whitespace_anomaly( string $source, string $target ): bool {
		if ( '' === $source || '' === $target ) {
			return false;
		}
		if ( preg_match( '/^\s/', $target ) ) {
			return true;
		}
		if ( preg_match( '/\s$/', $target ) && ! preg_match( '/\s$/', $source ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Case-insensitive term containment.
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 */
	private function contains_term( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return false;
		}

		return false !== mb_stripos( $haystack, $needle );
	}
}
