<?php
/**
 * Declared AI provider capabilities (ADR-F11-007).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Capability flags for workspace adaptation without vendor branching.
 */
final class ProviderCapabilities {

	/**
	 * Builds a capability set.
	 *
	 * @param bool $translate Full translation persist path.
	 * @param bool $improve   Improve profile.
	 * @param bool $rewrite   Rewrite profile.
	 * @param bool $shorten   Shorten profile.
	 * @param bool $formal    Formal profile.
	 * @param bool $casual    Casual profile.
	 * @param bool $batch     Multi-segment batch.
	 */
	public function __construct(
		public readonly bool $translate = false,
		public readonly bool $improve = false,
		public readonly bool $rewrite = false,
		public readonly bool $shorten = false,
		public readonly bool $formal = false,
		public readonly bool $casual = false,
		public readonly bool $batch = false,
	) {
	}

	/**
	 * No capabilities (NullAIProvider / unconfigured).
	 */
	public static function none(): self {
		return new self();
	}

	/**
	 * Full suggest + translate capability set.
	 */
	public static function all(): self {
		return new self( true, true, true, true, true, true, true );
	}

	/**
	 * Whether the given prompt profile is supported.
	 *
	 * @param string $profile_id Prompt profile id.
	 */
	public function supports_profile( string $profile_id ): bool {
		switch ( $profile_id ) {
			case PromptProfileRegistry::TRANSLATE:
				return $this->translate;
			case PromptProfileRegistry::IMPROVE:
				return $this->improve;
			case PromptProfileRegistry::REWRITE:
				return $this->rewrite;
			case PromptProfileRegistry::SHORTEN:
				return $this->shorten;
			case PromptProfileRegistry::FORMAL:
				return $this->formal;
			case PromptProfileRegistry::CASUAL:
				return $this->casual;
			default:
				return false;
		}
	}

	/**
	 * Array shape for REST/admin (never includes vendor secrets).
	 *
	 * @return array<string, bool>
	 */
	public function to_array(): array {
		return array(
			'translate' => $this->translate,
			'improve'   => $this->improve,
			'rewrite'   => $this->rewrite,
			'shorten'   => $this->shorten,
			'formal'    => $this->formal,
			'casual'    => $this->casual,
			'batch'     => $this->batch,
		);
	}
}
