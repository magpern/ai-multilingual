<?php
/**
 * Normalized suggestion contract (ADR-F11-005).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Suggestion;

/**
 * Common suggestion shape returned by every SuggestionProvider.
 */
final class NormalizedSuggestion {

	/**
	 * Builds one suggestion.
	 *
	 * @param string               $provider_id Source provider id.
	 * @param string               $target_text Suggested text.
	 * @param float                $confidence  0–100 score.
	 * @param int                  $rank_tier   Deterministic tier (§2.6).
	 * @param array<string, mixed> $metadata    Provider-specific display hints.
	 */
	public function __construct(
		public readonly string $provider_id,
		public readonly string $target_text,
		public readonly float $confidence,
		public readonly int $rank_tier,
		public readonly array $metadata = array()
	) {
	}

	/**
	 * REST/ViewModel array shape.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'provider_id' => $this->provider_id,
			'target_text' => $this->target_text,
			'confidence'  => $this->confidence,
			'rank_tier'   => $this->rank_tier,
			'metadata'    => $this->metadata,
		);
	}
}
