<?php
/**
 * Structural validation of AI provider responses (F11 §4.6 / TI.1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

use AIMultilingual\Translation\Store;

/**
 * Provider-pipeline structural checks — not QAEngine.
 *
 * Rejects suggestions/translations that drop placeholders, HTML inventory,
 * invent dangerous markup, drop absolute URLs, or become empty.
 */
final class ResponseValidator {

	public const CODE_PLACEHOLDER_MISMATCH = 'placeholder_mismatch';
	public const CODE_HTML_MISMATCH        = 'html_structure_mismatch';
	public const CODE_EMPTY_TARGET         = 'empty_target';
	public const CODE_NUMBER_MISMATCH      = 'number_mismatch';
	public const CODE_FORBIDDEN_MARKUP     = 'forbidden_markup';
	public const CODE_URL_MISMATCH         = 'url_mismatch';

	/**
	 * Persist path omits number constraints (TI.1 TS7 Outcome B — SV localization FP).
	 *
	 * Suggest path may still enforce numbers via analyzer-derived constraints.
	 */
	public const PERSIST_OMIT_NUMBER_CONSTRAINTS = true;

	/**
	 * Constraint analyzer.
	 *
	 * @var SegmentConstraintAnalyzer
	 */
	private SegmentConstraintAnalyzer $analyzer;

	/**
	 * Builds the validator.
	 *
	 * @param SegmentConstraintAnalyzer|null $analyzer Optional analyzer.
	 */
	public function __construct( ?SegmentConstraintAnalyzer $analyzer = null ) {
		$this->analyzer = $analyzer ?? new SegmentConstraintAnalyzer();
	}

	/**
	 * Returns the analyzer (tests / persist constraint builders).
	 */
	public function analyzer(): SegmentConstraintAnalyzer {
		return $this->analyzer;
	}

	/**
	 * Constraints for the persist-path gate (TI.1).
	 *
	 * Same analyzer inventory as suggest, except numbers are omitted after TS7
	 * localization proof narrowed persist BLOCK (literal substring FP on SV forms).
	 *
	 * @param string $source_text Source text.
	 * @param string $text_format Store text format.
	 * @return list<string>
	 */
	public function persist_constraints( string $source_text, string $text_format = Store::FORMAT_PLAIN ): array {
		$analysis    = $this->analyzer->analyze( $source_text, $text_format );
		$constraints = $analysis['constraints'];

		if ( self::PERSIST_OMIT_NUMBER_CONSTRAINTS ) {
			$constraints = array_values(
				array_filter(
					$constraints,
					static fn( string $id ): bool => 'numbers' !== $id
				)
			);
		}

		return $constraints;
	}

	/**
	 * Validates a provider target against source structure.
	 *
	 * @param string             $source_text Source text.
	 * @param string             $target_text Provider output.
	 * @param string             $text_format Store text format.
	 * @param array<int, string> $constraints Optional constraint ids to enforce.
	 */
	public function validate(
		string $source_text,
		string $target_text,
		string $text_format = Store::FORMAT_PLAIN,
		array $constraints = array()
	): ResponseValidationResult {
		$analysis = $this->analyzer->analyze( $source_text, $text_format );
		$required = array() !== $constraints ? $constraints : $analysis['constraints'];

		if ( in_array( 'non_empty', $required, true ) ) {
			if ( '' !== trim( $source_text ) && '' === trim( $target_text ) ) {
				return ResponseValidationResult::fail(
					self::CODE_EMPTY_TARGET,
					'Translation rejected: provider returned an empty translation.'
				);
			}
		}

		if ( in_array( 'placeholders', $required, true ) ) {
			foreach ( $analysis['placeholders'] as $token ) {
				if ( ! str_contains( $target_text, $token ) ) {
					return ResponseValidationResult::fail(
						self::CODE_PLACEHOLDER_MISMATCH,
						sprintf(
							/* translators: %s: placeholder token */
							'Translation rejected: required placeholder %s is missing.',
							$token
						),
						array( 'token' => $token )
					);
				}
			}
		}

		if ( in_array( 'html', $required, true ) && Store::FORMAT_HTML === $text_format ) {
			$target_tags = $this->analyzer->extract_html_tags( $target_text );
			$missing     = array_diff( $analysis['html_tags'], $target_tags );
			if ( array() !== $missing ) {
				return ResponseValidationResult::fail(
					self::CODE_HTML_MISMATCH,
					'Translation rejected: required HTML structure is missing.',
					array( 'missing_tags' => array_values( $missing ) )
				);
			}
		}

		$forbidden = $this->invented_dangerous_tags( $source_text, $target_text );
		if ( array() !== $forbidden ) {
			return ResponseValidationResult::fail(
				self::CODE_FORBIDDEN_MARKUP,
				'Translation rejected: dangerous markup was introduced.',
				array( 'tags' => $forbidden )
			);
		}

		if ( in_array( 'urls', $required, true ) ) {
			foreach ( $analysis['urls'] as $url ) {
				if ( ! str_contains( $target_text, $url ) ) {
					return ResponseValidationResult::fail(
						self::CODE_URL_MISMATCH,
						'Translation rejected: required URL is missing.',
						array( 'url' => $url )
					);
				}
			}
		}

		if ( in_array( 'numbers', $required, true ) ) {
			foreach ( $analysis['numbers'] as $number ) {
				if ( ! str_contains( $target_text, $number ) ) {
					return ResponseValidationResult::fail(
						self::CODE_NUMBER_MISMATCH,
						'Translation rejected: required number is missing.',
						array( 'number' => $number )
					);
				}
			}
		}

		return ResponseValidationResult::ok();
	}

	/**
	 * Dangerous tags present in target but not source.
	 *
	 * @param string $source_text Source.
	 * @param string $target_text Target.
	 * @return list<string>
	 */
	private function invented_dangerous_tags( string $source_text, string $target_text ): array {
		$source_tags = $this->analyzer->extract_html_tags( $source_text );
		$target_tags = $this->analyzer->extract_html_tags( $target_text );
		$invented    = array();

		foreach ( SegmentConstraintAnalyzer::DANGEROUS_TAGS as $tag ) {
			if ( in_array( $tag, $target_tags, true ) && ! in_array( $tag, $source_tags, true ) ) {
				$invented[] = $tag;
			}
		}

		return $invented;
	}
}
