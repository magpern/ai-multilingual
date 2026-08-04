<?php
/**
 * Structural validation of AI provider responses (F11 §4.6).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

use AIMultilingual\Translation\Store;

/**
 * Provider-pipeline structural checks — not QAEngine.
 *
 * Rejects suggestions that drop placeholders, HTML inventory, or become empty.
 */
final class ResponseValidator {

	public const CODE_PLACEHOLDER_MISMATCH = 'placeholder_mismatch';
	public const CODE_HTML_MISMATCH        = 'html_structure_mismatch';
	public const CODE_EMPTY_TARGET         = 'empty_target';
	public const CODE_NUMBER_MISMATCH      = 'number_mismatch';

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
					'Provider returned an empty translation for non-empty source.'
				);
			}
		}

		if ( in_array( 'placeholders', $required, true ) ) {
			foreach ( $analysis['placeholders'] as $token ) {
				if ( ! str_contains( $target_text, $token ) ) {
					return ResponseValidationResult::fail(
						self::CODE_PLACEHOLDER_MISMATCH,
						'Provider response is missing a required placeholder.',
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
					'Provider response is missing required HTML structure.',
					array( 'missing_tags' => array_values( $missing ) )
				);
			}
		}

		if ( in_array( 'numbers', $required, true ) ) {
			foreach ( $analysis['numbers'] as $number ) {
				if ( ! str_contains( $target_text, $number ) ) {
					return ResponseValidationResult::fail(
						self::CODE_NUMBER_MISMATCH,
						'Provider response is missing a required number.',
						array( 'number' => $number )
					);
				}
			}
		}

		return ResponseValidationResult::ok();
	}
}
