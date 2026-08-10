<?php
/**
 * Policy-neutral raw deterministic QA finding (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

/**
 * Detected fact only — no severity, owner, or blocking_class.
 */
final class RawFinding {

	public const DIMENSION_STRUCTURAL  = 'structural';
	public const DIMENSION_TERMINOLOGY = 'terminology';
	public const DIMENSION_LEAKAGE     = 'leakage';
	public const DIMENSION_SOFT        = 'soft';

	/**
	 * Builds a policy-neutral finding.
	 *
	 * @param string               $check_id       Stable detector id (e.g. qd5_placeholder_loss).
	 * @param string               $check_version  Detector revision.
	 * @param string               $dimension      structural|terminology|leakage|soft.
	 * @param string               $message        Explainable fact description.
	 * @param array<string, mixed> $evidence       Bounded evidence map.
	 * @param array<string, mixed> $detector_meta  Reproducibility metadata (not severity).
	 */
	public function __construct(
		public readonly string $check_id,
		public readonly string $check_version,
		public readonly string $dimension,
		public readonly string $message,
		public readonly array $evidence = array(),
		public readonly array $detector_meta = array(),
	) {
	}

	/**
	 * Array form for tests / serialization (still policy-neutral).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'check_id'      => $this->check_id,
			'check_version' => $this->check_version,
			'dimension'     => $this->dimension,
			'message'       => $this->message,
			'evidence'      => $this->evidence,
			'detector_meta' => $this->detector_meta,
		);
	}
}
