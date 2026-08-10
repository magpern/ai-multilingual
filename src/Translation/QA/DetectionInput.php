<?php
/**
 * Input bundle for shared deterministic detectors (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

use AIMultilingual\Translation\Store;

/**
 * Source/target pair plus optional marker / glossary evidence.
 */
final class DetectionInput {

	/**
	 * Builds detection input.
	 *
	 * @param string                    $source_text           Source.
	 * @param string                    $target_text           Target.
	 * @param string                    $text_format           Store format.
	 * @param array<int, string>        $scaffolding_markers   Request-scoped markers (may be empty).
	 * @param bool                      $markers_applicable    False ⇒ leakage detectors must yield N/A at policy layer.
	 * @param array<string, mixed>|null $glossary_terms        Optional preferred-term list for QD16.
	 * @param string                    $source_locale         Optional source locale.
	 * @param string                    $target_locale         Optional target locale.
	 * @param array<string, mixed>      $expected_invariants   Optional TQ.0-style invariants.
	 */
	public function __construct(
		public readonly string $source_text,
		public readonly string $target_text,
		public readonly string $text_format = Store::FORMAT_PLAIN,
		public readonly array $scaffolding_markers = array(),
		public readonly bool $markers_applicable = false,
		public readonly ?array $glossary_terms = null,
		public readonly string $source_locale = '',
		public readonly string $target_locale = '',
		public readonly array $expected_invariants = array(),
	) {
	}
}
