<?php
/**
 * One assessment facet (TI.5 / ADR-0019).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Assessment;

/**
 * Explainable facet with reason/finding refs.
 */
final class AssessmentFacet {

	/**
	 * Builds one facet.
	 *
	 * @param string                           $id                          Facet id.
	 * @param string                           $state                       Facet state token.
	 * @param string                           $severity_or_applicability  Severity or applicability label.
	 * @param array<int, string>               $reason_codes                Reason codes.
	 * @param array<int, AssessmentFindingRef> $finding_refs                Bounded refs.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $state,
		public readonly string $severity_or_applicability,
		public readonly array $reason_codes = array(),
		public readonly array $finding_refs = array(),
	) {
	}

	/**
	 * Serializes the facet for ViewModels / JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                        => $this->id,
			'state'                     => $this->state,
			'severity_or_applicability' => $this->severity_or_applicability,
			'reason_codes'              => $this->reason_codes,
			'finding_refs'              => array_map(
				static fn( AssessmentFindingRef $ref ): array => $ref->to_array(),
				$this->finding_refs
			),
		);
	}
}
