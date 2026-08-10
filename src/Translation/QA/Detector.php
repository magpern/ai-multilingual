<?php
/**
 * Shared deterministic detector contract (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

/**
 * Pure local detector — returns policy-neutral raw findings only.
 */
interface Detector {

	/**
	 * Stable detector family id.
	 */
	public function id(): string;

	/**
	 * Detector revision for reproducibility.
	 */
	public function version(): string;

	/**
	 * Evaluates source vs target.
	 *
	 * @param DetectionInput $input Detection input.
	 * @return list<RawFinding>
	 */
	public function detect( DetectionInput $input ): array;
}
