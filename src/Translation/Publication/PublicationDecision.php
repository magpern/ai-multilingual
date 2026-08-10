<?php
/**
 * Versioned TI.7 publication decision (P1.0).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Publication;

/**
 * Pure policy output — no mutation, no score, no LLM confidence.
 */
final class PublicationDecision {

	public const VERSION = 'P1.0';

	/**
	 * @param string               $policy_version           Policy contract version.
	 * @param bool                 $eligible                 Whether publication may proceed.
	 * @param list<string>         $reason_codes             Stable reason codes.
	 * @param string               $mode                     Active publication mode.
	 * @param string               $assessment_version       TI.5 assessment version.
	 * @param string               $overall_category         TI.5 category.
	 * @param string               $publish_status           Current publish_status.
	 * @param string               $review_status            Current review_status.
	 * @param string               $evidence_completeness    Evidence completeness.
	 * @param string               $provenance_class         Provenance class.
	 * @param bool                 $source_public            Source visibility result.
	 * @param bool                 $is_stale                 Staleness result.
	 * @param array<string, mixed> $dimensions               Bounded guard results.
	 */
	public function __construct(
		public readonly string $policy_version,
		public readonly bool $eligible,
		public readonly array $reason_codes,
		public readonly string $mode,
		public readonly string $assessment_version,
		public readonly string $overall_category,
		public readonly string $publish_status,
		public readonly string $review_status,
		public readonly string $evidence_completeness,
		public readonly string $provenance_class,
		public readonly bool $source_public,
		public readonly bool $is_stale,
		public readonly array $dimensions = array(),
	) {
	}

	/**
	 * Machine-readable ViewModel.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'policy_version'        => $this->policy_version,
			'eligible'              => $this->eligible,
			'reason_codes'          => array_values( $this->reason_codes ),
			'mode'                  => $this->mode,
			'assessment_version'    => $this->assessment_version,
			'overall_category'      => $this->overall_category,
			'publish_status'        => $this->publish_status,
			'review_status'         => $this->review_status,
			'evidence_completeness' => $this->evidence_completeness,
			'provenance_class'      => $this->provenance_class,
			'source_public'         => $this->source_public,
			'is_stale'              => $this->is_stale,
			'dimensions'            => $this->dimensions,
		);
	}
}
