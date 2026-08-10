<?php
/**
 * Bounded finding/reason reference for assessment output (TI.5).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Assessment;

/**
 * Machine-readable finding reference without full bodies.
 */
final class AssessmentFindingRef {

	/**
	 * Builds a bounded finding reference.
	 *
	 * @param string               $check_id Check / reason id.
	 * @param string               $lane     hard|error|warning|info|na.
	 * @param string               $message  Bounded message.
	 * @param array<string, mixed> $evidence Bounded evidence (already capped upstream).
	 */
	public function __construct(
		public readonly string $check_id,
		public readonly string $lane,
		public readonly string $message,
		public readonly array $evidence = array(),
	) {
	}

	/**
	 * Serializes the reference for ViewModels / JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'check_id' => $this->check_id,
			'lane'     => $this->lane,
			'message'  => $this->message,
			'evidence' => $this->evidence,
		);
	}
}
